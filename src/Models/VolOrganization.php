<?php

namespace Nexus\Models;

use Nexus\Core\Database;

class VolOrganization
{
    public static function create($tenantId, $userId, $name, $description, $email, $website = null)
    {
        // Default status is 'pending'
        $sql = "INSERT INTO vol_organizations (tenant_id, user_id, name, description, contact_email, website, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        Database::query($sql, [$tenantId, $userId, $name, $description, $email, $website]);
        return Database::getConnection()->lastInsertId();
    }

    public static function update($id, $name, $description, $email, $website, $autoPay = false)
    {
        $sql = "UPDATE vol_organizations SET name = ?, description = ?, contact_email = ?, website = ?, auto_pay_enabled = ? WHERE id = ?";
        Database::query($sql, [$name, $description, $email, $website, $autoPay ? 1 : 0, $id]);
    }

    public static function updateStatus($id, $status)
    {
        $sql = "UPDATE vol_organizations SET status = ? WHERE id = ?";
        Database::query($sql, [$status, $id]);
    }

    public static function find($id)
    {
        return Database::query("SELECT * FROM vol_organizations WHERE id = ?", [$id])->fetch();
    }

    public static function findByOwner($userId)
    {
        return Database::query("SELECT * FROM vol_organizations WHERE user_id = ?", [$userId])->fetchAll();
    }

    public static function all($tenantId)
    {
        return Database::query("SELECT * FROM vol_organizations WHERE tenant_id = ? ORDER BY name ASC", [$tenantId])->fetchAll();
    }

    /**
     * Get all approved organizations for public listing
     */
    public static function getApproved($tenantId)
    {
        return Database::query(
            "SELECT vo.*, CONCAT(u.first_name, ' ', u.last_name) as owner_name, u.avatar_url as owner_avatar,
                    (SELECT COUNT(*) FROM vol_opportunities WHERE organization_id = vo.id) as opportunity_count
             FROM vol_organizations vo
             JOIN users u ON vo.user_id = u.id
             WHERE vo.tenant_id = ? AND vo.status = 'approved'
             ORDER BY vo.name ASC",
            [$tenantId]
        )->fetchAll();
    }

    /**
     * Search approved organizations by name or description
     */
    public static function search($tenantId, $query)
    {
        $searchTerm = '%' . $query . '%';
        return Database::query(
            "SELECT vo.*, CONCAT(u.first_name, ' ', u.last_name) as owner_name, u.avatar_url as owner_avatar,
                    (SELECT COUNT(*) FROM vol_opportunities WHERE organization_id = vo.id) as opportunity_count
             FROM vol_organizations vo
             JOIN users u ON vo.user_id = u.id
             WHERE vo.tenant_id = ? AND vo.status = 'approved'
               AND (vo.name LIKE ? OR vo.description LIKE ?)
             ORDER BY vo.name ASC",
            [$tenantId, $searchTerm, $searchTerm]
        )->fetchAll();
    }
}
