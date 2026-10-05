<?php

/**
 * CalendarItem Model
 * Handles calendar items and notes for market trends
 */

class CalendarItem {
    private $conn;
    public $id;
    public $user_id;
    public $trend_id;
    public $calendar_date;
    public $note;
    public $created_at;
    public $updated_at;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Create or update calendar item
     */
    public function saveCalendarItem($user_id, $calendar_date, $note, $trend_id = null) {
        // Check if item already exists for this date and user
        $check_query = "SELECT id FROM calendar_items WHERE user_id = ? AND calendar_date = ? AND trend_id = ?";
        $check_stmt = $this->conn->prepare($check_query);
        if (!$check_stmt) {
            return false;
        }
        $check_stmt->bind_param('isi', $user_id, $calendar_date, $trend_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_stmt->close();

        if ($check_result->num_rows > 0) {
            // Update existing item
            $existing = $check_result->fetch_assoc();
            return $this->updateCalendarItem($existing['id'], $note);
        } else {
            // Create new item
            $query = "INSERT INTO calendar_items (user_id, trend_id, calendar_date, note) VALUES (?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('iiss', $user_id, $trend_id, $calendar_date, $note);
            if ($stmt->execute()) {
                return $this->conn->insert_id;
            }
            return false;
        }
    }

    /**
     * Update calendar item
     */
    private function updateCalendarItem($item_id, $note) {
        $query = "UPDATE calendar_items SET note = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $note, $item_id);
        return $stmt->execute();
    }

    /**
     * Get calendar items for a user on a specific date
     */
    public function getCalendarItemsByDate($user_id, $calendar_date) {
        $query = "SELECT ci.*, mt.title, mt.category, mt.impact_level 
                  FROM calendar_items ci
                  LEFT JOIN market_trends mt ON ci.trend_id = mt.id
                  WHERE ci.user_id = ? AND ci.calendar_date = ?
                  ORDER BY ci.created_at ASC";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('is', $user_id, $calendar_date);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get all calendar items for a user in a date range
     */
    public function getCalendarItemsByRange($user_id, $start_date, $end_date) {
        $query = "SELECT ci.*, mt.title, mt.category, mt.impact_level 
                  FROM calendar_items ci
                  LEFT JOIN market_trends mt ON ci.trend_id = mt.id
                  WHERE ci.user_id = ? AND ci.calendar_date BETWEEN ? AND ?
                  ORDER BY ci.calendar_date ASC, ci.created_at ASC";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('iss', $user_id, $start_date, $end_date);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get all calendar items for a user's month
     */
    public function getCalendarItemsByMonth($user_id, $year, $month) {
        $start_date = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
        $end_date = date('Y-m-t', strtotime($start_date));
        
        return $this->getCalendarItemsByRange($user_id, $start_date, $end_date);
    }

    /**
     * Delete calendar item
     */
    public function deleteCalendarItem($item_id, $user_id) {
        $query = "DELETE FROM calendar_items WHERE id = ? AND user_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $item_id, $user_id);
        return $stmt->execute();
    }

    /**
     * Get all market notes for a user
     */
    public function getMarketNotes($user_id, $limit = 50, $offset = 0) {
        $query = "SELECT * FROM market_notes 
                  WHERE user_id = ?
                  ORDER BY note_date DESC, created_at DESC
                  LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('iii', $user_id, $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get market notes for a specific date
     */
    public function getMarketNotesByDate($user_id, $note_date) {
        $query = "SELECT * FROM market_notes 
                  WHERE user_id = ? AND note_date = ?
                  ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('is', $user_id, $note_date);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Create market note
     */
    public function createMarketNote($user_id, $title, $content, $note_date) {
        $query = "INSERT INTO market_notes (user_id, title, content, note_date) 
                  VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('isss', $user_id, $title, $content, $note_date);
        if ($stmt->execute()) {
            return $this->conn->insert_id;
        }
        return false;
    }

    /**
     * Update market note
     */
    public function updateMarketNote($note_id, $user_id, $title, $content, $note_date) {
        $query = "UPDATE market_notes 
                  SET title = ?, content = ?, note_date = ?, updated_at = NOW() 
                  WHERE id = ? AND user_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('sssii', $title, $content, $note_date, $note_id, $user_id);
        return $stmt->execute();
    }

    /**
     * Delete market note
     */
    public function deleteMarketNote($note_id, $user_id) {
        $query = "DELETE FROM market_notes WHERE id = ? AND user_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $note_id, $user_id);
        return $stmt->execute();
    }

    /**
     * Get market note count for a user
     */
    public function getMarketNotesCount($user_id) {
        $query = "SELECT COUNT(*) as count FROM market_notes WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    }
}
?>
