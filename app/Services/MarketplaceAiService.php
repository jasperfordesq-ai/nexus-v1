<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\MarketplaceListing;
use Illuminate\Support\Facades\Log;

/**
 * MarketplaceAiService — AI-powered features for the marketplace module.
 *
 * NEXUS differentiator: sellers get AI-generated auto-replies to buyer questions
 * and AI-suggested descriptions. Leverages AiChatService for OpenAI calls.
 */
class MarketplaceAiService
{
    public function __construct(
        private readonly AiChatService $aiChatService,
    ) {}

    /**
     * Generate an AI auto-reply for a seller based on listing details and buyer message.
     *
     * The AI considers the listing's title, description, price, condition, location,
     * and delivery method to craft a contextual, helpful reply to the buyer's question.
     *
     * @param int $listingId
     * @param string $buyerMessage The buyer's incoming question/message
     * @return string The generated reply text
     *
     * @throws \RuntimeException If listing not found or AI service fails
     */
    public function generateAutoReply(int $listingId, string $buyerMessage): string
    {
        $listing = MarketplaceListing::query()->find($listingId);

        if (!$listing) {
            throw new \RuntimeException('Listing not found');
        }

        $listingContext = $this->buildListingContext($listing);

        $systemPrompt = <<<PROMPT
You are a helpful marketplace assistant for a community timebanking platform called NEXUS.
A buyer has sent a message about a listing. Generate a polite, helpful reply from the seller's perspective.

LISTING DETAILS:
{$listingContext}

RULES:
- Be friendly and professional
- Answer based ONLY on the listing information provided
- If the question cannot be answered from the listing details, suggest the buyer ask the seller directly
- Keep the reply concise (2-4 sentences)
- Do not make up information not in the listing
- If the listing accepts time credits, mention that as an option
- Do not include greetings like "Hi" or sign-offs — just the reply body
PROMPT;

        $result = $this->aiChatService->chat(0, $buyerMessage, [
            'system_prompt' => $systemPrompt,
            'model' => 'gpt-4o-mini',
            'max_tokens' => 256,
        ]);

        if ($result['error'] ?? false) {
            Log::warning('MarketplaceAiService::generateAutoReply failed', [
                'listing_id' => $listingId,
                'error' => $result['reply'],
            ]);
            throw new \RuntimeException('Failed to generate auto-reply');
        }

        return $result['reply'];
    }

    /**
     * Generate an AI-suggested description for a marketplace listing.
     *
     * Extracted from the controller to a reusable service method.
     *
     * @param string $title The listing title
     * @param string|null $category Optional category name
     * @param string|null $condition Optional item condition
     * @return string The generated description
     *
     * @throws \RuntimeException If AI service fails
     */
    public function suggestDescription(string $title, ?string $category = null, ?string $condition = null): string
    {
        $contextParts = ["Item: {$title}"];
        if ($category) {
            $contextParts[] = "Category: {$category}";
        }
        if ($condition) {
            $contextParts[] = "Condition: {$condition}";
        }
        $context = implode("\n", $contextParts);

        $systemPrompt = <<<PROMPT
You are a marketplace listing assistant for a community platform.
Generate a compelling, honest product description for a marketplace listing.

{$context}

RULES:
- Write 3-5 sentences
- Be descriptive but honest
- Highlight key selling points
- Mention condition naturally if provided
- Do not exaggerate or make claims
- Use a friendly, community-oriented tone
- Do not use markdown formatting
PROMPT;

        $result = $this->aiChatService->chat(0, "Write a marketplace listing description for: {$title}", [
            'system_prompt' => $systemPrompt,
            'model' => 'gpt-4o-mini',
            'max_tokens' => 512,
        ]);

        if ($result['error'] ?? false) {
            throw new \RuntimeException('Failed to generate description');
        }

        return $result['reply'];
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    /**
     * Build a textual context string from listing attributes for the AI prompt.
     */
    private function buildListingContext(MarketplaceListing $listing): string
    {
        $parts = [];
        $parts[] = "Title: {$listing->title}";

        if ($listing->description) {
            // Truncate long descriptions to avoid token waste
            $desc = mb_substr($listing->description, 0, 500);
            $parts[] = "Description: {$desc}";
        }

        if ($listing->price !== null) {
            $parts[] = "Price: {$listing->price_currency} {$listing->price}";
        }

        if ($listing->time_credit_price !== null && $listing->time_credit_price > 0) {
            $parts[] = "Time Credit Price: {$listing->time_credit_price} TC";
        }

        if ($listing->price_type) {
            $parts[] = "Price Type: {$listing->price_type}";
        }

        if ($listing->condition) {
            $parts[] = "Condition: {$listing->condition}";
        }

        if ($listing->location) {
            $parts[] = "Location: {$listing->location}";
        }

        if ($listing->delivery_method) {
            $parts[] = "Delivery: {$listing->delivery_method}";
        }

        if ($listing->quantity > 1) {
            $parts[] = "Quantity available: {$listing->quantity}";
        }

        return implode("\n", $parts);
    }
}
