<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Models\ResourceItem;
use Nexus\Models\ActivityLog;

class ResourceController
{
    private function checkFeature()
    {
        if (!TenantContext::hasFeature('resources')) {
            header("HTTP/1.0 404 Not Found");
            echo "Resource Library module is not enabled.";
            exit;
        }
    }

    public function index()
    {
        $this->checkFeature();
        $categoryId = $_GET['cat'] ?? null;
        $resources = ResourceItem::all(TenantContext::getId(), $categoryId);
        $categories = \Nexus\Models\Category::getByType('resource');

        \Nexus\Core\SEO::setTitle('Resource Library');
        \Nexus\Core\SEO::setDescription('Download guides, tools, and documents.');

        View::render('resources/index', [
            'resources' => $resources,
            'categories' => $categories,
            'categoryId' => $categoryId
        ]);
    }

    public function create()
    {
        $this->checkFeature();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $categories = \Nexus\Models\Category::getByType('resource');
        \Nexus\Core\SEO::setTitle('Upload Resource');
        View::render('resources/create', ['categories' => $categories]);
    }

    public function store()
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $title = $_POST['title'];
        $desc = $_POST['description'];
        $categoryId = !empty($_POST['category_id']) ? $_POST['category_id'] : null;

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            die("File upload failed.");
        }

        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Security: Removed .zip (can contain malicious files), .txt (can be misused)
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($ext, $allowed)) {
            header('Location: ' . TenantContext::getBasePath() . '/resources/create?error=invalid_file_type');
            exit;
        }

        // Security: For images, validate they are actually images
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $imageExtensions)) {
            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                header('Location: ' . TenantContext::getBasePath() . '/resources/create?error=invalid_image');
                exit;
            }
        }

        // Security: Use cryptographically secure random filename
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $targetDir = __DIR__ . '/../../httpdocs/uploads/resources/';
        // Security: Use 0755 instead of 0777 (not world-writable)
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

        $targetPath = $targetDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $webPath = '/uploads/resources/' . $filename;
            $id = ResourceItem::create(TenantContext::getId(), $_SESSION['user_id'], $title, $desc, $webPath, $file['type'], $file['size'], $categoryId);

            // Feed Log
            ActivityLog::log($_SESSION['user_id'], 'shared a Resource 📚', $title, true, '/resources');

            header('Location: ' . TenantContext::getBasePath() . '/resources');
        } else {
            echo "Failed to move uploaded file.";
        }
    }

    public function edit($id)
    {
        $this->checkFeature();
        if (!isset($_SESSION['user_id'])) die("Access denied");

        $resource = ResourceItem::find($id);
        if (!$resource) die("Resource not found");

        // Auth check: Owner or Admin
        $isOwner = $resource['user_id'] == $_SESSION['user_id'];
        $isAdmin = isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'];
        if (!$isOwner && !$isAdmin) die("Access denied");

        $categories = \Nexus\Models\Category::getByType('resource');
        \Nexus\Core\SEO::setTitle('Edit Resource');
        View::render('resources/edit', [
            'resource' => $resource,
            'categories' => $categories
        ]);
    }

    public function update($id)
    {
        $this->checkFeature();
        Csrf::verifyOrDie();

        $resource = ResourceItem::find($id);
        if (!$resource) die("Resource not found");

        $isOwner = $resource['user_id'] == $_SESSION['user_id'];
        $isAdmin = isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'];
        if (!$isOwner && !$isAdmin) die("Access denied");

        $title = $_POST['title'];
        $desc = $_POST['description'];
        $categoryId = !empty($_POST['category_id']) ? $_POST['category_id'] : null;

        ResourceItem::update($id, $title, $desc, $categoryId);
        header('Location: ' . TenantContext::getBasePath() . '/resources');
    }

    public function destroy($id)
    {
        $this->checkFeature();
        Csrf::verifyOrDie();

        $resource = ResourceItem::find($id);
        if (!$resource) die("Resource not found");

        $isOwner = $resource['user_id'] == $_SESSION['user_id'];
        $isAdmin = isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'];
        if (!$isOwner && !$isAdmin) die("Access denied");

        ResourceItem::delete($id);
        header('Location: ' . TenantContext::getBasePath() . '/resources');
    }

    /**
     * Show download confirmation page with countdown
     */
    public function download($id)
    {
        $this->checkFeature();
        $resource = ResourceItem::find($id);
        if (!$resource) {
            http_response_code(404);
            die("Resource not found");
        }

        $filepath = __DIR__ . '/../../httpdocs' . $resource['file_path'];
        if (!file_exists($filepath)) {
            http_response_code(404);
            die("File not found on server.");
        }

        View::render('resources/download', [
            'resource' => $resource
        ]);
    }

    /**
     * Serve the actual file download
     */
    public function file($id)
    {
        $this->checkFeature();
        $res = ResourceItem::find($id);
        if (!$res) {
            http_response_code(404);
            die("Resource not found");
        }

        // SECURITY: Validate path to prevent directory traversal attacks
        $baseDir = realpath(__DIR__ . '/../../httpdocs/uploads');
        if (!$baseDir) {
            error_log("SECURITY: Base uploads directory not found");
            http_response_code(500);
            die("Server configuration error");
        }

        $filepath = __DIR__ . '/../../httpdocs' . $res['file_path'];
        $realPath = realpath($filepath);

        // SECURITY: Ensure the resolved path is within the uploads directory
        if (!$realPath || strpos($realPath, $baseDir) !== 0) {
            error_log("SECURITY: Path traversal attempt blocked for resource $id: " . $res['file_path']);
            http_response_code(403);
            die("Access denied");
        }

        if (!file_exists($realPath)) {
            http_response_code(404);
            die("File not found on server.");
        }

        // Use the validated realPath from here on
        $filepath = $realPath;

        // Increment download counter
        ResourceItem::incrementDownload($id);

        // Prepare filename
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($res['title'])) . '.' . $extension;

        // Get mime type - force octet-stream for PDFs to ensure download
        $mimeType = $res['file_type'] ?? 'application/octet-stream';
        if (stripos($mimeType, 'pdf') !== false) {
            $mimeType = 'application/octet-stream';
        }

        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Send headers for file download
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');

        // Flush headers
        flush();

        // Output file
        readfile($filepath);
        exit();
    }
}
