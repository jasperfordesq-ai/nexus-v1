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
     * Legacy CivicOne theme has been removed. Always returns 'modern'.
     *
     * @return string Always 'modern'
     */
    function layout(): string
    {
        return 'modern';
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
     * @param array $attributes Optional HTML attributes (e.g., ['loading' => 'eager'] for above-fold)
     * @return string HTML for avatar with WebP optimization
     */
    function webp_avatar(?string $avatarPath, string $userName = 'User', int $size = 40, array $attributes = []): string
    {
        return \Nexus\Helpers\ImageHelper::avatar($avatarPath, $userName, $size, $attributes);
    }
}

