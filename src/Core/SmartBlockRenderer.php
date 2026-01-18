<?php

namespace Nexus\Core;

use Nexus\Models\User;
use Nexus\Models\Group;
use Nexus\Models\Listing;
use Nexus\Models\Event;

/**
 * Smart Block Renderer
 *
 * Renders dynamic content blocks for CMS pages.
 * Replaces placeholders with data-smart-type attributes with actual content.
 */
class SmartBlockRenderer
{
    private string $basePath;
    private int $tenantId;

    public function __construct()
    {
        $this->basePath = TenantContext::getBasePath();
        $this->tenantId = TenantContext::getId();
    }

    /**
     * Process HTML content and render all Smart Blocks
     */
    public function render(string $html): string
    {
        // Find and replace all smart blocks using regex
        $pattern = '/<div[^>]*data-smart-type=["\']([^"\']+)["\'][^>]*>.*?<\/div>/is';

        return preg_replace_callback($pattern, function($matches) {
            $fullMatch = $matches[0];
            $blockType = $matches[1];

            return $this->renderBlock($blockType);
        }, $html);
    }

    /**
     * Render a specific block type
     */
    private function renderBlock(string $type): string
    {
        switch ($type) {
            case 'members-grid':
                return $this->renderMembersGrid();
            case 'groups-grid':
                return $this->renderGroupsGrid();
            case 'listings-grid':
                return $this->renderListingsGrid();
            case 'events-grid':
                return $this->renderEventsGrid();
            default:
                return $this->renderUnknownBlock($type);
        }
    }

    /**
     * Render Members Grid - Shows recent/featured members
     */
    private function renderMembersGrid(): string
    {
        $members = Database::query(
            "SELECT id, first_name, last_name, avatar_url, location, bio, profile_type, organization_name
             FROM users
             WHERE tenant_id = ? AND is_approved = 1
             ORDER BY created_at DESC
             LIMIT 8",
            [$this->tenantId]
        )->fetchAll();

        if (empty($members)) {
            return $this->renderEmptyState('members', 'No members to display yet');
        }

        $html = '<div class="smart-block-grid smart-members-grid">';
        foreach ($members as $member) {
            $name = $member['profile_type'] === 'organisation' && !empty($member['organization_name'])
                ? htmlspecialchars($member['organization_name'])
                : htmlspecialchars($member['first_name'] . ' ' . $member['last_name']);

            $avatar = !empty($member['avatar_url'])
                ? htmlspecialchars($member['avatar_url'])
                : 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=6366f1&color=fff';

            $location = !empty($member['location']) ? htmlspecialchars($member['location']) : '';
            $bio = !empty($member['bio']) ? htmlspecialchars(substr($member['bio'], 0, 80)) . '...' : '';

            $html .= <<<HTML
            <a href="{$this->basePath}/members/{$member['id']}" class="smart-member-card">
                <img src="{$avatar}" alt="{$name}" class="smart-member-avatar">
                <div class="smart-member-info">
                    <div class="smart-member-name">{$name}</div>
                    {$this->renderLocation($location)}
                </div>
            </a>
HTML;
        }
        $html .= '</div>';
        $html .= '<div class="smart-block-footer"><a href="' . $this->basePath . '/members" class="smart-view-all">View All Members &rarr;</a></div>';

        return $html;
    }

    /**
     * Render Groups Grid - Shows public groups/hubs
     */
    private function renderGroupsGrid(): string
    {
        $groups = Database::query(
            "SELECT g.id, g.name, g.description, g.image_url, g.location, g.visibility,
                    COUNT(gm.id) as member_count
             FROM `groups` g
             LEFT JOIN group_members gm ON g.id = gm.group_id
             WHERE g.tenant_id = ? AND g.visibility = 'public'
             GROUP BY g.id
             ORDER BY member_count DESC, g.created_at DESC
             LIMIT 6",
            [$this->tenantId]
        )->fetchAll();

        if (empty($groups)) {
            return $this->renderEmptyState('groups', 'No groups to display yet');
        }

        $html = '<div class="smart-block-grid smart-groups-grid">';
        foreach ($groups as $group) {
            $name = htmlspecialchars($group['name']);
            $description = !empty($group['description'])
                ? htmlspecialchars(substr($group['description'], 0, 100)) . '...'
                : '';

            $image = !empty($group['image_url'])
                ? htmlspecialchars($group['image_url'])
                : 'https://ui-avatars.com/api/?name=' . urlencode($group['name']) . '&background=8b5cf6&color=fff&size=200';

            $memberCount = (int)$group['member_count'];

            $html .= <<<HTML
            <a href="{$this->basePath}/groups/{$group['id']}" class="smart-group-card">
                <img src="{$image}" alt="{$name}" class="smart-group-image">
                <div class="smart-group-info">
                    <div class="smart-group-name">{$name}</div>
                    <div class="smart-group-meta">{$memberCount} member{$this->plural($memberCount)}</div>
                    <div class="smart-group-desc">{$description}</div>
                </div>
            </a>
HTML;
        }
        $html .= '</div>';
        $html .= '<div class="smart-block-footer"><a href="' . $this->basePath . '/groups" class="smart-view-all">View All Groups &rarr;</a></div>';

        return $html;
    }

    /**
     * Render Listings Grid - Shows recent offers/requests
     */
    private function renderListingsGrid(): string
    {
        $listings = Database::query(
            "SELECT l.id, l.title, l.description, l.type, l.image_url, l.created_at,
                    u.first_name, u.last_name, u.avatar_url, u.profile_type, u.organization_name,
                    c.name as category_name
             FROM listings l
             JOIN users u ON l.user_id = u.id AND u.tenant_id = ?
             LEFT JOIN categories c ON l.category_id = c.id
             WHERE l.tenant_id = ? AND l.status = 'active'
             ORDER BY l.created_at DESC
             LIMIT 6",
            [$this->tenantId, $this->tenantId]
        )->fetchAll();

        if (empty($listings)) {
            return $this->renderEmptyState('listings', 'No listings to display yet');
        }

        $html = '<div class="smart-block-grid smart-listings-grid">';
        foreach ($listings as $listing) {
            $title = htmlspecialchars($listing['title']);
            $description = !empty($listing['description'])
                ? htmlspecialchars(substr(strip_tags($listing['description']), 0, 80)) . '...'
                : '';

            $authorName = $listing['profile_type'] === 'organisation' && !empty($listing['organization_name'])
                ? htmlspecialchars($listing['organization_name'])
                : htmlspecialchars($listing['first_name'] . ' ' . $listing['last_name']);

            $type = ucfirst($listing['type']);
            $typeClass = $listing['type'] === 'offer' ? 'type-offer' : 'type-request';
            $category = !empty($listing['category_name']) ? htmlspecialchars($listing['category_name']) : '';

            $html .= <<<HTML
            <a href="{$this->basePath}/listings/{$listing['id']}" class="smart-listing-card">
                <div class="smart-listing-header">
                    <span class="smart-listing-type {$typeClass}">{$type}</span>
                </div>
                <div class="smart-listing-title">{$title}</div>
                <div class="smart-listing-desc">{$description}</div>
                <div class="smart-listing-meta">
                    <span class="smart-listing-author">{$authorName}</span>
                    {$this->renderCategory($category)}
                </div>
            </a>
HTML;
        }
        $html .= '</div>';
        $html .= '<div class="smart-block-footer"><a href="' . $this->basePath . '/listings" class="smart-view-all">View All Listings &rarr;</a></div>';

        return $html;
    }

    /**
     * Render Events Grid - Shows upcoming events
     */
    private function renderEventsGrid(): string
    {
        $events = Database::query(
            "SELECT e.id, e.title, e.description, e.location, e.start_time, e.end_time,
                    u.first_name, u.last_name,
                    c.name as category_name, c.color as category_color
             FROM events e
             JOIN users u ON e.user_id = u.id
             LEFT JOIN categories c ON e.category_id = c.id
             LEFT JOIN `groups` g ON e.group_id = g.id
             WHERE e.tenant_id = ? AND e.start_time >= NOW()
             AND (g.visibility IS NULL OR g.visibility = 'public')
             ORDER BY e.start_time ASC
             LIMIT 6",
            [$this->tenantId]
        )->fetchAll();

        if (empty($events)) {
            return $this->renderEmptyState('events', 'No upcoming events');
        }

        $html = '<div class="smart-block-grid smart-events-grid">';
        foreach ($events as $event) {
            $title = htmlspecialchars($event['title']);
            $location = !empty($event['location']) ? htmlspecialchars($event['location']) : 'Location TBD';

            $startTime = strtotime($event['start_time']);
            $date = date('M j', $startTime);
            $time = date('g:i A', $startTime);
            $day = date('D', $startTime);

            $html .= <<<HTML
            <a href="{$this->basePath}/events/{$event['id']}" class="smart-event-card">
                <div class="smart-event-date">
                    <span class="smart-event-day">{$day}</span>
                    <span class="smart-event-date-num">{$date}</span>
                </div>
                <div class="smart-event-info">
                    <div class="smart-event-title">{$title}</div>
                    <div class="smart-event-time"><i class="fas fa-clock"></i> {$time}</div>
                    <div class="smart-event-location"><i class="fas fa-map-marker-alt"></i> {$location}</div>
                </div>
            </a>
HTML;
        }
        $html .= '</div>';
        $html .= '<div class="smart-block-footer"><a href="' . $this->basePath . '/events" class="smart-view-all">View All Events &rarr;</a></div>';

        return $html;
    }

    /**
     * Render unknown block type placeholder
     */
    private function renderUnknownBlock(string $type): string
    {
        return '<div class="smart-block-error">Unknown block type: ' . htmlspecialchars($type) . '</div>';
    }

    /**
     * Render empty state for a block
     */
    private function renderEmptyState(string $type, string $message): string
    {
        return <<<HTML
        <div class="smart-block-empty">
            <div class="smart-block-empty-icon">
                <i class="fas fa-inbox"></i>
            </div>
            <p>{$message}</p>
        </div>
HTML;
    }

    /**
     * Helper to render location badge
     */
    private function renderLocation(string $location): string
    {
        if (empty($location)) return '';
        return '<div class="smart-location"><i class="fas fa-map-marker-alt"></i> ' . $location . '</div>';
    }

    /**
     * Helper to render category badge
     */
    private function renderCategory(string $category): string
    {
        if (empty($category)) return '';
        return '<span class="smart-category">' . $category . '</span>';
    }

    /**
     * Helper for pluralization
     */
    private function plural(int $count): string
    {
        return $count === 1 ? '' : 's';
    }

    /**
     * Get CSS styles for Smart Blocks
     */
    public static function getStyles(): string
    {
        return <<<CSS
/* ============================================
   SMART BLOCKS - Dynamic Content Styles
   ============================================ */

.smart-block-grid {
    display: grid;
    gap: 20px;
    margin: 20px 0;
}

.smart-members-grid {
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
}

.smart-groups-grid,
.smart-listings-grid {
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
}

.smart-events-grid {
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
}

/* Member Cards */
.smart-member-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    background: var(--nexus-card-bg, #fff);
    border: 1px solid var(--nexus-border, #e2e8f0);
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.2s;
}

.smart-member-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    border-color: #6366f1;
}

.smart-member-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
}

.smart-member-name {
    font-weight: 600;
    color: var(--nexus-text-main, #1e293b);
    font-size: 0.95rem;
}

/* Group Cards */
.smart-group-card {
    background: var(--nexus-card-bg, #fff);
    border: 1px solid var(--nexus-border, #e2e8f0);
    border-radius: 12px;
    overflow: hidden;
    text-decoration: none;
    transition: all 0.2s;
}

.smart-group-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.smart-group-image {
    width: 100%;
    height: 120px;
    object-fit: cover;
}

.smart-group-info {
    padding: 15px;
}

.smart-group-name {
    font-weight: 600;
    color: var(--nexus-text-main, #1e293b);
    font-size: 1rem;
    margin-bottom: 4px;
}

.smart-group-meta {
    font-size: 0.8rem;
    color: #6366f1;
    margin-bottom: 8px;
}

.smart-group-desc {
    font-size: 0.85rem;
    color: var(--nexus-text-muted, #64748b);
    line-height: 1.4;
}

/* Listing Cards */
.smart-listing-card {
    display: flex;
    flex-direction: column;
    padding: 20px;
    background: var(--nexus-card-bg, #fff);
    border: 1px solid var(--nexus-border, #e2e8f0);
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.2s;
}

.smart-listing-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.smart-listing-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.smart-listing-type {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.smart-listing-type.type-offer {
    background: #dcfce7;
    color: #16a34a;
}

.smart-listing-type.type-request {
    background: #dbeafe;
    color: #2563eb;
}

.smart-listing-credits {
    font-weight: 600;
    color: #6366f1;
    font-size: 0.85rem;
}

.smart-listing-title {
    font-weight: 600;
    color: var(--nexus-text-main, #1e293b);
    font-size: 1rem;
    margin-bottom: 8px;
}

.smart-listing-desc {
    font-size: 0.85rem;
    color: var(--nexus-text-muted, #64748b);
    margin-bottom: 12px;
    flex: 1;
}

.smart-listing-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8rem;
    color: var(--nexus-text-muted, #64748b);
}

/* Event Cards */
.smart-event-card {
    display: flex;
    gap: 15px;
    padding: 15px;
    background: var(--nexus-card-bg, #fff);
    border: 1px solid var(--nexus-border, #e2e8f0);
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.2s;
}

.smart-event-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    border-color: #6366f1;
}

.smart-event-date {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 60px;
    padding: 10px;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    border-radius: 10px;
    color: white;
}

.smart-event-day {
    font-size: 0.7rem;
    text-transform: uppercase;
    opacity: 0.9;
}

.smart-event-date-num {
    font-size: 1rem;
    font-weight: 700;
}

.smart-event-info {
    flex: 1;
}

.smart-event-title {
    font-weight: 600;
    color: var(--nexus-text-main, #1e293b);
    font-size: 1rem;
    margin-bottom: 6px;
}

.smart-event-time,
.smart-event-location {
    font-size: 0.8rem;
    color: var(--nexus-text-muted, #64748b);
    margin-bottom: 3px;
}

.smart-event-time i,
.smart-event-location i {
    width: 16px;
    margin-right: 4px;
    color: #6366f1;
}

/* Common Elements */
.smart-location {
    font-size: 0.8rem;
    color: var(--nexus-text-muted, #64748b);
}

.smart-location i {
    color: #6366f1;
    margin-right: 4px;
}

.smart-category {
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.75rem;
}

.smart-block-footer {
    text-align: center;
    margin-top: 20px;
}

.smart-view-all {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #6366f1;
    font-weight: 600;
    text-decoration: none;
    font-size: 0.9rem;
}

.smart-view-all:hover {
    text-decoration: underline;
}

/* Empty State */
.smart-block-empty {
    text-align: center;
    padding: 40px 20px;
    background: #f8fafc;
    border-radius: 12px;
    border: 2px dashed #e2e8f0;
}

.smart-block-empty-icon {
    font-size: 2rem;
    color: #94a3b8;
    margin-bottom: 10px;
}

.smart-block-empty p {
    color: #64748b;
    margin: 0;
}

.smart-block-error {
    padding: 20px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    color: #dc2626;
    text-align: center;
}

/* Dark Mode */
[data-theme="dark"] .smart-member-card,
[data-theme="dark"] .smart-group-card,
[data-theme="dark"] .smart-listing-card,
[data-theme="dark"] .smart-event-card {
    background: rgba(30, 41, 59, 0.6);
    border-color: rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .smart-member-name,
[data-theme="dark"] .smart-group-name,
[data-theme="dark"] .smart-listing-title,
[data-theme="dark"] .smart-event-title {
    color: #e2e8f0;
}

[data-theme="dark"] .smart-group-desc,
[data-theme="dark"] .smart-listing-desc,
[data-theme="dark"] .smart-location,
[data-theme="dark"] .smart-listing-meta,
[data-theme="dark"] .smart-event-time,
[data-theme="dark"] .smart-event-location {
    color: #94a3b8;
}

[data-theme="dark"] .smart-category {
    background: rgba(51, 65, 85, 0.6);
    color: #94a3b8;
}

[data-theme="dark"] .smart-block-empty {
    background: rgba(30, 41, 59, 0.4);
    border-color: rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .smart-listing-type.type-offer {
    background: rgba(22, 163, 74, 0.2);
    color: #4ade80;
}

[data-theme="dark"] .smart-listing-type.type-request {
    background: rgba(37, 99, 235, 0.2);
    color: #60a5fa;
}

/* Responsive */
@media (max-width: 768px) {
    .smart-block-grid {
        grid-template-columns: 1fr;
    }

    .smart-members-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .smart-members-grid {
        grid-template-columns: 1fr;
    }
}
CSS;
    }
}
