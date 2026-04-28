<?php

/**
 * Role-Based Access Control Middleware
 * Handles authorization checks for protected routes
 */

class RBACMiddleware
{
    private $session;
    private $baseUrl;

    public function __construct($session, $baseUrl = '/LechGo_Final/public')
    {
        $this->session = $session;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Check if user can access a route
     * Returns true if authorized, false otherwise
     */
    public function canAccess($route)
    {
        // User must be authenticated
        if (!$this->session->isAuthenticated()) {
            return false;
        }

        $user = $this->session->getUser();
        $role = $user['role'] ?? null;

        if (!$role || !isValidRole($role)) {
            return false;
        }

        // Check if route is permitted for this role
        return hasRoutePermission($role, $route);
    }

    /**
     * Verify access to a route and redirect if unauthorized
     * If authorized, returns user data. If unauthorized, redirects and exits.
     */
    public function authorize($route)
    {
        if (!$this->session->isAuthenticated()) {
            $_SESSION['error'] = 'Please log in to continue';
            header('Location: ' . $this->baseUrl . '/login');
            exit;
        }

        $user = $this->session->getUser();
        $role = $user['role'] ?? null;

        if (!$role || !isValidRole($role)) {
            $_SESSION['error'] = 'Invalid user role';
            $this->redirectToDashboard($role);
        }

        if (!hasRoutePermission($role, $route)) {
            $_SESSION['error'] = 'You do not have permission to access this page';
            $this->redirectToDashboard($role);
        }

        return $user;
    }

    /**
     * Redirect user to their role-specific dashboard
     */
    public function redirectToDashboard($role = null)
    {
        if (!$role) {
            $user = $this->session->getUser();
            $role = $user['role'] ?? 'customer';
        }

        $dashboard = getRoleDashboard($role);
        header('Location: ' . $this->baseUrl . $dashboard);
        exit;
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole($requiredRole)
    {
        if (!$this->session->isAuthenticated()) {
            return false;
        }

        $user = $this->session->getUser();
        return isset($user['role']) && $user['role'] === $requiredRole;
    }

    /**
     * Check if user has any of the specified roles
     */
    public function hasAnyRole(array $roles)
    {
        if (!$this->session->isAuthenticated()) {
            return false;
        }

        $user = $this->session->getUser();
        return isset($user['role']) && in_array($user['role'], $roles);
    }

    /**
     * Check if user has all of the specified roles (usually just one, but for flexibility)
     */
    public function hasAllRoles(array $roles)
    {
        if (!$this->session->isAuthenticated()) {
            return false;
        }

        $user = $this->session->getUser();
        if (!isset($user['role'])) {
            return false;
        }

        foreach ($roles as $role) {
            if ($user['role'] !== $role) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get current user's role
     */
    public function getUserRole()
    {
        if (!$this->session->isAuthenticated()) {
            return null;
        }

        $user = $this->session->getUser();
        return $user['role'] ?? null;
    }

    /**
     * Check if route requires authentication
     */
    public static function isProtectedRoute($route)
    {
        // Define routes that don't require authentication
        $publicRoutes = [
            'landing',
            'login',
            'register',
            'verify-email',
            'verify-otp',
            'select-role',
            'setup-database',
            'auth/register',
            'auth/login',
            'auth/verify-otp',
            'auth/select-role',
            'auth/resend-verification',
            'auth/resend-otp',
            'logout',
            'api/locations',
            'api/create-payment-intent',
            'api/attach-payment-method',
            'api/',
        ];

        foreach ($publicRoutes as $publicRoute) {
            if ($route === $publicRoute || strpos($route, $publicRoute . '/') === 0) {
                return false;
            }
        }

        return true;
    }
}
