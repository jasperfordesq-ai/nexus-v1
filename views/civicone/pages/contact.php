<?php
// CivicOne View: Contact Us
$pageTitle = 'Contact Us';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <div class="civic-card" style="margin-bottom: 40px; text-align: center; padding: 40px;">
        <h1 style="text-transform: uppercase; margin-bottom: 10px; font-size: 2.5rem; color: var(--skin-primary);">Contact Us</h1>
        <p style="font-size: 1.2rem; max-width: 600px; margin: 0 auto; color: #555;">
            We'd love to hear from you! Whether you have questions about timebanking, need support, or just want to say hello.
        </p>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">

        <!-- Contact Form -->
        <div class="civic-card">
            <h2 style="margin-top: 0; color: var(--skin-primary); margin-bottom: 20px;">Send a Message</h2>
            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/contact/submit" method="POST">
                <?= Nexus\Core\Csrf::input() ?>

                <div style="margin-bottom: 20px;">
                    <label for="name" style="display: block; font-weight: bold; margin-bottom: 5px;">Your Name</label>
                    <input type="text" name="name" id="name" required class="civic-input" style="width: 100%;" value="<?= isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '' ?>">
                </div>

                <div style="margin-bottom: 20px;">
                    <label for="email" style="display: block; font-weight: bold; margin-bottom: 5px;">Email Address</label>
                    <input type="email" name="email" id="email" required class="civic-input" style="width: 100%;" value="<?= isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : '' ?>">
                </div>

                <div style="margin-bottom: 20px;">
                    <label for="subject" style="display: block; font-weight: bold; margin-bottom: 5px;">Subject</label>
                    <select name="subject" id="subject" class="civic-input" style="width: 100%;">
                        <option value="General Inquiry">General Inquiry</option>
                        <option value="Support">Support</option>
                        <option value="Partnership">Partnership</option>
                        <option value="Feedback">Feedback</option>
                    </select>
                </div>

                <div style="margin-bottom: 20px;">
                    <label for="message" style="display: block; font-weight: bold; margin-bottom: 5px;">Message</label>
                    <textarea name="message" id="message" rows="5" required class="civic-input" style="width: 100%; font-family: inherit;"></textarea>
                </div>

                <button type="submit" class="civic-btn" style="width: 100%; font-size: 1.2rem;">Send Message</button>
            </form>
        </div>

        <!-- Info & Map -->
        <div>
            <div class="civic-card" style="margin-bottom: 30px;">
                <h2 style="margin-top: 0; color: var(--skin-primary); margin-bottom: 20px;">Get in Touch</h2>

                <div style="margin-bottom: 20px; display: flex; align-items: flex-start;">
                    <div style="font-size: 1.5rem; margin-right: 15px; color: var(--skin-primary);">📍</div>
                    <div>
                        <strong style="display: block; font-size: 1.1rem; margin-bottom: 3px;">Address</strong>
                        hOUR Timebank CLG<br>
                        Main Street, Skibbereen<br>
                        Co. Cork, Ireland
                    </div>
                </div>

                <div style="margin-bottom: 20px; display: flex; align-items: flex-start;">
                    <div style="font-size: 1.5rem; margin-right: 15px; color: var(--skin-primary);">📧</div>
                    <div>
                        <strong style="display: block; font-size: 1.1rem; margin-bottom: 3px;">Email</strong>
                        hello@hourtimebank.ie
                    </div>
                </div>

                <div style="display: flex; align-items: flex-start;">
                    <div style="font-size: 1.5rem; margin-right: 15px; color: var(--skin-primary);">🕒</div>
                    <div>
                        <strong style="display: block; font-size: 1.1rem; margin-bottom: 3px;">Hours</strong>
                        Mon - Fri: 9am - 5pm
                    </div>
                </div>
            </div>

            <!-- Simple Embedded Map Placeholder -->
            <div class="civic-card" style="padding: 0; overflow: hidden; height: 300px; background: #eee; display: flex; align-items: center; justify-content: center; color: #888;">
                [ MAP EMBED WOULD GO HERE ]
            </div>
        </div>

    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>