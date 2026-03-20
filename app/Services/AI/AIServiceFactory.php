<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\AI;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\OllamaProvider;
use App\Services\AI\Providers\OpenAIProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AIServiceFactory — Laravel DI-based factory for AI providers.
 *
 * Reads provider config from config files + database (ai_settings table).
 * Database settings ALWAYS override config/env values.
 */
class AIServiceFactory
{
    /** @var array<string, AIProviderInterface> */
    private array $instances = [];

    private ?array $config = null;

    public function __construct()
    {
    }

    /**
     * Get an AI provider instance by ID (or the default).
     */
    public function getProvider(?string $providerId = null): AIProviderInterface
    {
        $providerId = $providerId ?? $this->getDefaultProvider();

        if (!isset($this->instances[$providerId])) {
            $this->instances[$providerId] = $this->createProvider($providerId);
        }

        return $this->instances[$providerId];
    }

    /**
     * Get a provider with automatic fallback if the preferred one is unavailable.
     *
     * @return array{provider: AIProviderInterface, provider_id: string, is_fallback: bool}
     */
    public function getProviderWithFallback(?string $preferredId = null): array
    {
        $preferredId = $preferredId ?? $this->getDefaultProvider();
        $fallbackOrder = $this->getFallbackOrder($preferredId);

        foreach ($fallbackOrder as $providerId) {
            try {
                $provider = $this->getProvider($providerId);
                if ($provider->isConfigured()) {
                    return [
                        'provider'    => $provider,
                        'provider_id' => $providerId,
                        'is_fallback' => ($providerId !== $preferredId),
                    ];
                }
            } catch (\Exception $e) {
                Log::debug("Provider {$providerId} not available: " . $e->getMessage());
                continue;
            }
        }

        // If all else fails, return the preferred provider — let caller handle error
        return [
            'provider'    => $this->getProvider($preferredId),
            'provider_id' => $preferredId,
            'is_fallback' => false,
        ];
    }

    /**
     * Execute a chat request with automatic provider fallback.
     *
     * @return array{content: string, tokens_used: int, model: string, provider: string, used_fallback: bool, ...}
     */
    public function chatWithFallback(array $messages, array $options = [], ?string $preferredProvider = null): array
    {
        $fallbackOrder = $this->getFallbackOrder($preferredProvider ?? $this->getDefaultProvider());
        $lastError = null;

        foreach ($fallbackOrder as $providerId) {
            try {
                $provider = $this->getProvider($providerId);
                if (!$provider->isConfigured()) {
                    continue;
                }

                $response = $provider->chat($messages, $options);
                $response['provider'] = $providerId;
                $response['used_fallback'] = ($providerId !== ($preferredProvider ?? $this->getDefaultProvider()));

                return $response;
            } catch (\Exception $e) {
                $lastError = $e;
                Log::warning("AI Provider {$providerId} failed: " . $e->getMessage());
                continue;
            }
        }

        if ($lastError) {
            throw $lastError;
        }

        throw new \RuntimeException('No AI providers available');
    }

    /**
     * Get configuration for a specific provider.
     *
     * CRITICAL: Database settings ALWAYS override config file settings.
     */
    public function getProviderConfig(string $providerId): array
    {
        $config = $this->getConfig();
        $providerConfig = $config['providers'][$providerId] ?? [];

        $tenantId = \App\Core\TenantContext::getId();

        if ($tenantId) {
            $dbSettings = $this->getDbSettings($tenantId);

            switch ($providerId) {
                case 'gemini':
                    if (!empty($dbSettings['gemini_api_key'])) {
                        $providerConfig['api_key'] = $dbSettings['gemini_api_key'];
                    }
                    if (!empty($dbSettings['gemini_model'])) {
                        $providerConfig['default_model'] = $dbSettings['gemini_model'];
                    }
                    break;

                case 'openai':
                    if (!empty($dbSettings['openai_api_key'])) {
                        $providerConfig['api_key'] = $dbSettings['openai_api_key'];
                    }
                    if (!empty($dbSettings['openai_model'])) {
                        $providerConfig['default_model'] = $dbSettings['openai_model'];
                    }
                    if (!empty($dbSettings['openai_org_id'])) {
                        $providerConfig['org_id'] = $dbSettings['openai_org_id'];
                    }
                    break;

                case 'anthropic':
                    if (!empty($dbSettings['anthropic_api_key'])) {
                        $providerConfig['api_key'] = $dbSettings['anthropic_api_key'];
                    }
                    if (!empty($dbSettings['claude_model'])) {
                        $providerConfig['default_model'] = $dbSettings['claude_model'];
                    }
                    break;

                case 'ollama':
                    if (!empty($dbSettings['ollama_host'])) {
                        $providerConfig['api_url'] = $dbSettings['ollama_host'];
                    }
                    if (!empty($dbSettings['ollama_model'])) {
                        $providerConfig['default_model'] = $dbSettings['ollama_model'];
                    }
                    $providerConfig['self_hosted'] = true;
                    break;
            }
        }

        // Validation: throw clear error if API key is missing (except Ollama)
        if ($providerId !== 'ollama' && empty($providerConfig['api_key'])) {
            Log::error("AI FACTORY ERROR: Provider [{$providerId}] has no API key configured.");
            throw new \RuntimeException("AI Provider '{$providerId}' is not configured. Missing API key. Please configure it in Admin > AI Settings.");
        }

        return $providerConfig;
    }

    /**
     * Get the default provider ID from DB settings or config.
     */
    public function getDefaultProvider(): string
    {
        $tenantId = \App\Core\TenantContext::getId();

        if ($tenantId) {
            $dbProvider = $this->getDbSetting($tenantId, 'ai_provider');
            if ($dbProvider) {
                return $dbProvider;
            }
        }

        $config = $this->getConfig();
        return $config['default_provider'] ?? 'gemini';
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Create a provider instance from config.
     */
    private function createProvider(string $providerId): AIProviderInterface
    {
        $config = $this->getProviderConfig($providerId);

        return match ($providerId) {
            'gemini'    => new GeminiProvider($config),
            'openai'    => new OpenAIProvider($config),
            'anthropic' => new AnthropicProvider($config),
            'ollama'    => new OllamaProvider($config),
            default     => throw new \RuntimeException("Unknown AI provider: {$providerId}"),
        };
    }

    /**
     * Get fallback order for providers (preferred first, then free-tier, then others).
     *
     * @return string[]
     */
    private function getFallbackOrder(string $preferredId): array
    {
        $config = $this->getConfig();
        $providers = $config['providers'] ?? [];
        $order = [$preferredId];

        foreach ($providers as $id => $providerConfig) {
            if ($id === $preferredId) {
                continue;
            }
            try {
                $provider = $this->getProvider($id);
                if ($provider->isConfigured()) {
                    if (!empty($providerConfig['free_tier'])) {
                        array_splice($order, 1, 0, [$id]);
                    } else {
                        $order[] = $id;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return array_values(array_unique($order));
    }

    /**
     * Load the AI configuration file.
     */
    private function getConfig(): array
    {
        if ($this->config === null) {
            // Try Laravel config first, then fall back to legacy path
            $configPath = base_path('config/ai.php');
            if (!file_exists($configPath)) {
                $configPath = base_path('src/Config/ai.php');
            }

            if (file_exists($configPath)) {
                $this->config = require $configPath;
            } else {
                $this->config = [
                    'enabled'          => false,
                    'default_provider' => 'gemini',
                    'providers'        => [],
                    'features'         => [],
                    'limits'           => [],
                ];
            }
        }

        return $this->config;
    }

    /**
     * Get all AI settings from the database for a tenant.
     *
     * @return array<string, string>
     */
    private function getDbSettings(int $tenantId): array
    {
        try {
            $rows = DB::select(
                "SELECT setting_key, setting_value, is_encrypted FROM ai_settings WHERE tenant_id = ?",
                [$tenantId]
            );

            $settings = [];
            foreach ($rows as $row) {
                $value = $row->setting_value;
                // Decrypt if encrypted (matches legacy AiSettings behaviour)
                if ($row->is_encrypted && $value) {
                    $value = $this->decryptValue($value);
                }
                $settings[$row->setting_key] = $value;
            }

            return $settings;
        } catch (\Exception $e) {
            Log::error("AIServiceFactory::getDbSettings error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a single AI setting from the database.
     */
    private function getDbSetting(int $tenantId, string $key): ?string
    {
        try {
            $row = DB::selectOne(
                "SELECT setting_value, is_encrypted FROM ai_settings WHERE tenant_id = ? AND setting_key = ?",
                [$tenantId, $key]
            );

            if (!$row) {
                return null;
            }

            $value = $row->setting_value;
            if ($row->is_encrypted && $value) {
                $value = $this->decryptValue($value);
            }

            return $value;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Decrypt an encrypted setting value (matches legacy AiSettings::decrypt).
     */
    private function decryptValue(string $encrypted): string
    {
        $key = $this->getEncryptionKey();
        if (!$key) {
            return $encrypted;
        }

        $data = base64_decode($encrypted, true);
        if ($data === false || strlen($data) < 28) {
            return $encrypted; // Not encrypted or corrupted
        }

        $ivLen = openssl_cipher_iv_length('aes-256-gcm');
        $iv = substr($data, 0, $ivLen);
        $tag = substr($data, $ivLen, 16);
        $ciphertext = substr($data, $ivLen + 16);

        $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        return $decrypted !== false ? $decrypted : $encrypted;
    }

    /**
     * Get the encryption key for AI settings.
     */
    private function getEncryptionKey(): ?string
    {
        $key = $_ENV['AI_ENCRYPTION_KEY'] ?? $_SERVER['AI_ENCRYPTION_KEY'] ?? getenv('AI_ENCRYPTION_KEY');
        if ($key) {
            return $key;
        }

        // Fall back to APP_KEY
        $appKey = $_ENV['APP_KEY'] ?? $_SERVER['APP_KEY'] ?? getenv('APP_KEY');
        if ($appKey) {
            return hash('sha256', $appKey, true) ? substr(hash('sha256', $appKey), 0, 32) : null;
        }

        return null;
    }

    /**
     * Clear cached instances (useful for testing or config changes).
     */
    public function clearCache(): void
    {
        $this->instances = [];
        $this->config = null;
    }
}
