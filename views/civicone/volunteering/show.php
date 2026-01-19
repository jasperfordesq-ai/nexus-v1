<?php
// CivicOne View: Volunteering Opportunity Detail - WCAG 2.1 AA Compliant
// CSS extracted to civicone-volunteering.css

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

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
