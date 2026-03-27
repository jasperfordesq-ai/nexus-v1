-- Add created_at column to outbound_webhook_logs
-- WebhookDispatchService references created_at but the table only had attempted_at.
-- Backfill existing rows from attempted_at so ordering/filtering stays correct.

ALTER TABLE outbound_webhook_logs
    ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'Record creation timestamp (distinct from attempted_at which updates on each retry)'
        AFTER tenant_id;

-- Backfill existing rows from attempted_at (best approximation for historical data)
UPDATE outbound_webhook_logs SET created_at = attempted_at WHERE created_at = '0000-00-00 00:00:00' OR created_at IS NULL;
