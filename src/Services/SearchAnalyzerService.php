<?php

namespace Nexus\Services;

use Nexus\Services\AI\AIServiceFactory;

/**
 * SearchAnalyzerService
 *
 * AI-powered search query analysis and understanding.
 * Extracts intent, entities, keywords, and generates optimized search strategies.
 */
class SearchAnalyzerService
{
    /**
     * Analyze search query and extract structured intent data
     *
     * @param string $query User's search query
     * @param array $userContext Optional user context (location, interests, etc.)
     * @return array Structured intent data
     */
    public function analyzeIntent(string $query, array $userContext = []): array
    {
        // For very short queries or single words, skip AI analysis
        if (strlen(trim($query)) < 3) {
            return $this->getBasicIntent($query);
        }

        // Check if AI is available
        if (!AIServiceFactory::isEnabled()) {
            return $this->getBasicIntent($query);
        }

        try {
            $prompt = $this->buildAnalysisPrompt($query, $userContext);

            $response = AIServiceFactory::chatWithFallback([
                ['role' => 'user', 'content' => $prompt]
            ]);

            $content = $response['content'] ?? '';

            // Try to extract JSON from the response
            $intent = $this->extractJsonFromResponse($content);

            if ($intent) {
                // Add the original query
                $intent['original_query'] = $query;
                $intent['ai_analyzed'] = true;
                return $intent;
            }
        } catch (\Exception $e) {
            error_log("Search analysis failed: " . $e->getMessage());
        }

        // Fallback to basic analysis
        return $this->getBasicIntent($query);
    }

    /**
     * Build the AI analysis prompt
     */
    private function buildAnalysisPrompt(string $query, array $userContext): string
    {
        $contextInfo = '';
        if (!empty($userContext['location'])) {
            $contextInfo .= "User Location: {$userContext['location']}\n";
        }
        if (!empty($userContext['interests'])) {
            $contextInfo .= "User Interests: " . implode(', ', $userContext['interests']) . "\n";
        }

        return "Analyze this search query and extract structured data. Return ONLY valid JSON, no other text.

Query: \"$query\"
$contextInfo
Extract and return JSON with these fields:
{
  \"intent\": \"service_request\" | \"find_person\" | \"browse_listings\" | \"find_group\" | \"general_search\",
  \"category\": \"string or null (listings category if applicable)\",
  \"location\": \"string or null (city/region if mentioned)\",
  \"keywords\": [\"array\", \"of\", \"main\", \"terms\"],
  \"expanded_keywords\": [\"synonyms\", \"related\", \"terms\"],
  \"urgency\": \"low\" | \"medium\" | \"high\",
  \"filters\": {
    \"type\": \"user|listing|group|all\",
    \"active_only\": true|false,
    \"nearby\": true|false,
    \"recent\": true|false
  }
}

Examples:
Query: \"I need help fixing my roof in Dublin\"
{\"intent\":\"service_request\",\"category\":\"services\",\"location\":\"Dublin\",\"keywords\":[\"roof\",\"fix\",\"repair\"],\"expanded_keywords\":[\"roofing\",\"roofer\",\"roof repair\",\"roof maintenance\"],\"urgency\":\"high\",\"filters\":{\"type\":\"listing\",\"active_only\":true,\"nearby\":true,\"recent\":false}}

Query: \"john smith\"
{\"intent\":\"find_person\",\"category\":null,\"location\":null,\"keywords\":[\"john\",\"smith\"],\"expanded_keywords\":[],\"urgency\":\"low\",\"filters\":{\"type\":\"user\",\"active_only\":false,\"nearby\":false,\"recent\":false}}

Now analyze: \"$query\"";
    }

    /**
     * Extract JSON from AI response (handles cases where AI adds extra text)
     */
    private function extractJsonFromResponse(string $content): ?array
    {
        // Try direct JSON decode first
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Try to find JSON in the response
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Basic intent analysis without AI (fallback)
     */
    private function getBasicIntent(string $query): array
    {
        $query = strtolower(trim($query));
        $words = preg_split('/\s+/', $query);

        // Detect intent from keywords
        $intent = 'general_search';
        $type = 'all';

        // Service request indicators
        if (preg_match('/\b(need|help|looking for|require|want)\b/i', $query)) {
            $intent = 'service_request';
            $type = 'listing';
        }

        // Person search indicators (name-like patterns)
        if (count($words) <= 3 && !preg_match('/\b(service|help|group|hub)\b/i', $query)) {
            // Might be a person's name
            if (preg_match('/^[A-Z][a-z]+(\s+[A-Z][a-z]+)*$/i', $query)) {
                $intent = 'find_person';
                $type = 'user';
            }
        }

        // Group/hub indicators
        if (preg_match('/\b(group|hub|community|club)\b/i', $query)) {
            $intent = 'find_group';
            $type = 'group';
        }

        // Extract location (common Irish cities)
        $location = null;
        $cities = ['dublin', 'cork', 'galway', 'limerick', 'waterford', 'drogheda', 'dundalk', 'bray', 'navan', 'kilkenny'];
        foreach ($cities as $city) {
            if (stripos($query, $city) !== false) {
                $location = ucfirst($city);
                break;
            }
        }

        return [
            'intent' => $intent,
            'category' => null,
            'location' => $location,
            'keywords' => $words,
            'expanded_keywords' => [],
            'urgency' => 'medium',
            'filters' => [
                'type' => $type,
                'active_only' => false,
                'nearby' => false,
                'recent' => false
            ],
            'original_query' => $query,
            'ai_analyzed' => false
        ];
    }

    /**
     * Spell check and correction suggestions
     *
     * @param string $query
     * @return array ['corrected' => string|null, 'suggestions' => array]
     */
    public function checkSpelling(string $query): array
    {
        // Common typos dictionary for platform-specific terms
        $corrections = [
            'plubming' => 'plumbing',
            'plumer' => 'plumber',
            'electrisial' => 'electrical',
            'electrican' => 'electrician',
            'rofe' => 'roof',
            'roofr' => 'roofer',
            'gardning' => 'gardening',
            'gardener' => 'gardener',
            'carpinter' => 'carpenter',
            'carpentary' => 'carpentry',
            'paiter' => 'painter',
            'paintng' => 'painting',
            'comunity' => 'community',
            'neigborhood' => 'neighborhood',
            'neighbour' => 'neighbour',
            'servise' => 'service',
            'maintenence' => 'maintenance',
            'repare' => 'repair',
        ];

        $words = preg_split('/\s+/', strtolower($query));
        $corrected = [];
        $hasCorrection = false;

        foreach ($words as $word) {
            if (isset($corrections[$word])) {
                $corrected[] = $corrections[$word];
                $hasCorrection = true;
            } else {
                $corrected[] = $word;
            }
        }

        if (!$hasCorrection) {
            // Try AI-based spell check for complex cases
            if (AIServiceFactory::isEnabled()) {
                try {
                    $response = AIServiceFactory::chatWithFallback([
                        ['role' => 'user', 'content' => "If this search query has spelling errors, return ONLY the corrected version. If it's correct, return ONLY the word 'CORRECT'. Query: \"$query\""]
                    ], ['max_tokens' => 50]);

                    $suggestion = trim($response['content'] ?? '');

                    if ($suggestion && $suggestion !== 'CORRECT' && strtolower($suggestion) !== strtolower($query)) {
                        return [
                            'corrected' => $suggestion,
                            'suggestions' => [$suggestion]
                        ];
                    }
                } catch (\Exception $e) {
                    // AI not available, continue
                }
            }

            return [
                'corrected' => null,
                'suggestions' => []
            ];
        }

        return [
            'corrected' => implode(' ', $corrected),
            'suggestions' => [implode(' ', $corrected)]
        ];
    }

    /**
     * Expand query with synonyms and related terms
     */
    public function expandQuery(string $query, array $intent): array
    {
        $expanded = [$query];

        // Use AI-provided expanded keywords if available
        if (!empty($intent['expanded_keywords'])) {
            $expanded = array_merge($expanded, $intent['expanded_keywords']);
        }

        // Add manual expansions for common terms
        $synonyms = [
            'fix' => ['repair', 'mend', 'restore'],
            'repair' => ['fix', 'mend', 'restore'],
            'help' => ['assistance', 'support', 'aid'],
            'find' => ['search', 'locate', 'discover'],
            'buy' => ['purchase', 'acquire', 'get'],
            'sell' => ['offer', 'provide', 'supply'],
        ];

        $words = preg_split('/\s+/', strtolower($query));
        foreach ($words as $word) {
            if (isset($synonyms[$word])) {
                $expanded = array_merge($expanded, $synonyms[$word]);
            }
        }

        return array_unique($expanded);
    }
}
