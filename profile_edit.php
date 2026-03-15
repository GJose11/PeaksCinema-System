<?php
session_start();
include("peakscinemas_database.php");

// Handle logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
    header("Location: personal_info_form.php?logged_out=1");
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header("Location: personal_info_form.php");
    exit;
}

$stmt = $conn->prepare("SELECT Name, Email, PhoneNumber, Password, ProfilePhoto FROM customer WHERE Customer_ID = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_unset(); session_destroy();
    header("Location: personal_info_form.php?error=user_not_found");
    exit;
}

$user = $result->fetch_assoc();

$profile_photo = $user['ProfilePhoto'] ?? $_SESSION['profile_photo'] ?? null;
$nameParts     = explode(' ', trim($user['Name'] ?? ''));
$user_initials = strtoupper(substr($nameParts[0]??'',0,1) . substr(end($nameParts)??'',0,1));
if (strlen($user_initials) === 1) $user_initials = strtoupper(substr($nameParts[0]??'',0,2));

$message      = '';
$message_type = 'ok'; // 'ok' or 'err'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $password = trim($_POST['password'] ?? '');

    $errors = [];
    if (empty($name) || strlen($name) > 100)          $errors[] = "Name must not be empty or longer than 100 characters.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $errors[] = "Invalid email format.";
    if (!preg_match('/^[0-9]{1,10}$/', $phone))        $errors[] = "Phone number must be 1–10 digits.";
    if (!empty($password) && strlen($password) < 6)   $errors[] = "Password must be at least 6 characters.";

    if (!empty($errors)) {
        $message      = implode('<br>', $errors);
        $message_type = 'err';
    } else {
        $hashedPassword = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : $user['Password'];
        $upd = $conn->prepare("UPDATE customer SET Name=?, Email=?, PhoneNumber=?, Password=? WHERE Customer_ID=?");
        $upd->bind_param("ssssi", $name, $email, $phone, $hashedPassword, $_SESSION['user_id']);
        if ($upd->execute()) {
            $message      = "Profile updated successfully!";
            $message_type = 'ok';
            $user['Name']        = $name;
            $user['Email']       = $email;
            $user['PhoneNumber'] = $phone;
            // refresh initials
            $np = explode(' ', trim($name));
            $user_initials = strtoupper(substr($np[0]??'',0,1).substr(end($np)??'',0,1));
            if (strlen($user_initials)===1) $user_initials = strtoupper(substr($np[0]??'',0,2));
        } else {
            $message      = "Error updating profile. Please try again.";
            $message_type = 'err';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
<title>Edit Profile – Peak's Cinema</title>
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Outfit', sans-serif;
    background: #0f0f0f;
    color: #F9F9F9;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}
body::before {
    content: '';
    position: fixed; inset: 0;
    background: url('movie-background-collage.jpg') center/cover no-repeat;
    opacity: 0.12; z-index: 0; pointer-events: none;
}
body::after {
    content: '';
    position: fixed; inset: 0;
    background: radial-gradient(ellipse at center, transparent 10%, rgba(15,15,15,0.55) 60%, #0f0f0f 100%);
    z-index: 1; pointer-events: none;
}

/* ── Header ── */
header {
    background: #1C1C1C;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 30px;
    position: fixed; top: 0; left: 0; width: 100%;
    height: 60px; z-index: 1000;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
}
.logo img { height: 46px; cursor: pointer; filter: invert(1); transition: transform 0.2s; }
.logo img:hover { transform: scale(1.05); }
.header-right { display: flex; align-items: center; gap: 10px; }
.profile-btn {
    background: #F9F9F9; border: none; border-radius: 50%;
    width: 42px; height: 42px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; overflow: hidden; padding: 0;
    transition: all 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.profile-btn img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.profile-btn:hover { transform: scale(1.1); }
.profile-initials {
    width: 100%; height: 100%; border-radius: 50%;
    background: linear-gradient(135deg, #ff4d4d, #c0392b);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.82rem; font-weight: 800; color: #fff;
}
.btn-logout {
    color: #ff6b6b; text-decoration: none;
    font-size: 0.78rem; font-weight: 600;
    padding: 6px 14px; border-radius: 6px;
    border: 1px solid rgba(255,77,77,0.3);
    background: rgba(255,77,77,0.08);
    transition: all 0.2s;
}
.btn-logout:hover { background: rgba(255,77,77,0.18); color: #fff; }

/* ── Page layout ── */
.page-wrap {
    position: relative; z-index: 10;
    flex: 1;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 80px 20px 50px;
}

.profile-col {
    width: 100%;
    max-width: 480px;
    margin-top: 28px;
}

/* Page heading */
.page-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #ff4d4d; margin-bottom: 5px; }
.page-title  { font-size: 1.7rem; font-weight: 800; margin-bottom: 20px; }

/* Avatar */
.avatar-row {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px 20px;
    background: #1a1a1a;
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 14px;
    margin-bottom: 16px;
}
.avatar-circle {
    width: 64px; height: 64px; flex-shrink: 0;
    border-radius: 50%;
    overflow: hidden;
    border: 2px solid rgba(255,255,255,0.1);
}
.avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
.avatar-initials {
    width: 100%; height: 100%; border-radius: 50%;
    background: linear-gradient(135deg, #ff4d4d, #c0392b);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; font-weight: 800; color: #fff;
}
.avatar-info h3 { font-size: 1rem; font-weight: 700; margin-bottom: 3px; }
.avatar-info p  { font-size: 0.78rem; color: rgba(249,249,249,0.4); }

/* Panel */
.panel {
    background: #1a1a1a;
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 14px; overflow: hidden;
    margin-bottom: 16px;
}
.panel:last-child { margin-bottom: 0; }
.panel-header {
    padding: 14px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex; align-items: center; justify-content: space-between;
}
.panel-header h2 {
    font-size: 0.78rem; font-weight: 700;
    letter-spacing: 1.5px; text-transform: uppercase;
    color: rgba(249,249,249,0.45);
}
.panel-body { padding: 20px; }

/* Fields */
.field { margin-bottom: 16px; }
.field:last-of-type { margin-bottom: 0; }
.field label {
    display: block;
    font-size: 0.68rem; font-weight: 700;
    letter-spacing: 1px; text-transform: uppercase;
    color: rgba(249,249,249,0.4);
    margin-bottom: 6px;
}
.field input {
    width: 100%; padding: 10px 13px;
    border-radius: 9px;
    border: 1px solid rgba(255,255,255,0.1);
    background: #222; color: #F9F9F9;
    font-family: 'Outfit', sans-serif; font-size: 0.88rem;
    outline: none; transition: border-color 0.2s;
}
.field input:focus { border-color: rgba(255,77,77,0.5); background: #252525; }
.field input::placeholder { color: rgba(249,249,249,0.2); }
.field input:-webkit-autofill,
.field input:-webkit-autofill:focus {
    -webkit-text-fill-color: #F9F9F9 !important;
    -webkit-box-shadow: 0 0 0 1000px #222 inset !important;
    caret-color: #F9F9F9;
}
.field-hint { font-size: 0.68rem; color: rgba(249,249,249,0.25); margin-top: 5px; }

/* Password field with toggle */
.pw-wrap { position: relative; }
.pw-wrap input { padding-right: 60px; }
.pw-toggle {
    position: absolute; right: 12px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none;
    color: rgba(249,249,249,0.35);
    font-family: 'Outfit', sans-serif;
    font-size: 0.72rem; font-weight: 600;
    cursor: pointer; transition: color 0.2s; padding: 0;
}
.pw-toggle:hover { color: rgba(249,249,249,0.75); }

/* Message banner */
.msg-banner {
    padding: 12px 16px;
    border-radius: 9px;
    font-size: 0.82rem;
    margin-bottom: 16px;
    display: flex; align-items: flex-start; gap: 8px;
    animation: fadeUp 0.3s ease;
}
.msg-banner.ok  { background: rgba(76,175,80,0.08); border: 1px solid rgba(76,175,80,0.2); color: #81c784; }
.msg-banner.err { background: rgba(255,77,77,0.08); border: 1px solid rgba(255,77,77,0.2); border-left: 3px solid #ff4d4d; color: #ff6b6b; }

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Save button */
.btn-save {
    width: 100%; padding: 13px;
    border-radius: 9px; border: none;
    background: #ff4d4d; color: #fff;
    font-family: 'Outfit', sans-serif; font-size: 0.9rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s; margin-top: 4px;
}
.btn-save:hover { background: #e03c3c; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,77,77,0.3); }

/* Back link */
.btn-back {
    display: block; width: 100%; padding: 11px;
    border-radius: 9px; border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.04); color: rgba(249,249,249,0.5);
    font-family: 'Outfit', sans-serif; font-size: 0.88rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s; text-align: center; text-decoration: none;
    margin-top: 10px;
}
.btn-back:hover { background: rgba(255,255,255,0.08); color: #F9F9F9; }
</style>
</head>
<body>

<header>
    <div class="logo">
        <img src="peakscinematransparent.png" alt="Peak's Cinema" onclick="window.location.href='home.php'">
    </div>
    <div class="header-right">
        <button class="profile-btn" title="Profile">
            <?php if (!empty($profile_photo)): ?>
                <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile" referrerpolicy="no-referrer">
            <?php elseif (!empty($user_initials)): ?>
                <div class="profile-initials"><?= htmlspecialchars($user_initials) ?></div>
            <?php else: ?>
                <div class="profile-initials">?</div>
            <?php endif; ?>
        </button>
        <a href="?logout=1" class="btn-logout" onclick="return confirm('Log out of Peak\'s Cinema?')">→ Log Out</a>
    </div>
</header>

<div class="page-wrap">
<div class="profile-col">

    <p class="page-label">My Account</p>
    <h1 class="page-title">Edit Profile</h1>

    <!-- Avatar row -->
    <div class="avatar-row">
        <div class="avatar-circle">
            <?php if (!empty($profile_photo)): ?>
                <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile" referrerpolicy="no-referrer">
            <?php else: ?>
                <div class="avatar-initials"><?= htmlspecialchars($user_initials ?: '?') ?></div>
            <?php endif; ?>
        </div>
        <div class="avatar-info">
            <h3><?= htmlspecialchars($user['Name']) ?></h3>
            <p><?= htmlspecialchars($user['Email']) ?></p>
        </div>
    </div>

    <!-- Message -->
    <?php if (!empty($message)): ?>
    <div class="msg-banner <?= $message_type ?>">
        <?= $message_type === 'ok' ? '✓' : '⚠' ?>&nbsp;<?= $message ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="">

        <!-- Personal info panel -->
        <div class="panel">
            <div class="panel-header"><h2>👤 Personal Information</h2></div>
            <div class="panel-body">
                <div class="field">
                    <label>Full Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($user['Name']) ?>" placeholder="Your full name" required>
                </div>
                <div class="field">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['Email']) ?>" placeholder="you@example.com" required>
                </div>
                <div class="field">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($user['PhoneNumber']) ?>" placeholder="9XXXXXXXXX" pattern="[0-9]{1,10}">
                    <p class="field-hint">Up to 10 digits, numbers only.</p>
                </div>
            </div>
        </div>

        <!-- Password panel -->
        <div class="panel">
            <div class="panel-header"><h2>🔒 Change Password</h2></div>
            <div class="panel-body">
                <div class="field">
                    <label>New Password <span style="opacity:0.35;font-size:0.6rem;text-transform:none;letter-spacing:0;">(leave blank to keep current)</span></label>
                    <div class="pw-wrap">
                        <input type="password" id="pwInput" name="password" placeholder="Enter new password">
                        <button type="button" class="pw-toggle" id="pwToggle">Show</button>
                    </div>
                    <p class="field-hint">Minimum 6 characters.</p>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-save">Save Changes →</button>
    </form>

    <a href="home.php" class="btn-back">← Back to Home</a>

</div>
</div>

<script>
const pwInput  = document.getElementById('pwInput');
const pwToggle = document.getElementById('pwToggle');
pwToggle.addEventListener('click', () => {
    const show = pwInput.type === 'password';
    pwInput.type   = show ? 'text' : 'password';
    pwToggle.textContent = show ? 'Hide' : 'Show';
});
</script>
</body>
</html>