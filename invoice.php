<?php
session_start();
include __DIR__ . '/db_connect.php';

// Check if admin is logged in OR if user is viewing their own order
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    die("Invalid order ID");
}

// Get order details
try {
    // Check if users table has address column
    $has_address_column = false;
    try {
        $check_stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'address'");
        $has_address_column = $check_stmt->rowCount() > 0;
    } catch (Exception $e) {
        $has_address_column = false;
    }
    
    // Build query based on column existence
    if ($has_address_column) {
        $query = "
            SELECT o.*, u.full_name, u.email, u.phone, u.address as user_address
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ";
    } else {
        $query = "
            SELECT o.*, u.full_name, u.email, u.phone, '' as user_address
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ";
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        die("Order not found");
    }
    
    // Check permissions
    $is_admin = isset($_SESSION['admin_id']);
    $is_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $order['user_id'];
    
    if (!$is_admin && !$is_owner) {
        die("You don't have permission to view this invoice");
    }
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name, p.sku, p.description
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get brand settings
    $brand_stmt = $pdo->query("SELECT * FROM brand_settings LIMIT 1");
    $brand = $brand_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$brand) {
        $brand = [
            'brand_name' => 'Online Store',
            'contact_email' => 'support@example.com',
            'contact_phone' => '+1234567890',
            'address' => '123 Store Street, City, Country',
            'logo_path' => ''
        ];
    }
    
    // Generate invoice number if not exists
    if (empty($order['invoice_number'])) {
        $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad($order_id, 6, '0', STR_PAD_LEFT);
        $pdo->prepare("UPDATE orders SET invoice_number = ? WHERE id = ?")->execute([$invoice_number, $order_id]);
        $order['invoice_number'] = $invoice_number;
    }
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= htmlspecialchars($order['invoice_number']) ?></title>
    <style>
        /* Invoice Styles */
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .invoice-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
        }
        
        .invoice-header h1 {
            font-size: 2.5rem;
            margin-bottom: 5px;
            font-weight: 700;
        }
        
        .invoice-header .subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .invoice-body {
            padding: 30px;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.2rem;
            color: #667eea;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #667eea;
            font-weight: 600;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }
        
        .col {
            flex: 1;
            padding: 0 15px;
            min-width: 250px;
        }
        
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        .info-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 1rem;
            font-weight: 500;
        }
        
        .info-value.large {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        /* Table Styles */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        /* Totals Section */
        .totals {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-top: 30px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .total-row:last-child {
            border-bottom: none;
            font-size: 1.2rem;
            font-weight: 700;
            color: #28a745;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .status-pending { background: #ffc107; color: #000; }
        .status-paid { background: #17a2b8; color: #fff; }
        .status-shipped { background: #6f42c1; color: #fff; }
        .status-completed { background: #28a745; color: #fff; }
        .status-cancelled { background: #dc3545; color: #fff; }
        
        /* Footer */
        .invoice-footer {
            background: #f8f9fa;
            padding: 20px 30px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            color: #666;
        }
        
        .invoice-footer p {
            margin: 5px 0;
        }
        
        /* Print Styles */
        @media print {
            body {
                background: white;
                padding: 0;
                font-size: 12px;
            }
            
            .invoice-container {
                box-shadow: none;
                max-width: 100%;
                margin: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            .invoice-header {
                background: #667eea !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                padding: 20px;
            }
            
            .invoice-header h1 {
                font-size: 1.8rem;
            }
            
            .invoice-body {
                padding: 20px;
            }
            
            .table {
                font-size: 11px;
            }
            
            .table th, .table td {
                padding: 8px;
            }
            
            .action-buttons, .btn-download-pdf, .btn-back, .btn-download {
                display: none !important;
            }
            
            @page {
                margin: 0.5cm;
            }
        }
        
        /* Action Buttons */
        .action-buttons {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .btn-print {
            background: #28a745;
            color: white;
        }
        
        .btn-download {
            background: #17a2b8;
            color: white;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
        }
        
        .btn-download-pdf {
            background: #dc3545;
            color: white;
        }
        
        .btn-save {
            background: #6610f2;
            color: white;
        }
        
        /* Logo */
        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .logo {
            max-height: 60px;
            max-width: 150px;
        }
        
        /* QR Code (optional) */
        .qr-code {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            border: 1px dashed #dee2e6;
            border-radius: 6px;
        }
        
        .qr-placeholder {
            width: 150px;
            height: 150px;
            background: #f8f9fa;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .invoice-header h1 {
                font-size: 1.8rem;
            }
            
            .row {
                flex-direction: column;
            }
            
            .col {
                margin-bottom: 15px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            body {
                padding: 10px;
            }
        }
        
        /* Download as PDF link style */
        .pdf-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            color: #856404;
            font-size: 0.9rem;
        }
        
        .pdf-info a {
            color: #0066cc;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="invoice-header">
            <div class="logo-container">
                <?php if (!empty($brand['logo_path'])): ?>
                    <img src="<?= htmlspecialchars($brand['logo_path']) ?>" alt="Logo" class="logo">
                <?php endif; ?>
                <div>
                    <h1><?= htmlspecialchars($brand['brand_name'] ?? 'Online Store') ?></h1>
                    <div class="subtitle">Professional Invoice</div>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons no-print">
            <button class="btn btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print Invoice
            </button>
            
            <!-- <button class="btn btn-save" onclick="downloadAsPDF()">
                <i class="fas fa-file-pdf"></i> Save as PDF
            </button> -->
            
            <?php if ($is_admin): ?>
                <a href="admin_orders.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Orders
                </a>
                <a href="admin_order_view.php?id=<?= $order_id ?>" class="btn btn-download">
                    <i class="fas fa-edit"></i> Manage Order
                </a>
            <?php else: ?>
                <a href="user_orders.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Back to My Orders
                </a>
            <?php endif; ?>
        </div>
        
        <!-- PDF Info Message -->
        <div class="pdf-info no-print">
            <strong><i class="fas fa-info-circle"></i> PDF Download Tip:</strong> 
            Use your browser's "Print to PDF" feature:
            <ol style="margin: 5px 0 0 20px;">
                <li>Click "Print Invoice" button</li>
                <li>In the print dialog, choose "Save as PDF" or "Microsoft Print to PDF"</li>
                <!-- <li>Click "Save" to download as PDF file</li> -->
            </ol>
        </div>
        
        <!-- Invoice Body -->
        <div class="invoice-body">
            <!-- Invoice Info -->
            <div class="section">
                <h2 class="section-title">Invoice Details</h2>
                <div class="row">
                    <div class="col">
                        <div class="info-box">
                            <div class="info-label">Invoice Number</div>
                            <div class="info-value large"><?= htmlspecialchars($order['invoice_number'] ?? 'INV-' . date('Ymd') . '-' . $order_id) ?></div>
                        </div>
                        <div class="info-box">
                            <div class="info-label">Order Date</div>
                            <div class="info-value"><?= date('F j, Y, g:i a', strtotime($order['created_at'])) ?></div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="info-box">
                            <div class="info-label">Order Status</div>
                            <div class="info-value">
                                <span class="status-badge status-<?= $order['status'] ?>">
                                    <?= ucfirst($order['status']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-box">
                            <div class="info-label">Order ID</div>
                            <div class="info-value">#<?= $order_id ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Company & Customer Info -->
            <div class="section">
                <h2 class="section-title">Billing Information</h2>
                <div class="row">
                    <div class="col">
                        <div class="info-box">
                            <h3>From:</h3>
                            <p><strong><?= htmlspecialchars($brand['brand_name'] ?? 'Online Store') ?></strong></p>
                            <?php if (!empty($brand['address'])): ?>
                                <p><?= htmlspecialchars($brand['address']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($brand['contact_phone'])): ?>
                                <p>Phone: <?= htmlspecialchars($brand['contact_phone']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($brand['contact_email'])): ?>
                                <p>Email: <?= htmlspecialchars($brand['contact_email']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col">
                        <div class="info-box">
                            <h3>Bill To:</h3>
                            <p><strong><?= htmlspecialchars($order['full_name']) ?></strong></p>
                            <?php if (!empty($order['user_address']) && $order['user_address'] !== ''): ?>
                                <p><?= htmlspecialchars($order['user_address']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($order['phone'])): ?>
                                <p>Phone: <?= htmlspecialchars($order['phone']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($order['email'])): ?>
                                <p>Email: <?= htmlspecialchars($order['email']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Order Items -->
            <div class="section">
                <h2 class="section-title">Order Items</h2>
                <?php if (empty($items)): ?>
                    <div class="info-box">
                        <p class="text-center text-muted">No items found in this order.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Description</th>
                                    <th class="text-center">SKU</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-right">Unit Price</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $subtotal = 0;
                                $tax_rate = 0.10; // 10% tax - adjust as needed
                                $shipping = 0;
                                
                                foreach ($items as $index => $item):
                                    $item_total = $item['price'] * $item['quantity'];
                                    $subtotal += $item_total;
                                ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($item['name']) ?></strong>
                                        <?php if (!empty($item['description'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars(substr($item['description'], 0, 100)) ?><?= strlen($item['description']) > 100 ? '...' : '' ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= htmlspecialchars($item['sku'] ?? 'N/A') ?></td>
                                    <td class="text-center"><?= $item['quantity'] ?></td>
                                    <td class="text-right">$<?= number_format($item['price'], 2) ?></td>
                                    <td class="text-right">$<?= number_format($item_total, 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Totals -->
            <?php if (!empty($items)): ?>
            <div class="totals">
                <?php
                $tax = $subtotal * $tax_rate;
                $total = $subtotal + $tax + $shipping;
                ?>
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span>$<?= number_format($subtotal, 2) ?></span>
                </div>
                <div class="total-row">
                    <span>Tax (<?= ($tax_rate * 100) ?>%):</span>
                    <span>$<?= number_format($tax, 2) ?></span>
                </div>
                <div class="total-row">
                    <span>Shipping:</span>
                    <span>$<?= number_format($shipping, 2) ?></span>
                </div>
                <div class="total-row">
                    <span><strong>Grand Total:</strong></span>
                    <span><strong>$<?= number_format($order['total_amount'], 2) ?></strong></span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Payment & Shipping Info -->
            <div class="section">
                <h2 class="section-title">Additional Information</h2>
                <div class="row">
                    <div class="col">
                        <div class="info-box">
                            <h3>Payment Information</h3>
                            <p><strong>Payment Status:</strong> 
                                <span class="status-badge status-<?= in_array($order['status'], ['paid', 'completed', 'shipped']) ? 'paid' : 'pending' ?>">
                                    <?= in_array($order['status'], ['paid', 'completed', 'shipped']) ? 'Paid' : 'Pending' ?>
                                </span>
                            </p>
                            <p><strong>Payment Method:</strong> Credit Card / Online Payment</p>
                            <p><strong>Invoice Date:</strong> <?= date('F j, Y') ?></p>
                            <?php if (in_array($order['status'], ['paid', 'completed', 'shipped'])): ?>
                                <p><strong>Paid Date:</strong> <?= date('F j, Y', strtotime($order['created_at'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col">
                        <div class="info-box">
                            <h3>Shipping Information</h3>
                            <p><strong>Shipping Status:</strong> 
                                <span class="status-badge status-<?= $order['status'] ?>">
                                    <?= ucfirst($order['status']) ?>
                                </span>
                            </p>
                            <?php if (!empty($order['tracking_number'])): ?>
                                <p><strong>Tracking Number:</strong> <code><?= htmlspecialchars($order['tracking_number']) ?></code></p>
                            <?php endif; ?>
                            <?php if (!empty($order['shipped_at'])): ?>
                                <p><strong>Shipped Date:</strong> <?= date('F j, Y', strtotime($order['shipped_at'])) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($order['completed_at'])): ?>
                                <p><strong>Delivered Date:</strong> <?= date('F j, Y', strtotime($order['completed_at'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- QR Code for Invoice Verification -->
            <div class="qr-code">
                <h3>Invoice Verification</h3>
                <p>Scan QR code to verify this invoice</p>
                <div class="qr-placeholder">
                    <div style="font-family: monospace; text-align: center; font-size: 14px;">
                        <strong>INVOICE</strong><br>
                        #<?= $order_id ?><br>
                        <?= $order['invoice_number'] ?? '' ?><br>
                        Date: <?= date('Y-m-d') ?>
                    </div>
                </div>
                <p><small>Invoice ID: <?= $order['invoice_number'] ?? 'INV-' . date('Ymd') . '-' . $order_id ?></small></p>
            </div>
            
            <!-- Terms & Conditions -->
            <div class="section">
                <h2 class="section-title">Terms & Conditions</h2>
                <div class="info-box">
                    <p>1. Goods once sold will not be taken back or exchanged.</p>
                    <p>2. All disputes are subject to jurisdiction of the courts in our city.</p>
                    <p>3. Payment should be made within 30 days from invoice date.</p>
                    <p>4. Late payments are subject to 2% monthly interest.</p>
                    <p>5. This is a computer generated invoice, no signature required.</p>
                </div>
            </div>
            
            <!-- Notes -->
            <?php if (!empty($order['cancelled_reason'])): ?>
            <div class="section">
                <h2 class="section-title">Cancellation Notes</h2>
                <div class="info-box" style="background: #ffeaea;">
                    <p><strong>Reason for Cancellation:</strong> <?= htmlspecialchars($order['cancelled_reason']) ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Footer -->
        <div class="invoice-footer">
            <p><strong><?= htmlspecialchars($brand['brand_name'] ?? 'Online Store') ?></strong></p>
            <?php if (!empty($brand['address'])): ?>
                <p><?= htmlspecialchars($brand['address']) ?></p>
            <?php endif; ?>
            <?php if (!empty($brand['contact_phone']) || !empty($brand['contact_email'])): ?>
                <p>
                    <?php if (!empty($brand['contact_phone'])): ?>
                        Phone: <?= htmlspecialchars($brand['contact_phone']) ?>
                    <?php endif; ?>
                    <?php if (!empty($brand['contact_phone']) && !empty($brand['contact_email'])): ?>
                         | 
                    <?php endif; ?>
                    <?php if (!empty($brand['contact_email'])): ?>
                        Email: <?= htmlspecialchars($brand['contact_email']) ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <p>This is a computer generated invoice and does not require a physical signature.</p>
            <p class="text-success"><strong>Thank you for your business!</strong></p>
        </div>
    </div>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        // Auto-print option
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('print') === '1') {
            setTimeout(() => {
                window.print();
            }, 500);
        }
        
        // Download as PDF using browser's print to PDF
        function downloadAsPDF() {
            // First hide non-print elements
            const nonPrintElements = document.querySelectorAll('.no-print');
            nonPrintElements.forEach(el => {
                el.style.display = 'none';
            });
            
            // Trigger print dialog
            window.print();
            
            // Show elements back after a delay
            setTimeout(() => {
                nonPrintElements.forEach(el => {
                    el.style.display = '';
                });
            }, 1000);
        }
        
        // Add watermark for draft invoices
        document.addEventListener('DOMContentLoaded', function() {
            if ('<?= $order['status'] ?>' === 'pending') {
                const watermark = document.createElement('div');
                watermark.innerHTML = 'DRAFT';
                watermark.style.cssText = `
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%) rotate(-45deg);
                    font-size: 80px;
                    color: rgba(255,0,0,0.1);
                    z-index: 1000;
                    pointer-events: none;
                    font-weight: bold;
                    font-family: Arial, sans-serif;
                `;
                document.body.appendChild(watermark);
            }
            
            // Auto-print if URL has print=1
            if (urlParams.get('print') === '1') {
                downloadAsPDF();
            }
        });
        
        // Better print handling
        window.addEventListener('beforeprint', function() {
            // Add print-specific styles
            document.body.classList.add('printing');
            
            // Update title for print
            const originalTitle = document.title;
            document.title = 'Invoice_<?= $order['invoice_number'] ?? $order_id ?>_' + new Date().toISOString().split('T')[0];
            
            // Restore title after print
            window.addEventListener('afterprint', function() {
                document.title = originalTitle;
                document.body.classList.remove('printing');
                
                // Show non-print elements again
                const nonPrintElements = document.querySelectorAll('.no-print');
                nonPrintElements.forEach(el => {
                    el.style.display = '';
                });
            });
        });
    </script>
    
    <!-- Print-specific styles -->
    <style>
        @media print {
            .printing .no-print {
                display: none !important;
            }
            
            body.printing {
                font-size: 11px;
            }
            
            .printing .invoice-header {
                padding: 15px !important;
            }
            
            .printing .invoice-header h1 {
                font-size: 20px !important;
            }
            
            .printing .invoice-body {
                padding: 15px !important;
            }
            
            .printing .table {
                font-size: 10px !important;
            }
            
            .printing .table th,
            .printing .table td {
                padding: 6px !important;
            }
            
            .printing .section-title {
                font-size: 14px !important;
            }
        }
    </style>
</body>
</html>