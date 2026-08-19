<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }

    $action = isset($data['action']) ? trim($data['action']) : '';

    // === ADMIN: Update request status ===
    if ($action === 'update_status') {
        if (!in_array($userRole, ['admin', 'admin'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            exit;
        }
        $requestId = isset($data['request_id']) ? (int)$data['request_id'] : 0;
        $newStatus = isset($data['status']) ? trim($data['status']) : '';
        $adminNotes = isset($data['admin_notes']) ? trim($data['admin_notes']) : '';

        $validStatuses = ['pending', 'in_review', 'approved', 'rejected', 'ready_for_purchase', 'completed'];
        if (!in_array($newStatus, $validStatuses)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid status']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE custom_printing_requests SET status = ?, admin_notes = ?, is_viewed = 0 WHERE id = ?");
        $stmt->bind_param('ssi', $newStatus, $adminNotes, $requestId);
        $stmt->execute();
        $stmt->close();

        $reqCheck = $conn->prepare("SELECT user_id FROM custom_printing_requests WHERE id = ?");
        $reqCheck->bind_param('i', $requestId);
        $reqCheck->execute();
        $reqResult = $reqCheck->get_result();
        $reqRow = $reqResult->fetch_assoc();
        $reqCheck->close();
        if ($reqRow) {
            $customerId = (int)$reqRow['user_id'];
            $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at) VALUES (?, 'custom_request_status', ?, ?, 'custom_request', ?, NOW())");
            $notifTitle = "Custom Request #{$requestId} Status Updated";
            $notifBody = "Your custom request #{$requestId} status has been updated to: {$newStatus}.";
            $notifStmt->bind_param('issi', $customerId, $notifTitle, $notifBody, $requestId);
            $notifStmt->execute();
            $notifStmt->close();
        }

        echo json_encode(['success' => true, 'message' => 'Status updated']);
        $conn->close();
        exit;
    }

    // === ADMIN: Set ready for purchase ===
    if ($action === 'set_ready_for_purchase') {
        if (!in_array($userRole, ['admin', 'admin'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            exit;
        }
        $requestId = isset($data['request_id']) ? (int)$data['request_id'] : 0;
        $price = isset($data['price']) ? (float)$data['price'] : 0;
        $productName = isset($data['product_name']) ? trim($data['product_name']) : '';
        $qty = isset($data['quantity']) ? (int)$data['quantity'] : 1;
        $image = '';
        if (isset($_FILES['rfp_image']) && $_FILES['rfp_image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            $imageInfo = getimagesize($_FILES['rfp_image']['tmp_name']);
            if ($imageInfo !== false && isset($allowedTypes[$imageInfo['mime']])) {
                $ext = $allowedTypes[$imageInfo['mime']];
                $filename = 'rfp_' . $requestId . '_' . time() . '.' . $ext;
                $uploadDir = __DIR__ . '/../uploads/products/';
                if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
                if (move_uploaded_file($_FILES['rfp_image']['tmp_name'], $uploadDir . $filename)) {
                    @chmod($uploadDir . $filename, 0644);
                    $image = 'uploads/products/' . $filename;
                }
            }
        }

        if ($price <= 0 || empty($productName)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Price and product name are required']);
            exit;
        }

        $shipping = isset($data['shipping']) ? (float)$data['shipping'] : 0;
        $stmt = $conn->prepare("UPDATE custom_printing_requests SET status = 'ready_for_purchase', is_viewed = 0, ready_for_purchase_price = ?, ready_for_purchase_name = ?, ready_for_purchase_qty = ?, ready_for_purchase_image = ?, ready_for_purchase_shipping = ? WHERE id = ?");
        $stmt->bind_param('dsisid', $price, $productName, $qty, $image, $shipping, $requestId);
        $stmt->execute();
        $stmt->close();

        $reqCheck = $conn->prepare("SELECT user_id FROM custom_printing_requests WHERE id = ?");
        $reqCheck->bind_param('i', $requestId);
        $reqCheck->execute();
        $reqResult = $reqCheck->get_result();
        $reqRow = $reqResult->fetch_assoc();
        $reqCheck->close();
        if ($reqRow) {
            $customerId = (int)$reqRow['user_id'];
            $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at) VALUES (?, 'ready_for_purchase', ?, ?, 'custom_request', ?, NOW())");
            $notifTitle = "Custom Request #{$requestId} Ready for Purchase";
            $notifBody = "Your custom request #{$requestId} ({$productName}) is now ready for purchase at ₱" . number_format($price, 2) . "!";
            $notifStmt->bind_param('issi', $customerId, $notifTitle, $notifBody, $requestId);
            $notifStmt->execute();
            $notifStmt->close();

            // Auto-create order proposal if none exists
            $checkProp = $conn->prepare("SELECT id FROM order_proposals WHERE request_id = ? LIMIT 1");
            $checkProp->bind_param('i', $requestId);
            $checkProp->execute();
            $existingProp = $checkProp->get_result()->fetch_assoc();
            $checkProp->close();

            if (!$existingProp) {
                // Build proposal items from request items with per-unit pricing
                $reqItems = [];
                $reqItemsStmt = $conn->prepare("SELECT items FROM custom_printing_requests WHERE id = ?");
                $reqItemsStmt->bind_param('i', $requestId);
                $reqItemsStmt->execute();
                $reqItemsResult = $reqItemsStmt->get_result();
                $reqItemsRow = $reqItemsResult->fetch_assoc();
                $reqItemsStmt->close();
                if ($reqItemsRow && $reqItemsRow['items']) {
                    $reqItems = json_decode($reqItemsRow['items'], true) ?: [];
                }
                $proposalItems = [];
                $subtotal = 0;
                if (!empty($reqItems) && isset($reqItems[0]['unit_price'])) {
                    foreach ($reqItems as $ri) {
                        $pItem = [
                            'name' => $ri['size'] ?? $productName,
                            'quantity' => (int)($ri['qty'] ?? 1),
                            'unit_price' => (float)($ri['unit_price'] ?? 0)
                        ];
                        $proposalItems[] = $pItem;
                        $subtotal += $pItem['unit_price'] * $pItem['quantity'];
                    }
                } else {
                    $proposalItems = [['name' => $productName, 'quantity' => $qty, 'unit_price' => $price]];
                    $subtotal = $price * $qty;
                }
                $itemsJson = json_encode($proposalItems);
                $total = $subtotal + $shipping;
                $insertProp = $conn->prepare("INSERT INTO order_proposals (user_id, admin_id, request_id, conversation_id, items, subtotal, shipping_fee, total_amount, admin_notes, status, created_at) VALUES (?, ?, ?, 0, ?, ?, ?, ?,             '', 'sent', NOW())");
                $insertProp->bind_param('iiisddd', $customerId, $userId, $requestId, $itemsJson, $subtotal, $shipping, $total);
                $insertProp->execute();
                $insertProp->close();
            }
        }

        echo json_encode(['success' => true, 'message' => 'Request marked as ready for purchase']);
        $conn->close();
        exit;
    }

    // === MARK VIEWED (admin or customer) ===
    if ($action === 'mark_viewed' && isset($data['request_id'])) {
        $requestId = (int)$data['request_id'];

        if ($userRole === 'admin') {
            $stmt = $conn->prepare("UPDATE custom_printing_requests SET is_viewed = 1 WHERE id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE custom_printing_requests SET is_viewed = 1 WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $requestId, $userId);
        }
        if ($userRole === 'admin') {
            $stmt->bind_param('i', $requestId);
        }
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true]);
        $conn->close();
        exit;
    }

    // === ADMIN: Delete a custom request ===
    if ($action === 'delete' && isset($data['request_id'])) {
        if (!in_array($userRole, ['admin', 'admin'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            exit;
        }
        $requestId = (int)$data['request_id'];

        try {
            $conn->begin_transaction();

            // Unlink conversation
            $conn->query("UPDATE chat_conversations SET request_id = NULL WHERE request_id = $requestId");

            // Set order_proposals request_id to NULL
            $conn->query("UPDATE order_proposals SET request_id = NULL WHERE request_id = $requestId");

            // Clean up notifications
            $conn->query("DELETE FROM notifications WHERE related_type = 'custom_request' AND related_id = $requestId");

            // Delete the request (cascades to custom_request_files)
            $stmt = $conn->prepare("DELETE FROM custom_printing_requests WHERE id = ?");
            $stmt->bind_param('i', $requestId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Custom request deleted']);
        } catch (Throwable $th) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to delete custom request: ' . $th->getMessage()]);
        }
        $conn->close();
        exit;
    }

    // === ADMIN: Update customization details ===
    if ($action === 'admin_update_details' && isset($data['request_id'])) {
        if (!in_array($userRole, ['admin', 'admin'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            exit;
        }
        $requestId = (int)$data['request_id'];
        $material = trim($data['material'] ?? '');
        $items = isset($data['items']) ? json_encode($data['items']) : '[]';
        $specialRequests = trim($data['special_requests'] ?? '');
        $preferredDeadline = trim($data['preferred_deadline'] ?? '');
        $unitPrice = (float)($data['unit_price'] ?? 0);

        $stmt = $conn->prepare("UPDATE custom_printing_requests SET material = ?, items = ?, special_requests = ?, preferred_deadline = ?, ready_for_purchase_price = ?, status = 'in_review', is_viewed = 0 WHERE id = ?");
        $stmt->bind_param('ssssdi', $material, $items, $specialRequests, $preferredDeadline, $unitPrice, $requestId);
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
            $stmt->close();
            $conn->close();
            exit;
        }
        $stmt->close();

        $reqCheck = $conn->prepare("SELECT user_id FROM custom_printing_requests WHERE id = ?");
        $reqCheck->bind_param('i', $requestId);
        $reqCheck->execute();
        $reqResult = $reqCheck->get_result();
        $reqRow = $reqResult->fetch_assoc();
        $reqCheck->close();
        if ($reqRow) {
            $customerId = (int)$reqRow['user_id'];
            $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at) VALUES (?, 'request_updated', ?, ?, 'custom_request', ?, NOW())");
            $notifTitle = "Custom Request #{$requestId} Updated by Admin";
            $notifBody = "Your custom request #{$requestId} has been reviewed and updated by our team. Please check the latest details.";
            $notifStmt->bind_param('issi', $customerId, $notifTitle, $notifBody, $requestId);
            $notifStmt->execute();
            $notifStmt->close();
        }

        echo json_encode(['success' => true, 'message' => 'Customization details saved']);
        $conn->close();
        exit;
    }

    // === ADMIN: Create a custom request from chat (no message sent to customer) ===
    if ($action === 'admin_create_from_chat') {
        if (!in_array($userRole, ['admin', 'admin'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            exit;
        }
        $conversationId = isset($data['conversation_id']) ? (int)$data['conversation_id'] : 0;
        if (!$conversationId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Conversation ID is required']);
            exit;
        }

        // Look up conversation to get user_id and product info
        $convStmt = $conn->prepare("
            SELECT cc.user_id, cc.product_id, p.name as product_name, p.price as product_price
            FROM chat_conversations cc
            LEFT JOIN products p ON cc.product_id = p.id
            WHERE cc.id = ?
        ");
        $convStmt->bind_param('i', $conversationId);
        $convStmt->execute();
        $convResult = $convStmt->get_result();
        $convRow = $convResult->fetch_assoc();
        $convStmt->close();

        if (!$convRow) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Conversation not found']);
            exit;
        }

        $customerId = (int)$convRow['user_id'];
        $productId = $convRow['product_id'] ? (int)$convRow['product_id'] : 0;
        $serviceType = $convRow['product_name'] ?: 'Custom Request';
        $readyForPurchaseName = $convRow['product_name'] ?: '';
        $readyForPurchasePrice = $convRow['product_price'] ? (float)$convRow['product_price'] : 0;

        try {
            $conn->begin_transaction();

            $insertStmt = $conn->prepare("
                INSERT INTO custom_printing_requests (user_id, product_id, service_type, ready_for_purchase_name, ready_for_purchase_price, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $insertStmt->bind_param('iissd', $customerId, $productId, $serviceType, $readyForPurchaseName, $readyForPurchasePrice);
            $insertStmt->execute();
            $newRequestId = $conn->insert_id;
            $insertStmt->close();

            // Link conversation to this request
            $conn->query("UPDATE chat_conversations SET request_id = $newRequestId WHERE id = $conversationId");
            $conn->query("UPDATE custom_printing_requests SET chat_conversation_id = $conversationId WHERE id = $newRequestId");

            $conn->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Custom request created',
                'request_id' => $newRequestId
            ]);
        } catch (Throwable $th) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to create request: ' . $th->getMessage()]);
        }
        $conn->close();
        exit;
    }

    // === CUSTOMER: Submit details for an existing custom request ===
    if ($action === 'submit_details') {
        $requestId = isset($data['request_id']) ? (int)$data['request_id'] : 0;
        $check = $conn->prepare("SELECT id FROM custom_printing_requests WHERE id = ? AND user_id = ?");
        $check->bind_param('ii', $requestId, $userId);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Request not found']);
            exit;
        }
        $check->close();
        $material = isset($data['material']) ? trim($data['material']) : '';
        $items = isset($data['items']) ? json_encode($data['items']) : '[]';
        $specialRequests = isset($data['special_requests']) ? trim($data['special_requests']) : '';
        $preferredDeadline = isset($data['preferred_deadline']) ? trim($data['preferred_deadline']) : null;
        $stmt = $conn->prepare("UPDATE custom_printing_requests SET material = ?, items = ?, special_requests = ?, preferred_deadline = ?, status = 'in_review', is_viewed = 0 WHERE id = ?");
        $stmt->bind_param('ssssi', $material, $items, $specialRequests, $preferredDeadline, $requestId);
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
            $stmt->close();
            $conn->close();
            exit;
        }
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Request details submitted']);
        $conn->close();
        exit;
    }

    // === SUBMIT new custom printing request ===
    $serviceType = isset($data['service_type']) ? trim($data['service_type']) : '';
    $material = isset($data['material']) ? trim($data['material']) : '';
    $items = isset($data['items']) ? json_encode($data['items']) : '[]';
    $specialRequests = isset($data['special_requests']) ? trim($data['special_requests']) : '';
    $needDesignAssistance = isset($data['need_design_assistance']) ? (int)$data['need_design_assistance'] : 0;
    $preferredDeadline = isset($data['preferred_deadline']) ? trim($data['preferred_deadline']) : null;
    $referenceImages = isset($data['reference_images']) ? $data['reference_images'] : [];
    $files = isset($data['files']) ? $data['files'] : [];
    $productId = isset($data['product_id']) ? (int)$data['product_id'] : 0;

    $readyForPurchaseName = '';
    $readyForPurchasePrice = 0;
    if ($productId) {
        $prodQ = $conn->query("SELECT name, price FROM products WHERE id = $productId");
        if ($prodQ && $prodQ->num_rows) {
            $prodRow = $prodQ->fetch_assoc();
            $readyForPurchaseName = $prodRow['name'];
            $readyForPurchasePrice = (float)$prodRow['price'];
        }
    }

    if (empty($serviceType)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Service type is required']);
        exit;
    }

    try {
        $conn->begin_transaction();

        // Insert custom printing request
        $stmt = $conn->prepare("
            INSERT INTO custom_printing_requests 
            (user_id, product_id, service_type, material, items, special_requests, need_design_assistance, preferred_deadline, reference_images, ready_for_purchase_name, ready_for_purchase_price)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $referenceImagesJson = json_encode($referenceImages);
        $stmt->bind_param('iissssissd', 
            $userId, $productId, $serviceType, $material, $items, 
            $specialRequests, $needDesignAssistance, 
            $preferredDeadline, $referenceImagesJson,
            $readyForPurchaseName, $readyForPurchasePrice
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to create custom printing request');
        }

        $requestId = $conn->insert_id;
        $stmt->close();

        // Insert uploaded files
        if (!empty($files)) {
            $fileStmt = $conn->prepare("
                INSERT INTO custom_request_files (request_id, file_url, file_type, file_name, sort_order)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($files as $index => $file) {
                $fileUrl = isset($file['url']) ? $file['url'] : '';
                $fileType = isset($file['type']) ? $file['type'] : 'image';
                $fileName = isset($file['name']) ? $file['name'] : '';

                if ($fileUrl) {
                    $fileStmt->bind_param('isssi', $requestId, $fileUrl, $fileType, $fileName, $index);
                    $fileStmt->execute();
                }
            }
            $fileStmt->close();
        }

        // Find an admin to auto-create chat
        $adminStmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
        $adminStmt->execute();
        $adminResult = $adminStmt->get_result();
        $adminRow = $adminResult->fetch_assoc();
        $adminStmt->close();

        $chatConversationId = null;
        if ($adminRow) {
            $adminId = (int)$adminRow['id'];

            // Check if conversation already exists
            $convCheck = $conn->prepare("SELECT id FROM chat_conversations WHERE user_id = ? AND admin_id = ? ORDER BY id DESC LIMIT 1");
            $convCheck->bind_param('ii', $userId, $adminId);
            $convCheck->execute();
            $convResult = $convCheck->get_result();
            $convRow = $convResult->fetch_assoc();
            $convCheck->close();

            if ($convRow) {
                $chatConversationId = (int)$convRow['id'];
            } else {
                $convInsert = $conn->prepare("INSERT INTO chat_conversations (user_id, admin_id, request_id, last_message_at) VALUES (?, ?, ?, NOW())");
                $convInsert->bind_param('iii', $userId, $adminId, $requestId);
                $convInsert->execute();
                $chatConversationId = $conn->insert_id;
                $convInsert->close();
            }

            // Send welcome message
            $welcomeMsg = "Hi! I've submitted a custom printing request for $serviceType. Let me know if you need more details.";
            $msgStmt = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_id, message_type, content, created_at) VALUES (?, ?, 'text', ?, NOW())");
            $msgStmt->bind_param('iis', $chatConversationId, $userId, $welcomeMsg);
            $msgStmt->execute();
            $msgStmt->close();

            // Update conversation timestamp
            $conn->query("UPDATE chat_conversations SET last_message_at = NOW() WHERE id = $chatConversationId");

            // Link request to conversation
            $conn->query("UPDATE custom_printing_requests SET chat_conversation_id = $chatConversationId WHERE id = $requestId");
        }

        // Create notification for user
        $notifStmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at)
            VALUES (?, 'custom_request', ?, ?, 'custom_request', ?, NOW())
        ");
        $notifTitle = "Custom Printing Request Submitted";
        $notifBody = "Your custom printing request for $serviceType has been submitted successfully. A chat has been started with our team.";
        $notifStmt->bind_param('issi', $userId, $notifTitle, $notifBody, $requestId);
        $notifStmt->execute();
        $notifStmt->close();

        // Create notifications for admins
        $adminNotif = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at)
            SELECT id, 'custom_request', ?, ?, 'custom_request', ?, NOW()
            FROM users WHERE role IN ('admin', 'admin')
        ");
        $adminTitle = "New Custom Printing Request #$requestId";
        $adminBody = "A new custom printing request for $serviceType has been submitted by a customer. A chat has been started.";
        $adminNotif->bind_param('ssi', $adminTitle, $adminBody, $requestId);
        $adminNotif->execute();
        $adminNotif->close();

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Custom printing request submitted successfully',
            'request_id' => $requestId,
            'chat_conversation_id' => $chatConversationId
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Custom printing error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to submit request. Please try again.']);
    }

    $conn->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'my_requests';

    if ($action === 'my_requests') {
        $stmt = $conn->prepare("
            SELECT cpr.*,
                   COALESCE(cpr.ready_for_purchase_price, p.price) as ready_for_purchase_price,
                   COALESCE(cpr.ready_for_purchase_name, p.name) as ready_for_purchase_name,
                   COALESCE(cpr.ready_for_purchase_image, p.image_url) as ready_for_purchase_image,
                   (SELECT COUNT(*) FROM custom_request_files WHERE request_id = cpr.id) as file_count
            FROM custom_printing_requests cpr
            LEFT JOIN products p ON cpr.product_id = p.id
            WHERE cpr.user_id = ?
            ORDER BY cpr.created_at DESC
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $requests = [];
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'requests' => $requests]);
        $conn->close();
        exit;
    }

    if ($action === 'all' && in_array($userRole, ['admin', 'admin'])) {
        $status = isset($_GET['status']) ? $_GET['status'] : 'all';

        $where = "WHERE 1=1";
        $params = [];
        $types = '';

        if ($status !== 'all') {
            $where .= " AND cpr.status = ?";
            $params[] = $status;
            $types .= 's';
        }

        $sql = "
            SELECT cpr.id, cpr.user_id, cpr.product_id, cpr.service_type, cpr.material, cpr.quantity, cpr.need_design_assistance, cpr.preferred_deadline, cpr.`status`, cpr.chat_conversation_id,
                   COALESCE(cpr.ready_for_purchase_price, p.price) as ready_for_purchase_price,
                   COALESCE(cpr.ready_for_purchase_name, p.name) as ready_for_purchase_name,
                   cpr.ready_for_purchase_qty,
                   COALESCE(cpr.ready_for_purchase_image, p.image_url) as ready_for_purchase_image,
                   cpr.is_viewed, cpr.created_at, cpr.updated_at,
                   u.name as user_name, u.email as user_email,
                   (SELECT COUNT(*) FROM custom_request_files WHERE request_id = cpr.id) as file_count
            FROM custom_printing_requests cpr
            JOIN users u ON cpr.user_id = u.id
            LEFT JOIN products p ON cpr.product_id = p.id
            $where
            ORDER BY cpr.created_at DESC
        ";

        $stmt = $conn->prepare($sql);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $requests = [];
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'requests' => $requests]);
        $conn->close();
        exit;
    }

    if ($action === 'get' && isset($_GET['id'])) {
        $requestId = (int)$_GET['id'];
        $checkStmt = $conn->prepare("
            SELECT cpr.id, cpr.user_id, cpr.product_id, cpr.service_type, cpr.material, cpr.quantity, cpr.need_design_assistance, cpr.preferred_deadline, cpr.`status`, cpr.chat_conversation_id,
                   COALESCE(cpr.ready_for_purchase_price, p.price) as ready_for_purchase_price,
                   COALESCE(cpr.ready_for_purchase_name, p.name) as ready_for_purchase_name,
                   cpr.ready_for_purchase_qty, cpr.ready_for_purchase_image, cpr.ready_for_purchase_shipping,
                   cpr.is_viewed, cpr.created_at, cpr.updated_at,
                   cpr.items, cpr.special_requests, cpr.admin_notes, cpr.reference_images,
                   cpr.size, cpr.color, cpr.finish,
                   u.name as user_name, u.email as user_email,
                   (SELECT COUNT(*) FROM custom_request_files WHERE request_id = cpr.id) as file_count
            FROM custom_printing_requests cpr
            JOIN users u ON cpr.user_id = u.id
            LEFT JOIN products p ON cpr.product_id = p.id
            WHERE cpr.id = ? AND (cpr.user_id = ? OR ? IN ('admin', 'admin'))
        ");
        $checkStmt->bind_param('iis', $requestId, $userId, $userRole);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $request = $result->fetch_assoc();
        $checkStmt->close();
        if ($request) {
            echo json_encode(['success' => true, 'request' => $request]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Request not found']);
        }
        $conn->close();
        exit;
    }

    if ($action === 'files' && isset($_GET['request_id'])) {
        $requestId = (int)$_GET['request_id'];

        $checkStmt = $conn->prepare("
            SELECT id FROM custom_printing_requests 
            WHERE id = ? AND (user_id = ? OR ? IN ('admin', 'admin'))
        ");
        $checkStmt->bind_param('iis', $requestId, $userId, $userRole);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkStmt->close();

        if (!$checkResult || $checkResult->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Request not found']);
            $conn->close();
            exit;
        }

        $stmt = $conn->prepare("
            SELECT * FROM custom_request_files 
            WHERE request_id = ?
            ORDER BY sort_order ASC, created_at ASC
        ");
        $stmt->bind_param('i', $requestId);
        $stmt->execute();
        $result = $stmt->get_result();

        $files = [];
        while ($row = $result->fetch_assoc()) {
            $files[] = $row;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'files' => $files]);
        $conn->close();
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
