<?php
require __DIR__ . '/vendor/autoload.php';

use Nexus\Core\Router;

// Capture routes
$router = new Router();
require __DIR__ . '/httpdocs/routes/super-admin.php';
require __DIR__ . '/httpdocs/routes/federation-api-v1.php';
require __DIR__ . '/httpdocs/routes/legacy-api.php';
require __DIR__ . '/httpdocs/routes/tenant-bootstrap.php';
require __DIR__ . '/httpdocs/routes/listings.php';
require __DIR__ . '/httpdocs/routes/users.php';
require __DIR__ . '/httpdocs/routes/messages.php';
require __DIR__ . '/httpdocs/routes/exchanges.php';
require __DIR__ . '/httpdocs/routes/events.php';
require __DIR__ . '/httpdocs/routes/groups.php';
require __DIR__ . '/httpdocs/routes/social.php';
require __DIR__ . '/httpdocs/routes/content.php';
require __DIR__ . '/httpdocs/routes/admin-api.php';
require __DIR__ . '/httpdocs/routes/misc-api.php';

$routes = $router->getRoutes();

$openapi = [
    'openapi' => '3.0.3',
    'info' => [
        'title' => 'Project NEXUS v2 API',
        'version' => '2.0.0',
        'description' => 'The complete V2 REST API for Project NEXUS. Use this standard when building federated integrations.'
    ],
    'servers' => [
        ['url' => 'https://api.project-nexus.net', 'description' => 'Production Server']
    ],
    'components' => [
        'securitySchemes' => [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT'
            ]
        ]
    ],
    'security' => [['bearerAuth' => []]],
    'paths' => []
];

foreach ($routes as $method => $endpoints) {
    foreach ($endpoints as $path => $handler) {
        if (!str_starts_with($path, '/api/v2/')) {
            continue;
        }

        // Convert {id} to {id} parameters
        preg_match_all('/\{([^}]+)\}/', $path, $matches);
        $parameters = [];
        foreach ($matches[1] as $param) {
            $parameters[] = [
                'name' => $param,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string']
            ];
        }

        // Determine tags from path
        $parts = explode('/', trim($path, '/'));
        $tag = isset($parts[2]) ? ucfirst($parts[2]) : 'Core';
        if (isset($parts[2]) && $parts[2] === 'admin' && isset($parts[3])) {
            $tag = 'Admin ' . ucfirst($parts[3]);
        }

        $methodLower = strtolower($method);

        if (!isset($openapi['paths'][$path])) {
            $openapi['paths'][$path] = [];
        }

        $openapi['paths'][$path][$methodLower] = [
            'summary' => $handler,
            'tags' => [$tag],
            'parameters' => $parameters,
            'responses' => [
                '200' => ['description' => 'Successful operation']
            ]
        ];
    }
}

file_put_contents(__DIR__ . '/openapi.json', json_encode($openapi, JSON_PRETTY_PRINT));
echo "Generated " . count($openapi['paths']) . " path definitions in openapi.json\n";
