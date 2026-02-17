<?php

namespace Nexus\Controllers;

use Nexus\Core\Csrf;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Tenant;

class MasterController
{
    private function checkSuperAdmin()
    {
        if (!isset($_SESSION['user_id']) || empty($_SESSION['is_super_admin'])) {
            header('HTTP/1.0 403 Forbidden');
            echo "<h1>403 Forbidden</h1><p>Only Super Admins can access this area.</p>";
            exit;
        }
    }

    public function index()
    {
        $this->checkSuperAdmin();

        // Fetch All Tenants
        $tenants = Database::query("SELECT * FROM tenants ORDER BY created_at DESC")->fetchAll();

        \Nexus\Core\View::render('admin/super-admin/dashboard', [
            'tenants' => $tenants
        ]);
    }

    public function createTenant()
    {
        $this->checkSuperAdmin();
        Csrf::verifyOrDie();

        $name = $_POST['name'] ?? '';
        $slug = $_POST['slug'] ?? '';
        $domain = $_POST['domain'] ?? null;
        $tagline = $_POST['tagline'] ?? null;
        $description = $_POST['description'] ?? null;

        // Default Features
        $features = json_encode([
            'listings' => isset($_POST['feat_listings']),
            'groups'   => isset($_POST['feat_groups']),
            'wallet'   => isset($_POST['feat_wallet']),
            'volunteering' => isset($_POST['feat_volunteering']),
            'events'   => isset($_POST['feat_events']),
            'resources' => isset($_POST['feat_resources']),
            'polls'    => isset($_POST['feat_polls']),
            'goals'    => isset($_POST['feat_goals']),
            'blog'     => isset($_POST['feat_blog']),
            'help_center' => isset($_POST['feat_help_center']),
        ]);

        if ($name && $slug) {
            $sql = "INSERT INTO tenants (name, slug, domain, tagline, description, features) VALUES (?, ?, ?, ?, ?, ?)";
            try {
                Database::query($sql, [$name, $slug, $domain, $tagline, $description, $features]);
                $tenantId = Database::lastInsertId();

                // Create Initial Admin
                $adminName = $_POST['admin_name'] ?? '';
                $adminEmail = $_POST['admin_email'] ?? '';
                $adminPass = $_POST['admin_password'] ?? '';

                if ($adminName && $adminEmail && $adminPass) {
                    $parts = explode(' ', $adminName, 2);
                    $firstName = $parts[0];
                    $lastName = $parts[1] ?? '';

                    $hash = password_hash($adminPass, PASSWORD_DEFAULT);
                    Database::query(
                        "INSERT INTO users (tenant_id, first_name, last_name, email, password_hash, role, is_approved) VALUES (?, ?, ?, ?, ?, 'admin', 1)",
                        [$tenantId, $firstName, $lastName, $adminEmail, $hash]
                    );

                    // Initialize Default SEO Metadata
                    Database::query(
                        "INSERT INTO seo_metadata (tenant_id, entity_type, entity_id, meta_title, meta_description) VALUES (?, 'global', NULL, ?, ?)",
                        [$tenantId, $name, $tagline ?: $description]
                    );
                }

                header('Location: /super-admin?msg=tenant_created');
                exit;
            } catch (\PDOException $e) {
                echo "Error creating tenant: " . $e->getMessage();
            }
        }
    }

    public function addAdmin()
    {
        $this->checkSuperAdmin();
        Csrf::verifyOrDie();

        $tenantId = $_POST['tenant_id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = $_POST['password'];

        if ($tenantId && $name && $email && $password) {
            $parts = explode(' ', $name, 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? '';

            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                Database::query(
                    "INSERT INTO users (tenant_id, first_name, last_name, email, password_hash, role, is_approved) VALUES (?, ?, ?, ?, ?, 'admin', 1)",
                    [$tenantId, $firstName, $lastName, $email, $hash]
                );
            } catch (\Exception $e) {
                // Ignore for now or handle dupes
            }
        }

        header('Location: /super-admin/tenant/edit?id=' . $tenantId . '&msg=admin_added');
        exit;
    }

    public function deleteAdmin()
    {
        $this->checkSuperAdmin();
        Csrf::verifyOrDie();
        $adminId = $_POST['admin_id'] ?? 0;
        $tenantId = $_POST['tenant_id'] ?? 0;

        if ($adminId && $tenantId) {
            // Confirm user belongs to tenant and is admin
            $check = Database::query("SELECT id FROM users WHERE id = ? AND tenant_id = ? AND role = 'admin'", [$adminId, $tenantId])->fetch();
            if ($check) {
                Database::query("DELETE FROM users WHERE id = ? AND tenant_id = ?", [$adminId, $tenantId]);
            }
        }

        header('Location: /super-admin/tenant/edit?id=' . $tenantId . '&msg=admin_removed');
        exit;
    }

    public function edit()
    {
        $this->checkSuperAdmin();
        $id = $_GET['id'] ?? 0;
        $tenant = Database::query("SELECT * FROM tenants WHERE id = ?", [$id])->fetch();
        $admins = Database::query("SELECT * FROM users WHERE tenant_id = ? AND role = 'admin'", [$id])->fetchAll();

        if (!$tenant) {
            echo "Tenant not found.";
            exit;
        }

        \Nexus\Core\View::render('admin/super-admin/tenant-edit', [
            'tenant' => $tenant,
            'admins' => $admins
        ]);
    }

    public function update()
    {
        $this->checkSuperAdmin();
        Csrf::verifyOrDie();

        $id = $_POST['id'];
        $name = $_POST['name'];
        $slug = $_POST['slug'];
        $domain = $_POST['domain'] ?: null;
        $tagline = $_POST['tagline'] ?: null;
        $description = $_POST['description'] ?: null;

        // SEO Fields
        $metaTitle = $_POST['meta_title'] ?: null;
        $metaDescription = $_POST['meta_description'] ?: null;
        $h1Headline = $_POST['h1_headline'] ?: null;
        $heroIntro = $_POST['hero_intro'] ?: null;

        // Location/Geo Fields
        $countryCode = $_POST['country_code'] ?: null;
        $serviceArea = $_POST['service_area'] ?: 'national';
        $locationName = $_POST['location_name'] ?: null;
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

        $features = json_encode([
            'listings' => isset($_POST['feat_listings']),
            'groups'   => isset($_POST['feat_groups']),
            'wallet'   => isset($_POST['feat_wallet']),
            'volunteering' => isset($_POST['feat_volunteering']),
            'events'   => isset($_POST['feat_events']),
            'resources' => isset($_POST['feat_resources']),
            'polls'    => isset($_POST['feat_polls']),
            'goals'    => isset($_POST['feat_goals']),
            'blog'     => isset($_POST['feat_blog']),
            'help_center' => isset($_POST['feat_help_center']),
        ]);

        $config = json_encode([
            'footer_text' => $_POST['footer_text'] ?? '',
            'privacy_text' => $_POST['privacy_text'] ?? '',
            'terms_text' => $_POST['terms_text'] ?? ''
        ]);

        $sql = "UPDATE tenants SET
            name=?, slug=?, domain=?, tagline=?, description=?, features=?, configuration=?,
            meta_title=?, meta_description=?, h1_headline=?, hero_intro=?,
            country_code=?, service_area=?, location_name=?, latitude=?, longitude=?
            WHERE id=?";
        Database::query($sql, [
            $name, $slug, $domain, $tagline, $description, $features, $config,
            $metaTitle, $metaDescription, $h1Headline, $heroIntro,
            $countryCode, $serviceArea, $locationName, $latitude, $longitude,
            $id
        ]);

        header('Location: /super-admin?msg=tenant_updated');
        exit;
    }

    public function updateConfig()
    {
        // Enforce Super Admin
        if (empty($_SESSION['is_super_admin'])) {
            header("HTTP/1.1 403 Forbidden");
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $tenantId = $input['tenant_id'] ?? null;
        $config = $input['config'] ?? null;

        if (!$tenantId || !$config) {
            header("HTTP/1.1 400 Bad Request");
            echo json_encode(['error' => 'Missing tenant_id or config']);
            exit;
        }

        if (\Nexus\Models\Tenant::updateConfig($tenantId, $config)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            header("HTTP/1.1 500 Server Error");
            echo json_encode(['error' => 'Update failed']);
        }
        exit;
    }

    public function users()
    {
        $this->checkSuperAdmin();
        $users = \Nexus\Models\User::getAllGlobal();

        \Nexus\Core\View::render('admin/super-admin/users', [
            'users' => $users
        ]);
    }

    public function deleteUser()
    {
        $this->checkSuperAdmin();
        Csrf::verifyOrDie();
        $userId = $_POST['user_id'] ?? null;

        if ($userId) {
            // Strict check: don't delete self!
            if ($userId == $_SESSION['user_id']) {
                header('Location: /super-admin/users?error=cannot_delete_self');
                exit;
            }
            // Use raw query for cross-tenant deletion
            Database::query("DELETE FROM users WHERE id = ?", [$userId]);
        }

        header('Location: /super-admin/users?deleted=true');
        exit;
    }

    public function approveUser()
    {
        $this->checkSuperAdmin();
        Csrf::verifyOrDie();
        $userId = $_POST['user_id'] ?? null;

        if ($userId) {
            Database::query("UPDATE users SET is_approved = 1 WHERE id = ?", [$userId]);
        }

        header('Location: /super-admin/users?approved=true');
        exit;
    }
}
