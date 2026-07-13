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

require_once __DIR__ . '/../db-config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'user';

// GET: list proposals
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        if ($userRole === 'admin') {
            $status = isset($_GET['status']) ? $_GET['status'] : '';
            $sql = "SELECT op.*, u.name as user_name, u.email as user_email,
                           o.order_reference
                    FROM order_proposals op
                    LEFT JOIN users u ON op.user_id = u.id
                    LEFT JOIN orders o ON op.order_id = o.id
                    WHERE 1=1";
            $params = [];
            $types = '';
            if ($status && $status !== 'all') {
                $sql .= " AND op.status = ?";
                $params[] = $status;
                $types .= 's';
            }
            $requestId = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
            if ($requestId) {
                $sql .= " AND op.request_id = ?";
                $params[] = $requestId;
                $types .= 'i';
            }
            $sql .= " ORDER BY op.created_at DESC";
            $stmt = $conn->prepare($sql);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $proposals = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Decode items JSON for each proposal
            foreach ($proposals as &$p) {
                $p['items'] = json_decode($p['items'], true) ?: [];
            }

            echo json_encode(['success' => true, 'proposals' => $proposals]);
        } else {
            // Customer sees their own proposals
            $status = isset($_GET['status']) ? $_GET['status'] : '';
            $sql = "SELECT op.*, o.order_reference
                    FROM order_proposals op
                    LEFT JOIN orders o ON op.order_id = o.id
                    WHERE op.user_id = ?";
            $params = [$userId];
            $types = 'i';
            if ($status && $status !== 'all') {
                $sql .= " AND op.status = ?";
                $params[] = $status;
                $types .= 's';
            }
            $reqIdFilter = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
            if ($reqIdFilter) {
                $sql .= " AND op.request_id = ?";
                $params[] = $reqIdFilter;
                $types .= 'i';
            }
            $sql .= " ORDER BY op.created_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $proposals = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($proposals as &$p) {
                $p['items'] = json_decode($p['items'], true) ?: [];
            }

            echo json_encode(['success' => true, 'proposals' => $proposals]);
        }
        $conn->close();
        exit;
    }

    if ($action === 'get') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'Proposal ID required']);
            $conn->close();
            exit;
        }

        if ($userRole === 'admin') {
            $stmt = $conn->prepare("SELECT op.*, u.name as user_name, u.email as user_email FROM order_proposals op LEFT JOIN users u ON op.user_id = u.id WHERE op.id = ?");
        } else {
            $stmt = $conn->prepare("SELECT op.* FROM order_proposals op WHERE op.id = ? AND op.user_id = ?");
            $stmt->bind_param('ii', $id, $userId);
        }
        if ($userRole === 'admin') {
            $stmt->bind_param('i', $id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $proposal = $result->fetch_assoc();
        $stmt->close();

        if (!$proposal) {
            echo json_encode(['success' => false, 'error' => 'Proposal not found']);
            $conn->close();
            exit;
        }

        $proposal['items'] = json_decode($proposal['items'], true) ?: [];
        echo json_encode(['success' => true, 'proposal' => $proposal]);
        $conn->close();
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    $conn->close();
    exit;
}

// POST: create, update, approve, reject, submit_by_customer
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }

    $action = $data['action'] ?? '';

    // Admin: create proposal
    if ($action === 'create') {
        if ($userRole !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin only']);
            $conn->close();
            exit;
        }

        $customerId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
        $requestId = isset($data['request_id']) ? (int)$data['request_id'] : 0;
        $conversationId = isset($data['conversation_id']) ? (int)$data['conversation_id'] : 0;
        $items = $data['items'] ?? [];
        $shippingFee = floatval($data['shipping_fee'] ?? 0);
        $adminNotes = trim($data['admin_notes'] ?? '');

        if (!$customerId || empty($items)) {
            echo json_encode(['success' => false, 'error' => 'Customer ID and items are required']);
            $conn->close();
            exit;
        }

        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += floatval($item['unit_price'] ?? 0) * intval($item['quantity'] ?? 1);
        }
        $totalAmount = $subtotal + $shippingFee;

        $itemsJson = json_encode($items);

        $stmt = $conn->prepare("
            INSERT INTO order_proposals (user_id, admin_id, request_id, conversation_id, items, subtotal, shipping_fee, total_amount, admin_notes, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'sent', NOW())
        ");
        $stmt->bind_param('iiiisddds', $customerId, $userId, $requestId, $conversationId, $itemsJson, $subtotal, $shippingFee, $totalAmount, $adminNotes);

        if ($stmt->execute()) {
            $proposalId = $conn->insert_id;

            // Notify customer
            $notifStmt = $conn->prepare("
                INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at)
                VALUES (?, 'proposal_sent', 'Order Form Ready', 'Admin has sent you an order form. Please fill in your details to proceed.', 'order_proposal', ?, NOW())
            ");
            $notifStmt->bind_param('ii', $customerId, $proposalId);
            $notifStmt->execute();
            $notifStmt->close();

            echo json_encode(['success' => true, 'message' => 'Order form sent to customer!', 'proposal_id' => $proposalId]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create proposal']);
        }
        $stmt->close();
        $conn->close();
        exit;
    }

    // Customer: fill and submit proposal
    if ($action === 'submit_details') {
        $proposalId = isset($data['proposal_id']) ? (int)$data['proposal_id'] : 0;

        $stmt = $conn->prepare("SELECT id, user_id, request_id, status FROM order_proposals WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $proposalId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $proposal = $result->fetch_assoc();
        $stmt->close();

        if (!$proposal) {
            echo json_encode(['success' => false, 'error' => 'Proposal not found']);
            $conn->close();
            exit;
        }

        if ($proposal['status'] !== 'sent' && $proposal['status'] !== 'rejected') {
            echo json_encode(['success' => false, 'error' => 'Proposal cannot be edited']);
            $conn->close();
            exit;
        }

        $fullName = trim($data['full_name'] ?? '');
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $deliveryAddress = trim($data['delivery_address'] ?? '');
        $city = trim($data['city'] ?? '');
        $province = trim($data['province'] ?? '');
        $zip = trim($data['zip'] ?? '');
        $paymentMethod = trim($data['payment_method'] ?? '');
        $additionalNotes = trim($data['additional_notes'] ?? '');
        $landmark = trim($data['landmark'] ?? '');

        $errors = [];
        if (!$fullName) $errors[] = 'Full name is required';
        if (!$email) $errors[] = 'Email is required';
        if (!$phone) $errors[] = 'Contact number is required';
        if (!$deliveryAddress) $errors[] = 'Delivery address is required';
        if (!$paymentMethod) $errors[] = 'Payment method is required';

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
            $conn->close();
            exit;
        }

        $updateStmt = $conn->prepare("
            UPDATE order_proposals SET
                full_name = ?, email = ?, phone = ?, delivery_address = ?,
                city = ?, province = ?, zip = ?, landmark = ?,
                payment_method = ?, additional_notes = ?, status = 'filled', updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $updateStmt->bind_param('ssssssssssii', $fullName, $email, $phone, $deliveryAddress, $city, $province, $zip, $landmark, $paymentMethod, $additionalNotes, $proposalId, $userId);

        if ($updateStmt->execute()) {
            // Notify admin
            $adminStmt = $conn->prepare("SELECT admin_id FROM order_proposals WHERE id = ?");
            $adminStmt->bind_param('i', $proposalId);
            $adminStmt->execute();
            $adminResult = $adminStmt->get_result();
            $adminRow = $adminResult->fetch_assoc();
            $adminStmt->close();

            $notifStmt = $conn->prepare("
                INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at)
                VALUES (?, 'proposal_filled', 'Order Form Submitted', 'A customer has submitted their order details for review.', 'order_proposal', ?, NOW())
            ");
            $notifStmt->bind_param('ii', $adminRow['admin_id'], $proposalId);
            $notifStmt->execute();
            $notifStmt->close();

            // Auto-approve the custom request
            if (!empty($proposal['request_id'])) {
                $crStmt = $conn->prepare("UPDATE custom_printing_requests SET status = 'approved', updated_at = NOW() WHERE id = ?");
                $crStmt->bind_param('i', $proposal['request_id']);
                $crStmt->execute();
                $crStmt->close();
            }

            echo json_encode(['success' => true, 'message' => 'Your details have been submitted! The admin will review your order.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to submit details']);
        }
        $updateStmt->close();
        $conn->close();
        exit;
    }

    // Admin: approve proposal
    if ($action === 'approve') {
        if ($userRole !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin only']);
            $conn->close();
            exit;
        }

        $proposalId = isset($data['proposal_id']) ? (int)$data['proposal_id'] : 0;

        $stmt = $conn->prepare("SELECT * FROM order_proposals WHERE id = ? AND status = 'filled'");
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $result = $stmt->get_result();
        $proposal = $result->fetch_assoc();
        $stmt->close();

        if (!$proposal) {
            echo json_encode(['success' => false, 'error' => 'Proposal not found or not in filled status']);
            $conn->close();
            exit;
        }

        $conn->begin_transaction();
        try {
            // Create the order
            $orderRef = 'ORD-' . strtoupper(substr(uniqid(), -6));
            $orderStmt = $conn->prepare("
                INSERT INTO orders (user_id, admin_id, order_reference, total_amount, status, payment_method, payment_status,
                    contact_name, contact_email, contact_phone, delivery_address, delivery_city, delivery_province,
                    delivery_zip, delivery_country, order_notes, shipping_fee, total_weight, created_at)
                VALUES (?, ?, ?, ?, 'pending', ?, 'pending',
                    ?, ?, ?, ?, ?, ?, ?, 'Philippines', ?, ?, 0, NOW())
            ");
            $orderStmt->bind_param('iisssssssssssd',
                $proposal['user_id'], $userId, $orderRef, $proposal['total_amount'],
                $proposal['payment_method'],
                $proposal['full_name'], $proposal['email'], $proposal['phone'],
                $proposal['delivery_address'], $proposal['city'], $proposal['province'],
                $proposal['zip'], $proposal['additional_notes'], $proposal['shipping_fee']
            );
            $orderStmt->execute();
            if ($orderStmt->errno) throw new Exception("Order insert failed: " . $orderStmt->error);
            $orderId = $conn->insert_id;
            $orderStmt->close();

            // Create order items
            $items = json_decode($proposal['items'], true) ?: [];
            if (empty($items)) throw new Exception("No items found in proposal to create order");
            $totalWeight = 0;
            foreach ($items as $item) {
                $itemStmt = $conn->prepare("
                    INSERT INTO order_items (order_id, product_name, quantity, unit_price)
                    VALUES (?, ?, ?, ?)
                ");
                $productName = $item['name'] ?? 'Product';
                $qty = intval($item['quantity'] ?? 1);
                $unitPrice = floatval($item['unit_price'] ?? 0);
                $itemStmt->bind_param('isid', $orderId, $productName, $qty, $unitPrice);
                $itemStmt->execute();
                if ($itemStmt->errno) throw new Exception("Order item insert failed: " . $itemStmt->error);
                $itemStmt->close();
                $totalWeight += floatval($item['weight'] ?? 0) * $qty;
            }

            if ($totalWeight > 0) {
                $conn->query("UPDATE orders SET total_weight = $totalWeight WHERE id = $orderId");
            }

            // Order timeline
            $timelineStmt = $conn->prepare("
                INSERT INTO order_timeline (order_id, from_status, to_status, changed_by, notes, created_at)
                VALUES (?, NULL, 'pending', ?, 'Order created from approved proposal #$proposalId', NOW())
            ");
            $timelineStmt->bind_param('ii', $orderId, $userId);
            $timelineStmt->execute();
            $timelineStmt->close();

            // Update proposal
            $updateStmt = $conn->prepare("UPDATE order_proposals SET status = 'converted', order_id = ?, updated_at = NOW() WHERE id = ?");
            $updateStmt->bind_param('ii', $orderId, $proposalId);
            $updateStmt->execute();
            $updateStmt->close();

            // Update custom request status to approved
            if (!empty($proposal['request_id'])) {
                $crStmt = $conn->prepare("UPDATE custom_printing_requests SET status = 'approved', updated_at = NOW() WHERE id = ?");
                $crStmt->bind_param('i', $proposal['request_id']);
                $crStmt->execute();
                $crStmt->close();
            }

            // Notify customer
            $notifStmt = $conn->prepare("
                INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at)
                VALUES (?, 'order_approved', 'Order Approved!', 'Your order #$orderRef has been approved and is being prepared.', 'order', ?, NOW())
            ");
            $notifStmt->bind_param('ii', $proposal['user_id'], $orderId);
            $notifStmt->execute();
            $notifStmt->close();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => "Order approved! Order #$orderRef created.", 'order_id' => $orderId]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => 'Failed to approve: ' . $e->getMessage()]);
        }
        $conn->close();
        exit;
    }

    // Admin: reject proposal
    if ($action === 'reject') {
        if ($userRole !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin only']);
            $conn->close();
            exit;
        }

        $proposalId = isset($data['proposal_id']) ? (int)$data['proposal_id'] : 0;
        $reason = trim($data['reason'] ?? 'No reason provided');

        $stmt = $conn->prepare("SELECT user_id, status, request_id FROM order_proposals WHERE id = ?");
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $result = $stmt->get_result();
        $proposal = $result->fetch_assoc();
        $stmt->close();

        if (!$proposal || $proposal['status'] !== 'filled') {
            echo json_encode(['success' => false, 'error' => 'Proposal not found or not in filled status']);
            $conn->close();
            exit;
        }

        $updateStmt = $conn->prepare("UPDATE order_proposals SET status = 'rejected', rejection_reason = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->bind_param('si', $reason, $proposalId);
        $updateStmt->execute();
        $updateStmt->close();

        // Revert custom request status to in_review so admin can re-edit
        if (!empty($proposal['request_id'])) {
            $crStmt = $conn->prepare("UPDATE custom_printing_requests SET status = 'in_review', updated_at = NOW() WHERE id = ?");
            $crStmt->bind_param('i', $proposal['request_id']);
            $crStmt->execute();
            $crStmt->close();
        }

        // Notify customer
        $notifStmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at)
            VALUES (?, 'proposal_rejected', 'Order Form Rejected', ?, 'order_proposal', ?, NOW())
        ");
        $body = "Your order form was rejected. Reason: $reason. Please edit and resubmit.";
        $notifStmt->bind_param('isi', $proposal['user_id'], $body, $proposalId);
        $notifStmt->execute();
        $notifStmt->close();

        echo json_encode(['success' => true, 'message' => 'Proposal rejected. Customer can edit and resubmit.']);
        $conn->close();
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    $conn->close();
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
$conn->close();
