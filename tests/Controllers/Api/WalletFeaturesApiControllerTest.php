<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class WalletFeaturesApiControllerTest extends ApiTestCase
{
    public function testCommunityFundBalance(): void
    {
        $response = $this->get('/api/v2/wallet/community-fund', [], [],
            'Nexus\Controllers\Api\WalletFeaturesApiController@communityFundBalance');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testCommunityFundTransactions(): void
    {
        $response = $this->get('/api/v2/wallet/community-fund/transactions', [], [],
            'Nexus\Controllers\Api\WalletFeaturesApiController@communityFundTransactions');

        $this->assertIsArray($response);
    }

    public function testCommunityFundDonate(): void
    {
        $response = $this->post('/api/v2/wallet/community-fund/donate', [
            'amount'  => 5,
            'message' => 'For the community!',
        ], [], 'Nexus\Controllers\Api\WalletFeaturesApiController@communityFundDonate');

        $this->assertIsArray($response);
    }

    public function testListCategories(): void
    {
        $response = $this->get('/api/v2/wallet/categories', [], [],
            'Nexus\Controllers\Api\WalletFeaturesApiController@listCategories');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testRateExchange(): void
    {
        $response = $this->post('/api/v2/exchanges/1/rate', [
            'rating'  => 5,
            'comment' => 'Excellent exchange!',
        ], [], 'Nexus\Controllers\Api\WalletFeaturesApiController@rateExchange');

        $this->assertIsArray($response);
    }

    public function testExchangeRatings(): void
    {
        $response = $this->get('/api/v2/exchanges/1/ratings', [], [],
            'Nexus\Controllers\Api\WalletFeaturesApiController@exchangeRatings');

        $this->assertIsArray($response);
    }

    public function testUserRating(): void
    {
        $response = $this->get('/api/v2/users/1/rating', [], [],
            'Nexus\Controllers\Api\WalletFeaturesApiController@userRating');

        $this->assertIsArray($response);
    }

    public function testDonate(): void
    {
        $response = $this->post('/api/v2/wallet/donate', [
            'recipient_type' => 'user',
            'recipient_id'   => 2,
            'amount'         => 3,
        ], [], 'Nexus\Controllers\Api\WalletFeaturesApiController@donate');

        $this->assertIsArray($response);
    }

    public function testDonationHistory(): void
    {
        $response = $this->get('/api/v2/wallet/donations', [], [],
            'Nexus\Controllers\Api\WalletFeaturesApiController@donationHistory');

        $this->assertIsArray($response);
    }

    public function testGetStartingBalance(): void
    {
        $response = $this->get('/api/v2/wallet/starting-balance', [], [],
            'Nexus\Controllers\Api\WalletFeaturesApiController@getStartingBalance');

        $this->assertIsArray($response);
    }

    public function testStatement(): void
    {
        $response = $this->get('/api/v2/wallet/statement', [
            'start_date' => '2026-01-01',
            'end_date'   => '2026-03-01',
        ], [], 'Nexus\Controllers\Api\WalletFeaturesApiController@statement');

        $this->assertIsArray($response);
    }
}
