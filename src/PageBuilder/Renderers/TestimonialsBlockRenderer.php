<?php
/**
 * Testimonials Block Renderer
 *
 * Renders customer testimonials/reviews
 */

namespace Nexus\PageBuilder\Renderers;

class TestimonialsBlockRenderer implements BlockRendererInterface
{
    public function render(array $data): string
    {
        $title = htmlspecialchars($data['title'] ?? 'What Our Customers Say');
        $testimonials = $data['testimonials'] ?? [];
        $columns = (int)($data['columns'] ?? 3);
        $style = htmlspecialchars($data['style'] ?? 'cards');

        if (empty($testimonials)) {
            return '<div class="pb-testimonials-empty">No testimonials added yet.</div>';
        }

        $html = '<div class="pb-testimonials pb-testimonials-' . $style . '">';

        if ($title) {
            $html .= '<h2 class="pb-testimonials-title">' . $title . '</h2>';
        }

        $html .= '<div class="pb-testimonials-grid columns-' . $columns . '">';

        foreach ($testimonials as $testimonial) {
            $quote = htmlspecialchars($testimonial['quote'] ?? '');
            $name = htmlspecialchars($testimonial['name'] ?? '');
            $position = htmlspecialchars($testimonial['position'] ?? '');
            $company = htmlspecialchars($testimonial['company'] ?? '');
            $avatar = htmlspecialchars($testimonial['avatar'] ?? '');
            $rating = (int)($testimonial['rating'] ?? 0);

            if (empty($quote)) {
                continue;
            }

            $html .= '<div class="pb-testimonial-card">';

            // Rating stars (if provided)
            if ($rating > 0 && $rating <= 5) {
                $html .= '<div class="pb-testimonial-rating">';
                for ($i = 1; $i <= 5; $i++) {
                    $starClass = $i <= $rating ? 'fa-solid' : 'fa-regular';
                    $html .= '<i class="' . $starClass . ' fa-star"></i>';
                }
                $html .= '</div>';
            }

            // Quote
            $html .= '<div class="pb-testimonial-quote">';
            $html .= '<i class="fa-solid fa-quote-left pb-quote-icon"></i>';
            $html .= '<p>' . $quote . '</p>';
            $html .= '</div>';

            // Author info
            $html .= '<div class="pb-testimonial-author">';

            if ($avatar) {
                $html .= '<img src="' . $avatar . '" alt="' . $name . '" class="pb-testimonial-avatar" loading="lazy">';
            }

            $html .= '<div class="pb-testimonial-info">';
            if ($name) {
                $html .= '<div class="pb-testimonial-name">' . $name . '</div>';
            }
            if ($position || $company) {
                $html .= '<div class="pb-testimonial-meta">';
                if ($position) {
                    $html .= $position;
                }
                if ($position && $company) {
                    $html .= ' at ';
                }
                if ($company) {
                    $html .= $company;
                }
                $html .= '</div>';
            }
            $html .= '</div>';

            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    public function validate(array $data): bool
    {
        return !empty($data['testimonials']) && is_array($data['testimonials']);
    }
}
