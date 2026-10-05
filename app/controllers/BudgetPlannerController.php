<?php

require_once __DIR__ . '/../models/BudgetPlanner.php';

class BudgetPlannerController {
    private $conn;
    private $budgetPlanner;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->budgetPlanner = new BudgetPlanner($this->conn);
    }

    /**
     * Save budget record (create or update)
     */
    public function save() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        // Check if user is logged in
        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }

        $user_id = $_SESSION['user']['id'];
        $input = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        if (empty($input['holiday_event']) || 
            !isset($input['planned_budget']) || 
            !isset($input['family_members'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }

        // Calculate totals
        $total_planned_cost = 0;
        if (!empty($input['planned_items'])) {
            foreach ($input['planned_items'] as $item) {
                $total_planned_cost += ($item['price'] ?? 0) * ($item['qty'] ?? 1);
            }
        }

        $holiday_afc = 0;
        if (!empty($input['actual_items'])) {
            foreach ($input['actual_items'] as $item) {
                $holiday_afc += $item['amount'] ?? 0;
            }
        }

        $overspending_percent = 0;
        if ($input['planned_budget'] > 0) {
            $overspending_percent = (($holiday_afc - $input['planned_budget']) / $input['planned_budget']) * 100;
        }

        // Prepare data
        $data = [
            'user_id' => $user_id,
            'holiday_event' => $input['holiday_event'],
            'planned_budget' => $input['planned_budget'],
            'family_members' => $input['family_members'],
            'total_planned_cost' => $total_planned_cost,
            'holiday_afc' => $holiday_afc,
            'overspending_percent' => $overspending_percent,
            'status' => $input['status'] ?? 'planning',
            'planned_items' => $input['planned_items'] ?? [],
            'actual_items' => $input['actual_items'] ?? []
        ];

        // Create or update
        if (!empty($input['id'])) {
            // Update existing record
            $result = $this->budgetPlanner->update($input['id'], $data);
            if ($result) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Budget updated successfully',
                    'id' => $input['id']
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update budget']);
            }
        } else {
            // Create new record
            $id = $this->budgetPlanner->create($data);
            if ($id) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Budget saved successfully',
                    'id' => $id
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save budget']);
            }
        }
    }

    /**
     * Get all budget records for logged-in user
     */
    public function getRecords() {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }

        // Handle DELETE request for deleting a single record
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!empty($input['id'])) {
                $this->delete($input['id']);
                return;
            }
        }

        $user_id = $_SESSION['user']['id'];
        $records = $this->budgetPlanner->getByUserId($user_id);

        echo json_encode([
            'success' => true,
            'records' => $records
        ]);
    }

    /**
     * Get single budget record
     */
    public function getRecord($id) {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }

        $user_id = $_SESSION['user']['id'];
        $record = $this->budgetPlanner->getById($id, $user_id);

        if ($record) {
            echo json_encode([
                'success' => true,
                'record' => $record
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Record not found']);
        }
    }

    /**
     * Delete budget record
     */
    public function delete($id) {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }

        $user_id = $_SESSION['user']['id'];
        $result = $this->budgetPlanner->delete($id, $user_id);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Record deleted successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete record']);
        }
    }

    /**
     * Get total lechon spending for logged-in customer
     * Returns sum of all lechon order prices from lechon_orders table
     */
    public function getLechonSpending() {
        // Clean any accidental output (e.g. PHP notices/warnings) before JSON
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }

        $user_id = $_SESSION['user']['id'];

        try {
            // Check if lechon_orders table exists
            $tbl_check = $this->conn->query("SHOW TABLES LIKE 'lechon_orders'");
            if (!$tbl_check || $tbl_check->num_rows === 0) {
                echo json_encode(['success' => true, 'total_lechon_spending' => 0, 'order_count' => 0]);
                return;
            }

            $stmt = $this->conn->prepare(
                "SELECT COALESCE(SUM(price), 0) as total_spending, COUNT(*) as order_count
                 FROM lechon_orders
                 WHERE customer_id = ?
                 AND order_status NOT IN ('cancelled')"
            );

            if (!$stmt) {
                echo json_encode(['success' => true, 'total_lechon_spending' => 0, 'order_count' => 0]);
                return;
            }

            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            echo json_encode([
                'success' => true,
                'total_lechon_spending' => (float)($result['total_spending'] ?? 0),
                'order_count' => (int)($result['order_count'] ?? 0)
            ]);

        } catch (Exception $e) {
            echo json_encode(['success' => true, 'total_lechon_spending' => 0, 'order_count' => 0]);
        }
    }

    /**
     * Get user statistics
     */
    public function getStats() {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }

        $user_id = $_SESSION['user']['id'];
        $stats = $this->budgetPlanner->getUserStats($user_id);

        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
    }
}
