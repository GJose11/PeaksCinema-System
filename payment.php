<?php
include("peakscinemas_database.php");
session_start();

$profile_link  = "personal_info_form.php";
$profile_photo = $_SESSION['profile_photo'] ?? null;
$user_initials = '';

if (isset($_SESSION['user_id'])) {
    $profile_link = "profile_edit.php";
    $stmt = $conn->prepare("SELECT Name, ProfilePhoto FROM customer WHERE Customer_ID = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!empty($row['ProfilePhoto'])) {
        $profile_photo = $row['ProfilePhoto'];
        $_SESSION['profile_photo'] = $profile_photo;
    }
    $nameParts = explode(' ', trim($row['Name'] ?? ''));
    $user_initials = strtoupper(substr($nameParts[0]??'',0,1).substr(end($nameParts)??'',0,1));
    if (strlen($user_initials)===1) $user_initials = strtoupper(substr($nameParts[0]??'',0,2));
}

$Movie_ID      = $_POST['movie_id']      ?? '';
$Mall_ID       = $_POST['mall_id']       ?? '';
$Date          = $_POST['date']          ?? '';
$TimeSlot_ID   = $_POST['timeslot_id']   ?? '';
$selectedSeats = $_POST['selectedSeats'] ?? [];
$totalPrice    = $_POST['priceTotal']    ?? 0;

if (empty($selectedSeats) || $totalPrice <= 0) {
    header("Location: seat_selection.php?movie_id=$Movie_ID&mall_id=$Mall_ID&date=$Date&timeslot_id=$TimeSlot_ID");
    exit;
}

$_SESSION['booking_data'] = [
    'movie_id'      => $Movie_ID,
    'mall_id'       => $Mall_ID,
    'date'          => $Date,
    'timeslot_id'   => $TimeSlot_ID,
    'selectedSeats' => $selectedSeats,
    'totalPrice'    => $totalPrice,
];

$movie_stmt = $conn->prepare("SELECT * FROM movie WHERE Movie_ID = ?");
$movie_stmt->bind_param("i", $Movie_ID); $movie_stmt->execute();
$movieDetails = $movie_stmt->get_result()->fetch_assoc();

$mall_stmt = $conn->prepare("SELECT * FROM mall WHERE Mall_ID = ?");
$mall_stmt->bind_param("i", $Mall_ID); $mall_stmt->execute();
$mallDetails = $mall_stmt->get_result()->fetch_assoc();

$timeslot_stmt = $conn->prepare("SELECT * FROM timeslot INNER JOIN theater ON timeslot.Theater_ID = theater.Theater_ID WHERE TimeSlot_ID = ?");
$timeslot_stmt->bind_param("i", $TimeSlot_ID); $timeslot_stmt->execute();
$timeslotDetails = $timeslot_stmt->get_result()->fetch_assoc();

$seatPositions = [];
if (!empty($selectedSeats)) {
    $placeholders = implode(',', array_fill(0, count($selectedSeats), '?'));
    $seat_stmt = $conn->prepare("SELECT Seat_ID, SeatRow, SeatColumn FROM seats WHERE Seat_ID IN ($placeholders)");
    $types = str_repeat('i', count($selectedSeats));
    $seat_stmt->bind_param($types, ...$selectedSeats);
    $seat_stmt->execute();
    $seatResult = $seat_stmt->get_result();
    while ($s = $seatResult->fetch_assoc()) {
        $seatPositions[] = $s['SeatRow'] . $s['SeatColumn'];
    }
    sort($seatPositions);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
<title>Payment – <?= htmlspecialchars($movieDetails['MovieName'] ?? 'Peak\'s Cinema') ?></title>
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Outfit', sans-serif;
    background: #0f0f0f;
    color: #F9F9F9;
    min-height: 100vh;
    padding-top: 70px;
    padding-bottom: 60px;
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
.logo img { height: 48px; cursor: pointer; filter: invert(1); transition: transform 0.2s; }
.logo img:hover { transform: scale(1.05); }
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

/* ── Layout ── */
.outer {
    position: relative; z-index: 10;
    width: 95%; max-width: 1100px;
    margin: 28px auto;
}
.page-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #ff4d4d; margin-bottom: 5px; }
.page-title { font-size: 1.7rem; font-weight: 800; margin-bottom: 20px; }

.two-col {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 24px;
    align-items: start;
}
@media (max-width: 860px) {
    .two-col { grid-template-columns: 1fr; }
    .sidebar { position: static !important; }
}

/* ── Panels ── */
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
    display: flex; align-items: center; gap: 8px;
}
.panel-header h2 {
    font-size: 0.78rem; font-weight: 700;
    letter-spacing: 1.5px; text-transform: uppercase;
    color: rgba(249,249,249,0.45);
}
.panel-body { padding: 20px; }

/* ── Sidebar ── */
.sidebar { position: sticky; top: 80px; }

/* Movie info card */
.movie-card { display: flex; gap: 14px; padding: 16px; align-items: flex-start; }
.movie-thumb {
    width: 52px; height: 78px; flex-shrink: 0;
    border-radius: 7px; background: #111;
    background-size: cover; background-position: center;
    border: 1px solid rgba(255,255,255,0.08);
}
.movie-card h3 { font-size: 0.88rem; font-weight: 800; margin-bottom: 6px; line-height: 1.3; }
.movie-card-meta { font-size: 0.7rem; color: rgba(249,249,249,0.4); display: flex; flex-direction: column; gap: 3px; }
.type-pill {
    display: inline-block; margin-top: 5px;
    font-size: 0.62rem; font-weight: 700; letter-spacing: 1px;
    background: rgba(255,77,77,0.12); border: 1px solid rgba(255,77,77,0.25);
    color: #ff6b6b; padding: 2px 8px; border-radius: 10px;
}

/* Order summary */
.order-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; padding: 7px 0; border-bottom: 1px solid rgba(255,255,255,0.04); }
.order-row:last-child { border-bottom: none; }
.order-row .lbl { color: rgba(249,249,249,0.45); }
.order-row .val { font-weight: 600; text-align: right; max-width: 60%; }
.seats-wrap { display: flex; flex-wrap: wrap; gap: 4px; justify-content: flex-end; margin-top: 4px; }
.seat-chip {
    padding: 2px 8px; border-radius: 5px;
    background: rgba(255,77,77,0.1); border: 1px solid rgba(255,77,77,0.2);
    font-size: 0.7rem; font-weight: 700; color: #ff6b6b;
}
.total-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 14px 20px;
    background: rgba(255,77,77,0.06); border-top: 1px solid rgba(255,77,77,0.12);
}
.total-lbl { font-size: 0.82rem; color: rgba(249,249,249,0.5); }
.total-val { font-size: 1.2rem; font-weight: 800; color: #ff4d4d; }

/* ── Payment methods ── */
.method-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.method-card {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 14px;
    background: #222; border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px; cursor: pointer;
    transition: all 0.2s;
}
.method-card:hover { border-color: rgba(255,255,255,0.2); background: #272727; }
.method-card.selected { border-color: #ff4d4d; background: rgba(255,77,77,0.08); }
.method-card input[type="radio"] { display: none; }
.method-logo {
    width: 38px; height: 24px; object-fit: contain;
    background: #fff; border-radius: 4px; padding: 2px 4px;
    flex-shrink: 0;
}
.method-name { font-size: 0.8rem; font-weight: 600; color: #F9F9F9; }

/* ── Form fields ── */
.fields-wrap { margin-top: 20px; display: none; }
.fields-wrap.active { display: block; }
.form-row { display: flex; gap: 12px; }
.form-group { flex: 1; margin-bottom: 14px; }
.form-group label {
    display: block; font-size: 0.68rem; font-weight: 700;
    letter-spacing: 1px; text-transform: uppercase;
    color: rgba(249,249,249,0.4); margin-bottom: 6px;
}
.form-group input {
    width: 100%; padding: 10px 13px;
    border-radius: 9px; border: 1px solid rgba(255,255,255,0.1);
    background: #222; color: #F9F9F9;
    font-family: 'Outfit', sans-serif; font-size: 0.88rem;
    outline: none; transition: border-color 0.2s;
}
.form-group input::placeholder { color: rgba(249,249,249,0.2); }
.form-group input:focus { border-color: rgba(255,77,77,0.5); background: #252525; }
.form-group input.valid   { border-color: rgba(76,175,80,0.6); }
.form-group input.invalid { border-color: rgba(255,77,77,0.5); background: rgba(255,77,77,0.04); }
.err { font-size: 0.68rem; color: #ff6b6b; margin-top: 4px; display: none; }
.form-hint { font-size: 0.75rem; color: rgba(249,249,249,0.3); margin-top: 6px; }

/* Status */
.status-box { border-radius: 8px; padding: 11px 14px; font-size: 0.8rem; margin-top: 4px; display: none; }
.status-box.ok  { background: rgba(76,175,80,0.08); border: 1px solid rgba(76,175,80,0.25); color: #81c784; }
.status-box.err { background: rgba(255,77,77,0.08); border: 1px solid rgba(255,77,77,0.2); color: #ff6b6b; }

/* Submit */
.btn-submit {
    width: 100%; padding: 14px;
    background: #2a2a2a; border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px; color: rgba(249,249,249,0.3);
    font-family: 'Outfit', sans-serif; font-size: 0.95rem; font-weight: 700;
    cursor: not-allowed; pointer-events: none;
    transition: all 0.25s; margin-top: 6px;
    letter-spacing: 0.3px;
}
.btn-submit.active { background: #ff4d4d; color: #fff; border-color: #ff4d4d; cursor: pointer; pointer-events: all; }
.btn-submit.active:hover { background: #e03c3c; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,77,77,0.3); }

/* autofill fix */
input:-webkit-autofill,
input:-webkit-autofill:hover,
input:-webkit-autofill:focus {
    -webkit-text-fill-color: #F9F9F9 !important;
    -webkit-box-shadow: 0 0 0 1000px #222 inset !important;
    caret-color: #F9F9F9;
}
</style>
</head>
<body>

<header>
    <div class="logo">
        <img src="peakscinematransparent.png" alt="Peak's Cinema" onclick="window.location.href='home.php'">
    </div>
    <button class="profile-btn" onclick="window.location.href='<?= $profile_link ?>'" title="Profile">
        <?php if (!empty($profile_photo)): ?>
            <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile" referrerpolicy="no-referrer">
        <?php elseif (!empty($user_initials)): ?>
            <div class="profile-initials"><?= htmlspecialchars($user_initials) ?></div>
        <?php else: ?>
            <div class="profile-initials">?</div>
        <?php endif; ?>
    </button>
</header>

<div class="outer">
    <p class="page-label">Book Tickets</p>
    <h1 class="page-title">Payment</h1>

    <div class="two-col">

        <!-- ── LEFT: Payment form ── -->
        <div>
            <form id="paymentForm" action="receipt.php" method="POST" novalidate>
                <input type="hidden" name="movie_id"    value="<?= htmlspecialchars($Movie_ID) ?>">
                <input type="hidden" name="mall_id"     value="<?= htmlspecialchars($Mall_ID) ?>">
                <input type="hidden" name="date"        value="<?= htmlspecialchars($Date) ?>">
                <input type="hidden" name="timeslot_id" value="<?= htmlspecialchars($TimeSlot_ID) ?>">
                <input type="hidden" name="totalPrice"  value="<?= htmlspecialchars($totalPrice) ?>">
                <?php foreach ($selectedSeats as $seat): ?>
                <input type="hidden" name="selectedSeats[]" value="<?= htmlspecialchars($seat) ?>">
                <?php endforeach; ?>

                <!-- Payment method selection -->
                <div class="panel">
                    <div class="panel-header"><h2>💳 Choose Payment Method</h2></div>
                    <div class="panel-body">
                        <div class="method-grid">
                            <div class="method-card" onclick="selectMethod('credit')">
                                <input type="radio" name="paymentMethod" value="credit" id="credit">
                                <img src="visa.png" alt="Card" class="method-logo">
                                <span class="method-name">Credit / Debit</span>
                            </div>
                            <div class="method-card" onclick="selectMethod('paypal')">
                                <input type="radio" name="paymentMethod" value="paypal" id="paypal">
                                <img src="paypal.png" alt="PayPal" class="method-logo">
                                <span class="method-name">PayPal</span>
                            </div>
                            <div class="method-card" onclick="selectMethod('gcash')">
                                <input type="radio" name="paymentMethod" value="gcash" id="gcash">
                                <img src="gcash.png" alt="GCash" class="method-logo">
                                <span class="method-name">GCash</span>
                            </div>
                            <div class="method-card" onclick="selectMethod('paymaya')">
                                <input type="radio" name="paymentMethod" value="paymaya" id="paymaya">
                                <img src="paymaya.png" alt="PayMaya" class="method-logo">
                                <span class="method-name">PayMaya</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Credit / Debit Card -->
                <div class="panel">
                    <div class="panel-header"><h2>📝 Payment Details</h2></div>
                    <div class="panel-body">

                        <div id="creditFields" class="fields-wrap">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" id="cardFirstName" name="cardFirstName" placeholder="Juan" data-required data-pattern="[A-Za-z\s]+">
                                    <div class="err" id="cardFirstNameErr">Letters only</div>
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" id="cardLastName" name="cardLastName" placeholder="Dela Cruz" data-required data-pattern="[A-Za-z\s]+">
                                    <div class="err" id="cardLastNameErr">Letters only</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Card Number</label>
                                <input type="text" id="cardNumber" name="cardNumber" placeholder="1234 5678 9012 3456" data-required maxlength="19">
                                <div class="err" id="cardNumberErr">Enter a valid card number (13–16 digits)</div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Expiry Date</label>
                                    <input type="text" id="expiryDate" name="expiryDate" placeholder="MM/YY" data-required maxlength="5">
                                    <div class="err" id="expiryDateErr">Format: MM/YY</div>
                                </div>
                                <div class="form-group">
                                    <label>CVV</label>
                                    <input type="text" id="cvv" name="cvv" placeholder="123" data-required data-pattern="[0-9]{3,4}" maxlength="4">
                                    <div class="err" id="cvvErr">3–4 digits</div>
                                </div>
                            </div>
                        </div>

                        <!-- PayPal -->
                        <div id="paypalFields" class="fields-wrap">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" id="paypalFirstName" name="paypalFirstName" placeholder="Juan" data-required data-pattern="[A-Za-z\s]+">
                                    <div class="err" id="paypalFirstNameErr">Letters only</div>
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" id="paypalLastName" name="paypalLastName" placeholder="Dela Cruz" data-required data-pattern="[A-Za-z\s]+">
                                    <div class="err" id="paypalLastNameErr">Letters only</div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="text" id="paypalPhone" name="paypalPhone" placeholder="09XXXXXXXXX" data-required data-pattern="09[0-9]{9}" maxlength="11">
                                    <div class="err" id="paypalPhoneErr">Format: 09XXXXXXXXX</div>
                                </div>
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" id="paypalEmail" name="paypalEmail" placeholder="you@example.com" data-required>
                                    <div class="err" id="paypalEmailErr">Enter a valid email</div>
                                </div>
                            </div>
                            <p class="form-hint">You will be redirected to PayPal to complete payment.</p>
                        </div>

                        <!-- GCash -->
                        <div id="gcashFields" class="fields-wrap">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" id="gcashFirstName" name="gcashFirstName" placeholder="Juan" data-required data-pattern="[A-Za-z\s]+">
                                    <div class="err" id="gcashFirstNameErr">Letters only</div>
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" id="gcashLastName" name="gcashLastName" placeholder="Dela Cruz" data-required data-pattern="[A-Za-z\s]+">
                                    <div class="err" id="gcashLastNameErr">Letters only</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>GCash Mobile Number</label>
                                <input type="text" id="gcashNumber" name="gcashNumber" placeholder="09XXXXXXXXX" data-required data-pattern="09[0-9]{9}" maxlength="11">
                                <div class="err" id="gcashNumberErr">Format: 09XXXXXXXXX</div>
                            </div>
                            <p class="form-hint">You will receive a payment request in your GCash app.</p>
                        </div>

                        <!-- PayMaya -->
                        <div id="paymayaFields" class="fields-wrap">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" id="paymayaFirstName" name="paymayaFirstName" placeholder="Juan" data-required data-pattern="[A-Za-z\s]+">
                                    <div class="err" id="paymayaFirstNameErr">Letters only</div>
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" id="paymayaLastName" name="paymayaLastName" placeholder="Dela Cruz" data-required data-pattern="[A-Za-z\s]+">
                                    <div class="err" id="paymayaLastNameErr">Letters only</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>PayMaya Mobile Number</label>
                                <input type="text" id="paymayaNumber" name="paymayaNumber" placeholder="09XXXXXXXXX" data-required data-pattern="09[0-9]{9}" maxlength="11">
                                <div class="err" id="paymayaNumberErr">Format: 09XXXXXXXXX</div>
                            </div>
                            <p class="form-hint">You will receive a payment request in your PayMaya app.</p>
                        </div>

                        <!-- No method selected placeholder -->
                        <div id="noMethodMsg" style="text-align:center;padding:30px 0;color:rgba(249,249,249,0.2);font-size:0.85rem;">
                            ← Select a payment method to continue
                        </div>

                        <div id="statusBox" class="status-box"></div>
                        <button type="submit" class="btn-submit" id="submitBtn" disabled>Select a payment method</button>
                    </div>
                </div>

            </form>
        </div>

        <!-- ── RIGHT: Order summary ── -->
        <div class="sidebar">

            <!-- Movie info -->
            <div class="panel">
                <div class="panel-header"><h2>🎬 Your Booking</h2></div>
                <div class="movie-card">
                    <?php if ($movieDetails): ?>
                    <div class="movie-thumb" style="background-image:url('/<?= htmlspecialchars($movieDetails['MoviePoster']) ?>');"></div>
                    <?php endif; ?>
                    <div>
                        <h3><?= htmlspecialchars($movieDetails['MovieName'] ?? '—') ?></h3>
                        <div class="movie-card-meta">
                            <?php if ($mallDetails): ?>
                            <span>📍 <?= htmlspecialchars($mallDetails['MallName']) ?></span>
                            <?php endif; ?>
                            <?php if ($timeslotDetails): ?>
                            <span>🏛 <?= htmlspecialchars($timeslotDetails['TheaterName']) ?></span>
                            <span>📅 <?= date('F d, Y', strtotime($Date)) ?></span>
                            <span>🕐 <?= date('g:i A', strtotime($timeslotDetails['StartTime'])) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($timeslotDetails): ?>
                        <span class="type-pill"><?= htmlspecialchars($timeslotDetails['ScreeningType']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Order summary -->
            <div class="panel">
                <div class="panel-header"><h2>🎟 Order Summary</h2></div>
                <div class="panel-body">
                    <div class="order-row">
                        <span class="lbl">Seats</span>
                        <span class="val">
                            <div class="seats-wrap">
                                <?php foreach ($seatPositions as $sp): ?>
                                <span class="seat-chip"><?= htmlspecialchars($sp) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </span>
                    </div>
                    <div class="order-row">
                        <span class="lbl">Quantity</span>
                        <span class="val"><?= count($selectedSeats) ?> seat<?= count($selectedSeats) > 1 ? 's' : '' ?></span>
                    </div>
                </div>
                <div class="total-row">
                    <span class="total-lbl">Total Amount</span>
                    <span class="total-val">₱<?= number_format($totalPrice, 2) ?></span>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
let currentMethod = '';

function selectMethod(method) {
    currentMethod = method;
    document.getElementById(method).checked = true;

    // Update card styles
    document.querySelectorAll('.method-card').forEach(c => c.classList.remove('selected'));
    document.querySelector(`.method-card input[value="${method}"]`).closest('.method-card').classList.add('selected');

    // Show correct fields
    document.querySelectorAll('.fields-wrap').forEach(f => f.classList.remove('active'));
    document.getElementById(method + 'Fields').classList.add('active');
    document.getElementById('noMethodMsg').style.display = 'none';

    revalidate();
}

function validateField(el, showErr) {
    const val     = el.value.trim();
    const req     = el.hasAttribute('data-required');
    const pattern = el.getAttribute('data-pattern');
    const errEl   = document.getElementById(el.id + 'Err');
    el.classList.remove('valid', 'invalid');

    if (!req && !val) { if (errEl) errEl.style.display = 'none'; return true; }
    if (req && !val) {
        if (showErr) { el.classList.add('invalid'); if (errEl) errEl.style.display = 'block'; }
        return false;
    }

    let ok = true;
    if (el.id === 'cardNumber')  ok = el.value.replace(/\s/g,'').length >= 13;
    else if (el.id === 'expiryDate') ok = /^(0[1-9]|1[0-2])\/[0-9]{2}$/.test(el.value);
    else if (el.type === 'email') ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
    else if (pattern) ok = new RegExp('^' + pattern + '$').test(val);

    el.classList.add(ok ? 'valid' : 'invalid');
    if (errEl) errEl.style.display = ok ? 'none' : 'block';
    return ok;
}

function revalidate() {
    const statusEl = document.getElementById('statusBox');
    const btn      = document.getElementById('submitBtn');
    if (!currentMethod) { statusEl.style.display = 'none'; btn.disabled = true; btn.className = 'btn-submit'; btn.textContent = 'Select a payment method'; return; }

    const fields  = document.querySelectorAll(`#${currentMethod}Fields input[data-required]`);
    let allOk = true, anyFilled = false;
    fields.forEach(f => { if (f.value.trim()) anyFilled = true; if (!validateField(f, false)) allOk = false; });

    if (!anyFilled) { statusEl.style.display = 'none'; }
    else if (allOk) { statusEl.className = 'status-box ok'; statusEl.textContent = '✓ All fields valid. Ready to pay.'; statusEl.style.display = 'block'; }
    else { statusEl.className = 'status-box err'; statusEl.textContent = 'Please fill in all required fields correctly.'; statusEl.style.display = 'block'; }

    btn.disabled = !allOk;
    btn.className = allOk ? 'btn-submit active' : 'btn-submit';
    btn.textContent = allOk ? 'Complete Payment →' : 'Complete all fields to continue';
}

// Card number auto-format
document.getElementById('cardNumber')?.addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').replace(/(.{4})/g,'$1 ').trim();
    this.value = v; revalidate();
});
// Expiry auto-format
document.getElementById('expiryDate')?.addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'');
    if (v.length >= 2) v = v.slice(0,2) + '/' + v.slice(2,4);
    this.value = v; revalidate();
});

// Attach input/blur listeners to all required fields
document.querySelectorAll('input[data-required]').forEach(input => {
    input.addEventListener('input', revalidate);
    input.addEventListener('blur', function() { validateField(this, true); revalidate(); });
});

// Prevent submit if invalid
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    if (!currentMethod) { e.preventDefault(); alert('Please select a payment method.'); return; }
    const fields = document.querySelectorAll(`#${currentMethod}Fields input[data-required]`);
    let ok = true;
    fields.forEach(f => { if (!validateField(f, true)) ok = false; });
    if (!ok) { e.preventDefault(); alert('Please fill in all required fields correctly.'); }
});

// Hide header on scroll
(function() {
    const h = document.querySelector('header');
    let last = window.scrollY, tick = false;
    window.addEventListener('scroll', function() {
        if (!tick) {
            requestAnimationFrame(function() {
                const cur = window.scrollY;
                h.style.transform = (cur > last && cur > 80) ? 'translateY(-100%)' : 'translateY(0)';
                last = cur; tick = false;
            });
            tick = true;
        }
    }, { passive: true });
})();
</script>
</body>
</html>