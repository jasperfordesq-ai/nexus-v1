<?php

namespace Nexus\Controllers\Admin;

// Security: Debug output disabled in production
// Enable only in development by setting APP_ENV=development in .env
if (getenv('APP_ENV') === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Models\AiSettings;
use Nexus\Models\AiUsage;
use Nexus\Models\AiUserLimit;
use Nexus\Services\AI\AIServiceFactory;

/**
 * Admin AI Settings Controller
 *
 * Manages AI configuration, API keys, and usage analytics.
 */
class AiSettingsController
{
    private function requireAdmin()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']);
        $isSuper = !empty($_SESSION['is_super_admin']);
        $isAdminSession = !empty($_SESSION['is_admin']);

        if (!$isAdmin && !$isSuper && !$isAdminSession) {
            header('HTTP/1.0 403 Forbidden');
            echo "<h1>403 Forbidden</h1><p>Access Denied.</p>";
            exit;
        }
    }

    /**
     * AI Settings page
     */
    public function index()
    {
        $this->requireAdmin();

        $tenantId = TenantContext::getId();

        // Get all current settings
        $settings = AiSettings::getAllForTenant($tenantId);

        // Get available providers - wrapped in try/catch to prevent 500 errors
        try {
            $providers = AIServiceFactory::getAvailableProviders();
        } catch (\Exception $e) {
            $providers = []; // Fallback to empty array if providers fail to load
        }

        // CRITICAL: Get masked API keys for display
        // If getMasked returns null, fall back to checking $settings directly
        $maskedKeys = [
            'gemini' => AiSettings::getMasked($tenantId, 'gemini_api_key'),
            'openai' => AiSettings::getMasked($tenantId, 'openai_api_key'),
            'anthropic' => AiSettings::getMasked($tenantId, 'anthropic_api_key'),
        ];

        // FALLBACK: If getMasked is returning null but we have keys in settings, use a simple mask
        if (empty($maskedKeys['gemini']) && !empty($settings['gemini_api_key'])) {
            $maskedKeys['gemini'] = substr($settings['gemini_api_key'], 0, 10) . str_repeat('*', 20);
        }
        if (empty($maskedKeys['openai']) && !empty($settings['openai_api_key'])) {
            $maskedKeys['openai'] = substr($settings['openai_api_key'], 0, 10) . str_repeat('*', 20);
        }
        if (empty($maskedKeys['anthropic']) && !empty($settings['anthropic_api_key'])) {
            $maskedKeys['anthropic'] = substr($settings['anthropic_api_key'], 0, 10) . str_repeat('*', 20);
        }

        // Get usage stats
        $usageStats = AiUsage::getStats('month');
        $usageByProvider = AiUsage::getByProvider('month');
        $usageByFeature = AiUsage::getByFeature('month');
        $dailyTrend = AiUsage::getDailyTrend(30);
        $currentMonthCost = AiUsage::getCurrentMonthCost();

        // Get user limit stats
        $userLimitStats = AiUserLimit::getUsageStats();
        $topUsers = AiUserLimit::getTopUsers(10);

        \Nexus\Core\View::render('admin/ai-settings', [
            'settings' => $settings,
            'providers' => $providers,
            'maskedKeys' => $maskedKeys, // PASS MASKED KEYS EXPLICITLY
            'usageStats' => $usageStats,
            'usageByProvider' => $usageByProvider,
            'usageByFeature' => $usageByFeature,
            'dailyTrend' => $dailyTrend,
            'currentMonthCost' => $currentMonthCost,
            'userLimitStats' => $userLimitStats,
            'topUsers' => $topUsers,
            'currentProvider' => $settings['ai_provider'] ?? 'gemini'
        ]);
    }

    /**
     * Save AI settings
     * CRITICAL FIX: All API keys are now trimmed to prevent whitespace issues
     */
    public function save()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $tenantId = TenantContext::getId();

        try {
            // Collect settings from POST
            $settingsToSave = [];

            // General settings
            $settingsToSave['ai_enabled'] = isset($_POST['ai_enabled']) ? '1' : '0';
            $settingsToSave['ai_provider'] = trim($_POST['ai_provider'] ?? 'gemini');

            // Feature toggles
            $settingsToSave['ai_chat_enabled'] = isset($_POST['ai_chat_enabled']) ? '1' : '0';
            $settingsToSave['ai_content_gen_enabled'] = isset($_POST['ai_content_gen_enabled']) ? '1' : '0';
            $settingsToSave['ai_recommendations_enabled'] = isset($_POST['ai_recommendations_enabled']) ? '1' : '0';
            $settingsToSave['ai_analytics_enabled'] = isset($_POST['ai_analytics_enabled']) ? '1' : '0';

            // API Keys (CRITICAL: trim() prevents copy-paste whitespace that causes 401 errors)
            // Only save if provided and not placeholder
            // SECURITY: Never log API keys or their lengths
            if (!empty($_POST['gemini_api_key']) && strpos($_POST['gemini_api_key'], '*') === false) {
                $settingsToSave['gemini_api_key'] = trim($_POST['gemini_api_key']);
            }
            if (!empty($_POST['openai_api_key']) && strpos($_POST['openai_api_key'], '*') === false) {
                $settingsToSave['openai_api_key'] = trim($_POST['openai_api_key']);
            }
            if (!empty($_POST['anthropic_api_key']) && strpos($_POST['anthropic_api_key'], '*') === false) {
                $settingsToSave['anthropic_api_key'] = trim($_POST['anthropic_api_key']);
            }

            // Model selections (also trimmed for consistency)
            if (!empty($_POST['gemini_model'])) $settingsToSave['gemini_model'] = trim($_POST['gemini_model']);
            if (!empty($_POST['openai_model'])) $settingsToSave['openai_model'] = trim($_POST['openai_model']);
            if (!empty($_POST['claude_model'])) $settingsToSave['claude_model'] = trim($_POST['claude_model']);

            // Ollama settings (trimmed)
            if (!empty($_POST['ollama_host'])) $settingsToSave['ollama_host'] = trim($_POST['ollama_host']);
            if (!empty($_POST['ollama_model'])) $settingsToSave['ollama_model'] = trim($_POST['ollama_model']);

            // User limits
            if (isset($_POST['default_daily_limit'])) {
                $settingsToSave['default_daily_limit'] = (string) max(1, (int) $_POST['default_daily_limit']);
            }
            if (isset($_POST['default_monthly_limit'])) {
                $settingsToSave['default_monthly_limit'] = (string) max(1, (int) $_POST['default_monthly_limit']);
            }

            // Custom system prompt (trimmed)
            if (isset($_POST['ai_system_prompt'])) {
                $settingsToSave['ai_system_prompt'] = trim($_POST['ai_system_prompt']);
            }

            // Welcome message (trimmed)
            if (isset($_POST['ai_welcome_message'])) {
                $settingsToSave['ai_welcome_message'] = trim($_POST['ai_welcome_message']);
            }

            // Save all settings
            AiSettings::setMultiple($tenantId, $settingsToSave);

            // Clear factory cache so new keys are immediately available
            AIServiceFactory::clearCache();

            $_SESSION['flash_success'] = 'AI settings saved successfully.';
        } catch (\Throwable $e) {
            error_log("AI Settings Save Error: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Failed to save settings. Please inspect error logs.';
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/ai-settings');
        exit;
    }

    /**
     * Test a provider connection
     * CRITICAL FIX: Enhanced debugging and defensive key re-injection
     */
    public function testProvider()
    {
        $this->requireAdmin();
        Csrf::verifyOrDieJson();

        header('Content-Type: application/json');

        $providerId = trim($_POST['provider'] ?? 'gemini');
        $tenantId = TenantContext::getId();

        try {
            // Clear cache to get fresh provider instance with latest settings
            AIServiceFactory::clearCache();

            // Get provider instance
            $provider = AIServiceFactory::getProvider($providerId);

            // CRITICAL DEBUGGING: Log provider configuration status
            $isConfigured = $provider->isConfigured();
            error_log("Testing Provider [$providerId]. Configured: " . ($isConfigured ? 'YES' : 'NO'));

            // DEFENSIVE PROGRAMMING: If not configured, verify the key exists in database
            if (!$isConfigured) {
                error_log("Provider [$providerId] reports NOT configured. Checking database...");

                // Try to get the key from database
                $dbSettings = AiSettings::getAllForTenant($tenantId);
                $keyName = $providerId . '_api_key';

                if (!empty($dbSettings[$keyName])) {
                    $keyLength = strlen($dbSettings[$keyName]);
                    $keyPreview = substr($dbSettings[$keyName], 0, 10) . '...' . substr($dbSettings[$keyName], -4);
                    error_log("Database has key for [$providerId]: Length=$keyLength, Preview=$keyPreview");
                    error_log("WARNING: Provider not configured but database has key. This suggests AIServiceFactory may not be loading it correctly.");
                } else {
                    error_log("Database does NOT have key for [$providerId]. Key name checked: $keyName");
                }

                echo json_encode([
                    'success' => false,
                    'message' => "Provider '$providerId' is not configured. Please add the API key and save settings first."
                ]);
                exit;
            }

            // Provider is configured - proceed with test
            error_log("Provider [$providerId] is configured. Calling testConnection()...");
            $result = $provider->testConnection();

            // Log result
            if ($result['success'] ?? false) {
                error_log("Provider [$providerId] test SUCCEEDED. Latency: " . ($result['latency_ms'] ?? 'unknown') . "ms");
            } else {
                error_log("Provider [$providerId] test FAILED. Message: " . ($result['message'] ?? 'unknown'));
            }

            echo json_encode($result);
        } catch (\Exception $e) {
            error_log("Provider [$providerId] test EXCEPTION: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    /**
     * Initialize default settings for tenant
     */
    public function initialize()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $tenantId = TenantContext::getId();

        AiSettings::initializeDefaults($tenantId);

        $_SESSION['flash_success'] = 'AI settings initialized with defaults.';
        header('Location: ' . TenantContext::getBasePath() . '/admin/ai-settings');
        exit;
    }
}
