<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Members Grid Renderer
 *
 * Renders a grid of member profiles with REAL database integration
 */

namespace Nexus\PageBuilder\Renderers;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class MembersGridRenderer implements BlockRendererInterface
{
    public function render(array $data): string
    {
        $limit = (int)($data['limit'] ?? 6);
        $columns = (int)($data['columns'] ?? 3);
        $orderBy = $data['orderBy'] ?? 'created_at';
        $filter = $data['filter'] ?? 'all';
        $showBio = (bool)($data['showBio'] ?? true);
        $showAvatar = (bool)($data['showAvatar'] ?? true);

        // Build query
        $tenantId = TenantContext::getId();
        $sql = "SELECT id, name, avatar, bio, created_at FROM users WHERE tenant_id = ?";
        $params = [$tenantId];

        // Apply filters
        if ($filter === 'verified') {
            $sql .= " AND verified = 1";
        } elseif ($filter === 'featured') {
            $sql .= " AND featured = 1";
        } elseif ($filter === 'active') {
            $sql .= " AND last_active_at > DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }

        // Order
        $validOrders = ['created_at', 'name', 'last_active_at'];
        if (!in_array($orderBy, $validOrders)) {
            $orderBy = 'created_at';
        }
        $sql .= " ORDER BY {$orderBy} DESC LIMIT ?";
        $params[] = $limit;

        // Fetch members
        $members = Database::query($sql, $params)->fetchAll();

        if (empty($members)) {
            return '<div class="pb-members-grid-empty">No members found.</div>';
        }

        // Render grid
        $html = '<div class="pb-members-grid columns-' . $columns . '">';

        foreach ($members as $member) {
            $html .= $this->renderMemberCard($member, $showAvatar, $showBio);
        }

        $html .= '</div>';

        return $html;
    }

    private function renderMemberCard(array $member, bool $showAvatar, bool $showBio): string
    {
        $basePath = TenantContext::getBasePath();
        $name = htmlspecialchars($member['name']);
        $bio = htmlspecialchars($member['bio'] ?? '');
        $avatar = htmlspecialchars($member['avatar'] ?? '/assets/img/defaults/default_avatar.png');
        $profileUrl = $basePath . '/member/' . $member['id'];

        $html = '<div class="pb-member-card">';

        if ($showAvatar) {
            $html .= '<a href="' . $profileUrl . '" class="pb-member-avatar">';
            $html .= '<img src="' . $avatar . '" alt="' . $name . '" loading="lazy">';
            $html .= '</a>';
        }

        $html .= '<div class="pb-member-info">';
        $html .= '<h3 class="pb-member-name">';
        $html .= '<a href="' . $profileUrl . '">' . $name . '</a>';
        $html .= '</h3>';

        if ($showBio && $bio) {
            // Truncate bio to 100 characters
            $shortBio = strlen($bio) > 100 ? substr($bio, 0, 100) . '...' : $bio;
            $html .= '<p class="pb-member-bio">' . $shortBio . '</p>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    public function validate(array $data): bool
    {
        // Validate limit is reasonable
        $limit = (int)($data['limit'] ?? 0);
        if ($limit < 1 || $limit > 100) {
            return false;
        }

        // Validate columns
        $columns = (int)($data['columns'] ?? 0);
        $validColumns = [1, 2, 3, 4, 6];
        if (!in_array($columns, $validColumns)) {
            return false;
        }

        return true;
    }
}
