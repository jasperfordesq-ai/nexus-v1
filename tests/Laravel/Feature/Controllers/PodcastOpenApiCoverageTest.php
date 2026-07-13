<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\Laravel\TestCase;

final class PodcastOpenApiCoverageTest extends TestCase
{
    public function test_every_podcast_api_operation_is_documented_in_both_specs(): void
    {
        $publicSpec = $this->readSpec(base_path('resources/openapi.json'));
        $completeSpec = $this->readSpec(base_path('openapi.json'));

        $operations = collect(Route::getRoutes()->getRoutes())
            ->filter(static function (LaravelRoute $route): bool {
                $uri = $route->uri();

                return str_starts_with($uri, 'api/v2/podcasts')
                    || str_starts_with($uri, 'api/v2/admin/podcasts')
                    || str_starts_with($uri, 'api/v2/admin/config/podcasts');
            })
            ->flatMap(static function (LaravelRoute $route): array {
                $methods = array_values(array_diff($route->methods(), ['HEAD']));

                return array_map(
                    static fn (string $method): array => [
                        'path' => '/' . $route->uri(),
                        'method' => strtolower($method),
                    ],
                    $methods,
                );
            })
            ->values();

        $this->assertCount(35, $operations, 'Update the podcast OpenAPI contract when the route surface changes.');

        foreach ($operations as $operation) {
            $resourcePath = substr($operation['path'], strlen('/api'));
            $documentedResourcePath = $this->matchingTemplatedPath($publicSpec, $resourcePath);
            $documentedCompletePath = $this->matchingTemplatedPath($completeSpec, $operation['path']);
            $this->assertArrayHasKey($operation['method'], $publicSpec['paths'][$documentedResourcePath], strtoupper($operation['method']) . ' ' . $resourcePath . ' missing from resources/openapi.json');
            $this->assertArrayHasKey($operation['method'], $completeSpec['paths'][$documentedCompletePath], strtoupper($operation['method']) . ' ' . $operation['path'] . ' missing from openapi.json');
        }
    }

    public function test_podcast_specs_have_unique_templates_complete_inputs_and_json_response_schemas(): void
    {
        foreach ([base_path('resources/openapi.json'), base_path('openapi.json')] as $path) {
            $spec = $this->readSpec($path);
            $podcastPaths = array_filter(
                array_keys($spec['paths']),
                static fn (string $candidate): bool => str_contains($candidate, '/podcasts'),
            );
            $canonicalPaths = array_map([$this, 'canonicalPathTemplate'], $podcastPaths);
            $this->assertSameSize(array_unique($canonicalPaths), $canonicalPaths, $path . ' contains equivalent templated podcast paths.');

            $showInput = $spec['components']['schemas']['PodcastShowInput'];
            foreach (['title', 'slug', 'summary', 'description', 'artwork_url', 'language', 'category', 'author_name', 'owner_email', 'copyright', 'funding_url', 'explicit', 'visibility'] as $field) {
                $this->assertArrayHasKey($field, $showInput['properties'], $field . ' missing from PodcastShowInput in ' . $path);
            }

            $episodeInput = $spec['components']['schemas']['PodcastEpisodeInput'];
            foreach (['title', 'slug', 'summary', 'description', 'audio_url', 'audio_mime', 'audio_bytes', 'duration_seconds', 'episode_number', 'season_number', 'explicit', 'episode_type', 'visibility', 'transcript', 'transcript_language', 'cover_image_url', 'scheduled_for', 'chapters'] as $field) {
                $this->assertArrayHasKey($field, $episodeInput['properties'], $field . ' missing from PodcastEpisodeInput in ' . $path);
            }

            foreach ($podcastPaths as $podcastPath) {
                if (str_contains($podcastPath, '/feed/') || str_ends_with($podcastPath, '/feed.xml') || str_contains($podcastPath, '/media/') || str_contains($podcastPath, '/transcripts/')) {
                    continue;
                }
                foreach (['get', 'post', 'put', 'delete'] as $method) {
                    if (!isset($spec['paths'][$podcastPath][$method])) {
                        continue;
                    }
                    foreach ($spec['paths'][$podcastPath][$method]['responses'] as $status => $response) {
                        if (!str_starts_with((string) $status, '2')) {
                            continue;
                        }
                        $this->assertArrayHasKey('application/json', $response['content'] ?? [], strtoupper($method) . ' ' . $podcastPath . ' lacks a JSON success response schema.');
                        $this->assertArrayHasKey('schema', $response['content']['application/json'], strtoupper($method) . ' ' . $podcastPath . ' lacks a JSON success response schema.');
                    }
                }
            }
        }
    }

    public function test_artwork_upload_operations_require_multipart_images_and_bearer_auth(): void
    {
        $spec = $this->readSpec(base_path('resources/openapi.json'));
        $paths = [
            '/v2/podcasts/{id}/artwork',
            '/v2/podcasts/{showId}/episodes/{episodeId}/cover',
        ];

        foreach ($paths as $path) {
            $operation = $spec['paths'][$path]['post'];
            $requestBody = $operation['requestBody'];
            if (isset($requestBody['$ref'])) {
                $requestBody = $this->resolveLocalReference($spec, (string) $requestBody['$ref']);
            }
            $schema = $requestBody['content']['multipart/form-data']['schema'];

            $this->assertSame(['image'], $schema['required']);
            $this->assertSame('string', $schema['properties']['image']['type']);
            $this->assertSame('binary', $schema['properties']['image']['format']);
            $this->assertArrayNotHasKey('security', $operation, $path . ' must inherit bearer authentication.');
        }
    }

    public function test_distribution_operations_are_explicitly_anonymous_but_member_reads_inherit_bearer_auth(): void
    {
        $spec = $this->readSpec(base_path('resources/openapi.json'));
        $publicPaths = [
            '/v2/podcasts/feed/{tenantId}/{showSlug}.xml',
            '/v2/podcasts/{showSlug}/feed.xml',
            '/v2/podcasts/media/{tenantId}/{episodeId}/audio',
            '/v2/podcasts/transcripts/{tenantId}/{episodeId}.txt',
            '/v2/podcasts/chapters/{tenantId}/{episodeId}.json',
            '/v2/podcasts/episodes/{episodeId}/listen',
        ];

        foreach ($publicPaths as $path) {
            $this->assertSame([], $spec['paths'][$path]['get']['security'] ?? $spec['paths'][$path]['post']['security'] ?? null, $path . ' must explicitly override bearer authentication.');
        }

        $this->assertArrayNotHasKey('security', $spec['paths']['/v2/podcasts']['get']);
        $this->assertSame([['bearerAuth' => []]], $spec['security']);
    }

    /** @param array<string,mixed> $spec
     *  @return array<string,mixed>
     */
    private function resolveLocalReference(array $spec, string $reference): array
    {
        $this->assertStringStartsWith('#/', $reference);
        $value = $spec;
        foreach (explode('/', substr($reference, 2)) as $segment) {
            $key = str_replace(['~1', '~0'], ['/', '~'], $segment);
            $this->assertArrayHasKey($key, $value, $reference . ' does not resolve');
            $value = $value[$key];
            $this->assertIsArray($value);
        }

        return $value;
    }

    /** @return array<string,mixed> */
    private function readSpec(string $path): array
    {
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /** @param array<string,mixed> $spec */
    private function matchingTemplatedPath(array $spec, string $runtimePath): string
    {
        $canonical = $this->canonicalPathTemplate($runtimePath);
        foreach (array_keys($spec['paths']) as $documentedPath) {
            if ($this->canonicalPathTemplate((string) $documentedPath) === $canonical) {
                return (string) $documentedPath;
            }
        }

        $this->fail($runtimePath . ' missing from OpenAPI paths.');
    }

    private function canonicalPathTemplate(string $path): string
    {
        return (string) preg_replace('/\{[^}]+\}/', '{}', $path);
    }
}
