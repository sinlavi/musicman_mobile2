# MusicMan Flutter Android

A native Flutter port of the MusicMan PHP single-page player. It uses the same
MusicMan API host, Bearer authentication format, search, fresh/popular, lookup,
and Telegram deep-link endpoints as `index.php`.

## Features

- Fresh and popular discovery, search filtering, artist/album/track detail views.
- Streaming preview/full audio when the API exposes an audio URL, queue controls,
  a mini player, now-playing seeking, and automatic next-track playback.
- Device-local likes, listening history, playlists, and offline audio downloads.
- Track lyrics where the API response supplies them, Telegram handoff, and dark/light UI.
- Android 7.0+ support (`minSdk 24`).

## Build

```bash
flutter pub get
flutter run
flutter build apk --release --split-per-abi
```

The GitHub Actions workflow uploads ABI-specific release APKs and, for a `v*`
tag, attaches them to a GitHub Release. Universal Android compatibility comes
from separate `armeabi-v7a`, `arm64-v8a`, and `x86_64` APKs; Android 7+ is
supported by the manifest/build configuration.

> Configure a real API token before publishing if the deployment does not
> accept the development fallback token used by the PHP app.
