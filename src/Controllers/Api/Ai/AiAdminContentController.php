<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Controllers\Api\Ai;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\AiUsage;
use Nexus\Models\AiUserLimit;
use Nexus\Models\Event;
use Nexus\Models\Listing;
use Nexus\Models\User;
use Nexus\Services\AI\AIServiceFactory;

/**
 * AI Admin Content Controller
 *
 * Handles admin-facing content generation: newsletters, blogs, pages.
 */
class AiAdminContentController extends BaseAiController
{
    /**
     * POST /api/ai/generate/newsletter
     * Generate newsletter content (subject, preview text, body)
     */
    public function generateNewsletter(): void
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
        $type = $input['type'] ?? 'subject';
        $context = $input['context'] ?? [];

        try {
            $aiProvider = AIServiceFactory::getProvider();
            $prompt = $this->buildNewsletterPrompt($type, $context);

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
     * POST /api/ai/generate/blog
     * Generate blog article content
     */
    public function generateBlog(): void
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
        $type = $input['type'] ?? 'content';
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
     * POST /api/ai/generate/page
     * Generate page content for the page builder
     */
    public function generatePage(): void
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
        $type = $input['type'] ?? 'section';
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
     * Build newsletter generation prompt with real platform data
     */
    private function buildNewsletterPrompt(string $type, array $context): string
    {
        $topic = $context['topic'] ?? '';
        $audience = $context['audience'] ?? 'community members';
        $tone = $context['tone'] ?? 'friendly and engaging';
        $existingSubject = $context['subject'] ?? '';
        $template = $context['template'] ?? '';
        $existingContent = $context['existing_content'] ?? '';
        $userPrompt = $context['user_prompt'] ?? '';

        $platformData = $this->getNewsletterPlatformData();
        $platformName = $platformData['platform_name'];

        $prompt = "# TASK: Generate Newsletter ";

        switch ($type) {
            case 'subject':
                $prompt .= "Subject Line for {$platformName}\n\n";
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
     */
    private function getNewsletterPlatformData(): array
    {
        $tenantId = TenantContext::getId();
        $tenant = TenantContext::get();
        $platformName = $tenant['name'] ?? 'NEXUS TimeBank';

        $recentOffers = [];
        $recentRequests = [];
        $upcomingEvents = [];
        $totalMembers = 0;
        $newMembersThisMonth = 0;
        $exchangesThisMonth = 0;
        $hoursExchangedThisMonth = 0;
        $activeGroups = [];

        try {
            $twoWeeksAgo = date('Y-m-d H:i:s', strtotime('-14 days'));
            $recentOffers = Listing::getRecent('offer', 5, $twoWeeksAgo) ?: [];
            $recentRequests = Listing::getRecent('request', 5, $twoWeeksAgo) ?: [];
        } catch (\Exception $e) {
            error_log("Newsletter AI: Error fetching listings - " . $e->getMessage());
        }

        try {
            $upcomingEvents = Event::upcoming($tenantId, 5) ?: [];
        } catch (\Exception $e) {
            error_log("Newsletter AI: Error fetching events - " . $e->getMessage());
        }

        try {
            $totalMembers = User::count() ?: 0;
        } catch (\Exception $e) {
            error_log("Newsletter AI: Error fetching user count - " . $e->getMessage());
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE tenant_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
            $stmt->execute([$tenantId]);
            $newMembersThisMonth = (int)($stmt->fetch()['count'] ?? 0);
        } catch (\Exception $e) {
            error_log("Newsletter AI: Error fetching new members count - " . $e->getMessage());
        }

        try {
            $db = Database::getConnection();
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
     * Build page generation prompt
     */
    private function buildPagePrompt(string $type, array $context): string
    {
        $pageTitle = $context['page_title'] ?? $context['title'] ?? '';
        $purpose = $context['prompt'] ?? $context['purpose'] ?? '';
        $existingContent = $context['existing_content'] ?? '';

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
