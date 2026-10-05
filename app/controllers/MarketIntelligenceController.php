<?php

/**
 * Market Intelligence Controller
 * Handles all market intelligence routes and API endpoints
 */

class MarketIntelligenceController {
    private $conn;
    private $sessionMiddleware;
    private $marketTrendModel;
    private $pinnedTrendModel;
    private $calendarModel;
    private $marketService;
    private $baseUrl;

    public function __construct($conn, $sessionMiddleware = null, $baseUrl = '') {
        $this->conn = $conn;
        $this->sessionMiddleware = $sessionMiddleware;
        $this->baseUrl = $baseUrl;
        
        // Load models
        require_once __DIR__ . '/../models/MarketTrend.php';
        require_once __DIR__ . '/../models/PinnedTrend.php';
        require_once __DIR__ . '/../models/CalendarItem.php';
        require_once __DIR__ . '/../services/MarketTrendsService.php';
        
        $this->marketTrendModel = new MarketTrend($conn);
        $this->pinnedTrendModel = new PinnedTrend($conn);
        $this->calendarModel = new CalendarItem($conn);
        
        // Initialize service with API key if available
        $newsApiKey = getenv('MARKET_NEWS_API_KEY') ?: null;
        $this->marketService = new MarketTrendsService($conn, $newsApiKey);
    }

    /**
     * Show market intelligence main page
     */
    public function showPage() {
        $user = $this->requireLivestockOwner();
        
        // Only livestock_owner role can access Market Intelligence
        if ($user['role'] !== 'livestock_owner') {
            $_SESSION['error'] = 'Market Intelligence is only available for Livestock Owners';
            header('Location: ' . $this->baseUrl . '/dashboard');
            exit;
        }
        
        // Get initial trends
        $trends = $this->marketTrendModel->getAllTrends(10, 0);
        
        // Get pinned trend IDs for highlighting
        $pinnedTrendIds = $this->pinnedTrendModel->getUserPinnedTrendIds($user['id']);
        
        // Get categories for filters (but prioritize livestock/pork for this role)
        $categories = $this->marketTrendModel->getAllCategories();
        $impactLevels = $this->marketTrendModel->getAllImpactLevels();
        
        // Get pinned trends count
        $pinnedCount = $this->pinnedTrendModel->getUserPinnedTrendsCount($user['id']);
        
        // Set page content
        $pageTitle = 'Market Trends';
        $currentPage = 'market-intelligence';
        
        ob_start();
        require __DIR__ . '/../../resources/views/market-intelligence/index.php';
        $content = ob_get_clean();
        
        // Use dashboard layout
        require __DIR__ . '/../../resources/views/layouts/dashboard-layout.php';
    }

    /**
     * Check if user is livestock owner (required for all Market Intelligence operations)
     */
    private function requireLivestockOwner() {
        $user = $this->sessionMiddleware->getUser();
        
        if (!$user) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'User not authenticated'
            ]);
            exit;
        }
        
        if ($user['role'] !== 'livestock_owner') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Market Intelligence is only available for Livestock Owners'
            ]);
            exit;
        }
        
        return $user;
    }

    /**
     * API: Get trends with pagination
     */
    public function getTrends() {
        header('Content-Type: application/json');
        
        try {
            $user = $this->requireLivestockOwner();
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = 10;
            $offset = ($page - 1) * $limit;
            
            $category = $_GET['category'] ?? '';
            $search = $_GET['search'] ?? '';
            $sort = $_GET['sort'] ?? 'latest';
            
            if ($search) {
                $trends = $this->marketTrendModel->searchTrends($search, $limit, $offset);
            } elseif ($category) {
                $trends = $this->marketTrendModel->getTrendsByCategory($category, $limit, $offset);
            } else {
                $trends = $this->marketTrendModel->getAllTrends($limit, $offset);
            }
            
            // Get pinned status for each trend
            $pinnedIds = $this->pinnedTrendModel->getUserPinnedTrendIds($user['id']);
            foreach ($trends as &$trend) {
                $trend['is_pinned'] = in_array($trend['id'], $pinnedIds);
            }
            
            echo json_encode([
                'success' => true,
                'trends' => $trends,
                'page' => $page,
                'limit' => $limit
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * API: Get single trend details
     */
    public function getTrendDetails() {
        header('Content-Type: application/json');
        
        try {
            $user = $this->requireLivestockOwner();
            $trend_id = intval($_GET['id'] ?? 0);
            
            if (!$trend_id) {
                throw new Exception('Trend ID is required');
            }
            
            $trend = $this->marketTrendModel->getTrendById($trend_id);
            if (!$trend) {
                throw new Exception('Trend not found');
            }
            
            // Check if pinned
            $trend['is_pinned'] = $this->pinnedTrendModel->isTrendPinned($user['id'], $trend_id);
            
            echo json_encode([
                'success' => true,
                'trend' => $trend
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * API: Pin a trend
     */
    public function pinTrend() {
        header('Content-Type: application/json');
        
        try {
            $user = $this->requireLivestockOwner();
            $trend_id = intval($_POST['trend_id'] ?? 0);
            
            if (!$trend_id) {
                throw new Exception('Trend ID is required');
            }
            
            // Verify trend exists
            $trend = $this->marketTrendModel->getTrendById($trend_id);
            if (!$trend) {
                throw new Exception('Trend not found');
            }
            
            if ($this->pinnedTrendModel->pinTrend($user['id'], $trend_id)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Trend pinned successfully'
                ]);
            } else {
                throw new Exception('Failed to pin trend');
            }
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * API: Unpin a trend
     */
    public function unpinTrend() {
        header('Content-Type: application/json');
        
        try {
            $user = $this->requireLivestockOwner();
            $trend_id = intval($_POST['trend_id'] ?? 0);
            
            if (!$trend_id) {
                throw new Exception('Trend ID is required');
            }
            
            if ($this->pinnedTrendModel->unpinTrend($user['id'], $trend_id)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Trend unpinned successfully'
                ]);
            } else {
                throw new Exception('Failed to unpin trend');
            }
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * API: Get pinned trends
     */
    public function getPinnedTrends() {
        header('Content-Type: application/json');
        
        try {
            $user = $this->requireLivestockOwner();
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = 10;
            $offset = ($page - 1) * $limit;
            
            $trends = $this->pinnedTrendModel->getUserPinnedTrends($user['id'], $limit, $offset);
            $count = $this->pinnedTrendModel->getUserPinnedTrendsCount($user['id']);
            
            echo json_encode([
                'success' => true,
                'trends' => $trends,
                'count' => $count,
                'page' => $page,
                'limit' => $limit
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * API: Save calendar item
     */
    public function saveCalendarItem() {
        header('Content-Type: application/json');
        
        try {
            $user = $this->requireLivestockOwner();
            $calendar_date = $_POST['date'] ?? '';
            $note = $_POST['note'] ?? '';
            $trend_id = !empty($_POST['trend_id']) ? intval($_POST['trend_id']) : null;
            
            if (!$calendar_date) {
                throw new Exception('Date is required');
            }
            
            if ($this->calendarModel->saveCalendarItem($user['id'], $calendar_date, $note, $trend_id)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Calendar item saved successfully'
                ]);
            } else {
                throw new Exception('Failed to save calendar item');
            }
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * API: Get calendar items for a date
     */
    public function getCalendarItems() {
        header('Content-Type: application/json');
        
        try {
            $user = $this->requireLivestockOwner();
            $date = $_GET['date'] ?? date('Y-m-d');
            
            $items = $this->calendarModel->getCalendarItemsByDate($user['id'], $date);
            
            echo json_encode([
                'success' => true,
                'items' => $items,
                'date' => $date
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * API: Get calendar items for a month
     */
    public function getCalendarMonth() {
        header('Content-Type: application/json');
        
        try {
            $user = $this->requireLivestockOwner();
            $year = intval($_GET['year'] ?? date('Y'));
            $month = intval($_GET['month'] ?? date('m'));
            
            $items = $this->calendarModel->getCalendarItemsByMonth($user['id'], $year, $month);
            
            echo json_encode([
                'success' => true,
                'items' => $items,
                'year' => $year,
                'month' => $month
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * API: Delete calendar item
     */
    public function deleteCalendarItem() {
        header('Content-Type: application/json');
        
        try {
            $user = $this->requireLivestockOwner();
            $item_id = intval($_POST['item_id'] ?? 0);
            
            if (!$item_id) {
                throw new Exception('Item ID is required');
            }
            
            if ($this->calendarModel->deleteCalendarItem($item_id, $user['id'])) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Calendar item deleted successfully'
                ]);
            } else {
                throw new Exception('Failed to delete calendar item');
            }
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * API: Create market note
     */
    public function createNote() {
        header('Content-Type: application/json');
        
        try {
            $user = $this->requireLivestockOwner();
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $note_date = $_POST['date'] ?? date('Y-m-d');
            
            if (!$title || !$content) {
                throw new Exception('Title and content are required');
            }
            
            if ($this->calendarModel->createMarketNote($user['id'], $title, $content, $note_date)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Note created successfully'
                ]);
            } else {
                throw new Exception('Failed to create note');
            }
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * API: Get market notes
     */
    public function getNotes() {
        header('Content-Type: application/json');
        
        try {
            $user = $this->requireLivestockOwner();
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
            $notes = $this->calendarModel->getMarketNotes($user['id'], $limit, $offset);
            $count = $this->calendarModel->getMarketNotesCount($user['id']);
            
            echo json_encode([
                'success' => true,
                'notes' => $notes,
                'count' => $count,
                'page' => $page,
                'limit' => $limit
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * API: Update market note
     */
    public function updateNote() {
        header('Content-Type: application/json');
        
        try {
            $user = $this->requireLivestockOwner();
            $note_id = intval($_POST['note_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $note_date = $_POST['date'] ?? date('Y-m-d');
            
            if (!$note_id || !$title || !$content) {
                throw new Exception('All fields are required');
            }
            
            if ($this->calendarModel->updateMarketNote($note_id, $user['id'], $title, $content, $note_date)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Note updated successfully'
                ]);
            } else {
                throw new Exception('Failed to update note');
            }
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * API: Delete market note
     */
    public function deleteNote() {
        header('Content-Type: application/json');
        
        try {
            $user = $this->requireLivestockOwner();
            $note_id = intval($_POST['note_id'] ?? 0);
            
            if (!$note_id) {
                throw new Exception('Note ID is required');
            }
            
            if ($this->calendarModel->deleteMarketNote($note_id, $user['id'])) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Note deleted successfully'
                ]);
            } else {
                throw new Exception('Failed to delete note');
            }
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * API: Refresh trends (manual refresh)
     */
    public function refreshTrends() {
        header('Content-Type: application/json');
        
        try {
            $user = $this->requireLivestockOwner();
            $forceRefresh = $_POST['force'] ?? false;
            
            // Try to fetch new trends
            $new_trends = $this->marketService->fetchFromNewsApi();
            
            if ($new_trends && count($new_trends) > 0) {
                // Save new trends
                foreach ($new_trends as $trend) {
                    $this->marketService->saveTrend($trend);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Trends updated successfully',
                    'trends_count' => count($new_trends),
                    'last_update' => date('Y-m-d H:i:s')
                ]);
            } else {
                // No new trends from API, but that's not necessarily an error
                echo json_encode([
                    'success' => true,
                    'message' => 'No new trends available at this time',
                    'trends_count' => 0,
                    'last_update' => $this->marketService->getLastUpdateTimestamp()
                ]);
            }
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * API: Get last update info
     */
    public function getLastUpdate() {
        header('Content-Type: application/json');
        
        try {
            $user = $this->requireLivestockOwner();
            $lastUpdate = $this->marketService->getLastUpdateTimestamp();
            
            if ($lastUpdate) {
                $timestamp = strtotime($lastUpdate);
                $formatted = date('F j, Y • g:i A', $timestamp);
                $relative = $this->getRelativeTime($lastUpdate);
                
                echo json_encode([
                    'success' => true,
                    'last_update' => $lastUpdate,
                    'formatted' => $formatted,
                    'relative' => $relative,
                    'is_live' => true
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'last_update' => null,
                    'formatted' => 'Never',
                    'relative' => 'No live updates yet',
                    'is_live' => false
                ]);
            }
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * API: Get trends by date filter
     */
    public function getTrendsByDateFilter() {
        header('Content-Type: application/json');
        
        try {
            $filter = $_GET['filter'] ?? 'latest'; // today, yesterday, last7, last30, all
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = 10;
            $offset = ($page - 1) * $limit;

            $startDate = null;
            $endDate = date('Y-m-d');

            switch ($filter) {
                case 'today':
                    $startDate = date('Y-m-d');
                    break;
                case 'yesterday':
                    $startDate = date('Y-m-d', strtotime('-1 day'));
                    $endDate = date('Y-m-d', strtotime('-1 day'));
                    break;
                case 'last7':
                    $startDate = date('Y-m-d', strtotime('-7 days'));
                    break;
                case 'last30':
                    $startDate = date('Y-m-d', strtotime('-30 days'));
                    break;
                case 'all':
                    $startDate = '2000-01-01';
                    break;
                case 'latest':
                default:
                    $startDate = date('Y-m-d', strtotime('-7 days'));
                    break;
            }

            $trends = $this->marketTrendModel->getTrendsByDateRange($startDate, $endDate, $limit, $offset);
            $count = $this->marketTrendModel->getTrendCountByDateRange($startDate, $endDate);

            // Get pinned status
            $user = $this->requireLivestockOwner();
            $pinnedIds = $this->pinnedTrendModel->getUserPinnedTrendIds($user['id']);
            foreach ($trends as &$trend) {
                $trend['is_pinned'] = in_array($trend['id'], $pinnedIds);
            }

            echo json_encode([
                'success' => true,
                'trends' => $trends,
                'count' => $count,
                'filter' => $filter,
                'page' => $page,
                'limit' => $limit
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Get relative time string (e.g., "2 hours ago")
     */
    private function getRelativeTime($date) {
        $time = strtotime($date);
        $now = time();
        $diff = $now - $time;

        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M j, Y', $time);
        }
    }
}
?>

