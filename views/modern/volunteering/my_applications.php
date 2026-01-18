<?php
// Phoenix View: My Volunteering Applications - Holographic Design
$pageTitle = 'My Applications';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';
?>

<style>
/* ============================================
   HOLOGRAPHIC GLASSMORPHISM - My Applications
   ============================================ */

.holo-applications-page {
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
    padding: 20px 16px 120px;
}

/* Desktop: Clear fixed header */
@media (min-width: 901px) {
    .holo-applications-page {
        padding: 180px 20px 60px;
    }
}

/* Animated background */
.holo-applications-page::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background:
        radial-gradient(ellipse at 20% 20%, rgba(16, 185, 129, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(59, 130, 246, 0.12) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 50%, rgba(139, 92, 246, 0.08) 0%, transparent 60%),
        linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    z-index: -2;
}

/* Floating orbs */
.holo-orb {
    position: fixed;
    border-radius: 50%;
    filter: blur(60px);
    opacity: 0.5;
    z-index: -1;
    pointer-events: none;
}

.holo-orb-1 {
    width: 400px;
    height: 400px;
    background: linear-gradient(135deg, #10b981, #059669);
    top: -100px;
    right: -100px;
    animation: orbFloat 20s ease-in-out infinite;
}

.holo-orb-2 {
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, #3b82f6, #6366f1);
    bottom: 20%;
    left: -100px;
    animation: orbFloat 25s ease-in-out infinite reverse;
}

.holo-orb-3 {
    width: 250px;
    height: 250px;
    background: linear-gradient(135deg, #8b5cf6, #a855f7);
    top: 50%;
    right: 10%;
    animation: orbFloat 22s ease-in-out infinite 5s;
}

@keyframes orbFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    25% { transform: translate(30px, -30px) scale(1.05); }
    50% { transform: translate(-20px, 20px) scale(0.95); }
    75% { transform: translate(20px, 30px) scale(1.02); }
}

/* Container */
.holo-container {
    max-width: 1100px;
    margin: 0 auto;
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Page Header */
.holo-page-header {
    text-align: center;
    margin-bottom: 30px;
}

.holo-page-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.3), rgba(59, 130, 246, 0.3));
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2.5rem;
    box-shadow:
        0 8px 32px rgba(16, 185, 129, 0.3),
        inset 0 0 20px rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    animation: iconPulse 3s ease-in-out infinite;
}

@keyframes iconPulse {
    0%, 100% { transform: scale(1); box-shadow: 0 8px 32px rgba(16, 185, 129, 0.3); }
    50% { transform: scale(1.05); box-shadow: 0 12px 40px rgba(16, 185, 129, 0.4); }
}

.holo-page-title {
    font-size: 2.2rem;
    font-weight: 800;
    background: linear-gradient(135deg, #10b981, #3b82f6, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0 0 10px;
    animation: holoShift 8s ease-in-out infinite;
}

@keyframes holoShift {
    0%, 100% { filter: hue-rotate(0deg); }
    50% { filter: hue-rotate(20deg); }
}

.holo-page-subtitle {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1.1rem;
    margin: 0;
}

/* Header Actions */
.holo-header-actions {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.holo-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.holo-action-btn-primary {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
}

.holo-action-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(16, 185, 129, 0.4);
    color: white;
}

.holo-action-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
}

.holo-action-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateY(-2px);
    color: white;
}

/* Stats Row */
.holo-stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
    margin-bottom: 30px;
}

.holo-stat-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
}

.holo-stat-card:hover {
    transform: translateY(-3px);
    background: rgba(255, 255, 255, 0.08);
}

.holo-stat-value {
    font-size: 2rem;
    font-weight: 800;
    background: linear-gradient(135deg, #10b981, #3b82f6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.holo-stat-label {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
    margin-top: 5px;
}

/* Badges Section */
.holo-badges-section {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(34, 197, 94, 0.1));
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 20px 24px;
    margin-bottom: 24px;
    border: 1px solid rgba(16, 185, 129, 0.3);
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.holo-badges-title {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #10b981;
    font-weight: 700;
    font-size: 1rem;
}

.holo-badge {
    background: rgba(255, 255, 255, 0.15);
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 0.9rem;
    font-weight: 600;
    color: white;
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: help;
    transition: all 0.2s ease;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.holo-badge:hover {
    transform: scale(1.05);
    background: rgba(255, 255, 255, 0.2);
}

/* Glass Card */
.holo-glass-card {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    overflow: hidden;
    position: relative;
    box-shadow:
        0 4px 24px rgba(0, 0, 0, 0.2),
        0 0 0 1px rgba(255, 255, 255, 0.05) inset;
}

/* Shimmer effect */
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
        rgba(255, 255, 255, 0.03),
        transparent
    );
    animation: shimmer 8s infinite;
    pointer-events: none;
}

@keyframes shimmer {
    0% { left: -100%; }
    50%, 100% { left: 100%; }
}

.holo-card-header {
    padding: 24px 28px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.holo-card-title {
    color: white;
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.holo-card-body {
    padding: 24px 28px;
}

/* Empty State */
.holo-empty-state {
    text-align: center;
    padding: 60px 20px;
}

.holo-empty-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

.holo-empty-title {
    color: white;
    font-size: 1.4rem;
    font-weight: 700;
    margin: 0 0 10px;
}

.holo-empty-text {
    color: rgba(255, 255, 255, 0.5);
    margin: 0 0 30px;
}

.holo-empty-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 28px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
}

.holo-empty-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(16, 185, 129, 0.4);
    color: white;
}

/* Application Item */
.holo-application-item {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 16px;
    transition: all 0.3s ease;
}

.holo-application-item:last-child {
    margin-bottom: 0;
}

.holo-application-item:hover {
    background: rgba(255, 255, 255, 0.05);
    transform: translateX(4px);
    border-color: rgba(255, 255, 255, 0.12);
}

.holo-app-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 16px;
}

.holo-app-title {
    color: white;
    font-size: 1.15rem;
    font-weight: 700;
    margin: 0 0 6px;
}

.holo-app-org {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.95rem;
    margin: 0;
}

.holo-app-org strong {
    color: rgba(255, 255, 255, 0.8);
}

.holo-app-shift {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
    font-size: 0.9rem;
    color: #818cf8;
}

.holo-app-shift a {
    color: #818cf8;
    text-decoration: underline;
}

.holo-app-shift a:hover {
    color: #a5b4fc;
}

/* Status Badge */
.holo-status {
    padding: 6px 14px;
    border-radius: 25px;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.holo-status-approved {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.holo-status-pending {
    background: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.holo-status-declined {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

/* Application Footer */
.holo-app-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    flex-wrap: wrap;
    gap: 12px;
}

.holo-app-date {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.9rem;
}

.holo-app-actions {
    display: flex;
    gap: 10px;
}

.holo-app-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.holo-app-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
}

.holo-app-btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
}

.holo-app-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.holo-app-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
}

/* Modal Styles */
.holo-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.holo-modal {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.98));
    backdrop-filter: blur(20px);
    border-radius: 24px;
    max-width: 500px;
    width: 100%;
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
    overflow: hidden;
}

.holo-modal-header {
    padding: 24px 28px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.holo-modal-title {
    color: white;
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.holo-modal-close {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: rgba(255, 255, 255, 0.6);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.holo-modal-close:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

.holo-modal-body {
    padding: 28px;
}

.holo-modal-info {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 24px;
}

.holo-modal-info p {
    margin: 0 0 8px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.95rem;
}

.holo-modal-info p:last-child {
    margin-bottom: 0;
}

.holo-modal-info strong {
    color: white;
}

/* Form Elements */
.holo-form-group {
    margin-bottom: 20px;
}

.holo-label {
    display: block;
    color: rgba(255, 255, 255, 0.8);
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 0.95rem;
}

.holo-input,
.holo-textarea {
    width: 100%;
    padding: 14px 16px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    color: white;
    font-size: 1rem;
    font-family: inherit;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

.holo-input:focus,
.holo-textarea:focus {
    outline: none;
    border-color: rgba(16, 185, 129, 0.5);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
}

.holo-input::placeholder,
.holo-textarea::placeholder {
    color: rgba(255, 255, 255, 0.3);
}

.holo-textarea {
    min-height: 100px;
    resize: vertical;
}

.holo-modal-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}

.holo-modal-btn {
    flex: 1;
    padding: 14px 20px;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.holo-modal-btn-primary {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
}

.holo-modal-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
}

.holo-modal-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.8);
    flex: 0 0 auto;
    padding: 14px 24px;
}

.holo-modal-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
}

/* Star Rating */
.holo-star-rating {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
}

.holo-star {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    cursor: pointer;
    transition: all 0.2s ease;
    color: rgba(255, 255, 255, 0.3);
}

.holo-star:hover,
.holo-star.active {
    background: rgba(251, 191, 36, 0.2);
    border-color: rgba(251, 191, 36, 0.4);
    color: #fbbf24;
    transform: scale(1.1);
}

/* Offline Banner */
.holo-offline-banner {
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

.holo-offline-banner.visible {
    transform: translateY(0);
}

/* Light Mode Overrides */
[data-theme="light"] .holo-applications-page::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(16, 185, 129, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(59, 130, 246, 0.08) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 50%, rgba(139, 92, 246, 0.05) 0%, transparent 60%),
        linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #f1f5f9 100%);
}

[data-theme="light"] .holo-orb {
    opacity: 0.3;
}

[data-theme="light"] .holo-page-subtitle {
    color: rgba(0, 0, 0, 0.5);
}

[data-theme="light"] .holo-stat-card {
    background: rgba(255, 255, 255, 0.7);
    border-color: rgba(0, 0, 0, 0.08);
}

[data-theme="light"] .holo-stat-label {
    color: rgba(0, 0, 0, 0.5);
}

[data-theme="light"] .holo-glass-card {
    background: rgba(255, 255, 255, 0.8);
    border-color: rgba(0, 0, 0, 0.08);
}

[data-theme="light"] .holo-card-title {
    color: #1e293b;
}

[data-theme="light"] .holo-application-item {
    background: rgba(255, 255, 255, 0.6);
    border-color: rgba(0, 0, 0, 0.08);
}

[data-theme="light"] .holo-app-title {
    color: #1e293b;
}

[data-theme="light"] .holo-app-org {
    color: rgba(0, 0, 0, 0.6);
}

[data-theme="light"] .holo-app-org strong {
    color: rgba(0, 0, 0, 0.8);
}

[data-theme="light"] .holo-app-date {
    color: rgba(0, 0, 0, 0.5);
}

[data-theme="light"] .holo-empty-title {
    color: #1e293b;
}

[data-theme="light"] .holo-empty-text {
    color: rgba(0, 0, 0, 0.5);
}

[data-theme="light"] .holo-modal {
    background: rgba(255, 255, 255, 0.95);
}

[data-theme="light"] .holo-modal-title {
    color: #1e293b;
}

[data-theme="light"] .holo-modal-info {
    background: rgba(0, 0, 0, 0.03);
}

[data-theme="light"] .holo-modal-info p {
    color: rgba(0, 0, 0, 0.6);
}

[data-theme="light"] .holo-modal-info strong {
    color: #1e293b;
}

[data-theme="light"] .holo-label {
    color: rgba(0, 0, 0, 0.7);
}

[data-theme="light"] .holo-input,
[data-theme="light"] .holo-textarea {
    background: rgba(0, 0, 0, 0.03);
    border-color: rgba(0, 0, 0, 0.1);
    color: #1e293b;
}

[data-theme="light"] .holo-input::placeholder,
[data-theme="light"] .holo-textarea::placeholder {
    color: rgba(0, 0, 0, 0.3);
}

[data-theme="light"] .holo-action-btn-secondary {
    background: rgba(0, 0, 0, 0.05);
    color: rgba(0, 0, 0, 0.7);
}

[data-theme="light"] .holo-badge {
    background: rgba(0, 0, 0, 0.05);
    color: #1e293b;
}

[data-theme="light"] .holo-app-btn-secondary {
    background: rgba(0, 0, 0, 0.05);
    color: rgba(0, 0, 0, 0.7);
    border-color: rgba(0, 0, 0, 0.1);
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .holo-page-title {
        font-size: 1.8rem;
    }

    .holo-page-icon {
        width: 70px;
        height: 70px;
        font-size: 2rem;
    }

    .holo-header-actions {
        flex-direction: column;
    }

    .holo-action-btn {
        width: 100%;
        justify-content: center;
    }

    .holo-stats-row {
        grid-template-columns: repeat(2, 1fr);
    }

    .holo-badges-section {
        flex-direction: column;
        align-items: flex-start;
    }

    .holo-app-header {
        flex-direction: column;
    }

    .holo-app-footer {
        flex-direction: column;
        align-items: flex-start;
    }

    .holo-app-actions {
        width: 100%;
    }

    .holo-app-btn {
        flex: 1;
        justify-content: center;
    }

    .holo-card-body {
        padding: 16px;
    }

    .holo-application-item {
        padding: 16px;
    }
}
</style>

<!-- Offline Banner -->
<div class="holo-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="holo-applications-page">
    <!-- Floating Orbs -->
    <div class="holo-orb holo-orb-1"></div>
    <div class="holo-orb holo-orb-2"></div>
    <div class="holo-orb holo-orb-3"></div>

    <div class="holo-container">
        <!-- Page Header -->
        <div class="holo-page-header">
            <div class="holo-page-icon">
                <i class="fa-solid fa-clipboard-list"></i>
            </div>
            <h1 class="holo-page-title">My Applications</h1>
            <p class="holo-page-subtitle">Track your volunteering journey</p>

            <div class="holo-header-actions">
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering" class="holo-action-btn holo-action-btn-primary">
                    <i class="fa-solid fa-search"></i>
                    Find Opportunities
                </a>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/certificate" target="_blank" class="holo-action-btn holo-action-btn-secondary">
                    <i class="fa-solid fa-scroll"></i>
                    Get Certificate
                </a>
            </div>
        </div>

        <?php if (!empty($applications)): ?>
            <!-- Stats Row -->
            <?php
            $totalApps = count($applications);
            $approvedApps = count(array_filter($applications, fn($a) => $a['status'] === 'approved'));
            $pendingApps = count(array_filter($applications, fn($a) => $a['status'] === 'pending'));
            ?>
            <div class="holo-stats-row">
                <div class="holo-stat-card">
                    <div class="holo-stat-value"><?= $totalApps ?></div>
                    <div class="holo-stat-label">Total Applications</div>
                </div>
                <div class="holo-stat-card">
                    <div class="holo-stat-value"><?= $approvedApps ?></div>
                    <div class="holo-stat-label">Approved</div>
                </div>
                <div class="holo-stat-card">
                    <div class="holo-stat-value"><?= $pendingApps ?></div>
                    <div class="holo-stat-label">Pending</div>
                </div>
                <div class="holo-stat-card">
                    <div class="holo-stat-value"><?= count($badges ?? []) ?></div>
                    <div class="holo-stat-label">Badges Earned</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Badges Section -->
        <?php if (!empty($badges)): ?>
            <div class="holo-badges-section">
                <div class="holo-badges-title">
                    <i class="fa-solid fa-trophy"></i>
                    Achievements
                </div>
                <?php foreach ($badges as $badge): ?>
                    <div class="holo-badge" title="<?= htmlspecialchars($badge['name']) ?> - Earned <?= date('M Y', strtotime($badge['awarded_at'])) ?>">
                        <?= $badge['icon'] ?> <?= htmlspecialchars($badge['name']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Applications List -->
        <div class="holo-glass-card">
            <div class="holo-card-header">
                <h2 class="holo-card-title">
                    <i class="fa-solid fa-list-check"></i>
                    Your Applications
                </h2>
            </div>
            <div class="holo-card-body">
                <?php if (empty($applications)): ?>
                    <div class="holo-empty-state">
                        <div class="holo-empty-icon">
                            <i class="fa-solid fa-folder-open"></i>
                        </div>
                        <h3 class="holo-empty-title">No Applications Yet</h3>
                        <p class="holo-empty-text">You haven't applied to any volunteer opportunities yet.</p>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering" class="holo-empty-btn">
                            <i class="fa-solid fa-search"></i>
                            Find Opportunities
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <?php
                        $statusClass = match ($app['status']) {
                            'approved' => 'holo-status-approved',
                            'declined' => 'holo-status-declined',
                            default => 'holo-status-pending'
                        };
                        ?>
                        <div class="holo-application-item">
                            <div class="holo-app-header">
                                <div>
                                    <h3 class="holo-app-title"><?= htmlspecialchars($app['opp_title']) ?></h3>
                                    <p class="holo-app-org">
                                        with <strong><?= htmlspecialchars($app['org_name']) ?></strong>
                                    </p>
                                    <?php if ($app['shift_start']): ?>
                                        <div class="holo-app-shift">
                                            <i class="fa-regular fa-calendar"></i>
                                            <?= date('M d, h:i A', strtotime($app['shift_start'])) ?>
                                            <span style="margin: 0 4px;">|</span>
                                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/ics/<?= $app['id'] ?>">
                                                <i class="fa-solid fa-calendar-plus"></i> Add to Calendar
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <span class="holo-status <?= $statusClass ?>">
                                    <?= htmlspecialchars($app['status']) ?>
                                </span>
                            </div>

                            <div class="holo-app-footer">
                                <span class="holo-app-date">
                                    <i class="fa-regular fa-clock"></i>
                                    Applied on <?= date('M j, Y', strtotime($app['created_at'])) ?>
                                </span>
                                <?php if ($app['status'] == 'approved'): ?>
                                    <div class="holo-app-actions">
                                        <button
                                            onclick="openLogModal(<?= $app['organization_id'] ?>, <?= $app['opportunity_id'] ?>, '<?= htmlspecialchars($app['org_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($app['opp_title'], ENT_QUOTES) ?>')"
                                            class="holo-app-btn holo-app-btn-primary">
                                            <i class="fa-regular fa-clock"></i>
                                            Log Hours
                                        </button>
                                        <button
                                            onclick="openReviewModal(<?= $app['organization_id'] ?>, '<?= htmlspecialchars($app['org_name'], ENT_QUOTES) ?>')"
                                            class="holo-app-btn holo-app-btn-secondary">
                                            <i class="fa-regular fa-star"></i>
                                            Review
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Log Hours Modal -->
<div id="logHoursModal" class="holo-modal-overlay">
    <div class="holo-modal">
        <div class="holo-modal-header">
            <h3 class="holo-modal-title">
                <i class="fa-regular fa-clock"></i>
                Log Volunteer Hours
            </h3>
            <button class="holo-modal-close" onclick="closeLogModal()">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="holo-modal-body">
            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/log-hours" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="org_id" id="log_org_id">
                <input type="hidden" name="opp_id" id="log_opp_id">

                <div class="holo-modal-info">
                    <p><strong>Organization:</strong> <span id="log_org_name"></span></p>
                    <p><strong>Role:</strong> <span id="log_opp_title"></span></p>
                </div>

                <div class="holo-form-group">
                    <label class="holo-label">Date</label>
                    <input type="date" name="date" required value="<?= date('Y-m-d') ?>" class="holo-input">
                </div>

                <div class="holo-form-group">
                    <label class="holo-label">Hours Worked</label>
                    <input type="number" step="0.5" name="hours" required placeholder="e.g. 2.5" class="holo-input">
                </div>

                <div class="holo-form-group">
                    <label class="holo-label">Description (optional)</label>
                    <textarea name="description" rows="3" placeholder="Briefly describe what you did..." class="holo-textarea"></textarea>
                </div>

                <div class="holo-modal-actions">
                    <button type="submit" class="holo-modal-btn holo-modal-btn-primary">
                        <i class="fa-solid fa-check"></i>
                        Submit Hours
                    </button>
                    <button type="button" onclick="closeLogModal()" class="holo-modal-btn holo-modal-btn-secondary">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div id="reviewModal" class="holo-modal-overlay">
    <div class="holo-modal">
        <div class="holo-modal-header">
            <h3 class="holo-modal-title">
                <i class="fa-regular fa-star"></i>
                Leave a Review
            </h3>
            <button class="holo-modal-close" onclick="closeReviewModal()">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="holo-modal-body">
            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/reviews" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="target_type" value="organization">
                <input type="hidden" name="target_id" id="review_target_id">

                <div class="holo-modal-info">
                    <p><strong>Reviewing:</strong> <span id="review_target_name"></span></p>
                </div>

                <div class="holo-form-group">
                    <label class="holo-label">Your Rating</label>
                    <div class="holo-star-rating" id="starRating">
                        <div class="holo-star" data-value="1"><i class="fa-solid fa-star"></i></div>
                        <div class="holo-star" data-value="2"><i class="fa-solid fa-star"></i></div>
                        <div class="holo-star" data-value="3"><i class="fa-solid fa-star"></i></div>
                        <div class="holo-star" data-value="4"><i class="fa-solid fa-star"></i></div>
                        <div class="holo-star" data-value="5"><i class="fa-solid fa-star"></i></div>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" value="5">
                </div>

                <div class="holo-form-group">
                    <label class="holo-label">Your Review</label>
                    <textarea name="content" rows="4" placeholder="Share your experience volunteering with this organization..." class="holo-textarea" required></textarea>
                </div>

                <div class="holo-modal-actions">
                    <button type="submit" class="holo-modal-btn holo-modal-btn-primary">
                        <i class="fa-solid fa-paper-plane"></i>
                        Submit Review
                    </button>
                    <button type="button" onclick="closeReviewModal()" class="holo-modal-btn holo-modal-btn-secondary">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal Functions
function openLogModal(orgId, oppId, orgName, oppTitle) {
    document.getElementById('log_org_id').value = orgId;
    document.getElementById('log_opp_id').value = oppId;
    document.getElementById('log_org_name').innerText = orgName;
    document.getElementById('log_opp_title').innerText = oppTitle;
    document.getElementById('logHoursModal').style.display = 'flex';
}

function closeLogModal() {
    document.getElementById('logHoursModal').style.display = 'none';
}

function openReviewModal(targetId, targetName) {
    document.getElementById('review_target_id').value = targetId;
    document.getElementById('review_target_name').innerText = targetName;
    document.getElementById('reviewModal').style.display = 'flex';
    // Reset stars
    updateStars(5);
}

function closeReviewModal() {
    document.getElementById('reviewModal').style.display = 'none';
}

// Star Rating
function updateStars(rating) {
    document.getElementById('ratingInput').value = rating;
    document.querySelectorAll('.holo-star').forEach((star, index) => {
        star.classList.toggle('active', index < rating);
    });
}

document.querySelectorAll('.holo-star').forEach(star => {
    star.addEventListener('click', function() {
        updateStars(parseInt(this.dataset.value));
    });
});

// Initialize with 5 stars
updateStars(5);

// Close modals on backdrop click
window.addEventListener('click', function(event) {
    if (event.target.id === 'logHoursModal') closeLogModal();
    if (event.target.id === 'reviewModal') closeReviewModal();
});

// Close modals on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeLogModal();
        closeReviewModal();
    }
});

// Offline Indicator
(function initOfflineIndicator() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.add('visible');
        if (navigator.vibrate) navigator.vibrate(100);
    }

    function handleOnline() {
        banner.classList.remove('visible');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
})();

// Form Submission Offline Protection
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to submit.');
            return;
        }
    });
});
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
