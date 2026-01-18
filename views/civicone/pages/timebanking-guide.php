<?php
// CivicOne View: Timebanking Guide
$pageTitle = 'Timebanking Guide';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <!-- Header Section -->
    <div class="civic-card" style="margin-bottom: 40px; text-align: center; padding: 50px;">
        <h1 style="margin-top: 0; margin-bottom: 15px; color: var(--skin-primary);">hOUR Timebank: Building Community</h1>
        <p style="font-size: 1.3rem; color: #555; margin-bottom: 30px;">Give an hour, get an hour. It’s that simple.</p>

        <div style="display: flex; justify-content: center; gap: 20px;">
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/register" class="civic-btn">Join Community</a>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/impact-report" class="civic-btn" style="background: #fff; color: #555; border: 1px solid #ccc;">See Impact</a>
        </div>
    </div>

    <!-- Verified Impact Stats -->
    <div style="margin-bottom: 50px;">
        <div style="text-align: center; margin-bottom: 30px;">
            <span style="background: var(--skin-primary); color: white; padding: 5px 15px; border-radius: 4px; font-weight: bold;">Our Verified Impact</span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px;">
            <div class="civic-card" style="text-align: center; padding: 30px;">
                <h3 style="color: var(--civic-text-secondary, #4B5563); font-size: 1rem; text-transform: uppercase; margin-top: 0;">Social Return</h3>
                <div style="font-size: 3rem; font-weight: bold; color: var(--skin-primary);">16:1</div>
            </div>
            <div class="civic-card" style="text-align: center; padding: 30px;">
                <h3 style="color: var(--civic-text-secondary, #4B5563); font-size: 1rem; text-transform: uppercase; margin-top: 0;">Improved Wellbeing</h3>
                <div style="font-size: 3rem; font-weight: bold; color: #ec4899;">100%</div>
            </div>
            <div class="civic-card" style="text-align: center; padding: 30px;">
                <h3 style="color: var(--civic-text-secondary, #4B5563); font-size: 1rem; text-transform: uppercase; margin-top: 0;">Socially Connected</h3>
                <div style="font-size: 3rem; font-weight: bold; color: #10b981;">95%</div>
            </div>
        </div>
    </div>

    <!-- How It Works -->
    <div class="civic-card" style="padding: 50px; margin-bottom: 50px;">
        <h2 style="text-align: center; margin-top: 0; margin-bottom: 40px; color: #333;">How It Works: 3 Simple Steps</h2>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px;">

            <div style="text-align: center;">
                <div style="background: #dbeafe; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; color: #2563eb; font-size: 2rem;">
                    <i class="fa-solid fa-handshake"></i>
                </div>
                <h3 style="color: #333; margin-bottom: 15px;">Give an Hour</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); line-height: 1.6;">Share a skill you love—from practical help to a friendly chat or a lift to the shops.</p>
            </div>

            <div style="text-align: center;">
                <div style="background: #fce7f3; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; color: #db2777; font-size: 2rem;">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <h3 style="color: #333; margin-bottom: 15px;">Earn a Credit</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); line-height: 1.6;">You automatically earn one Time Credit for every hour you spend helping another member.</p>
            </div>

            <div style="text-align: center;">
                <div style="background: #dcfce7; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; color: #166534; font-size: 2rem;">
                    <i class="fa-solid fa-user-group"></i>
                </div>
                <h3 style="color: #333; margin-bottom: 15px;">Get Help</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); line-height: 1.6;">Spend your credit to get support, learn a new skill, or join a community work day.</p>
            </div>

        </div>
    </div>

    <!-- Fundamental Values -->
    <div class="civic-card" style="padding: 50px; margin-bottom: 50px; border-left: 5px solid var(--skin-primary);">
        <h2 style="text-align: center; margin-top: 0; margin-bottom: 30px; color: #333;">Our Fundamental Values</h2>
        <p style="text-align: center; font-size: 1.1rem; color: #555; max-width: 800px; margin: 0 auto 30px auto;">
            At hOUR Timebank, we believe that true wealth is found in our connections with one another. Our community is built on five fundamental values:
        </p>

        <ul style="max-width: 800px; margin: 0 auto; line-height: 1.8; color: #555; padding-left: 20px;">
            <li style="margin-bottom: 15px;"><strong>We Are All Assets:</strong> Every human being has something of value to contribute.</li>
            <li style="margin-bottom: 15px;"><strong>Redefining Work:</strong> We honour the real work of family and community.</li>
            <li style="margin-bottom: 15px;"><strong>Reciprocity:</strong> Helping works better as a two-way street.</li>
            <li style="margin-bottom: 15px;"><strong>Social Networks:</strong> People flourish in community and perish in isolation.</li>
        </ul>
    </div>

    <!-- CTA -->
    <div class="civic-card" style="padding: 50px; text-align: center;">
        <span style="background: #9333ea; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; margin-bottom: 20px; display: inline-block;">Social Impact</span>
        <h2 style="margin-top: 0; margin-bottom: 20px; color: var(--skin-primary);">A 1:16 Return on Investment</h2>
        <p style="font-size: 1.1rem; color: #555; max-width: 700px; margin: 0 auto 30px auto; line-height: 1.6;">
            We have a proven, independently validated model. We are now seeking strategic partners to help us secure our core operations and scale our impact across Ireland.
        </p>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/partner" class="civic-btn">Partner With Us</a>
    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>