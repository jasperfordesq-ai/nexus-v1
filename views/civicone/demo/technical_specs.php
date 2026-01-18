<?php
$pageTitle = "Technical Specifications - Project NEXUS";
$hSubtitle = "Security Standards, GDPR Compliance & Cloud Architecture";
require __DIR__ . '/../../layouts/civicone/header.php';
?>

<main id="main-content" class="civic-container" style="padding: 40px 20px;">

    <header style="margin-bottom: 40px; text-align: center;">
        <h1 style="color: #0d1b2a; font-size: 2.5rem; margin-bottom: 10px;">Technical Proposal</h1>
        <p style="font-size: 1.2rem; color: #475569;">Project NEXUS: Public Sector Edition</p>
    </header>

    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 40px; max-width: 900px; margin: 0 auto;">

        <!-- Platform Summary -->
        <h2 style="color: #002d72; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-top: 0;">1. Executive Summary</h2>
        <table style="width: 100%; text-align: left; border-collapse: collapse; margin-bottom: 30px;">
            <tr>
                <th style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; color: #64748b; width: 30%;">Platform</th>
                <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; font-weight: 600;">NEXUS (Custom PHP MVC)</td>
            </tr>
            <tr>
                <th style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; color: #64748b;">Database</th>
                <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; font-weight: 600;">MySQL (Spatial Extensions Enabled)</td>
            </tr>
            <tr>
                <th style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; color: #64748b;">Hosting</th>
                <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; font-weight: 600;">Ireland-based / Data Sovereign</td>
            </tr>
            <tr>
                <th style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; color: #64748b;">Performance</th>
                <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; font-weight: 600;">
                    < 300ms Page Load (Zero-Bloat Arch)</td>
            </tr>
        </table>

        <!-- Security -->
        <h2 style="color: #002d72; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">2. Security & Compliance</h2>
        <ul style="line-height: 1.6; color: #334155; margin-bottom: 30px;">
            <li style="margin-bottom: 10px;"><strong>SQL Injection Proof:</strong> All database interactions utilize PDO Prepared Statements.</li>
            <li style="margin-bottom: 10px;"><strong>Strict Multi-Tenancy:</strong> Physical `tenant_id` scoping at the Database Wrapper level prevents cross-contamination.</li>
            <li style="margin-bottom: 10px;"><strong>Audit Logging:</strong> Granular tracking of all User/Admin actions.</li>
            <li style="margin-bottom: 10px;"><strong>Accessibility:</strong> WCAG 2.1 Level AA Compliant (CivicOne Layout).</li>
        </ul>

        <!-- Integration -->
        <h2 style="color: #002d72; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">3. Integration Capabilities</h2>
        <p style="color: #334155; line-height: 1.6;">
            The NEXUS platform is API-First. We expose secure RESTful endpoints for integration with existing Council/HSE data portals, CRM systems (Salesforce/Microsoft Dynamics), and volunteer registries.
        </p>

    </div>

    <div style="text-align: center; margin-top: 50px;">
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/" class="civic-btn" style="background-color: #0d1b2a; color: white; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: 600;">Back to Demo Home</a>
    </div>

</main>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>