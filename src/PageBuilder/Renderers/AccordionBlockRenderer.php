<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Accordion Block Renderer
 *
 * Renders collapsible accordion/FAQ sections
 */

namespace Nexus\PageBuilder\Renderers;

class AccordionBlockRenderer implements BlockRendererInterface
{
    public function render(array $data): string
    {
        $title = htmlspecialchars($data['title'] ?? 'Frequently Asked Questions');
        $items = $data['items'] ?? [];
        $style = htmlspecialchars($data['style'] ?? 'default');
        $allowMultiple = (bool)($data['allowMultiple'] ?? false);

        if (empty($items)) {
            return '<!-- No accordion items -->';
        }

        // Generate unique ID for this accordion instance
        $accordionId = 'accordion-' . uniqid();

        $html = '<div class="pb-accordion pb-accordion-' . $style . '" data-allow-multiple="' . ($allowMultiple ? 'true' : 'false') . '">';

        if ($title) {
            $html .= '<h2 class="pb-accordion-title">' . $title . '</h2>';
        }

        $html .= '<div class="pb-accordion-items" id="' . $accordionId . '">';

        foreach ($items as $index => $item) {
            $question = htmlspecialchars($item['question'] ?? '');
            $answer = $item['answer'] ?? '';
            $itemId = $accordionId . '-item-' . $index;

            if (empty($question)) {
                continue;
            }

            $html .= '<div class="pb-accordion-item">';
            $html .= '<button class="pb-accordion-header" aria-expanded="false" aria-controls="' . $itemId . '">';
            $html .= '<span class="pb-accordion-question">' . $question . '</span>';
            $html .= '<i class="pb-accordion-icon fa-solid fa-chevron-down"></i>';
            $html .= '</button>';
            $html .= '<div class="pb-accordion-content" id="' . $itemId . '" style="display: none;">';
            $html .= '<div class="pb-accordion-answer">' . $answer . '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';

        // Add inline JavaScript for accordion functionality
        $html .= <<<SCRIPT
<script>
(function() {
    const accordion = document.getElementById('{$accordionId}');
    if (!accordion) return;

    const allowMultiple = accordion.parentElement.dataset.allowMultiple === 'true';
    const headers = accordion.querySelectorAll('.pb-accordion-header');

    headers.forEach(header => {
        header.addEventListener('click', function() {
            const content = this.nextElementSibling;
            const icon = this.querySelector('.pb-accordion-icon');
            const isExpanded = this.getAttribute('aria-expanded') === 'true';

            // Close others if allowMultiple is false
            if (!allowMultiple && !isExpanded) {
                headers.forEach(h => {
                    if (h !== this) {
                        h.setAttribute('aria-expanded', 'false');
                        h.nextElementSibling.style.display = 'none';
                        h.querySelector('.pb-accordion-icon').style.transform = 'rotate(0deg)';
                    }
                });
            }

            // Toggle current
            if (isExpanded) {
                this.setAttribute('aria-expanded', 'false');
                content.style.display = 'none';
                icon.style.transform = 'rotate(0deg)';
            } else {
                this.setAttribute('aria-expanded', 'true');
                content.style.display = 'block';
                icon.style.transform = 'rotate(180deg)';
            }
        });
    });
})();
</script>
SCRIPT;

        return $html;
    }

    public function validate(array $data): bool
    {
        return !empty($data['items']) && is_array($data['items']);
    }
}
