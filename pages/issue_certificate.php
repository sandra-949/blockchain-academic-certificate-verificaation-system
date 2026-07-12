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
$error   = '';
$generatedHash = '';
$certData = [];
$certID = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentName = trim($_POST['studentName'] ?? '');
    $studentID   = trim($_POST['studentID'] ?? '');
    $program     = trim($_POST['program'] ?? '');
    $dateIssued  = $_POST['dateIssued'] ?? '';

    if (empty($studentName) || empty($studentID) || empty($program) || empty($dateIssued)) {
        $error = 'All fields are required.';
    } else {
        $dataToHash = $studentName . '|' . $studentID . '|' . $program . '|' . $dateIssued . '|' . time();
        $hashValue  = hash('sha256', $dataToHash);

        $check = $conn->prepare("SELECT certificateID FROM certificates WHERE studentID = ? AND program = ?");
        $check->bind_param("ss", $studentID, $program);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'A certificate for this Student ID and Program already exists.';
        } else {
            $stmt = $conn->prepare("INSERT INTO certificates (studentName, studentID, program, dateIssued, hashValue, issuedBy) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $studentName, $studentID, $program, $dateIssued, $hashValue, $_SESSION['user_id']);

            if ($stmt->execute()) {
                $certID = $conn->insert_id;
                $log = $conn->prepare("INSERT INTO transactions (certificateID, verifiedBy, transactionType, ipAddress) VALUES (?, ?, 'issued', ?)");
                $ip = $_SERVER['REMOTE_ADDR'];
                $log->bind_param("iis", $certID, $_SESSION['user_id'], $ip);
                $log->execute();

                $success = 'Certificate issued successfully!';
                $generatedHash = $hashValue;
                $certData = compact('studentName', 'studentID', 'program', 'dateIssued');
                $certData['certificateID'] = $certID;
            } else {
                $error = 'Failed to issue certificate. Please try again.';
            }
        }
    }
}
?>

<div class="page-header">
    <h2><i class="fas fa-plus-circle me-2" style="color:var(--accent);"></i>Issue New Certificate</h2>
    <p>Issuing as: <strong><?php echo htmlspecialchars($userName); ?></strong> &mdash; Fill in the student details below.</p>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="page-card">
            <div class="card-header-cv">
                <h5><i class="fas fa-user-graduate me-2"></i>Certificate Details</h5>
            </div>

            <?php if ($error): ?>
            <div class="alert-cv danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Full Name of Student <span style="color:red">*</span></label>
                    <input type="text" name="studentName" class="form-control"
                           placeholder="e.g. Sandra Iradukunda"
                           value="<?php echo htmlspecialchars($_POST['studentName'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Student ID Number <span style="color:red">*</span></label>
                    <input type="text" name="studentID" class="form-control"
                           placeholder="e.g. 106-142"
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
            <div class="card-header-cv">
                <h5><i class="fas fa-check-circle me-2" style="color:var(--success);"></i>Certificate Issued Successfully</h5>
            </div>
            <div class="alert-cv success mb-3">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            </div>

            <div style="margin-bottom:1.2rem;">
                <div class="cert-detail-row"><span class="label">Student Name</span><span class="value"><?php echo htmlspecialchars($certData['studentName']); ?></span></div>
                <div class="cert-detail-row"><span class="label">Student ID</span><span class="value"><?php echo htmlspecialchars($certData['studentID']); ?></span></div>
                <div class="cert-detail-row"><span class="label">Program</span><span class="value"><?php echo htmlspecialchars($certData['program']); ?></span></div>
                <div class="cert-detail-row"><span class="label">Date Issued</span><span class="value"><?php echo date('d M Y', strtotime($certData['dateIssued'])); ?></span></div>
                <div class="cert-detail-row"><span class="label">Issued By</span><span class="value"><?php echo htmlspecialchars($userName); ?></span></div>
            </div>

            <label class="form-label">Generated SHA-256 Hash</label>
            <div class="hash-display mb-3"><?php echo htmlspecialchars($generatedHash); ?></div>

            <button onclick="copyHash('<?php echo $generatedHash; ?>')" class="btn-accent w-100 mb-2">
                <i class="fas fa-copy me-2"></i>Copy Hash
            </button>

            <!-- View in browser -->
            <a href="../actions/generate_certificate_pdf.php?id=<?php echo $certData['certificateID']; ?>&view=1"
               target="_blank"
               class="btn-primary-cv w-100 mb-2"
               style="display:block;text-align:center;">
                <i class="fas fa-eye me-2"></i>View Certificate (opens in browser)
            </a>

            <!-- Force download -->
            <a href="../actions/generate_certificate_pdf.php?id=<?php echo $certData['certificateID']; ?>"
               target="_blank"
               class="btn-primary-cv w-100 mb-2"
               style="display:block;text-align:center;background:var(--success);">
                <i class="fas fa-download me-2"></i>Download Certificate PDF
            </a>

            <a href="issue_certificate.php"
               class="btn-primary-cv w-100"
               style="display:block;text-align:center;background:var(--light-bg);color:var(--primary);border:1px solid var(--border);">
                <i class="fas fa-plus me-2"></i>Issue Another Certificate
            </a>
        </div>
        <?php else: ?>
        <div class="page-card" style="background:linear-gradient(135deg,#f4f7fb,#e8eef7);">
            <div style="text-align:center;padding:2rem 1rem;color:var(--text-muted);">
                <i class="fas fa-shield-halved" style="font-size:3rem;color:var(--border);margin-bottom:1rem;display:block;"></i>
                <h5 style="font-weight:700;color:var(--primary);margin-bottom:0.5rem;">How It Works</h5>
                <p style="font-size:0.88rem;line-height:1.7;">
                    When you submit the form, the system generates a unique <strong>SHA-256 cryptographic hash</strong>
                    from the certificate data. This hash acts as a tamper-proof digital fingerprint stored securely
                    in the database. You can then view the certificate in your browser or download it as a PDF.
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function copyHash(hash) {
    navigator.clipboard.writeText(hash).then(function() {
        alert('Hash copied to clipboard!\n\n' + hash);
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
