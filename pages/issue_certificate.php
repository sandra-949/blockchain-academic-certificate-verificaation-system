<?php
// pages/issue_certificate.php — Multi-Node Blockchain Version
require_once '../config/db.php';
require_once '../includes/header.php';

if ($userRole !== 'institution') {
    echo '<div class="alert-cv danger"><i class="fas fa-ban me-2"></i>Access Denied. Only institutions can issue certificates.</div>';
    require_once '../includes/footer.php';
    exit();
}

$success       = '';
$error         = '';
$generatedHash = '';
$certData      = [];
$certID        = null;
$blockResult   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentName = trim($_POST['studentName'] ?? '');
    $studentID   = trim($_POST['studentID']   ?? '');
    $program     = trim($_POST['program']     ?? '');
    $dateIssued  = $_POST['dateIssued']       ?? '';

    if (empty($studentName) || empty($studentID) || empty($program) || empty($dateIssued)) {
        $error = 'All fields are required.';
    } else {
        // Generate SHA-256 certificate hash (unique fingerprint)
        $dataToHash = $studentName . '|' . $studentID . '|' . $program . '|' . $dateIssued . '|' . time();
        $hashValue  = hash('sha256', $dataToHash);

        // Check for duplicate on primary node
        $check = $conn->prepare("SELECT certificateID FROM certificates WHERE studentID = ? AND program = ?");
        $check->bind_param("ss", $studentID, $program);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'A certificate for this Student ID and Program already exists across the network.';
        } else {
            // ── WRITE TO ALL NODES SIMULTANEOUSLY ──────────────
            $blockResult = writeToAllNodes(
                $connections,
                $studentName, $studentID, $program,
                $dateIssued, $hashValue,
                $_SESSION['user_id']
            );

            if ($blockResult['success']) {
                // Get the certID from primary node for PDF link
                $r = $conn->query("SELECT certificateID FROM certificates WHERE hashValue = '$hashValue'");
                $certID = $r->fetch_assoc()['certificateID'] ?? null;

                $success = 'Certificate issued and recorded across ' . $blockResult['nodes_written'] . ' of 3 blockchain nodes.';
                $generatedHash = $hashValue;
                $certData = compact('studentName', 'studentID', 'program', 'dateIssued');
                $certData['certificateID'] = $certID;
            } else {
                $error = 'Failed to achieve network consensus. Only ' . $blockResult['nodes_written'] . ' node(s) responded. Minimum 2 required.';
            }
        }
    }
}
?>

<div class="page-header">
    <h2><i class="fas fa-plus-circle me-2" style="color:var(--accent);"></i>Issue New Certificate</h2>
    <p>Issuing as: <strong><?php echo htmlspecialchars($userName); ?></strong> &mdash;
       Certificate will be recorded on <strong>all 3 blockchain nodes</strong> simultaneously.</p>
</div>

<!-- Node status bar -->
<div style="display:flex;gap:8px;margin-bottom:1.2rem;flex-wrap:wrap;">
    <?php foreach([1,2,3] as $n): ?>
    <div style="display:flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;
        background:<?php echo $nodeStatus[$n]==='online'?'#edfaf3':'#fff0f0'; ?>;
        border:1px solid <?php echo $nodeStatus[$n]==='online'?'#86efac':'#fca5a5'; ?>;
        font-size:0.82rem;font-weight:600;
        color:<?php echo $nodeStatus[$n]==='online'?'var(--success)':'var(--danger)'; ?>;">
        <i class="fas fa-circle" style="font-size:0.5rem;"></i>
        Node <?php echo $n; ?> — <?php echo ucfirst($nodeStatus[$n]); ?>
    </div>
    <?php endforeach; ?>
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
                    <i class="fas fa-network-wired me-2"></i>Issue & Broadcast to All Nodes
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

            <!-- Node broadcast result -->
            <div style="background:var(--light-bg);border-radius:8px;padding:0.9rem;margin-bottom:1rem;">
                <div style="font-size:0.82rem;font-weight:600;color:var(--primary);margin-bottom:8px;">
                    <i class="fas fa-cubes me-2"></i>Blockchain Network Broadcast
                </div>
                <div style="display:flex;gap:6px;">
                    <?php foreach([1,2,3] as $n): ?>
                    <div style="flex:1;text-align:center;padding:8px 4px;border-radius:6px;
                        background:<?php echo ($n <= $blockResult['nodes_written']) ? '#edfaf3' : '#fff0f0'; ?>;
                        border:1px solid <?php echo ($n <= $blockResult['nodes_written']) ? '#86efac' : '#fca5a5'; ?>;">
                        <div style="font-size:1rem;"><?php echo ($n <= $blockResult['nodes_written']) ? '✅' : '❌'; ?></div>
                        <div style="font-size:0.75rem;font-weight:600;color:<?php echo ($n <= $blockResult['nodes_written']) ? 'var(--success)' : 'var(--danger)'; ?>;">Node <?php echo $n; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="font-size:0.78rem;color:var(--text-muted);margin-top:6px;text-align:center;">
                    Block #<?php echo $blockResult['block_index']; ?> added to the chain
                </div>
            </div>

            <div style="margin-bottom:1rem;">
                <div class="cert-detail-row"><span class="label">Student Name</span><span class="value"><?php echo htmlspecialchars($certData['studentName']); ?></span></div>
                <div class="cert-detail-row"><span class="label">Student ID</span><span class="value"><?php echo htmlspecialchars($certData['studentID']); ?></span></div>
                <div class="cert-detail-row"><span class="label">Program</span><span class="value"><?php echo htmlspecialchars($certData['program']); ?></span></div>
                <div class="cert-detail-row"><span class="label">Date Issued</span><span class="value"><?php echo date('d M Y', strtotime($certData['dateIssued'])); ?></span></div>
            </div>

            <label class="form-label">SHA-256 Certificate Hash</label>
            <div class="hash-display mb-3"><?php echo htmlspecialchars($generatedHash); ?></div>

            <button onclick="copyHash('<?php echo $generatedHash; ?>')" class="btn-accent w-100 mb-2">
                <i class="fas fa-copy me-2"></i>Copy Hash
            </button>
            <?php if ($certID): ?>
            <a href="../actions/generate_certificate_pdf.php?id=<?php echo $certID; ?>&view=1"
               target="_blank" class="btn-primary-cv w-100 mb-2" style="display:block;text-align:center;">
                <i class="fas fa-eye me-2"></i>View Certificate (browser)
            </a>
            <a href="../actions/generate_certificate_pdf.php?id=<?php echo $certID; ?>"
               target="_blank" class="btn-primary-cv w-100 mb-2" style="display:block;text-align:center;background:var(--success);">
                <i class="fas fa-download me-2"></i>Download PDF
            </a>
            <?php endif; ?>
            <a href="issue_certificate.php"
               class="btn-primary-cv w-100" style="display:block;text-align:center;background:var(--light-bg);color:var(--primary);border:1px solid var(--border);">
                <i class="fas fa-plus me-2"></i>Issue Another
            </a>
        </div>
        <?php else: ?>
        <div class="page-card" style="background:linear-gradient(135deg,#f4f7fb,#e8eef7);">
            <div style="text-align:center;padding:1.5rem 1rem;color:var(--text-muted);">
                <i class="fas fa-cubes" style="font-size:2.5rem;color:var(--border);margin-bottom:1rem;display:block;"></i>
                <h5 style="font-weight:700;color:var(--primary);margin-bottom:0.5rem;">How the blockchain works</h5>
                <p style="font-size:0.87rem;line-height:1.75;text-align:left;">
                    When you submit a certificate, the system:<br><br>
                    <strong>1.</strong> Generates a unique SHA-256 hash from the student data.<br>
                    <strong>2.</strong> Computes a <em>block hash</em> by combining the certificate hash with the hash of the previous record — chaining them together.<br>
                    <strong>3.</strong> Writes the certificate to <strong>all 3 independent nodes</strong> simultaneously.<br>
                    <strong>4.</strong> Any future verification queries all 3 nodes and requires consensus — if anyone tampers with one node, the others detect it.
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function copyHash(hash) {
    navigator.clipboard.writeText(hash).then(() => alert('Hash copied!\n\n' + hash));
}
</script>

<?php require_once '../includes/footer.php'; ?>
