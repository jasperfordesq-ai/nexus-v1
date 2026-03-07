-- Reset webauthn_credentials for lbuchs/WebAuthn library migration
-- Old credentials stored base64url-encoded COSE keys (hand-rolled parser)
-- New credentials store PEM-format public keys (from lbuchs/WebAuthn library)
-- These formats are incompatible, so existing credentials must be cleared.
-- Users will need to re-register their passkeys.
-- The feature was disabled ("pending full audit") so very few if any credentials exist.

DELETE FROM webauthn_credentials WHERE 1=1;

-- Ensure public_key column is large enough for PEM keys
ALTER TABLE webauthn_credentials
    MODIFY COLUMN public_key TEXT NOT NULL COMMENT 'PEM-format public key from lbuchs/WebAuthn library';
