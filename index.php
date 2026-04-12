<?php
// index.php - Login Page
require_once 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $conn->prepare("SELECT userID, fullName, email, passwordHash, role, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if ($user['status'] === 'inactive') {
                $error = 'Your account has been deactivated. Contact the administrator.';
            } elseif (password_verify($password, $user['passwordHash'])) {
                $_SESSION['user_id']    = $user['userID'];
                $_SESSION['user_name']  = $user['fullName'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role']  = $user['role'];
                header("Location: pages/dashboard.php");
                exit();
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Invalid email or password.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CertVerify - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-logo">
            <div class="logo-box"><i class="fas fa-shield-halved"></i></div>
            <h2>CertVerify</h2>
            <p class="subtitle">Blockchain-Inspired Certificate Validation System<br>Cavendish University Zambia</p>
        </div>

        <?php if ($error): ?>
        <div class="alert-cv danger" style="margin-bottom:1rem;">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" class="form-control"
                           placeholder="Enter your email"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" id="passwordField"
                           class="form-control" placeholder="Enter your password" required>
                    <button type="button" class="input-group-text" style="cursor:pointer;"
                            onclick="togglePwd()">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-primary-cv w-100 mb-3">
                <i class="fas fa-sign-in-alt me-2"></i>Sign In
            </button>
        </form>

        <hr class="divider">
        <div style="text-align:center;font-size:0.82rem;color:var(--text-muted);">
            <strong>Test Accounts:</strong><br>
            Admin: admin@certverify.com / admin123<br>
            Institution: institution@certverify.com / institution123
        </div>

        <div style="text-align:center;margin-top:1rem;">
            <a href="public_verify.php" style="color:var(--primary-light);font-size:0.88rem;font-weight:500;">
                <i class="fas fa-search me-1"></i>Verify a Certificate (Public)
            </a>
        </div>
    </div>
</div>

<script>
function togglePwd() {
    var f = document.getElementById('passwordField');
    var i = document.getElementById('eyeIcon');
    if (f.type === 'password') { f.type = 'text'; i.className = 'fas fa-eye-slash'; }
    else { f.type = 'password'; i.className = 'fas fa-eye'; }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
