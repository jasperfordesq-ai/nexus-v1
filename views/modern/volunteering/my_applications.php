<?php
// Phoenix View: My Volunteering Applications - Holographic Design
$pageTitle = 'My Applications';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';
?>


<!-- Offline Banner -->
<div class="holo-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="holo-applications-page">
    <!-- Floating Orbs -->
    <div class="holo-orb holo-orb-1"></div>
    <div class="holo-orb holo-orb-2"></div>
    <div class="holo-orb holo-orb-3"></div>

    <div class="holo-container">
        <!-- Page Header -->
        <div class="holo-page-header">
            <div class="holo-page-icon">
                <i class="fa-solid fa-clipboard-list"></i>
            </div>
            <h1 class="holo-page-title">My Applications</h1>
            <p class="holo-page-subtitle">Track your volunteering journey</p>

            <div class="holo-header-actions">
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering" class="holo-action-btn holo-action-btn-primary">
                    <i class="fa-solid fa-search"></i>
                    Find Opportunities
                </a>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/certificate" target="_blank" class="holo-action-btn holo-action-btn-secondary">
                    <i class="fa-solid fa-scroll"></i>
                    Get Certificate
                </a>
            </div>
        </div>

        <?php if (!empty($applications)): ?>
            <!-- Stats Row -->
            <?php
            $totalApps = count($applications);
            $approvedApps = count(array_filter($applications, fn($a) => $a['status'] === 'approved'));
            $pendingApps = count(array_filter($applications, fn($a) => $a['status'] === 'pending'));
            ?>
            <div class="holo-stats-row">
                <div class="holo-stat-card">
                    <div class="holo-stat-value"><?= $totalApps ?></div>
                    <div class="holo-stat-label">Total Applications</div>
                </div>
                <div class="holo-stat-card">
                    <div class="holo-stat-value"><?= $approvedApps ?></div>
                    <div class="holo-stat-label">Approved</div>
                </div>
                <div class="holo-stat-card">
                    <div class="holo-stat-value"><?= $pendingApps ?></div>
                    <div class="holo-stat-label">Pending</div>
                </div>
                <div class="holo-stat-card">
                    <div class="holo-stat-value"><?= count($badges ?? []) ?></div>
                    <div class="holo-stat-label">Badges Earned</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Badges Section -->
        <?php if (!empty($badges)): ?>
            <div class="holo-badges-section">
                <div class="holo-badges-title">
                    <i class="fa-solid fa-trophy"></i>
                    Achievements
                </div>
                <?php foreach ($badges as $badge): ?>
                    <div class="holo-badge" title="<?= htmlspecialchars($badge['name']) ?> - Earned <?= date('M Y', strtotime($badge['awarded_at'])) ?>">
                        <?= $badge['icon'] ?> <?= htmlspecialchars($badge['name']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Applications List -->
        <div class="holo-glass-card">
            <div class="holo-card-header">
                <h2 class="holo-card-title">
                    <i class="fa-solid fa-list-check"></i>
                    Your Applications
                </h2>
            </div>
            <div class="holo-card-body">
                <?php if (empty($applications)): ?>
                    <div class="holo-empty-state">
                        <div class="holo-empty-icon">
                            <i class="fa-solid fa-folder-open"></i>
                        </div>
                        <h3 class="holo-empty-title">No Applications Yet</h3>
                        <p class="holo-empty-text">You haven't applied to any volunteer opportunities yet.</p>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering" class="holo-empty-btn">
                            <i class="fa-solid fa-search"></i>
                            Find Opportunities
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <?php
                        $statusClass = match ($app['status']) {
                            'approved' => 'holo-status-approved',
                            'declined' => 'holo-status-declined',
                            default => 'holo-status-pending'
                        };
                        ?>
                        <div class="holo-application-item">
                            <div class="holo-app-header">
                                <div>
                                    <h3 class="holo-app-title"><?= htmlspecialchars($app['opp_title']) ?></h3>
                                    <p class="holo-app-org">
                                        with <strong><?= htmlspecialchars($app['org_name']) ?></strong>
                                    </p>
                                    <?php if ($app['shift_start']): ?>
                                        <div class="holo-app-shift">
                                            <i class="fa-regular fa-calendar"></i>
                                            <?= date('M d, h:i A', strtotime($app['shift_start'])) ?>
                                            <span style="margin: 0 4px;">|</span>
                                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/ics/<?= $app['id'] ?>">
                                                <i class="fa-solid fa-calendar-plus"></i> Add to Calendar
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <span class="holo-status <?= $statusClass ?>">
                                    <?= htmlspecialchars($app['status']) ?>
                                </span>
                            </div>

                            <div class="holo-app-footer">
                                <span class="holo-app-date">
                                    <i class="fa-regular fa-clock"></i>
                                    Applied on <?= date('M j, Y', strtotime($app['created_at'])) ?>
                                </span>
                                <?php if ($app['status'] == 'approved'): ?>
                                    <div class="holo-app-actions">
                                        <button
                                            onclick="openLogModal(<?= $app['organization_id'] ?>, <?= $app['opportunity_id'] ?>, '<?= htmlspecialchars($app['org_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($app['opp_title'], ENT_QUOTES) ?>')"
                                            class="holo-app-btn holo-app-btn-primary">
                                            <i class="fa-regular fa-clock"></i>
                                            Log Hours
                                        </button>
                                        <button
                                            onclick="openReviewModal(<?= $app['organization_id'] ?>, '<?= htmlspecialchars($app['org_name'], ENT_QUOTES) ?>')"
                                            class="holo-app-btn holo-app-btn-secondary">
                                            <i class="fa-regular fa-star"></i>
                                            Review
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Log Hours Modal -->
<div id="logHoursModal" class="holo-modal-overlay">
    <div class="holo-modal">
        <div class="holo-modal-header">
            <h3 class="holo-modal-title">
                <i class="fa-regular fa-clock"></i>
                Log Volunteer Hours
            </h3>
            <button class="holo-modal-close" onclick="closeLogModal()">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="holo-modal-body">
            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/log-hours" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="org_id" id="log_org_id">
                <input type="hidden" name="opp_id" id="log_opp_id">

                <div class="holo-modal-info">
                    <p><strong>Organization:</strong> <span id="log_org_name"></span></p>
                    <p><strong>Role:</strong> <span id="log_opp_title"></span></p>
                </div>

                <div class="holo-form-group">
                    <label class="holo-label">Date</label>
                    <input type="date" name="date" required value="<?= date('Y-m-d') ?>" class="holo-input">
                </div>

                <div class="holo-form-group">
                    <label class="holo-label">Hours Worked</label>
                    <input type="number" step="0.5" name="hours" required placeholder="e.g. 2.5" class="holo-input">
                </div>

                <div class="holo-form-group">
                    <label class="holo-label">Description (optional)</label>
                    <textarea name="description" rows="3" placeholder="Briefly describe what you did..." class="holo-textarea"></textarea>
                </div>

                <div class="holo-modal-actions">
                    <button type="submit" class="holo-modal-btn holo-modal-btn-primary">
                        <i class="fa-solid fa-check"></i>
                        Submit Hours
                    </button>
                    <button type="button" onclick="closeLogModal()" class="holo-modal-btn holo-modal-btn-secondary">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div id="reviewModal" class="holo-modal-overlay">
    <div class="holo-modal">
        <div class="holo-modal-header">
            <h3 class="holo-modal-title">
                <i class="fa-regular fa-star"></i>
                Leave a Review
            </h3>
            <button class="holo-modal-close" onclick="closeReviewModal()">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="holo-modal-body">
            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/reviews" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="target_type" value="organization">
                <input type="hidden" name="target_id" id="review_target_id">

                <div class="holo-modal-info">
                    <p><strong>Reviewing:</strong> <span id="review_target_name"></span></p>
                </div>

                <div class="holo-form-group">
                    <label class="holo-label">Your Rating</label>
                    <div class="holo-star-rating" id="starRating">
                        <div class="holo-star" data-value="1"><i class="fa-solid fa-star"></i></div>
                        <div class="holo-star" data-value="2"><i class="fa-solid fa-star"></i></div>
                        <div class="holo-star" data-value="3"><i class="fa-solid fa-star"></i></div>
                        <div class="holo-star" data-value="4"><i class="fa-solid fa-star"></i></div>
                        <div class="holo-star" data-value="5"><i class="fa-solid fa-star"></i></div>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" value="5">
                </div>

                <div class="holo-form-group">
                    <label class="holo-label">Your Review</label>
                    <textarea name="content" rows="4" placeholder="Share your experience volunteering with this organization..." class="holo-textarea" required></textarea>
                </div>

                <div class="holo-modal-actions">
                    <button type="submit" class="holo-modal-btn holo-modal-btn-primary">
                        <i class="fa-solid fa-paper-plane"></i>
                        Submit Review
                    </button>
                    <button type="button" onclick="closeReviewModal()" class="holo-modal-btn holo-modal-btn-secondary">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal Functions
function openLogModal(orgId, oppId, orgName, oppTitle) {
    document.getElementById('log_org_id').value = orgId;
    document.getElementById('log_opp_id').value = oppId;
    document.getElementById('log_org_name').innerText = orgName;
    document.getElementById('log_opp_title').innerText = oppTitle;
    document.getElementById('logHoursModal').style.display = 'flex';
}

function closeLogModal() {
    document.getElementById('logHoursModal').style.display = 'none';
}

function openReviewModal(targetId, targetName) {
    document.getElementById('review_target_id').value = targetId;
    document.getElementById('review_target_name').innerText = targetName;
    document.getElementById('reviewModal').style.display = 'flex';
    // Reset stars
    updateStars(5);
}

function closeReviewModal() {
    document.getElementById('reviewModal').style.display = 'none';
}

// Star Rating
function updateStars(rating) {
    document.getElementById('ratingInput').value = rating;
    document.querySelectorAll('.holo-star').forEach((star, index) => {
        star.classList.toggle('active', index < rating);
    });
}

document.querySelectorAll('.holo-star').forEach(star => {
    star.addEventListener('click', function() {
        updateStars(parseInt(this.dataset.value));
    });
});

// Initialize with 5 stars
updateStars(5);

// Close modals on backdrop click
window.addEventListener('click', function(event) {
    if (event.target.id === 'logHoursModal') closeLogModal();
    if (event.target.id === 'reviewModal') closeReviewModal();
});

// Close modals on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeLogModal();
        closeReviewModal();
    }
});

// Offline Indicator
(function initOfflineIndicator() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.add('visible');
        if (navigator.vibrate) navigator.vibrate(100);
    }

    function handleOnline() {
        banner.classList.remove('visible');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
})();

// Form Submission Offline Protection
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to submit.');
            return;
        }
    });
});
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
