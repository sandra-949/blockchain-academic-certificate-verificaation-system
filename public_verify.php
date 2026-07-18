<?php
// public_verify.php - Public Certificate Verification (No login required)
require_once 'config/db.php';

$result = null;
$searchHash = '';
$searchCode = '';
$studentID = '';
$searched = false;
$verifyMethod = 'code'; // 'code' or 'student'

// Get institutions for dropdown
$institutions = $conn->query("SELECT userID, fullName FROM users WHERE role = 'institution' AND status = 'active' ORDER BY fullName");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $searched = true;
    $verifyMethod = $_POST['verifyMethod'] ?? 'code';

    if ($verifyMethod === 'code') {
        // Option A: Verification Code
        $searchCode = trim($_POST['verificationCode'] ?? '');

        if (!empty($searchCode)) {
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
        } else {
            $result = ['status' => 'empty_input'];
        }

    } else {
        // Option B: Student ID + Institution
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
        } else {
            $result = ['status' => 'empty_input'];
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
    <style>
        .method-tab {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border);
            padding-bottom: 0.5rem;
        }

        .method-tab button {
            padding: 0.5rem 1.5rem;
            border: none;
            background: transparent;
            font-weight: 600;
            color: var(--text-muted);
            border-radius: 8px 8px 0 0;
            transition: all 0.2s;
            cursor: pointer;
        }

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

        .verify-result {
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 1rem;
        }

        .verify-result.valid {
            background: #e8f5e9;
            border: 2px solid #4caf50;
        }

        .verify-result.invalid {
            background: #ffebee;
            border: 2px solid #f44336;
        }

        .verify-result .result-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }

        .verify-result h4 {
            font-weight: 700;
            margin-bottom: 0.3rem;
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

        .hash-display {
            background: var(--light-bg);
            padding: 0.8rem 1rem;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.78rem;
            word-break: break-all;
            border: 1px solid var(--border);
        }

        .verify-hero {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 3rem 1rem 2rem;
            text-align: center;
        }

        .verify-hero h1 {
            font-weight: 700;
            font-size: 2.2rem;
        }

        .verify-hero p {
            opacity: 0.9;
            font-size: 1.05rem;
        }
    </style>
</head>

<body>

    <!-- Hero -->
    <div class="verify-hero">
        <div class="container">
            <div
                style="display:inline-flex;align-items:center;gap:12px;background:rgba(255,255,255,0.12);padding:0.5rem 1.2rem;border-radius:50px;margin-bottom:1rem;font-size:0.88rem;">
                <i class="fas fa-shield-halved" style="color:var(--accent);"></i>
                Cavendish University Zambia
            </div>
            <h1><i class="fas fa-certificate me-2" style="color:var(--accent);"></i>Certificate Verification</h1>
            <p>Verify academic certificates instantly using the verification code or student details</p>
        </div>
    </div>

    <div class="container" style="max-width:720px;padding:2rem 1rem;">

        <!-- Verification Form -->
        <div class="page-card">
            <!-- Tabs -->
            <div class="method-tab">
                <button class="active" onclick="switchMethod('code')" id="tabCode">
                    <i class="fas fa-qrcode me-1"></i> Verification Code
                </button>
                <button onclick="switchMethod('student')" id="tabStudent">
                    <i class="fas fa-user-graduate me-1"></i> Student ID + Institution
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

                <!-- Option B: Student ID + Institution -->
                <div id="panelStudent" class="method-panel">
                    <label class="form-label" style="font-size:1rem;font-weight:600;color:var(--primary);">
                        <i class="fas fa-user-graduate me-2"></i>Student Details
                    </label>
                    <div class="mb-3">
                        <label class="form-label">Student ID</label>
                        <input type="text" name="studentID" class="form-control" placeholder="e.g. 105321"
                            value="<?php echo htmlspecialchars($studentID); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Institution</label>
                        <select name="institutionID" class="form-select">
                            <option value="">-- Select Institution --</option>
                            <?php while ($inst = $institutions->fetch_assoc()): ?>
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
                        <div style="margin-top:1rem;">
                            <label class="form-label">SHA-256 Hash</label>
                            <div class="hash-display">
                                <?php echo htmlspecialchars($result['cert']['hashValue']); ?>
                            </div>
                        </div>
                    </div>

                <?php elseif ($result['status'] === 'revoked'): ?>
                    <div class="verify-result invalid">
                        <div class="result-icon">🚫</div>
                        <h4>Certificate Has Been Revoked</h4>
                        <p style="color:var(--danger);">This certificate has been revoked by the issuing institution and is no
                            longer valid.</p>
                    </div>

                <?php elseif ($result['status'] === 'not_found'): ?>
                    <div class="verify-result invalid">
                        <div class="result-icon">❌</div>
                        <h4>Certificate Not Found</h4>
                        <p style="color:var(--danger);">No certificate matches the provided details. This document may be
                            forged.</p>
                    </div>

                <?php elseif ($result['status'] === 'empty_input'): ?>
                    <div class="verify-result invalid">
                        <div class="result-icon">⚠️</div>
                        <h4>Please Fill in All Fields</h4>
                        <p style="color:var(--danger);">Please enter the required information before verifying.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Info Box -->
        <div class="page-card" style="background:linear-gradient(135deg,#f4f7fb,#e8eef7);text-align:center;">
            <i class="fas fa-info-circle"
                style="color:var(--primary-light);font-size:1.5rem;margin-bottom:0.7rem;display:block;"></i>
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

    <script>
        function switchMethod(method) {
            // Update hidden field
            document.getElementById('verifyMethod').value = method;

            // Update tabs
            document.getElementById('tabCode').classList.toggle('active', method === 'code');
            document.getElementById('tabStudent').classList.toggle('active', method === 'student');

            // Update panels
            document.getElementById('panelCode').classList.toggle('active', method === 'code');
            document.getElementById('panelStudent').classList.toggle('active', method === 'student');
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>