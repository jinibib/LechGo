<?php

/**
 * LocationController - Handles location data requests
 * Provides API endpoints for fetching municipalities, barangays, and streets
 */

class LocationController {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Get distinct municipalities from database
     */
    public function getMunicipalities() {
        $query = "SELECT DISTINCT municipality FROM locations WHERE city = 'Davao City' ORDER BY municipality ASC";
        $result = $this->conn->query($query);
        
        $municipalities = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $municipalities[] = $row['municipality'];
            }
        }
        
        return $municipalities;
    }

    /**
     * Get barangays for a specific municipality
     */
    public function getBarangays($municipality) {
        $municipality = $this->conn->real_escape_string($municipality);
        $query = "SELECT DISTINCT barangay FROM locations WHERE municipality = '$municipality' AND city = 'Davao City' ORDER BY barangay ASC";
        $result = $this->conn->query($query);
        
        $barangays = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $barangays[] = $row['barangay'];
            }
        }
        
        return $barangays;
    }

    /**
     * Get streets for a specific municipality and barangay
     */
    public function getStreets($municipality, $barangay) {
        $municipality = $this->conn->real_escape_string($municipality);
        $barangay = $this->conn->real_escape_string($barangay);
        $query = "SELECT DISTINCT street FROM locations WHERE municipality = '$municipality' AND barangay = '$barangay' AND city = 'Davao City' ORDER BY street ASC";
        $result = $this->conn->query($query);
        
        $streets = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $streets[] = $row['street'];
            }
        }
        
        return $streets;
    }

    /**
     * Get all existing farms from livestock_owners table
     */
    public function getFarms() {
        $query = "SELECT DISTINCT farm_name, location FROM livestock_owners ORDER BY farm_name ASC";
        $result = $this->conn->query($query);
        
        $farms = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $farms[] = [
                    'farm_name' => $row['farm_name'],
                    'location' => $row['location']
                ];
            }
        }
        
        return $farms;
    }

    /**
     * API endpoint handler - returns JSON response
     */
    public function handleRequest() {
        header('Content-Type: application/json');

        $action = $_GET['action'] ?? null;

        try {
            switch ($action) {
                case 'municipalities':
                    echo json_encode([
                        'success' => true,
                        'data' => $this->getMunicipalities()
                    ]);
                    break;

                case 'barangays':
                    $municipality = $_GET['municipality'] ?? null;
                    if (!$municipality) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Municipality parameter required'
                        ]);
                        return;
                    }
                    echo json_encode([
                        'success' => true,
                        'data' => $this->getBarangays($municipality)
                    ]);
                    break;

                case 'streets':
                    $municipality = $_GET['municipality'] ?? null;
                    $barangay = $_GET['barangay'] ?? null;
                    if (!$municipality || !$barangay) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Municipality and barangay parameters required'
                        ]);
                        return;
                    }
                    echo json_encode([
                        'success' => true,
                        'data' => $this->getStreets($municipality, $barangay)
                    ]);
                    break;

                case 'farms':
                    echo json_encode([
                        'success' => true,
                        'data' => $this->getFarms()
                    ]);
                    break;

                default:
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid action'
                    ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ]);
        }
    }
}
