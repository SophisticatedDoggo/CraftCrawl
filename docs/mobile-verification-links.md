# Mobile verification links

CraftCrawl uses the existing HTTPS verification URL as the deep link:

```text
https://app.craftcrawl.site/verify_email.php?token=...
```

When mobile associations are configured:

1. iOS Universal Links or Android App Links open the installed CraftCrawl app first.
2. `js/theme_init.js` detects the launch URL inside the native shell and routes the WebView to `verify_email.php`.
3. The verification page verifies the token and redirects to the matching login page on success.
4. If the app is not installed, the same HTTPS URL opens on the website and follows the same verification/login flow.

## iOS

`ios/App/App/App.entitlements` already declares:

```text
applinks:app.craftcrawl.site
```

Host an `apple-app-site-association` file at:

```text
https://app.craftcrawl.site/.well-known/apple-app-site-association
```

The production association file is checked into:

```text
.well-known/apple-app-site-association
```

It currently uses Apple Team ID `TLKMLWTXKA`:

```json
{
  "applinks": {
    "details": [
      {
        "appIDs": ["TLKMLWTXKA.com.craftcrawl.app"],
        "components": [
          {
            "/": "/verify_email.php"
          }
        ]
      }
    ]
  }
}
```

The file must be served as JSON without redirects.

## Android

`android/app/src/main/AndroidManifest.xml` already declares an App Link for:

```text
https://app.craftcrawl.site/verify_email.php
```

Host `assetlinks.json` at:

```text
https://app.craftcrawl.site/.well-known/assetlinks.json
```

Use this shape, replacing `ANDROID_RELEASE_SHA256_FINGERPRINT` with the signing fingerprint used by the installed release build:

```json
[
  {
    "relation": ["delegate_permission/common.handle_all_urls"],
    "target": {
      "namespace": "android_app",
      "package_name": "com.craftcrawl.app",
      "sha256_cert_fingerprints": ["ANDROID_RELEASE_SHA256_FINGERPRINT"]
    }
  }
]
```

If Google Play App Signing is enabled, use the fingerprint reported by Play Console for production releases, not only the local upload key.

## Staging

Android staging is configured for:

```text
https://staging.craftcrawl.site/verify_email.php
```

If you also want staging Universal Links on iOS, add a staging associated domain entitlement and host a matching AASA file for the staging bundle identifier.
