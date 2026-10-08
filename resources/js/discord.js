import axios from 'axios';

// Discord launches Activities with these query parameters, and the SDK
// refuses to start without them.
const params = new URLSearchParams(window.location.search);
export const isDiscordActivity = ['frame_id', 'instance_id', 'platform'].every((key) => params.has(key));

const RELOAD_FLAG = 'discordSignInReload';

let sdk = null;

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

        const { code } = await sdk.commands.authorize({
            client_id: clientId,
            response_type: 'code',
            state: '',
            prompt: 'none',
            scope: ['identify', 'rpc.activities.write'],
        });

        const { data } = await axios.post('/discord/token', { code });
        await sdk.commands.authenticate({ access_token: data.access_token });

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
    } catch (error) {
        console.warn('Discord Activity setup failed; continuing as a guest.', error);
    }
}
