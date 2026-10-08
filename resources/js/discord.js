import axios from 'axios';
import { reactive, ref } from 'vue';
import { useToast } from './composables/toast.js';

// Discord launches Activities with these query parameters, and the SDK
// refuses to start without them.
const params = new URLSearchParams(window.location.search);
export const isDiscordActivity = ['frame_id', 'instance_id', 'platform'].every((key) => params.has(key));

// Give up waiting for Discord after this long and continue as a guest.
const SIGN_IN_TIMEOUT_MS = 20000;

let sdk = null;

// Discord's layout for the Activity: 0 focused, 1 picture-in-picture,
// 2 grid tile in the call. The app shows only the timer in the small ones.
export const discordLayoutMode = ref(0);
const SMALL_LAYOUTS = [1, 2];
export const isSmallLayout = (mode) => SMALL_LAYOUTS.includes(mode);

// async so that even a synchronous throw becomes a rejection the caller ignores.
const watchLayoutMode = async () => sdk.subscribe('ACTIVITY_LAYOUT_MODE_UPDATE', ({ layout_mode }) => {
    discordLayoutMode.value = layout_mode;
});
let authenticated = false;
let pendingPresence = null;

/**
 * Sign-in state of the Activity:
 * - connecting: talking to Discord, the app shows a loading screen
 * - signed-in: the session cookie works, data is saved to the account
 * - guest: sign-in failed or the browser dropped the session cookie, so
 *   data only lives on this device
 * Outside Discord it stays 'off'.
 */
export const discordSession = reactive({
    status: isDiscordActivity ? 'connecting' : 'off',
    // The Discord user (id, username, global_name, avatar) once authenticated.
    user: null,
    projects: [],
    // Set when the account's session expires after signing in.
    expired: false,
});

// Discord's CDN is another origin, which the Activity's proxy only reaches
// through a URL mapping: /discord-cdn -> cdn.discordapp.com (see README).
const DISCORD_CDN = '/discord-cdn';

export function discordAvatarUrl(user, size = 64) {
    if (user.avatar) return `${DISCORD_CDN}/avatars/${user.id}/${user.avatar}.png?size=${size}`;
    // Default avatars, as Discord picks them for accounts without discriminators.
    const index = Number((BigInt(user.id) >> 22n) % 6n);
    return `${DISCORD_CDN}/embed/avatars/${index}.png`;
}

export const discordDisplayName = (user) => user.global_name || user.username;

/**
 * Connect to the Discord client and sign the user in with their Discord
 * account. Outside Discord, or if anything fails, the app keeps working in
 * guest mode.
 */
export async function startDiscordActivity(clientId) {
    if (!isDiscordActivity) return;
    if (!clientId) {
        discordSession.status = 'guest';
        return;
    }

    const timeout = setTimeout(() => {
        if (discordSession.status !== 'connecting') return;
        console.warn('Discord sign-in timed out; continuing as a guest.');
        discordSession.status = 'guest';
    }, SIGN_IN_TIMEOUT_MS);

    try {
        discordSession.status = await signIn(clientId);
    } catch (error) {
        console.warn('Discord Activity setup failed; continuing as a guest.', error);
        discordSession.status = 'guest';
    } finally {
        clearTimeout(timeout);
    }
    flushPresence();
}

async function signIn(clientId) {
    // Loaded on demand so regular visitors don't download the SDK.
    const { DiscordSDK } = await import('@discord/embedded-app-sdk');
    sdk = new DiscordSDK(clientId);
    await sdk.ready();
    // Not awaited: sign-in must not wait on it. Some clients only allow
    // it after authentication, so it is retried below if it failed.
    let layoutWatched = false;
    watchLayoutMode().then(() => { layoutWatched = true; }, () => {});

    const { code } = await sdk.commands.authorize({
        client_id: clientId,
        response_type: 'code',
        state: '',
        prompt: 'none',
        scope: ['identify', 'rpc.activities.write'],
    });

    const { data } = await axios.post('/discord/token', { code });
    const auth = await sdk.commands.authenticate({ access_token: data.access_token });
    authenticated = true;
    discordSession.user = auth.user;
    if (!layoutWatched) await watchLayoutMode().catch(() => {});

    // The response set the session cookie, and the next requests use it
    // without a page reload. Check it came back: browsers may block cookies
    // in Discord's iframe, and the user must then know nothing is synced.
    const { data: session } = await axios.get('/discord/session');
    if (!session.authenticated) {
        console.warn('Discord sign-in did not persist; continuing as a guest.');
        return 'guest';
    }
    discordSession.projects = [...data.projects, ...await importGuestProjects()];
    watchForExpiredSession();
    return 'signed-in';
}

// Same key as ProjectsAndTasks uses for guests.
const GUEST_PROJECTS_KEY = 'localProjects';

/**
 * Move projects made as a guest on this device (e.g. while sign-in failed
 * on an earlier launch) into the account, one project at a time so a
 * failure leaves the rest to import next time.
 */
async function importGuestProjects() {
    let remaining;
    try {
        remaining = JSON.parse(localStorage.getItem(GUEST_PROJECTS_KEY) || '[]');
    } catch {
        return [];
    }

    const imported = [];
    try {
        while (remaining.length > 0) {
            const guest = remaining[0];
            const { data: project } = await axios.post('/projects', { name: guest.name });
            project.tasks = [];
            for (const task of guest.tasks ?? []) {
                const { data: saved } = await axios.post(`/projects/${project.id}/tasks`, { name: task.name });
                project.tasks.push(saved);
            }
            imported.push(project);
            remaining.shift();
            localStorage.setItem(GUEST_PROJECTS_KEY, JSON.stringify(remaining));
        }
        localStorage.removeItem(GUEST_PROJECTS_KEY);
    } catch (error) {
        console.warn('Could not import every guest project; the rest stay on this device.', error);
    }

    if (imported.length > 0) {
        const count = imported.length === 1 ? '1 project' : `${imported.length} projects`;
        useToast().success(`Moved ${count} from this device to your account`);
    }
    return imported;
}

// A session that expires during a long call would otherwise drop every
// focus session without a word: say so once, and mark the app not synced.
function watchForExpiredSession() {
    axios.interceptors.response.use(undefined, (error) => {
        const status = error.response?.status;
        if ((status === 401 || status === 419) && !discordSession.expired) {
            discordSession.expired = true;
            useToast().error('Your Discord sign-in expired, so new sessions are only saved on this device. Reopen the Activity to sign in again.');
        }
        return Promise.reject(error);
    });
}

const flushPresence = () => {
    if (!authenticated || !pendingPresence) return;
    const activity = pendingPresence;
    pendingPresence = null;
    sdk.commands.setActivity({ activity }).catch((error) => {
        console.warn('Could not update Discord presence.', error);
    });
};

/**
 * Show the timer in the user's Discord status. Calls made before the SDK
 * is authenticated are kept, and the latest one is sent once it is.
 */
export function setPresence(activity) {
    if (!isDiscordActivity) return;
    pendingPresence = activity;
    flushPresence();
}

const PRESENCE_DETAILS = {
    pomodoro: 'Focusing',
    short_break: 'On a short break',
    long_break: 'On a long break',
};

// Project and task names are deliberately left out: presence is visible to
// the user's friends.
export function timerPresence({ timerType, isRunning, isPaused, secondsLeft }) {
    if (isRunning) {
        return {
            type: 0,
            details: PRESENCE_DETAILS[timerType],
            timestamps: { end: Date.now() + secondsLeft * 1000 },
        };
    }
    return {
        type: 0,
        details: isPaused ? `Paused · ${PRESENCE_DETAILS[timerType]}` : 'Ready to focus',
    };
}
