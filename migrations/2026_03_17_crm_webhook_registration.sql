-- Register CRM volunteering webhook for tenant 2 (hour-timebank)
-- The ASP.NET CRM backend receives these events and auto-creates notes/tasks/tags
-- Shared secret must match Webhooks:SharedSecret in ASP.NET appsettings

INSERT INTO outbound_webhooks (tenant_id, name, url, secret, events, is_active, failure_count, created_by, created_at)
SELECT 2,
       'CRM Volunteering Integration',
       'http://nexus-backend-api:8080/api/webhooks/volunteering',
       'CHANGE_ME_USE_ENV_VAR',  -- IMPORTANT: rotate this secret and set via environment variable, not hardcoded
       '["volunteer.applied","volunteer.approved","volunteer.declined","shift.signup","shift.completed","shift.noshow","hours.logged","hours.verified","expense.submitted","expense.approved","safeguarding.incident","credential.expiring","training.expired"]',
       1,
       0,
       1,
       NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM outbound_webhooks
    WHERE tenant_id = 2 AND name = 'CRM Volunteering Integration'
);
