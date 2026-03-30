<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * OpenApiDocController — Eloquent-powered OpenAPI documentation endpoints.
 *
 * Fully migrated from legacy delegation to native Laravel.
 * Serves the OpenAPI spec as JSON or YAML, and a Swagger UI page.
 */
class OpenApiDocController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** Path to the OpenAPI spec file */
    private const SPEC_PATH = 'docs/openapi.yaml';

    /**
     * GET /api/docs/openapi.json
     *
     * Returns the OpenAPI specification as JSON.
     * Accessible without authentication.
     */
    public function json(): JsonResponse
    {
        $specPath = base_path(self::SPEC_PATH);

        if (!file_exists($specPath)) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api.openapi_spec_not_found'), null, 404);
        }

        $yaml = file_get_contents($specPath);
        $spec = $this->parseYaml($yaml);

        if ($spec === null) {
            return $this->respondWithError('SERVER_INTERNAL_ERROR', __('api.openapi_parse_failed'), null, 500);
        }

        return response()->json($spec, 200, [
            'Cache-Control' => 'public, max-age=3600',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /api/docs/openapi.yaml
     *
     * Returns the OpenAPI specification as YAML.
     * Accessible without authentication.
     *
     * @return Response
     */
    public function yaml(): Response
    {
        $specPath = base_path(self::SPEC_PATH);

        if (!file_exists($specPath)) {
            return response(json_encode([
                'errors' => [['code' => 'RESOURCE_NOT_FOUND', 'message' => 'OpenAPI specification not found']],
            ]), 404, ['Content-Type' => 'application/json']);
        }

        $content = file_get_contents($specPath);

        return response($content, 200, [
            'Content-Type' => 'application/x-yaml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * GET /api/docs
     *
     * Serves a Swagger UI page for browsing the API documentation.
     * Only available in non-production environments unless SHOW_API_DOCS=true.
     *
     * @return Response|JsonResponse
     */
    public function ui(): Response|JsonResponse
    {
        $appEnv = config('app.env', 'production');
        $showDocsInProd = config('app.show_api_docs', false);

        if ($appEnv === 'production' && !$showDocsInProd) {
            return $this->respondWithError('FORBIDDEN', __('api.api_docs_disabled_production'), null, 403);
        }

        $specUrl = url('/api/docs/openapi.json');

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project NEXUS API Documentation</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui.css">
    <style>
        body { margin: 0; padding: 0; }
        .swagger-ui .topbar { display: none; }
        .swagger-ui .info { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-bundle.js"></script>
    <script>
        window.onload = function() {
            SwaggerUIBundle({
                url: "' . htmlspecialchars($specUrl, ENT_QUOTES, 'UTF-8') . '",
                dom_id: "#swagger-ui",
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIBundle.SwaggerUIStandalonePreset
                ],
                layout: "BaseLayout",
                deepLinking: true,
                showExtensions: true,
                showCommonExtensions: true,
                persistAuthorization: true
            });
        };
    </script>
</body>
</html>';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    /**
     * Parse YAML content to PHP array.
     *
     * Tries yaml_parse extension first, then Symfony YAML, then Spyc.
     */
    private function parseYaml(string $yaml): ?array
    {
        // Try yaml_parse extension first
        if (function_exists('yaml_parse')) {
            $result = yaml_parse($yaml);
            return $result !== false ? $result : null;
        }

        // Try Symfony YAML component if available
        if (class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            try {
                return \Symfony\Component\Yaml\Yaml::parse($yaml);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('[OpenApiDocController] YAML parse error', [
                    'message' => $e->getMessage(),
                ]);
                return null;
            }
        }

        // Spyc fallback
        if (class_exists(\Spyc::class)) {
            return \Spyc::YAMLLoadString($yaml);
        }

        \Illuminate\Support\Facades\Log::error('[OpenApiDocController] No YAML parser available. Install yaml extension or symfony/yaml.');
        return null;
    }
}
