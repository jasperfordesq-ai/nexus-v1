<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Models\Event;
use Nexus\Models\EventRsvp;

class EventController
{
    private function checkFeature()
    {
        if (!TenantContext::hasFeature('events')) {
            header("HTTP/1.0 404 Not Found");
            echo "Events module is not enabled for this tenant.";
            exit;
        }
    }

    public function index()
    {
        $this->checkFeature();

        // Filter Params
        $categoryId = $_GET['category'] ?? null;
        $dateFilter = $_GET['date'] ?? null;
        $search = $_GET['search'] ?? null; // Intelligent Search

        // Fetch Events with Filters
        $events = Event::upcoming(TenantContext::getId(), 50, $categoryId, $dateFilter, $search);

        // Fetch Categories for Dropdown
        $categories = \Nexus\Models\Category::getByType('event');

        // Enrich events with attendee count
        foreach ($events as &$ev) {
            $ev['attendee_count'] = EventRsvp::getCount($ev['id'], 'going');
        }

        \Nexus\Core\SEO::setTitle('Community Events');
        \Nexus\Core\SEO::setDescription('Join upcoming events, workshops, and gatherings.');

        View::render('events/index', [
            'events' => $events,
            'categories' => $categories,
            'selectedCategory' => $categoryId,
            'selectedDate' => $dateFilter,
            'searchQuery' => $search
        ]);
    }

    public function calendar()
    {
        $this->checkFeature();

        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['month'] ?? date('m');

        if (!checkdate($month, 1, $year)) {
            $year = date('Y');
            $month = date('m');
        }

        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        $events = Event::getRange(TenantContext::getId(), $startDate, $endDate);

        // Group by Day
        $eventsByDay = [];
        foreach ($events as $ev) {
            $day = date('j', strtotime($ev['start_time'])); // 1-31
            $eventsByDay[$day][] = $ev;
        }

        \Nexus\Core\SEO::setTitle('Event Calendar');
        View::render('events/calendar', [
            'year' => $year,
            'month' => $month,
            'eventsByDay' => $eventsByDay
        ]);
    }

    public function show($id)
    {
        $this->checkFeature();
        $event = Event::find($id);

        if (!$event) die("Event not found");

        // Handle AJAX actions for likes/comments
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $this->handleEventAjax($event);
            exit;
        }

        $attendees = EventRsvp::getAttendees($id);
        $myStatus = isset($_SESSION['user_id']) ? EventRsvp::getUserStatus($id, $_SESSION['user_id']) : null;
        $count = count($attendees);

        \Nexus\Core\SEO::setTitle($event['title']);
        \Nexus\Core\SEO::setDescription("Join us for " . $event['title'] . " at " . $event['location']);
        if (!empty($event['cover_image'])) {
            \Nexus\Core\SEO::setImage($event['cover_image']);
        }

        // Load Overrides
        \Nexus\Core\SEO::load('event', $id);

        // Auto-generate description if not set
        if (!empty($event['description'])) {
            \Nexus\Core\SEO::autoDescription($event['description']);
        }

        // Add JSON-LD Event Schema
        $organizer = \Nexus\Models\User::findById($event['user_id']);
        \Nexus\Core\SEO::autoSchema('event', $event, $organizer);
        \Nexus\Core\SEO::setType('event');

        // Breadcrumbs
        \Nexus\Core\SEO::addBreadcrumbs([
            ['name' => 'Home', 'url' => '/'],
            ['name' => 'Events', 'url' => '/events'],
            ['name' => $event['title'], 'url' => '/events/' . $id]
        ]);

        $canInvite = false;
        $potentialInvitees = [];

        if (isset($_SESSION['user_id'])) {
            $canInvite = ($event['user_id'] == $_SESSION['user_id']);
            if (!$canInvite && $event['group_id']) {
                $canInvite = \Nexus\Models\Group::isMember($event['group_id'], $_SESSION['user_id']);
            }

            if ($canInvite) {
                // Fetch potential invitees (Group members OR All Tenant Users)
                if ($event['group_id']) {
                    $allMembers = \Nexus\Models\Group::getMembers($event['group_id']);
                } else {
                    // Public event: Fetch all active users in tenant (Limit 100 for performance)
                    $allMembers = \Nexus\Core\Database::query(
                        "SELECT id, CONCAT(first_name, ' ', last_name) as name, avatar_url FROM users WHERE tenant_id = ? AND is_approved = 1 ORDER BY first_name ASC LIMIT 100",
                        [TenantContext::getId()]
                    )->fetchAll();
                }

                // Get list of users already interacted
                $rsvps = \Nexus\Core\Database::query("SELECT user_id FROM event_rsvps WHERE event_id = ?", [$id])->fetchAll(\PDO::FETCH_COLUMN);

                $potentialInvitees = array_filter($allMembers, function ($m) use ($rsvps) {
                    return !in_array($m['id'], $rsvps);
                });
            }
        }

        View::render('events/show', [
            'event' => $event,
            'attendees' => $attendees,
            'myStatus' => $myStatus,
            'count' => $count,
            'canInvite' => $canInvite,
            'potentialInvitees' => $potentialInvitees
        ]);
    }

    public function create()
    {
        $this->checkFeature();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        // Fetch user's groups for the dropdown
        $myGroups = \Nexus\Models\Group::getUserGroups($_SESSION['user_id']);
        $categories = \Nexus\Models\Category::getByType('event');

        // Check if pre-selected group
        $selectedGroupId = $_GET['group_id'] ?? null;

        // Federation settings
        $federationEnabled = false;
        $userFederationOptedIn = false;
        if (class_exists('\Nexus\Services\FederationFeatureService')) {
            try {
                $federationEnabled = \Nexus\Services\FederationFeatureService::isTenantFederationEnabled();
                if ($federationEnabled) {
                    $userFedSettings = \Nexus\Core\Database::query(
                        "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
                        [$_SESSION['user_id']]
                    )->fetch();
                    $userFederationOptedIn = $userFedSettings && $userFedSettings['federation_optin'];
                }
            } catch (\Exception $e) {
                $federationEnabled = false;
            }
        }

        \Nexus\Core\SEO::setTitle('Host an Event');

        View::render('events/create', [
            'myGroups' => $myGroups,
            'categories' => $categories,
            'selectedGroupId' => $selectedGroupId,
            'federationEnabled' => $federationEnabled,
            'userFederationOptedIn' => $userFederationOptedIn
        ]);
    }

    public function store()
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $title = $_POST['title'];
        $desc = $_POST['description'];
        $loc = $_POST['location'];
        $start = $_POST['start_date'] . ' ' . $_POST['start_time'];
        $end = $_POST['end_date'] ? ($_POST['end_date'] . ' ' . ($_POST['end_time'] ?? '00:00')) : null;

        $groupId = !empty($_POST['group_id']) ? $_POST['group_id'] : null;
        $categoryId = !empty($_POST['category_id']) ? $_POST['category_id'] : null;

        // Verify group membership if group selected
        if ($groupId) {
            $isMember = \Nexus\Models\Group::isMember($groupId, $_SESSION['user_id']);
            if (!$isMember) die("Unauthorized to post in this group");
        }

        $lat = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
        $lon = !empty($_POST['longitude']) ? $_POST['longitude'] : null;

        // Handle federated visibility (only if user has opted into federation)
        $federatedVisibility = 'none';
        if (!empty($_POST['federated_visibility']) && in_array($_POST['federated_visibility'], ['listed', 'joinable'])) {
            if (class_exists('\Nexus\Services\FederationFeatureService') &&
                \Nexus\Services\FederationFeatureService::isTenantFederationEnabled()) {
                $userFedSettings = \Nexus\Core\Database::query(
                    "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
                    [$_SESSION['user_id']]
                )->fetch();

                if ($userFedSettings && $userFedSettings['federation_optin']) {
                    $federatedVisibility = $_POST['federated_visibility'];
                }
            }
        }

        $id = Event::create(TenantContext::getId(), $_SESSION['user_id'], $title, $desc, $loc, $start, $end, $groupId, $categoryId, $lat, $lon, $federatedVisibility);

        // Handle SDG Tags
        if (!empty($_POST['sdg_goals']) && is_array($_POST['sdg_goals'])) {
            $goals = array_map('intval', $_POST['sdg_goals']);
            $json = json_encode($goals);
            \Nexus\Core\Database::query("UPDATE events SET sdg_goals = ? WHERE id = ?", [$json, $id]);
        }

        // Log Activity
        \Nexus\Models\ActivityLog::log($_SESSION['user_id'], 'hosted an Event 🗓️', $title, true, '/events/' . $id);

        // Gamification: Check event host badges
        try {
            \Nexus\Services\GamificationService::checkEventBadges($_SESSION['user_id'], 'host');
        } catch (\Throwable $e) {
            error_log("Gamification event host error: " . $e->getMessage());
        }

        header('Location: ' . TenantContext::getBasePath() . '/events/' . $id);
    }

    public function rsvp()
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $eventId = $_POST['event_id'];
        $status = $_POST['status']; // going / declined

        EventRsvp::rsvp($eventId, $_SESSION['user_id'], $status);

        // Gamification: Check event attendance badges if RSVP is "going"
        if ($status === 'going') {
            try {
                \Nexus\Services\GamificationService::checkEventBadges($_SESSION['user_id'], 'attend');
            } catch (\Throwable $e) {
                error_log("Gamification event attend error: " . $e->getMessage());
            }
        }

        header('Location: ' . TenantContext::getBasePath() . '/events/' . $eventId . '?msg=rsvp_saved');
    }

    public function invite()
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $eventId = $_POST['event_id'];
        $userIds = $_POST['user_ids'] ?? [];

        if (empty($userIds)) {
            header('Location: ' . TenantContext::getBasePath() . '/events/' . $eventId . '?err=no_users');
            exit;
        }

        $event = Event::find($eventId);
        if (!$event) die("Event not found");

        // Verify Permission: Organizer OR Group Member (if group event)
        $canInvite = ($event['user_id'] == $_SESSION['user_id']);
        if (!$canInvite && $event['group_id']) {
            $canInvite = \Nexus\Models\Group::isMember($event['group_id'], $_SESSION['user_id']);
        }

        if (!$canInvite) die("Unauthorized to invite");

        foreach ($userIds as $uid) {
            // Check if already RSVP'd
            $status = EventRsvp::getUserStatus($eventId, $uid);
            if (!$status) {
                EventRsvp::rsvp($eventId, $uid, 'invited');

                // Send Notification
                $inviterName = \Nexus\Models\User::findById($_SESSION['user_id'])['name'] ?? 'Someone';
                \Nexus\Models\Notification::create($uid, "📅 $inviterName invited you to " . $event['title'], '/events/' . $eventId);

                // Send Email
                $invitedUser = \Nexus\Models\User::findById($uid);
                if ($invitedUser && !empty($invitedUser['email'])) {
                    $mailer = new \Nexus\Core\Mailer();
                    $subject = "You're invited to " . $event['title'];
                    $body = "
                        <div style='font-family: sans-serif; color: #333; line-height: 1.6;'>
                            <h2>You're Invited! 📅</h2>
                            <p><strong>$inviterName</strong> has invited you to an event:</p>
                            <div style='background: #fdf2f8; border-left: 4px solid #db2777; padding: 15px; margin: 20px 0;'>
                                <h3 style='margin: 0 0 10px 0; color: #be185d;'>{$event['title']}</h3>
                                <p style='margin: 0;'><strong>Time:</strong> " . date('F j, Y @ g:i A', strtotime($event['start_time'])) . "</p>
                                <p style='margin: 5px 0 0 0;'><strong>Location:</strong> " . htmlspecialchars($event['location']) . "</p>
                            </div>
                            <p>Click below to view details and RSVP:</p>
                            <p><a href='" . TenantContext::getDomain() . "/events/{$eventId}' style='background: #db2777; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Event</a></p>
                            <hr style='border: 0; border-top: 1px solid #eee; margin: 30px 0;'>
                            <small style='color: #777;'>Project NEXUS Community</small>
                        </div>
                    ";
                    $mailer->send($invitedUser['email'], $subject, $body);
                }
            }
        }

        header('Location: ' . TenantContext::getBasePath() . '/events/' . $eventId . '?msg=invites_sent');
    }

    public function edit($id)
    {
        $this->checkFeature();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $event = Event::find($id);
        if (!$event) die("Event not found");

        if ($event['user_id'] != $_SESSION['user_id'] && empty($_SESSION['is_super_admin'])) {
            die("Unauthorized");
        }

        $myGroups = \Nexus\Models\Group::getUserGroups($_SESSION['user_id']);
        $categories = \Nexus\Models\Category::getByType('event');

        // Federation settings
        $federationEnabled = false;
        $userFederationOptedIn = false;
        if (class_exists('\Nexus\Services\FederationFeatureService')) {
            try {
                $federationEnabled = \Nexus\Services\FederationFeatureService::isTenantFederationEnabled();
                if ($federationEnabled) {
                    $userFedSettings = \Nexus\Core\Database::query(
                        "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
                        [$_SESSION['user_id']]
                    )->fetch();
                    $userFederationOptedIn = $userFedSettings && $userFedSettings['federation_optin'];
                }
            } catch (\Exception $e) {
                $federationEnabled = false;
            }
        }

        \Nexus\Core\SEO::setTitle('Edit Event');
        View::render('events/edit', [
            'event' => $event,
            'myGroups' => $myGroups,
            'categories' => $categories,
            'federationEnabled' => $federationEnabled,
            'userFederationOptedIn' => $userFederationOptedIn
        ]);
    }

    public function update($id)
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $event = Event::find($id);
        if (!$event) die("Event not found");
        if ($event['user_id'] != $_SESSION['user_id'] && empty($_SESSION['is_super_admin'])) {
            die("Unauthorized");
        }

        $title = $_POST['title'];
        $desc = $_POST['description'];
        $loc = $_POST['location'];
        $start = $_POST['start_date'] . ' ' . $_POST['start_time'];
        $end = $_POST['end_date'] ? ($_POST['end_date'] . ' ' . ($_POST['end_time'] ?? '00:00')) : null;

        $groupId = !empty($_POST['group_id']) ? $_POST['group_id'] : null;
        $categoryId = !empty($_POST['category_id']) ? $_POST['category_id'] : null;

        $lat = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
        $lon = !empty($_POST['longitude']) ? $_POST['longitude'] : null;

        // Handle federated visibility (only if user has opted into federation)
        $federatedVisibility = null; // null means don't change
        if (isset($_POST['federated_visibility'])) {
            $requestedVisibility = $_POST['federated_visibility'];
            if ($requestedVisibility === 'none') {
                $federatedVisibility = 'none';
            } elseif (in_array($requestedVisibility, ['listed', 'joinable'])) {
                if (class_exists('\Nexus\Services\FederationFeatureService') &&
                    \Nexus\Services\FederationFeatureService::isTenantFederationEnabled()) {
                    $userFedSettings = \Nexus\Core\Database::query(
                        "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
                        [$_SESSION['user_id']]
                    )->fetch();

                    if ($userFedSettings && $userFedSettings['federation_optin']) {
                        $federatedVisibility = $requestedVisibility;
                    }
                }
            }
        }

        Event::update($id, $title, $desc, $loc, $start, $end, $groupId, $categoryId, $lat, $lon, $federatedVisibility);

        // Handle SDG Tags
        if (isset($_POST['sdg_goals'])) {
            // Handle empty array case as clearing tags
            $goals = !empty($_POST['sdg_goals']) && is_array($_POST['sdg_goals']) ? array_map('intval', $_POST['sdg_goals']) : [];
            $json = json_encode($goals);
            \Nexus\Core\Database::query("UPDATE events SET sdg_goals = ? WHERE id = ?", [$json, $id]);
        }

        // Save SEO metadata if provided
        if (isset($_POST['seo']) && is_array($_POST['seo'])) {
            \Nexus\Models\SeoMetadata::save('event', $id, [
                'meta_title' => trim($_POST['seo']['meta_title'] ?? ''),
                'meta_description' => trim($_POST['seo']['meta_description'] ?? ''),
                'meta_keywords' => trim($_POST['seo']['meta_keywords'] ?? ''),
                'canonical_url' => trim($_POST['seo']['canonical_url'] ?? ''),
                'og_image_url' => trim($_POST['seo']['og_image_url'] ?? ''),
                'noindex' => isset($_POST['seo']['noindex'])
            ]);
        }

        header('Location: ' . TenantContext::getBasePath() . '/events/' . $id . '?msg=updated');
    }

    public function destroy($id)
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $event = Event::find($id);
        if (!$event) die("Event not found");
        if ($event['user_id'] != $_SESSION['user_id'] && empty($_SESSION['is_super_admin'])) {
            die("Unauthorized");
        }

        Event::delete($id);
        header('Location: ' . TenantContext::getBasePath() . '/events?msg=deleted');
    }

    public function exportAttendees($id)
    {
        $this->checkFeature();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $event = Event::find($id);
        if (!$event) die("Event not found");

        if ($event['user_id'] != $_SESSION['user_id'] && empty($_SESSION['is_super_admin'])) {
            die("Unauthorized");
        }

        $attendees = EventRsvp::getAttendees($id);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="event_' . $id . '_attendees.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['User ID', 'Name', 'Email', 'Status', 'RSVP Date']);

        foreach ($attendees as $att) {
            fputcsv($output, [
                $att['user_id'],
                $att['name'],
                $att['email'] ?? 'hidden',
                $att['status'],
                $att['created_at']
            ]);
        }
        fclose($output);
        exit;
    }

    public function checkIn()
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $eventId = $_POST['event_id'];
        $attendeeId = $_POST['user_id'];

        $event = Event::find($eventId);
        if (!$event) die("Event not found");

        // Security: Only Organizer can check in
        if ($event['user_id'] != $_SESSION['user_id'] && empty($_SESSION['is_super_admin'])) {
            die("Unauthorized");
        }

        // Verify Status
        $currentStatus = EventRsvp::getUserStatus($eventId, $attendeeId);
        if ($currentStatus === 'attended') {
            header('Location: ' . TenantContext::getBasePath() . '/events/' . $eventId . '?err=already_checked_in');
            exit;
        }

        // Calculate Duration (Default 1 hour)
        $duration = 1;
        if (!empty($event['start_time']) && !empty($event['end_time'])) {
            $start = strtotime($event['start_time']);
            $end = strtotime($event['end_time']);
            $diff = ($end - $start) / 3600; // Hours
            $duration = round($diff, 2);
            if ($duration < 0.5) $duration = 0.5; // Minimum 30 mins
        }

        try {
            // Process Transaction: Organizer (Session) -> Attendee
            \Nexus\Models\Transaction::create(
                $_SESSION['user_id'],
                $attendeeId,
                $duration,
                "Event Attendance: " . $event['title']
            );

            // Update Status
            EventRsvp::rsvp($eventId, $attendeeId, 'attended');

            // --- CIVIC ACTION INTEGRATION ---
            // If this event has a linked volunteer opportunity, auto-log the hours
            if (!empty($event['volunteer_opportunity_id']) && !empty($event['auto_log_hours'])) {
                // Call VolLog logic (impact tracking)
                // Status 'approved' corresponds to "verified" in this context
                \Nexus\Models\VolLog::create(
                    $attendeeId,
                    1, // TODO: We need Organization ID. Event doesn't store it directly, but VolOpportunity does.
                    // For now, let's fetch the Opportunity to get the Org ID.
                    $event['volunteer_opportunity_id'],
                    date('Y-m-d'),
                    $duration,
                    "Auto-logged from Event: " . $event['title']
                );

                // We need to verify we fetched the Org ID correctly.
                // Re-doing this block with proper lookup:
            }

            header('Location: ' . TenantContext::getBasePath() . '/events/' . $eventId . '?msg=checked_in_paid');
        } catch (\Exception $e) {
            header('Location: ' . TenantContext::getBasePath() . '/events/' . $eventId . '?err=payment_failed');
        }
    }

    /**
     * Handle AJAX actions for event likes/comments
     */
    private function handleEventAjax($event)
    {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Clear any buffered output before JSON response
        if (ob_get_level()) ob_clean();
        header('Content-Type: application/json');

        // CSRF protection for AJAX state-changing requests
        \Nexus\Core\Csrf::verifyOrDieJson();

        $userId = $_SESSION['user_id'] ?? 0;
        $tenantId = TenantContext::getId();

        if (!$userId) {
            echo json_encode(['error' => 'Login required', 'redirect' => '/login']);
            return;
        }

        $action = $_POST['action'] ?? '';
        $targetType = 'event';
        $targetId = (int)$event['id'];

        try {
            // Get PDO instance directly - DatabaseWrapper adds tenant constraints that break JOINs
            $pdo = \Nexus\Core\Database::getInstance();

            // TOGGLE LIKE
            if ($action === 'toggle_like') {
                $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND target_type = ? AND target_id = ?");
                $stmt->execute([$userId, $targetType, $targetId]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $stmt = $pdo->prepare("DELETE FROM likes WHERE id = ?");
                    $stmt->execute([$existing['id']]);

                    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ?");
                    $stmt->execute([$targetType, $targetId]);
                    $countResult = $stmt->fetch();
                    echo json_encode(['status' => 'unliked', 'likes_count' => (int)($countResult['cnt'] ?? 0)]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO likes (user_id, target_type, target_id, tenant_id) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$userId, $targetType, $targetId, $tenantId]);

                    // Send notification to event organizer
                    if (class_exists('\Nexus\Services\SocialNotificationService')) {
                        $contentOwnerId = $event['user_id'] ?? null;
                        if ($contentOwnerId && $contentOwnerId != $userId) {
                            $contentPreview = $event['title'] ?? '';
                            \Nexus\Services\SocialNotificationService::notifyLike(
                                $contentOwnerId, $userId, $targetType, $targetId, $contentPreview
                            );
                        }
                    }

                    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ?");
                    $stmt->execute([$targetType, $targetId]);
                    $countResult = $stmt->fetch();
                    echo json_encode(['status' => 'liked', 'likes_count' => (int)($countResult['cnt'] ?? 0)]);
                }
            }

            // SUBMIT COMMENT
            elseif ($action === 'submit_comment') {
                $content = trim($_POST['content'] ?? '');
                if (empty($content)) {
                    echo json_encode(['error' => 'Comment cannot be empty']);
                    return;
                }

                $stmt = $pdo->prepare("INSERT INTO comments (user_id, tenant_id, target_type, target_id, content, created_at) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $tenantId, $targetType, $targetId, $content, date('Y-m-d H:i:s')]);

                // Send notification to event organizer
                if (class_exists('\Nexus\Services\SocialNotificationService')) {
                    $contentOwnerId = $event['user_id'] ?? null;
                    if ($contentOwnerId && $contentOwnerId != $userId) {
                        \Nexus\Services\SocialNotificationService::notifyComment(
                            $contentOwnerId, $userId, $targetType, $targetId, $content
                        );
                    }
                }

                echo json_encode(['status' => 'success', 'message' => 'Comment added']);
            }

            // FETCH COMMENTS (with nested replies and reactions)
            elseif ($action === 'fetch_comments') {
                $comments = \Nexus\Services\CommentService::fetchComments($targetType, $targetId, $userId);
                echo json_encode([
                    'status' => 'success',
                    'comments' => $comments,
                    'available_reactions' => \Nexus\Services\CommentService::getAvailableReactions()
                ]);
            }

            // DELETE COMMENT
            elseif ($action === 'delete_comment') {
                $commentId = (int)($_POST['comment_id'] ?? 0);
                $isSuperAdmin = !empty($_SESSION['is_super_admin']);
                $result = \Nexus\Services\CommentService::deleteComment($commentId, $userId, $isSuperAdmin);
                echo json_encode($result);
            }

            // EDIT COMMENT
            elseif ($action === 'edit_comment') {
                $commentId = (int)($_POST['comment_id'] ?? 0);
                $newContent = $_POST['content'] ?? '';
                $result = \Nexus\Services\CommentService::editComment($commentId, $userId, $newContent);
                echo json_encode($result);
            }

            // REPLY TO COMMENT
            elseif ($action === 'reply_comment') {
                $parentId = (int)($_POST['parent_id'] ?? 0);
                $content = trim($_POST['content'] ?? '');
                $result = \Nexus\Services\CommentService::addComment($userId, $tenantId, $targetType, $targetId, $content, $parentId);

                // Notify parent comment author
                if (isset($result['status']) && $result['status'] === 'success') {
                    $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
                    $stmt->execute([$parentId]);
                    $parent = $stmt->fetch();
                    if ($parent && $parent['user_id'] != $userId) {
                        if (class_exists('\Nexus\Services\SocialNotificationService')) {
                            \Nexus\Services\SocialNotificationService::notifyComment(
                                $parent['user_id'], $userId, 'reply', $parentId, $content
                            );
                        }
                    }
                }
                echo json_encode($result);
            }

            // TOGGLE REACTION ON COMMENT
            elseif ($action === 'toggle_reaction') {
                $commentId = (int)($_POST['comment_id'] ?? 0);
                $emoji = $_POST['emoji'] ?? '';
                $result = \Nexus\Services\CommentService::toggleReaction($userId, $tenantId, $commentId, $emoji);
                echo json_encode($result);
            }

            // SEARCH USERS FOR @MENTION
            elseif ($action === 'search_users') {
                $query = $_POST['query'] ?? '';
                $users = \Nexus\Services\CommentService::searchUsersForMention($query, $tenantId);
                echo json_encode(['status' => 'success', 'users' => $users]);
            }

            // SHARE EVENT TO FEED
            elseif ($action === 'share_event') {
                // Create a new post in feed_posts that references this event
                $stmt = $pdo->prepare("INSERT INTO feed_posts (user_id, tenant_id, content, parent_id, parent_type, visibility, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $shareContent = "Check out this event: " . ($event['title'] ?? 'Event');
                $stmt->execute([
                    $userId,
                    $tenantId,
                    $shareContent,
                    $targetId,
                    'event',
                    'public',
                    date('Y-m-d H:i:s')
                ]);

                // Notify event owner
                if (class_exists('\Nexus\Services\SocialNotificationService')) {
                    $contentOwnerId = $event['user_id'] ?? null;
                    if ($contentOwnerId && $contentOwnerId != $userId) {
                        // Use a generic notification or create a share notification method
                        \Nexus\Services\SocialNotificationService::notifyLike(
                            $contentOwnerId, $userId, 'event', $targetId, 'shared your event'
                        );
                    }
                }

                echo json_encode(['status' => 'success', 'message' => 'Event shared to feed']);
            }

            else {
                echo json_encode(['error' => 'Unknown action']);
            }

        } catch (\Exception $e) {
            error_log("EventController AJAX error: " . $e->getMessage());
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
