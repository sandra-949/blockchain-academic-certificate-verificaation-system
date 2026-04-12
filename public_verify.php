<?php
// public_verify.php - Public Certificate Verification (No login required)
require_once 'config/db.php';

$result     = null;
$searchHash = '';
$searched   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $searchHash = trim($_POST['hashValue'] ?? '');
    $searched = true;

    if (!empty($searchHash)) {
        if (!preg_match('/^[a-f0-9]{64}$/i', $searchHash)) {
            $result = ['status' => 'invalid_format'];
        } else {
            $stmt = $conn->prepare("
                SELECT c.*, u.fullName as issuedByName
                FROM certificates c
                JOIN users u ON c.issuedBy = u.userID
                WHERE c.hashValue = ?
            ");
            $stmt->bind_param("s", $searchHash);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows === 1) {
                $cert = $res->fetch_assoc();
                $result = ['status' => $cert['status'], 'cert' => $cert];
                // Log public verification
                $ip = $_SERVER['REMOTE_ADDR'];
                $verStatus = ($cert['status'] === 'valid') ? 'valid' : 'revoked';
                $log = $conn->prepare("INSERT INTO transactions (certificateID, verifiedBy, transactionType, verificationStatus, ipAddress) VALUES (?, NULL, 'verified', ?, ?)");
                $log->bind_param("iss", $cert['certificateID'], $verStatus, $ip);
                $log->execute();
            } else {
                $result = ['status' => 'not_found'];
                $ip = $_SERVER['REMOTE_ADDR'];
                $conn->query("INSERT INTO transactions (certificateID, verifiedBy, transactionType, verificationStatus, ipAddress) VALUES (NULL, NULL, 'verified', 'invalid', '$ip')");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CertVerify - Verify Certificate</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body style="margin:0;padding:0;background:var(--light-bg);">

<!-- Hero -->
<div class="verify-hero">
    <div class="container">
        <div style="display:inline-flex;align-items:center;gap:12px;background:rgba(255,255,255,0.12);padding:0.5rem 1.2rem;border-radius:50px;margin-bottom:1rem;font-size:0.88rem;">
            <i class="fas fa-shield-halved" style="color:var(--accent);"></i>
            Cavendish University Zambia
        </div>
        <h1><i class="fas fa-certificate me-2" style="color:var(--accent);"></i>Certificate Verification</h1>
        <p>Enter the certificate hash to instantly verify its authenticity</p>
    </div>
</div>

<div class="container" style="max-width:680px;padding:2rem 1rem;">

    <!-- Search Form -->
    <div class="page-card">
        <form method="POST" action="">
            <label class="form-label" style="font-size:1rem;font-weight:600;color:var(--primary);">
                <i class="fas fa-fingerprint me-2"></i>Enter Certificate Hash (SHA-256)
            </label>
            <div class="input-group mb-2">
                <input type="text" name="hashValue" class="form-control fw-mono"
                       placeholder="Paste the 64-character certificate hash here..."
                       value="<?php echo htmlspecialchars($searchHash); ?>"
                       style="font-size:0.83rem;letter-spacing:0.5px;"
                       autofocus>
                <button type="submit" class="btn-primary-cv" style="border-radius:0 8px 8px 0;">
                    <i class="fas fa-search me-1"></i> Verify
                </button>
            </div>
            <div style="font-size:0.78rem;color:var(--text-muted);">
                The hash is a 64-character string provided by the issuing institution.
            </div>
        </form>
    </div>

    <!-- Result -->
    <?php if ($searched && $result): ?>
    <div class="page-card">
        <?php if ($result['status'] === 'valid'): ?>
            <div class="verify-result valid">
                <div class="result-icon">✅</div>
                <h4>Certificate is Authentic</h4>
                <p style="color:var(--success);">This certificate is valid and issued by a recognised institution.</p>
            </div>
            <div style="margin-top:1.5rem;">
                <div class="cert-detail-row"><span class="label">Student Name</span><span class="value"><?php echo htmlspecialchars($result['cert']['studentName']); ?></span></div>
                <div class="cert-detail-row"><span class="label">Student ID</span><span class="value"><?php echo htmlspecialchars($result['cert']['studentID']); ?></span></div>
                <div class="cert-detail-row"><span class="label">Program</span><span class="value"><?php echo htmlspecialchars($result['cert']['program']); ?></span></div>
                <div class="cert-detail-row"><span class="label">Date Issued</span><span class="value"><?php echo date('d F Y', strtotime($result['cert']['dateIssued'])); ?></span></div>
                <div class="cert-detail-row"><span class="label">Issued By</span><span class="value"><?php echo htmlspecialchars($result['cert']['issuedByName']); ?></span></div>
                <div style="margin-top:1rem;">
                    <label class="form-label">Certificate Hash</label>
                    <div class="hash-display"><?php echo htmlspecialchars($result['cert']['hashValue']); ?></div>
                </div>
            </div>

        <?php elseif ($result['status'] === 'revoked'): ?>
            <div class="verify-result invalid">
                <div class="result-icon">🚫</div>
                <h4>Certificate Has Been Revoked</h4>
                <p style="color:var(--danger);">This certificate has been revoked by the issuing institution and is no longer valid.</p>
            </div>

        <?php elseif ($result['status'] === 'not_found'): ?>
            <div class="verify-result invalid">
                <div class="result-icon">❌</div>
                <h4>Certificate Not Found</h4>
                <p style="color:var(--danger);">No certificate with this hash exists in the system. This may be a forged document.</p>
            </div>

        <?php elseif ($result['status'] === 'invalid_format'): ?>
            <div class="verify-result invalid">
                <div class="result-icon">⚠️</div>
                <h4>Invalid Hash Format</h4>
                <p style="color:var(--danger);">Please enter a valid 64-character SHA-256 hash.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Info Box -->
    <div class="page-card" style="background:linear-gradient(135deg,#f4f7fb,#e8eef7);text-align:center;">
        <i class="fas fa-info-circle" style="color:var(--primary-light);font-size:1.5rem;margin-bottom:0.7rem;display:block;"></i>
        <p style="font-size:0.88rem;color:var(--text-muted);margin:0;line-height:1.7;">
            This system uses <strong>SHA-256 cryptographic hashing</strong> to verify certificates.
            Each certificate has a unique fingerprint. Any alteration renders it invalid.
        </p>
    </div>

    <div style="text-align:center;margin-top:0.5rem;font-size:0.85rem;">
        <a href="index.php" style="color:var(--primary-light);font-weight:600;">
            <i class="fas fa-lock me-1"></i>Institution Login
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
