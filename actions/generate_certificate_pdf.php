<?php
/**
 * actions/generate_certificate_pdf.php
 *
 * Generates a PDF certificate using:
 * - The issuing institution's name (dynamic, from users table)
 * - The institution's uploaded logo (if any)
 * - The institution's primary and secondary color preferences
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

// ---- FETCH CERTIFICATE + INSTITUTION BRANDING ----
$stmt = $conn->prepare("
    SELECT c.*,
           u.fullName       AS institutionName,
           u.logoPath       AS logoPath,
           u.primaryColor   AS primaryColor,
           u.secondaryColor AS secondaryColor
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

// Permission check
if ($_SESSION['user_role'] === 'institution' && $cert['issuedBy'] != $_SESSION['user_id']) {
    die('You do not have permission to download this certificate.');
}

// ---- BRANDING SETTINGS ----
$institutionName = strtoupper($cert['institutionName']);
$logoPath        = $cert['logoPath'] ?? '';
$primaryHex      = $cert['primaryColor']   ?: '#1a3a6c';
$secondaryHex    = $cert['secondaryColor'] ?: '#e8a020';

// Convert hex colors to RGB arrays for FPDF
function hexToRgbArr($hex) {
    $hex = ltrim($hex, '#');
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}
[$pr, $pg, $pb] = hexToRgbArr($primaryHex);   // primary color RGB
[$sr, $sg, $sb] = hexToRgbArr($secondaryHex); // secondary color RGB

// ---- BUILD VERIFICATION URL ----
$baseUrl     = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
$projectPath = '/certverify';
$verifyUrl   = $baseUrl . $projectPath . '/public_verify.php?hash=' . urlencode($cert['hashValue']);

// ---- GENERATE QR CODE ----
$qr          = QRCode::getMinimumQRCode($verifyUrl, QR_ERROR_CORRECT_LEVEL_L);
$moduleCount = $qr->getModuleCount();

// ---- PDF CLASS ----
class CertificatePDF extends FPDF {
    function Header() {}
    function Footer() {}
}

$pdf = new CertificatePDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetAutoPageBreak(false);

$pageW = 297;
$pageH = 210;

// ---- OUTER BORDER (primary color) ----
$pdf->SetLineWidth(1.5);
$pdf->SetDrawColor($pr, $pg, $pb);
$pdf->Rect(8, 8, $pageW - 16, $pageH - 16);

// ---- INNER BORDER (secondary color) ----
$pdf->SetLineWidth(0.5);
$pdf->SetDrawColor($sr, $sg, $sb);
$pdf->Rect(12, 12, $pageW - 24, $pageH - 24);

// ---- HEADER BACKGROUND BAR (primary color) ----
$pdf->SetFillColor($pr, $pg, $pb);
$pdf->Rect(8, 8, $pageW - 16, 38, 'F');

// ---- LOGO + INSTITUTION NAME — centered layout ----
$logoAbsPath = '';
if (!empty($logoPath)) {
    $logoAbsPath = dirname(__DIR__) . '/' . $logoPath;
}

$luminance = (0.299 * $pr + 0.587 * $pg + 0.114 * $pb) / 255;
$textR = $textG = $textB = ($luminance > 0.5) ? 30 : 255;

$hasLogo = false;
if (!empty($logoAbsPath) && file_exists($logoAbsPath)) {
    $imgInfo = getimagesize($logoAbsPath);
    $imgType = strtolower(pathinfo($logoAbsPath, PATHINFO_EXTENSION));
    if (in_array($imgType, ['png', 'jpg', 'jpeg', 'gif']) && $imgInfo) {
        $logoH   = 20;
        $scaledW = min(($imgInfo[0] / $imgInfo[1]) * $logoH, 40);

        // Center logo horizontally
        $logoX = ($pageW - $scaledW) / 2;
        $pdf->Image($logoAbsPath, $logoX, 10, $scaledW, $logoH);
        $hasLogo = true;

        // Institution name centered below logo
        $pdf->SetFont('Times', 'B', 16);
        $pdf->SetTextColor($textR, $textG, $textB);
        $pdf->SetXY(16, 31);
        $pdf->Cell($pageW - 32, 6, $institutionName, 0, 1, 'C');

        $pdf->SetFont('Times', '', 9);
        $pdf->SetXY(16, 38);
        $pdf->Cell($pageW - 32, 4, 'Faculty of Business and Information Technology', 0, 1, 'C');
    }
}

if (!$hasLogo) {
    $pdf->SetFont('Times', 'B', 18);
    $pdf->SetTextColor($textR, $textG, $textB);
    $pdf->SetXY(16, 14);
    $pdf->Cell($pageW - 32, 10, $institutionName, 0, 1, 'C');

    $pdf->SetFont('Times', '', 10);
    $pdf->SetXY(16, 26);
    $pdf->Cell($pageW - 32, 6, 'Faculty of Business and Information Technology', 0, 1, 'C');
}

// ---- SECONDARY COLOR DIVIDER LINE ----
$pdf->SetDrawColor($sr, $sg, $sb);
$pdf->SetLineWidth(0.8);
$pdf->Line(110, 50, 187, 50);

// ---- CERTIFICATE TITLE ----
$pdf->SetY(53);
$pdf->SetFont('Times', 'B', 20);
$pdf->SetTextColor($pr, $pg, $pb);
$pdf->Cell(0, 10, 'CERTIFICATE OF COMPLETION', 0, 1, 'C');

// ---- "This is to certify that" ----
$pdf->SetY(66);
$pdf->SetFont('Times', 'I', 13);
$pdf->SetTextColor(70, 70, 70);
$pdf->Cell(0, 7, 'This is to certify that', 0, 1, 'C');

// ---- STUDENT NAME ----
$pdf->SetY(75);
$pdf->SetFont('Times', 'B', 26);
$pdf->SetTextColor(15, 15, 15);
$pdf->Cell(0, 13, strtoupper($cert['studentName']), 0, 1, 'C');

// Underline in primary color
$nameWidth = $pdf->GetStringWidth(strtoupper($cert['studentName'])) + 10;
$centerX   = $pageW / 2;
$pdf->SetDrawColor($pr, $pg, $pb);
$pdf->SetLineWidth(0.4);
$pdf->Line($centerX - $nameWidth / 2, 89, $centerX + $nameWidth / 2, 89);

// ---- "has successfully completed" ----
$pdf->SetY(92);
$pdf->SetFont('Times', '', 12);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(0, 7, 'has successfully completed the program of study in', 0, 1, 'C');

// ---- PROGRAM NAME (primary color) ----
$pdf->SetY(101);
$pdf->SetFont('Times', 'B', 17);
$pdf->SetTextColor($pr, $pg, $pb);
$pdf->MultiCell(0, 9, $cert['program'], 0, 'C');

// ---- STUDENT ID & DATE ----
$pdf->SetY(122);
$pdf->SetFont('Times', '', 11);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(0, 6, 'Student ID: ' . $cert['studentID'] . '   |   Date Issued: ' . date('d F Y', strtotime($cert['dateIssued'])), 0, 1, 'C');

// ---- STATUS BADGE ----
$statusText  = ($cert['status'] === 'valid') ? 'VALID CERTIFICATE' : 'REVOKED CERTIFICATE';
$statusRgb   = ($cert['status'] === 'valid') ? [24, 160, 90] : [214, 48, 49];
$pdf->SetY(130);
$pdf->SetFont('Times', 'B', 10);
$pdf->SetTextColor($statusRgb[0], $statusRgb[1], $statusRgb[2]);
$pdf->Cell(0, 5, $statusText, 0, 1, 'C');

// ---- SECONDARY COLOR FOOTER BAR ----
$pdf->SetFillColor($sr, $sg, $sb);
$pdf->Rect(8, $pageH - 16, $pageW - 16, 6, 'F');

// ---- SIGNATURE: INSTITUTION ----
$pdf->SetDrawColor(120, 120, 120);
$pdf->SetLineWidth(0.3);
$pdf->Line(28, 170, 100, 170);
$pdf->SetFont('Times', '', 10);
$pdf->SetTextColor(60, 60, 60);
$pdf->SetXY(28, 172);
$pdf->Cell(72, 5, 'Authorized Signatory', 0, 0, 'C');
$pdf->SetXY(28, 178);
$pdf->SetFont('Times', 'I', 9);
$pdf->SetTextColor($pr, $pg, $pb);
$pdf->Cell(72, 5, $cert['institutionName'], 0, 0, 'C');

// ---- DATE ----
$pdf->SetDrawColor(120, 120, 120);
$pdf->Line(128, 170, 200, 170);
$pdf->SetFont('Times', '', 10);
$pdf->SetTextColor(60, 60, 60);
$pdf->SetXY(128, 172);
$pdf->Cell(72, 5, 'Date of Issue', 0, 0, 'C');
$pdf->SetXY(128, 178);
$pdf->SetFont('Times', 'I', 9);
$pdf->Cell(72, 5, date('d F Y', strtotime($cert['dateIssued'])), 0, 0, 'C');

// ---- QR CODE ----
$qrX        = 238;
$qrY        = 142;
$qrSize     = 34;
$moduleSize = $qrSize / $moduleCount;

$pdf->SetFillColor(0, 0, 0);
for ($row = 0; $row < $moduleCount; $row++) {
    for ($col = 0; $col < $moduleCount; $col++) {
        if ($qr->isDark($row, $col)) {
            $pdf->Rect($qrX + ($col * $moduleSize), $qrY + ($row * $moduleSize), $moduleSize, $moduleSize, 'F');
        }
    }
}
$pdf->SetFont('Times', '', 7);
$pdf->SetTextColor(120, 120, 120);
$pdf->SetXY($qrX - 4, $qrY + $qrSize + 1);
$pdf->Cell($qrSize + 8, 4, 'Scan to Verify', 0, 0, 'C');

// ---- HASH FOOTER ----
$pdf->SetFont('Times', '', 6.5);
$pdf->SetTextColor(160, 160, 160);
$pdf->SetXY(18, 192);
$pdf->Cell(0, 4, 'SHA-256: ' . $cert['hashValue'], 0, 0, 'L');

// ---- OUTPUT ----
$filename = 'Certificate_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $cert['studentName']) . '.pdf';
$pdf->Output('D', $filename);
exit();
