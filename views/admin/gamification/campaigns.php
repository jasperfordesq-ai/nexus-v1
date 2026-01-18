<?php
$hTitle = 'Achievement Campaigns';
$hSubtitle = 'Create and manage bulk badge and XP campaigns';
$hGradient = 'mt-hero-gradient-gamification';
$hType = 'Admin Console';

$basePath = \Nexus\Core\TenantContext::getBasePath();
$csrf = \Nexus\Core\Csrf::generate();
require dirname(__DIR__, 2) . '/layouts/modern/header.php';

$campaigns = $campaigns ?? [];
$badges = $badges ?? [];
?>

<style>
.campaign-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: transform 0.2s, box-shadow 0.2s;
}
.campaign-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}
.campaign-status {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}
.status-draft { background: #e5e7eb; color: #374151; }
.status-active { background: #d1fae5; color: #059669; }
.status-paused { background: #fef3c7; color: #d97706; }
.status-completed { background: #dbeafe; color: #2563eb; }
.campaign-type {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 8px;
}
.type-one_time { background: #ede9fe; color: #7c3aed; }
.type-recurring { background: #fce7f3; color: #db2777; }
.type-triggered { background: #ccfbf1; color: #0d9488; }
.stat-mini {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #6b7280;
    font-size: 14px;
}
.stat-mini i {
    width: 20px;
    text-align: center;
}
.btn-campaign {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-campaign:hover {
    transform: scale(1.05);
}
.modal-content {
    border-radius: 16px;
}
.modal-header {
    border-bottom: none;
    padding: 24px 24px 0;
}
.modal-body {
    padding: 24px;
}
.form-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
}
.form-control, .form-select {
    border-radius: 8px;
    padding: 10px 14px;
    border: 1px solid #e5e7eb;
}
.form-control:focus, .form-select:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}
.audience-config {
    display: none;
    background: #f9fafb;
    padding: 16px;
    border-radius: 8px;
    margin-top: 12px;
}
.audience-config.show {
    display: block;
}
.preview-count {
    background: #ede9fe;
    color: #4f46e5;
    padding: 12px 16px;
    border-radius: 8px;
    margin-top: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0"><i class="fa-solid fa-bullhorn text-primary"></i> Achievement Campaigns</h1>
                    <p class="text-muted">Create and manage bulk badge and XP campaigns</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#campaignModal">
                    <i class="fa-solid fa-plus"></i> New Campaign
                </button>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h2 mb-0 text-primary"><?= count(array_filter($campaigns, fn($c) => $c['status'] === 'active')) ?></div>
                    <div class="text-muted">Active Campaigns</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h2 mb-0 text-success"><?= array_sum(array_column($campaigns, 'total_awards')) ?></div>
                    <div class="text-muted">Total Awards Given</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h2 mb-0 text-warning"><?= count(array_filter($campaigns, fn($c) => $c['status'] === 'draft')) ?></div>
                    <div class="text-muted">Draft Campaigns</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="h2 mb-0 text-info"><?= count(array_filter($campaigns, fn($c) => $c['type'] === 'recurring')) ?></div>
                    <div class="text-muted">Recurring Campaigns</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Campaign List -->
    <div class="row">
        <div class="col-12">
            <?php if (empty($campaigns)): ?>
            <div class="text-center py-5">
                <div style="font-size: 64px; margin-bottom: 20px;">📢</div>
                <h4>No Campaigns Yet</h4>
                <p class="text-muted">Create your first campaign to award badges or XP to multiple users at once.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#campaignModal">
                    <i class="fa-solid fa-plus"></i> Create Campaign
                </button>
            </div>
            <?php else: ?>
            <?php foreach ($campaigns as $campaign): ?>
            <div class="campaign-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-2">
                            <span class="campaign-status status-<?= $campaign['status'] ?>"><?= ucfirst($campaign['status']) ?></span>
                            <span class="campaign-type type-<?= $campaign['type'] ?>"><?= str_replace('_', ' ', $campaign['type']) ?></span>
                        </div>
                        <h5 class="mb-1"><?= htmlspecialchars($campaign['name']) ?></h5>
                        <p class="text-muted mb-3"><?= htmlspecialchars($campaign['description'] ?? '') ?></p>

                        <div class="d-flex gap-4">
                            <?php if (!empty($campaign['badge_key'])): ?>
                            <div class="stat-mini">
                                <i class="fa-solid fa-medal"></i>
                                <span>Badge: <?= htmlspecialchars($campaign['badge_key']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($campaign['xp_amount'] > 0): ?>
                            <div class="stat-mini">
                                <i class="fa-solid fa-star"></i>
                                <span>+<?= number_format($campaign['xp_amount']) ?> XP</span>
                            </div>
                            <?php endif; ?>
                            <div class="stat-mini">
                                <i class="fa-solid fa-users"></i>
                                <span><?= ucfirst(str_replace('_', ' ', $campaign['target_audience'])) ?></span>
                            </div>
                            <div class="stat-mini">
                                <i class="fa-solid fa-trophy"></i>
                                <span><?= number_format($campaign['total_awards'] ?? 0) ?> awarded</span>
                            </div>
                            <?php if ($campaign['last_run_at']): ?>
                            <div class="stat-mini">
                                <i class="fa-solid fa-clock"></i>
                                <span>Last run: <?= date('M j, g:i A', strtotime($campaign['last_run_at'])) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <?php if ($campaign['status'] === 'draft'): ?>
                        <form action="<?= $basePath ?>/admin/gamification/campaigns/activate" method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                            <button type="submit" class="btn btn-success btn-campaign">
                                <i class="fa-solid fa-play"></i> Activate
                            </button>
                        </form>
                        <?php elseif ($campaign['status'] === 'active'): ?>
                        <form action="<?= $basePath ?>/admin/gamification/campaigns/run" method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-campaign">
                                <i class="fa-solid fa-bolt"></i> Run Now
                            </button>
                        </form>
                        <form action="<?= $basePath ?>/admin/gamification/campaigns/pause" method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                            <button type="submit" class="btn btn-warning btn-campaign">
                                <i class="fa-solid fa-pause"></i> Pause
                            </button>
                        </form>
                        <?php elseif ($campaign['status'] === 'paused'): ?>
                        <form action="<?= $basePath ?>/admin/gamification/campaigns/activate" method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                            <button type="submit" class="btn btn-success btn-campaign">
                                <i class="fa-solid fa-play"></i> Resume
                            </button>
                        </form>
                        <?php endif; ?>

                        <button class="btn btn-outline-secondary btn-campaign" onclick="editCampaign(<?= htmlspecialchars(json_encode($campaign)) ?>)">
                            <i class="fa-solid fa-edit"></i>
                        </button>

                        <form action="<?= $basePath ?>/admin/gamification/campaigns/delete" method="POST" style="display: inline;" onsubmit="return confirm('Delete this campaign?');">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-campaign">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Campaign Modal -->
<div class="modal fade" id="campaignModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="campaignForm" action="<?= $basePath ?>/admin/gamification/campaigns/save" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="campaign_id" id="campaign_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Create Campaign</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Campaign Name</label>
                            <input type="text" name="name" id="campaign_name" class="form-control" required placeholder="e.g., Welcome New Members">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Campaign Type</label>
                            <select name="type" id="campaign_type" class="form-select" required>
                                <option value="one_time">One Time</option>
                                <option value="recurring">Recurring</option>
                                <option value="triggered">Triggered</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="campaign_description" class="form-control" rows="2" placeholder="Describe what this campaign does..."></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Badge to Award (Optional)</label>
                            <select name="badge_key" id="campaign_badge" class="form-select">
                                <option value="">-- No Badge --</option>
                                <?php foreach ($badges as $key => $badge): ?>
                                <option value="<?= htmlspecialchars($key) ?>"><?= $badge['icon'] ?> <?= htmlspecialchars($badge['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">XP Amount</label>
                            <input type="number" name="xp_amount" id="campaign_xp" class="form-control" min="0" value="0" placeholder="0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Target Audience</label>
                        <select name="target_audience" id="campaign_audience" class="form-select" required onchange="toggleAudienceConfig()">
                            <option value="all_users">All Active Users</option>
                            <option value="new_users">New Users (joined in last 30 days)</option>
                            <option value="active_users">Active Users (logged in this week)</option>
                            <option value="inactive_users">Inactive Users (no login in 30+ days)</option>
                            <option value="level_range">Users at Specific Level Range</option>
                            <option value="badge_holders">Users with Specific Badge</option>
                        </select>

                        <!-- Level Range Config -->
                        <div id="levelRangeConfig" class="audience-config">
                            <div class="row">
                                <div class="col-6">
                                    <label class="form-label">Min Level</label>
                                    <input type="number" name="audience_config[min_level]" class="form-control" min="1" value="1">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Max Level</label>
                                    <input type="number" name="audience_config[max_level]" class="form-control" min="1" value="100">
                                </div>
                            </div>
                        </div>

                        <!-- Badge Holders Config -->
                        <div id="badgeHoldersConfig" class="audience-config">
                            <label class="form-label">Select Badge</label>
                            <select name="audience_config[badge_key]" class="form-select">
                                <?php foreach ($badges as $key => $badge): ?>
                                <option value="<?= htmlspecialchars($key) ?>"><?= $badge['icon'] ?> <?= htmlspecialchars($badge['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Recurring Schedule -->
                    <div id="recurringConfig" class="mb-3" style="display: none;">
                        <label class="form-label">Schedule</label>
                        <select name="schedule" id="campaign_schedule" class="form-select">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>

                    <!-- Preview Count -->
                    <div class="preview-count" id="previewCount" style="display: none;">
                        <i class="fa-solid fa-users"></i>
                        <span>This campaign will target <strong id="targetCount">0</strong> users</span>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" onclick="previewAudience()">
                            <i class="fa-solid fa-refresh"></i> Refresh
                        </button>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Campaign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleAudienceConfig() {
    const audience = document.getElementById('campaign_audience').value;

    document.querySelectorAll('.audience-config').forEach(el => el.classList.remove('show'));

    if (audience === 'level_range') {
        document.getElementById('levelRangeConfig').classList.add('show');
    } else if (audience === 'badge_holders') {
        document.getElementById('badgeHoldersConfig').classList.add('show');
    }

    previewAudience();
}

function toggleRecurringConfig() {
    const type = document.getElementById('campaign_type').value;
    const recurringConfig = document.getElementById('recurringConfig');
    recurringConfig.style.display = type === 'recurring' ? 'block' : 'none';
}

document.getElementById('campaign_type').addEventListener('change', toggleRecurringConfig);

function previewAudience() {
    const audience = document.getElementById('campaign_audience').value;
    const previewDiv = document.getElementById('previewCount');
    const countSpan = document.getElementById('targetCount');

    const formData = new FormData(document.getElementById('campaignForm'));

    fetch('<?= $basePath ?>/admin/gamification/campaigns/preview-audience', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        countSpan.textContent = data.count.toLocaleString();
        previewDiv.style.display = 'flex';
    })
    .catch(() => {
        previewDiv.style.display = 'none';
    });
}

function editCampaign(campaign) {
    document.getElementById('modalTitle').textContent = 'Edit Campaign';
    document.getElementById('campaign_id').value = campaign.id;
    document.getElementById('campaign_name').value = campaign.name;
    document.getElementById('campaign_description').value = campaign.description || '';
    document.getElementById('campaign_type').value = campaign.type;
    document.getElementById('campaign_badge').value = campaign.badge_key || '';
    document.getElementById('campaign_xp').value = campaign.xp_amount || 0;
    document.getElementById('campaign_audience').value = campaign.target_audience;
    document.getElementById('campaign_schedule').value = campaign.schedule || 'weekly';

    toggleAudienceConfig();
    toggleRecurringConfig();

    const config = JSON.parse(campaign.audience_config || '{}');
    if (config.min_level) {
        document.querySelector('[name="audience_config[min_level]"]').value = config.min_level;
    }
    if (config.max_level) {
        document.querySelector('[name="audience_config[max_level]"]').value = config.max_level;
    }
    if (config.badge_key) {
        document.querySelector('[name="audience_config[badge_key]"]').value = config.badge_key;
    }

    new bootstrap.Modal(document.getElementById('campaignModal')).show();
}

// Reset form when modal opens fresh
document.getElementById('campaignModal').addEventListener('show.bs.modal', function(e) {
    if (!e.relatedTarget) return; // Skip if opened programmatically

    document.getElementById('modalTitle').textContent = 'Create Campaign';
    document.getElementById('campaignForm').reset();
    document.getElementById('campaign_id').value = '';
    document.getElementById('previewCount').style.display = 'none';
    document.querySelectorAll('.audience-config').forEach(el => el.classList.remove('show'));
    toggleRecurringConfig();
});

// Initial preview on page load
document.addEventListener('DOMContentLoaded', previewAudience);
</script>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
