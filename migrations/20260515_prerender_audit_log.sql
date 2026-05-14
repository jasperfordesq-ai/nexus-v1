-- =============================================================================
-- Prerender Admin — audit log + circuit breaker state
-- =============================================================================
-- Records every mutating action against the prerender engine so operators can
-- trace WHO did WHAT WHEN, and powers the per-action rate limiter. The breaker
-- state lives in the cache, not here — but failure history is durable so the
-- admin UI can show "5 failures in last 10 min" without keeping a process
-- alive.
-- =============================================================================

CREATE TABLE IF NOT EXISTS prerender_audit_log (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- Acting user (NULL for unauthenticated webhook callers).
    actor_user_id   BIGINT UNSIGNED NULL,
    -- Action name. Free-form but kept short — see PrerenderAuditAction const list.
    action          VARCHAR(64) NOT NULL,
    -- Optional tenant scope of the action.
    tenant_id       INT UNSIGNED NULL,
    -- Job id this action created or affected (NULL for non-job actions).
    job_id          BIGINT UNSIGNED NULL,
    -- Outcome: ok | denied | error. Lets us count denies on the rate limiter.
    outcome         ENUM('ok','denied','error') NOT NULL DEFAULT 'ok',
    -- Truncated JSON of request body (sanitised — no secrets) and result.
    -- 8 KiB is plenty for audit and keeps the table compact.
    details         JSON NULL,
    -- IP + UA for forensics. IPs stored as plain string (v4 + v6) — no
    -- correlation to PII, the user record is enough for that.
    ip              VARCHAR(64) NULL,
    user_agent      VARCHAR(255) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_paudit_action_created (action, created_at),
    KEY idx_paudit_actor_created (actor_user_id, created_at),
    KEY idx_paudit_tenant_created (tenant_id, created_at),
    KEY idx_paudit_outcome (outcome, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
