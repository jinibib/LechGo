<?php
/**
 * Market Intelligence API - Fetch from News API
 * Backend wrapper to bypass CORS restrictions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $newsApiKey = 'f6f9a0c5f1064d688515ffc02394a610';
    
    // Build search query for pig and lechon content
    $searchQuery = 'pig OR pork OR lechon OR swine OR livestock OR farm';
    
    // Construct News API URL
    $url = 'https://newsapi.org/v2/everything?' . http_build_query([
        'q' => $searchQuery,
        'sortBy' => 'publishedAt',
        'language' => 'en',
        'pageSize' => 50,
        'apiKey' => $newsApiKey
    ]);
    
    error_log('Fetching from News API: ' . $url);
    
    // Fetch from News API
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'LechGO/1.0'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception('Failed to fetch from News API');
    }
    
    $data = json_decode($response, true);
    
    if ($data['status'] !== 'ok') {
        throw new Exception('News API error: ' . ($data['message'] ?? 'Unknown error'));
    }
    
    // Filter articles for pig/lechon content
    $filteredArticles = [];
    foreach ($data['articles'] as $article) {
        $text = strtolower($article['title'] . ' ' . ($article['description'] ?? ''));
        if (strpos($text, 'pig') !== false || 
            strpos($text, 'pork') !== false || 
            strpos($text, 'lechon') !== false ||
            strpos($text, 'swine') !== false ||
            strpos($text, 'livestock') !== false ||
            strpos($text, 'farm') !== false ||
            strpos($text, 'meat') !== false ||
            strpos($text, 'hog') !== false) {
            $filteredArticles[] = $article;
        }
    }
    
    // Return response
    echo json_encode([
        'status' => 'ok',
        'totalResults' => count($filteredArticles),
        'articles' => array_slice($filteredArticles, 0, 20)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
