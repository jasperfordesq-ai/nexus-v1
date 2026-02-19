<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Listings Grid Renderer
 *
 * Renders a grid of listings with REAL database integration
 */

namespace Nexus\PageBuilder\Renderers;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class ListingsGridRenderer implements BlockRendererInterface
{
    public function render(array $data): string
    {
        $limit = (int)($data['limit'] ?? 6);
        $columns = (int)($data['columns'] ?? 3);
        $orderBy = $data['orderBy'] ?? 'created_at';
        $categoryId = (int)($data['categoryId'] ?? 0);
        $showPrice = (bool)($data['showPrice'] ?? true);
        $showLocation = (bool)($data['showLocation'] ?? true);

        // Build query
        $tenantId = TenantContext::getId();
        $sql = "SELECT id, title, description, price, location, image, created_at
                FROM listings
                WHERE tenant_id = ? AND status = 'active'";
        $params = [$tenantId];

        // Apply category filter
        if ($categoryId > 0) {
            $sql .= " AND category_id = ?";
            $params[] = $categoryId;
        }

        // Order
        $validOrders = ['created_at', 'price', 'title'];
        if (!in_array($orderBy, $validOrders)) {
            $orderBy = 'created_at';
        }
        $sql .= " ORDER BY {$orderBy} DESC LIMIT ?";
        $params[] = $limit;

        // Fetch listings
        $listings = Database::query($sql, $params)->fetchAll();

        if (empty($listings)) {
            return '<div class="pb-listings-grid-empty">No listings found.</div>';
        }

        // Render grid
        $html = '<div class="pb-listings-grid columns-' . $columns . '">';

        foreach ($listings as $listing) {
            $html .= $this->renderListingCard($listing, $showPrice, $showLocation);
        }

        $html .= '</div>';

        return $html;
    }

    private function renderListingCard(array $listing, bool $showPrice, bool $showLocation): string
    {
        $basePath = TenantContext::getBasePath();
        $title = htmlspecialchars($listing['title']);
        $description = htmlspecialchars($listing['description'] ?? '');
        $image = htmlspecialchars($listing['image'] ?? '/assets/images/default-listing.png');
        $price = htmlspecialchars($listing['price'] ?? '');
        $location = htmlspecialchars($listing['location'] ?? '');
        $listingUrl = $basePath . '/listings/' . $listing['id'];

        $html = '<div class="pb-listing-card">';
        $html .= '<a href="' . $listingUrl . '" class="pb-listing-image">';
        $html .= '<img src="' . $image . '" alt="' . $title . '" loading="lazy">';
        $html .= '</a>';

        $html .= '<div class="pb-listing-info">';
        $html .= '<h3 class="pb-listing-title">';
        $html .= '<a href="' . $listingUrl . '">' . $title . '</a>';
        $html .= '</h3>';

        if ($description) {
            $shortDesc = strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description;
            $html .= '<p class="pb-listing-description">' . $shortDesc . '</p>';
        }

        $html .= '<div class="pb-listing-meta">';

        if ($showPrice && $price) {
            $html .= '<span class="pb-listing-price">' . $price . '</span>';
        }

        if ($showLocation && $location) {
            $html .= '<span class="pb-listing-location">';
            $html .= '<i class="fa-solid fa-location-dot"></i> ' . $location;
            $html .= '</span>';
        }

        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    public function validate(array $data): bool
    {
        $limit = (int)($data['limit'] ?? 0);
        if ($limit < 1 || $limit > 100) {
            return false;
        }

        $columns = (int)($data['columns'] ?? 0);
        $validColumns = [1, 2, 3, 4, 6];
        if (!in_array($columns, $validColumns)) {
            return false;
        }

        return true;
    }
}
