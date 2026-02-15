<?php
/**
 * Configuration Dashboard - Gold Standard v2.0
 * STANDALONE Admin Interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$isSuperAdmin = !empty($_SESSION['is_super_admin']);

// Admin header configuration
$adminPageTitle = 'Configuration';
$adminPageSubtitle = 'Settings & Secrets Management';
$adminPageIcon = 'fa-gears';

// Extract config with defaults
$environment = $config['environment'] ?? 'unknown';
$isDebug = $config['debug'] ?? false;
$vaultStatus = $config['vault'] ?? [];
$features = $config['features'] ?? [];
$vaultEnabled = $vaultStatus['vault_enabled'] ?? false;
$vaultAvailable = $vaultStatus['vault_available'] ?? false;

// Include standard admin header
require dirname(__DIR__, 2) . '/partials/admin-header.php';

// Navigation context for enterprise nav
$currentSection = 'config';
$currentPage = 'dashboard';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-sliders"></i>
            Configuration
        </h1>
        <p class="admin-page-subtitle">System environment settings and feature flags</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/enterprise" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Enterprise Hub
        </a>
        <a href="<?= $basePath ?>/admin-legacy/enterprise/config/secrets" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-vault"></i> Secrets Vault
        </a>
    </div>
</div>

<!-- Enterprise Sub-Navigation -->
<?php require dirname(__DIR__) . '/partials/nav.php'; ?>

<style>
/* Config Dashboard - Dark Mode Gold Standard */
.config-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.config-card {
    background: rgba(15, 23, 42, 0.85);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s;
}

.config-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

.config-card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.config-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
}

.config-card-icon.primary { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.config-card-icon.info { background: linear-gradient(135deg, #06b6d4, #22d3ee); }
.config-card-icon.success { background: linear-gradient(135deg, #10b981, #34d399); }
.config-card-icon.warning { background: linear-gradient(135deg, #f59e0b, #fbbf24); }

.config-card-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: #f1f5f9;
}

.config-card-subtitle {
    font-size: 0.85rem;
    color: #94a3b8;
}

.config-card-body {
    padding: 1.5rem;
}

/* Config Items */
.config-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 0;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.config-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.config-item:first-child {
    padding-top: 0;
}

.config-label {
    font-weight: 600;
    color: #f1f5f9;
}

.config-value {
    font-size: 0.9rem;
    color: #94a3b8;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.online {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-badge.offline {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.status-badge.warning {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-pulse {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: currentColor;
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Feature Flags */
.feature-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    background: rgba(99, 102, 241, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 12px;
    transition: all 0.2s;
}

.feature-item:hover {
    background: rgba(99, 102, 241, 0.1);
}

.feature-toggle {
    width: 44px;
    height: 24px;
    border-radius: 12px;
    background: rgba(99, 102, 241, 0.2);
    position: relative;
    cursor: pointer;
    transition: all 0.2s;
}

.feature-toggle.active {
    background: #10b981;
}

.feature-toggle::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: white;
    transition: all 0.2s;
}

.feature-toggle.active::after {
    left: 22px;
}

.feature-name {
    flex: 1;
    font-size: 0.875rem;
    color: #f1f5f9;
}

/* Cyber Buttons */
.cyber-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.875rem;
    text-decoration: none;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
}

.cyber-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
}

.cyber-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
}

.cyber-btn-outline {
    background: transparent;
    color: #f1f5f9;
    border: 1px solid rgba(99, 102, 241, 0.3);
}

.cyber-btn-outline:hover {
    background: rgba(99, 102, 241, 0.1);
}

/* Full width card */
.config-card-full {
    grid-column: 1 / -1;
}

/* Responsive */
@media (max-width: 768px) {
    .config-grid {
        grid-template-columns: 1fr;
    }

    .feature-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Config Grid -->
<div class="config-grid">

    <!-- Environment Info -->
    <div class="config-card">
        <div class="config-card-header">
            <div class="config-card-icon primary">
                <i class="fa-solid fa-server"></i>
            </div>
            <div>
                <div class="config-card-title">Environment</div>
                <div class="config-card-subtitle">System environment settings</div>
            </div>
        </div>
        <div class="config-card-body">
            <div class="config-item">
                <span class="config-label">Environment</span>
                <span class="status-badge <?= $environment === 'production' ? 'online' : 'warning' ?>">
                    <?= strtoupper($environment) ?>
                </span>
            </div>
            <div class="config-item">
                <span class="config-label">Debug Mode</span>
                <span class="status-badge <?= $isDebug ? 'warning' : 'online' ?>">
                    <?= $isDebug ? 'Enabled' : 'Disabled' ?>
                </span>
            </div>
            <div class="config-item">
                <span class="config-label">PHP Version</span>
                <span class="config-value"><?= PHP_VERSION ?></span>
            </div>
        </div>
    </div>

    <!-- Vault Status -->
    <div class="config-card">
        <div class="config-card-header">
            <div class="config-card-icon info">
                <i class="fa-solid fa-vault"></i>
            </div>
            <div>
                <div class="config-card-title">HashiCorp Vault</div>
                <div class="config-card-subtitle">Secrets management</div>
            </div>
        </div>
        <div class="config-card-body">
            <div class="config-item">
                <span class="config-label">Vault Status</span>
                <span class="status-badge <?= $vaultAvailable ? 'online' : 'offline' ?>">
                    <span class="status-pulse"></span>
                    <?= $vaultAvailable ? 'Connected' : 'Disconnected' ?>
                </span>
            </div>
            <div class="config-item">
                <span class="config-label">Vault Enabled</span>
                <span class="config-value"><?= $vaultEnabled ? 'Yes' : 'No' ?></span>
            </div>
            <div class="config-item">
                <a href="<?= $basePath ?>/admin-legacy/enterprise/config/secrets" class="cyber-btn cyber-btn-primary" style="width: 100%;">
                    <i class="fa-solid fa-key"></i>
                    Manage Secrets
                </a>
            </div>
        </div>
    </div>

</div>

<!-- Feature Flags -->
<div class="config-card config-card-full">
    <div class="config-card-header">
        <div class="config-card-icon success">
            <i class="fa-solid fa-toggle-on"></i>
        </div>
        <div>
            <div class="config-card-title">Feature Flags</div>
            <div class="config-card-subtitle">Enable or disable platform features. Changes take effect immediately.</div>
        </div>
    </div>
    <div class="config-card-body">
        <?php
        // Define feature groups and labels
        $featureGroups = [
            'Core Modules' => [
                'timebanking' => ['label' => 'Time Banking', 'icon' => 'fa-clock', 'desc' => 'Credit-based exchanges'],
                'listings' => ['label' => 'Listings', 'icon' => 'fa-list', 'desc' => 'Service offers & requests'],
                'messaging' => ['label' => 'Messaging', 'icon' => 'fa-comments', 'desc' => 'Direct messages'],
                'connections' => ['label' => 'Connections', 'icon' => 'fa-user-group', 'desc' => 'Member networking'],
                'profiles' => ['label' => 'Profiles', 'icon' => 'fa-id-card', 'desc' => 'User profiles'],
            ],
            'Community' => [
                'groups' => ['label' => 'Groups', 'icon' => 'fa-users', 'desc' => 'Community groups'],
                'events' => ['label' => 'Events', 'icon' => 'fa-calendar', 'desc' => 'Event management'],
                'volunteering' => ['label' => 'Volunteering', 'icon' => 'fa-hands-helping', 'desc' => 'Volunteer opportunities'],
                'organizations' => ['label' => 'Organizations', 'icon' => 'fa-building', 'desc' => 'Organization accounts'],
            ],
            'Engagement' => [
                'gamification' => ['label' => 'Gamification', 'icon' => 'fa-gamepad', 'desc' => 'XP & leveling system'],
                'leaderboard' => ['label' => 'Leaderboard', 'icon' => 'fa-trophy', 'desc' => 'Rankings & competition'],
                'badges' => ['label' => 'Badges', 'icon' => 'fa-award', 'desc' => 'Achievement badges'],
                'streaks' => ['label' => 'Streaks', 'icon' => 'fa-fire', 'desc' => 'Daily login streaks'],
            ],
            'AI & Smart Features' => [
                'ai_chat' => ['label' => 'AI Chat', 'icon' => 'fa-robot', 'desc' => 'AI assistant'],
                'smart_matching' => ['label' => 'Smart Matching', 'icon' => 'fa-wand-magic-sparkles', 'desc' => 'AI-powered matching'],
                'ai_moderation' => ['label' => 'AI Moderation', 'icon' => 'fa-shield-halved', 'desc' => 'Content moderation'],
            ],
            'Notifications' => [
                'push_notifications' => ['label' => 'Push Notifications', 'icon' => 'fa-bell', 'desc' => 'Browser & mobile push'],
                'email_notifications' => ['label' => 'Email Notifications', 'icon' => 'fa-envelope', 'desc' => 'Email alerts'],
            ],
            'Enterprise' => [
                'gdpr_compliance' => ['label' => 'GDPR Compliance', 'icon' => 'fa-user-shield', 'desc' => 'Data privacy tools'],
                'analytics' => ['label' => 'Analytics', 'icon' => 'fa-chart-line', 'desc' => 'Usage analytics'],
                'audit_logging' => ['label' => 'Audit Logging', 'icon' => 'fa-clipboard-list', 'desc' => 'Activity audit trail'],
            ],
            'Map & Location' => [
                'map_view' => ['label' => 'Map View', 'icon' => 'fa-map', 'desc' => 'Interactive maps'],
                'geolocation' => ['label' => 'Geolocation', 'icon' => 'fa-location-dot', 'desc' => 'Location services'],
            ],
        ];

        foreach ($featureGroups as $groupName => $groupFeatures):
        ?>
        <div style="margin-bottom: 28px;">
            <h4 style="margin: 0 0 14px 0; font-size: 0.8rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">
                <?= $groupName ?>
            </h4>
            <div class="feature-grid">
                <?php foreach ($groupFeatures as $key => $meta):
                    $enabled = $features[$key] ?? false;
                ?>
                <div class="feature-item" title="<?= htmlspecialchars($meta['desc']) ?>">
                    <div class="feature-toggle <?= $enabled ? 'active' : '' ?>"
                         onclick="toggleFeature('<?= $key ?>', this)"
                         data-feature="<?= $key ?>"></div>
                    <i class="fa-solid <?= $meta['icon'] ?>" style="color: #94a3b8; font-size: 0.9rem;"></i>
                    <span class="feature-name"><?= $meta['label'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(99, 102, 241, 0.15); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <p style="color: #94a3b8; font-size: 0.85rem; margin: 0;">
                <i class="fa-solid fa-info-circle" style="margin-right: 6px;"></i>
                Feature changes are saved instantly. Some features may require a page refresh to take effect.
            </p>
            <button class="cyber-btn cyber-btn-outline" onclick="resetFeatures()">
                <i class="fa-solid fa-rotate-left"></i>
                Reset to Defaults
            </button>
        </div>
    </div>
</div>

<script>
function toggleFeature(key, element) {
    const isActive = element.classList.contains('active');
    element.style.opacity = '0.5';

    fetch(`<?= $basePath ?>/admin-legacy/enterprise/config/features/${key}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ enabled: !isActive })
    })
    .then(r => r.json())
    .then(data => {
        element.style.opacity = '1';
        if (data.success) {
            element.classList.toggle('active');
        } else {
            alert('Failed to toggle feature: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        element.style.opacity = '1';
        console.error('Toggle error:', err);
        alert('Network error');
    });
}

function resetFeatures() {
    if (!confirm('Reset all feature flags to their default values?')) return;

    fetch(`<?= $basePath ?>/admin-legacy/enterprise/config/features/reset`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to reset features: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('Reset error:', err);
        alert('Network error');
    });
}
</script>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
