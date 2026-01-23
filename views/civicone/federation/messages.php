<?php
/**
 * Federation Messages Directory
 * CivicOne Theme - WCAG 2.1 AA Compliant
 * Template: Directory/List with provenance
 */
$pageTitle = $pageTitle ?? "Federated Messages";
$pageSubtitle = "Connect with members from partner timebanks";
$hideHero = true;
$bodyClass = 'civicone--federation';
$currentPage = 'messages';

\Nexus\Core\SEO::setTitle('Federated Messages - Partner Timebank Conversations');
\Nexus\Core\SEO::setDescription('View and manage your conversations with members from partner timebanks.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();

// Extract data passed from controller
$conversations = $conversations ?? [];
$unreadCount = $unreadCount ?? 0;
$partnerCommunities = $partnerCommunities ?? [];
$currentScope = $currentScope ?? 'all';
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
        <div class="civicone-federation-page-header">
            <div>
                <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">Federated Messages</h1>
                <?php if ($unreadCount > 0): ?>
                <p class="govuk-body">
                    <span class="govuk-tag govuk-tag--blue"><?= $unreadCount ?> unread message<?= $unreadCount !== 1 ? 's' : '' ?></span>
                </p>
                <?php endif; ?>
            </div>
            <a href="<?= $basePath ?>/federation/members" class="govuk-button">
                Find members to message
            </a>
        </div>

        <p class="govuk-body-l">
            View and manage your conversations with members from partner timebanks.
        </p>

        <!-- Messages List -->
        <?php if (!empty($conversations)): ?>
            <ul class="govuk-list">
                <?php foreach ($conversations as $conv): ?>
                <?php
                $isUnread = ($conv['unread_count'] ?? 0) > 0;
                $threadUrl = $basePath . '/federation/messages/' . $conv['sender_user_id'] . '?tenant=' . $conv['sender_tenant_id'];
                ?>
                <li class="govuk-!-margin-bottom-6">
                    <div class="govuk-summary-card <?= $isUnread ? 'govuk-summary-card--unread' : '' ?>">
                        <div class="govuk-summary-card__title-wrapper">
                            <h3 class="govuk-summary-card__title">
                                <a href="<?= $threadUrl ?>" class="govuk-link">
                                    <?= htmlspecialchars($conv['sender_name'] ?? 'Unknown') ?>
                                </a>
                            </h3>
                            <div class="civicone-federation-badges">
                                <!-- Unread Badge -->
                                <?php if ($isUnread): ?>
                                <span class="govuk-tag govuk-tag--blue">
                                    <?= $conv['unread_count'] ?> unread
                                </span>
                                <?php endif; ?>
                                <!-- PROVENANCE LABEL (MANDATORY) - Shows message direction -->
                                <span class="govuk-tag govuk-tag--grey">
                                    From <?= htmlspecialchars($conv['sender_tenant_name'] ?? 'Partner') ?>
                                </span>
                            </div>
                        </div>
                        <div class="govuk-summary-card__content">
                            <?php if (!empty($conv['body'])): ?>
                            <p class="govuk-body-s <?= $isUnread ? 'govuk-!-font-weight-bold' : '' ?>">
                                <?= htmlspecialchars(mb_substr($conv['body'], 0, 200)) ?><?= mb_strlen($conv['body']) > 200 ? '...' : '' ?>
                            </p>
                            <?php endif; ?>

                            <dl class="govuk-summary-list govuk-summary-list--no-border">
                                <?php if (!empty($conv['subject'])): ?>
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">Subject</dt>
                                    <dd class="govuk-summary-list__value"><?= htmlspecialchars($conv['subject']) ?></dd>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($conv['created_at'])): ?>
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">Last message</dt>
                                    <dd class="govuk-summary-list__value">
                                        <time datetime="<?= $conv['created_at'] ?>">
                                            <?= date('d M Y, H:i', strtotime($conv['created_at'])) ?>
                                        </time>
                                    </dd>
                                </div>
                                <?php endif; ?>

                                <?php if (isset($conv['message_count'])): ?>
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">Messages</dt>
                                    <dd class="govuk-summary-list__value">
                                        <?= $conv['message_count'] ?> message<?= $conv['message_count'] !== 1 ? 's' : '' ?> in thread
                                    </dd>
                                </div>
                                <?php endif; ?>
                            </dl>
                        </div>
                        <div class="govuk-summary-card__actions">
                            <a href="<?= $threadUrl ?>" class="govuk-link">
                                <?= $isUnread ? 'Read messages' : 'View conversation' ?><span class="govuk-visually-hidden"> with <?= htmlspecialchars($conv['sender_name'] ?? 'member') ?></span>
                            </a>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>

        <?php else: ?>
            <!-- Empty State -->
            <div class="govuk-panel govuk-panel--bordered" role="status">
                <h2 class="govuk-heading-m">No federated messages yet</h2>
                <p class="govuk-body">You haven't received any messages from partner timebanks yet.</p>
                <p class="govuk-body">
                    <a href="<?= $basePath ?>/federation/members" class="govuk-link govuk-!-font-weight-bold">
                        Find members to message
                    </a>
                </p>
            </div>
        <?php endif; ?>

    </main>
</div>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
