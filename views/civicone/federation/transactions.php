<?php
/**
 * Federation Transactions Directory
 * CivicOne Theme - WCAG 2.1 AA Compliant
 * Template: Directory/List with bidirectional provenance
 */
$pageTitle = $pageTitle ?? "Federated Transactions";
$pageSubtitle = "Cross-timebank exchanges";
$hideHero = true;
$bodyClass = 'civicone--federation';
$currentPage = 'transactions';

\Nexus\Core\SEO::setTitle('Federated Transactions - Cross-Timebank Exchanges');
\Nexus\Core\SEO::setDescription('View your cross-timebank transaction history and exchange stats.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();

// Extract data passed from controller
$transactions = $transactions ?? [];
$stats = $stats ?? [];
$balance = $balance ?? 0;
$partnerCommunities = $partnerCommunities ?? [];
$currentScope = $currentScope ?? 'all';

// Get current tenant name for provenance
$currentTenant = \Nexus\Core\Database::query(
    "SELECT name FROM tenants WHERE id = ?",
    [\Nexus\Core\TenantContext::getId()]
)->fetch(\PDO::FETCH_ASSOC);
$currentTenantName = $currentTenant['name'] ?? 'Your Community';
?>

<!-- Federation Scope Switcher (only if user has 2+ communities) -->
<?php if (count($partnerCommunities) >= 2): ?>
    <?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/federation-scope-switcher.php'; ?>
<?php endif; ?>

<!-- Federation Service Navigation -->
<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/federation-service-navigation.php'; ?>

<div class="civicone-width-container">
    <main class="civicone-main-wrapper">

        <!-- Page Header -->
        <h1 class="govuk-heading-xl">Federated Transactions</h1>

        <p class="govuk-body-l">
            View your cross-timebank transaction history and exchange statistics.
        </p>

        <!-- Stats Grid (GOV.UK Grid) -->
        <div class="govuk-grid-row govuk-!-margin-bottom-6">
            <div class="govuk-grid-column-one-quarter">
                <div class="civicone-federation-stat-card civicone-federation-stat-card--balance">
                    <p class="govuk-body-s govuk-!-margin-bottom-1 civicone-federation-stat-label">Current Balance</p>
                    <p class="govuk-heading-l govuk-!-margin-bottom-0 civicone-federation-stat-value--balance"><?= number_format($balance, 1) ?> hrs</p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="civicone-federation-stat-card civicone-federation-stat-card--sent">
                    <p class="govuk-body-s govuk-!-margin-bottom-1 civicone-federation-stat-label">Hours Sent</p>
                    <p class="govuk-heading-l govuk-!-margin-bottom-0"><?= number_format($stats['total_sent_hours'] ?? 0, 1) ?></p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="civicone-federation-stat-card civicone-federation-stat-card--received">
                    <p class="govuk-body-s govuk-!-margin-bottom-1 civicone-federation-stat-label">Hours Received</p>
                    <p class="govuk-heading-l govuk-!-margin-bottom-0"><?= number_format($stats['total_received_hours'] ?? 0, 1) ?></p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="civicone-federation-stat-card civicone-federation-stat-card--exchanges">
                    <p class="govuk-body-s govuk-!-margin-bottom-1 civicone-federation-stat-label">Total Exchanges</p>
                    <p class="govuk-heading-l govuk-!-margin-bottom-0"><?= ($stats['total_sent_count'] ?? 0) + ($stats['total_received_count'] ?? 0) ?></p>
                </div>
            </div>
        </div>

        <h2 class="govuk-heading-m">Transaction History</h2>

        <!-- Transactions List -->
        <?php if (!empty($transactions)): ?>
            <ul class="govuk-list">
                <?php foreach ($transactions as $tx): ?>
                <?php
                $isSent = ($tx['direction'] ?? '') === 'sent';
                $status = $tx['status'] ?? 'completed';
                $isCompleted = ($status === 'completed');
                $isPending = ($status === 'pending');
                ?>
                <li class="govuk-!-margin-bottom-6">
                    <div class="govuk-summary-card">
                        <div class="govuk-summary-card__title-wrapper">
                            <h3 class="govuk-summary-card__title">
                                <?= $isSent ? 'Sent to' : 'Received from' ?>
                                <?= htmlspecialchars($tx['other_party_name'] ?? 'Unknown') ?>
                            </h3>
                            <div class="civicone-federation-badges">
                                <!-- Amount Badge -->
                                <span class="govuk-tag <?= $isSent ? 'govuk-tag--orange' : 'govuk-tag--green' ?> civicone-federation-amount-badge">
                                    <?= $isSent ? '−' : '+' ?><?= number_format($tx['hours'] ?? 0, 1) ?> hrs
                                </span>
                                <!-- PROVENANCE LABEL (MANDATORY) - Shows bidirectional flow -->
                                <span class="govuk-tag govuk-tag--grey">
                                    <?php if ($isSent): ?>
                                        <?= htmlspecialchars($currentTenantName) ?> → <?= htmlspecialchars($tx['other_party_tenant_name'] ?? 'Partner') ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($tx['other_party_tenant_name'] ?? 'Partner') ?> → <?= htmlspecialchars($currentTenantName) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div class="govuk-summary-card__content">
                            <?php if (!empty($tx['description'])): ?>
                            <p class="govuk-body-s">
                                <?= htmlspecialchars($tx['description']) ?>
                            </p>
                            <?php endif; ?>

                            <dl class="govuk-summary-list govuk-summary-list--no-border">
                                <?php if (!empty($tx['created_at'])): ?>
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">Date</dt>
                                    <dd class="govuk-summary-list__value">
                                        <time datetime="<?= $tx['created_at'] ?>">
                                            <?= date('d M Y, H:i', strtotime($tx['created_at'])) ?>
                                        </time>
                                    </dd>
                                </div>
                                <?php endif; ?>

                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">Status</dt>
                                    <dd class="govuk-summary-list__value">
                                        <?php if ($isCompleted): ?>
                                            <span class="govuk-tag govuk-tag--green">Completed</span>
                                        <?php elseif ($isPending): ?>
                                            <span class="govuk-tag govuk-tag--yellow">Pending</span>
                                        <?php else: ?>
                                            <span class="govuk-tag"><?= ucfirst($status) ?></span>
                                        <?php endif; ?>
                                    </dd>
                                </div>

                                <?php if (!empty($tx['transaction_id'])): ?>
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">Reference</dt>
                                    <dd class="govuk-summary-list__value">#<?= $tx['transaction_id'] ?></dd>
                                </div>
                                <?php endif; ?>

                                <?php
                                $hasReviewed = $isSent ? (($tx['sender_reviewed'] ?? 0) == 1) : (($tx['receiver_reviewed'] ?? 0) == 1);
                                if ($isCompleted):
                                ?>
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">Review</dt>
                                    <dd class="govuk-summary-list__value">
                                        <?php if ($hasReviewed): ?>
                                            <span class="govuk-tag">Reviewed</span>
                                        <?php else: ?>
                                            <a href="<?= $basePath ?>/federation/reviews/create?transaction_id=<?= $tx['id'] ?>" class="govuk-link">
                                                Leave review
                                            </a>
                                        <?php endif; ?>
                                    </dd>
                                </div>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>

        <?php else: ?>
            <!-- Empty State -->
            <div class="govuk-panel govuk-panel--bordered" role="status">
                <h2 class="govuk-heading-m">No federated transactions yet</h2>
                <p class="govuk-body">You haven't completed any cross-timebank exchanges yet.</p>
                <p class="govuk-body">
                    <a href="<?= $basePath ?>/federation/members" class="govuk-link govuk-!-font-weight-bold">
                        Find members to connect with
                    </a>
                </p>
            </div>
        <?php endif; ?>

    </main>
</div>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
