<?php
// admin_page.php - Admin Dashboard with analytics
session_start();
include __DIR__ . '/db_connect.php';

// -- Simple admin check --
if (!isset($_SESSION['admin_id'])) {
    header("Location: login_page.php");
    exit;
}

function h($s){ return htmlspecialchars($s, ENT_QUOTES); }

// --- Fetch dashboard metrics ---
// Total products
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

// Low stock products (<5)
$lowStock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock < 5")->fetchColumn();

// Total categories
$totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

// Active users (logged in recently, assume 'created_at' as proxy)
$activeUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 30 DAY")->fetchColumn();

// Total sales & revenue
$totalRevenue = $pdo->query("SELECT IFNULL(SUM(total_amount),0) FROM orders WHERE status IN ('paid','shipped','completed')")->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('paid','shipped','completed')")->fetchColumn();

// Top-selling products
$topProductsStmt = $pdo->query("
    SELECT p.name, SUM(oi.quantity) AS total_sold 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    GROUP BY oi.product_id 
    ORDER BY total_sold DESC 
    LIMIT 5
");
$topProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.card-summary { text-align:center; padding:20px; border-radius:10px; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
.card-summary h5 { margin-bottom:10px; font-weight:bold; }
.card-summary p { font-size:1.5rem; margin:0; }
</style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Admin Dashboard</h1>
        <div>
            <a href="admin_orders.php" class="btn btn-primary">Manage Orders</a>

            <a href="brand_manage.php?action=list" class="btn btn-primary">Manage Brand</a>
            <a href="products_page.php?action=list" class="btn btn-primary">Manage Products</a>
            <a href="logout_page.php" class="btn btn-danger">Logout</a>
        </div>
    </div>

    <!-- Dashboard summary cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card-summary bg-light">
                <h5>Products</h5>
                <p><?php echo $totalProducts; ?></p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card-summary bg-warning text-white">
                <h5>Low Stock</h5>
                <p><?php echo $lowStock; ?></p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card-summary bg-info text-white">
                <h5>Categories</h5>
                <p><?php echo $totalCategories; ?></p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card-summary bg-success text-white">
                <h5>Total Orders</h5>
                <p><?php echo $totalOrders; ?></p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card-summary bg-primary text-white">
                <h5>Total Revenue</h5>
                <p>$<?php echo number_format($totalRevenue,2); ?></p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card-summary bg-secondary text-white">
                <h5>Active Users</h5>
                <p><?php echo $activeUsers; ?></p>
            </div>
        </div>
    </div>

    <!-- Top-selling products -->
    <div class="card mb-4">
        <div class="card-header"><strong>Top-Selling Products</strong></div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Units Sold</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($topProducts as $tp): ?>
                    <tr>
                        <td><?php echo h($tp['name']); ?></td>
                        <td><?php echo (int)$tp['total_sold']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($topProducts)) echo '<tr><td colspan="2">No sales yet.</td></tr>'; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Low-stock product list -->
    <div class="card mb-4">
        <div class="card-header"><strong>Low-Stock Products (&lt;5 units)</strong></div>
        <div class="card-body">
            <ul class="list-group">
                <?php
                $lowStockProducts = $pdo->query("SELECT name, stock FROM products WHERE stock < 5 ORDER BY stock ASC")->fetchAll(PDO::FETCH_ASSOC);
                foreach($lowStockProducts as $p) {
                    echo '<li class="list-group-item d-flex justify-content-between">'.$p['name'].'<span class="badge bg-danger">'.$p['stock'].'</span></li>';
                }
                if(empty($lowStockProducts)) echo '<li class="list-group-item">No low-stock products.</li>';
                ?>
            </ul>
        </div>
    </div>

    <!-- Analytics Placeholder -->
    <div class="card mb-4">
        <div class="card-header"><strong>Analytics</strong></div>
        <div class="card-body">
            <p>Here you can integrate charts for sales trends, revenue over time, and other analytics.</p>
            <p>Example: Use Chart.js or Google Charts to visualize monthly sales.</p>
        </div>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
