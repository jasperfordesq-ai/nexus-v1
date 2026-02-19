<?php
/**
 * Route-to-Controller Validation Script
 * Verifies that all routes in routes.php point to existing controller methods
 */

$routesFile = __DIR__ . '/../httpdocs/routes.php';
$routesContent = file_get_contents($routesFile);

// Extract all route definitions: $router->add('METHOD', '/path', 'Controller@method')
preg_match_all(
    '/\$router->add\(\s*\'([A-Z]+)\'\s*,\s*\'([^\']+)\'\s*,\s*\'([^@]+)@([^\']+)\'\s*\)/',
    $routesContent,
    $matches,
    PREG_SET_ORDER
);

$errors = [];
$warnings = [];
$checked = 0;
$v2ApiCount = 0;

foreach ($matches as $match) {
    $httpMethod = $match[1];
    $routePath = $match[2];
    $className = $match[3];
    $methodName = $match[4];

    $checked++;

    if (strpos($routePath, '/api/v2/') === 0) {
        $v2ApiCount++;
    }

    // Convert namespace to file path
    $relativePath = str_replace('Nexus\\', '', $className);
    $relativePath = str_replace('\\', '/', $relativePath);
    $filePath = __DIR__ . '/../src/' . $relativePath . '.php';

    if (!file_exists($filePath)) {
        $errors[] = "[MISSING FILE] {$className}@{$methodName} -> {$filePath} (Route: {$httpMethod} {$routePath})";
        continue;
    }

    // Check if method exists in file
    $classContent = file_get_contents($filePath);

    // Look for function definition
    $pattern = '/function\s+' . preg_quote($methodName, '/') . '\s*\(/';
    if (!preg_match($pattern, $classContent)) {
        $errors[] = "[MISSING METHOD] {$className}@{$methodName} (Route: {$httpMethod} {$routePath})";
    }
}

echo "=== ROUTE VALIDATION REPORT ===\n\n";
echo "Total routes checked: {$checked}\n";
echo "V2 API routes: {$v2ApiCount}\n";
echo "Errors found: " . count($errors) . "\n";
echo "Warnings found: " . count($warnings) . "\n\n";

if (count($errors) > 0) {
    echo "=== ERRORS (Missing Controllers/Methods) ===\n\n";
    foreach ($errors as $error) {
        echo "  {$error}\n";
    }
    echo "\n";
} else {
    echo "✅ ALL ROUTES VALIDATED SUCCESSFULLY\n\n";
}

if (count($warnings) > 0) {
    echo "=== WARNINGS ===\n\n";
    foreach ($warnings as $warning) {
        echo "  {$warning}\n";
    }
}

exit(count($errors) > 0 ? 1 : 0);
