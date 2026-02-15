<?php
/**
 * Admin Custom Badges - Gold Standard
 * STANDALONE admin interface - Complete redesign
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Core\Database;

$basePath = TenantContext::getBasePath();
$tenantId = TenantContext::getId();
$badges = $badges ?? [];

// Admin header configuration
$adminPageTitle = 'Custom Badges';
$adminPageSubtitle = 'Gamification';
$adminPageIcon = 'fa-medal';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<style>
/* Custom Badges Specific Styles */
.badges-stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card-compact {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.9));
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
}

.stat-card-compact:hover {
    border-color: rgba(99, 102, 241, 0.4);
    transform: translateY(-2px);
}

.stat-card-compact .stat-value {
    font-size: 2rem;
    font-weight: 700;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
}

.stat-card-compact .stat-label {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badges-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 1.5rem;
}

.badge-card {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.9));
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    padding: 0;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
}

.badge-card:hover {
    border-color: rgba(99, 102, 241, 0.5);
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(99, 102, 241, 0.15);
}

.badge-card.inactive {
    opacity: 0.5;
}

.badge-card-header {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.15));
    padding: 2rem;
    text-align: center;
    position: relative;
    border-bottom: 1px solid rgba(99, 102, 241, 0.2);
}

.badge-icon-display {
    font-size: 5rem;
    line-height: 1;
    margin-bottom: 1rem;
    filter: drop-shadow(0 0 30px currentColor);
    animation: badgeFloat 3s ease-in-out infinite;
}

@keyframes badgeFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.badge-status-indicator {
    position: absolute;
    top: 1rem;
    right: 1rem;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-pill.active {
    background: rgba(34, 197, 94, 0.15);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.status-pill.inactive {
    background: rgba(148, 163, 184, 0.15);
    border: 1px solid rgba(148, 163, 184, 0.3);
    color: #94a3b8;
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    animation: statusPulse 2s ease-in-out infinite;
}

@keyframes statusPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.badge-card-body {
    padding: 1.5rem 2rem;
}

.badge-name {
    font-size: 1.375rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 0.75rem 0;
    line-height: 1.3;
}

.badge-description {
    color: rgba(255, 255, 255, 0.65);
    font-size: 0.9375rem;
    line-height: 1.6;
    margin-bottom: 1.5rem;
    min-height: 3rem;
}

.badge-meta-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.badge-meta-item {
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 10px;
    padding: 0.75rem;
    text-align: center;
}

.meta-value {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 1.125rem;
    font-weight: 700;
    color: #a5b4fc;
    margin-bottom: 0.25rem;
}

.meta-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-stats-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 1.5rem;
}

.badge-stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.875rem;
}

.badge-stat-item i {
    color: rgba(99, 102, 241, 0.8);
}

.badge-actions {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
}

.action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.action-btn-edit {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2));
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: #a5b4fc;
}

.action-btn-edit:hover {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.3), rgba(139, 92, 246, 0.3));
    border-color: rgba(99, 102, 241, 0.5);
    transform: translateY(-1px);
}

.action-btn-award {
    background: rgba(34, 197, 94, 0.15);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.action-btn-award:hover {
    background: rgba(34, 197, 94, 0.25);
    border-color: rgba(34, 197, 94, 0.5);
    transform: translateY(-1px);
}

.action-btn-delete {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.action-btn-delete:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.5);
    transform: translateY(-1px);
}

/* Empty State */
.empty-state-card {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.9));
    border: 2px dashed rgba(99, 102, 241, 0.3);
    border-radius: 16px;
    padding: 4rem 2rem;
    text-align: center;
}

.empty-state-icon {
    font-size: 5rem;
    margin-bottom: 1.5rem;
    opacity: 0.3;
}

.empty-state-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 0.75rem;
}

.empty-state-text {
    color: rgba(255, 255, 255, 0.6);
    margin-bottom: 2rem;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(8px);
    z-index: 9998;
    animation: fadeIn 0.2s ease;
}

.modal-overlay.active {
    display: block;
}

.modal-container {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
    overflow-y: auto;
    padding: 2rem;
}

.modal-container.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.98), rgba(30, 41, 59, 0.98));
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 16px;
    max-width: 600px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
    animation: modalSlideIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0;
}

.modal-close {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: rgba(255, 255, 255, 0.6);
    width: 36px;
    height: 36px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    color: rgba(255, 255, 255, 0.9);
    font-weight: 600;
    margin-bottom: 0.75rem;
    font-size: 0.9375rem;
}

.form-select {
    width: 100%;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 10px;
    padding: 0.75rem;
    color: #fff;
    font-size: 0.9375rem;
    min-height: 200px;
}

.form-select option {
    background: #1e293b;
    padding: 0.75rem;
}

.form-help {
    display: block;
    margin-top: 0.5rem;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8125rem;
}

.alert-box {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-radius: 10px;
    margin-top: 1.5rem;
}

.alert-warning {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: #fbbf24;
}

@media (max-width: 992px) {
    .badges-stats-row {
        grid-template-columns: repeat(2, 1fr);
    }

    .badges-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .badges-stats-row {
        grid-template-columns: 1fr;
    }

    .badge-meta-grid {
        grid-template-columns: 1fr;
    }

    .badge-actions {
        grid-template-columns: 1fr;
    }

    .modal-container {
        padding: 1rem;
    }
}
</style>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-medal"></i>
            Custom Badges
        </h1>
        <p class="admin-page-subtitle">Create and manage custom badges to reward your community members</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/gamification" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-gamepad"></i> Gamification Hub
        </a>
        <a href="<?= $basePath ?>/admin-legacy/custom-badges/create" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i> Create Badge
        </a>
    </div>
</div>

<!-- Flash Messages -->
<?php if (!empty($_SESSION['flash_success'])): ?>
<div class="admin-alert admin-alert-success">
    <i class="fa-solid fa-check-circle"></i>
    <div><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
</div>
<?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
<div class="admin-alert admin-alert-error">
    <i class="fa-solid fa-exclamation-circle"></i>
    <div><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
</div>
<?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<?php if (!empty($badges)): ?>
<!-- Stats Overview -->
<div class="badges-stats-row">
    <div class="stat-card-compact">
        <div class="stat-value"><?= count($badges) ?></div>
        <div class="stat-label">Total Badges</div>
    </div>
    <div class="stat-card-compact">
        <div class="stat-value"><?= count(array_filter($badges, fn($b) => $b['is_active'])) ?></div>
        <div class="stat-label">Active</div>
    </div>
    <div class="stat-card-compact">
        <div class="stat-value"><?= number_format(array_sum(array_column($badges, 'award_count'))) ?></div>
        <div class="stat-label">Total Awarded</div>
    </div>
    <div class="stat-card-compact">
        <div class="stat-value"><?= number_format(array_sum(array_column($badges, 'xp'))) ?></div>
        <div class="stat-label">Total XP Value</div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($badges)): ?>
<!-- Empty State -->
<div class="empty-state-card">
    <div class="empty-state-icon">
        <i class="fa-solid fa-medal"></i>
    </div>
    <h3 class="empty-state-title">No Custom Badges Yet</h3>
    <p class="empty-state-text">
        Create your first custom badge to recognize and reward community members for special achievements, contributions, or milestones.
    </p>
    <a href="<?= $basePath ?>/admin-legacy/custom-badges/create" class="admin-btn admin-btn-primary">
        <i class="fa-solid fa-plus"></i> Create Your First Badge
    </a>
</div>
<?php else: ?>
<!-- Badges Grid -->
<div class="badges-grid">
    <?php foreach ($badges as $badge): ?>
    <div class="badge-card <?= $badge['is_active'] ? '' : 'inactive' ?>">
        <!-- Card Header -->
        <div class="badge-card-header">
            <div class="badge-status-indicator">
                <?php if ($badge['is_active']): ?>
                <span class="status-pill active">
                    <span class="status-dot"></span> Active
                </span>
                <?php else: ?>
                <span class="status-pill inactive">
                    <span class="status-dot"></span> Inactive
                </span>
                <?php endif; ?>
            </div>
            <div class="badge-icon-display"><?= $badge['icon'] ?></div>
        </div>

        <!-- Card Body -->
        <div class="badge-card-body">
            <h3 class="badge-name"><?= htmlspecialchars($badge['name']) ?></h3>
            <p class="badge-description"><?= htmlspecialchars($badge['description']) ?></p>

            <!-- Meta Grid -->
            <div class="badge-meta-grid">
                <div class="badge-meta-item">
                    <div class="meta-value">
                        <i class="fa-solid fa-star"></i>
                        <?= number_format($badge['xp']) ?>
                    </div>
                    <div class="meta-label">XP Value</div>
                </div>
                <div class="badge-meta-item">
                    <div class="meta-value">
                        <i class="fa-solid fa-tag"></i>
                        <?= ucfirst($badge['category']) ?>
                    </div>
                    <div class="meta-label">Category</div>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="badge-stats-bar">
                <div class="badge-stat-item">
                    <i class="fa-solid fa-users"></i>
                    <span><?= number_format($badge['award_count']) ?> awarded</span>
                </div>
                <div class="badge-stat-item">
                    <i class="fa-solid fa-calendar"></i>
                    <span><?= date('M j, Y', strtotime($badge['created_at'])) ?></span>
                </div>
            </div>

            <!-- Actions -->
            <div class="badge-actions">
                <a href="<?= $basePath ?>/admin-legacy/custom-badges/edit/<?= $badge['id'] ?>"
                   class="action-btn action-btn-edit">
                    <i class="fa-solid fa-pen"></i> Edit
                </a>
                <button type="button"
                        class="action-btn action-btn-award"
                        onclick="openAwardModal(<?= $badge['id'] ?>, '<?= htmlspecialchars($badge['name'], ENT_QUOTES) ?>', '<?= $badge['icon'] ?>')">
                    <i class="fa-solid fa-gift"></i> Award
                </button>
                <button type="button"
                        class="action-btn action-btn-delete"
                        onclick="openDeleteModal(<?= $badge['id'] ?>, '<?= htmlspecialchars($badge['name'], ENT_QUOTES) ?>')">
                    <i class="fa-solid fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Award Modal -->
<div class="modal" role="dialog" aria-modal="true"-overlay" id="awardModalOverlay" onclick="closeAwardModal()"></div>
<div class="modal" role="dialog" aria-modal="true"-container" id="awardModal">
    <div class="modal" role="dialog" aria-modal="true"-content" onclick="event.stopPropagation()">
        <form method="POST" action="<?= $basePath ?>/admin-legacy/custom-badges/award">
            <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
            <input type="hidden" name="badge_id" id="awardBadgeId">

            <div class="modal" role="dialog" aria-modal="true"-header">
                <h3 class="modal" role="dialog" aria-modal="true"-title">
                    <span id="awardBadgeIcon"></span>
                    Award Badge: <span id="awardBadgeName"></span>
                </h3>
                <button type="button" class="modal" role="dialog" aria-modal="true"-close" onclick="closeAwardModal()">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>

            <div class="modal" role="dialog" aria-modal="true"-body">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fa-solid fa-users"></i> Select Users to Award
                    </label>
                    <select name="user_ids[]" class="form-select" multiple required>
                        <?php
                        $users = Database::query(
                            "SELECT id, first_name, last_name, email FROM users WHERE tenant_id = ? ORDER BY first_name, last_name",
                            [$tenantId]
                        )->fetchAll();
                        foreach ($users as $user):
                        ?>
                        <option value="<?= $user['id'] ?>">
                            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                            &lt;<?= htmlspecialchars($user['email']) ?>&gt;
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-help">
                        <i class="fa-solid fa-info-circle"></i> Hold Ctrl (Windows) or Cmd (Mac) to select multiple users
                    </small>
                </div>
            </div>

            <div class="modal" role="dialog" aria-modal="true"-footer">
                <button type="button" class="admin-btn admin-btn-secondary" onclick="closeAwardModal()">
                    <i class="fa-solid fa-times"></i> Cancel
                </button>
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-gift"></i> Award Badge
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" role="dialog" aria-modal="true"-overlay" id="deleteModalOverlay" onclick="closeDeleteModal()"></div>
<div class="modal" role="dialog" aria-modal="true"-container" id="deleteModal">
    <div class="modal" role="dialog" aria-modal="true"-content" onclick="event.stopPropagation()" style="max-width: 500px;">
        <form method="POST" action="<?= $basePath ?>/admin-legacy/custom-badges/delete">
            <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
            <input type="hidden" name="id" id="deleteId">

            <div class="modal" role="dialog" aria-modal="true"-header">
                <h3 class="modal" role="dialog" aria-modal="true"-title">
                    <i class="fa-solid fa-trash" style="color: #ef4444;"></i>
                    Confirm Deletion
                </h3>
                <button type="button" class="modal" role="dialog" aria-modal="true"-close" onclick="closeDeleteModal()">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>

            <div class="modal" role="dialog" aria-modal="true"-body">
                <p style="color: rgba(255, 255, 255, 0.9); font-size: 1rem; margin-bottom: 1rem;">
                    Are you sure you want to delete <strong id="deleteName" style="color: #fff;"></strong>?
                </p>
                <div class="alert-box alert-warning">
                    <i class="fa-solid fa-exclamation-triangle" style="font-size: 1.25rem; flex-shrink: 0;"></i>
                    <div>
                        <strong style="display: block; margin-bottom: 0.25rem;">Warning!</strong>
                        This action cannot be undone. The badge will be removed from all users who have earned it.
                    </div>
                </div>
            </div>

            <div class="modal" role="dialog" aria-modal="true"-footer">
                <button type="button" class="admin-btn admin-btn-secondary" onclick="closeDeleteModal()">
                    <i class="fa-solid fa-times"></i> Cancel
                </button>
                <button type="submit" class="admin-btn admin-btn-danger">
                    <i class="fa-solid fa-trash"></i> Delete Badge
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Award Modal Functions
function openAwardModal(badgeId, badgeName, badgeIcon) {
    document.getElementById('awardBadgeId').value = badgeId;
    document.getElementById('awardBadgeName').textContent = badgeName;
    document.getElementById('awardBadgeIcon').textContent = badgeIcon;
    document.getElementById('awardModalOverlay').classList.add('active');
    document.getElementById('awardModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeAwardModal() {
    document.getElementById('awardModalOverlay').classList.remove('active');
    document.getElementById('awardModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Delete Modal Functions
function openDeleteModal(badgeId, badgeName) {
    document.getElementById('deleteId').value = badgeId;
    document.getElementById('deleteName').textContent = badgeName;
    document.getElementById('deleteModalOverlay').classList.add('active');
    document.getElementById('deleteModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModalOverlay').classList.remove('active');
    document.getElementById('deleteModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAwardModal();
        closeDeleteModal();
    }
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
