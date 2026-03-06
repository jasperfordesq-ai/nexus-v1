-- Add device_name and authenticator_type columns to webauthn_credentials
-- Allows users to identify which device each passkey belongs to

ALTER TABLE webauthn_credentials
    ADD COLUMN IF NOT EXISTS device_name VARCHAR(100) DEFAULT NULL AFTER transports,
    ADD COLUMN IF NOT EXISTS authenticator_type VARCHAR(30) DEFAULT NULL AFTER device_name;
