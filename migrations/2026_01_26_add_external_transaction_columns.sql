-- Add external partner columns to federation_transactions table
-- These columns support transactions with external partner platforms (via API)

-- Add external_partner_id column (references federation_external_partners.id)
ALTER TABLE federation_transactions
    ADD COLUMN external_partner_id INT(11) NULL DEFAULT NULL;

-- Add external_receiver_name column (stores the receiver's name from external system)
ALTER TABLE federation_transactions
    ADD COLUMN external_receiver_name VARCHAR(255) NULL DEFAULT NULL;

-- Add external_transaction_id column (stores the transaction ID returned by external API)
ALTER TABLE federation_transactions
    ADD COLUMN external_transaction_id VARCHAR(100) NULL DEFAULT NULL;

-- Add index for external partner lookup
CREATE INDEX idx_federation_transactions_external ON federation_transactions(external_partner_id);
