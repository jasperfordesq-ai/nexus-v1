<?php
/**
 * Newsletter Subscribers Management
 * Modern polished view with full layout integration
 */

// Layout detection and header
$layout = layout(); // Fixed: centralized detection
$basePath = \Nexus\Core\TenantContext::getBasePath();

$stats = $stats ?? ['total' => 0, 'active' => 0, 'pending' => 0, 'unsubscribed' => 0, 'members' => 0, 'external' => 0];
$subscribers = $subscribers ?? [];
$currentStatus = $currentStatus ?? null;
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;

// Hero settings for modern layout
$hTitle = 'Subscribers';
$hSubtitle = 'Manage your newsletter mailing list';
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
            <a href="<?= $basePath ?>/admin/newsletters" style="text-decoration: none; color: white; display: inline-flex; align-items: center; gap: 5px; background: rgba(0,0,0,0.2); padding: 6px 14px; border-radius: 20px; backdrop-filter: blur(4px); font-size: 0.9rem; transition: background 0.2s;">
                &larr; Back to Newsletters
            </a>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="<?= $basePath ?>/admin/newsletters/subscribers/export" style="background: rgba(255,255,255,0.15); color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px; backdrop-filter: blur(4px);">
                    <i class="fa-solid fa-download"></i> Export CSV
                </a>
                <button onclick="document.getElementById('import-modal').style.display='flex'" style="background: rgba(255,255,255,0.15); color: white; padding: 8px 16px; border-radius: 8px; border: none; font-weight: 500; font-size: 0.9rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; backdrop-filter: blur(4px);">
                    <i class="fa-solid fa-upload"></i> Import CSV
                </button>
                <button onclick="document.getElementById('add-modal').style.display='flex'" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 8px 16px; border-radius: 8px; border: none; font-weight: 600; font-size: 0.9rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);">
                    <i class="fa-solid fa-plus"></i> Add Subscriber
                </button>
            </div>
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
            <a href="<?= $basePath ?>/admin/newsletters/subscribers" class="nexus-card" style="padding: 24px; text-align: center; text-decoration: none; transition: all 0.2s; border-radius: 16px; <?= !$currentStatus ? 'border: 2px solid #6366f1; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);' : '' ?>">
                <div style="font-size: 2.5rem; font-weight: 800; color: #111827; line-height: 1;"><?= number_format($stats['total'] ?? 0) ?></div>
                <div style="color: #6b7280; font-size: 0.9rem; margin-top: 8px; font-weight: 500;">Total Subscribers</div>
            </a>
            <a href="?status=active" class="nexus-card" style="padding: 24px; text-align: center; text-decoration: none; transition: all 0.2s; border-radius: 16px; <?= $currentStatus === 'active' ? 'border: 2px solid #10b981; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);' : '' ?>">
                <div style="font-size: 2.5rem; font-weight: 800; color: #10b981; line-height: 1;"><?= number_format($stats['active'] ?? 0) ?></div>
                <div style="color: #6b7280; font-size: 0.9rem; margin-top: 8px; font-weight: 500;">Active</div>
            </a>
            <a href="?status=pending" class="nexus-card" style="padding: 24px; text-align: center; text-decoration: none; transition: all 0.2s; border-radius: 16px; <?= $currentStatus === 'pending' ? 'border: 2px solid #f59e0b; box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.1);' : '' ?>">
                <div style="font-size: 2.5rem; font-weight: 800; color: #f59e0b; line-height: 1;"><?= number_format($stats['pending'] ?? 0) ?></div>
                <div style="color: #6b7280; font-size: 0.9rem; margin-top: 8px; font-weight: 500;">Pending</div>
            </a>
            <a href="?status=unsubscribed" class="nexus-card" style="padding: 24px; text-align: center; text-decoration: none; transition: all 0.2s; border-radius: 16px; <?= $currentStatus === 'unsubscribed' ? 'border: 2px solid #ef4444; box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);' : '' ?>">
                <div style="font-size: 2.5rem; font-weight: 800; color: #ef4444; line-height: 1;"><?= number_format($stats['unsubscribed'] ?? 0) ?></div>
                <div style="color: #6b7280; font-size: 0.9rem; margin-top: 8px; font-weight: 500;">Unsubscribed</div>
            </a>
        </div>

        <!-- Sync Members Card -->
        <div class="nexus-card" style="padding: 20px 24px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; border-radius: 16px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1px solid #e2e8f0;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-users-gear" style="color: white; font-size: 1.2rem;"></i>
                </div>
                <div>
                    <div style="font-weight: 700; color: #111827; font-size: 1rem;">Sync Platform Members</div>
                    <div style="color: #6b7280; font-size: 0.9rem;">Add all existing platform members to your subscriber list</div>
                </div>
            </div>
            <form action="<?= $basePath ?>/admin/newsletters/subscribers/sync" method="POST" data-turbo="false" style="margin: 0;">
                <?= \Nexus\Core\Csrf::input() ?>
                <button type="submit" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 10px 20px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);">
                    <i class="fa-solid fa-sync"></i> Sync Now
                </button>
            </form>
        </div>

        <!-- Subscribers Table Card -->
        <div class="nexus-card" style="padding: 0; overflow: hidden; border-radius: 16px;">
            <!-- Table Header -->
            <div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-address-book" style="color: #f59e0b;"></i>
                    <?php if ($currentStatus): ?>
                        <?= ucfirst($currentStatus) ?> Subscribers
                    <?php else: ?>
                        All Subscribers
                    <?php endif; ?>
                </h3>
                <!-- Filter Pills -->
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="<?= $basePath ?>/admin/newsletters/subscribers" style="padding: 6px 14px; border-radius: 20px; text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: all 0.2s; <?= !$currentStatus ? 'background: #6366f1; color: white;' : 'background: #f1f5f9; color: #64748b;' ?>">
                        All
                    </a>
                    <a href="?status=active" style="padding: 6px 14px; border-radius: 20px; text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: all 0.2s; <?= $currentStatus === 'active' ? 'background: #10b981; color: white;' : 'background: #f1f5f9; color: #64748b;' ?>">
                        Active
                    </a>
                    <a href="?status=pending" style="padding: 6px 14px; border-radius: 20px; text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: all 0.2s; <?= $currentStatus === 'pending' ? 'background: #f59e0b; color: white;' : 'background: #f1f5f9; color: #64748b;' ?>">
                        Pending
                    </a>
                    <a href="?status=unsubscribed" style="padding: 6px 14px; border-radius: 20px; text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: all 0.2s; <?= $currentStatus === 'unsubscribed' ? 'background: #ef4444; color: white;' : 'background: #f1f5f9; color: #64748b;' ?>">
                        Unsubscribed
                    </a>
                </div>
            </div>

            <?php if (!empty($subscribers)): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; min-width: 700px;">
                    <thead>
                        <tr style="background: #fafafa; border-bottom: 1px solid #e5e7eb;">
                            <th style="padding: 14px 20px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing: 0.5px;">Email</th>
                            <th style="padding: 14px 20px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing: 0.5px;">Name</th>
                            <th style="padding: 14px 20px; text-align: center; font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing: 0.5px;">Status</th>
                            <th style="padding: 14px 20px; text-align: center; font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing: 0.5px;">Source</th>
                            <th style="padding: 14px 20px; text-align: center; font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing: 0.5px;">Date</th>
                            <th style="padding: 14px 20px; text-align: right; font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing: 0.5px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subscribers as $sub): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 16px 20px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.85rem;">
                                            <?= strtoupper(substr($sub['email'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <span style="font-weight: 600; color: #111827;"><?= htmlspecialchars($sub['email']) ?></span>
                                            <?php if ($sub['user_id']): ?>
                                                <span style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #1e40af; padding: 2px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 600; margin-left: 8px;">Member</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 16px 20px; color: #4b5563;">
                                    <?php
                                    $name = trim(($sub['first_name'] ?? '') . ' ' . ($sub['last_name'] ?? ''));
                                    if (!$name && $sub['user_id']) {
                                        $name = trim(($sub['member_first_name'] ?? '') . ' ' . ($sub['member_last_name'] ?? ''));
                                    }
                                    echo htmlspecialchars($name ?: '—');
                                    ?>
                                </td>
                                <td style="padding: 16px 20px; text-align: center;">
                                    <?php
                                    $statusStyles = [
                                        'active' => ['bg' => 'linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%)', 'color' => '#065f46', 'icon' => 'fa-check-circle'],
                                        'pending' => ['bg' => 'linear-gradient(135deg, #fef3c7 0%, #fde68a 100%)', 'color' => '#92400e', 'icon' => 'fa-clock'],
                                        'unsubscribed' => ['bg' => 'linear-gradient(135deg, #fee2e2 0%, #fecaca 100%)', 'color' => '#991b1b', 'icon' => 'fa-times-circle']
                                    ];
                                    $style = $statusStyles[$sub['status']] ?? $statusStyles['pending'];
                                    ?>
                                    <span style="background: <?= $style['bg'] ?>; color: <?= $style['color'] ?>; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; display: inline-flex; align-items: center; gap: 5px;">
                                        <i class="fa-solid <?= $style['icon'] ?>" style="font-size: 0.7rem;"></i>
                                        <?= $sub['status'] ?>
                                    </span>
                                </td>
                                <td style="padding: 16px 20px; text-align: center;">
                                    <span style="color: #6b7280; font-size: 0.85rem; text-transform: capitalize; background: #f1f5f9; padding: 4px 10px; border-radius: 6px;">
                                        <?= str_replace('_', ' ', $sub['source'] ?? 'signup') ?>
                                    </span>
                                </td>
                                <td style="padding: 16px 20px; text-align: center; color: #6b7280; font-size: 0.85rem;">
                                    <?= date('M j, Y', strtotime($sub['created_at'])) ?>
                                </td>
                                <td style="padding: 16px 20px; text-align: right;">
                                    <form action="<?= $basePath ?>/admin/newsletters/subscribers/delete" method="POST" style="display: inline;" onsubmit="return confirm('Remove this subscriber?');">
                                        <?= \Nexus\Core\Csrf::input() ?>
                                        <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                        <button type="submit" style="background: none; border: none; color: #dc2626; cursor: pointer; font-size: 0.85rem; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; padding: 6px 10px; border-radius: 6px; transition: background 0.15s;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='transparent'">
                                            <i class="fa-solid fa-trash" style="font-size: 0.75rem;"></i> Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div style="padding: 80px 40px; text-align: center;">
                    <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="fa-solid fa-inbox" style="font-size: 2rem; color: #94a3b8;"></i>
                    </div>
                    <h3 style="margin: 0 0 10px; color: #374151; font-size: 1.2rem;">No subscribers found</h3>
                    <p style="color: #6b7280; margin: 0 0 20px; font-size: 0.95rem;">Add subscribers manually or sync your existing platform members.</p>
                    <button onclick="document.getElementById('add-modal').style.display='flex'" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 12px 24px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-plus"></i> Add Your First Subscriber
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div style="margin-top: 24px; display: flex; justify-content: center; gap: 8px;">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?><?= $currentStatus ? '&status=' . $currentStatus : '' ?>"
                       style="padding: 10px 16px; border-radius: 10px; text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: all 0.2s; <?= $i == $page ? 'background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);' : 'background: white; color: #374151; border: 1px solid #e5e7eb;' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

        <!-- Public Subscribe Link Card -->
        <div class="nexus-card" style="margin-top: 24px; padding: 24px; border-radius: 16px;">
            <div style="display: flex; align-items: flex-start; gap: 16px;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i class="fa-solid fa-link" style="color: white; font-size: 1.2rem;"></i>
                </div>
                <div style="flex: 1;">
                    <h3 style="margin: 0 0 8px; font-size: 1.1rem; font-weight: 700; color: #111827;">Public Subscribe Page</h3>
                    <p style="color: #6b7280; margin: 0 0 16px; font-size: 0.9rem;">Share this link to let people subscribe to your newsletter:</p>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <input type="text" readonly value="<?= htmlspecialchars((\Nexus\Core\Env::get('APP_URL') ?? '') . $basePath . '/newsletter/subscribe') ?>"
                               style="flex: 1; min-width: 300px; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 10px; background: #f9fafb; font-family: 'Monaco', 'Menlo', monospace; font-size: 0.85rem; color: #374151;"
                               onclick="this.select()" id="subscribe-link">
                        <button onclick="copySubscribeLink()" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 12px 20px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-copy"></i> Copy
                        </button>
                        <a href="<?= $basePath ?>/newsletter/subscribe" target="_blank" style="background: #f1f5f9; color: #475569; padding: 12px 20px; border-radius: 10px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-external-link"></i> Preview
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Subscriber Modal -->
<div id="add-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; padding: 32px; border-radius: 20px; max-width: 480px; width: 90%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);">
        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 24px;">
            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-user-plus" style="color: white; font-size: 1.2rem;"></i>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 1.2rem; font-weight: 700; color: #111827;">Add Subscriber</h3>
                <p style="margin: 4px 0 0; color: #6b7280; font-size: 0.9rem;">Add a new person to your mailing list</p>
            </div>
        </div>

        <form action="<?= $basePath ?>/admin/newsletters/subscribers/add" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #374151;">Email Address *</label>
                <input type="email" name="email" required class="nexus-input" style="width: 100%; padding: 12px 16px; border-radius: 10px; border: 2px solid #e5e7eb; font-size: 1rem;" placeholder="email@example.com">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #374151;">First Name</label>
                    <input type="text" name="first_name" class="nexus-input" style="width: 100%; padding: 12px 16px; border-radius: 10px; border: 2px solid #e5e7eb;" placeholder="John">
                </div>
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #374151;">Last Name</label>
                    <input type="text" name="last_name" class="nexus-input" style="width: 100%; padding: 12px 16px; border-radius: 10px; border: 2px solid #e5e7eb;" placeholder="Doe">
                </div>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('add-modal').style.display='none'" style="background: #f1f5f9; color: #475569; padding: 12px 24px; border-radius: 10px; border: none; font-weight: 500; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 12px 24px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);">
                    <i class="fa-solid fa-plus" style="margin-right: 6px;"></i> Add Subscriber
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Import Modal -->
<div id="import-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; padding: 32px; border-radius: 20px; max-width: 480px; width: 90%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);">
        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 24px;">
            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-file-import" style="color: white; font-size: 1.2rem;"></i>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 1.2rem; font-weight: 700; color: #111827;">Import Subscribers</h3>
                <p style="margin: 4px 0 0; color: #6b7280; font-size: 0.9rem;">Upload a CSV file to bulk import</p>
            </div>
        </div>

        <form action="<?= $basePath ?>/admin/newsletters/subscribers/import" method="POST" enctype="multipart/form-data">
            <?= \Nexus\Core\Csrf::input() ?>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #374151;">CSV File</label>
                <div style="border: 2px dashed #e5e7eb; border-radius: 12px; padding: 30px; text-align: center; background: #fafafa;">
                    <input type="file" name="csv_file" accept=".csv" required id="csv-file-input" style="display: none;">
                    <label for="csv-file-input" style="cursor: pointer;">
                        <div style="width: 48px; height: 48px; background: #e5e7eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                            <i class="fa-solid fa-cloud-upload" style="color: #6b7280; font-size: 1.3rem;"></i>
                        </div>
                        <div style="font-weight: 600; color: #374151; margin-bottom: 4px;">Click to upload</div>
                        <div style="color: #6b7280; font-size: 0.85rem;">or drag and drop</div>
                    </label>
                </div>
            </div>

            <div style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); padding: 16px; border-radius: 12px; margin-bottom: 24px;">
                <div style="font-weight: 600; color: #1e40af; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-info-circle"></i> CSV Format
                </div>
                <div style="color: #3b82f6; font-size: 0.85rem; font-family: monospace;">
                    email, first_name, last_name
                </div>
                <div style="color: #64748b; font-size: 0.8rem; margin-top: 6px;">First row should be the header</div>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('import-modal').style.display='none'" style="background: #f1f5f9; color: #475569; padding: 12px 24px; border-radius: 10px; border: none; font-weight: 500; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 12px 24px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);">
                    <i class="fa-solid fa-upload" style="margin-right: 6px;"></i> Import
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Copy subscribe link
function copySubscribeLink() {
    const input = document.getElementById('subscribe-link');
    input.select();
    document.execCommand('copy');
    alert('Link copied to clipboard!');
}

// Close modals on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('add-modal').style.display = 'none';
        document.getElementById('import-modal').style.display = 'none';
    }
});

// Close modals on backdrop click
['add-modal', 'import-modal'].forEach(function(id) {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
});
</script>

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
