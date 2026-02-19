<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Stats Block Renderer
 *
 * Renders animated statistics/counters
 */

namespace Nexus\PageBuilder\Renderers;

class StatsBlockRenderer implements BlockRendererInterface
{
    public function render(array $data): string
    {
        $stats = $data['stats'] ?? [];
        $columns = (int)($data['columns'] ?? 4);
        $style = htmlspecialchars($data['style'] ?? 'default');
        $animated = (bool)($data['animated'] ?? true);

        if (empty($stats)) {
            return '<div class="pb-stats-empty">No stats added yet.</div>';
        }

        $statsId = 'stats-' . uniqid();

        $html = '<div class="pb-stats pb-stats-' . $style . '" id="' . $statsId . '">';
        $html .= '<div class="pb-stats-grid columns-' . $columns . '">';

        foreach ($stats as $stat) {
            $number = htmlspecialchars($stat['number'] ?? '0');
            $label = htmlspecialchars($stat['label'] ?? '');
            $suffix = htmlspecialchars($stat['suffix'] ?? '');
            $prefix = htmlspecialchars($stat['prefix'] ?? '');
            $icon = htmlspecialchars($stat['icon'] ?? '');
            $color = htmlspecialchars($stat['color'] ?? 'primary');

            if (empty($label)) {
                continue;
            }

            $html .= '<div class="pb-stat-item pb-stat-' . $color . '">';

            if ($icon) {
                $html .= '<div class="pb-stat-icon">';
                $html .= '<i class="fa-solid ' . $icon . '"></i>';
                $html .= '</div>';
            }

            $html .= '<div class="pb-stat-content">';
            $html .= '<div class="pb-stat-number">';
            if ($prefix) {
                $html .= '<span class="pb-stat-prefix">' . $prefix . '</span>';
            }
            if ($animated) {
                $html .= '<span class="pb-stat-counter" data-target="' . $number . '">0</span>';
            } else {
                $html .= '<span>' . $number . '</span>';
            }
            if ($suffix) {
                $html .= '<span class="pb-stat-suffix">' . $suffix . '</span>';
            }
            $html .= '</div>';
            $html .= '<div class="pb-stat-label">' . $label . '</div>';
            $html .= '</div>';

            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';

        // Add animation script if enabled
        if ($animated) {
            $html .= <<<SCRIPT
<script>
(function() {
    const statsBlock = document.getElementById('{$statsId}');
    if (!statsBlock) return;

    const counters = statsBlock.querySelectorAll('.pb-stat-counter');
    let animated = false;

    function animateCounters() {
        if (animated) return;
        animated = true;

        counters.forEach(counter => {
            const target = parseInt(counter.dataset.target);
            const duration = 2000; // 2 seconds
            const increment = target / (duration / 16); // 60fps
            let current = 0;

            const updateCounter = () => {
                current += increment;
                if (current < target) {
                    counter.textContent = Math.floor(current).toLocaleString();
                    requestAnimationFrame(updateCounter);
                } else {
                    counter.textContent = target.toLocaleString();
                }
            };

            updateCounter();
        });
    }

    // Trigger animation when in viewport
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounters();
                observer.disconnect();
            }
        });
    }, { threshold: 0.5 });

    observer.observe(statsBlock);
})();
</script>
SCRIPT;
        }

        return $html;
    }

    public function validate(array $data): bool
    {
        return !empty($data['stats']) && is_array($data['stats']);
    }
}
