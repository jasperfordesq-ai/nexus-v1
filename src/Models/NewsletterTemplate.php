<?php

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class NewsletterTemplate
{
    /**
     * Get all templates for current tenant (including global starter templates)
     */
    public static function getAll($includeStarters = true, $activeOnly = true)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT * FROM newsletter_templates WHERE ";

        if ($includeStarters) {
            $sql .= "(tenant_id = ? OR (tenant_id = 0 AND category = 'starter'))";
        } else {
            $sql .= "tenant_id = ?";
        }

        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }

        $sql .= " ORDER BY category ASC, use_count DESC, name ASC";

        return Database::query($sql, [$tenantId])->fetchAll();
    }

    /**
     * Get templates by category
     */
    public static function getByCategory($category)
    {
        $tenantId = TenantContext::getId();

        if ($category === 'starter') {
            // Starter templates are global (tenant_id = 0)
            $sql = "SELECT * FROM newsletter_templates WHERE tenant_id = 0 AND category = 'starter' AND is_active = 1 ORDER BY name ASC";
            return Database::query($sql)->fetchAll();
        }

        $sql = "SELECT * FROM newsletter_templates WHERE tenant_id = ? AND category = ? AND is_active = 1 ORDER BY use_count DESC, name ASC";
        return Database::query($sql, [$tenantId, $category])->fetchAll();
    }

    /**
     * Find template by ID
     */
    public static function findById($id)
    {
        $tenantId = TenantContext::getId();

        // Allow access to global templates (tenant_id = 0) or tenant's own templates
        $sql = "SELECT * FROM newsletter_templates WHERE id = ? AND (tenant_id = ? OR tenant_id = 0)";
        return Database::query($sql, [$id, $tenantId])->fetch();
    }

    /**
     * Create a new template
     */
    public static function create($data)
    {
        $tenantId = TenantContext::getId();

        $sql = "INSERT INTO newsletter_templates
                (tenant_id, name, description, category, subject, preview_text, content, thumbnail, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        Database::query($sql, [
            $tenantId,
            $data['name'],
            $data['description'] ?? null,
            $data['category'] ?? 'custom',
            $data['subject'] ?? null,
            $data['preview_text'] ?? null,
            $data['content'] ?? '',
            $data['thumbnail'] ?? null,
            $data['created_by'] ?? null
        ]);

        return Database::getConnection()->lastInsertId();
    }

    /**
     * Update a template
     */
    public static function update($id, $data)
    {
        $tenantId = TenantContext::getId();
        $fields = [];
        $params = [];

        $allowedFields = ['name', 'description', 'subject', 'preview_text', 'content', 'thumbnail', 'is_active'];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "$key = ?";
                $params[] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $params[] = $tenantId;

        $sql = "UPDATE newsletter_templates SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?";
        return Database::query($sql, $params);
    }

    /**
     * Delete a template
     */
    public static function delete($id)
    {
        $tenantId = TenantContext::getId();

        // Can only delete own templates, not global starters
        $sql = "DELETE FROM newsletter_templates WHERE id = ? AND tenant_id = ?";
        return Database::query($sql, [$id, $tenantId]);
    }

    /**
     * Increment use count
     */
    public static function incrementUseCount($id)
    {
        $sql = "UPDATE newsletter_templates SET use_count = use_count + 1 WHERE id = ?";
        return Database::query($sql, [$id]);
    }

    /**
     * Save a newsletter as a template
     */
    public static function saveFromNewsletter($newsletterId, $name, $description = null)
    {
        $tenantId = TenantContext::getId();

        // Get the newsletter
        $newsletter = Newsletter::findById($newsletterId);
        if (!$newsletter) {
            throw new \Exception('Newsletter not found');
        }

        return self::create([
            'name' => $name,
            'description' => $description,
            'category' => 'saved',
            'subject' => $newsletter['subject'],
            'preview_text' => $newsletter['preview_text'],
            'content' => $newsletter['content'],
            'created_by' => $_SESSION['user_id'] ?? null
        ]);
    }

    /**
     * Copy a starter template to tenant (for customization)
     */
    public static function copyStarterToTenant($templateId)
    {
        $template = self::findById($templateId);
        if (!$template || $template['tenant_id'] != 0) {
            throw new \Exception('Template not found or not a starter template');
        }

        return self::create([
            'name' => $template['name'] . ' (Copy)',
            'description' => $template['description'],
            'category' => 'custom',
            'subject' => $template['subject'],
            'preview_text' => $template['preview_text'],
            'content' => $template['content'],
            'created_by' => $_SESSION['user_id'] ?? null
        ]);
    }

    /**
     * Count templates
     */
    public static function count($category = null)
    {
        $tenantId = TenantContext::getId();

        if ($category === 'starter') {
            $sql = "SELECT COUNT(*) as total FROM newsletter_templates WHERE tenant_id = 0 AND category = 'starter' AND is_active = 1";
            return Database::query($sql)->fetch()['total'];
        }

        if ($category) {
            $sql = "SELECT COUNT(*) as total FROM newsletter_templates WHERE tenant_id = ? AND category = ? AND is_active = 1";
            return Database::query($sql, [$tenantId, $category])->fetch()['total'];
        }

        $sql = "SELECT COUNT(*) as total FROM newsletter_templates WHERE (tenant_id = ? OR (tenant_id = 0 AND category = 'starter')) AND is_active = 1";
        return Database::query($sql, [$tenantId])->fetch()['total'];
    }
}
