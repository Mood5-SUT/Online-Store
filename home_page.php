<?php
session_start();
include __DIR__ . '/db_connect.php';

// Fetch brand info
$brand = $pdo->query("SELECT * FROM brand_settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);

// Fetch categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch featured products (latest 8)
$products = $pdo->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id ORDER BY p.id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($brand['brand_name'] ?? 'Our Store')?></title>
<meta name="description" content="<?=h($brand['meta_description'] ?? '')?>">
<meta name="keywords" content="<?=h($brand['meta_keywords'] ?? '')?>">
<?= $brand['meta_code'] ?? '' ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.card-img-top{height:200px; object-fit:cover;}
</style>
</head>
<body>

<!-- Header -->
<nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
  <div class="container">
    <a class="navbar-brand" href="#">
      <?php if(!empty($brand['logo_path'])): ?>
        <img src="<?=h($brand['logo_path'])?>" alt="<?=h($brand['brand_name'])?>" height="50">
      <?php else: ?>
        <?=h($brand['brand_name'] ?? 'Store')?>
      <?php endif; ?>
    </a>
  </div>
</nav>

<div class="container">

  <!-- About Section -->
  <?php if(!empty($brand['about'])): ?>
    <div class="mb-4">
      <h2>About <?=h($brand['brand_name'])?></h2>
      <p><?=nl2br(h($brand['about']))?></p>
    </div>
  <?php endif; ?>

  <!-- Categories -->
  <?php if(!empty($categories)): ?>
    <div class="mb-4">
      <h3>Categories</h3>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach($categories as $c): ?>
          <span class="badge bg-primary p-2"><?=h($c['name'])?></span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Featured Products -->
  <?php if(!empty($products)): ?>
    <div class="mb-4">
      <h3>Featured Products</h3>
      <div class="row">
        <?php foreach($products as $p): ?>
          <div class="col-md-3 mb-3">
            <div class="card">
              <?php
              $imgStmt = $pdo->prepare("SELECT filename FROM product_images WHERE product_id=? LIMIT 1");
              $imgStmt->execute([$p['id']]);
              $img = $imgStmt->fetchColumn();
              ?>
              <img src="uploads/images/<?=h($img ?: 'placeholder.png')?>" class="card-img-top" alt="<?=h($p['title'])?>">
              <div class="card-body">
                <h5 class="card-title"><?=h($p['title'])?></h5>
                <p class="card-text">Price: <?=number_format($p['price'],2)?> EGP</p>
                <a href="#" class="btn btn-primary btn-sm">View Product</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Contact Section -->
  <?php if(!empty($brand['contact_email']) || !empty($brand['contact_phone']) || !empty($brand['address'])): ?>
    <div class="mb-4">
      <h3>Contact Us</h3>
      <ul class="list-unstyled">
        <?php if(!empty($brand['contact_email'])): ?><li>Email: <?=h($brand['contact_email'])?></li><?php endif; ?>
        <?php if(!empty($brand['contact_phone'])): ?><li>Phone: <?=h($brand['contact_phone'])?></li><?php endif; ?>
        <?php if(!empty($brand['address'])): ?><li>Address: <?=h($brand['address'])?></li><?php endif; ?>
      </ul>
    </div>
  <?php endif; ?>

  <!-- Social Media -->
  <?php if(!empty($brand['facebook'])||!empty($brand['instagram'])||!empty($brand['twitter'])||!empty($brand['youtube'])): ?>
    <div class="mb-4">
      <h3>Follow Us</h3>
      <div class="d-flex gap-2">
        <?php if(!empty($brand['facebook'])): ?><a href="<?=h($brand['facebook'])?>" class="btn btn-primary btn-sm" target="_blank">Facebook</a><?php endif; ?>
        <?php if(!empty($brand['instagram'])): ?><a href="<?=h($brand['instagram'])?>" class="btn btn-danger btn-sm" target="_blank">Instagram</a><?php endif; ?>
        <?php if(!empty($brand['twitter'])): ?><a href="<?=h($brand['twitter'])?>" class="btn btn-info btn-sm" target="_blank">Twitter</a><?php endif; ?>
        <?php if(!empty($brand['youtube'])): ?><a href="<?=h($brand['youtube'])?>" class="btn btn-danger btn-sm" target="_blank">YouTube</a><?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>