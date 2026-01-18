<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\ImageUploader;
use Nexus\Core\TenantContext;

class UploadController
{
    public function store()
    {
        // Check Auth (Admin Only)
        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']) || !empty($_SESSION['is_super_admin']) || !empty($_SESSION['is_admin']);
        if (!$isAdmin) {
            header("HTTP/1.0 403 Forbidden");
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        header('Content-Type: application/json');

        $uploadedUrls = [];
        $files = $_FILES['files'] ?? [];

        // Normalize $_FILES structure if multiple
        if (isset($files['name']) && is_array($files['name'])) {
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i],
                ];
                try {
                    $url = ImageUploader::upload($file, 'pages/assets');
                    // GrapesJS expects absolute URL or relative to root
                    $uploadedUrls[] = $url;
                } catch (\Exception $e) {
                    // Log error but continue?
                }
            }
        } elseif (isset($files['name'])) {
            // Single file
            try {
                $url = ImageUploader::upload($files, 'pages/assets');
                $uploadedUrls[] = $url;
            } catch (\Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
                exit;
            }
        }

        // Return expected GrapesJS Asset Manager format: { data: [ url1, url2 ] }
        echo json_encode(['data' => $uploadedUrls]);
    }
}
