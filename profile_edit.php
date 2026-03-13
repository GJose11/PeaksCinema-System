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
$profile_photo = $_SESSION['profile_photo'] ?? null;

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
if (!empty($user['ProfilePhoto'])) {
    $profile_photo = $user['ProfilePhoto'];
    $_SESSION['profile_photo'] = $profile_photo;
}

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
    background-color: transparent;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 50%;
    width: 45px; height: 45px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    font-size: 1.2rem;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    overflow: hidden;
    padding: 0;
}
.profile-btn img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
    display: block;
}
.profile-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 0 12px rgba(255,255,255,0.3);
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
            <?php else: ?>
                👤
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