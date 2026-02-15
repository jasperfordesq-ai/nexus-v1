<?php

namespace Nexus\Controllers\Admin;

use Nexus\Models\Category;


class CategoryController
{
    private function requireAdmin()
    {
        // 1. Check Login
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
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
        $categories = Category::all();
        \Nexus\Core\View::render('admin/categories/index', ['categories' => $categories]);
    }

    public function create()
    {
        $this->requireAdmin();
        \Nexus\Core\View::render('admin/categories/create');
    }

    public function store()
    {
        $this->requireAdmin();

        $data = [
            'name' => $_POST['name'],
            'slug' => $this->slugify($_POST['name']),
            'color' => $_POST['color'] ?? 'blue',
            'type' => $_POST['type'] ?? 'listing'
        ];

        Category::create($data);
        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin-legacy/categories');
        exit;
    }

    public function edit($id)
    {
        $this->requireAdmin();
        $category = Category::find($id);
        \Nexus\Core\View::render('admin/categories/edit', ['category' => $category]);
    }

    public function update($id)
    {
        $this->requireAdmin();

        $data = [
            'name' => $_POST['name'],
            'slug' => $this->slugify($_POST['name']),
            'color' => $_POST['color'] ?? 'blue',
            'type' => $_POST['type'] ?? 'listing'
        ];

        Category::update($id, $data);
        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin-legacy/categories');
        exit;
    }

    public function delete($id)
    {
        $this->requireAdmin();
        Category::delete($id);
        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin-legacy/categories');
        exit;
    }

    private function slugify($text)
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text)));
    }
}
