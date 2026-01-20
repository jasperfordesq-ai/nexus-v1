<?php
// CivicOne Layout Header - Government/Public Sector Theme

// ============================================
// NO-CACHE HEADERS FOR THEME SWITCHING
// Prevents browser from serving stale cached pages when theme changes
// ============================================
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../onboarding_check.php';
require_once __DIR__ . '/../../consent_check.php';
// Load unified navigation configuration
require_once __DIR__ . '/../config/navigation.php';

// Theme mode support
$mode = $_COOKIE['nexus_mode'] ?? 'light'; // CivicOne defaults to light for accessibility
$hTitle = $hero_title ?? $pageTitle ?? 'CivicOne';
$hSubtitle = $hero_subtitle ?? $pageSubtitle ?? 'Public Sector Platform';
$hGradient = $hero_gradient ?? 'civic-hero-gradient';
$hType = $hero_type ?? 'Government';

// --- STRICT HOME DETECTION ---
$isHome = false;
$reqUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedPath = parse_url($reqUri, PHP_URL_PATH);
$normPath = rtrim($parsedPath, '/');
$normBase = '';
if (class_exists('\Nexus\Core\TenantContext')) {
    $normBase = rtrim(\Nexus\Core\TenantContext::getBasePath(), '/');
    if ($normPath === '' || $normPath === '/' || $normPath === '/home' || $normPath === $normBase || $normPath === $normBase . '/home') {
        $isHome = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $mode ?>" data-layout="civicone">
