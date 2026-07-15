<?php
// Database configuration
// Uses environment variables in production (Render), falls back to local XAMPP defaults
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'inkzion');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

$conn->set_charset("utf8");

function db_column_exists(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS count
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['count'] ?? 0) > 0;
}

function db_add_column_if_missing(mysqli $conn, string $table, string $column, string $definition): void {
    if (!db_column_exists($conn, $table, $column)) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN $definition");
    }
}

function db_create_table_if_missing(mysqli $conn, string $table, string $createSql): void {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows === 0) {
        $conn->query($createSql);
    }
}

function db_index_exists(mysqli $conn, string $table, string $index): bool {
    $result = $conn->query("SHOW INDEX FROM `$table` WHERE Key_name = '$index'");
    return $result && $result->num_rows > 0;
}

function db_add_index_if_missing(mysqli $conn, string $table, string $index, string $definition, array $requiredColumns = []): void {
    if (db_index_exists($conn, $table, $index)) return;
    foreach ($requiredColumns as $col) {
        if (!db_column_exists($conn, $table, $col)) return;
    }
    @$conn->query("CREATE INDEX $index ON `$table` $definition");
}

// ============================================================
// NEW TABLES FOR ENHANCED FEATURES
// ============================================================

// 1. Email verification
db_create_table_if_missing($conn, 'email_verifications', "
    CREATE TABLE email_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(128) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        verified_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id),
        INDEX idx_token (token)
    )
");

// 2. User addresses (multiple per user)
db_create_table_if_missing($conn, 'user_addresses', "
    CREATE TABLE user_addresses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        label VARCHAR(50) DEFAULT 'Home',
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        street VARCHAR(255) NOT NULL,
        barangay VARCHAR(100) NOT NULL,
        city VARCHAR(100) NOT NULL,
        province VARCHAR(100) NOT NULL,
        zip_code VARCHAR(10) NOT NULL,
        country VARCHAR(100) DEFAULT 'Philippines',
        is_default TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id)
    )
");

// 3. Order timeline (tracks status changes)
db_create_table_if_missing($conn, 'order_timeline', "
    CREATE TABLE order_timeline (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        from_status VARCHAR(30) DEFAULT NULL,
        to_status VARCHAR(30) NOT NULL,
        changed_by INT DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_order (order_id),
        INDEX idx_created_at (created_at)
    )
");

// 4. Product reviews
db_create_table_if_missing($conn, 'product_reviews', "
    CREATE TABLE product_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        user_id INT NOT NULL,
        order_id INT NOT NULL,
        rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
        title VARCHAR(255) DEFAULT NULL,
        review TEXT DEFAULT NULL,
        is_approved TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        INDEX idx_product (product_id),
        INDEX idx_user (user_id),
        INDEX idx_order (order_id)
    )
");

// 5. Add is_verified column to users
db_add_column_if_missing($conn, 'users', 'email_verified_at', 'email_verified_at DATETIME DEFAULT NULL');
db_add_column_if_missing($conn, 'users', 'is_online', 'is_online TINYINT(1) DEFAULT 0');
db_add_column_if_missing($conn, 'users', 'last_seen', 'last_seen DATETIME DEFAULT NULL');

// 6. Product variants (sizes, colors, materials)
db_create_table_if_missing($conn, 'product_variants', "
    CREATE TABLE product_variants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        size VARCHAR(50) DEFAULT NULL,
        color VARCHAR(50) DEFAULT NULL,
        material VARCHAR(100) DEFAULT NULL,
        sku VARCHAR(100) DEFAULT NULL,
        price DECIMAL(10,2) DEFAULT NULL,
        stock INT DEFAULT 0,
        image_url VARCHAR(500) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        INDEX idx_product (product_id)
    )
");

// 7. Product images (multiple images per product)
db_create_table_if_missing($conn, 'product_images', "
    CREATE TABLE product_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        image_url VARCHAR(500) NOT NULL,
        is_primary TINYINT(1) DEFAULT 0,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        INDEX idx_product (product_id)
    )
");

// 8. Product videos
db_create_table_if_missing($conn, 'product_videos', "
    CREATE TABLE product_videos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        video_url VARCHAR(500) NOT NULL,
        thumbnail_url VARCHAR(500) DEFAULT NULL,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        INDEX idx_product (product_id)
    )
");

// 9. Product discounts
db_create_table_if_missing($conn, 'product_discounts', "
    CREATE TABLE product_discounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        discount_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
        discount_value DECIMAL(10,2) NOT NULL,
        start_date DATETIME DEFAULT NULL,
        end_date DATETIME DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        INDEX idx_product (product_id)
    )
");

// 10. Wishlist
db_create_table_if_missing($conn, 'wishlist', "
    CREATE TABLE wishlist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        UNIQUE KEY unique_wishlist (user_id, product_id),
        INDEX idx_user (user_id)
    )
");

// 11. Recently viewed
db_create_table_if_missing($conn, 'recently_viewed', "
    CREATE TABLE recently_viewed (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        INDEX idx_user (user_id),
        INDEX idx_viewed_at (viewed_at)
    )
");

// 12. Custom printing requests
db_create_table_if_missing($conn, 'custom_printing_requests', "
    CREATE TABLE custom_printing_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        service_type VARCHAR(100) NOT NULL,
        size VARCHAR(50) DEFAULT NULL,
        material VARCHAR(100) DEFAULT NULL,
        color VARCHAR(50) DEFAULT NULL,
        finish VARCHAR(50) DEFAULT NULL,
        quantity INT DEFAULT 1,
        special_requests TEXT DEFAULT NULL,
        need_design_assistance TINYINT(1) DEFAULT 0,
        preferred_deadline DATE DEFAULT NULL,
        reference_images TEXT DEFAULT NULL,
        status ENUM('pending', 'reviewed', 'approved', 'rejected', 'completed') DEFAULT 'pending',
        admin_notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id),
        INDEX idx_status (status)
    )
");

// 13. Custom request files (artwork uploads)
db_create_table_if_missing($conn, 'custom_request_files', "
    CREATE TABLE custom_request_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        file_url VARCHAR(500) NOT NULL,
        file_type VARCHAR(50) DEFAULT 'image',
        file_name VARCHAR(255) DEFAULT NULL,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES custom_printing_requests(id) ON DELETE CASCADE,
        INDEX idx_request (request_id)
    )
");

// 14. Subcategories
db_create_table_if_missing($conn, 'subcategories', "
    CREATE TABLE subcategories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
        INDEX idx_category (category_id)
    )
");

// 15. Chat conversations
db_create_table_if_missing($conn, 'chat_conversations', "
    CREATE TABLE chat_conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        seller_id INT NOT NULL,
        last_message_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id),
        INDEX idx_seller (seller_id),
        INDEX idx_last_message (last_message_at)
    )
");

// Add request_id and product_id to chat_conversations (migration)
db_add_column_if_missing($conn, 'chat_conversations', 'request_id', 'request_id INT DEFAULT NULL AFTER seller_id');
db_add_column_if_missing($conn, 'chat_conversations', 'product_id',  'product_id INT DEFAULT NULL AFTER request_id');

// 16. Chat messages
db_create_table_if_missing($conn, 'chat_messages', "
    CREATE TABLE chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        sender_id INT NOT NULL,
        message_type ENUM('text', 'image', 'file') DEFAULT 'text',
        content TEXT DEFAULT NULL,
        file_url VARCHAR(500) DEFAULT NULL,
        file_name VARCHAR(255) DEFAULT NULL,
        file_type VARCHAR(50) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_conversation (conversation_id),
        INDEX idx_created_at (created_at)
    )
");

// Extend chat_messages message_type ENUM to support custom_request and order_form links
$conn->query("ALTER TABLE chat_messages MODIFY COLUMN message_type ENUM('text','image','file','custom_request','order_form') DEFAULT 'text'");

// 17. Chat typing indicators
db_create_table_if_missing($conn, 'chat_typing', "
    CREATE TABLE chat_typing (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        user_id INT NOT NULL,
        is_typing TINYINT(1) DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_typing (conversation_id, user_id)
    )
");

// Chat performance indexes
db_add_index_if_missing($conn, 'chat_messages', 'idx_conversation_created', '(conversation_id, created_at)', ['conversation_id', 'created_at']);
db_add_index_if_missing($conn, 'chat_conversations', 'idx_conv_user_lastmsg', '(user_id, last_message_at)', ['user_id', 'last_message_at']);
db_add_index_if_missing($conn, 'chat_conversations', 'idx_conv_admin_lastmsg', '(admin_id, last_message_at)', ['admin_id', 'last_message_at']);
db_add_index_if_missing($conn, 'chat_conversations', 'idx_conv_seller_lastmsg', '(seller_id, last_message_at)', ['seller_id', 'last_message_at']);
db_add_index_if_missing($conn, 'chat_typing', 'idx_typing_conv_updated', '(conversation_id, updated_at)', ['conversation_id', 'updated_at']);

// Simplify roles: remove seller, convert to admin
$conn->query("UPDATE users SET role = 'admin' WHERE role = 'seller'");
$conn->query("ALTER TABLE users MODIFY role ENUM('admin', 'user') DEFAULT 'user'");

// Lightweight compatibility migrations for existing local installs.
$conn->query("CREATE TABLE IF NOT EXISTS password_reset_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(128) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
)");

$conn->query("CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
)");

db_add_column_if_missing($conn, 'users', 'security_question_1', 'security_question_1 VARCHAR(255) DEFAULT NULL');
db_add_column_if_missing($conn, 'users', 'security_answer_1', 'security_answer_1 VARCHAR(255) DEFAULT NULL');
db_add_column_if_missing($conn, 'users', 'security_question_2', 'security_question_2 VARCHAR(255) DEFAULT NULL');
db_add_column_if_missing($conn, 'users', 'security_answer_2', 'security_answer_2 VARCHAR(255) DEFAULT NULL');
db_add_column_if_missing($conn, 'products', 'seller_id', 'seller_id INT DEFAULT NULL AFTER category_id');
db_add_column_if_missing($conn, 'products', 'subcategory_id', 'subcategory_id INT DEFAULT NULL AFTER category_id');
db_add_column_if_missing($conn, 'products', 'is_featured', 'is_featured TINYINT(1) DEFAULT 0 AFTER stock');
db_add_column_if_missing($conn, 'products', 'is_best_seller', 'is_best_seller TINYINT(1) DEFAULT 0 AFTER is_featured');
db_add_column_if_missing($conn, 'products', 'is_new_arrival', 'is_new_arrival TINYINT(1) DEFAULT 0 AFTER is_best_seller');
db_add_column_if_missing($conn, 'products', 'video_url', 'video_url VARCHAR(500) DEFAULT NULL AFTER image_url');
db_add_column_if_missing($conn, 'products', 'weight', "weight DECIMAL(8,3) DEFAULT 0 AFTER stock");
db_add_column_if_missing($conn, 'orders', 'order_reference', 'order_reference VARCHAR(20) UNIQUE NULL AFTER user_id');
db_add_column_if_missing($conn, 'orders', 'payment_method', 'payment_method VARCHAR(50) DEFAULT NULL AFTER status');
db_add_column_if_missing($conn, 'orders', 'payment_status', "payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending' AFTER payment_method");
db_add_column_if_missing($conn, 'orders', 'contact_name', 'contact_name VARCHAR(255) DEFAULT NULL AFTER payment_status');
db_add_column_if_missing($conn, 'orders', 'contact_email', 'contact_email VARCHAR(255) DEFAULT NULL AFTER contact_name');
db_add_column_if_missing($conn, 'orders', 'contact_phone', 'contact_phone VARCHAR(20) DEFAULT NULL AFTER contact_email');
db_add_column_if_missing($conn, 'orders', 'delivery_address', 'delivery_address TEXT DEFAULT NULL AFTER contact_phone');
db_add_column_if_missing($conn, 'orders', 'delivery_city', 'delivery_city VARCHAR(100) DEFAULT NULL AFTER delivery_address');
db_add_column_if_missing($conn, 'orders', 'delivery_province', 'delivery_province VARCHAR(100) DEFAULT NULL AFTER delivery_city');
db_add_column_if_missing($conn, 'orders', 'delivery_zip', 'delivery_zip VARCHAR(10) DEFAULT NULL AFTER delivery_province');
db_add_column_if_missing($conn, 'orders', 'delivery_country', "delivery_country VARCHAR(100) DEFAULT 'Philippines' AFTER delivery_zip");
db_add_column_if_missing($conn, 'orders', 'delivery_notes', 'delivery_notes TEXT DEFAULT NULL AFTER delivery_country');
db_add_column_if_missing($conn, 'orders', 'order_notes', 'order_notes TEXT DEFAULT NULL AFTER delivery_notes');
db_add_column_if_missing($conn, 'orders', 'tracking_number', 'tracking_number VARCHAR(100) DEFAULT NULL AFTER order_notes');
db_add_column_if_missing($conn, 'orders', 'courier', 'courier VARCHAR(100) DEFAULT NULL AFTER tracking_number');
db_add_column_if_missing($conn, 'orders', 'estimated_delivery', 'estimated_delivery DATE DEFAULT NULL AFTER courier');
db_add_column_if_missing($conn, 'orders', 'payment_reference', "payment_reference VARCHAR(255) DEFAULT NULL AFTER payment_method");
db_add_column_if_missing($conn, 'orders', 'balance_method', "balance_method VARCHAR(50) DEFAULT NULL AFTER payment_reference");
db_add_column_if_missing($conn, 'orders', 'total_weight', "total_weight DECIMAL(8,3) DEFAULT NULL AFTER balance_method");
db_add_column_if_missing($conn, 'orders', 'shipping_fee', "shipping_fee DECIMAL(10,2) DEFAULT NULL AFTER total_weight");
db_add_column_if_missing($conn, 'customization_requests', 'updated_at', 'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
// Simplify order statuses: map old to new
$conn->query("UPDATE orders SET status = 'pending' WHERE status NOT IN ('pending','confirmed','shipped','delivered','completed','cancelled','returned')");
$conn->query("UPDATE orders SET status = 'returned' WHERE status = 'return_refund'");
$conn->query("UPDATE orders SET status = 'confirmed' WHERE status IN ('payment_verification','design_review','awaiting_approval','approved','printing','quality_check','packaging','ready_pickup')");
$conn->query("UPDATE orders SET status = 'shipped' WHERE status IN ('out_delivery')");
$conn->query("ALTER TABLE orders MODIFY status ENUM('pending', 'confirmed', 'shipped', 'delivered', 'completed', 'cancelled', 'returned') DEFAULT 'pending'");
$conn->query("ALTER TABLE customization_requests MODIFY status ENUM('pending', 'reviewed', 'approved', 'rejected', 'completed') DEFAULT 'pending'");

// ============================================================
// PHASE B: Custom Request Chat + Buy Now columns
// ============================================================

db_add_column_if_missing($conn, 'custom_printing_requests', 'items', 'items TEXT DEFAULT NULL AFTER finish');
$conn->query("ALTER TABLE custom_printing_requests MODIFY items LONGTEXT DEFAULT NULL");
db_add_column_if_missing($conn, 'custom_printing_requests', 'chat_conversation_id', 'chat_conversation_id INT DEFAULT NULL AFTER admin_notes');
db_add_column_if_missing($conn, 'custom_printing_requests', 'ready_for_purchase_price', 'ready_for_purchase_price DECIMAL(12,2) DEFAULT NULL AFTER chat_conversation_id');
db_add_column_if_missing($conn, 'custom_printing_requests', 'ready_for_purchase_name', 'ready_for_purchase_name VARCHAR(255) DEFAULT NULL AFTER ready_for_purchase_price');
db_add_column_if_missing($conn, 'custom_printing_requests', 'ready_for_purchase_qty', 'ready_for_purchase_qty INT DEFAULT NULL AFTER ready_for_purchase_name');
db_add_column_if_missing($conn, 'custom_printing_requests', 'ready_for_purchase_image', 'ready_for_purchase_image VARCHAR(500) DEFAULT NULL AFTER ready_for_purchase_qty');
db_add_column_if_missing($conn, 'custom_printing_requests', 'ready_for_purchase_shipping', 'ready_for_purchase_shipping DECIMAL(12,2) DEFAULT 0.00 AFTER ready_for_purchase_image');
$conn->query("ALTER TABLE custom_printing_requests MODIFY status ENUM('pending', 'in_review', 'approved', 'rejected', 'ready_for_purchase', 'completed') DEFAULT 'pending'");

db_add_column_if_missing($conn, 'chat_conversations', 'request_id', 'request_id INT DEFAULT NULL AFTER last_message_at');
db_add_column_if_missing($conn, 'chat_conversations', 'admin_id', 'admin_id INT DEFAULT NULL AFTER request_id');

// ============================================================
// PHASE C: Site Content (Content Manager)
// ============================================================

db_create_table_if_missing($conn, 'site_content', "
    CREATE TABLE site_content (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section_key VARCHAR(50) NOT NULL UNIQUE,
        title VARCHAR(255) DEFAULT NULL,
        subtitle TEXT DEFAULT NULL,
        content TEXT DEFAULT NULL,
        image_url VARCHAR(500) DEFAULT NULL,
        meta JSON DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_section_key (section_key)
    )
");

// Insert default rows if empty
$conn->query("INSERT IGNORE INTO site_content (section_key, title, subtitle, content, meta) VALUES
    ('homepage_hero', 'Custom Printing Solutions', 'High-quality printing for your business needs', 'We specialize in custom printing services that bring your ideas to life.', '{\"button_text\":\"View Our Products\",\"button_link\":\"customer/store-product.php\"}'),
    ('about_us', 'About Inkzion Spectrum Ads', 'Your trusted printing partner', 'Inkzion Spectrum Ads is a premier printing services provider dedicated to delivering high-quality custom printing solutions that meet the unique needs of every client we serve. With years of hands-on experience in the printing and advertising industry, we have developed a deep understanding of what it takes to produce exceptional results — from the initial consultation and design phase all the way to final production and delivery. Our team takes great pride in our meticulous attention to detail, ensuring that every print job, whether big or small, meets the highest standards of quality and craftsmanship. We believe that strong customer service is the foundation of any successful partnership, which is why we maintain open communication, provide personalized support, and go the extra mile to exceed expectations. Beyond just printing, we are committed to helping businesses, schools, and individuals bring their ideas to life through creative, reliable, and cost-effective solutions that leave a lasting impression.', NULL),
    ('contact_info', 'Contact Us', 'We''d love to hear from you', 'Get in touch with us for inquiries, quotes, or support.', '{\"address\":\"123 Printing Street, Manila, Philippines\",\"phone\":\"+63 912 345 6789\",\"email\":\"info@inkzionspectrum.com\",\"map_url\":\"\"}'),
    ('help_center', 'Help Center', 'Frequently asked questions', NULL, '{\"faqs\":[{\"question\":\"How do I place an order?\",\"answer\":\"Browse our products, add items to your cart, and proceed to checkout. You can also request custom printing services through our Custom Printing page.\"},{\"question\":\"What payment methods do you accept?\",\"answer\":\"We accept various payment methods including bank transfers and cash on delivery for local orders.\"},{\"question\":\"How long does shipping take?\",\"answer\":\"Standard shipping typically takes 2-5 business days depending on your location.\"},{\"question\":\"Can I request a custom design?\",\"answer\":\"Yes! Visit our Custom Printing page to submit your requirements and our team will get back to you.\"}]}')
");

// ============================================================
// PHASE D: Google Sign-In columns
// ============================================================

db_add_column_if_missing($conn, 'users', 'google_id', 'google_id VARCHAR(255) DEFAULT NULL UNIQUE');
db_add_column_if_missing($conn, 'users', 'avatar', 'avatar VARCHAR(500) DEFAULT NULL');
$conn->query("ALTER TABLE users MODIFY password VARCHAR(255) DEFAULT NULL");

// ============================================================
// PHASE E: Page Views (Visitor Tracking)
// ============================================================

db_create_table_if_missing($conn, 'page_views', "
    CREATE TABLE page_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        page_url VARCHAR(500) NOT NULL,
        visitor_ip VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        user_id INT DEFAULT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_viewed_at (viewed_at),
        INDEX idx_page_url (page_url),
        INDEX idx_visitor_ip (visitor_ip)
    )
");

// Cleanup rows older than 60 days
$conn->query("DELETE FROM page_views WHERE viewed_at < NOW() - INTERVAL 60 DAY");

// ============================================================
// PHASE E1: Add admin_id to products table for API consistency
// ============================================================

db_add_column_if_missing($conn, 'products', 'admin_id', 'admin_id INT DEFAULT NULL AFTER seller_id');
$conn->query("UPDATE products SET admin_id = seller_id WHERE admin_id IS NULL AND seller_id IS NOT NULL");

// ============================================================
// PHASE F: Shipping Settings
// ============================================================

db_create_table_if_missing($conn, 'shipping_settings', "
    CREATE TABLE shipping_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section_key VARCHAR(50) NOT NULL UNIQUE,
        settings JSON NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_section_key (section_key)
    )
");

// Seed default shipping settings if empty
$result = $conn->query("SELECT COUNT(*) as cnt FROM shipping_settings");
$row = $result->fetch_assoc();
if ((int)$row['cnt'] === 0) {
    $defaults = json_encode([
        'couriers' => [
            'jnt' => ['enabled' => true, 'account_number' => '09XX-XXX-XXXX', 'pickup_address' => 'Inkzion Office - 123 Printing Street, Quezon City', 'default_fee' => 50, 'same_day_pickup' => true],
            'store_pickup' => ['enabled' => true, 'address' => 'Inkzion Spectrum Ads Office, 123 Printing Street, Brgy. San Jose, Quezon City, Metro Manila 1100', 'hours_start' => '09:00', 'hours_end' => '18:00']
        ],
        'addresses' => [
            ['id' => 1, 'label' => 'Default Address', 'is_default' => true, 'name' => 'Inkzion Spectrum Ads Office', 'street' => '123 Printing Street, Brgy. San Jose', 'city' => 'Quezon City', 'province' => 'Metro Manila', 'zip' => '1100', 'country' => 'Philippines', 'contact' => '+63 2X XXXX XXXX'],
            ['id' => 2, 'label' => 'Warehouse', 'is_default' => false, 'name' => 'Inkzion Warehouse', 'street' => '456 Industrial Ave, Brgy. Manggahan', 'city' => 'Pasig City', 'province' => 'Metro Manila', 'zip' => '1600', 'country' => 'Philippines', 'contact' => '+63 2X XXXX XXXX']
        ],
        'hours' => [
            'weekday_start' => '09:00', 'weekday_end' => '18:00',
            'saturday_start' => '09:00', 'saturday_end' => '12:00'
        ],
        'payment' => [
            'methods' => [
                'gcash' => ['enabled' => true, 'account_number' => '', 'account_name' => ''],
                'credit_card' => ['enabled' => true],
                'downpayment' => ['enabled' => true, 'percentage' => 50],
                'cod' => ['enabled' => true]
            ]
        ],
        'chat' => [
            'enabled' => true,
            'operating_hours' => ['enabled' => false, 'weekday_start' => '09:00', 'weekday_end' => '18:00'],
            'auto_response' => ['enabled' => false, 'message' => 'Thank you for reaching out! We will get back to you shortly.'],
            'file_upload' => ['max_size_mb' => 25, 'allowed_types' => 'jpg,jpeg,png,pdf,doc,docx']
        ],
        'notification' => [
            'in_app' => [
                'new_order' => true,
                'order_update' => true,
                'payment_update' => true,
                'chat_message' => true,
                'custom_request' => true
            ],
            'retention_days' => 90
        ]
    ]);
    $conn->query("INSERT INTO shipping_settings (section_key, settings) VALUES ('shipping', '" . $conn->real_escape_string($defaults) . "')");
}

// Migration: order_proposals table
$conn->query("CREATE TABLE IF NOT EXISTS order_proposals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    admin_id INT NOT NULL,
    request_id INT NULL,
    conversation_id INT NULL,
    items JSON NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    shipping_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    admin_notes TEXT NULL,
    full_name VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    delivery_address TEXT NULL,
    city VARCHAR(100) NULL,
    province VARCHAR(100) NULL,
    zip VARCHAR(20) NULL,
    payment_method VARCHAR(50) NULL,
    additional_notes TEXT NULL,
    landmark VARCHAR(255) NULL,
    status ENUM('sent','filled','approved','rejected','converted') NOT NULL DEFAULT 'sent',
    rejection_reason TEXT NULL,
    order_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_status (status)
)");

// Notifications table
db_create_table_if_missing($conn, 'notifications', "
    CREATE TABLE notifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        type VARCHAR(50) NOT NULL DEFAULT 'system',
        title VARCHAR(255) NOT NULL,
        body TEXT NULL,
        related_type VARCHAR(50) NULL,
        related_id INT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        is_deleted TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_is_read (is_read),
        INDEX idx_user_unread (user_id, is_read, is_deleted)
    )
");

// Migration: add landmark column to order_proposals
try { $conn->query("ALTER TABLE order_proposals ADD COLUMN landmark VARCHAR(255) NULL AFTER additional_notes"); } catch (Exception $e) {}

// Migration: add product_name to order_items, make product_id nullable (for proposals without a product ID)
try { $conn->query("ALTER TABLE order_items ADD COLUMN product_name VARCHAR(255) NULL AFTER product_id"); } catch (Exception $e) {}
try { $conn->query("ALTER TABLE order_items MODIFY COLUMN product_id INT NULL"); } catch (Exception $e) {}

// Migration: add admin_id to orders (for proposal-created orders that have no product_id link)
try { $conn->query("ALTER TABLE orders ADD COLUMN admin_id INT NULL AFTER user_id"); } catch (Exception $e) {}

// Migration: ensure notifications table has all required columns
db_add_column_if_missing($conn, 'notifications', 'is_deleted', "is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read");
db_add_column_if_missing($conn, 'notifications', 'related_type', "related_type VARCHAR(50) NULL AFTER body");
db_add_column_if_missing($conn, 'notifications', 'related_id', "related_id INT NULL AFTER related_type");

?>