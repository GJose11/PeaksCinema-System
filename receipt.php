<?php
session_start();

$Movie_ID = isset($_POST['movie_id']) ? $_POST['movie_id'] : '';
$Mall_ID = isset($_POST['mall_id']) ? $_POST['mall_id'] : '';
$Date = isset($_POST['date']) ? $_POST['date'] : '';
$TimeSlot_ID = isset($_POST['timeslot_id']) ? $_POST['timeslot_id'] : '';
$selectedSeats = isset($_POST['selectedSeats']) ? $_POST['selectedSeats'] : [];
$totalPrice = isset($_POST['totalPrice']) ? $_POST['totalPrice'] : 0;
$paymentMethod = isset($_POST['paymentMethod']) ? $_POST['paymentMethod'] : '';

// Ensure we have a valid logged-in customer before proceeding
if (isset($_SESSION['user_id'])) {
    $Customer_ID = $_SESSION['user_id'];
} elseif (isset($_SESSION['Customer_ID'])) {
    // Fallback in case a different session key is used elsewhere
    $Customer_ID = $_SESSION['Customer_ID'];
} else {
    // No user information in the session – force login and stop executing the script
    header("Location: login.php");
    exit;
}

$customerName = '';
if ($paymentMethod === 'credit') {
    $customerName = isset($_POST['cardFirstName']) ? $_POST['cardFirstName'] . ' ' . (isset($_POST['cardLastName']) ? $_POST['cardLastName'] : '') : '';
} elseif ($paymentMethod === 'paypal') {
    $customerName = isset($_POST['paypalFirstName']) ? $_POST['paypalFirstName'] . ' ' . (isset($_POST['paypalLastName']) ? $_POST['paypalLastName'] : '') : '';
} elseif ($paymentMethod === 'gcash') {
    $customerName = isset($_POST['gcashFirstName']) ? $_POST['gcashFirstName'] . ' ' . (isset($_POST['gcashLastName']) ? $_POST['gcashLastName'] : '') : '';
} elseif ($paymentMethod === 'paymaya') {
    $customerName = isset($_POST['paymayaFirstName']) ? $_POST['paymayaFirstName'] . ' ' . (isset($_POST['paymayaLastName']) ? $_POST['paymayaLastName'] : '') : '';
}

if (empty($paymentMethod)) {
    header("Location: payment.php");
    exit;
}

include("peakscinemas_database.php");

$movie_stmt = $conn->prepare("SELECT * FROM movie WHERE Movie_ID = ?");
$movie_stmt->bind_param("i", $Movie_ID);
$movie_stmt->execute();
$movieDetails = ($movie_stmt->get_result())->fetch_assoc();

$mall_stmt = $conn->prepare("SELECT * FROM mall WHERE Mall_ID = ?");
$mall_stmt->bind_param("i", $Mall_ID);
$mall_stmt->execute();
$mallDetails = ($mall_stmt->get_result())->fetch_assoc();


$timeslot_stmt = $conn->prepare("SELECT * FROM timeslot WHERE TimeSlot_ID = ?");
$timeslot_stmt->bind_param("i", $TimeSlot_ID);
$timeslot_stmt->execute();
$timeslotDetails = ($timeslot_stmt->get_result())->fetch_assoc();

$theater_stmt = $conn->prepare("SELECT TheaterName FROM theater WHERE Theater_ID = ?");
$theater_stmt->bind_param("i", $timeslotDetails['Theater_ID']);
$theater_stmt->execute();
$theaterDetails = ($theater_stmt->get_result())->fetch_assoc();

$seatPositions = [];
if (!empty($selectedSeats)) {
    // Create placeholders for the prepared statement
    $placeholders = str_repeat('?,', count($selectedSeats) - 1) . '?';
    $seat_stmt = $conn->prepare("SELECT Seat_ID, SeatRow, SeatColumn FROM seats WHERE Seat_ID IN ($placeholders)");
    
    // Bind parameters
    $types = str_repeat('i', count($selectedSeats));
    $seat_stmt->bind_param($types, ...$selectedSeats);
    $seat_stmt->execute();
    $seatResult = $seat_stmt->get_result();
    
    while ($seat = $seatResult->fetch_assoc()) {
        $seatPositions[] = $seat['SeatRow'] . $seat['SeatColumn'];
    }
    
    // Sort the seat positions for better display (A1, A2, B1, B2, etc.)
    sort($seatPositions);
}

$bookingRef = 'PC-' . date('Ymd') . '-' . rand(1000, 9999);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn -> begin_transaction();

    try {
        $seatUpdate_stmt = $conn -> prepare("UPDATE seats SET SeatAvailability = 0 WHERE Seat_ID = ?");

        foreach ($selectedSeats as $Seat_ID) {
            $seatUpdate_stmt -> bind_param("i", $Seat_ID);
            $seatUpdate_stmt -> execute();
        }

        $ticketIDs = [];
        $ticket_stmt = $conn -> prepare("INSERT INTO ticket(Seat_ID, Customer_ID, Movie_ID, TimeSlot_ID, Price, Status, DateTime)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");

        $Status = 1;
        $dateTime = date('Y-m-d H:i:s');    
        $price = $totalPrice / count($selectedSeats);

        foreach ($selectedSeats as $Seat_ID) {
            $ticket_stmt -> bind_param("iiiidis", $Seat_ID, $Customer_ID, $Movie_ID, $TimeSlot_ID, $price, $Status, $dateTime);
            $ticket_stmt -> execute();
            $ticketIDs[] = $conn -> insert_id;
        }

        $payment_stmt = $conn -> prepare("INSERT INTO payment(Ticket_ID, PaymentMethod, AmountPaid, PaymentDate, PaymentStatus)
                                        VALUES (?, ?, ?, ?, ?)");    

        $receipt_stmt = $conn -> prepare("INSERT INTO `e-receipt`(Payment_ID, DateIssued, ReceiptStatus, Status)
                                        VALUES (?, ?, ?, ?)");

        foreach ($ticketIDs as $Ticket_ID) {
            $payment_stmt -> bind_param("isdsi", $Ticket_ID, $paymentMethod, $price, $dateTime, $Status);
            $payment_stmt -> execute();
            $Payment_ID = $conn -> insert_id;

            $receipt_stmt -> bind_param("isii", $Payment_ID, $dateTime, $Status, $Status);
            $receipt_stmt -> execute();
        }

        $conn -> commit();
    } catch (Exception $e) {
        $conn -> rollback();
        throw $e;
    }    
}
?>

<!DOCTYPE html>
<html>
<head>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        header {
            background-color: #a3c2b1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 30px;
            border-bottom: 3px solid #4b4b4b;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #2b2b2b;
            color: white;
        }

        .logo img {
            height: 45px;
            width: auto;
        }

        nav {
            display: flex;
            gap: 10px;
        }

        nav a {
            background-color: #4b4b4b;
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 10px;
            border: 1px solid #a3c2b1;
            transition: 0.3s;
        }

        nav a:hover,
        nav a.active {
            background-color: #a3c2b1;
            color: #2b2b2b;
        }
       
        #topLinkSection {
            width: 50%;
            margin: 10px auto;
            display: flex;
            align-items: center;
            gap: 20px;
            border-radius: 10px;
        }

        .topLink {
            border-radius: 10px;
            width: auto;
            align-items: center;
            text-align: center;
        }

        .topLink a {
            background-color: #4b4b4b;
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 10px;
            border: 1px solid #a3c2b1;
            transition: 0.3s;
        }

        .topLink a#active {
            background-color: #a3c2b1;
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 10px;
            border: 1px solid #a3c2b1;
            font-weight: bold;
            color: #2b2b2b;
        }

        .topLink a:hover,
        .topLink a.active {
            background-color: #a3c2b1;
            color: #2b2b2b;
        }
       
        #receiptSection {
            background-color: #a3c2b1;
            padding: 20px;
            border: 5px solid black;
            width: 50%;
            margin: 5px auto;
            border-radius: 10px;
            color: #363635;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #4b4b4b;
            padding-bottom: 10px;
        }

        .receipt-details {
            margin-bottom: 20px;
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px dashed #4b4b4b;
        }

        .receipt-total {
            font-weight: bold;
            font-size: 18px;
            border-top: 2px solid #4b4b4b;
            padding-top: 10px;
            margin-top: 10px;
        }

        .qr-code {
            text-align: center;
            margin: 20px 0;
        }

        .qr-code img {
            width: 150px;
            height: 150px;
            border: 2px solid #4b4b4b;
            border-radius: 10px;
        }

        .btn {
            background-color: #4b4b4b;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
            margin-top: 10px;
            margin-right: 10px;
        }

        .btn:hover {
            background-color: #5c5c5c;
        }

        .btn-primary {
            background-color: #2b2b2b;
            width: 100%;
        }

        .btn-primary:hover {
            background-color: #3c3c3c;
        }

        .button-container {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            #receiptSection, #receiptSection * {
                visibility: visible;
            }
            #receiptSection {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                border: none;
                box-shadow: none;
            }
            .btn, .button-container {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <img src="peakscinema transparent.png" alt="PeaksCinemas Logo">
        </div>
        <nav>
            <a href="home.php" class="Active">Home</a>
            <a href="about.php">About Us</a>
        </nav>
    </header>

    <main>
        <div id="topLinkSection">
            <nav class="topLink">
                <a href="home.php">Home</a><p>&nbsp/&nbsp</p>
                <a href="movie.php?movie_id=<?= htmlspecialchars($Movie_ID) ?>">Malls with "<?= htmlspecialchars($movieDetails['MovieName']) ?>"</a><p>&nbsp/&nbsp</p>
                <a href="mall.php?movie_id=<?= htmlspecialchars($Movie_ID) ?>&mall_id=<?= htmlspecialchars($Mall_ID) ?>&date=<?= htmlspecialchars($Date) ?>">Available theaters in "<?= htmlspecialchars($mallDetails['MallName']) ?>"</a><p>&nbsp/&nbsp</p>
                <a href="seat_selection.php?movie_id=<?= htmlspecialchars($Movie_ID) ?>&mall_id=<?= htmlspecialchars($Mall_ID) ?>&date=<?= htmlspecialchars($Date) ?>&timeslot_id=<?= htmlspecialchars($TimeSlot_ID) ?>">Seats Selection in <?= htmlspecialchars($theaterDetails['TheaterName']) ?></a><p>&nbsp/&nbsp</p>
                <a href="payment.php">Payment</a><p>&nbsp/&nbsp</p>
                <a id="active">Receipt</a> 
            </nav>
        </div>

        <section id="receiptSection">
            <div class="receipt-header">
                <h2>Booking Confirmation</h2>
                <p>Thank you for your purchase!</p>
            </div>
            
            <div class="receipt-details">
                <div class="receipt-row">
                    <span>Movie:</span>
                    <span id="receiptMovieName"><?= htmlspecialchars($movieDetails['MovieName']) ?></span>
                </div>
                <div class="receipt-row">
                    <span>Cinema:</span>
                    <span id="receiptCinemaName"><?= htmlspecialchars($mallDetails['MallName']) ?> - <?= htmlspecialchars($theaterDetails['TheaterName']) ?></span>
                </div>
                <div class="receipt-row">
                    <span>Date & Time:</span>
                    <span id="receiptDateTime">
                        <?= htmlspecialchars($Date) ?> - 
                        <?php 
                        // FIXED: Use the same format as in mall.php - ScreeningType and StartTime
                        if (isset($timeslotDetails['ScreeningType']) && isset($timeslotDetails['StartTime'])) {
                            echo htmlspecialchars($timeslotDetails['ScreeningType'] . ' - ' . date("g:i A", strtotime($timeslotDetails['StartTime'])));
                        } else {
                            echo 'Time not available';
                        }
                        ?>
                    </span>
                </div>
                <div class="receipt-row">
                    <span>Seats:</span>
                    <span id="selectedSeatsReceipt">
                        <?php 
                        if (!empty($seatPositions)) {
                            echo implode(", ", $seatPositions);
                        } else {
                            echo 'No seats selected';
                        }
                        ?>
                    </span>
                </div>
                <div class="receipt-row">
                    <span>Tickets:</span>
                    <span id="ticketCountReceipt"><?= count($selectedSeats) ?></span>
                </div>
                <div class="receipt-row">
                    <span>Customer Name:</span>
                    <span id="customerNameReceipt"><?= !empty($customerName) ? htmlspecialchars($customerName) : '-' ?></span>
                </div>
                <div class="receipt-row">
                    <span>Payment Method:</span>
                    <span id="paymentMethodReceipt">
                        <?php 
                        switch($paymentMethod) {
                            case 'credit': echo 'Credit/Debit Card'; break;
                            case 'paypal': echo 'PayPal'; break;
                            case 'gcash': echo 'GCash'; break;
                            case 'paymaya': echo 'PayMaya'; break;
                            default: echo '-';
                        }
                        ?>
                    </span>
                </div>
                <div class="receipt-row receipt-total">
                    <span>Total:</span>
                    <span>₱<span id="totalReceipt"><?= number_format($totalPrice, 2) ?></span></span>
                </div>
            </div>
            
            <!-- Qr code dito, qrcode.png -->
            <div class="qr-code">
                <img src="qrcode.png" alt="QR Code">
            </div>
            
            <div class="receipt-header">
                <h3>Booking Reference</h3>
                <p id="bookingReference"><?= htmlspecialchars($bookingRef) ?></p>
            </div>
            
            <div class="button-container">
                <button class="btn btn-primary" onclick="downloadReceipt()">Download Receipt</button>
                <button class="btn" onclick="goHome()">Back to Home</button>
            </div>
        </section>
    </main>

    <script>
        // Download receipt as PDF using html2pdf library
        function downloadReceipt() {
          
            if (typeof html2pdf !== 'undefined') {
                const element = document.getElementById('receiptSection');
                const bookingRef = document.getElementById('bookingReference').textContent;
                
                const opt = {
                    margin: 10,
                    filename: `receipt_${bookingRef}.pdf`,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2 },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                };
                
                html2pdf().set(opt).from(element).save();
            } else {
                // Fallback to print if html2pdf is not available
                alert('PDF download feature requires html2pdf library. Printing instead.');
                printReceipt();
            }
        }
        
        // Print receipt
        function printReceipt() {
            window.print();
        }
        
        // Go back to home
        function goHome() {
            window.location.href = 'home.php';
        }
    </script>
    
    <!-- Include html2pdf library for PDF download functionality -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</body>
</html>