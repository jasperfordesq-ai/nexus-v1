-- =============================================================================
-- Prerender Admin Module — job queue + run history
-- =============================================================================
-- Tracks force-refresh jobs requested from the admin UI and their outcome.
-- The host-side processor (`scripts/prerender-job-processor.sh`) claims rows
-- atomically and runs `prerender-tenants.sh` with the captured flags.
-- =============================================================================

CREATE TABLE IF NOT EXISTS prerender_jobs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- Who requested the run (admin user id, NULL for system/auto).
    requested_by    BIGINT UNSIGNED NULL,
    -- Optional tenant scope. NULL = all tenants.
    tenant_id       INT UNSIGNED NULL,
    -- Comma-separated route list (e.g. "/about,/blog"). NULL/empty = all routes.
    routes          VARCHAR(2048) NULL,
    -- Render every selected page even if cached and current.
    force_render    TINYINT(1) NOT NULL DEFAULT 0,
    -- Dry run — plan only, no Playwright container.
    dry_run         TINYINT(1) NOT NULL DEFAULT 0,
    -- Lifecycle: queued -> claimed -> running -> (succeeded | failed | partial | cancelled).
    status          ENUM('queued','claimed','running','succeeded','failed','partial','cancelled')
                    NOT NULL DEFAULT 'queued',
    -- Hostname / pid of the processor that claimed the row (for diagnostics).
    claimed_by      VARCHAR(128) NULL,
    -- Counters reported by the worker (NULL until job finishes).
    planned_count   INT UNSIGNED NULL,
    rendered_count  INT UNSIGNED NULL,
    invalid_count   INT UNSIGNED NULL,
    duration_s      INT UNSIGNED NULL,
    -- Worker exit code (0 = success, non-zero = failure detail).
    exit_code       INT NULL,
    -- Tail of script stdout/stderr — capped to ~256KB at write time.
    log_excerpt     MEDIUMTEXT NULL,
    -- Free-form error reason if the row never made it to the worker.
    error_message   VARCHAR(1024) NULL,
    queued_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    claimed_at      TIMESTAMP NULL,
    started_at      TIMESTAMP NULL,
    finished_at     TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY idx_prerender_jobs_status_queued (status, queued_at),
    KEY idx_prerender_jobs_tenant (tenant_id),
    KEY idx_prerender_jobs_requested_by (requested_by),
    KEY idx_prerender_jobs_finished (finished_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
