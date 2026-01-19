<?php
/**
 * Create Federation API Key
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Create API Key';
$adminPageSubtitle = 'New Federation Key';
$adminPageIcon = 'fa-key';

require __DIR__ . '/../partials/admin-header.php';

$permissions = $permissions ?? [];
?>

<a href="<?= $basePath ?>/admin/federation/api-keys" class="admin-back-link">
    <i class="fa-solid fa-arrow-left"></i> Back to API Keys
</a>

<div class="fed-admin-card" style="max-width: 600px; margin-top: 1rem;">
    <div class="fed-admin-card-header">
        <h3 class="fed-admin-card-title">
            <i class="fa-solid fa-plus"></i>
            Create API Key
        </h3>
    </div>
    <div class="fed-admin-card-body">
        <form action="<?= $basePath ?>/admin/federation/api-keys/create" method="POST">
            <?= Csrf::input() ?>

            <div class="admin-form-group">
                <label class="admin-label">Key Name *</label>
                <input type="text" name="name" class="admin-input" required placeholder="e.g., Production Integration">
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Description</label>
                <textarea name="description" class="admin-input" rows="2" placeholder="What is this key used for?"></textarea>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Permissions</label>
                <div class="admin-toggle-list">
                    <?php
                    $availablePermissions = [
                        'read:members' => ['Read Members', 'Access member directory'],
                        'read:listings' => ['Read Listings', 'Access listing data'],
                        'read:events' => ['Read Events', 'Access event data'],
                        'write:messages' => ['Send Messages', 'Send cross-tenant messages'],
                        'write:transactions' => ['Create Transactions', 'Create time credit transactions'],
                    ];
                    foreach ($availablePermissions as $key => $info):
                    ?>
                    <label class="admin-toggle-item" style="cursor: pointer;">
                        <div class="admin-toggle-info">
                            <div>
                                <div class="admin-toggle-title"><?= $info[0] ?></div>
                                <div class="admin-toggle-desc"><?= $info[1] ?></div>
                            </div>
                        </div>
                        <input type="checkbox" name="permissions[]" value="<?= $key ?>" style="width: auto;">
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Expiration</label>
                <select name="expires_in" class="admin-input">
                    <option value="">Never expires</option>
                    <option value="30">30 days</option>
                    <option value="90">90 days</option>
                    <option value="180">180 days</option>
                    <option value="365">1 year</option>
                </select>
            </div>

            <button type="submit" class="admin-btn admin-btn-primary">
                <i class="fa-solid fa-key"></i>
                Generate API Key
            </button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
