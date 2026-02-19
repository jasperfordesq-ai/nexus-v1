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
use Nexus\Services\AI\AIServiceFactory;

/**
 * AI Content Controller
 *
 * Handles user-facing content generation: listings, events, messages, bios.
 */
class AiContentController extends BaseAiController
{
    /**
     * POST /api/ai/generate/listing
     * Generate a listing description with rich context
     */
    public function generateListing(): void
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

            $prompt = $this->buildListingPrompt($userId, $title, $type, $context);

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
     * POST /api/ai/generate/event
     * Generate an event description with rich context
     */
    public function generateEvent(): void
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

            $prompt = $this->buildEventPrompt($userId, $title, $context);

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
     * POST /api/ai/generate/message
     * Generate a message reply suggestion
     */
    public function generateMessage(): void
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
        $tone = $input['tone'] ?? 'friendly';

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
    public function generateBio(): void
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
     * Build a rich prompt for listing generation
     */
    private function buildListingPrompt(int $userId, string $title, string $type, array $context): string
    {
        error_log("AI buildListingPrompt - Title: {$title}, Type: {$type}, Context: " . json_encode($context));

        $isOffer = ($type === 'offer');
        $typeLabel = $isOffer ? 'Offer' : 'Request';

        $userPrompt = $context['user_prompt'] ?? '';
        $hasUserPrompt = !empty($userPrompt);

        $prompt = "# TASK: Write a Timebank Listing Description\n\n";
        $prompt .= "You must write a compelling description for a community timebank listing. ";
        $prompt .= "Use ALL the details provided below to create a specific, personalized description.\n\n";

        $prompt .= "## MANDATORY INFORMATION TO INCORPORATE\n\n";
        $prompt .= "**Listing Type:** {$typeLabel}\n";
        $prompt .= "- This is " . ($isOffer ? "an OFFER where I'm sharing my skills/services with others" : "a REQUEST where I need help from the community") . "\n\n";

        if ($hasUserPrompt) {
            $prompt .= "**USER'S REQUEST (THIS IS THE MAIN INPUT - FOLLOW IT CLOSELY):**\n";
            $prompt .= "---\n{$userPrompt}\n---\n";
            $prompt .= "Write a listing description based on what the user described above. This is what they want to offer or request.\n\n";

            if (!empty($title)) {
                $prompt .= "**Title (optional context):** \"{$title}\"\n\n";
            }
        } else {
            $prompt .= "**Title:** \"{$title}\"\n";
            $prompt .= "- The title tells you what this listing is about. Use it as the core topic.\n\n";
        }

        if (!empty($context['category'])) {
            $prompt .= "**Category:** {$context['category']}\n";
            $prompt .= "- This categorizes the type of service. Incorporate language appropriate to this category.\n\n";
        }

        if (!empty($context['listing_type'])) {
            $type = $context['listing_type'];
            $isOffer = ($type === 'offer');
        }

        if (!empty($context['attributes']) && is_array($context['attributes'])) {
            $prompt .= "**Service Features Selected:** " . implode(', ', $context['attributes']) . "\n";
            $prompt .= "- These features MUST be mentioned or implied in the description. They describe the service's characteristics.\n\n";
        }

        if (!empty($context['sdg_goals']) && is_array($context['sdg_goals'])) {
            $prompt .= "**Social Impact Goals:** " . implode(', ', $context['sdg_goals']) . "\n";
            $prompt .= "- Subtly weave in how this service contributes to these goals. Don't list them explicitly.\n\n";
        }

        $userContext = $this->getUserProfileContext($userId);
        if ($userContext) {
            $prompt .= "## ABOUT THE PERSON POSTING\n{$userContext}\n";
            $prompt .= "Use this background to add authenticity and personalization.\n\n";
        }

        if (!empty($context['existing_description'])) {
            $prompt .= "## EXISTING DRAFT TO IMPROVE\n";
            $prompt .= "The user wrote this draft:\n";
            $prompt .= "---\n{$context['existing_description']}\n---\n\n";
            $prompt .= "Enhance this while keeping their voice and intent. Make it more engaging, specific, and complete.\n\n";
        }

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
     * Build a rich prompt for event generation
     */
    private function buildEventPrompt(int $userId, string $title, array $context): string
    {
        error_log("AI buildEventPrompt - Title: {$title}, Context: " . json_encode($context));

        $prompt = "# TASK: Write a Community Event Description\n\n";
        $prompt .= "Create an engaging event description using ALL the details provided below. ";
        $prompt .= "The description should make people excited to attend.\n\n";

        $prompt .= "## MANDATORY INFORMATION TO INCORPORATE\n\n";
        $prompt .= "**Event Title:** \"{$title}\"\n";
        $prompt .= "- This is the event's name. Use it as the core theme.\n\n";

        if (!empty($context['category'])) {
            $prompt .= "**Category:** {$context['category']}\n";
            $prompt .= "- This tells you what type of event it is. Match the tone and vocabulary.\n\n";
        }

        if (!empty($context['location'])) {
            $prompt .= "**Location:** {$context['location']}\n";
            $prompt .= "- Mention or reference the location naturally in the description.\n\n";
        }

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

        if (!empty($context['group_name'])) {
            $prompt .= "**Hosted by:** {$context['group_name']}\n";
            $prompt .= "- This is the community hub organizing the event. Mention it.\n\n";
        }

        if (!empty($context['sdg_goals']) && is_array($context['sdg_goals'])) {
            $prompt .= "**Social Impact:** " . implode(', ', $context['sdg_goals']) . "\n";
            $prompt .= "- Subtly weave in how this event contributes to community well-being.\n\n";
        }

        $userContext = $this->getUserProfileContext($userId);
        if ($userContext) {
            $prompt .= "## ABOUT THE HOST\n{$userContext}\n";
            $prompt .= "Use this to add a personal touch to the invitation.\n\n";
        }

        if (!empty($context['existing_description'])) {
            $prompt .= "## EXISTING DRAFT TO IMPROVE\n";
            $prompt .= "The host wrote this draft:\n";
            $prompt .= "---\n{$context['existing_description']}\n---\n\n";
            $prompt .= "Enhance this while keeping their voice. Make it more engaging and complete.\n\n";
        }

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
}
