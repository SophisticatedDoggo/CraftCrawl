# CraftCrawl
A website to browse, review, and check in on local breweries, wineries and more

## Mobile app path

CraftCrawl includes a Capacitor shell scaffold for Android/iOS builds that load
the hosted PHP site. Read the setup notes before generating native projects:

- `docs/capacitor.md`
- `docs/hosting-deployment.md`
- `docs/environments.md`
- `docs/android-website-release.md`

## Environments

CraftCrawl uses `develop` for staging and `main` for prod:

- Staging web/mobile target: `https://staging.craftcrawl.site`
- Production web/mobile target: `https://app.craftcrawl.site`

GitHub Actions can lint PHP, deploy web files over SSH/rsync, and build
branch-targeted Android/iOS Capacitor shells. See `docs/environments.md` for
the required GitHub Environment and secret setup.

## Cloudinary photos

CraftCrawl uploads user and business photos to Cloudinary through server-side PHP.
Do not commit Cloudinary credentials to GitHub. Provide them as environment
variables on your host:

- `CLOUDINARY_CLOUD_NAME`
- `CLOUDINARY_API_KEY`
- `CLOUDINARY_API_SECRET`

The upload helper lives in `lib/cloudinary_upload.php`. It validates JPEG, PNG,
and WebP files, sends signed uploads to Cloudinary, and can save Cloudinary
metadata into the `photos` table.

## hCaptcha

CraftCrawl verifies hCaptcha on login and account creation forms. Do not commit
the secret key to GitHub. Configure these values as host environment variables:

- `HCAPTCHA_SITE_KEY`
- `HCAPTCHA_SECRET_KEY`

## Google and Apple sign-in

User login and account creation can show Google and Apple sign-in buttons on the
website and in the Capacitor Android/iOS shells. Configure these values on the
host:

- `GOOGLE_SIGN_IN_CLIENT_ID`
- `APPLE_SIGN_IN_CLIENT_ID`
- `CRAFTCRAWL_GOOGLE_CLIENT_IDS`
- `CRAFTCRAWL_APPLE_CLIENT_IDS`

`GOOGLE_SIGN_IN_CLIENT_ID` and `APPLE_SIGN_IN_CLIENT_ID` control which buttons
render on the page. The `CRAFTCRAWL_*_CLIENT_IDS` values are comma-separated
allowlists accepted by the server when verifying identity tokens, which lets you
include web, Android, and iOS client IDs.

Run the social sign-in migration for existing databases:

```sh
mysql -u craft_crawl -p craft_crawl < migrations/2026_05_13_social_sign_in.sql
```

## Email verification

New user and business accounts must verify their email address before login.
Verification and password reset emails are sent through Mailgun. Configure
these environment variables on the host:

- `CRAFTCRAWL_APP_URL`
- `CRAFTCRAWL_MAIL_FROM`
- `MAILGUN_API_KEY`
- `MAILGUN_URL`
- `MAILGUN_DOMAIN`

## OneSignal push notifications

CraftCrawl can register logged-in user browsers and native iOS/Android app
installs with OneSignal, then send targeted social notifications for friend
invites, accepted invites, comments, replies, and reactions. Configure these
environment variables on the host:

- `ONESIGNAL_APP_ID`
- `ONESIGNAL_API_KEY`
- `CRAFTCRAWL_APP_URL`

The OneSignal web app must be configured for the same origin as the site, such
as `https://staging.craftcrawl.site`, and `OneSignalSDKWorker.js` must remain
publicly accessible from the site root.

Native push requires the OneSignal Capacitor plugin to be synced into the iOS
and Android projects. iOS uses the `com.craftcrawl.app` bundle ID with APNs, and
Android uses the `com.craftcrawl.app` package with Firebase Cloud Messaging.

Run the email verification migration for existing databases:

```sh
mysql -u craft_crawl -p craft_crawl < migrations/2026_05_09_email_verification.sql
mysql -u craft_crawl -p craft_crawl < migrations/2026_05_09_password_resets.sql
mysql -u craft_crawl -p craft_crawl < migrations/2026_05_09_disable_accounts.sql
```

## Database configuration

Database credentials are read from environment variables or a local `.env` file
so secrets are not kept in source files. Copy `.env.example` to `.env` for local
development, then fill in your real values:

- `CRAFTCRAWL_DB_HOST`
- `CRAFTCRAWL_DB_USER`
- `CRAFTCRAWL_DB_PASSWORD`
- `CRAFTCRAWL_DB_NAME`

Rotate any credentials that were previously committed before deploying publicly.

## Leveling and badges

Run the leveling migration before using check-ins, XP, levels, and badges:

```sh
mysql -u craft_crawl -p craft_crawl < migrations/2026_05_10_leveling_system.sql
mysql -u craft_crawl -p craft_crawl < migrations/2026_05_12_progressive_level_state.sql
mysql -u craft_crawl -p craft_crawl < migrations/2026_05_14_user_profile_photos.sql
```

Check-ins use browser GPS and server-side distance checks. Review XP is only
awarded after a user has checked in at that location.

## Admin accounts

Run the SQL migration before using admin features:

```sh
mysql -u craft_crawl -p craft_crawl < migrations/2026_05_09_admin_features.sql
mysql -u craft_crawl -p craft_crawl < migrations/2026_05_09_remember_login_tokens.sql
```

Create or reset the first admin account from the command line:

```sh
php -r "echo password_hash('StrongPass!123', PASSWORD_DEFAULT), PHP_EOL;"
mysql -u craft_crawl -p craft_crawl < migrations/2026_05_09_create_admin_account_template.sql
```

Admins sign in at `admin_login.php`.
