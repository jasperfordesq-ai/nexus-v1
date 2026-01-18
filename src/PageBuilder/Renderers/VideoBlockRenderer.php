<?php
/**
 * Video Block Renderer
 *
 * Renders embedded videos (YouTube, Vimeo, native)
 */

namespace Nexus\PageBuilder\Renderers;

class VideoBlockRenderer implements BlockRendererInterface
{
    public function render(array $data): string
    {
        $videoUrl = $data['videoUrl'] ?? '';
        $width = htmlspecialchars($data['width'] ?? 'normal');
        $aspectRatio = htmlspecialchars($data['aspectRatio'] ?? '16-9');

        // Width classes
        $widthClass = [
            'narrow' => 'pb-video-width-narrow',
            'normal' => 'pb-video-width-normal',
            'wide' => 'pb-video-width-wide',
            'full' => 'pb-video-width-full'
        ][$width] ?? 'pb-video-width-normal';

        // Aspect ratio classes
        $aspectClass = [
            '16-9' => 'pb-video-aspect-16-9',
            '4-3' => 'pb-video-aspect-4-3',
            '1-1' => 'pb-video-aspect-1-1'
        ][$aspectRatio] ?? 'pb-video-aspect-16-9';

        $html = '<div class="pb-video-block ' . $widthClass . '">';
        $html .= '<div class="pb-video-container ' . $aspectClass . '">';

        // Detect video type and generate embed
        if (strpos($videoUrl, 'youtube.com') !== false || strpos($videoUrl, 'youtu.be') !== false) {
            $videoId = $this->extractYouTubeId($videoUrl);
            if ($videoId) {
                $html .= '<iframe src="https://www.youtube.com/embed/' . htmlspecialchars($videoId) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
            }
        } elseif (strpos($videoUrl, 'vimeo.com') !== false) {
            $videoId = $this->extractVimeoId($videoUrl);
            if ($videoId) {
                $html .= '<iframe src="https://player.vimeo.com/video/' . htmlspecialchars($videoId) . '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
            }
        } else {
            // Native HTML5 video
            $html .= '<video controls><source src="' . htmlspecialchars($videoUrl) . '"></video>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    public function validate(array $data): bool
    {
        return !empty($data['videoUrl']);
    }

    private function extractYouTubeId($url): ?string
    {
        if (preg_match('/youtube\.com\/watch\?v=([^&]+)/', $url, $matches)) {
            return $matches[1];
        }
        if (preg_match('/youtu\.be\/([^?]+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractVimeoId($url): ?string
    {
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
