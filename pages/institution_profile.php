<?php
// pages/institution_profile.php
// Allows institution users to upload their logo and set color preferences
require_once '../config/db.php';
require_once '../includes/header.php';

// Only institution users can access this
if ($userRole !== 'institution') {
    echo '<div class="alert-cv danger"><i class="fas fa-ban me-2"></i>Access denied. This page is for institution accounts only.</div>';
    require_once '../includes/footer.php';
    exit();
}

$success = '';
$error   = '';

// Fetch current branding settings
$stmt = $conn->prepare("SELECT fullName, logoPath, primaryColor, secondaryColor FROM users WHERE userID = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$institution = $stmt->get_result()->fetch_assoc();

$currentLogo      = $institution['logoPath'] ?? '';
$currentPrimary   = $institution['primaryColor'] ?? '#1a3a6c';
$currentSecondary = $institution['secondaryColor'] ?? '#e8a020';

// ---- HANDLE FORM SUBMISSION ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $primaryColor   = $_POST['primaryColor']   ?? '#1a3a6c';
    $secondaryColor = $_POST['secondaryColor'] ?? '#e8a020';

    // Validate hex colors
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryColor)) {
        $error = 'Invalid primary color format.';
    } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $secondaryColor)) {
        $error = 'Invalid secondary color format.';
    } else {
        $newLogoPath = $currentLogo; // keep existing unless new one uploaded

        // ---- HANDLE LOGO UPLOAD ----
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $file     = $_FILES['logo'];
            $fileSize = $file['size'];
            $fileTmp  = $file['tmp_name'];
            $fileType = mime_content_type($fileTmp);

            $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/svg+xml'];
            $maxSize      = 2 * 1024 * 1024; // 2MB

            if (!in_array($fileType, $allowedTypes)) {
                $error = 'Logo must be a PNG, JPG, GIF or SVG image.';
            } elseif ($fileSize > $maxSize) {
                $error = 'Logo file size must be under 2MB.';
            } else {
                // Build upload directory path
                $uploadDir = dirname(__DIR__) . '/uploads/logos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Delete old logo if exists
                if ($currentLogo && file_exists(dirname(__DIR__) . '/' . $currentLogo)) {
                    unlink(dirname(__DIR__) . '/' . $currentLogo);
                }

                // Save new logo with unique name
                $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'logo_' . $_SESSION['user_id'] . '_' . time() . '.' . strtolower($ext);
                $destPath = $uploadDir . $filename;

                if (move_uploaded_file($fileTmp, $destPath)) {
                    $newLogoPath = 'uploads/logos/' . $filename;
                } else {
                    $error = 'Failed to save logo. Check that the uploads/logos/ folder is writable.';
                }
            }
        }

        // ---- SAVE TO DATABASE ----
        if (empty($error)) {
            $update = $conn->prepare("UPDATE users SET logoPath = ?, primaryColor = ?, secondaryColor = ? WHERE userID = ?");
            $update->bind_param("sssi", $newLogoPath, $primaryColor, $secondaryColor, $_SESSION['user_id']);

            if ($update->execute()) {
                $success        = 'Branding settings saved successfully! Your certificates will now use these settings.';
                $currentLogo    = $newLogoPath;
                $currentPrimary = $primaryColor;
                $currentSecondary = $secondaryColor;
                $institution['logoPath']       = $currentLogo;
                $institution['primaryColor']   = $currentPrimary;
                $institution['secondaryColor'] = $currentSecondary;
            } else {
                $error = 'Failed to save settings. Please try again.';
            }
        }
    }
}

// Helper: hex to RGB for preview
function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    return [
        'r' => hexdec(substr($hex,0,2)),
        'g' => hexdec(substr($hex,2,2)),
        'b' => hexdec(substr($hex,4,2)),
    ];
}
?>

<div class="page-header">
    <h2><i class="fas fa-palette me-2" style="color:var(--accent);"></i>Institution Branding</h2>
    <p>Customize how your certificates look — upload your logo and set your institution's colors.</p>
</div>

<?php if ($success): ?>
<div class="alert-cv success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-cv danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="row g-3">

    <!-- LEFT: Settings Form -->
    <div class="col-lg-6">
        <div class="page-card">
            <div class="card-header-cv">
                <h5><i class="fas fa-cog me-2"></i>Branding Settings</h5>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">

                <!-- LOGO UPLOAD -->
                <div class="mb-4">
                    <label class="form-label">Institution Logo</label>

                    <?php if ($currentLogo && file_exists(dirname(__DIR__) . '/' . $currentLogo)): ?>
                    <div style="margin-bottom:0.8rem;padding:0.8rem;background:var(--light-bg);border-radius:8px;display:flex;align-items:center;gap:12px;">
                        <img src="../<?php echo htmlspecialchars($currentLogo); ?>"
                             style="max-height:60px;max-width:120px;object-fit:contain;" alt="Current logo">
                        <div>
                            <div style="font-size:0.85rem;font-weight:600;color:var(--primary);">Current logo</div>
                            <div style="font-size:0.78rem;color:var(--text-muted);">Upload a new one below to replace it</div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/gif,image/svg+xml">
                    <div style="font-size:0.78rem;color:var(--text-muted);margin-top:4px;">
                        Accepted formats: PNG, JPG, GIF, SVG &bull; Max size: 2MB &bull; Recommended: PNG with transparent background
                    </div>
                </div>

                <!-- PRIMARY COLOR -->
                <div class="mb-3">
                    <label class="form-label">Primary Color <span style="font-size:0.78rem;color:var(--text-muted);">(header, borders, text)</span></label>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <input type="color" name="primaryColor" id="primaryColor"
                               value="<?php echo htmlspecialchars($currentPrimary); ?>"
                               style="width:52px;height:42px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;padding:2px;"
                               oninput="updatePreview()">
                        <input type="text" id="primaryHex" value="<?php echo htmlspecialchars($currentPrimary); ?>"
                               class="form-control" style="max-width:120px;font-family:monospace;"
                               oninput="syncColor('primary')"
                               maxlength="7" placeholder="#1a3a6c">
                        <span style="font-size:0.82rem;color:var(--text-muted);">e.g. #1a3a6c for navy</span>
                    </div>
                </div>

                <!-- SECONDARY COLOR -->
                <div class="mb-4">
                    <label class="form-label">Secondary Color <span style="font-size:0.78rem;color:var(--text-muted);">(dividers, accents, highlights)</span></label>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <input type="color" name="secondaryColor" id="secondaryColor"
                               value="<?php echo htmlspecialchars($currentSecondary); ?>"
                               style="width:52px;height:42px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;padding:2px;"
                               oninput="updatePreview()">
                        <input type="text" id="secondaryHex" value="<?php echo htmlspecialchars($currentSecondary); ?>"
                               class="form-control" style="max-width:120px;font-family:monospace;"
                               oninput="syncColor('secondary')"
                               maxlength="7" placeholder="#e8a020">
                        <span style="font-size:0.82rem;color:var(--text-muted);">e.g. #e8a020 for gold</span>
                    </div>
                </div>

                <!-- PRESET PALETTES -->
                <div class="mb-4">
                    <label class="form-label">Quick Presets</label>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        <?php
                        $presets = [
                            ['Navy & Gold',   '#1a3a6c', '#e8a020'],
                            ['Forest & Amber','#1a5c2e', '#f59e0b'],
                            ['Maroon & Gold', '#7c1d1d', '#d4a017'],
                            ['Teal & Orange', '#0d6e6e', '#e85d04'],
                            ['Purple & Silver','#4c1d95','#94a3b8'],
                            ['Black & Red',   '#1a1a1a', '#dc2626'],
                        ];
                        foreach ($presets as [$name, $p, $s]):
                        ?>
                        <button type="button"
                                onclick="applyPreset('<?php echo $p; ?>', '<?php echo $s; ?>')"
                                style="display:flex;align-items:center;gap:6px;padding:5px 10px;border:1.5px solid var(--border);border-radius:20px;background:#fff;cursor:pointer;font-size:0.8rem;font-weight:500;color:var(--text-main);">
                            <span style="width:14px;height:14px;border-radius:50%;background:<?php echo $p; ?>;display:inline-block;flex-shrink:0;"></span>
                            <span style="width:14px;height:14px;border-radius:50%;background:<?php echo $s; ?>;display:inline-block;flex-shrink:0;"></span>
                            <?php echo $name; ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn-primary-cv w-100">
                    <i class="fas fa-save me-2"></i>Save Branding Settings
                </button>
            </form>
        </div>
    </div>

    <!-- RIGHT: Live Certificate Preview -->
    <div class="col-lg-6">
        <div class="page-card">
            <div class="card-header-cv">
                <h5><i class="fas fa-eye me-2"></i>Certificate Preview</h5>
            </div>

            <!-- Mini certificate preview -->
            <div id="certPreview" style="
                border:3px solid <?php echo htmlspecialchars($currentPrimary); ?>;
                border-radius:8px;
                padding:0;
                overflow:hidden;
                box-shadow:0 4px 20px rgba(0,0,0,0.12);
                background:#fff;
                font-family: Georgia, serif;
            ">
                <!-- Header bar -->
                <div id="previewHeader" style="
                    background:<?php echo htmlspecialchars($currentPrimary); ?>;
                    padding:14px 16px;
                    text-align:center;
                ">
                    <?php if ($currentLogo && file_exists(dirname(__DIR__) . '/' . $currentLogo)): ?>
                    <img src="../<?php echo htmlspecialchars($currentLogo); ?>"
                         id="previewLogo"
                         style="max-height:50px;max-width:100px;object-fit:contain;margin-bottom:6px;display:block;margin-left:auto;margin-right:auto;"
                         alt="Logo">
                    <?php else: ?>
                    <div id="previewLogoPlaceholder" style="
                        width:52px;height:52px;border-radius:50%;
                        background:rgba(255,255,255,0.2);
                        margin:0 auto 6px;
                        display:flex;align-items:center;justify-content:center;
                        font-size:1.2rem;color:rgba(255,255,255,0.6);
                    "><i class="fas fa-university"></i></div>
                    <?php endif; ?>
                    <div style="color:#fff;font-size:0.95rem;font-weight:700;letter-spacing:0.5px;">
                        <?php echo htmlspecialchars(strtoupper($institution['fullName'])); ?>
                    </div>
                </div>

                <!-- Certificate body -->
                <div style="padding:16px 20px;text-align:center;">
                    <div id="previewDivider" style="
                        height:2px;
                        background:<?php echo htmlspecialchars($currentSecondary); ?>;
                        width:60px;margin:0 auto 12px;border-radius:2px;
                    "></div>

                    <div style="font-size:0.75rem;color:#888;letter-spacing:1px;text-transform:uppercase;margin-bottom:6px;">Certificate of Completion</div>
                    <div style="font-size:0.72rem;color:#555;font-style:italic;margin-bottom:4px;">This is to certify that</div>
                    <div style="font-size:1.1rem;font-weight:700;color:#1a1a1a;margin:6px 0;">STUDENT FULL NAME</div>

                    <div id="previewUnderline" style="
                        height:1.5px;width:140px;
                        background:<?php echo htmlspecialchars($currentPrimary); ?>;
                        margin:0 auto 8px;
                    "></div>

                    <div style="font-size:0.7rem;color:#666;margin-bottom:4px;">has successfully completed</div>
                    <div id="previewProgram" style="font-size:0.85rem;font-weight:700;color:<?php echo htmlspecialchars($currentPrimary); ?>;">
                        Bachelor of Science in Computer Science
                    </div>

                    <div style="margin-top:14px;display:flex;justify-content:space-around;border-top:1px solid #eee;padding-top:10px;">
                        <div style="text-align:center;">
                            <div style="width:70px;height:1px;background:#999;margin:0 auto 3px;"></div>
                            <div style="font-size:0.65rem;color:#888;">Authorized Signatory</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="width:50px;height:1px;background:#999;margin:0 auto 3px;"></div>
                            <div style="font-size:0.65rem;color:#888;">Date</div>
                        </div>
                    </div>
                </div>

                <!-- Footer bar -->
                <div id="previewFooter" style="
                    background:<?php echo htmlspecialchars($currentSecondary); ?>;
                    height:6px;
                "></div>
            </div>

            <div style="margin-top:0.8rem;font-size:0.78rem;color:var(--text-muted);text-align:center;">
                <i class="fas fa-info-circle me-1"></i>Preview updates live as you change colors above
            </div>
        </div>
    </div>
</div>

<script>
function updatePreview() {
    const primary   = document.getElementById('primaryColor').value;
    const secondary = document.getElementById('secondaryColor').value;

    // Sync hex text inputs
    document.getElementById('primaryHex').value   = primary;
    document.getElementById('secondaryHex').value = secondary;

    // Update preview elements
    document.getElementById('certPreview').style.borderColor         = primary;
    document.getElementById('previewHeader').style.background        = primary;
    document.getElementById('previewDivider').style.background       = secondary;
    document.getElementById('previewUnderline').style.background     = primary;
    document.getElementById('previewProgram').style.color            = primary;
    document.getElementById('previewFooter').style.background        = secondary;
}

function syncColor(which) {
    const hexInput = document.getElementById(which + 'Hex');
    const picker   = document.getElementById(which + 'Color');
    const val = hexInput.value;
    if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
        picker.value = val;
        updatePreview();
    }
}

function applyPreset(primary, secondary) {
    document.getElementById('primaryColor').value   = primary;
    document.getElementById('secondaryColor').value = secondary;
    document.getElementById('primaryHex').value     = primary;
    document.getElementById('secondaryHex').value   = secondary;
    updatePreview();
}
</script>

<?php require_once '../includes/footer.php'; ?>
