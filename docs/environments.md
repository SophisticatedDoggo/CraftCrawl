# CraftCrawl Environments

CraftCrawl uses two long-lived branches and two Cloudways apps:

- `develop` deploys to the `staging` Cloudways app at `https://staging.craftcrawl.site`.
- `main` deploys to the `prod` Cloudways app at `https://app.craftcrawl.site`.

Use staging for web QA, Android emulator/device testing, and iOS simulator or
TestFlight testing. Use prod only for the live website and store builds.

## GitHub Secrets

Add the deploy values as repository secrets:

GitHub repo -> Settings -> Secrets and variables -> Actions -> Repository secrets.

## Web Deploy Secrets

The `Deploy Web` workflow deploys `develop` to staging and `main` to prod with
`rsync` over SSH. Add these repository secrets:

Staging:

- `STAGING_SSH_HOST`
- `STAGING_SSH_USER`
- `STAGING_SSH_PORT`
- `STAGING_SSH_PRIVATE_KEY`
- `STAGING_DEPLOY_PATH`

Prod:

- `PROD_SSH_HOST`
- `PROD_SSH_USER`
- `PROD_SSH_PORT`
- `PROD_SSH_PRIVATE_KEY`
- `PROD_DEPLOY_PATH`

Cloudways usually provides SSH/SFTP credentials and an application path. Use a
dedicated SSH key when possible. The deploy excludes local secrets, GitHub
workflow files, Node dependencies, generated native projects, and local upload
folders.

## Mobile URLs

The Capacitor app reads `CRAFTCRAWL_MOBILE_URL` when syncing native projects:

- Staging builds use `https://staging.craftcrawl.site`.
- Prod builds use `https://app.craftcrawl.site`.

The Android and iOS workflows set this automatically from the branch or manual
workflow target.

## Mobile Test Builds

Android staging builds are installable beside prod builds:

- Staging package id: `com.craftcrawl.app.staging`.
- Prod package id: `com.craftcrawl.app`.

Download the debug APK artifact from the `Capacitor Android Build` workflow.
Use `develop`/`staging` for test devices and `main`/`prod` for release
candidate checks.

iOS simulator builds are produced by the `Capacitor iOS Build` workflow. Real
iPhone testing or TestFlight requires an Apple Developer account and signing in
Xcode.

## iOS Staging Setup

For local iOS testing you need macOS with Xcode installed:

```sh
npm ci
npm run cap:add:ios
npm run cap:sync:staging:ios
npm run cap:open:ios
```

In Xcode, select an iPhone simulator and run the `App` scheme. For physical
device testing or TestFlight, enroll in the Apple Developer Program, set the
team/signing settings in Xcode, and archive a build pointed at staging first.

## Production Readiness Checklist

Before merging `develop` into `main`:

- Staging web login, account creation, check-ins, events, friends, feed, admin,
  and business portal flows have been tested.
- Android debug build works against staging.
- iOS simulator or TestFlight build works against staging.
- Prod host has its own database, `.env` values, Cloudinary, hCaptcha,
  Mailgun, and OneSignal configuration.
- `app.craftcrawl.site` has HTTPS and OneSignal configured for that origin.
