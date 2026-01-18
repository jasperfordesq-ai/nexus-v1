<?php
/**
 * Migration: Add Reviews & Ratings Help Article
 *
 * Adds a comprehensive help article explaining all the ways
 * members can leave reviews on the platform.
 *
 * Run: php scripts/migrations/add_reviews_help_article.php
 */

// Load autoloader
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} else {
    die("ERROR: Composer autoload not found. Run 'composer install' first.\n");
}

// Initialize database connection manually for CLI
use Nexus\Core\Database;

echo "Adding Reviews & Ratings help article...\n";

$db = Database::getConnection();

$slug = 'reviews-and-ratings-guide';
$title = 'Reviews & Ratings Guide';
$moduleTag = 'core';

// Check if article already exists
$stmt = $db->prepare("SELECT id FROM help_articles WHERE slug = ?");
$stmt->execute([$slug]);
$existing = $stmt->fetch();

if ($existing) {
    echo "Article already exists (ID: {$existing['id']}). Updating...\n";
    $sql = "UPDATE help_articles SET title = ?, content = ?, updated_at = NOW() WHERE slug = ?";
    $params = [$title, getContent(), $slug];
} else {
    echo "Creating new article...\n";
    $sql = "INSERT INTO help_articles (module_tag, title, slug, content, is_public, created_at) VALUES (?, ?, ?, ?, 1, NOW())";
    $params = [$moduleTag, $title, $slug, getContent()];
}

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo "SUCCESS: Reviews & Ratings help article " . ($existing ? "updated" : "created") . ".\n";

    // Get the article ID
    if ($existing) {
        $articleId = $existing['id'];
    } else {
        $articleId = $db->lastInsertId();
    }

    echo "Article ID: $articleId\n";
    echo "View at: /help/$slug\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nMigration complete!\n";

/**
 * Returns the HTML content for the help article
 */
function getContent(): string
{
    return '
<h2>Building Trust Through Reviews</h2>
<p>Reviews are a cornerstone of our community trust system. They help members make informed decisions, recognize valuable contributors, and maintain accountability in exchanges.</p>

<p>All reviews include a <strong>1-5 star rating</strong> and an <strong>optional comment</strong>.</p>

<hr>

<h2>5 Ways to Leave a Review</h2>

<h3>1. Automatic Prompt After Sending Credits</h3>
<p><strong>The most common way reviews happen.</strong></p>

<p>When you send time credits to another member, you\'ll automatically see a review page immediately after the transaction completes.</p>

<p><strong>How it works:</strong></p>
<ol>
    <li>Complete a credit transfer in your <a href="/wallet">Wallet</a></li>
    <li>You\'ll automatically see the "Rate Experience" page</li>
    <li>Select 1-5 stars</li>
    <li>Optionally add a comment about your experience</li>
    <li>Click "Submit Review" or skip by clicking "Return to Wallet"</li>
</ol>

<div style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1)); border-left: 4px solid #6366f1; padding: 16px; border-radius: 8px; margin: 20px 0;">
    <strong>💡 Tip:</strong> Take a moment to leave a review while the experience is fresh in your mind!
</div>

<hr>

<h3>2. Rate Button in Wallet History</h3>
<p><strong>Review past transactions anytime.</strong></p>

<p>Every transaction you\'ve sent has a "Rate" button in your wallet history, so you can leave a review after the fact.</p>

<p><strong>How to find it:</strong></p>
<ol>
    <li>Go to your <a href="/wallet">Wallet</a></li>
    <li>Scroll through your transaction history</li>
    <li>Click the ⭐ <strong>Rate</strong> button on any sent transaction</li>
    <li>Complete the review form</li>
</ol>

<hr>

<h3>3. Profile Page Review</h3>
<p><strong>Review any member directly from their profile.</strong></p>

<p>You can leave a review for any member by visiting their profile page. There are multiple ways to access this:</p>

<ul>
    <li><strong>Profile header:</strong> Click the "Leave Review" button near their name</li>
    <li><strong>Reviews section:</strong> Click "Write a Review" in their reviews area</li>
    <li><strong>Action menu (mobile):</strong> Tap the floating button and select "Write Review"</li>
</ul>

<p><strong>How it works:</strong></p>
<ol>
    <li>Visit any member\'s <a href="/members">profile</a></li>
    <li>Click one of the review buttons</li>
    <li>A review form will appear</li>
    <li>Select your star rating and add an optional comment</li>
    <li>Submit your review</li>
</ol>

<hr>

<h3>4. Group Member Reviews</h3>
<p><strong>Review fellow members within your groups/hubs.</strong></p>

<p>When you\'re a member of a group, you can review other members based on your interactions within that community.</p>

<p><strong>Requirements:</strong></p>
<ul>
    <li>You must be a member of the group</li>
    <li>The person you\'re reviewing must also be a group member</li>
    <li>You cannot review yourself</li>
</ul>

<p><strong>How to leave a group review:</strong></p>
<ol>
    <li>Go to your <a href="/groups">Group</a> page</li>
    <li>Click on the "Members" tab</li>
    <li>Find the member you want to review</li>
    <li>Click the ⭐ <strong>Review</strong> button on their card</li>
    <li>Complete the review form</li>
</ol>

<p>Group reviews appear in the group\'s "Reviews" tab and help other members see who\'s active and trustworthy within that community.</p>

<hr>

<h3>5. Volunteering Reviews</h3>
<p><strong>Review your volunteer experiences.</strong></p>

<p>After participating in volunteer opportunities, both volunteers and organizations can leave reviews.</p>

<p><strong>For Volunteers:</strong></p>
<ul>
    <li>Review the organization you volunteered with</li>
    <li>Share your experience to help other potential volunteers</li>
</ul>

<p><strong>For Organizations:</strong></p>
<ul>
    <li>Review volunteers who helped you</li>
    <li>Recognize great contributors</li>
</ul>

<p><strong>How to leave a volunteering review:</strong></p>
<ol>
    <li>Go to <a href="/volunteering/applications">My Applications</a> in the Volunteering section</li>
    <li>Find the completed opportunity</li>
    <li>Click the "Review" button</li>
    <li>Complete the review form</li>
</ol>

<hr>

<h2>The Review Form</h2>

<p>All review forms include:</p>

<h4>Star Rating (Required)</h4>
<ul>
    <li><strong>⭐ 1 Star</strong> - Poor experience</li>
    <li><strong>⭐⭐ 2 Stars</strong> - Fair experience</li>
    <li><strong>⭐⭐⭐ 3 Stars</strong> - Good experience</li>
    <li><strong>⭐⭐⭐⭐ 4 Stars</strong> - Great experience</li>
    <li><strong>⭐⭐⭐⭐⭐ 5 Stars</strong> - Excellent experience</li>
</ul>

<h4>Comment (Optional)</h4>
<p>Add details about your experience. This helps others understand the context of your rating.</p>

<hr>

<h2>Where Reviews Appear</h2>

<h3>On User Profiles</h3>
<ul>
    <li>Average rating displayed prominently</li>
    <li>Total review count shown</li>
    <li>Individual reviews listed with reviewer name, rating, comment, and date</li>
</ul>

<h3>In Groups</h3>
<ul>
    <li>Reviews tab shows all member reviews within that group</li>
    <li>Visible to group members and organizers</li>
</ul>

<h3>Reputation Indicators</h3>
<ul>
    <li>Profile badges may reflect review milestones</li>
    <li>High ratings contribute to your community reputation</li>
</ul>

<hr>

<h2>Best Practices for Leaving Reviews</h2>

<ol>
    <li><strong>Be specific</strong> - Mention what made the interaction positive or negative</li>
    <li><strong>Be fair</strong> - Rate based on the actual experience, not personal feelings</li>
    <li><strong>Be constructive</strong> - If giving a low rating, explain why helpfully</li>
    <li><strong>Be timely</strong> - Review soon after the interaction while details are fresh</li>
    <li><strong>Be honest</strong> - Your reviews help others make informed decisions</li>
</ol>

<hr>

<h2>Notifications</h2>

<p>When someone leaves you a review:</p>
<ul>
    <li>You\'ll receive an in-app notification</li>
    <li>The notification shows who reviewed you and their rating</li>
    <li>Click the notification to view the full review</li>
</ul>

<hr>

<h2>Quick Reference</h2>

<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
    <thead>
        <tr style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white;">
            <th style="padding: 12px; text-align: left; border-radius: 8px 0 0 0;">Method</th>
            <th style="padding: 12px; text-align: left;">Where to Find It</th>
            <th style="padding: 12px; text-align: left; border-radius: 0 8px 0 0;">Best For</th>
        </tr>
    </thead>
    <tbody>
        <tr style="background: rgba(99, 102, 241, 0.05);">
            <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">Auto-prompt</td>
            <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">After sending credits</td>
            <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">Transaction reviews</td>
        </tr>
        <tr>
            <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">Wallet Rate button</td>
            <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">Wallet → History</td>
            <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">Past transactions</td>
        </tr>
        <tr style="background: rgba(99, 102, 241, 0.05);">
            <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">Profile buttons</td>
            <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">Any member profile</td>
            <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">Direct reviews</td>
        </tr>
        <tr>
            <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">Group reviews</td>
            <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">Group → Members tab</td>
            <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">Hub interactions</td>
        </tr>
        <tr style="background: rgba(99, 102, 241, 0.05);">
            <td style="padding: 12px; border-radius: 0 0 0 8px;">Volunteering</td>
            <td style="padding: 12px;">My Applications</td>
            <td style="padding: 12px; border-radius: 0 0 8px 0;">Volunteer experiences</td>
        </tr>
    </tbody>
</table>

<hr>

<h2>Questions?</h2>
<p>If you have any issues leaving a review or questions about the review system, please <a href="/contact">contact our support team</a>.</p>
';
}
