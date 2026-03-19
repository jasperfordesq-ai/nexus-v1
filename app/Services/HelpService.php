<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * HelpService — Eloquent-based service for help/FAQ operations.
 *
 * FAQs are stored in help_faqs with tenant fallback to global (tenant_id = 0).
 */
class HelpService
{
    /**
     * Get published FAQs for a tenant, grouped by category.
     * Falls back to global defaults (tenant_id = 0) if tenant has none.
     *
     * @return array Array of { category: string, faqs: { id, question, answer }[] }
     */
    public function getFaqs(int $tenantId, ?int $categoryId = null, ?string $search = null): array
    {
        $query = DB::table('help_faqs')
            ->where('tenant_id', $tenantId)
            ->where('is_published', 1);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($search) {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('question', 'LIKE', $term)
                  ->orWhere('answer', 'LIKE', $term);
            });
        }

        $faqs = $query->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();

        // Fallback to global defaults
        if (empty($faqs) && ! $categoryId && ! $search) {
            $faqs = DB::table('help_faqs')
                ->where('tenant_id', 0)
                ->where('is_published', 1)
                ->orderBy('category')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->all();
        }

        // Group by category
        $grouped = [];
        foreach ($faqs as $faq) {
            $cat = $faq->category ?? 'General';
            if (! isset($grouped[$cat])) {
                $grouped[$cat] = ['category' => $cat, 'faqs' => []];
            }
            $grouped[$cat]['faqs'][] = [
                'id'       => (int) $faq->id,
                'question' => $faq->question,
                'answer'   => $faq->answer,
            ];
        }

        return array_values($grouped);
    }
}
