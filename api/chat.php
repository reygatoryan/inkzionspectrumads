<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db-config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'user';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'conversations';
    
    if ($action === 'conversations') {
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        
        // Get list of conversations
        if ($userRole === 'admin') {
            $sql = "
                SELECT cc.id, cc.user_id, cc.admin_id, cc.last_message_at, cc.request_id, cc.product_id,
                       u.name as user_name, u.is_online as user_online,
                       (SELECT COUNT(*) FROM chat_messages WHERE conversation_id = cc.id AND is_read = 0 AND sender_id != ?) as unread_count,
                       (SELECT content FROM chat_messages WHERE conversation_id = cc.id ORDER BY created_at DESC LIMIT 1) as last_message,
                       cpr.service_type as request_type, cpr.status as request_status,
                       p.name as product_name, p.image_url as product_image
                FROM chat_conversations cc
                JOIN users u ON cc.user_id = u.id
                LEFT JOIN custom_printing_requests cpr ON cc.request_id = cpr.id
                LEFT JOIN products p ON cc.product_id = p.id
                WHERE cc.admin_id = ?
            ";
            $params = [$userId, $userId];
            $types = 'ii';
            
            if ($search !== '') {
                $sql .= " AND (u.name LIKE ? OR p.name LIKE ?)";
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $types .= 'ss';
            }
            
            $sql .= " ORDER BY cc.last_message_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
        } else {
            $sql = "
                SELECT cc.id, cc.user_id, cc.admin_id, cc.last_message_at, cc.request_id, cc.product_id,
                       u.name as seller_name, u.is_online as seller_online,
                       (SELECT COUNT(*) FROM chat_messages WHERE conversation_id = cc.id AND is_read = 0 AND sender_id != ?) as unread_count,
                       (SELECT content FROM chat_messages WHERE conversation_id = cc.id ORDER BY created_at DESC LIMIT 1) as last_message,
                       cpr.service_type as request_type, cpr.status as request_status,
                       p.name as product_name, p.image_url as product_image
                FROM chat_conversations cc
                JOIN users u ON cc.admin_id = u.id
                LEFT JOIN custom_printing_requests cpr ON cc.request_id = cpr.id
                LEFT JOIN products p ON cc.product_id = p.id
                WHERE cc.user_id = ?
            ";
            $params = [$userId, $userId];
            $types = 'ii';
            
            if ($search !== '') {
                $sql .= " AND (u.name LIKE ? OR p.name LIKE ?)";
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $types .= 'ss';
            }
            
            $sql .= " ORDER BY cc.last_message_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to load conversations', 'sql_error' => $stmt->error]);
            $conn->close();
            exit;
        }
        $result = $stmt->get_result();
        $conversations = [];
        while ($row = $result->fetch_assoc()) {
            $conversations[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'conversations' => $conversations]);
        $conn->close();
        exit;
    }
    
    if ($action === 'messages' && isset($_GET['conversation_id'])) {
        $conversationId = (int)$_GET['conversation_id'];
        $limit = isset($_GET['limit']) ? min(max((int)$_GET['limit'], 1), 100) : 50;
        $offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;
        
        // Verify access
        $checkStmt = $conn->prepare("
            SELECT id FROM chat_conversations 
            WHERE id = ? AND (user_id = ? OR admin_id = ?)
        ");
        $checkStmt->bind_param('iii', $conversationId, $userId, $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkStmt->close();
        
        if (!$checkResult || $checkResult->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Conversation not found']);
            $conn->close();
            exit;
        }
        
        // Mark messages as read (only on first load, not pagination)
        if ($offset === 0) {
            $updateStmt = $conn->prepare("
                UPDATE chat_messages 
                SET is_read = 1 
                WHERE conversation_id = ? AND sender_id != ? AND is_read = 0
            ");
            $updateStmt->bind_param('ii', $conversationId, $userId);
            $updateStmt->execute();
            $updateStmt->close();
        }
        
        // Get total count for has_more
        $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM chat_messages WHERE conversation_id = ?");
        $countStmt->bind_param('i', $conversationId);
        $countStmt->execute();
        $totalResult = $countStmt->get_result()->fetch_assoc();
        $totalMessages = (int)$totalResult['total'];
        $countStmt->close();
        
        // Fetch messages (most recent first for pagination, then reverse)
        $msgStmt = $conn->prepare("
            SELECT cm.*, u.name as sender_name, u.role as sender_role
            FROM chat_messages cm
            JOIN users u ON cm.sender_id = u.id
            WHERE cm.conversation_id = ?
            ORDER BY cm.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $msgStmt->bind_param('iii', $conversationId, $limit, $offset);
        $msgStmt->execute();
        $result = $msgStmt->get_result();
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        $msgStmt->close();
        
        // Reverse to chronological order
        $messages = array_reverse($messages);
        
        echo json_encode([
            'success' => true, 
            'messages' => $messages,
            'has_more' => ($offset + $limit) < $totalMessages,
            'total' => $totalMessages
        ]);
        $conn->close();
        exit;
    }
    
    if ($action === 'typing_status' && isset($_GET['conversation_id'])) {
        $convId = (int)$_GET['conversation_id'];
        $checkStmt = $conn->prepare("SELECT id FROM chat_conversations WHERE id = ? AND (user_id = ? OR admin_id = ?)");
        $checkStmt->bind_param('iii', $convId, $userId, $userId);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Not found']);
            $checkStmt->close();
            $conn->close();
            exit;
        }
        $checkStmt->close();
        
        $stmt = $conn->prepare("
            SELECT ct.user_id, ct.is_typing, u.name as user_name
            FROM chat_typing ct
            JOIN users u ON ct.user_id = u.id
            WHERE ct.conversation_id = ? AND ct.is_typing = 1 AND ct.user_id != ?
            ORDER BY ct.updated_at DESC LIMIT 1
        ");
        $stmt->bind_param('ii', $convId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $typing = $result->fetch_assoc();
        $stmt->close();
        
        if ($typing) {
            echo json_encode(['success' => true, 'is_typing' => true, 'user_id' => (int)$typing['user_id'], 'user_name' => $typing['user_name']]);
        } else {
            echo json_encode(['success' => true, 'is_typing' => false]);
        }
        $conn->close();
        exit;
    }
    
    if ($action === 'sellers') {
        // Get list of available sellers
        $stmt = $conn->prepare("
            SELECT id, name, is_online, last_seen FROM users 
            WHERE role IN ('admin', 'seller')
            ORDER BY is_online DESC, last_seen DESC
        ");
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch sellers', 'sql_error' => $stmt->error]);
            $conn->close();
            exit;
        }
        $result = $stmt->get_result();
        $sellers = [];
        while ($row = $result->fetch_assoc()) {
            $sellers[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'sellers' => $sellers]);
        $conn->close();
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }
    
    $action = $data['action'] ?? '';
    
    if ($action === 'start_conversation' && isset($data['admin_id'])) {
        try {
        $sellerId = (int)$data['admin_id'];
        $requestId = isset($data['request_id']) ? (int)$data['request_id'] : null;
        $productId = isset($data['product_id']) ? (int)$data['product_id'] : null;
        
        // Create new conversation (always creates fresh chat)
        if ($requestId) {
            $stmt = $conn->prepare("
                INSERT INTO chat_conversations (user_id, admin_id, request_id, product_id, last_message_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param('iiii', $userId, $sellerId, $requestId, $productId);
        } elseif ($productId) {
            $stmt = $conn->prepare("
                INSERT INTO chat_conversations (user_id, admin_id, product_id, last_message_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->bind_param('iii', $userId, $sellerId, $productId);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO chat_conversations (user_id, admin_id, last_message_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->bind_param('ii', $userId, $sellerId);
        }
        
        if ($stmt->execute()) {
            $conversationId = $conn->insert_id;

            // Auto-create custom printing request if starting with product_id
            if ($productId && !$requestId) {
                $prodQ = $conn->query("SELECT name FROM products WHERE id = $productId");
                $prodName = $prodQ && $prodQ->num_rows ? $prodQ->fetch_assoc()['name'] : 'Custom Product';
                $reqStmt = $conn->prepare("INSERT INTO custom_printing_requests (user_id, service_type, status) VALUES (?, ?, 'pending')");
                $reqStmt->bind_param('is', $userId, $prodName);
                $reqStmt->execute();
                $newReqId = $conn->insert_id;
                $reqStmt->close();
                $conn->query("UPDATE chat_conversations SET request_id = $newReqId WHERE id = $conversationId");
            }

            // Auto-response
            $arStmt = $conn->prepare("SELECT settings FROM shipping_settings WHERE section_key = 'shipping' LIMIT 1");
            $arStmt->execute();
            $arRow = $arStmt->get_result()->fetch_assoc();
            $arStmt->close();
            if ($arRow) {
                $shipSet = json_decode($arRow['settings'], true);
                if (!empty($shipSet['chat']['auto_response']['enabled']) && !empty($shipSet['chat']['auto_response']['message'])) {
                    $welcomeMsg = $shipSet['chat']['auto_response']['message'];
                    $ams = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_id, message_type, content) VALUES (?, ?, 'text', ?)");
                    $ams->bind_param('iis', $conversationId, $sellerId, $welcomeMsg);
                    $ams->execute();
                    $ams->close();
                }
            }

            echo json_encode(['success' => true, 'conversation_id' => $conversationId]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to start conversation', 'sql_error' => $stmt->error]);
        }
        $stmt->close();
        $conn->close();
        exit;
        } catch (Throwable $th) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $th->getMessage(), 'file' => $th->getFile(), 'line' => $th->getLine()]);
            if (isset($conn) && $conn instanceof mysqli) $conn->close();
            exit;
        }
    }
    
    if ($action === 'send_message' && (isset($data['conversation_id']) || isset($_POST['conversation_id']))) {
        try {
        $conversationId = (int)($data['conversation_id'] ?? $_POST['conversation_id']);
        $messageType = $data['message_type'] ?? $_POST['message_type'] ?? 'text';
        $content = isset($data['content']) ? trim($data['content']) : (isset($_POST['content']) ? trim($_POST['content']) : '');
        $fileUrl = isset($data['file_url']) ? trim($data['file_url']) : (isset($_POST['file_url']) ? trim($_POST['file_url']) : '');
        $fileName = isset($data['file_name']) ? trim($data['file_name']) : (isset($_POST['file_name']) ? trim($_POST['file_name']) : '');
        $fileType = isset($data['file_type']) ? trim($data['file_type']) : (isset($_POST['file_type']) ? trim($_POST['file_type']) : '');
        
        // Handle file upload from multipart form
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/chats/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $safeName = uniqid('chat_', true) . '.' . $ext;
            $destPath = $uploadDir . $safeName;
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $destPath)) {
                $fileUrl = 'uploads/chats/' . $safeName;
                $fileName = $_FILES['file']['name'];
                $fileType = $_FILES['file']['type'];
                if ($messageType === 'text') {
                    $messageType = str_starts_with($fileType, 'image/') ? 'image' : 'file';
                }
            }
        }
        
        // Backward compatibility: if file_url is a data URL, save it to disk
        if (str_starts_with($fileUrl, 'data:')) {
            $uploadDir = __DIR__ . '/../uploads/chats/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = 'bin';
            if (preg_match('/^data:image\/(\w+);base64,/', $fileUrl, $m)) {
                $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
            }
            $safeName = uniqid('chat_', true) . '.' . $ext;
            $destPath = $uploadDir . $safeName;
            $dataPieces = explode(',', $fileUrl);
            if (isset($dataPieces[1]) && file_put_contents($destPath, base64_decode($dataPieces[1])) !== false) {
                $fileUrl = 'uploads/chats/' . $safeName;
            }
        }
        
        // Verify access
        $checkStmt = $conn->prepare("
            SELECT id FROM chat_conversations 
            WHERE id = ? AND (user_id = ? OR admin_id = ?)
        ");
        $checkStmt->bind_param('iii', $conversationId, $userId, $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkStmt->close();
        
        if (!$checkResult || $checkResult->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Conversation not found']);
            $conn->close();
            exit;
        }
        
        // Get conversation details (customer + admin IDs, product_id)
        $convStmt = $conn->prepare("
            SELECT cc.user_id, cc.admin_id, cc.product_id,
                   p.name as product_name FROM chat_conversations cc
            LEFT JOIN products p ON cc.product_id = p.id
            WHERE cc.id = ?
        ");
        $convStmt->bind_param('i', $conversationId);
        $convStmt->execute();
        $conv = $convStmt->get_result()->fetch_assoc();
        $convStmt->close();
        
        $customerId = $conv['user_id'];
        $isAdmin = ($userRole === 'admin');
        $recipientId = $isAdmin ? $customerId : $conv['admin_id'];
        
        // Insert message
        $stmt = $conn->prepare("
            INSERT INTO chat_messages (conversation_id, sender_id, message_type, content, file_url, file_name, file_type)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('iisssss', $conversationId, $userId, $messageType, $content, $fileUrl, $fileName, $fileType);
        
        if ($stmt->execute()) {
            $messageId = $conn->insert_id;
            
            // Update conversation last_message_at
            $updateStmt = $conn->prepare("
                UPDATE chat_conversations SET last_message_at = NOW() WHERE id = ?
            ");
            $updateStmt->bind_param('i', $conversationId);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Handle custom_request: admin sends a custom printing request link
            if ($messageType === 'custom_request' && $isAdmin) {
                $reqData = json_decode($content, true) ?: ['title' => 'Custom Request'];
                $serviceType = $reqData['title'] ?? 'Custom Request';
                $productName = $conv['product_name'] ?? '';
                if ($productName && $serviceType === 'Custom Request') {
                    $serviceType = $productName;
                }
                
                $insertReq = $conn->prepare("
                    INSERT INTO custom_printing_requests (user_id, service_type, status, created_at)
                    VALUES (?, ?, 'pending', NOW())
                ");
                $insertReq->bind_param('is', $customerId, $serviceType);
                $insertReq->execute();
                $newRequestId = $conn->insert_id;
                $insertReq->close();
                
                // Link conversation to this request
                $conn->query("UPDATE chat_conversations SET request_id = $newRequestId WHERE id = $conversationId");
                
                // Update message content to include request_id
                $newContent = json_encode(['request_id' => $newRequestId, 'title' => $serviceType]);
                $updateMsg = $conn->prepare("UPDATE chat_messages SET content = ? WHERE id = ?");
                $updateMsg->bind_param('si', $newContent, $messageId);
                $updateMsg->execute();
                $updateMsg->close();
            }
            
            // Handle order_form: admin sends an order proposal link
            if ($messageType === 'order_form' && $isAdmin) {
                $reqData = json_decode($content, true) ?: [];
                $requestId = $reqData['request_id'] ?? null;
                $title = $reqData['title'] ?? 'Order Form';
                
                if (empty($reqData['proposal_id'])) {
                    // Create a new order proposal
                    $items = $reqData['items'] ?? json_encode([['name' => $title, 'quantity' => 1, 'unit_price' => 0]]);
                    if (is_array($items)) $items = json_encode($items);
                    
                    $insertProp = $conn->prepare("
                        INSERT INTO order_proposals (user_id, request_id, conversation_id, items, subtotal, shipping_fee, total_amount, admin_notes, status, created_at)
                        VALUES (?, ?, ?, ?, 0, 0, 0, ?, 'sent', NOW())
                    ");
                    $adminNotes = "Order form for: " . ($reqData['title'] ?? '');
                    $insertProp->bind_param('iiiss', $customerId, $requestId, $conversationId, $items, $adminNotes);
                    $insertProp->execute();
                    $newProposalId = $conn->insert_id;
                    $insertProp->close();
                } else {
                    // Proposal already created (e.g., from CR modal)
                    $newProposalId = (int)$reqData['proposal_id'];
                }
                
                $newContent = json_encode(['proposal_id' => $newProposalId, 'request_id' => $requestId, 'title' => $title]);
                $updateMsg = $conn->prepare("UPDATE chat_messages SET content = ? WHERE id = ?");
                $updateMsg->bind_param('si', $newContent, $messageId);
                $updateMsg->execute();
                $updateMsg->close();
            }
            
            // Create notification for recipient
            $notifStmt = $conn->prepare("
                INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at)
                VALUES (?, 'chat_message', ?, ?, 'chat', ?, NOW())
            ");
            $notifTitle = "New Message";
            if ($messageType === 'custom_request') {
                $notifBody = "Sent you a custom request form";
            } elseif ($messageType === 'order_form') {
                $notifBody = "Sent you an order form";
            } else {
                $notifBody = $messageType === 'text' ? $content : "Sent you a $messageType";
            }
            $notifStmt->bind_param('issi', $recipientId, $notifTitle, $notifBody, $conversationId);
            $notifStmt->execute();
            $notifStmt->close();
            
            echo json_encode(['success' => true, 'message_id' => $messageId]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to send message']);
        }
        $stmt->close();
        $conn->close();
        exit;
        } catch (Throwable $th) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $th->getMessage(), 'file' => $th->getFile(), 'line' => $th->getLine()]);
            if (isset($conn) && $conn instanceof mysqli) $conn->close();
            exit;
        }
    }
    
    if ($action === 'delete_conversation' && isset($data['conversation_id'])) {
        try {
            $conversationId = (int)$data['conversation_id'];

            $checkStmt = $conn->prepare("
                SELECT id FROM chat_conversations 
                WHERE id = ? AND (user_id = ? OR admin_id = ?)
            ");
            $checkStmt->bind_param('iii', $conversationId, $userId, $userId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $checkStmt->close();

            if (!$checkResult || $checkResult->num_rows === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Conversation not found']);
                $conn->close();
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM chat_conversations WHERE id = ?");
            $stmt->bind_param('i', $conversationId);

            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to delete conversation']);
            }
            $stmt->close();
            $conn->close();
            exit;
        } catch (Throwable $th) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $th->getMessage(), 'file' => $th->getFile(), 'line' => $th->getLine()]);
            if (isset($conn) && $conn instanceof mysqli) $conn->close();
            exit;
        }
    }

    if ($action === 'typing' && isset($data['conversation_id'])) {
        try {
        $conversationId = (int)$data['conversation_id'];
        $isTyping = isset($data['is_typing']) ? (int)$data['is_typing'] : 1;
        
        // Verify access
        $checkStmt = $conn->prepare("
            SELECT id FROM chat_conversations 
            WHERE id = ? AND (user_id = ? OR admin_id = ?)
        ");
        $checkStmt->bind_param('iii', $conversationId, $userId, $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkStmt->close();
        
        if (!$checkResult || $checkResult->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Conversation not found']);
            $conn->close();
            exit;
        }
        
        // Update typing status
        $stmt = $conn->prepare("
            INSERT INTO chat_typing (conversation_id, user_id, is_typing)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE is_typing = ?, updated_at = NOW()
        ");
        $stmt->bind_param('iiii', $conversationId, $userId, $isTyping, $isTyping);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => true]);
        $conn->close();
        exit;
        } catch (Throwable $th) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $th->getMessage(), 'file' => $th->getFile(), 'line' => $th->getLine()]);
            if (isset($conn) && $conn instanceof mysqli) $conn->close();
            exit;
        }
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
