<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\ImageUploader;
use Nexus\Core\TenantContext;

/**
 * UploadController
 *
 * API endpoint for file uploads (Page Builder, Newsletter Editor, etc.).
 * Requires admin permissions.
 * Supports both session-based and Bearer token authentication.
 *
 * Response Format:
 * Success: { "data": [url1, url2, ...] }
 * Error:   { "errors": [{ "code": "...", "message": "..." }] }
 */
class UploadController extends BaseApiController
{
    /**
     * POST /api/upload
     *
     * Upload one or more files (images, documents, etc.).
     * Requires admin role.
     *
     * Request: multipart/form-data with 'files' or 'files[]'
     *
     * Response: 200 OK with array of uploaded file URLs
     */
    public function store(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $this->rateLimit('upload', 30, 60);

        $uploadedUrls = [];
        $errors = [];
        $files = $_FILES['files'] ?? [];

        // Check if any files were uploaded
        if (empty($files) || (isset($files['error']) && $files['error'] === UPLOAD_ERR_NO_FILE)) {
            $this->respondWithError('NO_FILES', 'No files were uploaded', 'files', 400);
        }

        // Normalize $_FILES structure if multiple files
        if (isset($files['name']) && is_array($files['name'])) {
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                // Skip files with errors
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    $errors[] = [
                        'code' => 'UPLOAD_FAILED',
                        'message' => $this->getUploadErrorMessage($files['error'][$i]),
                        'file' => $files['name'][$i]
                    ];
                    continue;
                }

                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i],
                ];
                try {
                    $url = ImageUploader::upload($file, 'pages/assets');
                    $uploadedUrls[] = $url;
                } catch (\Exception $e) {
                    $errors[] = [
                        'code' => 'UPLOAD_FAILED',
                        'message' => $e->getMessage(),
                        'file' => $files['name'][$i]
                    ];
                }
            }
        } elseif (isset($files['name'])) {
            // Single file
            if ($files['error'] !== UPLOAD_ERR_OK) {
                $this->respondWithError(
                    'UPLOAD_FAILED',
                    $this->getUploadErrorMessage($files['error']),
                    'files',
                    400
                );
            }

            try {
                $url = ImageUploader::upload($files, 'pages/assets');
                $uploadedUrls[] = $url;
            } catch (\Exception $e) {
                $this->respondWithError('UPLOAD_FAILED', $e->getMessage(), 'files', 400);
            }
        }

        // If all files failed, return error
        if (empty($uploadedUrls) && !empty($errors)) {
            $this->respondWithErrors($errors, 400);
        }

        // Return response (compatible with GrapesJS Asset Manager format)
        $response = ['data' => $uploadedUrls];

        // Include partial errors if some files failed
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        $this->jsonResponse($response);
    }

    /**
     * Get human-readable upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive specified in the form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
        ];

        return $messages[$errorCode] ?? 'Unknown upload error';
    }
}
