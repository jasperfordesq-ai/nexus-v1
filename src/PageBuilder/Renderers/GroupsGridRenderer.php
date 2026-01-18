<?php
/**
 * Groups Grid Renderer
 *
 * Renders a grid of groups with REAL database integration
 */

namespace Nexus\PageBuilder\Renderers;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class GroupsGridRenderer implements BlockRendererInterface
{
    public function render(array $data): string
    {
        $limit = (int)($data['limit'] ?? 6);
        $columns = (int)($data['columns'] ?? 3);
        $orderBy = $data['orderBy'] ?? 'created_at';
        $filter = $data['filter'] ?? 'all';
        $showDescription = (bool)($data['showDescription'] ?? true);
        $showMemberCount = (bool)($data['showMemberCount'] ?? true);

        // Build query
        $tenantId = TenantContext::getId();
        $sql = "SELECT g.id, g.name, g.description, g.image, g.created_at,
                       COUNT(DISTINCT gm.user_id) as member_count
                FROM groups g
                LEFT JOIN group_members gm ON g.id = gm.group_id
                WHERE g.tenant_id = ?";
        $params = [$tenantId];

        // Apply filters
        if ($filter === 'public') {
            $sql .= " AND g.is_private = 0";
        } elseif ($filter === 'private') {
            $sql .= " AND g.is_private = 1";
        } elseif ($filter === 'featured') {
            $sql .= " AND g.is_featured = 1";
        }

        $sql .= " GROUP BY g.id";

        // Order
        $validOrders = ['created_at', 'name', 'member_count'];
        if (!in_array($orderBy, $validOrders)) {
            $orderBy = 'created_at';
        }
        $sql .= " ORDER BY {$orderBy} DESC LIMIT ?";
        $params[] = $limit;

        // Fetch groups
        $groups = Database::query($sql, $params)->fetchAll();

        if (empty($groups)) {
            return '<div class="pb-groups-grid-empty">No groups found.</div>';
        }

        // Render grid
        $html = '<div class="pb-groups-grid columns-' . $columns . '">';

        foreach ($groups as $group) {
            $html .= $this->renderGroupCard($group, $showDescription, $showMemberCount);
        }

        $html .= '</div>';

        return $html;
    }

    private function renderGroupCard(array $group, bool $showDescription, bool $showMemberCount): string
    {
        $basePath = TenantContext::getBasePath();
        $name = htmlspecialchars($group['name']);
        $description = htmlspecialchars($group['description'] ?? '');
        $image = htmlspecialchars($group['image'] ?? '/assets/images/default-group.png');
        $groupUrl = $basePath . '/groups/' . $group['id'];
        $memberCount = (int)($group['member_count'] ?? 0);

        $html = '<div class="pb-group-card">';
        $html .= '<a href="' . $groupUrl . '" class="pb-group-image">';
        $html .= '<img src="' . $image . '" alt="' . $name . '" loading="lazy">';
        $html .= '</a>';

        $html .= '<div class="pb-group-info">';
        $html .= '<h3 class="pb-group-name">';
        $html .= '<a href="' . $groupUrl . '">' . $name . '</a>';
        $html .= '</h3>';

        if ($showDescription && $description) {
            $shortDesc = strlen($description) > 120 ? substr($description, 0, 120) . '...' : $description;
            $html .= '<p class="pb-group-description">' . $shortDesc . '</p>';
        }

        if ($showMemberCount) {
            $memberText = $memberCount === 1 ? 'member' : 'members';
            $html .= '<div class="pb-group-meta">';
            $html .= '<i class="fa-solid fa-users"></i> ';
            $html .= $memberCount . ' ' . $memberText;
            $html .= '</div>';
        }

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
