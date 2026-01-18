<?php
/**
 * AI Settings - Gold Standard Mission Control
 * STANDALONE admin interface - does NOT use main site header/footer
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'AI Settings';
$adminPageSubtitle = 'Intelligence';
$adminPageIcon = 'fa-microchip';

// Include the standalone admin header (includes <!DOCTYPE html>, <head>, etc.)
require __DIR__ . '/partials/admin-header.php';

// Safe defaults for variables
$providers = $providers ?? [];
$currentProvider = $currentProvider ?? 'gemini';
$settings = $settings ?? [];
$maskedKeys = $maskedKeys ?? [];
$usageStats = $usageStats ?? ['total_requests' => 0, 'unique_users' => 0, 'total_tokens' => 0];
$currentMonthCost = $currentMonthCost ?? 0;
$userLimitStats = $userLimitStats ?? ['total_monthly_usage' => 0, 'avg_monthly_usage' => 0];

// Calculate stats
$totalRequests = $usageStats['total_requests'] ?? 0;
$uniqueUsers = $usageStats['unique_users'] ?? 0;
$totalTokens = $usageStats['total_tokens'] ?? 0;
$configuredProviders = count(array_filter($providers, fn($p) => $p['configured'] ?? false));
?>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-microchip"></i>
            AI Configuration
        </h1>
        <p class="admin-page-subtitle">Configure AI providers, API keys, and usage limits</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back
        </a>
        <button class="admin-btn admin-btn-secondary" onclick="location.reload()">
            <i class="fa-solid fa-rotate"></i>
            Refresh
        </button>
    </div>
</div>

<!-- Primary Stats Grid -->
<div class="admin-stats-grid">
    <!-- Total Requests -->
    <div class="admin-stat-card admin-stat-purple">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-message"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($totalRequests) ?></div>
            <div class="admin-stat-label">Requests This Month</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-chart-line"></i>
            <span>Monthly</span>
        </div>
    </div>

    <!-- Active Users -->
    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($uniqueUsers) ?></div>
            <div class="admin-stat-label">Active AI Users</div>
        </div>
        <div class="admin-stat-trend admin-stat-trend-up">
            <i class="fa-solid fa-user-check"></i>
            <span>Active</span>
        </div>
    </div>

    <!-- Tokens Used -->
    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-coins"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($totalTokens / 1000, 1) ?>K</div>
            <div class="admin-stat-label">Tokens Used</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-bolt"></i>
            <span>Usage</span>
        </div>
    </div>

    <!-- Estimated Cost -->
    <div class="admin-stat-card admin-stat-blue">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-dollar-sign"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value">$<?= number_format($currentMonthCost, 2) ?></div>
            <div class="admin-stat-label">Estimated Cost</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-receipt"></i>
            <span>MTD</span>
        </div>
    </div>
</div>

<!-- Flash Messages -->
<?php if (!empty($_SESSION['flash_success'])): ?>
<div class="admin-alert admin-alert-success">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-check-circle"></i>
    </div>
    <div class="admin-alert-content">
        <div class="admin-alert-title">Settings Saved</div>
        <div class="admin-alert-text"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    </div>
</div>
<?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<form action="<?= $basePath ?>/admin/ai-settings/save" method="POST">
    <?= Csrf::input() ?>

    <!-- Main Content Grid -->
    <div class="admin-dashboard-grid">
        <!-- Left Column - Provider & Keys -->
        <div class="admin-dashboard-main">

            <!-- AI Provider Selection -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-purple">
                        <i class="fa-solid fa-brain"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">AI Provider</h3>
                        <p class="admin-card-subtitle">Select your preferred AI provider</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <p class="settings-intro">Choose from multiple AI providers. Gemini offers a generous free tier for getting started.</p>

                    <div class="form-group">
                        <label class="form-label">Select Active Provider</label>
                        <select name="ai_provider" class="form-input" id="providerSelect">
                            <?php
                            // Safety net: valid providers list
                            $allProviders = !empty($providers) ? $providers : [
                                'gemini' => ['name' => 'Google Gemini'],
                                'openai' => ['name' => 'OpenAI'],
                                'anthropic' => ['name' => 'Anthropic Claude'],
                                'ollama' => ['name' => 'Ollama (Self-Hosted)']
                            ];

                            foreach ($allProviders as $id => $provider):
                                // Check if configured from provider data
                                $isConfigured = $provider['configured'] ?? false;

                                // FALLBACK: If not configured according to provider, check if we have a key in settings
                                if (!$isConfigured && isset($settings[$id . '_api_key']) && !empty($settings[$id . '_api_key'])) {
                                    $isConfigured = true;
                                }

                                $configStatus = $isConfigured ? ' ✓' : ' (Not configured)';
                            ?>
                                <option value="<?= htmlspecialchars($id) ?>" <?= $currentProvider === $id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($provider['name'] ?? ucfirst($id)) ?><?= $configStatus ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-help">API keys must be configured below for the selected provider to work</small>
                    </div>

                    <button type="button" class="admin-btn admin-btn-secondary" onclick="testCurrentProvider()" style="width: 100%; margin-top: 1rem;">
                        <i class="fa-solid fa-plug"></i> Test Connection
                    </button>
                    <div id="testResult" class="test-result"></div>
                </div>
            </div>

            <!-- API Keys -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-orange">
                        <i class="fa-solid fa-key"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">API Keys</h3>
                        <p class="admin-card-subtitle">Configure your provider credentials</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fa-brands fa-google"></i> Google Gemini API Key
                        </label>
                        <div class="input-with-action">
                            <input type="password" name="gemini_api_key" class="form-input"
                                   value="<?= htmlspecialchars($maskedKeys['gemini'] ?? '') ?>"
                                   placeholder="Enter your Gemini API key">
                            <button type="button" class="input-action" onclick="togglePassword(this)">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-hint">
                            <a href="https://makersuite.google.com/app/apikey" target="_blank" class="hint-link">
                                <i class="fa-solid fa-external-link"></i> Get free API key
                            </a>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fa-solid fa-robot"></i> OpenAI API Key
                        </label>
                        <div class="input-with-action">
                            <input type="password" name="openai_api_key" class="form-input"
                                   value="<?= htmlspecialchars($maskedKeys['openai'] ?? '') ?>"
                                   placeholder="sk-...">
                            <button type="button" class="input-action" onclick="togglePassword(this)">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-hint">
                            <a href="https://platform.openai.com/api-keys" target="_blank" class="hint-link">
                                <i class="fa-solid fa-external-link"></i> Get API key
                            </a>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fa-solid fa-a"></i> Anthropic API Key
                        </label>
                        <div class="input-with-action">
                            <input type="password" name="anthropic_api_key" class="form-input"
                                   value="<?= htmlspecialchars($maskedKeys['anthropic'] ?? '') ?>"
                                   placeholder="sk-ant-...">
                            <button type="button" class="input-action" onclick="togglePassword(this)">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-hint">
                            <a href="https://console.anthropic.com/" target="_blank" class="hint-link">
                                <i class="fa-solid fa-external-link"></i> Get API key
                            </a>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fa-solid fa-server"></i> Ollama Host URL
                        </label>
                        <input type="text" name="ollama_host" class="form-input"
                               value="<?= htmlspecialchars($settings['ollama_host'] ?? 'http://localhost:11434') ?>"
                               placeholder="http://localhost:11434">
                        <div class="form-hint">For self-hosted Ollama installations</div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column - Features & Limits -->
        <div class="admin-dashboard-sidebar">

            <!-- Feature Toggles -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-green">
                        <i class="fa-solid fa-sliders"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Features</h3>
                        <p class="admin-card-subtitle">Enable/disable AI capabilities</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="toggle-list">
                        <div class="toggle-item">
                            <div class="toggle-info">
                                <div class="toggle-label">AI Enabled</div>
                                <div class="toggle-desc">Master switch for all AI features</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="ai_enabled" <?= ($settings['ai_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>

                        <div class="toggle-item">
                            <div class="toggle-info">
                                <div class="toggle-label">Chat Assistant</div>
                                <div class="toggle-desc">AI chat conversations</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="ai_chat_enabled" <?= ($settings['ai_chat_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>

                        <div class="toggle-item">
                            <div class="toggle-info">
                                <div class="toggle-label">Content Generation</div>
                                <div class="toggle-desc">Generate listings, events, etc.</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="ai_content_gen_enabled" <?= ($settings['ai_content_gen_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>

                        <div class="toggle-item">
                            <div class="toggle-info">
                                <div class="toggle-label">Recommendations</div>
                                <div class="toggle-desc">AI-powered suggestions</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="ai_recommendations_enabled" <?= ($settings['ai_recommendations_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Limits -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-blue">
                        <i class="fa-solid fa-gauge-high"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">User Limits</h3>
                        <p class="admin-card-subtitle">Control usage per user</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="form-group">
                        <label class="form-label">Daily Limit</label>
                        <input type="number" name="default_daily_limit" class="form-input"
                               value="<?= htmlspecialchars($settings['default_daily_limit'] ?? '50') ?>"
                               min="1" max="10000">
                        <div class="form-hint">Max requests per user per day</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Monthly Limit</label>
                        <input type="number" name="default_monthly_limit" class="form-input"
                               value="<?= htmlspecialchars($settings['default_monthly_limit'] ?? '1000') ?>"
                               min="1" max="100000">
                        <div class="form-hint">Max requests per user per month</div>
                    </div>

                    <div class="usage-summary">
                        <div class="usage-stat">
                            <div class="usage-value"><?= number_format($userLimitStats['total_monthly_usage'] ?? 0) ?></div>
                            <div class="usage-label">Total Monthly Requests</div>
                        </div>
                        <div class="usage-stat">
                            <div class="usage-value"><?= number_format($userLimitStats['avg_monthly_usage'] ?? 0, 1) ?></div>
                            <div class="usage-label">Avg Per User</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Welcome Message -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-purple">
                        <i class="fa-solid fa-message"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Welcome Message</h3>
                        <p class="admin-card-subtitle">Customize the AI greeting when users open chat</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="form-group">
                        <label class="form-label">AI Welcome Message</label>
                        <textarea name="ai_welcome_message" class="form-input" rows="4"
                                  placeholder="Hello! I am your Platform Assistant..."><?= htmlspecialchars($settings['ai_welcome_message'] ?? "Hello! I am your new Platform Assistant. 🧠\n\nI am currently in Learning Mode and digesting the database of Members and Listings. Please bear with me while I learn the ropes—I will do my best to connect you with the right offers!") ?></textarea>
                        <div class="form-hint">This message appears when users first open the AI chat interface</div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-cyan">
                        <i class="fa-solid fa-rocket"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Quick Actions</h3>
                        <p class="admin-card-subtitle">Common tasks</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="ai-quick-actions-grid">
                        <a href="<?= $basePath ?>/admin/smart-matching" class="ai-quick-action-card">
                            <div class="ai-quick-action-icon ai-icon-pink">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                            </div>
                            <span>Smart Matching</span>
                        </a>
                        <a href="<?= $basePath ?>/admin/feed-algorithm" class="ai-quick-action-card">
                            <div class="ai-quick-action-icon ai-icon-purple">
                                <i class="fa-solid fa-sliders"></i>
                            </div>
                            <span>Feed Algorithm</span>
                        </a>
                        <a href="<?= $basePath ?>/admin/algorithm-settings" class="ai-quick-action-card">
                            <div class="ai-quick-action-icon ai-icon-orange">
                                <i class="fa-solid fa-gears"></i>
                            </div>
                            <span>Algorithm Settings</span>
                        </a>
                        <a href="<?= $basePath ?>/admin" class="ai-quick-action-card">
                            <div class="ai-quick-action-icon ai-icon-blue">
                                <i class="fa-solid fa-gauge"></i>
                            </div>
                            <span>Dashboard</span>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Save Button -->
    <div class="form-actions">
        <button type="submit" class="admin-btn admin-btn-primary admin-btn-lg">
            <i class="fa-solid fa-check"></i>
            Save AI Settings
        </button>
    </div>

</form>

<!-- Module Navigation -->
<div class="admin-section-header">
    <h2 class="admin-section-title">
        <i class="fa-solid fa-grid-2"></i>
        AI & Intelligence Modules
    </h2>
    <p class="admin-section-subtitle">Access all AI administrative functions</p>
</div>

<div class="admin-modules-grid">
    <a href="<?= $basePath ?>/admin/ai-settings" class="admin-module-card admin-module-card-gradient">
        <div class="admin-module-icon admin-module-icon-gradient-indigo">
            <i class="fa-solid fa-microchip"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">AI Settings</h4>
            <p class="admin-module-desc">Provider configuration</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin/smart-matching" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-pink">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Smart Matching</h4>
            <p class="admin-module-desc">AI recommendations</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin/feed-algorithm" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-cyan">
            <i class="fa-solid fa-sliders"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Feed Algorithm</h4>
            <p class="admin-module-desc">EdgeRank configuration</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin/algorithm-settings" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-emerald">
            <i class="fa-solid fa-gears"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Algorithm Settings</h4>
            <p class="admin-module-desc">Tuning parameters</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>
</div>

<style>
/**
 * AI Settings Dashboard Specific Styles
 * These supplement the shared admin styles from admin-header.php
 */

/* Page Header */
.admin-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.admin-page-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0;
}

.admin-page-title i {
    color: #8b5cf6;
}

.admin-page-subtitle {
    color: rgba(255, 255, 255, 0.6);
    margin: 0.25rem 0 0 0;
    font-size: 0.9rem;
}

.admin-page-header-actions {
    display: flex;
    gap: 0.75rem;
}

/* Stats Grid */
.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

@media (max-width: 1200px) {
    .admin-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .admin-stats-grid {
        grid-template-columns: 1fr;
    }
}

.admin-stat-card {
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 16px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.admin-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--stat-color), transparent);
}

.admin-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
}

.admin-stat-purple { --stat-color: #8b5cf6; }
.admin-stat-green { --stat-color: #22c55e; }
.admin-stat-orange { --stat-color: #f59e0b; }
.admin-stat-blue { --stat-color: #3b82f6; }

.admin-stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    background: linear-gradient(135deg, var(--stat-color), color-mix(in srgb, var(--stat-color) 70%, #000));
    color: white;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.admin-stat-content {
    flex: 1;
}

.admin-stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: #fff;
    line-height: 1;
}

.admin-stat-label {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

.admin-stat-trend {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    padding: 0.25rem 0.5rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 6px;
}

.admin-stat-trend-up {
    color: #22c55e;
    background: rgba(34, 197, 94, 0.1);
}

/* Alert Banner */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
}

.admin-alert-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.admin-alert-success .admin-alert-icon {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.admin-alert-success .admin-alert-title {
    color: #22c55e;
}

.admin-alert-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.admin-alert-content {
    flex: 1;
}

.admin-alert-title {
    font-weight: 600;
}

.admin-alert-text {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
}

.admin-btn-primary:hover {
    box-shadow: 0 4px 20px rgba(99, 102, 241, 0.3);
    transform: translateY(-1px);
}

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.2);
}

.admin-btn-lg {
    padding: 0.875rem 2rem;
    font-size: 1rem;
}

/* Dashboard Grid Layout */
.admin-dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

@media (max-width: 1200px) {
    .admin-dashboard-grid {
        grid-template-columns: 1fr;
    }
}

.admin-dashboard-main {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.admin-dashboard-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Glass Card */
.admin-glass-card {
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 16px;
    overflow: hidden;
}

.admin-card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-card-header-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.admin-card-header-icon-purple { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
.admin-card-header-icon-green { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
.admin-card-header-icon-orange { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
.admin-card-header-icon-blue { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
.admin-card-header-icon-cyan { background: rgba(6, 182, 212, 0.2); color: #06b6d4; }

.admin-card-header-content {
    flex: 1;
}

.admin-card-title {
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
    margin: 0;
}

.admin-card-subtitle {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0.125rem 0 0 0;
}

.admin-card-body {
    padding: 1.25rem 1.5rem;
}

/* Settings Intro */
.settings-intro {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    margin: 0 0 1.25rem 0;
    line-height: 1.5;
}

/* Provider Grid - DISABLED (Now using standard dropdown) */
/*
.provider-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

.provider-card {
    position: relative;
    background: rgba(255, 255, 255, 0.03);
    border: 2px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
}

.provider-card:hover {
    border-color: rgba(139, 92, 246, 0.4);
    background: rgba(139, 92, 246, 0.05);
}

.provider-card.selected {
    border-color: #8b5cf6;
    background: rgba(139, 92, 246, 0.1);
}

.provider-card input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.provider-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    font-size: 0.6rem;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 700;
    text-transform: uppercase;
}

.badge-free {
    background: rgba(34, 197, 94, 0.2);
    color: #4ade80;
}

.badge-paid {
    background: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
}

.provider-icon {
    width: 40px;
    height: 40px;
    margin: 0 auto 0.5rem;
    border-radius: 10px;
    background: rgba(139, 92, 246, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: #a78bfa;
}

.provider-name {
    font-weight: 600;
    color: #fff;
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.provider-status {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
}

.provider-status.configured {
    color: #4ade80;
}

.provider-status i {
    margin-right: 0.25rem;
}
*/

/* Test Result */
.test-result {
    margin-top: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    display: none;
}

.test-result.testing {
    display: block;
    background: rgba(99, 102, 241, 0.1);
    color: #a5b4fc;
    border: 1px solid rgba(99, 102, 241, 0.3);
}

.test-result.success {
    display: block;
    background: rgba(34, 197, 94, 0.1);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.test-result.error {
    display: block;
    background: rgba(239, 68, 68, 0.1);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

/* Form Elements */
.form-group {
    margin-bottom: 1.25rem;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: #e2e8f0;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.form-label i {
    color: #8b5cf6;
    width: 16px;
    text-align: center;
}

.form-input {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 10px;
    color: #fff;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.form-input:focus {
    outline: none;
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.form-input::placeholder {
    color: rgba(255, 255, 255, 0.3);
}

.input-with-action {
    position: relative;
}

.input-with-action .form-input {
    padding-right: 3rem;
}

.input-action {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    padding: 0.25rem;
    transition: color 0.2s;
}

.input-action:hover {
    color: #8b5cf6;
}

.form-hint {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 0.375rem;
}

.hint-link {
    color: #8b5cf6;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    transition: color 0.2s;
}

.hint-link:hover {
    color: #a78bfa;
}

/* Toggle List */
.toggle-list {
    display: flex;
    flex-direction: column;
}

.toggle-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.875rem 0;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.toggle-item:last-child {
    border-bottom: none;
}

.toggle-info {
    flex: 1;
}

.toggle-label {
    font-weight: 600;
    color: #fff;
    font-size: 0.9rem;
}

.toggle-desc {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 0.125rem;
}

/* Toggle Switch */
.toggle-switch {
    position: relative;
    width: 48px;
    height: 26px;
    flex-shrink: 0;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(100, 116, 139, 0.4);
    transition: 0.3s;
    border-radius: 26px;
}

.toggle-slider:before {
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

input:checked + .toggle-slider {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
}

input:checked + .toggle-slider:before {
    transform: translateX(22px);
}

/* Usage Summary */
.usage-summary {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(99, 102, 241, 0.15);
}

.usage-stat {
    background: rgba(99, 102, 241, 0.1);
    padding: 0.75rem;
    border-radius: 10px;
    text-align: center;
}

.usage-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #a5b4fc;
}

.usage-label {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 0.125rem;
}

/* AI Settings Quick Actions - Unique Classes */
.ai-quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

.ai-quick-action-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 0.75rem;
    background: rgba(15, 23, 42, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 12px;
    text-decoration: none;
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.8rem;
    font-weight: 500;
    text-align: center;
    transition: all 0.2s ease;
}

.ai-quick-action-card:hover {
    background: rgba(99, 102, 241, 0.2);
    border-color: rgba(99, 102, 241, 0.5);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25);
}

.ai-quick-action-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.ai-icon-purple { background: rgba(139, 92, 246, 0.25); color: #a78bfa; }
.ai-icon-pink { background: rgba(236, 72, 153, 0.25); color: #f472b6; }
.ai-icon-orange { background: rgba(245, 158, 11, 0.25); color: #fbbf24; }
.ai-icon-blue { background: rgba(59, 130, 246, 0.25); color: #60a5fa; }

.ai-quick-action-card span {
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.8rem;
    line-height: 1.3;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    padding: 1.5rem 0;
    margin-bottom: 2rem;
}

/* Section Header */
.admin-section-header {
    margin-bottom: 1.5rem;
}

.admin-section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #fff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.admin-section-title i {
    color: #8b5cf6;
}

.admin-section-subtitle {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
    margin: 0.25rem 0 0 0;
}

/* Modules Grid */
.admin-modules-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

@media (max-width: 1200px) {
    .admin-modules-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .admin-modules-grid {
        grid-template-columns: 1fr;
    }
}

.admin-module-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 14px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.admin-module-card:hover {
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
}

.admin-module-card-gradient {
    border-color: rgba(139, 92, 246, 0.3);
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(59, 130, 246, 0.05));
}

.admin-module-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.admin-module-icon-pink { background: rgba(236, 72, 153, 0.2); color: #ec4899; }
.admin-module-icon-cyan { background: rgba(6, 182, 212, 0.2); color: #06b6d4; }
.admin-module-icon-emerald { background: rgba(16, 185, 129, 0.2); color: #10b981; }

.admin-module-icon-gradient-indigo {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.admin-module-content {
    flex: 1;
    min-width: 0;
}

.admin-module-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #fff;
    margin: 0;
}

.admin-module-desc {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0.125rem 0 0 0;
}

.admin-module-arrow {
    color: rgba(255, 255, 255, 0.3);
    font-size: 0.85rem;
    transition: all 0.2s;
}

.admin-module-card:hover .admin-module-arrow {
    color: #8b5cf6;
    transform: translateX(4px);
}

/* Responsive */
@media (max-width: 768px) {
    /* .provider-grid styles removed - now using dropdown */
}
</style>

<script>
// selectProvider function removed - now using standard dropdown instead of grid

function togglePassword(btn) {
    const input = btn.parentElement.querySelector('input');
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Initialize - no longer needed with dropdown
document.addEventListener('DOMContentLoaded', function() {
    // Dropdown handles selection state automatically
});

async function testCurrentProvider() {
    const resultEl = document.getElementById('testResult');
    resultEl.className = 'test-result testing';
    resultEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Testing connection...';

    // Get value from dropdown select instead of radio button
    const selectedProvider = document.getElementById('providerSelect')?.value || 'gemini';

    try {
        const response = await fetch('<?= $basePath ?>/admin/ai-settings/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'provider=' + selectedProvider + '&csrf_token=<?= Csrf::generate() ?>'
        });

        const data = await response.json();

        if (data.success) {
            resultEl.className = 'test-result success';
            let msg = '<i class="fa-solid fa-check-circle"></i> Connected to ' + (data.provider || 'AI');
            msg += ' using ' + (data.model || 'default model');
            msg += ' (' + data.latency_ms + 'ms)';
            resultEl.innerHTML = msg;
        } else {
            resultEl.className = 'test-result error';
            resultEl.innerHTML = '<i class="fa-solid fa-times-circle"></i> ' + (data.message || 'Connection failed');
        }
    } catch (e) {
        resultEl.className = 'test-result error';
        resultEl.innerHTML = '<i class="fa-solid fa-times-circle"></i> Error: ' + e.message;
    }
}
</script>

<?php require __DIR__ . '/partials/admin-footer.php'; ?>
