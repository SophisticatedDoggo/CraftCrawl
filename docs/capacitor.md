# CraftCrawl Capacitor App

CraftCrawl uses Capacitor as a native Android/iOS shell around the hosted PHP site. The app does not run PHP or MySQL locally. It loads the production website configured by `CRAFTCRAWL_MOBILE_URL`.

## Tradeoff

`server.url` lets the native app load the live site, which keeps one PHP/CSS/JS codebase. Capacitor documents this setting mainly for live reload and development, so the app must feel polished and app-like enough for store review. If the hosted site is down, the mobile app is down too.

## Local Setup

Install Node dependencies:

```sh
npm install
```

Set the hosted site URL when running Capacitor commands:

```sh
export CRAFTCRAWL_MOBILE_URL="https://your-domain.com"
```

Add native projects after dependencies are installed:

```sh
npm run cap:add:android
npm run cap:add:ios
```

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
