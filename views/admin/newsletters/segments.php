<?php
/**
 * Newsletter Segments Management
 * Modern polished view with full layout integration
 */

// Layout detection and header
$layout = layout(); // Fixed: centralized detection
$basePath = \Nexus\Core\TenantContext::getBasePath();

$segments = $segments ?? [];
$stats = [
    'total' => count($segments),
    'active' => count(array_filter($segments, fn($s) => $s['is_active'] ?? false)),
    'inactive' => count(array_filter($segments, fn($s) => !($s['is_active'] ?? false))),
    'total_members' => array_sum(array_column($segments, 'member_count'))
];

// Hero settings for modern layout
$hTitle = 'Segments';
$hSubtitle = 'Create targeted audience groups for your newsletters';
$hGradient = 'mt-hero-gradient-brand';
$hType = 'Newsletter Admin';

else {
    require __DIR__ . '/../../layouts/modern/header.php';
}
?>

<div class="newsletter-admin-wrapper">
    <div style="max-width: 1200px; margin: 0 auto;">

        <!-- Back Button & Actions Row -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
            <a href="<?= $basePath ?>/admin-legacy/newsletters" style="text-decoration: none; color: white; display: inline-flex; align-items: center; gap: 5px; background: rgba(0,0,0,0.2); padding: 6px 14px; border-radius: 20px; backdrop-filter: blur(4px); font-size: 0.9rem; transition: background 0.2s;">
                &larr; Back to Newsletters
            </a>
            <a href="<?= $basePath ?>/admin-legacy/newsletters/segments/create" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);">
                <i class="fa-solid fa-plus"></i> Create Segment
            </a>
        </div>

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

        <!-- Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <div class="nexus-card" style="padding: 24px; text-align: center; border-radius: 16px;">
                <div style="font-size: 2.5rem; font-weight: 800; color: #6366f1; line-height: 1;"><?= number_format($stats['total']) ?></div>
                <div style="color: #6b7280; font-size: 0.9rem; margin-top: 8px; font-weight: 500;">Total Segments</div>
            </div>
            <div class="nexus-card" style="padding: 24px; text-align: center; border-radius: 16px;">
                <div style="font-size: 2.5rem; font-weight: 800; color: #10b981; line-height: 1;"><?= number_format($stats['active']) ?></div>
                <div style="color: #6b7280; font-size: 0.9rem; margin-top: 8px; font-weight: 500;">Active</div>
            </div>
            <div class="nexus-card" style="padding: 24px; text-align: center; border-radius: 16px;">
                <div style="font-size: 2.5rem; font-weight: 800; color: #94a3b8; line-height: 1;"><?= number_format($stats['inactive']) ?></div>
                <div style="color: #6b7280; font-size: 0.9rem; margin-top: 8px; font-weight: 500;">Inactive</div>
            </div>
            <div class="nexus-card" style="padding: 24px; text-align: center; border-radius: 16px;">
                <div style="font-size: 2.5rem; font-weight: 800; color: #f59e0b; line-height: 1;"><?= number_format($stats['total_members']) ?></div>
                <div style="color: #6b7280; font-size: 0.9rem; margin-top: 8px; font-weight: 500;">Total Reach</div>
            </div>
        </div>

        <!-- Segments List -->
        <?php if (empty($segments)): ?>
            <div class="nexus-card" style="padding: 80px 40px; text-align: center; border-radius: 16px;">
                <div style="width: 100px; height: 100px; background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
                    <i class="fa-solid fa-layer-group" style="font-size: 2.5rem; color: #94a3b8;"></i>
                </div>
                <h3 style="margin: 0 0 12px; color: #374151; font-size: 1.4rem; font-weight: 700;">No Segments Yet</h3>
                <p style="color: #6b7280; margin: 0 0 24px; font-size: 1rem; max-width: 400px; margin-left: auto; margin-right: auto;">
                    Create segments to target specific groups of members with your newsletters based on location, groups, activity, and more.
                </p>
                <a href="<?= $basePath ?>/admin-legacy/newsletters/segments/create" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 14px 28px; border-radius: 12px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 10px; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);">
                    <i class="fa-solid fa-plus"></i> Create Your First Segment
                </a>
            </div>
        <?php else: ?>
            <div class="nexus-card" style="padding: 0; overflow: hidden; border-radius: 16px;">
                <!-- Table Header -->
                <div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 10px;">
                        <i class="fa-solid fa-layer-group" style="color: #6366f1;"></i>
                        All Segments
                    </h3>
                    <span style="background: #e0e7ff; color: #4338ca; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                        <?= count($segments) ?> segment<?= count($segments) !== 1 ? 's' : '' ?>
                    </span>
                </div>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; min-width: 700px;">
                        <thead>
                            <tr style="background: #fafafa; border-bottom: 1px solid #e5e7eb;">
                                <th style="padding: 14px 20px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing: 0.5px;">Segment</th>
                                <th style="padding: 14px 20px; text-align: center; font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing: 0.5px; width: 120px;">Members</th>
                                <th style="padding: 14px 20px; text-align: center; font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing: 0.5px; width: 100px;">Status</th>
                                <th style="padding: 14px 20px; text-align: center; font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing: 0.5px; width: 120px;">Criteria</th>
                                <th style="padding: 14px 20px; text-align: right; font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing: 0.5px; width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($segments as $segment): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background='transparent'">
                                    <td style="padding: 20px;">
                                        <div style="display: flex; align-items: center; gap: 14px;">
                                            <div style="width: 44px; height: 44px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                <i class="fa-solid fa-users" style="color: white; font-size: 1rem;"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight: 700; color: #111827; font-size: 1rem;">
                                                    <?= htmlspecialchars($segment['name']) ?>
                                                </div>
                                                <?php if (!empty($segment['description'])): ?>
                                                    <div style="font-size: 0.85rem; color: #6b7280; margin-top: 4px; max-width: 300px;">
                                                        <?= htmlspecialchars($segment['description']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="text-align: center; padding: 20px;">
                                        <div style="display: flex; flex-direction: column; align-items: center;">
                                            <span style="font-size: 1.5rem; font-weight: 800; color: #6366f1; line-height: 1;">
                                                <?= number_format($segment['member_count'] ?? 0) ?>
                                            </span>
                                            <span style="font-size: 0.75rem; color: #94a3b8; margin-top: 2px;">members</span>
                                        </div>
                                    </td>
                                    <td style="text-align: center; padding: 20px;">
                                        <?php if ($segment['is_active']): ?>
                                            <span style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #065f46; padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                                                <i class="fa-solid fa-check-circle" style="font-size: 0.7rem;"></i> Active
                                            </span>
                                        <?php else: ?>
                                            <span style="background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); color: #6b7280; padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                                                <i class="fa-solid fa-pause-circle" style="font-size: 0.7rem;"></i> Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center; padding: 20px;">
                                        <div style="display: flex; flex-wrap: wrap; gap: 4px; justify-content: center;">
                                            <?php if (!empty($segment['rules']['conditions'])): ?>
                                                <?php
                                                $conditionIcons = [
                                                    'county' => ['icon' => 'fa-map-marker-alt', 'color' => '#ef4444'],
                                                    'town' => ['icon' => 'fa-city', 'color' => '#f59e0b'],
                                                    'group' => ['icon' => 'fa-users', 'color' => '#10b981'],
                                                    'role' => ['icon' => 'fa-user-tag', 'color' => '#6366f1'],
                                                    'type' => ['icon' => 'fa-id-badge', 'color' => '#8b5cf6'],
                                                    'joined' => ['icon' => 'fa-calendar', 'color' => '#06b6d4'],
                                                    'activity' => ['icon' => 'fa-chart-line', 'color' => '#ec4899']
                                                ];
                                                foreach (array_slice($segment['rules']['conditions'], 0, 3) as $condition):
                                                    $field = strtolower($condition['field'] ?? '');
                                                    $iconData = $conditionIcons[$field] ?? ['icon' => 'fa-filter', 'color' => '#6b7280'];
                                                ?>
                                                    <span style="display: inline-flex; align-items: center; gap: 5px; background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); color: #374151; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 500;">
                                                        <i class="fa-solid <?= $iconData['icon'] ?>" style="color: <?= $iconData['color'] ?>; font-size: 0.65rem;"></i>
                                                        <?= htmlspecialchars(ucfirst($condition['field'])) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                                <?php if (count($segment['rules']['conditions']) > 3): ?>
                                                    <span style="background: #e0e7ff; color: #4338ca; padding: 4px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 600;">
                                                        +<?= count($segment['rules']['conditions']) - 3 ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: #94a3b8; font-size: 0.85rem; font-style: italic;">No rules</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="text-align: right; padding: 20px;">
                                        <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                            <a href="<?= $basePath ?>/admin-legacy/newsletters/segments/edit/<?= $segment['id'] ?>"
                                               style="background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); color: #475569; padding: 8px 14px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s;"
                                               onmouseover="this.style.background='linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)'; this.style.color='white';"
                                               onmouseout="this.style.background='linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%)'; this.style.color='#475569';">
                                                <i class="fa-solid fa-pen" style="font-size: 0.75rem;"></i> Edit
                                            </a>
                                            <form action="<?= $basePath ?>/admin-legacy/newsletters/segments/delete" method="POST" style="display: inline; margin: 0;"
                                                  onsubmit="return confirm('Delete this segment? This action cannot be undone.')">
                                                <?= \Nexus\Core\Csrf::input() ?>
                                                <input type="hidden" name="id" value="<?= $segment['id'] ?>">
                                                <button type="submit" style="background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); color: #dc2626; padding: 8px 14px; border-radius: 8px; border: none; font-size: 0.85rem; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s;"
                                                        onmouseover="this.style.background='linear-gradient(135deg, #ef4444 0%, #dc2626 100%)'; this.style.color='white';"
                                                        onmouseout="this.style.background='linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%)'; this.style.color='#dc2626';">
                                                    <i class="fa-solid fa-trash" style="font-size: 0.75rem;"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Targeting Options Help Card -->
        <div class="nexus-card" style="margin-top: 24px; padding: 28px; border-radius: 16px; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 1px solid #bfdbfe;">
            <div style="display: flex; align-items: flex-start; gap: 16px; margin-bottom: 20px;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i class="fa-solid fa-bullseye" style="color: white; font-size: 1.2rem;"></i>
                </div>
                <div>
                    <h3 style="margin: 0 0 4px; font-size: 1.1rem; font-weight: 700; color: #1e40af;">Segment Targeting Options</h3>
                    <p style="margin: 0; color: #3b82f6; font-size: 0.9rem;">Target your newsletters to the right audience using these criteria</p>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
                <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-map-location-dot" style="color: #d97706; font-size: 0.9rem;"></i>
                        </div>
                        <strong style="color: #1e40af; font-size: 0.95rem;">Geographic</strong>
                    </div>
                    <ul style="margin: 0; padding-left: 20px; color: #374151; font-size: 0.85rem; line-height: 1.7;">
                        <li>Target by county</li>
                        <li>Target by town</li>
                        <li>Radius around a location</li>
                    </ul>
                </div>

                <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-user-group" style="color: #059669; font-size: 0.9rem;"></i>
                        </div>
                        <strong style="color: #1e40af; font-size: 0.95rem;">Groups</strong>
                    </div>
                    <ul style="margin: 0; padding-left: 20px; color: #374151; font-size: 0.85rem; line-height: 1.7;">
                        <li>Members of specific groups</li>
                        <li>Exclude group members</li>
                        <li>Multiple group targeting</li>
                    </ul>
                </div>

                <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-chart-simple" style="color: #4f46e5; font-size: 0.9rem;"></i>
                        </div>
                        <strong style="color: #1e40af; font-size: 0.95rem;">Activity</strong>
                    </div>
                    <ul style="margin: 0; padding-left: 20px; color: #374151; font-size: 0.85rem; line-height: 1.7;">
                        <li>New vs long-term members</li>
                        <li>Active sellers/listings</li>
                        <li>Engagement level</li>
                    </ul>
                </div>

                <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #fce7f3 0%, #fbcfe8 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-id-card" style="color: #db2777; font-size: 0.9rem;"></i>
                        </div>
                        <strong style="color: #1e40af; font-size: 0.95rem;">Profile</strong>
                    </div>
                    <ul style="margin: 0; padding-left: 20px; color: #374151; font-size: 0.85rem; line-height: 1.7;">
                        <li>Individual vs Organisation</li>
                        <li>User roles</li>
                        <li>Profile completeness</li>
                    </ul>
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
