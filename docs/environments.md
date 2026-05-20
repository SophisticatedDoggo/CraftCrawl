# CraftCrawl Environment

CraftCrawl deploys production from `main` to the Cloudways app at:

- `https://app.craftcrawl.site`

## GitHub Secrets

Add deploy values as repository secrets:

GitHub repo -> Settings -> Secrets and variables -> Actions -> Repository secrets.

## Web Deploy Secrets

The `Deploy Web` workflow deploys `main` to prod with `rsync` over SSH. Add these repository secrets:

- `PROD_SSH_HOST`
- `PROD_SSH_USER`
- `PROD_SSH_PORT`
- `PROD_SSH_PRIVATE_KEY`
- `PROD_DEPLOY_PATH`

Cloudways usually provides SSH/SFTP credentials and an application path. Use a dedicated SSH key when possible. The deploy excludes local secrets, GitHub workflow files, Node dependencies, generated native projects, and local upload folders.

## Mobile URL

The Capacitor app reads `CRAFTCRAWL_MOBILE_URL` when syncing native projects:

- Prod builds use `https://app.craftcrawl.site`.

The Android and iOS workflows build from `main` and point at prod.

## Social Sign-In

Set these values on the hosted app:

- `GOOGLE_SIGN_IN_CLIENT_ID`
- `GOOGLE_IOS_CLIENT_ID`
- `APPLE_SIGN_IN_CLIENT_ID`
- `CRAFTCRAWL_GOOGLE_CLIENT_IDS`
- `CRAFTCRAWL_APPLE_CLIENT_IDS`

`CRAFTCRAWL_GOOGLE_CLIENT_IDS` must include both the web client ID and the iOS
client ID so the PHP verifier accepts tokens from Safari/browser and the native
iOS auth bridge.

For iOS builds, set the Xcode build setting `GOOGLE_REVERSED_CLIENT_ID` to the
reversed iOS client ID from Google Cloud, for example
`com.googleusercontent.apps.1234567890-abc`.

For Android builds, create an Android OAuth client in Google Cloud with:

- Package name: `com.craftcrawl.app`
- SHA-1 certificate fingerprint for each signing key used by debug, direct APK,
  and Play/App Store distribution builds.

The Android native bridge requests a backend ID token for
`GOOGLE_SIGN_IN_CLIENT_ID`, so keep that web client ID in
`CRAFTCRAWL_GOOGLE_CLIENT_IDS`.

## OneSignal Push

Browser push is wired through `lib/onesignal.php`, `user/onesignal_config.php`, and `js/onesignal_push.js`. Set these environment values on the Cloudways app:

- `ONESIGNAL_APP_ID`
- `ONESIGNAL_API_KEY`
- `CRAFTCRAWL_APP_URL=https://app.craftcrawl.site`

The OneSignal service worker files must remain web-accessible at `/OneSignalSDKWorker.js` and `/OneSignalSDKUpdaterWorker.js`.

## Google Analytics

CraftCrawl uses the hosted web app for browser, Android, and iOS traffic, so production analytics should use a GA4 Web data stream, not separate Firebase app SDK streams. Set this environment value on the Cloudways app:

- `GOOGLE_ANALYTICS_MEASUREMENT_ID=G-XXXXXXXXXX`

When this value is empty or invalid, the Google tag is not rendered.

## Mobile Test Builds

Android debug builds use package id `com.craftcrawl.app` and app label `CraftCrawl`.

Download the debug APK artifact from the `Capacitor Android Build` workflow.

iOS simulator builds are produced by the `Capacitor iOS Build` workflow. Real iPhone testing or TestFlight requires an Apple Developer account and signing in Xcode.

## iOS Production Setup

For local iOS testing you need macOS with Xcode installed:

```sh
npm ci
npm run cap:sync:prod:ios
npm run cap:open:ios
```

In Xcode, select an iPhone simulator and run the `App` scheme. For physical device testing or TestFlight, enroll in the Apple Developer Program, set the team/signing settings in Xcode, and archive a build pointed at prod.

## Production Readiness Checklist

- Prod host has its own database, `.env` values, Cloudinary, hCaptcha, Mailgun, and OneSignal configuration.
- `app.craftcrawl.site` has HTTPS and OneSignal configured for that origin.
- Android debug build works against prod.
- iOS simulator or TestFlight build works against prod.
