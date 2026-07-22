<?php
// pages/users.php - User Management (Admin only)
require_once '../config/db.php';
require_once '../includes/header.php';

if ($userRole !== 'admin') {
    echo '<div class="alert-cv danger"><i class="fas fa-ban me-2"></i>Access denied. Admins only.</div>';
    require_once '../includes/footer.php';
    exit();
}

$success = '';
$error = '';

// Add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $fullName = trim($_POST['fullName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'employer';

    if (empty($fullName) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (fullName, email, passwordHash, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $fullName, $email, $hash, $role);
        if ($stmt->execute()) {
            $success = "User '$fullName' added successfully.";
        } else {
            $error = 'Email already exists or failed to add user.';
        }
    }
}

// Toggle user status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle') {
    $uid = intval($_POST['userID']);
    $newStatus = $_POST['newStatus'];
    if ($uid !== intval($_SESSION['user_id'])) { // Can't deactivate yourself
        $conn->query("UPDATE users SET status='$newStatus' WHERE userID=$uid");
    }
    header("Location: users.php");
    exit();
}

$users = $conn->query("SELECT * FROM users ORDER BY dateCreated DESC");
?>

<div class="page-header">
    <h2><i class="fas fa-users me-2" style="color:var(--accent);"></i>User Management</h2>
    <p>Manage system users and their access roles.</p>
</div>

<?php if ($success): ?>
    <div class="alert-cv success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert-cv danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="row g-3">
    <!-- Add User Form -->
    <div class="col-lg-4">
        <div class="page-card">
            <div class="card-header-cv">
                <h5><i class="fas fa-user-plus me-2"></i>Add New User</h5>
            </div>
            <form method="POST" action="" id="addUserForm">
                <input type="hidden" name="action" value="add">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="fullName" class="form-control" placeholder="Full name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="Email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Min. 6 characters"
                        required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Role</label>
                    <select name="role" id="roleSelect" class="form-select" onchange="updateAddButtonLabel()">
                        <option value="employer">Employer / Verifier</option>
                        <option value="institution">Institution</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary-cv w-100" id="addUserBtn">
                    <i class="fas fa-plus me-2"></i>Add Employer
                </button>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="col-lg-8">
        <div class="page-card">
            <div class="card-header-cv">
                <h5><i class="fas fa-list me-2"></i>All Users</h5>
            </div>
            
    <div class="table-responsive-cv">
    <table class="table-cv">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($u = $users->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($u['fullName']); ?></strong></td>
                                <td style="font-size:0.85rem;"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>
                                    <span
                                        class="badge-<?php echo $u['role'] === 'admin' ? 'pending' : ($u['role'] === 'institution' ? 'valid' : 'revoked'); ?>"
                                        style="<?php echo $u['role'] === 'employer' ? 'background:#f0f0ff;color:#6b21a8;' : ''; ?>">
                                        <?php echo ucfirst($u['role']); ?>
                                    </span>
                                </td>
                                <td style="font-size:0.82rem;"><?php echo date('d M Y', strtotime($u['dateCreated'])); ?>
                                </td>
                                <td>
                                    <?php if ($u['status'] === 'active'): ?>
                                        <span class="badge-valid">Active</span>
                                    <?php else: ?>
                                        <span class="badge-revoked">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['userID'] !== intval($_SESSION['user_id'])): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="userID" value="<?php echo $u['userID']; ?>">
                                            <input type="hidden" name="newStatus"
                                                value="<?php echo $u['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                            <button type="submit"
                                                style="background:none;border:none;cursor:pointer;font-size:0.8rem;font-weight:600;color:<?php echo $u['status'] === 'active' ? 'var(--danger)' : 'var(--success)'; ?>">
                                                <?php echo $u['status'] === 'active' ? '<i class="fas fa-ban"></i> Deactivate' : '<i class="fas fa-check"></i> Activate'; ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size:0.78rem;color:var(--text-muted);">You</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    function updateAddButtonLabel() {
        var role = document.getElementById('roleSelect').value;
        var btn = document.getElementById('addUserBtn');
        var labels = {
            'employer': 'Add Employer',
            'institution': 'Add Institution',
            'admin': 'Add Administrator'
        };
        btn.innerHTML = '<i class="fas fa-plus me-2"></i>' + (labels[role] || 'Add User');
    }
    // Set the correct label on page load too, in case the browser remembers a previous selection
    document.addEventListener('DOMContentLoaded', updateAddButtonLabel);
</script>

<?php require_once '../includes/footer.php'; ?>