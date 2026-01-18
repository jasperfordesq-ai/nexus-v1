<?php
// CivicOne View: Volunteering Opportunity Detail - MadeOpen Style

if (session_status() === PHP_SESSION_NONE) session_start();

$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? 0;
$opportunityId = $opportunity['id'] ?? 0;

$hTitle = $opportunity['title'] ?? 'Volunteer Opportunity';
$hSubtitle = "Volunteer with " . htmlspecialchars($opportunity['org_name'] ?? 'Organization');
$hType = "Volunteering";

require __DIR__ . '/../../layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- Breadcrumb -->
<nav class="civic-breadcrumb" aria-label="Breadcrumb">
    <a href="<?= $basePath ?>/volunteering">
        <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
        Back to Opportunities
    </a>
</nav>

<div class="civic-detail-layout">
    <!-- Main Content -->
    <main class="civic-detail-main">
        <!-- Organization Badge -->
        <div class="civic-detail-card">
            <div class="civic-detail-header">
                <span class="civic-badge civic-badge--coordinator">
                    <span class="dashicons dashicons-building" aria-hidden="true"></span>
                    <?= htmlspecialchars($opportunity['org_name'] ?? 'Organization') ?>
                </span>
                <?php if (!empty($opportunity['location'])): ?>
                    <span class="civic-detail-location">
                        <span class="dashicons dashicons-location" aria-hidden="true"></span>
                        <?= htmlspecialchars($opportunity['location']) ?>
                    </span>
                <?php endif; ?>
            </div>

            <h2 class="civic-detail-title"><?= htmlspecialchars($opportunity['title']) ?></h2>

            <?php if (!empty($opportunity['org_website'])): ?>
                <p class="civic-detail-meta">
                    <a href="<?= htmlspecialchars($opportunity['org_website']) ?>" target="_blank" rel="noopener noreferrer" class="civic-link">
                        <span class="dashicons dashicons-external" aria-hidden="true"></span>
                        Visit Organization Website
                    </a>
                </p>
            <?php endif; ?>
        </div>

        <!-- Description -->
        <div class="civic-detail-card">
            <h3 class="civic-detail-section-title">
                <span class="dashicons dashicons-info" aria-hidden="true"></span>
                About the Role
            </h3>
            <div class="civic-detail-description">
                <?= nl2br(htmlspecialchars($opportunity['description'] ?? '')) ?>
            </div>
        </div>

        <!-- Details Grid -->
        <div class="civic-detail-card">
            <h3 class="civic-detail-section-title">
                <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                Details
            </h3>
            <div class="civic-info-grid">
                <div class="civic-info-item">
                    <span class="civic-info-icon dashicons dashicons-admin-tools" aria-hidden="true"></span>
                    <div class="civic-info-content">
                        <span class="civic-info-label">Skills Needed</span>
                        <span class="civic-info-value"><?= htmlspecialchars($opportunity['skills_needed'] ?? 'None specified') ?></span>
                    </div>
                </div>
                <div class="civic-info-item">
                    <span class="civic-info-icon dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                    <div class="civic-info-content">
                        <span class="civic-info-label">Dates</span>
                        <span class="civic-info-value">
                            <?php if (!empty($opportunity['start_date'])): ?>
                                <?= date('M d, Y', strtotime($opportunity['start_date'])) ?>
                                <?= !empty($opportunity['end_date']) ? ' - ' . date('M d, Y', strtotime($opportunity['end_date'])) : ' (Ongoing)' ?>
                            <?php else: ?>
                                Flexible / Ongoing
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <?php if (!empty($opportunity['commitment'])): ?>
                <div class="civic-info-item">
                    <span class="civic-info-icon dashicons dashicons-clock" aria-hidden="true"></span>
                    <div class="civic-info-content">
                        <span class="civic-info-label">Time Commitment</span>
                        <span class="civic-info-value"><?= htmlspecialchars($opportunity['commitment']) ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Social Interactions -->
        <div class="civic-detail-card">
            <?php
            $targetType = 'volunteering';
            $targetId = $opportunity['id'];
            include dirname(__DIR__) . '/partials/social_interactions.php';
            ?>
        </div>
    </main>

    <!-- Sidebar -->
    <aside class="civic-detail-sidebar">
        <div class="civic-sidebar-card">
            <!-- Organization Info -->
            <div class="civic-sidebar-org">
                <div class="civic-sidebar-org-icon">
                    <span class="dashicons dashicons-building" aria-hidden="true"></span>
                </div>
                <h4 class="civic-sidebar-org-name"><?= htmlspecialchars($opportunity['org_name'] ?? 'Organization') ?></h4>
                <p class="civic-sidebar-org-type">Community Organization</p>
            </div>

            <!-- Application Status / Form -->
            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'applied'): ?>
                <div class="civic-alert civic-alert--success">
                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                    <div>
                        <strong>Application Sent!</strong>
                        <p>The organization will contact you shortly.</p>
                    </div>
                </div>
            <?php elseif (!empty($hasApplied)): ?>
                <div class="civic-alert civic-alert--info">
                    <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                    <div>
                        <strong>Already Applied</strong>
                        <p>You've applied for this opportunity.</p>
                    </div>
                </div>
            <?php elseif ($isLoggedIn): ?>
                <form action="<?= $basePath ?>/volunteering/apply" method="POST" class="civic-apply-form">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="opportunity_id" value="<?= $opportunity['id'] ?>">

                    <?php if (!empty($shifts)): ?>
                        <div class="civic-form-group">
                            <label class="civic-label">
                                <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                                Select a Shift
                            </label>
                            <div class="civic-shift-list">
                                <?php foreach ($shifts as $shift): ?>
                                    <label class="civic-shift-option">
                                        <input type="radio" name="shift_id" value="<?= $shift['id'] ?>" required>
                                        <span class="civic-shift-content">
                                            <span class="civic-shift-date"><?= date('M d', strtotime($shift['start_time'])) ?></span>
                                            <span class="civic-shift-time">
                                                <?= date('g:i A', strtotime($shift['start_time'])) ?> - <?= date('g:i A', strtotime($shift['end_time'])) ?>
                                            </span>
                                            <span class="civic-shift-capacity"><?= $shift['capacity'] ?> spots</span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="civic-form-group">
                        <label for="apply-message" class="civic-label">
                            <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                            Message (Optional)
                        </label>
                        <textarea name="message" id="apply-message" rows="3" class="civic-textarea"
                                  placeholder="Tell them why you'd like to volunteer..."></textarea>
                    </div>

                    <button type="submit" class="civic-btn civic-btn--full">
                        <span class="dashicons dashicons-yes" aria-hidden="true"></span>
                        Apply Now
                    </button>
                </form>
            <?php else: ?>
                <div class="civic-login-prompt">
                    <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                    <p>Join our community to volunteer.</p>
                    <a href="<?= $basePath ?>/login" class="civic-btn civic-btn--full">
                        Login to Apply
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</div>

<style>
    /* Breadcrumb */
    .civic-breadcrumb {
        margin-bottom: 24px;
    }

    .civic-breadcrumb a {
        color: var(--civic-text-muted);
        text-decoration: none;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .civic-breadcrumb a:hover {
        color: var(--civic-brand);
    }

    /* Detail Layout */
    .civic-detail-layout {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 32px;
        align-items: start;
    }

    @media (max-width: 900px) {
        .civic-detail-layout {
            grid-template-columns: 1fr;
        }
    }

    /* Detail Cards */
    .civic-detail-card {
        background: var(--civic-bg-card);
        border: 1px solid var(--civic-border);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
    }

    .civic-detail-header {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }

    .civic-detail-location {
        color: var(--civic-text-muted);
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .civic-detail-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--civic-text-main);
        margin: 0 0 12px 0;
        line-height: 1.3;
    }

    .civic-detail-meta {
        margin: 0;
    }

    .civic-link {
        color: var(--civic-brand);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .civic-link:hover {
        text-decoration: underline;
    }

    .civic-detail-section-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--civic-text-main);
        margin: 0 0 16px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .civic-detail-section-title .dashicons {
        color: var(--civic-brand);
    }

    .civic-detail-description {
        font-size: 1rem;
        line-height: 1.7;
        color: var(--civic-text-secondary);
        white-space: pre-wrap;
    }

    /* Info Grid */
    .civic-info-grid {
        display: grid;
        gap: 16px;
    }

    .civic-info-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px;
        background: var(--civic-bg-page);
        border-radius: 8px;
        border: 1px solid var(--civic-border);
    }

    .civic-info-icon {
        color: var(--civic-brand);
        font-size: 20px;
        width: 20px;
        height: 20px;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .civic-info-content {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .civic-info-label {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--civic-text-muted);
    }

    .civic-info-value {
        font-weight: 600;
        color: var(--civic-text-main);
    }

    /* Sidebar */
    .civic-sidebar-card {
        background: var(--civic-bg-card);
        border: 1px solid var(--civic-border);
        border-radius: 12px;
        padding: 24px;
        position: sticky;
        top: 20px;
    }

    .civic-sidebar-org {
        text-align: center;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--civic-border);
        margin-bottom: 20px;
    }

    .civic-sidebar-org-icon {
        width: 64px;
        height: 64px;
        background: var(--civic-brand);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 12px;
        color: white;
    }

    .civic-sidebar-org-icon .dashicons {
        font-size: 28px;
        width: 28px;
        height: 28px;
    }

    .civic-sidebar-org-name {
        margin: 0 0 4px 0;
        font-size: 1.1rem;
        color: var(--civic-text-main);
    }

    .civic-sidebar-org-type {
        margin: 0;
        color: var(--civic-text-muted);
        font-size: 14px;
    }

    /* Alerts */
    .civic-alert {
        display: flex;
        gap: 12px;
        padding: 16px;
        border-radius: 8px;
    }

    .civic-alert--success {
        background: #ECFDF5;
        color: #047857;
        border: 1px solid #A7F3D0;
    }

    .civic-alert--info {
        background: #DBEAFE;
        color: #1D4ED8;
        border: 1px solid #93C5FD;
    }

    .civic-alert .dashicons {
        font-size: 24px;
        width: 24px;
        height: 24px;
        flex-shrink: 0;
    }

    .civic-alert strong {
        display: block;
        margin-bottom: 4px;
    }

    .civic-alert p {
        margin: 0;
        font-size: 14px;
        opacity: 0.9;
    }

    /* Apply Form */
    .civic-apply-form {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .civic-form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .civic-label {
        font-weight: 600;
        color: var(--civic-text-main);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .civic-label .dashicons {
        color: var(--civic-brand);
        font-size: 16px;
        width: 16px;
        height: 16px;
    }

    .civic-textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid var(--civic-border);
        border-radius: 8px;
        font-size: 16px;
        font-family: inherit;
        resize: vertical;
        background: var(--civic-bg-page);
        color: var(--civic-text-main);
    }

    .civic-textarea:focus {
        outline: none;
        border-color: var(--civic-brand);
    }

    /* Shift Selection */
    .civic-shift-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .civic-shift-option {
        display: block;
        cursor: pointer;
    }

    .civic-shift-option input {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }

    .civic-shift-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px;
        border: 2px solid var(--civic-border);
        border-radius: 8px;
        background: var(--civic-bg-page);
        transition: all 0.2s ease;
    }

    .civic-shift-option input:checked + .civic-shift-content {
        border-color: var(--civic-brand);
        background: var(--civic-brand-light);
    }

    .civic-shift-option:hover .civic-shift-content {
        border-color: var(--civic-brand);
    }

    .civic-shift-date {
        font-weight: 600;
        color: var(--civic-text-main);
    }

    .civic-shift-time {
        font-size: 13px;
        color: var(--civic-text-muted);
    }

    .civic-shift-capacity {
        font-size: 12px;
        color: var(--civic-text-muted);
        background: var(--civic-bg-card);
        padding: 2px 8px;
        border-radius: 4px;
    }

    /* Full-width button */
    .civic-btn--full {
        width: 100%;
        justify-content: center;
    }

    /* Login Prompt */
    .civic-login-prompt {
        text-align: center;
        padding: 20px;
        background: var(--civic-bg-page);
        border-radius: 8px;
    }

    .civic-login-prompt .dashicons {
        font-size: 32px;
        width: 32px;
        height: 32px;
        color: var(--civic-text-muted);
        margin-bottom: 12px;
    }

    .civic-login-prompt p {
        margin: 0 0 16px 0;
        color: var(--civic-text-muted);
    }
</style>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
