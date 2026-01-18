<?php
// Master Dashboard Dispatcher
// Path: views/master/dashboard.php

// Force Modern Layout for Super Admin

$target = dirname(__DIR__) . '/modern/master/dashboard.php';

if (!file_exists($target)) {
    http_response_code(500);
    die("Error: Modern dashboard view not found at " . htmlspecialchars($target));
}

require $target;
return;
