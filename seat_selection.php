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

$Movie_ID    = filter_input(INPUT_GET, 'movie_id',    FILTER_VALIDATE_INT);
$Mall_ID     = filter_input(INPUT_GET, 'mall_id',     FILTER_VALIDATE_INT);
$Date        = filter_input(INPUT_GET, 'date');
$TimeSlot_ID = filter_input(INPUT_GET, 'timeslot_id', FILTER_VALIDATE_INT);

if (!$Movie_ID || !$Mall_ID || !$Date || !$TimeSlot_ID) { header("Location: home.php"); exit; }

$movie_stmt = $conn->prepare("SELECT * FROM movie WHERE Movie_ID = ?");
$movie_stmt->bind_param("i", $Movie_ID); $movie_stmt->execute();
$movieDetails = ($movie_stmt->get_result())->fetch_assoc();

$mall_stmt = $conn->prepare("SELECT * FROM mall WHERE Mall_ID = ?");
$mall_stmt->bind_param("i", $Mall_ID); $mall_stmt->execute();
$mallDetails = ($mall_stmt->get_result())->fetch_assoc();

$timeslot_stmt = $conn->prepare("SELECT * FROM timeslot INNER JOIN theater ON timeslot.Theater_ID = theater.Theater_ID WHERE TimeSlot_ID = ?");
$timeslot_stmt->bind_param("i", $TimeSlot_ID); $timeslot_stmt->execute();
$timeslotDetails = ($timeslot_stmt->get_result())->fetch_assoc();

if (!$movieDetails || !$mallDetails || !$timeslotDetails) { header("Location: home.php"); exit; }

$seats_stmt = $conn->prepare("SELECT * FROM seats WHERE TimeSlot_ID = ?");
$seats_stmt->bind_param("i", $TimeSlot_ID); $seats_stmt->execute();
$seatLayout = $seats_stmt->get_result();
$layoutProper = [];
if ($seatLayout) {
    while ($seat = $seatLayout->fetch_assoc()) {
        $layoutProper[$seat['SeatRow']][] = [
            'Seat_ID'          => $seat['Seat_ID'],
            'SeatType'         => $seat['SeatType'],
            'SeatPrice'        => $seat['SeatPrice'],
            'SeatAvailability' => $seat['SeatAvailability'],
            'SeatColumn'       => $seat['SeatColumn']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Outfit',sans-serif;background:url("movie-background-collage.jpg") no-repeat center center fixed;background-size:cover;color:#F9F9F9;padding-top:100px;padding-bottom:40px;min-height:100vh}
        header{background-color:#1C1C1C;display:flex;justify-content:space-between;align-items:center;padding:10px 30px;position:fixed;top:0;left:0;width:100%;z-index:1000}
        .logo img{height:50px;cursor:pointer;filter:invert(1);transition:transform 0.2s ease}
        .logo img:hover{transform:scale(1.05)}
        .profile-btn{background-color:transparent;border: 1px solid rgba(255,255,255,0.1);border-radius:50%;width:45px;height:45px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.3s ease;box-shadow:0 2px 4px rgba(0,0,0,0.2);overflow:hidden;padding:0;font-size:1.2rem;}
        .profile-btn img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
        .profile-btn:hover{transform:scale(1.1);box-shadow:0 0 12px rgba(255,255,255,0.3);}
        main{margin:30px auto;width:95%;max-width:1100px;display:flex;flex-direction:column;gap:20px}
        .panel{backdrop-filter:blur(2px);background-color:rgba(0,0,0,0.4);border-radius:8px;box-shadow:0 2px 5px rgba(0,0,0,0.6);padding:20px 15px;overflow:hidden}
        h2{font-size:1rem;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:#F9F9F9;margin-bottom:18px}
        #screenLabel{text-align:center;margin-bottom:20px}
        .screen-bar{display:inline-block;background:rgba(249,249,249,0.15);border:1px solid rgba(249,249,249,0.3);border-radius:6px;padding:6px 40px;font-size:0.75rem;font-weight:600;letter-spacing:4px;color:#F9F9F9;text-transform:uppercase}
        .screen-glow{width:80%;height:3px;margin:4px auto 0;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.25),transparent);border-radius:2px}
        .seats-scroll-wrapper{overflow-x:auto;-webkit-overflow-scrolling:touch;padding-bottom:8px;width:100%}
        .seats-scroll-wrapper::-webkit-scrollbar{height:4px}
        .seats-scroll-wrapper::-webkit-scrollbar-track{background:rgba(255,255,255,0.05);border-radius:2px}
        .seats-scroll-wrapper::-webkit-scrollbar-thumb{background:rgba(255,77,77,0.4);border-radius:2px}
        .seatsLayoutProper{border-collapse:separate;border-spacing:6px;margin:0 auto;width:max-content}
        .seatRows{font-size:0.8rem;font-weight:600;color:#F9F9F9;padding:0 7px;text-align:center;white-space:nowrap}
        .availableSeatCheckbox{display:block}
        .availableSeatCheckbox input{display:none}
        .availableTheaterSeat{width:48px;height:48px;background-color:#1C1C1C;border:1px solid #F9F9F9;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:600;color:#F9F9F9;cursor:pointer;transition:background 0.2s ease,transform 0.15s ease}
        .availableTheaterSeat:hover{background-color:rgba(249,249,249,0.15);border-color:rgba(249,249,249,0.6)}
        .availableSeatCheckbox input:checked+.availableTheaterSeat{background-color:#ff4d4d;border-color:#ff4d4d;transform:scale(1.1);color:#1C1C1C}
        .unavailableTheaterSeat{width:48px;height:48px;background-color:#1C1C1C;border:1px solid rgba(249,249,249,0.15);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:0.8rem;color:rgba(249,249,249,0.2);cursor:not-allowed}
        .legend{display:flex;gap:20px;justify-content:center;flex-wrap:wrap;margin-top:20px;font-size:0.85rem}
        .legend-item{display:flex;align-items:center;gap:7px}
        .legend-dot{width:16px;height:16px;border-radius:4px;flex-shrink:0}
        .dot-available{background:#1C1C1C;border:1px solid #F9F9F9}
        .dot-selected{background:#ff4d4d}
        .dot-taken{background:#1C1C1C;border:1px solid rgba(249,249,249,0.15)}
        #seatsCalculatorContainer{display:flex;align-items:center;justify-content:space-between;gap:15px;flex-wrap:wrap}
        .summary-stats{display:flex;gap:25px}
        .stat-block{display:flex;flex-direction:column;gap:2px}
        .stat-label{font-size:0.72rem;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:#F9F9F9}
        .stat-value{font-size:1.1rem;font-weight:700;color:#F9F9F9}
        button[type="submit"]{padding:8px 22px;border-radius:8px;border:none;background-color:#ff4d4d;color:#F9F9F9;font-family:'Outfit',sans-serif;font-size:0.9rem;font-weight:600;cursor:pointer;transition:background 0.2s ease;white-space:nowrap}
        button[type="submit"]:hover{background-color:#ff4d4d;transform:scale(1.02)}
        button[type="submit"]:disabled{background-color:rgba(249,249,249,0.12);color:rgba(249,249,249,0.3);cursor:not-allowed;transform:none}
    </style>
</head>
<body>
<header>
    <div class="logo"><img src="peakscinematransparent.png" alt="PeaksCinemas Logo" onclick="window.location.href='home.php'"></div>
    <button class="profile-btn" onclick="window.location.href='<?= $profile_link ?>'" title="Profile">
        <?php if (!empty($profile_photo)): ?>
            <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile" referrerpolicy="no-referrer">
        <?php else: ?>
            👤
        <?php endif; ?>
    </button>
</header>
<main>
    <form id="seatsSelectionSection" action="payment.php" method="POST">
        <input type="hidden" name="movie_id" value="<?= htmlspecialchars($Movie_ID) ?>">
        <input type="hidden" name="mall_id" value="<?= htmlspecialchars($Mall_ID) ?>">
        <input type="hidden" name="date" value="<?= htmlspecialchars($Date) ?>">
        <input type="hidden" name="timeslot_id" value="<?= htmlspecialchars($TimeSlot_ID) ?>">
        <div class="panel">
            <h2>Select Your Seats</h2>
            <div id="screenLabel"><div class="screen-bar">Screen</div><div class="screen-glow"></div></div>
            <div class="seats-scroll-wrapper">
                <table class="seatsLayoutProper">
                    <?php foreach ($layoutProper as $row => $columns): ?>
                    <tr>
                        <td class="seatRows"><?= htmlspecialchars($row) ?></td>
                        <?php foreach ($columns as $seat): if ((int)$seat['SeatColumn'] === 0) continue; ?>
                        <td>
                            <?php if ($seat['SeatAvailability'] == 1): ?>
                            <label class="availableSeatCheckbox"><input type="checkbox" name="selectedSeats[]" value="<?= $seat['Seat_ID'] ?>" data-price="<?= $seat['SeatPrice'] ?>"><div class="availableTheaterSeat"><?= $seat['SeatColumn'] ?></div></label>
                            <?php else: ?><div class="unavailableTheaterSeat"><?= $seat['SeatColumn'] ?></div><?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <td class="seatRows"><?= htmlspecialchars($row) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <div class="legend">
                <div class="legend-item"><div class="legend-dot dot-available"></div><span>Available</span></div>
                <div class="legend-item"><div class="legend-dot dot-selected"></div><span>Selected</span></div>
                <div class="legend-item"><div class="legend-dot dot-taken"></div><span>Taken</span></div>
            </div>
        </div>
        <div class="panel">
            <div id="seatsCalculatorContainer">
                <div class="summary-stats">
                    <div class="stat-block"><span class="stat-label">Seats Selected</span><span class="stat-value" id="seatTotal">0</span></div>
                    <div class="stat-block"><span class="stat-label">Total Price</span><span class="stat-value">₱ <span id="seatPriceTotal">0.00</span></span></div>
                </div>
                <input type="hidden" name="priceTotal" id="priceTotalHidden" value="0">
                <button type="submit" id="confirmBtn" disabled>Complete Booking</button>
            </div>
        </div>
    </form>
</main>
<script>
    const checkboxes=document.querySelectorAll('input[name="selectedSeats[]"]'),seatTotal=document.getElementById('seatTotal'),seatPriceTotal=document.getElementById('seatPriceTotal'),confirmBtn=document.getElementById('confirmBtn');
    function updateSummary(){
        let count=0,total=0;
        checkboxes.forEach(cb=>{if(cb.checked){count++;total+=parseFloat(cb.dataset.price)||0;}});
        seatTotal.textContent=count;seatPriceTotal.textContent=total.toFixed(2);document.getElementById('priceTotalHidden').value=total.toFixed(2);confirmBtn.disabled=count===0;
    }
    checkboxes.forEach(cb=>cb.addEventListener('change',updateSummary));
</script>
</body>
</html>