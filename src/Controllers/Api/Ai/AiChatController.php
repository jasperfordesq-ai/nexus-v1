<?php

declare(strict_types=1);

namespace Nexus\Controllers\Api\Ai;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\AiConversation;
use Nexus\Models\AiMessage;
use Nexus\Models\AiUsage;
use Nexus\Models\AiUserLimit;
use Nexus\Services\AI\AIServiceFactory;

/**
 * AI Chat Controller
 *
 * Handles chat/conversation endpoints and context building.
 */
class AiChatController extends BaseAiController
{
    /**
     * POST /api/ai/chat
     * Send a message and get AI response
     */
    public function chat(): void
    {
        $userId = $this->getUserId();
        $input = $this->getInput();

        $message = trim($input['message'] ?? '');
        $conversationId = $input['conversation_id'] ?? null;
        $provider = $input['provider'] ?? null;

        error_log("AI API Request: User [$userId] requesting chat. Provider: " . ($provider ?? 'default'));

        if (empty($message)) {
            $this->jsonResponse(['error' => 'Message is required'], 400);
        }

        if (!AIServiceFactory::isEnabled()) {
            $this->jsonResponse(['error' => 'AI features are not enabled'], 403);
        }

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

            $preferredProvider = $provider ?? $conversation['provider'];

            // Save user message
            AiMessage::createUserMessage($conversationId, $message);

            // Get conversation history for context
            $history = AiMessage::getRecentForContext($conversationId, 20);

            // Build messages array with system prompt
            $messages = [];
            $tenantId = TenantContext::getId();

            // Add system prompt with user context + smart context
            $systemPrompt = AIServiceFactory::getSystemPrompt();
            $userContext = $this->buildUserContext($userId);
            $smartContext = $this->fetchSmartContext($tenantId, $userId, $message);

            if ($systemPrompt) {
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

            if (!empty($response['used_fallback'])) {
                error_log("AI chat used fallback provider: " . ($response['provider'] ?? 'unknown'));
            }

            // Save assistant response
            $assistantMessageId = AiMessage::createAssistantMessage($conversationId, $response['content'], [
                'tokens_used' => $response['tokens_used'] ?? 0,
                'model' => $response['model'] ?? null,
            ]);

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

            AiUserLimit::incrementUsage($userId);

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
     * POST /api/ai/chat/stream
     * Stream AI response using Server-Sent Events
     */
    public function streamChat(): void
    {
        $userId = $this->getUserId();
        $input = $this->getInput();

        $message = trim($input['message'] ?? '');
        $conversationId = $input['conversation_id'] ?? null;
        $provider = $input['provider'] ?? null;

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
            if (!$conversationId) {
                $conversationId = AiConversation::create($userId, [
                    'provider' => $provider ?? AIServiceFactory::getDefaultProvider(),
                ]);
            }

            $preferredProvider = $provider ?? AIServiceFactory::getDefaultProvider();
            $aiProvider = AIServiceFactory::getProvider($preferredProvider);

            // PRE-FLIGHT CHECK: Verify provider is configured
            if (!$aiProvider->isConfigured()) {
                error_log("Stream Error: Provider [$preferredProvider] not configured for user [$userId]");
                echo "data: " . json_encode(['error' => 'AI provider is not configured. Please configure API keys in Admin > AI Settings.']) . "\n\n";
                ob_flush();
                flush();
                exit;
            }

            AiMessage::createUserMessage($conversationId, $message);

            $history = AiMessage::getRecentForContext($conversationId, 20);
            $messages = [];
            $tenantId = TenantContext::getId();

            $systemPrompt = AIServiceFactory::getSystemPrompt();
            $userContext = $this->buildUserContext($userId);
            $smartContext = $this->fetchSmartContext($tenantId, $userId, $message);

            if ($systemPrompt) {
                $fullContext = $systemPrompt . $userContext . $smartContext;
                $messages[] = ['role' => 'system', 'content' => $fullContext];
            }

            foreach ($history as $msg) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }

            $fullContent = '';
            $aiProvider->streamChat($messages, function ($chunk) use (&$fullContent) {
                $content = $chunk['content'] ?? '';
                $fullContent .= $content;

                echo "data: " . json_encode(['content' => $content, 'done' => $chunk['done'] ?? false]) . "\n\n";
                ob_flush();
                flush();
            });

            AiMessage::createAssistantMessage($conversationId, $fullContent);
            AiUserLimit::incrementUsage($userId);

            echo "data: " . json_encode(['done' => true, 'conversation_id' => $conversationId]) . "\n\n";
            ob_flush();
            flush();

        } catch (\Exception $e) {
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
    public function listConversations(): void
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
    public function getConversation($id): void
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
    public function createConversation(): void
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
    public function deleteConversation($id): void
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
     * Build dynamic user context for personalized AI responses
     */
    private function buildUserContext(int $userId): string
    {
        $context = "\n\n## CURRENT USER CONTEXT\n";
        $context .= "The following is real-time information about the user you're helping:\n\n";

        $userLocation = null;

        try {
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

            $tenantId = TenantContext::getId();
            $stmt = $db->prepare("SELECT balance FROM time_wallets WHERE user_id = ? AND tenant_id = ?");
            $stmt->execute([$userId, $tenantId]);
            $wallet = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($wallet) {
                $context .= "**Time Credit Balance:** {$wallet['balance']} hours\n";
            }

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

            $stmt = $db->prepare("SELECT COUNT(*) as count FROM time_transactions
                WHERE (from_user_id = ? OR to_user_id = ?) AND tenant_id = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute([$userId, $userId, $tenantId]);
            $transactions = $stmt->fetch(\PDO::FETCH_ASSOC);
            $context .= "**Exchanges in last 30 days:** " . ($transactions['count'] ?? 0) . "\n";

            $stmt = $db->prepare("SELECT COUNT(*) as count FROM user_achievements WHERE user_id = ?");
            $stmt->execute([$userId]);
            $achievements = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($achievements && $achievements['count'] > 0) {
                $context .= "**Achievements earned:** {$achievements['count']}\n";
            }

            $stmt = $db->prepare("SELECT xp FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $xpData = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($xpData && isset($xpData['xp']) && $xpData['xp'] > 0) {
                $context .= "**XP Points:** {$xpData['xp']}\n";
            }

        } catch (\Exception $e) {
            $context .= "(Unable to load some user data)\n";
        }

        $context .= "\nUse this context to give personalized, relevant responses. Reference their specific situation when helpful (e.g., 'Since you have 3 offers listed...' or 'With your balance of X hours...').\n";

        $context .= $this->buildPlatformContext($userId, $userLocation);

        return $context;
    }

    /**
     * Build platform-wide context with community data
     */
    private function buildPlatformContext(int $currentUserId, ?string $userLocation = null): string
    {
        $context = "\n\n## LIVE PLATFORM DATA\n";
        $context .= "Current community data you can reference when answering questions:\n\n";

        try {
            $db = Database::getConnection();
            $tenantId = TenantContext::getId();

            $context .= "### Community Statistics\n";

            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE tenant_id = ? AND status = 'active'");
            $stmt->execute([$tenantId]);
            $memberCount = $stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
            $context .= "- **Total active members:** {$memberCount}\n";

            $stmt = $db->prepare("SELECT
                COUNT(*) as total,
                SUM(CASE WHEN type = 'offer' THEN 1 ELSE 0 END) as offers,
                SUM(CASE WHEN type = 'request' THEN 1 ELSE 0 END) as requests
                FROM listings WHERE tenant_id = ? AND status = 'active'");
            $stmt->execute([$tenantId]);
            $listingStats = $stmt->fetch(\PDO::FETCH_ASSOC);
            $context .= "- **Active listings:** {$listingStats['total']} total ({$listingStats['offers']} offers, {$listingStats['requests']} requests)\n";

            $stmt = $db->prepare("SELECT COUNT(*) as count FROM groups WHERE tenant_id = ? AND status = 'active'");
            $stmt->execute([$tenantId]);
            $groupCount = $stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
            $context .= "- **Active groups/hubs:** {$groupCount}\n";

            $stmt = $db->prepare("SELECT COUNT(*) as count FROM events WHERE tenant_id = ? AND start_datetime > NOW() AND status = 'published'");
            $stmt->execute([$tenantId]);
            $eventCount = $stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
            $context .= "- **Upcoming events:** {$eventCount}\n";

            // Current Requests
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

            // Current Offers
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

            // Upcoming Events
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

            // Active Groups
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

            // Categories
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

            // Recent Activity
            $context .= "\n### Recent Community Activity\n";

            $stmt = $db->prepare("
                SELECT COUNT(*) as count, SUM(amount) as total_hours
                FROM time_transactions
                WHERE tenant_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$tenantId]);
            $recentTrans = $stmt->fetch(\PDO::FETCH_ASSOC);
            $context .= "- **Last 7 days:** {$recentTrans['count']} exchanges, " . round($recentTrans['total_hours'] ?? 0, 1) . " hours exchanged\n";

            $stmt = $db->prepare("
                SELECT COUNT(*) as count FROM users
                WHERE tenant_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute([$tenantId]);
            $newMembers = $stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
            $context .= "- **New members this month:** {$newMembers}\n";

            // Nearby Listings
            if (!empty($userLocation)) {
                $context .= "\n### Nearby Listings (Near {$userLocation})\n";
                $context .= "These listings are from members in or near the user's location. PRIORITIZE suggesting these when relevant:\n\n";

                $locationParts = array_map('trim', explode(',', $userLocation));
                $params = [$tenantId, 'offer', 'active', $currentUserId];

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

                    $params[1] = 'request';
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
     */
    private function fetchSmartContext(int $tenantId, int $userId, string $message): string
    {
        try {
            $db = Database::getConnection();

            // Tenant Guard
            if ($tenantId < 1) {
                error_log("Smart Context Engine: Invalid tenant $tenantId, defaulting to Master Tenant");
                $tenantId = 1;
            }

            // Intent Detection
            $messageLower = strtolower($message);
            $targetType = null;

            $needKeywords = ['need', 'looking for', 'want', 'search', 'help me', 'hire', 'find', 'require', 'seeking'];
            foreach ($needKeywords as $keyword) {
                if (strpos($messageLower, $keyword) !== false) {
                    $targetType = 'offer';
                    break;
                }
            }

            if ($targetType === null) {
                $giveKeywords = ['can i', 'want to help', 'volunteer', 'offer', 'available', 'i can', 'willing to', 'able to'];
                foreach ($giveKeywords as $keyword) {
                    if (strpos($messageLower, $keyword) !== false) {
                        $targetType = 'request';
                        break;
                    }
                }
            }

            // Get User Coordinates
            $stmt = $db->prepare("SELECT latitude, longitude, location FROM users WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$userId, $tenantId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            $userLat = $user['latitude'] ?? null;
            $userLng = $user['longitude'] ?? null;
            $userLocation = $user['location'] ?? null;

            if (empty($userLocation)) {
                $userLocation = 'Ireland';
                error_log("Smart Context Engine: User location null, defaulting to 'Ireland' (Tenant 2)");
            }

            $hasCoordinates = ($userLat !== null && $userLng !== null);

            // Extract Keywords
            $stopwords = ['i', 'need', 'want', 'can', 'help', 'me', 'with', 'the', 'a', 'an', 'to', 'for', 'in', 'on', 'at'];
            $words = preg_split('/\s+/', $messageLower);
            $keywords = array_filter($words, function($word) use ($stopwords) {
                return strlen($word) > 2 && !in_array($word, $stopwords);
            });

            $searchTerms = array_slice($keywords, 0, 3);

            // Build Query
            if ($hasCoordinates) {
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

                $sql .= " ORDER BY distance_km ASC, l.created_at DESC LIMIT 5";

                $stmt = $db->prepare($sql);
                $stmt->bindValue(':userLat', $userLat, \PDO::PARAM_STR);
                $stmt->bindValue(':userLng', $userLng, \PDO::PARAM_STR);
                $stmt->bindValue(':tenantId', $tenantId, \PDO::PARAM_INT);

                foreach ($searchTerms as $term) {
                    $hash = md5($term);
                    $stmt->bindValue(':keyword_title_' . $hash, '%' . $term . '%', \PDO::PARAM_STR);
                    $stmt->bindValue(':keyword_desc_' . $hash, '%' . $term . '%', \PDO::PARAM_STR);
                }

                if ($targetType !== null) {
                    $stmt->bindValue(':targetType', $targetType, \PDO::PARAM_STR);
                }

            } else {
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

            if (empty($results)) {
                return "";
            }

            // Format Context
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

            if (!$hasCoordinates) {
                $context .= "💡 **Proximity Tip:** User has not set precise coordinates. Showing national matches. Recommend they add their location for better distance-based matches.\n\n";
            }

            $context .= "**🇮🇪 NETWORK INSTRUCTION (Ireland):** You are the Tenant 2 Assistant representing the Ireland Timebank Community. ";
            $context .= "These are LIVE database matches scoped to Ireland. ";
            $context .= "Prioritize mentioning specific member names, locations, and distances (if available). ";
            $context .= "Use a humble, learning tone: 'Bear with me while I learn the ropes.' ";
            $context .= "Always emphasize local connections and nearest neighbors first. ";
            $context .= "If showing OFFERS, explain how they can help the user. If showing REQUESTS, explain how the user's skills match the needs.\n";

            return $context;

        } catch (\Exception $e) {
            error_log("Smart Context Engine Error: " . $e->getMessage());
            return "";
        }
    }
}
