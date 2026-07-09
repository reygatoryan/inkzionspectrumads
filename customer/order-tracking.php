<?php
session_start();
require_once dirname(__DIR__) . '/db-config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$orderId) {
    header('Location: ../index.php');
    exit;
}

// Get order details
$orderStmt = $conn->prepare("
    SELECT o.*, 
           p.name as product_name, 
           p.image_url as product_image,
           u.first_name, u.last_name, u.email,
           s.first_name as seller_first, s.last_name as seller_last, s.email as seller_email
    FROM orders o
    LEFT JOIN products p ON o.product_id = p.id
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN users s ON p.admin_id = s.id
    WHERE o.id = ? AND o.user_id = ?
");
$orderStmt->bind_param('ii', $orderId, $userId);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$order) {
    header('Location: ../index.php');
    exit;
}

// Get order timeline
$timelineStmt = $conn->prepare("
    SELECT ot.*, u.first_name, u.last_name, u.role
    FROM order_timeline ot
    LEFT JOIN users u ON ot.changed_by = u.id
    WHERE ot.order_id = ?
    ORDER BY ot.created_at ASC
");
$timelineStmt->bind_param('i', $orderId);
$timelineStmt->execute();
$timeline = $timelineStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$timelineStmt->close();

// Status flow for progress bar
$statusFlow = [
    'pending' => 1,
    'confirmed' => 2,
    'shipped' => 3,
    'delivered' => 4,
    'completed' => 5,
];

$currentStatus = $order['status'];
$currentStep = $statusFlow[$currentStatus] ?? 0;
$totalSteps = 5;
$progressPercent = $currentStep > 0 ? round(($currentStep / $totalSteps) * 100) : 0;

// Status labels
$statusLabels = [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'returned' => 'Returned',
];

// Parse JSON fields
$designFiles = json_decode($order['design_files'] ?? '[]', true) ?: [];
$proofImages = json_decode($order['proof_images'] ?? '[]', true) ?: [];
$packagePhotos = json_decode($order['package_photos'] ?? '[]', true) ?: [];

// Get user role
$roleStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$roleStmt->bind_param('i', $userId);
$roleStmt->execute();
$userRole = $roleStmt->get_result()->fetch_assoc();
$roleStmt->close();
$userRole = $userRole['role'] ?? 'customer';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking - Inkzion Spectrum Ads</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .tracking-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .order-header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .order-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 10px;
        }
        
        .order-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            color: #666;
            font-size: 14px;
        }
        
        .order-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .progress-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .progress-bar-container {
            position: relative;
            margin: 40px 0 20px;
        }
        
        .progress-bar {
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            position: relative;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #2563eb, #3b82f6);
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-top: -20px;
        }
        
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
            flex: 1;
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #999;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }
        
        .progress-step.completed .step-circle {
            background: #2563eb;
            color: white;
        }
        
        .progress-step.active .step-circle {
            background: #3b82f6;
            color: white;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
        }
        
        .step-label {
            font-size: 11px;
            color: #666;
            text-align: center;
            max-width: 80px;
            line-height: 1.2;
        }
        
        .progress-step.completed .step-label,
        .progress-step.active .step-label {
            color: #1a1a1a;
            font-weight: 500;
        }
        
        .current-status {
            text-align: center;
            padding: 20px;
            background: #f0f9ff;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            background: #2563eb;
            color: white;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .estimated-delivery {
            color: #666;
            font-size: 14px;
        }
        
        .tracking-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .info-item {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .info-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }
        
        .info-value {
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .timeline-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 20px;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e0e0e0;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 25px;
        }
        
        .timeline-item:last-child {
            padding-bottom: 0;
        }
        
        .timeline-dot {
            position: absolute;
            left: -26px;
            top: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #2563eb;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #2563eb;
        }
        
        .timeline-content {
            padding-left: 15px;
        }
        
        .timeline-title {
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        
        .timeline-time {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }
        
        .timeline-notes {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .media-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .media-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            aspect-ratio: 1;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .media-item:hover {
            transform: scale(1.05);
        }
        
        .media-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .media-item .file-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background: #f0f9ff;
            color: #2563eb;
            font-size: 40px;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #f0f9ff;
            color: #2563eb;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            transition: background 0.2s;
        }
        
        .back-btn:hover {
            background: #e0f2fe;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .progress-steps {
                overflow-x: auto;
                padding-bottom: 10px;
            }
            
            .step-label {
                font-size: 10px;
                max-width: 60px;
            }
            
            .step-circle {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <?php // header include removed ?>
    
    <div class="tracking-container">
        <a href="profile.php" class="back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Back to Orders
        </a>
        
        <div class="order-header">
            <h1 class="order-title">Order #<?php echo $order['order_reference'] ?? $order['id']; ?></h1>
            <div class="order-meta">
                <span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <?php echo date('F j, Y', strtotime($order['created_at'])); ?>
                </span>
                <span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                </span>
            </div>
        </div>
        
        <div class="progress-section">
            <div class="current-status">
                <div class="status-badge"><?php echo $statusLabels[$currentStatus] ?? ucfirst($currentStatus); ?></div>
                <?php if ($order['estimated_delivery']): ?>
                    <div class="estimated-delivery">
                        Estimated Delivery: <?php echo date('F j, Y', strtotime($order['estimated_delivery'])); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="progress-bar-container">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $progressPercent; ?>%"></div>
                </div>
                <div class="progress-steps">
                    <?php
                    $steps = [
                        1 => 'Pending',
                        2 => 'Confirmed',
                        3 => 'Shipped',
                        4 => 'Delivered',
                        5 => 'Completed',
                    ];
                    foreach ($steps as $num => $label):
                        $stepStatus = '';
                        if ($num < $currentStep) $stepStatus = 'completed';
                        elseif ($num == $currentStep) $stepStatus = 'active';
                    ?>
                        <div class="progress-step <?php echo $stepStatus; ?>">
                            <div class="step-circle"><?php echo $num; ?></div>
                            <div class="step-label"><?php echo $label; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="tracking-info">
                <?php if ($order['tracking_number']): ?>
                    <div class="info-item">
                        <div class="info-label">Tracking Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($order['tracking_number']); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if ($order['courier']): ?>
                    <div class="info-item">
                        <div class="info-label">Courier</div>
                        <div class="info-value"><?php echo htmlspecialchars($order['courier']); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($order['total_weight']): ?>
                    <div class="info-item">
                        <div class="info-label">Total Weight</div>
                        <div class="info-value"><?php echo number_format((float)$order['total_weight'], 3); ?> kg</div>
                    </div>
                <?php endif; ?>

                <?php if ($order['shipping_fee']): ?>
                    <div class="info-item">
                        <div class="info-label">Shipping Fee</div>
                        <div class="info-value" style="color:#e91e8c;font-weight:700;">₱<?php echo number_format((float)$order['shipping_fee'], 2); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if ($order['payment_method']): ?>
                    <div class="info-item">
                        <div class="info-label">Payment Method</div>
                        <div class="info-value"><?php echo ucfirst($order['payment_method']); ?></div>
                    </div>
                <?php endif; ?>
                
                <div class="info-item">
                    <div class="info-label">Payment Status</div>
                    <div class="info-value" style="color: <?php echo $order['payment_status'] === 'paid' ? '#10b981' : '#f59e0b'; ?>">
                        <?php echo ucfirst($order['payment_status']); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="timeline-section">
            <h2 class="section-title">Order Timeline</h2>
            <div class="timeline">
                <?php if (empty($timeline)): ?>
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <p>No timeline updates yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($timeline as $item): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <div class="timeline-title">
                                    <?php echo $statusLabels[$item['to_status']] ?? ucfirst($item['to_status']); ?>
                                    <?php if ($item['from_status']): ?>
                                        <span style="color: #999; font-weight: 400;">
                                            (from <?php echo $statusLabels[$item['from_status']] ?? ucfirst($item['from_status']); ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="timeline-time">
                                    <?php echo date('F j, Y g:i A', strtotime($item['created_at'])); ?>
                                    <?php if ($item['first_name']): ?>
                                        by <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?>
                                        (<?php echo ucfirst($item['role']); ?>)
                                    <?php endif; ?>
                                </div>
                                <?php if ($item['notes']): ?>
                                    <div class="timeline-notes"><?php echo htmlspecialchars($item['notes']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($order['seller_notes']): ?>
            <div class="media-section">
                <h2 class="section-title">Seller Notes</h2>
                <p style="color: #666; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($order['seller_notes'])); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($designFiles)): ?>
            <div class="media-section">
                <h2 class="section-title">Design Files</h2>
                <div class="media-grid">
                    <?php foreach ($designFiles as $file): ?>
                        <div class="media-item" onclick="window.open('<?php echo htmlspecialchars($file); ?>', '_blank')">
                            <img src="<?php echo htmlspecialchars($file); ?>" alt="Design file">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($proofImages)): ?>
            <div class="media-section">
                <h2 class="section-title">Proof Images</h2>
                <div class="media-grid">
                    <?php foreach ($proofImages as $image): ?>
                        <div class="media-item" onclick="window.open('<?php echo htmlspecialchars($image); ?>', '_blank')">
                            <img src="<?php echo htmlspecialchars($image); ?>" alt="Proof image">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($packagePhotos)): ?>
            <div class="media-section">
                <h2 class="section-title">Package Photos</h2>
                <div class="media-grid">
                    <?php foreach ($packagePhotos as $photo): ?>
                        <div class="media-item" onclick="window.open('<?php echo htmlspecialchars($photo); ?>', '_blank')">
                            <img src="<?php echo htmlspecialchars($photo); ?>" alt="Package photo">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($order['delivery_address']): ?>
            <div class="media-section">
                <h2 class="section-title">Delivery Address</h2>
                <p style="color: #666; line-height: 1.6;">
                    <?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?><br>
                    <?php echo htmlspecialchars($order['delivery_city']); ?>, <?php echo htmlspecialchars($order['delivery_province']); ?> <?php echo htmlspecialchars($order['delivery_zip']); ?><br>
                    <?php echo htmlspecialchars($order['delivery_country']); ?>
                </p>
                <?php if ($order['contact_phone']): ?>
                    <p style="color: #666; margin-top: 10px;">
                        <strong>Contact:</strong> <?php echo htmlspecialchars($order['contact_name']); ?> - <?php echo htmlspecialchars($order['contact_phone']); ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php // footer include removed ?>

<script>
// Real-time polling - check for status updates every 3 seconds
(function() {
  const currentStatus = '<?php echo $currentStatus; ?>';
  const orderId = <?php echo $orderId; ?>;
  let pollInterval = null;

  async function checkStatusUpdate() {
    try {
      // Simple check: reload the page data via API
      const res = await fetch(`../api/orders.php?action=list&search=<?php echo $order['order_reference'] ?? 'INK-' . str_pad($orderId, 6, '0', STR_PAD_LEFT); ?>`, { credentials: 'include' });
      const data = await res.json();
      if (data.success && data.orders && data.orders.length > 0) {
        const order = data.orders[0];
        if (order.status !== currentStatus) {
          // Status changed! Reload the page
          clearInterval(pollInterval);
          // Show a brief notification before refresh
          const notif = document.createElement('div');
          notif.style.cssText = 'position:fixed;top:1rem;right:1rem;background:#10b981;color:white;padding:1rem 1.5rem;border-radius:12px;font-weight:600;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,0.2);animation:slideIn 0.3s ease;';
          notif.textContent = '🔄 Order status updated to: ' + order.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
          document.body.appendChild(notif);
          setTimeout(() => location.reload(), 2000);
        }
      }
    } catch(e) {
      // Silently ignore polling errors
    }
  }

  // Only poll for active orders
  if (!['completed', 'cancelled', 'returned'].includes(currentStatus)) {
    pollInterval = setInterval(checkStatusUpdate, 3000);
  }
})();
</script>
</body>
</html>
