<?php
/**
 * Newsletter Admin Dashboard
 * Polished modern view with full layout integration
 */

// Layout detection and header
$layout = layout(); // Fixed: centralized detection
$basePath = \Nexus\Core\TenantContext::getBasePath();

// Hero settings for modern layout
$hTitle = 'Newsletter Hub';
$hSubtitle = 'Create, manage, and send beautiful email campaigns to your community';
$hGradient = 'mt-hero-gradient-brand';
$hType = 'Newsletter Admin';

else {
    require __DIR__ . '/../../layouts/modern/header.php';
}
?>

<div class="newsletter-admin-wrapper">
    <div style="max-width: 1200px; margin: 0 auto;">

        <!-- Flash Messages -->
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #065f46; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                <div style="width: 32px; height: 32px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-check" style="color: white;"></i>
                </div>
                <span style="font-weight: 500;"><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #991b1b; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                <div style="width: 32px; height: 32px; background: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-xmark" style="color: white;"></i>
                </div>
                <span style="font-weight: 500;"><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <!-- Quick Stats Cards -->
        <?php
        $totalNewsletters = count($newsletters ?? []);
        $sentCount = 0;
        $draftCount = 0;
        $scheduledCount = 0;
        foreach ($newsletters ?? [] as $n) {
            if ($n['status'] === 'sent') $sentCount++;
            elseif ($n['status'] === 'draft') $draftCount++;
            elseif ($n['status'] === 'scheduled') $scheduledCount++;
        }
        ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 30px;">
            <div class="nexus-card" style="padding: 20px; text-align: center; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 1px solid #bae6fd;">
                <div style="font-size: 2rem; font-weight: 700; color: #0369a1;"><?= $totalNewsletters ?></div>
                <div style="color: #0c4a6e; font-size: 0.85rem; font-weight: 500;">Total Newsletters</div>
            </div>
            <div class="nexus-card" style="padding: 20px; text-align: center; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1px solid #86efac;">
                <div style="font-size: 2rem; font-weight: 700; color: #15803d;"><?= $sentCount ?></div>
                <div style="color: #166534; font-size: 0.85rem; font-weight: 500;">Sent</div>
            </div>
            <div class="nexus-card" style="padding: 20px; text-align: center; background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border: 1px solid #c4b5fd;">
                <div style="font-size: 2rem; font-weight: 700; color: #7c3aed;"><?= $draftCount ?></div>
                <div style="color: #5b21b6; font-size: 0.85rem; font-weight: 500;">Drafts</div>
            </div>
            <div class="nexus-card" style="padding: 20px; text-align: center; background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border: 1px solid #fcd34d;">
                <div style="font-size: 2rem; font-weight: 700; color: #d97706;"><?= $scheduledCount ?></div>
                <div style="color: #92400e; font-size: 0.85rem; font-weight: 500;">Scheduled</div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="nexus-card" style="margin-bottom: 24px; padding: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <a href="<?= $basePath ?>/admin/newsletters/analytics" class="nexus-btn" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);">
                    <i class="fa-solid fa-chart-line"></i> Analytics
                </a>
                <a href="<?= $basePath ?>/admin/newsletters/templates" class="nexus-btn" style="background: #f3f4f6; color: #374151; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;">
                    <i class="fa-solid fa-palette"></i> Templates
                </a>
                <a href="<?= $basePath ?>/admin/newsletters/segments" class="nexus-btn" style="background: #f3f4f6; color: #374151; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;">
                    <i class="fa-solid fa-filter"></i> Segments
                </a>
                <a href="<?= $basePath ?>/admin/newsletters/subscribers" class="nexus-btn" style="background: #f3f4f6; color: #374151; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;">
                    <i class="fa-solid fa-address-book"></i> Subscribers
                </a>
                <a href="<?= $basePath ?>/admin/newsletters/bounces" class="nexus-btn" style="background: #f3f4f6; color: #374151; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;">
                    <i class="fa-solid fa-shield-halved"></i> Bounces
                </a>
                <a href="<?= $basePath ?>/admin/newsletters/diagnostics" class="nexus-btn" style="background: #f3f4f6; color: #374151; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;">
                    <i class="fa-solid fa-wrench"></i> Diagnostics
                </a>
            </div>
            <a href="<?= $basePath ?>/admin/newsletters/create" class="nexus-btn" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.4); transition: all 0.2s;">
                <i class="fa-solid fa-plus"></i> Create Newsletter
            </a>
        </div>

        <!-- Newsletters Table -->
        <div class="nexus-card" style="padding: 0; overflow: hidden; border-radius: 16px;">
            <?php if (empty($newsletters)): ?>
                <div style="padding: 80px 40px; text-align: center;">
                    <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
                        <i class="fa-solid fa-envelope-open-text" style="font-size: 2rem; color: #d97706;"></i>
                    </div>
                    <h3 style="margin: 0 0 10px 0; font-size: 1.25rem; color: #111827;">No newsletters yet</h3>
                    <p style="color: #6b7280; margin-bottom: 24px; max-width: 400px; margin-left: auto; margin-right: auto;">
                        Create your first newsletter to start engaging with your community members.
                    </p>
                    <a href="<?= $basePath ?>/admin/newsletters/create" class="nexus-btn" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-plus"></i> Create Your First Newsletter
                    </a>
                </div>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 16px 20px; text-align: left; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600;">Newsletter</th>
                            <th style="padding: 16px 20px; text-align: left; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600;">Status</th>
                            <th style="padding: 16px 20px; text-align: left; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600;">Audience</th>
                            <th style="padding: 16px 20px; text-align: center; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600;">Performance</th>
                            <th style="padding: 16px 20px; text-align: right; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($newsletters as $newsletter): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 20px;">
                                    <div style="display: flex; align-items: flex-start; gap: 14px;">
                                        <div style="width: 44px; height: 44px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <i class="fa-solid fa-envelope" style="color: #d97706;"></i>
                                        </div>
                                        <div>
                                            <a href="<?= $basePath ?>/admin/newsletters/edit/<?= $newsletter['id'] ?>" style="font-weight: 600; color: #111827; text-decoration: none; display: block; margin-bottom: 2px;">
                                                <?= htmlspecialchars($newsletter['subject']) ?>
                                            </a>
                                            <?php if (!empty($newsletter['ab_test_enabled'])): ?>
                                                <span style="background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 600; margin-right: 6px;">A/B Test</span>
                                            <?php endif; ?>
                                            <div style="font-size: 0.8rem; color: #6b7280;">
                                                <?= date('M j, Y', strtotime($newsletter['created_at'])) ?>
                                                <?php if (!empty($newsletter['author_name'])): ?>
                                                    &middot; <?= htmlspecialchars($newsletter['author_name']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 20px;">
                                    <?php
                                    $statusConfig = [
                                        'draft' => ['bg' => '#f1f5f9', 'color' => '#475569', 'icon' => 'fa-file-lines'],
                                        'scheduled' => ['bg' => '#fef3c7', 'color' => '#92400e', 'icon' => 'fa-clock'],
                                        'sending' => ['bg' => '#dbeafe', 'color' => '#1e40af', 'icon' => 'fa-paper-plane'],
                                        'sent' => ['bg' => '#dcfce7', 'color' => '#166534', 'icon' => 'fa-check-circle'],
                                        'failed' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'icon' => 'fa-exclamation-circle']
                                    ];
                                    $sc = $statusConfig[$newsletter['status']] ?? $statusConfig['draft'];
                                    ?>
                                    <div style="display: inline-flex; align-items: center; gap: 6px; background: <?= $sc['bg'] ?>; color: <?= $sc['color'] ?>; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
                                        <i class="fa-solid <?= $sc['icon'] ?>" style="font-size: 0.7rem;"></i>
                                        <?= ucfirst($newsletter['status']) ?>
                                    </div>
                                    <?php if ($newsletter['status'] === 'scheduled' && $newsletter['scheduled_at']): ?>
                                        <div style="font-size: 0.75rem; color: #6b7280; margin-top: 4px;">
                                            <i class="fa-regular fa-calendar"></i> <?= date('M j, g:ia', strtotime($newsletter['scheduled_at'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 20px;">
                                    <?php
                                    $audienceConfig = [
                                        'all_members' => ['label' => 'Members', 'bg' => '#dbeafe', 'color' => '#1e40af'],
                                        'subscribers_only' => ['label' => 'Subscribers', 'bg' => '#ede9fe', 'color' => '#6d28d9'],
                                        'both' => ['label' => 'All', 'bg' => '#dcfce7', 'color' => '#166534']
                                    ];
                                    $ac = $audienceConfig[$newsletter['target_audience'] ?? 'all_members'] ?? $audienceConfig['all_members'];
                                    ?>
                                    <span style="background: <?= $ac['bg'] ?>; color: <?= $ac['color'] ?>; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">
                                        <?= $ac['label'] ?>
                                    </span>
                                    <?php if (!empty($newsletter['segment_id'])): ?>
                                        <div style="font-size: 0.7rem; color: #6b7280; margin-top: 4px;">
                                            <i class="fa-solid fa-filter"></i> Segment applied
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 20px; text-align: center;">
                                    <?php if ($newsletter['status'] === 'sent'): ?>
                                        <?php
                                        $totalSent = $newsletter['total_sent'] ?? 0;
                                        $openRate = $totalSent > 0 ? round(($newsletter['unique_opens'] / $totalSent) * 100, 1) : 0;
                                        $clickRate = $totalSent > 0 ? round(($newsletter['unique_clicks'] / $totalSent) * 100, 1) : 0;
                                        ?>
                                        <div style="display: flex; justify-content: center; gap: 16px;">
                                            <div>
                                                <div style="font-size: 1.1rem; font-weight: 700; color: #111827;"><?= number_format($totalSent) ?></div>
                                                <div style="font-size: 0.7rem; color: #6b7280;">Sent</div>
                                            </div>
                                            <div>
                                                <div style="font-size: 1.1rem; font-weight: 700; color: #6366f1;"><?= $openRate ?>%</div>
                                                <div style="font-size: 0.7rem; color: #6b7280;">Opens</div>
                                            </div>
                                            <div>
                                                <div style="font-size: 1.1rem; font-weight: 700; color: #059669;"><?= $clickRate ?>%</div>
                                                <div style="font-size: 0.7rem; color: #6b7280;">Clicks</div>
                                            </div>
                                        </div>
                                    <?php elseif ($newsletter['total_recipients'] > 0): ?>
                                        <span style="color: #6b7280; font-size: 0.85rem;"><?= number_format($newsletter['total_recipients']) ?> queued</span>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 20px; text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <?php if ($newsletter['status'] !== 'sent'): ?>
                                            <a href="<?= $basePath ?>/admin/newsletters/edit/<?= $newsletter['id'] ?>" style="background: #f1f5f9; color: #475569; padding: 8px 14px; border-radius: 6px; text-decoration: none; font-size: 0.8rem; font-weight: 500;">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= $basePath ?>/admin/newsletters/preview/<?= $newsletter['id'] ?>" target="_blank" style="background: #f1f5f9; color: #475569; padding: 8px 14px; border-radius: 6px; text-decoration: none; font-size: 0.8rem; font-weight: 500;">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <?php if ($newsletter['status'] === 'sent'): ?>
                                            <a href="<?= $basePath ?>/admin/newsletters/stats/<?= $newsletter['id'] ?>" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 0.8rem; font-weight: 600;">
                                                <i class="fa-solid fa-chart-bar"></i> Stats
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= $basePath ?>/admin/newsletters/duplicate/<?= $newsletter['id'] ?>" style="background: #f1f5f9; color: #475569; padding: 8px 14px; border-radius: 6px; text-decoration: none; font-size: 0.8rem; font-weight: 500;" title="Duplicate">
                                            <i class="fa-solid fa-copy"></i>
                                        </a>
                                        <?php if ($newsletter['status'] !== 'sent'): ?>
                                            <form action="<?= $basePath ?>/admin/newsletters/delete" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this newsletter?');">
                                                <?= \Nexus\Core\Csrf::input() ?>
                                                <input type="hidden" name="id" value="<?= $newsletter['id'] ?>">
                                                <button type="submit" style="background: #fee2e2; color: #dc2626; padding: 8px 14px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.8rem; font-weight: 500;" title="Delete">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if (($totalPages ?? 1) > 1): ?>
            <div style="margin-top: 24px; display: flex; justify-content: center; gap: 8px;">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>" style="<?= $i == ($page ?? 1) ? 'background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white;' : 'background: #f1f5f9; color: #475569;' ?> padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 0.9rem;">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
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

    /* Desktop spacing */
    @media (min-width: 601px) {
        .newsletter-admin-wrapper {
            padding-top: 140px;
        }
    }

    /* Mobile responsiveness */
    @media (max-width: 600px) {
        .newsletter-admin-wrapper {
            padding: 120px 15px 100px 15px;
        }

        .newsletter-admin-wrapper .nexus-card table {
            font-size: 0.85rem;
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
