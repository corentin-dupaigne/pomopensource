import axios from 'axios';

/**
 * A page left open past the session lifetime (two hours without a
 * request, e.g. over lunch) holds a stale CSRF token: the server refuses
 * every write with 419 until the page is reloaded, and focus sessions
 * ended meanwhile never reach the account. Get a fresh token and send the
 * request once more. When the user was signed out, the request still
 * fails, for its caller to handle.
 */
let refreshing = null;

const refreshSession = () => {
    refreshing ??= axios.get('/session/refresh')
        .then(({ data }) => data.authenticated)
        .catch(() => false)
        .finally(() => { refreshing = null; });
    return refreshing;
};

axios.interceptors.response.use(undefined, async (error) => {
    const { config, response } = error;
    if (response?.status !== 419 || !config || config.retriedWithFreshToken) throw error;
    if (!(await refreshSession())) throw error;

    // The token is read from the refreshed cookie when the request is sent.
    const headers = { ...config.headers };
    delete headers['X-XSRF-TOKEN'];
    return axios({ ...config, headers, retriedWithFreshToken: true });
});
