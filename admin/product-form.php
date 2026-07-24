<?php
session_start();
require_once __DIR__ . '/../db-config.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Please log in as admin.'];
    header('Location: ../index.php');
    exit();
}

$adminId = (int)$_SESSION['user_id'];
$editProduct = null;

$uploadDir = __DIR__ . '/../uploads/products';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
}

$categoryOptions = [];
$errors = [];

$categoryStmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name");
if ($categoryStmt) {
    $categoryStmt->execute();
    $categoryResult = $categoryStmt->get_result();
    while ($row = $categoryResult->fetch_assoc()) {
        $categoryOptions[$row['name']] = (int)$row['id'];
    }
    $categoryStmt->close();
}

$editId = isset($_GET['id']) && ctype_digit($_GET['id']) ? intval($_GET['id']) : 0;
$isEditing = $editId > 0;

if ($isEditing) {
    $productStmt = $conn->prepare("SELECT p.id, p.name, p.description, p.price, p.image_url, p.stock, p.weight, c.name AS category FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ? LIMIT 1");
    if ($productStmt) {
        $productStmt->bind_param('i', $editId);
        $productStmt->execute();
        $productResult = $productStmt->get_result();
        $editProduct = $productResult->fetch_assoc();
        $productStmt->close();
    }
}

// Handle form submission — must run BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $name = trim($_POST['name'] ?? '');
    $categoryId = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $description = trim($_POST['description'] ?? '');
    $price = '0';
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $imageUrl = '';
    $newImageUploaded = false;

    if ($name === '') {
        $errors[] = 'Product name is required.';
    }
    if ($description === '') {
        $errors[] = 'Product description is required.';
    }
    if ($categoryId <= 0 || !in_array($categoryId, $categoryOptions, true)) {
        $errors[] = 'Please select a valid category.';
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $imageFile = $_FILES['image'];
        if ($imageFile['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload failed. Please try again.';
        } else {
            $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            $imageInfo = getimagesize($imageFile['tmp_name']);
            if ($imageInfo === false || !isset($allowedTypes[$imageInfo['mime']])) {
                $errors[] = 'Please upload a valid image file (JPG, PNG, GIF, WEBP).';
            } else {
                $extension = $allowedTypes[$imageInfo['mime']];
                $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
                $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
                if (!move_uploaded_file($imageFile['tmp_name'], $targetPath)) {
                    $errors[] = 'Unable to save the uploaded image. Check folder permissions.';
                } else {
                    $newImageUploaded = true;
                    $imageUrl = '../uploads/products/' . $filename;
                }
            }
        }
    }

    if ($productId > 0) {
        $imageStmt = $conn->prepare("SELECT image_url FROM products WHERE id = ? LIMIT 1");
        if ($imageStmt) {
            $imageStmt->bind_param('i', $productId);
            $imageStmt->execute();
            $imageResult = $imageStmt->get_result();
            $row = $imageResult->fetch_assoc();
            if (!empty($row)) {
                if (!$newImageUploaded) {
                    $imageUrl = $row['image_url'] ?? '';
                }
            } else {
                $errors[] = 'Product not found.';
            }
            $imageStmt->close();
        }
    }

    if ($productId === 0 && !$newImageUploaded) {
        $errors[] = 'A product image is required when creating a new product.';
    }

    if (empty($errors)) {
        $priceValue = floatval(str_replace([','], '', $price));
        if ($productId > 0) {
            $stmt = $conn->prepare("UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, image_url = ?, stock = ?, weight = ? WHERE id = ?");
            if ($stmt) {
                $weight = floatval($_POST['weight'] ?? 0);
                $stmt->bind_param('issdsidi', $categoryId, $name, $description, $priceValue, $imageUrl, $stock, $weight, $productId);
                if (!$stmt->execute()) {
                    $errors[] = 'Database error while saving product.';
                }
                $stmt->close();
            }
            if (empty($errors)) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Product updated successfully.'];
                header('Location: products.php');
                exit();
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO products (category_id, admin_id, name, description, price, image_url, stock, weight) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $weight = floatval($_POST['weight'] ?? 0);
                $stmt->bind_param('iissdsid', $categoryId, $adminId, $name, $description, $priceValue, $imageUrl, $stock, $weight);
                if (!$stmt->execute()) {
                    $errors[] = 'Database error while saving product.';
                }
                $stmt->close();
            }
            if (empty($errors)) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Product added successfully.'];
                header('Location: products.php');
                exit();
            }
        }
    }

    $editProduct = [
        'id' => $productId,
        'name' => $name,
        'description' => $description,
        'stock' => $stock,
        'weight' => $_POST['weight'] ?? 0,
        'image_url' => $imageUrl,
        'category' => array_search($categoryId, $categoryOptions, true) ?: '',
    ];
}

$pageTitle = $isEditing ? 'Edit Product' : 'Add Product';
$pageSubtitle = $isEditing ? 'Update product details' : 'Add a new product to your catalog';

require_once __DIR__ . '/includes/admin-header.php';
?>

      <section class="card" id="product-form">
        <div class="card-header">
          <a href="products.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Products</a>
          <div>
            <span>Product Manager</span>
            <h2><?php echo $isEditing ? 'Edit Product' : 'Add New Product'; ?></h2>
            <p><?php echo $isEditing ? 'Update the product details below.' : 'Fill in the product details and upload a photo to add to your catalog.'; ?></p>
          </div>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="message-bar error">
          <ul>
            <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <form action="product-form.php<?php echo $isEditing ? '?id=' . $editId : ''; ?>" method="post" enctype="multipart/form-data">
          <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($editProduct['id'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
          <div class="form-grid">
            <div class="form-group">
              <label>Product Name</label>
              <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($editProduct['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group">
              <label>Category</label>
              <select name="category_id" class="form-input" required>
                <option value="">Select category</option>
                <?php foreach ($categoryOptions as $name => $catId): ?>
                <option value="<?php echo htmlspecialchars($catId, ENT_QUOTES, 'UTF-8'); ?>" <?php echo isset($editProduct['category']) && $editProduct['category'] === $name ? 'selected' : ''; ?>><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group full-width">
              <label>Description</label>
              <textarea name="description" class="form-input" rows="4" required><?php echo htmlspecialchars($editProduct['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="form-group">
              <label>Stock Quantity</label>
              <input type="number" name="stock" class="form-input" value="<?php echo htmlspecialchars($editProduct['stock'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" min="0" placeholder="0">
            </div>

            <div class="form-group full-width">
              <label>Product Image</label>
              <input type="file" name="image" class="form-input" accept="image/*" <?php echo empty($editProduct) ? 'required' : ''; ?>>
              <?php if (!empty($editProduct['image_url'])): ?>
              <div style="display:flex;align-items:center;gap:0.75rem;margin-top:0.5rem;">
                <span>Current image:</span>
                <img src="<?php echo htmlspecialchars($editProduct['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Current product image">
              </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="modal-actions" style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary"><?php echo $isEditing ? 'Update Product' : 'Add Product'; ?></button>
            <a href="products.php" class="btn btn-outline">Cancel</a>
          </div>
        </form>
      </section>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
