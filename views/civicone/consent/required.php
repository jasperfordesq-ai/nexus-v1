<?php
/**
 * CivicOne Consent Re-acceptance Page
 * Professional GOV.UK-inspired design for GDPR compliance
 */
$pageTitle = 'Accept Updated Terms';
$hero_title = 'Terms & Conditions Update';
$hero_subtitle = 'Action required to continue';

require dirname(__DIR__) . '/../layouts/civicone/header.php';

$basePath = $basePath ?? \Nexus\Core\TenantContext::getBasePath();
$consents = $consents ?? [];
<!-- Consent Required CSS -->
<link rel="stylesheet" href="<?= NexusCoreTenantContext::getBasePath() ?>/assets/css/purged/civicone-consent-required.min.css">
    text-align: center;
}

[data-theme="dark"] .consent-submit {
    border-color: var(--color-gray-700, #374151);
}

.civic-btn--large {
    padding: 0.875rem 2rem;
    font-size: 1rem;
    font-weight: 600;
    min-width: 220px;
}

.civic-btn--primary {
    background: var(--color-primary-600, #4f46e5);
    color: white;
    border: none;
    border-radius: var(--radius-md, 8px);
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
}

.civic-btn--primary:hover:not(:disabled) {
    background: var(--color-primary-700, #4338ca);
}

.civic-btn--primary:active:not(:disabled) {
    transform: scale(0.98);
}

.civic-btn--primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.consent-submit__note {
    font-size: 0.8125rem;
    color: var(--color-gray-500, #6b7280);
    margin: var(--space-400, 1rem) 0 0 0;
    line-height: 1.5;
}

/* Help Section */
.consent-help {
    margin-top: var(--space-600, 1.5rem);
    padding: var(--space-500, 1.25rem);
    background: var(--color-gray-50, #f9fafb);
    border-radius: var(--radius-md, 8px);
}

[data-theme="dark"] .consent-help {
    background: var(--color-gray-800, #1f2937);
}

.consent-help h3 {
    font-size: 0.9375rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    color: var(--color-gray-900, #111827);
}

[data-theme="dark"] .consent-help h3 {
    color: var(--color-gray-100, #f3f4f6);
}

.consent-help p {
    font-size: 0.875rem;
    color: var(--color-gray-600, #4b5563);
    margin: 0 0 0.5rem 0;
    line-height: 1.5;
}

[data-theme="dark"] .consent-help p {
    color: var(--color-gray-400, #9ca3af);
}

.consent-help a {
    color: var(--color-primary-600, #4f46e5);
}

[data-theme="dark"] .consent-help a {
    color: var(--color-primary-400, #818cf8);
}

.consent-help__decline {
    font-size: 0.8125rem;
    color: var(--color-gray-500, #6b7280);
}

/* Responsive */
@media (max-width: 640px) {
    .consent-item__header {
        flex-direction: column;
        gap: 0.5rem;
    }

    .civic-btn--large {
        width: 100%;
    }
}
</style>

<!-- Consent Required JavaScript -->
<script src="<?= NexusCoreTenantContext::getBasePath() ?>/assets/js/civicone-consent-required.min.js" defer></script>
</script>

<?php require dirname(__DIR__) . '/../layouts/civicone/footer.php'; ?>
