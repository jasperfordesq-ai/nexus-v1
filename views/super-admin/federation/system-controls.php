<?php
/**
 * Super Admin Federation System Controls
 * Master kill switch and global feature toggles
 */

use Nexus\Core\Csrf;

$pageTitle = $pageTitle ?? 'Federation System Controls';
require __DIR__ . '/../partials/header.php';
?>

<!-- Page Header -->
<div class="super-page-header">
    <div>
        <h1 class="super-page-title">
            <i class="fa-solid fa-sliders"></i>
            Federation System Controls
        </h1>
        <p class="super-page-subtitle">
            Master controls for platform-wide federation settings
        </p>
    </div>
    <div class="super-page-actions">
        <a href="/super-admin/federation" class="super-btn super-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Overview
        </a>
    </div>
</div>

<!-- Emergency Lockdown Card -->
<div class="super-card" style="border: 2px solid #dc2626; margin-bottom: 1.5rem;">
    <div class="super-card-header" style="background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);">
        <h3 class="super-card-title" style="color: #dc2626;">
            <i class="fa-solid fa-triangle-exclamation"></i>
            Emergency Controls
        </h3>
    </div>
    <div class="super-card-body">
        <?php if (!empty($systemStatus['emergency_lockdown_active'])): ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem; color: #dc2626; font-weight: 600;">
                <i class="fa-solid fa-lock"></i>
                LOCKDOWN ACTIVE
            </div>
            <p style="margin: 0.5rem 0 0 0; color: #991b1b;">
                <?= htmlspecialchars($systemStatus['emergency_lockdown_reason'] ?? 'No reason provided') ?>
            </p>
            <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; color: #7f1d1d;">
                Activated: <?= date('M j, Y g:i A', strtotime($systemStatus['emergency_lockdown_at'])) ?>
            </p>
        </div>
        <button onclick="liftLockdown()" class="super-btn super-btn-primary" style="background: #16a34a;">
            <i class="fa-solid fa-unlock"></i>
            Lift Emergency Lockdown
        </button>
        <?php else: ?>
        <p style="color: var(--super-text-muted); margin-bottom: 1rem;">
            Emergency lockdown immediately disables ALL federation features across the entire platform.
            Use only in case of security incidents or critical issues.
        </p>
        <button onclick="triggerLockdown()" class="super-btn" style="background: #dc2626; color: white;">
            <i class="fa-solid fa-lock"></i>
            Trigger Emergency Lockdown
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Master Kill Switch -->
<div class="super-card" style="margin-bottom: 1.5rem;">
    <div class="super-card-header">
        <h3 class="super-card-title">
            <i class="fa-solid fa-power-off"></i>
            Master Kill Switch
        </h3>
    </div>
    <div class="super-card-body">
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: var(--super-bg); border-radius: 8px;">
            <div>
                <div style="font-weight: 600; font-size: 1.1rem;">Federation System</div>
                <p style="margin: 0.25rem 0 0 0; color: var(--super-text-muted);">
                    Main switch to enable/disable all federation features platform-wide
                </p>
            </div>
            <label class="super-toggle">
                <input type="checkbox" id="federation_enabled" <?= $systemStatus['federation_enabled'] ? 'checked' : '' ?>
                    onchange="updateControl('federation_enabled', this.checked)">
                <span class="super-toggle-slider"></span>
            </label>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: var(--super-bg); border-radius: 8px; margin-top: 1rem;">
            <div>
                <div style="font-weight: 600;">Whitelist Mode</div>
                <p style="margin: 0.25rem 0 0 0; color: var(--super-text-muted);">
                    When enabled, only whitelisted tenants can participate in federation
                </p>
            </div>
            <label class="super-toggle">
                <input type="checkbox" id="whitelist_mode_enabled" <?= $systemStatus['whitelist_mode_enabled'] ? 'checked' : '' ?>
                    onchange="updateControl('whitelist_mode_enabled', this.checked)">
                <span class="super-toggle-slider"></span>
            </label>
        </div>

        <div style="padding: 1rem; background: var(--super-bg); border-radius: 8px; margin-top: 1rem;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-weight: 600;">Maximum Federation Level</div>
                    <p style="margin: 0.25rem 0 0 0; color: var(--super-text-muted);">
                        Highest level of federation allowed (0 = disabled, 4 = full)
                    </p>
                </div>
                <select id="max_federation_level" class="super-input" style="width: 150px;"
                    onchange="updateControl('max_federation_level', this.value)">
                    <option value="0" <?= $systemStatus['max_federation_level'] == 0 ? 'selected' : '' ?>>0 - Disabled</option>
                    <option value="1" <?= $systemStatus['max_federation_level'] == 1 ? 'selected' : '' ?>>1 - Discovery</option>
                    <option value="2" <?= $systemStatus['max_federation_level'] == 2 ? 'selected' : '' ?>>2 - Social</option>
                    <option value="3" <?= $systemStatus['max_federation_level'] == 3 ? 'selected' : '' ?>>3 - Economic</option>
                    <option value="4" <?= $systemStatus['max_federation_level'] == 4 ? 'selected' : '' ?>>4 - Integrated</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Cross-Tenant Feature Toggles -->
<div class="super-card">
    <div class="super-card-header">
        <h3 class="super-card-title">
            <i class="fa-solid fa-toggle-on"></i>
            Cross-Tenant Features
        </h3>
    </div>
    <div class="super-card-body">
        <p style="color: var(--super-text-muted); margin-bottom: 1.5rem;">
            Enable or disable specific cross-tenant features. These are global caps - tenants can only enable
            features that are enabled here.
        </p>

        <div style="display: grid; gap: 1rem;">
            <?php
            $features = [
                'cross_tenant_profiles_enabled' => [
                    'title' => 'Cross-Tenant Profiles',
                    'desc' => 'Allow users to view profiles from other tenants',
                    'icon' => 'fa-user'
                ],
                'cross_tenant_messaging_enabled' => [
                    'title' => 'Cross-Tenant Messaging',
                    'desc' => 'Allow users to send messages to users in partner tenants',
                    'icon' => 'fa-envelope'
                ],
                'cross_tenant_transactions_enabled' => [
                    'title' => 'Cross-Tenant Transactions',
                    'desc' => 'Allow time credit exchanges between partner tenants',
                    'icon' => 'fa-exchange-alt'
                ],
                'cross_tenant_listings_enabled' => [
                    'title' => 'Cross-Tenant Listings',
                    'desc' => 'Allow listings to be visible to partner tenants',
                    'icon' => 'fa-list'
                ],
                'cross_tenant_events_enabled' => [
                    'title' => 'Cross-Tenant Events',
                    'desc' => 'Allow events to be visible/joinable by partner tenants',
                    'icon' => 'fa-calendar'
                ],
                'cross_tenant_groups_enabled' => [
                    'title' => 'Cross-Tenant Groups',
                    'desc' => 'Allow groups to accept members from partner tenants',
                    'icon' => 'fa-users'
                ],
            ];
            foreach ($features as $key => $feature):
                $enabled = !empty($systemStatus[$key]);
            ?>
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: var(--super-bg); border-radius: 8px;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 40px; height: 40px; background: var(--super-card-bg); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid <?= $feature['icon'] ?>" style="color: var(--super-primary);"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600;"><?= $feature['title'] ?></div>
                        <p style="margin: 0.25rem 0 0 0; color: var(--super-text-muted); font-size: 0.9rem;">
                            <?= $feature['desc'] ?>
                        </p>
                    </div>
                </div>
                <label class="super-toggle">
                    <input type="checkbox" id="<?= $key ?>" <?= $enabled ? 'checked' : '' ?>
                        onchange="updateControl('<?= $key ?>', this.checked)">
                    <span class="super-toggle-slider"></span>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Status Message -->
<div id="statusMessage" style="display: none; position: fixed; bottom: 2rem; right: 2rem; padding: 1rem 1.5rem; border-radius: 8px; color: white; z-index: 1000;"></div>

<style>
.super-toggle {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
}
.super-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}
.super-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.3s;
    border-radius: 26px;
}
.super-toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}
.super-toggle input:checked + .super-toggle-slider {
    background-color: #16a34a;
}
.super-toggle input:checked + .super-toggle-slider:before {
    transform: translateX(24px);
}
</style>

<script>
const csrfToken = '<?= Csrf::token() ?>';

function updateControl(field, value) {
    const data = {};
    data[field] = value;

    fetch('/super-admin/federation/update-system-controls', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(data => {
        showStatus(data.success ? 'Setting updated' : (data.error || 'Update failed'), data.success);
    })
    .catch(err => {
        showStatus('Network error', false);
    });
}

function triggerLockdown() {
    const reason = prompt('Enter reason for emergency lockdown:');
    if (!reason) return;

    fetch('/super-admin/federation/emergency-lockdown', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ reason: reason })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to trigger lockdown');
        }
    });
}

function liftLockdown() {
    if (!confirm('Are you sure you want to lift the emergency lockdown?')) return;

    fetch('/super-admin/federation/lift-lockdown', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to lift lockdown');
        }
    });
}

function showStatus(message, success) {
    const el = document.getElementById('statusMessage');
    el.textContent = message;
    el.style.background = success ? '#16a34a' : '#dc2626';
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 3000);
}
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
