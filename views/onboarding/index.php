<?php
// Onboarding Dispatcher

// Nexus Social Bridge


// Fallback to Modern
// (Assumed location based on legacy structure)
$legacyPath = dirname(__DIR__) . '/modern/onboarding/wizard.php';
if (file_exists($legacyPath)) {
    require $legacyPath;
} else {
    echo "View not found: Onboarding";
}
