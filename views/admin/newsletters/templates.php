<?php
/**
 * Newsletter Templates Library
 */
$layout = layout(); // Fixed: centralized detection
$basePath = \Nexus\Core\TenantContext::getBasePath();

// Hero settings
$hTitle = 'Template Library';
$hSubtitle = 'Pre-built and custom email templates for your newsletters';
$hGradient = 'mt-hero-gradient-brand';
$hType = 'Templates';

else {
    require __DIR__ . '/../../layouts/modern/header.php';
}
?>

<div class="newsletter-admin-wrapper">
    <div style="max-width: 1200px; margin: 0 auto;">

        <!-- Flash Messages -->
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #065f46; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                <i class="fa-solid fa-check-circle"></i>
                <?= htmlspecialchars($_SESSION['flash_success']) ?>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #991b1b; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                <i class="fa-solid fa-exclamation-circle"></i>
                <?= htmlspecialchars($_SESSION['flash_error']) ?>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <!-- Action Bar -->
        <div class="nexus-card" style="margin-bottom: 24px; padding: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <a href="<?= $basePath ?>/admin/newsletters" style="color: #6b7280; text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-arrow-left"></i> Back to Newsletters
                </a>
            </div>
            <a href="<?= $basePath ?>/admin/newsletters/templates/create" class="nexus-btn" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-plus"></i> Create Template
            </a>
        </div>

        <?php if (!empty($grouped['starter'])): ?>
        <!-- Starter Templates -->
        <div style="margin-bottom: 40px;">
            <h2 style="font-size: 1.25rem; color: #1f2937; margin: 0 0 20px 0; display: flex; align-items: center; gap: 10px;">
                <span style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-star" style="color: #d97706;"></i>
                </span>
                Starter Templates
            </h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                <?php foreach ($grouped['starter'] as $template): ?>
                <div class="nexus-card" style="padding: 0; overflow: hidden; transition: all 0.2s; cursor: pointer;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 24px rgba(0,0,0,0.1)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
                    <!-- Preview Area -->
                    <div style="height: 160px; background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                        <iframe src="<?= $basePath ?>/admin/newsletters/templates/preview/<?= $template['id'] ?>" style="width: 200%; height: 200%; transform: scale(0.5); transform-origin: top left; pointer-events: none; border: none; position: absolute; top: 0; left: 0;"></iframe>
                        <div style="position: absolute; inset: 0; background: linear-gradient(to bottom, transparent 60%, rgba(0,0,0,0.1));"></div>
                    </div>

                    <!-- Info -->
                    <div style="padding: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                            <h3 style="margin: 0; font-size: 1rem; color: #1f2937;"><?= htmlspecialchars($template['name']) ?></h3>
                            <span style="background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 600;">STARTER</span>
                        </div>
                        <p style="color: #6b7280; font-size: 0.85rem; margin: 0 0 15px 0; line-height: 1.4;">
                            <?= htmlspecialchars($template['description'] ?? 'Ready-to-use template') ?>
                        </p>
                        <div style="display: flex; gap: 8px;">
                            <a href="<?= $basePath ?>/admin/newsletters/templates/preview/<?= $template['id'] ?>" target="_blank" style="flex: 1; background: #f3f4f6; color: #374151; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 500; text-align: center;">
                                <i class="fa-solid fa-eye"></i> Preview
                            </a>
                            <a href="<?= $basePath ?>/admin/newsletters/templates/duplicate/<?= $template['id'] ?>" style="flex: 1; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 500; text-align: center;">
                                <i class="fa-solid fa-copy"></i> Use
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($grouped['saved'])): ?>
        <!-- Saved From Newsletters -->
        <div style="margin-bottom: 40px;">
            <h2 style="font-size: 1.25rem; color: #1f2937; margin: 0 0 20px 0; display: flex; align-items: center; gap: 10px;">
                <span style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-bookmark" style="color: #2563eb;"></i>
                </span>
                Saved Templates
            </h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                <?php foreach ($grouped['saved'] as $template): ?>
                <div class="nexus-card" style="padding: 0; overflow: hidden; transition: all 0.2s;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
                    <div style="height: 140px; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                        <iframe src="<?= $basePath ?>/admin/newsletters/templates/preview/<?= $template['id'] ?>" style="width: 200%; height: 200%; transform: scale(0.5); transform-origin: top left; pointer-events: none; border: none; position: absolute; top: 0; left: 0;"></iframe>
                    </div>
                    <div style="padding: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <h3 style="margin: 0; font-size: 0.95rem; color: #1f2937;"><?= htmlspecialchars($template['name']) ?></h3>
                            <span style="color: #6b7280; font-size: 0.75rem;">Used <?= $template['use_count'] ?>x</span>
                        </div>
                        <?php if (!empty($template['subject'])): ?>
                        <p style="color: #6b7280; font-size: 0.8rem; margin: 0 0 12px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?= htmlspecialchars($template['subject']) ?>
                        </p>
                        <?php endif; ?>
                        <div style="display: flex; gap: 6px;">
                            <a href="<?= $basePath ?>/admin/newsletters/templates/edit/<?= $template['id'] ?>" style="flex: 1; background: #f3f4f6; color: #374151; padding: 6px 10px; border-radius: 5px; text-decoration: none; font-size: 0.8rem; text-align: center;">Edit</a>
                            <a href="<?= $basePath ?>/admin/newsletters/templates/duplicate/<?= $template['id'] ?>" style="flex: 1; background: #6366f1; color: white; padding: 6px 10px; border-radius: 5px; text-decoration: none; font-size: 0.8rem; text-align: center;">Use</a>
                            <form action="<?= $basePath ?>/admin/newsletters/templates/delete" method="POST" style="margin: 0;" onsubmit="return confirm('Delete this template?')">
                                <?= \Nexus\Core\Csrf::input() ?>
                                <input type="hidden" name="id" value="<?= $template['id'] ?>">
                                <button type="submit" style="background: #fee2e2; color: #dc2626; border: none; padding: 6px 10px; border-radius: 5px; cursor: pointer; font-size: 0.8rem;"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($grouped['custom'])): ?>
        <!-- Custom Templates -->
        <div style="margin-bottom: 40px;">
            <h2 style="font-size: 1.25rem; color: #1f2937; margin: 0 0 20px 0; display: flex; align-items: center; gap: 10px;">
                <span style="background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-palette" style="color: #16a34a;"></i>
                </span>
                Custom Templates
            </h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                <?php foreach ($grouped['custom'] as $template): ?>
                <div class="nexus-card" style="padding: 0; overflow: hidden; transition: all 0.2s;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
                    <div style="height: 140px; background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                        <iframe src="<?= $basePath ?>/admin/newsletters/templates/preview/<?= $template['id'] ?>" style="width: 200%; height: 200%; transform: scale(0.5); transform-origin: top left; pointer-events: none; border: none; position: absolute; top: 0; left: 0;"></iframe>
                    </div>
                    <div style="padding: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <h3 style="margin: 0; font-size: 0.95rem; color: #1f2937;"><?= htmlspecialchars($template['name']) ?></h3>
                            <span style="color: #6b7280; font-size: 0.75rem;">Used <?= $template['use_count'] ?>x</span>
                        </div>
                        <?php if (!empty($template['description'])): ?>
                        <p style="color: #6b7280; font-size: 0.8rem; margin: 0 0 12px 0; line-height: 1.4;">
                            <?= htmlspecialchars(substr($template['description'], 0, 80)) ?><?= strlen($template['description']) > 80 ? '...' : '' ?>
                        </p>
                        <?php endif; ?>
                        <div style="display: flex; gap: 6px;">
                            <a href="<?= $basePath ?>/admin/newsletters/templates/edit/<?= $template['id'] ?>" style="flex: 1; background: #f3f4f6; color: #374151; padding: 6px 10px; border-radius: 5px; text-decoration: none; font-size: 0.8rem; text-align: center;">Edit</a>
                            <a href="<?= $basePath ?>/admin/newsletters/templates/duplicate/<?= $template['id'] ?>" style="flex: 1; background: #16a34a; color: white; padding: 6px 10px; border-radius: 5px; text-decoration: none; font-size: 0.8rem; text-align: center;">Use</a>
                            <form action="<?= $basePath ?>/admin/newsletters/templates/delete" method="POST" style="margin: 0;" onsubmit="return confirm('Delete this template?')">
                                <?= \Nexus\Core\Csrf::input() ?>
                                <input type="hidden" name="id" value="<?= $template['id'] ?>">
                                <button type="submit" style="background: #fee2e2; color: #dc2626; border: none; padding: 6px 10px; border-radius: 5px; cursor: pointer; font-size: 0.8rem;"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($grouped['starter']) && empty($grouped['saved']) && empty($grouped['custom'])): ?>
        <!-- Empty State -->
        <div class="nexus-card" style="padding: 60px 40px; text-align: center;">
            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
                <i class="fa-solid fa-palette" style="font-size: 2rem; color: #6b7280;"></i>
            </div>
            <h3 style="margin: 0 0 10px 0; font-size: 1.25rem; color: #111827;">No templates yet</h3>
            <p style="color: #6b7280; margin-bottom: 24px;">Create your first template or run the migration to load starter templates.</p>
            <a href="<?= $basePath ?>/admin/newsletters/templates/create" class="nexus-btn" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                <i class="fa-solid fa-plus"></i> Create Template
            </a>
        </div>
        <?php endif; ?>

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

        .newsletter-admin-wrapper [style*="grid-template-columns"] {
            grid-template-columns: 1fr 1fr !important;
        }
    }
</style>

<?php
else {
    require __DIR__ . '/../../layouts/modern/footer.php';
}
?>
