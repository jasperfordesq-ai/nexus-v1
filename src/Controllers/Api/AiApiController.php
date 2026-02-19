<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\ApiAuth;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\AiConversation;
use Nexus\Models\AiMessage;
use Nexus\Models\AiUsage;
use Nexus\Models\AiUserLimit;
use Nexus\Models\AiSettings;
use Nexus\Models\User;
use Nexus\Models\Listing;
use Nexus\Models\Group;
use Nexus\Models\Event;
use Nexus\Services\AI\AIServiceFactory;

/**
 * AI API Controller
 *
 * Handles all AI-related API endpoints.
 */
class AiApiController
{
    use ApiAuth;

    private function jsonResponse($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    private function getUserId()
    {
        return $this->requireAuth();
    }

    private function getInput(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    /**
     * Build dynamic user context for personalized AI responses
     */
    private function buildUserContext(int $userId): string
    {
        $context = "\n\n## CURRENT USER CONTEXT\n";
        $context .= "The following is real-time information about the user you're helping:\n\n";

        $userLocation = null; // Track user's location for nearby suggestions

        try {
            // Get user basic info including location
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT id, name, email, bio, location, created_at FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($user) {
                $context .= "**User:** {$user['name']}\n";
                $memberSince = date('F Y', strtotime($user['created_at']));
                $context .= "**Member since:** {$memberSince}\n";
                if (!empty($user['location'])) {
                    $context .= "**Location:** {$user['location']}\n";
                    $userLocation = $user['location'];
                }
                if (!empty($user['bio'])) {
                    $context .= "**Bio:** {$user['bio']}\n";
                }
            }

            // Get wallet balance
            $tenantId = TenantContext::getId();
            $stmt = $db->prepare("SELECT balance FROM time_wallets WHERE user_id = ? AND tenant_id = ?");
            $stmt->execute([$userId, $tenantId]);
            $wallet = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($wallet) {
                $context .= "**Time Credit Balance:** {$wallet['balance']} hours\n";
            }

            // Get user's listings count
            $stmt = $db->prepare("SELECT
                COUNT(*) as total,
                SUM(CASE WHEN type = 'offer' THEN 1 ELSE 0 END) as offers,
                SUM(CASE WHEN type = 'request' THEN 1 ELSE 0 END) as requests
                FROM listings WHERE user_id = ? AND tenant_id = ? AND status = 'active'");
            $stmt->execute([$userId, $tenantId]);
            $listings = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($listings && $listings['total'] > 0) {
                $context .= "**Active Listings:** {$listings['total']} ({$listings['offers']} offers, {$listings['requests']} requests)\n";
            } else {
                $context .= "**Active Listings:** None yet\n";
            }

            // Get user's recent listing titles
            $stmt = $db->prepare("SELECT title, type FROM listings WHERE user_id = ? AND tenant_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 5");
            $stmt->execute([$userId, $tenantId]);
            $recentListings = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($recentListings)) {
                $context .= "**Their listings:**\n";
                foreach ($recentListings as $listing) {
                    $type = ucfirst($listing['type']);
                    $context .= "  - [{$type}] {$listing['title']}\n";
                }
            }

            // Get groups membership
            $stmt = $db->prepare("SELECT g.name FROM groups g
                JOIN group_members gm ON g.id = gm.group_id
                WHERE gm.user_id = ? AND g.tenant_id = ?
                LIMIT 5");
            $stmt->execute([$userId, $tenantId]);
            $groups = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($groups)) {
                $groupNames = array_column($groups, 'name');
                $context .= "**Member of groups:** " . implode(', ', $groupNames) . "\n";
            }

            // Get recent transaction activity
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM time_transactions
                WHERE (from_user_id = ? OR to_user_id = ?) AND tenant_id = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute([$userId, $userId, $tenantId]);
            $transactions = $stmt->fetch(\PDO::FETCH_ASSOC);
            $context .= "**Exchanges in last 30 days:** " . ($transactions['count'] ?? 0) . "\n";

            // Get achievement count
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM user_achievements WHERE user_id = ?");
            $stmt->execute([$userId]);
            $achievements = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($achievements && $achievements['count'] > 0) {
                $context .= "**Achievements earned:** {$achievements['count']}\n";
            }

            // Get XP if available
            $stmt = $db->prepare("SELECT xp FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $xpData = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($xpData && isset($xpData['xp']) && $xpData['xp'] > 0) {
                $context .= "**XP Points:** {$xpData['xp']}\n";
            }

        } catch (\Exception $e) {
            // If we can't get context, just return minimal info
            $context .= "(Unable to load some user data)\n";
        }

        $context .= "\nUse this context to give personalized, relevant responses. Reference their specific situation when helpful (e.g., 'Since you have 3 offers listed...' or 'With your balance of X hours...').\n";

        // Add platform-wide context with user's location for nearby suggestions
        $context .= $this->buildPlatformContext($userId, $userLocation);

        return $context;
    }

    /**
     * Build platform-wide context with community data the AI can reference
     * @param int $currentUserId The current user's ID
     * @param string|null $userLocation The user's location for nearby suggestions
     */
    private function buildPlatformContext(int $currentUserId, ?string $userLocation = null): string
    {
        $context = "\n\n## LIVE PLATFORM DATA\n";
        $context .= "Current community data you can reference when answering questions:\n\n";

        try {
            $db = Database::getConnection();
            $tenantId = TenantContext::getId();

            // Platform statistics
            $context .= "### Community Statistics\n";

            // Total members
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE tenant_id = ? AND status = 'active'");
            $stmt->execute([$tenantId]);
            $memberCount = $stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
            $context .= "- **Total active members:** {$memberCount}\n";

            // Total listings
            $stmt = $db->prepare("SELECT
                COUNT(*) as total,
                SUM(CASE WHEN type = 'offer' THEN 1 ELSE 0 END) as offers,
                SUM(CASE WHEN type = 'request' THEN 1 ELSE 0 END) as requests
                FROM listings WHERE tenant_id = ? AND status = 'active'");
            $stmt->execute([$tenantId]);
            $listingStats = $stmt->fetch(\PDO::FETCH_ASSOC);
            $context .= "- **Active listings:** {$listingStats['total']} total ({$listingStats['offers']} offers, {$listingStats['requests']} requests)\n";

            // Active groups
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM groups WHERE tenant_id = ? AND status = 'active'");
            $stmt->execute([$tenantId]);
            $groupCount = $stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
            $context .= "- **Active groups/hubs:** {$groupCount}\n";

            // Upcoming events
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM events WHERE tenant_id = ? AND start_datetime > NOW() AND status = 'published'");
            $stmt->execute([$tenantId]);
            $eventCount = $stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
            $context .= "- **Upcoming events:** {$eventCount}\n";

            // =====================================
            // CURRENT REQUESTS (help needed)
            // =====================================
            $context .= "\n### Current Requests (Community Needs Help With)\n";
            $context .= "These are active requests from community members looking for help:\n\n";

            $stmt = $db->prepare("
                SELECT l.id, l.title, l.description, u.name as user_name, u.location as user_location, c.name as category_name
                FROM listings l
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN categories c ON l.category_id = c.id
                WHERE l.tenant_id = ? AND l.type = 'request' AND l.status = 'active'
                ORDER BY l.created_at DESC
                LIMIT 15
            ");
            $stmt->execute([$tenantId]);
            $requests = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($requests)) {
                foreach ($requests as $req) {
                    $category = $req['category_name'] ? " [{$req['category_name']}]" : "";
                    $location = $req['user_location'] ? " (📍 {$req['user_location']})" : "";
                    $shortDesc = strlen($req['description'] ?? '') > 100 ? substr($req['description'], 0, 100) . '...' : ($req['description'] ?? '');
                    $shortDesc = str_replace(["\n", "\r"], ' ', $shortDesc);
                    $context .= "- **\"{$req['title']}\"**{$category} - requested by {$req['user_name']}{$location}\n";
                    if ($shortDesc) {
                        $context .= "  _{$shortDesc}_\n";
                    }
                }
            } else {
                $context .= "_No active requests at this time._\n";
            }

            // =====================================
            // CURRENT OFFERS (skills available)
            // =====================================
            $context .= "\n### Current Offers (Skills Available in Community)\n";
            $context .= "These are active offers from community members willing to help:\n\n";

            $stmt = $db->prepare("
                SELECT l.id, l.title, l.description, u.name as user_name, u.location as user_location, c.name as category_name
                FROM listings l
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN categories c ON l.category_id = c.id
                WHERE l.tenant_id = ? AND l.type = 'offer' AND l.status = 'active'
                ORDER BY l.created_at DESC
                LIMIT 15
            ");
            $stmt->execute([$tenantId]);
            $offers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($offers)) {
                foreach ($offers as $offer) {
                    $category = $offer['category_name'] ? " [{$offer['category_name']}]" : "";
                    $location = $offer['user_location'] ? " (📍 {$offer['user_location']})" : "";
                    $shortDesc = strlen($offer['description'] ?? '') > 100 ? substr($offer['description'], 0, 100) . '...' : ($offer['description'] ?? '');
                    $shortDesc = str_replace(["\n", "\r"], ' ', $shortDesc);
                    $context .= "- **\"{$offer['title']}\"**{$category} - offered by {$offer['user_name']}{$location}\n";
                    if ($shortDesc) {
                        $context .= "  _{$shortDesc}_\n";
                    }
                }
            } else {
                $context .= "_No active offers at this time._\n";
            }

            // =====================================
            // UPCOMING EVENTS
            // =====================================
            $context .= "\n### Upcoming Events\n";

            $stmt = $db->prepare("
                SELECT e.id, e.title, e.description, e.start_datetime, e.location, u.name as host_name
                FROM events e
                LEFT JOIN users u ON e.user_id = u.id
                WHERE e.tenant_id = ? AND e.start_datetime > NOW() AND e.status = 'published'
                ORDER BY e.start_datetime ASC
                LIMIT 10
            ");
            $stmt->execute([$tenantId]);
            $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($events)) {
                foreach ($events as $event) {
                    $dateStr = date('M j, Y g:ia', strtotime($event['start_datetime']));
                    $location = $event['location'] ? " at {$event['location']}" : "";
                    $context .= "- **\"{$event['title']}\"** - {$dateStr}{$location}\n";
                    $context .= "  Hosted by {$event['host_name']}\n";
                }
            } else {
                $context .= "_No upcoming events scheduled._\n";
            }

            // =====================================
            // ACTIVE GROUPS/HUBS
            // =====================================
            $context .= "\n### Active Groups/Hubs\n";

            $stmt = $db->prepare("
                SELECT g.id, g.name, g.description,
                    (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) as member_count
                FROM `groups` g
                WHERE g.tenant_id = ?
                ORDER BY member_count DESC
                LIMIT 10
            ");
            $stmt->execute([$tenantId]);
            $groups = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($groups)) {
                foreach ($groups as $group) {
                    $context .= "- **\"{$group['name']}\"** ({$group['member_count']} members)\n";
                }
            } else {
                $context .= "_No active groups._\n";
            }

            // =====================================
            // LISTING CATEGORIES
            // =====================================
            $context .= "\n### Available Categories\n";

            $stmt = $db->prepare("
                SELECT c.name, COUNT(l.id) as listing_count
                FROM categories c
                LEFT JOIN listings l ON c.id = l.category_id AND l.status = 'active' AND l.tenant_id = ?
                WHERE c.tenant_id = ? OR c.tenant_id IS NULL
                GROUP BY c.id, c.name
                HAVING listing_count > 0
                ORDER BY listing_count DESC
                LIMIT 15
            ");
            $stmt->execute([$tenantId, $tenantId]);
            $categories = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($categories)) {
                $catList = array_map(fn($c) => "{$c['name']} ({$c['listing_count']})", $categories);
                $context .= implode(', ', $catList) . "\n";
            }

            // =====================================
            // RECENT ACTIVITY
            // =====================================
            $context .= "\n### Recent Community Activity\n";

            // Recent transactions
            $stmt = $db->prepare("
                SELECT COUNT(*) as count, SUM(amount) as total_hours
                FROM time_transactions
                WHERE tenant_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$tenantId]);
            $recentTrans = $stmt->fetch(\PDO::FETCH_ASSOC);
            $context .= "- **Last 7 days:** {$recentTrans['count']} exchanges, " . round($recentTrans['total_hours'] ?? 0, 1) . " hours exchanged\n";

            // New members this month
            $stmt = $db->prepare("
                SELECT COUNT(*) as count FROM users
                WHERE tenant_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute([$tenantId]);
            $newMembers = $stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
            $context .= "- **New members this month:** {$newMembers}\n";

            // =====================================
            // NEARBY LISTINGS (Location-based suggestions)
            // =====================================
            if (!empty($userLocation)) {
                $context .= "\n### Nearby Listings (Near {$userLocation})\n";
                $context .= "These listings are from members in or near the user's location. PRIORITIZE suggesting these when relevant:\n\n";

                // Get nearby offers - members with similar/same location
                $locationParts = array_map('trim', explode(',', $userLocation));
                $locationPatterns = [];
                $params = [$tenantId, 'offer', 'active', $currentUserId];

                // Build flexible location matching (city, region, or full location match)
                $locationConditions = [];
                foreach ($locationParts as $part) {
                    if (strlen($part) > 2) {
                        $locationConditions[] = "u.location LIKE ?";
                        $params[] = '%' . $part . '%';
                    }
                }

                if (!empty($locationConditions)) {
                    $locationWhere = '(' . implode(' OR ', $locationConditions) . ')';

                    $stmt = $db->prepare("
                        SELECT l.id, l.title, l.type, u.name as user_name, u.location as user_location, c.name as category_name
                        FROM listings l
                        LEFT JOIN users u ON l.user_id = u.id
                        LEFT JOIN categories c ON l.category_id = c.id
                        WHERE l.tenant_id = ? AND l.type = ? AND l.status = ? AND l.user_id != ?
                        AND {$locationWhere}
                        ORDER BY l.created_at DESC
                        LIMIT 8
                    ");
                    $stmt->execute($params);
                    $nearbyOffers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                    if (!empty($nearbyOffers)) {
                        $context .= "**Nearby Offers (people who can help near you):**\n";
                        foreach ($nearbyOffers as $offer) {
                            $category = $offer['category_name'] ? " [{$offer['category_name']}]" : "";
                            $loc = $offer['user_location'] ? " - {$offer['user_location']}" : "";
                            $context .= "- **\"{$offer['title']}\"**{$category} by {$offer['user_name']}{$loc}\n";
                        }
                        $context .= "\n";
                    }

                    // Get nearby requests
                    $params[1] = 'request'; // Change type to request
                    $stmt = $db->prepare("
                        SELECT l.id, l.title, l.type, u.name as user_name, u.location as user_location, c.name as category_name
                        FROM listings l
                        LEFT JOIN users u ON l.user_id = u.id
                        LEFT JOIN categories c ON l.category_id = c.id
                        WHERE l.tenant_id = ? AND l.type = ? AND l.status = ? AND l.user_id != ?
                        AND {$locationWhere}
                        ORDER BY l.created_at DESC
                        LIMIT 8
                    ");
                    $stmt->execute($params);
                    $nearbyRequests = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                    if (!empty($nearbyRequests)) {
                        $context .= "**Nearby Requests (neighbors who need help near you):**\n";
                        foreach ($nearbyRequests as $request) {
                            $category = $request['category_name'] ? " [{$request['category_name']}]" : "";
                            $loc = $request['user_location'] ? " - {$request['user_location']}" : "";
                            $context .= "- **\"{$request['title']}\"**{$category} by {$request['user_name']}{$loc}\n";
                        }
                        $context .= "\n";
                    }

                    // Get nearby members count
                    $memberParams = [$tenantId, 'active', $currentUserId];
                    $memberConditions = [];
                    foreach ($locationParts as $part) {
                        if (strlen($part) > 2) {
                            $memberConditions[] = "location LIKE ?";
                            $memberParams[] = '%' . $part . '%';
                        }
                    }
                    if (!empty($memberConditions)) {
                        $memberWhere = '(' . implode(' OR ', $memberConditions) . ')';
                        $stmt = $db->prepare("
                            SELECT COUNT(*) as count FROM users
                            WHERE tenant_id = ? AND status = ? AND id != ? AND {$memberWhere}
                        ");
                        $stmt->execute($memberParams);
                        $nearbyMemberCount = $stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
                        if ($nearbyMemberCount > 0) {
                            $context .= "**{$nearbyMemberCount} other members are in or near {$userLocation}**\n";
                        }
                    }

                    if (empty($nearbyOffers) && empty($nearbyRequests)) {
                        $context .= "_No nearby listings found. Consider suggesting listings from other areas or encouraging the user to browse all listings._\n";
                    }
                }
            }

            $context .= "\n---\n";
            $context .= "You have access to all the above real-time platform data. When users ask questions like 'what requests are there?' or 'what help is needed?', refer to the specific listings above. When they ask about events, groups, or offers, use the actual data provided.\n";
            if (!empty($userLocation)) {
                $context .= "\n**IMPORTANT - Location-Aware Suggestions:** The user is located in **{$userLocation}**. When suggesting listings, ALWAYS prioritize nearby listings first and mention their proximity. If suggesting a listing from another area, note that it's not in their immediate vicinity.\n";
            }

        } catch (\Exception $e) {
            $context .= "(Unable to load some platform data)\n";
            error_log("AI Platform Context Error: " . $e->getMessage());
        }

        return $context;
    }

    /**
     * Smart Context Engine - Tenant-Aware, Geo-Intelligent Context Retrieval
     *
     * This method implements a scoped, multi-tenant-aware intelligence pipeline:
     * 1. Tenant Isolation: Strict scoping to user's assigned tenant
     * 2. Intent Detection: Analyzes if user needs help (show OFFERS) or wants to help (show REQUESTS)
     * 3. Geo-Proximity: Uses Haversine formula with location-based fallback
     * 4. Keyword Matching: Filters by relevant keywords in title/description
     *
     * @param int $tenantId The tenant ID (enforced scope)
     * @param int $userId The current user ID
     * @param string $message The user's message to analyze
     * @return string Formatted context block for AI system prompt
     */
    private function fetchSmartContext(int $tenantId, int $userId, string $message): string
    {
        try {
            $db = Database::getConnection();

            // ========================================
            // TENANT GUARD (Priority 1: Tenant Isolation)
            // ========================================
            // Validate tenant exists - use user's actual tenant or fallback to Master (1)
            if ($tenantId < 1) {
                error_log("Smart Context Engine: Invalid tenant $tenantId, defaulting to Master Tenant");
                $tenantId = 1; // Master Tenant fallback
            }

            // ========================================
            // STEP 1: INTENT DETECTION
            // ========================================
            $messageLower = strtolower($message);
            $targetType = null;

            // "Need Help" keywords -> Show OFFERS (people offering services)
            $needKeywords = ['need', 'looking for', 'want', 'search', 'help me', 'hire', 'find', 'require', 'seeking'];
            foreach ($needKeywords as $keyword) {
                if (strpos($messageLower, $keyword) !== false) {
                    $targetType = 'offer';
                    break;
                }
            }

            // "Give Help" keywords -> Show REQUESTS (people requesting services)
            if ($targetType === null) {
                $giveKeywords = ['can i', 'want to help', 'volunteer', 'offer', 'available', 'i can', 'willing to', 'able to'];
                foreach ($giveKeywords as $keyword) {
                    if (strpos($messageLower, $keyword) !== false) {
                        $targetType = 'request';
                        break;
                    }
                }
            }

            // ========================================
            // STEP 2: GET USER COORDINATES (Irish Anchor)
            // ========================================
            // CRITICAL: Tenant-scoped user query
            $stmt = $db->prepare("SELECT latitude, longitude, location FROM users WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$userId, $tenantId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            $userLat = $user['latitude'] ?? null;
            $userLng = $user['longitude'] ?? null;
            $userLocation = $user['location'] ?? null;

            // ========================================
            // IRISH ANCHOR FALLBACK
            // ========================================
            // If location is ambiguous or missing, default to "Ireland"
            if (empty($userLocation)) {
                $userLocation = 'Ireland'; // National scope for Tenant 2
                error_log("Smart Context Engine: User location null, defaulting to 'Ireland' (Tenant 2)");
            }

            $hasCoordinates = ($userLat !== null && $userLng !== null);

            // ========================================
            // STEP 3: EXTRACT KEYWORDS FROM MESSAGE
            // ========================================
            // Remove common stopwords and extract meaningful keywords
            $stopwords = ['i', 'need', 'want', 'can', 'help', 'me', 'with', 'the', 'a', 'an', 'to', 'for', 'in', 'on', 'at'];
            $words = preg_split('/\s+/', $messageLower);
            $keywords = array_filter($words, function($word) use ($stopwords) {
                return strlen($word) > 2 && !in_array($word, $stopwords);
            });

            // Use first few meaningful words as search terms
            $searchTerms = array_slice($keywords, 0, 3);

            // ========================================
            // STEP 4: BUILD GEO-AWARE SQL QUERY
            // ========================================

            if ($hasCoordinates) {
                // GEO-BOOST QUERY with Haversine Distance Formula
                $sql = "SELECT
                    l.id,
                    l.title,
                    l.description,
                    l.type,
                    l.location,
                    l.user_id,
                    u.name as user_name,
                    (6371 * acos(
                        cos(radians(:userLat))
                        * cos(radians(l.latitude))
                        * cos(radians(l.longitude) - radians(:userLng))
                        + sin(radians(:userLat))
                        * sin(radians(l.latitude))
                    )) AS distance_km
                FROM listings l
                JOIN users u ON l.user_id = u.id
                WHERE l.tenant_id = :tenantId
                    AND l.status = 'active'
                    AND l.latitude IS NOT NULL
                    AND l.longitude IS NOT NULL";

                // Add keyword filtering if we have search terms
                if (!empty($searchTerms)) {
                    $sql .= " AND (";
                    $conditions = [];
                    foreach ($searchTerms as $term) {
                        $hash = md5($term);
                        $conditions[] = "l.title LIKE :keyword_title_{$hash} OR l.description LIKE :keyword_desc_{$hash}";
                    }
                    $sql .= implode(' OR ', $conditions);
                    $sql .= ")";
                }

                // Add type filter if intent was detected
                if ($targetType !== null) {
                    $sql .= " AND l.type = :targetType";
                }

                // Order by proximity first, then freshness
                $sql .= " ORDER BY distance_km ASC, l.created_at DESC LIMIT 5";

                $stmt = $db->prepare($sql);
                $stmt->bindValue(':userLat', $userLat, \PDO::PARAM_STR);
                $stmt->bindValue(':userLng', $userLng, \PDO::PARAM_STR);
                $stmt->bindValue(':tenantId', $tenantId, \PDO::PARAM_INT);

                // Bind keyword parameters (separate params for title and description)
                foreach ($searchTerms as $term) {
                    $hash = md5($term);
                    $stmt->bindValue(':keyword_title_' . $hash, '%' . $term . '%', \PDO::PARAM_STR);
                    $stmt->bindValue(':keyword_desc_' . $hash, '%' . $term . '%', \PDO::PARAM_STR);
                }

                if ($targetType !== null) {
                    $stmt->bindValue(':targetType', $targetType, \PDO::PARAM_STR);
                }

            } else {
                // FALLBACK: Simple text search without geo-distance
                $sql = "SELECT
                    l.id,
                    l.title,
                    l.description,
                    l.type,
                    l.location,
                    l.user_id,
                    u.name as user_name
                FROM listings l
                JOIN users u ON l.user_id = u.id
                WHERE l.tenant_id = :tenantId
                    AND l.status = 'active'";

                if (!empty($searchTerms)) {
                    $sql .= " AND (";
                    $conditions = [];
                    foreach ($searchTerms as $term) {
                        $hash = md5($term);
                        $conditions[] = "l.title LIKE :keyword_title_{$hash} OR l.description LIKE :keyword_desc_{$hash}";
                    }
                    $sql .= implode(' OR ', $conditions);
                    $sql .= ")";
                }

                if ($targetType !== null) {
                    $sql .= " AND l.type = :targetType";
                }

                $sql .= " ORDER BY l.created_at DESC LIMIT 5";

                $stmt = $db->prepare($sql);
                $stmt->bindValue(':tenantId', $tenantId, \PDO::PARAM_INT);

                // Bind keyword parameters (separate params for title and description)
                foreach ($searchTerms as $term) {
                    $hash = md5($term);
                    $stmt->bindValue(':keyword_title_' . $hash, '%' . $term . '%', \PDO::PARAM_STR);
                    $stmt->bindValue(':keyword_desc_' . $hash, '%' . $term . '%', \PDO::PARAM_STR);
                }

                if ($targetType !== null) {
                    $stmt->bindValue(':targetType', $targetType, \PDO::PARAM_STR);
                }
            }

            $stmt->execute();
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // ========================================
            // STEP 5: FORMAT CONTEXT FOR AI (Irish Identity)
            // ========================================

            if (empty($results)) {
                return ""; // No relevant listings found
            }

            // Irish Tenant Identity Header
            $context = "\n\n## 🎯 SMART MATCH INTELLIGENCE (Tenant 2: Ireland Network 🇮🇪)\n";
            $context .= "**Network Scope:** Ireland Timebank Community\n";
            $context .= "**Intent Detected:** " . ($targetType === 'offer' ? "User NEEDS help (showing OFFERS)" : ($targetType === 'request' ? "User WANTS to help (showing REQUESTS)" : "General inquiry")) . "\n";
            $context .= "**User Location:** $userLocation\n";
            $context .= "**Relevant Listings Found:** " . count($results) . "\n\n";

            foreach ($results as $listing) {
                $typeLabel = strtoupper($listing['type']);
                $title = htmlspecialchars($listing['title']);
                $userName = htmlspecialchars($listing['user_name']);
                $location = htmlspecialchars($listing['location'] ?? 'Location not specified');

                // Truncate description to 150 chars
                $description = $listing['description'] ?? '';
                if (strlen($description) > 150) {
                    $description = substr($description, 0, 150) . '...';
                }
                $description = str_replace(["\n", "\r"], ' ', $description);

                if ($hasCoordinates && isset($listing['distance_km'])) {
                    $distance = round($listing['distance_km'], 1);
                    $distanceLabel = $distance < 1 ? "< 1 km away" : "$distance km away";
                    $context .= "**[$typeLabel - $distanceLabel]** \"$title\" by $userName\n";
                } else {
                    $context .= "**[$typeLabel]** \"$title\" by $userName (Location: $location)\n";
                }

                if ($description) {
                    $context .= "  _$description_\n";
                }
                $context .= "\n";
            }

            // Add proximity tip if user lacks coordinates
            if (!$hasCoordinates) {
                $context .= "💡 **Proximity Tip:** User has not set precise coordinates. Showing national matches. Recommend they add their location for better distance-based matches.\n\n";
            }

            // Enhanced instruction for Irish Network context
            $context .= "**🇮🇪 NETWORK INSTRUCTION (Ireland):** You are the Tenant 2 Assistant representing the Ireland Timebank Community. ";
            $context .= "These are LIVE database matches scoped to Ireland. ";
            $context .= "Prioritize mentioning specific member names, locations, and distances (if available). ";
            $context .= "Use a humble, learning tone: 'Bear with me while I learn the ropes.' ";
            $context .= "Always emphasize local connections and nearest neighbors first. ";
            $context .= "If showing OFFERS, explain how they can help the user. If showing REQUESTS, explain how the user's skills match the needs.\n";

            return $context;

        } catch (\Exception $e) {
            error_log("Smart Context Engine Error: " . $e->getMessage());
            return ""; // Fail gracefully
        }
    }

    /**
     * POST /api/ai/chat
     * Send a message and get AI response
     */
    public function chat()
    {
        $userId = $this->getUserId();
        $input = $this->getInput();

        $message = trim($input['message'] ?? '');
        $conversationId = $input['conversation_id'] ?? null;
        $provider = $input['provider'] ?? null;

        // TRACE REQUEST: Log incoming request for debugging
        error_log("AI API Request: User [$userId] requesting chat. Provider: " . ($provider ?? 'default'));

        if (empty($message)) {
            $this->jsonResponse(['error' => 'Message is required'], 400);
        }

        // Check if AI is enabled
        if (!AIServiceFactory::isEnabled()) {
            $this->jsonResponse(['error' => 'AI features are not enabled'], 403);
        }

        // Check user limits
        $limitCheck = AiUserLimit::canMakeRequest($userId);
        if (!$limitCheck['allowed']) {
            $this->jsonResponse([
                'error' => 'Usage limit reached',
                'reason' => $limitCheck['reason'],
                'limits' => $limitCheck,
            ], 429);
        }

        try {
            // Get or create conversation
            if ($conversationId) {
                $conversation = AiConversation::findById($conversationId);
                if (!$conversation || !AiConversation::belongsToUser($conversationId, $userId)) {
                    $this->jsonResponse(['error' => 'Conversation not found'], 404);
                }
            } else {
                $conversationId = AiConversation::create($userId, [
                    'provider' => $provider ?? AIServiceFactory::getDefaultProvider(),
                ]);
                $conversation = AiConversation::findById($conversationId);
            }

            // Get preferred provider
            $preferredProvider = $provider ?? $conversation['provider'];

            // Save user message
            AiMessage::createUserMessage($conversationId, $message);

            // Get conversation history for context
            $history = AiMessage::getRecentForContext($conversationId, 20);

            // Build messages array with system prompt
            $messages = [];

            // Get tenant ID for context building
            $tenantId = TenantContext::getId();

            // Add system prompt with user context + smart context
            $systemPrompt = AIServiceFactory::getSystemPrompt();
            $userContext = $this->buildUserContext($userId);
            $smartContext = $this->fetchSmartContext($tenantId, $userId, $message);

            if ($systemPrompt) {
                // Combine all context layers
                $fullContext = $systemPrompt . $userContext . $smartContext;
                $messages[] = ['role' => 'system', 'content' => $fullContext];
            }

            // Add conversation history
            foreach ($history as $msg) {
                $messages[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content'],
                ];
            }

            // Call AI with automatic fallback on failure
            $response = AIServiceFactory::chatWithFallback($messages, [], $preferredProvider);

            // Log if fallback was used
            if (!empty($response['used_fallback'])) {
                error_log("AI chat used fallback provider: " . ($response['provider'] ?? 'unknown'));
            }

            // Save assistant response
            $assistantMessageId = AiMessage::createAssistantMessage($conversationId, $response['content'], [
                'tokens_used' => $response['tokens_used'] ?? 0,
                'model' => $response['model'] ?? null,
            ]);

            // Get actual provider used (may be fallback)
            $actualProvider = $response['provider'] ?? $preferredProvider;

            // Update conversation title if it's the first message
            $messageCount = AiMessage::countByConversationId($conversationId);
            if ($messageCount <= 2) {
                AiConversation::updateTitleFromContent($conversationId, $message);
                AiConversation::update($conversationId, [
                    'provider' => $actualProvider,
                    'model' => $response['model'] ?? null,
                ]);
            }

            // Log usage
            $cost = AiUsage::calculateCost(
                $actualProvider,
                $response['model'] ?? '',
                $response['tokens_input'] ?? 0,
                $response['tokens_output'] ?? 0
            );

            AiUsage::log($userId, $actualProvider, 'chat', [
                'tokens_input' => $response['tokens_input'] ?? 0,
                'tokens_output' => $response['tokens_output'] ?? 0,
                'cost_usd' => $cost,
            ]);

            // Increment user limit counter
            AiUserLimit::incrementUsage($userId);

            // Get updated limits
            $limits = AiUserLimit::canMakeRequest($userId);

            $this->jsonResponse([
                'success' => true,
                'conversation_id' => $conversationId,
                'message' => [
                    'id' => $assistantMessageId,
                    'role' => 'assistant',
                    'content' => $response['content'],
                ],
                'tokens_used' => $response['tokens_used'] ?? 0,
                'model' => $response['model'] ?? null,
                'provider' => $actualProvider,
                'used_fallback' => !empty($response['used_fallback']),
                'limits' => [
                    'daily_remaining' => $limits['daily_remaining'],
                    'monthly_remaining' => $limits['monthly_remaining'],
                ],
            ]);

        } catch (\Exception $e) {
            error_log("AI chat error: " . $e->getMessage());
            $this->jsonResponse(['error' => $this->getFriendlyErrorMessage($e)], 500);
        }
    }

    /**
     * Convert technical errors to user-friendly messages
     */
    private function getFriendlyErrorMessage(\Exception $e): string
    {
        $message = $e->getMessage();

        // Rate limit errors
        if (strpos($message, '429') !== false || stripos($message, 'quota') !== false || stripos($message, 'rate') !== false) {
            return "I'm getting a lot of requests right now. Please wait a moment and try again.";
        }

        // API key errors
        if (stripos($message, 'api key') !== false || stripos($message, 'unauthorized') !== false || strpos($message, '401') !== false) {
            return "There's a configuration issue with the AI service. Please contact an administrator.";
        }

        // Model not found
        if (stripos($message, 'not found') !== false && stripos($message, 'model') !== false) {
            return "The AI model is temporarily unavailable. Please try again later.";
        }

        // Network errors
        if (stripos($message, 'curl') !== false || stripos($message, 'connection') !== false || stripos($message, 'timeout') !== false) {
            return "I couldn't connect to the AI service. Please check your internet connection and try again.";
        }

        // Content filter
        if (stripos($message, 'safety') !== false || stripos($message, 'blocked') !== false || stripos($message, 'filter') !== false) {
            return "I couldn't process that request. Please try rephrasing your message.";
        }

        // Token/length errors
        if (stripos($message, 'token') !== false || stripos($message, 'length') !== false || stripos($message, 'too long') !== false) {
            return "Your message is too long. Please try a shorter message.";
        }

        // Server errors
        if (strpos($message, '500') !== false || strpos($message, '502') !== false || strpos($message, '503') !== false) {
            return "The AI service is temporarily down. Please try again in a few minutes.";
        }

        // Generic fallback
        return "Something went wrong. Please try again. If the problem persists, contact support.";
    }

    /**
     * POST /api/ai/chat/stream
     * Stream AI response using Server-Sent Events
     *
     * CRITICAL FIX: Properly handles errors by sending them as SSE instead of hanging
     */
    public function streamChat()
    {
        $userId = $this->getUserId();
        $input = $this->getInput();

        $message = trim($input['message'] ?? '');
        $conversationId = $input['conversation_id'] ?? null;
        $provider = $input['provider'] ?? null;

        // TRACE REQUEST: Log incoming stream request for debugging
        error_log("AI API Stream Request: User [$userId] requesting stream chat. Provider: " . ($provider ?? 'default'));

        if (empty($message)) {
            $this->jsonResponse(['error' => 'Message is required'], 400);
        }

        if (!AIServiceFactory::isEnabled()) {
            $this->jsonResponse(['error' => 'AI features are not enabled'], 403);
        }

        $limitCheck = AiUserLimit::canMakeRequest($userId);
        if (!$limitCheck['allowed']) {
            $this->jsonResponse(['error' => 'Usage limit reached'], 429);
        }

        // Set up SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        try {
            // Get or create conversation
            if (!$conversationId) {
                $conversationId = AiConversation::create($userId, [
                    'provider' => $provider ?? AIServiceFactory::getDefaultProvider(),
                ]);
            }

            // Get preferred provider
            $preferredProvider = $provider ?? AIServiceFactory::getDefaultProvider();
            $aiProvider = AIServiceFactory::getProvider($preferredProvider);

            // PRE-FLIGHT CHECK: Verify provider is configured BEFORE starting stream
            // This prevents hanging on invalid API keys
            if (!$aiProvider->isConfigured()) {
                error_log("Stream Error: Provider [$preferredProvider] not configured for user [$userId]");
                echo "data: " . json_encode(['error' => 'AI provider is not configured. Please configure API keys in Admin > AI Settings.']) . "\n\n";
                ob_flush();
                flush();
                exit;
            }

            // Save user message
            AiMessage::createUserMessage($conversationId, $message);

            // Build messages
            $history = AiMessage::getRecentForContext($conversationId, 20);
            $messages = [];

            // Get tenant ID for context building
            $tenantId = TenantContext::getId();

            $systemPrompt = AIServiceFactory::getSystemPrompt();
            $userContext = $this->buildUserContext($userId);
            $smartContext = $this->fetchSmartContext($tenantId, $userId, $message);

            if ($systemPrompt) {
                // Combine all context layers
                $fullContext = $systemPrompt . $userContext . $smartContext;
                $messages[] = ['role' => 'system', 'content' => $fullContext];
            }

            foreach ($history as $msg) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }

            // Stream response - wrapped in inner try/catch for granular error handling
            $fullContent = '';
            $aiProvider->streamChat($messages, function ($chunk) use (&$fullContent) {
                $content = $chunk['content'] ?? '';
                $fullContent .= $content;

                echo "data: " . json_encode(['content' => $content, 'done' => $chunk['done'] ?? false]) . "\n\n";
                ob_flush();
                flush();
            });

            // Save the complete response
            AiMessage::createAssistantMessage($conversationId, $fullContent);
            AiUserLimit::incrementUsage($userId);

            // Send done event
            echo "data: " . json_encode(['done' => true, 'conversation_id' => $conversationId]) . "\n\n";
            ob_flush();
            flush();

        } catch (\Exception $e) {
            // CRITICAL FIX: Send error as SSE data so frontend can display it
            // Without this, the stream hangs and the user sees nothing
            error_log("Stream Error for user [$userId]: " . $e->getMessage());
            echo "data: " . json_encode(['error' => $this->getFriendlyErrorMessage($e)]) . "\n\n";
            ob_flush();
            flush();
        }

        exit;
    }

    /**
     * GET /api/ai/conversations
     * List user's conversations
     */
    public function listConversations()
    {
        $userId = $this->getUserId();
        $limit = min((int) ($_GET['limit'] ?? 50), 100);
        $offset = (int) ($_GET['offset'] ?? 0);

        $conversations = AiConversation::getByUserId($userId, $limit, $offset);
        $total = AiConversation::countByUserId($userId);

        $this->jsonResponse([
            'success' => true,
            'data' => $conversations,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * GET /api/ai/conversations/:id
     * Get a conversation with messages
     */
    public function getConversation($id)
    {
        $userId = $this->getUserId();
        $id = (int) $id;

        if (!AiConversation::belongsToUser($id, $userId)) {
            $this->jsonResponse(['error' => 'Conversation not found'], 404);
        }

        $conversation = AiConversation::getWithMessages($id);

        $this->jsonResponse([
            'success' => true,
            'data' => $conversation,
        ]);
    }

    /**
     * POST /api/ai/conversations
     * Create a new conversation
     */
    public function createConversation()
    {
        $userId = $this->getUserId();
        $input = $this->getInput();

        $conversationId = AiConversation::create($userId, [
            'title' => $input['title'] ?? 'New Chat',
            'provider' => $input['provider'] ?? null,
            'context_type' => $input['context_type'] ?? 'general',
            'context_id' => $input['context_id'] ?? null,
        ]);

        $this->jsonResponse([
            'success' => true,
            'conversation_id' => $conversationId,
        ]);
    }

    /**
     * DELETE /api/ai/conversations/:id
     * Delete a conversation
     */
    public function deleteConversation($id)
    {
        $userId = $this->getUserId();
        $id = (int) $id;

        if (!AiConversation::belongsToUser($id, $userId)) {
            $this->jsonResponse(['error' => 'Conversation not found'], 404);
        }

        AiConversation::delete($id);

        $this->jsonResponse(['success' => true]);
    }

    /**
     * GET /api/ai/providers
     * Get available AI providers
     */
    public function getProviders()
    {
        $this->getUserId(); // Ensure authenticated

        $providers = AIServiceFactory::getAvailableProviders();
        $defaultProvider = AIServiceFactory::getDefaultProvider();

        $this->jsonResponse([
            'success' => true,
            'providers' => $providers,
            'default' => $defaultProvider,
            'enabled' => AIServiceFactory::isEnabled(),
        ]);
    }

    /**
     * GET /api/ai/limits
     * Get user's current usage limits
     */
    public function getLimits()
    {
        $userId = $this->getUserId();
        $limits = AiUserLimit::canMakeRequest($userId);

        $this->jsonResponse([
            'success' => true,
            'limits' => $limits,
        ]);
    }

    /**
     * POST /api/ai/test-provider
     * Test an AI provider connection (admin only)
     */
    public function testProvider()
    {
        $userId = $this->getUserId();

        // Check if admin (you may want to add proper admin check)
        $input = $this->getInput();
        $providerId = $input['provider'] ?? 'gemini';

        try {
            $provider = AIServiceFactory::getProvider($providerId);
            $result = $provider->testConnection();

            $this->jsonResponse([
                'success' => $result['success'],
                'message' => $result['message'],
                'latency_ms' => $result['latency_ms'],
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * POST /api/ai/generate/listing
     * Generate a listing description with rich context
     */
    public function generateListing()
    {
        $userId = $this->getUserId();

        if (!AIServiceFactory::isFeatureEnabled('content_generation')) {
            $this->jsonResponse(['error' => 'Content generation is not enabled'], 403);
        }

        $limitCheck = AiUserLimit::canMakeRequest($userId);
        if (!$limitCheck['allowed']) {
            $this->jsonResponse(['error' => 'Usage limit reached'], 429);
        }

        $input = $this->getInput();
        $title = trim($input['title'] ?? '');
        $type = $input['type'] ?? 'offer';
        $context = $input['context'] ?? [];

        if (empty($title)) {
            $this->jsonResponse(['error' => 'Title is required'], 400);
        }

        try {
            $aiProvider = AIServiceFactory::getProvider();

            // Build a rich, context-aware prompt
            $prompt = $this->buildListingPrompt($userId, $title, $type, $context);

            // Use chat for better results with system context
            $messages = [
                [
                    'role' => 'system',
                    'content' => $this->getContentGenerationSystemPrompt()
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ];

            $response = $aiProvider->chat($messages, ['temperature' => 0.7, 'max_tokens' => 800]);

            AiUserLimit::incrementUsage($userId);
            AiUsage::log($userId, $aiProvider->getId(), 'generate_listing', [
                'tokens_input' => $response['tokens_input'] ?? 0,
                'tokens_output' => $response['tokens_output'] ?? 0,
            ]);

            $this->jsonResponse([
                'success' => true,
                'content' => trim($response['content']),
            ]);

        } catch (\Exception $e) {
            error_log("AI generateListing error: " . $e->getMessage());
            $this->jsonResponse(['error' => $this->getFriendlyErrorMessage($e)], 500);
        }
    }

    /**
     * Build a rich prompt for listing generation
     */
    private function buildListingPrompt(int $userId, string $title, string $type, array $context): string
    {
        // Debug log the received context
        error_log("AI buildListingPrompt - Title: {$title}, Type: {$type}, Context: " . json_encode($context));

        $isOffer = ($type === 'offer');
        $typeLabel = $isOffer ? 'Offer' : 'Request';

        // Check if user provided a prompt (new flow) vs just a title (old flow)
        $userPrompt = $context['user_prompt'] ?? '';
        $hasUserPrompt = !empty($userPrompt);

        // Start with a clear task description
        $prompt = "# TASK: Write a Timebank Listing Description\n\n";
        $prompt .= "You must write a compelling description for a community timebank listing. ";
        $prompt .= "Use ALL the details provided below to create a specific, personalized description.\n\n";

        // Listing details section - make it prominent
        $prompt .= "## MANDATORY INFORMATION TO INCORPORATE\n\n";
        $prompt .= "**Listing Type:** {$typeLabel}\n";
        $prompt .= "- This is " . ($isOffer ? "an OFFER where I'm sharing my skills/services with others" : "a REQUEST where I need help from the community") . "\n\n";

        // User's prompt is the PRIMARY driver now
        if ($hasUserPrompt) {
            $prompt .= "**USER'S REQUEST (THIS IS THE MAIN INPUT - FOLLOW IT CLOSELY):**\n";
            $prompt .= "---\n{$userPrompt}\n---\n";
            $prompt .= "Write a listing description based on what the user described above. This is what they want to offer or request.\n\n";

            // Title is optional context
            if (!empty($title)) {
                $prompt .= "**Title (optional context):** \"{$title}\"\n\n";
            }
        } else {
            // Fallback to old behavior if no user prompt
            $prompt .= "**Title:** \"{$title}\"\n";
            $prompt .= "- The title tells you what this listing is about. Use it as the core topic.\n\n";
        }

        // Category is critical - emphasize it
        if (!empty($context['category'])) {
            $prompt .= "**Category:** {$context['category']}\n";
            $prompt .= "- This categorizes the type of service. Incorporate language appropriate to this category.\n\n";
        }

        // Listing type from context (offer/request)
        if (!empty($context['listing_type'])) {
            $type = $context['listing_type']; // Override with context value
            $isOffer = ($type === 'offer');
        }

        // Service attributes - these are key differentiators
        if (!empty($context['attributes']) && is_array($context['attributes'])) {
            $prompt .= "**Service Features Selected:** " . implode(', ', $context['attributes']) . "\n";
            $prompt .= "- These features MUST be mentioned or implied in the description. They describe the service's characteristics.\n\n";
        }

        // SDG goals - add social impact angle
        if (!empty($context['sdg_goals']) && is_array($context['sdg_goals'])) {
            $prompt .= "**Social Impact Goals:** " . implode(', ', $context['sdg_goals']) . "\n";
            $prompt .= "- Subtly weave in how this service contributes to these goals. Don't list them explicitly.\n\n";
        }

        // User profile context
        $userContext = $this->getUserProfileContext($userId);
        if ($userContext) {
            $prompt .= "## ABOUT THE PERSON POSTING\n{$userContext}\n";
            $prompt .= "Use this background to add authenticity and personalization.\n\n";
        }

        // Handle improvement mode
        if (!empty($context['existing_description'])) {
            $prompt .= "## EXISTING DRAFT TO IMPROVE\n";
            $prompt .= "The user wrote this draft:\n";
            $prompt .= "---\n{$context['existing_description']}\n---\n\n";
            $prompt .= "Enhance this while keeping their voice and intent. Make it more engaging, specific, and complete.\n\n";
        }

        // Writing instructions
        $prompt .= "## OUTPUT REQUIREMENTS\n\n";
        $prompt .= "Write 2-3 paragraphs (100-200 words) that:\n";

        if ($isOffer) {
            $prompt .= "1. Opens with what you're offering and why you enjoy it\n";
            $prompt .= "2. Describes your approach/experience and who benefits most\n";
            $prompt .= "3. Mentions practical details (flexibility, what to bring/expect)\n";
            $prompt .= "4. Ends with a warm invitation to connect\n";
        } else {
            $prompt .= "1. Opens with what help you need and why it matters\n";
            $prompt .= "2. Describes the ideal helper and what you're hoping for\n";
            $prompt .= "3. Mentions timeline, flexibility, or other relevant details\n";
            $prompt .= "4. Ends warmly, expressing appreciation for community support\n";
        }

        $prompt .= "\n**Writing Style:**\n";
        $prompt .= "- First person, warm, conversational\n";
        $prompt .= "- Specific and detailed (NOT generic filler text)\n";
        $prompt .= "- Authentic human voice, avoid corporate speak\n";
        $prompt .= "- Reference the specific details provided above\n";

        $prompt .= "\n**OUTPUT FORMAT:** Return ONLY the description paragraphs. No title, headers, bullet points, or other formatting.";

        return $prompt;
    }

    /**
     * Get user profile context for personalized generation
     */
    private function getUserProfileContext(int $userId): string
    {
        $context = '';

        try {
            $db = Database::getConnection();

            // Get user info
            $stmt = $db->prepare("SELECT name, bio, location FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($user) {
                if (!empty($user['name'])) {
                    $context .= "- Name: {$user['name']}\n";
                }
                if (!empty($user['location'])) {
                    $context .= "- Location: {$user['location']}\n";
                }
                if (!empty($user['bio'])) {
                    // Truncate long bios
                    $bio = strlen($user['bio']) > 200 ? substr($user['bio'], 0, 200) . '...' : $user['bio'];
                    $context .= "- Bio: {$bio}\n";
                }
            }

            // Get existing listings to understand their style
            $tenantId = TenantContext::getId();
            $stmt = $db->prepare("SELECT title, type FROM listings WHERE user_id = ? AND tenant_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 3");
            $stmt->execute([$userId, $tenantId]);
            $listings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($listings)) {
                $listingTitles = array_map(fn($l) => "[{$l['type']}] {$l['title']}", $listings);
                $context .= "- Other listings: " . implode(', ', $listingTitles) . "\n";
            }

        } catch (\Exception $e) {
            // Silently fail - context is optional
        }

        return $context;
    }

    /**
     * Get system prompt for content generation
     */
    private function getContentGenerationSystemPrompt(): string
    {
        return <<<EOT
You are an expert copywriter for NEXUS TimeBank, a community platform where neighbors exchange skills and services using time credits (1 hour = 1 credit).

## YOUR MISSION
Write authentic, compelling listing descriptions that help real community members connect. Every description you write should feel like it was written by a thoughtful human who genuinely wants to help their neighbors.

## CRITICAL RULES
1. **USE ALL PROVIDED DETAILS** - The user has provided specific information (category, features, goals). You MUST incorporate these into your writing. Never ignore provided context.

2. **BE SPECIFIC, NOT GENERIC** - Instead of "I have experience," say "I've been doing this for 5 years" or reference specific aspects. Generic text fails.

3. **WRITE AS THE PERSON** - Use first person ("I", "my"). Sound like a real neighbor, not a marketing department.

4. **MATCH CATEGORY TONE** - Professional services need professional-but-friendly tone. Creative/casual services can be more playful.

5. **NO FORMATTING** - Return only plain paragraph text. No headers, bullets, asterisks, or markdown.

## WHAT MAKES GREAT LISTINGS
- Opens with personality or a hook
- Mentions specific skills/experience relevant to the service
- Explains who would benefit and why
- Includes practical details (flexibility, what to expect)
- Ends with a warm, inviting call-to-action

Remember: You're helping neighbors connect. Make it real, make it warm, make it specific.
EOT;
    }

    /**
     * POST /api/ai/generate/event
     * Generate an event description with rich context
     */
    public function generateEvent()
    {
        $userId = $this->getUserId();

        if (!AIServiceFactory::isFeatureEnabled('content_generation')) {
            $this->jsonResponse(['error' => 'Content generation is not enabled'], 403);
        }

        $limitCheck = AiUserLimit::canMakeRequest($userId);
        if (!$limitCheck['allowed']) {
            $this->jsonResponse(['error' => 'Usage limit reached'], 429);
        }

        $input = $this->getInput();
        $title = trim($input['title'] ?? '');
        $context = $input['context'] ?? [];

        if (empty($title)) {
            $this->jsonResponse(['error' => 'Title is required'], 400);
        }

        try {
            $aiProvider = AIServiceFactory::getProvider();

            // Build a rich, context-aware prompt
            $prompt = $this->buildEventPrompt($userId, $title, $context);

            // Use chat for better results with system context
            $messages = [
                [
                    'role' => 'system',
                    'content' => $this->getEventGenerationSystemPrompt()
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ];

            $response = $aiProvider->chat($messages, ['temperature' => 0.7, 'max_tokens' => 800]);

            AiUserLimit::incrementUsage($userId);
            AiUsage::log($userId, $aiProvider->getId(), 'generate_event', [
                'tokens_input' => $response['tokens_input'] ?? 0,
                'tokens_output' => $response['tokens_output'] ?? 0,
            ]);

            $this->jsonResponse([
                'success' => true,
                'content' => trim($response['content']),
            ]);

        } catch (\Exception $e) {
            error_log("AI generateEvent error: " . $e->getMessage());
            $this->jsonResponse(['error' => $this->getFriendlyErrorMessage($e)], 500);
        }
    }

    /**
     * Build a rich prompt for event generation
     */
    private function buildEventPrompt(int $userId, string $title, array $context): string
    {
        // Debug log the received context
        error_log("AI buildEventPrompt - Title: {$title}, Context: " . json_encode($context));

        $prompt = "# TASK: Write a Community Event Description\n\n";
        $prompt .= "Create an engaging event description using ALL the details provided below. ";
        $prompt .= "The description should make people excited to attend.\n\n";

        // Event details section
        $prompt .= "## MANDATORY INFORMATION TO INCORPORATE\n\n";
        $prompt .= "**Event Title:** \"{$title}\"\n";
        $prompt .= "- This is the event's name. Use it as the core theme.\n\n";

        // Add category
        if (!empty($context['category'])) {
            $prompt .= "**Category:** {$context['category']}\n";
            $prompt .= "- This tells you what type of event it is. Match the tone and vocabulary.\n\n";
        }

        // Add location - very important for events
        if (!empty($context['location'])) {
            $prompt .= "**Location:** {$context['location']}\n";
            $prompt .= "- Mention or reference the location naturally in the description.\n\n";
        }

        // Add date/time information
        if (!empty($context['start_date'])) {
            $dateStr = $context['start_date'];
            if (!empty($context['start_time'])) {
                $dateStr .= ' at ' . $context['start_time'];
            }

            $prompt .= "**When:** {$dateStr}";

            if (!empty($context['end_time'])) {
                $prompt .= " until {$context['end_time']}";
            }
            $prompt .= "\n";

            // Calculate and mention duration if applicable
            if (!empty($context['start_time']) && !empty($context['end_time']) &&
                (empty($context['end_date']) || $context['end_date'] === $context['start_date'])) {
                try {
                    $start = new \DateTime($context['start_time']);
                    $end = new \DateTime($context['end_time']);
                    $diff = $start->diff($end);
                    $hours = $diff->h + ($diff->i / 60);
                    if ($hours > 0) {
                        $prompt .= "**Duration:** approximately " . round($hours, 1) . " hours\n";
                    }
                } catch (\Exception $e) {
                    // Ignore date parsing errors
                }
            }
            $prompt .= "- Reference the timing naturally (e.g., 'Join us this Saturday afternoon...')\n\n";
        }

        // Add host group/hub
        if (!empty($context['group_name'])) {
            $prompt .= "**Hosted by:** {$context['group_name']}\n";
            $prompt .= "- This is the community hub organizing the event. Mention it.\n\n";
        }

        // Add SDG goals for social impact context
        if (!empty($context['sdg_goals']) && is_array($context['sdg_goals'])) {
            $prompt .= "**Social Impact:** " . implode(', ', $context['sdg_goals']) . "\n";
            $prompt .= "- Subtly weave in how this event contributes to community well-being.\n\n";
        }

        // Get host context for personalization
        $userContext = $this->getUserProfileContext($userId);
        if ($userContext) {
            $prompt .= "## ABOUT THE HOST\n{$userContext}\n";
            $prompt .= "Use this to add a personal touch to the invitation.\n\n";
        }

        // Handle improvement mode
        if (!empty($context['existing_description'])) {
            $prompt .= "## EXISTING DRAFT TO IMPROVE\n";
            $prompt .= "The host wrote this draft:\n";
            $prompt .= "---\n{$context['existing_description']}\n---\n\n";
            $prompt .= "Enhance this while keeping their voice. Make it more engaging and complete.\n\n";
        }

        // Writing instructions
        $prompt .= "## OUTPUT REQUIREMENTS\n\n";
        $prompt .= "Write 2-3 paragraphs (100-200 words) that:\n";
        $prompt .= "1. Opens with an engaging hook about what makes this event special\n";
        $prompt .= "2. Describes what attendees will experience, learn, or do\n";
        $prompt .= "3. Mentions who should come (skill level, interests, everyone welcome?)\n";
        $prompt .= "4. Ends with a warm invitation to join\n";

        $prompt .= "\n**Writing Style:**\n";
        $prompt .= "- Enthusiastic but genuine (not salesy or over-the-top)\n";
        $prompt .= "- Specific details from the information above\n";
        $prompt .= "- Community-focused, welcoming tone\n";
        $prompt .= "- First person when appropriate ('We're excited to host...')\n";

        $prompt .= "\n**OUTPUT FORMAT:** Return ONLY the description paragraphs. No title, headers, bullet points, or other formatting.";

        return $prompt;
    }

    /**
     * Get system prompt for event generation
     */
    private function getEventGenerationSystemPrompt(): string
    {
        return <<<EOT
You are a community events coordinator for NEXUS TimeBank, a platform where neighbors exchange skills and build community.

## YOUR MISSION
Write event descriptions that make people genuinely excited to attend. Every description should feel like a warm, personal invitation from a neighbor.

## CRITICAL RULES
1. **USE ALL PROVIDED DETAILS** - Location, time, category, hosting group - weave ALL of these into your description naturally. Don't ignore any provided information.

2. **CREATE VIVID PICTURES** - Help readers imagine themselves at the event. What will they see, do, learn, experience?

3. **BE SPECIFIC** - "Learn 3 traditional bread recipes" beats "Learn to bake bread". Details create excitement.

4. **WELCOMING TONE** - Events are for everyone. Make newcomers feel they belong.

5. **NO FORMATTING** - Return only plain paragraph text. No headers, bullets, asterisks, or markdown.

## WHAT MAKES GREAT EVENT DESCRIPTIONS
- Opens with an engaging hook or the event's unique appeal
- Clearly explains what attendees will experience
- Mentions who the event is perfect for
- References location and timing naturally
- Ends with an inviting call-to-join

Remember: These are real community gatherings. Make each one feel special and worth attending.
EOT;
    }

    /**
     * POST /api/ai/generate/message
     * Generate a message reply suggestion
     */
    public function generateMessage()
    {
        $userId = $this->getUserId();

        if (!AIServiceFactory::isFeatureEnabled('content_generation')) {
            $this->jsonResponse(['error' => 'Content generation is not enabled'], 403);
        }

        $limitCheck = AiUserLimit::canMakeRequest($userId);
        if (!$limitCheck['allowed']) {
            $this->jsonResponse(['error' => 'Usage limit reached'], 429);
        }

        $input = $this->getInput();
        $originalMessage = trim($input['original_message'] ?? '');
        $context = $input['context'] ?? [];
        $tone = $input['tone'] ?? 'friendly'; // friendly, professional, casual

        if (empty($originalMessage)) {
            $this->jsonResponse(['error' => 'Original message is required'], 400);
        }

        try {
            $aiProvider = AIServiceFactory::getProvider();

            $prompt = "Suggest a reply to this message on a community timebank platform.\n\n";
            $prompt .= "## ORIGINAL MESSAGE\n{$originalMessage}\n\n";

            if (!empty($context['listing_title'])) {
                $prompt .= "## CONTEXT\nThis is about the listing: \"{$context['listing_title']}\"\n\n";
            }

            $prompt .= "## INSTRUCTIONS\n";
            $prompt .= "Write a {$tone} reply (2-4 sentences) that:\n";
            $prompt .= "- Responds appropriately to what was said\n";
            $prompt .= "- Moves the conversation forward\n";
            $prompt .= "- Sounds natural and human\n";
            $prompt .= "\n**Return ONLY the reply text, no labels or formatting.**";

            $messages = [
                [
                    'role' => 'system',
                    'content' => 'You help timebank community members communicate effectively. Write natural, friendly messages that sound like they come from a real person, not a bot.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ];

            $response = $aiProvider->chat($messages, ['temperature' => 0.8, 'max_tokens' => 300]);

            AiUserLimit::incrementUsage($userId);
            AiUsage::log($userId, $aiProvider->getId(), 'generate_message', [
                'tokens_input' => $response['tokens_input'] ?? 0,
                'tokens_output' => $response['tokens_output'] ?? 0,
            ]);

            $this->jsonResponse([
                'success' => true,
                'content' => trim($response['content']),
            ]);

        } catch (\Exception $e) {
            error_log("AI generateMessage error: " . $e->getMessage());
            $this->jsonResponse(['error' => $this->getFriendlyErrorMessage($e)], 500);
        }
    }

    /**
     * POST /api/ai/generate/bio
     * Generate or enhance a user bio
     */
    public function generateBio()
    {
        $userId = $this->getUserId();

        if (!AIServiceFactory::isFeatureEnabled('content_generation')) {
            $this->jsonResponse(['error' => 'Content generation is not enabled'], 403);
        }

        $limitCheck = AiUserLimit::canMakeRequest($userId);
        if (!$limitCheck['allowed']) {
            $this->jsonResponse(['error' => 'Usage limit reached'], 429);
        }

        $input = $this->getInput();
        $existingBio = trim($input['existing_bio'] ?? '');
        $interests = $input['interests'] ?? [];
        $skills = $input['skills'] ?? [];

        try {
            $aiProvider = AIServiceFactory::getProvider();
            $db = Database::getConnection();

            // Get user's listings for context
            $tenantId = TenantContext::getId();
            $stmt = $db->prepare("SELECT title, type FROM listings WHERE user_id = ? AND tenant_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 5");
            $stmt->execute([$userId, $tenantId]);
            $listings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $prompt = "Write a friendly bio for a timebank community member.\n\n";

            if (!empty($existingBio)) {
                $prompt .= "## CURRENT BIO TO IMPROVE\n{$existingBio}\n\n";
            }

            if (!empty($listings)) {
                $prompt .= "## THEIR LISTINGS\n";
                foreach ($listings as $listing) {
                    $prompt .= "- [{$listing['type']}] {$listing['title']}\n";
                }
                $prompt .= "\n";
            }

            if (!empty($interests)) {
                $prompt .= "## INTERESTS\n" . implode(', ', $interests) . "\n\n";
            }

            if (!empty($skills)) {
                $prompt .= "## SKILLS\n" . implode(', ', $skills) . "\n\n";
            }

            $prompt .= "## INSTRUCTIONS\n";
            $prompt .= "Write a warm, engaging bio (2-3 sentences, under 150 words) that:\n";
            $prompt .= "- Introduces them as a community member\n";
            $prompt .= "- Highlights what they can offer or are interested in\n";
            $prompt .= "- Sounds friendly and approachable\n";
            $prompt .= "\n**Return ONLY the bio text.**";

            $messages = [
                [
                    'role' => 'system',
                    'content' => 'You write authentic, friendly bios for community members. Keep them genuine and avoid clichés.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ];

            $response = $aiProvider->chat($messages, ['temperature' => 0.7, 'max_tokens' => 250]);

            AiUserLimit::incrementUsage($userId);
            AiUsage::log($userId, $aiProvider->getId(), 'generate_bio', [
                'tokens_input' => $response['tokens_input'] ?? 0,
                'tokens_output' => $response['tokens_output'] ?? 0,
            ]);

            $this->jsonResponse([
                'success' => true,
                'content' => trim($response['content']),
            ]);

        } catch (\Exception $e) {
            error_log("AI generateBio error: " . $e->getMessage());
            $this->jsonResponse(['error' => $this->getFriendlyErrorMessage($e)], 500);
        }
    }

    /**
     * POST /api/ai/generate/newsletter
     * Generate newsletter content (subject, preview text, body)
     */
    public function generateNewsletter()
    {
        $userId = $this->getUserId();

        if (!AIServiceFactory::isFeatureEnabled('content_generation')) {
            $this->jsonResponse(['error' => 'Content generation is not enabled'], 403);
        }

        $limitCheck = AiUserLimit::canMakeRequest($userId);
        if (!$limitCheck['allowed']) {
            $this->jsonResponse(['error' => 'Usage limit reached'], 429);
        }

        $input = $this->getInput();
        $type = $input['type'] ?? 'subject'; // subject, preview, content, full
        $context = $input['context'] ?? [];

        try {
            $aiProvider = AIServiceFactory::getProvider();
            $prompt = $this->buildNewsletterPrompt($type, $context);

            // Debug log the prompt for newsletter content generation
            if ($type === 'content') {
                error_log("AI Newsletter Prompt (first 2000 chars): " . substr($prompt, 0, 2000));
            }

            $messages = [
                ['role' => 'system', 'content' => $this->getNewsletterSystemPrompt()],
                ['role' => 'user', 'content' => $prompt]
            ];

            $response = $aiProvider->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => $type === 'content' ? 2000 : 500
            ]);

            AiUserLimit::incrementUsage($userId);
            AiUsage::log($userId, $aiProvider->getId(), 'generate_newsletter', [
                'tokens_input' => $response['tokens_input'] ?? 0,
                'tokens_output' => $response['tokens_output'] ?? 0,
            ]);

            $this->jsonResponse([
                'success' => true,
                'content' => trim($response['content']),
                'type' => $type,
            ]);

        } catch (\Exception $e) {
            error_log("AI generateNewsletter error: " . $e->getMessage());
            $this->jsonResponse(['error' => $this->getFriendlyErrorMessage($e)], 500);
        }
    }

    /**
     * Build newsletter generation prompt with real platform data
     */
    private function buildNewsletterPrompt(string $type, array $context): string
    {
        $topic = $context['topic'] ?? '';
        $audience = $context['audience'] ?? 'community members';
        $tone = $context['tone'] ?? 'friendly and engaging';
        $existingSubject = $context['subject'] ?? '';
        $template = $context['template'] ?? '';
        $existingContent = $context['existing_content'] ?? ''; // User's draft/prompt in content field
        $userPrompt = $context['user_prompt'] ?? ''; // Explicit user instructions

        // Get real platform data for content generation
        $platformData = $this->getNewsletterPlatformData();
        $platformName = $platformData['platform_name'];

        $prompt = "# TASK: Generate Newsletter ";

        switch ($type) {
            case 'subject':
                $prompt .= "Subject Line for {$platformName}\n\n";

                // Add platform context
                $prompt .= $this->formatPlatformDataForPrompt($platformData);

                $prompt .= "## REQUIREMENTS\n";
                $prompt .= "Generate 3 compelling email subject lines for this community newsletter.\n\n";

                if ($userPrompt) {
                    $prompt .= "**Admin's Instructions:** {$userPrompt}\n\n";
                }
                if ($topic) {
                    $prompt .= "**Topic/Theme:** {$topic}\n";
                }
                $prompt .= "**Target Audience:** {$audience}\n";
                $prompt .= "**Tone:** {$tone}\n\n";

                $prompt .= "## OUTPUT FORMAT\n";
                $prompt .= "Return exactly 3 subject lines, one per line, numbered 1-3.\n";
                $prompt .= "Each should be under 60 characters for mobile compatibility.\n";
                $prompt .= "Reference REAL data from above (actual events, listings, stats).\n";
                $prompt .= "Make them specific to this community, not generic.\n";
                break;

            case 'preview':
                $prompt .= "Preview Text for {$platformName}\n\n";
                $prompt .= "## REQUIREMENTS\n";
                $prompt .= "Generate preview text that appears after the subject line in email clients.\n\n";
                if ($existingSubject) {
                    $prompt .= "**Subject Line:** {$existingSubject}\n";
                }
                if ($topic) {
                    $prompt .= "**Topic:** {$topic}\n";
                }
                $prompt .= "\n## OUTPUT FORMAT\n";
                $prompt .= "Return a single preview text, 40-90 characters.\n";
                $prompt .= "It should complement the subject line and encourage opening.\n";
                break;

            case 'content':
                $prompt .= "Body Content for {$platformName}\n\n";

                // Add real platform data
                $prompt .= $this->formatPlatformDataForPrompt($platformData);

                $prompt .= "## ⚠️ ABSOLUTE RULES - VIOLATION WILL FAIL ⚠️\n\n";
                $prompt .= "**STOP! READ THIS CAREFULLY BEFORE WRITING:**\n\n";
                $prompt .= "1. **NEVER INVENT NAMES** - Do NOT create fake names like 'Sarah', 'Mike', 'Jennifer'. If you need to mention a member, use ONLY the real names from the data above.\n";
                $prompt .= "2. **NEVER INVENT LISTINGS** - Do NOT make up services like 'sourdough baking' or 'computer troubleshooting' unless they appear in the REAL data above.\n";
                $prompt .= "3. **NEVER INVENT EVENTS** - Do NOT create fake events like 'Monthly Meetup' or 'Skills Workshop'. Use ONLY the real events listed above.\n";
                $prompt .= "4. **NEVER INVENT STATISTICS** - Do NOT say '3 people learned' or '5 households helped'. Use ONLY the real stats provided.\n";
                $prompt .= "5. **IF NO DATA EXISTS** - Write a general newsletter encouraging people to post listings and join the community. Do NOT fill it with made-up content.\n\n";
                $prompt .= "**WHAT TO DO INSTEAD:**\n";
                $prompt .= "- If real offers exist above, feature those exact titles and member names\n";
                $prompt .= "- If real events exist above, promote those exact events with real dates\n";
                $prompt .= "- If no data, write about the benefits of timebanking and encourage participation\n";
                $prompt .= "- Keep it honest and authentic - empty community = encourage first posts\n\n";

                // Check if user provided content as a prompt/framework
                if (!empty($existingContent) && strlen(trim($existingContent)) > 20) {
                    $prompt .= "## USER'S CONTENT FRAMEWORK (FOLLOW THIS CLOSELY)\n";
                    $prompt .= "The admin has written the following as guidance for what they want:\n";
                    $prompt .= "---\n{$existingContent}\n---\n\n";
                    $prompt .= "Expand and enhance this into a polished newsletter while keeping their intent and structure.\n";
                    $prompt .= "If they mention specific topics, focus on those using the real data above.\n\n";
                }

                if ($userPrompt) {
                    $prompt .= "## ADMIN'S SPECIFIC INSTRUCTIONS\n";
                    $prompt .= "{$userPrompt}\n\n";
                }

                $prompt .= "## NEWSLETTER DETAILS\n";
                if ($existingSubject) {
                    $prompt .= "**Subject:** {$existingSubject}\n";
                }
                if ($topic) {
                    $prompt .= "**Topic/Theme:** {$topic}\n";
                }
                $prompt .= "**Target Audience:** {$audience}\n";
                $prompt .= "**Tone:** {$tone}\n";

                // Template-specific guidance
                if ($template) {
                    $prompt .= "\n## TEMPLATE TYPE: " . strtoupper($template) . "\n";
                    switch ($template) {
                        case 'weekly':
                        case 'weekly-digest':
                            $prompt .= "Focus on: This week's highlights, new listings, upcoming events, member spotlight\n";
                            break;
                        case 'monthly':
                        case 'monthly-digest':
                            $prompt .= "Focus on: Monthly stats, community achievements, featured members, upcoming events\n";
                            break;
                        case 'event':
                        case 'event-announcement':
                            $prompt .= "Focus on: Featured upcoming event(s), why to attend, how to RSVP\n";
                            break;
                        case 'welcome':
                            $prompt .= "Focus on: Welcoming new members, how to get started, first steps\n";
                            break;
                        case 'announcement':
                            $prompt .= "Focus on: Important community news or updates\n";
                            break;
                        default:
                            $prompt .= "General community update newsletter\n";
                    }
                }

                $prompt .= "\n## OUTPUT FORMAT\n";
                $prompt .= "Write the newsletter body in clean HTML format.\n";
                $prompt .= "Structure:\n";
                $prompt .= "- Warm greeting using {{first_name}}\n";
                $prompt .= "- 2-3 content sections with <h2> headings\n";
                $prompt .= "- Feature REAL listings/events from the data above\n";
                $prompt .= "- Clear call-to-action (browse listings, attend event, etc.)\n";
                $prompt .= "- Friendly sign-off\n\n";
                $prompt .= "Use semantic HTML: h2, h3, p, ul, li, strong, a tags.\n";
                $prompt .= "Keep it scannable: short paragraphs, bullet points for lists.\n";
                $prompt .= "Length: 300-500 words.\n";
                $prompt .= "Personalization: Use {{first_name}} in greeting.\n";
                $prompt .= "\n**IMPORTANT:** Output ONLY the HTML content, no explanations or markdown.\n";
                break;

            case 'subject_ab':
                $prompt .= "A/B Test Subject Line Variant\n\n";
                $prompt .= "## REQUIREMENTS\n";
                $prompt .= "Create an alternative subject line for A/B testing.\n\n";
                $prompt .= "**Original Subject (A):** {$existingSubject}\n";
                if ($topic) {
                    $prompt .= "**Topic:** {$topic}\n";
                }
                $prompt .= "\n## OUTPUT FORMAT\n";
                $prompt .= "Return a single alternative subject line that:\n";
                $prompt .= "- Takes a different angle or approach\n";
                $prompt .= "- Has similar length (under 60 chars)\n";
                $prompt .= "- Tests a different emotional appeal or hook\n";
                break;

            default:
                $prompt .= "Content\n\n";
                $prompt .= "Generate appropriate newsletter content based on context.\n";
        }

        return $prompt;
    }

    /**
     * Get system prompt for newsletter generation
     */
    private function getNewsletterSystemPrompt(): string
    {
        $tenant = TenantContext::get();
        $platformName = $tenant['name'] ?? 'NEXUS TimeBank';

        return <<<EOT
You are a newsletter writer for {$platformName}. You write ONLY based on real data provided to you.

## CRITICAL: DO NOT HALLUCINATE

You have a serious problem with making up fake content. STOP DOING THIS.

❌ NEVER DO THIS:
- Invent member names (no "Sarah taught sourdough", no "Mike helped with computers", no "Meet Jennifer")
- Invent statistics (no "3 people learned", no "5 households helped", no "8 new volunteers")
- Invent events (no "Monthly Meetup at Central Park", no "Skills Workshop")
- Invent testimonials or quotes from fake people
- Fill empty sections with made-up examples

✅ INSTEAD DO THIS:
- Use ONLY the real listings, events, and member names provided in the prompt
- If the data shows 0 events, do NOT mention any events
- If no member names are provided, do NOT mention any members by name
- If data is sparse, write a shorter newsletter focused on general encouragement
- It's OK to have a simple newsletter that says "post your first listing!" if there's no activity

## ABOUT {$platformName}
A timebanking platform where neighbors exchange services using time credits (1 hour = 1 credit).

## OUTPUT FORMAT
- Clean HTML only: h2, h3, p, ul, li, strong, a
- Use {{first_name}} for recipient personalization
- NO markdown, NO code blocks, NO explanations
- Keep it concise - a short honest newsletter beats a long fake one

Remember: An empty/quiet community newsletter that encourages first posts is BETTER than a newsletter full of invented activity.
EOT;
    }

    /**
     * Get real platform data for newsletter content generation
     * Fetches actual listings, events, members, and stats
     */
    private function getNewsletterPlatformData(): array
    {
        $tenantId = TenantContext::getId();
        $tenant = TenantContext::get();
        $platformName = $tenant['name'] ?? 'NEXUS TimeBank';

        // Initialize with defaults in case of errors
        $recentOffers = [];
        $recentRequests = [];
        $upcomingEvents = [];
        $totalMembers = 0;
        $newMembersThisMonth = 0;
        $exchangesThisMonth = 0;
        $hoursExchangedThisMonth = 0;
        $activeGroups = [];

        try {
            // Get recent listings (last 14 days)
            $twoWeeksAgo = date('Y-m-d H:i:s', strtotime('-14 days'));
            $recentOffers = Listing::getRecent('offer', 5, $twoWeeksAgo) ?: [];
            $recentRequests = Listing::getRecent('request', 5, $twoWeeksAgo) ?: [];
        } catch (\Exception $e) {
            error_log("Newsletter AI: Error fetching listings - " . $e->getMessage());
        }

        try {
            // Get upcoming events
            $upcomingEvents = Event::upcoming($tenantId, 5) ?: [];
        } catch (\Exception $e) {
            error_log("Newsletter AI: Error fetching events - " . $e->getMessage());
        }

        try {
            // Get member stats
            $totalMembers = User::count() ?: 0;
        } catch (\Exception $e) {
            error_log("Newsletter AI: Error fetching user count - " . $e->getMessage());
        }

        try {
            // Get recent activity stats
            $db = Database::getConnection();

            // Count new members this month
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE tenant_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
            $stmt->execute([$tenantId]);
            $newMembersThisMonth = (int)($stmt->fetch()['count'] ?? 0);
        } catch (\Exception $e) {
            error_log("Newsletter AI: Error fetching new members count - " . $e->getMessage());
        }

        try {
            $db = Database::getConnection();
            // Count total exchanges/transactions this month (column is 'amount' not 'hours')
            $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total_hours FROM transactions WHERE tenant_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
            $stmt->execute([$tenantId]);
            $txData = $stmt->fetch();
            $exchangesThisMonth = (int)($txData['count'] ?? 0);
            $hoursExchangedThisMonth = (float)($txData['total_hours'] ?? 0);
        } catch (\Exception $e) {
            error_log("Newsletter AI: Error fetching transactions - " . $e->getMessage());
        }

        try {
            $db = Database::getConnection();
            // Get active groups
            $stmt = $db->prepare("SELECT name FROM `groups` WHERE tenant_id = ? AND visibility = 'public' ORDER BY created_at DESC LIMIT 3");
            $stmt->execute([$tenantId]);
            $activeGroups = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        } catch (\Exception $e) {
            error_log("Newsletter AI: Error fetching groups - " . $e->getMessage());
        }

        return [
            'platform_name' => $platformName,
            'total_members' => $totalMembers,
            'new_members_this_month' => $newMembersThisMonth,
            'exchanges_this_month' => $exchangesThisMonth,
            'hours_exchanged_this_month' => round($hoursExchangedThisMonth, 1),
            'recent_offers' => $recentOffers,
            'recent_requests' => $recentRequests,
            'upcoming_events' => $upcomingEvents,
            'active_groups' => $activeGroups,
        ];
    }

    /**
     * Format platform data as readable text for AI prompt
     */
    private function formatPlatformDataForPrompt(array $data): string
    {
        $output = "## ═══════════════════════════════════════════════════════\n";
        $output .= "## REAL DATA FROM {$data['platform_name']} DATABASE\n";
        $output .= "## USE ONLY THIS DATA - DO NOT ADD ANYTHING ELSE\n";
        $output .= "## ═══════════════════════════════════════════════════════\n\n";

        $output .= "### Platform Statistics (REAL NUMBERS)\n";
        $output .= "- Total Members: {$data['total_members']}\n";
        $output .= "- New Members This Month: {$data['new_members_this_month']}\n";
        $output .= "- Exchanges This Month: {$data['exchanges_this_month']}\n";
        $output .= "- Hours Shared This Month: {$data['hours_exchanged_this_month']}\n\n";

        // Recent Offers
        $offerCount = count($data['recent_offers'] ?? []);
        $output .= "### Recent Offers - COUNT: {$offerCount}\n";
        if ($offerCount > 0) {
            foreach ($data['recent_offers'] as $offer) {
                $title = htmlspecialchars($offer['title'] ?? 'Untitled');
                $name = htmlspecialchars($offer['user_name'] ?? 'A member');
                $output .= "- \"{$title}\" offered by {$name}\n";
            }
        } else {
            $output .= "- (No recent offers - DO NOT INVENT ANY)\n";
        }
        $output .= "\n";

        // Recent Requests
        $requestCount = count($data['recent_requests'] ?? []);
        $output .= "### Recent Requests - COUNT: {$requestCount}\n";
        if ($requestCount > 0) {
            foreach ($data['recent_requests'] as $request) {
                $title = htmlspecialchars($request['title'] ?? 'Untitled');
                $name = htmlspecialchars($request['user_name'] ?? 'A member');
                $output .= "- \"{$title}\" requested by {$name}\n";
            }
        } else {
            $output .= "- (No recent requests - DO NOT INVENT ANY)\n";
        }
        $output .= "\n";

        // Upcoming Events
        $eventCount = count($data['upcoming_events'] ?? []);
        $output .= "### Upcoming Events - COUNT: {$eventCount}\n";
        if ($eventCount > 0) {
            foreach ($data['upcoming_events'] as $event) {
                $title = htmlspecialchars($event['title'] ?? 'Untitled');
                $date = date('l, F j', strtotime($event['start_time'] ?? 'now'));
                $organizer = htmlspecialchars($event['organizer_name'] ?? 'Community');
                $output .= "- \"{$title}\" on {$date}, hosted by {$organizer}\n";
            }
        } else {
            $output .= "- (No upcoming events - DO NOT INVENT ANY)\n";
        }
        $output .= "\n";

        // Active Groups
        $groupCount = count($data['active_groups'] ?? []);
        $output .= "### Active Groups - COUNT: {$groupCount}\n";
        if ($groupCount > 0) {
            foreach ($data['active_groups'] as $group) {
                $output .= "- {$group}\n";
            }
        } else {
            $output .= "- (No active groups to mention)\n";
        }
        $output .= "\n";

        $output .= "## ═══════════════════════════════════════════════════════\n";
        $output .= "## END OF REAL DATA - ANYTHING NOT LISTED ABOVE IS FAKE\n";
        $output .= "## ═══════════════════════════════════════════════════════\n\n";

        return $output;
    }

    /**
     * POST /api/ai/generate/blog
     * Generate blog article content
     */
    public function generateBlog()
    {
        $userId = $this->getUserId();

        if (!AIServiceFactory::isFeatureEnabled('content_generation')) {
            $this->jsonResponse(['error' => 'Content generation is not enabled'], 403);
        }

        $limitCheck = AiUserLimit::canMakeRequest($userId);
        if (!$limitCheck['allowed']) {
            $this->jsonResponse(['error' => 'Usage limit reached'], 429);
        }

        $input = $this->getInput();
        $type = $input['type'] ?? 'content'; // title, excerpt, content, seo
        $context = $input['context'] ?? [];

        try {
            $aiProvider = AIServiceFactory::getProvider();
            $prompt = $this->buildBlogPrompt($type, $context);

            $messages = [
                ['role' => 'system', 'content' => $this->getBlogSystemPrompt()],
                ['role' => 'user', 'content' => $prompt]
            ];

            $response = $aiProvider->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => $type === 'content' ? 3000 : 500
            ]);

            AiUserLimit::incrementUsage($userId);
            AiUsage::log($userId, $aiProvider->getId(), 'generate_blog', [
                'tokens_input' => $response['tokens_input'] ?? 0,
                'tokens_output' => $response['tokens_output'] ?? 0,
            ]);

            $this->jsonResponse([
                'success' => true,
                'content' => trim($response['content']),
                'type' => $type,
            ]);

        } catch (\Exception $e) {
            error_log("AI generateBlog error: " . $e->getMessage());
            $this->jsonResponse(['error' => $this->getFriendlyErrorMessage($e)], 500);
        }
    }

    /**
     * Build blog generation prompt
     */
    private function buildBlogPrompt(string $type, array $context): string
    {
        $title = $context['title'] ?? '';
        $topic = $context['topic'] ?? '';
        $category = $context['category'] ?? '';
        $keywords = $context['keywords'] ?? '';
        $existingContent = $context['existing_content'] ?? '';

        $prompt = "# TASK: Generate Blog ";

        switch ($type) {
            case 'title':
                $prompt .= "Title\n\n";
                $prompt .= "## REQUIREMENTS\n";
                $prompt .= "Generate 3 compelling blog post titles.\n\n";
                if ($topic) {
                    $prompt .= "**Topic:** {$topic}\n";
                }
                if ($category) {
                    $prompt .= "**Category:** {$category}\n";
                }
                if ($keywords) {
                    $prompt .= "**Keywords to include:** {$keywords}\n";
                }
                $prompt .= "\n## OUTPUT FORMAT\n";
                $prompt .= "Return exactly 3 titles, one per line, numbered 1-3.\n";
                $prompt .= "Each should be engaging, SEO-friendly, and under 70 characters.\n";
                break;

            case 'excerpt':
                $prompt .= "Excerpt/Summary\n\n";
                $prompt .= "## REQUIREMENTS\n";
                $prompt .= "Generate a compelling excerpt for the blog post.\n\n";
                if ($title) {
                    $prompt .= "**Title:** {$title}\n";
                }
                if ($existingContent) {
                    $prompt .= "**Content Preview:** " . substr($existingContent, 0, 500) . "...\n";
                }
                $prompt .= "\n## OUTPUT FORMAT\n";
                $prompt .= "Return a single excerpt paragraph (2-3 sentences, 150-200 characters).\n";
                $prompt .= "It should hook readers and summarize the value of the article.\n";
                break;

            case 'content':
                $prompt .= "Article Content\n\n";
                $prompt .= "## REQUIREMENTS\n";
                $prompt .= "Write a complete blog article for a community timebank platform.\n\n";
                if ($title) {
                    $prompt .= "**Title:** {$title}\n";
                }
                if ($topic) {
                    $prompt .= "**Topic:** {$topic}\n";
                }
                if ($category) {
                    $prompt .= "**Category:** {$category}\n";
                }
                if ($keywords) {
                    $prompt .= "**Keywords:** {$keywords}\n";
                }
                $prompt .= "\n## OUTPUT FORMAT\n";
                $prompt .= "Write the article in clean HTML format.\n";
                $prompt .= "Structure:\n";
                $prompt .= "- Engaging introduction (hook the reader)\n";
                $prompt .= "- 3-4 main sections with h2 headings\n";
                $prompt .= "- Practical tips or actionable advice\n";
                $prompt .= "- Strong conclusion with call-to-action\n\n";
                $prompt .= "Use semantic HTML (h2, h3, p, ul, li, strong, em).\n";
                $prompt .= "Length: 600-1000 words.\n";
                $prompt .= "Tone: Informative, friendly, community-focused.\n";
                break;

            case 'seo':
                $prompt .= "SEO Meta Data\n\n";
                $prompt .= "## REQUIREMENTS\n";
                $prompt .= "Generate SEO meta title and description.\n\n";
                if ($title) {
                    $prompt .= "**Article Title:** {$title}\n";
                }
                if ($existingContent) {
                    $prompt .= "**Content Preview:** " . substr(strip_tags($existingContent), 0, 500) . "...\n";
                }
                $prompt .= "\n## OUTPUT FORMAT\n";
                $prompt .= "Return in this exact format:\n";
                $prompt .= "META_TITLE: [title under 60 chars]\n";
                $prompt .= "META_DESCRIPTION: [description 150-160 chars]\n";
                break;

            case 'improve':
                $prompt .= "Content Improvement\n\n";
                $prompt .= "## REQUIREMENTS\n";
                $prompt .= "Improve the existing blog content.\n\n";
                if ($title) {
                    $prompt .= "**Title:** {$title}\n";
                }
                $prompt .= "**Current Content:**\n{$existingContent}\n\n";
                $prompt .= "## IMPROVEMENTS NEEDED\n";
                $prompt .= "- Enhance readability and flow\n";
                $prompt .= "- Add more specific details or examples\n";
                $prompt .= "- Strengthen the introduction and conclusion\n";
                $prompt .= "- Improve formatting with proper headings\n\n";
                $prompt .= "Return the improved HTML content only.\n";
                break;

            default:
                $prompt .= "Content\n\n";
                $prompt .= "Generate appropriate blog content based on context.\n";
        }

        return $prompt;
    }

    /**
     * Get system prompt for blog generation
     */
    private function getBlogSystemPrompt(): string
    {
        return <<<EOT
You are a content writer for NEXUS TimeBank, a community platform where neighbors exchange skills and services using time credits.

## YOUR MISSION
Create engaging, informative blog content that educates and inspires community members. Your writing should be accessible, practical, and community-focused.

## CONTENT GUIDELINES
1. **AUDIENCE** - Community members interested in timebanking, skill-sharing, and local connection
2. **TONE** - Warm, helpful, and encouraging (not corporate or academic)
3. **VALUE** - Every article should teach something practical or inspire action
4. **FORMAT** - Use clear headings, short paragraphs, bullet points for scannability
5. **SEO** - Include relevant keywords naturally, write compelling meta descriptions

## TOPIC AREAS
- Timebanking tips and success stories
- Skill-sharing guides and tutorials
- Community building and connection
- Sustainable living and local economy
- Member spotlights and achievements

Remember: You're writing for real neighbors who want to connect and help each other.
EOT;
    }

    /**
     * POST /api/ai/generate/page
     * Generate page content for the page builder
     */
    public function generatePage()
    {
        $userId = $this->getUserId();

        if (!AIServiceFactory::isFeatureEnabled('content_generation')) {
            $this->jsonResponse(['error' => 'Content generation is not enabled'], 403);
        }

        $limitCheck = AiUserLimit::canMakeRequest($userId);
        if (!$limitCheck['allowed']) {
            $this->jsonResponse(['error' => 'Usage limit reached'], 429);
        }

        $input = $this->getInput();
        $type = $input['type'] ?? 'section'; // section, hero, cta, full, seo
        $context = $input['context'] ?? [];

        try {
            $aiProvider = AIServiceFactory::getProvider();
            $prompt = $this->buildPagePrompt($type, $context);

            $messages = [
                ['role' => 'system', 'content' => $this->getPageSystemPrompt()],
                ['role' => 'user', 'content' => $prompt]
            ];

            $response = $aiProvider->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => $type === 'full' ? 3000 : 1000
            ]);

            AiUserLimit::incrementUsage($userId);
            AiUsage::log($userId, $aiProvider->getId(), 'generate_page', [
                'tokens_input' => $response['tokens_input'] ?? 0,
                'tokens_output' => $response['tokens_output'] ?? 0,
            ]);

            $this->jsonResponse([
                'success' => true,
                'content' => trim($response['content']),
                'type' => $type,
            ]);

        } catch (\Exception $e) {
            error_log("AI generatePage error: " . $e->getMessage());
            $this->jsonResponse(['error' => $this->getFriendlyErrorMessage($e)], 500);
        }
    }

    /**
     * Build page generation prompt
     */
    private function buildPagePrompt(string $type, array $context): string
    {
        $pageTitle = $context['page_title'] ?? $context['title'] ?? '';
        $purpose = $context['prompt'] ?? $context['purpose'] ?? '';
        $existingContent = $context['existing_content'] ?? '';
        $style = $context['style'] ?? 'modern';

        $prompt = "# TASK: Generate Page ";

        switch ($type) {
            case 'hero':
                $prompt .= "Hero Section\n\n";
                $prompt .= "## REQUIREMENTS\n";
                $prompt .= "Generate a compelling hero section for a webpage.\n\n";
                if ($pageTitle) {
                    $prompt .= "**Page Title:** {$pageTitle}\n";
                }
                if ($purpose) {
                    $prompt .= "**User's Description:** {$purpose}\n";
                }
                $prompt .= "\n## OUTPUT FORMAT\n";
                $prompt .= "Return HTML for a hero section with:\n";
                $prompt .= "- Main headline (h1)\n";
                $prompt .= "- Supporting subheadline (p)\n";
                $prompt .= "- Call-to-action button\n";
                $prompt .= "- Background styling (gradient or solid color)\n\n";
                $prompt .= "Use inline styles for spacing/alignment.\n";
                $prompt .= "Keep text concise and impactful.\n";
                $prompt .= "Make sure the section has substantial padding (60-100px top/bottom).\n";
                break;

            case 'section':
                $prompt .= "Content Section\n\n";
                $prompt .= "## REQUIREMENTS\n";
                $prompt .= "Generate a content section for the page.\n\n";
                if ($pageTitle) {
                    $prompt .= "**Page Title:** {$pageTitle}\n";
                }
                if ($purpose) {
                    $prompt .= "**User's Description:** {$purpose}\n";
                }
                $prompt .= "\n## OUTPUT FORMAT\n";
                $prompt .= "Return HTML for a content section with:\n";
                $prompt .= "- Section heading (h2)\n";
                $prompt .= "- 2-3 paragraphs of content\n";
                $prompt .= "- Optional bullet points or features\n\n";
                $prompt .= "Use inline styles for padding (40-60px), good line-height.\n";
                $prompt .= "Make content relevant to a timebank community platform.\n";
                break;

            case 'cta':
                $prompt .= "Call-to-Action Section\n\n";
                $prompt .= "## REQUIREMENTS\n";
                $prompt .= "Generate a compelling CTA section.\n\n";
                if ($purpose) {
                    $prompt .= "**User's Description:** {$purpose}\n";
                }
                $prompt .= "\n## OUTPUT FORMAT\n";
                $prompt .= "Return HTML for a CTA section with:\n";
                $prompt .= "- Compelling headline\n";
                $prompt .= "- Brief supporting text\n";
                $prompt .= "- Prominent action button with good styling\n";
                $prompt .= "- Eye-catching background (gradient or solid color)\n\n";
                $prompt .= "Use inline styles with padding (50-80px).\n";
                $prompt .= "Create urgency without being pushy.\n";
                $prompt .= "Center-align the content.\n";
                break;

            case 'features':
                $prompt .= "Features/Benefits Section\n\n";
                $prompt .= "## REQUIREMENTS\n";
                $prompt .= "Generate a features section for a timebank platform page.\n\n";
                if ($purpose) {
                    $prompt .= "**User's Description:** {$purpose}\n";
                }
                $prompt .= "\n## OUTPUT FORMAT\n";
                $prompt .= "Return HTML for a 3-column features grid with:\n";
                $prompt .= "- Section title (h2)\n";
                $prompt .= "- 3 feature boxes, each with: emoji or icon, title (h3), brief description\n";
                $prompt .= "- Cards should have subtle background and padding\n\n";
                $prompt .= "Focus on timebank benefits: community, skill-sharing, time credits.\n";
                $prompt .= "Use flexbox with inline styles. Add padding (50-80px) to the section.\n";
                $prompt .= "Make cards responsive-friendly with flex-wrap.\n";
                break;

            case 'testimonials':
                $prompt .= "Testimonials Section\n\n";
                $prompt .= "## REQUIREMENTS\n";
                $prompt .= "Generate a testimonials section for a timebank community.\n\n";
                if ($purpose) {
                    $prompt .= "**User's Description:** {$purpose}\n";
                }
                $prompt .= "\n## OUTPUT FORMAT\n";
                $prompt .= "Return HTML for a testimonials section with:\n";
                $prompt .= "- Section heading (h2) like 'What Our Members Say'\n";
                $prompt .= "- 3 testimonial cards in a grid layout\n";
                $prompt .= "- Each card has: large quote marks or emoji, quote text (italic), member name (bold), brief description\n";
                $prompt .= "- Cards should have subtle shadow or border styling\n\n";
                $prompt .= "Make testimonials realistic and relatable to timebanking.\n";
                $prompt .= "Focus on community connection, skill sharing, and positive experiences.\n";
                $prompt .= "Use flexbox with inline styles. Add section padding (50-80px).\n";
                break;

            case 'faq':
                $prompt .= "FAQ Section\n\n";
                $prompt .= "## REQUIREMENTS\n";
                $prompt .= "Generate an FAQ section for a timebank platform.\n\n";
                if ($purpose) {
                    $prompt .= "**User's Description:** {$purpose}\n";
                }
                $prompt .= "\n## OUTPUT FORMAT\n";
                $prompt .= "Return HTML for an FAQ section with:\n";
                $prompt .= "- Section heading (h2) like 'Frequently Asked Questions'\n";
                $prompt .= "- 4-6 Q&A pairs relevant to timebanking\n";
                $prompt .= "- Each Q&A should be a div with: question (h3 or bold), answer (p)\n";
                $prompt .= "- Add light background or border to each Q&A item\n";
                $prompt .= "- Good vertical spacing between items\n\n";
                $prompt .= "Include questions about: how timebanking works, getting started, earning/spending time credits, safety.\n";
                $prompt .= "Use inline styles with section padding (50-80px).\n";
                break;

            case 'text':
                $prompt .= "Text Content Section\n\n";
                $prompt .= "## REQUIREMENTS\n";
                $prompt .= "Generate a text content section.\n\n";
                if ($pageTitle) {
                    $prompt .= "**Page Title:** {$pageTitle}\n";
                }
                if ($purpose) {
                    $prompt .= "**Topic:** {$purpose}\n";
                }
                $prompt .= "\n## OUTPUT FORMAT\n";
                $prompt .= "Return HTML for a content section with:\n";
                $prompt .= "- Section heading (h2)\n";
                $prompt .= "- 2-4 well-written paragraphs\n";
                $prompt .= "- Optionally include bullet points or highlights\n\n";
                $prompt .= "Write engaging, informative content relevant to a community timebank.\n";
                $prompt .= "Use inline styles for spacing and formatting.\n";
                break;

            case 'seo':
                $prompt .= "SEO Meta Data\n\n";
                $prompt .= "## REQUIREMENTS\n";
                $prompt .= "Generate SEO meta title and description for the page.\n\n";
                if ($pageTitle) {
                    $prompt .= "**Page Title:** {$pageTitle}\n";
                }
                if ($existingContent) {
                    // Strip HTML and get first 500 chars of content
                    $textContent = strip_tags($existingContent);
                    $textContent = substr($textContent, 0, 500);
                    $prompt .= "**Page Content Preview:** {$textContent}\n";
                }
                $prompt .= "\n## OUTPUT FORMAT\n";
                $prompt .= "Return in this exact format (no markdown, no extra text):\n";
                $prompt .= "META_TITLE: [title under 60 chars]\n";
                $prompt .= "META_DESCRIPTION: [description 150-160 chars]\n";
                break;

            case 'full':
                $prompt .= "Full Page Layout\n\n";
                $prompt .= "## REQUIREMENTS\n";
                $prompt .= "Generate a complete page layout for a timebank website.\n\n";
                if ($pageTitle) {
                    $prompt .= "**Page Title:** {$pageTitle}\n";
                }
                if ($purpose) {
                    $prompt .= "**Page Purpose:** {$purpose}\n";
                }
                $prompt .= "\n## OUTPUT FORMAT\n";
                $prompt .= "Return HTML for a complete page with:\n";
                $prompt .= "1. Hero section with headline and CTA\n";
                $prompt .= "2. Features/benefits section (3 columns)\n";
                $prompt .= "3. Content section explaining the platform\n";
                $prompt .= "4. Final CTA section\n\n";
                $prompt .= "Use semantic HTML and inline styles.\n";
                $prompt .= "Make it mobile-responsive with flexbox.\n";
                break;

            default:
                $prompt .= "Content\n\n";
                $prompt .= "Generate appropriate page content based on context.\n";
        }

        return $prompt;
    }

    /**
     * Get system prompt for page generation
     */
    private function getPageSystemPrompt(): string
    {
        return <<<EOT
You are a web content designer for NEXUS TimeBank, creating pages for a community time-exchange platform.

## YOUR MISSION
Generate clean, modern HTML content that's visually appealing and converts visitors into community members.

## DESIGN PRINCIPLES
1. **CLARITY** - Every section has a clear purpose and message
2. **SCANNABILITY** - Use headings, short paragraphs, bullet points
3. **ACTION-ORIENTED** - Include clear calls-to-action
4. **COMMUNITY-FOCUSED** - Emphasize connection, sharing, and mutual aid
5. **MOBILE-FIRST** - Use responsive-friendly layouts (flexbox)

## HTML GUIDELINES
- Use semantic HTML (section, article, h1-h3, p, ul, etc.)
- Include inline styles for spacing and alignment
- Use placeholder text like "[Button Text]" for CTAs
- Keep styles simple and modern (clean fonts, good spacing)
- Colors: Primary #6366f1, Success #10b981, Warning #f59e0b

## BRAND VOICE
- Warm and welcoming
- Community-focused
- Empowering and positive
- Simple and clear (no jargon)

Remember: These pages help build a community of neighbors helping neighbors.
EOT;
    }
}
