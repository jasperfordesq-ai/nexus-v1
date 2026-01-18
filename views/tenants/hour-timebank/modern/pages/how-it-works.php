<?php
// Phoenix View: How It Works
$hTitle = 'How It Works';
$hSubtitle = 'Exchange skills, save money, build trust.';
$hGradient = 'htb-hero-gradient-brand';

require __DIR__ . '/../../../..' . '/layouts/modern/header.php';
?>

<div class="htb-container" style="margin-top: -80px; position: relative; z-index: 20; display: block; max-width: 1000px; margin-left: auto; margin-right: auto;">

    <div class="htb-card">
        <div class="htb-card-body" style="padding: 60px 40px; text-align: center;">

            <h2 style="font-size: 2.5rem; font-weight: 800; color: var(--htb-text-main); margin-bottom: 20px;">The Simple Cycle of Timebanking</h2>
            <p style="font-size: 1.25rem; color: var(--htb-text-muted); margin-bottom: 60px; max-width: 700px; margin-left: auto; margin-right: auto; line-height: 1.6;">
                Hour Timebank is a community currency system where time is the money. Everyone's hour is worth the same, regardless of the service provided.
            </p>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:50px;">

                <!-- Step 1 -->
                <div style="text-align:center;">
                    <div style="width:100px; height:100px; background:rgba(37, 99, 235, 0.1); color:#3b82f6; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 25px auto; font-size: 3rem;">
                        ❤️
                    </div>
                    <h3 style="font-size:1.5rem; font-weight:700; margin-bottom:15px; color: var(--htb-text-main);">1. Give Time</h3>
                    <p style="color:var(--htb-text-muted); line-height:1.6;">Offer your skills or help to a neighbour. Whether it's gardening, teaching, or tech support, your contribution matters.</p>
                </div>

                <!-- Step 2 -->
                <div style="text-align:center;">
                    <div style="width:100px; height:100px; background:rgba(16, 185, 129, 0.1); color:#10b981; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 25px auto; font-size: 3rem;">
                        ⏰
                    </div>
                    <h3 style="font-size:1.5rem; font-weight:700; margin-bottom:15px; color: var(--htb-text-main);">2. Earn Credits</h3>
                    <p style="color:var(--htb-text-muted); line-height:1.6;">For every hour you give, you earn 1 Time Credit. It's banked automatically in your digital wallet.</p>
                </div>

                <!-- Step 3 -->
                <div style="text-align:center;">
                    <div style="width:100px; height:100px; background:rgba(249, 115, 22, 0.1); color:#f97316; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 25px auto; font-size: 3rem;">
                        🤝
                    </div>
                    <h3 style="font-size:1.5rem; font-weight:700; margin-bottom:15px; color: var(--htb-text-main);">3. Get Help</h3>
                    <p style="color:var(--htb-text-muted); line-height:1.6;">Spend your credits to receive help from others. Learn a new language, get a ride, or find a pet sitter.</p>
                </div>

            </div>

            <?php if (!isset($_SESSION['user_id'])): ?>
                <div style="margin-top: 60px;">
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/register" class="htb-btn htb-btn-primary" style="display:inline-flex; border-radius: 99px; padding: 15px 40px; font-size: 1.1rem;">Join the Movement</a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require __DIR__ . '/../../../..' . '/layouts/modern/footer.php'; ?>
