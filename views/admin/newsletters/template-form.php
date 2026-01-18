<?php
/**
 * Newsletter Template Create/Edit Form
 */
$layout = layout(); // Fixed: centralized detection
$basePath = \Nexus\Core\TenantContext::getBasePath();

$isEdit = !empty($template);
$hTitle = $isEdit ? 'Edit Template' : 'Create Template';
$hSubtitle = $isEdit ? 'Modify your email template' : 'Design a new reusable email template';
$hGradient = 'mt-hero-gradient-brand';
$hType = 'Templates';

else {
    require __DIR__ . '/../../layouts/modern/header.php';
}
?>

<div class="newsletter-admin-wrapper">
    <div style="max-width: 1000px; margin: 0 auto;">

        <!-- Flash Messages -->
        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #991b1b; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                <i class="fa-solid fa-exclamation-circle"></i>
                <?= htmlspecialchars($_SESSION['flash_error']) ?>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <!-- Action Bar -->
        <div class="nexus-card" style="margin-bottom: 24px; padding: 20px; display: flex; justify-content: space-between; align-items: center;">
            <a href="<?= $basePath ?>/admin/newsletters/templates" style="color: #6b7280; text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-arrow-left"></i> Back to Templates
            </a>
            <?php if ($isEdit): ?>
            <a href="<?= $basePath ?>/admin/newsletters/templates/preview/<?= $template['id'] ?>" target="_blank" style="color: #6366f1; text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-eye"></i> Preview
            </a>
            <?php endif; ?>
        </div>

        <form action="<?= $basePath ?>/admin/newsletters/templates/<?= $isEdit ? 'update/' . $template['id'] : 'store' ?>" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                <!-- Main Content -->
                <div style="display: flex; flex-direction: column; gap: 24px;">
                    <!-- Template Details -->
                    <div class="nexus-card" style="padding: 24px;">
                        <h2 style="margin: 0 0 20px 0; font-size: 1.1rem; color: #1f2937; display: flex; align-items: center; gap: 10px;">
                            <i class="fa-solid fa-info-circle" style="color: #6366f1;"></i>
                            Template Details
                        </h2>

                        <div style="display: flex; flex-direction: column; gap: 16px;">
                            <div>
                                <label style="display: block; font-weight: 500; color: #374151; margin-bottom: 6px;">Template Name *</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($template['name'] ?? '') ?>" required
                                    style="width: 100%; padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; box-sizing: border-box;"
                                    placeholder="e.g., Monthly Newsletter, Event Announcement">
                            </div>

                            <div>
                                <label style="display: block; font-weight: 500; color: #374151; margin-bottom: 6px;">Description</label>
                                <textarea name="description" rows="2"
                                    style="width: 100%; padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; box-sizing: border-box; resize: vertical;"
                                    placeholder="Brief description of when to use this template"><?= htmlspecialchars($template['description'] ?? '') ?></textarea>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                <div>
                                    <label style="display: block; font-weight: 500; color: #374151; margin-bottom: 6px;">Category</label>
                                    <select name="category" style="width: 100%; padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; background: white;">
                                        <option value="custom" <?= ($template['category'] ?? 'custom') === 'custom' ? 'selected' : '' ?>>Custom</option>
                                        <option value="saved" <?= ($template['category'] ?? '') === 'saved' ? 'selected' : '' ?>>Saved from Newsletter</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display: block; font-weight: 500; color: #374151; margin-bottom: 6px;">Status</label>
                                    <select name="is_active" style="width: 100%; padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; background: white;">
                                        <option value="1" <?= ($template['is_active'] ?? 1) == 1 ? 'selected' : '' ?>>Active</option>
                                        <option value="0" <?= ($template['is_active'] ?? 1) == 0 ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Settings -->
                    <div class="nexus-card" style="padding: 24px;">
                        <h2 style="margin: 0 0 20px 0; font-size: 1.1rem; color: #1f2937; display: flex; align-items: center; gap: 10px;">
                            <i class="fa-solid fa-envelope" style="color: #6366f1;"></i>
                            Email Settings
                        </h2>

                        <div style="display: flex; flex-direction: column; gap: 16px;">
                            <div>
                                <label style="display: block; font-weight: 500; color: #374151; margin-bottom: 6px;">Default Subject Line</label>
                                <input type="text" name="subject" value="<?= htmlspecialchars($template['subject'] ?? '') ?>"
                                    style="width: 100%; padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; box-sizing: border-box;"
                                    placeholder="Email subject when using this template">
                            </div>

                            <div>
                                <label style="display: block; font-weight: 500; color: #374151; margin-bottom: 6px;">Preview Text</label>
                                <input type="text" name="preview_text" value="<?= htmlspecialchars($template['preview_text'] ?? '') ?>"
                                    style="width: 100%; padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; box-sizing: border-box;"
                                    placeholder="Text shown in email client preview (after subject)">
                                <p style="margin: 6px 0 0; font-size: 0.8rem; color: #6b7280;">This appears after the subject line in most email clients.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Template Content -->
                    <div class="nexus-card" style="padding: 24px;">
                        <h2 style="margin: 0 0 20px 0; font-size: 1.1rem; color: #1f2937; display: flex; align-items: center; gap: 10px;">
                            <i class="fa-solid fa-code" style="color: #6366f1;"></i>
                            Template Content (HTML)
                        </h2>

                        <div style="margin-bottom: 16px; padding: 12px; background: #f3f4f6; border-radius: 8px;">
                            <p style="margin: 0 0 8px 0; font-size: 0.85rem; color: #374151; font-weight: 500;">Available Variables:</p>
                            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                <code style="background: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">{{first_name}}</code>
                                <code style="background: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">{{last_name}}</code>
                                <code style="background: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">{{email}}</code>
                                <code style="background: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">{{tenant_name}}</code>
                                <code style="background: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">{{unsubscribe_link}}</code>
                                <code style="background: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">{{view_in_browser}}</code>
                            </div>
                        </div>

                        <textarea name="content" id="template-content" rows="20"
                            style="width: 100%; padding: 16px; border: 1px solid #d1d5db; border-radius: 8px; font-family: 'Fira Code', 'Monaco', monospace; font-size: 0.9rem; box-sizing: border-box; resize: vertical; line-height: 1.5;"
                            placeholder="Enter your HTML email template here..."><?= htmlspecialchars($template['content'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Sidebar -->
                <div style="display: flex; flex-direction: column; gap: 24px;">
                    <!-- Actions -->
                    <div class="nexus-card" style="padding: 24px; position: sticky; top: 20px;">
                        <h3 style="margin: 0 0 16px 0; font-size: 1rem; color: #1f2937;">Actions</h3>

                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <button type="submit" style="width: 100%; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 14px 20px; border: none; border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <i class="fa-solid fa-save"></i>
                                <?= $isEdit ? 'Update Template' : 'Save Template' ?>
                            </button>

                            <?php if ($isEdit): ?>
                            <a href="<?= $basePath ?>/admin/newsletters/templates/duplicate/<?= $template['id'] ?>" style="width: 100%; background: #f3f4f6; color: #374151; padding: 12px 20px; border-radius: 8px; font-weight: 500; font-size: 0.9rem; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; box-sizing: border-box;">
                                <i class="fa-solid fa-copy"></i>
                                Duplicate Template
                            </a>
                            <?php endif; ?>

                            <a href="<?= $basePath ?>/admin/newsletters/templates" style="width: 100%; background: transparent; color: #6b7280; padding: 12px 20px; border: 1px solid #d1d5db; border-radius: 8px; font-weight: 500; font-size: 0.9rem; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; box-sizing: border-box;">
                                Cancel
                            </a>
                        </div>
                    </div>

                    <!-- Tips -->
                    <div class="nexus-card" style="padding: 24px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                        <h3 style="margin: 0 0 12px 0; font-size: 1rem; color: #166534; display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-lightbulb"></i>
                            Tips
                        </h3>
                        <ul style="margin: 0; padding-left: 20px; color: #166534; font-size: 0.85rem; line-height: 1.6;">
                            <li>Use inline CSS for best email client compatibility</li>
                            <li>Keep width under 600px for mobile</li>
                            <li>Test with multiple email clients</li>
                            <li>Include an unsubscribe link</li>
                            <li>Use web-safe fonts</li>
                        </ul>
                    </div>

                    <?php if ($isEdit && !empty($template['use_count'])): ?>
                    <!-- Usage Stats -->
                    <div class="nexus-card" style="padding: 24px;">
                        <h3 style="margin: 0 0 12px 0; font-size: 1rem; color: #1f2937;">Usage</h3>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-chart-line" style="color: #2563eb; font-size: 1.25rem;"></i>
                            </div>
                            <div>
                                <div style="font-size: 1.5rem; font-weight: 700; color: #1f2937;"><?= $template['use_count'] ?></div>
                                <div style="font-size: 0.8rem; color: #6b7280;">times used</div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>

    </div>
</div>

<style>
    .newsletter-admin-wrapper {
        position: relative;
        z-index: 20;
        padding: 0 40px 60px;
    }

    @media (min-width: 601px) {
        .newsletter-admin-wrapper {
            padding-top: 140px;
        }
    }

    @media (max-width: 600px) {
        .newsletter-admin-wrapper {
            padding: 120px 15px 100px 15px;
        }
    }
</style>

<?php
else {
    require __DIR__ . '/../../layouts/modern/footer.php';
}
?>
