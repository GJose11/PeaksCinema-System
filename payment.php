<?php
include("peakscinemas_database.php");
session_start();

$profile_link  = "personal_info_form.php";
$profile_photo = $_SESSION['profile_photo'] ?? null;

if (isset($_SESSION['user_id'])) {
    $profile_link = "profile_edit.php";
    $stmt = $conn->prepare("SELECT ProfilePhoto FROM customer WHERE Customer_ID = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!empty($row['ProfilePhoto'])) {
        $profile_photo = $row['ProfilePhoto'];
        $_SESSION['profile_photo'] = $profile_photo;
    }
}

$Movie_ID    = isset($_POST['movie_id'])      ? $_POST['movie_id']      : '';
$Mall_ID     = isset($_POST['mall_id'])       ? $_POST['mall_id']       : '';
$Date        = isset($_POST['date'])          ? $_POST['date']          : '';
$TimeSlot_ID = isset($_POST['timeslot_id'])   ? $_POST['timeslot_id']   : '';
$selectedSeats = isset($_POST['selectedSeats']) ? $_POST['selectedSeats'] : [];
$totalPrice  = isset($_POST['priceTotal'])    ? $_POST['priceTotal']    : 0;

if (empty($selectedSeats) || $totalPrice <= 0) {
    header("Location: seat_selection.php?movie_id=" . $Movie_ID . "&mall_id=" . $Mall_ID . "&date=" . $Date . "&timeslot_id=" . $TimeSlot_ID);
    exit;
}

$_SESSION['booking_data'] = [
    'movie_id'      => $Movie_ID,
    'mall_id'       => $Mall_ID,
    'date'          => $Date,
    'timeslot_id'   => $TimeSlot_ID,
    'selectedSeats' => $selectedSeats,
    'totalPrice'    => $totalPrice
];

$movie_stmt = $conn->prepare("SELECT * FROM movie WHERE Movie_ID = ?");
$movie_stmt->bind_param("i", $Movie_ID); $movie_stmt->execute();
$movieDetails = ($movie_stmt->get_result())->fetch_assoc();

$mall_stmt = $conn->prepare("SELECT * FROM mall WHERE Mall_ID = ?");
$mall_stmt->bind_param("i", $Mall_ID); $mall_stmt->execute();
$mallDetails = ($mall_stmt->get_result())->fetch_assoc();

$timeslot_stmt = $conn->prepare("SELECT * FROM timeslot INNER JOIN theater ON timeslot.Theater_ID=theater.Theater_ID WHERE TimeSlot_ID = ?");
$timeslot_stmt->bind_param("i", $TimeSlot_ID); $timeslot_stmt->execute();
$timeslotDetails = ($timeslot_stmt->get_result())->fetch_assoc();

$seatPositions = [];
if (!empty($selectedSeats)) {
    $placeholders = str_repeat('?,', count($selectedSeats) - 1) . '?';
    $seat_stmt = $conn->prepare("SELECT Seat_ID, SeatRow, SeatColumn FROM seats WHERE Seat_ID IN ($placeholders)");
    $types = str_repeat('i', count($selectedSeats));
    $seat_stmt->bind_param($types, ...$selectedSeats);
    $seat_stmt->execute();
    $seatResult = $seat_stmt->get_result();
    while ($seat = $seatResult->fetch_assoc()) {
        $seatPositions[] = $seat['SeatRow'] . $seat['SeatColumn'];
    }
    sort($seatPositions);
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: url("movie-background-collage.jpg") no-repeat center center fixed; background-size: cover; color: #F9F9F9; padding-top: 100px; padding-bottom: 40px; min-height: 100vh; }
        header { background-color: #1C1C1C; display: flex; justify-content: space-between; align-items: center; padding: 10px 30px; position: fixed; top: 0; left: 0; width: 100%; z-index: 1000; }
        .logo img { height: 50px; cursor: pointer; filter: invert(1); transition: transform 0.2s ease; }
        .logo img:hover { transform: scale(1.05); }
        .profile-btn { background-color: transparent; border: 1px solid rgba(255,255,255,0.1); border-radius: 50%; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.2); overflow: hidden; padding: 0; font-size: 1.2rem; }
        .profile-btn img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        .profile-btn:hover { transform: scale(1.1); box-shadow: 0 0 12px rgba(255,255,255,0.3); }
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
</head>
<body>
<header>
    <div class="logo">
        <img src="peakscinematransparent.png" alt="PeaksCinemas Logo" onclick="window.location.href='home.php'">
    </div>
    <button class="profile-btn" onclick="window.location.href='<?= $profile_link ?>'" title="Profile">
        <?php if (!empty($profile_photo)): ?>
            <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile" referrerpolicy="no-referrer">
        <?php else: ?>
            👤
        <?php endif; ?>
    </button>
</header>

<main>
    <!-- Booking summary panel -->
    <div class="panel">
        <div class="summary-bar">
            <div>
                <div class="summary-label">Seats</div>
                <div class="summary-value"><?php echo !empty($seatPositions) ? implode(', ', $seatPositions) : 'None'; ?></div>
            </div>
            <div class="summary-divider"></div>
            <div>
                <div class="summary-label">Total Price</div>
                <div class="summary-value">₱<?= number_format($totalPrice, 2) ?></div>
            </div>
            <?php if ($movieDetails): ?>
            <div class="summary-divider"></div>
            <div>
                <div class="summary-label">Movie</div>
                <div class="summary-value"><?= htmlspecialchars($movieDetails['MovieName']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($Date)): ?>
            <div class="summary-divider"></div>
            <div>
                <div class="summary-label">Date</div>
                <div class="summary-value"><?= htmlspecialchars($Date) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment form panel -->
    <form id="paymentForm" action="receipt.php" method="POST" novalidate>
        <input type="hidden" name="movie_id"    value="<?= htmlspecialchars($Movie_ID) ?>">
        <input type="hidden" name="mall_id"     value="<?= htmlspecialchars($Mall_ID) ?>">
        <input type="hidden" name="date"        value="<?= htmlspecialchars($Date) ?>">
        <input type="hidden" name="timeslot_id" value="<?= htmlspecialchars($TimeSlot_ID) ?>">
        <input type="hidden" name="totalPrice"  value="<?= htmlspecialchars($totalPrice) ?>">
        <?php foreach ($selectedSeats as $seat): ?>
            <input type="hidden" name="selectedSeats[]" value="<?= htmlspecialchars($seat) ?>">
        <?php endforeach; ?>

        <div class="panel">
            <h2>Payment Method</h2>
            <div class="payment-methods">
                <div class="payment-option" onclick="selectPaymentMethod('credit')">
                    <input type="radio" id="credit" name="paymentMethod" value="credit">
                    <img src="visa.png" alt="Visa" class="payment-logo">
                    <label for="credit" class="payment-label">Credit / Debit Card</label>
                </div>
                <div class="payment-option" onclick="selectPaymentMethod('paypal')">
                    <input type="radio" id="paypal" name="paymentMethod" value="paypal">
                    <img src="paypal.png" alt="PayPal" class="payment-logo">
                    <label for="paypal" class="payment-label">PayPal</label>
                </div>
                <div class="payment-option" onclick="selectPaymentMethod('gcash')">
                    <input type="radio" id="gcash" name="paymentMethod" value="gcash">
                    <img src="gcash.png" alt="GCash" class="payment-logo">
                    <label for="gcash" class="payment-label">GCash</label>
                </div>
                <div class="payment-option" onclick="selectPaymentMethod('paymaya')">
                    <input type="radio" id="paymaya" name="paymentMethod" value="paymaya">
                    <img src="paymaya.png" alt="PayMaya" class="payment-logo">
                    <label for="paymaya" class="payment-label">PayMaya</label>
                </div>
            </div>

            <!-- Credit Card -->
            <div id="creditDetails" class="payment-details">
                <div class="form-row">
                    <div class="form-group">
                        <label for="cardFirstName">First Name</label>
                        <input type="text" id="cardFirstName" name="cardFirstName" placeholder="John" data-pattern="[A-Za-z\s]+" data-required="true">
                        <div class="error-message" id="cardFirstNameError">Letters only</div>
                    </div>
                    <div class="form-group">
                        <label for="cardLastName">Last Name</label>
                        <input type="text" id="cardLastName" name="cardLastName" placeholder="Doe" data-pattern="[A-Za-z\s]+" data-required="true">
                        <div class="error-message" id="cardLastNameError">Letters only</div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="cardNumber">Card Number</label>
                    <input type="text" id="cardNumber" name="cardNumber" placeholder="1234 5678 9012 3456" data-pattern="[0-9\s]{13,19}" data-required="true" maxlength="19">
                    <div class="error-message" id="cardNumberError">Enter a valid card number (13–16 digits)</div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="expiryDate">Expiry Date</label>
                        <input type="text" id="expiryDate" name="expiryDate" placeholder="MM/YY" data-pattern="(0[1-9]|1[0-2])\/[0-9]{2}" data-required="true" maxlength="5">
                        <div class="error-message" id="expiryDateError">Format: MM/YY</div>
                    </div>
                    <div class="form-group">
                        <label for="cvv">CVV</label>
                        <input type="text" id="cvv" name="cvv" placeholder="123" data-pattern="[0-9]{3,4}" data-required="true" maxlength="4">
                        <div class="error-message" id="cvvError">3–4 digits</div>
                    </div>
                </div>
            </div>

            <!-- PayPal -->
            <div id="paypalDetails" class="payment-details">
                <div class="form-row">
                    <div class="form-group">
                        <label for="paypalFirstName">First Name</label>
                        <input type="text" id="paypalFirstName" name="paypalFirstName" placeholder="John" data-pattern="[A-Za-z\s]+" data-required="true">
                        <div class="error-message" id="paypalFirstNameError">Letters only</div>
                    </div>
                    <div class="form-group">
                        <label for="paypalLastName">Last Name</label>
                        <input type="text" id="paypalLastName" name="paypalLastName" placeholder="Doe" data-pattern="[A-Za-z\s]+" data-required="true">
                        <div class="error-message" id="paypalLastNameError">Letters only</div>
                    </div>
                    <div class="form-group">
                        <label for="paypalPhone">Phone Number</label>
                        <input type="text" id="paypalPhone" name="paypalPhone" placeholder="09XX XXX XXXX" data-pattern="09[0-9]{9}" data-required="true" maxlength="11">
                        <div class="error-message" id="paypalPhoneError">Format: 09XXXXXXXXX</div>
                    </div>
                    <div class="form-group">
                        <label for="paypalEmail">Email Address</label>
                        <input type="email" id="paypalEmail" name="paypalEmail" placeholder="you@example.com" data-required="true">
                        <div class="error-message" id="paypalEmailError">Enter a valid email</div>
                    </div>
                </div>
                <p>You will be redirected to PayPal to complete your payment.</p>
            </div>

            <!-- GCash -->
            <div id="gcashDetails" class="payment-details">
                <div class="form-row">
                    <div class="form-group">
                        <label for="gcashFirstName">First Name</label>
                        <input type="text" id="gcashFirstName" name="gcashFirstName" placeholder="John" data-pattern="[A-Za-z\s]+" data-required="true">
                        <div class="error-message" id="gcashFirstNameError">Letters only</div>
                    </div>
                    <div class="form-group">
                        <label for="gcashLastName">Last Name</label>
                        <input type="text" id="gcashLastName" name="gcashLastName" placeholder="Doe" data-pattern="[A-Za-z\s]+" data-required="true">
                        <div class="error-message" id="gcashLastNameError">Letters only</div>
                    </div>
                    <div class="form-group">
                        <label for="gcashNumber">GCash Mobile Number</label>
                        <input type="text" id="gcashNumber" name="gcashNumber" placeholder="09XX XXX XXXX" data-pattern="09[0-9]{9}" data-required="true" maxlength="11">
                        <div class="error-message" id="gcashNumberError">Format: 09XXXXXXXXX</div>
                    </div>
                </div>
                <p>You will receive a payment request on your GCash app.</p>
            </div>

            <!-- PayMaya -->
            <div id="paymayaDetails" class="payment-details">
                <div class="form-row">
                    <div class="form-group">
                        <label for="paymayaFirstName">First Name</label>
                        <input type="text" id="paymayaFirstName" name="paymayaFirstName" placeholder="John" data-pattern="[A-Za-z\s]+" data-required="true">
                        <div class="error-message" id="paymayaFirstNameError">Letters only</div>
                    </div>
                    <div class="form-group">
                        <label for="paymayaLastName">Last Name</label>
                        <input type="text" id="paymayaLastName" name="paymayaLastName" placeholder="Doe" data-pattern="[A-Za-z\s]+" data-required="true">
                        <div class="error-message" id="paymayaLastNameError">Letters only</div>
                    </div>
                    <div class="form-group">
                        <label for="paymayaNumber">PayMaya Mobile Number</label>
                        <input type="text" id="paymayaNumber" name="paymayaNumber" placeholder="09XX XXX XXXX" data-pattern="09[0-9]{9}" data-required="true" maxlength="11">
                        <div class="error-message" id="paymayaNumberError">Format: 09XXXXXXXXX</div>
                    </div>
                </div>
                <p>You will receive a payment request on your PayMaya app.</p>
            </div>

            <div id="validationStatus" class="validation-status"></div>
            <div class="submit-row">
                <button type="submit" id="submitButton" disabled>Complete Payment</button>
            </div>
        </div>
    </form>
</main>

<script>
    let currentPaymentMethod = '';

    function selectPaymentMethod(method) {
        document.getElementById(method).checked = true;
        currentPaymentMethod = method;
        document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
        document.querySelector(`.payment-option input[value="${method}"]`).closest('.payment-option').classList.add('selected');
        document.querySelectorAll('.payment-details').forEach(d => d.classList.remove('active'));
        document.getElementById(method + 'Details').classList.add('active');
        validateCurrentForm();
    }

    function formatCardNumber(input) {
        let value = input.value.replace(/\D/g, '');
        value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
        input.value = value;
        return value.replace(/\s/g, '');
    }

    function formatExpiryDate(input) {
        let value = input.value.replace(/\D/g, '');
        if (value.length >= 2) value = value.substring(0, 2) + '/' + value.substring(2, 4);
        input.value = value;
        return value;
    }

    function validateField(field, showEmpty = true) {
        const value = field.value.trim();
        const pattern = field.getAttribute('data-pattern');
        const isRequired = field.getAttribute('data-required') === 'true';
        const errorEl = document.getElementById(field.id + 'Error');
        field.classList.remove('valid', 'invalid');
        if (!isRequired && value === '') { if (errorEl) errorEl.style.display = 'none'; return true; }
        if (isRequired && value === '') { if (showEmpty) { field.classList.add('invalid'); if (errorEl) errorEl.style.display = 'block'; } return false; }
        let isValid = true;
        if (field.id === 'cardNumber') { isValid = formatCardNumber(field).length >= 13; }
        else if (field.id === 'expiryDate') { isValid = /^(0[1-9]|1[0-2])\/[0-9]{2}$/.test(formatExpiryDate(field)); }
        else if (field.type === 'email') { isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value); }
        else if (pattern) { isValid = new RegExp('^' + pattern + '$').test(value); }
        field.classList.add(isValid ? 'valid' : 'invalid');
        if (errorEl) errorEl.style.display = isValid ? 'none' : 'block';
        return isValid;
    }

    function validateCurrentForm() {
        const statusEl = document.getElementById('validationStatus');
        const submitBtn = document.getElementById('submitButton');
        if (!currentPaymentMethod) { statusEl.style.display = 'none'; submitBtn.disabled = true; return false; }
        const activeDetails = document.querySelector('.payment-details.active');
        if (!activeDetails) { submitBtn.disabled = true; return false; }
        const fields = activeDetails.querySelectorAll('input[data-required="true"]');
        let allValid = true, filled = 0;
        fields.forEach(f => { if (!validateField(f)) allValid = false; if (f.value.trim() !== '') filled++; });
        if (filled === 0) { statusEl.style.display = 'none'; statusEl.className = 'validation-status'; }
        else if (allValid) { statusEl.style.display = 'block'; statusEl.textContent = '✓ All fields valid. Ready to complete payment.'; statusEl.className = 'validation-status valid'; }
        else { statusEl.style.display = 'block'; statusEl.textContent = 'Please fill in all required fields correctly.'; statusEl.className = 'validation-status invalid'; }
        submitBtn.disabled = !allValid;
        return allValid;
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('input[data-required="true"]').forEach(input => {
            input.addEventListener('input', function () {
                const active = document.querySelector('.payment-details.active');
                if (active && active.contains(this)) { validateField(this, this.value.trim() !== ''); validateCurrentForm(); }
            });
            input.addEventListener('blur', function () {
                const active = document.querySelector('.payment-details.active');
                if (active && active.contains(this)) { validateField(this, true); validateCurrentForm(); }
            });
        });
        const cardInput = document.getElementById('cardNumber');
        if (cardInput) cardInput.addEventListener('input', () => formatCardNumber(cardInput));
        const expiryInput = document.getElementById('expiryDate');
        if (expiryInput) expiryInput.addEventListener('input', () => formatExpiryDate(expiryInput));
        document.getElementById('paymentForm').addEventListener('submit', function (e) {
            if (!document.querySelector('input[name="paymentMethod"]:checked')) { e.preventDefault(); alert('Please select a payment method'); return; }
            if (!validateCurrentForm()) { e.preventDefault(); alert('Please fill in all required fields correctly.'); }
        });
        validateCurrentForm();
    });
</script>
</body>
</html>