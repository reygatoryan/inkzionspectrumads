<?php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'inkzion');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

$conn->set_charset("utf8");

function db_column_exists(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
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
