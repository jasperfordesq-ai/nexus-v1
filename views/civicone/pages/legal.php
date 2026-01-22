<?php
/**
 * Legal & Info Hub - CivicOne Mobile-First Layout
 * Full mobile native experience with all footer information
 */

// Hero variables for header
$hTitle = $hero_title ?? 'Legal & Privacy';
$hSubtitle = $hero_subtitle ?? 'Privacy, Terms, and Platform Information';
$hType = $hero_type ?? 'Legal';

require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
$tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
$isHourTimebank = ($tSlug === 'hour-timebank' || $tSlug === 'hour_timebank');

// Get tenant info
$tenantFooter = '';
$tenantName = 'This Community';
if (class_exists('Nexus\Core\TenantContext')) {
    $t = Nexus\Core\TenantContext::get();
    $tenantName = $t['name'] ?? 'This Community';
    if (!empty($t['configuration'])) {
        $tConfig = json_decode($t['configuration'], true);
        if (!empty($tConfig['footer_text'])) {
            $tenantFooter = $tConfig['footer_text'];
        }
    }
}
?>

<!-- Legal Page CSS -->
<link rel="stylesheet" href="<?= NexusCoreTenantContext::getBasePath() ?>/assets/css/purged/civicone-pages-legal.min.css">
