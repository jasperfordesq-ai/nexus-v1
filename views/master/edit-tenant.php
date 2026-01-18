<?php
// Master Tenant Editor Dispatcher
// Path: views/master/edit-tenant.php

// Force Modern Layout for Super Admin
// Force Modern Layout for Super Admin


$target = dirname(__DIR__) . '/modern/master/edit-tenant.php';

if (!file_exists($target)) {
    http_response_code(500);
    die("Error: Modern edit-tenant view not found at " . htmlspecialchars($target));
}

require $target;
return;
