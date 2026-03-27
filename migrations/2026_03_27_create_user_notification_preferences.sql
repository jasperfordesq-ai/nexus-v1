-- Create user_notification_preferences table
-- Stores per-user email/push notification opt-in settings.
-- No tenant_id column — tenant isolation is enforced by the caller passing a tenant-scoped user_id.

CREATE TABLE IF NOT EXISTS user_notification_preferences (
    id                          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id                     INT UNSIGNED NOT NULL,

    -- Email notifications
    email_messages              TINYINT(1) NOT NULL DEFAULT 1,
    email_listings              TINYINT(1) NOT NULL DEFAULT 1,
    email_digest                TINYINT(1) NOT NULL DEFAULT 1,
    email_connections           TINYINT(1) NOT NULL DEFAULT 1,
    email_transactions          TINYINT(1) NOT NULL DEFAULT 1,
    email_reviews               TINYINT(1) NOT NULL DEFAULT 1,
    email_gamification_digest   TINYINT(1) NOT NULL DEFAULT 1,
    email_gamification_milestones TINYINT(1) NOT NULL DEFAULT 1,
    email_org_payments          TINYINT(1) NOT NULL DEFAULT 1,
    email_org_transfers         TINYINT(1) NOT NULL DEFAULT 1,
    email_org_membership        TINYINT(1) NOT NULL DEFAULT 1,
    email_org_admin             TINYINT(1) NOT NULL DEFAULT 1,

    -- Push notifications
    push_enabled                TINYINT(1) NOT NULL DEFAULT 1,

    updated_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_user_notification_preferences_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
