# Android Website Release

CraftCrawl can publish a signed Android APK directly to the production website:

```text
https://app.craftcrawl.site/download.php
https://app.craftcrawl.site/downloads/craftcrawl-prod.apk
```

This is an early release path for direct testers. Google Play can be added later.
The web deploy workflow excludes `downloads/` so normal site deploys do not
delete the separately published APK.

## One-Time Signing Setup

Create a release keystore locally and keep it somewhere safe:

```sh
keytool -genkeypair \
  -v \
  -storetype PKCS12 \
  -keystore ~/.ssh/craftcrawl-android-release.keystore \
  -alias craftcrawl-release \
  -keyalg RSA \
  -keysize 4096 \
  -validity 10000
```

Remember the keystore password and key password. Losing this keystore means you
cannot ship updates signed with the same app identity.

Convert the keystore to base64 for GitHub:

```sh
base64 -w 0 ~/.ssh/craftcrawl-android-release.keystore
```

Add these GitHub repository secrets:

- `ANDROID_RELEASE_KEYSTORE_BASE64`: base64 output from the keystore file.
- `ANDROID_KEYSTORE_PASSWORD`: keystore password.
- `ANDROID_KEY_ALIAS`: `craftcrawl-release`.
- `ANDROID_KEY_PASSWORD`: key password.

The release workflow also reuses the existing prod deploy secrets:

- `PROD_SSH_HOST`
- `PROD_SSH_USER`
- `PROD_SSH_PORT`
- `PROD_SSH_PRIVATE_KEY`
- `PROD_DEPLOY_PATH`

## Publishing A Release

1. Merge tested changes into `main`.
2. Open GitHub Actions.
3. Run `Android Website Release` from the `main` branch.
4. Choose `prod` for the target.
5. Enter a version name, such as `1.0.0`.
6. Enter a version code, starting at `1` and increasing for every release.
7. Choose the initial app icon. Use `trail` for the normal release default;
   users can switch to the other icons later from mobile app settings.
8. When the workflow passes, open `https://app.craftcrawl.site/download.php`.

The workflow uploads the APK to:

```text
downloads/craftcrawl-prod.apk
```

`download.php` shows the download button only when that file exists on the
selected website target. The workflow verifies the uploaded file over SSH after
publishing it.

Android users may need to allow installs from their browser before installing
the downloaded APK.
