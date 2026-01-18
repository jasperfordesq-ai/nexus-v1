<?php
// Phoenix Create Listing View - Full Holographic Glassmorphism Edition
$hero_title = "Post a Listing";
$hero_subtitle = "Offer help or request assistance.";
$hero_gradient = 'htb-hero-gradient-create';
$hero_type = 'Contribution';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';

// Get user data for location
$currentUser = \Nexus\Models\User::findById($_SESSION['user_id']);
$userLocation = $currentUser['location'] ?? null;
$basePath = Nexus\Core\TenantContext::getBasePath();
$type = $_GET['type'] ?? 'offer';
?>

<style>
/* ============================================
   HOLOGRAPHIC GLASSMORPHISM CREATE LISTING
   Full Modern Design System
   ============================================ */

/* Page Background with Ambient Effects */
.holo-create-page {
    min-height: 100vh;
    padding: 180px 20px 60px;
    position: relative;
    overflow: hidden;
}

@media (max-width: 900px) {
    .holo-create-page {
        padding: 20px 16px 120px;
    }
}

/* Animated Background Gradient */
.holo-create-page::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background:
        radial-gradient(ellipse 80% 50% at 20% 40%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse 60% 40% at 80% 60%, rgba(236, 72, 153, 0.12) 0%, transparent 50%),
        radial-gradient(ellipse 50% 30% at 50% 80%, rgba(6, 182, 212, 0.1) 0%, transparent 50%);
    pointer-events: none;
    z-index: -1;
    animation: holoShift 20s ease-in-out infinite alternate;
}

[data-theme="dark"] .holo-create-page::before {
    background:
        radial-gradient(ellipse 80% 50% at 20% 40%, rgba(99, 102, 241, 0.2) 0%, transparent 50%),
        radial-gradient(ellipse 60% 40% at 80% 60%, rgba(236, 72, 153, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse 50% 30% at 50% 80%, rgba(6, 182, 212, 0.12) 0%, transparent 50%);
}

@keyframes holoShift {
    0% { opacity: 1; transform: scale(1); }
    100% { opacity: 0.8; transform: scale(1.1); }
}

/* Floating Orbs */
.holo-orb {
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    pointer-events: none;
    z-index: -1;
    opacity: 0.4;
}

.holo-orb-1 {
    width: 400px;
    height: 400px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    top: 10%;
    left: -10%;
    animation: orbFloat1 15s ease-in-out infinite;
}

.holo-orb-2 {
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, #ec4899, #f43f5e);
    bottom: 20%;
    right: -5%;
    animation: orbFloat2 18s ease-in-out infinite;
}

.holo-orb-3 {
    width: 250px;
    height: 250px;
    background: linear-gradient(135deg, #06b6d4, #10b981);
    top: 60%;
    left: 30%;
    animation: orbFloat3 12s ease-in-out infinite;
}

@keyframes orbFloat1 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(50px, 30px) scale(1.1); }
}

@keyframes orbFloat2 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-40px, -20px) scale(0.9); }
}

@keyframes orbFloat3 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(30px, -40px) scale(1.05); }
}

/* Main Container */
.holo-create-container {
    max-width: 720px;
    margin: 0 auto;
    animation: fadeInUp 0.5s ease-out;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Page Header */
.holo-page-header {
    text-align: center;
    margin-bottom: 40px;
}

.holo-page-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
    border-radius: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    box-shadow:
        0 20px 40px rgba(99, 102, 241, 0.3),
        0 0 60px rgba(99, 102, 241, 0.2);
    animation: iconPulse 3s ease-in-out infinite;
}

@keyframes iconPulse {
    0%, 100% { box-shadow: 0 20px 40px rgba(99, 102, 241, 0.3), 0 0 60px rgba(99, 102, 241, 0.2); }
    50% { box-shadow: 0 25px 50px rgba(99, 102, 241, 0.4), 0 0 80px rgba(99, 102, 241, 0.3); }
}

.holo-page-title {
    font-size: 2.5rem;
    font-weight: 800;
    margin: 0 0 12px;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    letter-spacing: -1px;
}

.holo-page-subtitle {
    font-size: 1.1rem;
    color: var(--htb-text-muted, #64748b);
    margin: 0;
}

/* Glass Card */
.holo-glass-card {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(40px) saturate(180%);
    -webkit-backdrop-filter: blur(40px) saturate(180%);
    border-radius: 32px;
    border: 1px solid rgba(255, 255, 255, 0.5);
    padding: 48px 40px;
    box-shadow:
        0 25px 50px rgba(0, 0, 0, 0.08),
        0 0 100px rgba(99, 102, 241, 0.08),
        inset 0 0 0 1px rgba(255, 255, 255, 0.3);
    position: relative;
    overflow: hidden;
}

[data-theme="dark"] .holo-glass-card {
    background: rgba(15, 23, 42, 0.6);
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow:
        0 25px 50px rgba(0, 0, 0, 0.4),
        0 0 100px rgba(99, 102, 241, 0.15),
        inset 0 0 0 1px rgba(255, 255, 255, 0.05);
}

/* Card Shimmer Effect */
.holo-glass-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.1),
        transparent
    );
    animation: shimmer 8s ease-in-out infinite;
    pointer-events: none;
}

@keyframes shimmer {
    0% { left: -100%; }
    50%, 100% { left: 100%; }
}

@media (max-width: 768px) {
    .holo-glass-card {
        padding: 32px 24px;
        border-radius: 24px;
    }

    .holo-page-title {
        font-size: 1.8rem;
    }

    .holo-page-icon {
        width: 64px;
        height: 64px;
        font-size: 2rem;
        border-radius: 18px;
    }
}

/* Type Selection Cards */
.holo-type-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 32px;
}

@media (max-width: 600px) {
    .holo-type-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
}

.holo-type-card {
    position: relative;
    padding: 28px 24px;
    border-radius: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid transparent;
    background: rgba(0, 0, 0, 0.02);
    overflow: hidden;
}

[data-theme="dark"] .holo-type-card {
    background: rgba(255, 255, 255, 0.03);
}

.holo-type-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
}

.holo-type-card.selected-offer {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(6, 182, 212, 0.1) 100%);
    border-color: #10b981;
    box-shadow:
        0 12px 30px rgba(16, 185, 129, 0.2),
        inset 0 0 30px rgba(16, 185, 129, 0.05);
}

.holo-type-card.selected-request {
    background: linear-gradient(135deg, rgba(249, 115, 22, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
    border-color: #f97316;
    box-shadow:
        0 12px 30px rgba(249, 115, 22, 0.2),
        inset 0 0 30px rgba(249, 115, 22, 0.05);
}

.holo-type-icon {
    font-size: 3rem;
    margin-bottom: 12px;
    display: block;
}

.holo-type-label {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--htb-text-main, #1e293b);
}

[data-theme="dark"] .holo-type-label {
    color: #f1f5f9;
}

.holo-type-desc {
    font-size: 0.85rem;
    color: var(--htb-text-muted, #64748b);
    margin-top: 4px;
}

/* Section Headers */
.holo-section {
    margin-bottom: 28px;
}

.holo-section-title {
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--htb-text-muted, #64748b);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.holo-section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, rgba(99, 102, 241, 0.3), transparent);
}

/* Form Labels */
.holo-label {
    display: block;
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--htb-text-main, #1e293b);
    margin-bottom: 10px;
}

[data-theme="dark"] .holo-label {
    color: #e2e8f0;
}

.holo-label-optional {
    font-weight: 400;
    font-size: 0.8rem;
    color: var(--htb-text-muted, #94a3b8);
    margin-left: 6px;
}

/* Input Fields */
.holo-input,
.holo-select,
.holo-textarea {
    width: 100%;
    padding: 16px 20px;
    border-radius: 16px;
    font-size: 1rem;
    font-family: inherit;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid rgba(0, 0, 0, 0.06);
    background: rgba(255, 255, 255, 0.6);
    color: var(--htb-text-main, #0f172a);
    outline: none;
}

[data-theme="dark"] .holo-input,
[data-theme="dark"] .holo-select,
[data-theme="dark"] .holo-textarea {
    background: rgba(0, 0, 0, 0.2);
    border-color: rgba(255, 255, 255, 0.08);
    color: #f8fafc;
}

.holo-input:focus,
.holo-select:focus,
.holo-textarea:focus {
    border-color: #6366f1;
    background: rgba(255, 255, 255, 0.9);
    box-shadow:
        0 0 0 4px rgba(99, 102, 241, 0.1),
        0 8px 20px rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] .holo-input:focus,
[data-theme="dark"] .holo-select:focus,
[data-theme="dark"] .holo-textarea:focus {
    background: rgba(0, 0, 0, 0.3);
    border-color: #818cf8;
    box-shadow:
        0 0 0 4px rgba(99, 102, 241, 0.15),
        0 8px 20px rgba(99, 102, 241, 0.15);
}

.holo-input::placeholder,
.holo-textarea::placeholder {
    color: var(--htb-text-muted, #94a3b8);
}

.holo-textarea {
    resize: vertical;
    min-height: 140px;
    line-height: 1.6;
}

/* Select Dropdown */
.holo-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 16px center;
    background-size: 18px;
    padding-right: 48px;
    cursor: pointer;
}

/* Location Info Box */
.holo-location-box {
    padding: 20px 24px;
    border-radius: 16px;
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 28px;
}

.holo-location-box.has-location {
    background: linear-gradient(135deg, rgba(6, 182, 212, 0.08) 0%, rgba(16, 185, 129, 0.08) 100%);
    border: 1px solid rgba(6, 182, 212, 0.2);
}

.holo-location-box.no-location {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.08) 0%, rgba(239, 68, 68, 0.08) 100%);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.holo-location-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}

.has-location .holo-location-icon {
    background: linear-gradient(135deg, #06b6d4, #10b981);
    color: white;
}

.no-location .holo-location-icon {
    background: linear-gradient(135deg, #f59e0b, #ef4444);
    color: white;
}

.holo-location-content {
    flex: 1;
}

.holo-location-title {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--htb-text-main, #1e293b);
    margin-bottom: 4px;
}

[data-theme="dark"] .holo-location-title {
    color: #f1f5f9;
}

.holo-location-text {
    font-size: 0.9rem;
    color: var(--htb-text-muted, #64748b);
    line-height: 1.5;
}

.holo-location-text a {
    color: #6366f1;
    font-weight: 600;
    text-decoration: none;
}

.holo-location-text a:hover {
    text-decoration: underline;
}

/* File Upload */
.holo-file-upload {
    position: relative;
    border: 2px dashed rgba(99, 102, 241, 0.3);
    border-radius: 16px;
    padding: 32px 24px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: rgba(99, 102, 241, 0.02);
}

.holo-file-upload:hover {
    border-color: #6366f1;
    background: rgba(99, 102, 241, 0.05);
}

.holo-file-upload input[type="file"] {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.holo-file-icon {
    font-size: 2.5rem;
    color: #6366f1;
    margin-bottom: 12px;
}

.holo-file-text {
    font-weight: 600;
    color: var(--htb-text-main, #1e293b);
    margin-bottom: 4px;
}

[data-theme="dark"] .holo-file-text {
    color: #e2e8f0;
}

.holo-file-hint {
    font-size: 0.85rem;
    color: var(--htb-text-muted, #94a3b8);
}

/* Attributes Section */
.holo-attributes-box {
    background: rgba(0, 0, 0, 0.02);
    border: 1px solid rgba(0, 0, 0, 0.04);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 28px;
}

[data-theme="dark"] .holo-attributes-box {
    background: rgba(255, 255, 255, 0.02);
    border-color: rgba(255, 255, 255, 0.05);
}

.holo-attributes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 12px;
}

.holo-attribute-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    background: rgba(255, 255, 255, 0.5);
    border: 1px solid transparent;
}

[data-theme="dark"] .holo-attribute-item {
    background: rgba(0, 0, 0, 0.15);
}

.holo-attribute-item:hover {
    background: rgba(99, 102, 241, 0.08);
}

.holo-attribute-item input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: #6366f1;
    margin: 0;
    flex-shrink: 0;
}

.holo-attribute-item span {
    font-size: 0.9rem;
    color: var(--htb-text-main, #374151);
}

[data-theme="dark"] .holo-attribute-item span {
    color: #e2e8f0;
}

/* Federation Section */
.holo-federation-section {
    margin-bottom: 32px;
    padding: 24px;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.05) 0%, rgba(99, 102, 241, 0.03) 100%);
    border: 1px solid rgba(139, 92, 246, 0.15);
}

[data-theme="dark"] .holo-federation-section {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(99, 102, 241, 0.05) 100%);
    border-color: rgba(139, 92, 246, 0.2);
}

.holo-federation-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.holo-radio-card {
    display: flex;
    align-items: flex-start;
    padding: 16px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.6);
    border: 2px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
}

[data-theme="dark"] .holo-radio-card {
    background: rgba(30, 41, 59, 0.6);
}

.holo-radio-card:hover {
    background: rgba(255, 255, 255, 0.9);
    border-color: rgba(139, 92, 246, 0.3);
}

[data-theme="dark"] .holo-radio-card:hover {
    background: rgba(30, 41, 59, 0.9);
}

.holo-radio-card:has(input:checked) {
    background: rgba(139, 92, 246, 0.1);
    border-color: #8b5cf6;
}

[data-theme="dark"] .holo-radio-card:has(input:checked) {
    background: rgba(139, 92, 246, 0.2);
}

.holo-radio-card input[type="radio"] {
    margin-right: 12px;
    margin-top: 4px;
    accent-color: #8b5cf6;
    width: 18px;
    height: 18px;
}

.radio-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
}

.radio-icon {
    display: none;
}

.radio-label {
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--htb-text-main, #1f2937);
}

[data-theme="dark"] .radio-label {
    color: #f1f5f9;
}

.radio-desc {
    font-size: 0.85rem;
    color: var(--htb-text-secondary, #6b7280);
    line-height: 1.4;
}

[data-theme="dark"] .radio-desc {
    color: #94a3b8;
}

.holo-federation-optin-notice {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    border-radius: 12px;
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.holo-federation-optin-notice i {
    color: #f59e0b;
    font-size: 1.25rem;
    margin-top: 2px;
}

.holo-federation-optin-notice strong {
    display: block;
    color: var(--htb-text-main, #1f2937);
    margin-bottom: 4px;
}

[data-theme="dark"] .holo-federation-optin-notice strong {
    color: #f1f5f9;
}

.holo-federation-optin-notice p {
    font-size: 0.9rem;
    color: var(--htb-text-secondary, #6b7280);
    margin: 0;
}

[data-theme="dark"] .holo-federation-optin-notice p {
    color: #94a3b8;
}

.holo-federation-optin-notice a {
    color: #8b5cf6;
    text-decoration: underline;
}

/* SDG Section */
.holo-sdg-accordion {
    border: 1px solid rgba(0, 0, 0, 0.06);
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 32px;
    background: rgba(0, 0, 0, 0.01);
}

[data-theme="dark"] .holo-sdg-accordion {
    border-color: rgba(255, 255, 255, 0.06);
    background: rgba(255, 255, 255, 0.01);
}

.holo-sdg-header {
    padding: 20px 24px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 600;
    color: var(--htb-text-main, #374151);
    transition: background 0.2s ease;
}

[data-theme="dark"] .holo-sdg-header {
    color: #e2e8f0;
}

.holo-sdg-header:hover {
    background: rgba(0, 0, 0, 0.02);
}

.holo-sdg-header i {
    transition: transform 0.3s ease;
    color: var(--htb-text-muted, #94a3b8);
}

.holo-sdg-accordion[open] .holo-sdg-header i {
    transform: rotate(180deg);
}

.holo-sdg-content {
    padding: 0 24px 24px;
    border-top: 1px solid rgba(0, 0, 0, 0.04);
}

[data-theme="dark"] .holo-sdg-content {
    border-top-color: rgba(255, 255, 255, 0.04);
}

.holo-sdg-intro {
    font-size: 0.9rem;
    color: var(--htb-text-muted, #64748b);
    margin: 16px 0;
}

.holo-sdg-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 10px;
}

.holo-sdg-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 2px solid rgba(0, 0, 0, 0.06);
    background: rgba(255, 255, 255, 0.5);
}

[data-theme="dark"] .holo-sdg-card {
    border-color: rgba(255, 255, 255, 0.08);
    background: rgba(0, 0, 0, 0.15);
}

.holo-sdg-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.holo-sdg-card input {
    display: none;
}

.holo-sdg-card .sdg-icon {
    font-size: 1.3rem;
}

.holo-sdg-card .sdg-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--htb-text-main, #374151);
}

[data-theme="dark"] .holo-sdg-card .sdg-label {
    color: #e2e8f0;
}

/* Submit Button */
.holo-submit-btn {
    width: 100%;
    padding: 20px 32px;
    font-size: 1.1rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    border: none;
    border-radius: 16px;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
    color: white;
    box-shadow:
        0 12px 30px rgba(99, 102, 241, 0.35),
        0 0 50px rgba(99, 102, 241, 0.15);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.holo-submit-btn:hover {
    transform: translateY(-3px);
    box-shadow:
        0 18px 40px rgba(99, 102, 241, 0.45),
        0 0 60px rgba(99, 102, 241, 0.2);
}

.holo-submit-btn:active {
    transform: translateY(-1px) scale(0.98);
}

/* Button Shimmer */
.holo-submit-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.2),
        transparent
    );
    transition: left 0.5s ease;
}

.holo-submit-btn:hover::before {
    left: 100%;
}

/* Loading State */
.holo-submit-btn.loading {
    pointer-events: none;
    opacity: 0.8;
}

.holo-submit-btn.loading::after {
    content: '';
    position: absolute;
    width: 24px;
    height: 24px;
    border: 3px solid transparent;
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-left: 12px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Prevent iOS Zoom */
@media (max-width: 768px) {
    .holo-input,
    .holo-select,
    .holo-textarea {
        font-size: 16px !important;
    }
}

/* Focus Visible */
.holo-input:focus-visible,
.holo-select:focus-visible,
.holo-textarea:focus-visible,
.holo-submit-btn:focus-visible,
.holo-type-card:focus-visible {
    outline: 3px solid #6366f1;
    outline-offset: 2px;
}
</style>

<div class="holo-create-page">
    <!-- Floating Orbs -->
    <div class="holo-orb holo-orb-1"></div>
    <div class="holo-orb holo-orb-2"></div>
    <div class="holo-orb holo-orb-3"></div>

    <div class="holo-create-container">
        <!-- Page Header -->
        <div class="holo-page-header">
            <div class="holo-page-icon">✨</div>
            <h1 class="holo-page-title">Create Listing</h1>
            <p class="holo-page-subtitle">Share your skills or request help from the community</p>
        </div>

        <!-- Glass Card Form -->
        <div class="holo-glass-card">
            <form action="<?= $basePath ?>/listings/store" method="POST" enctype="multipart/form-data" id="createListingForm">
                <?= Nexus\Core\Csrf::input() ?>

                <!-- Type Selection -->
                <div class="holo-section">
                    <div class="holo-section-title">What would you like to do?</div>
                    <div class="holo-type-grid">
                        <label>
                            <input type="radio" name="type" value="offer" <?= $type === 'offer' ? 'checked' : '' ?> style="display: none;" onchange="updateTypeStyles(this)">
                            <div id="type-offer-box" class="holo-type-card <?= $type === 'offer' ? 'selected-offer' : '' ?>">
                                <span class="holo-type-icon">🎁</span>
                                <div class="holo-type-label">Offer Help</div>
                                <div class="holo-type-desc">Share a skill or service</div>
                            </div>
                        </label>
                        <label>
                            <input type="radio" name="type" value="request" <?= $type === 'request' ? 'checked' : '' ?> style="display: none;" onchange="updateTypeStyles(this)">
                            <div id="type-request-box" class="holo-type-card <?= $type === 'request' ? 'selected-request' : '' ?>">
                                <span class="holo-type-icon">🙋</span>
                                <div class="holo-type-label">Request Help</div>
                                <div class="holo-type-desc">Ask for assistance</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Category -->
                <div class="holo-section">
                    <label class="holo-label" for="category_id">Category</label>
                    <select name="category_id" id="category_id" class="holo-select" required>
                        <option value="" disabled selected>Choose a category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Title -->
                <div class="holo-section">
                    <label class="holo-label" for="title">Title</label>
                    <input type="text" name="title" id="title" class="holo-input" placeholder="e.g. Gardening Assistance, Guitar Lessons..." required>
                </div>

                <!-- Location Info -->
                <div class="holo-location-box <?= $userLocation ? 'has-location' : 'no-location' ?>">
                    <div class="holo-location-icon">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>
                    <div class="holo-location-content">
                        <div class="holo-location-title">Location</div>
                        <?php if ($userLocation): ?>
                            <div class="holo-location-text">
                                This listing will use your profile location: <strong><?= htmlspecialchars($userLocation) ?></strong>
                            </div>
                        <?php else: ?>
                            <div class="holo-location-text">
                                <i class="fa-solid fa-exclamation-triangle" style="color: #f59e0b; margin-right: 4px;"></i>
                                No location set. <a href="<?= $basePath ?>/profile/edit">Add one to your profile</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Description -->
                <div class="holo-section">
                    <label class="holo-label" for="description">Description</label>
                    <?php
                    $aiGenerateType = 'listing';
                    $aiTitleField = 'title';
                    $aiDescriptionField = 'description';
                    $aiTypeField = 'type';
                    include __DIR__ . '/../../partials/ai-generate-button.php';
                    ?>
                    <textarea name="description" id="description" class="holo-textarea" placeholder="Describe what you're offering or requesting in detail..." required></textarea>
                </div>

                <!-- Image Upload -->
                <div class="holo-section">
                    <label class="holo-label">Image <span class="holo-label-optional">(Optional)</span></label>
                    <div class="holo-file-upload">
                        <input type="file" name="image" id="image" accept="image/*">
                        <div class="holo-file-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                        <div class="holo-file-text">Drop an image or click to browse</div>
                        <div class="holo-file-hint">PNG, JPG, GIF up to 5MB</div>
                    </div>
                </div>

                <!-- Dynamic Attributes -->
                <?php if (!empty($attributes)): ?>
                <div id="attributes-container" class="holo-attributes-box">
                    <label class="holo-label" style="margin-bottom: 16px;">Service Details</label>
                    <div class="holo-attributes-grid">
                        <?php foreach ($attributes as $attr): ?>
                            <label class="holo-attribute-item attribute-item"
                                data-category-id="<?= $attr['category_id'] ?? 'global' ?>"
                                data-target-type="<?= $attr['target_type'] ?? 'any' ?>">
                                <?php if ($attr['input_type'] === 'checkbox'): ?>
                                    <input type="checkbox" name="attributes[<?= $attr['id'] ?>]" value="1">
                                <?php endif; ?>
                                <span><?= htmlspecialchars($attr['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Partner Timebanks (Federation) -->
                <?php if (!empty($federationEnabled)): ?>
                <div class="holo-federation-section">
                    <label class="holo-label">
                        <i class="fa-solid fa-globe" style="margin-right: 8px; color: #8b5cf6;"></i>
                        Share with Partner Timebanks
                        <span style="font-weight: 400; opacity: 0.6; font-size: 0.85rem;">(Optional)</span>
                    </label>

                    <?php if (!empty($userFederationOptedIn)): ?>
                    <p class="holo-field-hint" style="margin-bottom: 12px;">
                        Make this listing visible to members of our partner timebanks.
                    </p>
                    <div class="holo-federation-options">
                        <label class="holo-radio-card">
                            <input type="radio" name="federated_visibility" value="none" checked>
                            <span class="radio-content">
                                <span class="radio-icon"><i class="fa-solid fa-lock"></i></span>
                                <span class="radio-label">Local Only</span>
                                <span class="radio-desc">Only visible to members of this timebank</span>
                            </span>
                        </label>
                        <label class="holo-radio-card">
                            <input type="radio" name="federated_visibility" value="listed">
                            <span class="radio-content">
                                <span class="radio-icon"><i class="fa-solid fa-eye"></i></span>
                                <span class="radio-label">Visible</span>
                                <span class="radio-desc">Partner timebank members can see this listing</span>
                            </span>
                        </label>
                        <label class="holo-radio-card">
                            <input type="radio" name="federated_visibility" value="bookable">
                            <span class="radio-content">
                                <span class="radio-icon"><i class="fa-solid fa-handshake"></i></span>
                                <span class="radio-label">Bookable</span>
                                <span class="radio-desc">Partner members can contact you about this listing</span>
                            </span>
                        </label>
                    </div>
                    <?php else: ?>
                    <div class="holo-federation-optin-notice">
                        <i class="fa-solid fa-info-circle"></i>
                        <div>
                            <strong>Enable federation to share listings</strong>
                            <p>To share your listings with partner timebanks, you need to opt into federation in your <a href="<?= $basePath ?>/settings?section=federation">account settings</a>.</p>
                        </div>
                    </div>
                    <input type="hidden" name="federated_visibility" value="none">
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- SDGs -->
                <details class="holo-sdg-accordion">
                    <summary class="holo-sdg-header">
                        <span>🌍 Social Impact <span style="font-weight: 400; opacity: 0.6; font-size: 0.85rem;">(Optional)</span></span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </summary>
                    <div class="holo-sdg-content">
                        <p class="holo-sdg-intro">Which UN Sustainable Development Goals does this support?</p>
                        <?php
                        require_once __DIR__ . '/../../../src/Helpers/SDG.php';
                        $sdgs = \Nexus\Helpers\SDG::all();
                        ?>
                        <div class="holo-sdg-grid">
                            <?php foreach ($sdgs as $id => $goal): ?>
                                <label class="holo-sdg-card" data-color="<?= $goal['color'] ?>">
                                    <input type="checkbox" name="sdg_goals[]" value="<?= $id ?>" onchange="toggleSDG(this, '<?= $goal['color'] ?>')">
                                    <span class="sdg-icon"><?= $goal['icon'] ?></span>
                                    <span class="sdg-label"><?= $goal['label'] ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>

                <!-- Submit Button -->
                <button type="submit" class="holo-submit-btn" id="submitBtn">
                    <i class="fa-solid fa-paper-plane" style="margin-right: 10px;"></i>
                    Post Listing
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Type Selection Styling
function updateTypeStyles(radio) {
    const offerBox = document.getElementById('type-offer-box');
    const requestBox = document.getElementById('type-request-box');

    // Clear all selections
    offerBox.classList.remove('selected-offer', 'selected-request');
    requestBox.classList.remove('selected-offer', 'selected-request');

    // Apply new selection
    if (radio.value === 'offer') {
        offerBox.classList.add('selected-offer');
    } else {
        requestBox.classList.add('selected-request');
    }

    // Filter attributes if function exists
    if (typeof filterAttributes === 'function') {
        filterAttributes();
    }
}

// Attribute Filtering
const categorySelect = document.getElementById('category_id');

function filterAttributes() {
    const container = document.getElementById('attributes-container');
    if (!container) return;

    const selectedCat = categorySelect.value;
    const selectedType = document.querySelector('input[name="type"]:checked')?.value || 'offer';
    const items = document.querySelectorAll('.attribute-item');
    let visibleCount = 0;

    items.forEach(item => {
        const itemCat = item.getAttribute('data-category-id');
        const itemType = item.getAttribute('data-target-type');

        const catMatch = itemCat === 'global' || itemCat == selectedCat;
        const typeMatch = itemType === 'any' || itemType === selectedType;

        if (catMatch && typeMatch) {
            item.style.display = 'flex';
            visibleCount++;
        } else {
            item.style.display = 'none';
            const checkbox = item.querySelector('input');
            if (checkbox) checkbox.checked = false;
        }
    });

    container.style.display = visibleCount > 0 ? 'block' : 'none';
}

if (categorySelect) {
    categorySelect.addEventListener('change', filterAttributes);
}

// SDG Toggle
function toggleSDG(checkbox, color) {
    const card = checkbox.closest('.holo-sdg-card');
    if (checkbox.checked) {
        card.style.borderColor = color;
        card.style.backgroundColor = color + '18';
        card.style.boxShadow = `0 4px 15px ${color}30`;
    } else {
        card.style.borderColor = '';
        card.style.backgroundColor = '';
        card.style.boxShadow = '';
    }
}

// File Upload Preview
const fileInput = document.getElementById('image');
const fileUpload = document.querySelector('.holo-file-upload');

if (fileInput && fileUpload) {
    fileInput.addEventListener('change', function() {
        const fileName = this.files[0]?.name;
        if (fileName) {
            fileUpload.querySelector('.holo-file-text').textContent = fileName;
            fileUpload.querySelector('.holo-file-icon').innerHTML = '<i class="fa-solid fa-check-circle" style="color: #10b981;"></i>';
        }
    });
}

// Form Submission
document.addEventListener('DOMContentLoaded', function() {
    // Initialize
    if (typeof filterAttributes === 'function') {
        filterAttributes();
    }

    const checkedRadio = document.querySelector('input[name="type"]:checked');
    if (checkedRadio) {
        updateTypeStyles(checkedRadio);
    }

    // Form submission handling
    const form = document.getElementById('createListingForm');
    const submitBtn = document.getElementById('submitBtn');

    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            if (!navigator.onLine) {
                e.preventDefault();
                alert('You are offline. Please connect to the internet to submit your listing.');
                return;
            }

            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Posting...';
        });
    }

    // Touch feedback for cards
    document.querySelectorAll('.holo-type-card, .holo-sdg-card, .holo-submit-btn').forEach(el => {
        el.addEventListener('pointerdown', () => el.style.transform = 'scale(0.97)');
        el.addEventListener('pointerup', () => el.style.transform = '');
        el.addEventListener('pointerleave', () => el.style.transform = '');
    });
});
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
