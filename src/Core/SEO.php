<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

use Nexus\Models\SeoMetadata;
use Nexus\Services\SchemaService;

class SEO
{
    protected static $title = 'Project NEXUS';
    protected static $description = 'Community currency for a better future.';
    protected static $image = '/assets/images/og-default.jpg';
    protected static $url = '';
    protected static $meta = [];
    protected static $schemas = [];
    protected static $canonical = '';

    // Cache for global SEO settings
    private static $globalCache = null;

    // Flag to control auto-schema generation
    private static $autoSchemaEnabled = true;

    /**
     * Load SEO settings from Database (Global + Entity Override)
     */
    public static function load(string $entityType = 'global', int $entityId = null)
    {
        // 1. Load Tenant-specific SEO first (from tenants table)
        self::loadFromTenant();

        // 2. Load Global Defaults from seo_metadata (can override tenant if set)
        $global = self::loadGlobalCached();
        if ($global) {
            if (!empty($global['meta_title'])) self::setTitle($global['meta_title']);
            if (!empty($global['meta_description'])) self::setDescription($global['meta_description']);
            if (!empty($global['og_image_url'])) self::setImage($global['og_image_url']);
            if (!empty($global['noindex'])) self::addMeta('robots', 'noindex, nofollow');
        }

        // 3. Load Entity Specific (if not just loading global)
        if ($entityType !== 'global') {
            $entity = SeoMetadata::get($entityType, $entityId);
            if ($entity) {
                if (!empty($entity['meta_title'])) self::setTitle($entity['meta_title']);
                if (!empty($entity['meta_description'])) self::setDescription($entity['meta_description']);
                if (!empty($entity['meta_keywords'])) self::addMeta('keywords', $entity['meta_keywords']);
                if (!empty($entity['canonical_url'])) self::setCanonical($entity['canonical_url']);
                if (!empty($entity['og_image_url'])) self::setImage($entity['og_image_url']);
                if (!empty($entity['noindex'])) self::addMeta('robots', 'noindex, nofollow');
            }
        }
    }

    /**
     * Load SEO settings directly from the tenant record
     * This pulls meta_title, meta_description, etc. from the tenants table
     */
    public static function loadFromTenant(): void
    {
        try {
            $tenant = TenantContext::get();
            if (!$tenant) return;

            // Set title from tenant (priority: meta_title > name)
            if (!empty($tenant['meta_title'])) {
                self::setTitle($tenant['meta_title']);
            } elseif (!empty($tenant['name'])) {
                self::setTitle($tenant['name']);
            }

            // Set description from tenant (priority: meta_description > tagline > description)
            if (!empty($tenant['meta_description'])) {
                self::setDescription($tenant['meta_description']);
            } elseif (!empty($tenant['tagline'])) {
                self::setDescription($tenant['tagline']);
            } elseif (!empty($tenant['description'])) {
                self::setDescription(substr($tenant['description'], 0, 160));
            }

            // Set OG image if specified
            if (!empty($tenant['og_image_url'])) {
                self::setImage($tenant['og_image_url']);
            }
        } catch (\Exception $e) {
            // Silently fail - don't break page rendering
        }
    }

    /**
     * Get tenant's hero content for homepage
     * Returns array with h1_headline and hero_intro, with smart fallbacks
     *
     * @return array ['h1' => string, 'intro' => string]
     */
    public static function getTenantHeroContent(): array
    {
        try {
            $tenant = TenantContext::get();
            if (!$tenant) {
                return [
                    'h1' => 'Community Exchange',
                    'intro' => 'Share skills, build community, and exchange time.'
                ];
            }

            // H1: priority is h1_headline > name
            $h1 = !empty($tenant['h1_headline'])
                ? $tenant['h1_headline']
                : ($tenant['name'] ?? 'Community Exchange');

            // Intro: priority is hero_intro > tagline > description > default
            $intro = '';
            if (!empty($tenant['hero_intro'])) {
                $intro = $tenant['hero_intro'];
            } elseif (!empty($tenant['tagline'])) {
                $intro = $tenant['tagline'];
            } elseif (!empty($tenant['description'])) {
                $intro = substr($tenant['description'], 0, 200);
            } else {
                $intro = 'Share skills, build community, and exchange time.';
            }

            return ['h1' => $h1, 'intro' => $intro];
        } catch (\Exception $e) {
            return [
                'h1' => 'Community Exchange',
                'intro' => 'Share skills, build community, and exchange time.'
            ];
        }
    }

    /**
     * Load global SEO settings with caching
     */
    private static function loadGlobalCached(): ?array
    {
        if (self::$globalCache === null) {
            self::$globalCache = SeoMetadata::get('global', null) ?: [];
        }
        return self::$globalCache ?: null;
    }

    /**
     * Clear the global cache (useful after saving new settings)
     */
    public static function clearCache(): void
    {
        self::$globalCache = null;
    }

    /**
     * Auto-generate description from content if not already set
     *
     * @param string $content Raw content to extract description from
     * @param bool $force Force override even if description exists
     */
    public static function autoDescription(string $content, bool $force = false): void
    {
        if ($force || empty(self::$meta['description']) || self::$meta['description'] === self::$description) {
            $generated = self::generateDescription($content);
            if ($generated) {
                self::setDescription($generated);
            }
        }
    }

    /**
     * Generate a clean meta description from content
     */
    public static function generateDescription(string $content): string
    {
        // Strip HTML tags
        $clean = strip_tags($content);

        // Decode HTML entities
        $clean = html_entity_decode($clean, ENT_QUOTES, 'UTF-8');

        // Normalize whitespace
        $clean = preg_replace('/\s+/', ' ', $clean);
        $clean = trim($clean);

        // Empty check
        if (empty($clean)) {
            return '';
        }

        // Limit to 155 characters, ending at word boundary
        if (strlen($clean) > 155) {
            $clean = substr($clean, 0, 155);
            $lastSpace = strrpos($clean, ' ');
            if ($lastSpace !== false && $lastSpace > 100) {
                $clean = substr($clean, 0, $lastSpace);
            }
            $clean = rtrim($clean, '.,!?:;') . '...';
        }

        return $clean;
    }

    /**
     * Add a schema from SchemaService
     *
     * @param string $type Schema type: 'article', 'event', 'offer', 'localBusiness', 'organization', 'webSite', 'breadcrumbs', 'faqPage'
     * @param array $data Entity data
     * @param array|null $author Author/organizer data
     */
    public static function autoSchema(string $type, array $data, ?array $author = null): void
    {
        if (!self::$autoSchemaEnabled) {
            return;
        }

        try {
            $schema = match ($type) {
                'article' => SchemaService::article($data, $author),
                'event' => SchemaService::event($data, $author),
                'offer', 'listing' => SchemaService::offer($data, $author),
                'localBusiness', 'group' => SchemaService::localBusiness($data),
                'organization' => SchemaService::organization(),
                'webSite' => SchemaService::webSite(),
                'breadcrumbs' => SchemaService::breadcrumbs($data),
                'faqPage' => SchemaService::faqPage($data),
                'person' => SchemaService::person($data),
                default => null
            };

            if ($schema) {
                self::addSchema($schema);
            }
        } catch (\Exception $e) {
            // Silently fail - don't break page rendering for schema errors
        }
    }

    /**
     * Enable or disable auto-schema generation
     */
    public static function setAutoSchema(bool $enabled): void
    {
        self::$autoSchemaEnabled = $enabled;
    }

    /**
     * Add Organization and WebSite schemas (typically for homepage)
     */
    public static function addSiteSchemas(): void
    {
        self::autoSchema('organization', []);
        self::autoSchema('webSite', []);
    }

    /**
     * Add breadcrumb schema
     *
     * @param array $crumbs Array of ['name' => 'Page', 'url' => '/path']
     */
    public static function addBreadcrumbs(array $crumbs): void
    {
        if (!empty($crumbs)) {
            self::autoSchema('breadcrumbs', $crumbs);
        }
    }

    /**
     * Set the page title.
     * @param string $title
     */
    public static function setTitle(string $title)
    {
        self::$title = $title;
        self::$meta['og:title'] = $title;
        self::$meta['twitter:title'] = $title;
    }

    /**
     * Get current title
     */
    public static function getTitle(): string
    {
        return self::$title;
    }

    /**
     * Set the page description.
     * @param string $description
     */
    public static function setDescription(string $description)
    {
        // Strip tags and limit length
        $cleanDesc = substr(strip_tags($description), 0, 160);
        self::$description = $cleanDesc;
        self::$meta['description'] = $cleanDesc;
        self::$meta['og:description'] = $cleanDesc;
        self::$meta['twitter:description'] = $cleanDesc;
    }

    /**
     * Get current description
     */
    public static function getDescription(): string
    {
        return self::$description;
    }

    /**
     * Set the social share image.
     * @param string $imageUrl Relative or Absolute URL
     */
    public static function setImage(string $imageUrl)
    {
        // If relative, prepend host
        if (strpos($imageUrl, 'http') === false) {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $imageUrl = $protocol . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $imageUrl;
        }
        self::$image = $imageUrl;
        self::$meta['og:image'] = $imageUrl;
        self::$meta['twitter:image'] = $imageUrl;
    }

    /**
     * Set the Open Graph URL (and default canonical if not strictly set).
     * @param string $url
     */
    public static function setUrl(string $url)
    {
        self::$url = $url;
        self::$meta['og:url'] = $url;
    }

    /**
     * Explicitly set the Canonical URL.
     * @param string $url
     */
    public static function setCanonical(string $url)
    {
        self::$canonical = $url;
    }

    /**
     * Set the Open Graph type
     * @param string $type e.g., 'website', 'article', 'event'
     */
    public static function setType(string $type): void
    {
        self::$meta['og:type'] = $type;
    }

    /**
     * Add a JSON-LD structured data schema.
     * @param array $schema Assoc array representing the schema
     */
    public static function addSchema(array $schema)
    {
        if (empty($schema['@context'])) {
            $schema['@context'] = 'https://schema.org';
        }
        self::$schemas[] = $schema;
    }

    /**
     * Get all schemas (for debugging)
     */
    public static function getSchemas(): array
    {
        return self::$schemas;
    }

    /**
     * Clear all schemas
     */
    public static function clearSchemas(): void
    {
        self::$schemas = [];
    }

    /**
     * Add an arbitrary meta tag.
     * @param string $name
     * @param string $content
     */
    public static function addMeta(string $name, string $content)
    {
        self::$meta[$name] = $content;
    }

    /**
     * Get a meta tag value
     */
    public static function getMeta(string $name): ?string
    {
        return self::$meta[$name] ?? null;
    }

    /**
     * Reset all SEO data to defaults (useful for testing)
     */
    public static function reset(): void
    {
        self::$title = 'Project NEXUS';
        self::$description = 'Community currency for a better future.';
        self::$image = '/assets/images/og-default.jpg';
        self::$url = '';
        self::$meta = [];
        self::$schemas = [];
        self::$canonical = '';
        self::$globalCache = null;
    }

    /**
     * Render all SEO tags as HTML.
     * @return string
     */
    public static function render(): string
    {
        $html = "<!-- SEO Headers -->\n";

        // Title
        $html .= "    <title>" . htmlspecialchars(self::$title) . "</title>\n";

        // Standard Meta
        if (!isset(self::$meta['description'])) self::$meta['description'] = self::$description;

        // Open Graph Defaults
        if (!isset(self::$meta['og:title'])) self::$meta['og:title'] = self::$title;
        if (!isset(self::$meta['og:description'])) self::$meta['og:description'] = self::$description;
        if (!isset(self::$meta['og:image'])) self::$meta['og:image'] = self::$image;
        if (!isset(self::$meta['og:type'])) self::$meta['og:type'] = 'website';

        // Twitter Card Type
        if (!isset(self::$meta['twitter:card'])) self::$meta['twitter:card'] = 'summary_large_image';

        // URL Defaults
        if (empty(self::$url)) {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            self::$url = $protocol . "://" . $host . $uri;
        }
        self::$meta['og:url'] = self::$url;

        // Canonical Auto-Resolution
        if (empty(self::$canonical)) {
            // Default to the OG URL (current page) to prevent dupes via query params
            self::$canonical = strtok(self::$url, '?');
        }
        $html .= "    <link rel=\"canonical\" href=\"" . htmlspecialchars(self::$canonical) . "\">\n";

        // Render Meta Array
        foreach (self::$meta as $name => $content) {
            // Handle OpenGraph (property) vs Twitter (name) vs Standard (name)
            if (strpos($name, 'og:') === 0) {
                $attr = 'property';
            } elseif (strpos($name, 'twitter:') === 0) {
                $attr = 'name';
            } else {
                $attr = 'name';
            }
            $html .= "    <meta $attr=\"" . htmlspecialchars($name) . "\" content=\"" . htmlspecialchars($content) . "\">\n";
        }

        // Render Schemas (JSON-LD) - Each in its own script block for clarity
        if (!empty(self::$schemas)) {
            foreach (self::$schemas as $schema) {
                $html .= "    <script type=\"application/ld+json\">\n";
                $html .= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $html .= "\n    </script>\n";
            }
        }

        return $html;
    }
}
