<?php

declare(strict_types=1);

namespace Nexus\Controllers\Api\Ai;

use Nexus\Models\AiUserLimit;
use Nexus\Services\AI\AIServiceFactory;

/**
 * AI Provider Controller
 *
 * Handles provider information, user limits, and provider testing.
 */
class AiProviderController extends BaseAiController
{
    /**
     * GET /api/ai/providers
     * Get available AI providers
     */
    public function getProviders(): void
    {
        $this->getUserId(); // Ensure authenticated

        $providers = AIServiceFactory::getAvailableProviders();
        $defaultProvider = AIServiceFactory::getDefaultProvider();

        $this->jsonResponse([
            'success' => true,
            'providers' => $providers,
            'default' => $defaultProvider,
            'enabled' => AIServiceFactory::isEnabled(),
        ]);
    }

    /**
     * GET /api/ai/limits
     * Get user's current usage limits
     */
    public function getLimits(): void
    {
        $userId = $this->getUserId();
        $limits = AiUserLimit::canMakeRequest($userId);

        $this->jsonResponse([
            'success' => true,
            'limits' => $limits,
        ]);
    }

    /**
     * POST /api/ai/test-provider
     * Test an AI provider connection (admin only)
     */
    public function testProvider(): void
    {
        $this->getUserId();

        $input = $this->getInput();
        $providerId = $input['provider'] ?? 'gemini';

        try {
            $provider = AIServiceFactory::getProvider($providerId);
            $result = $provider->testConnection();

            $this->jsonResponse([
                'success' => $result['success'],
                'message' => $result['message'],
                'latency_ms' => $result['latency_ms'],
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
