<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\SearchService;
use Nexus\Core\TenantContext;

class SearchController
{
    public function index()
    {
        $query = $_GET['q'] ?? '';
        $searchData = [
            'results' => [],
            'intent' => null,
            'corrected_query' => null,
            'suggestions' => [],
            'total' => 0
        ];

        if (!empty($query)) {
            $service = new SearchService();

            // Get current user ID if logged in
            $userId = null;
            if (isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
            }

            // Execute AI-enhanced search
            $searchData = $service->search($query, 20, $userId);
        }

        View::render('search/results', [
            'query' => $query,
            'results' => $searchData['results'],
            'intent' => $searchData['intent'],
            'corrected_query' => $searchData['corrected_query'],
            'suggestions' => $searchData['suggestions'],
            'total' => $searchData['total']
        ]);
    }
}
