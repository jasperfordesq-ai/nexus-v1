<?php
/**
 * Seed Legal Documents Content
 *
 * This script imports the existing Terms of Service (v2.0) and Privacy Policy
 * content into the new legal_documents versioning system.
 *
 * Run after: 2026_01_25_create_legal_documents_system.sql
 * Usage: php migrations/2026_01_25_seed_legal_documents_content.php
 *
 * @date 2026-01-25
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;

echo "=== Legal Documents Content Seeder ===\n\n";

// Check if tables exist
try {
    $stmt = Database::query("SHOW TABLES LIKE 'legal_documents'");
    if (!$stmt->fetch()) {
        die("ERROR: legal_documents table does not exist. Run the SQL migration first.\n");
    }
} catch (Exception $e) {
    die("ERROR: Database connection failed: " . $e->getMessage() . "\n");
}

// Get admin user ID (first admin user)
$adminStmt = Database::query("SELECT id FROM users WHERE is_admin = 1 LIMIT 1");
$admin = $adminStmt->fetch();
$adminId = $admin ? $admin['id'] : 1;

echo "Using admin ID: {$adminId}\n\n";

// ============================================================================
// HOUR-TIMEBANK (Tenant 2) TERMS OF SERVICE v2.0
// ============================================================================

$termsContent = <<<'HTML'
<h2 id="agreement">Agreement to Terms</h2>
<p>By accessing or using hOUR Timebank Ireland (accessible at <strong>hour-timebank.ie</strong>), you agree to be bound by these Terms of Service. Please read them carefully before participating in our community.</p>
<p>These terms establish a framework for <strong>fair, respectful, and meaningful exchanges</strong> between community members across Ireland. Our goal is to create a trusted environment where everyone's time is valued equally.</p>
<p>If you are using the platform on behalf of an organisation, you represent that you have authority to bind that organisation to these terms.</p>

<h2 id="platform-role">Platform Role & Responsibility</h2>
<p><strong>hOUR Timebank Ireland is a connection platform only.</strong> We provide technology and tools that enable community members to find and arrange service exchanges with each other.</p>
<div class="legal-notice">
    <h4>Critical Distinction</h4>
    <p><strong>We are NOT a service provider.</strong> We do not perform, supervise, direct, or control any services exchanged between members. We are similar to online platforms like Yelp, Facebook Marketplace, or community notice boards — we facilitate connections, but the actual exchanges are entirely between independent members.</p>
</div>
<p><strong>What we provide:</strong></p>
<ul>
    <li>A platform to post service offers and requests</li>
    <li>Tools to connect with other community members</li>
    <li>A time credit system to track exchanges</li>
    <li>Community guidelines and dispute resolution support</li>
    <li>Educational resources about timebanking</li>
</ul>
<p><strong>What we do NOT provide:</strong></p>
<ul>
    <li>Vetting, training, or certification of service providers</li>
    <li>Supervision or quality control of services</li>
    <li>Insurance coverage for exchanges</li>
    <li>Employment or contractor relationships</li>
    <li>Guarantees about service quality or completion</li>
</ul>

<h2 id="who-we-are">1. Who We Are</h2>
<p>hOUR Timebank Ireland is operated by <strong>hOUR Timebank CLG</strong> (Company Limited by Guarantee), a registered Irish charity.</p>
<ul>
    <li><strong>Charity Registration Number:</strong> RCN 20162023</li>
    <li><strong>Registered Address:</strong> 21 Páirc Goodman, Skibbereen, Co. Cork, P81 AK26, Ireland</li>
    <li><strong>Primary Contact:</strong> jasper@hour-timebank.ie</li>
</ul>
<p>We are registered with the Charities Regulator and operate in compliance with the Charities Act 2009.</p>

<h2 id="transactions">3. Transaction Recording & Verification</h2>
<p>Time credits are the internal currency of our timebank. They are recorded when members complete service exchanges.</p>
<h3>Recording Process</h3>
<p>After completing an exchange:</p>
<ol>
    <li>The member who <strong>received</strong> the service logs the transaction in their account</li>
    <li>They record the number of hours and a description of the service</li>
    <li>The member who <strong>provided</strong> the service receives a notification</li>
    <li>Both members can view the transaction in their wallet history</li>
</ol>
<h3>Disputes</h3>
<p>If there is a disagreement about a transaction:</p>
<ul>
    <li>Members should first attempt to resolve it directly with each other</li>
    <li>If unresolved, either party may contact our Community Support team</li>
    <li>We will review the transaction and may adjust or cancel it if appropriate</li>
    <li>Our decision on disputed transactions is final</li>
</ul>
<p><strong>Time credits have no monetary value</strong> and cannot be exchanged for cash. They exist solely to facilitate community exchanges.</p>

<h2 id="safeguarding">4. Safeguarding</h2>
<p>We take safeguarding seriously. hOUR Timebank Ireland operates in accordance with the <strong>National Vetting Bureau (Children and Vulnerable Persons) Acts 2012-2016</strong> and <strong>Children First Act 2015</strong>.</p>
<h3>Garda Vetting</h3>
<p>Members who wish to provide services involving:</p>
<ul>
    <li>Children under 18 years of age</li>
    <li>Vulnerable adults</li>
    <li>Unsupervised access to private homes</li>
</ul>
<p>...may be required to undergo Garda Vetting through our registered organisation.</p>
<h3>Reporting Concerns</h3>
<p>If you have any safeguarding concerns about a member or an exchange, please report them immediately to our Safeguarding Lead at <a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a>.</p>

<h2 id="relationship">5. Nature of Relationship</h2>
<p>The relationship between members is that of <strong>independent volunteers</strong> helping each other within a community framework.</p>
<div class="legal-notice">
    <h4>No Employment Relationship</h4>
    <p>Time banking is a form of <strong>mutual aid</strong>. Members are NOT employees, contractors, agents, or joint venture partners of hOUR Timebank Ireland or each other. There is no employer-employee relationship created by participating in timebanking.</p>
</div>
<p>Under Irish law and Revenue guidance, genuine time-for-time exchanges between individuals are generally not considered taxable income. However, members are responsible for their own tax affairs and should seek professional advice if uncertain.</p>

<h2 id="assumption-of-risk">6. Assumption of Risk</h2>
<p>By participating in timebanking exchanges, you acknowledge and accept certain inherent risks:</p>
<ul>
    <li><strong>Quality of Services:</strong> Services are provided by community volunteers with varying skill levels</li>
    <li><strong>Property:</strong> There is some risk of accidental damage when inviting others into your home or using their tools/equipment</li>
    <li><strong>Personal Safety:</strong> While we maintain community guidelines, we cannot guarantee the behaviour of all members</li>
    <li><strong>Completion:</strong> Members may sometimes be unable to complete agreed exchanges</li>
</ul>
<p>We encourage members to:</p>
<ul>
    <li>Start with small exchanges to build trust</li>
    <li>Check references and reviews where available</li>
    <li>Meet in public places for first meetings where appropriate</li>
    <li>Trust your instincts — you can always decline an exchange</li>
</ul>

<h2 id="privacy">7. Privacy & Data Protection</h2>
<p>We are committed to protecting your personal data in accordance with the <strong>General Data Protection Regulation (GDPR)</strong> and the <strong>Data Protection Acts 1988-2018</strong>.</p>
<h3>Data Controller</h3>
<p>hOUR Timebank CLG is the data controller for personal information collected through this platform.</p>
<h3>What We Collect</h3>
<p>We collect only the information necessary to operate the timebank:</p>
<ul>
    <li>Account information (name, email, password)</li>
    <li>Profile details (bio, skills, location)</li>
    <li>Transaction history (time credits, exchanges)</li>
    <li>Communications through the platform</li>
</ul>
<p>For full details, please see our <a href="/hour-timebank/privacy">Privacy Policy</a>.</p>

<h2 id="content">8. User Content</h2>
<p>You retain ownership of any content you post (listings, messages, reviews, etc.), but grant us a license to display it on the platform. You are responsible for ensuring your content:</p>
<ul>
    <li>Is accurate and not misleading</li>
    <li>Does not infringe any third-party rights</li>
    <li>Complies with Irish law</li>
    <li>Follows our Community Guidelines</li>
</ul>
<p>We may remove content that violates these terms without notice.</p>

<h2 id="reviews">9. Reviews and Feedback System</h2>
<p>Our review system helps build trust in the community by allowing members to share their experiences.</p>
<h3>Review Guidelines</h3>
<p>When leaving reviews, members must:</p>
<ul>
    <li>Be honest and factual</li>
    <li>Base feedback on actual exchange experiences</li>
    <li>Avoid personal attacks or discriminatory language</li>
    <li>Not include private contact information</li>
</ul>
<h3>Review Moderation</h3>
<p>We moderate reviews and may remove content that:</p>
<ul>
    <li>Contains false statements of fact</li>
    <li>Is defamatory under the <strong>Defamation Act 2009</strong></li>
    <li>Violates our Community Guidelines</li>
    <li>Is clearly fraudulent or malicious</li>
</ul>
<h3>Dispute Process</h3>
<p>If you believe a review about you is unfair or inaccurate:</p>
<ol>
    <li>You may respond publicly to the review</li>
    <li>You may report the review for moderation</li>
    <li>Our team will review and may remove it if it violates our policies</li>
</ol>
<p>In accordance with Section 27 of the Defamation Act 2009, we will consider removing reviews containing false statements of fact upon receipt of a valid complaint.</p>

<h2 id="liability">10. Limitation of Liability</h2>
<div class="legal-notice">
    <h4>Please Read Carefully</h4>
    <p>To the fullest extent permitted by Irish law:</p>
</div>
<ul>
    <li>hOUR Timebank Ireland provides this platform <strong>"as is"</strong> without warranties of any kind</li>
    <li>We are <strong>not liable</strong> for any damages arising from exchanges between members</li>
    <li>We are <strong>not liable</strong> for the quality, safety, or legality of services exchanged</li>
    <li>We are <strong>not liable</strong> for any loss, injury, or damage resulting from your use of the platform or participation in exchanges</li>
    <li>Our total liability to you for any claims shall not exceed the value of time credits in your account</li>
</ul>
<p>Nothing in these terms excludes or limits our liability for:</p>
<ul>
    <li>Death or personal injury caused by our negligence</li>
    <li>Fraud or fraudulent misrepresentation</li>
    <li>Any other matter which cannot be excluded by law</li>
</ul>

<h2 id="indemnity">11. Indemnity</h2>
<p>You agree to indemnify and hold harmless hOUR Timebank Ireland, its officers, directors, volunteers, and agents from any claims, damages, losses, or expenses (including legal fees) arising from:</p>
<ul>
    <li>Your use of the platform</li>
    <li>Your participation in exchanges</li>
    <li>Your violation of these terms</li>
    <li>Your violation of any rights of another person</li>
</ul>

<h2 id="termination">12. Account Termination</h2>
<p>We may suspend or terminate your account if you:</p>
<ul>
    <li>Violate these Terms of Service</li>
    <li>Breach our Community Guidelines</li>
    <li>Engage in fraudulent or illegal activity</li>
    <li>Pose a safeguarding risk to other members</li>
</ul>
<p>You may close your account at any time through your account settings. Upon termination, your time credits will expire and cannot be transferred.</p>

<h2 id="insurance">13. Insurance</h2>
<p>hOUR Timebank Ireland maintains appropriate insurance for its organisational activities. However:</p>
<ul>
    <li>This insurance does <strong>not</strong> cover exchanges between members</li>
    <li>Members are responsible for their own insurance (home, liability, etc.)</li>
    <li>We recommend members check their existing policies cover volunteer activities</li>
</ul>

<h2 id="disputes">14. Dispute Resolution</h2>
<p>If you have a complaint about the platform or another member:</p>
<ol>
    <li><strong>Direct Resolution:</strong> Try to resolve the issue directly with the other party</li>
    <li><strong>Community Support:</strong> Contact our Community Support team for mediation</li>
    <li><strong>Formal Complaint:</strong> Submit a formal complaint in writing if still unresolved</li>
</ol>
<p>These terms are governed by <strong>Irish law</strong>. Any disputes shall be subject to the exclusive jurisdiction of the courts of Ireland.</p>

<h2 id="modifications">15. Modifications</h2>
<p>We may update these Terms of Service from time to time. When we make significant changes:</p>
<ul>
    <li>We will update the "Last Updated" date at the top</li>
    <li>We will notify you via email or platform notification</li>
    <li>Continued use after changes constitutes acceptance</li>
</ul>

<h2 id="accessibility">16. Accessibility</h2>
<p>We are committed to making our platform accessible to all users, including those with disabilities. If you experience accessibility issues, please contact us at <a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a>.</p>

<h2 id="intellectual-property">17. Intellectual Property</h2>
<p>The hOUR Timebank Ireland name, logo, and platform design are our intellectual property. You may not use our branding without written permission.</p>

<h2 id="force-majeure">18. Force Majeure</h2>
<p>We are not liable for any failure or delay in performing our obligations due to circumstances beyond our reasonable control, including natural disasters, pandemics, government actions, or technical failures.</p>

<h2 id="severability">19. Severability</h2>
<p>If any provision of these terms is found to be invalid or unenforceable, the remaining provisions shall continue in full force and effect.</p>

<h2 id="contact">20. Contact Us</h2>
<p>For questions about these Terms of Service:</p>
<ul>
    <li><strong>Email:</strong> <a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a></li>
    <li><strong>Post:</strong> hOUR Timebank CLG, 21 Páirc Goodman, Skibbereen, Co. Cork, P81 AK26, Ireland</li>
</ul>
HTML;

// ============================================================================
// PRIVACY POLICY CONTENT
// ============================================================================

$privacyContent = <<<'HTML'
<h2 id="commitment">Our Commitment to Your Privacy</h2>
<p>hOUR Timebank Ireland is committed to protecting your privacy and ensuring your personal data is handled responsibly in accordance with the <strong>General Data Protection Regulation (GDPR)</strong> and the Irish <strong>Data Protection Acts 1988-2018</strong>.</p>
<p>This policy explains what information we collect, why we collect it, how we use it, and your rights regarding your personal data. We believe in <strong>transparency</strong> and <strong>user control</strong>.</p>

<h2 id="data-controller">1. Data Controller</h2>
<p>The data controller responsible for your personal data is:</p>
<ul>
    <li><strong>Legal Name:</strong> hOUR Timebank CLG (Company Limited by Guarantee)</li>
    <li><strong>Registered Business Name:</strong> Timebank Ireland</li>
    <li><strong>Charity Registration:</strong> RCN 20162023 (Charities Regulator)</li>
    <li><strong>Registered Address:</strong> 21 Páirc Goodman, Skibbereen, Co. Cork, P81 AK26, Ireland</li>
    <li><strong>Data Protection Contact:</strong> <a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a></li>
</ul>
<p>If you are a member of a Partner Organisation using our platform, that organisation may also be a data controller for data they collect directly from you.</p>

<h2 id="data-collection">2. Information We Collect</h2>
<p>We collect only the information necessary to provide our timebanking services:</p>
<table>
    <thead>
        <tr><th>Data Type</th><th>Purpose</th></tr>
    </thead>
    <tbody>
        <tr><td><strong>Account Information</strong></td><td>Name, email address, password (encrypted) — Required to create and manage your account</td></tr>
        <tr><td><strong>Profile Details</strong></td><td>Bio, skills, location, photo — Helps connect you with community members</td></tr>
        <tr><td><strong>Contact Information</strong></td><td>Phone number (optional), address — For arranging exchanges if you choose to share</td></tr>
        <tr><td><strong>Activity Data</strong></td><td>Exchanges, messages, time credits — Essential for platform functionality</td></tr>
        <tr><td><strong>Device Information</strong></td><td>Browser type, IP address, device type — Used for security and troubleshooting</td></tr>
    </tbody>
</table>

<h2 id="legal-basis">3. Legal Basis for Processing</h2>
<p>Under GDPR, we process your personal data based on the following legal grounds:</p>
<ul>
    <li><strong>Contract Performance:</strong> Processing necessary to provide our timebanking services to you</li>
    <li><strong>Consent:</strong> Where you have given explicit consent (e.g., marketing communications)</li>
    <li><strong>Legitimate Interests:</strong> Improving our services, preventing fraud, and ensuring platform security</li>
    <li><strong>Legal Obligation:</strong> Complying with Irish and EU legal requirements</li>
</ul>

<h2 id="data-usage">4. How We Use Your Data</h2>
<p>Your data is used exclusively for the following purposes:</p>
<ul>
    <li><strong>Service Delivery:</strong> Facilitating time exchanges and community connections</li>
    <li><strong>Communication:</strong> Sending important updates, notifications, and messages from other members</li>
    <li><strong>Security:</strong> Protecting your account and preventing fraud or abuse</li>
    <li><strong>Improvement:</strong> Analysing usage patterns to enhance platform features (anonymised)</li>
    <li><strong>Legal Compliance:</strong> Meeting regulatory requirements under Irish and EU law</li>
    <li><strong>Charitable Reporting:</strong> Aggregated, anonymised statistics for charity reporting purposes</li>
</ul>
<p><strong>We do not sell your personal data to third parties.</strong> Your information is never shared with advertisers or data brokers.</p>

<h2 id="data-sharing">5. Data Sharing</h2>
<p>We may share your data with:</p>
<ul>
    <li><strong>Other Members:</strong> Your profile information is visible to verified members to facilitate exchanges</li>
    <li><strong>Partner Organisations:</strong> If you join a specific community, that organisation's coordinators can see your membership details</li>
    <li><strong>Service Providers:</strong> Trusted providers who help us operate the platform (hosting, email delivery) under strict data processing agreements</li>
    <li><strong>Legal Authorities:</strong> When required by law or to protect our legal rights</li>
</ul>
<p>All service providers are bound by GDPR-compliant data processing agreements.</p>

<h2 id="data-protection">6. How We Protect Your Data</h2>
<p>We implement robust security measures:</p>
<ul>
    <li><strong>Encryption:</strong> All data is encrypted in transit (TLS/HTTPS) and at rest</li>
    <li><strong>Secure Passwords:</strong> Passwords are hashed using industry-standard algorithms</li>
    <li><strong>Access Controls:</strong> Strict internal policies limit who can access your data</li>
    <li><strong>Regular Audits:</strong> We conduct security reviews and update our practices accordingly</li>
    <li><strong>EU-Based Hosting:</strong> Your data is stored within the European Union</li>
</ul>

<h2 id="your-rights">7. Your GDPR Rights</h2>
<p>Under GDPR, you have the following rights:</p>
<ul>
    <li><strong>Right of Access:</strong> Request a copy of all personal data we hold about you</li>
    <li><strong>Right to Rectification:</strong> Correct any inaccurate or incomplete information</li>
    <li><strong>Right to Erasure:</strong> Request deletion of your data ("right to be forgotten")</li>
    <li><strong>Right to Restrict:</strong> Limit how we process your data in certain circumstances</li>
    <li><strong>Right to Portability:</strong> Receive your data in a structured, machine-readable format</li>
    <li><strong>Right to Object:</strong> Object to processing based on legitimate interests</li>
</ul>
<p>To exercise any of these rights, contact us at <a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a>. We will respond within one month.</p>

<h2 id="cookies">8. Cookies & Tracking</h2>
<p>We use cookies to enhance your experience:</p>
<ul>
    <li><strong>Essential Cookies:</strong> Required for login, security, and basic functionality</li>
    <li><strong>Preference Cookies:</strong> Remember your settings like theme preference</li>
    <li><strong>Analytics Cookies:</strong> Help us understand how people use the platform (anonymised)</li>
</ul>
<p>We do <strong>not</strong> use advertising cookies or tracking pixels.</p>

<h2 id="retention">9. Data Retention</h2>
<ul>
    <li><strong>Active Accounts:</strong> Data is kept while your account remains active</li>
    <li><strong>Inactive Accounts:</strong> Accounts inactive for 24 months may be anonymised after notice</li>
    <li><strong>Deleted Accounts:</strong> Personal data is removed within 30 days of deletion</li>
    <li><strong>Transaction Records:</strong> May be retained for up to 7 years for legal/charity reporting</li>
</ul>

<h2 id="international">10. International Data Transfers</h2>
<p>Your data is primarily stored within the European Union. If we need to transfer data outside the EU/EEA, we ensure appropriate safeguards (SCCs, adequacy decisions).</p>

<h2 id="children">11. Children's Privacy</h2>
<p>Our platform is intended for users aged <strong>18 years and older</strong>. We do not knowingly collect data from anyone under 18.</p>

<h2 id="complaints">12. Complaints</h2>
<p>You may lodge a complaint with the <strong>Data Protection Commission</strong>:</p>
<ul>
    <li><strong>Address:</strong> 21 Fitzwilliam Square South, Dublin 2, D02 RD28, Ireland</li>
    <li><strong>Website:</strong> <a href="https://www.dataprotection.ie" target="_blank">www.dataprotection.ie</a></li>
</ul>

<h2 id="changes">13. Changes to This Policy</h2>
<p>We may update this Privacy Policy to reflect changes in our practices. We will notify you via email or platform notification for significant changes.</p>

<h2 id="contact">Contact Us</h2>
<p>For privacy questions: <a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a></p>
HTML;

// ============================================================================
// INSERT TERMS VERSION
// ============================================================================

echo "Inserting Terms of Service v2.0 for hour-timebank (tenant 2)...\n";

try {
    // Get or create Terms document for tenant 2
    $stmt = Database::query(
        "SELECT id FROM legal_documents WHERE tenant_id = 2 AND document_type = 'terms'"
    );
    $termsDoc = $stmt->fetch();

    if (!$termsDoc) {
        echo "  Creating Terms document...\n";
        Database::query(
            "INSERT INTO legal_documents (tenant_id, document_type, title, slug, requires_acceptance, acceptance_required_for, notify_on_update, is_active, created_by)
             VALUES (2, 'terms', 'Terms of Service', 'terms', 1, 'registration', 1, 1, ?)",
            [$adminId]
        );
        $termsDocId = Database::lastInsertId();
    } else {
        $termsDocId = $termsDoc['id'];
    }

    // Check if version already exists
    $stmt = Database::query(
        "SELECT id FROM legal_document_versions WHERE document_id = ? AND version_number = '2.0'",
        [$termsDocId]
    );
    if ($stmt->fetch()) {
        echo "  Terms v2.0 already exists, skipping...\n";
    } else {
        // Insert version
        Database::query(
            "INSERT INTO legal_document_versions
             (document_id, version_number, version_label, content, content_plain, summary_of_changes, effective_date, is_draft, is_current, published_at, created_by, published_by)
             VALUES (?, '2.0', 'January 2026 Insurance Update', ?, ?, ?, '2026-01-25', 0, 1, NOW(), ?, ?)",
            [
                $termsDocId,
                $termsContent,
                strip_tags($termsContent),
                'Updated to address insurance company requirements: Added platform role distinction, transaction recording process, reviews and feedback policy, enhanced liability disclaimers.',
                $adminId,
                $adminId
            ]
        );
        $termsVersionId = Database::lastInsertId();

        // Update document to point to this version
        Database::query(
            "UPDATE legal_documents SET current_version_id = ? WHERE id = ?",
            [$termsVersionId, $termsDocId]
        );

        echo "  Created Terms v2.0 (version ID: {$termsVersionId})\n";
    }

} catch (Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

// ============================================================================
// INSERT PRIVACY VERSION
// ============================================================================

echo "\nInserting Privacy Policy v1.0 for hour-timebank (tenant 2)...\n";

try {
    // Get or create Privacy document for tenant 2
    $stmt = Database::query(
        "SELECT id FROM legal_documents WHERE tenant_id = 2 AND document_type = 'privacy'"
    );
    $privacyDoc = $stmt->fetch();

    if (!$privacyDoc) {
        echo "  Creating Privacy document...\n";
        Database::query(
            "INSERT INTO legal_documents (tenant_id, document_type, title, slug, requires_acceptance, acceptance_required_for, notify_on_update, is_active, created_by)
             VALUES (2, 'privacy', 'Privacy Policy', 'privacy', 1, 'registration', 1, 1, ?)",
            [$adminId]
        );
        $privacyDocId = Database::lastInsertId();
    } else {
        $privacyDocId = $privacyDoc['id'];
    }

    // Check if version already exists
    $stmt = Database::query(
        "SELECT id FROM legal_document_versions WHERE document_id = ? AND version_number = '1.0'",
        [$privacyDocId]
    );
    if ($stmt->fetch()) {
        echo "  Privacy v1.0 already exists, skipping...\n";
    } else {
        // Insert version
        Database::query(
            "INSERT INTO legal_document_versions
             (document_id, version_number, version_label, content, content_plain, summary_of_changes, effective_date, is_draft, is_current, published_at, created_by, published_by)
             VALUES (?, '1.0', 'Initial GDPR Compliant Version', ?, ?, ?, '2026-01-25', 0, 1, NOW(), ?, ?)",
            [
                $privacyDocId,
                $privacyContent,
                strip_tags($privacyContent),
                'Initial privacy policy for the legal documents versioning system.',
                $adminId,
                $adminId
            ]
        );
        $privacyVersionId = Database::lastInsertId();

        // Update document to point to this version
        Database::query(
            "UPDATE legal_documents SET current_version_id = ? WHERE id = ?",
            [$privacyVersionId, $privacyDocId]
        );

        echo "  Created Privacy v1.0 (version ID: {$privacyVersionId})\n";
    }

} catch (Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Seeding Complete ===\n";
echo "You can now access:\n";
echo "  - Admin: /admin/legal-documents\n";
echo "  - Public Terms: /terms (will show versioned content)\n";
echo "  - Public Privacy: /privacy (will show versioned content)\n";
