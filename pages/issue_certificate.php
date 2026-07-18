<?php
// pages/issue_certificate.php
require_once '../config/db.php';
require_once '../includes/header.php';

if ($userRole !== 'institution') {
    echo '<div class="alert-cv danger">
        <i class="fas fa-ban me-2"></i>
        <strong>Access Denied.</strong> Only registered institutions can issue certificates.
        ' . ($userRole === 'admin' ? ' Administrators manage the system but do not issue certificates.' : '') . '
    </div>';
    require_once '../includes/footer.php';
    exit();
}

$success = '';
$error = '';
$generatedHash = '';
$generatedCode = '';
$certData = [];
$certID = null;

/**
 * Generate a short human-readable verification code
 * Format: {studentID}-{year}-{5 random alphanumeric chars}
 * Example: 105321-2026-A3F7K
 */
function generateVerificationCode($studentID, $dateIssued)
{
    $year = date('Y', strtotime($dateIssued));
    $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    return $studentID . '-' . $year . '-' . $random;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentName = trim($_POST['studentName'] ?? '');
    $studentID = trim($_POST['studentID'] ?? '');
    $program = trim($_POST['program'] ?? '');
    $dateIssued = $_POST['dateIssued'] ?? '';

    if (empty($studentName) || empty($studentID) || empty($program) || empty($dateIssued)) {
        $error = 'All fields are required.';
    } else {
        // Generate SHA-256 hash
        $dataToHash = $studentName . '|' . $studentID . '|' . $program . '|' . $dateIssued . '|' . time();
        $hashValue = hash('sha256', $dataToHash);

        // Generate human-readable verification code
        $verificationCode = generateVerificationCode($studentID, $dateIssued);

        $check = $conn->prepare("SELECT certificateID FROM certificates WHERE studentID = ? AND program = ?");
        $check->bind_param("ss", $studentID, $program);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'A certificate for this Student ID and Program already exists.';
        } else {
            // Insert with verificationCode
            $stmt = $conn->prepare("INSERT INTO certificates (studentName, studentID, program, dateIssued, hashValue, verificationCode, issuedBy) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssi", $studentName, $studentID, $program, $dateIssued, $hashValue, $verificationCode, $_SESSION['user_id']);

            if ($stmt->execute()) {
                $certID = $conn->insert_id;
                $log = $conn->prepare("INSERT INTO transactions (certificateID, verifiedBy, transactionType, ipAddress) VALUES (?, ?, 'issued', ?)");
                $ip = $_SERVER['REMOTE_ADDR'];
                $log->bind_param("iis", $certID, $_SESSION['user_id'], $ip);
                $log->execute();

                $success = 'Certificate issued successfully!';
                $generatedHash = $hashValue;
                $generatedCode = $verificationCode;
                $certData = compact('studentName', 'studentID', 'program', 'dateIssued');
                $certData['certificateID'] = $certID;
                $certData['verificationCode'] = $verificationCode;
            } else {
                $error = 'Failed to issue certificate. Please try again.';
            }
        }
    }
}
?>

<div class="page-header">
    <h2><i class="fas fa-plus-circle me-2" style="color:var(--accent);"></i>Issue New Certificate</h2>
    <p>Issuing as: <strong><?php echo htmlspecialchars($userName); ?></strong> &mdash; Fill in the student details
        below.</p>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="page-card">
            <div class="card-header-cv">
                <h5><i class="fas fa-user-graduate me-2"></i>Certificate Details</h5>
            </div>

            <?php if ($error): ?>
                <div class="alert-cv danger"><i
                        class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Full Name of Student <span style="color:red">*</span></label>
                    <input type="text" name="studentName" class="form-control" placeholder="e.g. Sandra Iradukunda"
                        value="<?php echo htmlspecialchars($_POST['studentName'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Student ID Number <span style="color:red">*</span></label>
                    <input type="text" name="studentID" class="form-control" placeholder="e.g. 106-142"
                        value="<?php echo htmlspecialchars($_POST['studentID'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Program / Qualification <span style="color:red">*</span></label>
                    <input type="text" name="program" class="form-control"
                        placeholder="e.g. Bachelor of Science in Computer Science"
                        value="<?php echo htmlspecialchars($_POST['program'] ?? ''); ?>" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Date Issued <span style="color:red">*</span></label>
                    <input type="date" name="dateIssued" class="form-control"
                        value="<?php echo htmlspecialchars($_POST['dateIssued'] ?? date('Y-m-d')); ?>" required>
                </div>
                <button type="submit" class="btn-primary-cv w-100">
                    <i class="fas fa-shield-halved me-2"></i>Issue & Generate Hash
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <?php if ($success && $generatedHash): ?>
            <div class="page-card">
                <div class="card-header-cv" style="background:var(--success);color:white;">
                    <h5 style="color:white;"><i class="fas fa-check-circle me-2"></i>Certificate Issued Successfully</h5>
                </div>
                <div class="alert-cv success mb-3">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                </div>

                <!-- VERIFICATION CODE - PROMINENT DISPLAY -->
                <div
                    style="background:linear-gradient(135deg, #f0f7ff, #e3eef9);border-radius:10px;padding:1.2rem;margin-bottom:1.5rem;border:2px solid var(--primary);text-align:center;">
                    <span
                        style="font-size:0.8rem;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);font-weight:600;">Verification
                        Code</span>
                    <div
                        style="font-size:1.8rem;font-weight:700;color:var(--primary);font-family:monospace;letter-spacing:2px;">
                        <?php echo htmlspecialchars($generatedCode); ?>
                    </div>
                    <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.3rem;">
                        <i class="fas fa-info-circle me-1"></i>Use this code for quick verification
                    </div>
                    <button onclick="copyCode('<?php echo $generatedCode; ?>')" class="btn-accent"
                        style="margin-top:0.5rem;padding:0.3rem 1.5rem;font-size:0.8rem;">
                        <i class="fas fa-copy me-1"></i>Copy Code
                    </button>
                </div>

                <div style="margin-bottom:1.2rem;">
                    <div class="cert-detail-row"><span class="label">Student Name</span><span
                            class="value"><?php echo htmlspecialchars($certData['studentName']); ?></span></div>
                    <div class="cert-detail-row"><span class="label">Student ID</span><span
                            class="value"><?php echo htmlspecialchars($certData['studentID']); ?></span></div>
                    <div class="cert-detail-row"><span class="label">Program</span><span
                            class="value"><?php echo htmlspecialchars($certData['program']); ?></span></div>
                    <div class="cert-detail-row"><span class="label">Date Issued</span><span
                            class="value"><?php echo date('d M Y', strtotime($certData['dateIssued'])); ?></span></div>
                    <div class="cert-detail-row"><span class="label">Issued By</span><span
                            class="value"><?php echo htmlspecialchars($userName); ?></span></div>
                </div>

                <label class="form-label">SHA-256 Hash (for reference)</label>
                <div class="hash-display mb-3" style="font-size:0.7rem;word-break:break-all;">
                    <?php echo htmlspecialchars($generatedHash); ?></div>

                <div class="row g-2">
                    <div class="col-6">
                        <a href="../actions/generate_certificate_pdf.php?id=<?php echo $certData['certificateID']; ?>&view=1"
                            target="_blank" class="btn-primary-cv w-100"
                            style="display:block;text-align:center;font-size:0.85rem;">
                            <i class="fas fa-eye me-1"></i>View PDF
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="../actions/generate_certificate_pdf.php?id=<?php echo $certData['certificateID']; ?>"
                            target="_blank" class="btn-primary-cv w-100"
                            style="display:block;text-align:center;background:var(--success);font-size:0.85rem;">
                            <i class="fas fa-download me-1"></i>Download PDF
                        </a>
                    </div>
                </div>

                <a href="issue_certificate.php" class="btn-primary-cv w-100 mt-2"
                    style="display:block;text-align:center;background:var(--light-bg);color:var(--primary);border:1px solid var(--border);">
                    <i class="fas fa-plus me-2"></i>Issue Another Certificate
                </a>
            </div>
        <?php else: ?>
            <div class="page-card" style="background:linear-gradient(135deg,#f4f7fb,#e8eef7);">
                <div style="text-align:center;padding:2rem 1rem;color:var(--text-muted);">
                    <i class="fas fa-shield-halved"
                        style="font-size:3rem;color:var(--border);margin-bottom:1rem;display:block;"></i>
                    <h5 style="font-weight:700;color:var(--primary);margin-bottom:0.5rem;">How It Works</h5>
                    <p style="font-size:0.88rem;line-height:1.7;">
                        When you submit the form, the system generates:
                        <br>
                        <strong style="color:var(--primary);">1. SHA-256 Hash</strong> &mdash; tamper-proof digital
                        fingerprint
                        <br>
                        <strong style="color:var(--primary);">2. Verification Code</strong> &mdash; human-readable code for
                        easy verification
                        <br><br>
                        <span style="font-size:0.8rem;color:var(--text-muted);">Example:
                            <code>105321-2026-A3F7K</code></span>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function copyHash(hash) {
        navigator.clipboard.writeText(hash).then(function () {
            alert('Hash copied to clipboard!\n\n' + hash);
        });
    }

    function copyCode(code) {
        navigator.clipboard.writeText(code).then(function () {
            alert('Verification Code copied!\n\n' + code + '\n\nUse this code to verify the certificate.');
        });
    }
</script>

<?php require_once '../includes/footer.php'; ?>