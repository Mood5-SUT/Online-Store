<?php
session_start();
include __DIR__ . '/db_connect.php';

/* Admin authentication */
if (!isset($_SESSION['admin_id'])) {
    header("Location: login_page.php");
    exit;
}

/* Fetch orders with users */
$sql = "
SELECT 
    o.id,
    o.total_amount,
    o.status,
    o.tracking_number,
    o.created_at,
    u.full_name,
    u.email,
    u.phone
FROM orders o
JOIN users u ON o.user_id = u.id
ORDER BY o.created_at DESC
";
$orders = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin | Orders</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family: Arial; background:#f4f6f8; }
.container { width:95%; margin:auto; }
h2 { margin:20px 0; }
table {
    width:100%;
    border-collapse:collapse;
    background:#fff;
}
th, td {
    padding:10px;
    border-bottom:1px solid #ddd;
    text-align:center;
}
th { background:#222; color:#fff; }
.status {
    padding:4px 10px;
    border-radius:6px;
    color:#fff;
    font-size:13px;
}
.pending { background:#f39c12; }
.paid { background:#3498db; }
.shipped { background:#8e44ad; }
.completed { background:#2ecc71; }
.cancelled { background:#e74c3c; }

.actions a {
    padding:6px 10px;
    text-decoration:none;
    color:#fff;
    border-radius:5px;
    font-size:13px;
    margin:2px;
    display:inline-block;
}
.view { background:#3498db; }
.invoice { background:#2ecc71; }
</style>
</head>

<body>

<div class="container" class="container py-4">
<br>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Orders Management</h1>
    <div>
        <a href="admin_orders.php" class="btn btn-primary">Manage Orders</a>

        <a href="brand_manage.php?action=list" class="btn btn-primary">Manage Brand</a>
        <a href="products_page.php?action=list" class="btn btn-primary">Manage Products</a>
        <a href="logout_page.php" class="btn btn-danger">Logout</a>
    </div>
</div>

<table>
<tr>
<th>#</th>
<th>Customer</th>
<th>Contact</th>
<th>Total</th>
<th>Status</th>
<th>Tracking</th>
<th>Date</th>
<th>Actions</th>
</tr>

<?php if ($orders->num_rows > 0): ?>
<?php while ($o = $orders->fetch_assoc()): ?>
<tr>
<td><?= $o['id'] ?></td>
<td><?= htmlspecialchars($o['full_name']) ?></td>
<td>
<?= htmlspecialchars($o['email']) ?><br>
<?= htmlspecialchars($o['phone']) ?>
</td>
<td>$<?= number_format($o['total_amount'],2) ?></td>
<td>
<span class="status <?= $o['status'] ?>">
<?= ucfirst($o['status']) ?>
</span>
</td>
<td><?= $o['tracking_number'] ?? '-' ?></td>
<td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
<td class="actions">
<a class="view" href="admin_order_view.php?id=<?= $o['id'] ?>">Manage</a>
<a class="invoice" href="invoice.php?id=<?= $o['id'] ?>" target="_blank">Invoice</a>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="8">No orders found</td></tr>
<?php endif; ?>

</table>
</div>

</body>
</html>