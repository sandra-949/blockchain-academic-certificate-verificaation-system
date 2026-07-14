<?php
// pages/certificates.php - View All Certificates
require_once '../config/db.php';
require_once '../includes/header.php';

// Handle revoke action (admin only)
if (isset($_POST['action']) && $_POST['action'] === 'revoke' && $userRole === 'admin') {
    $certID = intval($_POST['certID']);
    $conn->query("UPDATE certificates SET status='revoked' WHERE certificateID=$certID");
    $log = $conn->prepare("INSERT INTO transactions (certificateID, verifiedBy, transactionType, verificationStatus, ipAddress) VALUES (?, ?, 'revoked', 'revoked', ?)");
    $ip = $_SERVER['REMOTE_ADDR'];
    $log->bind_param("iis", $certID, $_SESSION['user_id'], $ip);
    $log->execute();
    echo '<script>window.location.href="certificates.php?msg=revoked";</script>';
    exit();
}
if (isset($_POST['action']) && $_POST['action'] === 'restore' && $userRole === 'admin') {
    $certID = intval($_POST['certID']);
    $conn->query("UPDATE certificates SET status='valid' WHERE certificateID=$certID");
    echo '<script>window.location.href="certificates.php?msg=restored";</script>';
    exit();
}

// Search & filter
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all';

$where = "WHERE 1=1";

// FIXED: Institution users only see certificates THEY issued.
// Admin sees all. Employer sees all (they verify, not issue).
if ($userRole === 'institution') {
    $where .= " AND c.issuedBy = " . intval($_SESSION['user_id']);
}

if (!empty($search)) {
    $s = $conn->real_escape_string($search);


    $where .= " AND (c.studentName LIKE '%$s%' OR c.studentID LIKE '%$s%' OR c.program LIKE '%$s%' OR c.hashValue LIKE '%$s%')";
}
if ($filter === 'valid')
    $where .= " AND c.status='valid'";
if ($filter === 'revoked')
    $where .= " AND c.status='revoked'";

$certs = $conn->query("
    SELECT c.*, u.fullName as issuedByName
    FROM certificates c
    JOIN users u ON c.issuedBy = u.userID
    $where
    ORDER BY c.createdAt DESC
");
?>

<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h2><i class="fas fa-certificate me-2" style="color:var(--accent);"></i>Certificates</h2>
        <p>
            <?php if ($userRole === 'institution'): ?>
                Certificates issued by <strong><?php echo htmlspecialchars($userName); ?></strong>.
            <?php elseif ($userRole === 'admin'): ?>
                All certificates across all institutions.
            <?php else: ?>
                Browse and verify academic certificates.
            <?php endif; ?>
        </p>
    </div>
    <?php if ($userRole === 'institution'): ?>
        <a href="issue_certificate.php" class="btn-primary-cv">
            <i class="fas fa-plus me-2"></i>Issue New
        </a>
    <?php endif; ?>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert-cv <?php echo $_GET['msg'] === 'revoked' ? 'danger' : 'success'; ?>">
        <i class="fas fa-<?php echo $_GET['msg'] === 'revoked' ? 'ban' : 'check-circle'; ?> me-2"></i>
        Certificate <?php echo $_GET['msg'] === 'revoked' ? 'revoked' : 'restored'; ?> successfully.
    </div>
<?php endif; ?>

<div class="page-card">
    <!-- Search & Filter -->
    <form method="GET" action="" class="row g-2 mb-3">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" name="search" class="form-control"
                    placeholder="Search by name, ID, program or hash..."
                    value="<?php echo htmlspecialchars($search); ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select name="filter" class="form-select">
                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="valid" <?php echo $filter === 'valid' ? 'selected' : ''; ?>>Valid Only</option>
                <option value="revoked" <?php echo $filter === 'revoked' ? 'selected' : ''; ?>>Revoked Only</option>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn-primary-cv w-100">
                <i class="fas fa-filter me-1"></i>Apply
            </button>
        </div>
    </form>

    <div style="overflow-x:auto;">
        <table class="table-cv">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Date Issued</th>
                    <th>Certificate Hash</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($certs && $certs->num_rows > 0): ?>
                    <?php $i = 1;
                    while ($cert = $certs->fetch_assoc()): ?>
                        <tr>
                            <td style="color:var(--text-muted);font-size:0.82rem;"><?php echo $i++; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($cert['studentName']); ?></strong><br>
                                <span style="font-size:0.78rem;color:var(--text-muted);">ID:
                                    <?php echo htmlspecialchars($cert['studentID']); ?></span>
                            </td>
                            <td style="font-size:0.88rem;"><?php echo htmlspecialchars($cert['program']); ?></td>
                            <td style="white-space:nowrap;"><?php echo date('d M Y', strtotime($cert['dateIssued'])); ?></td>
                            <td>
                                <div class="hash-display"
                                    style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                    title="<?php echo htmlspecialchars($cert['hashValue']); ?>">
                                    <?php echo substr($cert['hashValue'], 0, 16); ?>...
                                </div>
                            </td>
                            <td>
                                <?php if ($cert['status'] === 'valid'): ?>
                                    <span class="badge-valid"><i class="fas fa-check me-1"></i>Valid</span>
                                <?php else: ?>
                                    <span class="badge-revoked"><i class="fas fa-ban me-1"></i>Revoked</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <!-- Verify link -->
                                <a href="verify.php?hash=<?php echo urlencode($cert['hashValue']); ?>"
                                    style="font-size:0.8rem;color:var(--primary-light);font-weight:600;margin-right:8px;">
                                    <i class="fas fa-search"></i> Verify
                                </a>

                                <!-- View PDF in browser -->
                                <a href="../actions/generate_certificate_pdf.php?id=<?php echo $cert['certificateID']; ?>&view=1"
                                    target="_blank"
                                    style="font-size:0.8rem;color:var(--teal, #0d9488);font-weight:600;margin-right:8px;">
                                    <i class="fas fa-eye"></i> View
                                </a>

                                <!-- Download PDF -->
                                <a href="../actions/generate_certificate_pdf.php?id=<?php echo $cert['certificateID']; ?>"
                                    target="_blank"
                                    style="font-size:0.8rem;color:var(--success);font-weight:600;margin-right:8px;">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </a>

                                <!-- Revoke/Restore (admin only) -->
                                <?php if ($userRole === 'institution'): ?>
                                    <?php if ($cert['status'] === 'valid'): ?>
                                        <form method="POST" style="display:inline;"
                                            onsubmit="return confirm('Revoke this certificate?')">
                                            <input type="hidden" name="action" value="revoke">
                                            <input type="hidden" name="certID" value="<?php echo $cert['certificateID']; ?>">
                                            <button type="submit"
                                                style="background:none;border:none;color:var(--danger);font-size:0.8rem;font-weight:600;cursor:pointer;">
                                                <i class="fas fa-ban"></i> Revoke
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="certID" value="<?php echo $cert['certificateID']; ?>">
                                            <button type="submit"
                                                style="background:none;border:none;color:var(--success);font-size:0.8rem;font-weight:600;cursor:pointer;">
                                                <i class="fas fa-check"></i> Restore
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center;color:var(--text-muted);padding:2.5rem;">
                            No certificates found.
                            <?php if ($userRole === 'institution'): ?>
                                <a href="issue_certificate.php" style="color:var(--primary-light);font-weight:600;">Issue the
                                    first one →</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>