import axios from 'axios';

const PENDING_SESSIONS_KEY = 'pendingSessions';

const load = () => {
    try { return JSON.parse(localStorage.getItem(PENDING_SESSIONS_KEY) || '[]'); }
    catch { return []; }
};

const store = (sessions) => {
    try {
        if (sessions.length > 0) localStorage.setItem(PENDING_SESSIONS_KEY, JSON.stringify(sessions));
        else localStorage.removeItem(PENDING_SESSIONS_KEY);
    } catch {
        // Storage unavailable: nothing more can be done here.
    }
};

const send = (session) => axios.patch('/focused-sessions/current', session);

let flushing = null;

/**
 * Send the sessions the server could not take earlier, oldest first. Each
 * names its start time, so one that did go through is not counted twice.
 * Stops at the first failure and keeps the rest for next time.
 */
export function flushPendingSessions() {
    flushing ??= (async () => {
        try {
            for (let pending = load(); pending.length > 0; pending = load()) {
                const [session] = pending;
                try {
                    await send(session);
                } catch (err) {
                    // Refused as it is, e.g. its project was deleted since:
                    // keep the time without the project, or else drop it.
                    if (err.response?.status !== 422) return;
                    if (session.project_id) {
                        try {
                            await send({ ...session, project_id: null });
                        } catch (retryErr) {
                            if (retryErr.response?.status !== 422) return;
                        }
                    }
                }
                store(load().filter((s) => s.started_at !== session.started_at));
            }
        } finally {
            flushing = null;
        }
    })();
    return flushing;
}

/**
 * End a signed-in user's focus session. When the server cannot take it
 * (offline, signed out, down), it is kept on this device and sent later:
 * it used to go with the guest sessions, which a signed-in account never
 * reads. Resolves to whether the server has it.
 */
export async function saveSession(session) {
    try {
        await send(session);
    } catch (err) {
        console.error('Error ending session', err);
        if (session.time_focused > 0) store([...load(), session]);
        return false;
    }
    flushPendingSessions();
    return true;
}
