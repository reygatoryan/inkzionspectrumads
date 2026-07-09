<?php
/**
 * Error Handler and Logger
 * Provides centralized error handling, logging, and user-friendly error pages
 */

// Initialize error handling
function initErrorHandler() {
    // Set custom error handler
    set_error_handler('handleError');
    
    // Set custom exception handler
    set_exception_handler('handleException');
    
    // Set custom shutdown handler for fatal errors
    register_shutdown_function('handleShutdown');
}

/**
 * Handle PHP errors
 */
function handleError($errno, $errstr, $errfile, $errline) {
    // Don't handle errors suppressed with @
    if (error_reporting() === 0) {
        return false;
    }
    
    $errorMessage = formatError($errno, $errstr, $errfile, $errline);
    logError($errorMessage, 'PHP_ERROR');
    
    // Don't display errors in production
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
        return true;
    }
    
    return false;
}

/**
 * Handle uncaught exceptions
 */
function handleException($exception) {
    $errorMessage = 'Uncaught Exception: ' . $exception->getMessage() . 
                   ' in ' . $exception->getFile() . ':' . $exception->getLine();
    
    logError($errorMessage, 'EXCEPTION', $exception->getTraceAsString());
    
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
        showErrorPage(500, 'Internal Server Error', 'Something went wrong. Please try again later.');
    } else {
        echo '<h1>Error</h1>';
        echo '<p>' . htmlspecialchars($errorMessage) . '</p>';
        echo '<pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>';
    }
    
    exit;
}

/**
 * Handle shutdown (fatal errors)
 */
function handleShutdown() {
    $error = error_get_last();
    
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $errorMessage = formatError($error['type'], $error['message'], $error['file'], $error['line']);
        logError($errorMessage, 'FATAL_ERROR');
        
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
            showErrorPage(500, 'Internal Server Error', 'Something went wrong. Please try again later.');
        } else {
            echo '<h1>Fatal Error</h1>';
            echo '<p>' . htmlspecialchars($errorMessage) . '</p>';
        }
    }
}

/**
 * Format error message
 */
function formatError($errno, $errstr, $errfile, $errline) {
    $types = [
        E_ERROR => 'Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];
    
    $type = isset($types[$errno]) ? $types[$errno] : 'Unknown Error';
    
    return "[$type] $errstr in $errfile on line $errline";
}

/**
 * Log error to file and database
 */
function logError($message, $type = 'ERROR', $context = null) {
    $timestamp = date('Y-m-d H:i:s');
    $userId = $_SESSION['user_id'] ?? null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $url = $_SERVER['REQUEST_URI'] ?? 'unknown';
    
    // Log to file
    $logFile = __DIR__ . '/../logs/error.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logMessage = "[$timestamp] [$type] User: $userId | IP: $ipAddress | URL: $url | $message";
    
    if ($context) {
        $logMessage .= "\nContext: " . substr($context, 0, 500);
    }
    
    $logMessage .= "\n" . str_repeat('-', 80) . "\n";
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    
    // Log to database if connection is available
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address, metadata, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $metadata = json_encode([
                'type' => $type,
                'url' => $url,
                'context' => $context ? substr($context, 0, 1000) : null
            ]);
            
            $stmt->bind_param('issss', $userId, $type, $message, $ipAddress, $metadata);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            // Silently fail if database logging fails
        }
    }
}

/**
 * Log successful operations
 */
function logSuccess($action, $description, $context = null) {
    $timestamp = date('Y-m-d H:i:s');
    $userId = $_SESSION['user_id'] ?? null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $logFile = __DIR__ . '/../logs/success.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logMessage = "[$timestamp] [SUCCESS] User: $userId | IP: $ipAddress | $action: $description";
    
    if ($context) {
        $logMessage .= " | Context: " . substr($context, 0, 200);
    }
    
    $logMessage .= "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Show error page
 */
function showErrorPage($code, $title, $message) {
    http_response_code($code);
    
    $errorPage = match($code) {
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Page Not Found',
        405 => 'Method Not Allowed',
        408 => 'Request Timeout',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        default => 'Error'
    };
    
    $title = $title ?: $errorPage;
    
    include __DIR__ . '/../templates/error.php';
    exit;
}

/**
 * Validate and sanitize with error handling
 */
function validateOrFail($condition, $errorMessage, $errorCode = 400) {
    if (!$condition) {
        logError($errorMessage, 'VALIDATION_ERROR');
        
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            http_response_code($errorCode);
            echo json_encode([
                'success' => false,
                'error' => $errorMessage,
                'timestamp' => time()
            ]);
            exit;
        } else {
            showErrorPage($errorCode, 'Validation Error', $errorMessage);
        }
    }
}

/**
 * Retry failed database operation
 */
function retryDbOperation($callback, $maxRetries = 3, $delay = 100) {
    $attempts = 0;
    
    while ($attempts < $maxRetries) {
        try {
            return $callback();
        } catch (Exception $e) {
            $attempts++;
            
            if ($attempts >= $maxRetries) {
                logError('Database operation failed after ' . $maxRetries . ' attempts: ' . $e->getMessage(), 'DB_ERROR');
                throw $e;
            }
            
            // Wait before retrying
            usleep($delay * 1000);
            $delay *= 2; // Exponential backoff
        }
    }
}

/**
 * Safe database query with error handling
 */
function safeQuery($conn, $query, $params = null) {
    try {
        $stmt = $conn->prepare($query);
        
        if ($params) {
            $types = '';
            $values = [];
            
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_null($param)) {
                    $types .= 's';
                    $param = null;
                } else {
                    $types .= 's';
                }
                $values[] = $param;
            }
            
            $stmt->bind_param($types, ...$values);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        logSuccess('DB_QUERY', 'Query executed successfully', substr($query, 0, 100));
        
        return [
            'success' => true,
            'stmt' => $stmt,
            'result' => $result
        ];
        
    } catch (Exception $e) {
        logError('Database query failed: ' . $e->getMessage() . ' | Query: ' . substr($query, 0, 200), 'DB_ERROR');
        
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'stmt' => null,
            'result' => null
        ];
    }
}

/**
 * Handle API errors consistently
 */
function apiError($message, $code = 400, $details = null) {
    http_response_code($code);
    
    $response = [
        'success' => false,
        'error' => $message,
        'timestamp' => time()
    ];
    
    if ($details && defined('ENVIRONMENT') && ENVIRONMENT !== 'production') {
        $response['details'] = $details;
    }
    
    logError("API Error: $message", 'API_ERROR', $details);
    
    echo json_encode($response);
    exit;
}

/**
 * Handle successful API response
 */
function apiSuccess($data = null, $message = null, $cacheKey = null) {
    $response = [
        'success' => true,
        'timestamp' => time()
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    if ($message !== null) {
        $response['message'] = $message;
    }
    
    if ($cacheKey) {
        // Cache::set($cacheKey, $response, 300);
    }
    
    logSuccess('API_SUCCESS', $message ?: 'Request completed successfully');
    
    echo json_encode($response);
    exit;
}

/**
 * Validate required fields
 */
function validateRequired($data, $fields) {
    $missing = [];
    
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        $errorMessage = 'Missing required fields: ' . implode(', ', $missing);
        logError($errorMessage, 'VALIDATION_ERROR');
        
        return [
            'valid' => false,
            'error' => $errorMessage,
            'missing_fields' => $missing
        ];
    }
    
    return ['valid' => true];
}

/**
 * Validate email format
 */
function validateEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'valid' => false,
            'error' => 'Invalid email format'
        ];
    }
    
    return ['valid' => true];
}

/**
 * Validate password strength
 */
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character';
    }
    
    if (!empty($errors)) {
        return [
            'valid' => false,
            'error' => implode(', ', $errors)
        ];
    }
    
    return ['valid' => true];
}

/**
 * Validate file upload
 */
function validateUpload($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'], $maxSize = 5 * 1024 * 1024) {
    $errors = [];
    
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed with error code: ' . ($file['error'] ?? 'unknown');
        return ['valid' => false, 'error' => implode(', ', $errors)];
    }
    
    if ($file['size'] > $maxSize) {
        $errors[] = 'File size exceeds maximum limit of ' . ($maxSize / 1024 / 1024) . 'MB';
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        $errors[] = 'Invalid file type: ' . $mimeType . '. Allowed types: ' . implode(', ', $allowedTypes);
    }
    
    if (!empty($errors)) {
        return [
            'valid' => false,
            'error' => implode(', ', $errors)
        ];
    }
    
    return ['valid' => true, 'mime_type' => $mimeType];
}

/**
 * Retry failed HTTP request
 */
function retryHttpRequest($url, $options = [], $maxRetries = 3) {
    $attempts = 0;
    $delay = 1000; // 1 second
    
    while ($attempts < $maxRetries) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            
            if (!empty($options)) {
                foreach ($options as $key => $value) {
                    curl_setopt($ch, $key, $value);
                }
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception($error);
            }
            
            if ($httpCode >= 200 && $httpCode < 300) {
                logSuccess('HTTP_REQUEST', "Request to $url succeeded");
                return [
                    'success' => true,
                    'data' => $response,
                    'http_code' => $httpCode
                ];
            } else {
                throw new Exception("HTTP error: $httpCode");
            }
            
        } catch (Exception $e) {
            $attempts++;
            
            if ($attempts >= $maxRetries) {
                logError("HTTP request to $url failed after $maxRetries attempts: " . $e->getMessage(), 'HTTP_ERROR');
                
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'attempts' => $attempts
                ];
            }
            
            // Wait before retrying
            sleep($delay / 1000);
            $delay *= 2; // Exponential backoff
        }
    }
}

/**
 * Check if request is AJAX
 */
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Check if request expects JSON
 */
function expectsJson() {
    return isset($_SERVER['HTTP_ACCEPT']) && 
           strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
}

/**
 * Send JSON response with error handling
 */
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    
    try {
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Exception $e) {
        logError('JSON encoding failed: ' . $e->getMessage(), 'JSON_ERROR');
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to encode response'
        ]);
    }
    
    exit;
}

/**
 * Get user-friendly error message
 */
function getUserFriendlyMessage($error) {
    $messages = [
        'database' => 'We\'re experiencing technical difficulties. Please try again later.',
        'network' => 'Network error. Please check your connection and try again.',
        'validation' => 'Please check your input and try again.',
        'authentication' => 'Please log in to continue.',
        'authorization' => 'You don\'t have permission to perform this action.',
        'not_found' => 'The requested resource was not found.',
        'timeout' => 'The request timed out. Please try again.',
        'unknown' => 'Something went wrong. Please try again later.'
    ];
    
    $errorLower = strtolower($error);
    
    foreach ($messages as $key => $message) {
        if (strpos($errorLower, $key) !== false) {
            return $message;
        }
    }
    
    return $messages['unknown'];
}

/**
 * Initialize error handling
 */
initErrorHandler();
?>