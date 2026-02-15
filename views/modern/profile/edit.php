<?php
// Phoenix View: Edit Profile
$hero_title = 'Profile Settings';
$hero_subtitle = 'Manage your public identity and account preferences';
$hero_gradient = 'htb-hero-gradient-teal';
$hero_type = 'Account';
$hideHero = true;

// Get TinyMCE API key from environment or .env file
$tinymceApiKey = getenv('TINYMCE_API_KEY') ?: 'no-api-key';
if ($tinymceApiKey === 'no-api-key') {
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
}

require dirname(__DIR__, 2) . '/layouts/modern/header.php';
?>

<!-- CSS moved to /assets/css/profile-edit.css -->

<div class="edit-glass-bg"></div>

<div class="htb-container edit-container mte-profile-edit--container">

    <!-- Top Action Bar -->
    <div class="mte-profile-edit--back-bar">
        <a href="/profile/<?= $user['id'] ?>" class="mte-profile-edit--back-link">
            &larr; Back to Profile
        </a>
    </div>

    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/profile/update" method="POST" enctype="multipart/form-data">
        <?= Nexus\Core\Csrf::input() ?>
        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">

        <!-- Flex Container for Responsive Layout -->
        <div class="mte-profile-edit--layout">

            <!-- COLUMN 1: Avatar & Identity (Width: 300px min, grows slightly) -->
            <div class="mte-profile-edit--col-left">

                <!-- Box 1: Avatar -->
                <div class="htb-card">
                    <div class="htb-card-body mte-profile-edit--avatar-body">
                        <h3 class="mte-profile-edit--section-title">Profile Photo</h3>

                        <div class="mte-profile-edit--avatar-container">
                            <img id="avatar-preview" src="<?= $user['avatar_url'] ?: '/assets/img/defaults/default_avatar.webp' ?>" loading="lazy" class="mte-profile-edit--avatar-img">

                            <label for="avatar" class="mte-profile-edit--avatar-upload-btn">
                                📷
                            </label>
                            <input type="file" name="avatar" id="avatar" accept="image/*" class="hidden" onchange="const max=2*1024*1024; if(this.files[0].size > max) { alert('File too large (Max 2MB)'); this.value=''; return; } document.getElementById('avatar-preview').src = window.URL.createObjectURL(this.files[0])">
                        </div>

                        <p class="mte-profile-edit--avatar-hint">
                            Tap the camera to update.<br>
                            <span class="mte-profile-edit--avatar-hint-sub">Max 2MB (JPG, PNG)</span>
                        </p>
                    </div>
                </div>

                <!-- Box 2: Status -->
                <div class="htb-card">
                    <div class="htb-card-body">
                        <h3 class="mte-profile-edit--section-title mte-profile-edit--section-title-sm">Account Status</h3>
                        <div class="mte-profile-edit--status-row">
                            <span class="mte-profile-edit--status-label">Type</span>
                            <span class="mte-profile-edit--status-value"><?= ucfirst($user['role']) ?></span>
                        </div>
                        <div class="mte-profile-edit--status-row">
                            <span class="mte-profile-edit--status-label">Member Since</span>
                            <span class="mte-profile-edit--status-value mte-profile-edit--status-value-plain"><?= date('M Y', strtotime($user['created_at'])) ?></span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- COLUMN 2: Details Form (Width: 500px min, grows to fill) -->
            <div class="mte-profile-edit--col-right">

                <!-- Box 3: Basic Info -->
                <div class="htb-card">
                    <div class="htb-card-body mte-profile-edit--form-body">
                        <h3 class="mte-profile-edit--form-title">Basic Information</h3>

                        <div class="mte-profile-edit--form-row">
                            <div class="mte-profile-edit--form-col">
                                <label class="mte-profile-edit--label">First Name</label>
                                <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required class="htb-form-control mte-profile-edit--input">
                            </div>
                            <div class="mte-profile-edit--form-col">
                                <label class="mte-profile-edit--label">Last Name</label>
                                <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required class="htb-form-control mte-profile-edit--input">
                            </div>
                        </div>

                        <div class="mte-profile-edit--form-group">
                            <label class="mte-profile-edit--label">Email Address</label>
                            <div class="mte-profile-edit--input-wrapper">
                                <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled class="htb-form-control mte-profile-edit--input mte-profile-edit--input-disabled">
                                <span class="mte-profile-edit--input-lock" title="Cannot be changed">🔒</span>
                            </div>
                        </div>

                        <div class="mte-profile-edit--form-group">
                            <label class="mte-profile-edit--label">Location / Area</label>
                            <input type="text" name="location" value="<?= htmlspecialchars($user['location'] ?? '') ?>" placeholder="Start typing your town or city (not full address)..." class="htb-form-control mapbox-location-input-v2 mte-profile-edit--input">
                            <input type="hidden" name="latitude" value="<?= htmlspecialchars($user['latitude'] ?? '') ?>">
                            <input type="hidden" name="longitude" value="<?= htmlspecialchars($user['longitude'] ?? '') ?>">
                        </div>

                        <div class="mte-profile-edit--form-group">
                            <label class="mte-profile-edit--label">Phone Number</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+1 555-0123" class="htb-form-control mte-profile-edit--input">
                            <p class="mte-profile-edit--hint">Only visible to administrators.</p>
                        </div>
                    </div>
                </div>

                <!-- Box 4: Bio/About -->
                <div class="htb-card">
                    <div class="htb-card-body mte-profile-edit--form-body">
                        <h3 class="mte-profile-edit--form-title">About You</h3>

                        <div class="mte-profile-edit--form-group">
                            <label class="mte-profile-edit--label">Bio / Introduction</label>
                            <textarea name="bio" id="bio-editor" rows="6" class="htb-form-control mte-profile-edit--textarea" placeholder="Share your interests, skills, or what you're looking for in the TimeBank..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                        </div>

                        <!-- TinyMCE for Bio -->
                        <script src="https://cdn.tiny.cloud/1/<?= htmlspecialchars($tinymceApiKey) ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
                        <script>
                        tinymce.init({
                            selector: '#bio-editor',
                            height: 220,
                            menubar: false,
                            statusbar: false,
                            plugins: ['link', 'lists', 'emoticons'],
                            toolbar: 'bold italic | bullist numlist | link emoticons',
                            content_style: `
                                body {
                                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                                    font-size: 15px;
                                    line-height: 1.6;
                                    color: #374151;
                                    padding: 10px;
                                }
                            `,
                            placeholder: 'Share your interests, skills, or what you\'re looking for...',
                            branding: false,
                            promotion: false
                        });
                        </script>
                    </div>
                </div>

                <!-- Bottom Action Bar (Save + Back) -->
                <div class="mte-profile-edit--action-bar">
                    <a href="/profile/<?= $user['id'] ?>" class="mte-profile-edit--cancel-link">Cancel</a>
                    <button type="submit" class="htb-btn htb-btn-primary mte-profile-edit--submit-btn">
                        Save Profile
                    </button>
                </div>

            </div>
        </div>
    </form>

    <div class="mte-profile-edit--spacer"></div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>