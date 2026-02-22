<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

/**
 * OpenApiController - Serve OpenAPI specification
 *
 * Provides the OpenAPI spec for API documentation and client generation.
 *
 * Endpoints:
 * - GET /api/docs/openapi.json - OpenAPI spec as JSON
 * - GET /api/docs/openapi.yaml - OpenAPI spec as YAML
 * - GET /api/docs - Swagger UI (if enabled)
 */
class OpenApiController extends BaseApiController
{
    /**
     * Path to the OpenAPI spec file
     */
    private const SPEC_PATH = __DIR__ . '/../../../docs/openapi.yaml';

    /**
     * GET /api/docs/openapi.json
     *
     * Returns the OpenAPI specification as JSON.
     * Accessible without authentication.
     */
    public function json(): void
    {
        // Check if spec file exists
        if (!file_exists(self::SPEC_PATH)) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode([
                'errors' => [[
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'OpenAPI specification not found'
                ]]
            ]);
            exit;
        }

        // Parse YAML and convert to JSON
        $yaml = file_get_contents(self::SPEC_PATH);
        $spec = $this->parseYaml($yaml);

        if ($spec === null) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'errors' => [[
                    'code' => 'SERVER_INTERNAL_ERROR',
                    'message' => 'Failed to parse OpenAPI specification'
                ]]
            ]);
            exit;
        }

        header('Content-Type: application/json');
        header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
        echo json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * GET /api/docs/openapi.yaml
     *
     * Returns the OpenAPI specification as YAML.
     * Accessible without authentication.
     */
    public function yaml(): void
    {
        // Check if spec file exists
        if (!file_exists(self::SPEC_PATH)) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode([
                'errors' => [[
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'OpenAPI specification not found'
                ]]
            ]);
            exit;
        }

        header('Content-Type: application/x-yaml');
        header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
        readfile(self::SPEC_PATH);
        exit;
    }

    /**
     * GET /api/docs
     *
     * Serves a simple Swagger UI page for browsing the API documentation.
     */
    public function ui(): void
    {
        // Check environment - only show in development by default
        $appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production');
        $showDocsInProd = getenv('SHOW_API_DOCS') ?: ($_ENV['SHOW_API_DOCS'] ?? 'false');

        if ($appEnv === 'production' && $showDocsInProd !== 'true') {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'errors' => [[
                    'code' => 'FORBIDDEN',
                    'message' => 'API documentation is disabled in production'
                ]]
            ]);
            exit;
        }

        // Get the base URL for the spec
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $specUrl = $protocol . '://' . $host . '/api/docs/openapi.json';

        // Output Swagger UI
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>
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
                url: "' . htmlspecialchars($specUrl) . '",
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
        exit;
    }

    /**
     * Parse YAML content to PHP array
     *
     * Uses yaml_parse if available, otherwise a simple custom parser for basic YAML.
     *
     * @param string $yaml YAML content
     * @return array|null Parsed array or null on failure
     */
    private function parseYaml(string $yaml): ?array
    {
        // Try yaml_parse extension first
        if (function_exists('yaml_parse')) {
            $result = yaml_parse($yaml);
            return $result !== false ? $result : null;
        }

        // Try Symfony YAML component if available
        if (class_exists('\Symfony\Component\Yaml\Yaml')) {
            try {
                return \Symfony\Component\Yaml\Yaml::parse($yaml);
            } catch (\Exception $e) {
                error_log('[OpenApiController] YAML parse error: ' . $e->getMessage());
                return null;
            }
        }

        // Fallback: Use a simple JSON conversion approach
        // This is a workaround that works for many OpenAPI specs
        // Convert YAML to JSON using spyc if available
        if (class_exists('\Spyc')) {
            return \Spyc::YAMLLoadString($yaml);
        }

        // Last resort: try to serve as-is and let client parse
        // This won't work for most clients expecting JSON, so return null
        error_log('[OpenApiController] No YAML parser available. Install yaml extension or symfony/yaml.');
        return null;
    }
}
