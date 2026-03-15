<?php
session_start();
include("peakscinemas_database.php");

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (!isset($_SESSION['pending_user_id'])) {
        unset($_SESSION['show_form']);
    }
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
    if (!empty($_POST["lastName"]))  { $lastName  = input_cleanup($_POST['lastName']);  if (!preg_match("/^[a-zA-Z-' ]*$/", $lastName))  { echo "<script>alert('Invalid last name.');</script>";  exit(); } }
    if (!empty($_POST["firstName"])) { $firstName = input_cleanup($_POST['firstName']); if (!preg_match("/^[a-zA-Z-' ]*$/", $firstName)) { echo "<script>alert('Invalid first name.');</script>"; exit(); } }
    if (!empty($_POST["email"]))     { $email     = input_cleanup($_POST['email']);     if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo "<script>alert('Invalid email format.');</script>"; exit(); } }
    if (!empty($_POST["password"]))  {
        $passwordPlain = input_cleanup($_POST['password']);
        $confirmPassword = input_cleanup($_POST['confirmPassword']);
        if ($passwordPlain !== $confirmPassword) { echo "<script>alert('Passwords do not match.');</script>"; exit(); }
        else { $password = password_hash($passwordPlain, PASSWORD_DEFAULT); }
    }
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
            $otp = rand(100000, 999999);
            $otp_expiry = date('Y-m-d H:i:s', time() + 300);
            $otp_resend = date('Y-m-d H:i:s', time() + 60);
            $conn->query("DELETE FROM otp WHERE customer_id = " . $user['Customer_ID']);
            $stmt2 = $conn->prepare("INSERT INTO otp (customer_id, otp_code, otp_expiry, otp_resend_after) VALUES (?, ?, ?, ?)");
            $stmt2->bind_param("isss", $user['Customer_ID'], $otp, $otp_expiry, $otp_resend); $stmt2->execute();
            $_SESSION['pending_user_id']   = $user['Customer_ID'];
            $_SESSION['pending_user_name'] = $user['Name'];
            $_SESSION['pending_email']     = $email;
            $_SESSION['show_form']         = 'otp';
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true;
                $mail->Username = 'peakscinema@gmail.com'; $mail->Password = 'pggs pvye frmk tmah';
                $mail->SMTPSecure = 'ssl'; $mail->Port = 465;
                $mail->setFrom('peakscinema@gmail.com', 'PeaksCinema');
                $mail->addAddress($email); $mail->isHTML(true);
                $mail->Subject = "Your PeaksCinema OTP Code";
                $mail->Body = "<p>Your OTP code is: <b>$otp</b></p><p>This code will expire in 5 minutes.</p>";
                $mail->send();
                echo "<script>alert('OTP sent to your email. Please enter it to continue.');</script>";
            } catch (Exception $e) {
                echo "<script>alert('Mailer Error: " . addslashes($mail->ErrorInfo) . "');</script>";
                $conn->query("DELETE FROM otp WHERE customer_id = " . $user['Customer_ID']);
                unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['show_form']);
            }
        } else { echo "<script>alert('Invalid password.');</script>"; $_SESSION['show_form'] = 'login'; }
    } else { echo "<script>alert('Email not found.');</script>"; $_SESSION['show_form'] = 'login'; }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["verify_otp"])) {
    $userOtp = trim($_POST['otp']);
    if (empty($userOtp)) { echo "<script>alert('Please enter the OTP code.');</script>"; $_SESSION['show_form'] = 'otp'; }
    elseif (isset($_SESSION['pending_user_id'])) {
        $uid = $_SESSION['pending_user_id'];
        $stmt = $conn->prepare("SELECT * FROM otp WHERE customer_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("i", $uid); $stmt->execute(); $result = $stmt->get_result(); $otpRow = $result->fetch_assoc();
        if (!$otpRow) { echo "<script>alert('No OTP found. Please log in again.');</script>"; unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['show_form']); }
        elseif (strtotime($otpRow['otp_expiry']) < time()) { echo "<script>alert('OTP expired. Please log in again.');</script>"; $conn->query("DELETE FROM otp WHERE customer_id = $uid"); unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['show_form']); }
        elseif ($userOtp == $otpRow['otp_code']) {
            $_SESSION['user_id']   = $_SESSION['pending_user_id'];
            $_SESSION['user_name'] = $_SESSION['pending_user_name'];
            if (!empty($_SESSION['pending_photo'])) $_SESSION['profile_photo'] = $_SESSION['pending_photo'];
            $conn->query("DELETE FROM otp WHERE customer_id = $uid");
            unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['pending_photo'], $_SESSION['show_form']);
            header("Location: home.php"); exit();
        } else { echo "<script>alert('Invalid OTP. Please try again.');</script>"; $_SESSION['show_form'] = 'otp'; }
    } else { echo "<script>alert('Session expired. Please log in again.');</script>"; unset($_SESSION['show_form']); }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["resend_otp"])) {
    if (!isset($_SESSION['pending_user_id'])) { echo "<script>alert('Session expired. Please log in again.');</script>"; unset($_SESSION['show_form']); }
    else {
        $uid = $_SESSION['pending_user_id'];
        $stmt = $conn->prepare("SELECT * FROM otp WHERE customer_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("i", $uid); $stmt->execute(); $result = $stmt->get_result(); $otpRow = $result->fetch_assoc();
        if ($otpRow && strtotime($otpRow['otp_resend_after']) > time()) {
            $wait = strtotime($otpRow['otp_resend_after']) - time();
            echo "<script>alert('Please wait $wait second(s) before requesting a new OTP.');</script>";
            $_SESSION['show_form'] = 'otp';
        } else {
            $otp = rand(100000, 999999); $otp_expiry = date('Y-m-d H:i:s', time() + 300); $otp_resend = date('Y-m-d H:i:s', time() + 60);
            $conn->query("DELETE FROM otp WHERE customer_id = $uid");
            $stmt2 = $conn->prepare("INSERT INTO otp (customer_id, otp_code, otp_expiry, otp_resend_after) VALUES (?, ?, ?, ?)");
            $stmt2->bind_param("isss", $uid, $otp, $otp_expiry, $otp_resend); $stmt2->execute();
            $_SESSION['show_form'] = 'otp'; $email = $_SESSION['pending_email'];
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true;
                $mail->Username = 'peakscinema@gmail.com'; $mail->Password = 'pggs pvye frmk tmah';
                $mail->SMTPSecure = 'ssl'; $mail->Port = 465;
                $mail->setFrom('peakscinema@gmail.com', 'PeaksCinema');
                $mail->addAddress($email); $mail->isHTML(true);
                $mail->Subject = "Your New PeaksCinema OTP Code";
                $mail->Body = "<p>Your new OTP code is: <b>$otp</b></p><p>This code will expire in 5 minutes.</p>";
                $mail->send(); echo "<script>alert('A new OTP has been sent to your email.');</script>";
            } catch (Exception $e) { echo "<script>alert('Mailer Error: " . addslashes($mail->ErrorInfo) . "');</script>"; }
        }
    }
}

mysqli_close($conn);

if (isset($_SESSION['show_form']) && $_SESSION['show_form'] === 'otp') {
    $activeForm = (isset($_SESSION['pending_email']) && isset($_SESSION['pending_user_id'])) ? 'otp' : 'signup';
    if ($activeForm === 'signup') unset($_SESSION['show_form']);
} elseif (isset($_SESSION['show_form'])) {
    $activeForm = $_SESSION['show_form'];
} else {
    $activeForm = isset($_GET['tab']) && $_GET['tab'] === 'register' ? 'signup' : 'login';
}

define('GOOGLE_CLIENT_ID', '180356811024-djv9cq9s2975b22r89dndvb1cr9ico80.apps.googleusercontent.com');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
<script src="https://accounts.google.com/gsi/client" async defer></script>
<title>Peak's Cinema — Sign In</title>
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
}
.logo img { height: 46px; cursor: pointer; filter: invert(1); transition: transform 0.2s; }
.logo img:hover { transform: scale(1.05); }

/* ── Page layout ── */
.page-wrap {
    position: relative; z-index: 10;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 80px 20px 40px;
}

.auth-col {
    width: 100%;
    max-width: 380px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0;
}

/* Page heading above card */
.auth-heading {
    text-align: center;
    margin-bottom: 20px;
}
.auth-eyebrow {
    font-size: 0.68rem; font-weight: 700;
    letter-spacing: 3px; text-transform: uppercase;
    color: #ff4d4d; margin-bottom: 6px;
}
.auth-heading h1 {
    font-size: 1.5rem; font-weight: 800;
    color: #F9F9F9;
}

/* ── Auth card ── */
.auth-card {
    background: #1a1a1a;
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 16px;
    padding: 28px 24px 24px;
    width: 100%;
    animation: fadeUp 0.35s ease;
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}

.card-title {
    font-size: 1rem; font-weight: 700;
    letter-spacing: 1.5px; text-transform: uppercase;
    color: rgba(249,249,249,0.45);
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

/* Fields */
.field { margin-bottom: 14px; }
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
    background: #222;
    color: #F9F9F9;
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

.field-row { display: flex; gap: 10px; }
.field-row .field { flex: 1; margin-bottom: 0; }
.field-row .field:first-child { flex: 0 0 80px; }

/* Optional label */
.opt { opacity: 0.35; font-size: 0.6rem; text-transform: none; letter-spacing: 0; }

/* Submit button */
.btn-submit {
    display: block; width: 100%;
    padding: 12px;
    border-radius: 9px; border: none;
    background: #ff4d4d; color: #fff;
    font-family: 'Outfit', sans-serif; font-size: 0.9rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s;
    margin-top: 6px;
}
.btn-submit:hover { background: #e03c3c; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,77,77,0.3); }

/* Switch link */
.switch-link {
    background: none; border: none;
    color: rgba(249,249,249,0.35);
    font-family: 'Outfit', sans-serif; font-size: 0.78rem;
    cursor: pointer; margin-top: 14px;
    display: block; width: 100%; text-align: center;
    transition: color 0.2s; padding: 0;
}
.switch-link:hover { color: rgba(249,249,249,0.75); }
.switch-link span { color: #ff6b6b; font-weight: 600; }

/* Divider */
.divider {
    display: flex; align-items: center; gap: 10px;
    margin: 18px 0 14px;
    color: rgba(255,255,255,0.2);
    font-size: 0.7rem; letter-spacing: 1px; text-transform: uppercase;
}
.divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.08); }

/* Google button */
.google-btn-wrap { display: flex; justify-content: center; }
.google-btn-wrap > div { border-radius: 9px !important; overflow: hidden; }

/* OTP card extras */
.otp-hint {
    font-size: 0.82rem; color: rgba(249,249,249,0.4);
    text-align: center; line-height: 1.6; margin-bottom: 16px;
}
.otp-resend {
    text-align: center; margin-top: 14px;
    font-size: 0.78rem; color: rgba(249,249,249,0.35);
}
.otp-resend button {
    background: none; border: none;
    color: #ff6b6b; font-family: 'Outfit', sans-serif;
    font-size: 0.78rem; font-weight: 600;
    cursor: pointer; padding: 0; margin-left: 4px;
    transition: color 0.2s;
}
.otp-resend button:hover { color: #ff4d4d; }
.otp-small {
    display: block; text-align: center;
    font-size: 0.65rem; color: rgba(249,249,249,0.2);
    margin-top: 8px;
}
</style>
</head>
<body>

<header>
    <div class="logo">
        <img src="peakscinematransparent.png" alt="Peak's Cinema" onclick="window.location.href='index.php'">
    </div>
</header>

<div class="page-wrap">
<div class="auth-col">

    <!-- SIGN UP -->
    <div id="signupSection" style="width:100%;<?= $activeForm !== 'signup' ? 'display:none;' : '' ?>">
        <div class="auth-heading">
            <p class="auth-eyebrow">Peak's Cinema</p>
            <h1>Create Account</h1>
        </div>
        <div class="auth-card">
            <p class="card-title">👤 Sign Up</p>
            <form method="POST">
                <div class="field-row" style="margin-bottom:14px;">
                    <div class="field">
                        <label>First Name</label>
                        <input type="text" name="firstName" placeholder="Juan">
                    </div>
                    <div class="field" style="flex:1;">
                        <label>Last Name</label>
                        <input type="text" name="lastName" placeholder="Dela Cruz">
                    </div>
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="you@example.com">
                </div>
                <div class="field">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Create a password">
                </div>
                <div class="field">
                    <label>Confirm Password</label>
                    <input type="password" name="confirmPassword" placeholder="Re-enter your password">
                </div>
                <div class="field">
                    <label>Phone Number <span class="opt">(optional)</span></label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" name="countryCode" placeholder="+63" style="width:68px;flex-shrink:0;">
                        <input type="tel" name="phoneNumber" placeholder="9XXXXXXXXX" style="flex:1;">
                    </div>
                </div>
                <button type="submit" name="customer_info" class="btn-submit">Create Account →</button>
            </form>
            <button class="switch-link" id="showLogin">Already have an account? <span>Sign in</span></button>

            <div class="divider">or</div>
            <div class="google-btn-wrap">
                <div id="g_id_onload"
                     data-client_id="<?= GOOGLE_CLIENT_ID ?>"
                     data-login_uri="<?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ?>/PeaksCinema/google_login.php"
                     data-auto_prompt="false"></div>
                <div class="g_id_signin"
                     data-type="standard" data-shape="rectangular"
                     data-theme="filled_black" data-text="continue_with"
                     data-size="large" data-logo_alignment="left" data-width="332"></div>
            </div>
        </div>
    </div>

    <!-- LOG IN -->
    <div id="loginSection" style="width:100%;<?= $activeForm !== 'login' ? 'display:none;' : '' ?>">
        <div class="auth-heading">
            <p class="auth-eyebrow">Peak's Cinema</p>
            <h1>Welcome Back</h1>
        </div>
        <div class="auth-card">
            <p class="card-title">🔑 Log In</p>
            <form method="POST">
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="loginEmail" placeholder="you@example.com">
                </div>
                <div class="field">
                    <label>Password</label>
                    <input type="password" name="loginPassword" placeholder="Your password">
                </div>
                <button type="submit" name="login_user" class="btn-submit">Sign In →</button>
            </form>
            <button class="switch-link" id="showSignup">Don't have an account? <span>Sign up</span></button>

            <div class="divider">or</div>
            <div class="google-btn-wrap">
                <div id="g_id_onload_login"
                     data-client_id="<?= GOOGLE_CLIENT_ID ?>"
                     data-login_uri="<?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ?>/PeaksCinema/google_login.php"
                     data-auto_prompt="false"></div>
                <div class="g_id_signin"
                     data-type="standard" data-shape="rectangular"
                     data-theme="filled_black" data-text="signin_with"
                     data-size="large" data-logo_alignment="left" data-width="332"></div>
            </div>
        </div>
    </div>

    <!-- OTP -->
    <div id="otpSection" style="width:100%;<?= $activeForm !== 'otp' ? 'display:none;' : '' ?>">
        <div class="auth-heading">
            <p class="auth-eyebrow">Peak's Cinema</p>
            <h1>Verify Email</h1>
        </div>
        <div class="auth-card">
            <p class="card-title">🔐 Enter OTP</p>
            <p class="otp-hint">We've sent a 6-digit code to your email.<br>Enter it below to continue.</p>
            <form method="POST" action="personal_info_form.php">
                <div class="field">
                    <label>OTP Code</label>
                    <input type="text" name="otp" maxlength="6" placeholder="000000"
                           style="text-align:center;font-size:1.4rem;font-weight:800;letter-spacing:8px;">
                </div>
                <button type="submit" name="verify_otp" class="btn-submit">Verify &amp; Continue →</button>
            </form>
            <form method="POST" action="personal_info_form.php">
                <p class="otp-resend">
                    Didn't receive the code?
                    <button type="submit" name="resend_otp">Resend</button>
                </p>
            </form>
            <small class="otp-small">Check your spam or promotions folder if you don't see it.</small>
        </div>
    </div>

</div>
</div>

<script>
    document.getElementById('showLogin')?.addEventListener('click', () => {
        document.getElementById('signupSection').style.display = 'none';
        document.getElementById('loginSection').style.display  = 'block';
    });
    document.getElementById('showSignup')?.addEventListener('click', () => {
        document.getElementById('loginSection').style.display  = 'none';
        document.getElementById('signupSection').style.display = 'block';
    });
</script>
</body>
</html>