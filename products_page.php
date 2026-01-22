<?php
session_start();
include __DIR__ . '/db_connect.php';

// require_once '../db_connect.php';

// Admin authentication check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login_page.php");
    exit;
}

// Initialize variables
$error = '';
$success = '';
$action = $_GET['action'] ?? 'list';

// Helper function
function h($s){ return htmlspecialchars($s, ENT_QUOTES); }

// ---------- Handle POST actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Debug
    error_log("=== POST REQUEST START ===");
    error_log("POST Data: " . print_r($_POST, true));
    error_log("FILES Data: " . print_r($_FILES, true));

    // Add or edit product
    if (isset($_POST['save_product'])) {
        error_log("=== SAVE PRODUCT FORM SUBMITTED ===");
        
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $name = trim($_POST['name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $discount = (float)($_POST['discount'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $stock_status = $_POST['stock_status'] ?? 'in_stock';
        $description = trim($_POST['description'] ?? '');
        $attributes = trim($_POST['attributes'] ?? '{}');
        
        // Debug log all form data
        error_log("Form Data Received:");
        error_log("ID: $id");
        error_log("Name: $name");
        error_log("Category ID: $category_id");
        error_log("Price: $price");
        error_log("Discount: $discount");
        error_log("Stock: $stock");
        error_log("Stock Status: $stock_status");

        if ($attributes === '') {
            $attributes = '{}';
        }

        if (empty($name)) {
            $error = '❌ Product name is required.';
            error_log("Validation Error: Product name is empty");
        } else {
            try {
                if ($id && $id > 0) {
                    // UPDATE existing product
                    error_log("=== UPDATING EXISTING PRODUCT (ID: $id) ===");
                    
                    // First, let's check if the product exists
                    $checkStmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
                    $checkStmt->execute([$id]);
                    $exists = $checkStmt->fetch();
                    
                    if (!$exists) {
                        $error = "❌ Product ID $id not found in database.";
                        error_log("Error: Product with ID $id does not exist");
                    } else {
                        // Prepare SQL statement
                        $sql = "UPDATE products SET 
                                name = ?, 
                                category_id = ?, 
                                price = ?, 
                                discount_price = ?, 
                                stock = ?, 
                                stock_status = ?, 
                                description = ?, 
                                attributes = ?, 
                                updated_at = NOW() 
                                WHERE id = ?";
                        
                        error_log("SQL Query: $sql");
                        error_log("SQL Params: name=$name, category_id=$category_id, price=$price, discount=$discount, stock=$stock, status=$stock_status, id=$id");
                        
                        $stmt = $pdo->prepare($sql);
                        $result = $stmt->execute([$name, $category_id, $price, $discount, $stock, $stock_status, $description, $attributes, $id]);
                        
                        if ($result) {
                            $rowsAffected = $stmt->rowCount();
                            error_log("✅ UPDATE SUCCESSFUL! Rows affected: $rowsAffected");
                            
                            // Verify the update by fetching the updated data
                            $verifyStmt = $pdo->prepare("SELECT name, price, discount_price, stock FROM products WHERE id = ?");
                            $verifyStmt->execute([$id]);
                            $updatedData = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                            error_log("✅ VERIFICATION - Database now has: " . print_r($updatedData, true));
                            
                            // Store success in session and redirect
                            $_SESSION['success_message'] = '✅ Product updated successfully!';
                            header("Location: products_page.php?action=edit&id=" . $id);
                            exit();
                        } else {
                            $error = '❌ Failed to update product. No rows affected.';
                            error_log("❌ UPDATE FAILED: No rows affected");
                            $errorInfo = $stmt->errorInfo();
                            error_log("PDO Error Info: " . print_r($errorInfo, true));
                        }
                    }
                } else {
                    // INSERT new product
                    error_log("=== INSERTING NEW PRODUCT ===");
                    $stmt = $pdo->prepare("INSERT INTO products (name, category_id, price, discount_price, stock, stock_status, description, attributes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $result = $stmt->execute([$name, $category_id, $price, $discount, $stock, $stock_status, $description, $attributes]);
                    
                    if ($result) {
                        $product_id = $pdo->lastInsertId();
                        error_log("✅ INSERT SUCCESSFUL! New product ID: $product_id");
                        $_SESSION['success_message'] = '✅ Product created successfully!';
                        header("Location: products_page.php?action=edit&id=" . $product_id);
                        exit();
                    } else {
                        $error = '❌ Failed to create product.';
                        error_log("❌ INSERT FAILED");
                    }
                }

                // Handle image uploads if product was saved successfully (NO error)
                if (empty($error) && isset($product_id) && !empty($_FILES['images']['name'][0])) {
                    error_log("Processing image uploads...");
                    
                    $upload_dir = __DIR__ . '/uploads/images/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                        error_log("Created upload directory: $upload_dir");
                    }
                    
                    // Check if product already has a main image
                    $has_main = false;
                    if (isset($id) && $id > 0) {
                        $check_main = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id=? AND is_main=1");
                        $check_main->execute([$id]);
                        $has_main = $check_main->fetchColumn() > 0;
                    }
                    
                    $uploaded_count = 0;
                    for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                            error_log("File upload error for index $i: " . $_FILES['images']['error'][$i]);
                            continue;
                        }
                        
                        $tmp = $_FILES['images']['tmp_name'][$i];
                        $orig = basename($_FILES['images']['name'][$i]);
                        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                        
                        if (!in_array($ext, $allowed)) {
                            error_log("Invalid file extension: $ext");
                            continue;
                        }
                        
                        $fn = uniqid('img_') . '.' . $ext;
                        $dest = $upload_dir . $fn;
                        
                        if (move_uploaded_file($tmp, $dest)) {
                            $is_main = ($uploaded_count === 0 && !$has_main) ? 1 : 0;
                            $imgStmt = $pdo->prepare("INSERT INTO product_images (product_id, filename, is_main, created_at) VALUES (?, ?, ?, NOW())");
                            $imgStmt->execute([isset($id) ? $id : $product_id, $fn, $is_main]);
                            $uploaded_count++;
                            error_log("Image uploaded: $fn (main: $is_main)");
                        } else {
                            error_log("Failed to move uploaded file: $tmp to $dest");
                        }
                    }
                    
                    if ($uploaded_count > 0) {
                        if (isset($_SESSION['success_message'])) {
                            $_SESSION['success_message'] .= " 📸 $uploaded_count image(s) uploaded.";
                        }
                    }
                }

                // Handle video upload
                if (empty($error) && isset($product_id) && !empty($_FILES['video']['name'])) {
                    $tmp = $_FILES['video']['tmp_name'];
                    if ($_FILES['video']['error'] === UPLOAD_ERR_OK) {
                        $orig = basename($_FILES['video']['name']);
                        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                        $allowed = ['mp4', 'webm', 'ogg'];
                        
                        if (in_array($ext, $allowed)) {
                            $video_dir = __DIR__ . '/uploads/videos/';
                            if (!file_exists($video_dir)) {
                                mkdir($video_dir, 0777, true);
                            }
                            
                            $fn = uniqid('vid_') . '.' . $ext;
                            $dest = $video_dir . $fn;
                            
                            if (move_uploaded_file($tmp, $dest)) {
                                $vidStmt = $pdo->prepare("UPDATE products SET video=? WHERE id=?");
                                $vidStmt->execute([$fn, isset($id) ? $id : $product_id]);
                                if (isset($_SESSION['success_message'])) {
                                    $_SESSION['success_message'] .= " 🎬 Video uploaded.";
                                }
                            }
                        }
                    }
                }
                
            } catch (PDOException $e) {
                $error = '❌ Database error: ' . $e->getMessage();
                error_log("❌ PDO EXCEPTION: " . $e->getMessage());
                error_log("SQL Error Code: " . $e->getCode());
                error_log("Error in file: " . $e->getFile() . " on line " . $e->getLine());
            }
        }
    }

    // Delete product
    if (isset($_POST['delete_product']) && !empty($_POST['product_id'])) {
        $pid = (int)$_POST['product_id'];
        try {
            // Delete images
            $imgs = $pdo->prepare("SELECT filename FROM product_images WHERE product_id=?");
            $imgs->execute([$pid]);
            foreach($imgs->fetchAll(PDO::FETCH_COLUMN) as $f) {
                $file_path = __DIR__.'/uploads/images/'.$f;
                if (file_exists($file_path)) @unlink($file_path);
            }
            $pdo->prepare("DELETE FROM product_images WHERE product_id=?")->execute([$pid]);

            // Delete video
            $vid = $pdo->prepare("SELECT video FROM products WHERE id=?");
            $vid->execute([$pid]);
            $v = $vid->fetchColumn();
            if ($v) {
                $video_path = __DIR__.'/uploads/videos/'.$v;
                if (file_exists($video_path)) @unlink($video_path);
            }

            // Delete product
            $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$pid]);
            $_SESSION['success_message'] = '✅ Product deleted successfully.';
            header("Location: products_page.php?action=list");
            exit();
            
        } catch (PDOException $e) {
            $error = '❌ Error deleting product: ' . $e->getMessage();
        }
    }

    // Delete image
    if (isset($_POST['delete_image']) && !empty($_POST['image_id'])) {
        $image_id = (int)$_POST['image_id'];
        $product_id = (int)$_POST['product_id'];
        
        try {
            // Check if this is the main image
            $check_main = $pdo->prepare("SELECT is_main FROM product_images WHERE id=?");
            $check_main->execute([$image_id]);
            $is_main = $check_main->fetchColumn();
            
            // Get filename before deleting
            $stmt = $pdo->prepare("SELECT filename FROM product_images WHERE id=?");
            $stmt->execute([$image_id]);
            $filename = $stmt->fetchColumn();
            
            if ($filename) {
                // Delete file from server
                $file_path = __DIR__.'/uploads/images/'.$filename;
                if (file_exists($file_path)) @unlink($file_path);
                
                // Delete from database
                $pdo->prepare("DELETE FROM product_images WHERE id=?")->execute([$image_id]);
                
                // If we deleted the main image, set another image as main
                if ($is_main) {
                    $new_main = $pdo->prepare("SELECT id FROM product_images WHERE product_id=? ORDER BY id ASC LIMIT 1");
                    $new_main->execute([$product_id]);
                    $new_main_id = $new_main->fetchColumn();
                    if ($new_main_id) {
                        $pdo->prepare("UPDATE product_images SET is_main=1 WHERE id=?")->execute([$new_main_id]);
                    }
                }
                
                $_SESSION['success_message'] = '✅ Image deleted successfully.';
            } else {
                $error = '❌ Image not found.';
            }
        } catch (PDOException $e) {
            $error = '❌ Error deleting image: ' . $e->getMessage();
        }
        
        header("Location: products_page.php?action=edit&id=".$product_id);
        exit();
    }

    // Set main image
    if (isset($_POST['set_main_image']) && !empty($_POST['image_id'])) {
        $image_id = (int)$_POST['image_id'];
        $product_id = (int)$_POST['product_id'];
        
        try {
            // First, set all images for this product as not main
            $pdo->prepare("UPDATE product_images SET is_main=0 WHERE product_id=?")->execute([$product_id]);
            // Then set the selected image as main
            $pdo->prepare("UPDATE product_images SET is_main=1 WHERE id=?")->execute([$image_id]);
            $_SESSION['success_message'] = '✅ Main image updated successfully.';
        } catch (PDOException $e) {
            $error = '❌ Error setting main image: ' . $e->getMessage();
        }
        
        header("Location: products_page.php?action=edit&id=".$product_id);
        exit();
    }

    // Delete video
    if (isset($_POST['delete_video']) && !empty($_POST['product_id'])) {
        $product_id = (int)$_POST['product_id'];
        
        try {
            // Get current video filename
            $stmt = $pdo->prepare("SELECT video FROM products WHERE id=?");
            $stmt->execute([$product_id]);
            $video_filename = $stmt->fetchColumn();
            
            if ($video_filename) {
                // Delete file from server
                $video_path = __DIR__.'/uploads/videos/'.$video_filename;
                if (file_exists($video_path)) @unlink($video_path);
                
                // Remove video reference from database
                $pdo->prepare("UPDATE products SET video=NULL WHERE id=?")->execute([$product_id]);
                $_SESSION['success_message'] = '✅ Video deleted successfully.';
            } else {
                $error = '❌ No video found to delete.';
            }
        } catch (PDOException $e) {
            $error = '❌ Error deleting video: ' . $e->getMessage();
        }
        
        header("Location: products_page.php?action=edit&id=".$product_id);
        exit();
    }

    // Add/edit category
    if (isset($_POST['save_category'])) {
        $cid = !empty($_POST['cid']) ? (int)$_POST['cid'] : null;
        $cname = trim($_POST['cname'] ?? '');
        if ($cname === '') {
            $error = '❌ Category name required.';
        } else {
            try {
                if ($cid) {
                    $pdo->prepare("UPDATE categories SET name=? WHERE id=?")->execute([$cname, $cid]);
                    $success = '✅ Category updated.';
                } else {
                    $pdo->prepare("INSERT INTO categories (name) VALUES (?)")->execute([$cname]);
                    $success = '✅ Category added.';
                }
            } catch (PDOException $e) {
                $error = '❌ Error saving category: ' . $e->getMessage();
            }
        }
    }

    // Delete category
    if (isset($_POST['delete_category']) && !empty($_POST['cid'])) {
        $cid = (int)$_POST['cid'];
        try {
            $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$cid]);
            $success = '✅ Category deleted.';
        } catch (PDOException $e) {
            $error = '❌ Error deleting category: ' . $e->getMessage();
        }
    }

    // Bulk CSV upload
    if (isset($_POST['bulk_upload']) && !empty($_FILES['csv']['tmp_name'])) {
        $tmp = $_FILES['csv']['tmp_name'];
        if (($handle = fopen($tmp, 'r')) !== false) {
            $row = 0; $imported = 0;
            try {
                while (($data = fgetcsv($handle)) !== false) {
                    $row++;
                    if ($row == 1 && stripos(implode(',', $data), 'sku') !== false) continue;
                    
                    $sku = $data[0] ?? '';
                    $name = $data[1] ?? '';
                    $category = $data[2] ?? '';
                    $price = isset($data[3]) ? (float)$data[3] : 0;
                    $stock = isset($data[4]) ? (int)$data[4] : 0;
                    $description = $data[5] ?? '';
                    $attributes = $data[6] ?? '{}';

                    // Ensure category exists
                    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name=? LIMIT 1");
                    $stmt->execute([$category]);
                    $catId = $stmt->fetchColumn();
                    if (!$catId) {
                        $pdo->prepare("INSERT INTO categories (name) VALUES (?)")->execute([$category]);
                        $catId = $pdo->lastInsertId();
                    }

                    // Insert product
                    $stmt = $pdo->prepare("INSERT INTO products (sku, name, category_id, price, stock, description, attributes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$sku, $name, $catId, $price, $stock, $description, $attributes]);
                    $imported++;
                }
                fclose($handle);
                $success = "✅ Bulk upload completed. Imported: $imported products";
            } catch (PDOException $e) {
                $error = '❌ Error during bulk upload: ' . $e->getMessage();
            }
        } else {
            $error = '❌ Failed to read CSV.';
        }
    }
    
    error_log("=== POST REQUEST END ===");
}

// Check for session success message (AFTER POST handling)
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
    error_log("Success message from session: $success");
}

// Check for success message in URL
if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
    error_log("Success message from URL: $success");
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Products Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.thumb{ max-width:120px; max-height:90px; object-fit:cover; }
.card{ margin-bottom:1rem;}
.image-container {
    position: relative;
    display: inline-block;
    margin: 5px;
    border: 2px solid #ddd;
    border-radius: 4px;
    padding: 2px;
    background: white;
}
.image-container img {
    display: block;
    width: 120px;
    height: 90px;
    object-fit: cover;
}
.delete-btn {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.main-badge {
    position: absolute;
    top: -8px;
    left: -8px;
    background: #28a745;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: bold;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.set-main-btn {
    position: absolute;
    bottom: 5px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(23, 162, 184, 0.95);
    color: white;
    border: none;
    border-radius: 3px;
    padding: 3px 8px;
    font-size: 10px;
    cursor: pointer;
    white-space: nowrap;
}
.set-main-btn:hover {
    background: #138496;
}
.delete-btn:hover {
    background: #c82333;
}
.image-container.main-image {
    border-color: #28a745;
    border-width: 3px;
}

/* Improved button spacing */
.btn-group-custom {
    display: flex;
    gap: 10px; /* Increased gap */
    flex-wrap: wrap;
}
.btn-group-custom .btn {
    padding: 6px 15px;
    font-size: 14px;
    min-width: 80px;
}

/* Success/error message styling */
.alert-success {
    border-left: 4px solid #28a745;
    animation: slideIn 0.3s ease-out;
}
.alert-danger {
    border-left: 4px solid #dc3545;
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Debug panel */
.debug-toggle {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
}
.debug-panel {
    position: fixed;
    bottom: 60px;
    right: 20px;
    width: 400px;
    height: 300px;
    background: rgba(0,0,0,0.9);
    color: #0f0;
    font-family: monospace;
    font-size: 12px;
    padding: 10px;
    border-radius: 5px;
    overflow: auto;
    display: none;
    z-index: 1000;
}

/* Form feedback */
.form-control:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

/* Status badges */
.badge-in_stock { background-color: #28a745; }
.badge-out_of_stock { background-color: #dc3545; }
.badge-preorder { background-color: #ffc107; color: #000; }
</style>
</head>
<body>
<div class="container py-4">
    <!-- Debug toggle button -->
    <button class="btn btn-sm btn-warning debug-toggle" onclick="toggleDebug()">🐛 Debug</button>
    <div id="debugPanel" class="debug-panel"></div>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>📦 Products Management</h1>
        <div class="btn-group-custom">
            <a href="products_page.php?action=list" class="btn btn-primary">📋 Manage Products</a>
            <a href="admin_page.php" class="btn btn-secondary">← Dashboard</a>
        </div>
    </div>

    <div class="mb-4">
        <div class="btn-group-custom">
            
            <a href="?action=add" class="btn btn-primary">➕ Add Product</a>
            <a href="?action=categories" class="btn btn-primary">📂 Categories</a>
            <a href="?action=bulk" class="btn btn-primary">📤 Bulk Upload</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>❌ Error:</strong> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>✅ Success:</strong> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

<?php if ($action === 'list'): ?>
    <?php
    try {
        $stmt = $pdo->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Loaded " . count($products) . " products from database");
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">❌ Error loading products: ' . h($e->getMessage()) . '</div>';
        error_log("Error loading products: " . $e->getMessage());
        $products = [];
    }
    ?>
    
    <?php if (empty($products)): ?>
        <div class="alert alert-info">
            <strong>📭 No products found.</strong> 
            <a href="?action=add" class="alert-link">Add your first product</a>
        </div>
    <?php else: ?>
        <div class="row">
        <?php foreach($products as $p): ?>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex">
                            <?php
                            try {
                                $imgStmt = $pdo->prepare("SELECT filename FROM product_images WHERE product_id=? AND is_main=1 LIMIT 1");
                                $imgStmt->execute([$p['id']]);
                                $main_img = $imgStmt->fetchColumn();
                                if (!$main_img) {
                                    $imgStmt = $pdo->prepare("SELECT filename FROM product_images WHERE product_id=? LIMIT 1");
                                    $imgStmt->execute([$p['id']]);
                                    $main_img = $imgStmt->fetchColumn();
                                }
                            } catch (PDOException $e) {
                                $main_img = null;
                            }
                            ?>

                            <img src="uploads/images/<?php echo $main_img ? h($main_img) : 'placeholder.png'; ?>" 
                                 class="thumb me-3" 
                                 alt="<?php echo h($p['name']); ?>"
                                 onerror="this.src='https://via.placeholder.com/120x90?text=No+Image'">
                            
                            <div class="flex-grow-1">
                                <h5 class="card-title"><?php echo h($p['name']); ?></h5>
                                <p class="card-text small text-muted mb-1">
                                    <strong>Category:</strong> <?php echo h($p['category_name'] ?? 'Uncategorized'); ?>
                                </p>
                                <p class="card-text mb-1">
                                    <strong>Price:</strong> 
                                    <?php if ($p['discount_price'] > 0): ?>
                                        <span class="text-danger"><del>$<?php echo number_format($p['price'], 2); ?></del></span>
                                        <span class="text-success"> $<?php echo number_format($p['discount_price'], 2); ?></span>
                                    <?php else: ?>
                                        <span>$<?php echo number_format($p['price'], 2); ?></span>
                                    <?php endif; ?>
                                </p>
                                <p class="card-text small mb-2">
                                    <strong>Stock:</strong> <?php echo (int)$p['stock']; ?> |
                                    <strong>Status:</strong> 
                                    <span class="badge badge-<?php echo $p['stock_status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $p['stock_status'])); ?>
                                    </span>
                                </p>
                                <div class="btn-group-custom mt-3">
                                    <a href="?action=edit&id=<?php echo $p['id']; ?>" class="btn btn-primary">
                                        ✏️ Edit
                                    </a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ Are you sure you want to delete this product?\n\nThis will delete all images and videos.\n\nThis action cannot be undone!');">
                                        <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                        <button class="btn btn-danger" name="delete_product">
                                            🗑️ Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-muted small">
                        ID: <?php echo $p['id']; ?> | 
                        Updated: <?php echo !empty($p['updated_at']) ? date('Y-m-d', strtotime($p['updated_at'])) : date('Y-m-d', strtotime($p['created_at'])); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        
        <div class="mt-4 text-center">
            <div class="alert alert-light">
                <strong>📊 Total Products:</strong> <?php echo count($products); ?>
                <?php 
                $in_stock = array_filter($products, function($p) { return $p['stock_status'] == 'in_stock'; });
                $out_of_stock = array_filter($products, function($p) { return $p['stock_status'] == 'out_of_stock'; });
                ?>
                | <span class="text-success">In Stock: <?php echo count($in_stock); ?></span>
                | <span class="text-danger">Out of Stock: <?php echo count($out_of_stock); ?></span>
            </div>
        </div>
    <?php endif; ?>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <?php
    $editing = ($action === 'edit');
    $product = [
        'id' => '', 
        'name' => '', 
        'category_id' => 0, 
        'price' => 0, 
        'discount_price' => 0, 
        'stock' => 0, 
        'stock_status' => 'in_stock', 
        'description' => '', 
        'attributes' => '{}', 
        'video' => null
    ];
    
    // Fetch product data from database (this ensures fresh data after update)
    if ($editing) {
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id=? LIMIT 1");
                $stmt->execute([$id]);
                $prod = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($prod) {
                    $product = $prod;
                    error_log("🔄 LOADED product data from DB for ID: $id");
                    error_log("Current product data: Name='{$product['name']}', Price={$product['price']}, Discount={$product['discount_price']}, Stock={$product['stock']}");
                } else {
                    echo '<div class="alert alert-danger">❌ Product not found. <a href="?action=list">Back to list</a></div>';
                    exit;
                }
            } catch (PDOException $e) {
                echo '<div class="alert alert-danger">❌ Error loading product: ' . h($e->getMessage()) . '</div>';
                error_log("Error loading product: " . $e->getMessage());
            }
        }
    }
    
    try {
        $cats = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo '<div class="alert alert-warning">⚠️ Error loading categories: ' . h($e->getMessage()) . '</div>';
        $cats = [];
    }
    
    $images = [];
    if ($editing && !empty($product['id'])) {
        try {
            $imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id=? ORDER BY is_main DESC, id ASC");
            $imgStmt->execute([$product['id']]);
            $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("📸 Found " . count($images) . " images for product ID: " . $product['id']);
        } catch (PDOException $e) {
            echo '<div class="alert alert-warning">⚠️ Error loading images: ' . h($e->getMessage()) . '</div>';
        }
    }
    ?>
    
    <form method="POST" enctype="multipart/form-data" id="productForm" onsubmit="return validateForm()">
        <input type="hidden" name="id" value="<?php echo h($product['id']); ?>">

        <a href="?action=list" class="btn btn-secondary">← Back to List</a>
        <br><br>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <!-- <a href="?action=list" class="btn btn-secondary">← Back to List</a> -->
                <h5 class="mb-0"><?php echo $editing ? '✏️ Edit Product (ID: ' . $product['id'] . ')' : '➕ Add New Product'; ?></h5>
                <?php if ($editing): ?>
                    <span class="badge bg-light text-dark">Last Updated: <?php echo !empty($product['updated_at']) ? date('Y-m-d H:i', strtotime($product['updated_at'])) : 'Never'; ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Product Name *</label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?php echo h($product['name']); ?>" 
                                   placeholder="Enter product name" required id="productName">
                            <div class="form-text">Required field</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Category</label>
                            <select name="category_id" class="form-select" id="categorySelect">
                                <option value="0">-- Select Category --</option>
                                <?php foreach($cats as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" 
                                        <?php echo ($c['id'] == $product['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo h($c['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Price ($) *</label>
                                <input type="number" step="0.01" min="0" name="price" class="form-control" 
                                       value="<?php echo number_format($product['price'], 2, '.', ''); ?>" required id="productPrice">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Discount Price ($)</label>
                                <input type="number" step="0.01" min="0" name="discount" class="form-control" 
                                       value="<?php echo number_format($product['discount_price'], 2, '.', ''); ?>"
                                       placeholder="0.00" id="productDiscount">
                                <div class="form-text">Leave empty or 0 for no discount</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Stock Quantity *</label>
                                <input type="number" min="0" name="stock" class="form-control" 
                                       value="<?php echo h($product['stock']); ?>" required id="productStock">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Stock Status</label>
                                <select name="stock_status" class="form-select" id="stockStatus">
                                    <option value="in_stock" <?php echo ($product['stock_status']=='in_stock') ? 'selected' : ''; ?>>✅ In stock</option>
                                    <option value="out_of_stock" <?php echo ($product['stock_status']=='out_of_stock') ? 'selected' : ''; ?>>❌ Out of stock</option>
                                    <option value="preorder" <?php echo ($product['stock_status']=='preorder') ? 'selected' : ''; ?>>📅 Pre-order</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Description</label>
                            <textarea name="description" class="form-control" rows="4" 
                                      placeholder="Enter product description" id="productDescription"><?php echo h($product['description']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Attributes (JSON)</label>
                            <textarea name="attributes" class="form-control" rows="2" 
                                      placeholder='{"color":["red","blue"],"size":["S","M","L"]}' id="productAttributes"><?php echo h($product['attributes']); ?></textarea>
                            <div class="form-text">Enter valid JSON format for product attributes (optional)</div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Product Images</label>
                            <input type="file" name="images[]" multiple class="form-control" accept="image/*" id="productImages">
                            <div class="form-text small">
                                Upload multiple images (JPG, PNG, GIF, WebP)<br>
                                Max 5MB per image
                            </div>
                            
                            <?php if(!empty($images)): ?>
                                <div class="mt-3">
                                    <h6>Current Images (<?php echo count($images); ?>):</h6>
                                    <div class="d-flex flex-wrap">
                                        <?php foreach($images as $im): ?>
                                            <div class="image-container <?php echo $im['is_main'] ? 'main-image' : ''; ?>">
                                                <img src="uploads/images/<?php echo h($im['filename']); ?>" 
                                                     alt="Product image"
                                                     onerror="this.src='https://via.placeholder.com/120x90?text=Image+Error'">
                                                <?php if($im['is_main']): ?>
                                                    <span class="main-badge">MAIN</span>
                                                <?php else: ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="image_id" value="<?php echo $im['id']; ?>">
                                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                        <button type="submit" name="set_main_image" class="set-main-btn" 
                                                                onclick="return confirm('Set this as the main product image?')">
                                                            Set as Main
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="image_id" value="<?php echo $im['id']; ?>">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                    <button type="submit" name="delete_image" class="delete-btn" 
                                                            onclick="return confirm('Delete this image?')">
                                                        ×
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="mt-2 text-center">
                                    <p class="text-muted small">No images uploaded yet</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Product Video (optional)</label>
                            <input type="file" name="video" accept="video/*" class="form-control" id="productVideo">
                            <div class="form-text small">Max 50MB. Supported: MP4, WebM, OGG</div>
                            
                            <?php if(!empty($product['video'])): ?>
                                <div class="mt-2">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="badge bg-info me-2">Current Video</span>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" name="delete_video" class="btn btn-danger btn-sm" 
                                                    onclick="return confirm('Delete this video?')">
                                                Remove
                                            </button>
                                        </form>
                                    </div>
                                    <video width="100%" controls>
                                        <source src="uploads/videos/<?php echo h($product['video']); ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($editing): ?>
                        <div class="alert alert-info small">
                            <strong>🔄 Form Status:</strong> 
                            <span id="formStatus">Ready to update</span><br>
                            <strong>📝 Form Values:</strong><br>
                            <div id="formValues" class="small"></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="d-flex gap-2 mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary px-4" name="save_product" id="submitBtn">
                        <?php echo $editing ? '💾 Update Product' : '➕ Create Product'; ?>
                    </button>
                    <!-- <a href="?action=list" class="btn btn-secondary">← Back to List</a> -->
                    <?php if ($editing): ?>
                        <a href="?action=add" class="btn btn-success">➕ Add New</a>
                        <button type="button" class="btn btn-warning" onclick="location.reload()">🔄 Refresh</button>
                        <!-- <button type="button" class="btn btn-info" onclick="showFormData()">📊 Show Data</button> -->
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>

<?php elseif ($action === 'categories'): ?>
    <!-- Categories section (same as before) -->
<?php elseif ($action === 'bulk'): ?>
    <!-- Bulk upload section (same as before) -->
<?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Debug panel toggle
function toggleDebug() {
    const panel = document.getElementById('debugPanel');
    panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
    if (panel.style.display === 'block') {
        updateDebugInfo();
    }
}

// Update debug info
function updateDebugInfo() {
    const panel = document.getElementById('debugPanel');
    const form = document.getElementById('productForm');
    let debugInfo = '';
    
    if (form) {
        debugInfo += '=== FORM DATA ===\n';
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            debugInfo += `${input.name}: ${input.value}\n`;
        });
    }
    
    debugInfo += '\n=== PAGE INFO ===\n';
    debugInfo += `URL: ${window.location.href}\n`;
    debugInfo += `Action: <?php echo $action; ?>\n`;
    debugInfo += `Editing: <?php echo $editing ? 'Yes' : 'No'; ?>\n`;
    debugInfo += `Product ID: <?php echo $product['id']; ?>\n`;
    
    panel.innerHTML = '<pre>' + debugInfo + '</pre>';
}

// Form validation
function validateForm() {
    const name = document.getElementById('productName').value.trim();
    const price = document.getElementById('productPrice').value;
    const stock = document.getElementById('productStock').value;
    const submitBtn = document.getElementById('submitBtn');
    
    // Update form status
    document.getElementById('formStatus').textContent = 'Validating...';
    
    if (!name) {
        alert('❌ Product name is required.');
        document.getElementById('productName').focus();
        document.getElementById('formStatus').textContent = 'Error: Name required';
        return false;
    }
    
    if (!price || parseFloat(price) < 0) {
        alert('❌ Please enter a valid price (0 or higher).');
        document.getElementById('productPrice').focus();
        document.getElementById('formStatus').textContent = 'Error: Invalid price';
        return false;
    }
    
    if (!stock || parseInt(stock) < 0) {
        alert('❌ Please enter a valid stock quantity (0 or higher).');
        document.getElementById('productStock').focus();
        document.getElementById('formStatus').textContent = 'Error: Invalid stock';
        return false;
    }
    
    // Show loading state
    if (submitBtn) {
        submitBtn.innerHTML = '⏳ Processing...';
        submitBtn.disabled = true;
        document.getElementById('formStatus').textContent = 'Submitting form...';
        
        // Re-enable button after 10 seconds just in case
        setTimeout(() => {
            if (submitBtn.disabled) {
                submitBtn.innerHTML = '💾 Update Product';
                submitBtn.disabled = false;
                document.getElementById('formStatus').textContent = 'Error: Submission timeout';
                alert('⚠️ Form submission is taking longer than expected. Please check your connection.');
            }
        }, 10000);
    }
    
    document.getElementById('formStatus').textContent = 'Form validated, submitting...';
    return true;
}

// Show form data
function showFormData() {
    const form = document.getElementById('productForm');
    let data = '=== Current Form Data ===\n\n';
    
    const inputs = form.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        if (input.type !== 'file') {
            data += `${input.name}: ${input.value}\n`;
        }
    });
    
    alert(data);
}

// Update form values display
function updateFormValues() {
    const valuesDiv = document.getElementById('formValues');
    if (!valuesDiv) return;
    
    const name = document.getElementById('productName')?.value || '';
    const price = document.getElementById('productPrice')?.value || '';
    const discount = document.getElementById('productDiscount')?.value || '';
    const stock = document.getElementById('productStock')?.value || '';
    
    valuesDiv.innerHTML = `
        Name: ${name.substring(0, 20)}${name.length > 20 ? '...' : ''}<br>
        Price: $${price}<br>
        Discount: $${discount}<br>
        Stock: ${stock}
    `;
}

// Monitor form changes
document.addEventListener('DOMContentLoaded', function() {
    // Update form values on input change
    const form = document.getElementById('productForm');
    if (form) {
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('change', updateFormValues);
            input.addEventListener('keyup', updateFormValues);
        });
        
        // Initial update
        updateFormValues();
    }
    
    // Auto-dismiss alerts
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // File size validation
    const imageInput = document.getElementById('productImages');
    if (imageInput) {
        imageInput.addEventListener('change', function() {
            const maxSize = 5 * 1024 * 1024;
            for (let file of this.files) {
                if (file.size > maxSize) {
                    alert(`❌ "${file.name}" is too large (${(file.size/1024/1024).toFixed(1)}MB). Max 5MB.`);
                    this.value = '';
                    break;
                }
            }
        });
    }
    
    const videoInput = document.getElementById('productVideo');
    if (videoInput) {
        videoInput.addEventListener('change', function() {
            const maxSize = 50 * 1024 * 1024;
            if (this.files[0] && this.files[0].size > maxSize) {
                alert(`❌ Video file is too large (${(this.files[0].size/1024/1024).toFixed(1)}MB). Max 50MB.`);
                this.value = '';
            }
        });
    }
    
    // Log form submission
    if (form) {
        form.addEventListener('submit', function() {
            console.log('Form submitted at: ' + new Date().toLocaleString());
            console.log('Form data:', {
                name: document.getElementById('productName')?.value,
                price: document.getElementById('productPrice')?.value,
                discount: document.getElementById('productDiscount')?.value,
                stock: document.getElementById('productStock')?.value
            });
        });
    }
});
</script>
</body>
</html>