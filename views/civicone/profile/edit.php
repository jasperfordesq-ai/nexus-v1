<?php
// CivicOne View: Edit Profile
$hTitle = 'Edit Profile';
$hSubtitle = 'Update your personal information';
$hType = 'Profile';

// Get TinyMCE API key from .env
$tinymceApiKey = 'no-api-key';
$envPath = dirname(__DIR__, 3) . '/.env';
if (file_exists($envPath)) {
    $envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos($line, 'TINYMCE_API_KEY=') === 0) {
            $tinymceApiKey = trim(substr($line, 16), '"\'');
            break;
        }
    }
}

require __DIR__ . '/../../layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- Action Bar -->
<div class="civic-action-bar" style="margin-bottom: 24px;">
    <a href="<?= $basePath ?>/profile/<?= $user['id'] ?>" class="civic-btn civic-btn--outline">
        <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
        Back to Profile
    </a>
</div>

<div class="civic-card" style="max-width: 700px;">
    <form action="<?= $basePath ?>/profile/update" method="POST" enctype="multipart/form-data">
        <?= Nexus\Core\Csrf::input() ?>

        <!-- Avatar Section -->
        <div class="civic-form-group" style="text-align: center; margin-bottom: 30px;">
            <img src="<?= htmlspecialchars($user['avatar_url'] ?? '/assets/images/default-avatar.svg') ?>"
                 alt="Current profile photo"
                 id="avatar-preview"
                 style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--civic-brand); margin-bottom: 16px;">

            <div>
                <label for="avatar" class="civic-btn civic-btn--outline civic-btn--sm" style="cursor: pointer;">
                    <span class="dashicons dashicons-camera" aria-hidden="true"></span>
                    Change Photo
                </label>
                <input type="file" name="avatar" id="avatar" accept="image/*" style="display: none;"
                       onchange="document.getElementById('avatar-preview').src = window.URL.createObjectURL(this.files[0])">
            </div>
        </div>

        <!-- Profile Type -->
        <div class="civic-form-group">
            <label for="profile_type" class="civic-label">Profile Type</label>
            <select name="profile_type" id="profile_type_select" class="civic-input" onchange="toggleOrgField()">
                <option value="individual" <?= ($user['profile_type'] ?? 'individual') === 'individual' ? 'selected' : '' ?>>Individual</option>
                <option value="organisation" <?= ($user['profile_type'] ?? 'individual') === 'organisation' ? 'selected' : '' ?>>Organisation</option>
            </select>
        </div>

        <!-- Organisation Name (conditional) -->
        <div id="org_field_container" class="civic-form-group" style="display: <?= ($user['profile_type'] ?? 'individual') === 'organisation' ? 'block' : 'none' ?>;">
            <label for="organization_name" class="civic-label">Organisation Name</label>
            <input type="text" name="organization_name" id="organization_name"
                   value="<?= htmlspecialchars($user['organization_name'] ?? '') ?>"
                   class="civic-input" placeholder="Enter your organisation name">
        </div>

        <!-- First Name -->
        <div class="civic-form-group">
            <label for="first_name" class="civic-label">First Name <span class="civic-required" aria-label="required">*</span></label>
            <input type="text" name="first_name" id="first_name"
                   value="<?= htmlspecialchars($user['first_name'] ?? '') ?>"
                   required class="civic-input" placeholder="Your first name">
        </div>

        <!-- Last Name -->
        <div class="civic-form-group">
            <label for="last_name" class="civic-label">Last Name <span class="civic-required" aria-label="required">*</span></label>
            <input type="text" name="last_name" id="last_name"
                   value="<?= htmlspecialchars($user['last_name'] ?? '') ?>"
                   required class="civic-input" placeholder="Your last name">
        </div>

        <!-- Location -->
        <div class="civic-form-group">
            <label for="location" class="civic-label">Location</label>
            <input type="text" name="location" id="location"
                   value="<?= htmlspecialchars($user['location'] ?? '') ?>"
                   class="civic-input mapbox-location-input-v2" placeholder="City or area">
        </div>

        <!-- Bio -->
        <div class="civic-form-group">
            <label for="bio" class="civic-label">Bio</label>
            <textarea name="bio" id="bio-editor" rows="5" class="civic-textarea"
                      placeholder="Tell others about yourself..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
            <p class="civic-form-hint">Share your skills, interests, or what brings you to the community.</p>
        </div>

        <!-- TinyMCE for Bio -->
        <script src="https://cdn.tiny.cloud/1/<?= htmlspecialchars($tinymceApiKey) ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
        <script>
        tinymce.init({
            selector: '#bio-editor',
            height: 200,
            menubar: false,
            statusbar: false,
            plugins: ['link', 'lists', 'emoticons'],
            toolbar: 'bold italic | bullist numlist | link emoticons',
            content_style: `
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    font-size: 15px;
                    line-height: 1.5;
                    color: #333;
                    padding: 8px;
                }
            `,
            placeholder: 'Tell others about yourself...',
            branding: false,
            promotion: false
        });
        </script>

        <!-- Submit -->
        <div class="civic-modal-actions" style="margin-top: 30px;">
            <button type="submit" class="civic-btn">
                <span class="dashicons dashicons-yes" aria-hidden="true"></span>
                Save Changes
            </button>
            <a href="<?= $basePath ?>/profile/<?= $user['id'] ?>" class="civic-btn civic-btn--outline">
                Cancel
            </a>
        </div>
    </form>
</div>

<style>
    .civic-required {
        color: #DC2626;
    }

    .civic-form-hint {
        font-size: 13px;
        color: var(--civic-text-muted);
        margin-top: 6px;
    }
</style>

<script>
    function toggleOrgField() {
        const type = document.getElementById('profile_type_select').value;
        const container = document.getElementById('org_field_container');
        container.style.display = type === 'organisation' ? 'block' : 'none';
    }
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
