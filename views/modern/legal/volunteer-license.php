<?php
// Volunteer Module Agreement - Modern Holographic Glassmorphism Edition
require __DIR__ . '/../../layouts/modern/header.php';
?>


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

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
