<?php
// pages/dashboard.php
require_once '../config/db.php';
require_once '../includes/header.php';

// Stats
$totalCerts    = $conn->query("SELECT COUNT(*) as c FROM certificates")->fetch_assoc()['c'];
$validCerts    = $conn->query("SELECT COUNT(*) as c FROM certificates WHERE status='valid'")->fetch_assoc()['c'];
$revokedCerts  = $conn->query("SELECT COUNT(*) as c FROM certificates WHERE status='revoked'")->fetch_assoc()['c'];
$totalVerifs   = $conn->query("SELECT COUNT(*) as c FROM transactions WHERE transactionType='verified'")->fetch_assoc()['c'];
$totalUsers    = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];

// Recent certificates
$recentCerts = $conn->query("
    SELECT c.certificateID, c.studentName, c.program, c.dateIssued, c.status, c.hashValue, u.fullName as issuedByName
    FROM certificates c
    JOIN users u ON c.issuedBy = u.userID
    ORDER BY c.createdAt DESC LIMIT 5
");

// Recent transactions
$recentTrans = $conn->query("
    SELECT t.*, c.studentName, c.program, u.fullName as verifierName
    FROM transactions t
    LEFT JOIN certificates c ON t.certificateID = c.certificateID
    LEFT JOIN users u ON t.verifiedBy = u.userID
    ORDER BY t.timestamp DESC LIMIT 5
");
?>

<div class="page-header">
    <h2><i class="fas fa-th-large me-2" style="color:var(--accent);"></i>Dashboard</h2>
    <p>Welcome back, <?php echo htmlspecialchars($userName); ?>. Here's your system overview.</p>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-certificate" style="color:var(--primary);"></i></div>
            <div>
                <div class="stat-number"><?php echo $totalCerts; ?></div>
                <div class="stat-label">Total Certificates</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card success">
            <div class="stat-icon"><i class="fas fa-check-circle" style="color:var(--success);"></i></div>
            <div>
                <div class="stat-number" style="color:var(--success);"><?php echo $validCerts; ?></div>
                <div class="stat-label">Valid Certificates</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card accent">
            <div class="stat-icon"><i class="fas fa-search-plus" style="color:var(--accent);"></i></div>
            <div>
                <div class="stat-number" style="color:var(--accent);"><?php echo $totalVerifs; ?></div>
                <div class="stat-label">Verifications Done</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card danger">
            <div class="stat-icon"><i class="fas fa-ban" style="color:var(--danger);"></i></div>
            <div>
                <div class="stat-number" style="color:var(--danger);"><?php echo $revokedCerts; ?></div>
                <div class="stat-label">Revoked</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Recent Certificates -->
    <div class="col-lg-7">
        <div class="page-card">
            <div class="card-header-cv">
                <h5><i class="fas fa-certificate me-2"></i>Recent Certificates</h5>
                <a href="certificates.php" class="btn-accent" style="font-size:0.82rem;padding:0.4rem 0.9rem;border-radius:6px;">View All</a>
            </div>
           
    <div class="table-responsive-cv">
    <table class="table-cv">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Date Issued</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($recentCerts->num_rows > 0): ?>
                    <?php while($cert = $recentCerts->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($cert['studentName']); ?></strong><br>
                            <span style="font-size:0.75rem;color:var(--text-muted);"><?php echo htmlspecialchars($cert['issuedByName']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($cert['program']); ?></td>
                        <td><?php echo date('d M Y', strtotime($cert['dateIssued'])); ?></td>
                        <td>
                            <?php if ($cert['status'] === 'valid'): ?>
                                <span class="badge-valid"><i class="fas fa-check me-1"></i>Valid</span>
                            <?php else: ?>
                                <span class="badge-revoked"><i class="fas fa-ban me-1"></i>Revoked</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:2rem;">No certificates issued yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <!-- Recent Verifications -->
    <div class="col-lg-5">
        <div class="page-card">
            <div class="card-header-cv">
                <h5><i class="fas fa-history me-2"></i>Recent Activity</h5>
            </div>
            <?php if ($recentTrans->num_rows > 0): ?>
                <?php while($t = $recentTrans->fetch_assoc()): ?>
                <div style="display:flex;align-items:flex-start;gap:10px;padding:0.65rem 0;border-bottom:1px solid var(--border);">
                    <div style="width:34px;height:34px;border-radius:50%;background:<?php echo $t['transactionType']==='issued'?'#eff6ff':($t['verificationStatus']==='valid'?'#edfaf3':'#fff0f0'); ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:0.85rem;">
                        <?php if($t['transactionType']==='issued'): ?>
                            <i class="fas fa-plus" style="color:var(--primary-light);"></i>
                        <?php elseif($t['verificationStatus']==='valid'): ?>
                            <i class="fas fa-check" style="color:var(--success);"></i>
                        <?php else: ?>
                            <i class="fas fa-times" style="color:var(--danger);"></i>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:0.88rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?php echo htmlspecialchars($t['studentName'] ?? 'Unknown'); ?>
                        </div>
                        <div style="font-size:0.78rem;color:var(--text-muted);">
                            <?php echo ucfirst($t['transactionType']); ?> &bull; <?php echo date('d M Y H:i', strtotime($t['timestamp'])); ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align:center;color:var(--text-muted);padding:2rem;font-size:0.9rem;">No activity yet.</div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="page-card">
            <div class="card-header-cv">
                <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
            </div>
            <?php if ($userRole === 'admin' || $userRole === 'institution'): ?>
            <a href="issue_certificate.php" class="btn-primary-cv w-100 mb-2" style="display:block;text-align:center;">
                <i class="fas fa-plus-circle me-2"></i>Issue New Certificate
            </a>
            <?php endif; ?>
            <a href="verify.php" class="btn-accent w-100" style="display:block;text-align:center;">
                <i class="fas fa-search-plus me-2"></i>Verify a Certificate
            </a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
