# iOS TestFlight Without a Mac

CraftCrawl uses GitHub Actions as the macOS build machine and Fastlane for iOS
signing and TestFlight uploads.

## App Identity

- App name: `CraftCrawl`
- Bundle ID: `com.craftcrawl.app`
- Website loaded by the native app: `https://app.craftcrawl.site`
- GitHub branch for TestFlight builds: `main`

## Apple Setup

1. Enroll in the Apple Developer Program with your Apple ID.
2. Open App Store Connect and create a new app named `CraftCrawl`.
3. Create or select bundle ID `com.craftcrawl.app`.
4. Create an App Store Connect API key with App Manager access.
5. Download the `.p8` API key once.

## Fastlane Match Repo

Create a separate private Git repository for signing assets, for example
`noah/craftcrawl-ios-signing`. Fastlane Match stores encrypted certificates and
provisioning profiles there.

Choose a strong `MATCH_PASSWORD` and keep it in your password manager. Losing it
means you cannot decrypt the signing repo later.

## GitHub Secrets

Add these repository secrets in GitHub:

- `APPLE_ID`: your Apple ID email.
- `APPLE_TEAM_ID`: Apple Developer team ID.
- `APP_STORE_CONNECT_TEAM_ID`: optional App Store Connect provider/team ID.
- `APP_STORE_CONNECT_API_KEY_ID`: API key ID from App Store Connect.
- `APP_STORE_CONNECT_API_ISSUER_ID`: issuer ID from App Store Connect.
- `APP_STORE_CONNECT_API_KEY_BASE64`: base64 encoded contents of the `.p8` file.
- `MATCH_GIT_URL`: SSH or HTTPS URL for the private Match repo.
- `MATCH_GIT_BASIC_AUTHORIZATION`: optional base64 HTTP auth for a private Match repo.
- `MATCH_PASSWORD`: encryption password for the Match repo.
- `MATCH_KEYCHAIN_PASSWORD`: any strong temporary CI keychain password.

On Linux, encode the API key with:

```sh
base64 -w 0 AuthKey_XXXXXXXXXX.p8
```

For a private GitHub Match repo over HTTPS, create a fine-grained GitHub token
with access to that repo and encode:

```sh
printf "x-access-token:YOUR_GITHUB_TOKEN" | base64 -w 0
```

Put that value in `MATCH_GIT_BASIC_AUTHORIZATION` and use an HTTPS
`MATCH_GIT_URL`, such as `https://github.com/noah/craftcrawl-ios-signing.git`.

## First Signing Run

After the secrets are set, run the `iOS TestFlight` workflow manually from
`main` and choose the `setup_signing` lane. This creates the App Store
certificate and provisioning profile, then stores them in the encrypted Match
repo.

## TestFlight Upload

Run the `iOS TestFlight` workflow manually from `main` and choose the `beta`
lane. The workflow will:

1. Install Node dependencies.
2. Sync Capacitor for `https://app.craftcrawl.site`.
3. Install Fastlane.
4. Download signing assets from Match.
5. Archive the iOS app.
6. Upload `CraftCrawl.ipa` to TestFlight.

The Fastlane lane increments the iOS build number from the GitHub Actions run
number, so each upload has a unique build number.
