<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\JobVacancy;
use App\Models\Notification;
use App\Services\JobOfferService;
use App\Services\WebhookDispatchService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class JobOfferServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─── Helper: build a mock application with a vacancy ───────────────

    private function makeMockApplication(array $overrides = []): object
    {
        $tenantId = TenantContext::getId();

        $vacancy = Mockery::mock(JobVacancy::class)->makePartial();
        $vacancy->tenant_id = $overrides['vacancy_tenant_id'] ?? $tenantId;
        $vacancy->user_id = $overrides['vacancy_user_id'] ?? 100;
        $vacancy->id = $overrides['vacancy_id'] ?? 10;
        $vacancy->title = $overrides['vacancy_title'] ?? 'Software Engineer';
        $vacancy->shouldReceive('update')->andReturn(true);
        $vacancy->shouldReceive('getAttribute')->with('tenant_id')->andReturn($vacancy->tenant_id);
        $vacancy->shouldReceive('getAttribute')->with('user_id')->andReturn($vacancy->user_id);
        $vacancy->shouldReceive('getAttribute')->with('id')->andReturn($vacancy->id);
        $vacancy->shouldReceive('getAttribute')->with('title')->andReturn($vacancy->title);

        $application = Mockery::mock(JobApplication::class)->makePartial();
        $application->id = $overrides['application_id'] ?? 1;
        $application->vacancy_id = $overrides['vacancy_id'] ?? 10;
        $application->user_id = $overrides['applicant_user_id'] ?? 200;
        $application->vacancy = $vacancy;
        $application->shouldReceive('update')->andReturn(true);
        $application->shouldReceive('getAttribute')->with('vacancy')->andReturn($vacancy);
        $application->shouldReceive('getAttribute')->with('vacancy_id')->andReturn($application->vacancy_id);
        $application->shouldReceive('getAttribute')->with('user_id')->andReturn($application->user_id);
        $application->shouldReceive('getAttribute')->with('id')->andReturn($application->id);

        return $application;
    }

    // ─── Helper: build a mock offer ────────────────────────────────────

    private function makeMockOffer(array $overrides = []): object
    {
        $tenantId = TenantContext::getId();
        $application = $this->makeMockApplication($overrides);

        $offer = Mockery::mock(JobOffer::class)->makePartial();
        $offer->id = $overrides['offer_id'] ?? 50;
        $offer->tenant_id = $overrides['offer_tenant_id'] ?? $tenantId;
        $offer->vacancy_id = $overrides['vacancy_id'] ?? 10;
        $offer->application_id = $overrides['application_id'] ?? 1;
        $offer->status = $overrides['offer_status'] ?? 'pending';
        $offer->application = $application;
        $offer->shouldReceive('update')->andReturn(true);
        $offer->shouldReceive('toArray')->andReturn([
            'id' => $offer->id,
            'tenant_id' => $offer->tenant_id,
            'vacancy_id' => $offer->vacancy_id,
            'application_id' => $offer->application_id,
            'status' => $offer->status,
        ]);
        $offer->shouldReceive('getAttribute')->with('tenant_id')->andReturn($offer->tenant_id);
        $offer->shouldReceive('getAttribute')->with('status')->andReturn($offer->status);
        $offer->shouldReceive('getAttribute')->with('application')->andReturn($application);
        $offer->shouldReceive('getAttribute')->with('application_id')->andReturn($offer->application_id);
        $offer->shouldReceive('getAttribute')->with('vacancy_id')->andReturn($offer->vacancy_id);
        $offer->shouldReceive('getAttribute')->with('id')->andReturn($offer->id);

        return $offer;
    }

    // ====================================================================
    // create()
    // ====================================================================

    public function test_create_returns_offer_array_on_success(): void
    {
        $application = $this->makeMockApplication();
        $tenantId = TenantContext::getId();

        // Mock JobApplication::with(['vacancy'])->find($applicationId)
        $appQuery = Mockery::mock();
        $appQuery->shouldReceive('find')->with(1)->andReturn($application);
        $appMock = Mockery::mock('alias:' . JobApplication::class);
        $appMock->shouldReceive('with')->with(['vacancy'])->andReturn($appQuery);

        // Mock JobOffer::where(...)->exists()
        $existsQuery = Mockery::mock();
        $existsQuery->shouldReceive('exists')->andReturn(false);
        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('where')->with('application_id', 1)->andReturn($existsQuery);

        // Mock JobOffer::create()
        $createdOffer = Mockery::mock();
        $createdOffer->shouldReceive('toArray')->andReturn([
            'id' => 50,
            'tenant_id' => $tenantId,
            'vacancy_id' => 10,
            'application_id' => 1,
            'salary_offered' => 50000.0,
            'salary_currency' => 'EUR',
            'status' => 'pending',
        ]);
        $offerMock->shouldReceive('create')->once()->andReturn($createdOffer);

        // Mock Notification (allow failure silently)
        $notifMock = Mockery::mock('alias:' . Notification::class);
        $notifMock->shouldReceive('createNotification')->andReturn(1);

        $result = JobOfferService::create(1, 100, [
            'salary_offered' => 50000,
            'salary_currency' => 'EUR',
            'message' => 'Welcome aboard!',
        ]);

        $this->assertIsArray($result);
        $this->assertSame(50, $result['id']);
        $this->assertSame('pending', $result['status']);
    }

    public function test_create_returns_false_when_application_not_found(): void
    {
        $appQuery = Mockery::mock();
        $appQuery->shouldReceive('find')->with(999)->andReturn(null);
        $appMock = Mockery::mock('alias:' . JobApplication::class);
        $appMock->shouldReceive('with')->with(['vacancy'])->andReturn($appQuery);

        $result = JobOfferService::create(999, 100, []);

        $this->assertFalse($result);
    }

    public function test_create_returns_false_when_vacancy_belongs_to_different_tenant(): void
    {
        $application = $this->makeMockApplication(['vacancy_tenant_id' => 999]);

        $appQuery = Mockery::mock();
        $appQuery->shouldReceive('find')->with(1)->andReturn($application);
        $appMock = Mockery::mock('alias:' . JobApplication::class);
        $appMock->shouldReceive('with')->with(['vacancy'])->andReturn($appQuery);

        $result = JobOfferService::create(1, 100, []);

        $this->assertFalse($result);
    }

    public function test_create_returns_false_when_non_owner_creates_offer(): void
    {
        $application = $this->makeMockApplication(['vacancy_user_id' => 100]);

        $appQuery = Mockery::mock();
        $appQuery->shouldReceive('find')->with(1)->andReturn($application);
        $appMock = Mockery::mock('alias:' . JobApplication::class);
        $appMock->shouldReceive('with')->with(['vacancy'])->andReturn($appQuery);

        // User 999 is NOT the vacancy owner (100)
        $result = JobOfferService::create(1, 999, []);

        $this->assertFalse($result);
    }

    public function test_create_returns_false_when_offer_already_exists(): void
    {
        $application = $this->makeMockApplication();

        $appQuery = Mockery::mock();
        $appQuery->shouldReceive('find')->with(1)->andReturn($application);
        $appMock = Mockery::mock('alias:' . JobApplication::class);
        $appMock->shouldReceive('with')->with(['vacancy'])->andReturn($appQuery);

        $existsQuery = Mockery::mock();
        $existsQuery->shouldReceive('exists')->andReturn(true);
        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('where')->with('application_id', 1)->andReturn($existsQuery);

        $result = JobOfferService::create(1, 100, ['salary_offered' => 50000]);

        $this->assertFalse($result);
    }

    // ====================================================================
    // accept()
    // ====================================================================

    public function test_accept_returns_true_on_success(): void
    {
        $offer = $this->makeMockOffer(['applicant_user_id' => 200]);

        $offerQuery = Mockery::mock();
        $offerQuery->shouldReceive('find')->with(50)->andReturn($offer);
        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($offerQuery);

        $notifMock = Mockery::mock('alias:' . Notification::class);
        $notifMock->shouldReceive('createNotification')->andReturn(1);

        $webhookMock = Mockery::mock('alias:' . WebhookDispatchService::class);
        $webhookMock->shouldReceive('dispatch')->once();

        $result = JobOfferService::accept(50, 200);

        $this->assertTrue($result);
    }

    public function test_accept_returns_false_when_offer_not_found(): void
    {
        $offerQuery = Mockery::mock();
        $offerQuery->shouldReceive('find')->with(999)->andReturn(null);
        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($offerQuery);

        $result = JobOfferService::accept(999, 200);

        $this->assertFalse($result);
    }

    public function test_accept_returns_false_when_wrong_tenant(): void
    {
        $offer = $this->makeMockOffer(['offer_tenant_id' => 999]);

        $offerQuery = Mockery::mock();
        $offerQuery->shouldReceive('find')->with(50)->andReturn($offer);
        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($offerQuery);

        $result = JobOfferService::accept(50, 200);

        $this->assertFalse($result);
    }

    public function test_accept_returns_false_when_wrong_user(): void
    {
        $offer = $this->makeMockOffer(['applicant_user_id' => 200]);

        $offerQuery = Mockery::mock();
        $offerQuery->shouldReceive('find')->with(50)->andReturn($offer);
        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($offerQuery);

        // User 300 is NOT the applicant (200)
        $result = JobOfferService::accept(50, 300);

        $this->assertFalse($result);
    }

    public function test_accept_returns_false_when_status_not_pending(): void
    {
        $offer = $this->makeMockOffer([
            'applicant_user_id' => 200,
            'offer_status' => 'accepted',
        ]);

        $offerQuery = Mockery::mock();
        $offerQuery->shouldReceive('find')->with(50)->andReturn($offer);
        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($offerQuery);

        $result = JobOfferService::accept(50, 200);

        $this->assertFalse($result);
    }

    // ====================================================================
    // reject()
    // ====================================================================

    public function test_reject_returns_true_on_success(): void
    {
        $offer = $this->makeMockOffer(['applicant_user_id' => 200]);

        $offerQuery = Mockery::mock();
        $offerQuery->shouldReceive('find')->with(50)->andReturn($offer);
        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($offerQuery);

        $notifMock = Mockery::mock('alias:' . Notification::class);
        $notifMock->shouldReceive('createNotification')->andReturn(1);

        $result = JobOfferService::reject(50, 200);

        $this->assertTrue($result);
    }

    public function test_reject_returns_false_when_offer_not_found(): void
    {
        $offerQuery = Mockery::mock();
        $offerQuery->shouldReceive('find')->with(999)->andReturn(null);
        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($offerQuery);

        $result = JobOfferService::reject(999, 200);

        $this->assertFalse($result);
    }

    public function test_reject_returns_false_when_wrong_user(): void
    {
        $offer = $this->makeMockOffer(['applicant_user_id' => 200]);

        $offerQuery = Mockery::mock();
        $offerQuery->shouldReceive('find')->with(50)->andReturn($offer);
        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($offerQuery);

        $result = JobOfferService::reject(50, 300);

        $this->assertFalse($result);
    }

    public function test_reject_returns_false_when_status_not_pending(): void
    {
        $offer = $this->makeMockOffer([
            'applicant_user_id' => 200,
            'offer_status' => 'rejected',
        ]);

        $offerQuery = Mockery::mock();
        $offerQuery->shouldReceive('find')->with(50)->andReturn($offer);
        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($offerQuery);

        $result = JobOfferService::reject(50, 200);

        $this->assertFalse($result);
    }

    // ====================================================================
    // withdraw()
    // ====================================================================

    public function test_withdraw_returns_true_on_success(): void
    {
        $offer = $this->makeMockOffer(['vacancy_user_id' => 100]);

        $offerQuery = Mockery::mock();
        $offerQuery->shouldReceive('find')->with(50)->andReturn($offer);
        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($offerQuery);

        $notifMock = Mockery::mock('alias:' . Notification::class);
        $notifMock->shouldReceive('createNotification')->andReturn(1);

        $result = JobOfferService::withdraw(50, 100);

        $this->assertTrue($result);
    }

    public function test_withdraw_returns_false_when_offer_not_found(): void
    {
        $offerQuery = Mockery::mock();
        $offerQuery->shouldReceive('find')->with(999)->andReturn(null);
        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($offerQuery);

        $result = JobOfferService::withdraw(999, 100);

        $this->assertFalse($result);
    }

    public function test_withdraw_returns_false_when_wrong_user(): void
    {
        $offer = $this->makeMockOffer(['vacancy_user_id' => 100]);

        $offerQuery = Mockery::mock();
        $offerQuery->shouldReceive('find')->with(50)->andReturn($offer);
        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($offerQuery);

        // User 999 is NOT the vacancy owner (100)
        $result = JobOfferService::withdraw(50, 999);

        $this->assertFalse($result);
    }

    public function test_withdraw_returns_false_when_status_not_pending(): void
    {
        $offer = $this->makeMockOffer([
            'vacancy_user_id' => 100,
            'offer_status' => 'accepted',
        ]);

        $offerQuery = Mockery::mock();
        $offerQuery->shouldReceive('find')->with(50)->andReturn($offer);
        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($offerQuery);

        $result = JobOfferService::withdraw(50, 100);

        $this->assertFalse($result);
    }

    // ====================================================================
    // getForApplication()
    // ====================================================================

    public function test_getForApplication_returns_offer_for_applicant(): void
    {
        $tenantId = TenantContext::getId();
        $offer = $this->makeMockOffer(['applicant_user_id' => 200]);

        $query = Mockery::mock();
        $query->shouldReceive('where')->with('tenant_id', $tenantId)->andReturnSelf();
        $query->shouldReceive('where')->with('application_id', 1)->andReturnSelf();
        $query->shouldReceive('first')->andReturn($offer);

        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($query);

        $result = JobOfferService::getForApplication(1, 200);

        $this->assertIsArray($result);
        $this->assertSame(50, $result['id']);
    }

    public function test_getForApplication_returns_offer_for_vacancy_owner(): void
    {
        $tenantId = TenantContext::getId();
        $offer = $this->makeMockOffer([
            'applicant_user_id' => 200,
            'vacancy_user_id' => 100,
        ]);

        $query = Mockery::mock();
        $query->shouldReceive('where')->with('tenant_id', $tenantId)->andReturnSelf();
        $query->shouldReceive('where')->with('application_id', 1)->andReturnSelf();
        $query->shouldReceive('first')->andReturn($offer);

        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($query);

        $result = JobOfferService::getForApplication(1, 100);

        $this->assertIsArray($result);
    }

    public function test_getForApplication_returns_null_when_not_found(): void
    {
        $tenantId = TenantContext::getId();

        $query = Mockery::mock();
        $query->shouldReceive('where')->with('tenant_id', $tenantId)->andReturnSelf();
        $query->shouldReceive('where')->with('application_id', 999)->andReturnSelf();
        $query->shouldReceive('first')->andReturn(null);

        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($query);

        $result = JobOfferService::getForApplication(999, 200);

        $this->assertNull($result);
    }

    public function test_getForApplication_returns_null_for_unauthorized_user(): void
    {
        $tenantId = TenantContext::getId();
        $offer = $this->makeMockOffer([
            'applicant_user_id' => 200,
            'vacancy_user_id' => 100,
        ]);

        $query = Mockery::mock();
        $query->shouldReceive('where')->with('tenant_id', $tenantId)->andReturnSelf();
        $query->shouldReceive('where')->with('application_id', 1)->andReturnSelf();
        $query->shouldReceive('first')->andReturn($offer);

        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($query);

        // User 555 is neither applicant (200) nor vacancy owner (100)
        $result = JobOfferService::getForApplication(1, 555);

        $this->assertNull($result);
    }

    // ====================================================================
    // getForUser()
    // ====================================================================

    public function test_getForUser_returns_offers_array(): void
    {
        $tenantId = TenantContext::getId();

        $query = Mockery::mock();
        $query->shouldReceive('where')->with('tenant_id', $tenantId)->andReturnSelf();
        $query->shouldReceive('whereHas')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->with('created_at')->andReturnSelf();
        $collection = Mockery::mock();
        $collection->shouldReceive('toArray')->andReturn([
            ['id' => 50, 'status' => 'pending'],
            ['id' => 51, 'status' => 'accepted'],
        ]);
        $query->shouldReceive('get')->andReturn($collection);

        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('with')
            ->with(['vacancy:id,title,user_id', 'application:id,user_id,vacancy_id,status'])
            ->andReturn($query);

        $result = JobOfferService::getForUser(200);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function test_getForUser_returns_empty_array_when_no_offers(): void
    {
        $tenantId = TenantContext::getId();

        $query = Mockery::mock();
        $query->shouldReceive('where')->with('tenant_id', $tenantId)->andReturnSelf();
        $query->shouldReceive('whereHas')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->with('created_at')->andReturnSelf();
        $collection = Mockery::mock();
        $collection->shouldReceive('toArray')->andReturn([]);
        $query->shouldReceive('get')->andReturn($collection);

        $offerMock = Mockery::mock('alias:' . JobOffer::class);
        $offerMock->shouldReceive('with')
            ->with(['vacancy:id,title,user_id', 'application:id,user_id,vacancy_id,status'])
            ->andReturn($query);

        $result = JobOfferService::getForUser(200);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
