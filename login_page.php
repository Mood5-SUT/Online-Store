<?php
session_start();
include __DIR__ . '/db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $login_input = trim($_POST['login_input']);
    $password    = $_POST['password'];

    if (empty($login_input) || empty($password)) {
        $error = "Please fill in all fields.";
        goto end;
    }

    $isEmail = filter_var($login_input, FILTER_VALIDATE_EMAIL);
    $isPhone = preg_match('/^\+?[0-9]{10,15}$/', $login_input);

    // ==========================
    // 1️⃣ ADMIN LOGIN (EMAIL ONLY)
    // ==========================
    if ($isEmail) {
        $adminStmt = $pdo->prepare("
            SELECT id, username, email, password, role 
            FROM admin_users 
            WHERE email = ? 
            LIMIT 1
        ");
        $adminStmt->execute([$login_input]);
        $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['role']     = $admin['role'];
            header("Location: admin_page.php");
            exit;
        }
    }

    // ==========================
    // 2️⃣ USER LOGIN (EMAIL or PHONE)
    // ==========================
    if ($isEmail) {
        $userStmt = $pdo->prepare("
            SELECT id, full_name, email, phone, password 
            FROM users 
            WHERE email = ? 
            LIMIT 1
        ");
        $userStmt->execute([$login_input]);
    }
    elseif ($isPhone) {
        $userStmt = $pdo->prepare("
            SELECT id, full_name, email, phone, password 
            FROM users 
            WHERE phone = ? 
            LIMIT 1
        ");
        $userStmt->execute([$login_input]);
    }
    else {
        $error = "Please enter a valid email or phone number.";
        goto end;
    }

    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    // USER CHECK
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        header("Location: index.php");
        exit;
    }

    // FALLBACK ERROR
    $error = "Invalid login credentials.";
}

end:
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - My Online Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- <link href="css/bootstrap.min.css" rel="stylesheet">
    <script src="js/bootstrap.bundle.min.js"></script> -->
    
    <style>
        body { background: linear-gradient(to right, #4A00E0, #8E2DE2); min-height: 100vh; display: flex; align-items: center; }
        .login-card { max-width: 420px; margin: 0 auto; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .btn-primary { background: #4A00E0; border: none; }
        .btn-primary:hover { background: #3a00b8; }
        
        /* Social Button Styling */
        .btn-social { position: relative; padding-left: 44px; text-align: left; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .btn-social :first-child { position: absolute; left: 0; top: 0; bottom: 0; width: 32px; line-height: 34px; font-size: 1.6em; text-align: center; border-right: 1px solid rgba(0, 0, 0, 0.2); }
        .btn-google { color: #fff; background-color: #dd4b39; border-color: rgba(0,0,0,0.2); }
        .btn-microsoft { color: #fff; background-color: #2f2f2f; border-color: rgba(0,0,0,0.2); }
        .btn-yahoo { color: #fff; background-color: #410093; border-color: rgba(0,0,0,0.2); }
        .btn-google:hover, .btn-microsoft:hover, .btn-yahoo:hover { opacity: 0.9; color: #fff;}
    </style>
</head>
<body>
<div class="container">
    <div class="card login-card">
        <div class="card-body p-5">
            <h2 class="text-center mb-4">Welcome Back</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Email or Phone Number</label>
                    <input type="text" class="form-control form-control-lg" name="login_input" placeholder="example@gmail.com" value="<?php echo isset($_POST['login_input']) ? htmlspecialchars($_POST['login_input']) : ''; ?>" required>
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control form-control-lg" name="password" required>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-3 fw-bold mb-3">Login</button>
            </form>

            <div class="d-flex align-items-center mb-3">
                <hr class="flex-grow-1">
                <span class="mx-2 text-muted small">OR LOGIN WITH</span>
                <hr class="flex-grow-1">
            </div>

            <div class="d-grid gap-2">
                <button class="btn btn-google btn-social" id="btn-google">
                    <i class="fab fa-google"></i> Sign in with Google
                </button>
                <button class="btn btn-microsoft btn-social" id="btn-microsoft">
                    <i class="fab fa-microsoft"></i> Sign in with Microsoft
                </button>
                <button class="btn btn-yahoo btn-social" id="btn-yahoo">
                    <i class="fab fa-yahoo"></i> Sign in with Yahoo
                </button>
            </div>

            <div class="text-center mt-4">
                <p>Don't have an account? <a href="register_page.php" class="text-decoration-none">Sign up</a></p>
                <a href="index.php" class="text-muted small">Continue as guest</a>
            </div>
        </div>
    </div>
</div>

<script type="module">
  import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
  import { getAuth, GoogleAuthProvider, OAuthProvider, signInWithPopup } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js";

  // --- PASTE YOUR FIREBASE CONFIG HERE ---
  const firebaseConfig = {
    apiKey: "AIzaSyCgFIXRrU379Q4z52TGoMo1NpLXmt2-els",
    authDomain: "online-store-14641.firebaseapp.com",
    projectId: "online-store-14641",
    storageBucket: "online-store-14641.firebasestorage.app",
    messagingSenderId: "915287166536",
    appId: "1:915287166536:web:4f15840b59b25e9bf9127d",
    measurementId: "G-5B5MN7M7VS"
  };

  const app = initializeApp(firebaseConfig);
  const auth = getAuth(app);

  // Helper function to handle the login result securely
  function handleSocialLogin(provider) {
    signInWithPopup(auth, provider)
      .then((result) => {
        const user = result.user;
        console.log("Firebase Login Success:", user);
        
        // 1. Get the Secure ID Token
        user.getIdToken().then((idToken) => {
            
            // 2. Send the TOKEN to PHP (not just the email)
            fetch('social_login_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    idToken: idToken,
                    displayName: user.displayName
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    alert("Login failed: " + data.message);
                }
            })
            .catch(error => console.error('Error:', error));
            
        });
      })
      .catch((error) => {
        console.error("Auth Error:", error);
        alert("Authentication failed: " + error.message);
      });
  }

  // 1. Google Login
  document.getElementById('btn-google').addEventListener('click', () => {
      const provider = new GoogleAuthProvider();
      handleSocialLogin(provider);
  });

  // 2. Microsoft Login
  document.getElementById('btn-microsoft').addEventListener('click', () => {
      const provider = new OAuthProvider('microsoft.com');
      handleSocialLogin(provider);
  });

  // 3. Yahoo Login
  document.getElementById('btn-yahoo').addEventListener('click', () => {
      const provider = new OAuthProvider('yahoo.com');
      handleSocialLogin(provider);
  });

</script>
</body>
</html>