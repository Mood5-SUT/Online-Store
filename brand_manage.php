<?php
session_start();
include __DIR__ . '/db_connect.php';

// Admin authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: login_page.php");
    exit;
}

$error = '';
$success = '';

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES); }

// Create table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS brand_settings (
    id INT PRIMARY KEY DEFAULT 1,
    brand_name VARCHAR(255),
    logo_path VARCHAR(255),
    about TEXT,
    description TEXT,
    contact_email VARCHAR(150),
    contact_phone VARCHAR(50),
    address VARCHAR(255),
    facebook VARCHAR(255),
    instagram VARCHAR(255),
    twitter VARCHAR(255),
    youtube VARCHAR(255),
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords TEXT,
    meta_code TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;");

// Save brand
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_brand'])) {

    $brand_name = trim($_POST['brand_name'] ?? '');
    if ($brand_name === '') {
        $error = 'Brand name is required.';
    } else {
        $logo_path = $_POST['current_logo'] ?? null;

        if (!empty($_FILES['logo']['name'])) {
            $dir = __DIR__ . '/uploads/brand/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowed = ['png','jpg','jpeg','webp'];
            if (in_array($ext,$allowed)) {
                $fname = 'logo_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir.$fname)) {
                    $logo_path = 'uploads/brand/'.$fname;
                }
            }
        }

        $stmt = $pdo->prepare("INSERT INTO brand_settings
            (id,brand_name,logo_path,about,description,contact_email,contact_phone,address,
             facebook,instagram,twitter,youtube,meta_title,meta_description,meta_keywords,meta_code)
            VALUES (1,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
            brand_name=VALUES(brand_name), logo_path=VALUES(logo_path), about=VALUES(about),
            description=VALUES(description), contact_email=VALUES(contact_email),
            contact_phone=VALUES(contact_phone), address=VALUES(address),
            facebook=VALUES(facebook), instagram=VALUES(instagram), twitter=VALUES(twitter),
            youtube=VALUES(youtube), meta_title=VALUES(meta_title),
            meta_description=VALUES(meta_description), meta_keywords=VALUES(meta_keywords),
            meta_code=VALUES(meta_code)");

        $stmt->execute([
            $brand_name,
            $logo_path,
            $_POST['about'] ?? '',
            $_POST['description'] ?? '',
            $_POST['contact_email'] ?? '',
            $_POST['contact_phone'] ?? '',
            $_POST['address'] ?? '',
            $_POST['facebook'] ?? '',
            $_POST['instagram'] ?? '',
            $_POST['twitter'] ?? '',
            $_POST['youtube'] ?? '',
            $_POST['meta_title'] ?? '',
            $_POST['meta_description'] ?? '',
            $_POST['meta_keywords'] ?? '',
            $_POST['meta_code'] ?? ''
        ]);

        $success = 'Brand settings saved successfully.';
    }
}

$brand = $pdo->query("SELECT * FROM brand_settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Brand Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.thumb{max-height:80px}
.card{margin-bottom:1rem}
</style>
</head>
<body>
<div class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Brand Management</h1>
    <div>
        <a href="products_page.php" class="btn btn-primary">Products</a>
        <a href="admin_page.php" class="btn btn-secondary">Dashboard</a>
    </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?=h($error)?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?=h($success)?></div><?php endif; ?>

<div class="card">
<div class="card-body">
<form method="POST" enctype="multipart/form-data">

<h5 class="mb-3">Basic Info</h5>
<div class="mb-3">
    <label class="form-label">Brand Name</label>
    <input type="text" name="brand_name" class="form-control" value="<?=h($brand['brand_name']??'')?>" required>
</div>

<div class="mb-3">
    <label class="form-label">Logo</label>
    <input type="file" name="logo" class="form-control">
    <input type="hidden" name="current_logo" value="<?=h($brand['logo_path']??'')?>">
    <?php if(!empty($brand['logo_path'])): ?>
        <img src="<?=h($brand['logo_path'])?>" class="thumb mt-2">
    <?php endif; ?>
</div>

<div class="mb-3">
    <label class="form-label">About</label>
    <textarea name="about" class="form-control" rows="3"><?=h($brand['about']??'')?></textarea>
</div>

<div class="mb-3">
    <label class="form-label">Description</label>
    <textarea name="description" class="form-control" rows="3"><?=h($brand['description']??'')?></textarea>
</div>

<hr>
<h5>Contact</h5>
<div class="row mb-3">
    <div class="col-md-4"><input class="form-control" name="contact_email" placeholder="Email" value="<?=h($brand['contact_email']??'')?>"></div>
    <div class="col-md-4"><input class="form-control" name="contact_phone" placeholder="Phone" value="<?=h($brand['contact_phone']??'')?>"></div>
    <div class="col-md-4"><input class="form-control" name="address" placeholder="Address" value="<?=h($brand['address']??'')?>"></div>
</div>

<hr>
<h5>Social Media</h5>
<div class="row mb-3">
    <div class="col-md-3"><input class="form-control" name="facebook" placeholder="Facebook" value="<?=h($brand['facebook']??'')?>"></div>
    <div class="col-md-3"><input class="form-control" name="instagram" placeholder="Instagram" value="<?=h($brand['instagram']??'')?>"></div>
    <div class="col-md-3"><input class="form-control" name="twitter" placeholder="Twitter" value="<?=h($brand['twitter']??'')?>"></div>
    <div class="col-md-3"><input class="form-control" name="youtube" placeholder="YouTube" value="<?=h($brand['youtube']??'')?>"></div>
</div>

<hr>
<h5>SEO / Meta</h5>
<div class="mb-3"><input class="form-control" name="meta_title" placeholder="Meta Title" value="<?=h($brand['meta_title']??'')?>"></div>
<div class="mb-3"><textarea class="form-control" name="meta_description" rows="2" placeholder="Meta Description"><?=h($brand['meta_description']??'')?></textarea></div>
<div class="mb-3"><textarea class="form-control" name="meta_keywords" rows="2" placeholder="Meta Keywords"><?=h($brand['meta_keywords']??'')?></textarea></div>
<div class="mb-3"><textarea class="form-control" name="meta_code" rows="3" placeholder="Analytics / Pixel code"><?=h($brand['meta_code']??'')?></textarea></div>

<button class="btn btn-primary" name="save_brand">Save Brand Settings</button>

</form>
</div>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
