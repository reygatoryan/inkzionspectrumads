<?php
$pageTitle = 'Products';
$pageSubtitle = 'Manage your product catalog';
require_once __DIR__ . '/includes/admin-header.php';

$uploadDir = __DIR__ . '/uploads/products';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
}

$categoryOptions = [];
$categoryStmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name");
if ($categoryStmt) {
    $categoryStmt->execute();
    $categoryResult = $categoryStmt->get_result();
    while ($row = $categoryResult->fetch_assoc()) {
        $categoryOptions[$row['name']] = (int)$row['id'];
    }
    $categoryStmt->close();
}

$products = [];
if (!empty($categoryOptions)) {
    $productStmt = $conn->prepare("SELECT p.id, p.name, p.description, p.price, p.image_url, p.stock, p.weight, c.name AS category FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.name");
    if ($productStmt) {
        $productStmt->execute();
        $productResult = $productStmt->get_result();
        while ($row = $productResult->fetch_assoc()) {
            $products[] = $row;
        }
        $productStmt->close();
    }
}
?>

      <?php if (!empty($categoryOptions)): ?>
      <section>
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap; margin-bottom: 24px;">
          <h2 style="margin: 0;"><i class="fas fa-box"></i> All Products (<?php echo count($products); ?>)</h2>
          <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <div style="position: relative; min-width: 220px;">
              <i class="fas fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.9rem; pointer-events: none;"></i>
              <input type="text" id="productSearch" placeholder="Search products..." style="width: 100%; border: 1px solid #d1d5db; border-radius: 12px; padding: 10px 14px 10px 38px; font-size: 0.9rem; color: #111827; background: #ffffff; box-sizing: border-box; outline: none; transition: border-color 0.2s ease, box-shadow 0.2s ease;">
            </div>
            <select id="categoryFilter" style="border: 1px solid #d1d5db; border-radius: 12px; padding: 10px 14px; font-size: 0.9rem; color: #111827; background: #ffffff; outline: none; cursor: pointer; transition: border-color 0.2s ease, box-shadow 0.2s ease;">
              <option value="">All Categories</option>
              <?php foreach ($categoryOptions as $name => $catId): ?>
              <option value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <?php if (empty($products)): ?>
        <div class="empty-state">
          <i class="fas fa-box-open"></i>
          <h3>No products yet</h3>
          <p>Start by adding your first product.</p>
        </div>
        <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Image</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Weight</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $product): 
                $pJson = htmlspecialchars(json_encode([
                  'id' => $product['id'],
                  'name' => $product['name'],
                  'category' => $product['category'],
                  'price' => number_format((float)$product['price'], 2),
                  'stock' => (int)$product['stock'],
                  'description' => $product['description'],
                  'image_url' => $product['image_url'] ?? '../assets/logo.png',
                ]), ENT_QUOTES, 'UTF-8');
              ?>
              <tr>
                <td>
                  <img src="<?php echo htmlspecialchars($product['image_url'] ?? '../assets/logo.png', ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" width="40" height="40" loading="lazy" style="width:40px;height:40px;border-radius:8px;object-fit:cover;cursor:pointer;" onclick='viewProduct(<?php echo $pJson; ?>)'>
                </td>
                <td style="font-weight:600;">
                  <span style="color:var(--primary);cursor:pointer;" onclick='viewProduct(<?php echo $pJson; ?>)'>
                    <?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                </td>
                <td><?php echo htmlspecialchars($product['category'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td style="font-weight:700;color:var(--text-primary);">?<?php echo number_format((float)$product['price'], 2); ?></td>
                <td>
                  <span class="badge" style="<?php echo ($product['stock'] > 0) ? 'background:rgba(16,185,129,0.1);color:#059669;' : 'background:var(--danger-bg);color:var(--danger);'; ?>">
                    <?php echo (int)$product['stock']; ?> units
                  </span>
                </td>
                <td><?php echo number_format((float)($product['weight'] ?? 0), 3); ?> kg</td>
                <td>
                  <div style="display:flex;gap:0.35rem;">
                    <button class="btn btn-sm btn-outline" onclick='viewProduct(<?php echo $pJson; ?>)' title="View product details">
                      <i class="fas fa-eye"></i> View
                    </button>
                    <a href="product-form.php?id=<?php echo htmlspecialchars($product['id'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline">
                      <i class="fas fa-edit"></i> Edit
                    </a>
                    <button class="btn btn-sm btn-danger" onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>')">
                      <i class="fas fa-trash"></i> Delete
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </section>
      <?php else: ?>
      <div class="card" style="text-align: center; padding: 60px;">
        <i class="fas fa-database" style="font-size: 3rem; color: #d1d5db; margin-bottom: 16px;"></i>
        <h2>No Categories Available</h2>
        <p>Please set up product categories in the database before you can start adding products.</p>
      </div>
      <?php endif; ?>
  <!-- View Product Modal -->
  <div class="modal-overlay" id="viewModal">
    <div class="modal-content" style="position:relative;max-width:640px;">
      <button style="position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:1.5rem;color:var(--text-muted);cursor:pointer;" onclick="closeViewModal()">&times;</button>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
        <div style="background:#f8fafc;border-radius:12px;overflow:hidden;display:flex;align-items:center;justify-content:center;min-height:200px;">
          <img id="viewModalImage" src="" alt="Product image">
        </div>
        <div>
          <p style="font-size:0.7rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.25rem;" id="viewModalCategory">Category</p>
          <h2 id="viewModalName">Product Name</h2>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin:0.75rem 0;">
            <div>
              <span style="font-size:0.72rem;font-weight:600;color:var(--text-muted);display:block;">Category</span>
              <span style="font-size:0.9rem;font-weight:600;color:var(--text-primary);" id="viewModalCategory2">-</span>
            </div>
            <div>
              <span style="font-size:0.72rem;font-weight:600;color:var(--text-muted);display:block;">Stock</span>
              <span style="font-size:0.9rem;font-weight:600;color:var(--text-primary);" id="viewModalStock">-</span>
            </div>
          </div>
          <div style="font-size:1.3rem;font-weight:800;color:var(--primary);margin:0.75rem 0;" id="viewModalPrice">?0.00</div>
          <div style="font-size:0.85rem;color:var(--text-secondary);line-height:1.7;margin-bottom:1rem;" id="viewModalDescription">No description available.</div>
          <div class="modal-actions">
            <a href="product-form.php" id="viewModalEditBtn" class="btn-primary"><i class="fas fa-edit"></i> Edit Product</a>
            <button class="btn-outline" onclick="closeViewModal()"><i class="fas fa-times"></i> Close</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal-overlay" id="deleteModal" style="display: none;">
    <div class="modal-content" style="max-width: 440px; text-align: center;">
      <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
      <div style="font-size: 3rem; margin-bottom: 16px; color: #ef4444;">&#9888;</div>
      <h2>Delete Product</h2>
      <p style="color: #64748b; margin-bottom: 24px;">Are you sure you want to delete <strong id="deleteProductName"></strong>? This action cannot be undone.</p>
      <div style="display: flex; gap: 12px; justify-content: center;">
        <button class="btn btn-outline" onclick="closeDeleteModal()" style="padding: 12px 28px; border-radius: 12px; font-weight: 600; cursor: pointer; background: white; color: #475569; border: 1px solid #cbd5e1;">Cancel</button>
        <button class="btn" style="background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border: none; padding: 12px 28px; border-radius: 12px; font-weight: 600; cursor: pointer;" onclick="confirmDelete()">
          <i class="fas fa-trash"></i> Delete Product
        </button>
      </div>
    </div>
  </div>

  <script>
    // View Product Modal
    function viewProduct(data) {
      document.getElementById('viewModalImage').src = data.image_url;
      document.getElementById('viewModalImage').alt = data.name;
      document.getElementById('viewModalCategory').textContent = data.category;
      document.getElementById('viewModalName').textContent = data.name;
      document.getElementById('viewModalCategory2').textContent = data.category;
      document.getElementById('viewModalStock').textContent = data.stock + ' units';
      document.getElementById('viewModalPrice').textContent = '?' + data.price;
      document.getElementById('viewModalDescription').textContent = data.description;
      document.getElementById('viewModalEditBtn').href = 'product-form.php?id=' + data.id;
      document.getElementById('viewModal').classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    function closeViewModal() {
      document.getElementById('viewModal').classList.remove('active');
      document.body.style.overflow = '';
    }

    document.getElementById('viewModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeViewModal();
      }
    });

    // Delete Product functionality
    let deleteProductId = 0;

    function deleteProduct(id, name) {
      deleteProductId = id;
      document.getElementById('deleteProductName').textContent = name;
      document.getElementById('deleteModal').style.display = 'flex';
    }

    function closeDeleteModal() {
      document.getElementById('deleteModal').style.display = 'none';
      deleteProductId = 0;
    }

    function confirmDelete() {
      if (!deleteProductId) return;

      const xhr = new XMLHttpRequest();
      xhr.open('POST', '../api/delete-product.php', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onload = function() {
        if (xhr.status === 200) {
          const data = JSON.parse(xhr.responseText);
          if (data.success) {
            closeDeleteModal();
            window.location.reload();
          } else {
            alert('Error: ' + (data.error || 'Could not delete product'));
          }
        } else {
          alert('Network error occurred');
        }
      };
      xhr.onerror = function() {
        alert('Network error occurred');
      };
      xhr.send('product_id=' + deleteProductId);
    }

    // Close modal on overlay click
    document.getElementById('deleteModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeDeleteModal();
      }
    });

    // Product search and filter functionality
    const searchInput = document.getElementById('productSearch');
    const categoryFilter = document.getElementById('categoryFilter');
    const productTableBody = document.querySelector('.table tbody');
    const productRows = productTableBody ? Array.from(productTableBody.querySelectorAll('tr')) : [];

    function filterProducts() {
      const searchTerm = (searchInput ? searchInput.value : '').toLowerCase().trim();
      const selectedCategory = categoryFilter ? categoryFilter.value : '';

      let visibleCount = 0;
      productRows.forEach(function(row) {
        const nameCell = row.querySelector('td:nth-child(2)');
        const categoryCell = row.querySelector('td:nth-child(3)');
        const name = nameCell ? nameCell.textContent.toLowerCase() : '';
        const category = categoryCell ? categoryCell.textContent.trim() : '';

        const matchesSearch = name.includes(searchTerm);
        const matchesCategory = !selectedCategory || category === selectedCategory;

        if (matchesSearch && matchesCategory) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });

      const heading = document.querySelector('section:not(.card) h2');
      if (heading) {
        const total = productRows.length;
        if (visibleCount !== total) {
          heading.innerHTML = '<i class="fas fa-box"></i> All Products (' + visibleCount + '/' + total + ')';
        } else {
          heading.innerHTML = '<i class="fas fa-box"></i> All Products (' + total + ')';
        }
      }

      let noResultsMsg = document.getElementById('noFilterResults');
      if (visibleCount === 0 && productRows.length > 0) {
        if (!noResultsMsg) {
          noResultsMsg = document.createElement('div');
          noResultsMsg.id = 'noFilterResults';
          noResultsMsg.className = 'empty-state';
          noResultsMsg.innerHTML = '<i class="fas fa-search"></i><h3>No matching products</h3><p>Try adjusting your search or filter criteria.</p>';
          const wrapper = document.querySelector('.table-wrap');
          if (wrapper) {
            wrapper.style.display = 'none';
            wrapper.parentNode.insertBefore(noResultsMsg, wrapper.nextSibling);
          }
        }
        noResultsMsg.style.display = '';
      } else {
        if (noResultsMsg) {
          noResultsMsg.style.display = 'none';
        }
        const wrapper = document.querySelector('.table-wrap');
        if (wrapper) {
          wrapper.style.display = '';
        }
      }
    }

    if (searchInput) {
      searchInput.addEventListener('input', filterProducts);
      searchInput.addEventListener('search', filterProducts);
    }
    if (categoryFilter) {
      categoryFilter.addEventListener('change', filterProducts);
    }

    // Focus styles for filter inputs
    if (searchInput) {
      searchInput.addEventListener('focus', function() {
        this.style.borderColor = '#2B4C52';
        this.style.boxShadow = '0 0 0 4px rgba(43, 76, 82, 0.08)';
      });
      searchInput.addEventListener('blur', function() {
        this.style.borderColor = '#d1d5db';
        this.style.boxShadow = 'none';
      });
    }
    if (categoryFilter) {
      categoryFilter.addEventListener('focus', function() {
        this.style.borderColor = '#2B4C52';
        this.style.boxShadow = '0 0 0 4px rgba(43, 76, 82, 0.08)';
      });
      categoryFilter.addEventListener('blur', function() {
        this.style.borderColor = '#d1d5db';
        this.style.boxShadow = 'none';
      });
    }
  </script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
