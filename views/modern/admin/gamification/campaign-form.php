<?php
/**
 * Admin Campaign Form - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = $campaign ? 'Edit Campaign' : 'Create Campaign';
$adminPageSubtitle = 'Gamification';
$adminPageIcon = 'fa-bullhorn';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

$campaign = $campaign ?? null;
$badges = $badges ?? [];

$audienceConfig = [];
if (!empty($campaign['audience_config'])) {
    $audienceConfig = is_string($campaign['audience_config'])
        ? json_decode($campaign['audience_config'], true) ?? []
        : $campaign['audience_config'];
}
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin-legacy/gamification/campaigns" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <?= $campaign ? 'Edit Campaign' : 'Create Campaign' ?>
        </h1>
        <p class="admin-page-subtitle"><?= $campaign ? 'Modify campaign settings' : 'Set up a new badge or XP campaign' ?></p>
    </div>
</div>

<!-- Campaign Form Card -->
<div class="admin-glass-card" style="max-width: 900px;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #8b5cf6, #a855f7);">
            <i class="fa-solid fa-bullhorn"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Campaign Details</h3>
            <p class="admin-card-subtitle">Configure the campaign settings and targeting</p>
        </div>
    </div>
    <div class="admin-card-body">
        <form action="<?= $basePath ?>/admin-legacy/gamification/campaigns/save" method="POST" id="campaignForm">
            <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
            <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?? '' ?>">

            <div class="form-group">
                <label class="form-label">Campaign Name</label>
                <input type="text" name="name" class="form-control" required
                       placeholder="e.g., Welcome New Members"
                       value="<?= htmlspecialchars($campaign['name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Description <span class="optional">(optional)</span></label>
                <textarea name="description" class="form-control" rows="2"
                          placeholder="Describe what this campaign does..."><?= htmlspecialchars($campaign['description'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Campaign Type</label>
                    <select name="type" id="campaign_type" class="form-control" required onchange="toggleRecurringConfig()">
                        <option value="one_time" <?= ($campaign['type'] ?? '') === 'one_time' ? 'selected' : '' ?>>One Time</option>
                        <option value="recurring" <?= ($campaign['type'] ?? '') === 'recurring' ? 'selected' : '' ?>>Recurring</option>
                        <option value="triggered" <?= ($campaign['type'] ?? '') === 'triggered' ? 'selected' : '' ?>>Triggered</option>
                    </select>
                    <small class="form-hint">One-time runs once, recurring runs on schedule, triggered runs on events</small>
                </div>
                <div class="form-group" id="recurringConfig" style="display: <?= ($campaign['type'] ?? '') === 'recurring' ? 'block' : 'none' ?>;">
                    <label class="form-label">Schedule</label>
                    <select name="schedule" class="form-control">
                        <option value="daily" <?= ($campaign['schedule'] ?? '') === 'daily' ? 'selected' : '' ?>>Daily</option>
                        <option value="weekly" <?= ($campaign['schedule'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                        <option value="monthly" <?= ($campaign['schedule'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Badge to Award <span class="optional">(optional)</span></label>
                    <select name="badge_key" class="form-control">
                        <option value="">-- No Badge --</option>
                        <?php foreach ($badges as $key => $badge): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= ($campaign['badge_key'] ?? '') === $key ? 'selected' : '' ?>>
                            <?= $badge['icon'] ?? '🏆' ?> <?= htmlspecialchars($badge['name'] ?? $key) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">XP Amount</label>
                    <input type="number" name="xp_amount" class="form-control" min="0"
                           value="<?= (int)($campaign['xp_amount'] ?? 0) ?>" placeholder="0">
                    <small class="form-hint">Bonus XP to award (0 for none)</small>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Target Audience</label>
                <select name="target_audience" id="campaign_audience" class="form-control" required onchange="toggleAudienceConfig()">
                    <option value="all_users" <?= ($campaign['target_audience'] ?? '') === 'all_users' ? 'selected' : '' ?>>All Active Users</option>
                    <option value="new_users" <?= ($campaign['target_audience'] ?? '') === 'new_users' ? 'selected' : '' ?>>New Users (joined in last 30 days)</option>
                    <option value="active_users" <?= ($campaign['target_audience'] ?? '') === 'active_users' ? 'selected' : '' ?>>Active Users (logged in this week)</option>
                    <option value="inactive_users" <?= ($campaign['target_audience'] ?? '') === 'inactive_users' ? 'selected' : '' ?>>Inactive Users (no login in 30+ days)</option>
                    <option value="level_range" <?= ($campaign['target_audience'] ?? '') === 'level_range' ? 'selected' : '' ?>>Users at Specific Level Range</option>
                    <option value="badge_holders" <?= ($campaign['target_audience'] ?? '') === 'badge_holders' ? 'selected' : '' ?>>Users with Specific Badge</option>
                </select>

                <div id="levelRangeConfig" class="audience-config <?= ($campaign['target_audience'] ?? '') === 'level_range' ? 'show' : '' ?>">
                    <div class="form-row">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Min Level</label>
                            <input type="number" name="audience_config[min_level]" class="form-control" min="1"
                                   value="<?= (int)($audienceConfig['min_level'] ?? 1) ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Max Level</label>
                            <input type="number" name="audience_config[max_level]" class="form-control" min="1"
                                   value="<?= (int)($audienceConfig['max_level'] ?? 100) ?>">
                        </div>
                    </div>
                </div>

                <div id="badgeHoldersConfig" class="audience-config <?= ($campaign['target_audience'] ?? '') === 'badge_holders' ? 'show' : '' ?>">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Select Badge</label>
                        <select name="audience_config[badge_key]" class="form-control">
                            <?php foreach ($badges as $key => $badge): ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= ($audienceConfig['badge_key'] ?? '') === $key ? 'selected' : '' ?>>
                                <?= $badge['icon'] ?? '🏆' ?> <?= htmlspecialchars($badge['name'] ?? $key) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="preview-count" id="previewCount">
                <i class="fa-solid fa-users"></i>
                <span>This campaign will target <strong id="targetCount">0</strong> users</span>
            </div>

            <div class="form-actions">
                <a href="<?= $basePath ?>/admin-legacy/gamification/campaigns" class="admin-btn admin-btn-secondary">
                    <i class="fa-solid fa-times"></i> Cancel
                </a>
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-check"></i> <?= $campaign ? 'Update Campaign' : 'Create Campaign' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.back-link {
    color: inherit;
    text-decoration: none;
    margin-right: 1rem;
    transition: opacity 0.2s;
}

.back-link:hover {
    opacity: 0.7;
}

/* Form Styles */
.form-group {
    margin-bottom: 1.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.form-label {
    display: block;
    font-weight: 600;
    color: #fff;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.form-label .optional {
    font-weight: 400;
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.8rem;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 0.75rem;
    background: rgba(0, 0, 0, 0.2);
    color: #fff;
    font-size: 0.95rem;
    transition: all 0.2s;
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

select.form-control {
    cursor: pointer;
}

select.form-control option {
    background: #1e293b;
    color: #fff;
}

.form-hint {
    display: block;
    margin-top: 0.35rem;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

/* Audience Config */
.audience-config {
    display: none;
    background: rgba(0, 0, 0, 0.2);
    padding: 1rem;
    border-radius: 0.75rem;
    margin-top: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.audience-config.show {
    display: block;
}

/* Preview Count */
.preview-count {
    background: rgba(99, 102, 241, 0.15);
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: #a5b4fc;
    padding: 1rem 1.25rem;
    border-radius: 0.75rem;
    margin-top: 1rem;
    margin-bottom: 1.5rem;
    display: none;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.95rem;
}

.preview-count.show {
    display: flex;
}

.preview-count strong {
    color: #fff;
    font-weight: 700;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.form-actions .admin-btn {
    flex: 1;
    justify-content: center;
}

.form-actions .admin-btn-primary {
    flex: 2;
}

/* Responsive */
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .form-actions {
        flex-direction: column;
    }

    .form-actions .admin-btn {
        width: 100%;
    }
}
</style>

<script>
const basePath = '<?= $basePath ?>';

function toggleRecurringConfig() {
    var typeEl = document.getElementById('campaign_type');
    var recurringEl = document.getElementById('recurringConfig');
    if (typeEl && recurringEl) {
        recurringEl.style.display = typeEl.value === 'recurring' ? 'block' : 'none';
    }
}

function toggleAudienceConfig() {
    var audience = document.getElementById('campaign_audience');
    if (!audience) return;
    var val = audience.value;

    document.getElementById('levelRangeConfig').classList.remove('show');
    document.getElementById('badgeHoldersConfig').classList.remove('show');

    if (val === 'level_range') {
        document.getElementById('levelRangeConfig').classList.add('show');
    } else if (val === 'badge_holders') {
        document.getElementById('badgeHoldersConfig').classList.add('show');
    }

    previewAudience();
}

function previewAudience() {
    var form = document.getElementById('campaignForm');
    if (!form) return;
    var formData = new FormData(form);
    fetch(basePath + '/admin-legacy/gamification/campaigns/preview-audience', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        document.getElementById('targetCount').textContent = data.count.toLocaleString();
        document.getElementById('previewCount').classList.add('show');
    })
    .catch(function() {
        document.getElementById('previewCount').classList.remove('show');
    });
}

// Preview on page load
document.addEventListener('DOMContentLoaded', function() {
    previewAudience();
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
