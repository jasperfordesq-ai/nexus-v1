<?php
$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/admin-legacy/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= $basePath ?>/admin">Admin</a></li>
                    <li class="breadcrumb-item"><a href="<?= $basePath ?>/admin-legacy/custom-badges">Custom Badges</a></li>
                    <li class="breadcrumb-item active"><?= $isEdit ? 'Edit' : 'Create' ?> Badge</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">
                <i class="fa-solid fa-medal text-warning"></i>
                <?= $isEdit ? 'Edit Badge' : 'Create Custom Badge' ?>
            </h1>
        </div>
    </div>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $_SESSION['flash_error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="<?= $basePath ?>/admin-legacy/custom-badges/<?= $isEdit ? 'update' : 'store' ?>">
                        <input type="hidden" name="csrf_token" value="<?= \Nexus\Core\Csrf::token() ?>">
                        <?php if ($isEdit): ?>
                            <input type="hidden" name="id" value="<?= $badge['id'] ?>">
                        <?php endif; ?>

                        <div class="row mb-4">
                            <div class="col-md-2">
                                <label class="form-label">Icon</label>
                                <div class="icon-picker-wrapper">
                                    <input type="text" name="icon" id="badgeIcon" class="form-control text-center"
                                           value="<?= htmlspecialchars($badge['icon'] ?? '🏆') ?>"
                                           style="font-size: 32px; height: 60px;" maxlength="4">
                                </div>
                                <div class="form-text">Pick an emoji</div>
                            </div>
                            <div class="col-md-10">
                                <label class="form-label">Badge Name *</label>
                                <input type="text" name="name" class="form-control form-control-lg" required
                                       value="<?= htmlspecialchars($badge['name'] ?? '') ?>"
                                       placeholder="e.g., Super Helper, Early Adopter">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"
                                      placeholder="Describe what this badge represents"><?= htmlspecialchars($badge['description'] ?? '') ?></textarea>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">XP Reward</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-star text-warning"></i></span>
                                    <input type="number" name="xp" class="form-control" min="0" max="1000"
                                           value="<?= (int)($badge['xp'] ?? 50) ?>">
                                    <span class="input-group-text">XP</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select">
                                    <option value="special" <?= ($badge['category'] ?? '') === 'special' ? 'selected' : '' ?>>Special</option>
                                    <option value="achievement" <?= ($badge['category'] ?? '') === 'achievement' ? 'selected' : '' ?>>Achievement</option>
                                    <option value="milestone" <?= ($badge['category'] ?? '') === 'milestone' ? 'selected' : '' ?>>Milestone</option>
                                    <option value="community" <?= ($badge['category'] ?? '') === 'community' ? 'selected' : '' ?>>Community</option>
                                    <option value="event" <?= ($badge['category'] ?? '') === 'event' ? 'selected' : '' ?>>Event</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                                       <?= ($badge['is_active'] ?? true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isActive">
                                    <strong>Active</strong> - Badge can be awarded to users
                                </label>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <a href="<?= $basePath ?>/admin-legacy/custom-badges" class="btn btn-outline-secondary">
                                <i class="fa-solid fa-arrow-left"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-save"></i> <?= $isEdit ? 'Update Badge' : 'Create Badge' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fa-solid fa-eye"></i> Preview</h5>
                </div>
                <div class="card-body text-center py-5">
                    <div id="previewIcon" style="font-size: 64px; margin-bottom: 16px;"><?= $badge['icon'] ?? '🏆' ?></div>
                    <h4 id="previewName"><?= htmlspecialchars($badge['name'] ?? 'Badge Name') ?></h4>
                    <p class="text-muted" id="previewDesc"><?= htmlspecialchars($badge['description'] ?? 'Badge description will appear here') ?></p>
                    <span class="badge bg-primary" id="previewXP">+<?= $badge['xp'] ?? 50 ?> XP</span>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fa-solid fa-lightbulb"></i> Quick Emoji Picker</h5>
                </div>
                <div class="card-body">
                    <div class="emoji-grid">
                        <?php
                        $emojis = ['🏆', '🥇', '🥈', '🥉', '🎖️', '🏅', '⭐', '🌟', '✨', '💫', '🔥', '❤️', '💪', '🎯', '🚀', '💎', '👑', '🎪', '🎭', '🎨', '🎬', '📚', '🎓', '🏠', '🌈', '🦋', '🌸', '🍀', '☀️', '🌙'];
                        foreach ($emojis as $emoji):
                        ?>
                        <button type="button" class="btn btn-light emoji-btn" onclick="selectEmoji('<?= $emoji ?>')">
                            <?= $emoji ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.emoji-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 8px;
}
.emoji-btn {
    font-size: 20px;
    padding: 8px;
}
.emoji-btn:hover {
    background: #e9ecef;
    transform: scale(1.1);
}
</style>

<script>
function selectEmoji(emoji) {
    document.getElementById('badgeIcon').value = emoji;
    document.getElementById('previewIcon').textContent = emoji;
}

// Live preview updates
document.querySelector('input[name="name"]').addEventListener('input', function() {
    document.getElementById('previewName').textContent = this.value || 'Badge Name';
});

document.querySelector('textarea[name="description"]').addEventListener('input', function() {
    document.getElementById('previewDesc').textContent = this.value || 'Badge description will appear here';
});

document.querySelector('input[name="xp"]').addEventListener('input', function() {
    document.getElementById('previewXP').textContent = '+' + (this.value || 0) + ' XP';
});

document.getElementById('badgeIcon').addEventListener('input', function() {
    document.getElementById('previewIcon').textContent = this.value || '🏆';
});
</script>

<?php require dirname(__DIR__, 2) . '/layouts/admin-legacy/footer.php'; ?>
