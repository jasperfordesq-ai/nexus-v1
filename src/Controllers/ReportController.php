<?php

namespace Nexus\Controllers;

use Nexus\Core\TenantContext;
use Nexus\Models\Report;

class ReportController
{
    public function store()
    {
        \Nexus\Core\Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $tenantId = TenantContext::getId();
        $reporterId = $_SESSION['user_id'];

        $targetType = $_POST['target_type'] ?? '';
        $targetId = $_POST['target_id'] ?? 0;
        $reason = trim($_POST['reason'] ?? '');

        $validTypes = ['listing', 'user', 'message'];

        if (in_array($targetType, $validTypes) && $targetId && $reason) {
            Report::create($tenantId, $reporterId, $targetType, $targetId, $reason);

            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success']);
                exit;
            }

            // Redirect back with flash message (using query param for MVP)
            $referer = $_SERVER['HTTP_REFERER'] ?? '/';
            // Append &reported=1
            $sep = (strpos($referer, '?') !== false) ? '&' : '?';
            header("Location: " . $referer . $sep . "msg=reported");
            exit;
        }

        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid report details']);
            exit;
        }

        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/?error=invalid_report');
    }

    public function resolve()
    {
        \Nexus\Core\Csrf::verifyOrDie();
        // Admin Check
        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']) || !empty($_SESSION['is_super_admin']) || !empty($_SESSION['is_admin']);
        if (!$isAdmin) {
            die("Access Denied");
        }

        $id = $_POST['report_id'];
        $status = $_POST['status']; // 'resolved' or 'dismissed'

        if ($id && in_array($status, ['resolved', 'dismissed'])) {
            Report::resolve($id, $status);
            \Nexus\Models\ActivityLog::log($_SESSION['user_id'], 'resolve_report', "Marked Report #$id as $status");
        }

        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin');
    }
}
