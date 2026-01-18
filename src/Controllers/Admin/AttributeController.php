<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\Csrf;
use Nexus\Core\TenantContext;
use Nexus\Models\Attribute;
use Nexus\Models\Category;

class AttributeController
{
    private function requireAdmin()
    {
        // 1. Check Login
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        // 2. Check Role (Admin OR Super Admin OR Tenant Admin)
        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']);
        $isSuper = !empty($_SESSION['is_super_admin']);
        $isAdminSession = !empty($_SESSION['is_admin']);

        if (!$isAdmin && !$isSuper && !$isAdminSession) {
            header('HTTP/1.0 403 Forbidden');
            echo "<h1>403 Forbidden</h1><p>Access Denied.</p>";
            exit;
        }
    }

    public function index()
    {
        $this->requireAdmin();
        $attributes = Attribute::all();

        View::render('admin/attributes/index', [
            'attributes' => $attributes
        ]);
    }

    public function create()
    {
        $this->requireAdmin();
        // Fetch categories for the scoping dropdown (only 'listing' type relevant for now)
        $categories = Category::getByType('listing');

        View::render('admin/attributes/create', [
            'categories' => $categories
        ]);
    }

    public function store()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $name = $_POST['name'] ?? '';
        $categoryId = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
        $inputType = $_POST['input_type'] ?? 'checkbox';

        if ($name) {
            Attribute::create($name, $categoryId, $inputType);
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/attributes');
    }

    public function edit($id)
    {
        $this->requireAdmin();
        $attribute = Attribute::find($id);
        if (!$attribute) die("Attribute not found");

        $categories = Category::getByType('listing');

        View::render('admin/attributes/edit', [
            'attribute' => $attribute,
            'categories' => $categories
        ]);
    }

    public function update()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $id = $_POST['id'];
        $name = $_POST['name'];
        $categoryId = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
        $inputType = $_POST['input_type'];
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        Attribute::update($id, [
            'name' => $name,
            'category_id' => $categoryId,
            'input_type' => $inputType,
            'is_active' => $isActive
        ]);

        header('Location: ' . TenantContext::getBasePath() . '/admin/attributes');
    }

    public function delete()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $id = $_POST['id'] ?? null;
        if ($id) {
            Attribute::delete($id);
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/attributes');
    }
}
