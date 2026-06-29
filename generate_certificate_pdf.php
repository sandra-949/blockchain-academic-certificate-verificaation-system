<?php
/**
 * actions/generate_certificate_pdf.php
 *
 * Generates a downloadable PDF certificate with embedded QR code
 * linking to the public verification page.
 *
 * USAGE: link to this file as:
 *   actions/generate_certificate_pdf.php?id=<certificateID>
 *
 * Place this file inside certverify/actions/
 * Place fpdf.php and qrcode.php inside certverify/lib/
 */

require_once '../config/db.php';
require_once '../lib/fpdf.php';
require_once '../lib/qrcode.php';

// ---- AUTH CHECK ----
if (!isset($_SESSION['user_id'])) {
    die('Access denied. Please log in.');
}

$certID = intval($_GET['id'] ?? 0);
if ($certID <= 0) {
    die('Invalid certificate ID.');
}

// ---- FETCH CERTIFICATE ----
$stmt = $conn->prepare("
    SELECT c.*, u.fullName AS issuedByName
    FROM certificates c
    JOIN users u ON c.issuedBy = u.userID
    WHERE c.certificateID = ?
");
$stmt->bind_param("i", $certID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die('Certificate not found.');
}
$cert = $result->fetch_assoc();

// Institution/employer users may only download their own institution's certs
if ($_SESSION['user_role'] === 'institution' && $cert['issuedBy'] != $_SESSION['user_id']) {
    die('You do not have permission to download this certificate.');
}

// ---- BUILD VERIFICATION URL ----
// IMPORTANT: Update this to match your actual deployed domain
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
$projectPath = '/certverify'; // change if your folder name differs
$verifyUrl = $baseUrl . $projectPath . '/public_verify.php?hash=' . urlencode($cert['hashValue']);

// ---- GENERATE QR CODE MATRIX ----
$qr = QRCode::getMinimumQRCode($verifyUrl, QR_ERROR_CORRECT_LEVEL_L);
$moduleCount = $qr->getModuleCount();

// ---- CUSTOM PDF CLASS WITH BORDER/WATERMARK STYLING ----
class CertificatePDF extends FPDF {
    function Header() {}
    function Footer() {}
}

$pdf = new CertificatePDF('L', 'mm', 'A4'); // Landscape A4
$pdf->AddPage();
$pdf->SetAutoPageBreak(false);

$pageW = 297;
$pageH = 210;

// ---- OUTER DECORATIVE BORDER ----
$pdf->SetLineWidth(1.2);
$pdf->SetDrawColor(26, 58, 108); // primary navy
$pdf->Rect(8, 8, $pageW - 16, $pageH - 16);

$pdf->SetLineWidth(0.4);
$pdf->SetDrawColor(232, 160, 32); // accent gold
$pdf->Rect(12, 12, $pageW - 24, $pageH - 24);

// ---- HEADER: INSTITUTION NAME ----
$pdf->SetFont('Times', 'B', 24);
$pdf->SetTextColor(26, 58, 108);
$pdf->SetY(22);
$pdf->Cell(0, 10, 'CAVENDISH UNIVERSITY ZAMBIA', 0, 1, 'C');

$pdf->SetFont('Times', '', 12);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 7, 'Faculty of Business and Information Technology', 0, 1, 'C');

// ---- DIVIDER ----
$pdf->SetDrawColor(232, 160, 32);
$pdf->SetLineWidth(0.6);
$pdf->Line(110, 42, 187, 42);

// ---- TITLE ----
$pdf->SetY(48);
$pdf->SetFont('Times', 'B', 20);
$pdf->SetTextColor(26, 58, 108);
$pdf->Cell(0, 12, 'CERTIFICATE OF COMPLETION', 0, 1, 'C');

// ---- "This is to certify that" ----
$pdf->SetY(64);
$pdf->SetFont('Times', 'I', 13);
$pdf->SetTextColor(60, 60, 60);
$pdf->Cell(0, 8, 'This is to certify that', 0, 1, 'C');

// ---- STUDENT NAME ----
$pdf->SetY(74);
$pdf->SetFont('Times', 'B', 26);
$pdf->SetTextColor(15, 15, 15);
$pdf->Cell(0, 14, strtoupper($cert['studentName']), 0, 1, 'C');

// underline beneath name
$nameWidth = $pdf->GetStringWidth(strtoupper($cert['studentName'])) + 10;
$centerX = $pageW / 2;
$pdf->SetDrawColor(26, 58, 108);
$pdf->SetLineWidth(0.3);
$pdf->Line($centerX - $nameWidth/2, 90, $centerX + $nameWidth/2, 90);

// ---- "has successfully completed" ----
$pdf->SetY(94);
$pdf->SetFont('Times', '', 13);
$pdf->SetTextColor(60, 60, 60);
$pdf->Cell(0, 8, 'has successfully completed the program of study in', 0, 1, 'C');

// ---- PROGRAM NAME ----
$pdf->SetY(104);
$pdf->SetFont('Times', 'B', 18);
$pdf->SetTextColor(26, 58, 108);
$pdf->MultiCell(0, 9, $cert['program'], 0, 'C');

// ---- DATE & STUDENT ID ----
$pdf->SetY(126);
$pdf->SetFont('Times', '', 11);
$pdf->SetTextColor(60, 60, 60);
$pdf->Cell(0, 6, 'Student ID: ' . $cert['studentID'] . '   |   Date Issued: ' . date('d F Y', strtotime($cert['dateIssued'])), 0, 1, 'C');

// ---- STATUS BADGE ----
$statusText = ($cert['status'] === 'valid') ? 'VALID CERTIFICATE' : 'REVOKED CERTIFICATE';
$statusColor = ($cert['status'] === 'valid') ? [24, 160, 90] : [214, 48, 49];
$pdf->SetY(134);
$pdf->SetFont('Times', 'B', 10);
$pdf->SetTextColor($statusColor[0], $statusColor[1], $statusColor[2]);
$pdf->Cell(0, 6, $statusText, 0, 1, 'C');

// ---- SIGNATURE LINE (left) ----
$pdf->SetDrawColor(80, 80, 80);
$pdf->SetLineWidth(0.3);
$pdf->Line(30, 175, 95, 175);
$pdf->SetFont('Times', '', 10);
$pdf->SetTextColor(60, 60, 60);
$pdf->SetXY(30, 177);
$pdf->Cell(65, 5, 'Authorized Signatory', 0, 0, 'C');
$pdf->SetXY(30, 182);
$pdf->SetFont('Times', 'I', 9);
$pdf->Cell(65, 5, $cert['issuedByName'], 0, 0, 'C');

// ---- ISSUE DATE (center-left) ----
$pdf->Line(130, 175, 195, 175);
$pdf->SetFont('Times', '', 10);
$pdf->SetXY(130, 177);
$pdf->Cell(65, 5, 'Date of Issue', 0, 0, 'C');
$pdf->SetXY(130, 182);
$pdf->SetFont('Times', 'I', 9);
$pdf->Cell(65, 5, date('d F Y', strtotime($cert['dateIssued'])), 0, 0, 'C');

// ---- QR CODE (bottom right) ----
$qrX = 235;       // mm position from left
$qrY = 150;       // mm position from top
$qrSize = 32;     // total QR size in mm
$moduleSize = $qrSize / $moduleCount;

$pdf->SetFillColor(0, 0, 0);
for ($row = 0; $row < $moduleCount; $row++) {
    for ($col = 0; $col < $moduleCount; $col++) {
        if ($qr->isDark($row, $col)) {
            $x = $qrX + ($col * $moduleSize);
            $y = $qrY + ($row * $moduleSize);
            $pdf->Rect($x, $y, $moduleSize, $moduleSize, 'F');
        }
    }
}

$pdf->SetFont('Times', '', 7);
$pdf->SetTextColor(100, 100, 100);
$pdf->SetXY($qrX - 4, $qrY + $qrSize + 1);
$pdf->Cell($qrSize + 8, 4, 'Scan to Verify', 0, 0, 'C');

// ---- HASH VALUE (footer, small) ----
$pdf->SetFont('Courier', '', 7);
$pdf->SetTextColor(140, 140, 140);
$pdf->SetXY(20, 195);
$pdf->Cell(0, 4, 'Certificate Hash (SHA-256): ' . $cert['hashValue'], 0, 0, 'L');

// ---- OUTPUT ----
$filename = 'Certificate_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $cert['studentName']) . '.pdf';
$pdf->Output('D', $filename); // 'D' forces download
exit();
