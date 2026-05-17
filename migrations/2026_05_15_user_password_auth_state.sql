ALTER TABLE users
    ADD COLUMN password_auth_enabled BOOL NOT NULL DEFAULT TRUE AFTER password_hash;

-- Social-only accounts were previously given an unknown random password hash.
-- Treat accounts already linked to a social provider as social-only so they can
-- create a real password from Settings instead of being locked out of account
-- management actions.
UPDATE users
SET password_auth_enabled = FALSE
WHERE google_sub IS NOT NULL OR apple_sub IS NOT NULL;
