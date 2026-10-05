<?php
/**
 * PSA Pig Statistics API Wrapper
 * Fetches official Philippine pig inventory data from PSA OpenStat
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $url = 'https://openstat.psa.gov.ph:443/PXWeb/api/v1/en/DB/2E/LP/PDN/0032E4FPLS0.px';
    
    // PSA API query for pig data (Animal Type 2 = Pig, Metro Manila = 1100000000, Years 23-26 = 2023-2026)
    $query = json_encode([
        "query" => [
            [
                "code" => "Animal Type",
                "selection" => [
                    "filter" => "item",
                    "values" => ["2"]
                ]
            ],
            [
                "code" => "Geolocation",
                "selection" => [
                    "filter" => "item",
                    "values" => ["1100000000"]
                ]
            ],
            [
                "code" => "Year",
                "selection" => [
                    "filter" => "item",
                    "values" => ["23", "24", "25", "26"]
                ]
            ]
        ],
        "response" => [
            "format" => "json"
        ]
    ]);
    
    error_log('PSA API Query: ' . $query);
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($query),
                'User-Agent: LechGO/1.0'
            ],
            'content' => $query,
            'timeout' => 15,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception('Failed to fetch from PSA API');
    }
    
    error_log('PSA Response: ' . substr($response, 0, 500));
    
    $data = json_decode($response, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON response from PSA');
    }
    
    // Parse PSA data structure
    $trends = [];
    
    if (isset($data['data']) && !empty($data['data'])) {
        foreach ($data['data'] as $key => $value) {
            $trends[] = [
                'id' => count($trends) + 1,
                'title' => 'Pig Inventory Statistics - ' . (isset($data['dimension']['Year']['category']['index'][$key]) ? $data['dimension']['Year']['category']['index'][$key] : 'Data'),
                'description' => 'Official PSA pig population data from Metropolitan areas. Current inventory count: ' . number_format($value) . ' heads',
                'category' => 'Pig Farming',
                'source' => 'Philippine Statistics Authority (PSA)',
                'value' => $value,
                'published_at' => date('c'),
                'emoji' => '🐷',
                'image' => null,
                'url' => 'https://openstat.psa.gov.ph'
            ];
        }
    }
    
    // If no data, provide sample trends
    if (empty($trends)) {
        $trends = [
            [
                'id' => 1,
                'title' => 'Pig Inventory Statistics 2023-2026',
                'description' => 'Official PSA data showing Philippine pig population trends in key regions. Data from Philippine Statistics Authority.',
                'category' => 'Pig Farming',
                'source' => 'Philippine Statistics Authority (PSA)',
                'published_at' => date('c'),
                'emoji' => '🐷',
                'image' => null,
                'url' => 'https://openstat.psa.gov.ph'
            ]
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'source' => 'PSA OpenStat',
        'totalResults' => count($trends),
        'articles' => $trends,
        'rawData' => $data
    ]);
    
} catch (Exception $e) {
    error_log('PSA API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'source' => 'PSA'
    ]);
}
?>
