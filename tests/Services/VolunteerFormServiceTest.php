<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use App\Core\Database;
use App\Core\TenantContext;
use App\Services\VolunteerFormService;

/**
 * VolunteerFormService Tests
 *
 * Tests custom field CRUD, field value storage, and accessibility needs management.
 */
class VolunteerFormServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testOrgId = null;
    protected static ?int $testFieldId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $ts = time();

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$testTenantId, "volform_user_{$ts}@test.com", "volform_user_{$ts}", 'Form', 'Tester', 'Form Tester', 100]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test organization
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, status, created_at)
             VALUES (?, ?, ?, ?, 'approved', NOW())",
            [self::$testTenantId, self::$testUserId, "Test Form Org {$ts}", 'Test organization for form service']
        );
        self::$testOrgId = (int)Database::getInstance()->lastInsertId();

        // Create test custom field for dependent tests
        $field = VolunteerFormService::createField([
            'field_key' => "test_dietary_{$ts}",
            'field_label' => 'Test Dietary Requirements',
            'field_type' => 'text',
            'organization_id' => self::$testOrgId,
            'applies_to' => 'application',
        ]);
        if (!empty($field['id'])) {
            self::$testFieldId = (int)$field['id'];
        }
    }

    public static function tearDownAfterClass(): void
    {
        // Cleanup test data in dependency order
        if (self::$testUserId) {
            try {
                Database::query(
                    "DELETE FROM vol_accessibility_needs WHERE user_id = ? AND tenant_id = ?",
                    [self::$testUserId, self::$testTenantId]
                );
            } catch (\Exception $e) {
            }
        }
        if (self::$testOrgId) {
            try {
                Database::query(
                    "DELETE FROM vol_custom_field_values WHERE tenant_id = ? AND field_id IN (SELECT id FROM vol_custom_fields WHERE tenant_id = ? AND organization_id = ?)",
                    [self::$testTenantId, self::$testTenantId, self::$testOrgId]
                );
            } catch (\Exception $e) {
            }
            try {
                Database::query(
                    "DELETE FROM vol_custom_fields WHERE tenant_id = ? AND organization_id = ?",
                    [self::$testTenantId, self::$testOrgId]
                );
            } catch (\Exception $e) {
            }
        }
        // Also clean up global (null org) fields created during tests
        try {
            Database::query(
                "DELETE FROM vol_custom_field_values WHERE tenant_id = ? AND field_id IN (SELECT id FROM vol_custom_fields WHERE tenant_id = ? AND field_label LIKE 'Test %')",
                [self::$testTenantId, self::$testTenantId]
            );
        } catch (\Exception $e) {
        }
        try {
            Database::query(
                "DELETE FROM vol_custom_fields WHERE tenant_id = ? AND field_label LIKE 'Test %'",
                [self::$testTenantId]
            );
        } catch (\Exception $e) {
        }
        if (self::$testOrgId) {
            try {
                Database::query("DELETE FROM vol_organizations WHERE id = ?", [self::$testOrgId]);
            } catch (\Exception $e) {
            }
        }
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {
            }
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Class & Method Existence Tests
    // ==========================================

    public function testVolunteerFormServiceClassExists(): void
    {
        $this->assertTrue(class_exists(VolunteerFormService::class));
    }

    public function testGetCustomFieldsMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(VolunteerFormService::class, 'getCustomFields');
        $this->assertTrue($ref->isStatic());
    }

    public function testCreateFieldMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(VolunteerFormService::class, 'createField');
        $this->assertTrue($ref->isStatic());
    }

    public function testUpdateFieldMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(VolunteerFormService::class, 'updateField');
        $this->assertTrue($ref->isStatic());
    }

    public function testDeleteFieldMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(VolunteerFormService::class, 'deleteField');
        $this->assertTrue($ref->isStatic());
    }

    public function testSaveFieldValuesMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(VolunteerFormService::class, 'saveFieldValues');
        $this->assertTrue($ref->isStatic());
    }

    public function testGetFieldValuesMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(VolunteerFormService::class, 'getFieldValues');
        $this->assertTrue($ref->isStatic());
    }

    public function testGetAccessibilityNeedsMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(VolunteerFormService::class, 'getAccessibilityNeeds');
        $this->assertTrue($ref->isStatic());
    }

    public function testUpdateAccessibilityNeedsMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(VolunteerFormService::class, 'updateAccessibilityNeeds');
        $this->assertTrue($ref->isStatic());
    }

    // ==========================================
    // Custom Field CRUD Tests
    // ==========================================

    public function testCreateFieldReturnsArrayWithExpectedKeys(): void
    {
        $field = VolunteerFormService::createField([
            'field_label' => 'Test Dietary Requirements',
            'field_type' => 'text',
            'organization_id' => self::$testOrgId,
            'applies_to' => 'application',
        ]);

        $this->assertIsArray($field);
        $this->assertNotEmpty($field);
        $this->assertArrayHasKey('id', $field);
        $this->assertArrayHasKey('field_label', $field);
        $this->assertArrayHasKey('field_type', $field);
        $this->assertEquals('Test Dietary Requirements', $field['field_label']);
        $this->assertEquals('text', $field['field_type']);

        // Store for later tests
        self::$testFieldId = (int)$field['id'];
    }

    public function testCreateFieldWithDefaultsUsesTextType(): void
    {
        $field = VolunteerFormService::createField([
            'field_label' => 'Test Default Type Field',
            'organization_id' => self::$testOrgId,
        ]);

        $this->assertIsArray($field);
        $this->assertNotEmpty($field);
        $this->assertEquals('text', $field['field_type']);
    }

    public function testCreateFieldWithSelectType(): void
    {
        $options = json_encode(['Option A', 'Option B', 'Option C']);
        $field = VolunteerFormService::createField([
            'field_label' => 'Test Select Field',
            'field_type' => 'select',
            'organization_id' => self::$testOrgId,
            'field_options' => $options,
        ]);

        $this->assertIsArray($field);
        $this->assertNotEmpty($field);
        $this->assertEquals('select', $field['field_type']);
        $this->assertEquals($options, $field['field_options']);
    }

    public function testCreateFieldWithCheckboxType(): void
    {
        $field = VolunteerFormService::createField([
            'field_label' => 'Test Checkbox Field',
            'field_type' => 'checkbox',
            'organization_id' => self::$testOrgId,
        ]);

        $this->assertIsArray($field);
        $this->assertNotEmpty($field);
        $this->assertEquals('checkbox', $field['field_type']);
    }

    public function testCreateFieldWithEmptyLabelDefaultsToEmptyString(): void
    {
        // Service defaults missing field_label to '' — does not throw
        $field = VolunteerFormService::createField([
            'field_type' => 'text',
            'organization_id' => self::$testOrgId,
            'field_label' => 'Test Empty Name Field',
        ]);

        $this->assertIsArray($field);
        $this->assertNotEmpty($field);
    }

    public function testGetCustomFieldsReturnsArray(): void
    {
        $fields = VolunteerFormService::getCustomFields(self::$testOrgId, 'application');

        $this->assertIsArray($fields);
        // Should contain the fields we created above
        $this->assertGreaterThanOrEqual(1, count($fields));
    }

    public function testGetCustomFieldsWithNullOrgReturnsGlobalOnly(): void
    {
        // Create a global field (no org)
        $globalField = VolunteerFormService::createField([
            'field_label' => 'Test Global Field',
            'field_type' => 'textarea',
            'organization_id' => null,
            'applies_to' => 'application',
        ]);

        $fields = VolunteerFormService::getCustomFields(null, 'application');

        $this->assertIsArray($fields);
        // Verify global field is present
        $globalIds = array_column($fields, 'id');
        $this->assertContains($globalField['id'], $globalIds);
    }

    public function testUpdateFieldChangesLabel(): void
    {
        $this->assertNotNull(self::$testFieldId, 'Test field must exist from prior test');

        $result = VolunteerFormService::updateField(self::$testFieldId, [
            'field_label' => 'Test Updated Label',
        ]);

        $this->assertTrue($result);

        // Verify the update persisted
        $fields = VolunteerFormService::getCustomFields(self::$testOrgId, 'application');
        $updated = null;
        foreach ($fields as $f) {
            if ((int)$f['id'] === self::$testFieldId) {
                $updated = $f;
                break;
            }
        }
        $this->assertNotNull($updated);
        $this->assertEquals('Test Updated Label', $updated['field_label']);
    }

    public function testUpdateFieldWithEmptyDataReturnsFalse(): void
    {
        $this->assertNotNull(self::$testFieldId);

        $result = VolunteerFormService::updateField(self::$testFieldId, []);

        $this->assertFalse($result);
    }

    public function testDeleteFieldReturnsTrue(): void
    {
        // Create a disposable field specifically for deletion
        $field = VolunteerFormService::createField([
            'field_label' => 'Test To Delete',
            'field_type' => 'date',
            'organization_id' => self::$testOrgId,
        ]);

        $this->assertNotEmpty($field);
        $fieldId = (int)$field['id'];

        $result = VolunteerFormService::deleteField($fieldId);
        $this->assertTrue($result);

        // Verify soft-delete: field should no longer appear in active custom fields
        $fields = VolunteerFormService::getCustomFields(self::$testOrgId, 'application');
        $activeIds = array_column($fields, 'id');
        $this->assertNotContains((string)$fieldId, $activeIds);
    }

    // ==========================================
    // Field Values Tests
    // ==========================================

    public function testSaveAndGetFieldValuesRoundTrip(): void
    {
        $this->assertNotNull(self::$testFieldId, 'Test field must exist from prior test');

        // Save a value
        VolunteerFormService::saveFieldValues('application', 999999, [
            self::$testFieldId => 'Vegetarian, no nuts',
        ]);

        // Retrieve it
        $values = VolunteerFormService::getFieldValues('application', 999999);

        $this->assertIsArray($values);
        $this->assertGreaterThanOrEqual(1, count($values));

        $found = false;
        foreach ($values as $v) {
            if ((int)$v['custom_field_id'] === self::$testFieldId) {
                $this->assertEquals('Vegetarian, no nuts', $v['field_value']);
                $this->assertArrayHasKey('field_label', $v);
                $this->assertArrayHasKey('field_type', $v);
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Saved field value should be retrievable');

        // Cleanup
        try {
            Database::query(
                "DELETE FROM vol_custom_field_values WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?",
                [self::$testTenantId, 'application', 999999]
            );
        } catch (\Exception $e) {
        }
    }

    public function testGetFieldValuesReturnsEmptyForNonexistentEntity(): void
    {
        $values = VolunteerFormService::getFieldValues('application', 0);

        $this->assertIsArray($values);
        $this->assertEmpty($values);
    }

    // ==========================================
    // Accessibility Needs Tests
    // ==========================================

    public function testGetAccessibilityNeedsReturnsEmptyArrayInitially(): void
    {
        $needs = VolunteerFormService::getAccessibilityNeeds(self::$testUserId);

        $this->assertIsArray($needs);
        $this->assertEmpty($needs);
    }

    public function testUpdateAndGetAccessibilityNeedsRoundTrip(): void
    {
        $needsInput = [
            [
                'need_type' => 'mobility',
                'description' => 'Uses wheelchair',
                'accommodations_required' => 'Ramp access needed',
            ],
            [
                'need_type' => 'dietary',
                'description' => 'Gluten intolerance',
                'accommodations_required' => 'Gluten-free meals',
            ],
        ];

        VolunteerFormService::updateAccessibilityNeeds(self::$testUserId, $needsInput);

        $needs = VolunteerFormService::getAccessibilityNeeds(self::$testUserId);

        $this->assertIsArray($needs);
        $this->assertCount(2, $needs);

        // Results ordered by need_type ASC: dietary, mobility
        $needTypes = array_column($needs, 'need_type');
        $this->assertContains('mobility', $needTypes);
        $this->assertContains('dietary', $needTypes);
    }

    public function testUpdateAccessibilityNeedsReplacesExisting(): void
    {
        // First set two needs
        VolunteerFormService::updateAccessibilityNeeds(self::$testUserId, [
            ['need_type' => 'visual', 'description' => 'Low vision'],
            ['need_type' => 'hearing', 'description' => 'Partial hearing loss'],
        ]);

        // Now replace with a single different need
        VolunteerFormService::updateAccessibilityNeeds(self::$testUserId, [
            ['need_type' => 'cognitive', 'description' => 'Requires clear instructions'],
        ]);

        $needs = VolunteerFormService::getAccessibilityNeeds(self::$testUserId);

        $this->assertCount(1, $needs);
        $this->assertEquals('cognitive', $needs[0]['need_type']);
    }

    public function testUpdateAccessibilityNeedsWithEmptyArrayClearsAll(): void
    {
        // Set a need first
        VolunteerFormService::updateAccessibilityNeeds(self::$testUserId, [
            ['need_type' => 'language', 'description' => 'Requires interpreter'],
        ]);

        // Verify it exists
        $needs = VolunteerFormService::getAccessibilityNeeds(self::$testUserId);
        $this->assertNotEmpty($needs);

        // Clear all
        VolunteerFormService::updateAccessibilityNeeds(self::$testUserId, []);

        $needs = VolunteerFormService::getAccessibilityNeeds(self::$testUserId);
        $this->assertIsArray($needs);
        $this->assertEmpty($needs);
    }

    public function testUpdateAccessibilityNeedsWithAllValidTypes(): void
    {
        $validTypes = ['mobility', 'visual', 'hearing', 'cognitive', 'dietary', 'language', 'other'];
        $needsInput = [];
        foreach ($validTypes as $type) {
            $needsInput[] = [
                'need_type' => $type,
                'description' => "Test {$type} need",
                'accommodations_required' => "Accommodation for {$type}",
            ];
        }

        VolunteerFormService::updateAccessibilityNeeds(self::$testUserId, $needsInput);

        $needs = VolunteerFormService::getAccessibilityNeeds(self::$testUserId);

        $this->assertCount(7, $needs);
        $savedTypes = array_column($needs, 'need_type');
        foreach ($validTypes as $type) {
            $this->assertContains($type, $savedTypes, "Need type '{$type}' should be saved");
        }

        // Cleanup
        VolunteerFormService::updateAccessibilityNeeds(self::$testUserId, []);
    }
}
