<?php
// Phoenix View: Social Impact Report - Gold Standard v7.0
// Modern Polish Edition - January 2026
$pageTitle = 'Social Impact Study';
$hideHero = true;

require __DIR__ . '/../../../..' . '/layouts/modern/header.php';
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<style>
/* ============================================
   GOLD STANDARD v7.0 - Native App Features
   Theme Color: Deep Violet (#7c3aed)
   ============================================ */

:root {
    --ir-primary: #7c3aed;
    --ir-primary-rgb: 124, 58, 237;
    --ir-primary-light: #8b5cf6;
    --ir-primary-dark: #6d28d9;
    --ir-secondary: #06b6d4;
    --ir-secondary-rgb: 6, 182, 212;
    --ir-accent: #f59e0b;
    --ir-success: #10b981;
    --ir-gradient: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 50%, #a78bfa 100%);
}

/* Offline Banner */
.offline-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 10001;
    padding: 12px 20px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    font-size: 0.9rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transform: translateY(-100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.offline-banner.visible { transform: translateY(0); }

/* Content Reveal Animation */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
}

@keyframes slideInLeft {
    from { opacity: 0; transform: translateX(-30px); }
    to { opacity: 1; transform: translateX(0); }
}

@keyframes countUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

@keyframes shimmer {
    0% { background-position: -200% center; }
    100% { background-position: 200% center; }
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

/* Touch Targets - WCAG 2.1 AA */
.ir-btn, .ir-nav-link, .ir-tab-btn {
    min-height: 44px !important;
    display: inline-flex;
    align-items: center;
}

/* Focus Visible */
.ir-btn:focus-visible, .ir-nav-link:focus-visible, a:focus-visible {
    outline: 3px solid rgba(124, 58, 237, 0.5);
    outline-offset: 2px;
}

/* Smooth Scroll */
html {
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}

/* ========================================
   MAIN WRAPPER
   ======================================== */
#impact-report-wrapper {
    position: relative;
    min-height: 100vh;
    padding: 0;
    animation: fadeInUp 0.5s ease-out;
}

/* Animated Background */
#impact-report-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    pointer-events: none;
}

[data-theme="light"] #impact-report-wrapper::before {
    background:
        radial-gradient(ellipse at 0% 0%, rgba(124, 58, 237, 0.08) 0%, transparent 50%),
        radial-gradient(ellipse at 100% 0%, rgba(6, 182, 212, 0.06) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 100%, rgba(245, 158, 11, 0.04) 0%, transparent 50%),
        linear-gradient(180deg, rgba(255,255,255,1) 0%, rgba(250,250,255,1) 100%);
}

[data-theme="dark"] #impact-report-wrapper::before {
    background:
        radial-gradient(ellipse at 0% 0%, rgba(124, 58, 237, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 100% 0%, rgba(6, 182, 212, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 100%, rgba(245, 158, 11, 0.05) 0%, transparent 50%);
}

/* ========================================
   HERO SECTION
   ======================================== */
.ir-hero {
    position: relative;
    padding: 160px 1.5rem 80px;
    text-align: center;
    overflow: hidden;
}

@media (max-width: 900px) {
    .ir-hero { padding: 100px 1rem 60px; }
}

.ir-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--ir-gradient);
    opacity: 0.03;
    z-index: -1;
}

[data-theme="dark"] .ir-hero::before {
    opacity: 0.08;
}

.ir-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    animation: scaleIn 0.4s ease-out 0.1s both;
}

[data-theme="light"] .ir-hero-badge {
    background: rgba(124, 58, 237, 0.1);
    color: var(--ir-primary);
    border: 1px solid rgba(124, 58, 237, 0.2);
}

[data-theme="dark"] .ir-hero-badge {
    background: rgba(124, 58, 237, 0.2);
    color: var(--ir-primary-light);
    border: 1px solid rgba(124, 58, 237, 0.3);
}

.ir-hero-title {
    font-size: clamp(2.5rem, 6vw, 4rem);
    font-weight: 900;
    line-height: 1.1;
    margin: 0 0 1rem 0;
    background: var(--ir-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: fadeInUp 0.5s ease-out 0.2s both;
}

.ir-hero-subtitle {
    font-size: clamp(1.1rem, 2.5vw, 1.35rem);
    color: var(--htb-text-muted);
    max-width: 600px;
    margin: 0 auto 2rem;
    line-height: 1.6;
    animation: fadeInUp 0.5s ease-out 0.3s both;
}

.ir-hero-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
    animation: fadeInUp 0.5s ease-out 0.4s both;
}

.ir-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 28px;
    border-radius: 14px;
    font-weight: 700;
    font-size: 0.95rem;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid transparent;
    cursor: pointer;
}

.ir-btn i { font-size: 1.1rem; }

.ir-btn-primary {
    background: var(--ir-gradient);
    color: white;
    box-shadow: 0 8px 30px rgba(124, 58, 237, 0.4);
}

.ir-btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 40px rgba(124, 58, 237, 0.5);
}

.ir-btn-outline {
    color: var(--htb-text-main);
    border-color: rgba(124, 58, 237, 0.3);
}

[data-theme="light"] .ir-btn-outline {
    background: rgba(255, 255, 255, 0.8);
}

[data-theme="dark"] .ir-btn-outline {
    background: rgba(30, 41, 59, 0.6);
    border-color: rgba(124, 58, 237, 0.4);
}

.ir-btn-outline:hover {
    background: var(--ir-gradient);
    color: white;
    border-color: transparent;
    transform: translateY(-3px);
}

/* ========================================
   SROI SHOWCASE - HERO STAT
   ======================================== */
.ir-sroi-showcase {
    max-width: 900px;
    margin: -20px auto 60px;
    padding: 0 1.5rem;
    animation: scaleIn 0.6s ease-out 0.5s both;
}

.ir-sroi-card {
    position: relative;
    padding: 3rem 2.5rem;
    border-radius: 28px;
    text-align: center;
    overflow: hidden;
    background: var(--ir-gradient);
    box-shadow:
        0 20px 60px rgba(124, 58, 237, 0.3),
        0 0 0 1px rgba(255, 255, 255, 0.1) inset;
}

.ir-sroi-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, transparent 50%, rgba(0,0,0,0.1) 100%);
    pointer-events: none;
}

.ir-sroi-label {
    font-size: 1.1rem;
    font-weight: 600;
    color: rgba(255,255,255,0.9);
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 0.5rem;
}

.ir-sroi-number {
    font-size: clamp(4rem, 12vw, 7rem);
    font-weight: 900;
    color: white;
    line-height: 1;
    margin: 0.5rem 0;
    text-shadow: 0 4px 30px rgba(0,0,0,0.2);
    animation: pulse 3s ease-in-out infinite;
}

.ir-sroi-desc {
    font-size: 1.25rem;
    color: rgba(255,255,255,0.95);
    margin-bottom: 1.5rem;
}

.ir-sroi-detail {
    display: inline-block;
    padding: 12px 24px;
    background: rgba(255,255,255,0.15);
    border-radius: 50px;
    font-size: 0.95rem;
    color: rgba(255,255,255,0.9);
    backdrop-filter: blur(10px);
}

/* ========================================
   QUICK STATS GRID
   ======================================== */
.ir-stats-section {
    max-width: 1200px;
    margin: 0 auto 60px;
    padding: 0 1.5rem;
}

.ir-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
}

@media (max-width: 1024px) {
    .ir-stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 600px) {
    .ir-stats-grid { grid-template-columns: 1fr; }
}

.ir-stat-card {
    padding: 2rem 1.5rem;
    border-radius: 20px;
    text-align: center;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    transition: all 0.3s ease;
    animation: fadeInUp 0.5s ease-out both;
}

.ir-stat-card:nth-child(1) { animation-delay: 0.1s; }
.ir-stat-card:nth-child(2) { animation-delay: 0.2s; }
.ir-stat-card:nth-child(3) { animation-delay: 0.3s; }
.ir-stat-card:nth-child(4) { animation-delay: 0.4s; }

[data-theme="light"] .ir-stat-card {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(124, 58, 237, 0.1);
    box-shadow: 0 4px 20px rgba(124, 58, 237, 0.08);
}

[data-theme="dark"] .ir-stat-card {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(124, 58, 237, 0.2);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.ir-stat-card:hover {
    transform: translateY(-8px);
}

[data-theme="light"] .ir-stat-card:hover {
    box-shadow: 0 16px 40px rgba(124, 58, 237, 0.15);
}

[data-theme="dark"] .ir-stat-card:hover {
    box-shadow: 0 16px 40px rgba(124, 58, 237, 0.25);
}

.ir-stat-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 1rem;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
}

.ir-stat-icon.purple {
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.15), rgba(124, 58, 237, 0.05));
}

.ir-stat-icon.cyan {
    background: linear-gradient(135deg, rgba(6, 182, 212, 0.15), rgba(6, 182, 212, 0.05));
}

.ir-stat-icon.amber {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(245, 158, 11, 0.05));
}

.ir-stat-icon.emerald {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(16, 185, 129, 0.05));
}

.ir-stat-value {
    font-size: 2.5rem;
    font-weight: 900;
    line-height: 1;
    margin-bottom: 0.5rem;
}

.ir-stat-value.purple { color: var(--ir-primary); }
.ir-stat-value.cyan { color: var(--ir-secondary); }
.ir-stat-value.amber { color: var(--ir-accent); }
.ir-stat-value.emerald { color: var(--ir-success); }

.ir-stat-label {
    font-size: 0.95rem;
    color: var(--htb-text-muted);
    font-weight: 500;
}

/* ========================================
   TAB NAVIGATION
   ======================================== */
.ir-content-section {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1.5rem 4rem;
}

.ir-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 2rem;
    padding: 8px;
    border-radius: 16px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

[data-theme="light"] .ir-tabs {
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(124, 58, 237, 0.1);
}

[data-theme="dark"] .ir-tabs {
    background: rgba(30, 41, 59, 0.5);
    border: 1px solid rgba(124, 58, 237, 0.2);
}

.ir-tab-btn {
    flex-shrink: 0;
    padding: 12px 20px;
    border-radius: 12px;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--htb-text-muted);
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.ir-tab-btn:hover {
    color: var(--ir-primary);
}

[data-theme="light"] .ir-tab-btn:hover {
    background: rgba(124, 58, 237, 0.05);
}

[data-theme="dark"] .ir-tab-btn:hover {
    background: rgba(124, 58, 237, 0.1);
}

.ir-tab-btn.active {
    background: var(--ir-gradient);
    color: white;
    box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
}

/* Tab Panel */
.ir-tab-panel {
    display: none;
    animation: fadeInUp 0.4s ease-out;
}

.ir-tab-panel.active {
    display: block;
}

/* ========================================
   GLASS SECTIONS
   ======================================== */
.ir-glass-section {
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 24px;
    margin-bottom: 2rem;
    overflow: hidden;
    transition: all 0.3s ease;
}

[data-theme="light"] .ir-glass-section {
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid rgba(124, 58, 237, 0.1);
    box-shadow: 0 8px 32px rgba(124, 58, 237, 0.08);
}

[data-theme="dark"] .ir-glass-section {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(124, 58, 237, 0.15);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
}

.ir-glass-section:hover {
    transform: translateY(-4px);
}

[data-theme="light"] .ir-glass-section:hover {
    box-shadow: 0 16px 48px rgba(124, 58, 237, 0.12);
}

[data-theme="dark"] .ir-glass-section:hover {
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.35);
}

/* Section Header */
.ir-section-header {
    padding: 1.5rem 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-bottom: 1px solid rgba(124, 58, 237, 0.1);
}

[data-theme="light"] .ir-section-header {
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.05) 0%, transparent 100%);
}

[data-theme="dark"] .ir-section-header {
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.1) 0%, transparent 100%);
}

.ir-section-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    background: var(--ir-gradient);
    color: white;
    box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
}

.ir-section-title {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0;
}

.ir-section-badge {
    margin-left: auto;
    padding: 6px 14px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
}

[data-theme="light"] .ir-section-badge {
    background: rgba(124, 58, 237, 0.1);
    color: var(--ir-primary);
}

[data-theme="dark"] .ir-section-badge {
    background: rgba(124, 58, 237, 0.2);
    color: var(--ir-primary-light);
}

/* Section Body */
.ir-section-body {
    padding: 2rem;
}

.ir-section-body p {
    color: var(--htb-text-muted);
    font-size: 1.05rem;
    line-height: 1.8;
    margin: 0 0 1.25rem 0;
}

.ir-section-body p:last-child {
    margin-bottom: 0;
}

.ir-section-body h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 2rem 0 1rem 0;
}

.ir-section-body h3:first-child {
    margin-top: 0;
}

.ir-section-body ul, .ir-section-body ol {
    color: var(--htb-text-muted);
    line-height: 1.8;
    margin: 0 0 1.25rem 0;
    padding-left: 1.5rem;
}

.ir-section-body li {
    margin-bottom: 0.75rem;
}

/* ========================================
   CASE STUDY CARDS
   ======================================== */
.ir-case-study {
    padding: 1.75rem;
    border-radius: 20px;
    margin: 1.5rem 0;
    position: relative;
    overflow: hidden;
}

[data-theme="light"] .ir-case-study {
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.08) 0%, rgba(124, 58, 237, 0.02) 100%);
    border-left: 4px solid var(--ir-primary);
}

[data-theme="dark"] .ir-case-study {
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.15) 0%, rgba(124, 58, 237, 0.05) 100%);
    border-left: 4px solid var(--ir-primary-light);
}

.ir-case-study::before {
    content: '"';
    position: absolute;
    top: -10px;
    right: 20px;
    font-size: 8rem;
    font-family: Georgia, serif;
    color: var(--ir-primary);
    opacity: 0.1;
    line-height: 1;
}

.ir-case-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 1rem;
}

.ir-case-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--ir-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    font-weight: 700;
}

.ir-case-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--ir-primary);
    margin: 0;
}

.ir-case-study p {
    margin-bottom: 1rem;
}

.ir-testimonial {
    padding: 1.25rem;
    border-radius: 14px;
    font-style: italic;
    margin-top: 1rem;
}

[data-theme="light"] .ir-testimonial {
    background: rgba(124, 58, 237, 0.08);
    color: var(--htb-text-main);
}

[data-theme="dark"] .ir-testimonial {
    background: rgba(124, 58, 237, 0.15);
    color: var(--htb-text-main);
}

/* ========================================
   CHARTS & DATA VISUALIZATION
   ======================================== */
.ir-chart-container {
    padding: 1.5rem;
    border-radius: 18px;
    margin: 1.5rem 0;
}

[data-theme="light"] .ir-chart-container {
    background: rgba(124, 58, 237, 0.04);
    border: 1px solid rgba(124, 58, 237, 0.1);
}

[data-theme="dark"] .ir-chart-container {
    background: rgba(124, 58, 237, 0.08);
    border: 1px solid rgba(124, 58, 237, 0.15);
}

.ir-chart-title {
    text-align: center;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 1.5rem 0;
}

.ir-bar-chart {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.ir-bar-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.ir-bar-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--htb-text-muted);
}

.ir-bar-track {
    height: 28px;
    border-radius: 14px;
    overflow: hidden;
    position: relative;
}

[data-theme="light"] .ir-bar-track {
    background: rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .ir-bar-track {
    background: rgba(255, 255, 255, 0.1);
}

.ir-bar-fill {
    height: 100%;
    border-radius: 14px;
    transition: width 1s ease-out;
    position: relative;
}

.ir-bar-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    background-size: 200% 100%;
    animation: shimmer 2s infinite;
}

.ir-bar-fill.purple { background: linear-gradient(90deg, #7c3aed, #a78bfa); }
.ir-bar-fill.cyan { background: linear-gradient(90deg, #0891b2, #22d3ee); }
.ir-bar-fill.amber { background: linear-gradient(90deg, #d97706, #fbbf24); }
.ir-bar-fill.emerald { background: linear-gradient(90deg, #059669, #34d399); }

/* ========================================
   DATA TABLE
   ======================================== */
.ir-table-wrapper {
    overflow-x: auto;
    margin: 1.5rem 0;
    border-radius: 16px;
}

.ir-table {
    width: 100%;
    border-collapse: collapse;
}

.ir-table th {
    padding: 1rem 1.25rem;
    text-align: left;
    font-weight: 700;
    font-size: 0.9rem;
    background: var(--ir-gradient);
    color: white;
}

.ir-table th:first-child { border-radius: 16px 0 0 0; }
.ir-table th:last-child { border-radius: 0 16px 0 0; }

.ir-table td {
    padding: 1rem 1.25rem;
    font-size: 0.95rem;
    color: var(--htb-text-muted);
}

[data-theme="light"] .ir-table td {
    border-bottom: 1px solid rgba(124, 58, 237, 0.08);
    background: rgba(255, 255, 255, 0.6);
}

[data-theme="dark"] .ir-table td {
    border-bottom: 1px solid rgba(124, 58, 237, 0.12);
    background: rgba(30, 41, 59, 0.4);
}

[data-theme="light"] .ir-table tr:nth-child(even) td {
    background: rgba(124, 58, 237, 0.02);
}

[data-theme="dark"] .ir-table tr:nth-child(even) td {
    background: rgba(124, 58, 237, 0.05);
}

.ir-table tr:last-child td:first-child { border-radius: 0 0 0 16px; }
.ir-table tr:last-child td:last-child { border-radius: 0 0 16px 0; }

/* ========================================
   STICKY TOC SIDEBAR
   ======================================== */
.ir-layout {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 2.5rem;
    align-items: start;
}

@media (max-width: 1024px) {
    .ir-layout { grid-template-columns: 1fr; }
}

.ir-sidebar {
    position: sticky;
    top: 100px;
}

@media (max-width: 1024px) {
    .ir-sidebar { display: none; }
}

.ir-toc {
    padding: 1.5rem;
    border-radius: 20px;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}

[data-theme="light"] .ir-toc {
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(124, 58, 237, 0.15);
    box-shadow: 0 8px 32px rgba(124, 58, 237, 0.1);
}

[data-theme="dark"] .ir-toc {
    background: rgba(30, 41, 59, 0.7);
    border: 1px solid rgba(124, 58, 237, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.ir-toc-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--ir-primary);
    margin: 0 0 1.25rem 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.ir-toc-list {
    list-style: none;
    padding: 0;
    margin: 0 0 1.5rem 0;
}

.ir-toc-list li { margin-bottom: 0.5rem; }

.ir-toc-link {
    display: block;
    padding: 0.6rem 0.875rem;
    border-radius: 10px;
    font-size: 0.875rem;
    color: var(--htb-text-muted);
    text-decoration: none;
    transition: all 0.2s ease;
}

.ir-toc-link:hover {
    color: var(--ir-primary);
    padding-left: 1.125rem;
}

[data-theme="light"] .ir-toc-link:hover {
    background: rgba(124, 58, 237, 0.08);
}

[data-theme="dark"] .ir-toc-link:hover {
    background: rgba(124, 58, 237, 0.15);
}

.ir-toc-link.active {
    color: var(--ir-primary);
    font-weight: 600;
}

[data-theme="light"] .ir-toc-link.active {
    background: rgba(124, 58, 237, 0.1);
}

[data-theme="dark"] .ir-toc-link.active {
    background: rgba(124, 58, 237, 0.2);
}

/* TOC Download Buttons */
.ir-toc-downloads {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(124, 58, 237, 0.15);
}

.ir-toc-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 0.875rem 1rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    transition: all 0.3s ease;
}

.ir-toc-btn.primary {
    background: var(--ir-gradient);
    color: white;
    box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
}

.ir-toc-btn.primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(124, 58, 237, 0.4);
}

.ir-toc-btn.secondary {
    border: 1px solid rgba(124, 58, 237, 0.3);
    color: var(--htb-text-main);
}

[data-theme="light"] .ir-toc-btn.secondary {
    background: rgba(255, 255, 255, 0.8);
}

[data-theme="dark"] .ir-toc-btn.secondary {
    background: rgba(30, 41, 59, 0.8);
}

.ir-toc-btn.secondary:hover {
    border-color: var(--ir-primary);
    transform: translateY(-2px);
}

/* ========================================
   BIBLIOGRAPHY
   ======================================== */
.ir-bibliography {
    list-style: none;
    padding: 0;
    margin: 0;
    counter-reset: bib-counter;
}

.ir-bibliography li {
    counter-increment: bib-counter;
    padding: 1rem 1rem 1rem 3.5rem;
    margin-bottom: 0.75rem;
    border-radius: 12px;
    position: relative;
    font-size: 0.95rem;
    line-height: 1.6;
    color: var(--htb-text-muted);
}

[data-theme="light"] .ir-bibliography li {
    background: rgba(124, 58, 237, 0.04);
}

[data-theme="dark"] .ir-bibliography li {
    background: rgba(124, 58, 237, 0.08);
}

.ir-bibliography li::before {
    content: counter(bib-counter);
    position: absolute;
    left: 1rem;
    top: 1rem;
    width: 24px;
    height: 24px;
    background: var(--ir-gradient);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
}

/* ========================================
   CTA FOOTER
   ======================================== */
.ir-cta-section {
    max-width: 900px;
    margin: 0 auto 60px;
    padding: 0 1.5rem;
}

.ir-cta-card {
    text-align: center;
    padding: 3.5rem 2.5rem;
    border-radius: 28px;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    position: relative;
    overflow: hidden;
}

[data-theme="light"] .ir-cta-card {
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.08) 0%, rgba(124, 58, 237, 0.02) 100%);
    border: 1px solid rgba(124, 58, 237, 0.15);
}

[data-theme="dark"] .ir-cta-card {
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.15) 0%, rgba(124, 58, 237, 0.05) 100%);
    border: 1px solid rgba(124, 58, 237, 0.25);
}

.ir-cta-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(124, 58, 237, 0.1) 0%, transparent 50%);
    animation: float 6s ease-in-out infinite;
    pointer-events: none;
}

.ir-cta-title {
    font-size: 2rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 1rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.ir-cta-text {
    font-size: 1.1rem;
    color: var(--htb-text-muted);
    max-width: 550px;
    margin: 0 auto 2rem;
    line-height: 1.7;
}

.ir-cta-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}

/* ========================================
   RESPONSIVE
   ======================================== */
@media (max-width: 768px) {
    .ir-hero { padding: 90px 1rem 50px; }
    .ir-hero-actions { flex-direction: column; gap: 10px; }
    .ir-btn { width: 100%; justify-content: center; }
    .ir-sroi-showcase { margin: 0 auto 40px; padding: 0 1rem; }
    .ir-sroi-card { padding: 2rem 1.5rem; }
    .ir-sroi-number { font-size: 4rem; }
    .ir-stats-section { padding: 0 1rem; }
    .ir-content-section { padding: 0 1rem 3rem; }
    .ir-section-header { padding: 1.25rem 1.5rem; flex-wrap: wrap; }
    .ir-section-body { padding: 1.5rem; }
    .ir-section-title { font-size: 1.25rem; }
    .ir-tabs { padding: 6px; gap: 4px; }
    .ir-tab-btn { padding: 10px 14px; font-size: 0.85rem; }
    .ir-cta-section { padding: 0 1rem; }
    .ir-cta-card { padding: 2.5rem 1.5rem; }
    .ir-cta-title { font-size: 1.5rem; flex-direction: column; gap: 8px; }
    .ir-cta-actions { flex-direction: column; }
}

/* Browser Fallback */
@supports not (backdrop-filter: blur(10px)) {
    [data-theme="light"] .ir-glass-section,
    [data-theme="light"] .ir-toc,
    [data-theme="light"] .ir-stat-card,
    [data-theme="light"] .ir-cta-card {
        background: rgba(255, 255, 255, 0.95);
    }
    [data-theme="dark"] .ir-glass-section,
    [data-theme="dark"] .ir-toc,
    [data-theme="dark"] .ir-stat-card,
    [data-theme="dark"] .ir-cta-card {
        background: rgba(30, 41, 59, 0.95);
    }
}

/* Print Styles */
@media print {
    .ir-hero { padding-top: 20px; }
    .ir-hero-actions, .ir-tabs, .ir-toc-downloads, .ir-cta-section, .ir-sidebar { display: none; }
    .ir-glass-section, .ir-stat-card { break-inside: avoid; box-shadow: none; border: 1px solid #ddd; }
}
</style>

<div id="impact-report-wrapper">

    <!-- Hero Section -->
    <section class="ir-hero">
        <div class="ir-hero-badge">
            <i class="fa-solid fa-award"></i>
            <span>Independently Validated Research</span>
        </div>
        <h1 class="ir-hero-title">Social Impact Study</h1>
        <p class="ir-hero-subtitle">Discover how hOUR Timebank creates transformational change in West Cork communities through the power of time exchange.</p>
        <div class="ir-hero-actions">
            <a href="/uploads/tenants/hour-timebank/TBI-Social-Impact-Study-Executive-Summary-Design-Version-Final.pdf" target="_blank" class="ir-btn ir-btn-primary">
                <i class="fa-solid fa-file-pdf"></i>
                <span>Executive Summary</span>
            </a>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/impact-summary" class="ir-btn ir-btn-outline">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Impact Summary</span>
            </a>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/contact" class="ir-btn ir-btn-outline">
                <i class="fa-solid fa-envelope"></i>
                <span>Contact Us</span>
            </a>
        </div>
    </section>

    <!-- SROI Showcase -->
    <section class="ir-sroi-showcase">
        <div class="ir-sroi-card">
            <p class="ir-sroi-label">Social Return on Investment</p>
            <div class="ir-sroi-number" data-target="16">€<span class="counter">16</span></div>
            <p class="ir-sroi-desc">generated for every €1 invested</p>
            <span class="ir-sroi-detail">
                <i class="fa-solid fa-chart-line"></i>
                Total Present Value: €803,184 from €50,000 input
            </span>
        </div>
    </section>

    <!-- Quick Stats Grid -->
    <section class="ir-stats-section">
        <div class="ir-stats-grid">
            <div class="ir-stat-card">
                <div class="ir-stat-icon purple">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="ir-stat-value purple" data-target="391">391</div>
                <div class="ir-stat-label">Active Members</div>
            </div>
            <div class="ir-stat-card">
                <div class="ir-stat-icon cyan">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div class="ir-stat-value cyan" data-target="2868">2,868</div>
                <div class="ir-stat-label">Hours Exchanged</div>
            </div>
            <div class="ir-stat-card">
                <div class="ir-stat-icon amber">
                    <i class="fa-solid fa-exchange-alt"></i>
                </div>
                <div class="ir-stat-value amber" data-target="797">797</div>
                <div class="ir-stat-label">Transactions</div>
            </div>
            <div class="ir-stat-card">
                <div class="ir-stat-icon emerald">
                    <i class="fa-solid fa-heart"></i>
                </div>
                <div class="ir-stat-value emerald">100%</div>
                <div class="ir-stat-label">Wellbeing Improved</div>
            </div>
        </div>
    </section>

    <!-- Main Content Section -->
    <section class="ir-content-section">

        <!-- Tab Navigation -->
        <div class="ir-tabs" role="tablist">
            <button class="ir-tab-btn active" role="tab" data-tab="overview">
                <i class="fa-solid fa-house"></i>&nbsp; Overview
            </button>
            <button class="ir-tab-btn" role="tab" data-tab="methodology">
                <i class="fa-solid fa-microscope"></i>&nbsp; Methodology
            </button>
            <button class="ir-tab-btn" role="tab" data-tab="activity">
                <i class="fa-solid fa-chart-bar"></i>&nbsp; Activity Data
            </button>
            <button class="ir-tab-btn" role="tab" data-tab="impact">
                <i class="fa-solid fa-heart-pulse"></i>&nbsp; Impact
            </button>
            <button class="ir-tab-btn" role="tab" data-tab="recommendations">
                <i class="fa-solid fa-lightbulb"></i>&nbsp; Recommendations
            </button>
        </div>

        <!-- Tab: Overview -->
        <div class="ir-tab-panel active" id="tab-overview">
            <div class="ir-layout">
                <aside class="ir-sidebar">
                    <nav class="ir-toc">
                        <h3 class="ir-toc-title">
                            <i class="fa-solid fa-list"></i> Contents
                        </h3>
                        <ul class="ir-toc-list">
                            <li><a href="#introduction" class="ir-toc-link active">1. Introduction</a></li>
                            <li><a href="#objectives" class="ir-toc-link">2. Objectives</a></li>
                            <li><a href="#about-tbi" class="ir-toc-link">3. About hOUR Timebank</a></li>
                            <li><a href="#literature" class="ir-toc-link">4. Literature Review</a></li>
                            <li><a href="#sroi-method" class="ir-toc-link">5. SROI Method</a></li>
                        </ul>
                        <div class="ir-toc-downloads">
                            <a href="/uploads/tenants/hour-timebank/TBI-Social-Impact-Study-Executive-Summary-Design-Version-Final.pdf" target="_blank" class="ir-toc-btn primary">
                                <i class="fa-solid fa-file-pdf"></i> Executive Summary
                            </a>
                            <a href="/uploads/tenants/hour-timebank/TBI-Social-Impact-Study-Final-Full-Report-May-23.pdf" target="_blank" class="ir-toc-btn secondary">
                                <i class="fa-solid fa-download"></i> Full Report PDF
                            </a>
                        </div>
                    </nav>
                </aside>

                <div class="ir-main-content">
                    <!-- Introduction -->
                    <article id="introduction" class="ir-glass-section">
                        <header class="ir-section-header">
                            <div class="ir-section-icon">
                                <i class="fa-solid fa-book-open"></i>
                            </div>
                            <h2 class="ir-section-title">Introduction & Context</h2>
                            <span class="ir-section-badge">Section 1</span>
                        </header>
                        <div class="ir-section-body">
                            <p>As part of their ongoing Social Inclusion & Community Activation Programme (SICAP) support for hOUR Timebank (TBI), West Cork Development Partnership (WCDP) commissioned this social impact study for the twelve-month period, November 1st, 2021, to October 31st, 2022.</p>

                            <p>This study demonstrates how TBI creates measurable social value through community-based time exchange, particularly for vulnerable and hard-to-reach groups in rural West Cork.</p>

                            <div class="ir-case-study">
                                <div class="ir-case-header">
                                    <div class="ir-case-avatar">M</div>
                                    <h4 class="ir-case-name">Case Study: Monica</h4>
                                </div>
                                <p>Monica found out about TBI through the outreach mental health team. She lives alone in a rural area with no family support and found it difficult to integrate in West Cork. Monica has post-traumatic stress disorder and an auto immune condition.</p>
                                <p>Since getting involved in TBI, Monica feels much more connected to the community, which has had a positive mental health impact.</p>
                                <div class="ir-testimonial">
                                    "Contact is important for both giver and receiver... Giving is good for the soul and being in contact with new people is lovely. Helping someone out has given me a new purpose in life."
                                </div>
                            </div>
                        </div>
                    </article>

                    <!-- Objectives -->
                    <article id="objectives" class="ir-glass-section">
                        <header class="ir-section-header">
                            <div class="ir-section-icon">
                                <i class="fa-solid fa-bullseye"></i>
                            </div>
                            <h2 class="ir-section-title">Study Objectives</h2>
                            <span class="ir-section-badge">Section 2</span>
                        </header>
                        <div class="ir-section-body">
                            <p>The objectives of this comprehensive study were to:</p>
                            <ul>
                                <li><strong>Demonstrate social impact</strong> through measuring outcomes for members including many vulnerable and hard to reach groups.</li>
                                <li><strong>Capture engagement methods</strong> showing how TBI engages with participants in an effective and tangible way.</li>
                                <li><strong>Tell the transformation story</strong> of the journey of change experienced by TBI members.</li>
                                <li><strong>Enhance value proposition</strong> to strengthen applications to statutory and philanthropic funders.</li>
                                <li><strong>Provide replication guidance</strong> for those considering expanding the initiative to other areas.</li>
                                <li><strong>Identify key learning</strong> and actionable recommendations.</li>
                            </ul>
                        </div>
                    </article>

                    <!-- About TBI -->
                    <article id="about-tbi" class="ir-glass-section">
                        <header class="ir-section-header">
                            <div class="ir-section-icon">
                                <i class="fa-solid fa-handshake"></i>
                            </div>
                            <h2 class="ir-section-title">About hOUR Timebank</h2>
                            <span class="ir-section-badge">Section 3</span>
                        </header>
                        <div class="ir-section-body">
                            <p>TBI is a group of people who help and support each other by sharing services, skills, talents, and knowledge. Its vision is of an interconnected community where meaningful relationships strengthen resilience, solidarity, and prosperity.</p>

                            <p>Members provide services voluntarily enabling them to give and receive time—<strong>no money is exchanged</strong>. Through this exchange, TBI appreciates the value of every member and recognises all have needs as well as gifts to share.</p>

                            <h3>How It Works</h3>
                            <p>Anyone is eligible to join TBI. An online portal displays each member's array of skills as a list of offers and requests. Members deposit time in the Timebank by spending a few hours delivering a requested service. They are then able to withdraw these time credits when they need help themselves.</p>

                            <h3>Common Exchanges Include</h3>
                            <ul>
                                <li>Shopping & Errands</li>
                                <li>Gardening & Outdoor Work</li>
                                <li>Transport & Lifts</li>
                                <li>Computer & Tech Help</li>
                                <li>Language Lessons</li>
                                <li>DIY & Home Repairs</li>
                                <li>Befriending & Companionship</li>
                            </ul>

                            <div class="ir-case-study">
                                <div class="ir-case-header">
                                    <div class="ir-case-avatar">J</div>
                                    <h4 class="ir-case-name">Case Study: John</h4>
                                </div>
                                <p>John got involved through a friend about 3 years ago and has significant health issues. He lives in a rural and remote area in West Cork. John receives support with transport through TBI, getting lifts to hospital and GP appointments, but also help in the garden. Through a Meitheal, John's cottage was painted, and some handyman jobs were completed.</p>
                                <div class="ir-testimonial">
                                    "I've been a member of timebank for a while and it is a fantastic organisation. I think belonging to timebank has helped change my life... I recommend anyone should join timebank and offer a service."
                                </div>
                            </div>
                        </div>
                    </article>

                    <!-- Literature Review -->
                    <article id="literature" class="ir-glass-section">
                        <header class="ir-section-header">
                            <div class="ir-section-icon">
                                <i class="fa-solid fa-book"></i>
                            </div>
                            <h2 class="ir-section-title">Literature Review</h2>
                            <span class="ir-section-badge">Section 4</span>
                        </header>
                        <div class="ir-section-body">
                            <p>The review of timebank literature for this study was concerned with identifying areas for development and learning for TBI and validation of current practice.</p>

                            <h3>Timebanking in Ireland</h3>
                            <p>In a 2020 study, Isaac Hurley found that out of fifty-seven Community Currencies (CC) established in Ireland between 2000 and 2020, only three were still operational. Hurley concludes that TBI has great potential and represented a <strong>"clear step up in evolution of Irish CCs towards more broadly appreciated and professional systems."</strong></p>

                            <h3>Timebanking in the UK</h3>
                            <p>Under the New Labour government (1997-2010), Timebanks (TBs) were viewed as a tool to address social exclusion. A 2014 Cambridge University evaluation found TBs were successful in investing in community capacity and supporting social capital.</p>

                            <div class="ir-case-study">
                                <div class="ir-case-header">
                                    <div class="ir-case-avatar">B</div>
                                    <h4 class="ir-case-name">Case Study: Brenda</h4>
                                </div>
                                <p>Brenda joined in early 2022 to give back to the community. She has a great interest in nature and growing vegetables.</p>
                                <div class="ir-testimonial">
                                    "TBI has been crucial for settling back into life in West Cork after being away for 25 years."
                                </div>
                            </div>
                        </div>
                    </article>

                    <!-- SROI Method -->
                    <article id="sroi-method" class="ir-glass-section">
                        <header class="ir-section-header">
                            <div class="ir-section-icon">
                                <i class="fa-solid fa-calculator"></i>
                            </div>
                            <h2 class="ir-section-title">SROI Methodology</h2>
                            <span class="ir-section-badge">Section 5</span>
                        </header>
                        <div class="ir-section-body">
                            <p>As Social Return on Investment (SROI) is both social value and outcome focussed, we believe it provides the most robust framework for measuring the impact and quality of TBI.</p>

                            <h3>Mixed Method Approach</h3>
                            <p>A mixed method approach was adopted for data collection, capturing both quantitative and qualitative data:</p>
                            <ul>
                                <li><strong>Web-based survey</strong> eliciting 30 responses from TBI members.</li>
                                <li><strong>Semi-structured interviews</strong> with a further ten TBI members.</li>
                                <li><strong>Stakeholder consultations</strong> with external partners and supporters.</li>
                            </ul>

                            <p>Proxy costs were estimated for outcomes (e.g., improved health = cost of community counselling). The total input for the study period was <strong>€50,000</strong>.</p>
                        </div>
                    </article>
                </div>
            </div>
        </div>

        <!-- Tab: Methodology -->
        <div class="ir-tab-panel" id="tab-methodology">
            <article class="ir-glass-section">
                <header class="ir-section-header">
                    <div class="ir-section-icon">
                        <i class="fa-solid fa-flask"></i>
                    </div>
                    <h2 class="ir-section-title">Research Methodology</h2>
                </header>
                <div class="ir-section-body">
                    <h3>Data Collection Methods</h3>
                    <p>A comprehensive mixed-method approach was employed to ensure robust and reliable findings:</p>

                    <div class="ir-chart-container">
                        <h4 class="ir-chart-title">Research Sample Breakdown</h4>
                        <div class="ir-bar-chart">
                            <div class="ir-bar-item">
                                <div class="ir-bar-label">
                                    <span>Web Survey Responses</span>
                                    <span>30 participants</span>
                                </div>
                                <div class="ir-bar-track">
                                    <div class="ir-bar-fill purple" style="width: 75%"></div>
                                </div>
                            </div>
                            <div class="ir-bar-item">
                                <div class="ir-bar-label">
                                    <span>1-1 Interviews</span>
                                    <span>10 participants</span>
                                </div>
                                <div class="ir-bar-track">
                                    <div class="ir-bar-fill cyan" style="width: 25%"></div>
                                </div>
                            </div>
                            <div class="ir-bar-item">
                                <div class="ir-bar-label">
                                    <span>External Stakeholders</span>
                                    <span>5 organisations</span>
                                </div>
                                <div class="ir-bar-track">
                                    <div class="ir-bar-fill amber" style="width: 12.5%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h3>SROI Framework</h3>
                    <p>The Social Return on Investment framework allows us to quantify social value created. We estimated proxy costs for each outcome category and calculated the present value of benefits against total inputs.</p>
                </div>
            </article>
        </div>

        <!-- Tab: Activity Data -->
        <div class="ir-tab-panel" id="tab-activity">
            <article class="ir-glass-section">
                <header class="ir-section-header">
                    <div class="ir-section-icon">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <h2 class="ir-section-title">TBI Activity 2021-22</h2>
                </header>
                <div class="ir-section-body">
                    <h3>Membership Growth</h3>
                    <p>There was a significant increase in enabled users from 219 to 391 over the year—a <strong>79% growth rate</strong>. 95% of enabled users are resident in County Cork.</p>

                    <div class="ir-chart-container">
                        <h4 class="ir-chart-title">Member Age Distribution</h4>
                        <div class="ir-bar-chart">
                            <div class="ir-bar-item">
                                <div class="ir-bar-label">
                                    <span>56 - 65 years</span>
                                    <span>37.5%</span>
                                </div>
                                <div class="ir-bar-track">
                                    <div class="ir-bar-fill purple" style="width: 37.5%"></div>
                                </div>
                            </div>
                            <div class="ir-bar-item">
                                <div class="ir-bar-label">
                                    <span>66+ years</span>
                                    <span>20%</span>
                                </div>
                                <div class="ir-bar-track">
                                    <div class="ir-bar-fill cyan" style="width: 20%"></div>
                                </div>
                            </div>
                            <div class="ir-bar-item">
                                <div class="ir-bar-label">
                                    <span>46 - 55 years</span>
                                    <span>17.5%</span>
                                </div>
                                <div class="ir-bar-track">
                                    <div class="ir-bar-fill amber" style="width: 17.5%"></div>
                                </div>
                            </div>
                            <div class="ir-bar-item">
                                <div class="ir-bar-label">
                                    <span>36 - 45 years</span>
                                    <span>17.5%</span>
                                </div>
                                <div class="ir-bar-track">
                                    <div class="ir-bar-fill emerald" style="width: 17.5%"></div>
                                </div>
                            </div>
                            <div class="ir-bar-item">
                                <div class="ir-bar-label">
                                    <span>Under 36 years</span>
                                    <span>7.5%</span>
                                </div>
                                <div class="ir-bar-track">
                                    <div class="ir-bar-fill purple" style="width: 7.5%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h3>Transaction Activity</h3>
                    <p>The currency used is time credits in units of one hour. <strong>2,868 hours</strong> were exchanged via <strong>797 transactions</strong>. By the end of October 2022, there was more than one million hours of time credits in the Community Treasure Chest.</p>

                    <div class="ir-table-wrapper">
                        <table class="ir-table">
                            <thead>
                                <tr>
                                    <th>Metric</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Gross Income</td>
                                    <td>1,941.10 Time Credits</td>
                                </tr>
                                <tr>
                                    <td>Number of Incoming Transfers</td>
                                    <td>559</td>
                                </tr>
                                <tr>
                                    <td>Number of Logins</td>
                                    <td>1,560</td>
                                </tr>
                                <tr>
                                    <td>Balance of Community Account</td>
                                    <td>1,007,748.95</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="ir-case-study">
                        <div class="ir-case-header">
                            <div class="ir-case-avatar">D</div>
                            <h4 class="ir-case-name">Case Study: Delores</h4>
                        </div>
                        <p>Delores had an accident and felt very isolated during Covid. She has received support with a clean up and has had plans drawn for her home. The home clean enabled her to get a council grant for home improvements, and the whole experience helped improve her mental well-being.</p>
                    </div>
                </div>
            </article>
        </div>

        <!-- Tab: Impact -->
        <div class="ir-tab-panel" id="tab-impact">
            <article class="ir-glass-section">
                <header class="ir-section-header">
                    <div class="ir-section-icon">
                        <i class="fa-solid fa-heart"></i>
                    </div>
                    <h2 class="ir-section-title">Impact on Members</h2>
                </header>
                <div class="ir-section-body">
                    <p>This section explores the impact for members, the primary TBI stakeholder. <strong>40 members were consulted</strong> through surveys and interviews.</p>

                    <h3>Social Connection Outcomes</h3>
                    <p><strong>95% of respondents</strong> indicated they felt more socially connected due to TBI.</p>

                    <div class="ir-chart-container">
                        <h4 class="ir-chart-title">Social Connection Improvement</h4>
                        <div class="ir-bar-chart">
                            <div class="ir-bar-item">
                                <div class="ir-bar-label">
                                    <span>A Little More Connected</span>
                                    <span>50%</span>
                                </div>
                                <div class="ir-bar-track">
                                    <div class="ir-bar-fill cyan" style="width: 50%"></div>
                                </div>
                            </div>
                            <div class="ir-bar-item">
                                <div class="ir-bar-label">
                                    <span>Much More Connected</span>
                                    <span>45%</span>
                                </div>
                                <div class="ir-bar-track">
                                    <div class="ir-bar-fill purple" style="width: 45%"></div>
                                </div>
                            </div>
                            <div class="ir-bar-item">
                                <div class="ir-bar-label">
                                    <span>No Change</span>
                                    <span>5%</span>
                                </div>
                                <div class="ir-bar-track">
                                    <div class="ir-bar-fill amber" style="width: 5%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h3>Wellbeing Improvement</h3>
                    <p><strong>100% of respondents</strong> indicated their wellbeing had improved since joining TBI. This remarkable result demonstrates the profound impact of community connection and mutual support.</p>

                    <div class="ir-case-study">
                        <div class="ir-case-header">
                            <div class="ir-case-avatar">E</div>
                            <h4 class="ir-case-name">Case Study: Elaine</h4>
                        </div>
                        <p>Elaine is single with no family, lives remotely, and struggles with mental health issues and chronic pain. Since joining TBI, she has found both purpose in helping others and vital support for her own needs.</p>
                        <div class="ir-testimonial">
                            "TBI is the most important support in my life."
                        </div>
                    </div>
                </div>
            </article>
        </div>

        <!-- Tab: Recommendations -->
        <div class="ir-tab-panel" id="tab-recommendations">
            <article class="ir-glass-section">
                <header class="ir-section-header">
                    <div class="ir-section-icon">
                        <i class="fa-solid fa-lightbulb"></i>
                    </div>
                    <h2 class="ir-section-title">Key Recommendations</h2>
                </header>
                <div class="ir-section-body">
                    <h3>Sustainable Funding</h3>
                    <p>We recommend TBI use the SROI findings (<strong>1:16</strong>) to approach funders. The initial ask should be to increase the Broker role, which is central to facilitating exchanges and supporting members.</p>

                    <h3>Increasing Membership</h3>
                    <p>The community account has over <strong>1 million time credits</strong>—this represents a significant opportunity to increase membership and activity. TBI should work with WCDP to identify impactful projects to deploy these credits in the community.</p>

                    <h3>Social Prescribing Integration</h3>
                    <p>The study explicitly concluded that Timebank Ireland <strong>"could become part of a social prescribing offering"</strong>. This represents a significant opportunity for healthcare integration and sustainable funding streams.</p>
                </div>
            </article>

            <!-- Bibliography -->
            <article class="ir-glass-section">
                <header class="ir-section-header">
                    <div class="ir-section-icon">
                        <i class="fa-solid fa-bookmark"></i>
                    </div>
                    <h2 class="ir-section-title">Bibliography</h2>
                </header>
                <div class="ir-section-body">
                    <ol class="ir-bibliography">
                        <li>Bretherton, Joanne and Pleace, Nicholas (2014) <em>An evaluation of the Broadway Skills Exchange Time Bank</em>. Centre for Housing Policy, University of York.</li>
                        <li>Burgess, G. (2014) <em>Evaluation of the Cambridgeshire Timebanks</em>. Cambridge Centre for Housing & Planning Research.</li>
                        <li>Hurley, Isaac (2020) <em>Uncovering Ireland's Monetary Ecology: Community Currencies in Ireland 2000-2020</em>. Trinity College Dublin.</li>
                    </ol>
                </div>
            </article>
        </div>

    </section>

    <!-- CTA Section -->
    <section class="ir-cta-section">
        <div class="ir-cta-card">
            <h2 class="ir-cta-title">
                <i class="fa-solid fa-rocket"></i>
                Ready to Create Impact?
            </h2>
            <p class="ir-cta-text">Join our growing community or partner with us to bring the transformational benefits of timebanking to more people across Ireland.</p>
            <div class="ir-cta-actions">
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/register" class="ir-btn ir-btn-primary">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>Join Now</span>
                </a>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/partner" class="ir-btn ir-btn-outline">
                    <i class="fa-solid fa-handshake"></i>
                    <span>Partner With Us</span>
                </a>
            </div>
        </div>
    </section>

</div>

<script>
// ============================================
// GOLD STANDARD v7.0 - Interactive Features
// ============================================

(function() {
    'use strict';

    // Offline Indicator
    const offlineBanner = document.getElementById('offlineBanner');
    if (offlineBanner) {
        function handleOffline() {
            offlineBanner.classList.add('visible');
            if (navigator.vibrate) navigator.vibrate(100);
        }
        function handleOnline() {
            offlineBanner.classList.remove('visible');
        }
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
        if (!navigator.onLine) handleOffline();
    }

    // Tab Navigation
    const tabBtns = document.querySelectorAll('.ir-tab-btn');
    const tabPanels = document.querySelectorAll('.ir-tab-panel');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.getAttribute('data-tab');

            // Update active states
            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanels.forEach(p => p.classList.remove('active'));

            btn.classList.add('active');
            document.getElementById('tab-' + tabId)?.classList.add('active');

            // Scroll to top of content
            document.querySelector('.ir-content-section')?.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        });
    });

    // TOC Active State
    const tocLinks = document.querySelectorAll('.ir-toc-link');
    const sections = document.querySelectorAll('.ir-glass-section[id]');

    function updateTOC() {
        let current = '';
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            if (window.scrollY >= sectionTop - 150) {
                current = section.getAttribute('id');
            }
        });

        tocLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === '#' + current) {
                link.classList.add('active');
            }
        });
    }

    window.addEventListener('scroll', updateTOC, { passive: true });

    // Animated Counters
    function animateCounters() {
        const counters = document.querySelectorAll('[data-target]');

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const target = parseInt(entry.target.getAttribute('data-target'));
                    const counter = entry.target.querySelector('.counter') || entry.target;
                    const duration = 2000;
                    const step = target / (duration / 16);
                    let current = 0;

                    const animate = () => {
                        current += step;
                        if (current < target) {
                            counter.textContent = Math.floor(current).toLocaleString();
                            requestAnimationFrame(animate);
                        } else {
                            counter.textContent = target.toLocaleString();
                        }
                    };

                    animate();
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(counter => observer.observe(counter));
    }

    // Initialize counters after DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', animateCounters);
    } else {
        animateCounters();
    }

    // Button Press States
    document.querySelectorAll('.ir-btn, .ir-toc-btn, .ir-tab-btn').forEach(btn => {
        btn.addEventListener('pointerdown', function() {
            this.style.transform = 'scale(0.96)';
        });
        btn.addEventListener('pointerup', function() {
            this.style.transform = '';
        });
        btn.addEventListener('pointerleave', function() {
            this.style.transform = '';
        });
    });

    // Dynamic Theme Color for Mobile
    function updateThemeColor() {
        let meta = document.querySelector('meta[name="theme-color"]');
        if (!meta) {
            meta = document.createElement('meta');
            meta.name = 'theme-color';
            document.head.appendChild(meta);
        }
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        meta.setAttribute('content', isDark ? '#0f172a' : '#7c3aed');
    }

    const themeObserver = new MutationObserver(updateThemeColor);
    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });
    updateThemeColor();

    // Animate bars on scroll
    const barFills = document.querySelectorAll('.ir-bar-fill');
    const barObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const width = entry.target.style.width;
                entry.target.style.width = '0%';
                setTimeout(() => {
                    entry.target.style.width = width;
                }, 100);
                barObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.3 });

    barFills.forEach(bar => barObserver.observe(bar));

})();
</script>

<?php require __DIR__ . '/../../../..' . '/layouts/modern/footer.php'; ?>
