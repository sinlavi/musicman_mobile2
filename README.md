# MusicMan Flutter Android App

A native Flutter Android music player app based on the MusicMan PHP structure, UI, and API endpoints.

## Features

- **Home & Discovery**: Fresh releases and popular tracks fetched directly from the MusicMan API (`/fresh`, `/popular`).
- **Instant Search with Autocomplete**: Live suggestions popup (`/suggest`), category filtering (Songs, Albums, Artists), and search history.
- **Audio Engine & Player**:
  - Mini Player with progress bar and playback controls.
  - Full-Screen Player with **Playing**, **Queue** (with drag-and-drop reordering), **Synced Lyrics** (with text size controls), and **Track Info** tabs.
  - Seeking, Shuffle, Repeat (Off, All, One), A-B Repeat loop, and Sleep Timer with fade out.
- **Detail Views**:
  - **Track View**: Full artwork, audio play/preview, server crawl status card, notes card, synced lyrics, and Telegram bot launcher.
  - **Album & Artist Views**: Discography listing, follow artist option, and batch download/crawl triggers.
- **Library & Local Stores**:
  - Liked Songs & Followed Artists.
  - User Playlists & Smart Playlists (*Shuffle All Liked*, *Most Played*, *Recently Added*, *Forgotten Favorites*).
  - Offline Downloads manager with direct MP3 file export.
  - Listening Stats dashboard (total minutes, play count, top tracks).
  - Recently Played listening history log.
- **Settings & Backup**:
  - Dark / Light Theme and Auto Theme (Day/Night schedule).
  - Accent Color picker.
  - Sleep Timer fade out.
  - Full Backup Export & Import (JSON).
  - Offline Storage cache clear and App Reset.

## Screenshots

An example screenshot of the running MusicMan app UI:

![MusicMan App Screenshot](doc/screenshots/app_preview.png)

## Android Compatibility & GitHub Actions Release Workflow

- **Android Version**: Built with `minSdk 24` supporting Android 7.0+ (Nougat and above).
- **Automated APK Releases**:
  - The GitHub Actions workflow (`.github/workflows/android-release.yml`) builds ABI-specific release APKs (`armeabi-v7a`, `arm64-v8a`, `x86_64`) on every push to tags matching `v*` or via `workflow_dispatch`.
  - Artifacts are uploaded and automatically published to GitHub Releases for `v*` tags.

## Development & Build

```bash
# Get dependencies
flutter pub get

# Run unit tests
flutter test

# Build release split APKs for Android
flutter build apk --release --split-per-abi
```
