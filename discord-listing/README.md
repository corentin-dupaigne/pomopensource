# Discord App Directory listing

Everything on the Activity's Discord pages, to fill in the [Developer Portal](https://discord.com/developers/applications/1557798944061399090). The app doesn't use any of it.

| File | Portal field | Format |
|---|---|---|
| `listing.md` | General Information (description, tags) and App Directory (descriptions, categories, links) | Text to paste |
| `app-icon.png` | General Information → App Icon | 1024×1024 |
| `activity-art/cover-art.png` | Activities → Art Assets → Cover Art | 1920×1080; the text stays inside the middle 1276 px, so it survives the 13:11 crop |
| `activity-art/embedded-background.png` | Activities → Art Assets → Embedded Background | 1920×1080, with a calm middle for Discord's UI |
| `activity-art/video-preview.mp4` | Activities → Art Assets → Video Preview | 640×360, 9.5 s, 81 KB (Discord's limits: 10 s, 1 MB) |
| `carousel/01…04-*.png` | App Directory → Media | 1920×1080, in this order |

The media field only takes links, so the carousel images are served from this repo:

```
https://raw.githubusercontent.com/corentin-dupaigne/pomopensource/master/discord-listing/carousel/01-shared-timer.png
https://raw.githubusercontent.com/corentin-dupaigne/pomopensource/master/discord-listing/carousel/02-stats.png
https://raw.githubusercontent.com/corentin-dupaigne/pomopensource/master/discord-listing/carousel/03-call-durations.png
https://raw.githubusercontent.com/corentin-dupaigne/pomopensource/master/discord-listing/carousel/04-settings.png
```

Don't move or rename them: the listing may load them from these links.

## The images

- **Backgrounds:** Lofi Cafe, the app's default background and the one about 75% of accounts never change, is used everywhere. The exception is the last slide, about personal settings, which uses Ghibli House, the background most often picked by accounts that change it.
- **Carousel:**
  1. One timer for the whole call;
  2. Your focus time, saved;
  3. Set the rhythm for everyone;
  4. Make it yours.
- **How the screenshots were taken:** from the real app in Activity mode, with a demo account and made-up participants (Alex, Mia, Leo).
- **Rights:** the backgrounds are third-party art used without a license: Lofi Cafe comes from ArtStation (`artstation.com/artwork/rAlwZE`), and Ghibli House from a Studio Ghibli film. If a takedown is ever requested, replace the images that show them.
