// A stand-in for Discord around the Activity, for local development only.
//
// Port 3333 serves two things, told apart by the host name:
// - http://localhost:3333 is the simulated Discord client (public/). The
//   Embedded App SDK only answers a parent page from a few origins, and
//   http://localhost:3333 is the one it allows for local testing.
// - http://127.0.0.N:3333 is the app, through a proxy like Discord's
//   (<client id>.discordsays.com): another site than the parent, so the
//   session cookie is a cross-site one as in Discord, with the same
//   Content-Security-Policy idea and the /discord-cdn mapping. Each
//   simulated participant gets its own 127.0.0.N, hence its own cookies.
//
// Port 3334, reached only by the app container, fakes the two Discord API
// calls the server makes to sign a user in (DISCORD_API_URL).

import http from 'node:http';
import https from 'node:https';
import { readFile } from 'node:fs/promises';
import { extname, join, normalize } from 'node:path';

const APP = new URL(process.env.APP_UPSTREAM ?? 'http://app');
const CLIENT_ID = process.env.DISCORD_CLIENT_ID ?? 'simulated-client';
const PUBLIC_DIR = new URL('./public/', import.meta.url).pathname;

// The people who can join the simulated call. IDs are made-up snowflakes,
// so each signs in to its own account in the local database.
const USERS = [
    { id: '100000000000000001', username: 'ada', global_name: 'Ada' },
    { id: '100000000000000002', username: 'linus', global_name: 'Linus' },
    { id: '100000000000000003', username: 'grace', global_name: 'Grace' },
    { id: '100000000000000004', username: 'alan', global_name: null },
];

// Discord's proxy only lets the Activity reach its own origin: anything
// else must go through a URL mapping. Close enough to catch a new request
// to another site, which would fail in Discord too.
const CSP = [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data: blob:",
    "font-src 'self' data:",
    "media-src 'self' data: blob:",
    "connect-src 'self'",
    'frame-ancestors http://localhost:3333',
].join('; ');

const TYPES = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript', '.css': 'text/css', '.json': 'application/json' };

http.createServer((req, res) => {
    // Through an HTTP proxy, the request line holds the whole URL.
    if (/^https?:/.test(req.url)) req.url = new URL(req.url).pathname + new URL(req.url).search;
    const host = (req.headers.host ?? '').split(':')[0];
    if (host === 'localhost') return simulator(req, res);
    if (req.url.startsWith('/discord-cdn/')) {
        return forward(req, res, new URL(req.url.slice('/discord-cdn'.length), 'https://cdn.discordapp.com'), {});
    }
    return forward(req, res, new URL(req.url, APP), { 'content-security-policy': CSP });
}).listen(3333, () => console.log('Simulated Discord Activity on http://localhost:3333'));

http.createServer(fakeDiscordApi).listen(3334);

async function simulator(req, res) {
    if (req.url === '/config.json') {
        return send(res, 200, { clientId: CLIENT_ID, users: USERS });
    }
    const path = normalize(join(PUBLIC_DIR, req.url.split('?')[0] === '/' ? 'index.html' : req.url.split('?')[0]));
    if (!path.startsWith(PUBLIC_DIR)) return send(res, 404, { message: 'Not found' });
    try {
        const body = await readFile(path);
        res.writeHead(200, { 'content-type': TYPES[extname(path)] ?? 'application/octet-stream', 'cache-control': 'no-store' });
        res.end(body);
    } catch {
        send(res, 404, { message: 'Not found' });
    }
}

function forward(req, res, target, extraHeaders) {
    const client = target.protocol === 'https:' ? https : http;
    const upstream = client.request(target, {
        method: req.method,
        headers: {
            ...req.headers,
            host: target.host,
            'x-forwarded-host': req.headers.host,
            'x-forwarded-proto': 'http',
            'x-forwarded-port': '3333',
        },
    }, (answer) => {
        const headers = { ...answer.headers, ...extraHeaders };
        delete headers['x-frame-options'];
        res.writeHead(answer.statusCode, headers);
        answer.pipe(res);
    });
    upstream.on('error', (error) => {
        if (!res.headersSent) send(res, 502, { message: `Could not reach ${target.origin}: ${error.message}` });
        else res.destroy();
    });
    req.pipe(upstream);
}

// The authorize command hands out "sim.<user id>" as the code, which the
// app exchanges here for an access token of the same value.
function fakeDiscordApi(req, res) {
    if (req.method === 'POST' && req.url === '/api/oauth2/token') {
        let body = '';
        req.on('data', (chunk) => { body += chunk; });
        req.on('end', () => {
            const code = new URLSearchParams(body).get('code') ?? '';
            if (!userOf(code)) return send(res, 400, { error: 'invalid_grant' });
            send(res, 200, { access_token: code, token_type: 'Bearer', expires_in: 604800, scope: 'identify rpc.activities.write' });
        });
        return;
    }
    if (req.method === 'GET' && req.url === '/api/users/@me') {
        const user = userOf((req.headers.authorization ?? '').replace(/^Bearer /, ''));
        if (!user) return send(res, 401, { message: '401: Unauthorized', code: 0 });
        return send(res, 200, { ...user, avatar: null, discriminator: '0', public_flags: 0 });
    }
    send(res, 404, { message: '404: Not Found', code: 0 });
}

function userOf(token) {
    return USERS.find((user) => token === `sim.${user.id}`);
}

function send(res, status, json) {
    res.writeHead(status, { 'content-type': 'application/json' });
    res.end(JSON.stringify(json));
}
