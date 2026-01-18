<?php
// Tenant Override: Public Sector Demo - Contact Us
$hTitle = 'Contact Us';
$hSubtitle = 'Get in touch with Project NEXUS.';
$hGradient = 'civic-hero-gradient-brand';

// FIX: Path is 3 levels deep (views/tenants/slug/pages) -> views/layouts
require __DIR__ . '/../../../layouts/header.php';
?>

<div class="civic-container civic-container-full" style="margin-top: -80px; position: relative; z-index: 20;">

    <div class="civic-card">
        <div class="civic-card-body" style="padding: 60px 40px;">

            <div style="text-align: center; margin-bottom: 60px;">
                <h2 style="font-size: 2.5rem; font-weight: 800; color: var(--civic-text-main); margin-bottom: 15px;">Get in Touch</h2>
                <p style="font-size: 1.15rem; color: var(--civic-text-muted);">Whether you're looking to join, partner with us, or discuss a national strategy, we're here to help.</p>
            </div>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap:50px;">

                <!-- Contact Form -->
                <div>
                    <div style="background: var(--civic-bg-card); border: 1px solid var(--civic-border-color); border-radius: 16px; padding: 40px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                        <h3 style="margin-top: 0; margin-bottom: 25px; color: var(--civic-text-main); font-size: 1.5rem; font-weight: 700;">Send Us a Message</h3>

                        <?php if (isset($_GET['sent'])): ?>
                            <div style="background: rgba(22, 163, 74, 0.2); color: var(--civic-text-main); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                                <strong>Success!</strong> Your message has been sent. We'll get back to you soon.
                            </div>
                        <?php endif; ?>

                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/contact/send" method="POST">
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--civic-text-main);">Your Name</label>
                                <input type="text" name="name" required class="civic-input" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--civic-border-color);">
                            </div>

                            <div style="margin-bottom: 20px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--civic-text-main);">Your Email</label>
                                <input type="email" name="email" required class="civic-input" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--civic-border-color);">
                            </div>

                            <div style="margin-bottom: 20px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--civic-text-main);">Subject</label>
                                <input type="text" name="subject" required class="civic-input" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--civic-border-color);">
                            </div>

                            <div style="margin-bottom: 30px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--civic-text-main);">Message</label>
                                <textarea name="message" rows="5" required class="civic-input" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--civic-border-color);"></textarea>
                            </div>

                            <button type="submit" class="civic-btn civic-btn-primary" style="width: 100%; justify-content: center;">Send Message</button>
                        </form>
                    </div>
                </div>

                <!-- Info Column -->
                <div style="display: flex; flex-direction: column; gap: 30px;">

                    <div style="background: rgba(37, 99, 235, 0.1); padding: 30px; border-radius: 16px; border: 1px solid rgba(37, 99, 235, 0.2);">
                        <h3 style="margin-top: 0; margin-bottom: 15px; color: #3b82f6;">Platform Coordinator</h3>
                        <p style="margin-bottom: 10px; color: var(--civic-text-main);"><strong>Email:</strong> <a href="mailto:web-admin@project-nexus.ie" style="color: #3b82f6; text-decoration: none;">web-admin@project-nexus.ie</a></p>
                    </div>

                    <div style="background: rgba(219, 39, 119, 0.1); padding: 30px; border-radius: 16px; border: 1px solid rgba(219, 39, 119, 0.2);">
                        <h3 style="margin-top: 0; margin-bottom: 15px; color: #db2777;">Mailing Address</h3>
                        <p style="margin-bottom: 0; color: var(--civic-text-muted); line-height: 1.6;">
                            Project NEXUS<br>
                            21 Páirc Goodman,<br>
                            Skibbereen,<br>
                            Co. Cork<br>
                            P81 AK26
                        </p>
                    </div>

                    <div style="background: var(--civic-bg-card); padding: 30px; border-radius: 16px; border: 1px solid var(--civic-border-color);">
                        <p style="margin-bottom: 0; color: var(--civic-text-muted); font-size: 0.9rem; text-align: center;">
                            &copy; 2025 Project NEXUS
                        </p>
                    </div>

                </div>

            </div>

        </div>
    </div>

</div>

<?php require __DIR__ . '/../../../layouts/civicone/footer.php'; ?>