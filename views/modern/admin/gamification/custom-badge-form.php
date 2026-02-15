<?php
/**
 * Admin Custom Badge Form - Gold Standard
 * STANDALONE admin interface - Complete redesign
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$badge = $badge ?? null;
$isEdit = $isEdit ?? false;

// Admin header configuration
$adminPageTitle = $isEdit ? 'Edit Custom Badge' : 'Create Custom Badge';
$adminPageSubtitle = 'Gamification';
$adminPageIcon = 'fa-medal';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<style>
/* Badge Form Specific Styles */
.form-layout {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 2rem;
    align-items: start;
}

.form-main {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.9));
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    overflow: hidden;
}

.form-header {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.15));
    padding: 2rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.2);
}

.form-header-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2));
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #a5b4fc;
    margin-bottom: 1rem;
}

.form-header-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 0.5rem 0;
}

.form-header-subtitle {
    color: rgba(255, 255, 255, 0.6);
    margin: 0;
}

.form-body {
    padding: 2rem;
}

.form-group {
    margin-bottom: 1.75rem;
}

.form-label {
    display: block;
    color: rgba(255, 255, 255, 0.9);
    font-weight: 600;
    margin-bottom: 0.75rem;
    font-size: 0.9375rem;
}

.form-label i {
    color: rgba(99, 102, 241, 0.8);
    margin-right: 0.5rem;
}

.form-input,
.form-textarea,
.form-select {
    width: 100%;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 10px;
    padding: 0.875rem 1rem;
    color: #fff;
    font-size: 0.9375rem;
    transition: all 0.2s ease;
    font-family: inherit;
}

.form-input:focus,
.form-textarea:focus,
.form-select:focus {
    outline: none;
    border-color: rgba(99, 102, 241, 0.6);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    background: rgba(0, 0, 0, 0.4);
}

.form-input::placeholder,
.form-textarea::placeholder {
    color: rgba(255, 255, 255, 0.3);
}

.form-textarea {
    resize: vertical;
    min-height: 100px;
}

.form-help {
    display: block;
    margin-top: 0.5rem;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8125rem;
}

.icon-picker-wrapper {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}

.icon-input-group {
    flex: 1;
}

.icon-preview-large {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    border: 2px solid rgba(99, 102, 241, 0.3);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3.5rem;
    filter: drop-shadow(0 0 20px currentColor);
    transition: all 0.3s ease;
}

.icon-preview-large:hover {
    transform: scale(1.05);
    border-color: rgba(99, 102, 241, 0.5);
}

.icon-grid {
    display: grid;
    grid-template-columns: repeat(10, 1fr);
    gap: 0.5rem;
    margin-top: 0.75rem;
}

.icon-option {
    width: 100%;
    aspect-ratio: 1;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    font-size: 1.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.icon-option:hover {
    background: rgba(99, 102, 241, 0.2);
    border-color: rgba(99, 102, 241, 0.4);
    transform: scale(1.1);
}

.icon-option.selected {
    background: rgba(99, 102, 241, 0.3);
    border-color: rgba(99, 102, 241, 0.6);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
}

.toggle-group {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: rgba(99, 102, 241, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 10px;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 56px;
    height: 32px;
    flex-shrink: 0;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: 0.3s;
    border-radius: 34px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 24px;
    width: 24px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

input:checked + .toggle-slider {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    border-color: #22c55e;
}

input:checked + .toggle-slider:before {
    transform: translateX(24px);
}

.toggle-label {
    color: rgba(255, 255, 255, 0.9);
    font-weight: 600;
}

.form-sidebar {
    position: sticky;
    top: 2rem;
}

.preview-card {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.9));
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    overflow: hidden;
}

.preview-header {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.15));
    padding: 1.5rem;
    text-align: center;
    border-bottom: 1px solid rgba(99, 102, 241, 0.2);
}

.preview-label {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
    margin-bottom: 1rem;
}

.preview-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    filter: drop-shadow(0 0 20px currentColor);
    animation: previewFloat 3s ease-in-out infinite;
}

@keyframes previewFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}

.preview-status {
    margin-top: 1rem;
}

.preview-body {
    padding: 1.5rem;
}

.preview-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 0.75rem;
    text-align: center;
}

.preview-description {
    color: rgba(255, 255, 255, 0.65);
    font-size: 0.875rem;
    line-height: 1.6;
    margin-bottom: 1.5rem;
    text-align: center;
}

.preview-meta {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

.preview-meta-item {
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 8px;
    padding: 0.75rem;
    text-align: center;
}

.preview-meta-value {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
    font-size: 1rem;
    font-weight: 700;
    color: #a5b4fc;
    margin-bottom: 0.25rem;
}

.preview-meta-label {
    font-size: 0.6875rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1.5rem 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.2);
}

@media (max-width: 1200px) {
    .form-layout {
        grid-template-columns: 1fr;
    }

    .form-sidebar {
        position: static;
    }

    .icon-grid {
        grid-template-columns: repeat(8, 1fr);
    }
}

@media (max-width: 576px) {
    .icon-grid {
        grid-template-columns: repeat(5, 1fr);
    }

    .icon-picker-wrapper {
        flex-direction: column;
        align-items: center;
    }

    .preview-meta {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-medal"></i>
            <?= $isEdit ? 'Edit' : 'Create' ?> Custom Badge
        </h1>
        <p class="admin-page-subtitle">
            <?= $isEdit ? 'Update badge details and configuration' : 'Design a new custom badge for your community' ?>
        </p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/custom-badges" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to Badges
        </a>
    </div>
</div>

<!-- Flash Messages -->
<?php if (!empty($_SESSION['flash_error'])): ?>
<div class="admin-alert admin-alert-error">
    <i class="fa-solid fa-exclamation-circle"></i>
    <div><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
</div>
<?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<!-- Form Layout -->
<form method="POST" action="<?= $basePath ?>/admin-legacy/custom-badges/<?= $isEdit ? 'update' : 'store' ?>" id="badgeForm" class="form-layout">
    <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
    <?php if ($isEdit && $badge): ?>
    <input type="hidden" name="id" value="<?= $badge['id'] ?>">
    <?php endif; ?>

    <!-- Main Form -->
    <div class="form-main">
        <div class="form-header">
            <div class="form-header-icon">
                <i class="fa-solid fa-medal"></i>
            </div>
            <h2 class="form-header-title"><?= $isEdit ? 'Edit Badge' : 'Create New Badge' ?></h2>
            <p class="form-header-subtitle">Configure your custom badge details and properties</p>
        </div>

        <div class="form-body">
            <!-- Badge Name -->
            <div class="form-group">
                <label class="form-label" for="name">
                    <i class="fa-solid fa-heading"></i> Badge Name *
                </label>
                <input type="text"
                       id="name"
                       name="name"
                       class="form-input"
                       value="<?= $badge ? htmlspecialchars($badge['name']) : '' ?>"
                       placeholder="e.g., Community Champion, Top Contributor"
                       required
                       maxlength="100"
                       oninput="updatePreview()">
                <small class="form-help">A clear, memorable name that describes the achievement</small>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label class="form-label" for="description">
                    <i class="fa-solid fa-align-left"></i> Description *
                </label>
                <textarea id="description"
                          name="description"
                          class="form-textarea"
                          placeholder="Describe what this badge represents and how members can earn it..."
                          required
                          maxlength="500"
                          oninput="updatePreview()"><?= $badge ? htmlspecialchars($badge['description']) : '' ?></textarea>
                <small class="form-help">Explain what this badge represents and the criteria for earning it</small>
            </div>

            <!-- Icon Picker -->
            <div class="form-group">
                <label class="form-label" for="icon">
                    <i class="fa-solid fa-icons"></i> Badge Icon *
                </label>
                <div class="icon-picker-wrapper">
                    <div class="icon-input-group">
                        <input type="text"
                               id="icon"
                               name="icon"
                               class="form-input"
                               value="<?= $badge ? htmlspecialchars($badge['icon']) : '🏆' ?>"
                               required
                               maxlength="10"
                               oninput="updatePreview()">
                        <small class="form-help">
                            <i class="fa-solid fa-lightbulb"></i> Choose an emoji icon or enter your own
                        </small>

                        <!-- Popular Icons Grid -->
                        <div class="icon-grid">
                            <?php
                            $popularIcons = ['🏆', '🌟', '⭐', '🎖️', '🥇', '🥈', '🥉', '👑', '💎', '🔥',
                                           '🎯', '🚀', '💪', '🎊', '🎉', '🏅', '✨', '🌈', '⚡', '💫'];
                            foreach ($popularIcons as $emoji):
                            ?>
                            <button type="button"
                                    class="icon-option"
                                    onclick="selectIcon('<?= $emoji ?>')"
                                    data-icon="<?= $emoji ?>"><?= $emoji ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="icon-preview-large" id="iconPreview">
                        <?= $badge ? htmlspecialchars($badge['icon']) : '🏆' ?>
                    </div>
                </div>
            </div>

            <!-- XP Value -->
            <div class="form-group">
                <label class="form-label" for="xp">
                    <i class="fa-solid fa-star"></i> XP Value *
                </label>
                <input type="number"
                       id="xp"
                       name="xp"
                       class="form-input"
                       value="<?= $badge ? $badge['xp'] : '50' ?>"
                       required
                       min="0"
                       max="10000"
                       step="10"
                       oninput="updatePreview()">
                <small class="form-help">Experience points awarded when a member earns this badge</small>
            </div>

            <!-- Category -->
            <div class="form-group">
                <label class="form-label" for="category">
                    <i class="fa-solid fa-tag"></i> Category *
                </label>
                <select id="category" name="category" class="form-select" required oninput="updatePreview()">
                    <option value="special" <?= $badge && $badge['category'] === 'special' ? 'selected' : '' ?>>🎯 Special</option>
                    <option value="achievement" <?= $badge && $badge['category'] === 'achievement' ? 'selected' : '' ?>>🏆 Achievement</option>
                    <option value="participation" <?= $badge && $badge['category'] === 'participation' ? 'selected' : '' ?>>🙌 Participation</option>
                    <option value="milestone" <?= $badge && $badge['category'] === 'milestone' ? 'selected' : '' ?>>📊 Milestone</option>
                    <option value="community" <?= $badge && $badge['category'] === 'community' ? 'selected' : '' ?>>👥 Community</option>
                    <option value="expertise" <?= $badge && $badge['category'] === 'expertise' ? 'selected' : '' ?>>💡 Expertise</option>
                </select>
                <small class="form-help">Organize badges by type for easier management</small>
            </div>

            <!-- Active Status -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fa-solid fa-toggle-on"></i> Badge Status
                </label>
                <div class="toggle-group">
                    <label class="toggle-switch">
                        <input type="checkbox"
                               id="is_active"
                               name="is_active"
                               <?= $badge ? ($badge['is_active'] ? 'checked' : '') : 'checked' ?>
                               onchange="updatePreview()">
                        <span class="toggle-slider"></span>
                    </label>
                    <div>
                        <div class="toggle-label">Badge is Active</div>
                        <small class="form-help" style="margin: 0;">Inactive badges cannot be awarded to members</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="<?= $basePath ?>/admin-legacy/custom-badges" class="admin-btn admin-btn-secondary">
                <i class="fa-solid fa-times"></i> Cancel
            </a>
            <button type="submit" class="admin-btn admin-btn-primary">
                <i class="fa-solid fa-<?= $isEdit ? 'save' : 'plus' ?>"></i>
                <?= $isEdit ? 'Update Badge' : 'Create Badge' ?>
            </button>
        </div>
    </div>

    <!-- Preview Sidebar -->
    <div class="form-sidebar">
        <div class="preview-card">
            <div class="preview-header">
                <div class="preview-label">Live Preview</div>
                <div class="preview-icon" id="previewIcon"><?= $badge ? htmlspecialchars($badge['icon']) : '🏆' ?></div>
                <div class="preview-status">
                    <span class="status-pill active" id="previewStatus">
                        <span class="status-dot"></span>
                        <span id="previewStatusText"><?= $badge && !$badge['is_active'] ? 'Inactive' : 'Active' ?></span>
                    </span>
                </div>
            </div>

            <div class="preview-body">
                <h3 class="preview-name" id="previewName">
                    <?= $badge ? htmlspecialchars($badge['name']) : 'Badge Name' ?>
                </h3>
                <p class="preview-description" id="previewDescription">
                    <?= $badge ? htmlspecialchars($badge['description']) : 'Badge description will appear here...' ?>
                </p>

                <div class="preview-meta">
                    <div class="preview-meta-item">
                        <div class="preview-meta-value">
                            <i class="fa-solid fa-star"></i>
                            <span id="previewXP"><?= $badge ? $badge['xp'] : '50' ?></span>
                        </div>
                        <div class="preview-meta-label">XP Value</div>
                    </div>
                    <div class="preview-meta-item">
                        <div class="preview-meta-value">
                            <i class="fa-solid fa-tag"></i>
                            <span id="previewCategory"><?= $badge ? ucfirst($badge['category']) : 'Special' ?></span>
                        </div>
                        <div class="preview-meta-label">Category</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Icon selection
function selectIcon(emoji) {
    document.getElementById('icon').value = emoji;

    // Update visual selection
    document.querySelectorAll('.icon-option').forEach(btn => {
        btn.classList.remove('selected');
        if (btn.getAttribute('data-icon') === emoji) {
            btn.classList.add('selected');
        }
    });

    updatePreview();
}

// Live preview updates
function updatePreview() {
    const icon = document.getElementById('icon').value || '🏆';
    const name = document.getElementById('name').value || 'Badge Name';
    const description = document.getElementById('description').value || 'Badge description will appear here...';
    const xp = document.getElementById('xp').value || '50';
    const category = document.getElementById('category');
    const categoryText = category.options[category.selectedIndex].text.replace(/^.+\s/, '');
    const isActive = document.getElementById('is_active').checked;

    document.getElementById('iconPreview').textContent = icon;
    document.getElementById('previewIcon').textContent = icon;
    document.getElementById('previewName').textContent = name;
    document.getElementById('previewDescription').textContent = description;
    document.getElementById('previewXP').textContent = xp;
    document.getElementById('previewCategory').textContent = categoryText;

    // Update status pill
    const statusPill = document.getElementById('previewStatus');
    const statusText = document.getElementById('previewStatusText');
    if (isActive) {
        statusPill.className = 'status-pill active';
        statusText.textContent = 'Active';
    } else {
        statusPill.className = 'status-pill inactive';
        statusText.textContent = 'Inactive';
    }
}

// Initialize selected icon on load
document.addEventListener('DOMContentLoaded', function() {
    const currentIcon = document.getElementById('icon').value;
    document.querySelectorAll('.icon-option').forEach(btn => {
        if (btn.getAttribute('data-icon') === currentIcon) {
            btn.classList.add('selected');
        }
    });
});

// Form validation
document.getElementById('badgeForm').addEventListener('submit', function(e) {
    const name = document.getElementById('name').value.trim();
    const description = document.getElementById('description').value.trim();
    const icon = document.getElementById('icon').value.trim();
    const xp = parseInt(document.getElementById('xp').value);

    if (!name || name.length < 3) {
        e.preventDefault();
        alert('Badge name must be at least 3 characters long');
        document.getElementById('name').focus();
        return false;
    }

    if (!description || description.length < 10) {
        e.preventDefault();
        alert('Badge description must be at least 10 characters long');
        document.getElementById('description').focus();
        return false;
    }

    if (!icon) {
        e.preventDefault();
        alert('Please select or enter a badge icon');
        document.getElementById('icon').focus();
        return false;
    }

    if (isNaN(xp) || xp < 0 || xp > 10000) {
        e.preventDefault();
        alert('XP value must be between 0 and 10,000');
        document.getElementById('xp').focus();
        return false;
    }
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
