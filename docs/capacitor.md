# CraftCrawl Capacitor App

CraftCrawl uses Capacitor as a native Android/iOS shell around the hosted PHP site. The app does not run PHP or MySQL locally. It loads the website configured by `CRAFTCRAWL_MOBILE_URL`.

## Tradeoff

`server.url` lets the native app load the live site, which keeps one PHP/CSS/JS codebase. Capacitor documents this setting mainly for live reload and development, so the app must feel polished and app-like enough for store review. If the hosted site is down, the mobile app is down too.

## Local Setup

Install local tooling:

- Node.js 22 or newer
- Java/JDK 21 or newer
- Android Studio with Android SDK, Platform Tools, and an emulator or physical Android device
- macOS with Xcode for iOS simulator, physical iPhone, or TestFlight builds

Install Node dependencies:

```sh
npm install
```

Set the hosted site URL when running Capacitor commands:

```sh
export CRAFTCRAWL_MOBILE_URL="https://your-domain.com"
```

If a native project is missing, add it after dependencies are installed:

```sh
npm run cap:add:android
npm run cap:add:ios
```

For staging Android testing, use:

```sh
npm run cap:sync:staging:android
npm run android:debug:staging
npm run cap:open:android
```

For production Android testing before a store build, use:

```sh
npm run cap:sync:prod:android
npm run android:debug:prod
npm run cap:open:android
```

For iOS testing on macOS, use the production app target:

```sh
npm run cap:sync:prod:ios
npm run cap:open:ios
```

## GitHub Action Builds

`Capacitor Android Build` creates a debug APK artifact:

- Push to `develop`: staging APK pointed at `https://staging.craftcrawl.site`.
- Push to `main`: prod APK pointed at `https://app.craftcrawl.site`.
- Manual run: choose `staging` from `develop` or `prod` from `main`.

Android uses build flavors:

- `staging`: package id `com.craftcrawl.app.staging`, app label `CraftCrawl Staging`.
- `prod`: package id `com.craftcrawl.app`, app label `CraftCrawl`.

Download the artifact from the workflow run and install it on an emulator or
Android test device.

`Capacitor iOS Build` creates an unsigned iOS simulator app artifact from `main`:

- Push to `main`: prod simulator app pointed at `https://app.craftcrawl.site`.
- Manual run: run from `main`.

This validates the iOS Capacitor shell in CI, but TestFlight and physical iPhone
distribution still require Apple Developer signing.

iOS uses bundle id `com.craftcrawl.app` and app name `CraftCrawl`.

## iOS TestFlight Path

If you do not have a Mac, use the GitHub Actions and Fastlane workflow in
`docs/ios-testflight.md`.

To ship TestFlight builds:

1. Enroll in the Apple Developer Program.
2. Work from `main`.
3. Run `npm run cap:sync:prod:ios`.
4. Open Xcode with `npm run cap:open:ios`.
5. Set the signing team and bundle identifier.
6. Archive and upload to App Store Connect/TestFlight.

Keep TestFlight pointed at `https://app.craftcrawl.site`.

Sync config and assets after changing Capacitor settings:

```sh
npm run cap:sync
```

Open native projects:

```sh
npm run cap:open:android
npm run cap:open:ios
```

## Required Native Permissions

CraftCrawl check-ins use geolocation. After generating native projects, confirm:

- Android has fine/coarse location permission strings in `android/app/src/main/AndroidManifest.xml`.
- iOS has `NSLocationWhenInUseUsageDescription` in `ios/App/App/Info.plist`.

The permission copy should explain that CraftCrawl uses location to find nearby eligible check-ins.

## App Icons and Splash

Replace the generated Capacitor icon/splash placeholders before store submission. Keep the logo readable at small sizes and test both light and dark device modes.

## Store Review Notes

The app should open directly into the CraftCrawl experience, not a marketing page. Mobile navigation, geolocation prompts, login, check-ins, event interactions, friend feed, and profile pages should all work well on a real phone before submitting.
