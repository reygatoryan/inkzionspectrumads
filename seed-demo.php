<?php
/**
 * Seed demo data for order flow testing
 * Run once from CLI or browser to seed demo data. Safe to re-run.
 */

require_once __DIR__ . '/db-config.php';

echo "<pre>\n";

// ── 1. Lookup admin account ──
$stmt = $conn->prepare("SELECT id, name FROM users WHERE email = 'kennethryanlccl@gmail.com' AND role = 'admin'");
$stmt->execute();
$adminRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$adminRow) { die("Admin not found: kennethryanlccl@gmail.com\n"); }
$adminId = $adminRow['id'];
echo "✓ Admin: {$adminRow['name']} (id=$adminId)\n";

// ── 2. Lookup customer account ──
$stmt = $conn->prepare("SELECT id, name FROM users WHERE email = 'retchie.inkzion@gmail.com' AND role = 'user'");
$stmt->execute();
$customerRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$customerRow) { die("Customer not found: retchie.inkzion@gmail.com\n"); }
$customerId = $customerRow['id'];
echo "✓ Customer: {$customerRow['name']} (id=$customerId)\n";

// ── 3. Product ──
$productId = null;
$r = $conn->query("SELECT id FROM products WHERE name = 'Custom Printed T-Shirt' AND admin_id = $adminId LIMIT 1");
if ($r && $r->num_rows) {
    $productId = $r->fetch_assoc()['id'];
    echo "✓ Product already exists (id=$productId)\n";
} else {
    $img = 'uploads/products/demo-tshirt.jpg';
    $catId = 4; // Apparel & Wearables
    $stmt = $conn->prepare("INSERT INTO products (admin_id, category_id, name, description, price, image_url, created_at)
        VALUES (?, ?, 'Custom Printed T-Shirt', 'High-quality custom printed t-shirt. Choose your design, size, and color.', 350.00, ?, NOW())");
    $stmt->bind_param('iis', $adminId, $catId, $img);
    $stmt->execute();
    $productId = $conn->insert_id;
    $stmt->close();
    echo "✓ Product created (id=$productId) — Custom Printed T-Shirt\n";
}

// ── 4. Conversation ──
$convId = null;
$r = $conn->query("SELECT id FROM chat_conversations WHERE user_id = $customerId AND admin_id = $adminId AND product_id = $productId LIMIT 1");
if ($r && $r->num_rows) {
    $convId = $r->fetch_assoc()['id'];
    echo "✓ Conversation already exists (id=$convId)\n";
} else {
    $stmt = $conn->prepare("INSERT INTO chat_conversations (user_id, admin_id, product_id, last_message_at, created_at)
        VALUES (?, ?, ?, NOW(), NOW())");
    $stmt->bind_param('iii', $customerId, $adminId, $productId);
    $stmt->execute();
    $convId = $conn->insert_id;
    $stmt->close();
    echo "✓ Conversation created (id=$convId)\n";
}

// ── 5. Custom Printing Request ──
$requestId = null;
$r = $conn->query("SELECT id FROM custom_printing_requests WHERE user_id = $customerId AND service_type = 'Custom Printed T-Shirt' LIMIT 1");
if ($r && $r->num_rows) {
    $requestId = $r->fetch_assoc()['id'];
    echo "✓ Custom request already exists (id=$requestId)\n";
} else {
    $stmt = $conn->prepare("INSERT INTO custom_printing_requests (user_id, service_type, quantity, special_requests, status, chat_conversation_id, created_at, updated_at)
        VALUES (?, 'Custom Printed T-Shirt', 5, 'I want a black t-shirt with my logo printed on the front.', 'in_review', ?, NOW(), NOW())");
    $stmt->bind_param('ii', $customerId, $convId);
    $stmt->execute();
    $requestId = $conn->insert_id;
    $stmt->close();
    echo "✓ Custom request created (id=$requestId) — status: in_review\n";
}

// ── 6. Order Proposal (filled) ──
$proposalId = null;
$r = $conn->query("SELECT id FROM order_proposals WHERE user_id = $customerId AND request_id = $requestId LIMIT 1");
if ($r && $r->num_rows) {
    $proposalId = $r->fetch_assoc()['id'];
    echo "✓ Proposal already exists (id=$proposalId)\n";
} else {
    $items = json_encode([
        ['name' => 'Custom Printed T-Shirt', 'quantity' => 5, 'unit_price' => 250.00, 'weight' => 0.2]
    ]);
    $subtotal = 1250.00;
    $shipping = 100.00;
    $total = 1350.00;
    $fullName = 'Juan Dela Cruz';
    $email = 'juan@email.com';
    $phone = '09171234567';
    $delivery = '123 Rizal St, Barangay Poblacion';
    $city = 'Manila';
    $province = 'Metro Manila';
    $zip = '1000';
    $landmark = 'Near 7-Eleven, beside Jollibee';
    $paymentMethod = 'gcash';
    $adminNotes = 'Please double-check the logo placement.';

    $addlNotes = 'Faster delivery in the morning please';
    $params = [$customerId, $adminId, $requestId, $convId, $items, $subtotal, $shipping, $total, $adminNotes, $fullName, $email, $phone, $delivery, $city, $province, $zip, $landmark, $paymentMethod, $addlNotes];
    $types = 'iiiisddd' . str_repeat('s', count($params) - 8);
    $stmt = $conn->prepare("INSERT INTO order_proposals SET
        user_id = ?, admin_id = ?, request_id = ?, conversation_id = ?,
        items = ?, subtotal = ?, shipping_fee = ?, total_amount = ?,
        admin_notes = ?, full_name = ?, email = ?, phone = ?,
        delivery_address = ?, city = ?, province = ?, zip = ?,
        landmark = ?, payment_method = ?, additional_notes = ?,
        status = 'filled', created_at = NOW(), updated_at = NOW()");
    $bindParams = [$types];
    foreach ($params as &$p) { $bindParams[] = &$p; }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    $stmt->execute();
    $proposalId = $conn->insert_id;
    $stmt->close();
    echo "✓ Order proposal created (id=$proposalId) — status: filled (awaiting admin approval)\n";
}

// ── 7. Chat messages ──
$msgCount = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM chat_messages WHERE conversation_id = $convId");
if ($r) { $msgCount = $r->fetch_assoc()['c']; }

if ($msgCount > 0) {
    echo "✓ Chat messages already exist ($msgCount messages)\n";
} else {
    $messages = [
        ['sender' => $customerId, 'role' => 'user', 'text' => 'Hi! I\'m interested in a custom printed t-shirt. Can you help me with the design?'],
        ['sender' => $adminId, 'role' => 'admin', 'text' => 'Hello Juan! Of course. What design and colors are you thinking?'],
        ['sender' => $customerId, 'role' => 'user', 'text' => 'I want a black t-shirt with my company logo on the front chest area.'],
        ['sender' => $adminId, 'role' => 'admin', 'text' => 'Great choice! Let me send you an order form with the quote details.'],
    ];
    foreach ($messages as $m) {
        $type = $m['role'] === 'admin' ? 'text' : 'text';
        $stmt = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_id, message_type, content, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param('iiss', $convId, $m['sender'], $type, $m['text']);
        $stmt->execute();
        $stmt->close();
    }
    echo "✓ Chat messages created (4 messages)\n";
}

$conn->close();

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  DEMO DATA READY\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n  Accounts used:\n";
echo "  Admin:    kennethryanlccl@gmail.com\n";
echo "  Customer: retchie.inkzion@gmail.com\n";
echo "\n  What to check:\n";
echo "  1. Log in as customer → Profile → see 'Awaiting admin approval'\n";
echo "  2. Log in as admin → Orders → Order Forms tab → approve\n";
echo "  3. Chat between both users (conversation exists)\n";
echo "  4. Admin → Custom Requests → see landmark field filled\n";
echo "\n  ⚠  Delete seed-demo.php after testing!\n";
echo "</pre>\n";
