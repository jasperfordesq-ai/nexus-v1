<?php
/**
 * CivicOne Admin Header - Uses Modern as Source of Truth
 *
 * Since Modern is the canonical admin interface, CivicOne simply includes
 * the Modern admin header to ensure feature parity and consistency.
 */

require dirname(__DIR__, 3) . '/modern/admin/partials/admin-header.php';
