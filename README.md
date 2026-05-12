# CraftCrawl
A website to browse, review, and check in on local breweries, wineries and more

## Mobile app path

CraftCrawl includes a Capacitor shell scaffold for Android/iOS builds that load
the hosted PHP site. Read the setup notes before generating native projects:

- `docs/capacitor.md`
- `docs/hosting-deployment.md`

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

## Email verification

New user and business accounts must verify their email address before login.
Verification and password reset emails are sent through Mailgun. Configure
these environment variables on the host:

- `CRAFTCRAWL_APP_URL`
- `CRAFTCRAWL_MAIL_FROM`
- `MAILGUN_API_KEY`
- `MAILGUN_URL`
- `MAILGUN_DOMAIN`

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
