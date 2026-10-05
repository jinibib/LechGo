<?php

class BudgetPlanner {
    private $conn;
    private $table = 'budget_planner';

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create new budget record
     */
    public function create($data) {
        $query = "INSERT INTO {$this->table} 
                  (user_id, holiday_event, planned_budget, family_members, 
                   total_planned_cost, holiday_afc, overspending_percent, 
                   status, planned_items, actual_items) 
                  VALUES 
                  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            error_log("BudgetPlanner::create - Prepare error: " . $this->conn->error);
            return false;
        }

        // Convert arrays to JSON
        $planned_items_json = json_encode($data['planned_items'] ?? []);
        $actual_items_json = json_encode($data['actual_items'] ?? []);

        $stmt->bind_param(
            'isdiiddsss',
            $data['user_id'],
            $data['holiday_event'],
            $data['planned_budget'],
            $data['family_members'],
            $data['total_planned_cost'],
            $data['holiday_afc'],
            $data['overspending_percent'],
            $data['status'],
            $planned_items_json,
            $actual_items_json
        );

        if ($stmt->execute()) {
            $insert_id = $this->conn->insert_id;
            $stmt->close();
            return $insert_id;
        }

        error_log("BudgetPlanner::create - Execute error: " . $stmt->error);
        $stmt->close();
        return false;
    }

    /**
     * Update existing budget record
     */
    public function update($id, $data) {
        $query = "UPDATE {$this->table} 
                  SET holiday_event = ?,
                      planned_budget = ?,
                      family_members = ?,
                      total_planned_cost = ?,
                      holiday_afc = ?,
                      overspending_percent = ?,
                      status = ?,
                      planned_items = ?,
                      actual_items = ?
                  WHERE id = ? AND user_id = ?";

        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            error_log("BudgetPlanner::update - Prepare error: " . $this->conn->error);
            return false;
        }

        // Convert arrays to JSON
        $planned_items_json = json_encode($data['planned_items'] ?? []);
        $actual_items_json = json_encode($data['actual_items'] ?? []);

        $stmt->bind_param(
            'sdiiddsssii',
            $data['holiday_event'],
            $data['planned_budget'],
            $data['family_members'],
            $data['total_planned_cost'],
            $data['holiday_afc'],
            $data['overspending_percent'],
            $data['status'],
            $planned_items_json,
            $actual_items_json,
            $id,
            $data['user_id']
        );

        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Get all records for a user
     */
    public function getByUserId($user_id, $limit = null) {
        $query = "SELECT * FROM {$this->table} 
                  WHERE user_id = ? 
                  ORDER BY created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT ?";
        }

        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            error_log("BudgetPlanner::getByUserId - Prepare error: " . $this->conn->error);
            return [];
        }

        if ($limit) {
            $stmt->bind_param('ii', $user_id, $limit);
        } else {
            $stmt->bind_param('i', $user_id);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $records = [];

        while ($row = $result->fetch_assoc()) {
            $row['planned_items'] = json_decode($row['planned_items'], true) ?? [];
            $row['actual_items'] = json_decode($row['actual_items'], true) ?? [];
            $records[] = $row;
        }

        $stmt->close();
        return $records;
    }

    /**
     * Get single record by ID
     */
    public function getById($id, $user_id) {
        $query = "SELECT * FROM {$this->table} 
                  WHERE id = ? AND user_id = ?";

        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            error_log("BudgetPlanner::getById - Prepare error: " . $this->conn->error);
            return null;
        }

        $stmt->bind_param('ii', $id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $record = null;
        if ($result->num_rows > 0) {
            $record = $result->fetch_assoc();
            $record['planned_items'] = json_decode($record['planned_items'], true) ?? [];
            $record['actual_items'] = json_decode($record['actual_items'], true) ?? [];
        }

        $stmt->close();
        return $record;
    }

    /**
     * Delete record
     */
    public function delete($id, $user_id) {
        $query = "DELETE FROM {$this->table} 
                  WHERE id = ? AND user_id = ?";

        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            error_log("BudgetPlanner::delete - Prepare error: " . $this->conn->error);
            return false;
        }

        $stmt->bind_param('ii', $id, $user_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Get user statistics
     */
    public function getUserStats($user_id) {
        $query = "SELECT 
                    COUNT(*) as total_records,
                    AVG(overspending_percent) as avg_overspending,
                    SUM(CASE WHEN overspending_percent <= 10 THEN 1 ELSE 0 END) as within_target,
                    SUM(CASE WHEN overspending_percent > 10 THEN 1 ELSE 0 END) as above_target
                  FROM {$this->table} 
                  WHERE user_id = ? AND status = 'completed'";

        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            error_log("BudgetPlanner::getUserStats - Prepare error: " . $this->conn->error);
            return null;
        }

        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        $stmt->close();

        return $stats;
    }
}
