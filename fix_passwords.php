<?php
// fix_passwords.php — Multi-Node Version
// Place in certverify/ root, visit once, then DELETE.
require_once 'config/db.php';

$adminHash       = password_hash('admin123',       PASSWORD_BCRYPT);
$institutionHash = password_hash('institution123', PASSWORD_BCRYPT);

$updated = 0;
foreach ($connections as $nodeNum => $c) {
    if (!$c) continue;
    $c->query("UPDATE users SET passwordHash = '$adminHash'       WHERE email = 'admin@certverify.com'");
    $c->query("UPDATE users SET passwordHash = '$institutionHash' WHERE email = 'institution@certverify.com'");
    $updated++;
}

echo '<div style="font-family:sans-serif;padding:30px;max-width:550px;margin:40px auto;
      background:#edfaf3;border:2px solid #18a05a;border-radius:12px;">
    <h2 style="color:#18a05a;">✅ Passwords Fixed on All Nodes!</h2>
    <p>Updated on <strong>' . $updated . '</strong> of 3 nodes.</p>
    <table style="width:100%;border-collapse:collapse;font-size:0.95rem;">
        <tr style="border-bottom:1px solid #ccc;">
            <td style="padding:8px;"><strong>Admin</strong></td>
            <td>admin@certverify.com</td>
            <td>admin123</td>
        </tr>
        <tr>
            <td style="padding:8px;"><strong>Institution</strong></td>
            <td>institution@certverify.com</td>
            <td>institution123</td>
        </tr>
    </table>
    <p style="margin-top:1rem;color:#c00;"><strong>⚠️ Delete this file now!</strong></p>
    <a href="index.php" style="display:inline-block;margin-top:1rem;background:#1a3a6c;
       color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;">Go to Login →</a>
</div>';
?>
