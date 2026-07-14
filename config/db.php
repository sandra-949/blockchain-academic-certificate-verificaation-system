<?php
// config/db.php — Multi-Node Blockchain Configuration
// Three independent database nodes simulate a decentralized ledger.
// Certificates are written to ALL nodes on issuance.
// Verification requires consensus across ALL nodes.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Node credentials ──────────────────────────────────────
// All three nodes run on the same MySQL server (XAMPP localhost)
// but are completely separate databases — simulating separate servers.
// In production these would be three different IP addresses/servers.

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');   // your MySQL password if set

$nodes = [
    1 => 'certverify_node1',
    2 => 'certverify_node2',
    3 => 'certverify_node3',
];

// ── Connect to all three nodes ────────────────────────────
$connections = [];
$nodeStatus  = [];

foreach ($nodes as $nodeNum => $dbName) {
    $c = new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName);
    if ($c->connect_error) {
        $nodeStatus[$nodeNum] = 'offline';
        $connections[$nodeNum] = null;
    } else {
        $c->set_charset("utf8");
        $connections[$nodeNum] = $c;
        $nodeStatus[$nodeNum]  = 'online';
    }
}

// Primary connection for auth/user queries (node 1)
$conn = $connections[1];

// ── Minimum nodes required for consensus ─────────────────
define('MIN_CONSENSUS_NODES', 2); // at least 2 of 3 must agree

// ── Blockchain helper functions ───────────────────────────

/**
 * Get the latest block hash from a node (for chaining).
 * Returns the genesis hash if no blocks exist yet.
 */
function getLastBlockHash($conn) {
    $genesis = '0000000000000000000000000000000000000000000000000000000000000000';
    if (!$conn) return $genesis;
    $result = $conn->query("SELECT blockHash FROM certificates ORDER BY blockIndex DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['blockHash'];
    }
    return $genesis;
}

/**
 * Get the next block index from a node.
 */
function getNextBlockIndex($conn) {
    if (!$conn) return 0;
    $result = $conn->query("SELECT MAX(blockIndex) as maxIdx FROM certificates");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return ($row['maxIdx'] === null) ? 0 : (int)$row['maxIdx'] + 1;
    }
    return 0;
}

/**
 * Compute the block hash — combines certificate data + previousHash.
 * This is what links each record to the one before it,
 * making tampering detectable (same as blockchain block hashing).
 */
function computeBlockHash($studentName, $studentID, $program, $dateIssued, $certHash, $previousHash, $blockIndex) {
    $data = implode('|', [$blockIndex, $previousHash, $studentName, $studentID, $program, $dateIssued, $certHash]);
    return hash('sha256', $data);
}

/**
 * Write a certificate to ALL online nodes simultaneously.
 * Returns array: ['success' => bool, 'nodes_written' => int, 'block_index' => int]
 */
function writeToAllNodes($connections, $studentName, $studentID, $program, $dateIssued, $hashValue, $issuedBy) {
    global $nodeStatus;

    $nodesWritten = 0;
    $blockIndex   = null;

    foreach ($connections as $nodeNum => $conn) {
        if (!$conn) continue;

        // Get chain state for this node
        $prevHash   = getLastBlockHash($conn);
        $thisIndex  = getNextBlockIndex($conn);
        $blockHash  = computeBlockHash($studentName, $studentID, $program, $dateIssued, $hashValue, $prevHash, $thisIndex);

        if ($blockIndex === null) $blockIndex = $thisIndex;

        $stmt = $conn->prepare("
            INSERT INTO certificates
                (studentName, studentID, program, dateIssued, hashValue, issuedBy, blockIndex, previousHash, blockHash)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssssiiiss",
            $studentName, $studentID, $program, $dateIssued,
            $hashValue, $issuedBy, $thisIndex, $prevHash, $blockHash
        );

        // Fix: correct bind_param types
        $stmt = $conn->prepare("
            INSERT INTO certificates
                (studentName, studentID, program, dateIssued, hashValue, issuedBy, blockIndex, previousHash, blockHash)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssssiiss",
            $studentName, $studentID, $program, $dateIssued,
            $hashValue, $issuedBy, $thisIndex, $prevHash, $blockHash
        );

        if ($stmt->execute()) {
            $nodesWritten++;
            // Log the issuance transaction on this node
            $certID = $conn->insert_id;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $logStmt = $conn->prepare("
                INSERT INTO transactions (certificateID, verifiedBy, transactionType, ipAddress)
                VALUES (?, ?, 'issued', ?)
            ");
            $logStmt->bind_param("iis", $certID, $issuedBy, $ip);
            $logStmt->execute();
        }
    }

    return [
        'success'      => $nodesWritten >= MIN_CONSENSUS_NODES,
        'nodes_written'=> $nodesWritten,
        'block_index'  => $blockIndex,
    ];
}

/**
 * Verify a certificate across ALL nodes (consensus check).
 * Returns detailed result including which nodes agreed/disagreed.
 */
function verifyAcrossNodes($connections, $hashValue) {
    $results    = [];
    $onlineCount = 0;

    foreach ($connections as $nodeNum => $conn) {
        if (!$conn) {
            $results[$nodeNum] = ['status' => 'node_offline', 'cert' => null];
            continue;
        }
        $onlineCount++;

        $stmt = $conn->prepare("
            SELECT c.*, u.fullName AS institutionName,
                   u.primaryColor, u.secondaryColor, u.logoPath
            FROM certificates c
            JOIN users u ON c.issuedBy = u.userID
            WHERE c.hashValue = ?
        ");
        $stmt->bind_param("s", $hashValue);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 1) {
            $cert = $res->fetch_assoc();
            $results[$nodeNum] = ['status' => $cert['status'], 'cert' => $cert];
        } else {
            $results[$nodeNum] = ['status' => 'not_found', 'cert' => null];
        }
    }

    // Count what each node says
    $statusCounts = [];
    foreach ($results as $r) {
        $s = $r['status'];
        $statusCounts[$s] = ($statusCounts[$s] ?? 0) + 1;
    }

    // Consensus: majority of online nodes agree
    $consensusStatus = null;
    $consensusCert   = null;
    foreach ($statusCounts as $status => $count) {
        if ($count >= MIN_CONSENSUS_NODES && $status !== 'node_offline') {
            $consensusStatus = $status;
            // Get the cert data from a node that agreed
            foreach ($results as $r) {
                if ($r['status'] === $status && $r['cert'] !== null) {
                    $consensusCert = $r['cert'];
                    break;
                }
            }
            break;
        }
    }

    // Detect tampering — nodes disagree on the record
    $activeStatuses = array_filter(array_column($results, 'status'), fn($s) => $s !== 'node_offline');
    $uniqueStatuses = array_unique($activeStatuses);
    $tamperingDetected = count($uniqueStatuses) > 1;

    return [
        'node_results'       => $results,
        'consensus_status'   => $consensusStatus,
        'consensus_cert'     => $consensusCert,
        'tampering_detected' => $tamperingDetected,
        'online_nodes'       => $onlineCount,
        'status_counts'      => $statusCounts,
    ];
}

/**
 * Revoke/restore a certificate across all nodes.
 */
function updateStatusAllNodes($connections, $hashValue, $newStatus, $adminID) {
    $updated = 0;
    foreach ($connections as $nodeNum => $conn) {
        if (!$conn) continue;
        $stmt = $conn->prepare("UPDATE certificates SET status = ? WHERE hashValue = ?");
        $stmt->bind_param("ss", $newStatus, $hashValue);
        if ($stmt->execute()) $updated++;

        // Log it
        $certStmt = $conn->prepare("SELECT certificateID FROM certificates WHERE hashValue = ?");
        $certStmt->bind_param("s", $hashValue);
        $certStmt->execute();
        $certRow = $certStmt->get_result()->fetch_assoc();
        if ($certRow) {
            $cid = $certRow['certificateID'];
            $type = 'revoked';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $logStmt = $conn->prepare("
                INSERT INTO transactions (certificateID, verifiedBy, transactionType, verificationStatus, ipAddress)
                VALUES (?, ?, ?, ?, ?)
            ");
            $logStmt->bind_param("iisss", $cid, $adminID, $type, $newStatus, $ip);
            $logStmt->execute();
        }
    }
    return $updated;
}
