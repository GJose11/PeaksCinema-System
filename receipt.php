<?php
session_start();

$Movie_ID      = isset($_POST['movie_id'])      ? $_POST['movie_id']      : '';
$Mall_ID       = isset($_POST['mall_id'])        ? $_POST['mall_id']        : '';
$Date          = isset($_POST['date'])           ? $_POST['date']           : '';
$TimeSlot_ID   = isset($_POST['timeslot_id'])    ? $_POST['timeslot_id']    : '';
$selectedSeats = isset($_POST['selectedSeats'])  ? $_POST['selectedSeats']  : [];
$totalPrice    = isset($_POST['totalPrice'])      ? $_POST['totalPrice']     : 0;
$paymentMethod = isset($_POST['paymentMethod'])  ? $_POST['paymentMethod']  : '';

if (isset($_SESSION['user_id'])) {
    $Customer_ID = $_SESSION['user_id'];
} elseif (isset($_SESSION['Customer_ID'])) {
    $Customer_ID = $_SESSION['Customer_ID'];
} else {
    header("Location: personal_info_form.php");
    exit;
}

$profile_link  = "profile_edit.php";
$profile_photo = $_SESSION['profile_photo'] ?? null;

$customerName = '';
if ($paymentMethod === 'credit') {
    $customerName = trim(($_POST['cardFirstName'] ?? '') . ' ' . ($_POST['cardLastName'] ?? ''));
} elseif ($paymentMethod === 'paypal') {
    $customerName = trim(($_POST['paypalFirstName'] ?? '') . ' ' . ($_POST['paypalLastName'] ?? ''));
} elseif ($paymentMethod === 'gcash') {
    $customerName = trim(($_POST['gcashFirstName'] ?? '') . ' ' . ($_POST['gcashLastName'] ?? ''));
} elseif ($paymentMethod === 'paymaya') {
    $customerName = trim(($_POST['paymayaFirstName'] ?? '') . ' ' . ($_POST['paymayaLastName'] ?? ''));
}

if (empty($paymentMethod)) {
    header("Location: payment.php");
    exit;
}

include("peakscinemas_database.php");

// Fetch profile photo from DB
$stmt = $conn->prepare("SELECT Name, Email, ProfilePhoto FROM customer WHERE Customer_ID = ?");
$stmt->bind_param("i", $Customer_ID);
$stmt->execute();
$customerRow = $stmt->get_result()->fetch_assoc();
$customerEmail = $customerRow['Email'] ?? '';
if (!empty($customerRow['ProfilePhoto'])) {
    $profile_photo = $customerRow['ProfilePhoto'];
    $_SESSION['profile_photo'] = $profile_photo;
}
$user_initials = '';
$nameParts = explode(' ', trim($customerRow['Name'] ?? ''));
$user_initials = strtoupper(substr($nameParts[0]??'',0,1).substr(end($nameParts)??'',0,1));
if (strlen($user_initials)===1) $user_initials = strtoupper(substr($nameParts[0]??'',0,2));

$movie_stmt = $conn->prepare("SELECT * FROM movie WHERE Movie_ID = ?");
$movie_stmt->bind_param("i", $Movie_ID); $movie_stmt->execute();
$movieDetails = ($movie_stmt->get_result())->fetch_assoc();

$mall_stmt = $conn->prepare("SELECT * FROM mall WHERE Mall_ID = ?");
$mall_stmt->bind_param("i", $Mall_ID); $mall_stmt->execute();
$mallDetails = ($mall_stmt->get_result())->fetch_assoc();

$timeslot_stmt = $conn->prepare("SELECT * FROM timeslot WHERE TimeSlot_ID = ?");
$timeslot_stmt->bind_param("i", $TimeSlot_ID); $timeslot_stmt->execute();
$timeslotDetails = ($timeslot_stmt->get_result())->fetch_assoc();

$theater_stmt = $conn->prepare("SELECT TheaterName FROM theater WHERE Theater_ID = ?");
$theater_stmt->bind_param("i", $timeslotDetails['Theater_ID']); $theater_stmt->execute();
$theaterDetails = ($theater_stmt->get_result())->fetch_assoc();

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

$bookingRef = 'PC-' . date('Ymd') . '-' . rand(1000, 9999);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();
    try {
        $seatUpdate_stmt = $conn->prepare("UPDATE seats SET SeatAvailability = 0 WHERE Seat_ID = ?");
        foreach ($selectedSeats as $Seat_ID) {
            $seatUpdate_stmt->bind_param("i", $Seat_ID);
            $seatUpdate_stmt->execute();
        }

        $ticketIDs = [];
        $ticket_stmt = $conn->prepare("INSERT INTO ticket(Seat_ID, Customer_ID, Movie_ID, TimeSlot_ID, Price, Status, DateTime) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $Status   = 1;
        $dateTime = date('Y-m-d H:i:s');
        $price    = $totalPrice / count($selectedSeats);

        foreach ($selectedSeats as $Seat_ID) {
            $ticket_stmt->bind_param("iiiidis", $Seat_ID, $Customer_ID, $Movie_ID, $TimeSlot_ID, $price, $Status, $dateTime);
            $ticket_stmt->execute();
            $ticketIDs[] = $conn->insert_id;
        }

        $payment_stmt = $conn->prepare("INSERT INTO payment(Ticket_ID, PaymentMethod, AmountPaid, PaymentDate, PaymentStatus) VALUES (?, ?, ?, ?, ?)");
        $receipt_stmt = $conn->prepare("INSERT INTO `e-receipt`(PaymentID, DateIssued, SentToEmail, ReceiptStatus, Status) VALUES (?, ?, ?, ?, ?)");

        foreach ($ticketIDs as $Ticket_ID) {
            $payment_stmt->bind_param("isdsi", $Ticket_ID, $paymentMethod, $price, $dateTime, $Status);
            $payment_stmt->execute();
            $Payment_ID  = $conn->insert_id;
            $dateIssued  = date('Y-m-d', strtotime($dateTime));
            $receipt_stmt->bind_param("issii", $Payment_ID, $dateIssued, $customerEmail, $Status, $Status);
            $receipt_stmt->execute();
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

$paymentLabel = match($paymentMethod) {
    'credit'  => 'Credit / Debit Card',
    'paypal'  => 'PayPal',
    'gcash'   => 'GCash',
    'paymaya' => 'PayMaya',
    default   => '-'
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <title>PeaksCinemas – Booking Confirmation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Outfit', sans-serif;
            background: url("movie-background-collage.jpg") no-repeat center center fixed;
            background-size: cover;
            color: #F9F9F9;
            min-height: 100vh;
            padding-top: 100px;
            padding-bottom: 50px;
        }

        /* ── Header ── */
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
        }

        .logo img {
            height: 50px;
            cursor: pointer;
            filter: invert(1);
            transition: transform 0.2s ease;
        }
        .logo img:hover { transform: scale(1.05); }

        .profile-btn {
            background-color: #F9F9F9;
            border: none;
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
        .profile-btn img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        .profile-btn:hover { transform: scale(1.1); box-shadow: 0 0 12px rgba(255,255,255,0.3); }
        .profile-btn img { width:100%; height:100%; object-fit:cover; border-radius:50%; }
        .profile-initials {
            width: 100%; height: 100%; border-radius: 50%;
            background: linear-gradient(135deg, #ff4d4d, #c0392b);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.82rem; font-weight: 800; color: #fff;
            letter-spacing: 0.5px; font-family: 'Outfit', sans-serif;
        }

        /* ── Main layout ── */
        main {
            width: 90%;
            max-width: 620px;
            margin: 30px auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* ── Success banner ── */
        .success-banner {
            backdrop-filter: blur(2px);
            background: rgba(0,0,0,0.4);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.6);
            padding: 24px 20px;
            text-align: center;
        }

        .success-icon {
            width: 60px; height: 60px;
            border-radius: 50%;
            background: rgba(255,77,77,0.15);
            border: 2px solid #ff4d4d;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem;
            margin: 0 auto 14px;
        }

        .success-banner h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #F9F9F9;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .success-banner p {
            font-size: 0.85rem;
            color: rgba(249,249,249,0.5);
        }

        .booking-ref {
            display: inline-block;
            margin-top: 12px;
            background: rgba(255,77,77,0.1);
            border: 1px solid rgba(255,77,77,0.3);
            border-radius: 8px;
            padding: 6px 18px;
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 2px;
            color: #ff4d4d;
        }

        /* ── Receipt card ── */
        .receipt-card {
            backdrop-filter: blur(2px);
            background: rgba(0,0,0,0.4);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.6);
            overflow: hidden;
        }

        .receipt-card-header {
            background: rgba(255,77,77,0.08);
            border-bottom: 1px solid rgba(255,77,77,0.2);
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .receipt-card-header h2 {
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #ff4d4d;
        }

        .receipt-body { padding: 6px 20px 18px; }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            padding: 11px 0;
            border-bottom: 1px solid rgba(249,249,249,0.07);
        }

        .receipt-row:last-child { border-bottom: none; }

        .receipt-label {
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.4);
            flex-shrink: 0;
        }

        .receipt-value {
            font-size: 0.85rem;
            font-weight: 600;
            color: #F9F9F9;
            text-align: right;
        }

        /* ── Total row ── */
        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            background: rgba(255,77,77,0.07);
            border-top: 1px solid rgba(255,77,77,0.2);
        }

        .receipt-total-label {
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.6);
        }

        .receipt-total-value {
            font-size: 1.3rem;
            font-weight: 800;
            color: #ff4d4d;
        }

        /* ── Seat badges ── */
        .seat-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            justify-content: flex-end;
        }

        .seat-badge {
            background: rgba(255,77,77,0.12);
            border: 1px solid rgba(255,77,77,0.3);
            border-radius: 5px;
            padding: 2px 8px;
            font-size: 0.78rem;
            font-weight: 600;
            color: #ff4d4d;
        }

        /* ── QR section ── */
        .qr-section {
            backdrop-filter: blur(2px);
            background: rgba(0,0,0,0.4);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.6);
            padding: 20px;
            text-align: center;
        }

        .qr-section p {
            font-size: 0.72rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.35);
            margin-bottom: 12px;
        }

        .qr-section img {
            width: 130px; height: 130px;
            border-radius: 10px;
            border: 2px solid rgba(249,249,249,0.1);
            background: #fff;
            padding: 6px;
        }

        /* ── Action buttons ── */
        .action-row {
            display: flex;
            gap: 10px;
        }

        .btn-primary {
            flex: 1;
            padding: 11px;
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
        .btn-primary:hover { background-color: #e03c3c; transform: scale(1.02); }

        .btn-secondary {
            flex: 1;
            padding: 11px;
            border-radius: 8px;
            border: 1px solid rgba(249,249,249,0.2);
            background: rgba(249,249,249,0.05);
            color: #F9F9F9;
            font-family: 'Outfit', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
        }
        .btn-secondary:hover { background: rgba(249,249,249,0.1); transform: scale(1.02); }

        /* ── Print styles ── */
        @media print {
            body { background: #fff; color: #000; padding: 0; }
            header, .action-row, .qr-section p { display: none !important; }
            .receipt-card, .success-banner, .qr-section {
                box-shadow: none;
                background: #fff !important;
                color: #000 !important;
            }
            .receipt-label { color: #555 !important; }
            .receipt-value, .success-banner h1 { color: #000 !important; }
            .receipt-total-value, .booking-ref { color: #c0392b !important; }
        }
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
        <?php elseif (!empty($user_initials)): ?>
            <div class="profile-initials"><?= htmlspecialchars($user_initials) ?></div>
        <?php else: ?>
            <div class="profile-initials">?</div>
        <?php endif; ?>
    </button>
</header>

<main>

    <!-- Success banner -->
    <div class="success-banner">
        <div class="success-icon">🎬</div>
        <h1>Booking Confirmed!</h1>
        <p>Your tickets have been booked successfully.<br>Enjoy the show!</p>
        <div class="booking-ref"><?= htmlspecialchars($bookingRef) ?></div>
    </div>

    <!-- Receipt details -->
    <div class="receipt-card">
        <div class="receipt-card-header">
            <h2>🎟 Booking Details</h2>
        </div>
        <div class="receipt-body">
            <div class="receipt-row">
                <span class="receipt-label">Movie</span>
                <span class="receipt-value"><?= htmlspecialchars($movieDetails['MovieName']) ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Cinema</span>
                <span class="receipt-value"><?= htmlspecialchars($mallDetails['MallName']) ?><br><span style="color:rgba(249,249,249,0.5);font-size:0.78rem;"><?= htmlspecialchars($theaterDetails['TheaterName']) ?></span></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Date & Time</span>
                <span class="receipt-value">
                    <?= htmlspecialchars($Date) ?><br>
                    <span style="color:rgba(249,249,249,0.5);font-size:0.78rem;">
                    <?php
                    if (isset($timeslotDetails['ScreeningType']) && isset($timeslotDetails['StartTime'])) {
                        echo htmlspecialchars($timeslotDetails['ScreeningType'] . ' · ' . date("g:i A", strtotime($timeslotDetails['StartTime'])));
                    } else {
                        echo 'Time not available';
                    }
                    ?>
                    </span>
                </span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Seats</span>
                <span class="receipt-value">
                    <div class="seat-badges">
                        <?php foreach ($seatPositions as $sp): ?>
                            <span class="seat-badge"><?= htmlspecialchars($sp) ?></span>
                        <?php endforeach; ?>
                    </div>
                </span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Tickets</span>
                <span class="receipt-value"><?= count($selectedSeats) ?> ticket<?= count($selectedSeats) > 1 ? 's' : '' ?></span>
            </div>
            <?php if (!empty($customerName)): ?>
            <div class="receipt-row">
                <span class="receipt-label">Customer</span>
                <span class="receipt-value"><?= htmlspecialchars($customerName) ?></span>
            </div>
            <?php endif; ?>
            <div class="receipt-row">
                <span class="receipt-label">Payment</span>
                <span class="receipt-value"><?= htmlspecialchars($paymentLabel) ?></span>
            </div>
        </div>
        <div class="receipt-total-row">
            <span class="receipt-total-label">Total Paid</span>
            <span class="receipt-total-value">₱<?= number_format($totalPrice, 2) ?></span>
        </div>
    </div>

    <!-- QR Code -->
    <div class="qr-section">
        <p>Scan at entrance</p>
        <img src="qrcode.png" alt="QR Code">
    </div>

    <!-- Action buttons -->
    <div class="action-row">
        <button class="btn-primary" onclick="downloadReceipt()">⬇ Download Receipt</button>
        <button class="btn-secondary" onclick="window.location.href='home.php'">← Back to Home</button>
    </div>

</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    function downloadReceipt() {
        if (typeof html2pdf !== 'undefined') {
            const element = document.querySelector('main');
            const bookingRef = '<?= htmlspecialchars($bookingRef) ?>';
            const opt = {
                margin: 10,
                filename: `receipt_${bookingRef}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        } else {
            window.print();
        }
    }
</script>

</body>
</html>