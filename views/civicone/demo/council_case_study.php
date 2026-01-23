<?php
$pageTitle = "Council Management Case Study - Project NEXUS";
$hSubtitle = "Community Wealth Building & Time Credits for Local Govt";
require __DIR__ . '/../../layouts/civicone/header.php';
?>

<main class="civic-container" style="padding: 40px 20px;">

    <!-- Council Branding Header -->
    <div style="background: linear-gradient(135deg, #002d72 0%, #1e3a8a 100%); padding: 50px; border-radius: 12px; color: white; margin-bottom: 40px;">
        <span style="background: rgba(255,255,255,0.2); text-transform: uppercase; letter-spacing: 1px; font-size: 0.8rem; padding: 4px 10px; border-radius: 4px;">Case Study: Local Government</span>
        <h1 style="color: white; margin: 15px 0; font-size: 2.5rem;">One County, Ten Hubs, One Dashboard</h1>
        <p style="font-size: 1.2rem; opacity: 0.9;">Centralised oversight with localised identity for Bantry, Ennis, and Skibbereen.</p>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 50px;">

        <div>
            <h2 style="color: #002d72; border-bottom: 2px solid #002d72; padding-bottom: 10px; display: inline-block;">The Challenge</h2>
            <p style="font-size: 1.1rem; line-height: 1.7; color: #334155;">
                County Councils manage diverse towns, each with unique needs and identities. Centralised "one-size-fits-all" platforms often fail to engage local residents who feel disconnected from a county-wide system.
            </p>
            <p style="font-size: 1.1rem; line-height: 1.7; color: #334155;">
                However, managing 30 different standalone websites for every town is administratively impossible and creates data silos.
            </p>
        </div>

        <div>
            <img src="https://placehold.co/600x300/002d72/FFF?text=Multi-Hub+Architecture" alt="Diagram showing Hub and Spoke model" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <p style="text-align: center; font-size: 0.85rem; color: #64748b; margin-top: 10px;">Fig 1. The Hub & Spoke Tenant Model</p>
        </div>

    </div>

    <div style="margin-top: 40px;">
        <h3 style="color: #002d72;">The Solution: Multi-Hub Management</h3>
        <p style="font-size: 1.1rem; margin-bottom: 30px;">
            A County Council manages town-specific exchanges from a single "Master Seat."
        </p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            <div style="background: #f8fafc; padding: 25px; border-radius: 8px; border-left: 4px solid #002d72;">
                <strong>Local Skins</strong>
                <p style="font-size: 0.95rem; color: #475569;">Each town maintains its unique identity, logo, and "Welcome" message. Residents feel they are joining *their* town's hub.</p>
            </div>
            <div style="background: #f8fafc; padding: 25px; border-radius: 8px; border-left: 4px solid #002d72;">
                <strong>Central Data</strong>
                <p style="font-size: 0.95rem; color: #475569;">The Council centralises data for grant reporting. "How many hours of volunteering happened in West Cork?" is answered in one click.</p>
            </div>
            <div style="background: #f8fafc; padding: 25px; border-radius: 8px; border-left: 4px solid #002d72;">
                <strong>Strategic Planning</strong>
                <p style="font-size: 0.95rem; color: #475569;">Identify capability gaps. If Hub A has excess gardening tools and Hub B has none, facilitate the transfer.</p>
            </div>
        </div>
    </div>

    <!-- Call to Action -->
    <div style="margin-top: 50px; text-align: center;">
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/technical-specs" class="civic-btn" style="background-color: #002d72; color: white; padding: 12px 25px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 1.1rem;">Review Technical Architecture</a>
    </div>

</main>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>