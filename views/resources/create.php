<?php
// RESOURCES CREATE DISPATCHER
// This file determines which layout view to load based on the user's session.

// 1. Modern / Civic Layouts
if (($_SESSION['nexus_layout'] ?? 'default') === 'modern' || ($_SESSION['nexus_layout'] ?? '') === 'civicone') {
    require __DIR__ . '/../modern/resources/create.php';
    return;
}

// 2. Nexus Social Layout (Default)

