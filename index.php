<?php
date_default_timezone_set('Asia/Manila');
session_start();
include("peakscinemas_database.php");

// Already logged in — skip straight to home
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// ── Sign Up ──────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["customer_info"])) {
    function input_cleanup($d) { return htmlspecialchars(stripslashes(trim($d))); }
    $firstName = $lastName = $email = $password = $countryCode = $phoneNumber = "";
    if (!empty($_POST["lastName"]))  { $lastName  = input_cleanup($_POST['lastName']);  if (!preg_match("/^[a-zA-Z-' ]*$/", $lastName))  { $formError = "Invalid last name."; } }
    if (!empty($_POST["firstName"])) { $firstName = input_cleanup($_POST['firstName']); if (!preg_match("/^[a-zA-Z-' ]*$/", $firstName)) { $formError = "Invalid first name."; } }
    if (!empty($_POST["email"]))     { $email     = input_cleanup($_POST['email']);     if (!filter_var($email, FILTER_VALIDATE_EMAIL))  { $formError = "Invalid email format."; } }
    if (!empty($_POST["password"])) {
        $passwordPlain   = input_cleanup($_POST['password']);
        $confirmPassword = input_cleanup($_POST['confirmPassword']);
        if ($passwordPlain !== $confirmPassword) { $formError = "Passwords do not match."; }
        else { $password = password_hash($passwordPlain, PASSWORD_DEFAULT); }
    }
    $countryCode = input_cleanup($_POST['countryCode'] ?? '');
    $phoneNumber = input_cleanup($_POST['phoneNumber'] ?? '');
    if (!isset($formError) && $firstName && $lastName && $email && $password) {
        $check = mysqli_query($conn, "SELECT * FROM customer WHERE Email = '$email'");
        if (mysqli_num_rows($check) > 0) { $formError = "Email already exists. Please log in."; $activeForm = 'signup'; }
        else {
            $sql = "INSERT INTO customer (Name, Email, Password, CountryCode, PhoneNumber) VALUES ('$firstName $lastName', '$email', '$password', '$countryCode', '$phoneNumber')";
            if (mysqli_query($conn, $sql)) { $formSuccess = "Account created! You can now sign in."; $activeForm = 'login'; }
            else { $formError = "Database error. Please try again."; $activeForm = 'signup'; }
        }
    } else {
        if (!isset($formError)) $formError = "Please fill in all required fields.";
        $activeForm = 'signup';
    }
}

// ── Log In ───────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["login_user"])) {
    $email    = trim($_POST["loginEmail"]    ?? '');
    $password = trim($_POST["loginPassword"] ?? '');
    $stmt = $conn->prepare("SELECT Customer_ID, Name, Password FROM customer WHERE Email = ?");
    $stmt->bind_param("s", $email); $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['Password'])) {
            $otp = rand(100000, 999999);
            $otp_expiry = date('Y-m-d H:i:s', time() + 300);
            $otp_resend = date('Y-m-d H:i:s', time() + 60);
            $conn->query("DELETE FROM otp WHERE customer_id = " . $user['Customer_ID']);
            $stmt2 = $conn->prepare("INSERT INTO otp (customer_id, otp_code, otp_expiry, otp_resend_after) VALUES (?, ?, ?, ?)");
            $stmt2->bind_param("isss", $user['Customer_ID'], $otp, $otp_expiry, $otp_resend);
            $stmt2->execute();
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
                $mail->Body    = "<p>Your OTP code is: <b>$otp</b></p><p>Expires in 5 minutes.</p>";
                $mail->send();
                $activeForm = 'otp';
            } catch (Exception $e) {
                $formError = "Could not send OTP email. Please try again.";
                $conn->query("DELETE FROM otp WHERE customer_id = " . $user['Customer_ID']);
                unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['show_form']);
                $activeForm = 'login';
            }
        } else { $formError = "Incorrect password."; $activeForm = 'login'; }
    } else { $formError = "No account found with that email."; $activeForm = 'login'; }
}

// ── Verify OTP ───────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["verify_otp"])) {
    $userOtp = trim($_POST['otp'] ?? '');
    if (empty($userOtp)) { $formError = "Please enter the OTP code."; $activeForm = 'otp'; }
    elseif (isset($_SESSION['pending_user_id'])) {
        $uid = $_SESSION['pending_user_id'];
        $stmt = $conn->prepare("SELECT * FROM otp WHERE customer_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("i", $uid); $stmt->execute();
        $otpRow = $stmt->get_result()->fetch_assoc();
        if (!$otpRow) {
            $formError = "No OTP found. Please log in again.";
            unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['show_form']);
            $activeForm = 'login';
        } elseif (strtotime($otpRow['otp_expiry']) < time()) {
            $formError = "OTP expired. Please log in again.";
            $conn->query("DELETE FROM otp WHERE customer_id = $uid");
            unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['show_form']);
            $activeForm = 'login';
        } elseif ($userOtp == $otpRow['otp_code']) {
            $_SESSION['user_id']   = $_SESSION['pending_user_id'];
            $_SESSION['user_name'] = $_SESSION['pending_user_name'];
            if (!empty($_SESSION['pending_photo'])) $_SESSION['profile_photo'] = $_SESSION['pending_photo'];
            $conn->query("DELETE FROM otp WHERE customer_id = $uid");
            unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_email'], $_SESSION['pending_photo'], $_SESSION['show_form']);
            header("Location: home.php"); exit();
        } else { $formError = "Invalid OTP. Please try again."; $activeForm = 'otp'; }
    } else { $formError = "Session expired. Please log in again."; $activeForm = 'login'; }
}

// ── Resend OTP ───────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["resend_otp"])) {
    if (!isset($_SESSION['pending_user_id'])) {
        $formError = "Session expired. Please log in again."; $activeForm = 'login';
    } else {
        $uid  = $_SESSION['pending_user_id'];
        $stmt = $conn->prepare("SELECT * FROM otp WHERE customer_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("i", $uid); $stmt->execute();
        $otpRow = $stmt->get_result()->fetch_assoc();
        if ($otpRow && strtotime($otpRow['otp_resend_after']) > time()) {
            $wait = strtotime($otpRow['otp_resend_after']) - time();
            $formError = "Please wait {$wait}s before requesting a new OTP.";
            $activeForm = 'otp';
        } else {
            $otp = rand(100000, 999999);
            $otp_expiry = date('Y-m-d H:i:s', time() + 300);
            $otp_resend = date('Y-m-d H:i:s', time() + 60);
            $conn->query("DELETE FROM otp WHERE customer_id = $uid");
            $stmt2 = $conn->prepare("INSERT INTO otp (customer_id, otp_code, otp_expiry, otp_resend_after) VALUES (?, ?, ?, ?)");
            $stmt2->bind_param("isss", $uid, $otp, $otp_expiry, $otp_resend); $stmt2->execute();
            $_SESSION['show_form'] = 'otp';
            $email = $_SESSION['pending_email'];
            $mail  = new PHPMailer(true);
            try {
                $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true;
                $mail->Username = 'peakscinema@gmail.com'; $mail->Password = 'pggs pvye frmk tmah';
                $mail->SMTPSecure = 'ssl'; $mail->Port = 465;
                $mail->setFrom('peakscinema@gmail.com', 'PeaksCinema');
                $mail->addAddress($email); $mail->isHTML(true);
                $mail->Subject = "Your New PeaksCinema OTP Code";
                $mail->Body    = "<p>Your new OTP: <b>$otp</b></p><p>Expires in 5 minutes.</p>";
                $mail->send(); $formSuccess = "New OTP sent to your email."; $activeForm = 'otp';
            } catch (Exception $e) {
                $formError = "Could not send OTP. Please try again."; $activeForm = 'otp';
            }
        }
    }
}

// ── Determine active form ────────────────────────────────────
if (!isset($activeForm)) {
    if (isset($_SESSION['show_form']) && $_SESSION['show_form'] === 'otp' && isset($_SESSION['pending_user_id'])) {
        $activeForm = 'otp';
    } elseif (isset($_SESSION['show_form'])) {
        $activeForm = $_SESSION['show_form'];
    } elseif (isset($_GET['tab']) && $_GET['tab'] === 'register') {
        $activeForm = 'signup';
    } else {
        $activeForm = 'login';
    }
}

// Poster backdrop
$posters = [];
$res = $conn->query("SELECT MoviePoster, MovieName FROM movie WHERE MovieAvailability = 'Now Showing' LIMIT 8");
while ($r = $res->fetch_assoc()) $posters[] = $r;

define('GOOGLE_CLIENT_ID', '180356811024-djv9cq9s2975b22r89dndvb1cr9ico80.apps.googleusercontent.com');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="https://accounts.google.com/gsi/client" async defer></script>
<title>Peak's Cinema — Sign In</title>
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Outfit', sans-serif;
    background: #0f0f0f;
    color: #F9F9F9;
    min-height: 100vh;
    overflow-x: hidden;
}

/* Poster backdrop */
.backdrop {
    position: fixed; inset: 0;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    z-index: 0;
    transform: scale(1.06) rotate(-1.5deg);
    filter: blur(3px);
}
.backdrop-poster {
    aspect-ratio: 2/3;
    background-size: cover;
    background-position: center;
    opacity: 0.28;
}
.vignette {
    position: fixed; inset: 0; z-index: 1;
    background:
        radial-gradient(ellipse 90% 70% at 50% 50%, transparent 0%, rgba(15,15,15,0.7) 55%, rgba(15,15,15,0.97) 100%),
        linear-gradient(to bottom, rgba(15,15,15,0.8) 0%, transparent 20%, transparent 72%, rgba(15,15,15,0.95) 100%);
}
.film-line {
    position: fixed; top: 0; bottom: 0; width: 1px;
    background: linear-gradient(to bottom, transparent, rgba(255,45,45,0.15) 30%, rgba(255,45,45,0.15) 70%, transparent);
    z-index: 2;
}
.film-line:first-of-type { left: 10%; }
.film-line:last-of-type  { right: 10%; }

/* Stage */
.stage {
    position: relative; z-index: 10;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* Top bar */
.top-bar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 48px;
    flex-shrink: 0;
}
.logo-wrap img { height: 76px; filter: invert(1); cursor: pointer; transition: transform 0.2s; }
.logo-wrap img:hover { transform: scale(1.04); }

/* Main area — two columns on wide, single on narrow */
.main-area {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 60px;
    padding: 20px 48px 48px;
}

/* Left: branding */
.brand-col {
    flex: 1;
    max-width: 400px;
    display: flex;
    flex-direction: column;
    gap: 0;
}
.brand-eyebrow {
    font-size: 0.7rem; font-weight: 700;
    letter-spacing: 4px; text-transform: uppercase;
    color: #ff4d4d; margin-bottom: 16px;
    opacity: 0; animation: fadeUp 0.6s 0.1s forwards;
}
.brand-tagline {
    font-size: clamp(1.5rem, 3vw, 2.2rem);
    font-weight: 800; line-height: 1.2;
    opacity: 0; animation: fadeUp 0.6s 0.4s forwards;
    margin-bottom: 12px;
}
.brand-tagline .accent { color: #ff4d4d; }
.brand-sub {
    font-size: 0.9rem; color: rgba(249,249,249,0.38);
    line-height: 1.7;
    opacity: 0; animation: fadeUp 0.6s 0.55s forwards;
    margin-bottom: 28px;
}

/* Guest CTA */
.guest-cta {
    opacity: 0; animation: fadeUp 0.6s 0.7s forwards;
}
.btn-browse-guest {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 26px; border-radius: 9px;
    border: 1px solid rgba(255,255,255,0.14);
    background: rgba(255,255,255,0.04);
    color: rgba(249,249,249,0.6);
    font-family: 'Outfit', sans-serif; font-size: 0.88rem; font-weight: 600;
    text-decoration: none; transition: all 0.2s;
}
.btn-browse-guest:hover {
    border-color: rgba(255,255,255,0.3);
    background: rgba(255,255,255,0.08);
    color: #F9F9F9;
}
.guest-note {
    margin-top: 9px;
    font-size: 0.68rem; color: rgba(249,249,249,0.22);
    letter-spacing: 0.3px;
}

/* Now showing strip (below branding) */
.now-strip {
    margin-top: 32px;
    opacity: 0; animation: fadeUp 0.6s 0.85s forwards;
}
.strip-label {
    font-size: 0.6rem; font-weight: 700;
    letter-spacing: 3px; text-transform: uppercase;
    color: rgba(249,249,249,0.16); margin-bottom: 10px;
}
.poster-strip { display: flex; gap: 7px; flex-wrap: wrap; }
.strip-poster {
    width: 44px; aspect-ratio: 2/3; border-radius: 5px;
    background-size: cover; background-position: center;
    opacity: 0.42; border: 1px solid rgba(255,255,255,0.07);
    transition: all 0.3s;
}
.strip-poster:hover { opacity: 0.85; transform: scale(1.08) translateY(-3px); border-color: rgba(255,77,77,0.35); }

/* Right: auth card */
.auth-col {
    width: 360px;
    flex-shrink: 0;
    opacity: 0; animation: fadeUp 0.6s 0.2s forwards;
}

/* Card */
.auth-card {
    background: rgba(26,26,26,0.95);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    padding: 28px 24px 22px;
    backdrop-filter: blur(12px);
}
.card-title {
    font-size: 0.78rem; font-weight: 700;
    letter-spacing: 1.5px; text-transform: uppercase;
    color: rgba(249,249,249,0.4);
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

/* Feedback banners */
.msg-ok  { background: rgba(76,175,80,0.08); border: 1px solid rgba(76,175,80,0.2); color: #81c784; border-radius: 8px; padding: 10px 14px; font-size: 0.8rem; margin-bottom: 14px; }
.msg-err { background: rgba(255,77,77,0.08); border: 1px solid rgba(255,77,77,0.2); border-left: 3px solid #ff4d4d; color: #ff6b6b; border-radius: 8px; padding: 10px 14px; font-size: 0.8rem; margin-bottom: 14px; }

/* Fields */
.field { margin-bottom: 13px; }
.field label {
    display: block; font-size: 0.67rem; font-weight: 700;
    letter-spacing: 1px; text-transform: uppercase;
    color: rgba(249,249,249,0.38); margin-bottom: 5px;
}
.field input {
    width: 100%; padding: 9px 13px;
    border-radius: 9px; border: 1px solid rgba(255,255,255,0.09);
    background: #1e1e1e; color: #F9F9F9;
    font-family: 'Outfit', sans-serif; font-size: 0.86rem;
    outline: none; transition: border-color 0.2s;
}
.field input:focus { border-color: rgba(255,77,77,0.45); background: #222; }
.field input::placeholder { color: rgba(249,249,249,0.18); }
.field input:-webkit-autofill,
.field input:-webkit-autofill:focus {
    -webkit-text-fill-color: #F9F9F9 !important;
    -webkit-box-shadow: 0 0 0 1000px #1e1e1e inset !important;
}
.field-row { display: flex; gap: 10px; }
.field-row .field { flex: 1; }
.opt { opacity: 0.3; font-size: 0.58rem; text-transform: none; letter-spacing: 0; }

/* Buttons */
.btn-submit {
    width: 100%; padding: 11px;
    border-radius: 9px; border: none;
    background: #ff4d4d; color: #fff;
    font-family: 'Outfit', sans-serif; font-size: 0.88rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s; margin-top: 4px;
}
.btn-submit:hover { background: #e03c3c; transform: translateY(-1px); box-shadow: 0 6px 18px rgba(255,77,77,0.3); }

.switch-link {
    background: none; border: none;
    color: rgba(249,249,249,0.3);
    font-family: 'Outfit', sans-serif; font-size: 0.76rem;
    cursor: pointer; margin-top: 12px;
    display: block; width: 100%; text-align: center;
    transition: color 0.2s; padding: 0;
}
.switch-link:hover { color: rgba(249,249,249,0.7); }
.switch-link span { color: #ff6b6b; font-weight: 600; }

/* Divider */
.divider {
    display: flex; align-items: center; gap: 10px;
    margin: 16px 0 12px;
    color: rgba(255,255,255,0.18);
    font-size: 0.68rem; letter-spacing: 1px; text-transform: uppercase;
}
.divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.07); }

/* Google button */
.google-btn-wrap { display: flex; justify-content: center; }
.google-btn-wrap > div { border-radius: 9px !important; overflow: hidden; }

/* OTP */
.otp-hint { font-size: 0.8rem; color: rgba(249,249,249,0.38); text-align: center; line-height: 1.6; margin-bottom: 14px; }
.otp-resend { text-align: center; margin-top: 12px; font-size: 0.76rem; color: rgba(249,249,249,0.3); }
.otp-resend button { background: none; border: none; color: #ff6b6b; font-family: 'Outfit', sans-serif; font-size: 0.76rem; font-weight: 600; cursor: pointer; margin-left: 4px; }
.otp-small { display: block; text-align: center; font-size: 0.63rem; color: rgba(249,249,249,0.18); margin-top: 8px; }

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Responsive — stack on narrow screens */
@media (max-width: 860px) {
    .main-area { flex-direction: column; gap: 32px; padding: 16px 20px 40px; align-items: center; }
    .brand-col  { max-width: 100%; align-items: center; text-align: center; }
    .poster-strip { justify-content: center; }
    .auth-col   { width: 100%; max-width: 380px; }
    .top-bar    { padding: 16px 20px; }
}
</style>
</head>
<body>

<div class="backdrop">
    <?php foreach ($posters as $p): ?>
    <div class="backdrop-poster" style="background-image:url('/<?= htmlspecialchars($p['MoviePoster']) ?>');"></div>
    <?php endforeach; ?>
    <?php for ($i = count($posters); $i < 8; $i++) { ?>
    <div class="backdrop-poster" style="background:#111;"></div>
    <?php } ?>
</div>
<div class="vignette"></div>
<div class="film-line"></div>
<div class="film-line"></div>

<div class="stage">

    <div class="top-bar">
        <div class="logo-wrap">
            <img src="peakscinematransparent.png" alt="Peak's Cinema">
        </div>
    </div>

    <div class="main-area">

        <div class="brand-col">
            <p class="brand-eyebrow">Now Open &nbsp;·&nbsp; Metro Manila</p>
            
            <h1 class="brand-tagline">Welcome to<br><span class="accent">Peak's Cinema</span></h1>
            <p class="brand-sub">
                Premium cinema experiences across Metro Manila.
                Book your seats, pick your moment, live the story.
            </p>

            <div class="guest-cta">
                <a href="home.php" class="btn-browse-guest">
                    🎬 Browse Movies as Guest
                </a>
                <p class="guest-note">No account needed — sign in later to book tickets.</p>
            </div>

            <?php if (!empty($posters)): ?>
            <div class="now-strip">
                <p class="strip-label">Now Showing</p>
                <div class="poster-strip">
                    <?php foreach ($posters as $p): ?>
                    <div class="strip-poster"
                         style="background-image:url('/<?= htmlspecialchars($p['MoviePoster']) ?>');"
                         title="<?= htmlspecialchars($p['MovieName']) ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="auth-col">

            <div id="signupSection" style="<?= $activeForm !== 'signup' ? 'display:none;' : '' ?>">
                <div class="auth-card">
                    <p class="card-title">👤 Create Account</p>
                    <?php if (!empty($formError) && $activeForm === 'signup'): ?>
                    <div class="msg-err">⚠ <?= htmlspecialchars($formError) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($formSuccess) && $activeForm === 'signup'): ?>
                    <div class="msg-ok">✓ <?= htmlspecialchars($formSuccess) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="field-row">
                            <div class="field">
                                <label>First Name</label>
                                <input type="text" name="firstName" placeholder="Juan" required>
                            </div>
                            <div class="field">
                                <label>Last Name</label>
                                <input type="text" name="lastName" placeholder="Dela Cruz" required>
                            </div>
                        </div>
                        <div class="field">
                            <label>Email</label>
                            <input type="email" name="email" placeholder="you@example.com" required>
                        </div>
                        <div class="field">
                            <label>Password</label>
                            <input type="password" name="password" placeholder="Create a password" required>
                        </div>
                        <div class="field">
                            <label>Confirm Password</label>
                            <input type="password" name="confirmPassword" placeholder="Re-enter password" required>
                        </div>
                        <div class="field">
                            <label>Phone <span class="opt">(optional)</span></label>
                            <div style="display:flex;gap:8px;">
                                <input type="text" name="countryCode" placeholder="+63" style="width:62px;flex-shrink:0;">
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
                             data-login_uri="<?= (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'] ?>/PeaksCinema/google_login.php"
                             data-auto_prompt="false"></div>
                        <div class="g_id_signin"
                             data-type="standard" data-shape="rectangular"
                             data-theme="filled_black" data-text="continue_with"
                             data-size="large" data-logo_alignment="left" data-width="312"></div>
                    </div>
                </div>
            </div>

            <div id="loginSection" style="<?= $activeForm !== 'login' ? 'display:none;' : '' ?>">
                <div class="auth-card">
                    <p class="card-title">🔑 Sign In</p>
                    <?php if (!empty($formError) && $activeForm === 'login'): ?>
                    <div class="msg-err">⚠ <?= htmlspecialchars($formError) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($formSuccess) && $activeForm === 'login'): ?>
                    <div class="msg-ok">✓ <?= htmlspecialchars($formSuccess) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="field">
                            <label>Email</label>
                            <input type="email" name="loginEmail" placeholder="you@example.com" required>
                        </div>
                        <div class="field">
                            <label>Password</label>
                            <input type="password" name="loginPassword" placeholder="Your password" required>
                        </div>
                        <button type="submit" name="login_user" class="btn-submit">Sign In →</button>
                    </form>
                    <button class="switch-link" id="showSignup">Don't have an account? <span>Sign up</span></button>
                    <div class="divider">or</div>
                    <div class="google-btn-wrap">
                        <div id="g_id_onload_login"
                             data-client_id="<?= GOOGLE_CLIENT_ID ?>"
                             data-login_uri="<?= (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'] ?>/PeaksCinema/google_login.php"
                             data-auto_prompt="false"></div>
                        <div class="g_id_signin"
                             data-type="standard" data-shape="rectangular"
                             data-theme="filled_black" data-text="signin_with"
                             data-size="large" data-logo_alignment="left" data-width="312"></div>
                    </div>
                </div>
            </div>

            <div id="otpSection" style="<?= $activeForm !== 'otp' ? 'display:none;' : '' ?>">
                <div class="auth-card">
                    <p class="card-title">🔐 Verify Email</p>
                    <?php if (!empty($formError) && $activeForm === 'otp'): ?>
                    <div class="msg-err">⚠ <?= htmlspecialchars($formError) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($formSuccess) && $activeForm === 'otp'): ?>
                    <div class="msg-ok">✓ <?= htmlspecialchars($formSuccess) ?></div>
                    <?php endif; ?>
                    <p class="otp-hint">We've sent a 6-digit code to your email.<br>Enter it below to continue.</p>
                    <form method="POST">
                        <div class="field">
                            <label>OTP Code</label>
                            <input type="text" name="otp" maxlength="6" placeholder="000000"
                                   style="text-align:center;font-size:1.4rem;font-weight:800;letter-spacing:8px;" required>
                        </div>
                        <button type="submit" name="verify_otp" class="btn-submit">Verify &amp; Continue →</button>
                    </form>
                    <form method="POST">
                        <p class="otp-resend">
                            Didn't receive the code?
                            <button type="submit" name="resend_otp">Resend</button>
                        </p>
                    </form>
                    <small class="otp-small">Check your spam or promotions folder.</small>
                </div>
            </div>

        </div></div></div><script>
document.getElementById('showLogin')?.addEventListener('click', () => {
    document.getElementById('signupSection').style.display = 'none';
    document.getElementById('loginSection').style.display  = '';
});
document.getElementById('showSignup')?.addEventListener('click', () => {
    document.getElementById('loginSection').style.display  = 'none';
    document.getElementById('signupSection').style.display = '';
});
</script>
</body>
</html>