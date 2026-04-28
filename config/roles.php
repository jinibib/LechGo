<?php

/**
 * Role-Based Access Control Configuration
 * Defines roles, permissions, and route access control
 */

// Define all available roles in the system
define('ROLES', [
    'admin' => 'Administrator',
    'customer' => 'Customer',
    'lechonero' => 'Lechonero',
    'livestock_owner' => 'Livestock Owner',
    'supplier' => 'Feed Supplier',
    'pig_caretaker' => 'Pig Caretaker',
    'logistics' => 'Logistics/Driver',
    'feed_distributor' => 'Feed Distributor'
]);

// Define role-based permissions (which routes are accessible)
const ROLE_PERMISSIONS = [
    'admin' => [
        'dashboard',
        'home',
        'admin/*',
        'locations',
        'complete-profile',
        'auth/complete-profile',
    ],
    'customer' => [
        'dashboard',
        'home',
        'customer/*',
        'locations',
        'browse-lechon',
        'reserve-order',
        'payment',
        'orders',
        'complete-profile',
        'auth/complete-profile',
    ],
    'lechonero' => [
        'dashboard',
        'home',
        'lechonero/*',
        'cooking-status',
        'schedule',
        'orders',
        'locations',
        'complete-profile',
        'auth/complete-profile',
    ],
    'livestock_owner' => [
        'dashboard',
        'home',
        'livestock-owner/*',
        'available-feeds',
        'orders',
        'payments',
        'caretaker-reports',
        'locations',
        'complete-profile',
        'auth/complete-profile',
    ],
    'supplier' => [
        'dashboard',
        'home',
        'supplier/*',
        'product-inventory',
        'orders',
        'locations',
        'complete-profile',
        'auth/complete-profile',
    ],
    'pig_caretaker' => [
        'dashboard',
        'home',
        'pig-caretaker/*',
        'feed-inventory',
        'pigs',
        'feeding-schedule',
        'farm-profile',
        'received-orders',
        'locations',
        'complete-profile',
        'auth/complete-profile',
    ],
    'logistics' => [
        'dashboard',
        'home',
        'logistics/*',
        'delivery-status',
        'locations',
    ],
    'feed_distributor' => [
        'dashboard',
        'home',
        'feed-distributor/*',
        'locations',
        'complete-profile',
        'auth/complete-profile',
    ]
];

// Define dashboard redirects for each role
const ROLE_DASHBOARDS = [
    'admin' => '/home',
    'customer' => '/home',
    'lechonero' => '/home',
    'livestock_owner' => '/home',
    'supplier' => '/home',
    'pig_caretaker' => '/home',
    'logistics' => '/home',
    'feed_distributor' => '/home'
];

/**
 * Get all available roles
 */
function getAllRoles() {
    return ROLES;
}

/**
 * Check if role exists
 */
function isValidRole($role) {
    return isset(ROLES[$role]);
}

/**
 * Get permissions for a role
 */
function getRolePermissions($role) {
    return ROLE_PERMISSIONS[$role] ?? [];
}

/**
 * Get dashboard URL for a role
 */
function getRoleDashboard($role) {
    return ROLE_DASHBOARDS[$role] ?? '/home';
}

/**
 * Check if a route matches a permission pattern
 * Supports wildcards (e.g., 'customer/*' matches 'customer/orders', 'customer/dashboard')
 */
function routeMatchesPermission($route, $permission) {
    // Exact match
    if ($route === $permission) {
        return true;
    }

    // Wildcard match
    if (strpos($permission, '*') !== false) {
        $pattern = str_replace('\*', '.*', preg_quote($permission, '/'));
        return preg_match('/^' . $pattern . '$/', $route) === 1;
    }

    return false;
}

/**
 * Check if role has permission to access route
 */
function hasRoutePermission($role, $route) {
    if (!isValidRole($role)) {
        return false;
    }

    $permissions = getRolePermissions($role);

    foreach ($permissions as $permission) {
        if (routeMatchesPermission($route, $permission)) {
            return true;
        }
    }

    return false;
}
