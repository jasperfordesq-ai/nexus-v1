<?php
// Volunteer Module Agreement - Modern Holographic Glassmorphism Edition
require __DIR__ . '/../../layouts/civicone/header.php';
?>

<style>
/* ============================================
   VOLUNTEER LICENSE - Holographic Glassmorphism
   Purple Theme (#8b5cf6)
   ============================================ */

.holo-legal-page {
    min-height: 100vh;
    padding: 180px 20px 60px;
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 50%, #e9d5ff 100%);
}

[data-theme="dark"] .holo-legal-page {
    background: linear-gradient(135deg, #1a0a2e 0%, #2d1b4e 50%, #1f1035 100%);
}

/* Floating Orbs */
.holo-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(60px);
    opacity: 0.5;
    pointer-events: none;
    animation: floatOrb 20s ease-in-out infinite;
}

.holo-orb-1 {
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.4) 0%, transparent 70%);
    top: -100px;
    left: -100px;
    animation-delay: 0s;
}

.holo-orb-2 {
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(168, 85, 247, 0.35) 0%, transparent 70%);
    top: 40%;
    right: -80px;
    animation-delay: -7s;
}

.holo-orb-3 {
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(192, 132, 252, 0.3) 0%, transparent 70%);
    bottom: -50px;
    left: 30%;
    animation-delay: -14s;
}

[data-theme="dark"] .holo-orb-1 {
    background: radial-gradient(circle, rgba(139, 92, 246, 0.3) 0%, transparent 70%);
}

[data-theme="dark"] .holo-orb-2 {
    background: radial-gradient(circle, rgba(168, 85, 247, 0.25) 0%, transparent 70%);
}

[data-theme="dark"] .holo-orb-3 {
    background: radial-gradient(circle, rgba(192, 132, 252, 0.2) 0%, transparent 70%);
}

@keyframes floatOrb {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(30px, -30px) scale(1.05); }
    66% { transform: translate(-20px, 20px) scale(0.95); }
}

/* Glass Card */
.holo-glass-card {
    position: relative;
    max-width: 800px;
    margin: 0 auto;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(40px) saturate(180%);
    -webkit-backdrop-filter: blur(40px) saturate(180%);
    border-radius: 28px;
    border: 1px solid rgba(255, 255, 255, 0.5);
    padding: 48px;
    box-shadow:
        0 25px 50px -12px rgba(0, 0, 0, 0.1),
        0 0 0 1px rgba(255, 255, 255, 0.3) inset;
    overflow: hidden;
}

[data-theme="dark"] .holo-glass-card {
    background: rgba(24, 24, 27, 0.6);
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow:
        0 25px 50px -12px rgba(0, 0, 0, 0.4),
        0 0 0 1px rgba(255, 255, 255, 0.05) inset;
}

/* Shimmer Effect */
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
    0%, 100% { left: -100%; }
    50% { left: 100%; }
}

/* Iridescent Top Edge */
.holo-glass-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg,
        transparent,
        rgba(139, 92, 246, 0.6),
        rgba(168, 85, 247, 0.6),
        rgba(192, 132, 252, 0.6),
        transparent
    );
}

/* Header */
.holo-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding-bottom: 24px;
    margin-bottom: 32px;
    border-bottom: 2px solid rgba(139, 92, 246, 0.15);
}

[data-theme="dark"] .holo-header {
    border-bottom-color: rgba(168, 85, 247, 0.2);
}

.holo-header-content {
    display: flex;
    align-items: center;
    gap: 20px;
}

.holo-header-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    color: white;
    box-shadow: 0 8px 24px rgba(139, 92, 246, 0.3);
    flex-shrink: 0;
}

.holo-title {
    font-size: 1.6rem;
    font-weight: 800;
    margin: 0;
    background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 50%, #a78bfa 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.02em;
}

[data-theme="dark"] .holo-title {
    background: linear-gradient(135deg, #c4b5fd 0%, #a78bfa 50%, #ffffff 100%);
    -webkit-background-clip: text;
    background-clip: text;
}

.holo-btn-print {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 50px;
    background: rgba(139, 92, 246, 0.1);
    border: 2px solid rgba(139, 92, 246, 0.2);
    color: #7c3aed;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: inherit;
    white-space: nowrap;
}

.holo-btn-print:hover {
    background: rgba(139, 92, 246, 0.15);
    border-color: rgba(139, 92, 246, 0.35);
    transform: translateY(-2px);
}

[data-theme="dark"] .holo-btn-print {
    background: rgba(139, 92, 246, 0.15);
    border-color: rgba(168, 85, 247, 0.25);
    color: #c4b5fd;
}

[data-theme="dark"] .holo-btn-print:hover {
    background: rgba(139, 92, 246, 0.25);
    border-color: rgba(168, 85, 247, 0.4);
}

/* Content */
.holo-intro {
    font-size: 1.1rem;
    color: #4b5563;
    line-height: 1.7;
    margin-bottom: 32px;
}

[data-theme="dark"] .holo-intro {
    color: rgba(255, 255, 255, 0.75);
}

.holo-intro strong {
    color: #7c3aed;
}

[data-theme="dark"] .holo-intro strong {
    color: #c4b5fd;
}

/* Certifications Box */
.holo-cert-box {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.08) 0%, rgba(168, 85, 247, 0.05) 100%);
    border: 2px solid rgba(139, 92, 246, 0.2);
    border-radius: 20px;
    padding: 32px;
    margin-bottom: 32px;
}

[data-theme="dark"] .holo-cert-box {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.12) 0%, rgba(168, 85, 247, 0.06) 100%);
    border-color: rgba(168, 85, 247, 0.25);
}

.holo-cert-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.15rem;
    font-weight: 700;
    color: #6d28d9;
    margin: 0 0 24px;
}

.holo-cert-title i {
    color: #22c55e;
    font-size: 1.2rem;
}

[data-theme="dark"] .holo-cert-title {
    color: #c4b5fd;
}

.holo-cert-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.holo-cert-item {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 16px 0;
    border-bottom: 1px solid rgba(139, 92, 246, 0.1);
}

.holo-cert-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.holo-cert-item:first-child {
    padding-top: 0;
}

[data-theme="dark"] .holo-cert-item {
    border-bottom-color: rgba(168, 85, 247, 0.15);
}

.holo-cert-icon {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15) 0%, rgba(168, 85, 247, 0.1) 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #8b5cf6;
    font-size: 0.85rem;
    flex-shrink: 0;
}

[data-theme="dark"] .holo-cert-icon {
    background: rgba(139, 92, 246, 0.2);
    color: #c4b5fd;
}

.holo-cert-text {
    font-size: 1rem;
    color: #374151;
    line-height: 1.6;
    flex: 1;
}

[data-theme="dark"] .holo-cert-text {
    color: rgba(255, 255, 255, 0.8);
}

.holo-cert-text strong {
    color: #5b21b6;
}

[data-theme="dark"] .holo-cert-text strong {
    color: #e9d5ff;
}

/* Footer */
.holo-legal-footer {
    text-align: center;
    padding-top: 28px;
    margin-top: 8px;
    border-top: 1px solid rgba(139, 92, 246, 0.15);
}

[data-theme="dark"] .holo-legal-footer {
    border-top-color: rgba(168, 85, 247, 0.2);
}

.holo-legal-meta {
    font-size: 0.9rem;
    color: #6b7280;
    font-style: italic;
}

[data-theme="dark"] .holo-legal-meta {
    color: rgba(255, 255, 255, 0.5);
}

/* Mobile Responsive */
@media (max-width: 900px) {
    .holo-legal-page {
        padding: 20px 16px 120px;
    }

    .holo-glass-card {
        padding: 32px 24px;
        border-radius: 24px;
    }

    .holo-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }

    .holo-header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }

    .holo-title {
        font-size: 1.4rem;
    }

    .holo-header-icon {
        width: 48px;
        height: 48px;
        font-size: 1.2rem;
    }

    .holo-btn-print {
        width: 100%;
        justify-content: center;
    }

    .holo-intro {
        font-size: 1rem;
    }

    .holo-cert-box {
        padding: 24px 20px;
    }
}

/* Print Styles */
@media print {
    .holo-legal-page {
        padding: 20px;
        background: white !important;
    }

    .holo-orb {
        display: none !important;
    }

    .holo-glass-card {
        background: white !important;
        box-shadow: none !important;
        border: 1px solid #e5e7eb !important;
    }

    .holo-glass-card::before,
    .holo-glass-card::after {
        display: none !important;
    }

    .holo-btn-print {
        display: none !important;
    }

    .holo-title {
        -webkit-text-fill-color: #7c3aed !important;
        color: #7c3aed !important;
    }

    .holo-cert-box {
        background: #f9fafb !important;
        border-color: #e5e7eb !important;
    }
}
</style>

<div class="holo-legal-page">
    <!-- Floating Orbs -->
    <div class="holo-orb holo-orb-1"></div>
    <div class="holo-orb holo-orb-2"></div>
    <div class="holo-orb holo-orb-3"></div>

    <div class="holo-glass-card">
        <div class="holo-header">
            <div class="holo-header-content">
                <div class="holo-header-icon">
                    <i class="fa-solid fa-file-contract"></i>
                </div>
                <h1 class="holo-title">Registered Organization License</h1>
            </div>
            <button onclick="window.print()" class="holo-btn-print">
                <i class="fa-solid fa-print"></i>
                Print Agreement
            </button>
        </div>

        <p class="holo-intro">
            This document constitutes a binding agreement between <strong>Project NEXUS</strong> and the entity registering as a <strong>Volunteer Organization</strong>. By creating an organization profile, you acknowledge and agree to the following terms:
        </p>

        <div class="holo-cert-box">
            <h4 class="holo-cert-title">
                <i class="fa-solid fa-circle-check"></i>
                Key Certifications
            </h4>
            <ul class="holo-cert-list">
                <li class="holo-cert-item">
                    <div class="holo-cert-icon">
                        <i class="fa-solid fa-building-ngo"></i>
                    </div>
                    <div class="holo-cert-text">
                        You utilize this platform solely on behalf of a <strong>legitimate, registered non-profit, charity, or community group</strong> operating within Ireland.
                    </div>
                </li>
                <li class="holo-cert-item">
                    <div class="holo-cert-icon">
                        <i class="fa-solid fa-id-card"></i>
                    </div>
                    <div class="holo-cert-text">
                        You possess a valid <strong>Registered Charity Number (RCN)</strong> or equivalent constitution where applicable, which may be requested for verification.
                    </div>
                </li>
                <li class="holo-cert-item">
                    <div class="holo-cert-icon">
                        <i class="fa-solid fa-handshake-angle"></i>
                    </div>
                    <div class="holo-cert-text">
                        You understand that this module is for <strong>professional volunteer recruitment</strong> purposes only.
                    </div>
                </li>
                <li class="holo-cert-item">
                    <div class="holo-cert-icon">
                        <i class="fa-solid fa-ban"></i>
                    </div>
                    <div class="holo-cert-text">
                        <strong>Personal aid requests</strong> or solicitations for individual financial gain are strictly forbidden and will result in immediate account termination.
                    </div>
                </li>
            </ul>
        </div>

        <div class="holo-legal-footer">
            <p class="holo-legal-meta">
                Last Revised: <?= date('F Y') ?> &bull; Project NEXUS Legal Department
            </p>
        </div>
    </div>
</div>

<script>
// Button Touch Feedback
document.querySelectorAll('.holo-btn-print').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.97)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
