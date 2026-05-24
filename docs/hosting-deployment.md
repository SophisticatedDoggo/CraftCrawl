# CraftCrawl Hosting and Deployment Path

CraftCrawl needs a host that supports:

- PHP 8.3 or newer
- MySQL
- HTTPS on a real domain
- Environment variables or protected config for secrets
- File uploads routed through PHP, with photos stored in Cloudinary
- GitHub-based deploys or SSH/rsync deploys from GitHub Actions

## Branch and Host Model

CraftCrawl uses:

- `main` for prod at `https://app.craftcrawl.site`.

The web deploy workflow deploys from `main`. See `docs/environments.md` for the required secret setup.

## Hosting Options

### Easiest PHP/MySQL Start

Use managed shared hosting with cPanel-style PHP/MySQL support. This is usually the fastest way to get CraftCrawl online because the app is currently a traditional PHP app rather than a Node or Laravel app.

Good fit when:

- You want simple PHP/MySQL hosting.
- You want phpMyAdmin or a similar database UI.
- You are comfortable uploading through GitHub Actions over SFTP/SSH.

Tradeoffs:

- GitHub auto-deploy support varies by host.
- Background jobs and logs are usually more limited.

### Best Long-Term Control

Use a small VPS or managed cloud server. Install Nginx or Apache, PHP-FPM, MySQL, Composer if needed later, and deploy from GitHub Actions over SSH.

Good fit when:

- You want full control.
- You want reliable deploy automation.
- You are comfortable managing server updates and backups.

Tradeoffs:

- More setup responsibility.
- You must configure SSL, firewall, database backups, and monitoring.

### Container/App Platform

Use a platform that can deploy PHP through Docker. This can work well later, but it requires containerizing the app and deciding where MySQL lives.

Good fit when:

- You want repeatable infrastructure.
- You are comfortable with Docker.

Tradeoffs:

- More initial setup than shared hosting.
- Persistent MySQL is usually a separate paid service.

## GitHub Actions Deployment Shape

The repository includes these workflows:

- `PHP CI`: lints PHP files on push and pull request.
- `Deploy Web`: deploys `main` to prod with `rsync` over SSH.
- `Capacitor Android Build`: builds a prod debug APK.
- `Capacitor iOS Build`: builds a prod iOS simulator app on macOS.

Do not store passwords, API keys, database credentials, reCAPTCHA secrets, Cloudinary secrets, or signing keys in the repo. Put them in GitHub Actions secrets and host environment variables.

## Domain and HTTPS

The Capacitor app should use HTTPS only. Android and iOS store builds should point at the final production domain once ready:

```sh
CRAFTCRAWL_MOBILE_URL="https://app.craftcrawl.site"
```

Avoid shipping a store build pointed at localhost, a LAN IP, or an insecure `http://` URL.

## Mobile Store Accounts

You will eventually need:

- Apple Developer Program membership for iOS App Store distribution.
- Google Play Console account for Android Play Store distribution.
- App privacy details that describe location, account, profile, social/feed interactions, photos, and analytics data.

Keep the first store build simple: Android internal testing first, then iOS TestFlight, then public release after real-device testing.
