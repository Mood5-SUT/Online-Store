<?php
session_start();
include __DIR__ . '/db_connect.php';

// Check if admin is logged in - FIXED: Only check admin_id
if (!isset($_SESSION['admin_id'])) {
    header("Location: login_page.php");
    exit;
}

// Get order ID from URL
$order_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Validate order ID
if (empty($order_id) || !is_numeric($order_id) || $order_id <= 0) {
    die("Invalid order ID. Please check the URL. <a href='admin_orders.php'>Back to Orders</a>");
}

// Convert to integer
$order_id = (int)$order_id;

// Using PDO for consistency with other files
try {
    // Fetch order details
    $stmt = $pdo->prepare("
        SELECT o.*, u.full_name, u.email, u.phone
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        die("Order #$order_id not found. <a href='admin_orders.php'>Back to Orders</a>");
    }
    
    // Fetch order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name, p.sku, p.price as unit_price
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['update_status'])) {
            $status = $_POST['status'];
            $allowed_statuses = ['pending', 'paid', 'shipped', 'completed', 'cancelled'];
            
            if (in_array($status, $allowed_statuses)) {
                $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $stmt->execute([$status, $order_id]);
                
                // Set timestamps
                if ($status == 'shipped') {
                    $pdo->prepare("UPDATE orders SET shipped_at = NOW() WHERE id = ?")->execute([$order_id]);
                } elseif ($status == 'completed') {
                    $pdo->prepare("UPDATE orders SET completed_at = NOW() WHERE id = ?")->execute([$order_id]);
                }
                
                header("Location: admin_order_view.php?id=" . $order_id . "&updated=1");
                exit;
            }
        }
        
        if (isset($_POST['save_tracking'])) {
            $tracking = trim($_POST['tracking']);
            if (!empty($tracking)) {
                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET tracking_number = ?, status = 'shipped', shipped_at = NOW()
                    WHERE id = ? AND status IN ('pending','paid')
                ");
                $stmt->execute([$tracking, $order_id]);
                
                header("Location: admin_order_view.php?id=" . $order_id . "&tracking_updated=1");
                exit;
            }
        }
        
        if (isset($_POST['cancel'])) {
            $reason = trim($_POST['reason']);
            if (!empty($reason)) {
                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET status = 'cancelled', cancelled_reason = ?
                    WHERE id = ? AND status IN ('pending','paid')
                ");
                $stmt->execute([$reason, $order_id]);
                
                // Restore stock
                foreach ($items as $item) {
                    $restoreStmt = $pdo->prepare("
                        UPDATE products 
                        SET stock = stock + ? 
                        WHERE id = ?
                    ");
                    $restoreStmt->execute([$item['quantity'], $item['product_id']]);
                }
                
                header("Location: admin_order_view.php?id=" . $order_id . "&cancelled=1");
                exit;
            }
        }
        
        if (isset($_POST['add_note'])) {
            $note = trim($_POST['note']);
            if (!empty($note)) {
                $admin_id = $_SESSION['admin_id'];
                $stmt = $pdo->prepare("
                    INSERT INTO order_notes (order_id, admin_id, note)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$order_id, $admin_id, $note]);
                
                header("Location: admin_order_view.php?id=" . $order_id . "&note_added=1");
                exit;
            }
        }
        
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Fetch order notes if table exists
$notes = [];
try {
    // First check if table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'order_notes'")->rowCount() > 0;
    
    if ($tableExists) {
        $notesStmt = $pdo->prepare("
            SELECT onotes.*, au.username 
            FROM order_notes onotes
            LEFT JOIN admin_users au ON onotes.admin_id = au.id
            WHERE order_id = ? 
            ORDER BY created_at DESC
        ");
        $notesStmt->execute([$order_id]);
        $notes = $notesStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Table might not exist yet, ignore error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= $order_id ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }
        .status-pending { background: #ffc107; color: #000; }
        .status-paid { background: #17a2b8; color: #fff; }
        .status-shipped { background: #6f42c1; color: #fff; }
        .status-completed { background: #28a745; color: #fff; }
        .status-cancelled { background: #dc3545; color: #fff; }
        .card { margin-bottom: 20px; }
        .note-admin { font-size: 12px; color: #666; }
        .note-time { font-size: 11px; color: #999; }
        .btn-purple { background-color: #6f42c1; border-color: #6f42c1; color: white; }
        .btn-purple:hover { background-color: #5a32a3; border-color: #5a32a3; }
    </style>
</head>
<body>
    <div class="container-fluid py-3">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">📦 Order #<?= $order_id ?></h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin_page.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="admin_orders.php">Orders</a></li>
                        <li class="breadcrumb-item active">Order #<?= $order_id ?></li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="admin_orders.php" class="btn btn-outline-secondary">← Back to Orders</a>
                <a href="invoice.php?id=<?= $order_id ?>" target="_blank" class="btn btn-success">View Invoice</a>
            </div>
        </div>
        
        <!-- Success Messages -->
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                ✅ Order status updated successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['tracking_updated'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                ✅ Tracking number added and order marked as shipped!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['cancelled'])): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                ⚠️ Order cancelled and stock restored.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['note_added'])): ?>
            <div class="alert alert-info alert-dismissible fade show">
                📝 Note added successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                ❌ <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Left Column: Order Details -->
            <div class="col-lg-8">
                <!-- Customer Info Card -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Customer Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Name:</strong> <?= htmlspecialchars($order['full_name']) ?></p>
                                <p><strong>Email:</strong> <?= htmlspecialchars($order['email']) ?></p>
                                <p><strong>Phone:</strong> <?= htmlspecialchars($order['phone'] ?? 'N/A') ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Order Date:</strong> <?= date('F j, Y, g:i a', strtotime($order['created_at'])) ?></p>
                                <p><strong>Order Status:</strong> 
                                    <span class="status-badge status-<?= $order['status'] ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                </p>
                                <p><strong>Total Amount:</strong> <span class="h5 text-success">$<?= number_format($order['total_amount'], 2) ?></span></p>
                                <?php if ($order['tracking_number']): ?>
                                    <p><strong>Tracking Number:</strong> <code><?= htmlspecialchars($order['tracking_number']) ?></code></p>
                                <?php endif; ?>
                                <?php if ($order['cancelled_reason']): ?>
                                    <p><strong>Cancellation Reason:</strong> <span class="text-danger"><?= htmlspecialchars($order['cancelled_reason']) ?></span></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Order Items Card -->
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Order Items</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total = 0;
                                    foreach ($items as $item): 
                                        $subtotal = $item['price'] * $item['quantity'];
                                        $total += $subtotal;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['name']) ?></td>
                                        <td><small class="text-muted"><?= htmlspecialchars($item['sku'] ?? 'N/A') ?></small></td>
                                        <td class="text-center"><?= $item['quantity'] ?></td>
                                        <td class="text-end">$<?= number_format($item['price'], 2) ?></td>
                                        <td class="text-end">$<?= number_format($subtotal, 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Total:</strong></td>
                                        <td class="text-end"><strong>$<?= number_format($order['total_amount'], 2) ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Order Notes Card -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Order Notes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($notes)): ?>
                            <?php foreach ($notes as $note): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <p class="mb-1"><?= htmlspecialchars($note['note']) ?></p>
                                <div class="d-flex justify-content-between">
                                    <span class="note-admin">By: <?= htmlspecialchars($note['username'] ?? 'Admin') ?></span>
                                    <span class="note-time"><?= date('M j, Y g:i a', strtotime($note['created_at'])) ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted mb-0">No notes yet.</p>
                        <?php endif; ?>
                        
                        <form method="POST" class="mt-3">
                            <div class="input-group">
                                <input type="text" name="note" class="form-control" placeholder="Add a note about this order..." required>
                                <button type="submit" name="add_note" class="btn btn-info">Add Note</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Actions -->
            <div class="col-lg-4">
                <!-- Update Status Card -->
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0">Update Status</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Select Status:</label>
                                <select name="status" class="form-select" required>
                                    <option value="pending" <?= $order['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="paid" <?= $order['status'] == 'paid' ? 'selected' : '' ?>>Paid</option>
                                    <option value="shipped" <?= $order['status'] == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                    <option value="completed" <?= $order['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="cancelled" <?= $order['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            <button type="submit" name="update_status" class="btn btn-warning w-100">Update Status</button>
                        </form>
                    </div>
                </div>
                
                <?php if (in_array($order['status'], ['pending', 'paid'])): ?>
                <!-- Shipping Card -->
                <div class="card">
                    <div class="card-header" style="background-color: #6f42c1; color: white;">
                        <h5 class="mb-0">Shipping Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Tracking Number:</label>
                                <input type="text" name="tracking" class="form-control" placeholder="Enter tracking number" required>
                                <div class="form-text">Adding tracking will automatically mark as "shipped".</div>
                            </div>
                            <button type="submit" name="save_tracking" class="btn btn-purple w-100">Add Tracking & Ship</button>
                        </form>
                    </div>
                </div>
                
                <!-- Cancel Order Card -->
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">Cancel Order</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this order? This will restore product stock.')">
                            <div class="mb-3">
                                <label class="form-label">Reason for Cancellation:</label>
                                <textarea name="reason" class="form-control" rows="3" placeholder="Enter reason..." required></textarea>
                            </div>
                            <button type="submit" name="cancel" class="btn btn-danger w-100">Cancel Order</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Quick Actions Card -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="invoice.php?id=<?= $order_id ?>" target="_blank" class="btn btn-outline-success">
                                <i class="fas fa-file-invoice"></i> Generate Invoice
                            </a>
                            <a href="mailto:<?= htmlspecialchars($order['email']) ?>?subject=Order%20%23<?= $order_id ?>%20Update" class="btn btn-outline-primary">
                                <i class="fas fa-envelope"></i> Email Customer
                            </a>
                            <a href="admin_orders.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list"></i> Back to Orders List
                            </a>
                            <button class="btn btn-outline-info" onclick="window.print()">
                                <i class="fas fa-print"></i> Print Order Details
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Order Summary Card -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td>Order ID:</td>
                                <td class="text-end"><strong>#<?= $order_id ?></strong></td>
                            </tr>
                            <tr>
                                <td>Items:</td>
                                <td class="text-end"><?= count($items) ?></td>
                            </tr>
                            <tr>
                                <td>Status:</td>
                                <td class="text-end">
                                    <span class="badge bg-<?= $order['status'] == 'pending' ? 'warning' : ($order['status'] == 'paid' ? 'info' : ($order['status'] == 'shipped' ? 'primary' : ($order['status'] == 'completed' ? 'success' : 'danger'))) ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>Total:</td>
                                <td class="text-end h5 text-success">$<?= number_format($order['total_amount'], 2) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>