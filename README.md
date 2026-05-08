# CraftCrawl
A website to browse, review, and check in on local breweries, wineries and more

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

## Database configuration

Database credentials are read from environment variables so secrets are not kept
in source files:

- `CRAFTCRAWL_DB_HOST`
- `CRAFTCRAWL_DB_USER`
- `CRAFTCRAWL_DB_PASSWORD`
- `CRAFTCRAWL_DB_NAME`

Rotate any credentials that were previously committed before deploying publicly.
