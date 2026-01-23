<?php
/**
 * Validation Script - 100/100 Optimization
 * Checks that all files are in place and properly configured
 */

echo "🔍 VALIDATION SCRIPT - 100/100 Optimization\n";
echo str_repeat("=", 60) . "\n\n";

$errors = [];
$warnings = [];
$success = [];

// Define base path
$basePath = dirname(__DIR__);

// 1. Check Core CSS Files
echo "📦 Checking Core CSS Files...\n";
// Updated 2026-01-17: Removed references to non-existent files
$cssFiles = [
    'httpdocs/assets/css/nexus-phoenix.min.css' => 'Phoenix Framework',
    'httpdocs/assets/css/nexus-polish.min.css' => 'Polish (Consolidated)',
    'httpdocs/assets/css/nexus-interactions.min.css' => 'Interactions (Consolidated)',
    'httpdocs/assets/css/nexus-native-nav-v2.min.css' => 'Mobile Navigation v2',
    'httpdocs/assets/css/nexus-mobile.min.css' => 'Mobile Responsive',
];

foreach ($cssFiles as $file => $name) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        $size = filesize($fullPath);
        $sizeKB = round($size / 1024, 2);
        echo "  ✅ $name: {$sizeKB}KB\n";
        $success[] = "$name exists ({$sizeKB}KB)";
    } else {
        echo "  ❌ $name: NOT FOUND\n";
        $errors[] = "$name missing at $file";
    }
}
echo "\n";

// 2. Check Configuration Files
echo "⚙️  Checking Configuration Files...\n";
$configFiles = [
    'config/css-bundles.php' => 'CSS Bundle Config',
];

foreach ($configFiles as $file => $name) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        echo "  ✅ $name exists\n";
        $success[] = "$name configured";

        // Validate PHP syntax with properly escaped path
        $output = shell_exec('php -l ' . escapeshellarg($fullPath) . ' 2>&1');
        if (strpos($output, 'No syntax errors') !== false) {
            echo "     → Syntax valid\n";
        } else {
            $errors[] = "$name has syntax errors";
        }
    } else {
        echo "  ❌ $name: NOT FOUND\n";
        $errors[] = "$name missing at $file";
    }
}
echo "\n";

// 3. Check JavaScript Files
echo "🎯 Checking JavaScript Files...\n";
$jsFiles = [
    'httpdocs/assets/js/layout-switch-helper.js' => 'Layout Switcher v2.0',
];

foreach ($jsFiles as $file => $name) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        $size = filesize($fullPath);
        $sizeKB = round($size / 1024, 2);
        echo "  ✅ $name: {$sizeKB}KB\n";

        // Check for v2.0 implementation
        if (strpos($content, 'v2.0') !== false) {
            echo "     → v2.0 Clean Implementation ✓\n";
        } else {
            $warnings[] = "$name may not be v2.0";
        }

        // Check for session-based switching
        if (strpos($content, 'session') !== false || strpos($content, 'Session') !== false) {
            echo "     → Session-based switching ✓\n";
        }

        $success[] = "$name updated";
    } else {
        echo "  ❌ $name: NOT FOUND\n";
        $errors[] = "$name missing at $file";
    }
}
echo "\n";

// 4. Check API Endpoint (route-based)
echo "🔌 Checking API Endpoint...\n";

// Check for LayoutApiController
$controllerPath = $basePath . '/src/Controllers/Api/LayoutApiController.php';
if (file_exists($controllerPath)) {
    echo "  ✅ LayoutApiController exists\n";

    // Validate syntax with properly escaped path
    $output = shell_exec('php -l ' . escapeshellarg($controllerPath) . ' 2>&1');
    if (strpos($output, 'No syntax errors') !== false) {
        echo "     → Syntax valid ✓\n";
    }

    // Check route exists
    $routesPath = $basePath . '/httpdocs/routes.php';
    $routesContent = file_get_contents($routesPath);
    if (strpos($routesContent, '/api/layout-switch') !== false) {
        echo "     → Route configured ✓\n";
        $success[] = "Layout Switch API configured";
    } else {
        echo "  ⚠️  Route not found in routes.php\n";
        $warnings[] = "Layout Switch route missing - add to routes.php";
    }
} else {
    echo "  ❌ Layout Switch API: NOT FOUND\n";
    $errors[] = "LayoutApiController missing at src/Controllers/Api/LayoutApiController.php";
}
echo "\n";

// 5. Check PHP Service Updates
echo "🔧 Checking PHP Service Updates...\n";
$phpFiles = [
    'src/Services/LayoutHelper.php' => 'LayoutHelper',
];

foreach ($phpFiles as $file => $name) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        echo "  ✅ $name exists\n";

        // Check for deprecated method
        if (strpos($content, 'DEPRECATED') !== false) {
            echo "     → URL methods deprecated ✓\n";
        } else {
            $warnings[] = "$name may not have deprecation notices";
        }

        // Check for new methods
        if (strpos($content, 'handleLayoutSwitch') !== false) {
            echo "     → New switch handler ✓\n";
        }

        if (strpos($content, 'generateSwitchResponse') !== false) {
            echo "     → AJAX response method ✓\n";
        }

        $success[] = "$name updated";
    } else {
        echo "  ❌ $name: NOT FOUND\n";
        $errors[] = "$name missing at $file";
    }
}
echo "\n";

// 6. Check Font Loading
echo "🔤 Checking Font Loading...\n";
$fontFiles = [
    'views/layouts/modern/font-loading.php' => 'Font Loading Strategy',
];

foreach ($fontFiles as $file => $name) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        echo "  ✅ $name exists\n";
        $success[] = "$name configured";
    } else {
        echo "  ⚠️  $name: NOT FOUND (optional)\n";
        $warnings[] = "$name missing - font loading may not be optimized";
    }
}
echo "\n";

// 7. Check Documentation
echo "📚 Checking Documentation...\n";
$docFiles = [
    'LAYOUT_OPTIMIZATION_GUIDE.md' => 'Full Guide',
    'OPTIMIZATION_SUMMARY.md' => 'Quick Reference',
    '100_OUT_OF_100_ACHIEVEMENT.md' => 'Achievement',
    'IMPLEMENTATION_CHECKLIST.md' => 'Checklist',
];

foreach ($docFiles as $file => $name) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        $size = filesize($fullPath);
        $sizeKB = round($size / 1024, 2);
        echo "  ✅ $name: {$sizeKB}KB\n";
        $success[] = "$name available";
    } else {
        echo "  ❌ $name: NOT FOUND\n";
        $warnings[] = "$name missing";
    }
}
echo "\n";

// 8. Check Design Token Values
echo "🎨 Checking Design Token Implementation...\n";
$tokensPath = $basePath . '/httpdocs/assets/css/design-tokens.css';
if (file_exists($tokensPath)) {
    $content = file_get_contents($tokensPath);

    $checks = [
        '--space-' => 'Spacing scale',
        '--layout-header-height' => 'Layout dimensions',
        '--color-primary-' => 'Color system',
        '--glass-blur' => 'Glassmorphism',
        '--transition-' => 'Transitions',
    ];

    foreach ($checks as $token => $description) {
        if (strpos($content, $token) !== false) {
            echo "  ✅ $description defined\n";
        } else {
            echo "  ❌ $description missing\n";
            $errors[] = "$description tokens not found";
        }
    }
}
echo "\n";

// 9. Performance Checks
echo "⚡ Performance Validation...\n";

// Check if deprecated CSS/JS files still exist (can be removed)
// Updated 2026-01-17: Added more deprecated files after consolidation
$oldCSSFiles = [
    'httpdocs/assets/css/nexus-native-nav.css',    // v1 nav - replaced by v2
    'httpdocs/assets/css/nexus-10x-polish.css',    // replaced by nexus-polish.css
    'httpdocs/assets/css/nexus-ux-polish.css',     // replaced by nexus-polish.css
    'httpdocs/assets/css/nexus-smooth-polish.css', // replaced by nexus-polish.css
    'httpdocs/assets/css/nexus-header-polish.css', // replaced by nexus-polish.css
    'httpdocs/assets/css/nexus-micro-interactions.css', // replaced by nexus-interactions.css
    'httpdocs/assets/js/fds-mobile.js',            // abandoned mobile app deleted
];

foreach ($oldCSSFiles as $file) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        echo "  ⚠️  Legacy file found: " . basename($file) . "\n";
        $warnings[] = "Legacy file " . basename($file) . " still exists - consider removing";
    }
}

// Check total CSS size of new files
$totalSize = 0;
foreach ($cssFiles as $file => $name) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        $totalSize += filesize($fullPath);
    }
}
$totalSizeKB = round($totalSize / 1024, 2);
echo "  📦 Total new CSS size: {$totalSizeKB}KB\n";
if ($totalSize < 300000) { // Less than ~300KB
    echo "     → Excellent! Under 300KB unminified ✓\n";
} else {
    $warnings[] = "New CSS files larger than expected ({$totalSizeKB}KB)";
}
echo "\n";

// 10. Final Summary
echo str_repeat("=", 60) . "\n";
echo "📊 VALIDATION SUMMARY\n";
echo str_repeat("=", 60) . "\n\n";

echo "✅ Success: " . count($success) . " items\n";
foreach ($success as $item) {
    echo "   • $item\n";
}
echo "\n";

if (count($warnings) > 0) {
    echo "⚠️  Warnings: " . count($warnings) . " items\n";
    foreach ($warnings as $item) {
        echo "   • $item\n";
    }
    echo "\n";
}

if (count($errors) > 0) {
    echo "❌ Errors: " . count($errors) . " items\n";
    foreach ($errors as $item) {
        echo "   • $item\n";
    }
    echo "\n";
    echo "❌ VALIDATION FAILED - Please fix errors above\n";
    exit(1);
} else {
    echo "🎉 VALIDATION PASSED!\n\n";
    echo "Next Steps:\n";
    echo "1. Review IMPLEMENTATION_CHECKLIST.md\n";
    echo "2. Update header template to inline critical CSS\n";
    echo "3. Test in staging environment\n";
    echo "4. Run Lighthouse audit\n";
    echo "5. Deploy to production\n\n";
    echo "Target Scores: Desktop 100/100, Mobile 100/100 🎯\n";
    exit(0);
}
