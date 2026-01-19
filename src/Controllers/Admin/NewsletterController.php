<?php

namespace Nexus\Controllers\Admin;

use Nexus\Models\Newsletter;
use Nexus\Models\NewsletterSubscriber;
use Nexus\Models\NewsletterSegment;
use Nexus\Models\NewsletterAnalytics;
use Nexus\Models\NewsletterTemplate;
use Nexus\Models\NewsletterBounce;
use Nexus\Services\NewsletterService;
use Nexus\Core\View;
use Nexus\Core\Csrf;
use Nexus\Core\TenantContext;

class NewsletterController
{
    /**
     * List all newsletters
     */
    public function index()
    {
        $this->checkAdmin();

        $page = $_GET['page'] ?? 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $newsletters = Newsletter::getAll($limit, $offset);
        $total = Newsletter::count();
        $totalPages = ceil($total / $limit);

        View::render('admin/newsletters/index', [
            'pageTitle' => 'Newsletters',
            'newsletters' => $newsletters,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total
        ]);
    }

    /**
     * Show create form
     */
    public function create()
    {
        $this->checkAdmin();

        // DEBUG: Remove after fixing
        $debugTenantId = TenantContext::getId();
        $debugUserCount = \Nexus\Core\Database::query("SELECT COUNT(*) as c FROM users WHERE tenant_id = ?", [$debugTenantId])->fetch()['c'];
        error_log("Newsletter Debug - Session tenant_id: " . ($_SESSION['tenant_id'] ?? 'NOT SET') . ", TenantContext: $debugTenantId, Users in tenant: $debugUserCount");

        // Get audience counts for each target type
        $audienceCounts = [
            'all_members' => NewsletterService::getRecipientCount('all_members'),
            'subscribers_only' => NewsletterService::getRecipientCount('subscribers_only'),
            'both' => NewsletterService::getRecipientCount('both')
        ];

        // Get available segments
        $segments = [];
        try {
            $segments = NewsletterSegment::getAll(true);
        } catch (\Exception $e) {
            // Segment table might not exist yet
        }

        // Get available groups for targeting
        $groups = [];
        try {
            $groups = NewsletterSegment::getAvailableGroups();
        } catch (\Exception $e) {
            // Groups table might not exist
        }

        // Get saved templates for dropdown
        $savedTemplates = [];
        try {
            $savedTemplates = NewsletterTemplate::getAll(true, true);
        } catch (\Exception $e) {
            // Templates table might not exist
        }

        View::render('admin/newsletters/form', [
            'pageTitle' => 'Create Newsletter',
            'newsletter' => null,
            'audienceCounts' => $audienceCounts,
            'eligibleCount' => $audienceCounts['all_members'],
            'segments' => $segments,
            'groups' => $groups,
            'savedTemplates' => $savedTemplates
        ]);
    }

    /**
     * Store new newsletter
     */
    public function store()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $targetAudience = $_POST['target_audience'] ?? 'all_members';
        if (!in_array($targetAudience, ['all_members', 'subscribers_only', 'both'])) {
            $targetAudience = 'all_members';
        }

        $data = [
            'subject' => trim($_POST['subject'] ?? ''),
            'preview_text' => trim($_POST['preview_text'] ?? ''),
            'content' => $_POST['content'] ?? '',
            'status' => 'draft',
            'target_audience' => $targetAudience,
            'created_by' => $_SESSION['user_id']
        ];

        // Handle scheduling
        if (!empty($_POST['scheduled_at'])) {
            $data['scheduled_at'] = $_POST['scheduled_at'];
            $data['status'] = 'scheduled';
        }

        // Handle recurring schedule
        if (!empty($_POST['is_recurring'])) {
            $data['is_recurring'] = 1;
            $data['recurring_frequency'] = $_POST['recurring_frequency'] ?? null;
            $data['recurring_day'] = $_POST['recurring_day'] ?? null;
            $data['recurring_day_of_month'] = $_POST['recurring_day_of_month'] ?? null;
            $data['recurring_time'] = $_POST['recurring_time'] ?? '09:00';
            $data['recurring_end_date'] = !empty($_POST['recurring_end_date']) ? $_POST['recurring_end_date'] : null;
        }

        if (empty($data['subject'])) {
            $_SESSION['flash_error'] = 'Subject is required';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/create');
            exit;
        }

        try {
            $id = Newsletter::create($data);

            // Handle A/B testing fields after creation
            $abData = [];
            if (!empty($_POST['ab_test_enabled'])) {
                $abData['ab_test_enabled'] = 1;
                $abData['subject_b'] = trim($_POST['subject_b'] ?? '');
                $abData['ab_split_percentage'] = intval($_POST['ab_split_percentage'] ?? 50);
                $abData['ab_winner_metric'] = $_POST['ab_winner_metric'] ?? 'opens';
            }
            if (!empty($_POST['segment_id'])) {
                $abData['segment_id'] = intval($_POST['segment_id']);
            }

            // Handle direct targeting options
            if (!empty($_POST['target_counties'])) {
                $abData['target_counties'] = json_encode($_POST['target_counties']);
            }
            if (!empty($_POST['target_towns'])) {
                $towns = $_POST['target_towns'];
                // Add custom towns if provided
                if (!empty($_POST['custom_towns'])) {
                    $customTowns = array_map('trim', explode(',', $_POST['custom_towns']));
                    $towns = array_merge($towns, array_filter($customTowns));
                }
                $abData['target_towns'] = json_encode($towns);
            } elseif (!empty($_POST['custom_towns'])) {
                $customTowns = array_map('trim', explode(',', $_POST['custom_towns']));
                $abData['target_towns'] = json_encode(array_filter($customTowns));
            }
            if (!empty($_POST['target_groups'])) {
                $abData['target_groups'] = json_encode(array_map('intval', $_POST['target_groups']));
            }

            if (!empty($abData)) {
                Newsletter::update($id, $abData);
            }

            $_SESSION['flash_success'] = 'Newsletter created successfully';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/edit/' . $id);
        } catch (\Exception $e) {
            error_log("Newsletter create error: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Error creating newsletter';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/create');
        }
        exit;
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        $this->checkAdmin();

        $newsletter = Newsletter::findById($id);
        if (!$newsletter) {
            http_response_code(404);
            echo "Newsletter not found";
            exit;
        }

        // Get audience counts for each target type
        $audienceCounts = [
            'all_members' => NewsletterService::getRecipientCount('all_members'),
            'subscribers_only' => NewsletterService::getRecipientCount('subscribers_only'),
            'both' => NewsletterService::getRecipientCount('both')
        ];

        // Get current audience count
        $targetAudience = $newsletter['target_audience'] ?? 'all_members';
        $eligibleCount = $audienceCounts[$targetAudience] ?? $audienceCounts['all_members'];

        // Get available segments
        $segments = [];
        try {
            $segments = NewsletterSegment::getAll(true);
        } catch (\Exception $e) {
            // Segment table might not exist yet
        }

        // Get available groups for targeting
        $groups = [];
        try {
            $groups = NewsletterSegment::getAvailableGroups();
        } catch (\Exception $e) {
            // Groups table might not exist
        }

        View::render('admin/newsletters/form', [
            'pageTitle' => 'Edit Newsletter',
            'newsletter' => $newsletter,
            'audienceCounts' => $audienceCounts,
            'eligibleCount' => $eligibleCount,
            'segments' => $segments,
            'groups' => $groups
        ]);
    }

    /**
     * Update newsletter
     */
    public function update($id)
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $newsletter = Newsletter::findById($id);
        if (!$newsletter) {
            http_response_code(404);
            echo "Newsletter not found";
            exit;
        }

        // Don't allow editing sent newsletters
        if ($newsletter['status'] === 'sent') {
            $_SESSION['flash_error'] = 'Cannot edit a sent newsletter';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters');
            exit;
        }

        $targetAudience = $_POST['target_audience'] ?? 'all_members';
        if (!in_array($targetAudience, ['all_members', 'subscribers_only', 'both'])) {
            $targetAudience = 'all_members';
        }

        $data = [
            'subject' => trim($_POST['subject'] ?? ''),
            'preview_text' => trim($_POST['preview_text'] ?? ''),
            'content' => $_POST['content'] ?? '',
            'target_audience' => $targetAudience
        ];

        // Handle scheduling
        if (!empty($_POST['scheduled_at'])) {
            $data['scheduled_at'] = $_POST['scheduled_at'];
            $data['status'] = 'scheduled';
        } else {
            $data['status'] = 'draft';
            $data['scheduled_at'] = null;
        }

        // Handle A/B testing
        $data['ab_test_enabled'] = !empty($_POST['ab_test_enabled']) ? 1 : 0;
        if ($data['ab_test_enabled']) {
            $data['subject_b'] = trim($_POST['subject_b'] ?? '');
            $data['ab_split_percentage'] = intval($_POST['ab_split_percentage'] ?? 50);
            $data['ab_winner_metric'] = $_POST['ab_winner_metric'] ?? 'opens';
        } else {
            $data['subject_b'] = null;
        }

        // Handle segment
        $data['segment_id'] = !empty($_POST['segment_id']) ? intval($_POST['segment_id']) : null;

        // Handle direct targeting options
        if (!empty($_POST['target_counties'])) {
            $data['target_counties'] = json_encode($_POST['target_counties']);
        } else {
            $data['target_counties'] = null;
        }

        if (!empty($_POST['target_towns'])) {
            $towns = $_POST['target_towns'];
            // Add custom towns if provided
            if (!empty($_POST['custom_towns'])) {
                $customTowns = array_map('trim', explode(',', $_POST['custom_towns']));
                $towns = array_merge($towns, array_filter($customTowns));
            }
            $data['target_towns'] = json_encode($towns);
        } elseif (!empty($_POST['custom_towns'])) {
            $customTowns = array_map('trim', explode(',', $_POST['custom_towns']));
            $data['target_towns'] = json_encode(array_filter($customTowns));
        } else {
            $data['target_towns'] = null;
        }

        if (!empty($_POST['target_groups'])) {
            $data['target_groups'] = json_encode(array_map('intval', $_POST['target_groups']));
        } else {
            $data['target_groups'] = null;
        }

        // Handle recurring schedule
        $data['is_recurring'] = !empty($_POST['is_recurring']) ? 1 : 0;
        if ($data['is_recurring']) {
            $data['recurring_frequency'] = $_POST['recurring_frequency'] ?? null;
            $data['recurring_day'] = $_POST['recurring_day'] ?? null;
            $data['recurring_day_of_month'] = $_POST['recurring_day_of_month'] ?? null;
            $data['recurring_time'] = $_POST['recurring_time'] ?? '09:00';
            $data['recurring_end_date'] = !empty($_POST['recurring_end_date']) ? $_POST['recurring_end_date'] : null;
        } else {
            $data['recurring_frequency'] = null;
            $data['recurring_day'] = null;
            $data['recurring_day_of_month'] = null;
            $data['recurring_time'] = null;
            $data['recurring_end_date'] = null;
        }

        if (empty($data['subject'])) {
            $_SESSION['flash_error'] = 'Subject is required';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/edit/' . $id);
            exit;
        }

        try {
            Newsletter::update($id, $data);
            $_SESSION['flash_success'] = 'Newsletter updated successfully';
        } catch (\Exception $e) {
            error_log("Newsletter update error: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Error updating newsletter';
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/edit/' . $id);
        exit;
    }

    /**
     * Preview newsletter in browser
     */
    public function preview($id)
    {
        $this->checkAdmin();

        $newsletter = Newsletter::findById($id);
        if (!$newsletter) {
            http_response_code(404);
            echo "Newsletter not found";
            exit;
        }

        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Project NEXUS';

        // Render the email HTML
        $html = NewsletterService::renderEmail($newsletter, $tenantName);

        // Output directly for preview
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    /**
     * Send newsletter now
     */
    public function send($id)
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $newsletter = Newsletter::findById($id);
        if (!$newsletter) {
            $_SESSION['flash_error'] = 'Newsletter not found';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters');
            exit;
        }

        if ($newsletter['status'] === 'sent') {
            $_SESSION['flash_error'] = 'Newsletter already sent';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters');
            exit;
        }

        if (empty(trim($newsletter['content']))) {
            $_SESSION['flash_error'] = 'Cannot send empty newsletter';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/edit/' . $id);
            exit;
        }

        // Get target audience from form or use newsletter's saved value
        $targetAudience = $_POST['target_audience'] ?? ($newsletter['target_audience'] ?? 'all_members');
        if (!in_array($targetAudience, ['all_members', 'subscribers_only', 'both'])) {
            $targetAudience = 'all_members';
        }

        // Get segment if set
        $segmentId = $newsletter['segment_id'] ?? null;

        try {
            $count = NewsletterService::sendNow($id, $targetAudience, $segmentId);
            $message = "Newsletter sent to $count recipients";
            if (!empty($newsletter['ab_test_enabled']) && !empty($newsletter['subject_b'])) {
                $message .= " (A/B test enabled)";
            }
            $_SESSION['flash_success'] = $message;
        } catch (\Exception $e) {
            error_log("Newsletter send error: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Error sending newsletter: ' . $e->getMessage();
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters');
        exit;
    }

    /**
     * Send newsletter directly (called after save via JS)
     * Uses saved data from newsletter record
     */
    public function sendDirect($id)
    {
        $this->checkAdmin();

        $newsletter = Newsletter::findById($id);
        if (!$newsletter) {
            $_SESSION['flash_error'] = 'Newsletter not found';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters');
            exit;
        }

        if ($newsletter['status'] === 'sent') {
            $_SESSION['flash_error'] = 'Newsletter already sent';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters');
            exit;
        }

        if (empty(trim($newsletter['content']))) {
            $_SESSION['flash_error'] = 'Cannot send empty newsletter';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/edit/' . $id);
            exit;
        }

        // Use saved values from newsletter
        $targetAudience = $newsletter['target_audience'] ?? 'all_members';
        $segmentId = $newsletter['segment_id'] ?? null;

        try {
            $count = NewsletterService::sendNow($id, $targetAudience, $segmentId);
            $message = "Newsletter sent to $count recipients";
            if (!empty($newsletter['ab_test_enabled']) && !empty($newsletter['subject_b'])) {
                $message .= " (A/B test enabled)";
            }
            $_SESSION['flash_success'] = $message;
        } catch (\Exception $e) {
            error_log("Newsletter send error: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Error sending newsletter: ' . $e->getMessage();
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters');
        exit;
    }

    /**
     * Send test email to current admin
     */
    public function sendTest($id)
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $newsletter = Newsletter::findById($id);
        if (!$newsletter) {
            $this->jsonResponse(['success' => false, 'error' => 'Newsletter not found']);
            return;
        }

        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Project NEXUS';

        // Get current user's email
        $user = \Nexus\Models\User::findById($_SESSION['user_id']);
        if (!$user || empty($user['email'])) {
            $this->jsonResponse(['success' => false, 'error' => 'No email address found']);
            return;
        }

        try {
            $mailer = new \Nexus\Core\Mailer();
            $html = NewsletterService::renderEmail($newsletter, $tenantName);
            $subject = "[TEST] " . $newsletter['subject'];

            $success = $mailer->send($user['email'], $subject, $html);

            if ($success) {
                $this->jsonResponse(['success' => true, 'message' => 'Test email sent to ' . $user['email']]);
            } else {
                $this->jsonResponse(['success' => false, 'error' => 'Failed to send email']);
            }
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Delete newsletter
     */
    public function delete()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $id = $_POST['id'] ?? null;
        error_log("Newsletter delete called. ID from POST: " . var_export($id, true));

        if (!$id) {
            $_SESSION['flash_error'] = 'Invalid newsletter ID';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters');
            exit;
        }

        $newsletter = Newsletter::findById($id);
        error_log("Newsletter found: " . var_export($newsletter ? $newsletter['id'] : 'NOT FOUND', true));

        if (!$newsletter) {
            $_SESSION['flash_error'] = 'Newsletter not found';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters');
            exit;
        }

        // Don't allow deleting sent newsletters (keep for records)
        if ($newsletter['status'] === 'sent') {
            $_SESSION['flash_error'] = 'Cannot delete sent newsletters';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters');
            exit;
        }

        try {
            Newsletter::delete($id);
            $_SESSION['flash_success'] = 'Newsletter "' . $newsletter['subject'] . '" deleted successfully';
            error_log("Newsletter delete SUCCESS for ID: $id");
        } catch (\Exception $e) {
            error_log("Newsletter delete error: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Error deleting newsletter: ' . $e->getMessage();
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters');
        exit;
    }

    /**
     * Duplicate a newsletter
     */
    public function duplicate($id)
    {
        $this->checkAdmin();

        $newsletter = Newsletter::findById($id);
        if (!$newsletter) {
            $_SESSION['flash_error'] = 'Newsletter not found';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters');
            exit;
        }

        try {
            $newId = Newsletter::create([
                'subject' => $newsletter['subject'] . ' (Copy)',
                'preview_text' => $newsletter['preview_text'],
                'content' => $newsletter['content'],
                'status' => 'draft',
                'created_by' => $_SESSION['user_id']
            ]);

            $_SESSION['flash_success'] = 'Newsletter duplicated';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/edit/' . $newId);
        } catch (\Exception $e) {
            error_log("Newsletter duplicate error: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Error duplicating newsletter';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters');
        }
        exit;
    }

    /**
     * View send statistics
     */
    public function stats($id)
    {
        $this->checkAdmin();

        $newsletter = Newsletter::findById($id);
        if (!$newsletter) {
            http_response_code(404);
            echo "Newsletter not found";
            exit;
        }

        $stats = NewsletterService::getStats($id);

        // Get detailed analytics if newsletter was sent
        $analytics = [];
        $abResults = null;
        if ($newsletter['status'] === 'sent') {
            try {
                $analytics = NewsletterAnalytics::getDetails($id);

                // Get A/B test results if applicable
                if (!empty($newsletter['ab_test_enabled']) && !empty($newsletter['subject_b'])) {
                    $abResults = NewsletterService::getABTestResults($id);
                }
            } catch (\Exception $e) {
                // Analytics tables might not exist yet
                error_log("Analytics error: " . $e->getMessage());
            }
        }

        View::render('admin/newsletters/stats', [
            'pageTitle' => 'Newsletter Analytics',
            'newsletter' => $newsletter,
            'stats' => $stats,
            'analytics' => $analytics,
            'abResults' => $abResults
        ]);
    }

    /**
     * Aggregate analytics dashboard across all newsletters
     */
    public function analytics()
    {
        $this->checkAdmin();

        // Get all sent newsletters with stats
        $newsletters = Newsletter::getAllSent();

        // Calculate aggregate stats
        $totals = [
            'newsletters_sent' => 0,
            'total_sent' => 0,
            'total_failed' => 0,
            'total_opens' => 0,
            'unique_opens' => 0,
            'total_clicks' => 0,
            'unique_clicks' => 0
        ];

        $monthlyStats = [];
        $topPerformers = [];

        foreach ($newsletters as $newsletter) {
            $totals['newsletters_sent']++;
            $totals['total_sent'] += $newsletter['total_sent'] ?? 0;
            $totals['total_failed'] += $newsletter['total_failed'] ?? 0;
            $totals['total_opens'] += $newsletter['total_opens'] ?? 0;
            $totals['unique_opens'] += $newsletter['unique_opens'] ?? 0;
            $totals['total_clicks'] += $newsletter['total_clicks'] ?? 0;
            $totals['unique_clicks'] += $newsletter['unique_clicks'] ?? 0;

            // Group by month
            $month = date('Y-m', strtotime($newsletter['sent_at']));
            if (!isset($monthlyStats[$month])) {
                $monthlyStats[$month] = [
                    'month' => $month,
                    'newsletters' => 0,
                    'sent' => 0,
                    'opens' => 0,
                    'clicks' => 0
                ];
            }
            $monthlyStats[$month]['newsletters']++;
            $monthlyStats[$month]['sent'] += $newsletter['total_sent'] ?? 0;
            $monthlyStats[$month]['opens'] += $newsletter['unique_opens'] ?? 0;
            $monthlyStats[$month]['clicks'] += $newsletter['unique_clicks'] ?? 0;

            // Track top performers by open rate
            if (($newsletter['total_sent'] ?? 0) >= 10) {
                $openRate = $newsletter['total_sent'] > 0
                    ? round(($newsletter['unique_opens'] / $newsletter['total_sent']) * 100, 1)
                    : 0;
                $clickRate = $newsletter['total_sent'] > 0
                    ? round(($newsletter['unique_clicks'] / $newsletter['total_sent']) * 100, 1)
                    : 0;

                $topPerformers[] = [
                    'id' => $newsletter['id'],
                    'subject' => $newsletter['subject'],
                    'sent_at' => $newsletter['sent_at'],
                    'total_sent' => $newsletter['total_sent'],
                    'open_rate' => $openRate,
                    'click_rate' => $clickRate
                ];
            }
        }

        // Sort monthly stats by month
        ksort($monthlyStats);
        $monthlyStats = array_values($monthlyStats);

        // Sort top performers by open rate, take top 10
        usort($topPerformers, function ($a, $b) {
            return $b['open_rate'] <=> $a['open_rate'];
        });
        $topPerformers = array_slice($topPerformers, 0, 10);

        // Calculate average rates
        $avgOpenRate = $totals['total_sent'] > 0
            ? round(($totals['unique_opens'] / $totals['total_sent']) * 100, 1)
            : 0;
        $avgClickRate = $totals['total_sent'] > 0
            ? round(($totals['unique_clicks'] / $totals['total_sent']) * 100, 1)
            : 0;

        View::render('admin/newsletters/analytics', [
            'pageTitle' => 'Newsletter Analytics Overview',
            'totals' => $totals,
            'monthlyStats' => $monthlyStats,
            'topPerformers' => $topPerformers,
            'avgOpenRate' => $avgOpenRate,
            'avgClickRate' => $avgClickRate
        ]);
    }

    /**
     * Select A/B test winner
     */
    public function selectWinner($id)
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $winner = $_POST['winner'] ?? null;

        try {
            NewsletterService::selectABWinner($id, $winner);
            $_SESSION['flash_success'] = "Subject $winner selected as the winner";
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/stats/' . $id);
        exit;
    }

    // =========================================================================
    // SUBSCRIBER MANAGEMENT
    // =========================================================================

    /**
     * List all subscribers
     */
    public function subscribers()
    {
        $this->checkAdmin();

        $status = $_GET['status'] ?? null;
        $page = $_GET['page'] ?? 1;
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $subscribers = NewsletterSubscriber::getAll($limit, $offset, $status);
        $stats = NewsletterSubscriber::getStats();
        $total = $stats['total'] ?? 0;
        $totalPages = ceil($total / $limit);

        View::render('admin/newsletters/subscribers', [
            'pageTitle' => 'Newsletter Subscribers',
            'subscribers' => $subscribers,
            'stats' => $stats,
            'currentStatus' => $status,
            'page' => $page,
            'totalPages' => $totalPages
        ]);
    }

    /**
     * Add a subscriber manually
     */
    public function addSubscriber()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $email = trim($_POST['email'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Please enter a valid email address';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/subscribers');
            exit;
        }

        $existing = NewsletterSubscriber::findByEmail($email);
        if ($existing && $existing['status'] === 'active') {
            $_SESSION['flash_error'] = 'This email is already subscribed';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/subscribers');
            exit;
        }

        try {
            NewsletterSubscriber::createConfirmed($email, $firstName, $lastName, 'manual');
            $_SESSION['flash_success'] = 'Subscriber added successfully';
        } catch (\Exception $e) {
            error_log("Add subscriber error: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Error adding subscriber';
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/subscribers');
        exit;
    }

    /**
     * Delete a subscriber
     */
    public function deleteSubscriber()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $id = $_POST['id'] ?? null;
        if ($id) {
            NewsletterSubscriber::delete($id);
            $_SESSION['flash_success'] = 'Subscriber removed';
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/subscribers');
        exit;
    }

    /**
     * Sync existing members to subscriber list
     */
    public function syncMembers()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        try {
            $result = NewsletterSubscriber::syncMembersWithStats();

            if ($result['synced'] > 0) {
                $_SESSION['flash_success'] = "Synced {$result['synced']} new members to subscriber list";
            } elseif ($result['total_users'] == 0) {
                $_SESSION['flash_error'] = "No platform members found to sync";
            } elseif ($result['eligible'] == 0) {
                $_SESSION['flash_success'] = "All {$result['total_users']} members are already subscribed";
            } else {
                $_SESSION['flash_success'] = "All eligible members are already subscribed ({$result['already_subscribed']} members)";
            }
        } catch (\Exception $e) {
            error_log("Sync members error: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Error syncing members: ' . $e->getMessage();
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/subscribers');
        exit;
    }

    /**
     * Export subscribers as CSV
     */
    public function exportSubscribers()
    {
        $this->checkAdmin();

        $subscribers = NewsletterSubscriber::export();

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="newsletter_subscribers_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Email', 'First Name', 'Last Name', 'Status', 'Source', 'Subscribed At']);

        foreach ($subscribers as $sub) {
            fputcsv($output, [
                $sub['email'],
                $sub['first_name'] ?? '',
                $sub['last_name'] ?? '',
                $sub['status'],
                $sub['source'],
                $sub['confirmed_at'] ?? $sub['created_at']
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Import subscribers from CSV
     */
    public function importSubscribers()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'Please upload a valid CSV file';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/subscribers');
            exit;
        }

        $file = $_FILES['csv_file']['tmp_name'];
        $subscribers = [];

        if (($handle = fopen($file, 'r')) !== false) {
            $header = fgetcsv($handle); // Skip header row

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) >= 1 && filter_var($row[0], FILTER_VALIDATE_EMAIL)) {
                    $subscribers[] = [
                        'email' => $row[0],
                        'first_name' => $row[1] ?? null,
                        'last_name' => $row[2] ?? null
                    ];
                }
            }
            fclose($handle);
        }

        if (empty($subscribers)) {
            $_SESSION['flash_error'] = 'No valid email addresses found in file';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/subscribers');
            exit;
        }

        $result = NewsletterSubscriber::import($subscribers);
        $_SESSION['flash_success'] = "Imported {$result['imported']} subscribers ({$result['skipped']} skipped)";

        header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/subscribers');
        exit;
    }

    // =========================================================================
    // SEGMENT MANAGEMENT
    // =========================================================================

    /**
     * List all segments
     */
    public function segments()
    {
        $this->checkAdmin();

        $segments = NewsletterSegment::getAll(false);

        // Add member counts
        foreach ($segments as &$segment) {
            try {
                $segment['member_count'] = NewsletterSegment::countMatchingUsers($segment['id']);
            } catch (\Exception $e) {
                $segment['member_count'] = 0;
            }
        }

        View::render('admin/newsletters/segments', [
            'pageTitle' => 'Newsletter Segments',
            'segments' => $segments
        ]);
    }

    /**
     * Show create segment form
     */
    public function createSegment()
    {
        $this->checkAdmin();

        $fields = NewsletterSegment::getAvailableFields();
        $groups = NewsletterSegment::getAvailableGroups();
        $counties = NewsletterSegment::getIrishCounties();
        $towns = NewsletterSegment::getIrishTowns();

        View::render('admin/newsletters/segment-form', [
            'pageTitle' => 'Create Segment',
            'segment' => null,
            'fields' => $fields,
            'groups' => $groups,
            'counties' => $counties,
            'towns' => $towns
        ]);
    }

    /**
     * Store new segment
     */
    public function storeSegment()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'rules' => $this->parseSegmentRules($_POST),
            'created_by' => $_SESSION['user_id'] ?? null
        ];

        if (empty($data['name'])) {
            $_SESSION['flash_error'] = 'Segment name is required';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/segments/create');
            exit;
        }

        try {
            $id = NewsletterSegment::create($data);

            // Clear smart suggestions cache since member counts may have changed
            \Nexus\Services\SmartSegmentSuggestionService::clearCache();

            $_SESSION['flash_success'] = 'Segment created successfully';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/segments');
        } catch (\Exception $e) {
            error_log("Segment create error: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Error creating segment';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/segments/create');
        }
        exit;
    }

    /**
     * Show edit segment form
     */
    public function editSegment($id)
    {
        $this->checkAdmin();

        $segment = NewsletterSegment::findById($id);
        if (!$segment) {
            $_SESSION['flash_error'] = 'Segment not found';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/segments');
            exit;
        }

        $fields = NewsletterSegment::getAvailableFields();
        $groups = NewsletterSegment::getAvailableGroups();
        $counties = NewsletterSegment::getIrishCounties();
        $towns = NewsletterSegment::getIrishTowns();

        View::render('admin/newsletters/segment-form', [
            'pageTitle' => 'Edit Segment',
            'segment' => $segment,
            'fields' => $fields,
            'groups' => $groups,
            'counties' => $counties,
            'towns' => $towns
        ]);
    }

    /**
     * Update segment
     */
    public function updateSegment($id)
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $segment = NewsletterSegment::findById($id);
        if (!$segment) {
            $_SESSION['flash_error'] = 'Segment not found';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/segments');
            exit;
        }

        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'rules' => $this->parseSegmentRules($_POST),
            'is_active' => !empty($_POST['is_active']) ? 1 : 0
        ];

        if (empty($data['name'])) {
            $_SESSION['flash_error'] = 'Segment name is required';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/segments/edit/' . $id);
            exit;
        }

        try {
            NewsletterSegment::update($id, $data);

            // Clear smart suggestions cache since member counts may have changed
            \Nexus\Services\SmartSegmentSuggestionService::clearCache();

            $_SESSION['flash_success'] = 'Segment updated successfully';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/segments');
        } catch (\Exception $e) {
            error_log("Segment update error: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Error updating segment';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/segments/edit/' . $id);
        }
        exit;
    }

    /**
     * Delete segment
     */
    public function deleteSegment()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $id = $_POST['id'] ?? null;
        if (!$id) {
            $_SESSION['flash_error'] = 'Invalid segment';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/segments');
            exit;
        }

        try {
            NewsletterSegment::delete($id);

            // Clear smart suggestions cache
            \Nexus\Services\SmartSegmentSuggestionService::clearCache();

            $_SESSION['flash_success'] = 'Segment deleted';
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error deleting segment';
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/segments');
        exit;
    }

    /**
     * Preview segment member count (AJAX)
     */
    public function previewSegment()
    {
        $this->checkAdmin();

        $rules = $this->parseSegmentRules($_POST);

        try {
            $count = NewsletterSegment::previewRules($rules);
            $this->jsonResponse(['success' => true, 'count' => $count]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Parse segment rules from POST data
     */
    private function parseSegmentRules($post)
    {
        $rules = [
            'match' => $post['match'] ?? 'all',
            'conditions' => []
        ];

        $fields = $post['rule_field'] ?? [];
        $operators = $post['rule_operator'] ?? [];
        $values = $post['rule_value'] ?? [];

        for ($i = 0; $i < count($fields); $i++) {
            $field = $fields[$i] ?? '';
            if (empty($field)) continue;

            $condition = [
                'field' => $field,
                'operator' => $operators[$i] ?? 'equals',
                'value' => $values[$i] ?? ''
            ];

            // Handle special field types
            if ($field === 'geo_radius') {
                $condition['value'] = [
                    'lat' => floatval($post['geo_lat'][$i] ?? 0),
                    'lng' => floatval($post['geo_lng'][$i] ?? 0),
                    'radius_km' => intval($post['geo_radius'][$i] ?? 50)
                ];
            } elseif ($field === 'county') {
                // County can be multi-select
                $condition['value'] = $post['county_value'][$i] ?? [];
            } elseif ($field === 'town') {
                // Towns can be multi-select and/or comma-separated custom input
                $towns = $post['town_value'][$i] ?? [];
                // Also check for custom towns input
                $customTowns = trim($post['town_custom'][$i] ?? '');
                if (!empty($customTowns)) {
                    $customTownsArray = array_map('trim', explode(',', $customTowns));
                    $towns = array_merge($towns, array_filter($customTownsArray));
                }
                $condition['value'] = array_unique($towns);
            } elseif ($field === 'group_membership') {
                // Groups can be multi-select
                $condition['value'] = $post['group_value'][$i] ?? [];
            }

            $rules['conditions'][] = $condition;
        }

        return $rules;
    }

    // =========================================================================
    // SMART SEGMENT SUGGESTIONS (AJAX)
    // =========================================================================

    /**
     * Get smart segment suggestions based on member data analysis (AJAX)
     */
    public function getSmartSuggestions()
    {
        $this->checkAdmin();

        try {
            $suggestions = \Nexus\Services\SmartSegmentSuggestionService::getSuggestions();
            $this->jsonResponse([
                'success' => true,
                'suggestions' => $suggestions
            ]);
        } catch (\Exception $e) {
            error_log("Smart suggestions error: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to load suggestions'
            ]);
        }
    }

    /**
     * Create a segment from a smart suggestion (AJAX)
     */
    public function createFromSuggestion()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $suggestionId = $_POST['suggestion_id'] ?? '';

        if (empty($suggestionId)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'No suggestion specified'
            ]);
            return;
        }

        try {
            $suggestion = \Nexus\Services\SmartSegmentSuggestionService::getSuggestionById($suggestionId);

            if (!$suggestion) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Suggestion not found'
                ]);
                return;
            }

            $segmentId = NewsletterSegment::create([
                'name' => $suggestion['name'],
                'description' => $suggestion['description'],
                'rules' => $suggestion['rules'],
                'created_by' => $_SESSION['user_id'] ?? null
            ]);

            // Clear smart suggestions cache
            \Nexus\Services\SmartSegmentSuggestionService::clearCache();

            $this->jsonResponse([
                'success' => true,
                'segment_id' => $segmentId,
                'redirect' => TenantContext::getBasePath() . '/admin/newsletters/segments/edit/' . $segmentId
            ]);
        } catch (\Exception $e) {
            error_log("Create from suggestion error: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to create segment'
            ]);
        }
    }

    // =========================================================================
    // LIVE RECIPIENT COUNT & PREVIEW (AJAX)
    // =========================================================================

    /**
     * Get live recipient count based on current targeting filters (AJAX)
     */
    public function getRecipientCount()
    {
        $this->checkAdmin();

        $targetAudience = $_POST['target_audience'] ?? 'all_members';
        if (!in_array($targetAudience, ['all_members', 'subscribers_only', 'both'])) {
            $targetAudience = 'all_members';
        }

        // Build a mock newsletter array with targeting data
        $mockNewsletter = [
            'target_counties' => !empty($_POST['target_counties']) ? json_encode($_POST['target_counties']) : null,
            'target_towns' => null,
            'target_groups' => !empty($_POST['target_groups']) ? json_encode(array_map('intval', $_POST['target_groups'])) : null
        ];

        // Handle towns (including custom)
        if (!empty($_POST['target_towns'])) {
            $towns = $_POST['target_towns'];
            if (!empty($_POST['custom_towns'])) {
                $customTowns = array_map('trim', explode(',', $_POST['custom_towns']));
                $towns = array_merge($towns, array_filter($customTowns));
            }
            $mockNewsletter['target_towns'] = json_encode($towns);
        } elseif (!empty($_POST['custom_towns'])) {
            $customTowns = array_map('trim', explode(',', $_POST['custom_towns']));
            $mockNewsletter['target_towns'] = json_encode(array_filter($customTowns));
        }

        // Check if any targeting is active
        $hasTargeting = !empty($mockNewsletter['target_counties']) ||
                        !empty($mockNewsletter['target_towns']) ||
                        !empty($mockNewsletter['target_groups']);

        try {
            if ($hasTargeting) {
                $recipients = NewsletterService::getFilteredRecipients($mockNewsletter, $targetAudience);
                $count = count($recipients);
            } else {
                $count = NewsletterService::getRecipientCount($targetAudience);
            }

            $this->jsonResponse([
                'success' => true,
                'count' => $count,
                'filtered' => $hasTargeting
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'count' => 0
            ]);
        }
    }

    /**
     * Preview recipients that would receive the newsletter (AJAX)
     */
    public function previewRecipients()
    {
        $this->checkAdmin();

        $targetAudience = $_POST['target_audience'] ?? 'all_members';
        if (!in_array($targetAudience, ['all_members', 'subscribers_only', 'both'])) {
            $targetAudience = 'all_members';
        }

        $limit = intval($_POST['limit'] ?? 50);
        $offset = intval($_POST['offset'] ?? 0);

        // Build a mock newsletter array with targeting data
        $mockNewsletter = [
            'target_counties' => !empty($_POST['target_counties']) ? json_encode($_POST['target_counties']) : null,
            'target_towns' => null,
            'target_groups' => !empty($_POST['target_groups']) ? json_encode(array_map('intval', $_POST['target_groups'])) : null
        ];

        // Handle towns (including custom)
        if (!empty($_POST['target_towns'])) {
            $towns = $_POST['target_towns'];
            if (!empty($_POST['custom_towns'])) {
                $customTowns = array_map('trim', explode(',', $_POST['custom_towns']));
                $towns = array_merge($towns, array_filter($customTowns));
            }
            $mockNewsletter['target_towns'] = json_encode($towns);
        } elseif (!empty($_POST['custom_towns'])) {
            $customTowns = array_map('trim', explode(',', $_POST['custom_towns']));
            $mockNewsletter['target_towns'] = json_encode(array_filter($customTowns));
        }

        // Check if any targeting is active
        $hasTargeting = !empty($mockNewsletter['target_counties']) ||
                        !empty($mockNewsletter['target_towns']) ||
                        !empty($mockNewsletter['target_groups']);

        try {
            if ($hasTargeting) {
                $allRecipients = NewsletterService::getFilteredRecipients($mockNewsletter, $targetAudience);
            } else {
                $allRecipients = NewsletterService::getRecipients($targetAudience);
            }

            $total = count($allRecipients);
            $recipients = array_slice($allRecipients, $offset, $limit);

            // Format for display
            $formatted = array_map(function($r) {
                return [
                    'email' => $r['email'] ?? '',
                    'name' => $r['name'] ?? (($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
                    'type' => !empty($r['user_id']) ? 'member' : 'subscriber'
                ];
            }, $recipients);

            $this->jsonResponse([
                'success' => true,
                'recipients' => $formatted,
                'total' => $total,
                'showing' => count($formatted),
                'offset' => $offset,
                'filtered' => $hasTargeting
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'recipients' => []
            ]);
        }
    }

    // --- Helper Methods ---

    /**
     * Database diagnostics and repair tool
     * GET /admin/newsletters/diagnostics
     */
    public function diagnostics()
    {
        $this->checkAdmin();

        $tenantId = TenantContext::getId();
        $db = \Nexus\Core\Database::getInstance();

        $diagnostics = [
            'tables' => [],
            'newsletters' => [],
            'issues' => [],
            'fixes_available' => []
        ];

        // Check required tables exist
        $requiredTables = ['newsletters', 'newsletter_queue', 'newsletter_opens', 'newsletter_clicks', 'newsletter_subscribers'];
        foreach ($requiredTables as $table) {
            try {
                $db->query("SELECT 1 FROM $table LIMIT 1");
                $diagnostics['tables'][$table] = ['exists' => true, 'status' => 'OK'];
            } catch (\Exception $e) {
                $diagnostics['tables'][$table] = ['exists' => false, 'status' => 'MISSING'];
                $diagnostics['issues'][] = "Table '$table' does not exist";
            }
        }

        // Check newsletter columns
        try {
            $cols = $db->query("SHOW COLUMNS FROM newsletters")->fetchAll(\PDO::FETCH_COLUMN);
            $requiredCols = ['total_opens', 'unique_opens', 'total_clicks', 'unique_clicks', 'total_sent'];
            foreach ($requiredCols as $col) {
                if (!in_array($col, $cols)) {
                    $diagnostics['issues'][] = "Column 'newsletters.$col' is missing";
                    $diagnostics['fixes_available'][] = "add_column_$col";
                }
            }
        } catch (\Exception $e) {
            // Table doesn't exist
        }

        // Get newsletter status summary
        try {
            $stats = $db->prepare("SELECT status, COUNT(*) as count FROM newsletters WHERE tenant_id = ? GROUP BY status");
            $stats->execute([$tenantId]);
            $diagnostics['newsletter_stats'] = $stats->fetchAll(\PDO::FETCH_KEY_PAIR);
        } catch (\Exception $e) {
            $diagnostics['newsletter_stats'] = [];
        }

        // Find newsletters that were sent but status not updated
        try {
            $stmt = $db->prepare("SELECT id, subject, status, sent_at, total_sent, total_recipients
                                  FROM newsletters
                                  WHERE tenant_id = ? AND sent_at IS NOT NULL AND status != 'sent'
                                  ORDER BY sent_at DESC");
            $stmt->execute([$tenantId]);
            $needsStatusFix = $stmt->fetchAll();
            if (!empty($needsStatusFix)) {
                $diagnostics['issues'][] = count($needsStatusFix) . " newsletter(s) were sent but status is not 'sent'";
                $diagnostics['fixes_available'][] = 'fix_sent_status';
                $diagnostics['newsletters_needing_fix'] = $needsStatusFix;
            }
        } catch (\Exception $e) {
            // Ignore
        }

        // Find newsletters with missing total_sent
        try {
            $stmt = $db->prepare("SELECT id, subject, status, total_sent, total_recipients
                                  FROM newsletters
                                  WHERE tenant_id = ? AND status = 'sent' AND (total_sent IS NULL OR total_sent = 0)
                                  ORDER BY id DESC");
            $stmt->execute([$tenantId]);
            $needsTotalFix = $stmt->fetchAll();
            if (!empty($needsTotalFix)) {
                $diagnostics['issues'][] = count($needsTotalFix) . " sent newsletter(s) have no total_sent count";
                $diagnostics['fixes_available'][] = 'fix_total_sent';
            }
        } catch (\Exception $e) {
            // Ignore
        }

        // Find newsletters stuck in 'sending' status
        try {
            $stmt = $db->prepare("SELECT n.id, n.subject, n.status, n.created_at, n.total_recipients,
                                         (SELECT COUNT(*) FROM newsletter_queue WHERE newsletter_id = n.id AND status = 'pending') as pending_count,
                                         (SELECT COUNT(*) FROM newsletter_queue WHERE newsletter_id = n.id AND status = 'sent') as sent_count
                                  FROM newsletters n
                                  WHERE n.tenant_id = ? AND n.status = 'sending'
                                  ORDER BY n.created_at DESC");
            $stmt->execute([$tenantId]);
            $stuckSending = $stmt->fetchAll();
            if (!empty($stuckSending)) {
                $diagnostics['issues'][] = count($stuckSending) . " newsletter(s) stuck in 'Sending' status";
                $diagnostics['fixes_available'][] = 'fix_stuck_sending';
                $diagnostics['stuck_sending'] = $stuckSending;
            }
        } catch (\Exception $e) {
            // Ignore
        }

        // Check group filter debug info
        if (!empty($_GET['debug_group'])) {
            $groupId = intval($_GET['debug_group']);
            try {
                $stmt = $db->prepare("SELECT gm.user_id, gm.status as member_status, u.email, u.first_name, u.last_name, u.is_approved
                                      FROM group_members gm
                                      JOIN users u ON gm.user_id = u.id
                                      WHERE gm.group_id = ? AND u.tenant_id = ?");
                $stmt->execute([$groupId, $tenantId]);
                $diagnostics['group_members'] = $stmt->fetchAll();
                $diagnostics['debug_group_id'] = $groupId;
            } catch (\Exception $e) {
                $diagnostics['group_error'] = $e->getMessage();
            }
        }

        View::render('admin/newsletters/diagnostics', [
            'pageTitle' => 'Newsletter Diagnostics',
            'diagnostics' => $diagnostics
        ]);
    }

    /**
     * Apply database fixes
     * POST /admin/newsletters/repair
     */
    public function repair()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $tenantId = TenantContext::getId();
        $db = \Nexus\Core\Database::getInstance();
        $fix = $_POST['fix'] ?? '';
        $results = ['success' => false, 'message' => '', 'affected' => 0];

        try {
            switch ($fix) {
                case 'fix_sent_status':
                    // Update newsletters that have sent_at but wrong status
                    $stmt = $db->prepare("UPDATE newsletters SET status = 'sent' WHERE tenant_id = ? AND sent_at IS NOT NULL AND status != 'sent'");
                    $stmt->execute([$tenantId]);
                    $results['affected'] = $stmt->rowCount();
                    $results['success'] = true;
                    $results['message'] = "Updated {$results['affected']} newsletter(s) to 'sent' status";
                    break;

                case 'fix_total_sent':
                    // Set total_sent from total_recipients where missing
                    $stmt = $db->prepare("UPDATE newsletters SET total_sent = COALESCE(total_recipients, 0) WHERE tenant_id = ? AND status = 'sent' AND (total_sent IS NULL OR total_sent = 0)");
                    $stmt->execute([$tenantId]);
                    $results['affected'] = $stmt->rowCount();
                    $results['success'] = true;
                    $results['message'] = "Updated total_sent for {$results['affected']} newsletter(s)";
                    break;

                case 'init_tracking_columns':
                    // Initialize tracking columns to 0 where NULL
                    $stmt = $db->prepare("UPDATE newsletters SET
                        total_opens = COALESCE(total_opens, 0),
                        unique_opens = COALESCE(unique_opens, 0),
                        total_clicks = COALESCE(total_clicks, 0),
                        unique_clicks = COALESCE(unique_clicks, 0)
                        WHERE tenant_id = ?");
                    $stmt->execute([$tenantId]);
                    $results['affected'] = $stmt->rowCount();
                    $results['success'] = true;
                    $results['message'] = "Initialized tracking columns for {$results['affected']} newsletter(s)";
                    break;

                case 'create_tracking_tables':
                    // Create missing tracking tables
                    $db->exec("CREATE TABLE IF NOT EXISTS newsletter_opens (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        tenant_id INT NOT NULL,
                        newsletter_id INT NOT NULL,
                        queue_id INT NULL,
                        email VARCHAR(255) NOT NULL,
                        user_agent TEXT NULL,
                        ip_address VARCHAR(45) NULL,
                        opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_newsletter (newsletter_id),
                        INDEX idx_tenant_newsletter (tenant_id, newsletter_id)
                    )");

                    $db->exec("CREATE TABLE IF NOT EXISTS newsletter_clicks (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        tenant_id INT NOT NULL,
                        newsletter_id INT NOT NULL,
                        queue_id INT NULL,
                        email VARCHAR(255) NOT NULL,
                        url TEXT NOT NULL,
                        link_id VARCHAR(255) NULL,
                        user_agent TEXT NULL,
                        ip_address VARCHAR(45) NULL,
                        clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_newsletter (newsletter_id),
                        INDEX idx_tenant_newsletter (tenant_id, newsletter_id)
                    )");

                    $results['success'] = true;
                    $results['message'] = "Created tracking tables successfully";
                    break;

                case 'add_tracking_columns':
                    // Add tracking columns to newsletters table
                    $cols = $db->query("SHOW COLUMNS FROM newsletters")->fetchAll(\PDO::FETCH_COLUMN);
                    $added = [];

                    if (!in_array('total_opens', $cols)) {
                        $db->exec("ALTER TABLE newsletters ADD COLUMN total_opens INT DEFAULT 0");
                        $added[] = 'total_opens';
                    }
                    if (!in_array('unique_opens', $cols)) {
                        $db->exec("ALTER TABLE newsletters ADD COLUMN unique_opens INT DEFAULT 0");
                        $added[] = 'unique_opens';
                    }
                    if (!in_array('total_clicks', $cols)) {
                        $db->exec("ALTER TABLE newsletters ADD COLUMN total_clicks INT DEFAULT 0");
                        $added[] = 'total_clicks';
                    }
                    if (!in_array('unique_clicks', $cols)) {
                        $db->exec("ALTER TABLE newsletters ADD COLUMN unique_clicks INT DEFAULT 0");
                        $added[] = 'unique_clicks';
                    }

                    $results['success'] = true;
                    $results['message'] = empty($added) ? "All columns already exist" : "Added columns: " . implode(', ', $added);
                    break;

                case 'fix_stuck_sending':
                    // Fix newsletters stuck in 'sending' status - mark queue items as sent and update newsletter
                    // First, update all pending queue items to 'sent' for newsletters that are stuck
                    $stmt = $db->prepare("
                        UPDATE newsletter_queue nq
                        INNER JOIN newsletters n ON nq.newsletter_id = n.id
                        SET nq.status = 'sent', nq.sent_at = COALESCE(nq.sent_at, NOW())
                        WHERE n.tenant_id = ? AND n.status = 'sending' AND nq.status = 'pending'
                    ");
                    $stmt->execute([$tenantId]);
                    $queueFixed = $stmt->rowCount();

                    // Then update the newsletters themselves
                    $stmt = $db->prepare("
                        UPDATE newsletters n
                        SET n.status = 'sent',
                            n.sent_at = COALESCE(n.sent_at, NOW()),
                            n.total_sent = (SELECT COUNT(*) FROM newsletter_queue WHERE newsletter_id = n.id AND status = 'sent')
                        WHERE n.tenant_id = ? AND n.status = 'sending'
                    ");
                    $stmt->execute([$tenantId]);
                    $results['affected'] = $stmt->rowCount();
                    $results['success'] = true;
                    $results['message'] = "Fixed {$results['affected']} stuck newsletter(s), updated {$queueFixed} queue item(s)";
                    break;

                default:
                    $results['message'] = "Unknown fix type: $fix";
            }
        } catch (\Exception $e) {
            $results['message'] = "Error: " . $e->getMessage();
        }

        $_SESSION['flash_' . ($results['success'] ? 'success' : 'error')] = $results['message'];
        header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/diagnostics');
        exit;
    }

    private function checkAdmin()
    {
        $role = $_SESSION['user_role'] ?? '';
        $allowedRoles = ['admin', 'newsletter_admin'];

        if (!in_array($role, $allowedRoles) && empty($_SESSION['is_super_admin'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        // Set tenant context from session for admin routes (which use reserved paths)
        if (!empty($_SESSION['tenant_id'])) {
            TenantContext::setById($_SESSION['tenant_id']);
        } elseif (!empty($_SESSION['user_id'])) {
            // Fallback: get tenant_id from user record if not in session
            $user = \Nexus\Core\Database::query(
                "SELECT tenant_id FROM users WHERE id = ?",
                [$_SESSION['user_id']]
            )->fetch();
            if ($user && !empty($user['tenant_id'])) {
                $_SESSION['tenant_id'] = $user['tenant_id']; // Cache for future requests
                TenantContext::setById($user['tenant_id']);
            }
        }
    }

    private function jsonResponse($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    // =========================================================================
    // TEMPLATE MANAGEMENT
    // =========================================================================

    /**
     * List all templates
     */
    public function templates()
    {
        $this->checkAdmin();

        $category = $_GET['category'] ?? null;

        if ($category) {
            $templates = NewsletterTemplate::getByCategory($category);
        } else {
            $templates = NewsletterTemplate::getAll();
        }

        // Group by category for display
        $grouped = [
            'starter' => [],
            'saved' => [],
            'custom' => []
        ];

        foreach ($templates as $template) {
            $cat = $template['category'] ?? 'custom';
            $grouped[$cat][] = $template;
        }

        View::render('admin/newsletters/templates', [
            'pageTitle' => 'Newsletter Templates',
            'templates' => $templates,
            'grouped' => $grouped,
            'currentCategory' => $category
        ]);
    }

    /**
     * Create template form
     */
    public function createTemplate()
    {
        $this->checkAdmin();

        View::render('admin/newsletters/template-form', [
            'pageTitle' => 'Create Template',
            'template' => null
        ]);
    }

    /**
     * Edit template
     */
    public function editTemplate($id)
    {
        $this->checkAdmin();

        $template = NewsletterTemplate::findById($id);
        if (!$template) {
            $_SESSION['flash_error'] = 'Template not found';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/templates');
            exit;
        }

        // Starter templates can't be edited directly - copy first
        if ($template['tenant_id'] == 0) {
            $_SESSION['flash_error'] = 'Starter templates cannot be edited. Use "Duplicate" to create a copy.';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/templates');
            exit;
        }

        View::render('admin/newsletters/template-form', [
            'pageTitle' => 'Edit Template',
            'template' => $template
        ]);
    }

    /**
     * Store new template
     */
    public function storeTemplate()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $data = [
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'subject' => $_POST['subject'] ?? '',
            'preview_text' => $_POST['preview_text'] ?? '',
            'content' => $_POST['content'] ?? '',
            'category' => 'custom',
            'created_by' => $_SESSION['user_id'] ?? null
        ];

        if (empty($data['name'])) {
            $_SESSION['flash_error'] = 'Template name is required';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/templates/create');
            exit;
        }

        try {
            $id = NewsletterTemplate::create($data);
            $_SESSION['flash_success'] = 'Template created successfully';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/templates');
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error creating template: ' . $e->getMessage();
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/templates/create');
        }
        exit;
    }

    /**
     * Update template
     */
    public function updateTemplate($id)
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $data = [
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'subject' => $_POST['subject'] ?? '',
            'preview_text' => $_POST['preview_text'] ?? '',
            'content' => $_POST['content'] ?? ''
        ];

        if (empty($data['name'])) {
            $_SESSION['flash_error'] = 'Template name is required';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/templates/edit/' . $id);
            exit;
        }

        try {
            NewsletterTemplate::update($id, $data);
            $_SESSION['flash_success'] = 'Template updated successfully';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/templates');
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error updating template: ' . $e->getMessage();
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/templates/edit/' . $id);
        }
        exit;
    }

    /**
     * Delete template
     */
    public function deleteTemplate()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $id = $_POST['id'] ?? 0;

        try {
            NewsletterTemplate::delete($id);
            $_SESSION['flash_success'] = 'Template deleted';
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error deleting template';
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/templates');
        exit;
    }

    /**
     * Duplicate a template
     */
    public function duplicateTemplate($id)
    {
        $this->checkAdmin();

        $template = NewsletterTemplate::findById($id);
        if (!$template) {
            $_SESSION['flash_error'] = 'Template not found';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/templates');
            exit;
        }

        try {
            if ($template['tenant_id'] == 0) {
                // Copy starter template
                $newId = NewsletterTemplate::copyStarterToTenant($id);
            } else {
                // Duplicate existing template
                $newId = NewsletterTemplate::create([
                    'name' => $template['name'] . ' (Copy)',
                    'description' => $template['description'],
                    'subject' => $template['subject'],
                    'preview_text' => $template['preview_text'],
                    'content' => $template['content'],
                    'category' => 'custom',
                    'created_by' => $_SESSION['user_id'] ?? null
                ]);
            }

            $_SESSION['flash_success'] = 'Template duplicated successfully';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/templates/edit/' . $newId);
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error duplicating template: ' . $e->getMessage();
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/templates');
        }
        exit;
    }

    /**
     * Preview a template
     */
    public function previewTemplate($id)
    {
        $this->checkAdmin();

        $template = NewsletterTemplate::findById($id);
        if (!$template) {
            http_response_code(404);
            echo "Template not found";
            exit;
        }

        // Process dynamic variables for preview
        $content = NewsletterService::processTemplateVariables($template['content'], [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com'
        ]);

        header('Content-Type: text/html; charset=utf-8');
        echo $content;
        exit;
    }

    /**
     * Save newsletter as template (AJAX)
     */
    public function saveAsTemplate()
    {
        $this->checkAdmin();

        $newsletterId = $_POST['newsletter_id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';

        if (empty($name)) {
            $this->jsonResponse(['success' => false, 'error' => 'Template name is required']);
        }

        try {
            $id = NewsletterTemplate::saveFromNewsletter($newsletterId, $name, $description);
            $this->jsonResponse([
                'success' => true,
                'id' => $id,
                'message' => 'Newsletter saved as template'
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Get templates list (AJAX for newsletter creation)
     */
    public function getTemplates()
    {
        $this->checkAdmin();

        $templates = NewsletterTemplate::getAll();

        $this->jsonResponse([
            'success' => true,
            'templates' => array_map(function($t) {
                return [
                    'id' => $t['id'],
                    'name' => $t['name'],
                    'category' => $t['category'],
                    'subject' => $t['subject'],
                    'preview_text' => $t['preview_text'],
                    'use_count' => $t['use_count']
                ];
            }, $templates)
        ]);
    }

    /**
     * Load template content (AJAX)
     */
    public function loadTemplate($id)
    {
        $this->checkAdmin();

        $template = NewsletterTemplate::findById($id);
        if (!$template) {
            $this->jsonResponse(['success' => false, 'error' => 'Template not found']);
        }

        // Increment use count
        NewsletterTemplate::incrementUseCount($id);

        $this->jsonResponse([
            'success' => true,
            'template' => [
                'subject' => $template['subject'],
                'preview_text' => $template['preview_text'],
                'content' => $template['content']
            ]
        ]);
    }

    // =========================================================================
    // BOUNCE & SUPPRESSION MANAGEMENT
    // =========================================================================

    /**
     * View bounce/suppression list
     */
    public function bounces()
    {
        $this->checkAdmin();

        $tab = $_GET['tab'] ?? 'suppressed';
        $page = $_GET['page'] ?? 1;
        $limit = 50;
        $offset = ($page - 1) * $limit;

        if ($tab === 'recent') {
            $items = NewsletterBounce::getRecent($limit);
            $total = 0; // Not paginated for recent
        } else {
            $items = NewsletterBounce::getSuppressionList($limit, $offset);
            $total = NewsletterBounce::getSuppressionCount();
        }

        $stats = NewsletterBounce::getStats();
        $totalPages = ceil($total / $limit);

        View::render('admin/newsletters/bounces', [
            'pageTitle' => 'Bounce Management',
            'items' => $items,
            'stats' => $stats,
            'currentTab' => $tab,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total
        ]);
    }

    /**
     * Remove from suppression list
     */
    public function unsuppress()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $email = $_POST['email'] ?? '';

        if ($email) {
            NewsletterBounce::removeFromSuppressionList($email);
            $_SESSION['flash_success'] = 'Email removed from suppression list';
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/bounces');
        exit;
    }

    /**
     * Manually add to suppression list
     */
    public function suppress()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $email = $_POST['email'] ?? '';
        $reason = $_POST['reason'] ?? 'manual';

        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            NewsletterBounce::addToSuppressionList($email, $reason);
            $_SESSION['flash_success'] = 'Email added to suppression list';
        } else {
            $_SESSION['flash_error'] = 'Invalid email address';
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/bounces');
        exit;
    }

    // =========================================================================
    // RESEND TO NON-OPENERS
    // =========================================================================

    /**
     * Show resend form for non-openers
     */
    public function resendForm($id)
    {
        $this->checkAdmin();

        $newsletter = Newsletter::findById($id);
        if (!$newsletter) {
            $_SESSION['flash_error'] = 'Newsletter not found';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters');
            exit;
        }

        if ($newsletter['status'] !== 'sent') {
            $_SESSION['flash_error'] = 'Can only resend newsletters that have been sent';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/stats/' . $id);
            exit;
        }

        $resendInfo = NewsletterService::getResendInfo($id);

        View::render('admin/newsletters/resend', [
            'pageTitle' => 'Resend to Non-Openers',
            'newsletter' => $newsletter,
            'resendInfo' => $resendInfo
        ]);
    }

    /**
     * Execute resend to non-openers
     */
    public function resend($id)
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $newsletter = Newsletter::findById($id);
        if (!$newsletter) {
            $_SESSION['flash_error'] = 'Newsletter not found';
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters');
            exit;
        }

        $newSubject = trim($_POST['subject'] ?? '');
        if (empty($newSubject)) {
            $newSubject = "Reminder: " . $newsletter['subject'];
        }

        try {
            $result = NewsletterService::resendToNonOpeners($id, $newSubject);
            $_SESSION['flash_success'] = "Resent newsletter to {$result['recipients']} non-openers";
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/stats/' . $result['newsletter_id']);
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: ' . TenantContext::getBasePath() . '/admin/newsletters/resend/' . $id);
        }
        exit;
    }

    /**
     * Get resend info via AJAX
     */
    public function getResendInfo($id)
    {
        $this->checkAdmin();

        $info = NewsletterService::getResendInfo($id);

        if (!$info) {
            $this->jsonResponse(['success' => false, 'error' => 'Newsletter not found or not sent']);
        }

        $this->jsonResponse([
            'success' => true,
            'info' => $info
        ]);
    }

    /**
     * Get optimal send time recommendations (AJAX)
     */
    public function getSendTimeRecommendations()
    {
        $this->checkAdmin();

        try {
            $recommendations = NewsletterAnalytics::getOptimalSendTimes();
            $this->jsonResponse([
                'success' => true,
                'data' => $recommendations
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get send time heatmap data (AJAX)
     */
    public function getSendTimeHeatmap()
    {
        $this->checkAdmin();

        try {
            $heatmap = NewsletterAnalytics::getSendTimeHeatmap();
            $this->jsonResponse([
                'success' => true,
                'data' => $heatmap
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Show send time optimization page
     */
    public function sendTimeOptimization()
    {
        $this->checkAdmin();

        $recommendations = NewsletterAnalytics::getOptimalSendTimes();
        $heatmap = NewsletterAnalytics::getSendTimeHeatmap();

        View::render('admin/newsletters/send-time', [
            'pageTitle' => 'Send Time Optimization',
            'recommendations' => $recommendations,
            'heatmap' => $heatmap
        ]);
    }

    /**
     * Get email client preview (AJAX)
     * Returns HTML preview styled for different email clients
     */
    public function getEmailClientPreview($id)
    {
        $this->checkAdmin();

        $newsletter = Newsletter::findById($id);
        if (!$newsletter) {
            $this->jsonResponse(['success' => false, 'error' => 'Newsletter not found']);
            return;
        }

        $client = $_GET['client'] ?? 'gmail';
        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Project NEXUS';

        // Generate the email HTML
        $emailHtml = NewsletterService::renderEmail($newsletter, $tenantName);

        // Apply client-specific styling/wrapping
        $previewHtml = $this->applyClientStyling($emailHtml, $client, $newsletter);

        $this->jsonResponse([
            'success' => true,
            'html' => $previewHtml,
            'client' => $client
        ]);
    }

    /**
     * Apply email client specific styling for preview
     */
    private function applyClientStyling($html, $client, $newsletter)
    {
        $subject = htmlspecialchars($newsletter['subject']);
        $previewText = htmlspecialchars($newsletter['preview_text'] ?? '');

        switch ($client) {
            case 'gmail':
                return $this->wrapGmailPreview($html, $subject, $previewText);

            case 'outlook':
                return $this->wrapOutlookPreview($html, $subject, $previewText);

            case 'apple':
                return $this->wrapAppleMailPreview($html, $subject, $previewText);

            case 'mobile':
                return $this->wrapMobilePreview($html, $subject, $previewText);

            default:
                return $html;
        }
    }

    /**
     * Gmail-style preview wrapper
     */
    private function wrapGmailPreview($html, $subject, $previewText)
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f6f8fc; font-family: 'Google Sans', Roboto, Arial, sans-serif; }
        .gmail-header { background: #fff; padding: 16px 24px; border-bottom: 1px solid #e0e0e0; display: flex; align-items: center; gap: 16px; }
        .gmail-logo { font-size: 24px; font-weight: 500; color: #5f6368; }
        .gmail-inbox { background: #d3e3fd; color: #1a73e8; padding: 4px 16px; border-radius: 16px; font-size: 14px; }
        .gmail-email-header { background: #fff; padding: 20px 24px; border-bottom: 1px solid #e0e0e0; }
        .gmail-subject { font-size: 22px; color: #202124; margin-bottom: 12px; }
        .gmail-meta { display: flex; align-items: center; gap: 12px; }
        .gmail-avatar { width: 40px; height: 40px; background: #6366f1; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .gmail-sender { color: #202124; font-weight: 500; }
        .gmail-to { color: #5f6368; font-size: 13px; }
        .gmail-body { background: #fff; padding: 24px; }
        .preview-badge { position: fixed; top: 10px; right: 10px; background: #1a73e8; color: white; padding: 6px 12px; border-radius: 4px; font-size: 12px; z-index: 100; }
    </style>
</head>
<body>
    <div class="preview-badge">Gmail Preview</div>
    <div class="gmail-header">
        <div class="gmail-logo">Gmail</div>
        <div class="gmail-inbox">Inbox</div>
    </div>
    <div class="gmail-email-header">
        <div class="gmail-subject">{$subject}</div>
        <div class="gmail-meta">
            <div class="gmail-avatar">N</div>
            <div>
                <div class="gmail-sender">Newsletter &lt;newsletter@example.com&gt;</div>
                <div class="gmail-to">to me</div>
            </div>
        </div>
    </div>
    <div class="gmail-body">
        {$html}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Outlook-style preview wrapper
     */
    private function wrapOutlookPreview($html, $subject, $previewText)
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f3f2f1; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .outlook-header { background: #0078d4; padding: 12px 20px; color: white; display: flex; align-items: center; gap: 12px; }
        .outlook-logo { font-weight: 600; font-size: 18px; }
        .outlook-ribbon { background: #f3f2f1; padding: 8px 20px; border-bottom: 1px solid #d2d0ce; display: flex; gap: 16px; }
        .outlook-ribbon button { background: transparent; border: none; padding: 8px 12px; color: #323130; cursor: pointer; font-size: 12px; }
        .outlook-ribbon button:hover { background: #e1dfdd; }
        .outlook-email-pane { background: #fff; margin: 20px; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .outlook-email-header { padding: 20px; border-bottom: 1px solid #edebe9; }
        .outlook-subject { font-size: 20px; color: #323130; margin-bottom: 16px; font-weight: 600; }
        .outlook-sender-row { display: flex; align-items: center; gap: 12px; }
        .outlook-avatar { width: 36px; height: 36px; background: #0078d4; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 14px; }
        .outlook-sender { color: #323130; font-weight: 500; }
        .outlook-time { color: #605e5c; font-size: 12px; }
        .outlook-body { padding: 20px; }
        .preview-badge { position: fixed; top: 10px; right: 10px; background: #0078d4; color: white; padding: 6px 12px; border-radius: 4px; font-size: 12px; z-index: 100; }
    </style>
</head>
<body>
    <div class="preview-badge">Outlook Preview</div>
    <div class="outlook-header">
        <div class="outlook-logo">Outlook</div>
    </div>
    <div class="outlook-ribbon">
        <button>New mail</button>
        <button>Delete</button>
        <button>Archive</button>
        <button>Reply</button>
    </div>
    <div class="outlook-email-pane">
        <div class="outlook-email-header">
            <div class="outlook-subject">{$subject}</div>
            <div class="outlook-sender-row">
                <div class="outlook-avatar">N</div>
                <div>
                    <div class="outlook-sender">Newsletter</div>
                    <div class="outlook-time">Today at 10:00 AM</div>
                </div>
            </div>
        </div>
        <div class="outlook-body">
            {$html}
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Apple Mail-style preview wrapper
     */
    private function wrapAppleMailPreview($html, $subject, $previewText)
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f5f5f7; font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif; }
        .apple-toolbar { background: linear-gradient(180deg, #f6f6f6 0%, #e9e9e9 100%); padding: 10px 16px; display: flex; justify-content: space-between; border-bottom: 1px solid #c4c4c4; }
        .apple-toolbar-buttons { display: flex; gap: 8px; }
        .apple-toolbar-btn { width: 12px; height: 12px; border-radius: 50%; }
        .apple-close { background: #ff5f56; }
        .apple-minimize { background: #ffbd2e; }
        .apple-maximize { background: #27ca40; }
        .apple-email-container { background: #fff; margin: 0; }
        .apple-email-header { padding: 20px 24px; border-bottom: 1px solid #e5e5e5; }
        .apple-subject { font-size: 20px; color: #1d1d1f; font-weight: 600; margin-bottom: 12px; }
        .apple-from-row { display: flex; align-items: center; gap: 12px; margin-bottom: 4px; }
        .apple-label { color: #86868b; font-size: 13px; width: 50px; }
        .apple-value { color: #1d1d1f; font-size: 13px; }
        .apple-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 500; }
        .apple-body { padding: 24px; }
        .preview-badge { position: fixed; top: 10px; right: 10px; background: #1d1d1f; color: white; padding: 6px 12px; border-radius: 4px; font-size: 12px; z-index: 100; }
    </style>
</head>
<body>
    <div class="preview-badge">Apple Mail Preview</div>
    <div class="apple-toolbar">
        <div class="apple-toolbar-buttons">
            <div class="apple-toolbar-btn apple-close"></div>
            <div class="apple-toolbar-btn apple-minimize"></div>
            <div class="apple-toolbar-btn apple-maximize"></div>
        </div>
        <div style="color: #1d1d1f; font-size: 13px;">Mail</div>
        <div></div>
    </div>
    <div class="apple-email-container">
        <div class="apple-email-header">
            <div class="apple-subject">{$subject}</div>
            <div class="apple-from-row">
                <span class="apple-label">From:</span>
                <div class="apple-avatar">N</div>
                <span class="apple-value">Newsletter &lt;newsletter@example.com&gt;</span>
            </div>
            <div class="apple-from-row">
                <span class="apple-label">To:</span>
                <span class="apple-value">me</span>
            </div>
        </div>
        <div class="apple-body">
            {$html}
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Mobile-style preview wrapper
     */
    private function wrapMobilePreview($html, $subject, $previewText)
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #000; font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif; display: flex; justify-content: center; padding: 20px; }
        .phone-frame { width: 375px; background: #1c1c1e; border-radius: 40px; padding: 10px; box-shadow: 0 20px 60px rgba(0,0,0,0.5); }
        .phone-notch { width: 150px; height: 30px; background: #000; margin: 0 auto 10px; border-radius: 0 0 20px 20px; }
        .phone-screen { background: #fff; border-radius: 30px; overflow: hidden; height: 700px; overflow-y: auto; }
        .mobile-header { background: #f2f2f7; padding: 12px 16px; border-bottom: 1px solid #c6c6c8; position: sticky; top: 0; display: flex; align-items: center; gap: 12px; }
        .mobile-back { color: #007aff; font-size: 16px; }
        .mobile-title { flex: 1; text-align: center; font-weight: 600; color: #1c1c1e; }
        .mobile-email-header { padding: 16px; background: #fff; border-bottom: 1px solid #e5e5e5; }
        .mobile-subject { font-size: 17px; font-weight: 600; color: #1c1c1e; margin-bottom: 8px; }
        .mobile-sender { display: flex; align-items: center; gap: 10px; }
        .mobile-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 14px; }
        .mobile-sender-name { color: #1c1c1e; font-size: 15px; }
        .mobile-time { color: #8e8e93; font-size: 13px; }
        .mobile-body { padding: 16px; }
        .mobile-body img { max-width: 100% !important; height: auto !important; }
        .preview-badge { position: fixed; top: 10px; right: 10px; background: #007aff; color: white; padding: 6px 12px; border-radius: 4px; font-size: 12px; z-index: 100; }
    </style>
</head>
<body>
    <div class="preview-badge">Mobile Preview</div>
    <div class="phone-frame">
        <div class="phone-notch"></div>
        <div class="phone-screen">
            <div class="mobile-header">
                <span class="mobile-back">&lt; Inbox</span>
                <span class="mobile-title">Mail</span>
            </div>
            <div class="mobile-email-header">
                <div class="mobile-subject">{$subject}</div>
                <div class="mobile-sender">
                    <div class="mobile-avatar">N</div>
                    <div>
                        <div class="mobile-sender-name">Newsletter</div>
                        <div class="mobile-time">Just now</div>
                    </div>
                </div>
            </div>
            <div class="mobile-body">
                {$html}
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
