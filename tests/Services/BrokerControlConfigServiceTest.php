<?php

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\BrokerControlConfigService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * BrokerControlConfigServiceTest
 *
 * Tests for the broker control configuration service.
 * Covers configuration get/set, feature flags, and tenant-specific settings.
 */
class BrokerControlConfigServiceTest extends TestCase
{
    private static $testTenantId = 1;
    private static $originalConfig;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$testTenantId);

        // Store original config to restore later
        self::$originalConfig = BrokerControlConfigService::getConfig();
    }

    public static function tearDownAfterClass(): void
    {
        // Restore original configuration
        if (self::$originalConfig !== null) {
            BrokerControlConfigService::updateConfig(self::$originalConfig);
        }
    }

    /**
     * Test getting default configuration
     */
    public function testGetConfigReturnsArray(): void
    {
        $config = BrokerControlConfigService::getConfig();

        $this->assertIsArray($config, 'Config should be an array');
    }

    /**
     * Test config has expected structure
     */
    public function testConfigHasExpectedStructure(): void
    {
        $config = BrokerControlConfigService::getConfig();

        // Check main sections exist
        $this->assertArrayHasKey('messaging', $config, 'Should have messaging section');
        $this->assertArrayHasKey('risk_tagging', $config, 'Should have risk_tagging section');
        $this->assertArrayHasKey('exchange_workflow', $config, 'Should have exchange_workflow section');
        $this->assertArrayHasKey('broker_visibility', $config, 'Should have broker_visibility section');
    }

    /**
     * Test messaging section has required keys
     */
    public function testMessagingSectionHasRequiredKeys(): void
    {
        $config = BrokerControlConfigService::getConfig();

        $this->assertArrayHasKey('direct_messaging_enabled', $config['messaging']);
        $this->assertArrayHasKey('first_contact_monitoring', $config['messaging']);
        $this->assertArrayHasKey('new_member_monitoring_days', $config['messaging']);
    }

    /**
     * Test updating configuration
     */
    public function testUpdateConfig(): void
    {
        $newConfig = [
            'messaging' => [
                'direct_messaging_enabled' => false,
                'first_contact_monitoring' => true,
                'new_member_monitoring_days' => 60,
            ],
            'risk_tagging' => [
                'enabled' => true,
                'high_risk_requires_approval' => true,
            ],
            'exchange_workflow' => [
                'enabled' => true,
                'require_broker_approval' => true,
                'confirmation_deadline_hours' => 48,
            ],
            'broker_visibility' => [
                'enabled' => true,
                'copy_first_contact' => true,
            ],
        ];

        $result = BrokerControlConfigService::updateConfig($newConfig);
        $this->assertTrue($result, 'Update should succeed');

        // Verify the update persisted
        $savedConfig = BrokerControlConfigService::getConfig();
        $this->assertFalse($savedConfig['messaging']['direct_messaging_enabled'], 'Messaging should be disabled');
        $this->assertEquals(60, $savedConfig['messaging']['new_member_monitoring_days'], 'Days should be 60');
    }

    /**
     * Test isDirectMessagingEnabled helper
     */
    public function testIsDirectMessagingEnabled(): void
    {
        // Disable messaging
        $config = BrokerControlConfigService::getConfig();
        $config['messaging']['direct_messaging_enabled'] = false;
        BrokerControlConfigService::updateConfig($config);

        $this->assertFalse(
            BrokerControlConfigService::isDirectMessagingEnabled(),
            'Should return false when disabled'
        );

        // Enable messaging
        $config['messaging']['direct_messaging_enabled'] = true;
        BrokerControlConfigService::updateConfig($config);

        $this->assertTrue(
            BrokerControlConfigService::isDirectMessagingEnabled(),
            'Should return true when enabled'
        );
    }

    /**
     * Test isExchangeWorkflowEnabled helper
     */
    public function testIsExchangeWorkflowEnabled(): void
    {
        $config = BrokerControlConfigService::getConfig();

        // Enable exchange workflow
        $config['exchange_workflow']['enabled'] = true;
        BrokerControlConfigService::updateConfig($config);

        $this->assertTrue(
            BrokerControlConfigService::isExchangeWorkflowEnabled(),
            'Should return true when enabled'
        );

        // Disable exchange workflow
        $config['exchange_workflow']['enabled'] = false;
        BrokerControlConfigService::updateConfig($config);

        $this->assertFalse(
            BrokerControlConfigService::isExchangeWorkflowEnabled(),
            'Should return false when disabled'
        );
    }

    /**
     * Test isRiskTaggingEnabled helper
     */
    public function testIsRiskTaggingEnabled(): void
    {
        $config = BrokerControlConfigService::getConfig();

        $config['risk_tagging']['enabled'] = true;
        BrokerControlConfigService::updateConfig($config);

        $this->assertTrue(
            BrokerControlConfigService::isRiskTaggingEnabled(),
            'Should return true when enabled'
        );
    }

    /**
     * Test isBrokerVisibilityEnabled helper
     */
    public function testIsBrokerVisibilityEnabled(): void
    {
        $config = BrokerControlConfigService::getConfig();

        $config['broker_visibility']['enabled'] = true;
        BrokerControlConfigService::updateConfig($config);

        $this->assertTrue(
            BrokerControlConfigService::isBrokerVisibilityEnabled(),
            'Should return true when enabled'
        );
    }

    /**
     * Test requiresBrokerApproval helper
     */
    public function testRequiresBrokerApproval(): void
    {
        $config = BrokerControlConfigService::getConfig();

        $config['exchange_workflow']['enabled'] = true;
        $config['exchange_workflow']['require_broker_approval'] = true;
        BrokerControlConfigService::updateConfig($config);

        $this->assertTrue(
            BrokerControlConfigService::requiresBrokerApproval(),
            'Should return true when exchange workflow is enabled and approval required'
        );
    }

    /**
     * Test getNewMemberMonitoringDays helper
     */
    public function testGetNewMemberMonitoringDays(): void
    {
        $config = BrokerControlConfigService::getConfig();
        $config['messaging']['new_member_monitoring_days'] = 45;
        BrokerControlConfigService::updateConfig($config);

        $this->assertEquals(
            45,
            BrokerControlConfigService::getNewMemberMonitoringDays(),
            'Should return configured days'
        );
    }

    /**
     * Test partial config update (merge behavior)
     */
    public function testPartialConfigUpdate(): void
    {
        // Get current config
        $config = BrokerControlConfigService::getConfig();
        $originalRiskTagging = $config['risk_tagging'];

        // Update only messaging
        $partialConfig = [
            'messaging' => [
                'direct_messaging_enabled' => true,
                'first_contact_monitoring' => false,
                'new_member_monitoring_days' => 14,
            ],
        ];

        BrokerControlConfigService::updateConfig($partialConfig);

        // Verify messaging was updated
        $newConfig = BrokerControlConfigService::getConfig();
        $this->assertEquals(14, $newConfig['messaging']['new_member_monitoring_days']);
    }
}
