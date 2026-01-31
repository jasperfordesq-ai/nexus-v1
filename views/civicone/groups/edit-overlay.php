<?php
/**
 * Groups Edit Overlay - GOV.UK Design System
 * WCAG 2.1 AA Compliant Two-Tab Modal
 *
 * Features:
 * - Desktop: Modal overlay
 * - Mobile: Full-screen overlay
 * - Two tabs: Edit Settings and Invite Members
 * - Accessibility: ARIA labels, keyboard navigation
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
$tenantId = \Nexus\Core\TenantContext::getId();
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? 'User';

// Get flash messages
$error = $_SESSION['group_error'] ?? null;
$success = $_SESSION['group_success'] ?? null;
unset($_SESSION['group_error'], $_SESSION['group_success']);

$pageTitle = $pageTitle ?? 'Edit Group';
$defaultTab = $defaultTab ?? 'edit';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="theme-color" content="#1d70b8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/govuk-frontend-5.14.0/govuk-frontend.min.css">
    <link rel="stylesheet" href="/assets/css/design-tokens.min.css">
    <link rel="stylesheet" href="/assets/css/groups-edit-overlay.min.css">
    <style>
        /* Minimal reset - everything else in external CSS */
        * { box-sizing: border-box; margin: 0; padding: 0; }
    </style>
</head>
<body class="govuk-overlay-body">
    <div class="govuk-overlay-container" role="dialog" aria-modal="true" aria-labelledby="overlay-title">
        <!-- Header -->
        <div class="govuk-overlay-header">
            <button type="button" class="govuk-overlay-close-btn" onclick="closeEditOverlay()" aria-label="Close overlay">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <h1 id="overlay-title"><?= htmlspecialchars($group['name']) ?></h1>
        </div>

        <!-- Tab Navigation -->
        <nav class="govuk-overlay-tab-nav" role="tablist" aria-label="Group settings">
            <button type="button"
                    class="govuk-overlay-tab-btn <?= $defaultTab === 'edit' ? 'active' : '' ?>"
                    role="tab"
                    aria-selected="<?= $defaultTab === 'edit' ? 'true' : 'false' ?>"
                    aria-controls="panel-edit"
                    onclick="switchTab('edit')">
                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                Edit Settings
            </button>
            <button type="button"
                    class="govuk-overlay-tab-btn <?= $defaultTab === 'invite' ? 'active' : '' ?>"
                    role="tab"
                    aria-selected="<?= $defaultTab === 'invite' ? 'true' : 'false' ?>"
                    aria-controls="panel-invite"
                    onclick="switchTab('invite')">
                <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                Invite Members
            </button>
        </nav>

        <!-- Edit Settings Panel -->
        <div id="panel-edit" class="govuk-overlay-tab-panel <?= $defaultTab === 'edit' ? 'active' : '' ?>" role="tabpanel">
            <?php if ($error): ?>
            <div class="govuk-error-summary govuk-!-margin-bottom-4" role="alert" aria-labelledby="error-summary-title" tabindex="-1">
                <h2 class="govuk-error-summary__title" id="error-summary-title">There is a problem</h2>
                <div class="govuk-error-summary__body">
                    <p class="govuk-body"><?= htmlspecialchars($error) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="govuk-notification-banner govuk-notification-banner--success govuk-!-margin-bottom-4" role="alert">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title">Success</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading"><?= htmlspecialchars($success) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <form action="<?= $basePath ?>/groups/update" method="POST" enctype="multipart/form-data">
                <?= Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="group_id" value="<?= $group['id'] ?>">

                <!-- Group Name -->
                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="name">Group Name</label>
                    <input type="text" id="name" name="name" class="govuk-input" value="<?= htmlspecialchars($group['name']) ?>" required>
                </div>

                <!-- Description -->
                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="description">Description</label>
                    <textarea id="description" name="description" class="govuk-textarea" rows="4" required><?= htmlspecialchars($group['description']) ?></textarea>
                </div>

                <!-- Location -->
                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="location">
                        Location
                        <span class="govuk-hint civicone-hint-inline">(optional)</span>
                    </label>
                    <input type="text" id="location" name="location" class="govuk-input" value="<?= htmlspecialchars($group['location'] ?? '') ?>" placeholder="City, State or Region">
                </div>

                <!-- Group Type -->
                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="type_id">Group Type</label>
                    <select id="type_id" name="type_id" class="govuk-select" required>
                        <?php foreach ($groupTypes as $type): ?>
                            <option value="<?= $type['id'] ?>" <?= $group['type_id'] == $type['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Visibility -->
                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="visibility">Visibility</label>
                    <select id="visibility" name="visibility" class="govuk-select" required>
                        <option value="public" <?= $group['visibility'] === 'public' ? 'selected' : '' ?>>Public</option>
                        <option value="private" <?= $group['visibility'] === 'private' ? 'selected' : '' ?>>Private</option>
                    </select>
                    <div class="govuk-hint">Public groups are visible to everyone. Private groups require approval to join.</div>
                </div>

                <!-- Featured Hub (Admin Only) -->
                <?php if ($isAdmin): ?>
                <div class="govuk-form-group">
                    <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="is_featured" name="is_featured" type="checkbox" value="1" <?= !empty($group['is_featured']) ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-checkboxes__label" for="is_featured">
                                <strong>Featured Hub</strong>
                            </label>
                            <div class="govuk-hint govuk-checkboxes__hint">
                                Featured hubs appear in a special section at the top of the hubs page.
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Group Image -->
                <div class="govuk-form-group govuk-overlay-image-section">
                    <label class="govuk-label govuk-label--m">Group Image</label>
                    <?php
                        $groupImageSrc = !empty($group['image_url'])
                            ? htmlspecialchars($group['image_url'])
                            : '/assets/img/defaults/group-placeholder.webp';
                    ?>
                    <div class="govuk-overlay-image-preview-box">
                        <img src="<?= $groupImageSrc ?>" alt="Group image" id="imagePreview" loading="lazy">
                    </div>
                    <div class="govuk-overlay-image-actions">
                        <label class="govuk-overlay-upload-btn">
                            <i class="fa-solid fa-image" aria-hidden="true"></i>
                            Change Image
                            <input type="file" name="image" accept="image/*" onchange="previewImage(this, 'imagePreview')">
                        </label>
                        <?php if (!empty($group['image_url'])): ?>
                        <label class="govuk-overlay-clear-btn">
                            <input type="checkbox" name="clear_avatar" value="1">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                            Clear
                        </label>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Cover Image -->
                <div class="govuk-form-group govuk-overlay-image-section">
                    <label class="govuk-label govuk-label--m">Cover Image</label>
                    <?php
                        $coverImageSrc = !empty($group['cover_image_url'])
                            ? htmlspecialchars($group['cover_image_url'])
                            : '/assets/img/defaults/cover-placeholder.webp';
                    ?>
                    <div class="govuk-overlay-image-preview-box">
                        <img src="<?= $coverImageSrc ?>" alt="Cover image" id="coverPreview" loading="lazy">
                    </div>
                    <div class="govuk-overlay-image-actions">
                        <label class="govuk-overlay-upload-btn">
                            <i class="fa-solid fa-image" aria-hidden="true"></i>
                            Change Cover
                            <input type="file" name="cover_image" accept="image/*" onchange="previewImage(this, 'coverPreview')">
                        </label>
                        <?php if (!empty($group['cover_image_url'])): ?>
                        <label class="govuk-overlay-clear-btn">
                            <input type="checkbox" name="clear_cover" value="1">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                            Clear
                        </label>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-check govuk-!-margin-right-2" aria-hidden="true"></i>
                    Save Changes
                </button>
            </form>
        </div>

        <!-- Invite Members Panel -->
        <div id="panel-invite" class="govuk-overlay-tab-panel <?= $defaultTab === 'invite' ? 'active' : '' ?>" role="tabpanel">
            <p class="govuk-body govuk-!-margin-bottom-4 govuk-!-text-align-centre">
                Select members to invite to <strong><?= htmlspecialchars($group['name']) ?></strong>
            </p>

            <?php if (empty($availableUsers)): ?>
                <div class="govuk-overlay-no-users">
                    <i class="fa-solid fa-user-check"></i>
                    <p class="govuk-body">All community members are already in this group!</p>
                </div>
            <?php else: ?>
                <form action="<?= $basePath ?>/groups/<?= $group['id'] ?>/invite" method="POST" id="inviteForm">
                    <?= Nexus\Core\Csrf::input() ?>

                    <div class="govuk-form-group">
                        <input type="text" class="govuk-input" id="userSearch" placeholder="Search members by name..." aria-label="Search members">
                    </div>

                    <div class="govuk-overlay-user-list" id="userList" role="list">
                        <?php foreach ($availableUsers as $user): ?>
                            <label class="govuk-overlay-user-item" data-name="<?= strtolower(htmlspecialchars($user['name'])) ?>" role="listitem">
                                <input type="checkbox" name="user_ids[]" value="<?= $user['id'] ?>" aria-label="Select <?= htmlspecialchars($user['name']) ?>">
                                <div class="govuk-overlay-user-avatar">
                                    <?php if ($user['avatar_url']): ?>
                                        <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="" loading="lazy">
                                    <?php else: ?>
                                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="govuk-overlay-user-info">
                                    <div class="govuk-overlay-user-name"><?= htmlspecialchars($user['name']) ?></div>
                                    <?php if (!empty($user['email'])): ?>
                                        <div class="govuk-overlay-user-email"><?= htmlspecialchars($user['email']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="govuk-overlay-selected-count" id="selectedCount" aria-live="polite">0 members selected</div>

                    <div class="govuk-overlay-add-directly-box">
                        <label>
                            <input type="checkbox" name="add_directly" value="1" id="addDirectlyCheckbox">
                            <div>
                                <div class="govuk-overlay-add-directly-title">Add directly to group</div>
                                <div class="govuk-overlay-add-directly-desc">
                                    Skip the invitation step and add selected members immediately.
                                </div>
                            </div>
                        </label>
                    </div>

                    <button type="submit" class="govuk-button govuk-!-margin-top-4" data-module="govuk-button" id="submitBtn" disabled>
                        <i class="fa-solid fa-paper-plane govuk-!-margin-right-2" aria-hidden="true"></i>
                        Send Invitations
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function closeEditOverlay() {
            window.history.back();
        }

        function switchTab(tab) {
            document.querySelectorAll('.govuk-overlay-tab-btn').forEach(btn => {
                const isActive = btn.getAttribute('aria-controls') === 'panel-' + tab;
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            document.querySelectorAll('.govuk-overlay-tab-panel').forEach(panel => {
                panel.classList.toggle('active', panel.id === 'panel-' + tab);
            });
        }

        function previewImage(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById(previewId).src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // User search
        const searchInput = document.getElementById('userSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                document.querySelectorAll('.govuk-overlay-user-item').forEach(item => {
                    const name = item.dataset.name || '';
                    item.classList.toggle('govuk-hidden', !name.includes(query));
                });
            });
        }

        // Selection counter
        const userList = document.getElementById('userList');
        if (userList) {
            userList.addEventListener('change', updateSelectedCount);
        }

        function updateSelectedCount() {
            const checked = document.querySelectorAll('.govuk-overlay-user-item input:checked').length;
            const countEl = document.getElementById('selectedCount');
            const submitBtn = document.getElementById('submitBtn');
            if (countEl) countEl.textContent = checked + ' member' + (checked !== 1 ? 's' : '') + ' selected';
            if (submitBtn) submitBtn.disabled = checked === 0;
        }

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeEditOverlay();
        });

        // Click outside to close on desktop
        document.body.addEventListener('click', function(e) {
            if (e.target === document.body) closeEditOverlay();
        });
    </script>
</body>
</html>
