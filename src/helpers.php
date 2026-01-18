<?php

/**
 * Global Helper Functions
 *
 * This file contains global helper functions used throughout the application.
 */

if (!function_exists('layout')) {
    /**
     * Get the current active layout
     *
     * Centralized layout detection function that wraps LayoutHelper::get()
     * for convenience throughout the application.
     *
     * @return string The active layout name (modern or civicone)
     */
    function layout(): string
    {
        return \Nexus\Services\LayoutHelper::get();
    }
}

if (!function_exists('is_civicone')) {
    /**
     * Check if the current layout is CivicOne
     *
     * @return bool True if the current layout is civicone, false otherwise
     */
    function is_civicone(): bool
    {
        return layout() === 'civicone';
    }
}

if (!function_exists('is_modern')) {
    /**
     * Check if the current layout is Modern
     *
     * @return bool True if the current layout is modern, false otherwise
     */
    function is_modern(): bool
    {
        return layout() === 'modern';
    }
}

if (!function_exists('webp_image')) {
    /**
     * Generate optimized image HTML with WebP support
     *
     * Returns a <picture> tag with WebP source and fallback for browsers
     * that don't support WebP. Automatically adds lazy loading.
     *
     * @param string $imagePath Original image path (e.g., "/uploads/photo.jpg")
     * @param string $alt Alt text for accessibility
     * @param string $class CSS classes to apply to the img element
     * @param array $attributes Additional HTML attributes (width, height, etc.)
     * @return string HTML <picture> tag or <img> tag if WebP doesn't exist
     *
     * @example
     * // Basic usage
     * <?= webp_image($post->image_url, $post->title) ?>
     *
     * // With CSS class
     * <?= webp_image($user->avatar, $user->name, 'avatar rounded-full') ?>
     *
     * // With additional attributes
     * <?= webp_image($image, 'Photo', 'hero-img', ['width' => 800, 'height' => 600]) ?>
     */
    function webp_image(string $imagePath, string $alt = '', string $class = '', array $attributes = []): string
    {
        return \Nexus\Helpers\ImageHelper::webp($imagePath, $alt, $class, $attributes);
    }
}

if (!function_exists('webp_avatar')) {
    /**
     * Generate optimized avatar image
     *
     * @param string|null $avatarPath Avatar image path (null for default)
     * @param string $userName User's name for alt text
     * @param int $size Size in pixels (default: 40)
     * @return string HTML for avatar with WebP optimization
     */
    function webp_avatar(?string $avatarPath, string $userName = 'User', int $size = 40): string
    {
        return \Nexus\Helpers\ImageHelper::avatar($avatarPath, $userName, $size);
    }
}

