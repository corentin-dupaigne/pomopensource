# Activity simulator

Runs the app inside a stand-in for the Discord client, to try the Activity
without uploading anything to Discord:

```sh
docker compose -f compose.activity.yml up --build
```

Then open http://localhost:3333. After changing the app, rebuild it with
`docker compose -f compose.activity.yml up --build app`.

## What is simulated

- **The frame**: approximate sizes of Discord's frames (focused, small
  window, picture-in-picture, grid tile, mobile portrait and landscape).
  Drag a frame's corner, or type a size, to try others.
- **The Embedded App SDK**: the app runs the real SDK, which talks to this
  page as it would to Discord: handshake, sign-in (`authorize`,
  `authenticate`), participants, layout changes, status (`setActivity`)
  and the logs the SDK forwards.
- **Sign-in**: the server exchanges the code with a fake Discord API
  (`DISCORD_API_URL`), so each participant gets an account in the local
  database. *Refused* and *Never answers* show the guest fallback.
- **A call with friends**: up to 4 participants in the same instance,
  sharing the timer. Each is served from its own `127.0.0.N`, so it keeps
  its own cookies, as on separate devices.
- **Discord's proxy**: the app is served from another site than the page
  around it, with the cookie settings the chart uses in production
  (`SameSite=None; Secure; Partitioned`), a Content-Security-Policy that
  blocks other origins, and the `/discord-cdn` mapping.
- **The call's place**: a server voice channel or a private call, in the
  instance ID as Discord writes it.

## Limits

- Port 3333 is required: the SDK only accepts messages from a parent page
  on `http://localhost:3333` (or Discord's own origins).
- Frame sizes are approximations; Discord's change with the window.
- No touch or device pixel ratio: use the browser's device mode for those.
- Only the SDK commands the app uses answer; any other is logged as not
  simulated.
