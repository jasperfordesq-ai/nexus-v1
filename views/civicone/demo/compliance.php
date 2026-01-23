<?php
$pageTitle = "Compliance Statement - Project NEXUS";
$hSubtitle = "WCAG 2.1 AA & GDPR Compliance Documentation";
require __DIR__ . '/../../layouts/civicone/header.php';
?>

<main class="civic-container" style="padding: 40px 20px;">

    <header style="margin-bottom: 40px; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px;">
        <span style="background: #e0f2fe; color: #002d72; padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 0.9rem;">Security & Standards</span>
        <h1 style="color: #002d72; margin-top: 15px; font-size: 2.5rem;">Government-Grade Infrastructure</h1>
        <p style="font-size: 1.2rem; color: #475569;">Security That Meets National Standards.</p>
    </header>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 40px;">

        <div>
            <p style="font-size: 1.1rem; line-height: 1.7; color: #334155; margin-bottom: 30px;">
                Designed with the Irish public sector in mind, our architecture ensures absolute data sovereignty and compliance. We adhere to rigorous standards to ensure trust and reliability for Local Authorities and the HSE.
            </p>

            <div style="margin-bottom: 30px;">
                <h3 style="color: #1e293b; display: flex; align-items: center; gap: 10px;">
                    <span style="background: #dcfce7; color: #166534; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">✓</span>
                    GDPR & Multi-Tenancy
                </h3>
                <p style="color: #475569; margin-left: 40px;">
                    Using strict physical <code>tenant_id</code> isolation to ensure no data leakage between different local authorities. Data is encrypted at rest and in transit.
                </p>
            </div>

            <div style="margin-bottom: 30px;">
                <h3 style="color: #1e293b; display: flex; align-items: center; gap: 10px;">
                    <span style="background: #dcfce7; color: #166534; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">✓</span>
                    S.I. No. 358/2020 Compliance
                </h3>
                <p style="color: #475569; margin-left: 40px;">
                    The CivicOne interface is audited for WCAG 2.1 Level AA accessibility, ensuring inclusivity for all citizens regardless of ability.
                </p>
            </div>

            <div style="margin-bottom: 30px;">
                <h3 style="color: #1e293b; display: flex; align-items: center; gap: 10px;">
                    <span style="background: #dcfce7; color: #166534; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">✓</span>
                    Forensic Audit Trails
                </h3>
                <p style="color: #475569; margin-left: 40px;">
                    Every administrative action is logged with Actor ID, IP Address, and Timestamps for full accountability and transparent governance.
                </p>
            </div>
        </div>

        <!-- Sidebar -->
        <div style="background: #f8fafc; padding: 25px; border-radius: 12px; height: fit-content; border: 1px solid #e2e8f0;">
            <h4 style="margin-top: 0; color: #002d72;">Technical Specs</h4>
            <ul style="padding-left: 20px; color: #64748b; line-height: 1.8;">
                <li><strong>Hosting:</strong> Dublin, Ireland (EU)</li>
                <li><strong>Encryption:</strong> AES-256</li>
                <li><strong>Role Access:</strong> RBAC Level 3</li>
                <li><strong>Sovereignty:</strong> 100% Irish Data Residency</li>
            </ul>

            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/technical-specs" class="civic-btn" style="display: block; text-align: center; margin-top: 20px; background: white; border: 1px solid #cbd5e1; color: #334155; text-decoration: none; padding: 10px; border-radius: 6px; font-weight: 600;">View Full Proposal</a>
        </div>

    </div>

</main>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>