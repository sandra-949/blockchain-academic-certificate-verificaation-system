<?php
// pages/verify.php - Certificate Verification (logged-in users)
require_once '../config/db.php';
require_once '../includes/header.php';

$result = null;
$searchHash = '';
$searchCode = '';
$searched = false;
$verifyMethod = 'code'; // 'code' or 'hash'

// Get institutions for dropdown (for student ID lookup)
$institutions = $conn->query("SELECT userID, fullName FROM users WHERE role = 'institution' AND status = 'active' ORDER BY fullName");

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['hash']) || isset($_GET['code'])) {
    $searched = true;
    $verifyMethod = $_POST['verifyMethod'] ?? $_GET['method'] ?? 'code';

    // Check if hash is provided via GET (for QR code scanning)
    if (isset($_GET['hash']) && !empty($_GET['hash'])) {
        $searchHash = trim($_GET['hash']);
        $verifyMethod = 'hash';
    }

    // Check if code is provided via GET
    if (isset($_GET['code']) && !empty($_GET['code'])) {
        $searchCode = trim($_GET['code']);
        $verifyMethod = 'code';
    }

    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($verifyMethod === 'code') {
            $searchCode = trim($_POST['verificationCode'] ?? '');
        } else {
            $searchHash = trim($_POST['hashValue'] ?? '');
        }
    }

    // ---- VERIFICATION BY CODE ----
    if ($verifyMethod === 'code' && !empty($searchCode)) {
        $stmt = $conn->prepare("
            SELECT c.*, u.fullName as issuedByName
            FROM certificates c
            JOIN users u ON c.issuedBy = u.userID
            WHERE c.verificationCode = ?
        ");
        $stmt->bind_param("s", $searchCode);
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
            $ip = $_SERVER['REMOTE_ADDR'];
            $log = $conn->prepare("INSERT INTO transactions (certificateID, verifiedBy, transactionType, verificationStatus, ipAddress) VALUES (NULL, ?, 'verified', 'invalid', ?)");
            $log->bind_param("is", $_SESSION['user_id'], $ip);
            $log->execute();
        }
    }

    // ---- VERIFICATION BY HASH ----
    elseif ($verifyMethod === 'hash' && !empty($searchHash)) {
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
                $ip = $_SERVER['REMOTE_ADDR'];
                $log = $conn->prepare("INSERT INTO transactions (certificateID, verifiedBy, transactionType, verificationStatus, ipAddress) VALUES (NULL, ?, 'verified', 'invalid', ?)");
                $log->bind_param("is", $_SESSION['user_id'], $ip);
                $log->execute();
            }
        }
    }

    // ---- STUDENT ID + INSTITUTION VERIFICATION ----
    elseif ($verifyMethod === 'student' && isset($_POST['studentID']) && isset($_POST['institutionID'])) {
        $studentID = trim($_POST['studentID'] ?? '');
        $institutionID = intval($_POST['institutionID'] ?? 0);

        if (!empty($studentID) && $institutionID > 0) {
            $stmt = $conn->prepare("
                SELECT c.*, u.fullName as issuedByName
                FROM certificates c
                JOIN users u ON c.issuedBy = u.userID
                WHERE c.studentID = ? AND c.issuedBy = ?
            ");
            $stmt->bind_param("si", $studentID, $institutionID);
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
                $ip = $_SERVER['REMOTE_ADDR'];
                $log = $conn->prepare("INSERT INTO transactions (certificateID, verifiedBy, transactionType, verificationStatus, ipAddress) VALUES (NULL, ?, 'verified', 'invalid', ?)");
                $log->bind_param("is", $_SESSION['user_id'], $ip);
                $log->execute();
            }
        } else {
            $result = ['status' => 'empty_input'];
        }
    }
}
?>

<div class="page-header">
    <h2><i class="fas fa-search-plus me-2" style="color:var(--accent);"></i>Verify Certificate</h2>
    <p>Verify certificate authenticity using the verification code, hash, or student details.</p>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="page-card">
            <div class="card-header-cv">
                <h5><i class="fas fa-shield-halved me-2"></i>Verification Options</h5>
            </div>

            <!-- Tabs -->
            <div class="method-tab"
                style="display:flex;gap:0.5rem;margin-bottom:1.5rem;border-bottom:2px solid var(--border);padding-bottom:0.5rem;">
                <button class="active" onclick="switchMethod('code')" id="tabCode"
                    style="padding:0.5rem 1.5rem;border:none;background:transparent;font-weight:600;color:var(--text-muted);border-radius:8px 8px 0 0;transition:all 0.2s;cursor:pointer;">
                    <i class="fas fa-qrcode me-1"></i> Verification Code
                </button>
                <button onclick="switchMethod('hash')" id="tabHash"
                    style="padding:0.5rem 1.5rem;border:none;background:transparent;font-weight:600;color:var(--text-muted);border-radius:8px 8px 0 0;transition:all 0.2s;cursor:pointer;">
                    <i class="fas fa-fingerprint me-1"></i> SHA-256 Hash
                </button>
                <button onclick="switchMethod('student')" id="tabStudent"
                    style="padding:0.5rem 1.5rem;border:none;background:transparent;font-weight:600;color:var(--text-muted);border-radius:8px 8px 0 0;transition:all 0.2s;cursor:pointer;">
                    <i class="fas fa-user-graduate me-1"></i> Student ID
                </button>
            </div>

            <form method="POST" action="" id="verifyForm">
                <input type="hidden" name="verifyMethod" id="verifyMethod" value="code">

                <!-- Option A: Verification Code -->
                <div id="panelCode" class="method-panel active">
                    <label class="form-label" style="font-size:1rem;font-weight:600;color:var(--primary);">
                        <i class="fas fa-fingerprint me-2"></i>Enter Verification Code
                    </label>
                    <div class="input-group mb-2">
                        <span class="input-group-text"><i class="fas fa-qrcode"></i></span>
                        <input type="text" name="verificationCode" class="form-control fw-mono"
                            placeholder="e.g. 105321-2026-A3F7K" value="<?php echo htmlspecialchars($searchCode); ?>"
                            style="font-size:0.95rem;letter-spacing:0.5px;" autofocus>
                        <button type="submit" class="btn-primary-cv" style="border-radius:0 8px 8px 0;">
                            <i class="fas fa-search me-1"></i> Verify
                        </button>
                    </div>
                    <div style="font-size:0.78rem;color:var(--text-muted);">
                        <i class="fas fa-info-circle me-1"></i>
                        Enter the verification code printed on the certificate (e.g. <strong>105321-2026-A3F7K</strong>)
                    </div>
                </div>

                <!-- Option B: SHA-256 Hash -->
                <div id="panelHash" class="method-panel">
                    <label class="form-label" style="font-size:1rem;font-weight:600;color:var(--primary);">
                        <i class="fas fa-fingerprint me-2"></i>Enter SHA-256 Hash
                    </label>
                    <div class="input-group mb-2">
                        <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                        <input type="text" name="hashValue" class="form-control fw-mono"
                            placeholder="64-character SHA-256 hash" value="<?php echo htmlspecialchars($searchHash); ?>"
                            maxlength="64" style="font-size:0.85rem;letter-spacing:0.5px;">
                        <button type="submit" class="btn-primary-cv" style="border-radius:0 8px 8px 0;">
                            <i class="fas fa-search me-1"></i> Verify
                        </button>
                    </div>
                    <div style="font-size:0.78rem;color:var(--text-muted);">
                        <i class="fas fa-info-circle me-1"></i>
                        The SHA-256 hash is a 64-character string provided when the certificate was issued.
                    </div>
                </div>

                <!-- Option C: Student ID + Institution -->
                <div id="panelStudent" class="method-panel">
                    <label class="form-label" style="font-size:1rem;font-weight:600;color:var(--primary);">
                        <i class="fas fa-user-graduate me-2"></i>Student Details
                    </label>
                    <div class="mb-3">
                        <label class="form-label">Student ID</label>
                        <input type="text" name="studentID" class="form-control" placeholder="e.g. 105321"
                            value="<?php echo htmlspecialchars($_POST['studentID'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Institution</label>
                        <select name="institutionID" class="form-select">
                            <option value="">-- Select Institution --</option>
                            <?php
                            $instResult = $conn->query("SELECT userID, fullName FROM users WHERE role = 'institution' AND status = 'active' ORDER BY fullName");
                            while ($inst = $instResult->fetch_assoc()):
                                ?>
                                <option value="<?php echo $inst['userID']; ?>">
                                    <?php echo htmlspecialchars($inst['fullName']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary-cv w-100">
                        <i class="fas fa-search me-2"></i>Verify Certificate
                    </button>
                    <div style="font-size:0.78rem;color:var(--text-muted);margin-top:0.8rem;">
                        <i class="fas fa-info-circle me-1"></i>
                        If you know the student's ID and institution, use this option (no code needed)
                    </div>
                </div>
            </form>
        </div>

        <?php if ($searched && $result): ?>
            <div class="page-card">
                <?php if ($result['status'] === 'valid'): ?>
                    <div class="verify-result valid"
                        style="padding:1.5rem;border-radius:12px;text-align:center;margin-bottom:1rem;background:#e8f5e9;border:2px solid #4caf50;">
                        <div class="result-icon" style="font-size:3rem;margin-bottom:0.5rem;">✅</div>
                        <h4 style="font-weight:700;margin-bottom:0.3rem;">Certificate is Authentic</h4>
                        <p style="color:var(--success);font-size:0.9rem;">This certificate is valid and has not been tampered
                            with.</p>
                    </div>
                    <div style="margin-top:1.5rem;">
                        <h6 style="font-weight:700;color:var(--primary);margin-bottom:1rem;">Certificate Details</h6>
                        <div class="cert-detail-row"><span class="label">Student Name</span><span class="value">
                                <?php echo htmlspecialchars($result['cert']['studentName']); ?>
                            </span></div>
                        <div class="cert-detail-row"><span class="label">Student ID</span><span class="value">
                                <?php echo htmlspecialchars($result['cert']['studentID']); ?>
                            </span></div>
                        <div class="cert-detail-row"><span class="label">Program</span><span class="value">
                                <?php echo htmlspecialchars($result['cert']['program']); ?>
                            </span></div>
                        <div class="cert-detail-row"><span class="label">Date Issued</span><span class="value">
                                <?php echo date('d F Y', strtotime($result['cert']['dateIssued'])); ?>
                            </span></div>
                        <div class="cert-detail-row"><span class="label">Issued By</span><span class="value">
                                <?php echo htmlspecialchars($result['cert']['issuedByName']); ?>
                            </span></div>
                        <?php if (!empty($result['cert']['verificationCode'])): ?>
                            <div class="cert-detail-row"
                                style="background:var(--light-bg);padding:0.7rem 0.5rem;border-radius:8px;margin-top:0.5rem;">
                                <span class="label"><i class="fas fa-qrcode me-1"></i>Verification Code</span>
                                <span class="value" style="font-family:monospace;font-weight:700;color:var(--primary);">
                                    <?php echo htmlspecialchars($result['cert']['verificationCode']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <div class="cert-detail-row"><span class="label">Status</span><span class="value"><span
                                    class="badge-valid">Valid</span></span></div>
                        <div style="margin-top:1rem;">
                            <label class="form-label">SHA-256 Hash</label>
                            <div class="hash-display"
                                style="background:var(--light-bg);padding:0.8rem 1rem;border-radius:8px;font-family:'Courier New',monospace;font-size:0.78rem;word-break:break-all;border:1px solid var(--border);">
                                <?php echo htmlspecialchars($result['cert']['hashValue']); ?>
                            </div>
                        </div>
                    </div>

                <?php elseif ($result['status'] === 'revoked'): ?>
                    <div class="verify-result invalid"
                        style="padding:1.5rem;border-radius:12px;text-align:center;margin-bottom:1rem;background:#ffebee;border:2px solid #f44336;">
                        <div class="result-icon" style="font-size:3rem;margin-bottom:0.5rem;">🚫</div>
                        <h4 style="font-weight:700;margin-bottom:0.3rem;">Certificate Has Been Revoked</h4>
                        <p style="color:var(--danger);font-size:0.9rem;">This certificate exists but has been revoked by the
                            issuing institution.</p>
                    </div>
                    <div style="margin-top:1.5rem;">
                        <div class="cert-detail-row"><span class="label">Student Name</span><span class="value">
                                <?php echo htmlspecialchars($result['cert']['studentName']); ?>
                            </span></div>
                        <div class="cert-detail-row"><span class="label">Program</span><span class="value">
                                <?php echo htmlspecialchars($result['cert']['program']); ?>
                            </span></div>
                        <div class="cert-detail-row"><span class="label">Status</span><span class="value"><span
                                    class="badge-revoked">Revoked</span></span></div>
                    </div>

                <?php elseif ($result['status'] === 'not_found'): ?>
                    <div class="verify-result invalid"
                        style="padding:1.5rem;border-radius:12px;text-align:center;margin-bottom:1rem;background:#ffebee;border:2px solid #f44336;">
                        <div class="result-icon" style="font-size:3rem;margin-bottom:0.5rem;">❌</div>
                        <h4 style="font-weight:700;margin-bottom:0.3rem;">Certificate Not Found</h4>
                        <p style="color:var(--danger);font-size:0.9rem;">No certificate matching the provided details was found
                            in the system. This may be a forged or tampered certificate.</p>
                    </div>

                <?php elseif ($result['status'] === 'invalid_format'): ?>
                    <div class="verify-result invalid"
                        style="padding:1.5rem;border-radius:12px;text-align:center;margin-bottom:1rem;background:#ffebee;border:2px solid #f44336;">
                        <div class="result-icon" style="font-size:3rem;margin-bottom:0.5rem;">⚠️</div>
                        <h4 style="font-weight:700;margin-bottom:0.3rem;">Invalid Hash Format</h4>
                        <p style="color:var(--danger);font-size:0.9rem;">Please enter a valid 64-character SHA-256 hash value.
                        </p>
                    </div>

                <?php elseif ($result['status'] === 'empty_input'): ?>
                    <div class="verify-result invalid"
                        style="padding:1.5rem;border-radius:12px;text-align:center;margin-bottom:1rem;background:#ffebee;border:2px solid #f44336;">
                        <div class="result-icon" style="font-size:3rem;margin-bottom:0.5rem;">⚠️</div>
                        <h4 style="font-weight:700;margin-bottom:0.3rem;">Please Fill in All Fields</h4>
                        <p style="color:var(--danger);font-size:0.9rem;">Please enter the required information before verifying.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function switchMethod(method) {
        // Update hidden field
        document.getElementById('verifyMethod').value = method;

        // Update tabs
        document.getElementById('tabCode').classList.toggle('active', method === 'code');
        document.getElementById('tabHash').classList.toggle('active', method === 'hash');
        document.getElementById('tabStudent').classList.toggle('active', method === 'student');

        // Update panels
        document.getElementById('panelCode').classList.toggle('active', method === 'code');
        document.getElementById('panelHash').classList.toggle('active', method === 'hash');
        document.getElementById('panelStudent').classList.toggle('active', method === 'student');
    }

// Handle GET parameters for QR code scanning
<?php if (isset($_GET['hash']) && !empty($_GET['hash'])): ?>
            // If hash is provided via GET, switch to hash tab
            document.addEventListener('DOMContentLoaded', function () {
                switchMethod('hash');
            });
<?php elseif (isset($_GET['code']) && !empty($_GET['code'])): ?>
            // If code is provided via GET, switch to code tab
            document.addEventListener('DOMContentLoaded', function () {
                switchMethod('code');
            });
<?php endif; ?>
</script>

<style>
    .method-tab button.active {
        color: var(--primary);
        background: var(--light-bg);
        border-bottom: 3px solid var(--primary);
    }

    .method-tab button:hover {
        background: var(--light-bg);
    }

    .method-panel {
        display: none;
    }

    .method-panel.active {
        display: block;
    }

    .fw-mono {
        font-family: 'Courier New', monospace;
    }

    .cert-detail-row {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        border-bottom: 1px solid var(--border);
    }

    .cert-detail-row:last-child {
        border-bottom: none;
    }

    .cert-detail-row .label {
        font-weight: 600;
        color: var(--text-muted);
    }

    .cert-detail-row .value {
        font-weight: 500;
    }
</style>

<?php require_once '../includes/footer.php'; ?>