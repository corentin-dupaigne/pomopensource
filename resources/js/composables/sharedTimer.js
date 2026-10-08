import axios from 'axios';

const POLL_INTERVAL_MS = 2000;

/**
 * Keep in step with the timer shared by everyone in a Discord Activity
 * instance (see ActivityRoom on the server).
 *
 * onState(state, { isNew, offset }) runs for every state received:
 * isNew is false when the state's version was already applied, and offset
 * is the server clock minus this device's, to place `ends_at` locally.
 */
export function useSharedTimer(instanceId, onState) {
    const url = `/activity-rooms/${encodeURIComponent(instanceId)}`;
    let version = -1;
    let pollTimer = null;
    let polling = false;

    const apply = (state) => {
        // Responses can arrive out of order: never step back.
        if (state.version < version) return;
        const isNew = state.version > version;
        version = state.version;
        onState(state, { isNew, offset: state.server_time - Date.now() });
    };

    const poll = async () => {
        if (polling) return;
        polling = true;
        try {
            const { data } = await axios.get(url);
            apply(data);
        } catch (error) {
            console.warn('Could not read the shared timer.', error);
        } finally {
            polling = false;
        }
    };

    const send = async (action, payload = {}) => {
        try {
            const { data } = await axios.post(url, { action, ...payload });
            apply(data);
        } catch (error) {
            console.warn(`Could not ${action} the shared timer.`, error);
            poll();
        }
    };

    // Background tabs throttle intervals: catch up as soon as it is visible.
    const onVisible = () => {
        if (document.visibilityState === 'visible') poll();
    };

    const start = () => {
        poll();
        pollTimer = setInterval(poll, POLL_INTERVAL_MS);
        document.addEventListener('visibilitychange', onVisible);
    };

    const stop = () => {
        clearInterval(pollTimer);
        document.removeEventListener('visibilitychange', onVisible);
    };

    return { start, stop, send };
}
