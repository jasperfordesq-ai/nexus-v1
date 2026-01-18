<?php
/**
 * Newsletter Bounce & Suppression Management
 */
$layout = layout(); // Fixed: centralized detection
$basePath = \Nexus\Core\TenantContext::getBasePath();

$hTitle = 'Bounce Management';
$hSubtitle = 'Monitor bounces and manage email suppression list';
$hGradient = 'mt-hero-gradient-brand';
$hType = 'Email Health';

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
            <a href="<?= $basePath ?>/admin/newsletters" style="color: #6b7280; text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-arrow-left"></i> Back to Newsletters
            </a>
            <div style="display: flex; gap: 12px;">
                <a href="<?= $basePath ?>/admin/newsletters/analytics" style="color: #6366f1; text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-chart-line"></i> Analytics
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <!-- Hard Bounces -->
            <div class="nexus-card" style="padding: 24px;">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-circle-xmark" style="font-size: 1.5rem; color: #dc2626;"></i>
                    </div>
                    <div>
                        <div style="font-size: 2rem; font-weight: 700; color: #1f2937;"><?= number_format($stats['hard'] ?? 0) ?></div>
                        <div style="color: #6b7280; font-size: 0.9rem;">Hard Bounces</div>
                    </div>
                </div>
            </div>

            <!-- Soft Bounces -->
            <div class="nexus-card" style="padding: 24px;">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.5rem; color: #d97706;"></i>
                    </div>
                    <div>
                        <div style="font-size: 2rem; font-weight: 700; color: #1f2937;"><?= number_format($stats['soft'] ?? 0) ?></div>
                        <div style="color: #6b7280; font-size: 0.9rem;">Soft Bounces</div>
                    </div>
                </div>
            </div>

            <!-- Complaints -->
            <div class="nexus-card" style="padding: 24px;">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #fce7f3 0%, #fbcfe8 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-flag" style="font-size: 1.5rem; color: #db2777;"></i>
                    </div>
                    <div>
                        <div style="font-size: 2rem; font-weight: 700; color: #1f2937;"><?= number_format($stats['complaint'] ?? 0) ?></div>
                        <div style="color: #6b7280; font-size: 0.9rem;">Complaints</div>
                    </div>
                </div>
            </div>

            <!-- Suppressed -->
            <div class="nexus-card" style="padding: 24px;">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-ban" style="font-size: 1.5rem; color: #4f46e5;"></i>
                    </div>
                    <div>
                        <div style="font-size: 2rem; font-weight: 700; color: #1f2937;"><?= number_format($suppressionCount ?? 0) ?></div>
                        <div style="color: #6b7280; font-size: 0.9rem;">Suppressed Emails</div>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
            <!-- Suppression List -->
            <div class="nexus-card" style="padding: 0; overflow: hidden;">
                <div style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="margin: 0; font-size: 1.1rem; color: #1f2937; display: flex; align-items: center; gap: 10px;">
                        <i class="fa-solid fa-list" style="color: #6366f1;"></i>
                        Suppression List
                    </h2>
                    <span style="background: #f3f4f6; color: #6b7280; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem;">
                        <?= number_format($suppressionCount ?? 0) ?> emails
                    </span>
                </div>

                <?php if (!empty($suppressionList)): ?>
                <div style="max-height: 500px; overflow-y: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f9fafb;">
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; font-size: 0.85rem;">Email</th>
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; font-size: 0.85rem;">Reason</th>
                                <th style="padding: 12px 16px; text-align: center; font-weight: 600; color: #374151; font-size: 0.85rem;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppressionList as $item): ?>
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 12px 16px;">
                                    <div style="font-size: 0.9rem; color: #1f2937;"><?= htmlspecialchars($item['email']) ?></div>
                                    <div style="font-size: 0.75rem; color: #9ca3af;"><?= date('M j, Y', strtotime($item['suppressed_at'])) ?></div>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <?php
                                    $reasonColors = [
                                        'hard_bounce' => ['bg' => '#fee2e2', 'text' => '#dc2626'],
                                        'repeated_soft_bounce' => ['bg' => '#fef3c7', 'text' => '#d97706'],
                                        'complaint' => ['bg' => '#fce7f3', 'text' => '#db2777'],
                                        'unsubscribe' => ['bg' => '#e0e7ff', 'text' => '#4f46e5'],
                                    ];
                                    $colors = $reasonColors[$item['reason']] ?? ['bg' => '#f3f4f6', 'text' => '#6b7280'];
                                    ?>
                                    <span style="background: <?= $colors['bg'] ?>; color: <?= $colors['text'] ?>; padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 500;">
                                        <?= ucwords(str_replace('_', ' ', $item['reason'])) ?>
                                    </span>
                                    <?php if ($item['bounce_count'] > 1): ?>
                                    <span style="color: #9ca3af; font-size: 0.75rem; margin-left: 4px;">(<?= $item['bounce_count'] ?>x)</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 16px; text-align: center;">
                                    <form action="<?= $basePath ?>/admin/newsletters/bounces/unsuppress" method="POST" style="display: inline;">
                                        <?= \Nexus\Core\Csrf::input() ?>
                                        <input type="hidden" name="email" value="<?= htmlspecialchars($item['email']) ?>">
                                        <button type="submit" onclick="return confirm('Remove this email from suppression list?')" style="background: none; border: none; color: #6366f1; cursor: pointer; font-size: 0.8rem; padding: 4px 8px;">
                                            <i class="fa-solid fa-rotate-left"></i> Restore
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div style="padding: 60px 24px; text-align: center;">
                    <div style="width: 64px; height: 64px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                        <i class="fa-solid fa-check" style="font-size: 1.5rem; color: #10b981;"></i>
                    </div>
                    <h3 style="margin: 0 0 8px 0; color: #1f2937; font-size: 1rem;">No Suppressed Emails</h3>
                    <p style="margin: 0; color: #6b7280; font-size: 0.9rem;">Your email list is clean!</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Bounces -->
            <div class="nexus-card" style="padding: 0; overflow: hidden;">
                <div style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb;">
                    <h2 style="margin: 0; font-size: 1.1rem; color: #1f2937; display: flex; align-items: center; gap: 10px;">
                        <i class="fa-solid fa-clock-rotate-left" style="color: #6366f1;"></i>
                        Recent Bounces
                    </h2>
                </div>

                <?php if (!empty($recentBounces)): ?>
                <div style="max-height: 500px; overflow-y: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f9fafb;">
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; font-size: 0.85rem;">Email</th>
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; font-size: 0.85rem;">Type</th>
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; font-size: 0.85rem;">Newsletter</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentBounces as $bounce): ?>
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 12px 16px;">
                                    <div style="font-size: 0.9rem; color: #1f2937;"><?= htmlspecialchars($bounce['email']) ?></div>
                                    <div style="font-size: 0.75rem; color: #9ca3af;"><?= date('M j, Y g:i A', strtotime($bounce['bounced_at'])) ?></div>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <?php
                                    $typeColors = [
                                        'hard' => ['bg' => '#fee2e2', 'text' => '#dc2626', 'icon' => 'circle-xmark'],
                                        'soft' => ['bg' => '#fef3c7', 'text' => '#d97706', 'icon' => 'triangle-exclamation'],
                                        'complaint' => ['bg' => '#fce7f3', 'text' => '#db2777', 'icon' => 'flag'],
                                    ];
                                    $tc = $typeColors[$bounce['bounce_type']] ?? ['bg' => '#f3f4f6', 'text' => '#6b7280', 'icon' => 'question'];
                                    ?>
                                    <span style="background: <?= $tc['bg'] ?>; color: <?= $tc['text'] ?>; padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 500; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa-solid fa-<?= $tc['icon'] ?>"></i>
                                        <?= ucfirst($bounce['bounce_type']) ?>
                                    </span>
                                </td>
                                <td style="padding: 12px 16px; font-size: 0.85rem; color: #6b7280;">
                                    <?php if (!empty($bounce['newsletter_subject'])): ?>
                                        <?= htmlspecialchars(substr($bounce['newsletter_subject'], 0, 30)) ?><?= strlen($bounce['newsletter_subject']) > 30 ? '...' : '' ?>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div style="padding: 60px 24px; text-align: center;">
                    <div style="width: 64px; height: 64px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                        <i class="fa-solid fa-envelope-circle-check" style="font-size: 1.5rem; color: #10b981;"></i>
                    </div>
                    <h3 style="margin: 0 0 8px 0; color: #1f2937; font-size: 1rem;">No Recent Bounces</h3>
                    <p style="margin: 0; color: #6b7280; font-size: 0.9rem;">All emails delivered successfully!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Manual Suppression -->
        <div class="nexus-card" style="padding: 24px; margin-top: 24px;">
            <h2 style="margin: 0 0 16px 0; font-size: 1.1rem; color: #1f2937; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-user-slash" style="color: #6366f1;"></i>
                Manually Suppress Email
            </h2>
            <p style="color: #6b7280; font-size: 0.9rem; margin: 0 0 16px 0;">
                Add an email address to the suppression list to prevent sending newsletters to them.
            </p>
            <form action="<?= $basePath ?>/admin/newsletters/bounces/suppress" method="POST" style="display: flex; gap: 12px; flex-wrap: wrap;">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="email" name="email" required placeholder="email@example.com"
                    style="flex: 1; min-width: 250px; padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem;">
                <select name="reason" style="padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; background: white;">
                    <option value="manual">Manual Suppression</option>
                    <option value="hard_bounce">Hard Bounce</option>
                    <option value="complaint">Complaint</option>
                    <option value="unsubscribe">Unsubscribe Request</option>
                </select>
                <button type="submit" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-plus"></i> Add to Suppression
                </button>
            </form>
        </div>

        <!-- Info Box -->
        <div style="margin-top: 24px; padding: 20px 24px; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-radius: 12px; border: 1px solid #bfdbfe;">
            <h3 style="margin: 0 0 12px 0; font-size: 1rem; color: #1e40af; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-info-circle"></i>
                About Bounce Types
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; color: #1e40af; font-size: 0.9rem;">
                <div>
                    <strong>Hard Bounces:</strong> Permanent delivery failures (invalid email, domain doesn't exist). Automatically suppressed.
                </div>
                <div>
                    <strong>Soft Bounces:</strong> Temporary issues (mailbox full, server down). Suppressed after 3 occurrences.
                </div>
                <div>
                    <strong>Complaints:</strong> Recipient marked email as spam. Automatically suppressed and should not be re-added.
                </div>
            </div>
        </div>

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
