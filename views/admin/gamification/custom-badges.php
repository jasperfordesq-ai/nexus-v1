<?php
$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/modern/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0"><i class="fa-solid fa-medal text-warning"></i> Custom Badges</h1>
            <p class="text-muted">Create and manage custom badges for your community</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?= $basePath ?>/admin-legacy/custom-badges/create" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Create Badge
            </a>
        </div>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['flash_success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $_SESSION['flash_error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (empty($badges)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <div class="mb-3" style="font-size: 64px;">🏆</div>
                <h4>No Custom Badges Yet</h4>
                <p class="text-muted mb-4">Create your first custom badge to reward your community members.</p>
                <a href="<?= $basePath ?>/admin-legacy/custom-badges/create" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Create Your First Badge
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($badges as $badge): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 <?= $badge['is_active'] ? '' : 'opacity-50' ?>">
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-3">
                            <div class="badge-icon me-3" style="font-size: 48px;">
                                <?= $badge['icon'] ?>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1">
                                    <?= htmlspecialchars($badge['name']) ?>
                                    <?php if (!$badge['is_active']): ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </h5>
                                <p class="text-muted small mb-0"><?= htmlspecialchars($badge['description']) ?></p>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <span class="badge bg-primary">+<?= $badge['xp'] ?> XP</span>
                                <span class="badge bg-secondary"><?= ucfirst($badge['category']) ?></span>
                            </div>
                            <div class="text-muted small">
                                <i class="fa-solid fa-users"></i> <?= $badge['award_count'] ?> awarded
                            </div>
                        </div>

                        <div class="btn-group w-100">
                            <a href="<?= $basePath ?>/admin-legacy/custom-badges/edit/<?= $badge['id'] ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fa-solid fa-edit"></i> Edit
                            </a>
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="showAwardModal(<?= $badge['id'] ?>, '<?= htmlspecialchars($badge['name'], ENT_QUOTES) ?>')">
                                <i class="fa-solid fa-gift"></i> Award
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDelete(<?= $badge['id'] ?>, '<?= htmlspecialchars($badge['name'], ENT_QUOTES) ?>')">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent text-muted small">
                        Created <?= date('M j, Y', strtotime($badge['created_at'])) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Award Modal -->
<div class="modal fade" id="awardModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $basePath ?>/admin-legacy/custom-badges/award">
                <input type="hidden" name="csrf_token" value="<?= \Nexus\Core\Csrf::token() ?>">
                <input type="hidden" name="badge_id" id="awardBadgeId">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-gift"></i> Award Badge: <span id="awardBadgeName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Users</label>
                        <select name="user_ids[]" id="userSelect" class="form-select" multiple size="10" required>
                            <?php
                            $tenantId = \Nexus\Core\TenantContext::getId();
                            $users = \Nexus\Core\Database::query(
                                "SELECT id, first_name, last_name, email FROM users WHERE tenant_id = ? ORDER BY first_name",
                                [$tenantId]
                            )->fetchAll();
                            foreach ($users as $user):
                            ?>
                            <option value="<?= $user['id'] ?>">
                                <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> (<?= $user['email'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Hold Ctrl/Cmd to select multiple users</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fa-solid fa-gift"></i> Award Badge
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $basePath ?>/admin-legacy/custom-badges/delete">
                <input type="hidden" name="csrf_token" value="<?= \Nexus\Core\Csrf::token() ?>">
                <input type="hidden" name="id" id="deleteId">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-trash text-danger"></i> Delete Badge</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="deleteName"></strong>?</p>
                    <p class="text-danger"><i class="fa-solid fa-exclamation-triangle"></i> This will also remove the badge from all users who have earned it.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa-solid fa-trash"></i> Delete Badge
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showAwardModal(badgeId, badgeName) {
    document.getElementById('awardBadgeId').value = badgeId;
    document.getElementById('awardBadgeName').textContent = badgeName;
    new bootstrap.Modal(document.getElementById('awardModal')).show();
}

function confirmDelete(badgeId, badgeName) {
    document.getElementById('deleteId').value = badgeId;
    document.getElementById('deleteName').textContent = badgeName;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
