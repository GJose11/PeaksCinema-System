<?php
session_start();
include("peakscinemas_database.php");

// Handle logout
if (isset($_GET['logout'])) {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: personal_info_form.php?logged_out=1");
    exit;
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: personal_info_form.php");
    exit;
}

$profile_link = "profile_edit.php";

// Fetch user data
$stmt = $conn->prepare("SELECT Name, Email, PhoneNumber, Password, ProfilePhoto FROM customer WHERE Customer_ID = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_unset();
    session_destroy();
    header("Location: personal_info_form.php?error=user_not_found");
    exit;
}

$user = $result->fetch_assoc();

// Generate initials from Name
$profile_photo = $user['ProfilePhoto'] ?? $_SESSION['profile_photo'] ?? null;
$nameParts     = explode(' ', trim($user['Name'] ?? ''));
$user_initials = strtoupper(substr($nameParts[0]??'',0,1) . substr(end($nameParts)??'',0,1));
if (strlen($user_initials) === 1) $user_initials = strtoupper(substr($nameParts[0]??'',0,2));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);

    $errors = [];

    // Validate name
    if (empty($name) || strlen($name) > 100) {
        $errors[] = "Name must not be empty or longer than 100 characters.";
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Validate phone
    if (!preg_match('/^[0-9]{1,10}$/', $phone)) {
        $errors[] = "Phone number must be 1–10 digits.";
    }

    // Validate password (only if entered)
    if (!empty($password) && strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }

    if (!empty($errors)) {
        $message = "❌ Please fix the following:<br>" . implode("<br>", $errors);
    } else {
        $hashedPassword = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : $user['Password'];

        $updateStmt = $conn->prepare("UPDATE customer SET Name = ?, Email = ?, PhoneNumber = ?, Password = ? WHERE Customer_ID = ?");
        $updateStmt->bind_param("ssssi", $name, $email, $phone, $hashedPassword, $_SESSION['user_id']);

        if ($updateStmt->execute()) {
            $message = "Your profile has been updated! (●'◡'●)";
            $user['Name'] = $name;
            $user['Email'] = $email;
            $user['PhoneNumber'] = $phone;
            $user['Password'] = $hashedPassword;
        } else {
            $message = "❌ Error updating profile. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<<<<<<< HEAD
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
<title>Edit Profile - PeaksCinemas</title>
<style>
:root {
    --bg-dark: #1f1f1f;
    --bg-light: #2e2e2e;
    --accent: #a3c2b1;
    --text-light: #ffffff;
    --border-color: #444;
    --error: #ff4b4b;
}
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: 'Outfit', sans-serif;
    background: url("movie-background-collage.jpg") no-repeat center center fixed;
    background-size: cover;
    background-color: #1C1C1C;
    color: #F9F9F9;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    align-items: center;
    justify-content: center;
    padding-top: 100px;
    padding-bottom: 40px;
}

header {
    background-color: #1C1C1C;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 30px;
    position: fixed;
    top: 0; left: 0;
    width: 100%;
    z-index: 1000;
    box-shadow: 0 4px 10px rgba(0,0,0,0.5);
    height: 70px;
}
.logo img {
    height: 50px;
    width: auto;
    cursor: pointer;
    transition: transform 0.2s ease;
    filter: invert(1);
}
.logo img:hover {
    transform: scale(1.05);
}
.header-actions {
    display: flex;
    align-items: center;
}
.profile-btn {
    background-color: #F9F9F9;
    border: none;
    border-radius: 50%;
    width: 45px; height: 45px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    font-size: 1.2rem;
    transition: all 0.3s ease;
}
.profile-btn {
    background: transparent;
}
.profile-btn:hover { transform: scale(1.1); box-shadow: 0 0 12px rgba(255,255,255,0.3); }
.profile-btn img {
    width: 100%; height: 100%;
    object-fit: cover; border-radius: 50%;
}
.profile-initials {
    width: 100%; height: 100%; border-radius: 50%;
    background: linear-gradient(135deg, #ff4d4d, #c0392b);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.82rem; font-weight: 800; color: #fff;
    letter-spacing: 0.5px; font-family: 'Outfit', sans-serif;
}
.logout-btn {
    background-color: #ff4b4b;
    color: #F9F9F9;
    border: none;
    border-radius: 6px;
    padding: 6px 12px;
    margin-left: 15px;
    font-family: 'Outfit', sans-serif;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: 0.3s;
}
.logout-btn:hover {
    background-color: #F9F9F9;
    color: #ff4b4b;
    transform: scale(1.1);
}


.main-container {
    background: rgba(20, 20, 20, 0.85);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.8);
    padding: 30px 25px;
    margin-top: 20px;
    margin-bottom: 20px;
    width: 320px;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

h2 {
    color: #F9F9F9;
    text-align: center;
    margin-bottom: 25px;
    font-weight: 600;
}

label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
}
input[type="text"], input[type="email"], input[type="tel"], input[type="password"] {
    width: 100%;
    padding: 10px 12px;
    margin-bottom: 15px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.08);
    background-color: rgba(255,255,255,0.06);
    box-shadow: 0 2px 5px rgba(0,0,0,0.4);
    color: white;
    transition: 0.3s;
    font-family: 'Outfit', sans-serif;
}
input:focus {
    border-color: #F9F9F9;
    outline: none;
    box-shadow: 0 0 5px #F9F9F9;
}

.password-container {
    position: relative;
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}
.password-container input[type="password"],
.password-container input[type="text"] {
    width: 100%;
    padding-right: 65px;
    margin-bottom: 0;
}

#togglePassword {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #aaa;
    cursor: pointer;
    font-weight: 600;
    transition: 0.3s;
    padding: 0;
    margin: 0;
    z-index: 2;
    font-family: 'Outfit', sans-serif;
}
#togglePassword:hover {
    color: #F9F9F9;
}

input[type="submit"] {
    background-color: #ff4b4b;
    color: #F9F9F9;
    border: none;
    padding: 12px;
    border-radius: 8px;
    font-weight: 600;
    width: 100%;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: 'Outfit', sans-serif;
    font-size: 1rem;
}
input[type="submit"]:hover {
    background-color: #ff3333;
    transform: scale(1.03);
    box-shadow: 0 0 18px rgba(255, 75, 75, 0.6);
}

.message {
    margin-bottom: 20px;
    text-align: center;
    font-weight: bold;
    color: var(--accent);
}

@media (max-width: 480px) {
    .main-container {
        width: 90%;
        padding: 20px;
    }
}
</style>
=======
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: url("movie-background-collage.jpg") no-repeat center center fixed; background-size: cover; color: #F9F9F9; padding-top: 100px; padding-bottom: 40px; min-height: 100vh; }
        header { background-color: #1C1C1C; display: flex; justify-content: space-between; align-items: center; padding: 10px 30px; position: fixed; top: 0; left: 0; width: 100%; z-index: 1000; }
        .logo img { height: 50px; cursor: pointer; filter: invert(1); transition: transform 0.2s ease; }
        .logo img:hover { transform: scale(1.05); }
        .profile-btn { background-color: #F9F9F9; border: none; border-radius: 50%; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.2); overflow: hidden; padding: 0; font-size: 1.2rem; }
        .profile-btn img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        .profile-btn:hover { transform: scale(1.1); box-shadow: 0 0 12px rgba(255,255,255,0.3); }
        .profile-btn img { width:100%; height:100%; object-fit:cover; border-radius:50%; }
        .profile-initials {
            width: 100%; height: 100%; border-radius: 50%;
            background: linear-gradient(135deg, #ff4d4d, #c0392b);
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: 800; color: #fff;
            letter-spacing: 0.5px; font-family: 'Outfit', sans-serif;
        }
        main { margin: 30px auto; width: 85%; max-width: 1000px; display: flex; flex-direction: column; gap: 12px; }
        .panel { backdrop-filter: blur(2px); background-color: rgba(0,0,0,0.4); border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.6); padding: 8px 20px; }
        .summary-bar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
        .summary-bar .summary-label { font-size: 0.72rem; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: #F9F9F9; margin-bottom: 2px; }
        .summary-bar .summary-value { font-size: 0.8rem; font-weight: 700; color: #F9F9F9; }
        .summary-divider { width: 1px; height: 24px; background: rgba(249,249,249,0.15); }
        h2 { font-size: 1rem; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: #F9F9F9; margin-bottom: 16px; }
        .payment-methods { display: flex; flex-direction: row; flex-wrap: wrap; gap: 6px; }
        .payment-option { display: flex; align-items: center; gap: 8px; padding: 6px 12px; border: 1px solid rgba(249,249,249,0.15); border-radius: 6px; cursor: pointer; transition: border-color 0.2s, background 0.2s; background: rgba(249,249,249,0.04); flex: 1; min-width: 140px; }
        .payment-option:hover { border-color: rgba(249,249,249,0.4); background: rgba(249,249,249,0.08); }
        .payment-option.selected { border-color: #ff4d4d; background: rgba(255,77,77,0.08); }
        .payment-option input[type="radio"] { accent-color: #ff4d4d; width: 14px; height: 14px; cursor: pointer; flex-shrink: 0; }
        .payment-logo { width: 34px; height: 22px; object-fit: contain; background: #fff; padding: 2px 4px; border-radius: 3px; border: 1px solid rgba(0,0,0,0.1); flex-shrink: 0; }
        .payment-label { font-size: 0.78rem; font-weight: 500; color: #F9F9F9; flex: 1; cursor: pointer; }
        .payment-details { display: none; margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(249,249,249,0.1); }
        .payment-details.active { display: block; }
        .payment-details p { font-size: 0.8rem; color: rgba(249,249,249,0.5); text-align: left; margin-top: 8px; }
        .form-row { display: flex; gap: 10px; }
        .form-group { flex: 1; margin-bottom: 12px; }
        .form-group label { display: block; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; color: #F9F9F9; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 9px 12px; border-radius: 7px; border: 1px solid rgba(249,249,249,0.2); background: #1C1C1C; color: #F9F9F9; font-family: 'Outfit', sans-serif; font-size: 0.85rem; transition: border-color 0.2s; outline: none; }
        .form-group input::placeholder { color: rgba(249,249,249,0.25); }
        .form-group input:-webkit-autofill, .form-group input:-webkit-autofill:hover, .form-group input:-webkit-autofill:focus, .form-group input:-webkit-autofill:active { -webkit-text-fill-color: #F9F9F9 !important; -webkit-box-shadow: 0 0 0px 1000px rgba(0,0,0,0.2) inset !important; transition: background-color 5000s ease-in-out 0s; caret-color: #F9F9F9; }
        .form-group input:focus { border-color: rgba(249,249,249,0.5); }
        .form-group input.valid { border-color: #4caf50; background: rgba(0,0,0,0.2); }
        .form-group input.invalid { border-color: #ff4d4d; background: rgba(255,77,77,0.08); }
        .error-message { color: #ff4d4d; font-size: 0.7rem; margin-top: 4px; display: none; }
        .validation-status { display: none; padding: 8px 12px; border-radius: 7px; font-size: 0.8rem; margin-top: 12px; }
        .validation-status.valid { background: rgba(76,175,80,0.12); border: 1px solid rgba(76,175,80,0.3); color: #81c784; }
        .validation-status.invalid { background: rgba(255,77,77,0.1); border: 1px solid rgba(255,77,77,0.3); color: #ff6b6b; }
        .submit-row { display: flex; justify-content: flex-end; margin-top: 10px; }
        button[type="submit"] { padding: 9px 26px; border-radius: 8px; border: none; background-color: #ff4d4d; color: #fff; font-family: 'Outfit', sans-serif; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: background 0.2s ease, transform 0.15s ease; white-space: nowrap; }
        button[type="submit"]:hover { background-color: #ff4d4d; transform: scale(1.02); }
        button[type="submit"]:disabled { background-color: rgba(249,249,249,0.12); color: rgba(249,249,249,0.3); cursor: not-allowed; transform: none; }
    </style>
>>>>>>> a8750aa6e3cdb443a525692cb99b678114bb862e
</head>
<body>
<header>
    <div class="logo">
        <img src="peakscinematransparent.png" alt="PeaksCinemas Logo" onclick="window.location.href='home.php'">
    </div>
    <div class="header-actions">
        <button class="profile-btn" onclick="window.location.href='<?= $profile_link ?>'" title="Profile">
            <?php if (!empty($profile_photo)): ?>
                <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile" referrerpolicy="no-referrer">
            <?php elseif (!empty($user_initials)): ?>
                <div class="profile-initials"><?= htmlspecialchars($user_initials) ?></div>
            <?php else: ?>
                <div class="profile-initials">?</div>
            <?php endif; ?>
        </button>
        <a href="?logout=1"><button class="logout-btn">Logout</button></a>
    </div>
</header>

<main class="main-container">
    <h2>Edit Your Profile</h2>
    <?php if (!empty($message)) echo "<div class='message'>{$message}</div>"; ?>
    <form method="post" action="">
        <label for="name">Full Name</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['Name']) ?>" required>

        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['Email']) ?>" required>

        <label for="phone">Phone Number</label>
        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['PhoneNumber']) ?>" required pattern="[0-9]{1,10}" title="Up to 10-digit phone number">

        <label for="password">New Password</label>
        <div class="password-container">
            <input type="password" id="password" name="password" placeholder="New Password">
            <button type="button" id="togglePassword">Show</button>
        </div>

        <input type="submit" value="Save Changes">
    </form>
</main>

<script>
const passwordInput = document.getElementById('password');
const toggleBtn = document.getElementById('togglePassword');

toggleBtn.addEventListener('click', () => {
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleBtn.textContent = 'Hide';
    } else {
        passwordInput.type = 'password';
        toggleBtn.textContent = 'Show';
    }
});
</script>

</body>
</html>