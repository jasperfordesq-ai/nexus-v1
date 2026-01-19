<?php
/**
 * Group Edit & Invite Overlay - Gold Standard Implementation
 *
 * Features:
 * - Desktop: Glassmorphism modal with holographic effects
 * - Mobile: Full-screen fixed overlay (100vw x 100vh)
 * - Two tabs: Edit Settings and Invite Members
 * - Horizontal scrollable pill navigation (YouTube/Instagram style)
 * - Dark mode by default
 * - Safe-area-inset support for notched devices
 * - PWA features: offline detection, haptic feedback
 * - Full accessibility: ARIA labels, keyboard navigation, focus trap
 * - All CSS and JS extracted to external files (gold standard compliance)
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
$tenantId = \Nexus\Core\TenantContext::getId();
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? 'User';
$userAvatar = $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp';

// Get flash messages
$error = $_SESSION['group_error'] ?? null;
$success = $_SESSION['group_success'] ?? null;
unset($_SESSION['group_error'], $_SESSION['group_success']);

$pageTitle = $pageTitle ?? 'Edit Group';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<!-- GOLD STANDARD OVERLAY V2 - LOADED -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="theme-color" content="#1e293b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Edit Overlay CSS -->
    <link rel="stylesheet" href="/assets/css/groups-edit-overlay.css">

    <!-- Edit Overlay JavaScript -->
    <script src="/assets/js/groups-edit-overlay.min.js" defer></script>
</head>
<body>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<!-- Backdrop with click-to-close on desktop -->
<div class="edit-backdrop" onclick="if(event.target === this) closeEditOverlay()">
    <!-- Main Overlay -->
    <div class="edit-overlay">

        <!-- Header -->
        <header class="edit-header">
            <div class="edit-header-left">
                <button type="button" class="edit-close-btn" onclick="closeEditOverlay()" aria-label="Close overlay">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <h1 class="edit-title"><?= htmlspecialchars($group['name']) ?></h1>
            </div>
        </header>

        <!-- Tab Navigation -->
        <nav class="edit-tabs" role="tablist" aria-label="Group settings">
            <div class="edit-tabs-inner">
                <button type="button"
                        class="edit-pill <?= $defaultTab === 'edit' ? 'active' : '' ?>"
                        data-type="edit"
                        role="tab"
                        aria-selected="<?= $defaultTab === 'edit' ? 'true' : 'false' ?>"
                        aria-controls="panel-edit"
                        onclick="switchTab('edit')">
                    <i class="fa-solid fa-pen-to-square"></i>
                    Edit Settings
                </button>
                <button type="button"
                        class="edit-pill <?= $defaultTab === 'invite' ? 'active' : '' ?>"
                        data-type="invite"
                        role="tab"
                        aria-selected="<?= $defaultTab === 'invite' ? 'true' : 'false' ?>"
                        aria-controls="panel-invite"
                        onclick="switchTab('invite')">
                    <i class="fa-solid fa-user-plus"></i>
                    Invite Members
                </button>
            </div>
        </nav>

        <!-- Content Area -->
        <main class="edit-content" id="contentArea">

            <!-- ============================================
                 PANEL 1: EDIT SETTINGS
                 ============================================ -->
            <div id="panel-edit"
                 class="edit-panel <?= $defaultTab === 'edit' ? 'active' : '' ?>"
                 role="tabpanel"
                 aria-labelledby="edit-tab">
                <form action="<?= $basePath ?>/groups/update"
                      method="POST"
                      enctype="multipart/form-data"
                      aria-label="Edit group settings">
                    <?= Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="group_id" value="<?= $group['id'] ?>">

                    <!-- Group Name -->
                    <div class="ed-field">
                        <label class="ed-label" for="name">Group Name</label>
                        <input type="text"
                               id="name"
                               name="name"
                               class="ed-input"
                               value="<?= htmlspecialchars($group['name']) ?>"
                               required
                               aria-required="true">
                    </div>

                    <!-- Description -->
                    <div class="ed-field">
                        <label class="ed-label" for="description">Description</label>
                        <textarea id="description"
                                  name="description"
                                  class="ed-textarea"
                                  required
                                  aria-required="true"><?= htmlspecialchars($group['description']) ?></textarea>
                    </div>

                    <!-- Location -->
                    <div class="ed-field">
                        <label class="ed-label" for="location">Location <span class="ed-label-hint">(Optional)</span></label>
                        <input type="text"
                               id="location"
                               name="location"
                               class="ed-input"
                               value="<?= htmlspecialchars($group['location'] ?? '') ?>"
                               placeholder="City, State or Region">
                    </div>

                    <!-- Group Type -->
                    <div class="ed-field">
                        <label class="ed-label" for="type_id">Group Type</label>
                        <select id="type_id"
                                name="type_id"
                                class="ed-select"
                                required
                                aria-required="true">
                            <?php foreach ($groupTypes as $type): ?>
                                <option value="<?= $type['id'] ?>"
                                        <?= $group['type_id'] == $type['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Visibility -->
                    <div class="ed-field">
                        <label class="ed-label" for="visibility">Visibility</label>
                        <select id="visibility"
                                name="visibility"
                                class="ed-select"
                                required
                                aria-required="true">
                            <option value="public" <?= $group['visibility'] === 'public' ? 'selected' : '' ?>>Public</option>
                            <option value="private" <?= $group['visibility'] === 'private' ? 'selected' : '' ?>>Private</option>
                        </select>
                        <div class="ed-hint">Public groups are visible to everyone. Private groups require approval to join.</div>
                    </div>

                    <!-- Featured Hub (Admin Only) -->
                    <?php if ($isAdmin): ?>
                    <div class="ed-checkbox-field">
                        <input type="checkbox"
                               id="is_featured"
                               name="is_featured"
                               value="1"
                               <?= !empty($group['is_featured']) ? 'checked' : '' ?>>
                        <div class="ed-checkbox-label">
                            <div class="ed-checkbox-title">⭐ Featured Hub</div>
                            <div class="ed-checkbox-desc">Featured hubs appear in a special section at the top of the hubs page. Only site administrators can mark groups as featured.</div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Group Image -->
                    <div class="ed-image-upload">
                        <label class="ed-label">Group Image</label>
                        <?php
                            $groupImageSrc = !empty($group['image_url'])
                                ? htmlspecialchars($group['image_url'])
                                : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 300'%3E%3Crect width='400' height='300' fill='%231e293b'/%3E%3Cg transform='translate(200,150)'%3E%3Ccircle r='50' fill='%23475569'/%3E%3Cpath d='M-20,-10 L-20,10 L0,0 Z M20,-10 L20,10 L0,0 Z' fill='%23cbd5e1'/%3E%3Ccircle cx='-15' cy='0' r='8' fill='%23cbd5e1'/%3E%3Ccircle cx='15' cy='0' r='8' fill='%23cbd5e1'/%3E%3C/g%3E%3Ctext x='200' y='270' text-anchor='middle' font-family='Arial' font-size='16' fill='%2394a3b8'%3EGroup Image%3C/text%3E%3C/svg%3E";
                        ?>
                        <img src="<?= $groupImageSrc ?>"
                             loading="lazy"
                             alt="Current group image"
                             class="ed-image-preview"
                             id="imagePreview"
                             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 400 300%27%3E%3Crect width=%27400%27 height=%27300%27 fill=%27%231e293b%27/%3E%3Cg transform=%27translate(200,150)%27%3E%3Ccircle r=%2750%27 fill=%27%23475569%27/%3E%3Cpath d=%27M-20,-10 L-20,10 L0,0 Z M20,-10 L20,10 L0,0 Z%27 fill=%27%23cbd5e1%27/%3E%3Ccircle cx=%27-15%27 cy=%270%27 r=%278%27 fill=%27%23cbd5e1%27/%3E%3Ccircle cx=%2715%27 cy=%270%27 r=%278%27 fill=%27%23cbd5e1%27/%3E%3C/g%3E%3Ctext x=%27200%27 y=%27270%27 text-anchor=%27middle%27 font-family=%27Arial%27 font-size=%2716%27 fill=%27%2394a3b8%27%3EGroup Image%3C/text%3E%3C/svg%3E'">
                        <div class="ed-image-actions">
                            <label class="ed-upload-btn">
                                <i class="fa-solid fa-image"></i>
                                Change Image
                                <input type="file"
                                       name="image"
                                       accept="image/*"
                                       onchange="previewImage(this, 'imagePreview')"
                                       aria-label="Upload group image">
                            </label>
                            <?php if (!empty($group['image_url'])): ?>
                            <label class="ed-clear-btn" title="Remove image">
                                <input type="checkbox" name="clear_avatar" value="1">
                                <i class="fa-solid fa-trash"></i>
                                Clear
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Cover Image -->
                    <div class="ed-image-upload">
                        <label class="ed-label">Cover Image</label>
                        <?php
                            $coverImageSrc = !empty($group['cover_image_url'])
                                ? htmlspecialchars($group['cover_image_url'])
                                : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 800 300'%3E%3Cdefs%3E%3ClinearGradient id='grad' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' style='stop-color:%231e293b;stop-opacity:1' /%3E%3Cstop offset='100%25' style='stop-color:%23334155;stop-opacity:1' /%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='800' height='300' fill='url(%23grad)'/%3E%3Cg transform='translate(400,120)'%3E%3Ccircle r='40' fill='%23475569' opacity='0.5'/%3E%3Cpath d='M-30,-20 L-10,-20 L0,-35 L10,-20 L30,-20 L30,20 L-30,20 Z' fill='%2364748b'/%3E%3Ccircle cx='-10' cy='-5' r='6' fill='%23fbbf24'/%3E%3C/g%3E%3Ctext x='400' y='270' text-anchor='middle' font-family='Arial' font-size='18' fill='%2394a3b8'%3ECover Image%3C/text%3E%3C/svg%3E";
                        ?>
                        <img src="<?= $coverImageSrc ?>"
                             loading="lazy"
                             alt="Current cover image"
                             class="ed-image-preview"
                             id="coverPreview"
                             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 800 300%27%3E%3Cdefs%3E%3ClinearGradient id=%27grad%27 x1=%270%25%27 y1=%270%25%27 x2=%27100%25%27 y2=%27100%25%27%3E%3Cstop offset=%270%25%27 style=%27stop-color:%231e293b;stop-opacity:1%27 /%3E%3Cstop offset=%27100%25%27 style=%27stop-color:%23334155;stop-opacity:1%27 /%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width=%27800%27 height=%27300%27 fill=%27url(%23grad)%27/%3E%3Cg transform=%27translate(400,120)%27%3E%3Ccircle r=%2740%27 fill=%27%23475569%27 opacity=%270.5%27/%3E%3Cpath d=%27M-30,-20 L-10,-20 L0,-35 L10,-20 L30,-20 L30,20 L-30,20 Z%27 fill=%27%2364748b%27/%3E%3Ccircle cx=%27-10%27 cy=%27-5%27 r=%276%27 fill=%27%23fbbf24%27/%3E%3C/g%3E%3Ctext x=%27400%27 y=%27270%27 text-anchor=%27middle%27 font-family=%27Arial%27 font-size=%2718%27 fill=%27%2394a3b8%27%3ECover Image%3C/text%3E%3C/svg%3E'">
                        <div class="ed-image-actions">
                            <label class="ed-upload-btn">
                                <i class="fa-solid fa-image"></i>
                                Change Cover
                                <input type="file"
                                       name="cover_image"
                                       accept="image/*"
                                       onchange="previewImage(this, 'coverPreview')"
                                       aria-label="Upload cover image">
                            </label>
                            <?php if (!empty($group['cover_image_url'])): ?>
                            <label class="ed-clear-btn" title="Remove cover">
                                <input type="checkbox" name="clear_cover" value="1">
                                <i class="fa-solid fa-trash"></i>
                                Clear
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="ed-submit">
                        <i class="fa-solid fa-check"></i> Save Changes
                    </button>
                </form>
            </div>

            <!-- ============================================
                 PANEL 2: INVITE MEMBERS
                 ============================================ -->
            <div id="panel-invite"
                 class="edit-panel <?= $defaultTab === 'invite' ? 'active' : '' ?>"
                 role="tabpanel"
                 aria-labelledby="invite-tab">
                <p class="text-center mb-2" style="color: var(--ed-text-secondary);">
                    Select members to invite to <strong><?= htmlspecialchars($group['name']) ?></strong>
                </p>

                <?php if (empty($availableUsers)): ?>
                    <div class="no-users">
                        <i class="fa-solid fa-user-check" style="font-size: 3rem; color: var(--ed-text-secondary); margin-bottom: 1rem;"></i>
                        <p>All community members are already in this group!</p>
                    </div>
                <?php else: ?>
                    <form action="<?= $basePath ?>/groups/<?= $group['id'] ?>/invite"
                          method="POST"
                          id="inviteForm"
                          aria-label="Invite members to group">
                        <?= Nexus\Core\Csrf::input() ?>

                        <input type="text"
                               class="invite-search"
                               id="userSearch"
                               placeholder="Search members by name..."
                               aria-label="Search members">

                        <div class="user-list" id="userList" role="list">
                            <?php foreach ($availableUsers as $user): ?>
                                <label class="user-item"
                                       data-name="<?= strtolower(htmlspecialchars($user['name'])) ?>"
                                       role="listitem">
                                    <input type="checkbox"
                                           name="user_ids[]"
                                           value="<?= $user['id'] ?>"
                                           aria-label="Select <?= htmlspecialchars($user['name']) ?>">
                                    <?php
                                        $avatarSrc = $user['avatar_url'] ?: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 128 128'%3E%3Ccircle cx='64' cy='64' r='64' fill='%23374151'/%3E%3Ccircle cx='64' cy='48' r='20' fill='%2394a3b8'/%3E%3Cellipse cx='64' cy='96' rx='32' ry='24' fill='%2394a3b8'/%3E%3C/svg%3E";
                                    ?>
                                    <img src="<?= htmlspecialchars($avatarSrc) ?>"
                                         loading="lazy"
                                         alt="<?= htmlspecialchars($user['name']) ?>"
                                         class="user-avatar">
                                    <div class="user-info">
                                        <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                                        <?php if (!empty($user['email'])): ?>
                                            <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="selected-count" id="selectedCount" aria-live="polite">0 members selected</div>

                        <!-- Add Directly Option -->
                        <div class="add-directly-box">
                            <label>
                                <input type="checkbox"
                                       name="add_directly"
                                       value="1"
                                       id="addDirectlyCheckbox">
                                <div>
                                    <div class="add-directly-title">Add directly to group</div>
                                    <div class="add-directly-desc">
                                        Skip the invitation step and add selected members immediately. They'll receive a notification that they've been added.
                                    </div>
                                </div>
                            </label>
                        </div>

                        <button type="submit"
                                class="ed-submit"
                                id="submitBtn"
                                disabled
                                aria-live="polite">
                            <i class="fa-solid fa-paper-plane"></i> Send Invitations
                        </button>
                    </form>
                <?php endif; ?>
            </div>

        </main>

    </div>
</div>

</body>
</html>
