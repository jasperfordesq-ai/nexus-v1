<?php
// Phoenix View: Edit Profile
$hero_title = 'Profile Settings';
$hero_subtitle = 'Manage your public identity and account preferences';
$hero_gradient = 'htb-hero-gradient-teal';
$hero_type = 'Account';
$hideHero = true;

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

require dirname(__DIR__, 2) . '/layouts/modern/header.php';
?>

<!-- CSS moved to /assets/css/profile-edit.css -->

<div class="edit-glass-bg"></div>

<div class="htb-container edit-container" style="padding: 120px 24px 40px 24px; position: relative; z-index: 20; max-width: 1100px;">

    <!-- Top Action Bar -->
    <div style="margin-bottom: 30px;">
        <a href="/profile/<?= $user['id'] ?>" style="text-decoration: none; color: white; display: inline-flex; align-items: center; gap: 5px; background: rgba(0,0,0,0.2); padding: 8px 16px; border-radius: 20px; backdrop-filter: blur(4px); font-weight: 600; font-size: 0.9rem; transition: background 0.2s;">
            &larr; Back to Profile
        </a>
    </div>

    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/profile/update" method="POST" enctype="multipart/form-data">
        <?= Nexus\Core\Csrf::input() ?>
        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">

        <!-- Flex Container for Responsive Layout -->
        <div style="display: flex; flex-wrap: wrap; gap: 30px; align-items: flex-start;">

            <!-- COLUMN 1: Avatar & Identity (Width: 300px min, grows slightly) -->
            <div style="flex: 1 1 300px; min-width: 280px; display: flex; flex-direction: column; gap: 20px;">

                <!-- Box 1: Avatar -->
                <div class="htb-card">
                    <div class="htb-card-body" style="text-align: center; padding: 30px;">
                        <h3 style="margin-top: 0; font-size: 1.1rem; color: #111827; margin-bottom: 20px;">Profile Photo</h3>

                        <div style="margin: 0 auto 20px auto; position: relative; width: 140px; height: 140px;">
                            <img id="avatar-preview" src="<?= $user['avatar_url'] ?: '/assets/img/defaults/default_avatar.webp' ?>" loading="lazy"
                                style="width: 140px; height: 140px; border-radius: 50%; object-fit: cover; border: 4px solid #f3f4f6; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">

                            <label for="avatar" style="position: absolute; bottom: 5px; right: 5px; background: #2563eb; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                                📷
                            </label>
                            <input type="file" name="avatar" id="avatar" accept="image/*" style="display: none;" onchange="const max=2*1024*1024; if(this.files[0].size > max) { alert('File too large (Max 2MB)'); this.value=''; return; } document.getElementById('avatar-preview').src = window.URL.createObjectURL(this.files[0])">
                        </div>

                        <p style="font-size: 0.8rem; color: #6b7280; margin-bottom: 0; line-height: 1.4;">
                            Tap the camera to update.<br>
                            <span style="color: #9ca3af;">Max 2MB (JPG, PNG)</span>
                        </p>
                    </div>
                </div>

                <!-- Box 2: Status -->
                <div class="htb-card">
                    <div class="htb-card-body">
                        <h3 style="margin-top: 0; font-size: 1.1rem; color: #111827; margin-bottom: 15px;">Account Status</h3>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <span style="color: #6b7280; font-size: 0.9rem;">Type</span>
                            <span style="font-weight: 700; color: #374151; background: #f3f4f6; padding: 2px 8px; border-radius: 4px; font-size: 0.85rem;"><?= ucfirst($user['role']) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #6b7280; font-size: 0.9rem;">Member Since</span>
                            <span style="font-weight: 600; color: #374151; font-size: 0.9rem;"><?= date('M Y', strtotime($user['created_at'])) ?></span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- COLUMN 2: Details Form (Width: 500px min, grows to fill) -->
            <div style="flex: 2 1 400px; min-width: 300px; display: flex; flex-direction: column; gap: 25px;">

                <!-- Box 3: Basic Info -->
                <div class="htb-card">
                    <div class="htb-card-body" style="padding: 30px;">
                        <h3 style="margin-top: 0; margin-bottom: 25px; font-size: 1.25rem; color: #111827; border-bottom: 1px solid #f3f4f6; padding-bottom: 15px;">Basic Information</h3>

                        <div style="margin-bottom: 25px; display: flex; gap: 15px;">
                            <div style="flex: 1;">
                                <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 0.9rem; color: #374151;">First Name</label>
                                <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required class="htb-form-control" style="width: 100%; padding: 12px 15px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem;">
                            </div>
                            <div style="flex: 1;">
                                <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 0.9rem; color: #374151;">Last Name</label>
                                <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required class="htb-form-control" style="width: 100%; padding: 12px 15px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem;">
                            </div>
                        </div>

                        <div style="margin-bottom: 25px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 0.9rem; color: #374151;">Email Address</label>
                            <div style="position: relative;">
                                <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled class="htb-form-control" style="width: 100%; padding: 12px 15px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; color: #6b7280; cursor: not-allowed;">
                                <span style="position: absolute; right: 12px; top: 12px; font-size: 1.2rem; color: #9ca3af;" title="Cannot be changed">🔒</span>
                            </div>
                        </div>

                        <div style="margin-bottom: 0;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 0.9rem; color: #374151;">Location / Area</label>
                            <input type="text" name="location" value="<?= htmlspecialchars($user['location'] ?? '') ?>" placeholder="Start typing your town or city (not full address)..." class="htb-form-control mapbox-location-input-v2" style="width: 100%; padding: 12px 15px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem;">
                            <input type="hidden" name="latitude" value="<?= htmlspecialchars($user['latitude'] ?? '') ?>">
                            <input type="hidden" name="longitude" value="<?= htmlspecialchars($user['longitude'] ?? '') ?>">
                        </div>

                        <div style="margin-top: 25px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 0.9rem; color: #374151;">Phone Number</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+1 555-0123" class="htb-form-control" style="width: 100%; padding: 12px 15px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem;">
                            <p style="font-size: 0.8rem; color: #6b7280; margin-top: 5px;">Only visible to administrators.</p>
                        </div>
                    </div>
                </div>

                <!-- Box 4: Bio/About -->
                <div class="htb-card">
                    <div class="htb-card-body" style="padding: 30px;">
                        <h3 style="margin-top: 0; margin-bottom: 25px; font-size: 1.25rem; color: #111827; border-bottom: 1px solid #f3f4f6; padding-bottom: 15px;">About You</h3>

                        <div style="margin-bottom: 0;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 0.9rem; color: #374151;">Bio / Introduction</label>
                            <textarea name="bio" id="bio-editor" rows="6" class="htb-form-control" style="width: 100%; padding: 12px 15px; border: 1px solid #d1d5db; border-radius: 8px; resize: vertical; line-height: 1.6; font-family: inherit; font-size: 0.95rem;" placeholder="Share your interests, skills, or what you're looking for in the TimeBank..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
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
                <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; justify-content: flex-end; align-items: center; gap: 15px; border: 1px solid #f3f4f6;">
                    <a href="/profile/<?= $user['id'] ?>" style="text-decoration: none; color: #6b7280; font-weight: 600; font-size: 0.95rem;">Cancel</a>
                    <button type="submit" class="htb-btn htb-btn-primary" style="padding: 12px 32px; font-size: 1rem; font-weight: 600; box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);">
                        Save Profile
                    </button>
                </div>

            </div>
        </div>
    </form>

    <div style="height: 50px;"></div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>