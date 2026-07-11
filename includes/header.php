<?php
// includes/header.php
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
$userName  = $_SESSION['user_name'] ?? 'User';
$userRole  = $_SESSION['user_role'] ?? 'employer';
$userEmail = $_SESSION['user_email'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CertVerify - <?php echo ucfirst(str_replace(['_','.php'],['  ',' '], $currentPage)); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>

<div class="sidebar">
    <div class="brand">
        <span class="brand-icon">CV</span>
        CertVerify
    </div>
    <nav>
        <a href="dashboard.php" class="<?php echo ($currentPage==='dashboard.php')?'active':''; ?>">
            <i class="fas fa-th-large"></i> Dashboard
        </a>

        <?php if ($userRole === 'institution'): ?>
        <a href="issue_certificate.php" class="<?php echo ($currentPage==='issue_certificate.php')?'active':''; ?>">
            <i class="fas fa-plus-circle"></i> Issue Certificate
        </a>
        <?php endif; ?>

        <a href="certificates.php" class="<?php echo ($currentPage==='certificates.php')?'active':''; ?>">
            <i class="fas fa-certificate"></i> Certificates
        </a>
        <a href="verify.php" class="<?php echo ($currentPage==='verify.php')?'active':''; ?>">
            <i class="fas fa-search-plus"></i> Verify
        </a>

        <?php if ($userRole === 'admin'): ?>
        <a href="users.php" class="<?php echo ($currentPage==='users.php')?'active':''; ?>">
            <i class="fas fa-users"></i> Users
        </a>
        <?php endif; ?>

        <?php if ($userRole === 'institution'): ?>
        <a href="institution_profile.php" class="<?php echo ($currentPage==='institution_profile.php')?'active':''; ?>">
            <i class="fas fa-palette"></i> Branding
        </a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="user-info">Logged in as</div>
        <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
        <div class="user-info" style="margin-top:2px;"><?php echo ucfirst($userRole); ?></div>
        <a href="logout.php" style="display:flex;align-items:center;gap:8px;color:rgba(255,255,255,0.55);font-size:0.82rem;margin-top:0.7rem;transition:color 0.2s;"
           onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.55)'">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

<div class="main-content">
