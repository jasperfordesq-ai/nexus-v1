<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\CourseCertificateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * CourseCertificateServiceTest
 *
 * Tests certificate issuance idempotency, unique serial generation,
 * findForUser scoping, and generateHtml structure.
 *
 * All fixtures use a private high-range tenant (99401) to avoid collision.
 */
class CourseCertificateServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99401;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tenants')->insertOrIgnore([
            'id'                => self::TENANT_ID,
            'name'              => 'Cert Test Tenant',
            'slug'              => 'test-99401',
            'is_active'         => true,
            'depth'             => 0,
            'allows_subtenants' => false,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function insertUser(string $firstName = 'Alice', string $lastName = 'Test'): int
    {
        $uid = uniqid('cert', true);
        return DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => $firstName . ' ' . $lastName,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $uid . '@cert.test',
            'status'     => 'active',
            'balance'    => 0,
            'role'       => 'member',
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertCourse(int $authorId, string $title = 'My Course'): int
    {
        return DB::table('courses')->insertGetId([
            'tenant_id'        => self::TENANT_ID,
            'author_user_id'   => $authorId,
            'title'            => $title,
            'slug'             => 'cert-course-' . uniqid(),
            'status'           => 'published',
            'moderation_status'=> 'approved',
            'level'            => 'beginner',
            'visibility'       => 'members',
            'enrollment_type'  => 'self_paced',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    // ── issue() — happy path ──────────────────────────────────────────────────

    public function test_issue_creates_certificate_with_required_fields(): void
    {
        $userId   = $this->insertUser();
        $courseId = $this->insertCourse($this->insertUser());

        $cert = CourseCertificateService::issue($courseId, $userId);

        $this->assertNotNull($cert);
        $this->assertEquals($courseId, $cert->course_id);
        $this->assertEquals($userId,   $cert->user_id);
        $this->assertNotEmpty($cert->serial);
        $this->assertNotNull($cert->issued_at);
    }

    public function test_issue_serial_has_crs_prefix(): void
    {
        $userId   = $this->insertUser();
        $courseId = $this->insertCourse($this->insertUser());

        $cert = CourseCertificateService::issue($courseId, $userId);

        $this->assertStringStartsWith('CRS-', $cert->serial);
    }

    public function test_issue_serial_is_16_chars_after_prefix(): void
    {
        $userId   = $this->insertUser();
        $courseId = $this->insertCourse($this->insertUser());

        $cert = CourseCertificateService::issue($courseId, $userId);

        // 'CRS-' + 12 random uppercase chars = 16 total
        $this->assertSame(16, strlen($cert->serial));
    }

    public function test_issue_persists_row_to_database(): void
    {
        $userId   = $this->insertUser();
        $courseId = $this->insertCourse($this->insertUser());

        $cert = CourseCertificateService::issue($courseId, $userId);

        $this->assertDatabaseHas('course_certificates', [
            'id'        => $cert->id,
            'tenant_id' => self::TENANT_ID,
            'course_id' => $courseId,
            'user_id'   => $userId,
        ]);
    }

    // ── issue() — idempotency ─────────────────────────────────────────────────

    public function test_issue_is_idempotent_returns_same_record_on_second_call(): void
    {
        $userId   = $this->insertUser();
        $courseId = $this->insertCourse($this->insertUser());

        $first  = CourseCertificateService::issue($courseId, $userId);
        $second = CourseCertificateService::issue($courseId, $userId);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->serial, $second->serial);
    }

    public function test_issue_does_not_create_duplicate_rows(): void
    {
        $userId   = $this->insertUser();
        $courseId = $this->insertCourse($this->insertUser());

        CourseCertificateService::issue($courseId, $userId);
        CourseCertificateService::issue($courseId, $userId);

        $count = DB::table('course_certificates')
            ->where('tenant_id', self::TENANT_ID)
            ->where('course_id', $courseId)
            ->where('user_id', $userId)
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_issue_issues_separate_certificates_for_different_courses(): void
    {
        $authorId  = $this->insertUser();
        $userId    = $this->insertUser();
        $courseId1 = $this->insertCourse($authorId, 'Course A');
        $courseId2 = $this->insertCourse($authorId, 'Course B');

        $cert1 = CourseCertificateService::issue($courseId1, $userId);
        $cert2 = CourseCertificateService::issue($courseId2, $userId);

        $this->assertNotSame($cert1->id,     $cert2->id);
        $this->assertNotSame($cert1->serial, $cert2->serial);
    }

    // ── findForUser() ─────────────────────────────────────────────────────────

    public function test_findForUser_returns_null_when_not_issued(): void
    {
        $result = CourseCertificateService::findForUser(999999, 999999);

        $this->assertNull($result);
    }

    public function test_findForUser_returns_cert_after_issue(): void
    {
        $userId   = $this->insertUser();
        $courseId = $this->insertCourse($this->insertUser());

        CourseCertificateService::issue($courseId, $userId);
        $found = CourseCertificateService::findForUser($courseId, $userId);

        $this->assertNotNull($found);
        $this->assertEquals($courseId, $found->course_id);
        $this->assertEquals($userId,   $found->user_id);
    }

    public function test_findForUser_is_scoped_to_current_tenant(): void
    {
        // Insert a certificate manually for a different tenant and confirm
        // findForUser (which goes through the model's HasTenantScope) does not
        // return it when a different tenant is active.
        $otherTenantId = 99402;
        DB::table('tenants')->insertOrIgnore([
            'id'                => $otherTenantId,
            'name'              => 'Other Cert Tenant',
            'slug'              => 'test-99402',
            'is_active'         => true,
            'depth'             => 0,
            'allows_subtenants' => false,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Insert a raw certificate row for the other tenant.
        $courseId = 999998;
        $userId   = 999997;
        DB::table('course_certificates')->insertOrIgnore([
            'tenant_id'  => $otherTenantId,
            'course_id'  => $courseId,
            'user_id'    => $userId,
            'serial'     => 'CRS-OTHERTENANT123',
            'issued_at'  => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Current tenant is 99401 — must NOT see the other tenant's cert.
        $found = CourseCertificateService::findForUser($courseId, $userId);

        $this->assertNull($found);
    }

    // ── generateHtml() ────────────────────────────────────────────────────────

    public function test_generateHtml_returns_valid_html_string(): void
    {
        $userId   = $this->insertUser('Bob', 'Builder');
        $courseId = $this->insertCourse($this->insertUser(), 'Introduction to Timebanking');

        $cert = CourseCertificateService::issue($courseId, $userId);
        $html = CourseCertificateService::generateHtml($cert);

        $this->assertIsString($html);
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('</html>', $html);
    }

    public function test_generateHtml_contains_certificate_serial(): void
    {
        $userId   = $this->insertUser();
        $courseId = $this->insertCourse($this->insertUser(), 'Time Credits 101');

        $cert = CourseCertificateService::issue($courseId, $userId);
        $html = CourseCertificateService::generateHtml($cert);

        $this->assertStringContainsString($cert->serial, $html);
    }
}
