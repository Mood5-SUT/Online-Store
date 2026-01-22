<?php
session_start();
include 'db_connect.php'; // Your PDO connection file

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['FullName']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    // --- Validation ---
    if (empty($full_name) || empty($email) || empty($phone) || empty($password) || empty($confirm)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!preg_match('/^\+?[0-9]{10,15}$/', $phone)) {
        $error = "Invalid phone number. Use 10–15 digits.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1");
        $stmt->execute([$email, $phone]);
        $existing = $stmt->fetch();

        if ($existing) {
            $error = "Email or phone number already exists.";
        } else {
            // Register user
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, email, phone, password)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$full_name, $email, $phone, $hashed]);

            // Auto login
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['full_name'] = $full_name;
            header("Location: index.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - My Online Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { background: linear-gradient(to right, #8E2DE2, #4A00E0); min-height: 100vh; display: flex; align-items: center; }
        .register-card { max-width: 450px; margin: 0 auto; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .btn-primary { background: #4A00E0; border: none; }
        .btn-primary:hover { background: #3a00b8; }
    </style>
</head>
<body>

<div class="container">
    <div class="card register-card">
        <div class="card-body p-5">
            <h2 class="text-center mb-4">Create an Account</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Full Name -->
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control form-control-lg" name="FullName" placeholder="Ahmed Mustafa"
                           value="<?php echo isset($_POST['FullName']) ? htmlspecialchars($_POST['FullName']) : ''; ?>" required>
                </div>

                <!-- Email -->
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control form-control-lg" name="email" placeholder="example@gmail.com"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>

                <!-- Phone -->
                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="text" class="form-control form-control-lg" name="phone" placeholder="+201234567890"
                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                </div>

                <!-- Password -->
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control form-control-lg" name="password" required>
                </div>

                <!-- Confirm Password -->
                <div class="mb-4">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" class="form-control form-control-lg" name="confirm_password" required>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-3 fw-bold">Register</button>
            </form>

            <div class="text-center mt-4">
                <p>Already have an account? <a href="login_page.php" class="text-decoration-none">Login</a></p>
                <a href="index.php" class="text-muted small">Continue as guest</a>
            </div>

        </div>
    </div>
</div>

</body>
</html>
