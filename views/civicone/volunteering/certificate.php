<?php
// Phoenix View: Volunteer Certificate - Modern Holographic Design
$pageTitle = 'Volunteer Certificate';
$hideHero = true;

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
    exit;
}

// Fetch User & Stats
$user = $currentUser ?? \Nexus\Models\User::findById($_SESSION['user_id']);
if (!$user) {
    die("User not found");
}

$userId = is_array($user) ? $user['id'] : $user->id;
$userName = is_array($user)
    ? ($user['first_name'] . ' ' . $user['last_name'])
    : ($user->first_name . ' ' . $user->last_name);

$totalHours = \Nexus\Models\VolLog::getTotalVerifiedHours($userId);
$logs = \Nexus\Models\VolLog::getForUser($userId);
$verifiedLogs = array_filter($logs, fn($l) => ($l['status'] ?? '') === 'approved');
$date = date('F j, Y');

// Preview mode
$previewMode = isset($_GET['preview']) && $_GET['preview'] === '1';
if ($previewMode) {
    $previewHours = 47.5;
    $previewLogs = [
        ['org_name' => 'Community Food Bank', 'date_logged' => date('Y-m-d', strtotime('-7 days')), 'hours' => 8],
        ['org_name' => 'Local Animal Shelter', 'date_logged' => date('Y-m-d', strtotime('-14 days')), 'hours' => 6],
        ['org_name' => 'Youth Mentorship Program', 'date_logged' => date('Y-m-d', strtotime('-21 days')), 'hours' => 12],
        ['org_name' => 'Environmental Cleanup', 'date_logged' => date('Y-m-d', strtotime('-30 days')), 'hours' => 5],
        ['org_name' => 'Senior Center', 'date_logged' => date('Y-m-d', strtotime('-45 days')), 'hours' => 16.5],
    ];
}

$displayHours = $previewMode ? $previewHours : $totalHours;
$displayLogs = $previewMode ? $previewLogs : $verifiedLogs;
$displayActivities = $previewMode ? count($previewLogs) : count($verifiedLogs);
$displayOrgs = $previewMode ? 5 : count(array_unique(array_column($verifiedLogs, 'organization_id')));

// Get tenant info
$tenant = \Nexus\Core\TenantContext::get();
$tenantName = $tenant['name'] ?? 'Hour Timebank';
$tenantLogo = $tenant['logo_url'] ?? '';

require __DIR__ . '/../../layouts/civicone/header.php';
?>

<style>
/* ============================================
   MODERN HOLOGRAPHIC CERTIFICATE PAGE
   ============================================ */

.vc-page {
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
    padding: 20px 16px 120px;
}

@media (min-width: 901px) {
    .vc-page {
        padding: 180px 20px 60px;
    }
}

/* Animated background */
.vc-page::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background:
        radial-gradient(ellipse at 15% 25%, rgba(99, 102, 241, 0.18) 0%, transparent 50%),
        radial-gradient(ellipse at 85% 75%, rgba(236, 72, 153, 0.12) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 50%, rgba(139, 92, 246, 0.08) 0%, transparent 60%),
        linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    z-index: -2;
}

/* Floating orbs */
.vc-orb {
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.5;
    z-index: -1;
    pointer-events: none;
}

.vc-orb-1 {
    width: 500px;
    height: 500px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    top: -150px;
    right: -150px;
    animation: vcOrbFloat 25s ease-in-out infinite;
}

.vc-orb-2 {
    width: 400px;
    height: 400px;
    background: linear-gradient(135deg, #ec4899, #f43f5e);
    bottom: 10%;
    left: -150px;
    animation: vcOrbFloat 30s ease-in-out infinite reverse;
}

.vc-orb-3 {
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, #14b8a6, #06b6d4);
    top: 40%;
    right: 5%;
    animation: vcOrbFloat 20s ease-in-out infinite 3s;
}

@keyframes vcOrbFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    25% { transform: translate(40px, -40px) scale(1.08); }
    50% { transform: translate(-30px, 30px) scale(0.95); }
    75% { transform: translate(25px, 40px) scale(1.03); }
}

/* Container */
.vc-container {
    max-width: 950px;
    margin: 0 auto;
    animation: vcFadeIn 0.6s ease-out;
}

@keyframes vcFadeIn {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Page Header */
.vc-header {
    text-align: center;
    margin-bottom: 35px;
}

.vc-header-icon {
    width: 90px;
    height: 90px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.25), rgba(236, 72, 153, 0.25));
    border-radius: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2.8rem;
    color: #c4b5fd;
    box-shadow:
        0 10px 40px rgba(99, 102, 241, 0.25),
        inset 0 0 30px rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.12);
    animation: vcIconGlow 4s ease-in-out infinite;
}

@keyframes vcIconGlow {
    0%, 100% { box-shadow: 0 10px 40px rgba(99, 102, 241, 0.25), inset 0 0 30px rgba(255, 255, 255, 0.1); }
    50% { box-shadow: 0 15px 50px rgba(139, 92, 246, 0.35), inset 0 0 30px rgba(255, 255, 255, 0.15); }
}

.vc-header-title {
    font-size: 2.4rem;
    font-weight: 800;
    background: linear-gradient(135deg, #a5b4fc, #f0abfc, #fda4af);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0 0 10px;
    letter-spacing: -0.5px;
}

.vc-header-subtitle {
    color: rgba(255, 255, 255, 0.55);
    font-size: 1.1rem;
    margin: 0;
    font-weight: 500;
}

/* Action Buttons */
.vc-actions {
    display: flex;
    justify-content: center;
    gap: 14px;
    margin: 35px 0;
    flex-wrap: wrap;
}

.vc-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 26px;
    border-radius: 14px;
    font-weight: 600;
    font-size: 0.95rem;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.vc-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6, #a855f7);
    color: white;
    box-shadow: 0 6px 25px rgba(99, 102, 241, 0.35);
}

.vc-btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 35px rgba(99, 102, 241, 0.45);
    color: white;
}

.vc-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.vc-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
    transform: translateY(-2px);
    color: white;
}

/* Preview Banner */
.vc-preview-banner {
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.15), rgba(245, 158, 11, 0.15));
    border: 1px solid rgba(251, 191, 36, 0.25);
    border-radius: 14px;
    padding: 14px 20px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    gap: 14px;
}

.vc-preview-banner i {
    color: #fbbf24;
    font-size: 1.3rem;
}

.vc-preview-banner strong {
    color: #fcd34d;
    font-weight: 700;
}

.vc-preview-banner span {
    color: rgba(255, 255, 255, 0.65);
    margin-left: 6px;
}

/* Stats Row */
.vc-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 35px;
}

@media (max-width: 600px) {
    .vc-stats {
        grid-template-columns: 1fr;
    }
}

.vc-stat {
    background: rgba(255, 255, 255, 0.04);
    backdrop-filter: blur(20px);
    border-radius: 18px;
    padding: 26px 20px;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.08);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.vc-stat::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #6366f1, #ec4899, #8b5cf6);
    opacity: 0;
    transition: opacity 0.3s;
}

.vc-stat:hover {
    transform: translateY(-4px);
    background: rgba(255, 255, 255, 0.07);
}

.vc-stat:hover::before {
    opacity: 1;
}

.vc-stat-value {
    font-size: 2.8rem;
    font-weight: 800;
    background: linear-gradient(135deg, #a5b4fc, #f0abfc);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1;
}

.vc-stat-label {
    color: rgba(255, 255, 255, 0.55);
    font-size: 0.9rem;
    margin-top: 8px;
    font-weight: 500;
}

/* Certificate Card Container */
.vc-card {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(25px);
    border-radius: 28px;
    border: 1px solid rgba(255, 255, 255, 0.07);
    overflow: hidden;
    margin-bottom: 35px;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.25);
}

.vc-card-header {
    padding: 22px 28px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(255, 255, 255, 0.02);
}

.vc-card-title {
    color: white;
    font-size: 1.15rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
}

.vc-card-badge {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ============================================
   THE CERTIFICATE - Modern Gradient Design
   ============================================ */

.vc-cert-wrapper {
    padding: 30px;
    background: linear-gradient(145deg, #1a1f35, #0f1629);
}

.vc-cert {
    background: linear-gradient(160deg, #1e2444 0%, #151a30 50%, #0d1022 100%);
    border-radius: 20px;
    padding: 50px 45px;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow:
        0 0 0 1px rgba(99, 102, 241, 0.1),
        0 30px 80px rgba(0, 0, 0, 0.4),
        inset 0 1px 0 rgba(255, 255, 255, 0.05);
}

/* Animated gradient border effect */
.vc-cert::before {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: linear-gradient(135deg, #6366f1, #ec4899, #8b5cf6, #06b6d4, #6366f1);
    background-size: 400% 400%;
    border-radius: 22px;
    z-index: -1;
    animation: vcGradientShift 8s ease infinite;
    opacity: 0.6;
}

@keyframes vcGradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* Decorative elements */
.vc-cert-deco {
    position: absolute;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    opacity: 0.08;
    pointer-events: none;
}

.vc-cert-deco-1 {
    top: -80px;
    right: -80px;
    background: radial-gradient(circle, #6366f1 0%, transparent 70%);
}

.vc-cert-deco-2 {
    bottom: -100px;
    left: -100px;
    background: radial-gradient(circle, #ec4899 0%, transparent 70%);
}

/* Logo area */
.vc-cert-logo {
    text-align: center;
    margin-bottom: 30px;
}

.vc-cert-logo img {
    width: 70px;
    height: 70px;
    object-fit: contain;
    border-radius: 16px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
}

.vc-cert-logo-placeholder {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    font-size: 1.8rem;
    color: white;
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
}

/* Certificate content */
.vc-cert-content {
    text-align: center;
    position: relative;
    z-index: 1;
}

.vc-cert-title {
    font-size: 0.9rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 4px;
    color: rgba(255, 255, 255, 0.4);
    margin: 0 0 8px;
}

.vc-cert-main-title {
    font-size: 2.6rem;
    font-weight: 800;
    background: linear-gradient(135deg, #fff, #e2e8f0, #cbd5e1);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0 0 35px;
    letter-spacing: 1px;
}

.vc-cert-presents {
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0 0 15px;
    font-weight: 500;
}

.vc-cert-recipient {
    font-size: 3rem;
    font-weight: 800;
    background: linear-gradient(135deg, #a5b4fc, #c4b5fd, #f0abfc);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0 0 10px;
    line-height: 1.2;
}

.vc-cert-line {
    width: 250px;
    height: 2px;
    background: linear-gradient(90deg, transparent, rgba(165, 180, 252, 0.5), transparent);
    margin: 0 auto 35px;
}

.vc-cert-body {
    font-size: 1.1rem;
    color: rgba(255, 255, 255, 0.65);
    line-height: 1.8;
    margin: 0 auto 25px;
    max-width: 480px;
}

/* Hours highlight */
.vc-cert-hours {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2));
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 16px;
    padding: 18px 35px;
    margin: 15px 0 35px;
}

.vc-cert-hours-value {
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, #a5b4fc, #c4b5fd);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.vc-cert-hours-label {
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.6);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Signature section */
.vc-cert-signatures {
    display: flex;
    justify-content: space-between;
    margin-top: 45px;
    padding: 0 20px;
}

.vc-cert-sig {
    text-align: center;
    min-width: 160px;
}

.vc-cert-sig-value {
    font-size: 1.3rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.85);
    margin-bottom: 8px;
}

.vc-cert-sig-line {
    width: 140px;
    height: 1px;
    background: rgba(255, 255, 255, 0.2);
    margin: 0 auto 10px;
}

.vc-cert-sig-label {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.4);
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Award badge */
.vc-cert-badge {
    position: absolute;
    bottom: 25px;
    left: 50%;
    transform: translateX(-50%);
    width: 65px;
    height: 65px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    box-shadow:
        0 6px 25px rgba(99, 102, 241, 0.5),
        inset 0 2px 4px rgba(255, 255, 255, 0.2);
}

/* History Card */
.vc-history {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.06);
    overflow: hidden;
}

.vc-history-header {
    padding: 20px 26px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.vc-history-title {
    color: white;
    font-size: 1.1rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
}

.vc-history-body {
    padding: 18px 26px;
    max-height: 280px;
    overflow-y: auto;
}

.vc-history-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}

.vc-history-item:last-child {
    border-bottom: none;
}

.vc-history-org {
    color: white;
    font-weight: 600;
    font-size: 0.95rem;
}

.vc-history-date {
    color: rgba(255, 255, 255, 0.45);
    font-size: 0.85rem;
    margin-top: 4px;
}

.vc-history-hours {
    background: rgba(99, 102, 241, 0.15);
    color: #a5b4fc;
    padding: 6px 14px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 0.85rem;
    border: 1px solid rgba(99, 102, 241, 0.2);
}

/* Empty state */
.vc-empty {
    padding: 70px 40px;
    text-align: center;
}

.vc-empty-icon {
    font-size: 4.5rem;
    margin-bottom: 25px;
    opacity: 0.3;
    background: linear-gradient(135deg, #6366f1, #ec4899);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.vc-empty-title {
    color: white;
    margin: 0 0 12px;
    font-size: 1.4rem;
    font-weight: 700;
}

.vc-empty-text {
    color: rgba(255, 255, 255, 0.5);
    margin: 0 0 30px;
    font-size: 1rem;
}

/* Light Mode */
[data-theme="light"] .vc-page::before {
    background:
        radial-gradient(ellipse at 15% 25%, rgba(99, 102, 241, 0.12) 0%, transparent 50%),
        radial-gradient(ellipse at 85% 75%, rgba(236, 72, 153, 0.08) 0%, transparent 50%),
        linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #f1f5f9 100%);
}

[data-theme="light"] .vc-orb { opacity: 0.25; }
[data-theme="light"] .vc-header-subtitle { color: rgba(0, 0, 0, 0.5); }
[data-theme="light"] .vc-stat { background: rgba(255, 255, 255, 0.7); border-color: rgba(0, 0, 0, 0.06); }
[data-theme="light"] .vc-stat-label { color: rgba(0, 0, 0, 0.5); }
[data-theme="light"] .vc-card { background: rgba(255, 255, 255, 0.8); border-color: rgba(0, 0, 0, 0.06); }
[data-theme="light"] .vc-card-title { color: #1e293b; }
[data-theme="light"] .vc-history { background: rgba(255, 255, 255, 0.8); border-color: rgba(0, 0, 0, 0.06); }
[data-theme="light"] .vc-history-title { color: #1e293b; }
[data-theme="light"] .vc-history-org { color: #1e293b; }
[data-theme="light"] .vc-history-date { color: rgba(0, 0, 0, 0.5); }
[data-theme="light"] .vc-btn-secondary { background: rgba(0, 0, 0, 0.05); color: rgba(0, 0, 0, 0.7); }

/* Mobile */
@media (max-width: 768px) {
    .vc-header-title { font-size: 1.9rem; }
    .vc-header-icon { width: 75px; height: 75px; font-size: 2.2rem; }
    .vc-actions { flex-direction: column; }
    .vc-btn { width: 100%; justify-content: center; }

    .vc-cert-wrapper { padding: 20px; }
    .vc-cert { padding: 35px 25px 70px; }
    .vc-cert-main-title { font-size: 1.9rem; }
    .vc-cert-recipient { font-size: 2.2rem; }
    .vc-cert-hours { padding: 14px 25px; flex-direction: column; gap: 5px; }
    .vc-cert-hours-value { font-size: 2rem; }
    .vc-cert-signatures { flex-direction: column; gap: 35px; padding: 0; }
    .vc-cert-badge { width: 55px; height: 55px; font-size: 1.3rem; bottom: 18px; }
}

/* ============================================
   PRINT STYLES
   ============================================ */

@media print {
    /* Page setup - minimal margins to fit content */
    @page {
        size: landscape;
        margin: 0.3in;
    }

    /* Reset everything */
    html, body {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        width: 100% !important;
        height: 100% !important;
    }

    /* Hide everything except print area */
    body * {
        visibility: hidden !important;
    }

    .vc-print-area,
    .vc-print-area * {
        visibility: visible !important;
    }

    .vc-print-area {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        height: auto !important;
        background: white !important;
        padding: 0.2in !important;
        margin: 0 !important;
        box-sizing: border-box !important;
    }

    /* Print certificate styles - sized to fit on one page */
    .vc-print-cert {
        background: white !important;
        border: 3px solid #1e293b !important;
        border-radius: 0 !important;
        padding: 35px 45px !important;
        text-align: center !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        box-sizing: border-box !important;
        page-break-inside: avoid !important;
    }

    .vc-print-title {
        font-size: 2.2rem !important;
        font-weight: 800 !important;
        color: #1e293b !important;
        margin: 0 0 8px !important;
        text-transform: uppercase !important;
        letter-spacing: 3px !important;
    }

    .vc-print-subtitle {
        font-size: 0.95rem !important;
        color: #64748b !important;
        margin: 0 0 30px !important;
        text-transform: uppercase !important;
        letter-spacing: 2px !important;
    }

    .vc-print-presents {
        font-size: 1rem !important;
        color: #475569 !important;
        margin: 0 0 15px !important;
    }

    .vc-print-name {
        font-size: 2.4rem !important;
        font-weight: 700 !important;
        color: #6366f1 !important;
        margin: 0 0 12px !important;
        font-style: italic !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .vc-print-line {
        width: 280px !important;
        height: 2px !important;
        background: #cbd5e1 !important;
        margin: 0 auto 30px !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .vc-print-body {
        font-size: 1rem !important;
        color: #475569 !important;
        line-height: 1.7 !important;
        margin: 0 auto 20px !important;
        max-width: 480px !important;
    }

    .vc-print-hours {
        display: inline-block !important;
        font-size: 1.9rem !important;
        font-weight: 800 !important;
        color: #6366f1 !important;
        border: 2px solid #6366f1 !important;
        padding: 12px 35px !important;
        border-radius: 8px !important;
        margin: 0 0 35px !important;
        background: rgba(99, 102, 241, 0.05) !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .vc-print-sigs {
        display: flex !important;
        justify-content: space-between !important;
        margin-top: 40px !important;
        padding: 0 40px !important;
    }

    .vc-print-sig {
        text-align: center !important;
    }

    .vc-print-sig-val {
        font-size: 1.1rem !important;
        color: #1e293b !important;
        margin-bottom: 8px !important;
    }

    .vc-print-sig-line {
        width: 160px !important;
        height: 1px !important;
        background: #1e293b !important;
        margin: 0 auto 6px !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .vc-print-sig-label {
        font-size: 0.8rem !important;
        color: #64748b !important;
        text-transform: uppercase !important;
    }
}

/* Print tip styling */
.vc-print-tip {
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    padding: 14px 18px;
    margin-top: 20px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.vc-print-tip i {
    color: #a5b4fc;
    font-size: 1.1rem;
    margin-top: 2px;
}

.vc-print-tip-content {
    flex: 1;
}

.vc-print-tip-title {
    color: #a5b4fc;
    font-weight: 700;
    font-size: 0.9rem;
    margin-bottom: 4px;
}

.vc-print-tip-text {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
    line-height: 1.5;
}

[data-theme="light"] .vc-print-tip {
    background: rgba(99, 102, 241, 0.08);
}

[data-theme="light"] .vc-print-tip-title {
    color: #6366f1;
}

[data-theme="light"] .vc-print-tip-text {
    color: rgba(0, 0, 0, 0.6);
}

/* Screen-only class */
.vc-screen-only {
    display: block;
}

@media print {
    .vc-screen-only {
        display: none !important;
    }
}

/* Print-only class */
.vc-print-only {
    display: none;
}

@media print {
    .vc-print-only {
        display: block !important;
    }
}
</style>

<!-- Print-only certificate (simplified for clean printing) -->
<?php if ($totalHours > 0 || $previewMode): ?>
<div class="vc-print-area vc-print-only">
    <div class="vc-print-cert">
        <h1 class="vc-print-title">Certificate</h1>
        <p class="vc-print-subtitle">of Volunteer Service</p>

        <p class="vc-print-presents">This is to certify that</p>

        <p class="vc-print-name"><?= htmlspecialchars($userName) ?></p>
        <div class="vc-print-line"></div>

        <p class="vc-print-body">
            has generously dedicated their time and effort to support the community,
            contributing a verified total of
        </p>

        <div class="vc-print-hours"><?= number_format($displayHours, 1) ?> Hours</div>

        <p class="vc-print-body">of voluntary service.</p>

        <div class="vc-print-sigs">
            <div class="vc-print-sig">
                <div class="vc-print-sig-line"></div>
                <div class="vc-print-sig-label">Date: <?= $date ?></div>
            </div>
            <div class="vc-print-sig">
                <div class="vc-print-sig-val"><?= htmlspecialchars($tenantName) ?></div>
                <div class="vc-print-sig-line"></div>
                <div class="vc-print-sig-label">Authorized Signature</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main screen content -->
<div class="vc-page vc-screen-only">
    <!-- Floating Orbs -->
    <div class="vc-orb vc-orb-1"></div>
    <div class="vc-orb vc-orb-2"></div>
    <div class="vc-orb vc-orb-3"></div>

    <div class="vc-container">
        <!-- Page Header -->
        <div class="vc-header">
            <div class="vc-header-icon">
                <i class="fa-solid fa-award"></i>
            </div>
            <h1 class="vc-header-title">Volunteer Certificate</h1>
            <p class="vc-header-subtitle">Your verified volunteering record</p>
        </div>

        <!-- Action Buttons -->
        <div class="vc-actions">
            <?php if ($totalHours > 0 || $previewMode): ?>
            <button onclick="window.print()" class="vc-btn vc-btn-primary">
                <i class="fa-solid fa-print"></i>
                Print Certificate
            </button>
            <?php endif; ?>
            <?php if ($previewMode): ?>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/volunteering/certificate" class="vc-btn vc-btn-secondary">
                <i class="fa-solid fa-times"></i>
                Exit Preview
            </a>
            <?php else: ?>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/volunteering/certificate?preview=1" class="vc-btn vc-btn-secondary">
                <i class="fa-solid fa-eye"></i>
                Preview Design
            </a>
            <?php endif; ?>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/volunteering/my-applications" class="vc-btn vc-btn-secondary">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Applications
            </a>
        </div>

        <?php if ($previewMode): ?>
        <div class="vc-preview-banner">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            <div>
                <strong>Preview Mode</strong>
                <span>Showing sample data to demonstrate the certificate design</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Row -->
        <div class="vc-stats">
            <div class="vc-stat">
                <div class="vc-stat-value"><?= number_format($displayHours, 1) ?></div>
                <div class="vc-stat-label">Total Verified Hours</div>
            </div>
            <div class="vc-stat">
                <div class="vc-stat-value"><?= $displayActivities ?></div>
                <div class="vc-stat-label">Activities Completed</div>
            </div>
            <div class="vc-stat">
                <div class="vc-stat-value"><?= $displayOrgs ?></div>
                <div class="vc-stat-label">Organizations Helped</div>
            </div>
        </div>

        <?php if ($totalHours > 0 || $previewMode): ?>
        <!-- Certificate Preview -->
        <div class="vc-card">
            <div class="vc-card-header">
                <div class="vc-card-title">
                    <i class="fa-solid fa-scroll"></i>
                    Certificate Preview
                </div>
                <span class="vc-card-badge"><?= $previewMode ? 'Sample' : 'Official' ?></span>
            </div>

            <div class="vc-cert-wrapper">
                <div class="vc-cert">
                    <!-- Decorative elements -->
                    <div class="vc-cert-deco vc-cert-deco-1"></div>
                    <div class="vc-cert-deco vc-cert-deco-2"></div>

                    <!-- Logo -->
                    <div class="vc-cert-logo">
                        <?php if ($tenantLogo): ?>
                            <img src="<?= htmlspecialchars($tenantLogo) ?>" loading="lazy" alt="<?= htmlspecialchars($tenantName) ?>">
                        <?php else: ?>
                            <div class="vc-cert-logo-placeholder">
                                <i class="fa-solid fa-hands-helping"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Content -->
                    <div class="vc-cert-content">
                        <p class="vc-cert-title">Certificate</p>
                        <h2 class="vc-cert-main-title">Volunteer Service</h2>

                        <p class="vc-cert-presents">This is to certify that</p>

                        <h3 class="vc-cert-recipient"><?= htmlspecialchars($userName) ?></h3>
                        <div class="vc-cert-line"></div>

                        <p class="vc-cert-body">
                            has generously dedicated their time and effort to support the community,
                            contributing a verified total of
                        </p>

                        <div class="vc-cert-hours">
                            <span class="vc-cert-hours-value"><?= number_format($displayHours, 1) ?></span>
                            <span class="vc-cert-hours-label">Hours</span>
                        </div>

                        <p class="vc-cert-body">of voluntary service.</p>

                        <div class="vc-cert-signatures">
                            <div class="vc-cert-sig">
                                <div class="vc-cert-sig-line"></div>
                                <div class="vc-cert-sig-label">Date: <?= $date ?></div>
                            </div>
                            <div class="vc-cert-sig">
                                <div class="vc-cert-sig-value"><?= htmlspecialchars($tenantName) ?></div>
                                <div class="vc-cert-sig-line"></div>
                                <div class="vc-cert-sig-label">Authorized Signature</div>
                            </div>
                        </div>
                    </div>

                    <!-- Badge -->
                    <div class="vc-cert-badge">
                        <i class="fa-solid fa-award"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Print Tip -->
        <div class="vc-print-tip">
            <i class="fa-solid fa-lightbulb"></i>
            <div class="vc-print-tip-content">
                <div class="vc-print-tip-title">Print Tip</div>
                <div class="vc-print-tip-text">
                    For a clean certificate without date/URL text, click "More settings" in the print dialog and uncheck "Headers and footers". You can also save as PDF for a digital copy.
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- No Hours Message -->
        <div class="vc-card">
            <div class="vc-card-header">
                <div class="vc-card-title">
                    <i class="fa-solid fa-scroll"></i>
                    Certificate
                </div>
            </div>
            <div class="vc-empty">
                <div class="vc-empty-icon">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
                <h3 class="vc-empty-title">No Verified Hours Yet</h3>
                <p class="vc-empty-text">Start volunteering and log your hours to receive a certificate.</p>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/volunteering" class="vc-btn vc-btn-primary" style="display: inline-flex;">
                    <i class="fa-solid fa-search"></i>
                    Find Opportunities
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Volunteer History -->
        <?php if (!empty($displayLogs)): ?>
        <div class="vc-history">
            <div class="vc-history-header">
                <h3 class="vc-history-title">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <?= $previewMode ? 'Sample Activity Log' : 'Verified Activity Log' ?>
                </h3>
            </div>
            <div class="vc-history-body">
                <?php foreach ($displayLogs as $log): ?>
                    <div class="vc-history-item">
                        <div>
                            <div class="vc-history-org"><?= htmlspecialchars($log['org_name'] ?? 'Volunteer Activity') ?></div>
                            <div class="vc-history-date"><?= date('M j, Y', strtotime($log['date_logged'])) ?></div>
                        </div>
                        <div class="vc-history-hours"><?= $log['hours'] ?>h</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
