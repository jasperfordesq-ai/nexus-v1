<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\Database;
use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Models\PayPlan;
use Nexus\Models\Tenant;
use Nexus\Services\PayPlanService;

/**
 * PlanController - Admin interface for managing subscription plans
 */
class PlanController
{
    private function checkAdmin($jsonResponse = false)
    {
        if (!isset($_SESSION['user_id'])) {
            if ($jsonResponse) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Not authenticated']);
                exit;
            }
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        // Only super admins can manage plans
        $isSuper = !empty($_SESSION['is_super_admin']);

        if (!$isSuper) {
            if ($jsonResponse) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Super admin access required']);
                exit;
            }
            header('HTTP/1.0 403 Forbidden');
            echo "Access Denied - Super Admin Only";
            exit;
        }
    }

    /**
     * List all plans
     */
    public function index()
    {
        $this->checkAdmin();

        $plans = PayPlan::all(false); // Include inactive plans

        View::render('admin/plans/index', [
            'plans' => $plans
        ]);
    }

    /**
     * Create new plan form
     */
    public function create()
    {
        $this->checkAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify()) {
                $_SESSION['error'] = 'Invalid CSRF token';
                header('Location: ' . TenantContext::getBasePath() . '/admin/plans');
                exit;
            }

            $db = Database::getConnection();

            // Parse features JSON
            $features = [];
            if (!empty($_POST['features'])) {
                $features = json_decode($_POST['features'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $_SESSION['error'] = 'Invalid features JSON';
                    header('Location: ' . TenantContext::getBasePath() . '/admin/plans/create');
                    exit;
                }
            }

            // Parse allowed layouts
            $allowedLayouts = [];
            if (!empty($_POST['allowed_layouts'])) {
                $allowedLayouts = $_POST['allowed_layouts']; // Array from checkboxes
            }

            $sql = "INSERT INTO pay_plans
                    (name, slug, description, tier_level, features, allowed_layouts,
                     max_menus, max_menu_items, price_monthly, price_yearly, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                $_POST['name'],
                self::generateSlug($_POST['name']),
                $_POST['description'] ?? '',
                (int)$_POST['tier_level'],
                json_encode($features),
                json_encode($allowedLayouts),
                (int)$_POST['max_menus'],
                (int)$_POST['max_menu_items'],
                (float)$_POST['price_monthly'],
                (float)$_POST['price_yearly'],
                isset($_POST['is_active']) ? 1 : 0
            ]);

            $_SESSION['success'] = 'Plan created successfully';
            header('Location: ' . TenantContext::getBasePath() . '/admin/plans');
            exit;
        }

        // Show create form
        View::render('admin/plans/form', [
            'plan' => null,
            'mode' => 'create',
            'available_layouts' => $this->getAvailableLayouts()
        ]);
    }

    /**
     * Edit existing plan
     */
    public function edit($id)
    {
        $this->checkAdmin();

        $plan = PayPlan::find($id);
        if (!$plan) {
            $_SESSION['error'] = 'Plan not found';
            header('Location: ' . TenantContext::getBasePath() . '/admin/plans');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify()) {
                $_SESSION['error'] = 'Invalid CSRF token';
                header('Location: ' . TenantContext::getBasePath() . '/admin/plans');
                exit;
            }

            $db = Database::getConnection();

            // Parse features JSON
            $features = [];
            if (!empty($_POST['features'])) {
                $features = json_decode($_POST['features'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $_SESSION['error'] = 'Invalid features JSON';
                    header('Location: ' . TenantContext::getBasePath() . '/admin/plans/edit/' . $id);
                    exit;
                }
            }

            // Parse allowed layouts
            $allowedLayouts = [];
            if (!empty($_POST['allowed_layouts'])) {
                $allowedLayouts = $_POST['allowed_layouts'];
            }

            $sql = "UPDATE pay_plans
                    SET name = ?, slug = ?, description = ?, tier_level = ?,
                        features = ?, allowed_layouts = ?, max_menus = ?, max_menu_items = ?,
                        price_monthly = ?, price_yearly = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                $_POST['name'],
                $_POST['slug'], // Allow manual slug editing
                $_POST['description'] ?? '',
                (int)$_POST['tier_level'],
                json_encode($features),
                json_encode($allowedLayouts),
                (int)$_POST['max_menus'],
                (int)$_POST['max_menu_items'],
                (float)$_POST['price_monthly'],
                (float)$_POST['price_yearly'],
                isset($_POST['is_active']) ? 1 : 0,
                $id
            ]);

            $_SESSION['success'] = 'Plan updated successfully';
            header('Location: ' . TenantContext::getBasePath() . '/admin/plans');
            exit;
        }

        // Show edit form
        View::render('admin/plans/form', [
            'plan' => $plan,
            'mode' => 'edit',
            'available_layouts' => $this->getAvailableLayouts()
        ]);
    }

    /**
     * Delete a plan
     */
    public function delete($id)
    {
        $this->checkAdmin(true);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!Csrf::verify()) {
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        // Check if any tenants are assigned to this plan
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM tenant_plan_assignments WHERE pay_plan_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();

        if ($result['count'] > 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Cannot delete plan - it has active tenant assignments'
            ]);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM pay_plans WHERE id = ?");
        $success = $stmt->execute([$id]);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Plan deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete plan']);
        }
    }

    /**
     * View all tenant subscriptions
     */
    public function subscriptions()
    {
        $this->checkAdmin();

        $db = Database::getConnection();

        // Get all tenant assignments with plan info
        $stmt = $db->query(
            "SELECT
                t.id as tenant_id,
                t.name as tenant_name,
                t.slug as tenant_slug,
                pp.id as plan_id,
                pp.name as plan_name,
                pp.tier_level,
                tpa.status,
                tpa.starts_at,
                tpa.expires_at,
                tpa.trial_ends_at
            FROM tenants t
            LEFT JOIN tenant_plan_assignments tpa ON t.id = tpa.tenant_id AND tpa.status = 'active'
            LEFT JOIN pay_plans pp ON tpa.pay_plan_id = pp.id
            ORDER BY t.id ASC"
        );

        $tenants = $stmt->fetchAll();
        $plans = PayPlan::all(true);

        View::render('admin/plans/subscriptions', [
            'tenants' => $tenants,
            'plans' => $plans
        ]);
    }

    /**
     * Assign plan to tenant (AJAX)
     */
    public function assignPlan()
    {
        $this->checkAdmin(true);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!Csrf::verify()) {
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        $tenantId = (int)$_POST['tenant_id'];
        $planId = (int)$_POST['plan_id'];
        $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        $isTrial = isset($_POST['is_trial']) && $_POST['is_trial'] == '1';

        if ($isTrial && !empty($_POST['trial_days'])) {
            $trialDays = (int)$_POST['trial_days'];
            $result = PayPlanService::startTrial($tenantId, $planId, $trialDays);
        } else {
            $result = PayPlanService::assignPlan($tenantId, $planId, $expiresAt);
        }

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /**
     * Plan comparison view
     */
    public function comparison()
    {
        $this->checkAdmin();

        $plans = PayPlan::getComparison();

        View::render('admin/plans/comparison', [
            'plans' => $plans
        ]);
    }

    /**
     * Helper: Generate unique slug
     */
    private static function generateSlug($name)
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        // Ensure uniqueness
        $originalSlug = $slug;
        $counter = 1;

        $db = Database::getConnection();
        while (true) {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM pay_plans WHERE slug = ?");
            $stmt->execute([$slug]);
            $result = $stmt->fetch();
            if ($result['count'] == 0) break;

            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Helper: Get available layouts
     */
    private function getAvailableLayouts()
    {
        return [
            'modern' => 'Modern',
            'civicone' => 'CivicOne'
        ];
    }
}
