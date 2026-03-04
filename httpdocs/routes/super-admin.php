<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
$router->add('GET', '/super-admin', 'Nexus\Controllers\SuperAdmin\DashboardController@index');
$router->add('GET', '/super-admin/dashboard', 'Nexus\Controllers\SuperAdmin\DashboardController@index');

// Tenant Management
$router->add('GET', '/super-admin/tenants', 'Nexus\Controllers\SuperAdmin\TenantController@index');
$router->add('GET', '/super-admin/tenants/hierarchy', 'Nexus\Controllers\SuperAdmin\TenantController@hierarchy');
$router->add('GET', '/super-admin/tenants/create', 'Nexus\Controllers\SuperAdmin\TenantController@create');
$router->add('POST', '/super-admin/tenants/store', 'Nexus\Controllers\SuperAdmin\TenantController@store');
$router->add('GET', '/super-admin/tenants/{id}', 'Nexus\Controllers\SuperAdmin\TenantController@show');
$router->add('GET', '/super-admin/tenants/{id}/edit', 'Nexus\Controllers\SuperAdmin\TenantController@edit');
$router->add('POST', '/super-admin/tenants/{id}/update', 'Nexus\Controllers\SuperAdmin\TenantController@update');
$router->add('POST', '/super-admin/tenants/{id}/delete', 'Nexus\Controllers\SuperAdmin\TenantController@delete');
$router->add('POST', '/super-admin/tenants/{id}/reactivate', 'Nexus\Controllers\SuperAdmin\TenantController@reactivate');
$router->add('POST', '/super-admin/tenants/{id}/toggle-hub', 'Nexus\Controllers\SuperAdmin\TenantController@toggleHub');
$router->add('POST', '/super-admin/tenants/{id}/move', 'Nexus\Controllers\SuperAdmin\TenantController@move');

// User Management (Cross-Tenant)
$router->add('GET', '/super-admin/users', 'Nexus\Controllers\SuperAdmin\UserController@index');
$router->add('GET', '/super-admin/users/create', 'Nexus\Controllers\SuperAdmin\UserController@create');
$router->add('POST', '/super-admin/users/store', 'Nexus\Controllers\SuperAdmin\UserController@store');
$router->add('GET', '/super-admin/users/{id}', 'Nexus\Controllers\SuperAdmin\UserController@show');
$router->add('GET', '/super-admin/users/{id}/edit', 'Nexus\Controllers\SuperAdmin\UserController@edit');
$router->add('POST', '/super-admin/users/{id}/update', 'Nexus\Controllers\SuperAdmin\UserController@update');
$router->add('POST', '/super-admin/users/{id}/grant-super-admin', 'Nexus\Controllers\SuperAdmin\UserController@grantSuperAdmin');
$router->add('POST', '/super-admin/users/{id}/revoke-super-admin', 'Nexus\Controllers\SuperAdmin\UserController@revokeSuperAdmin');
$router->add('POST', '/super-admin/users/{id}/grant-global-super-admin', 'Nexus\Controllers\SuperAdmin\UserController@grantGlobalSuperAdmin');
$router->add('POST', '/super-admin/users/{id}/revoke-global-super-admin', 'Nexus\Controllers\SuperAdmin\UserController@revokeGlobalSuperAdmin');
$router->add('POST', '/super-admin/users/{id}/move-tenant', 'Nexus\Controllers\SuperAdmin\UserController@moveTenant');
$router->add('POST', '/super-admin/users/{id}/move-and-promote', 'Nexus\Controllers\SuperAdmin\UserController@moveAndPromote');

// Bulk Operations
$router->add('GET', '/super-admin/bulk', 'Nexus\Controllers\SuperAdmin\BulkController@index');
$router->add('POST', '/super-admin/bulk/move-users', 'Nexus\Controllers\SuperAdmin\BulkController@moveUsers');
$router->add('POST', '/super-admin/bulk/update-tenants', 'Nexus\Controllers\SuperAdmin\BulkController@updateTenants');

// Audit Log
$router->add('GET', '/super-admin/audit', 'Nexus\Controllers\SuperAdmin\AuditController@index');

// Super Admin API Endpoints
$router->add('GET', '/super-admin/api/tenants', 'Nexus\Controllers\SuperAdmin\TenantController@apiList');
$router->add('GET', '/super-admin/api/tenants/hierarchy', 'Nexus\Controllers\SuperAdmin\TenantController@apiHierarchy');
$router->add('GET', '/super-admin/api/users/search', 'Nexus\Controllers\SuperAdmin\UserController@apiSearch');
$router->add('GET', '/super-admin/api/bulk/users', 'Nexus\Controllers\SuperAdmin\BulkController@apiGetUsers');
$router->add('GET', '/super-admin/api/audit', 'Nexus\Controllers\SuperAdmin\AuditController@apiLog');

$router->add('GET', '/super-admin/federation', 'Nexus\Controllers\SuperAdmin\FederationController@index');
$router->add('GET', '/super-admin/federation/system-controls', 'Nexus\Controllers\SuperAdmin\FederationController@systemControls');
$router->add('POST', '/super-admin/federation/update-system-controls', 'Nexus\Controllers\SuperAdmin\FederationController@updateSystemControls');
$router->add('POST', '/super-admin/federation/emergency-lockdown', 'Nexus\Controllers\SuperAdmin\FederationController@emergencyLockdown');
$router->add('POST', '/super-admin/federation/lift-lockdown', 'Nexus\Controllers\SuperAdmin\FederationController@liftLockdown');
$router->add('GET', '/super-admin/federation/whitelist', 'Nexus\Controllers\SuperAdmin\FederationController@whitelist');
$router->add('POST', '/super-admin/federation/add-to-whitelist', 'Nexus\Controllers\SuperAdmin\FederationController@addToWhitelist');
$router->add('POST', '/super-admin/federation/remove-from-whitelist', 'Nexus\Controllers\SuperAdmin\FederationController@removeFromWhitelist');
$router->add('GET', '/super-admin/federation/partnerships', 'Nexus\Controllers\SuperAdmin\FederationController@partnerships');
$router->add('POST', '/super-admin/federation/suspend-partnership', 'Nexus\Controllers\SuperAdmin\FederationController@suspendPartnership');
$router->add('POST', '/super-admin/federation/terminate-partnership', 'Nexus\Controllers\SuperAdmin\FederationController@terminatePartnership');
$router->add('GET', '/super-admin/federation/audit', 'Nexus\Controllers\SuperAdmin\FederationController@auditLog');
$router->add('GET', '/super-admin/federation/tenant/{id}', 'Nexus\Controllers\SuperAdmin\FederationController@tenantFeatures');
$router->add('POST', '/super-admin/federation/update-tenant-feature', 'Nexus\Controllers\SuperAdmin\FederationController@updateTenantFeature');
