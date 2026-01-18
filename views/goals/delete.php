<?php


// Fallback logic for legacy file location
$legacyPath = dirname(__DIR__) . '/modern/goals/delete.php';
if (file_exists($legacyPath)) {
    require $legacyPath;
} else {
    echo "View not found: Goals Delete";
}
