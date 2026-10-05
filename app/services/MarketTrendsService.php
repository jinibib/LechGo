<?php

/**
 * Market Trends Service
 * Handles fetching, filtering, and processing market trends
 */

class MarketTrendsService {
    private $conn;
    private $newsApiKey;
    private $newsApiUrl = 'https://newsapi.org/v2/everything';

    public function __construct($conn, $apiKey = null) {
        $this->conn = $conn;
        $this->newsApiKey = $apiKey;
    }

    /**
     * Get relevant keywords for filtering
     */
    public function getRelevantKeywords() {
        return [
            'pig' => ['pig', 'pigs', 'swine', 'hog', 'livestock', 'pig farming', 'swine farming', 'pig production'],
            'pork' => ['pork', 'pork price', 'pork prices', 'pork market', 'pork supply', 'pork demand', 'pork production'],
            'lechon' => ['lechon', 'lechon business', 'lechon price', 'lechon trend', 'roasted pig', 'Filipino food'],
            'agriculture' => ['agriculture', 'livestock market', 'farm prices', 'animal production', 'feed prices'],
            'food' => ['food trends', 'food pricing', 'consumer trends', 'restaurant trends', 'food demand']
        ];
    }

    /**
     * Categorize trend based on keywords
     */
    public function categorizeTrend($text) {
        $text_lower = strtolower($text);
        
        if (preg_match('/\b(lechon|roasted pig)\b/', $text_lower)) {
            return 'Lechon';
        }
        if (preg_match('/\b(pork price|pork market|pork supply|pork demand)\b/', $text_lower)) {
            return 'Pork Market';
        }
        if (preg_match('/\b(pig|swine|hog|livestock|pig farm|swine farm)\b/', $text_lower)) {
            return 'Pig Farming';
        }
        if (preg_match('/\b(price|cost|expensive|cheap)\b/', $text_lower)) {
            return 'Pricing';
        }
        if (preg_match('/\b(agriculture|farm)\b/', $text_lower)) {
            return 'Agriculture';
        }
        if (preg_match('/\b(food trend|consumer|restaurant)\b/', $text_lower)) {
            return 'Food Trends';
        }
        
        return 'Industry News';
    }

    /**
     * Determine trend type based on content
     */
    public function determineTrendType($text) {
        $text_lower = strtolower($text);
        
        if (preg_match('/\b(increase|rise|up|higher|growing)\b/', $text_lower)) {
            return 'Price Increase';
        }
        if (preg_match('/\b(decrease|fall|down|lower|declining)\b/', $text_lower)) {
            return 'Price Decrease';
        }
        if (preg_match('/\b(supply|shortage|shortage|limited)\b/', $text_lower)) {
            return 'Supply Change';
        }
        if (preg_match('/\b(demand|prefer|trend|popular)\b/', $text_lower)) {
            return 'Consumer Preference';
        }
        if (preg_match('/\b(opportunity|growth|expand)\b/', $text_lower)) {
            return 'Business Opportunity';
        }
        
        return 'Market News';
    }

    /**
     * Determine impact level
     */
    public function determineImpactLevel($text) {
        $text_lower = strtolower($text);
        
        // High impact keywords
        if (preg_match('/\b(critical|severe|urgent|mandatory|crisis|emergency)\b/', $text_lower)) {
            return 'High';
        }
        
        // Low impact keywords
        if (preg_match('/\b(minor|slight|small|modest)\b/', $text_lower)) {
            return 'Low';
        }
        
        return 'Moderate';
    }

    /**
     * Generate AI-like summary (fallback when no API available)
     */
    public function generateSummary($title, $description) {
        // Simple fallback summary generation
        $summary = substr($description, 0, 200);
        if (strlen($description) > 200) {
            $summary = substr($summary, 0, strrpos($summary, ' ')) . '...';
        }
        return $summary;
    }

    /**
     * Generate business insight
     */
    public function generateBusinessInsight($category, $trend_type, $title) {
        $insights = [
            'Pig Farming' => 'This development may affect farm operations and production costs. Monitor closely for strategic planning.',
            'Pork Market' => 'Market dynamics could impact pricing strategy and supplier negotiations. Review current market position.',
            'Lechon' => 'This trend could influence product differentiation and customer preferences. Consider strategic adjustments.',
            'Pricing' => 'Cost implications require attention to maintain profit margins and competitiveness.',
            'Agriculture' => 'Agricultural trends may affect feed costs and overall farm efficiency.',
            'Food Trends' => 'Consumer preferences are evolving. Align product offerings accordingly.',
            'Industry News' => 'Stay informed about industry developments that could affect operations.'
        ];
        
        return $insights[$category] ?? 'Monitor this development for business impact.';
    }

    /**
     * Generate suggested action
     */
    public function generateSuggestedAction($category, $trend_type) {
        $actions = [
            'Price Increase' => 'Review supplier pricing. Evaluate cost optimization opportunities.',
            'Price Decrease' => 'Consider bulk purchasing or strategic inventory buildups.',
            'Supply Change' => 'Diversify suppliers. Strengthen supply chain relationships.',
            'Consumer Preference' => 'Survey customers. Test product variations that match preferences.',
            'Business Opportunity' => 'Conduct feasibility study. Plan implementation if aligned with strategy.',
            'Market Risk' => 'Assess risk exposure. Develop mitigation strategies.'
        ];
        
        return $actions[$trend_type] ?? 'Evaluate impact and consider appropriate response.';
    }

    /**
     * Fetch trends from News API (if available)
     */
    public function fetchFromNewsApi() {
        if (!$this->newsApiKey) {
            return false; // API key not configured
        }

        try {
            $search_terms = [
                'pork prices',
                'pig farming',
                'lechon',
                'livestock market',
                'food trends Philippines'
            ];

            $all_articles = [];

            foreach ($search_terms as $term) {
                $url = $this->newsApiUrl . '?q=' . urlencode($term) . '&sortBy=publishedAt&pageSize=5&apiKey=' . $this->newsApiKey;
                
                $response = @file_get_contents($url);
                if ($response) {
                    $data = json_decode($response, true);
                    if (isset($data['articles'])) {
                        $all_articles = array_merge($all_articles, $data['articles']);
                    }
                }
            }

            // Remove duplicates and format
            $processed_trends = [];
            $seen_titles = [];

            foreach ($all_articles as $article) {
                if (!isset($seen_titles[$article['title']])) {
                    $trend = [
                        'title' => $article['title'],
                        'description' => $article['description'] ?? '',
                        'summary' => $this->generateSummary($article['title'], $article['description'] ?? ''),
                        'source' => $article['source']['name'] ?? 'News',
                        'source_url' => $article['url'] ?? '',
                        'image_url' => $article['urlToImage'] ?? '',
                        'published_at' => $article['publishedAt'] ?? date('Y-m-d H:i:s'),
                        'category' => $this->categorizeTrend($article['title'] . ' ' . $article['description']),
                        'trend_type' => $this->determineTrendType($article['title'] . ' ' . $article['description']),
                        'impact_level' => $this->determineImpactLevel($article['title'] . ' ' . $article['description']),
                        'business_insight' => $this->generateBusinessInsight(
                            $this->categorizeTrend($article['title'] . ' ' . $article['description']),
                            $this->determineTrendType($article['title'] . ' ' . $article['description']),
                            $article['title']
                        ),
                        'suggested_action' => $this->generateSuggestedAction(
                            $this->categorizeTrend($article['title'] . ' ' . $article['description']),
                            $this->determineTrendType($article['title'] . ' ' . $article['description'])
                        ),
                        'is_demo' => false
                    ];
                    
                    $processed_trends[] = $trend;
                    $seen_titles[$article['title']] = true;
                    
                    if (count($processed_trends) >= 15) {
                        break;
                    }
                }
            }

            return $processed_trends;
        } catch (Exception $e) {
            error_log('News API Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get demo trends from database
     */
    public function getDemoTrends() {
        $query = "SELECT * FROM market_trends WHERE is_demo = TRUE ORDER BY published_at DESC LIMIT 5";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return [];
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Save trend to database
     */
    public function saveTrend($trend_data) {
        $query = "INSERT INTO market_trends 
                  (title, description, summary, category, trend_type, impact_level, 
                   business_insight, suggested_action, source, source_url, image_url, 
                   published_at, is_demo) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }

        $is_demo = $trend_data['is_demo'] ?? false;
        
        $stmt->bind_param(
            'ssssssssssssi',
            $trend_data['title'],
            $trend_data['description'],
            $trend_data['summary'],
            $trend_data['category'],
            $trend_data['trend_type'],
            $trend_data['impact_level'],
            $trend_data['business_insight'],
            $trend_data['suggested_action'],
            $trend_data['source'],
            $trend_data['source_url'],
            $trend_data['image_url'],
            $trend_data['published_at'],
            $is_demo
        );

        if ($stmt->execute()) {
            return $this->conn->insert_id;
        }
        return false;
    }

    /**
     * Get the last update timestamp
     */
    public function getLastUpdateTimestamp() {
        $query = "SELECT MAX(created_at) as last_update FROM market_trends WHERE is_demo = FALSE";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return null;
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['last_update'] ?? null;
    }

    /**
     * Check if we should refresh trends (cache expiration check)
     */
    public function shouldRefreshTrends($cacheMinutes = 60) {
        $lastUpdate = $this->getLastUpdateTimestamp();
        
        if (!$lastUpdate) {
            return true; // No trends yet, should fetch
        }

        $lastUpdateTime = strtotime($lastUpdate);
        $now = time();
        $minutesElapsed = ($now - $lastUpdateTime) / 60;

        return $minutesElapsed >= $cacheMinutes;
    }

    /**
     * Get cached trends or fetch new ones
     */
    public function getCachedOrFetchTrends($forceRefresh = false) {
        // If force refresh is requested or cache expired, try to fetch new data
        if ($forceRefresh || $this->shouldRefreshTrends()) {
            $new_trends = $this->fetchFromNewsApi();
            
            if ($new_trends && count($new_trends) > 0) {
                // Save new trends to database
                foreach ($new_trends as $trend) {
                    $this->saveTrend($trend);
                }
                return $new_trends;
            }
        }

        // Return all available trends (both live and demo)
        $query = "SELECT * FROM market_trends ORDER BY published_at DESC LIMIT 50";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return $this->getDemoTrends();
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $trends = $result->fetch_all(MYSQLI_ASSOC);

        // If no trends at all, return demo data
        if (empty($trends)) {
            return $this->getDemoTrends();
        }

        return $trends;
    }

    /**
     * Get trends filtered by date range
     */
    public function getTrendsByDateRange($startDate = null, $endDate = null) {
        if (!$startDate) {
            $startDate = date('Y-m-d', strtotime('-7 days'));
        }
        if (!$endDate) {
            $endDate = date('Y-m-d');
        }

        $query = "SELECT * FROM market_trends 
                  WHERE DATE(published_at) BETWEEN ? AND ?
                  ORDER BY published_at DESC";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ss', $startDate, $endDate);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get trend count by date range
     */
    public function getTrendCountByDateRange($startDate = null, $endDate = null) {
        if (!$startDate) {
            $startDate = date('Y-m-d', strtotime('-7 days'));
        }
        if (!$endDate) {
            $endDate = date('Y-m-d');
        }

        $query = "SELECT COUNT(*) as count FROM market_trends 
                  WHERE DATE(published_at) BETWEEN ? AND ? AND is_demo = FALSE";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('ss', $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    }

    /**
     * Remove duplicate trends by source URL
     */
    public function removeDuplicateTrends($trends) {
        $seen = [];
        $unique = [];
        
        foreach ($trends as $trend) {
            $key = $trend['source_url'] . '_' . md5($trend['title']);
            if (!isset($seen[$key])) {
                $unique[] = $trend;
                $seen[$key] = true;
            }
        }
        
        return $unique;
    }
}
?>
