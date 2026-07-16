<?php
// fix_passwords.php
// Place this in your certverify/ root folder
// Visit: http://localhost/certverify/fix_passwords.php
// DELETE THIS FILE after running it!

require_once 'config/db.php';

$adminHash = password_hash('admin123', PASSWORD_BCRYPT);
$institutionHash = password_hash('institution123', PASSWORD_BCRYPT);

$conn->query("UPDATE users SET passwordHash = '$adminHash' WHERE email = 'admin@certverify.com'");
$conn->query("UPDATE users SET passwordHash = '$institutionHash' WHERE email = 'institution@certverify.com'");

echo '<div style="font-family:sans-serif;padding:30px;max-width:500px;margin:40px auto;background:#edfaf3;border:2px solid #18a05a;border-radius:12px;">
    <h2 style="color:#18a05a;">✅ Passwords Fixed!</h2>
    <p>Passwords have been reset successfully.</p>
    <table style="width:100%;border-collapse:collapse;font-size:0.95rem;">
        <tr style="border-bottom:1px solid #ccc;"><td style="padding:8px;"><strong>Admin</strong></td><td style="padding:8px;">admin@certverify.com</td><td style="padding:8px;">admin123</td></tr>
        <tr><td style="padding:8px;"><strong>Institution</strong></td><td style="padding:8px;">institution@certverify.com</td><td style="padding:8px;">institution123</td></tr>
    </table>
    <p style="margin-top:1rem;color:#c00;"><strong>⚠️ Please delete this file (fix_passwords.php) now!</strong></p>
    <a href="index.php" style="display:inline-block;margin-top:1rem;background:#1a3a6c;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;">Go to Login →</a>
</div>';
?>