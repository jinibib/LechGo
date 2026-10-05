<?php
// Move POST processing to the very top to ensure clean headers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    error_log("POST received with data: " . json_encode($_POST));
    
    // Keep database errors from turning the page into a blank white screen.
    mysqli_report(MYSQLI_REPORT_OFF);

    // Include required files
    include_once __DIR__ . '/../../config/db.php';

    $action = trim($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $batch_name = trim($_POST['batch_name'] ?? '') ?: date('F Y');

    $allowedActions = ['add_opex', 'edit_opex', 'add_capex', 'edit_capex', 'add_toc', 'edit_toc', 'delete'];

    if (in_array($action, $allowedActions, true)) {
        // Get user and owner info
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: /public/login');
            exit;
        }

        $stmt = $GLOBALS['conn']->prepare('SELECT id FROM livestock_owners WHERE user_id = ?');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $owner = $result->fetch_assoc();
        $stmt->close();

        if (!$owner) {
            $_SESSION['error'] = 'Livestock owner profile not found';
            header('Location: /livestock-owner/inventory-logs');
            exit;
        }

        // Process the form data
        try {
            $conn = $GLOBALS['conn'];

            // Read the actual inventory_logs columns first
            $columns = [];
            $columnResult = $conn->query("SHOW COLUMNS FROM inventory_logs");
            if (!$columnResult) {
                throw new Exception('Unable to read inventory_logs structure: ' . $conn->error);
            }
            while ($column = $columnResult->fetch_assoc()) {
                $columns[$column['Field']] = true;
            }
            $columnResult->free();

            $has = static function(string $name) use ($columns): bool {
                return isset($columns[$name]);
            };

            if (!$has('livestock_owner_id') || !$has('id')) {
                throw new Exception('inventory_logs table is missing required columns.');
            }

            // DELETE
            if ($action === 'delete') {
                if ($id <= 0) {
                    throw new Exception('Invalid entry ID.');
                }

                $stmt = $conn->prepare("DELETE FROM inventory_logs WHERE id=? AND livestock_owner_id=?");
                if (!$stmt) {
                    throw new Exception('Delete prepare failed: ' . $conn->error);
                }
                $stmt->bind_param('ii', $id, $owner['id']);
                if (!$stmt->execute()) {
                    throw new Exception('Delete failed: ' . $stmt->error);
                }
                $deleted = $stmt->affected_rows > 0;
                $stmt->close();

                $_SESSION[$deleted ? 'success' : 'error'] = $deleted
                    ? 'Entry deleted successfully!'
                    : 'Entry not found or already deleted.';

                header('Location: /livestock-owner/inventory-logs');
                exit;
            }

            // Build column values for INSERT/UPDATE
            $values = [];
            if ($has('batch_name')) $values['batch_name'] = $batch_name;

            if ($action === 'add_opex' || $action === 'edit_opex') {
                $values += [
                    'feed_cost' => (float)($_POST['feed_cost'] ?? 0),
                    'biologics_cost' => (float)($_POST['biologics_cost'] ?? 0),
                    'fuel_cost' => (float)($_POST['fuel_cost'] ?? 0),
                    'labor_cost' => (float)($_POST['labor_cost'] ?? 0),
                    'water_cost' => (float)($_POST['water_cost'] ?? 0),
                    'electricity_cost' => (float)($_POST['electricity_cost'] ?? 0),
                ];
                $successMessage = 'OPEX entry updated successfully!';
                if ($action === 'add_opex') $successMessage = 'OPEX entry added successfully!';
            } elseif ($action === 'add_capex' || $action === 'edit_capex') {
                $values += [
                    'building_depreciation' => (float)($_POST['building_depreciation'] ?? 0),
                    'equipment_depreciation' => (float)($_POST['equipment_depreciation'] ?? 0),
                    'permits_taxes' => (float)($_POST['permits_taxes'] ?? 0),
                ];
                $successMessage = 'CAPEX entry updated successfully!';
                if ($action === 'add_capex') $successMessage = 'CAPEX entry added successfully!';
            } else { // TOC
                $values += [
                    'capital_opportunity_cost' => (float)($_POST['capital_opportunity_cost'] ?? 0),
                    'land_rent_opportunity' => (float)($_POST['land_rent_opportunity'] ?? 0),
                ];
                $successMessage = 'TOC entry updated successfully!';
                if ($action === 'add_toc') $successMessage = 'TOC entry added successfully!';
            }

            // Keep only fields available in the actual database
            $values = array_filter(
                $values,
                static fn($value, $key) => isset($columns[$key]),
                ARRAY_FILTER_USE_BOTH
            );

            if (!$values) {
                throw new Exception('None of the submitted fields exist in inventory_logs.');
            }

            // INSERT or UPDATE
            if (str_starts_with($action, 'add_')) {
                // INSERT
                $insertColumns = [];
                $placeholders = [];
                $types = '';
                $params = [];

                if ($has('livestock_owner_id')) {
                    $insertColumns[] = 'livestock_owner_id';
                    $placeholders[] = '?';
                    $types .= 'i';
                    $params[] = $owner['id'];
                }
                if ($has('log_date')) {
                    $insertColumns[] = 'log_date';
                    $placeholders[] = 'NOW()';
                }

                foreach ($values as $column => $value) {
                    $insertColumns[] = $column;
                    $placeholders[] = '?';
                    if ($column === 'batch_name') {
                        $types .= 's';
                    } else {
                        $types .= 'd';
                    }
                    $params[] = $value;
                }

                $query = 'INSERT INTO inventory_logs (' . implode(', ', $insertColumns) .
                         ') VALUES (' . implode(', ', $placeholders) . ')';
                
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception('Insert prepare failed: ' . $conn->error);
                }

                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
            } else {
                // UPDATE
                if ($id <= 0) {
                    throw new Exception('Invalid entry ID for update.');
                }

                $sets = [];
                $types = '';
                $params = [];

                foreach ($values as $column => $value) {
                    $sets[] = "$column = ?";
                    if ($column === 'batch_name') {
                        $types .= 's';
                    } else {
                        $types .= 'd';
                    }
                    $params[] = $value;
                }
                $types .= 'ii';
                $params[] = $id;
                $params[] = $owner['id'];

                $query = 'UPDATE inventory_logs SET ' . implode(', ', $sets) .
                         ' WHERE id=? AND livestock_owner_id=?';
                
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception('Update prepare failed: ' . $conn->error);
                }

                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
            }

            if (!$stmt->execute()) {
                throw new Exception('Save failed: ' . $stmt->error);
            }
            $stmt->close();

            $_SESSION['success'] = $successMessage;
            header('Location: /livestock-owner/inventory-logs');
            exit;

        } catch (Throwable $e) {
            error_log("Inventory log save error: " . $e->getMessage());
            $_SESSION['error'] = 'Save error: ' . $e->getMessage();
            header('Location: /livestock-owner/inventory-logs');
            exit;
        }
    }
}

// Continue with normal page display
error_reporting(E_ALL);
ini_set('display_errors', 1);

$currentPage = 'inventory-logs';

$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Location:/public/login');
    exit;
}

// Get livestock owner ID first
$query = "SELECT id FROM livestock_owners WHERE user_id = ?";
$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) {
    $_SESSION['error'] = 'Database error: ' . $GLOBALS['conn']->error;
    header('Location:/public/dashboard');
    exit;
}
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$owner = $result->fetch_assoc();
$stmt->close();

if (!$owner) {
    $_SESSION['error'] = 'Livestock owner profile not found';
    header('Location:/public/dashboard');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST received with data: " . json_encode($_POST));
    
    // Keep database errors from turning the page into a blank white screen.
    mysqli_report(MYSQLI_REPORT_OFF);

    $action = trim($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $batch_name = trim($_POST['batch_name'] ?? '') ?: date('F Y');

    $allowedActions = ['add_opex', 'edit_opex', 'add_capex', 'edit_capex', 'add_toc', 'edit_toc', 'delete'];

    if (in_array($action, $allowedActions, true)) {
        try {
            $conn = $GLOBALS['conn'];

            // Read the actual inventory_logs columns first. This makes the page safe even
            // when the hosting database has an older version of the table.
            $columns = [];
            $columnResult = $conn->query("SHOW COLUMNS FROM inventory_logs");
            if (!$columnResult) {
                throw new Exception('Unable to read inventory_logs structure: ' . $conn->error);
            }
            while ($column = $columnResult->fetch_assoc()) {
                $columns[$column['Field']] = true;
            }
            $columnResult->free();

            $has = static function(string $name) use ($columns): bool {
                return isset($columns[$name]);
            };

            if (!$has('livestock_owner_id') || !$has('id')) {
                throw new Exception('inventory_logs table is missing required columns.');
            }

            // DELETE
            if ($action === 'delete') {
                if ($id <= 0) {
                    throw new Exception('Invalid entry ID.');
                }

                $stmt = $conn->prepare(
                    "DELETE FROM inventory_logs WHERE id=? AND livestock_owner_id=?"
                );
                if (!$stmt) {
                    throw new Exception('Delete prepare failed: ' . $conn->error);
                }
                $stmt->bind_param('ii', $id, $owner['id']);
                if (!$stmt->execute()) {
                    throw new Exception('Delete failed: ' . $stmt->error);
                }
                $deleted = $stmt->affected_rows > 0;
                $stmt->close();

                $_SESSION[$deleted ? 'success' : 'error'] = $deleted
                    ? 'Entry deleted successfully!'
                    : 'Entry not found or already deleted.';

                if (!headers_sent()) {
                    header('Location: /livestock-owner/inventory-logs', true, 303);
                    exit;
                }
                echo '<script>window.location.replace("/livestock-owner/inventory-logs");</script>';
                exit;
            }

            // Build only the columns that really exist in the live database.
            $values = [];
            if ($has('batch_name')) $values['batch_name'] = $batch_name;

            if ($action === 'add_opex' || $action === 'edit_opex') {
                $values += [
                    'feed_cost' => (float)($_POST['feed_cost'] ?? 0),
                    'biologics_cost' => (float)($_POST['biologics_cost'] ?? 0),
                    'fuel_cost' => (float)($_POST['fuel_cost'] ?? 0),
                    'labor_cost' => (float)($_POST['labor_cost'] ?? 0),
                    'water_cost' => (float)($_POST['water_cost'] ?? 0),
                    'electricity_cost' => (float)($_POST['electricity_cost'] ?? 0),
                ];
                $successMessage = 'OPEX entry updated successfully!';
                if ($action === 'add_opex') $successMessage = 'OPEX entry added successfully!';
            } elseif ($action === 'add_capex' || $action === 'edit_capex') {
                $values += [
                    'building_depreciation' => (float)($_POST['building_depreciation'] ?? 0),
                    'equipment_depreciation' => (float)($_POST['equipment_depreciation'] ?? 0),
                    'permits_taxes' => (float)($_POST['permits_taxes'] ?? 0),
                ];
                $successMessage = 'CAPEX entry updated successfully!';
                if ($action === 'add_capex') $successMessage = 'CAPEX entry added successfully!';
            } else { // TOC
                $values += [
                    'capital_opportunity_cost' => (float)($_POST['capital_opportunity_cost'] ?? 0),
                    'land_rent_opportunity' => (float)($_POST['land_rent_opportunity'] ?? 0),
                ];
                $successMessage = 'TOC entry updated successfully!';
                if ($action === 'add_toc') $successMessage = 'TOC entry added successfully!';
            }

            // Keep only fields available in the actual database.
            $values = array_filter(
                $values,
                static fn($value, $key) => isset($columns[$key]),
                ARRAY_FILTER_USE_BOTH
            );

            if (!$values) {
                throw new Exception('None of the submitted fields exist in inventory_logs.');
            }

            if (str_starts_with($action, 'add_')) {
                // log_date is required by the existing page and table.
                $insertColumns = [];
                $placeholders = [];
                $types = '';
                $params = [];

                if ($has('livestock_owner_id')) {
                    $insertColumns[] = 'livestock_owner_id';
                    $placeholders[] = '?';
                    $types .= 'i';
                    $params[] = $owner['id'];
                }
                if ($has('log_date')) {
                    $insertColumns[] = 'log_date';
                    $placeholders[] = 'NOW()';
                }

                foreach ($values as $column => $value) {
                    $insertColumns[] = $column;
                    $placeholders[] = '?';
                    if ($column === 'batch_name') {
                        $types .= 's';
                    } else {
                        $types .= 'd';
                    }
                    $params[] = $value;
                }

                $query = 'INSERT INTO inventory_logs (' . implode(', ', $insertColumns) .
                         ') VALUES (' . implode(', ', $placeholders) . ')';
                
                error_log("INSERT Query: " . $query);
                error_log("INSERT Params: " . json_encode($params));
                
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception('Insert prepare failed: ' . $conn->error);
                }

                if (!empty($params)) {
                    // Use array unpacking instead of call_user_func_array for PHP 8+ compatibility
                    try {
                        $stmt->bind_param($types, ...$params);
                    } catch (Error $e) {
                        error_log("Bind param error: " . $e->getMessage());
                        throw new Exception("Parameter binding failed: " . $e->getMessage());
                    }
                } else {
                    error_log("No parameters to bind for INSERT query");
                }
            } else {
                if ($id <= 0) {
                    throw new Exception('Invalid entry ID for update.');
                }

                $sets = [];
                $types = '';
                $params = [];
                foreach ($values as $column => $value) {
                    $sets[] = $column . '=?';
                    $types .= ($column === 'batch_name') ? 's' : 'd';
                    $params[] = $value;
                }
                $types .= 'ii';
                $params[] = $id;
                $params[] = $owner['id'];

                $query = 'UPDATE inventory_logs SET ' . implode(', ', $sets) .
                         ' WHERE id=? AND livestock_owner_id=?';
                
                error_log("UPDATE Query: " . $query);
                error_log("UPDATE Params: " . json_encode($params));
                
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception('Update prepare failed: ' . $conn->error);
                }

                if (!empty($params)) {
                    // Use array unpacking instead of call_user_func_array for PHP 8+ compatibility
                    try {
                        $stmt->bind_param($types, ...$params);
                    } catch (Error $e) {
                        error_log("Bind param error: " . $e->getMessage());
                        throw new Exception("Parameter binding failed: " . $e->getMessage());
                    }
                } else {
                    error_log("No parameters to bind for UPDATE query");
                }
            }

            if (!$stmt->execute()) {
                throw new Exception(($action === 'delete' ? 'Delete' : 'Save') . ' failed: ' . $stmt->error);
            }
            $stmt->close();

            $_SESSION['success'] = $successMessage;

            // Debug: Check if headers can be sent
            if (headers_sent($file, $line)) {
                error_log("Headers already sent in file: $file on line: $line");
                echo "<script>
                    alert('Success: $successMessage');
                    setTimeout(function() {
                        window.location.href = '/livestock-owner/inventory-logs';
                    }, 100);
                </script>";
                exit;
            }

            // 303 forces the browser to GET the inventory page after saving.
            // This prevents duplicate submissions and ensures the updated row appears immediately.
            header('Location: /livestock-owner/inventory-logs', true, 303);
            exit;
        } catch (Throwable $e) {
            error_log("Inventory log save error: " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            $_SESSION['error'] = 'Save error: ' . $e->getMessage();

            // Do NOT leave the user on a blank POST response.
            if (!headers_sent()) {
                header('Location: /livestock-owner/inventory-logs', true, 303);
                exit;
            } else {
                echo "<script>
                    alert('Error: " . addslashes($e->getMessage()) . "');
                    window.location.replace('/livestock-owner/inventory-logs');
                </script>";
                exit;
            }
        }
    }
}

// Get total live weight from pig_details table - ALL pigs with weight
$total_live_weight = 0;
$pig_weight_query = "SELECT COALESCE(SUM(weight_kg), 0) as total_weight
                     FROM pig_details
                     WHERE weight_kg > 0";
$stmt = $GLOBALS['conn']->prepare($pig_weight_query);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $weight_data = $result->fetch_assoc();
    $total_live_weight = $weight_data['total_weight'] ?? 0;
    $stmt->close();
}

// Get all inventory logs
$logs = [];
$logs_query = "SELECT * FROM inventory_logs 
               WHERE livestock_owner_id = ? 
               ORDER BY log_date DESC, created_at DESC";
$stmt = $GLOBALS['conn']->prepare($logs_query);
if ($stmt) {
    $stmt->bind_param('i', $owner['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC) ?? [];
    $stmt->close();
}

ob_start();
?>
<style>
/* Modern UI Styles for Inventory Logs - Red Theme */
.inventory-logs-container {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    min-height: 100vh;
    padding: 2rem;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.page-header {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
    border-radius: 20px;
    padding: 2.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 20px 40px rgba(220, 38, 38, 0.3);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-20px); }
}

.page-header h1 {
    font-size: 3rem;
    font-weight: 800;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    background: linear-gradient(45deg, #fff, #f0f9ff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.page-header p {
    font-size: 1.2rem;
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
    font-weight: 400;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

.summary-card {
    background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.summary-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #dc2626, #b91c1c);
}

.summary-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.summary-card .card-value {
    font-size: 2.5rem;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 0.5rem;
    color: #111827;
}

.summary-card .card-label {
    font-size: 1rem;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table-card {
    background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.3);
    margin-bottom: 3rem;
    position: relative;
    overflow: hidden;
}

.table-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 6px;
    background: linear-gradient(90deg, #dc2626, #b91c1c, #991b1b, #7f1d1d);
}

.table-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.btn-add-entry {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
    border: none;
    padding: 1rem 2rem;
    border-radius: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 8px 20px rgba(220, 38, 38, 0.3);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-add-entry:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 25px rgba(220, 38, 38, 0.4);
}

.table-responsive {
    border-radius: 16px;
    overflow-x: auto;
    overflow-y: hidden;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
}

.logs-table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
    font-size: 0.9rem;
    background: white;
    color: #111827;
    color: #111827;
}

.logs-table tbody td {
    color: #111827 !important;
    background: #ffffff;
}

.logs-table tbody tr:nth-child(even) td {
    background: #f8fafc;
}

.logs-table tbody td[style*="color"] {
    color: #111827 !important;
}

/* Force every data value in OPEX/CAPEX/TOC/Summary tables to readable black. */
.logs-table tbody td,
.logs-table tbody td *,
.logs-table tbody td strong,
.logs-table tbody td span {
    color: #111827 !important;
    -webkit-text-fill-color: #111827 !important;
    text-shadow: none !important;
}

.logs-table tbody td.highlight {
    color: #111827 !important;
    background: #f8fafc !important;
}

.logs-table tbody td.actions-cell,
.logs-table tbody td.actions-cell * {
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
}

.logs-table tbody .btn-action {
    color: #ffffff !important;
}

.logs-table th {
    padding: 1.5rem 1rem;
    text-align: left;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.8rem;
    border-bottom: 2px solid #e5e7eb;
    background: #fee2e2;
    color: #111827;
}

.logs-table td {
    padding: 1.25rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.2s ease;
}

.logs-table tbody tr:hover {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    transform: scale(1.01);
}

.logs-table tbody tr:hover td {
    border-color: #cbd5e1;
}

.highlight {
    font-weight: 700;
    font-size: 1.05rem;
}

.actions-cell {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.btn-action {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.btn-edit {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.btn-edit:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.btn-delete {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.btn-delete:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.glass-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.18);
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
}

.gradient-text {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.summary-total-cell, .summary-apc-cell {
    background: #f8fafc !important;
    color: #111827 !important;
    -webkit-text-fill-color: #111827 !important;
    font-weight: 700;
    font-size: 1.05rem;
}

/* Prevent the table from becoming clipped inside the card. */
.table-card {
    overflow: visible;
}

.table-responsive {
    overflow-x: auto;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
}

/* Responsive Design */
@media (max-width: 768px) {
    .inventory-logs-container {
        padding: 1rem;
    }
    
    .page-header {
        padding: 2rem 1.5rem;
        text-align: center;
    }
    
    .page-header h1 {
        font-size: 2.2rem;
    }
    
    .summary-cards {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .table-card {
        padding: 1.5rem;
        border-radius: 16px;
    }
    
    .logs-table {
        font-size: 0.8rem;
    }
    
    .logs-table th, .logs-table td {
        padding: 0.75rem 0.5rem;
    }
    
    .btn-add-entry {
        padding: 0.75rem 1.5rem;
        font-size: 0.8rem;
    }
}

.logs-table tfoot {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
    font-weight: 700;
}

.logs-table tfoot td {
    padding: 1.5rem 1rem;
    font-size: 1.1rem;
    border-top: 3px solid rgba(255, 255, 255, 0.2);
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
}

/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-overlay.active {
    display: flex;
}

.modal-container {
    background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
    border-radius: 20px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    transform: scale(0.8);
    transition: transform 0.3s ease;
}

.modal-overlay.active .modal-container {
    transform: scale(1);
}

.modal-header {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
    padding: 2rem;
    border-radius: 20px 20px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
}

.modal-close {
    background: none;
    border: none;
    color: white;
    font-size: 2rem;
    cursor: pointer;
    padding: 0;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: rotate(90deg);
}

.modal-body {
    padding: 2rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.form-group input {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: linear-gradient(135deg, #fff 0%, #fafafa 100%);
}

.form-group input:focus {
    outline: none;
    border-color: #dc2626;
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
    transform: scale(1.02);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 2px solid #f1f5f9;
}

.btn-primary {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
    border: none;
    padding: 0.75rem 2rem;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(220, 38, 38, 0.4);
}

.btn-secondary {
    background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
    color: white;
    border: none;
    padding: 0.75rem 2rem;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
}

.btn-secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(107, 114, 128, 0.4);
}

@media (max-width: 768px) {
    .modal-container {
        width: 95%;
        margin: 1rem;
    }
    
                            ₱<?php 
                                $totalTVC = 0;
                                foreach ($logs as $log) {
                                    $totalTVC += ($log['feed_cost'] ?? 0) + 
                                                 ($log['biologics_cost'] ?? 0) + 
                                                 ($log['fuel_cost'] ?? 0) + 
                                                 ($log['labor_cost'] ?? 0) + 
                                                 ($log['water_cost'] ?? 0) + 
                                                 ($log['electricity_cost'] ?? 0);
                                }
                                echo number_format($totalTVC, 2);
                            ?>
    
    .btn-primary, .btn-secondary {
        width: 100%;
    }
}

/* Readability fix: all table data/numbers use one dark text color. */
.logs-table th,
.logs-table td,
.logs-table td strong,
.logs-table td span,
.logs-table td .highlight {
    color: #111827 !important;
    -webkit-text-fill-color: #111827 !important;
    text-shadow: none !important;
}
.logs-table tbody tr td {
    background: #ffffff !important;
    color: #111827 !important;
}
.logs-table tbody tr:hover td {
    background: #f8fafc !important;
    color: #111827 !important;
}
.logs-table .highlight,
.logs-table td[style*="background"] {
    color: #111827 !important;
    -webkit-text-fill-color: #111827 !important;
}
.logs-table tfoot,
.logs-table tfoot td {
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
}
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.logs-table {
    min-width: 900px;
}
</style>
<div class="inventory-logs-container">
    <!-- Header -->
    <div class="page-header">
        <div class="header-left">
            <div class="header-text">
                <h1> Inventory Logs</h1>
                <p>Modern production cost tracking system with real-time analytics</p>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <?php if (!empty($logs)): ?>
        <div class="summary-cards">
            <div class="summary-card">
                <div class="card-icon"></div>
                <div class="card-content">
                    <div class="card-value"><?php echo count($logs); ?></div>
                    <div class="card-label">Total Logs</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="card-icon"></div>
                <div class="card-content">
                    <div class="card-value">₱<?php 
                        $totalProductionCost = 0;
                        foreach ($logs as $log) {
                            $tvc = ($log['feed_cost'] ?? 0) + ($log['biologics_cost'] ?? 0) + ($log['fuel_cost'] ?? 0) + ($log['labor_cost'] ?? 0) + ($log['water_cost'] ?? 0) + ($log['electricity_cost'] ?? 0);
                            $tfc = ($log['building_depreciation'] ?? 0) + ($log['equipment_depreciation'] ?? 0) + ($log['permits_taxes'] ?? 0);
                            $toc = ($log['capital_opportunity_cost'] ?? 0) + ($log['land_rent_opportunity'] ?? 0);
                            $totalProductionCost += $tvc + $tfc + $toc;
                        }
                        echo number_format($totalProductionCost, 2); 
                    ?></div>
                    <div class="card-label">Total Production Cost</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="card-icon"></div>
                <div class="card-content">
                    <div class="card-value">
                        <?php echo number_format($total_live_weight, 2) . ' kg'; ?>
                    </div>
                    <div class="card-label">Total Live Weight</div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Production Cost Tables - Always show these -->
    <!-- OPEX (Operating Expenses) Table -->
    <div class="table-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <div>
                <h2 class="table-title" style="margin: 0;"> OPEX - Operating Expenses (Variable Costs)</h2>
                <p style="margin: 0.25rem 0 0 0; color: #666; font-size: 0.9rem;">
                    Total Variable Costs (TVC) = Feed + Biologics + Fuel + Labor + Water + Electricity
                </p>
            </div>
            <button class="btn-add-entry" onclick="openAddOpexModal()" style="background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 700; cursor: pointer; white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; transition: all 0.3s;">
                ➕ ADD OPEX ENTRY
            </button>
        </div>
        <?php if (!empty($logs)): ?>
        <div class="table-responsive">
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>DATE</th>
                        <th>BATCH</th>
                        <th>FEED</th>
                        <th>BIOLOGICS</th>
                        <th>FUEL</th>
                        <th>LABOR</th>
                        <th>WATER</th>
                        <th>ELECTRICITY</th>
                        <th>TOTAL TVC</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): 
                        $tvc = ($log['feed_cost'] ?? 0) + 
                               ($log['biologics_cost'] ?? 0) + 
                               ($log['fuel_cost'] ?? 0) + 
                               ($log['labor_cost'] ?? 0) + 
                               ($log['water_cost'] ?? 0) + 
                               ($log['electricity_cost'] ?? 0);
                        
                        // Only show if OPEX fields have values
                        $has_opex = ($log['feed_cost'] ?? 0) > 0 ||
                                   ($log['biologics_cost'] ?? 0) > 0 ||
                                   ($log['fuel_cost'] ?? 0) > 0 ||
                                   ($log['labor_cost'] ?? 0) > 0 ||
                                   ($log['water_cost'] ?? 0) > 0 ||
                                   ($log['electricity_cost'] ?? 0) > 0;
                        
                        if (!$has_opex) continue;
                    ?>
                        <tr>
                            <td style="font-weight: 600;"><?php echo date('M d, Y', strtotime($log['log_date'])); ?></td>
                            <td style="font-weight: 600;"><?php echo htmlspecialchars($log['batch_name'] ?: 'N/A'); ?></td>
                            <td style="text-align: right; font-weight: 600;">₱<?php echo number_format($log['feed_cost'] ?? 0, 2); ?></td>
                            <td style="text-align: right; font-weight: 600;">₱<?php echo number_format($log['biologics_cost'] ?? 0, 2); ?></td>
                            <td style="text-align: right; font-weight: 600;">₱<?php echo number_format($log['fuel_cost'] ?? 0, 2); ?></td>
                            <td style="text-align: right; font-weight: 600;">₱<?php echo number_format($log['labor_cost'] ?? 0, 2); ?></td>
                            <td style="text-align: right; font-weight: 600;">₱<?php echo number_format($log['water_cost'] ?? 0, 2); ?></td>
                            <td style="text-align: right; font-weight: 600;">₱<?php echo number_format($log['electricity_cost'] ?? 0, 2); ?></td>
                            <td class="highlight">₱<?php echo number_format($tvc, 2); ?></td>
                            <td class="actions-cell">
                                <button class="btn-action btn-edit" onclick='editOpexLog(<?php echo htmlspecialchars(json_encode($log), ENT_QUOTES); ?>)' title="Edit OPEX">
                                    EDIT
                                </button>
                                <button class="btn-action btn-delete" onclick="deleteLog(<?php echo $log['id']; ?>)" title="Delete">
                                    DELETE
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot style="background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; font-weight: 700;">
                    <tr>
                        <td colspan="8" style="text-align: right; padding: 1rem;">TOTAL OPEX:</td>
                        <td style="padding: 1rem; font-size: 1.1rem;">
                            ₱<?php
                                $totalTVC = 0;
                                foreach ($logs as $log) {
                                    $totalTVC += ($log['feed_cost'] ?? 0) +
                                                 ($log['biologics_cost'] ?? 0) +
                                                 ($log['fuel_cost'] ?? 0) +
                                                 ($log['labor_cost'] ?? 0) +
                                                 ($log['water_cost'] ?? 0) +
                                                 ($log['electricity_cost'] ?? 0);
                                }
                                echo number_format($totalTVC, 2);
                            ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php else: ?>
            <p style="text-align: center; padding: 2rem; color: #999;">No OPEX entries yet. Click "ADD OPEX ENTRY" to start.</p>
        <?php endif; ?>
    </div>
    
    <!-- CAPEX (Capital Expenditures) Table -->
    <div class="table-card" style="margin-top: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <div>
                <h2 class="table-title" style="margin: 0;"> CAPEX - Capital Expenditures (Fixed Costs)</h2>
                <p style="margin: 0.25rem 0 0 0; color: #666; font-size: 0.9rem;">
                    Total Fixed Costs (TFC) = Building Depreciation + Equipment Depreciation + Permits/Taxes
                </p>
            </div>
            <button class="btn-add-entry" onclick="openAddCapexModal()" style="background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 700; cursor: pointer; white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; transition: all 0.3s;">
                ➕ ADD CAPEX ENTRY
            </button>
        </div>
        <?php if (!empty($logs)): ?>
        <div class="table-responsive">
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>DATE</th>
                        <th>BATCH</th>
                        <th>BUILDING DEPRECIATION</th>
                        <th>EQUIPMENT DEPRECIATION</th>
                        <th>PERMITS & TAXES</th>
                        <th>TOTAL TFC</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): 
                        $tfc = ($log['building_depreciation'] ?? 0) + 
                               ($log['equipment_depreciation'] ?? 0) + 
                               ($log['permits_taxes'] ?? 0);
                        
                        // Only show if CAPEX fields have values
                        $has_capex = $tfc > 0;
                        
                        if (!$has_capex) continue;
                    ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($log['log_date'])); ?></td>
                            <td><?php echo htmlspecialchars($log['batch_name'] ?: '-'); ?></td>
                            <td>₱<?php echo number_format($log['building_depreciation'] ?? 0, 2); ?></td>
                            <td>₱<?php echo number_format($log['equipment_depreciation'] ?? 0, 2); ?></td>
                            <td>₱<?php echo number_format($log['permits_taxes'] ?? 0, 2); ?></td>
                            <td class="highlight">₱<?php echo number_format($tfc, 2); ?></td>
                            <td class="actions-cell">
                                <button class="btn-action btn-edit" onclick='editCapexLog(<?php echo htmlspecialchars(json_encode($log), ENT_QUOTES); ?>)' title="Edit CAPEX">
                                    EDIT
                                </button>
                                <button class="btn-action btn-delete" onclick="deleteLog(<?php echo $log['id']; ?>)" title="Delete">
                                    DELETE
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot style="background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; font-weight: 700;">
                    <tr>
                        <td colspan="5" style="text-align: right; padding: 1rem;">TOTAL CAPEX:</td>
                        <td style="padding: 1rem; font-size: 1.1rem;">
                            ₱<?php 
                                $totalTFC = 0;
                                foreach ($logs as $log) {
                                    $totalTFC += ($log['building_depreciation'] ?? 0) + 
                                                ($log['equipment_depreciation'] ?? 0) + 
                                                ($log['permits_taxes'] ?? 0);
                                }
                                echo number_format($totalTFC, 2);
                            ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php else: ?>
            <p style="text-align: center; padding: 2rem; color: #999;">No CAPEX entries yet. Click "ADD CAPEX ENTRY" to start.</p>
        <?php endif; ?>
    </div>
    
    <!-- Opportunity Costs Table -->
    <div class="table-card" style="margin-top: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <div>
                <h2 class="table-title" style="margin: 0;"> Opportunity Costs (TOC)</h2>
                <p style="margin: 0.25rem 0 0 0; color: #666; font-size: 0.9rem;">
                    Total Opportunity Costs (TOC) = Foregone Interest on Capital + Land Rent Value
                </p>
            </div>
            <button class="btn-add-entry" onclick="openAddTocModal()" style="background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 700; cursor: pointer; white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; transition: all 0.3s;">
                ➕ ADD TOC ENTRY
            </button>
        </div>
        <?php if (!empty($logs)): ?>
        <div class="table-responsive">
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>DATE</th>
                        <th>BATCH</th>
                        <th>CAPITAL OPPORTUNITY COST</th>
                        <th>LAND RENT OPPORTUNITY</th>
                        <th>TOTAL TOC</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): 
                        $toc = ($log['capital_opportunity_cost'] ?? 0) + 
                               ($log['land_rent_opportunity'] ?? 0);
                        
                        // Only show if TOC fields have values
                        $has_toc = $toc > 0;
                        
                        if (!$has_toc) continue;
                    ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($log['log_date'])); ?></td>
                            <td><?php echo htmlspecialchars($log['batch_name'] ?: '-'); ?></td>
                            <td>₱<?php echo number_format($log['capital_opportunity_cost'] ?? 0, 2); ?></td>
                            <td>₱<?php echo number_format($log['land_rent_opportunity'] ?? 0, 2); ?></td>
                            <td class="highlight">₱<?php echo number_format($toc, 2); ?></td>
                            <td class="actions-cell">
                                <button class="btn-action btn-edit" onclick='editTocLog(<?php echo htmlspecialchars(json_encode($log), ENT_QUOTES); ?>)' title="Edit TOC">
                                    EDIT
                                </button>
                                <button class="btn-action btn-delete" onclick="deleteLog(<?php echo $log['id']; ?>)" title="Delete">
                                    DELETE
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot style="background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; font-weight: 700;">
                    <tr>
                        <td colspan="4" style="text-align: right; padding: 1rem;">TOTAL TOC:</td>
                        <td style="padding: 1rem; font-size: 1.1rem;">
                            ₱<?php 
                                $totalTOC = 0;
                                foreach ($logs as $log) {
                                    $totalTOC += ($log['capital_opportunity_cost'] ?? 0) + 
                                                ($log['land_rent_opportunity'] ?? 0);
                                }
                                echo number_format($totalTOC, 2);
                            ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php else: ?>
            <p style="text-align: center; padding: 2rem; color: #999;">No TOC entries yet. Click "ADD TOC ENTRY" to start.</p>
        <?php endif; ?>
    </div>
    <!-- Total Production Cost Summary - Only show if there are logs -->
    <?php if (!empty($logs)): ?>
    <div class="table-card" style="margin-top: 2rem; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border: 3px solid #27ae60;">
        <h2 class="table-title" style="color: #27ae60;"> Total Production Cost Summary</h2>
        <p style="margin: -0.5rem 0 1rem 0; color: #27ae60; font-size: 0.9rem; font-weight: 600;">
            Total Production Cost = TVC + TFC + TOC | Average Production Cost (APC) = Total Cost ÷ Total Weight
        </p>
        <div class="table-responsive">
            <table class="logs-table">
                <thead style="background: linear-gradient(135deg, #27ae60, #229954);">
                    <tr>
                        <th>DATE</th>
                        <th>BATCH</th>
                        <th>TVC (OPEX)</th>
                        <th>TFC (CAPEX)</th>
                        <th>TOC</th>
                        <th>TOTAL PRODUCTION COST</th>
                        <th>TOTAL WEIGHT (KG)</th>
                        <th>APC (₱/KG)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): 
                        $tvc = ($log['feed_cost'] ?? 0) + ($log['biologics_cost'] ?? 0) + ($log['fuel_cost'] ?? 0) + 
                               ($log['caretaker_labor_cost'] ?? $log['labor_cost']) + ($log['water_cost'] ?? 0) + ($log['electricity_cost'] ?? 0);
                        $tfc = ($log['building_depreciation'] ?? 0) + ($log['equipment_depreciation'] ?? 0) + ($log['permits_taxes'] ?? 0);
                        $toc = ($log['capital_opportunity_cost'] ?? 0) + ($log['land_rent_opportunity'] ?? 0);
                        $totalProductionCost = $tvc + $tfc + $toc;
                        $weight = $log['total_live_weight_tw'] ?? $log['final_weight_kg'] ?? 0;
                        $apc = $weight > 0 ? ($totalProductionCost / $weight) : 0;
                    ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($log['log_date'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($log['batch_name'] ?: '-'); ?></strong></td>
                            <td>₱<?php echo number_format($tvc, 2); ?></td>
                            <td>₱<?php echo number_format($tfc, 2); ?></td>
                            <td>₱<?php echo number_format($toc, 2); ?></td>
                            <td class="summary-total-cell">
                                ₱<?php echo number_format($totalProductionCost, 2); ?>
                            </td>
                            <td style="font-weight: 600;"><?php echo number_format($weight, 2); ?> kg</td>
                            <td style="background: #e8f5e9; font-weight: 700; font-size: 1.05rem;">
                                ₱<?php echo number_format($apc, 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Smart Production Optimizer -->
    <div class="table-card" style="margin-top: 3rem; background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 20%, #fc8181 50%, #dc2626 100%); border: none; position: relative; overflow: hidden;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="font-size: 4rem; margin-bottom: 1rem;"></div>
            <h2 class="gradient-text" style="margin: 0; font-size: 2.5rem; font-weight: 800;">
                Smart Production Optimizer
            </h2>
            <p style="margin: 1rem 0 0 0; color: #742a2a; font-size: 1.1rem; font-weight: 500; opacity: 0.9;">
                AI-powered system automatically calculates optimal production to maintain ₱150-200 selling price
            </p>
        </div>

        <?php
        // Calculate current production metrics
        $totalProductionCostCalc = 0;
        $totalWeight = $total_live_weight;
        
        foreach ($logs as $log) {
            $tvc = ($log['feed_cost'] ?? 0) + ($log['biologics_cost'] ?? 0) + ($log['fuel_cost'] ?? 0) + 
                   ($log['labor_cost'] ?? 0) + ($log['water_cost'] ?? 0) + ($log['electricity_cost'] ?? 0);
            $tfc = ($log['building_depreciation'] ?? 0) + ($log['equipment_depreciation'] ?? 0) + ($log['permits_taxes'] ?? 0);
            $toc = ($log['capital_opportunity_cost'] ?? 0) + ($log['land_rent_opportunity'] ?? 0);
            $totalProductionCostCalc += ($tvc + $tfc + $toc);
        }
        
        // Current cost per kg
        $currentCostPerKg = $totalWeight > 0 ? ($totalProductionCostCalc / $totalWeight) : 0;
        
        // Target selling prices
        $targetPriceLow = 150;
        $targetPriceHigh = 200;
        $optimalPrice = 175; // Middle of range
        
        // Calculate recommended production to achieve target margins
        $targetMargin = 0.30; // 30% profit margin
        $maxAllowableCostPerKg = $optimalPrice * (1 - $targetMargin); // ₱122.50 max cost per kg
        
        // Calculate optimal weight needed
        $optimalWeight = $totalProductionCostCalc > 0 ? ($totalProductionCostCalc / $maxAllowableCostPerKg) : 0;
        $avgPigWeight = 28; // Standard pig weight
        $optimalPigCount = $optimalWeight > 0 ? ceil($optimalWeight / $avgPigWeight) : 0;
        
        // Calculate current vs optimal
        $currentPigCount = max(1, ceil($totalWeight / $avgPigWeight));
        $weightAdjustment = $optimalWeight - $totalWeight;
        $pigAdjustment = $optimalPigCount - $currentPigCount;
        
        // Profitability analysis
        $currentSellingPrice = $currentCostPerKg > 0 ? $currentCostPerKg / (1 - $targetMargin) : $optimalPrice;
        $isProfitable = $currentSellingPrice <= $targetPriceHigh;
        ?>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; margin-bottom: 3rem;">
            <!-- Current Status -->
            <div class="glass-card" style="border-radius: 20px; padding: 2.5rem;">
                <h3 style="color: #742a2a; margin: 0 0 2rem 0; font-size: 1.6rem; display: flex; align-items: center; gap: 0.75rem;">
                     Current Production Status
                </h3>
                
                <div style="display: grid; gap: 1.5rem;">
                    <div style="background: linear-gradient(135deg, #fff 0%, #f9fafb 100%); border: 2px solid #e5e7eb; border-radius: 12px; padding: 1.5rem; text-align: center;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;"></div>
                        <div style="font-size: 0.9rem; color: #6b7280; font-weight: 600; margin-bottom: 0.5rem;">TOTAL WEIGHT</div>
                        <div style="font-size: 1.8rem; font-weight: 800; color: #374151;"><?php echo number_format($totalWeight, 1); ?> kg</div>
                    </div>
                    
                    <div style="background: linear-gradient(135deg, #fff 0%, #f9fafb 100%); border: 2px solid #e5e7eb; border-radius: 12px; padding: 1.5rem; text-align: center;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;"></div>
                        <div style="font-size: 0.9rem; color: #6b7280; font-weight: 600; margin-bottom: 0.5rem;">PIG COUNT</div>
                        <div style="font-size: 1.8rem; font-weight: 800; color: #374151;"><?php echo $currentPigCount; ?> pigs</div>
                    </div>
                    
                    <div style="background: <?php echo $isProfitable ? 'linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%)' : 'linear-gradient(135deg, #fee2e2 0%, #fca5a5 100%)'; ?>; border: 2px solid <?php echo $isProfitable ? '#10b981' : '#ef4444'; ?>; border-radius: 12px; padding: 1.5rem; text-align: center;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo $isProfitable ? '✅' : '⚠️'; ?></div>
                        <div style="font-size: 0.9rem; color: <?php echo $isProfitable ? '#065f46' : '#991b1b'; ?>; font-weight: 600; margin-bottom: 0.5rem;">COST PER KG</div>
                        <div style="font-size: 1.8rem; font-weight: 800; color: <?php echo $isProfitable ? '#047857' : '#dc2626'; ?>;">₱<?php echo number_format($currentCostPerKg, 2); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- AI Recommendations -->
            <div class="glass-card" style="border-radius: 20px; padding: 2.5rem;">
                <h3 style="color: #065f46; margin: 0 0 2rem 0; font-size: 1.6rem; display: flex; align-items: center; gap: 0.75rem;">
                     AI Optimization Recommendations
                </h3>
                
                <div style="display: grid; gap: 1.5rem;">
                    <div style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border: 2px solid #10b981; border-radius: 12px; padding: 1.5rem; text-align: center;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;"></div>
                        <div style="font-size: 0.9rem; color: #065f46; font-weight: 600; margin-bottom: 0.5rem;">OPTIMAL WEIGHT</div>
                        <div style="font-size: 1.8rem; font-weight: 800; color: #047857;"><?php echo number_format($optimalWeight, 1); ?> kg</div>
                    </div>
                    
                    <div style="background: linear-gradient(135deg, #dbeafe 0%, #93c5fd 100%); border: 2px solid #3b82f6; border-radius: 12px; padding: 1.5rem; text-align: center;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;"></div>
                        <div style="font-size: 0.9rem; color: #1d4ed8; font-weight: 600; margin-bottom: 0.5rem;">RECOMMENDED PIGS</div>
                        <div style="font-size: 1.8rem; font-weight: 800; color: #1e40af;"><?php echo $optimalPigCount; ?> pigs</div>
                    </div>
                    
                    <div style="background: linear-gradient(135deg, <?php echo $pigAdjustment > 0 ? '#fef3c7 0%, #fde68a 100%' : '#d1fae5 0%, #a7f3d0 100%'; ?>); border: 2px solid <?php echo $pigAdjustment > 0 ? '#f59e0b' : '#10b981'; ?>; border-radius: 12px; padding: 1.5rem; text-align: center;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo $pigAdjustment > 0 ? '📈' : '✅'; ?></div>
                        <div style="font-size: 0.9rem; color: <?php echo $pigAdjustment > 0 ? '#92400e' : '#065f46'; ?>; font-weight: 600; margin-bottom: 0.5rem;">ADJUSTMENT NEEDED</div>
                        <div style="font-size: 1.8rem; font-weight: 800; color: <?php echo $pigAdjustment > 0 ? '#d97706' : '#047857'; ?>;">
                            <?php 
                            if ($pigAdjustment > 0) {
                                echo '+' . $pigAdjustment . ' pigs';
                            } elseif ($pigAdjustment < 0) {
                                echo $pigAdjustment . ' pigs';
                            } else {
                                echo 'Optimal';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Analysis -->
        <div class="glass-card" style="border-radius: 20px; padding: 3rem;">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem;">
                <div style="font-size: 3rem;"></div>
                <h3 style="color: #374151; margin: 0; font-size: 1.8rem; font-weight: 800;">
                    Production Optimization Analysis
                </h3>
            </div>
            
            <div style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); padding: 2rem; border-radius: 16px; border-left: 6px solid #dc2626;">
                <?php if ($isProfitable): ?>
                    <div style="color: #065f46; font-size: 1.1rem; line-height: 1.8; margin-bottom: 1.5rem;">
                        <strong> PROFITABLE OPERATION:</strong> Your current production cost of ₱<?php echo number_format($currentCostPerKg, 2); ?>/kg allows for profitable sales at ₱<?php echo number_format($currentSellingPrice, 2); ?>/kg within your target range.
                    </div>
                <?php else: ?>
                    <div style="color: #dc2626; font-size: 1.1rem; line-height: 1.8; margin-bottom: 1.5rem;">
                        <strong> OPTIMIZATION NEEDED:</strong> Current production cost of ₱<?php echo number_format($currentCostPerKg, 2); ?>/kg requires selling at ₱<?php echo number_format($currentSellingPrice, 2); ?>/kg, which exceeds your target range.
                    </div>
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <div style="background: #e0f2fe; padding: 1.5rem; border-radius: 12px; border-left: 4px solid #0288d1;">
                        <div style="font-weight: 700; color: #01579b; margin-bottom: 0.5rem;"> Target Selling Price</div>
                        <div style="font-size: 1.2rem; color: #0277bd;">₱150 - ₱200 per kg</div>
                    </div>
                    <div style="background: #f3e5f5; padding: 1.5rem; border-radius: 12px; border-left: 4px solid #7b1fa2;">
                        <div style="font-weight: 700; color: #4a148c; margin-bottom: 0.5rem;"> Max Cost Allowable</div>
                        <div style="font-size: 1.2rem; color: #6a1b9a;">₱<?php echo number_format($maxAllowableCostPerKg, 2); ?> per kg</div>
                    </div>
                    <div style="background: #e8f5e9; padding: 1.5rem; border-radius: 12px; border-left: 4px solid #2e7d32;">
                        <div style="font-weight: 700; color: #1b5e20; margin-bottom: 0.5rem;"> Target Profit Margin</div>
                        <div style="font-size: 1.2rem; color: #2e7d32;">30%</div>
                    </div>
                </div>
                
                <div style="background: #fff; padding: 2rem; border-radius: 12px; border: 2px solid #e5e7eb;">
                    <h4 style="color: #374151; margin: 0 0 1rem 0; font-size: 1.2rem; font-weight: 700;"> Action Plan:</h4>
                    <ul style="margin: 0; padding-left: 1.5rem; color: #6b7280; line-height: 1.8;">
                        <?php if ($pigAdjustment > 0): ?>
                            <li><strong>Increase Production:</strong> Add <?php echo $pigAdjustment; ?> more pigs (<?php echo number_format($weightAdjustment, 1); ?> kg) to optimize cost efficiency</li>
                            <li><strong>Target Weight:</strong> Aim for <?php echo number_format($optimalWeight, 1); ?> kg total live weight</li>
                            <li><strong>Expected Result:</strong> Achieve ₱<?php echo number_format($maxAllowableCostPerKg, 2); ?>/kg production cost for profitable ₱175/kg selling price</li>
                        <?php elseif ($pigAdjustment < 0): ?>
                            <li><strong>Reduce Production:</strong> Current production exceeds optimal efficiency by <?php echo abs($pigAdjustment); ?> pigs</li>
                            <li><strong>Cost Optimization:</strong> Focus on reducing production costs rather than increasing volume</li>
                            <li><strong>Market Strategy:</strong> Consider premium pricing or value-added products</li>
                        <?php else: ?>
                            <li><strong>Optimal Production:</strong> Current pig count is ideal for your cost structure</li>
                            <li><strong>Maintain Quality:</strong> Focus on consistent production and cost control</li>
                            <li><strong>Market Ready:</strong> You can confidently sell at ₱<?php echo number_format($optimalPrice, 2); ?>/kg</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
</div>

<!-- Modal for OPEX Entry -->
<div class="modal-overlay" id="opexModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 id="opexModalTitle">Add OPEX Entry</h3>
            <button class="modal-close" onclick="closeOpexModal()">×</button>
        </div>
        <div class="modal-body">
            <form id="opexForm" method="POST" action="/process_inventory_log.php">
                <input type="hidden" id="opex_action" name="action" value="add_opex">
                <input type="hidden" id="opex_id" name="id">
                
                <div class="form-group">
                    <label for="opex_batch_name">Batch Name</label>
                    <input type="text" id="opex_batch_name" name="batch_name" readonly 
                           style="background: #f8fafc; cursor: not-allowed;"
                           value="<?php echo date('F Y'); // e.g., "September 2026" ?>">
                    <small style="color: #666; font-size: 0.85rem; margin-top: 0.25rem; display: block;">
                        📅 Automatically set to current month
                    </small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="opex_feed_cost">Feed Cost (₱)</label>
                        <input type="number" step="0.01" id="opex_feed_cost" name="feed_cost" min="0">
                    </div>
                    <div class="form-group">
                        <label for="opex_biologics_cost">Biologics Cost (₱)</label>
                        <input type="number" step="0.01" id="opex_biologics_cost" name="biologics_cost" min="0">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="opex_fuel_cost">Fuel Cost (₱)</label>
                        <input type="number" step="0.01" id="opex_fuel_cost" name="fuel_cost" min="0">
                    </div>
                    <div class="form-group">
                        <label for="opex_labor_cost">Labor Cost (₱)</label>
                        <input type="number" step="0.01" id="opex_labor_cost" name="labor_cost" min="0">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="opex_water_cost">Water Cost (₱)</label>
                        <input type="number" step="0.01" id="opex_water_cost" name="water_cost" min="0">
                    </div>
                    <div class="form-group">
                        <label for="opex_electricity_cost">Electricity Cost (₱)</label>
                        <input type="number" step="0.01" id="opex_electricity_cost" name="electricity_cost" min="0">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeOpexModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for CAPEX Entry -->
<div class="modal-overlay" id="capexModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 id="capexModalTitle">Add CAPEX Entry</h3>
            <button class="modal-close" onclick="closeCapexModal()">×</button>
        </div>
        <div class="modal-body">
            <form id="capexForm" method="POST" action="/process_inventory_log.php">
                <input type="hidden" id="capex_action" name="action" value="add_capex">
                <input type="hidden" id="capex_id" name="id">
                
                <div class="form-group">
                    <label for="capex_batch_name">Batch Name</label>
                    <input type="text" id="capex_batch_name" name="batch_name" readonly 
                           style="background: #f8fafc; cursor: not-allowed;"
                           value="<?php echo date('F Y'); // e.g., "September 2026" ?>">
                    <small style="color: #666; font-size: 0.85rem; margin-top: 0.25rem; display: block;">
                        📅 Automatically set to current month
                    </small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="capex_building_depreciation">Building Depreciation (₱)</label>
                        <input type="number" step="0.01" id="capex_building_depreciation" name="building_depreciation" min="0">
                    </div>
                    <div class="form-group">
                        <label for="capex_equipment_depreciation">Equipment Depreciation (₱)</label>
                        <input type="number" step="0.01" id="capex_equipment_depreciation" name="equipment_depreciation" min="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="capex_permits_taxes">Permits & Taxes (₱)</label>
                    <input type="number" step="0.01" id="capex_permits_taxes" name="permits_taxes" min="0">
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeCapexModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for TOC Entry -->
<div class="modal-overlay" id="tocModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 id="tocModalTitle">Add TOC Entry</h3>
            <button class="modal-close" onclick="closeTocModal()">×</button>
        </div>
        <div class="modal-body">
            <form id="tocForm" method="POST" action="">
                <input type="hidden" id="toc_action" name="action" value="add_toc">
                <input type="hidden" id="toc_id" name="id">
                
                <div class="form-group">
                    <label for="toc_batch_name">Batch Name</label>
                    <input type="text" id="toc_batch_name" name="batch_name" readonly 
                           style="background: #f8fafc; cursor: not-allowed;"
                           value="<?php echo date('F Y'); // e.g., "September 2026" ?>">
                    <small style="color: #666; font-size: 0.85rem; margin-top: 0.25rem; display: block;">
                        📅 Automatically set to current month
                    </small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="toc_capital_opportunity_cost">Capital Opportunity Cost (₱)</label>
                        <input type="number" step="0.01" id="toc_capital_opportunity_cost" name="capital_opportunity_cost" min="0">
                    </div>
                    <div class="form-group">
                        <label for="toc_land_rent_opportunity">Land Rent Opportunity (₱)</label>
                        <input type="number" step="0.01" id="toc_land_rent_opportunity" name="land_rent_opportunity" min="0">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeTocModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal and form functions for inventory logs

// OPEX Modal Functions
function openAddOpexModal() {
    document.getElementById('opexModalTitle').textContent = 'Add OPEX Entry';
    document.getElementById('opex_action').value = 'add_opex';
    document.getElementById('opex_id').value = '';
    
    // Clear form
    const currentMonth = new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    document.getElementById('opex_batch_name').value = currentMonth;
    document.getElementById('opex_feed_cost').value = '';
    document.getElementById('opex_biologics_cost').value = '';
    document.getElementById('opex_fuel_cost').value = '';
    document.getElementById('opex_labor_cost').value = '';
    document.getElementById('opex_water_cost').value = '';
    document.getElementById('opex_electricity_cost').value = '';
    
    document.getElementById('opexModal').classList.add('active');
}

function editOpexLog(log) {
    document.getElementById('opexModalTitle').textContent = 'Edit OPEX Entry';
    document.getElementById('opex_action').value = 'edit_opex';
    document.getElementById('opex_id').value = log.id;
    
    // Fill form with existing data (keep existing batch name for edits)
    document.getElementById('opex_batch_name').value = log.batch_name || new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    document.getElementById('opex_feed_cost').value = log.feed_cost || '';
    document.getElementById('opex_biologics_cost').value = log.biologics_cost || '';
    document.getElementById('opex_fuel_cost').value = log.fuel_cost || '';
    document.getElementById('opex_labor_cost').value = log.labor_cost || '';
    document.getElementById('opex_water_cost').value = log.water_cost || '';
    document.getElementById('opex_electricity_cost').value = log.electricity_cost || '';
    
    document.getElementById('opexModal').classList.add('active');
}

function closeOpexModal() {
    document.getElementById('opexModal').classList.remove('active');
}

// CAPEX Modal Functions
function openAddCapexModal() {
    document.getElementById('capexModalTitle').textContent = 'Add CAPEX Entry';
    document.getElementById('capex_action').value = 'add_capex';
    document.getElementById('capex_id').value = '';
    
    // Clear form
    const currentMonth = new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    document.getElementById('capex_batch_name').value = currentMonth;
    document.getElementById('capex_building_depreciation').value = '';
    document.getElementById('capex_equipment_depreciation').value = '';
    document.getElementById('capex_permits_taxes').value = '';
    
    document.getElementById('capexModal').classList.add('active');
}

function editCapexLog(log) {
    document.getElementById('capexModalTitle').textContent = 'Edit CAPEX Entry';
    document.getElementById('capex_action').value = 'edit_capex';
    document.getElementById('capex_id').value = log.id;
    
    // Fill form with existing data (keep existing batch name for edits)
    document.getElementById('capex_batch_name').value = log.batch_name || new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    document.getElementById('capex_building_depreciation').value = log.building_depreciation || '';
    document.getElementById('capex_equipment_depreciation').value = log.equipment_depreciation || '';
    document.getElementById('capex_permits_taxes').value = log.permits_taxes || '';
    
    document.getElementById('capexModal').classList.add('active');
}

function closeCapexModal() {
    document.getElementById('capexModal').classList.remove('active');
}

// TOC Modal Functions
function openAddTocModal() {
    document.getElementById('tocModalTitle').textContent = 'Add TOC Entry';
    document.getElementById('toc_action').value = 'add_toc';
    document.getElementById('toc_id').value = '';
    
    // Clear form
    const currentMonth = new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    document.getElementById('toc_batch_name').value = currentMonth;
    document.getElementById('toc_capital_opportunity_cost').value = '';
    document.getElementById('toc_land_rent_opportunity').value = '';
    
    document.getElementById('tocModal').classList.add('active');
}

function editTocLog(log) {
    document.getElementById('tocModalTitle').textContent = 'Edit TOC Entry';
    document.getElementById('toc_action').value = 'edit_toc';
    document.getElementById('toc_id').value = log.id;
    
    // Fill form with existing data (keep existing batch name for edits)
    document.getElementById('toc_batch_name').value = log.batch_name || new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    document.getElementById('toc_capital_opportunity_cost').value = log.capital_opportunity_cost || '';
    document.getElementById('toc_land_rent_opportunity').value = log.land_rent_opportunity || '';
    
    document.getElementById('tocModal').classList.add('active');
}

function closeTocModal() {
    document.getElementById('tocModal').classList.remove('active');
}

// Delete function (keeps the same confirmation dialog)
function deleteLog(logId) {
    if (confirm('Are you sure you want to delete this log entry? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = logId;
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Page enhancement and event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth animations to cards
    const cards = document.querySelectorAll('.summary-card, .table-card, .glass-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.6s ease';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Add hover effects to metric cards
    const metricCards = document.querySelectorAll('[style*="border: 2px solid"]');
    metricCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
            this.style.transition = 'all 0.3s ease';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Close modals when clicking outside
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });
    
    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                modal.classList.remove('active');
            });
        }
    });
    
    // Form validation and submission
    document.getElementById('opexForm').addEventListener('submit', function(e) {
        console.log('OPEX form submit triggered');
        // Temporarily remove preventDefault to test direct submission
        // e.preventDefault();
        // if (validateOpexForm()) {
        //     this.submit();
        // }
    });
    
    document.getElementById('capexForm').addEventListener('submit', function(e) {
        console.log('CAPEX form submit triggered');
        // Temporarily remove preventDefault to test direct submission
        // e.preventDefault();
        // if (validateCapexForm()) {
        //     this.submit();
        // }
    });
    
    document.getElementById('tocForm').addEventListener('submit', function(e) {
        console.log('TOC form submit triggered');
        // Temporarily remove preventDefault to test direct submission
        // e.preventDefault();
        // if (validateTocForm()) {
        //     this.submit();
        // }
    });
});

// Form validation functions
function validateOpexForm() {
    // No validation needed since batch name is automatically set
    return true;
}

function validateCapexForm() {
    // No validation needed since batch name is automatically set
    return true;
}

function validateTocForm() {
    // No validation needed since batch name is automatically set
    return true;
}
</script>

<?php
$content = ob_get_clean();

// Add JavaScript to handle redirect after successful operations
$content .= '
<script>
// Check if we\'re on a blank POST response and redirect back
if (window.location.href.includes("livestock-owner/inventory-logs") && 
    document.documentElement.innerHTML.trim() === "") {
    window.location.href = "/livestock-owner/inventory-logs";
}

// Auto-redirect if staying on POST response page
setTimeout(function() {
    if (document.body && document.body.innerHTML.trim() === "") {
        window.location.href = "/livestock-owner/inventory-logs";
    }
}, 500);
</script>';

include VIEWS_PATH . '/layouts/dashboard-layout.php';
?>