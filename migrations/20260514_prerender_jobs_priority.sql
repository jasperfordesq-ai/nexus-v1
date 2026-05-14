-- =============================================================================
-- Prerender job priority — let auto-recache run at low priority without
-- starving urgent user-initiated jobs.
-- =============================================================================
-- Values: 1 (highest) … 9 (lowest). Default 5 (normal user-initiated).
-- Convention:
--   3 = high     (per-snapshot force from admin UI)
--   5 = normal   (user-initiated tenant/routes run)
--   7 = low      (auto-recache for content-stale rows)
-- Lower number wins. Within a priority bucket, oldest queued_at wins.
-- =============================================================================

ALTER TABLE prerender_jobs
    ADD COLUMN priority TINYINT UNSIGNED NOT NULL DEFAULT 5 AFTER dry_run;

-- New ordering key. The pre-existing idx_prerender_jobs_status_queued stays —
-- it still serves the per-status listing pages in the admin UI.
ALTER TABLE prerender_jobs
    ADD KEY idx_prerender_jobs_claim (status, priority, queued_at);
