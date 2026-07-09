<?php
/**
 * Performance Helper Functions
 * Provides caching, pagination, and performance optimization utilities
 */

/**
 * Simple file-based cache
 */
class Cache {
    private static $cacheDir = 'cache/';
    private static $defaultTTL = 3600; // 1 hour
    
    /**
     * Initialize cache directory
     */
    public static function init() {
        if (!file_exists(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    /**
     * Get cached data
     */
    public static function get($key) {
        self::init();
        $file = self::$cacheDir . md5($key) . '.cache';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        if (!$data) {
            return null;
        }
        
        // Check if expired
        if (time() > $data['expires']) {
            unlink($file);
            return null;
        }
        
        return $data['value'];
    }
    
    /**
     * Set cached data
     */
    public static function set($key, $value, $ttl = null) {
        self::init();
        $file = self::$cacheDir . md5($key) . '.cache';
        
        $data = [
            'value' => $value,
            'expires' => time() + ($ttl ?? self::$defaultTTL)
        ];
        
        file_put_contents($file, json_encode($data), LOCK_EX);
    }
    
    /**
     * Delete cached data
     */
    public static function delete($key) {
        $file = self::$cacheDir . md5($key) . '.cache';
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    /**
     * Clear all cache
     */
    public static function clear() {
        self::init();
        $files = glob(self::$cacheDir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}

/**
 * Pagination helper
 */
class Paginator {
    private $totalItems;
    private $itemsPerPage;
    private $currentPage;
    private $totalPages;
    
    public function __construct($totalItems, $itemsPerPage = 20, $currentPage = 1) {
        $this->totalItems = max(0, (int)$totalItems);
        $this->itemsPerPage = max(1, (int)$itemsPerPage);
        $this->currentPage = max(1, (int)$currentPage);
        $this->totalPages = ceil($this->totalItems / $this->itemsPerPage);
        
        if ($this->currentPage > $this->totalPages && $this->totalPages > 0) {
            $this->currentPage = $this->totalPages;
        }
    }
    
    /**
     * Get offset for SQL query
     */
    public function getOffset() {
        return ($this->currentPage - 1) * $this->itemsPerPage;
    }
    
    /**
     * Get limit for SQL query
     */
    public function getLimit() {
        return $this->itemsPerPage;
    }
    
    /**
     * Get current page
     */
    public function getCurrentPage() {
        return $this->currentPage;
    }
    
    /**
     * Get total pages
     */
    public function getTotalPages() {
        return $this->totalPages;
    }
    
    /**
     * Get total items
     */
    public function getTotalItems() {
        return $this->totalItems;
    }
    
    /**
     * Check if there's a next page
     */
    public function hasNextPage() {
        return $this->currentPage < $this->totalPages;
    }
    
    /**
     * Check if there's a previous page
     */
    public function hasPrevPage() {
        return $this->currentPage > 1;
    }
    
    /**
     * Get next page number
     */
    public function getNextPage() {
        return $this->hasNextPage() ? $this->currentPage + 1 : null;
    }
    
    /**
     * Get previous page number
     */
    public function getPrevPage() {
        return $this->hasPrevPage() ? $this->currentPage - 1 : null;
    }
    
    /**
     * Generate pagination info array
     */
    public function toArray() {
        return [
            'total_items' => $this->totalItems,
            'items_per_page' => $this->itemsPerPage,
            'current_page' => $this->currentPage,
            'total_pages' => $this->totalPages,
            'has_next' => $this->hasNextPage(),
            'has_prev' => $this->hasPrevPage(),
            'next_page' => $this->getNextPage(),
            'prev_page' => $this->getPrevPage()
        ];
    }
}

/**
 * Image optimization helper
 */
class ImageOptimizer {
    /**
     * Compress image
     */
    public static function compress($sourcePath, $destinationPath, $quality = 80) {
        $info = getimagesize($sourcePath);
        
        if (!$info) {
            return false;
        }
        
        $mime = $info['mime'];
        
        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $image = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }
        
        if ($mime === 'image/png') {
            $quality = (int)(($quality / 100) * 9);
        }
        
        $result = imagejpeg($image, $destinationPath, $quality);
        imagedestroy($image);
        
        return $result;
    }
    
    /**
     * Create thumbnail
     */
    public static function createThumbnail($sourcePath, $destinationPath, $maxWidth = 300, $maxHeight = 300) {
        $info = getimagesize($sourcePath);
        
        if (!$info) {
            return false;
        }
        
        $width = $info[0];
        $height = $info[1];
        $mime = $info['mime'];
        
        // Calculate new dimensions
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);
        
        // Create image
        switch ($mime) {
            case 'image/jpeg':
                $srcImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $srcImage = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $srcImage = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }
        
        $dstImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($mime === 'image/png' || $mime === 'image/gif') {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
        }
        
        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        $result = imagejpeg($dstImage, $destinationPath, 80);
        imagedestroy($srcImage);
        imagedestroy($dstImage);
        
        return $result;
    }
}

/**
 * Lazy loading helper
 */
class LazyLoader {
    /**
     * Get items with lazy loading
     */
    public static function loadItems($queryCallback, $page = 1, $perPage = 20) {
        // Get total count
        $totalQuery = str_replace(['SELECT *', 'SELECT'], ['SELECT COUNT(*) as total', 'SELECT'], $queryCallback);
        $totalResult = $conn->query($totalQuery);
        $totalItems = $totalResult->fetch_assoc()['total'];
        
        // Create paginator
        $paginator = new Paginator($totalItems, $perPage, $page);
        
        // Get items with limit
        $offset = $paginator->getOffset();
        $limit = $paginator->getLimit();
        
        $query = $queryCallback . " LIMIT $offset, $limit";
        $result = $conn->query($query);
        
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        
        return [
            'items' => $items,
            'pagination' => $paginator->toArray()
        ];
    }
}

/**
 * Query optimizer
 */
class QueryOptimizer {
    /**
     * Build optimized SELECT query with indexes
     */
    public static function buildOptimizedQuery($table, $columns = '*', $conditions = [], $orderBy = null, $limit = null, $offset = null) {
        $query = "SELECT $columns FROM $table";
        
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        if ($orderBy) {
            $query .= " ORDER BY $orderBy";
        }
        
        if ($limit) {
            $query .= " LIMIT $limit";
        }
        
        if ($offset) {
            $query .= " OFFSET $offset";
        }
        
        return $query;
    }
    
    /**
     * Get query execution time
     */
    public static function getQueryTime($conn, $query) {
        $start = microtime(true);
        $conn->query($query);
        $end = microtime(true);
        return $end - $start;
    }
    
    /**
     * Log slow query
     */
    public static function logSlowQuery($query, $executionTime, $threshold = 1.0) {
        if ($executionTime > $threshold) {
            error_log("Slow Query ({$executionTime}s): " . substr($query, 0, 200));
        }
    }
}

/**
 * Response compression
 */
class ResponseCompressor {
    /**
     * Compress response data
     */
    public static function compress($data) {
        return gzencode(json_encode($data), 9);
    }
    
    /**
     * Check if client accepts gzip
     */
    public static function acceptsGzip() {
        return isset($_SERVER['HTTP_ACCEPT_ENCODING']) && 
               strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false;
    }
}

/**
 * API response helper with optimization
 */
function apiResponse($success, $data = null, $message = null, $cacheKey = null, $cacheTTL = 300) {
    $response = [
        'success' => $success,
        'timestamp' => time()
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    if ($message !== null) {
        $response['message'] = $message;
    }
    
    // Cache successful responses
    if ($success && $cacheKey && !empty($data)) {
        Cache::set($cacheKey, $response, $cacheTTL);
    }
    
    // Compress if client accepts
    if (ResponseCompressor::acceptsGzip()) {
        header('Content-Encoding: gzip');
        echo ResponseCompressor::compress($response);
    } else {
        echo json_encode($response);
    }
}

/**
 * Get cached API response
 */
function getCachedResponse($cacheKey) {
    return Cache::get($cacheKey);
}

/**
 * Invalidate cache for a pattern
 */
function invalidateCache($pattern) {
    Cache::init();
    $files = glob(Cache::$cacheDir . '*.cache');
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        if (strpos($content, $pattern) !== false) {
            unlink($file);
        }
    }
}

/**
 * Batch insert helper for better performance
 */
function batchInsert($conn, $table, $columns, $data, $batchSize = 1000) {
    $total = count($data);
    $inserted = 0;
    
    for ($i = 0; $i < $total; $i += $batchSize) {
        $batch = array_slice($data, $i, $batchSize);
        $values = [];
        $params = [];
        
        foreach ($batch as $row) {
            $placeholders = [];
            foreach ($columns as $col) {
                $placeholders[] = '?';
                $params[] = $row[$col] ?? null;
            }
            $values[] = '(' . implode(',', $placeholders) . ')';
        }
        
        $query = "INSERT INTO $table (" . implode(',', $columns) . ") VALUES " . implode(',', $values);
        $stmt = $conn->prepare($query);
        
        if ($stmt->execute($params)) {
            $inserted += count($batch);
        }
        
        $stmt->close();
    }
    
    return $inserted;
}

/**
 * Memory usage helper
 */
function getMemoryUsage() {
    return memory_get_usage(true);
}

/**
 * Check if memory limit is approaching
 */
function isMemoryLow($threshold = 128 * 1024 * 1024) { // 128MB default
    return getMemoryUsage() > $threshold;
}

/**
 * Clear output buffer to free memory
 */
function clearOutputBuffer() {
    while (ob_get_level()) {
        ob_end_clean();
    }
}