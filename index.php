<?php
// index.php - Login Page
require_once 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
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
                $_SESSION['user_id'] = $user['userID'];
                $_SESSION['user_name'] = $user['fullName'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
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
    <title>CertVerify - Blockchain-Based Certificate Validation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .landing-wrapper {
            min-height: 100vh;
            display: flex;
            background: #f4f7fb;
        }

        /* LEFT PANEL */
        .landing-left {
            flex: 1;
            background: linear-gradient(160deg, #1E2761 0%, #0e1a4a 60%, #131a45 100%);
            padding: 3rem 3.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .landing-left::before {
            content: '';
            position: absolute;
            width: 420px;
            height: 420px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(232, 160, 32, 0.10) 0%, transparent 70%);
            top: -80px;
            right: -80px;
        }

        .landing-left::after {
            content: '';
            position: absolute;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(202, 220, 252, 0.08) 0%, transparent 70%);
            bottom: -40px;
            left: -40px;
        }

        .landing-left .brand-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 2.4rem;
            position: relative;
            z-index: 1;
        }

        .landing-left .brand-header .icon-box {
            width: 52px;
            height: 52px;
            background: #e8a020;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: #1E2761;
            flex-shrink: 0;
        }

        .landing-left .brand-header h1 {
            font-size: 1.7rem;
            font-weight: 800;
            color: #fff;
            margin: 0;
            letter-spacing: 0.5px;
        }

        .landing-left .brand-header span {
            font-size: 0.78rem;
            color: rgba(255, 255, 255, 0.50);
            display: block;
            margin-top: 2px;
        }

        .landing-left .tagline {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.4;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .landing-left .tagline em {
            color: #e8a020;
            font-style: normal;
        }

        .landing-left .description {
            font-size: 0.94rem;
            color: rgba(255, 255, 255, 0.68);
            line-height: 1.78;
            margin-bottom: 2rem;
            position: relative;
            z-index: 1;
            max-width: 460px;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .feature-list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 1rem;
            color: rgba(255, 255, 255, 0.80);
            font-size: 0.90rem;
            line-height: 1.5;
        }

        .feature-list li .feat-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(232, 160, 32, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e8a020;
            font-size: 0.85rem;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .feature-list li strong {
            color: #fff;
            display: block;
            font-size: 0.87rem;
            margin-bottom: 1px;
        }

        .stats-row {
            display: flex;
            gap: 1rem;
            margin-top: 2.2rem;
            position: relative;
            z-index: 1;
            flex-wrap: wrap;
        }

        .stat-pill {
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.13);
            border-radius: 10px;
            padding: 0.7rem 1rem;
            text-align: center;
            flex: 1;
            min-width: 88px;
        }

        .stat-pill .num {
            font-size: 1.35rem;
            font-weight: 800;
            color: #e8a020;
            line-height: 1;
        }

        .stat-pill .lbl {
            font-size: 0.70rem;
            color: rgba(255, 255, 255, 0.50);
            margin-top: 4px;
            line-height: 1.35;
        }

        .uni-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 2.2rem;
            padding: 0.48rem 1rem;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.11);
            color: rgba(255, 255, 255, 0.55);
            font-size: 0.76rem;
            position: relative;
            z-index: 1;
        }

        .uni-badge i {
            color: #e8a020;
        }

        /* RIGHT PANEL */
        .landing-right {
            width: 420px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: #f4f7fb;
        }

        .login-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 40px rgba(26, 58, 108, 0.13);
            padding: 2.2rem 2rem;
            width: 100%;
        }

        .login-card .login-logo {
            text-align: center;
            margin-bottom: 1.4rem;
        }

        .login-card .login-logo .logo-box {
            background: linear-gradient(135deg, #1a3a6c, #2452a0);
            color: #fff;
            width: 58px;
            height: 58px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 0.65rem;
        }

        .login-card h2 {
            font-size: 1.45rem;
            font-weight: 700;
            color: #1a3a6c;
            margin-bottom: 0.15rem;
            text-align: center;
        }

        .login-card .subtitle {
            color: #6b7280;
            font-size: 0.82rem;
            text-align: center;
            margin-bottom: 1.4rem;
        }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .landing-wrapper {
                flex-direction: column;
            }

            .landing-left {
                padding: 2.2rem 1.8rem 2rem;
            }

            .landing-right {
                width: 100%;
                padding: 1.5rem 1rem 2rem;
            }
        }

        @media (max-width: 480px) {
            .landing-left {
                padding: 1.8rem 1.2rem;
            }

            .landing-left .tagline {
                font-size: 1.2rem;
            }

            .stat-pill {
                min-width: 76px;
            }
        }
    </style>
</head>

<body>

    <div class="landing-wrapper">

        <!-- LEFT — INFORMATION PANEL -->
        <div class="landing-left">

            <div class="brand-header">
                <div class="icon-box"><i class="fas fa-shield-halved"></i></div>
                <div>
                    <h1>CertVerify</h1>
                    <span>Blockchain-Based Certificate Validation System</span>
                </div>
            </div>

            <div class="tagline">
                Instant, tamper-proof<br>certificate verification for <em>Zambia</em>
            </div>

            <p class="description">
                CertVerify applies blockchain-based cryptographic hashing to issue and verify
                academic certificates instantly. Every certificate gets a unique SHA-256
                fingerprint and a human-readable verification code — making fraud detection
                fast, reliable, and accessible to any employer across Zambia.
            </p>

            <ul class="feature-list">
                <li>
                    <div class="feat-icon"><i class="fas fa-fingerprint"></i></div>
                    <div>
                        <strong>SHA-256 Cryptographic Hashing</strong>
                        Every certificate is assigned a unique tamper-evident digital fingerprint stored securely on the
                        system.
                    </div>
                </li>
                <li>
                    <div class="feat-icon"><i class="fas fa-id-badge"></i></div>
                    <div>
                        <strong>Human-Readable Verification Code</strong>
                        Each certificate carries a short code (e.g. 105321-2026-A3F) — employers verify in seconds, no
                        technical knowledge needed.
                    </div>
                </li>
                <li>
                    <div class="feat-icon"><i class="fas fa-search"></i></div>
                    <div>
                        <strong>Public Verification Portal</strong>
                        Anyone can verify a certificate without an account — enter the code, student ID, or scan the QR
                        code on the document.
                    </div>
                </li>
                <li>
                    <div class="feat-icon"><i class="fas fa-university"></i></div>
                    <div>
                        <strong>Institution Branding</strong>
                        Each institution issues certificates under their own logo and colors, generating professional
                        branded PDFs.
                    </div>
                </li>
            </ul>

            <div class="stats-row">
                <div class="stat-pill">
                    <div class="num">96.7%</div>
                    <div class="lbl">agree a digital system is useful</div>
                </div>
                <div class="stat-pill">
                    <div class="num">86.7%</div>
                    <div class="lbl">exposed to certificate fraud</div>
                </div>
                <div class="stat-pill">
                    <div class="num">90%</div>
                    <div class="lbl">would use or recommend</div>
                </div>
            </div>

            <div class="uni-badge">
                <i class="fas fa-graduation-cap"></i>
                Cavendish University Zambia &nbsp;·&nbsp; BSc Computer Science &nbsp;·&nbsp; 2026
            </div>

        </div>

        <!-- RIGHT — LOGIN PANEL -->
        <div class="landing-right">
            <div class="login-card">
                <div class="login-logo">
                    <div class="logo-box"><i class="fas fa-shield-halved"></i></div>
                    <h2>Welcome Back</h2>
                    <p class="subtitle">Sign in to access your institution dashboard</p>
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
                            <input type="email" name="email" class="form-control" placeholder="Enter your email"
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autofocus>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" id="passwordField" class="form-control"
                                placeholder="Enter your password" required>
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

                <div style="text-align:center;margin-top:0.8rem;">
                    <a href="public_verify.php" style="color:var(--primary-light);font-size:0.88rem;font-weight:600;">
                        <i class="fas fa-search me-1"></i>Verify a Certificate (Public — No Login Required)
                    </a>
                </div>

                <div style="text-align:center;margin-top:0.7rem;">
                    <span style="font-size:0.72rem;color:#9ca3af;">
                        Secured by SHA-256 &nbsp;·&nbsp; bcrypt &nbsp;·&nbsp; PHP &nbsp;·&nbsp; MySQL
                    </span>
                </div>
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