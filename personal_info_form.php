<?php
session_start();
include("peakscinemas_database.php");

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    unset($_SESSION['show_form']);
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["customer_info"])) {
    function input_cleanup($data) {
        $data = trim($data); $data = stripslashes($data); $data = htmlspecialchars($data); return $data;
    }
    $firstName = $lastName = $email = $password = $confirmPassword = $countryCode = $phoneNumber = "";
    if (!empty($_POST["lastName"])) { $lastName = input_cleanup($_POST['lastName']); if (!preg_match("/^[a-zA-Z-' ]*$/", $lastName)) { echo "<script>alert('Invalid last name.');</script>"; exit(); } }
    if (!empty($_POST["firstName"])) { $firstName = input_cleanup($_POST['firstName']); if (!preg_match("/^[a-zA-Z-' ]*$/", $firstName)) { echo "<script>alert('Invalid first name.');</script>"; exit(); } }
    if (!empty($_POST["email"])) { $email = input_cleanup($_POST['email']); if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo "<script>alert('Invalid email format.');</script>"; exit(); } }
    if (!empty($_POST["password"])) { $passwordPlain = input_cleanup($_POST['password']); $confirmPassword = input_cleanup($_POST['confirmPassword']); if ($passwordPlain !== $confirmPassword) { echo "<script>alert('Passwords do not match.');</script>"; exit(); } else { $password = password_hash($passwordPlain, PASSWORD_DEFAULT); } }
    $countryCode = input_cleanup($_POST['countryCode']);
    $phoneNumber = input_cleanup($_POST['phoneNumber']);
    if ($firstName && $lastName && $email && $password) {
        $check = mysqli_query($conn, "SELECT * FROM customer WHERE Email = '$email'");
        if (mysqli_num_rows($check) > 0) { echo "<script>alert('Email already exists. Please log in.');</script>"; }
        else {
            $sql = "INSERT INTO customer (Name, Email, Password, CountryCode, PhoneNumber) VALUES ('$firstName $lastName', '$email', '$password', '$countryCode', '$phoneNumber')";
            if (mysqli_query($conn, $sql)) { echo "<script>alert('Sign Up Successful! Please log in now.');</script>"; $_SESSION['show_form'] = 'login'; }
            else { echo "<script>alert('Database error: " . mysqli_error($conn) . "');</script>"; }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["login_user"])) {
    $email = trim($_POST["loginEmail"]); $password = trim($_POST["loginPassword"]);
    $stmt = $conn->prepare("SELECT Customer_ID, Name, Password FROM customer WHERE Email = ?");
    $stmt->bind_param("s", $email); $stmt->execute(); $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['Password'])) {
            $otp = rand(100000, 999999); $otp_expiry = date('Y-m-d H:i:s', time() + 300); $otp_resend = date('Y-m-d H:i:s', time() + 60);
            $conn->query("DELETE FROM otp WHERE customer_id = " . $user['Customer_ID']);
            $stmt2 = $conn->prepare("INSERT INTO otp (customer_id, otp_code, otp_expiry, otp_resend_after) VALUES (?, ?, ?, ?)");
            $stmt2->bind_param("isss", $user['Customer_ID'], $otp, $otp_expiry, $otp_resend); $stmt2->execute();
            $_SESSION['pending_user_id'] = $user['Customer_ID']; $_SESSION['pending_user_name'] = $user['Name']; $_SESSION['pending_email'] = $email; $_SESSION['show_form'] = 'otp';
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true; $mail->Username = 'peakscinema@gmail.com'; $mail->Password = 'pggs pvye frmk tmah'; $mail->SMTPSecure = 'ssl'; $mail->Port = 465;
                $mail->setFrom('peakscinema@gmail.com', 'PeaksCinema'); $mail->addAddress($email); $mail->isHTML(true); $mail->Subject = "Your PeaksCinema OTP Code"; $mail->Body = "<p>Your OTP code is: <b>$otp</b></p><p>This code will expire in 5 minutes.</p>";
                $mail->send(); echo "<script>alert('OTP sent to your email. Please enter it to continue.');</script>";
            } catch (Exception $e) { echo "<script>alert('Mailer Error: " . addslashes($mail->ErrorInfo) . "');</script>"; $conn->query("DELETE FROM otp WHERE customer_id = " . $user['Customer_ID']); unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['show_form']); }
        } else { echo "<script>alert('Invalid password.');</script>"; $_SESSION['show_form'] = 'login'; }
    } else { echo "<script>alert('Email not found.');</script>"; $_SESSION['show_form'] = 'login'; }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["verify_otp"])) {
    $userOtp = trim($_POST['otp']);
    if (empty($userOtp)) { echo "<script>alert('Please enter the OTP code.');</script>"; $_SESSION['show_form'] = 'otp'; }
    elseif (isset($_SESSION['pending_user_id'])) {
        $uid = $_SESSION['pending_user_id'];
        $stmt = $conn->prepare("SELECT * FROM otp WHERE customer_id = ? ORDER BY created_at DESC LIMIT 1"); $stmt->bind_param("i", $uid); $stmt->execute(); $result = $stmt->get_result(); $otpRow = $result->fetch_assoc();
        if (!$otpRow) { echo "<script>alert('No OTP found. Please log in again.');</script>"; unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['show_form']); }
        elseif (strtotime($otpRow['otp_expiry']) < time()) { echo "<script>alert('OTP expired. Please log in again.');</script>"; $conn->query("DELETE FROM otp WHERE customer_id = $uid"); unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['show_form']); }
        elseif ($userOtp == $otpRow['otp_code']) {
            $_SESSION['user_id']   = $_SESSION['pending_user_id'];
            $_SESSION['user_name'] = $_SESSION['pending_user_name'];
            // Save Google photo if this was a Google signup
            if (!empty($_SESSION['pending_photo'])) {
                $_SESSION['profile_photo'] = $_SESSION['pending_photo'];
            }
            $conn->query("DELETE FROM otp WHERE customer_id = $uid");
            unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['pending_photo'], $_SESSION['show_form']);
            header("Location: home.php"); exit();
        }
        else { echo "<script>alert('Invalid OTP. Please try again.');</script>"; $_SESSION['show_form'] = 'otp'; }
    } else { echo "<script>alert('Session expired. Please log in again.');</script>"; unset($_SESSION['show_form']); }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["resend_otp"])) {
    if (!isset($_SESSION['pending_user_id'])) { echo "<script>alert('Session expired. Please log in again.');</script>"; unset($_SESSION['show_form']); }
    else {
        $uid = $_SESSION['pending_user_id'];
        $stmt = $conn->prepare("SELECT * FROM otp WHERE customer_id = ? ORDER BY created_at DESC LIMIT 1"); $stmt->bind_param("i", $uid); $stmt->execute(); $result = $stmt->get_result(); $otpRow = $result->fetch_assoc();
        if ($otpRow && strtotime($otpRow['otp_resend_after']) > time()) { $wait = strtotime($otpRow['otp_resend_after']) - time(); echo "<script>alert('Please wait $wait second(s) before requesting a new OTP.');</script>"; $_SESSION['show_form'] = 'otp'; }
        else {
            $otp = rand(100000, 999999); $otp_expiry = date('Y-m-d H:i:s', time() + 300); $otp_resend = date('Y-m-d H:i:s', time() + 60);
            $conn->query("DELETE FROM otp WHERE customer_id = $uid");
            $stmt2 = $conn->prepare("INSERT INTO otp (customer_id, otp_code, otp_expiry, otp_resend_after) VALUES (?, ?, ?, ?)"); $stmt2->bind_param("isss", $uid, $otp, $otp_expiry, $otp_resend); $stmt2->execute();
            $_SESSION['show_form'] = 'otp'; $email = $_SESSION['pending_email'];
            $mail = new PHPMailer(true);
            try { $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true; $mail->Username = 'peakscinema@gmail.com'; $mail->Password = 'pggs pvye frmk tmah'; $mail->SMTPSecure = 'ssl'; $mail->Port = 465; $mail->setFrom('peakscinema@gmail.com', 'PeaksCinema'); $mail->addAddress($email); $mail->isHTML(true); $mail->Subject = "Your New PeaksCinema OTP Code"; $mail->Body = "<p>Your new OTP code is: <b>$otp</b></p><p>This code will expire in 5 minutes.</p>"; $mail->send(); echo "<script>alert('A new OTP has been sent to your email.');</script>"; }
            catch (Exception $e) { echo "<script>alert('Mailer Error: " . addslashes($mail->ErrorInfo) . "');</script>"; }
        }
    }
}

mysqli_close($conn);

if (isset($_SESSION['show_form']) && $_SESSION['show_form'] === 'otp') {
    $activeForm = (isset($_SESSION['pending_email']) && isset($_SESSION['pending_user_id'])) ? 'otp' : 'signup';
    if ($activeForm === 'signup') unset($_SESSION['show_form']);
} elseif (isset($_SESSION['show_form'])) { $activeForm = $_SESSION['show_form']; } else { $activeForm = 'signup'; }

// ── IMPORTANT: Replace this with your actual Google Client ID ──
// Get it from: https://console.cloud.google.com/ → APIs & Services → Credentials
define('GOOGLE_CLIENT_ID', '180356811024-djv9cq9s2975b22r89dndvb1cr9ico80.apps.googleusercontent.com');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
<!-- Google Identity Services -->
<script src="https://accounts.google.com/gsi/client" async defer></script>
<title>PeaksCinemas - Sign Up & Login</title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }

    body {
        font-family: 'Outfit', sans-serif;
        background: url("movie-background-collage.jpg") no-repeat center center fixed;
        background-size: cover;
        color: #F9F9F9;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding-top: 80px;
        padding-bottom: 40px;
    }

    header {
        background-color: #1C1C1C;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 30px;
        position: fixed;
        top:0; left:0;
        width: 100%;
        z-index: 1000;
    }

    .logo img {
        height: 50px;
        cursor: pointer;
        filter: invert(1);
        transition: transform 0.2s ease;
    }
    .logo img:hover { transform: scale(1.05); }

    .auth-card {
        backdrop-filter: blur(2px);
        background-color: rgba(0,0,0,0.4);
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.6);
        padding: 24px 20px;
        width: 100%;
        max-width: 320px;
        margin-top: 25px;
        animation: fadeIn 0.4s ease;
    }

    @keyframes fadeIn {
        from { opacity:0; transform:translateY(10px); }
        to   { opacity:1; transform:translateY(0); }
    }

    .auth-title {
        font-size: 1.5rem;
        font-weight: 600;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: #F9F9F9;
        margin-bottom: 18px;
        text-align: center;
    }

    .auth-card label {
        display: block;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: #F9F9F9;
        margin-bottom: 4px;
        margin-top: 10px;
    }

    .auth-card input[type="text"],
    .auth-card input[type="email"],
    .auth-card input[type="password"],
    .auth-card input[type="tel"] {
        width: 100%;
        padding: 6px 10px;
        border-radius: 8px;
        border: 1px solid rgba(249,249,249,0.2);
        background: #1C1C1C;
        color: #F9F9F9;
        font-family: 'Outfit', sans-serif;
        font-size: 0.8rem;
        outline: none;
        transition: border-color 0.2s;
    }

    .auth-card input:focus { border-color: rgba(249,249,249,0.5); }
    .auth-card input::placeholder { color: #aaa; }

    .auth-card input:-webkit-autofill,
    .auth-card input:-webkit-autofill:focus {
        -webkit-text-fill-color: #F9F9F9 !important;
        -webkit-box-shadow: 0 0 0px 1000px #1C1C1C inset !important;
    }

    .phone-row { display:flex; gap:8px; }
    .phone-row input:first-child { width:30%; }
    .phone-row input:last-child  { flex:1; }

    .btn-primary {
        display: block;
        width: 100%;
        margin-top: 18px;
        padding: 9px;
        border-radius: 8px;
        border: none;
        background-color: #ff4d4d;
        color: #fff;
        font-family: 'Outfit', sans-serif;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s, transform 0.15s;
    }
    .btn-primary:hover { background-color: #e04343; transform: scale(1.02); }

    .btn-link {
        background: none;
        border: none;
        color: rgba(249,249,249,0.45);
        font-family: 'Outfit', sans-serif;
        font-size: 0.78rem;
        cursor: pointer;
        margin-top: 12px;
        display: block;
        width: 100%;
        text-align: center;
        transition: color 0.2s;
        padding: 0;
    }
    .btn-link:hover { color: #F9F9F9; }

    /* ── Google Sign-In Divider ── */
    .divider {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 16px 0 12px;
        color: rgba(255,255,255,0.3);
        font-size: 0.75rem;
        letter-spacing: 1px;
        text-transform: uppercase;
    }
    .divider::before, .divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: rgba(255,255,255,0.12);
    }

    /* Google button wrapper - center it */
    .google-btn-wrapper {
        display: flex;
        justify-content: center;
        margin-top: 4px;
    }

    /* Override Google button to fit dark theme */
    .google-btn-wrapper > div {
        border-radius: 8px !important;
        overflow: hidden;
    }

    .otp-subtext {
        font-size: 0.82rem;
        color: rgba(249,249,249,0.5);
        text-align: center;
        line-height: 1.6;
        margin-bottom: 14px;
    }

    .otp-footer {
        text-align: center;
        margin-top: 10px;
        font-size: 0.78rem;
        color: rgba(249,249,249,0.4);
    }

    .otp-hint {
        display: block;
        text-align: center;
        font-size: 0.68rem;
        color: rgba(249,249,249,0.25);
        margin-top: 8px;
    }
</style>
</head>
<body>

<header>
    <div class="logo">
        <img src="peakscinematransparent.png" alt="PeaksCinemas Logo" onclick="window.location.href='home.php'">
    </div>
</header>

<main style="width:100%;display:flex;justify-content:center;padding:0 20px;">

    <!-- SIGN UP -->
    <div class="auth-card" id="signupForm" style="display:<?= $activeForm === 'signup' ? 'block' : 'none' ?>">
        <form method="POST">
            <div class="auth-title">Sign Up</div>
            <label>Last Name</label>
            <input type="text" name="lastName" placeholder="Enter your last name">
            <label>First Name</label>
            <input type="text" name="firstName" placeholder="Enter your first name">
            <label>Email</label>
            <input type="email" name="email" placeholder="Enter your email">
            <label>Password</label>
            <input type="password" name="password" placeholder="Enter your password">
            <label>Confirm Password</label>
            <input type="password" name="confirmPassword" placeholder="Re-enter your password">
            <label>Phone Number <span style="opacity:0.35;font-size:0.62rem;text-transform:none;">(optional)</span></label>
            <div class="phone-row">
                <input type="text" name="countryCode" placeholder="+00">
                <input type="tel" name="phoneNumber" placeholder="9XXXXXXXXX">
            </div>
            <button type="submit" name="customer_info" class="btn-primary">Next</button>
            <button type="button" class="btn-link" id="showLogin">Already have an account? Log in</button>
        </form>

        <div class="divider">or</div>
        <div class="google-btn-wrapper">
            <div id="g_id_onload"
                 data-client_id="<?= GOOGLE_CLIENT_ID ?>"
                 data-login_uri="<?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ?>/PeaksCinema/google_login.php"
                 data-auto_prompt="false">
            </div>
            <div class="g_id_signin"
                 data-type="standard"
                 data-shape="rectangular"
                 data-theme="filled_black"
                 data-text="continue_with"
                 data-size="large"
                 data-logo_alignment="left"
                 data-width="280">
            </div>
        </div>
    </div>

    <!-- LOG IN -->
    <div class="auth-card" id="loginForm" style="display:<?= $activeForm === 'login' ? 'block' : 'none' ?>">
        <form method="POST">
            <div class="auth-title">Log In</div>
            <label>Email</label>
            <input type="email" name="loginEmail" placeholder="Enter your email">
            <label>Password</label>
            <input type="password" name="loginPassword" placeholder="Enter your password">
            <button type="submit" name="login_user" class="btn-primary">Log In</button>
            <button type="button" class="btn-link" id="showSignup">Don't have an account? Sign up</button>
        </form>

        <div class="divider">or</div>
        <div class="google-btn-wrapper">
            <div id="g_id_onload_login"
                 data-client_id="<?= GOOGLE_CLIENT_ID ?>"
                 data-login_uri="<?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ?>/PeaksCinema/google_login.php"
                 data-auto_prompt="false">
            </div>
            <div class="g_id_signin"
                 data-type="standard"
                 data-shape="rectangular"
                 data-theme="filled_black"
                 data-text="signin_with"
                 data-size="large"
                 data-logo_alignment="left"
                 data-width="280">
            </div>
        </div>
    </div>

    <!-- OTP -->
    <div id="otpWrapper" style="display:<?= $activeForm === 'otp' ? 'flex' : 'none' ?>; justify-content: center; width:100%;">
        <div class="auth-card">
            <form method="POST" action="personal_info_form.php">
                <div class="auth-title">🔐 Verify Your Email</div>
                <p class="otp-subtext">We've sent a 6-digit code to your email.<br>Enter it below to continue.</p>
                <label>OTP Code</label>
                <input type="text" name="otp" maxlength="6" placeholder="Enter 6-digit code">
                <button type="submit" name="verify_otp" class="btn-primary">Verify &amp; Continue</button>
            </form>
            <form method="POST" action="personal_info_form.php" style="margin-top:0;">
                <div class="otp-footer">
                    Didn't receive the code?
                    <button type="submit" name="resend_otp" class="btn-link" style="display:inline;width:auto;margin:0 0 0 4px;">Resend</button>
                </div>
            </form>
            <small class="otp-hint">Check your spam or promotions folder if you don't see it.</small>
        </div>
    </div>

</main>

<script>
    const showLoginBtn  = document.getElementById('showLogin');
    const showSignupBtn = document.getElementById('showSignup');
    const signupDiv     = document.getElementById('signupForm');
    const loginDiv      = document.getElementById('loginForm');

    if (showLoginBtn) showLoginBtn.addEventListener('click', () => {
        signupDiv.style.display = 'none';
        loginDiv.style.display  = 'block';
    });

    if (showSignupBtn) showSignupBtn.addEventListener('click', () => {
        loginDiv.style.display  = 'none';
        signupDiv.style.display = 'block';
    });
</script>

</body>
</html>