<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\TenantContext;

/**
 * SchemaService - JSON-LD Structured Data Generator
 *
 * Generates Schema.org compliant JSON-LD for rich search results.
 * All methods return arrays ready for json_encode().
 */
class SchemaService
{
    /**
     * Organization Schema - Site-wide business/organization identity
     * Enhanced with geolocation support for local SEO
     */
    public static function organization(?array $tenant = null, ?array $config = null): array
    {
        $tenant = $tenant ?? TenantContext::get();
        $config = $config ?? self::getOrgConfig();
        $baseUrl = self::getBaseUrl();

        // Determine schema type based on service area
        // Local/Regional tenants get LocalBusiness for "near me" searches
        // National/International tenants get Organization
        $serviceArea = $tenant['service_area'] ?? 'national';
        $isLocal = in_array($serviceArea, ['local', 'regional']);

        $schema = [
            '@type' => $isLocal ? 'LocalBusiness' : 'Organization',
            '@id' => $baseUrl . '#organization',
            'name' => $tenant['name'] ?? 'Organization',
            'url' => $baseUrl,
        ];

        // Optional fields from config
        if (!empty($config['logo'])) {
            $schema['logo'] = [
                '@type' => 'ImageObject',
                'url' => self::absoluteUrl($config['logo']),
            ];
        }

        // Description - priority: meta_description > tagline > config description
        if (!empty($tenant['meta_description'])) {
            $schema['description'] = $tenant['meta_description'];
        } elseif (!empty($config['description'])) {
            $schema['description'] = $config['description'];
        } elseif (!empty($tenant['tagline'])) {
            $schema['description'] = $tenant['tagline'];
        }

        // Contact info from tenant table or config
        if (!empty($tenant['contact_email'])) {
            $schema['email'] = $tenant['contact_email'];
        } elseif (!empty($config['email'])) {
            $schema['email'] = $config['email'];
        }

        if (!empty($tenant['contact_phone'])) {
            $schema['telephone'] = $tenant['contact_phone'];
        } elseif (!empty($config['phone'])) {
            $schema['telephone'] = $config['phone'];
        }

        // Social profiles from tenant table
        $sameAs = [];
        if (!empty($tenant['social_facebook'])) $sameAs[] = $tenant['social_facebook'];
        if (!empty($tenant['social_twitter'])) $sameAs[] = $tenant['social_twitter'];
        if (!empty($tenant['social_linkedin'])) $sameAs[] = $tenant['social_linkedin'];
        if (!empty($tenant['social_instagram'])) $sameAs[] = $tenant['social_instagram'];
        if (!empty($tenant['social_youtube'])) $sameAs[] = $tenant['social_youtube'];
        // Fallback to config
        if (empty($sameAs)) {
            if (!empty($config['facebook'])) $sameAs[] = $config['facebook'];
            if (!empty($config['twitter'])) $sameAs[] = $config['twitter'];
            if (!empty($config['linkedin'])) $sameAs[] = $config['linkedin'];
            if (!empty($config['instagram'])) $sameAs[] = $config['instagram'];
            if (!empty($config['youtube'])) $sameAs[] = $config['youtube'];
        }
        if (!empty($sameAs)) {
            $schema['sameAs'] = $sameAs;
        }

        // Address - from tenant location fields or config
        if (!empty($tenant['location_name']) || !empty($tenant['country_code'])) {
            $address = ['@type' => 'PostalAddress'];

            if (!empty($tenant['location_name'])) {
                // Parse location name (e.g., "Dublin, Ireland")
                $parts = array_map('trim', explode(',', $tenant['location_name']));
                if (count($parts) >= 2) {
                    $address['addressLocality'] = $parts[0];
                    $address['addressCountry'] = $parts[count($parts) - 1];
                } else {
                    $address['addressLocality'] = $tenant['location_name'];
                }
            }

            if (!empty($tenant['country_code'])) {
                $address['addressCountry'] = $tenant['country_code'];
            }

            if (!empty($tenant['address'])) {
                $address['streetAddress'] = $tenant['address'];
            }

            $schema['address'] = $address;
        } elseif (!empty($config['address'])) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $config['address']['street'] ?? '',
                'addressLocality' => $config['address']['city'] ?? '',
                'addressRegion' => $config['address']['region'] ?? '',
                'postalCode' => $config['address']['postal_code'] ?? '',
                'addressCountry' => $config['address']['country'] ?? '',
            ];
        }

        // Geo coordinates - critical for "near me" searches
        if (!empty($tenant['latitude']) && !empty($tenant['longitude'])) {
            $schema['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $tenant['latitude'],
                'longitude' => (float) $tenant['longitude'],
            ];
        }

        // Area Served - based on service_area setting
        if (!empty($tenant['country_code']) && $serviceArea !== 'international') {
            $countryNames = self::getCountryNames();
            $countryName = $countryNames[$tenant['country_code']] ?? $tenant['country_code'];

            if ($serviceArea === 'national') {
                $schema['areaServed'] = [
                    '@type' => 'Country',
                    'name' => $countryName,
                ];
            } elseif ($serviceArea === 'regional' && !empty($tenant['location_name'])) {
                $schema['areaServed'] = [
                    '@type' => 'AdministrativeArea',
                    'name' => $tenant['location_name'],
                ];
            } elseif ($serviceArea === 'local' && !empty($tenant['location_name'])) {
                $schema['areaServed'] = [
                    '@type' => 'City',
                    'name' => explode(',', $tenant['location_name'])[0],
                ];
            }
        }

        return self::wrap($schema);
    }

    /**
     * Get country code to name mapping
     */
    private static function getCountryNames(): array
    {
        return [
            'IE' => 'Ireland',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'DE' => 'Germany',
            'FR' => 'France',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'PT' => 'Portugal',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'PL' => 'Poland',
            'AT' => 'Austria',
            'CH' => 'Switzerland',
        ];
    }

    /**
     * WebSite Schema - Site-wide with search action
     */
    public static function webSite(?array $tenant = null): array
    {
        $tenant = $tenant ?? TenantContext::get();
        $baseUrl = self::getBaseUrl();

        $schema = [
            '@type' => 'WebSite',
            '@id' => $baseUrl . '#website',
            'name' => $tenant['name'] ?? 'Website',
            'url' => $baseUrl,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $baseUrl . '/search?q={search_term_string}'
                ],
                'query-input' => 'required name=search_term_string'
            ]
        ];

        return self::wrap($schema);
    }

    /**
     * Article Schema - For blog posts
     */
    public static function article(array $post, ?array $author = null): array
    {
        $baseUrl = self::getBaseUrl();
        $url = $baseUrl . '/blog/' . ($post['slug'] ?? $post['id'] ?? 'article');

        $schema = [
            '@type' => 'Article',
            '@id' => $url . '#article',
            'headline' => self::truncate($post['title'] ?? 'Article', 110),
            'url' => $url,
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $url
            ],
        ];

        // Image
        if (!empty($post['featured_image'])) {
            $schema['image'] = [
                '@type' => 'ImageObject',
                'url' => self::absoluteUrl($post['featured_image']),
            ];
        }

        // Dates
        if (!empty($post['created_at'])) {
            $schema['datePublished'] = self::isoDate($post['created_at']);
        }
        if (!empty($post['updated_at'])) {
            $schema['dateModified'] = self::isoDate($post['updated_at']);
        }

        // Description
        if (!empty($post['excerpt'])) {
            $schema['description'] = self::truncate(strip_tags($post['excerpt']), 160);
        } elseif (!empty($post['content'])) {
            $schema['description'] = self::truncate(strip_tags($post['content']), 160);
        }

        // Author
        if ($author) {
            $schema['author'] = self::person($author);
        }

        // Publisher (Organization)
        $schema['publisher'] = [
            '@type' => 'Organization',
            '@id' => $baseUrl . '#organization',
        ];

        return self::wrap($schema);
    }

    /**
     * Event Schema - For community events
     */
    public static function event(array $event, ?array $organizer = null): array
    {
        $baseUrl = self::getBaseUrl();
        $url = $baseUrl . '/events/' . $event['id'];

        $schema = [
            '@type' => 'Event',
            '@id' => $url . '#event',
            'name' => $event['title'] ?? 'Event',
            'url' => $url,
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        ];

        // Start date (required)
        if (!empty($event['start_time'])) {
            $schema['startDate'] = self::isoDate($event['start_time']);
        } elseif (!empty($event['start_date'])) {
            $startDateTime = $event['start_date'];
            if (!empty($event['start_time_only'])) {
                $startDateTime .= ' ' . $event['start_time_only'];
            }
            $schema['startDate'] = self::isoDate($startDateTime);
        }

        // End date
        if (!empty($event['end_time'])) {
            $schema['endDate'] = self::isoDate($event['end_time']);
        } elseif (!empty($event['end_date'])) {
            $endDateTime = $event['end_date'];
            if (!empty($event['end_time_only'])) {
                $endDateTime .= ' ' . $event['end_time_only'];
            }
            $schema['endDate'] = self::isoDate($endDateTime);
        }

        // Description
        if (!empty($event['description'])) {
            $schema['description'] = self::truncate(strip_tags($event['description']), 300);
        }

        // Image
        if (!empty($event['cover_image'])) {
            $schema['image'] = self::absoluteUrl($event['cover_image']);
        } elseif (!empty($event['image_url'])) {
            $schema['image'] = self::absoluteUrl($event['image_url']);
        }

        // Location
        if (!empty($event['location'])) {
            $location = [
                '@type' => 'Place',
                'name' => $event['location'],
            ];

            // Add geo coordinates if available
            if (!empty($event['latitude']) && !empty($event['longitude'])) {
                $location['geo'] = [
                    '@type' => 'GeoCoordinates',
                    'latitude' => (float) $event['latitude'],
                    'longitude' => (float) $event['longitude'],
                ];
            }

            // Add address if available
            if (!empty($event['address'])) {
                $location['address'] = [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $event['address'],
                ];
            }

            $schema['location'] = $location;
        }

        // Organizer
        if ($organizer) {
            $schema['organizer'] = self::person($organizer);
        }

        // Offers (free admission default)
        $schema['offers'] = [
            '@type' => 'Offer',
            'price' => '0',
            'priceCurrency' => 'EUR',
            'availability' => 'https://schema.org/InStock',
            'url' => $url,
        ];

        return self::wrap($schema);
    }

    /**
     * Offer/Service Schema - For listings (offers and requests)
     */
    public static function offer(array $listing, ?array $seller = null): array
    {
        $baseUrl = self::getBaseUrl();
        $url = $baseUrl . '/listings/' . $listing['id'];
        $isOffer = ($listing['type'] ?? 'offer') === 'offer';

        $schema = [
            '@type' => $isOffer ? 'Offer' : 'Demand',
            '@id' => $url . '#listing',
            'url' => $url,
        ];

        // The item being offered/requested
        $schema['itemOffered'] = [
            '@type' => 'Service',
            'name' => $listing['title'] ?? 'Service',
            'description' => self::truncate(strip_tags($listing['description'] ?? ''), 300),
        ];

        // Image
        if (!empty($listing['image_url'])) {
            $schema['itemOffered']['image'] = self::absoluteUrl($listing['image_url']);
        }

        // Seller/Buyer
        if ($seller) {
            $schema[$isOffer ? 'seller' : 'buyer'] = self::person($seller);
        }

        // Area served
        if (!empty($listing['location'])) {
            $schema['areaServed'] = [
                '@type' => 'Place',
                'name' => $listing['location'],
            ];
        }

        // Category
        if (!empty($listing['category_name'])) {
            $schema['category'] = $listing['category_name'];
        }

        // Availability
        $status = $listing['status'] ?? 'active';
        if ($status === 'active') {
            $schema['availability'] = 'https://schema.org/InStock';
        } elseif ($status === 'completed') {
            $schema['availability'] = 'https://schema.org/SoldOut';
        }

        return self::wrap($schema);
    }

    /**
     * LocalBusiness Schema - For groups/hubs with location
     */
    public static function localBusiness(array $group): array
    {
        $baseUrl = self::getBaseUrl();
        $url = $baseUrl . '/groups/' . $group['id'];

        $schema = [
            '@type' => 'LocalBusiness',
            '@id' => $url . '#localbusiness',
            'name' => $group['name'] ?? 'Community Hub',
            'url' => $url,
        ];

        // Description
        if (!empty($group['description'])) {
            $schema['description'] = self::truncate(strip_tags($group['description']), 300);
        }

        // Image
        if (!empty($group['cover_image_url'])) {
            $schema['image'] = self::absoluteUrl($group['cover_image_url']);
        } elseif (!empty($group['image_url'])) {
            $schema['image'] = self::absoluteUrl($group['image_url']);
        }

        // Location
        if (!empty($group['location'])) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'addressLocality' => $group['location'],
            ];
        }

        // Geo coordinates
        if (!empty($group['latitude']) && !empty($group['longitude'])) {
            $schema['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $group['latitude'],
                'longitude' => (float) $group['longitude'],
            ];
        }

        return self::wrap($schema);
    }

    /**
     * BreadcrumbList Schema - For navigation trails
     */
    public static function breadcrumbs(array $crumbs): array
    {
        $items = [];
        $position = 1;

        foreach ($crumbs as $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $crumb['name'],
                'item' => self::absoluteUrl($crumb['url']),
            ];
        }

        return self::wrap([
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ]);
    }

    /**
     * FAQPage Schema - For FAQ sections
     */
    public static function faqPage(array $faqs): array
    {
        $items = [];

        foreach ($faqs as $faq) {
            $items[] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ];
        }

        return self::wrap([
            '@type' => 'FAQPage',
            'mainEntity' => $items,
        ]);
    }

    /**
     * Person Schema - Helper for author/organizer
     */
    public static function person(array $user): array
    {
        $person = [
            '@type' => 'Person',
            'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['name'] ?? 'Anonymous'),
        ];

        if (!empty($user['avatar_url'])) {
            $person['image'] = self::absoluteUrl($user['avatar_url']);
        }

        if (!empty($user['id'])) {
            $baseUrl = self::getBaseUrl();
            $person['url'] = $baseUrl . '/profile/' . $user['id'];
        }

        return $person;
    }

    // ============================================================
    // HELPER METHODS
    // ============================================================

    /**
     * Wrap schema with @context
     */
    private static function wrap(array $schema): array
    {
        return array_merge(['@context' => 'https://schema.org'], $schema);
    }

    /**
     * Get base URL for current tenant
     */
    private static function getBaseUrl(): string
    {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = '';

        if (class_exists('\Nexus\Core\TenantContext')) {
            $basePath = TenantContext::getBasePath();
        }

        return rtrim($protocol . '://' . $host . $basePath, '/');
    }

    /**
     * Convert relative URL to absolute
     */
    private static function absoluteUrl(string $url): string
    {
        if (strpos($url, 'http') === 0) {
            return $url;
        }
        return self::getBaseUrl() . '/' . ltrim($url, '/');
    }

    /**
     * Convert date to ISO 8601 format
     */
    private static function isoDate(string $date): string
    {
        try {
            $dt = new \DateTime($date);
            return $dt->format('c'); // ISO 8601
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Truncate string to max length at word boundary
     */
    private static function truncate(string $text, int $maxLength): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if (strlen($text) <= $maxLength) {
            return $text;
        }

        $truncated = substr($text, 0, $maxLength);
        $lastSpace = strrpos($truncated, ' ');

        if ($lastSpace !== false) {
            $truncated = substr($truncated, 0, $lastSpace);
        }

        return rtrim($truncated, '.,!?') . '...';
    }

    /**
     * Get organization config from tenant settings
     */
    private static function getOrgConfig(): array
    {
        try {
            $tenant = TenantContext::get();
            if (!empty($tenant['configuration'])) {
                $config = json_decode($tenant['configuration'], true);
                return $config['seo_organization'] ?? [];
            }
        } catch (\Exception $e) {
            // Ignore
        }
        return [];
    }
}
