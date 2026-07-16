<?php
// pages/verify.php - Certificate Verification (logged-in users)
require_once '../config/db.php';
require_once '../includes/header.php';

$result     = null;
$searchHash = '';
$searched   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['hash'])) {
    $searchHash = trim($_POST['hashValue'] ?? $_GET['hash'] ?? '');
    $searched = true;

    if (!empty($searchHash)) {
        // Validate hash format (64 hex chars)
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

                // Log verification transaction
                $ip = $_SERVER['REMOTE_ADDR'];
                $verStatus = ($cert['status'] === 'valid') ? 'valid' : 'revoked';
                $log = $conn->prepare("INSERT INTO transactions (certificateID, verifiedBy, transactionType, verificationStatus, ipAddress) VALUES (?, ?, 'verified', ?, ?)");
                $log->bind_param("iiss", $cert['certificateID'], $_SESSION['user_id'], $verStatus, $ip);
                $log->execute();
            } else {
                $result = ['status' => 'not_found'];
                // Log failed verification
                $ip = $_SERVER['REMOTE_ADDR'];
                $log = $conn->prepare("INSERT INTO transactions (certificateID, verifiedBy, transactionType, verificationStatus, ipAddress) VALUES (NULL, ?, 'verified', 'invalid', ?)");
                $log->bind_param("is", $_SESSION['user_id'], $ip);
                $log->execute();
            }
        }
    }
}
?>

<div class="page-header">
    <h2><i class="fas fa-search-plus me-2" style="color:var(--accent);"></i>Verify Certificate</h2>
    <p>Enter the SHA-256 hash of the certificate to verify its authenticity.</p>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="page-card">
            <div class="card-header-cv">
                <h5><i class="fas fa-hashtag me-2"></i>Enter Certificate Hash</h5>
            </div>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">SHA-256 Certificate Hash</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fingerprint"></i></span>
                        <input type="text" name="hashValue" class="form-control fw-mono"
                               placeholder="e.g. a3f8c2d1e4b7a9f0..."
                               value="<?php echo htmlspecialchars($searchHash); ?>"
                               maxlength="64"
                               style="font-size:0.85rem;letter-spacing:0.5px;"
                               required autofocus>
                    </div>
                    <div style="font-size:0.78rem;color:var(--text-muted);margin-top:4px;">
                        The hash is a 64-character string provided when the certificate was issued.
                    </div>
                </div>
                <button type="submit" class="btn-primary-cv">
                    <i class="fas fa-search me-2"></i>Verify Certificate
                </button>
            </form>
        </div>

        <?php if ($searched && $result): ?>
        <div class="page-card">
            <?php if ($result['status'] === 'valid'): ?>
                <div class="verify-result valid">
                    <div class="result-icon">✅</div>
                    <h4>Certificate is Authentic</h4>
                    <p style="color:var(--success);font-size:0.9rem;">This certificate is valid and has not been tampered with.</p>
                </div>
                <div style="margin-top:1.5rem;">
                    <h6 style="font-weight:700;color:var(--primary);margin-bottom:1rem;">Certificate Details</h6>
                    <div class="cert-detail-row"><span class="label">Student Name</span><span class="value"><?php echo htmlspecialchars($result['cert']['studentName']); ?></span></div>
                    <div class="cert-detail-row"><span class="label">Student ID</span><span class="value"><?php echo htmlspecialchars($result['cert']['studentID']); ?></span></div>
                    <div class="cert-detail-row"><span class="label">Program</span><span class="value"><?php echo htmlspecialchars($result['cert']['program']); ?></span></div>
                    <div class="cert-detail-row"><span class="label">Date Issued</span><span class="value"><?php echo date('d F Y', strtotime($result['cert']['dateIssued'])); ?></span></div>
                    <div class="cert-detail-row"><span class="label">Issued By</span><span class="value"><?php echo htmlspecialchars($result['cert']['issuedByName']); ?></span></div>
                    <div class="cert-detail-row"><span class="label">Status</span><span class="value"><span class="badge-valid">Valid</span></span></div>
                    <div style="margin-top:1rem;">
                        <label class="form-label">Certificate Hash</label>
                        <div class="hash-display"><?php echo htmlspecialchars($result['cert']['hashValue']); ?></div>
                    </div>
                </div>

            <?php elseif ($result['status'] === 'revoked'): ?>
                <div class="verify-result invalid">
                    <div class="result-icon">🚫</div>
                    <h4>Certificate Has Been Revoked</h4>
                    <p style="color:var(--danger);font-size:0.9rem;">This certificate exists but has been revoked by the issuing institution.</p>
                </div>
                <div style="margin-top:1.5rem;">
                    <div class="cert-detail-row"><span class="label">Student Name</span><span class="value"><?php echo htmlspecialchars($result['cert']['studentName']); ?></span></div>
                    <div class="cert-detail-row"><span class="label">Program</span><span class="value"><?php echo htmlspecialchars($result['cert']['program']); ?></span></div>
                    <div class="cert-detail-row"><span class="label">Status</span><span class="value"><span class="badge-revoked">Revoked</span></span></div>
                </div>

            <?php elseif ($result['status'] === 'not_found'): ?>
                <div class="verify-result invalid">
                    <div class="result-icon">❌</div>
                    <h4>Certificate Not Found</h4>
                    <p style="color:var(--danger);font-size:0.9rem;">No certificate matching this hash was found in the system. This may be a forged or tampered certificate.</p>
                </div>

            <?php elseif ($result['status'] === 'invalid_format'): ?>
                <div class="verify-result invalid">
                    <div class="result-icon">⚠️</div>
                    <h4>Invalid Hash Format</h4>
                    <p style="color:var(--danger);font-size:0.9rem;">Please enter a valid 64-character SHA-256 hash value.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
