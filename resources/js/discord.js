import axios from 'axios';
import { ref } from 'vue';

// Discord launches Activities with these query parameters, and the SDK
// refuses to start without them.
const params = new URLSearchParams(window.location.search);
export const isDiscordActivity = ['frame_id', 'instance_id', 'platform'].every((key) => params.has(key));

const RELOAD_FLAG = 'discordSignInReload';

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
 * Connect to the Discord client and sign the user in with their Discord
 * account. Outside Discord, or if anything fails, the app keeps working in
 * guest mode.
 */
export async function startDiscordActivity(clientId, { isAuthenticated }) {
    if (!isDiscordActivity || !clientId) return;

    try {
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
        await sdk.commands.authenticate({ access_token: data.access_token });
        authenticated = true;
        if (!layoutWatched) await watchLayoutMode().catch(() => {});

        // The page was rendered for a guest: reload once to load the account's
        // projects and settings. The flag stops a loop if the session cookie
        // does not stick (e.g. the browser blocks third-party cookies).
        if (data.logged_in && !isAuthenticated) {
            if (!sessionStorage.getItem(RELOAD_FLAG)) {
                sessionStorage.setItem(RELOAD_FLAG, '1');
                window.location.reload();
                return;
            }
            console.warn('Discord sign-in did not persist; continuing as a guest.');
        }
        sessionStorage.removeItem(RELOAD_FLAG);
        flushPresence();
    } catch (error) {
        console.warn('Discord Activity setup failed; continuing as a guest.', error);
    }
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
