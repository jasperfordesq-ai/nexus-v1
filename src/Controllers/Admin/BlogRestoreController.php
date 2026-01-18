<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\Database;
use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

class BlogRestoreController
{
    private function checkAdmin()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']);
        $isSuper = !empty($_SESSION['is_super_admin']);
        $isAdminSession = !empty($_SESSION['is_admin']);

        if (!$isAdmin && !$isSuper && !$isAdminSession) {
            header('HTTP/1.0 403 Forbidden');
            echo "Access Denied - Admin privileges required";
            exit;
        }
    }

    public function index()
    {
        $this->checkAdmin();

        // Get current blog statistics
        $tenantId = TenantContext::getId();

        $stats = [
            'total_posts' => 0,
            'published' => 0,
            'drafts' => 0,
            'tenant_posts' => 0,
        ];

        try {
            $stmt = Database::query("SELECT COUNT(*) as count FROM posts");
            $stats['total_posts'] = $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

            $stmt = Database::query("SELECT COUNT(*) as count FROM posts WHERE tenant_id = ?", [$tenantId]);
            $stats['tenant_posts'] = $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

            $stmt = Database::query("SELECT COUNT(*) as count FROM posts WHERE tenant_id = ? AND status = 'published'", [$tenantId]);
            $stats['published'] = $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

            $stmt = Database::query("SELECT COUNT(*) as count FROM posts WHERE tenant_id = ? AND status = 'draft'", [$tenantId]);
            $stats['drafts'] = $stmt->fetch(\PDO::FETCH_ASSOC)['count'];
        } catch (\Exception $e) {
            // Table might not exist yet
        }

        // Check for available export files in exports directory
        $exportFiles = [];
        $exportDir = __DIR__ . '/../../../exports';
        if (is_dir($exportDir)) {
            $files = glob($exportDir . '/exported_blogs_*.sql');
            foreach ($files as $file) {
                $size = filesize($file);
                $time = filemtime($file);
                $exportFiles[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => $this->formatBytes($size),
                    'date' => date('Y-m-d H:i:s', $time),
                    'age' => $this->timeAgo($time),
                ];
            }
            // Sort by date, newest first
            usort($exportFiles, function($a, $b) {
                return strcmp($b['date'], $a['date']);
            });
        }

        View::render('admin/blog-restore/index', [
            'pageTitle' => 'Restore Blog Posts',
            'stats' => $stats,
            'exportFiles' => $exportFiles,
            'tenantId' => $tenantId,
        ]);
    }

    public function diagnostic()
    {
        $this->checkAdmin();
        header('Content-Type: application/json');

        $tenantId = TenantContext::getId();
        $result = [
            'success' => true,
            'table_exists' => false,
            'total_posts' => 0,
            'tenant_posts' => 0,
            'posts_by_tenant' => [],
            'recent_posts' => [],
        ];

        try {
            // Check if posts table exists
            $stmt = Database::query("SHOW TABLES LIKE 'posts'");
            $result['table_exists'] = (bool) $stmt->fetch();

            if ($result['table_exists']) {
                // Total posts
                $stmt = Database::query("SELECT COUNT(*) as count FROM posts");
                $result['total_posts'] = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

                // Posts in current tenant
                $stmt = Database::query("SELECT COUNT(*) as count FROM posts WHERE tenant_id = ?", [$tenantId]);
                $result['tenant_posts'] = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

                // Posts by tenant
                $stmt = Database::query("SELECT tenant_id, COUNT(*) as count FROM posts GROUP BY tenant_id");
                $result['posts_by_tenant'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                // Recent posts in current tenant
                $stmt = Database::query(
                    "SELECT id, title, status, created_at FROM posts WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 10",
                    [$tenantId]
                );
                $result['recent_posts'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
        } catch (\Exception $e) {
            $result['success'] = false;
            $result['error'] = $e->getMessage();
        }

        echo json_encode($result);
        exit;
    }

    public function uploadForm()
    {
        $this->checkAdmin();
        View::render('admin/blog-restore/upload', [
            'pageTitle' => 'Upload Blog Export File',
        ]);
    }

    public function upload()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        header('Content-Type: application/json');

        if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
            exit;
        }

        $file = $_FILES['sql_file'];
        $filename = $file['name'];
        $tmpPath = $file['tmp_name'];

        // Validate file extension
        if (!preg_match('/\.sql$/i', $filename)) {
            echo json_encode(['success' => false, 'error' => 'File must be a .sql file']);
            exit;
        }

        // Create exports directory if it doesn't exist
        $exportDir = __DIR__ . '/../../../exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        // Generate unique filename
        $timestamp = date('Y_m_d_His');
        $newFilename = 'uploaded_blogs_' . $timestamp . '.sql';
        $destination = $exportDir . '/' . $newFilename;

        // Move uploaded file
        if (move_uploaded_file($tmpPath, $destination)) {
            echo json_encode([
                'success' => true,
                'filename' => $newFilename,
                'size' => $this->formatBytes(filesize($destination)),
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
        }
        exit;
    }

    public function import()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        header('Content-Type: application/json');

        $filename = $_POST['filename'] ?? null;
        if (!$filename) {
            echo json_encode(['success' => false, 'error' => 'No filename provided']);
            exit;
        }

        $exportDir = __DIR__ . '/../../../exports';
        $filepath = $exportDir . '/' . basename($filename); // basename for security

        if (!file_exists($filepath)) {
            echo json_encode(['success' => false, 'error' => 'Export file not found']);
            exit;
        }

        try {
            $tenantId = TenantContext::getId();

            // Get current count
            $stmt = Database::query("SELECT COUNT(*) as count FROM posts WHERE tenant_id = ?", [$tenantId]);
            $beforeCount = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

            // Create backup first
            $backupResult = $this->createBackup();
            if (!$backupResult['success']) {
                echo json_encode(['success' => false, 'error' => 'Backup failed: ' . $backupResult['error']]);
                exit;
            }

            // Read and execute SQL file
            $sql = file_get_contents($filepath);
            $pdo = Database::getInstance();
            $pdo->beginTransaction();

            // Split by semicolons and execute each statement
            $statements = explode(';', $sql);
            $insertedCount = 0;
            $skippedCount = 0;
            $errors = [];

            foreach ($statements as $statement) {
                $statement = trim($statement);

                // Skip empty statements and comments
                if (empty($statement) || strpos($statement, '--') === 0) {
                    continue;
                }

                try {
                    $pdo->exec($statement);
                    if (stripos($statement, 'INSERT INTO posts') === 0) {
                        $insertedCount++;
                    }
                } catch (\Exception $e) {
                    // Check if it's a duplicate entry error
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $skippedCount++;
                    } else {
                        $errors[] = substr($e->getMessage(), 0, 200);
                    }
                }
            }

            $pdo->commit();

            // Get new count
            $stmt = Database::query("SELECT COUNT(*) as count FROM posts WHERE tenant_id = ?", [$tenantId]);
            $afterCount = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

            echo json_encode([
                'success' => true,
                'before_count' => $beforeCount,
                'after_count' => $afterCount,
                'added_count' => $afterCount - $beforeCount,
                'inserted' => $insertedCount,
                'skipped' => $skippedCount,
                'backup_file' => $backupResult['filename'] ?? null,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }

        exit;
    }

    public function downloadExport()
    {
        $this->checkAdmin();

        $tenantId = $_GET['tenant'] ?? TenantContext::getId();
        $status = $_GET['status'] ?? 'all';

        try {
            // Build query
            $query = "SELECT * FROM posts WHERE tenant_id = ?";
            $params = [$tenantId];

            if ($status !== 'all') {
                $query .= " AND status = ?";
                $params[] = $status;
            }

            $query .= " ORDER BY id ASC";

            $stmt = Database::query($query, $params);
            $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($posts)) {
                die("No posts found to export");
            }

            // Generate SQL
            $sql = $this->generateExportSQL($posts, $tenantId, $status);

            // Download
            $timestamp = date('Y_m_d_His');
            $filename = "exported_blogs_{$timestamp}_tenant{$tenantId}";
            if ($status !== 'all') {
                $filename .= "_{$status}";
            }
            $filename .= ".sql";

            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($sql));

            echo $sql;
            exit;

        } catch (\Exception $e) {
            die("Export failed: " . $e->getMessage());
        }
    }

    private function createBackup()
    {
        try {
            $backupDir = __DIR__ . '/../../../backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $timestamp = date('Y_m_d_His');
            $filename = "backup_before_blog_restore_{$timestamp}.sql";
            $filepath = $backupDir . '/' . $filename;

            $dbName = getenv('DB_NAME');
            $dbUser = getenv('DB_USER');
            $dbPass = getenv('DB_PASS');
            $dbHost = getenv('DB_HOST') ?: 'localhost';

            $command = "mysqldump -h {$dbHost} -u {$dbUser} -p{$dbPass} {$dbName} posts > \"{$filepath}\" 2>&1";
            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($filepath)) {
                return [
                    'success' => true,
                    'filename' => $filename,
                    'path' => $filepath,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'mysqldump command failed: ' . implode("\n", $output),
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function generateExportSQL($posts, $tenantId, $status)
    {
        $count = count($posts);
        $columns = array_keys($posts[0]);

        $sql = "-- ============================================================================\n";
        $sql .= "-- Blog Posts Export\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Total Posts: {$count}\n";
        $sql .= "-- Tenant: {$tenantId}\n";
        if ($status !== 'all') {
            $sql .= "-- Status: {$status}\n";
        }
        $sql .= "-- ============================================================================\n\n";

        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($posts as $post) {
            $values = [];
            foreach ($columns as $column) {
                $value = $post[$column];

                if ($value === null) {
                    $values[] = "NULL";
                } else if (is_numeric($value)) {
                    $values[] = $value;
                } else {
                    $escaped = str_replace("'", "''", $value);
                    $values[] = "'" . $escaped . "'";
                }
            }

            $sql .= "INSERT INTO posts (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
        }

        $sql .= "\nSET FOREIGN_KEY_CHECKS=1;\n";

        return $sql;
    }

    private function formatBytes($bytes)
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }

    private function timeAgo($timestamp)
    {
        $diff = time() - $timestamp;

        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
        if ($diff < 604800) return floor($diff / 86400) . ' days ago';
        if ($diff < 2592000) return floor($diff / 604800) . ' weeks ago';

        return date('M j, Y', $timestamp);
    }
}
