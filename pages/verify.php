<?php
// pages/verify.php — Multi-Node Consensus Verification
require_once '../config/db.php';
require_once '../includes/header.php';

$verification = null;
$searchHash   = '';
$searched     = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['hash'])) {
    $searchHash = trim($_POST['hashValue'] ?? $_GET['hash'] ?? '');
    $searched   = true;

    if (!empty($searchHash)) {
        if (!preg_match('/^[a-f0-9]{64}$/i', $searchHash)) {
            $verification = ['error' => 'invalid_format'];
        } else {
            // ── QUERY ALL 3 NODES & REACH CONSENSUS ──────────
            $verification = verifyAcrossNodes($connections, $searchHash);

            // Log the verification event on all online nodes
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $verifierID = $_SESSION['user_id'];
            $consensusStatus = $verification['consensus_status'];
            $logStatus = $consensusStatus === 'valid' ? 'valid' : ($consensusStatus === 'revoked' ? 'revoked' : 'invalid');
            $consensusResult = $verification['tampering_detected'] ? 'disputed' : 'agreed';

            foreach ($connections as $nodeNum => $conn2) {
                if (!$conn2) continue;
                // Find cert on this node
                $cs = $conn2->prepare("SELECT certificateID FROM certificates WHERE hashValue = ?");
                $cs->bind_param("s", $searchHash);
                $cs->execute();
                $cr = $cs->get_result()->fetch_assoc();
                $cid = $cr['certificateID'] ?? null;

                $logStmt = $conn2->prepare("
                    INSERT INTO transactions
                        (certificateID, verifiedBy, transactionType, verificationStatus, consensusResult, ipAddress)
                    VALUES (?, ?, 'verified', ?, ?, ?)
                ");
                $logStmt->bind_param("iisss", $cid, $verifierID, $logStatus, $consensusResult, $ip);
                $logStmt->execute();
            }
        }
    }
}

// Helper: status label & color
function nodeStatusLabel($status) {
    return match($status) {
        'valid'        => ['✅ Valid',       '#166842', '#edfaf3', '#86efac'],
        'revoked'      => ['🚫 Revoked',     '#922b2b', '#fff0f0', '#fca5a5'],
        'not_found'    => ['❌ Not Found',   '#7c2d12', '#fff7ed', '#fed7aa'],
        'node_offline' => ['⚫ Offline',     '#6b7280', '#f3f4f6', '#d1d5db'],
        default        => ['❓ Unknown',     '#374151', '#f9fafb', '#e5e7eb'],
    };
}
?>

<div class="page-header">
    <h2><i class="fas fa-search-plus me-2" style="color:var(--accent);"></i>Verify Certificate</h2>
    <p>Verification queries <strong>all 3 blockchain nodes</strong> and requires consensus to confirm authenticity.</p>
</div>

<!-- Node status -->
<div style="display:flex;gap:8px;margin-bottom:1.2rem;flex-wrap:wrap;">
    <?php foreach([1,2,3] as $n): ?>
    <div style="display:flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;
        background:<?php echo $nodeStatus[$n]==='online'?'#edfaf3':'#fff0f0'; ?>;
        border:1px solid <?php echo $nodeStatus[$n]==='online'?'#86efac':'#fca5a5'; ?>;
        font-size:0.82rem;font-weight:600;
        color:<?php echo $nodeStatus[$n]==='online'?'var(--success)':'var(--danger)'; ?>;">
        <i class="fas fa-server" style="font-size:0.75rem;"></i>
        Node <?php echo $n; ?> — <?php echo ucfirst($nodeStatus[$n]); ?>
    </div>
    <?php endforeach; ?>
</div>

<div class="row justify-content-center">
    <div class="col-lg-9">

        <!-- Search form -->
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
                               placeholder="Paste the 64-character certificate hash here..."
                               value="<?php echo htmlspecialchars($searchHash); ?>"
                               maxlength="64" style="font-size:0.84rem;letter-spacing:0.5px;" autofocus>
                    </div>
                </div>
                <button type="submit" class="btn-primary-cv">
                    <i class="fas fa-network-wired me-2"></i>Verify Across All Nodes
                </button>
            </form>
        </div>

        <?php if ($searched && $verification): ?>

        <?php if (isset($verification['error'])): ?>
        <!-- Invalid format -->
        <div class="page-card">
            <div class="verify-result invalid">
                <div class="result-icon">⚠️</div>
                <h4>Invalid Hash Format</h4>
                <p style="color:var(--danger);">Please enter a valid 64-character SHA-256 hash.</p>
            </div>
        </div>

        <?php else: ?>

        <!-- ── NODE CONSENSUS PANEL ── -->
        <div class="page-card">
            <div class="card-header-cv">
                <h5><i class="fas fa-cubes me-2"></i>Node Consensus Report</h5>
                <span style="font-size:0.8rem;color:var(--text-muted);">
                    <?php echo $verification['online_nodes']; ?>/3 nodes online
                </span>
            </div>

            <!-- Three node results side by side -->
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:1.2rem;">
                <?php foreach($verification['node_results'] as $nodeNum => $nodeResult):
                    [$label, $textColor, $bgColor, $borderColor] = nodeStatusLabel($nodeResult['status']);
                ?>
                <div style="border-radius:10px;border:1.5px solid <?php echo $borderColor; ?>;
                     background:<?php echo $bgColor; ?>;padding:1rem;text-align:center;">
                    <div style="font-size:1.5rem;margin-bottom:4px;">
                        <i class="fas fa-server" style="color:<?php echo $textColor; ?>;font-size:1.1rem;"></i>
                    </div>
                    <div style="font-size:0.85rem;font-weight:700;color:var(--primary);margin-bottom:4px;">Node <?php echo $nodeNum; ?></div>
                    <div style="font-size:0.8rem;font-weight:600;color:<?php echo $textColor; ?>;"><?php echo $label; ?></div>
                    <?php if ($nodeResult['cert']): ?>
                    <div style="font-size:0.72rem;color:var(--text-muted);margin-top:4px;">
                        Block #<?php echo $nodeResult['cert']['blockIndex']; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Consensus verdict banner -->
            <?php if ($verification['tampering_detected']): ?>
            <div style="background:#fef2f2;border:2px solid #dc2626;border-radius:10px;padding:1rem;text-align:center;margin-bottom:1rem;">
                <div style="font-size:1.5rem;">🚨</div>
                <div style="font-weight:700;color:#dc2626;font-size:1rem;">INTEGRITY BREACH DETECTED</div>
                <div style="font-size:0.85rem;color:#7f1d1d;margin-top:4px;">
                    Nodes disagree on this certificate's record. This indicates tampering with one or more nodes. This certificate cannot be trusted.
                </div>
            </div>

            <?php elseif ($verification['consensus_status'] === 'valid'): ?>
            <div class="verify-result valid" style="margin-top:0;">
                <div class="result-icon">✅</div>
                <h4>Consensus Reached — Certificate is Authentic</h4>
                <p style="color:var(--success);font-size:0.9rem;">
                    All <?php echo $verification['online_nodes']; ?> online nodes agree this certificate is valid and untampered.
                </p>
            </div>

            <?php elseif ($verification['consensus_status'] === 'revoked'): ?>
            <div class="verify-result invalid" style="margin-top:0;">
                <div class="result-icon">🚫</div>
                <h4>Consensus Reached — Certificate Revoked</h4>
                <p style="color:var(--danger);font-size:0.9rem;">
                    All nodes agree this certificate has been revoked by the issuing institution.
                </p>
            </div>

            <?php elseif ($verification['consensus_status'] === 'not_found'): ?>
            <div class="verify-result invalid" style="margin-top:0;">
                <div class="result-icon">❌</div>
                <h4>Not Found on Any Node</h4>
                <p style="color:var(--danger);font-size:0.9rem;">
                    No node in the network holds a certificate with this hash. This may be a forged or tampered document.
                </p>
            </div>

            <?php else: ?>
            <div class="verify-result invalid" style="margin-top:0;">
                <div class="result-icon">⚠️</div>
                <h4>Insufficient Nodes Online</h4>
                <p style="color:var(--danger);font-size:0.9rem;">
                    Not enough nodes are online to establish consensus. Minimum <?php echo MIN_CONSENSUS_NODES; ?> required.
                </p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Certificate details (only if valid consensus) -->
        <?php if ($verification['consensus_status'] === 'valid' && $verification['consensus_cert']): ?>
        <?php $cert = $verification['consensus_cert']; ?>
        <div class="page-card">
            <div class="card-header-cv">
                <h5><i class="fas fa-certificate me-2"></i>Certificate Details</h5>
            </div>
            <div class="cert-detail-row"><span class="label">Student Name</span><span class="value"><?php echo htmlspecialchars($cert['studentName']); ?></span></div>
            <div class="cert-detail-row"><span class="label">Student ID</span><span class="value"><?php echo htmlspecialchars($cert['studentID']); ?></span></div>
            <div class="cert-detail-row"><span class="label">Program</span><span class="value"><?php echo htmlspecialchars($cert['program']); ?></span></div>
            <div class="cert-detail-row"><span class="label">Date Issued</span><span class="value"><?php echo date('d F Y', strtotime($cert['dateIssued'])); ?></span></div>
            <div class="cert-detail-row"><span class="label">Issued By</span><span class="value"><?php echo htmlspecialchars($cert['institutionName']); ?></span></div>
            <div class="cert-detail-row"><span class="label">Block Index</span><span class="value">#<?php echo $cert['blockIndex']; ?></span></div>
            <div class="cert-detail-row"><span class="label">Status</span><span class="value"><span class="badge-valid">Valid</span></span></div>
            <div style="margin-top:1rem;">
                <label class="form-label">Certificate Hash</label>
                <div class="hash-display"><?php echo htmlspecialchars($cert['hashValue']); ?></div>
            </div>
            <div style="margin-top:0.7rem;">
                <label class="form-label">Previous Block Hash (Chain Link)</label>
                <div class="hash-display" style="color:var(--text-muted);"><?php echo htmlspecialchars($cert['previousHash']); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
