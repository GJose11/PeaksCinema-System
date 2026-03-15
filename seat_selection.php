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

$Movie_ID    = filter_input(INPUT_GET, 'movie_id',    FILTER_VALIDATE_INT);
$Mall_ID     = filter_input(INPUT_GET, 'mall_id',     FILTER_VALIDATE_INT);
$Date        = filter_input(INPUT_GET, 'date');
$TimeSlot_ID = filter_input(INPUT_GET, 'timeslot_id', FILTER_VALIDATE_INT);

if (!$Movie_ID || !$Mall_ID || !$Date || !$TimeSlot_ID) { header("Location: home.php"); exit; }

$movie_stmt = $conn->prepare("SELECT * FROM movie WHERE Movie_ID = ?");
$movie_stmt->bind_param("i", $Movie_ID); $movie_stmt->execute();
$movieDetails = $movie_stmt->get_result()->fetch_assoc();

$mall_stmt = $conn->prepare("SELECT * FROM mall WHERE Mall_ID = ?");
$mall_stmt->bind_param("i", $Mall_ID); $mall_stmt->execute();
$mallDetails = $mall_stmt->get_result()->fetch_assoc();

$timeslot_stmt = $conn->prepare("SELECT * FROM timeslot INNER JOIN theater ON timeslot.Theater_ID = theater.Theater_ID WHERE TimeSlot_ID = ?");
$timeslot_stmt->bind_param("i", $TimeSlot_ID); $timeslot_stmt->execute();
$timeslotDetails = $timeslot_stmt->get_result()->fetch_assoc();

if (!$movieDetails || !$mallDetails || !$timeslotDetails) { header("Location: home.php"); exit; }

$seats_stmt = $conn->prepare("SELECT * FROM seats WHERE TimeSlot_ID = ? ORDER BY SeatRow ASC, CAST(SeatColumn AS UNSIGNED) ASC");
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
            'SeatColumn'       => $seat['SeatColumn'],
        ];
    }
}
uksort($layoutProper, 'strnatcasecmp');

$basePrice = floatval($movieDetails['Price'] ?? 350);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
<title>Select Seats – <?= htmlspecialchars($movieDetails['MovieName']) ?></title>
<style>
/* ── Reset ── */
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

/* ── Body ── */
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
.logo img {
    height: 48px; cursor: pointer;
    filter: invert(1); transition: transform 0.2s;
}
.logo img:hover { transform: scale(1.05); }
.profile-btn {
    background: #F9F9F9; border: none; border-radius: 50%;
    width: 42px; height: 42px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; overflow: hidden; padding: 0;
    transition: all 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.profile-btn img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.profile-btn:hover { transform: scale(1.1); box-shadow: 0 0 12px rgba(255,255,255,0.3); }
.profile-initials {
    width: 100%; height: 100%; border-radius: 50%;
    background: linear-gradient(135deg, #ff4d4d, #c0392b);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.82rem; font-weight: 800; color: #fff;
}

/* ── Page layout ── */
.outer {
    position: relative; z-index: 10;
    width: 95%; max-width: 1280px;
    margin: 28px auto;
}
.page-label {
    font-size: 0.72rem; font-weight: 700;
    letter-spacing: 2px; text-transform: uppercase;
    color: #ff4d4d; margin-bottom: 5px;
}
.page-title { font-size: 1.7rem; font-weight: 800; margin-bottom: 20px; }

.two-col {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 24px;
    align-items: start;
}
@media (max-width: 900px) {
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
    display: flex; align-items: center; justify-content: space-between;
}
.panel-header h2 {
    font-size: 0.78rem; font-weight: 700;
    letter-spacing: 1.5px; text-transform: uppercase;
    color: rgba(249,249,249,0.45);
}
.panel-body { padding: 20px; }

/* ── Sticky sidebar ── */
.sidebar { position: sticky; top: 80px; }

/* ── Movie info card ── */
.movie-card {
    display: flex; gap: 14px; padding: 16px;
    align-items: flex-start;
}
.movie-thumb {
    width: 52px; height: 78px; flex-shrink: 0;
    border-radius: 7px; background: #111;
    background-size: cover; background-position: center;
    border: 1px solid rgba(255,255,255,0.08);
}
.movie-card h3 { font-size: 0.88rem; font-weight: 800; margin-bottom: 6px; line-height: 1.3; }
.movie-card-meta {
    font-size: 0.7rem; color: rgba(249,249,249,0.4);
    display: flex; flex-direction: column; gap: 3px;
}
.type-pill {
    display: inline-block; margin-top: 5px;
    font-size: 0.62rem; font-weight: 700; letter-spacing: 1px;
    background: rgba(255,77,77,0.12); border: 1px solid rgba(255,77,77,0.25);
    color: #ff6b6b; padding: 2px 8px; border-radius: 10px;
}

/* ── Screen bar ── */
.screen-wrap { text-align: center; margin-bottom: 20px; }
.screen-bar {
    display: inline-block;
    padding: 7px 60px;
    background: linear-gradient(180deg, rgba(255,255,255,0.1), rgba(255,255,255,0.03));
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 2px 2px 28% 28% / 2px 2px 10px 10px;
    font-size: 0.65rem; font-weight: 700; letter-spacing: 4px;
    color: rgba(255,255,255,0.4);
}

/* ── Seat map — CSS table for perfect alignment ── */
.seat-map { display: table; margin: 0 auto; }
.srow     { display: table-row; }

.rlbl {
    display: table-cell;
    width: 18px; min-width: 18px;
    vertical-align: middle; text-align: center;
    font-size: 0.65rem; font-weight: 700;
    color: rgba(249,249,249,0.3);
    padding: 0; white-space: nowrap;
}
.rlbl-r { padding: 0; }

.seat-cell-wrap, .seat-empty-wrap {
    display: table-cell;
    width: 36px; height: 36px;
    vertical-align: middle; text-align: center;
}
.seat-gap     { display: table-cell; width: 5px; }
.aisle-gap    { display: table-cell; width: 22px; }
.section-gap  { display: table-cell; width: 12px; }
.row-gap      { display: table-row; height: 5px; }

/* ── Seat styles — identical to staff_seats ── */
.seat-btn {
    width: 36px; height: 36px;
    border-radius: 7px 7px 3px 3px;
    font-size: 0.62rem; font-weight: 700;
    line-height: 36px; text-align: center;
    cursor: pointer; user-select: none;
    display: block;
    transition: transform 0.1s, filter 0.1s;
    border: none; outline: none;
}
/* Hide checkbox */
.seat-checkbox { display: none; }

.seat-btn.standard { background: #2e2e2e; border-bottom: 3px solid #505050; color: rgba(249,249,249,0.5); }
.seat-btn.vip      { background: #3d1a1a; border-bottom: 3px solid #ff4d4d; color: #ff7070; }
.seat-btn.imax     { background: #332b00; border-bottom: 3px solid #ffc107; color: #ffd54f; }
.seat-btn.taken    { background: #1c1c1c; border-bottom: 3px solid #282828 !important; color: #333; cursor: not-allowed; pointer-events: none; }

.seat-btn:not(.taken):hover {
    transform: scale(1.18);
    filter: brightness(1.45);
    position: relative; z-index: 2;
}
.seat-btn.selected {
    background: rgba(255,77,77,0.28) !important;
    border-bottom: 3px solid #ff4d4d !important;
    color: #fff !important;
    transform: scale(1.12);
    position: relative; z-index: 2;
    box-shadow: 0 0 10px rgba(255,77,77,0.35);
}

/* ── Legend ── */
.legend {
    display: flex; flex-wrap: wrap; gap: 14px;
    justify-content: center;
    margin-top: 22px; padding-top: 16px;
    border-top: 1px solid rgba(255,255,255,0.06);
}
.legend-item { display: flex; align-items: center; gap: 6px; font-size: 0.68rem; color: rgba(249,249,249,0.4); }
.legend-dot  { width: 14px; height: 14px; border-radius: 3px; flex-shrink: 0; }

/* ── Summary sidebar ── */
.divider { height: 1px; background: rgba(255,255,255,0.06); margin: 14px 0; }
.no-seats { font-size: 0.78rem; color: rgba(249,249,249,0.22); }

.seat-tag {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(255,77,77,0.1); border: 1px solid rgba(255,77,77,0.25);
    border-radius: 6px; padding: 4px 10px;
    font-size: 0.78rem; font-weight: 700; color: #ff6b6b;
    margin: 3px;
}

.price-row { display: flex; justify-content: space-between; font-size: 0.82rem; margin-bottom: 8px; }
.price-row .lbl { color: rgba(249,249,249,0.45); }
.price-row .val { font-weight: 700; }
.price-row.total .lbl { font-size: 0.88rem; color: #F9F9F9; font-weight: 700; }
.price-row.total .val { font-size: 1.1rem; color: #ff4d4d; font-weight: 800; }

.btn-proceed {
    width: 100%; padding: 13px;
    background: #2a2a2a; border: 1px solid rgba(255,255,255,0.08);
    border-radius: 9px; color: rgba(249,249,249,0.3);
    font-family: 'Outfit', sans-serif; font-size: 0.88rem; font-weight: 700;
    cursor: not-allowed; pointer-events: none;
    transition: all 0.25s; margin-top: 6px;
}
.btn-proceed.active { background: #ff4d4d; color: #fff; border-color: #ff4d4d; cursor: pointer; pointer-events: all; }
.btn-proceed.active:hover { background: #e03c3c; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,77,77,0.3); }
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

<form id="seatForm" action="payment.php" method="POST">
    <input type="hidden" name="movie_id"    value="<?= htmlspecialchars($Movie_ID) ?>">
    <input type="hidden" name="mall_id"     value="<?= htmlspecialchars($Mall_ID) ?>">
    <input type="hidden" name="date"        value="<?= htmlspecialchars($Date) ?>">
    <input type="hidden" name="timeslot_id" value="<?= htmlspecialchars($TimeSlot_ID) ?>">
    <input type="hidden" name="priceTotal"  id="priceTotalHidden" value="0">

<div class="outer">
    <p class="page-label">Book Tickets</p>
    <h1 class="page-title">Select Your Seats</h1>

    <div class="two-col">

        <!-- ── LEFT: Seat map ── -->
        <div>
            <div class="panel">
                <div class="panel-header"><h2>🪑 Theater Layout</h2></div>
                <div class="panel-body">

                    <?php if (empty($layoutProper)): ?>
                    <div style="text-align:center;padding:40px;color:rgba(249,249,249,0.2);font-size:0.82rem;">
                        No seats found for this screening.
                    </div>
                    <?php else: ?>

                    <div class="screen-wrap">
                        <div class="screen-bar">── SCREEN ──</div>
                    </div>

                    <div style="overflow-x:auto;padding-bottom:8px;">
                    <div class="seat-map">

                        <?php
                        $firstRow = true;
                        if (!defined('CINEMA_HALF')) define('CINEMA_HALF', 10);
                        if (!defined('CINEMA_QTR'))  define('CINEMA_QTR',  5);
                        ?>

                        <?php foreach ($layoutProper as $rowLabel => $cols): ?>
                        <?php
                        $paddedCols = array_values($cols);
                        while (count($paddedCols) < 20) {
                            $last = end($paddedCols);
                            $paddedCols[] = [
                                'Seat_ID'          => 'p_'.$rowLabel.'_'.count($paddedCols),
                                'SeatType'         => $last['SeatType'] ?? 'Standard',
                                'SeatPrice'        => $last['SeatPrice'] ?? $basePrice,
                                'SeatAvailability' => 1,
                                'SeatColumn'       => count($paddedCols) + 1,
                            ];
                        }
                        $paddedCols = array_slice($paddedCols, 0, 20);
                        ?>

                        <?php if (!$firstRow): ?><div class="row-gap"></div><?php endif; $firstRow = false; ?>

                        <div class="srow">
                            <div class="rlbl"><?= htmlspecialchars($rowLabel) ?></div>
                            <div class="seat-gap"></div>

                            <?php foreach ($paddedCols as $seatPos => $seat):
                                $type   = strtolower(trim($seat['SeatType'] ?? 'standard'));
                                $avail  = (int)($seat['SeatAvailability'] ?? 1);
                                $css    = str_contains($type,'vip') ? 'vip' : (str_contains($type,'imax') ? 'imax' : 'standard');
                                $taken  = ($avail == 0);
                                $price  = floatval($seat['SeatPrice'] ?? $basePrice);
                                $dispNum = ($seatPos < CINEMA_HALF)
                                    ? (CINEMA_HALF - $seatPos)
                                    : ($seatPos - CINEMA_HALF + 1);
                                $lbl = $rowLabel . $dispNum;
                            ?>

                            <?php if ($seatPos > 0): ?><div class="seat-gap"></div><?php endif; ?>
                            <?php if ($seatPos === CINEMA_HALF): ?>
                            <div class="aisle-gap"></div>
                            <?php elseif ($seatPos === CINEMA_QTR || $seatPos === CINEMA_HALF + CINEMA_QTR): ?>
                            <div class="section-gap"></div>
                            <?php endif; ?>

                            <div class="seat-cell-wrap">
                                <?php if (!$taken): ?>
                                <label style="display:block;cursor:pointer;">
                                    <input class="seat-checkbox" type="checkbox"
                                           name="selectedSeats[]"
                                           value="<?= $seat['Seat_ID'] ?>"
                                           data-price="<?= $price ?>"
                                           data-label="<?= htmlspecialchars($lbl) ?>"
                                           onchange="onSeatChange(this)">
                                    <div class="seat-btn <?= $css ?>" id="seat-<?= $seat['Seat_ID'] ?>"><?= $dispNum ?></div>
                                </label>
                                <?php else: ?>
                                <div class="seat-btn taken">✕</div>
                                <?php endif; ?>
                            </div>

                            <?php endforeach; ?>

                            <div class="seat-gap"></div>
                            <div class="rlbl rlbl-r"><?= htmlspecialchars($rowLabel) ?></div>
                        </div>

                        <?php endforeach; ?>

                    </div>
                    </div>

                    <!-- Legend -->
                    <div class="legend">
                        <div class="legend-item"><div class="legend-dot" style="background:#2e2e2e;border-bottom:3px solid #505050;"></div>Standard</div>
                        <div class="legend-item"><div class="legend-dot" style="background:#3d1a1a;border-bottom:3px solid #ff4d4d;"></div>VIP</div>
                        <div class="legend-item"><div class="legend-dot" style="background:#332b00;border-bottom:3px solid #ffc107;"></div>IMAX</div>
                        <div class="legend-item"><div class="legend-dot" style="background:rgba(255,77,77,0.28);border-bottom:3px solid #ff4d4d;"></div>Selected</div>
                        <div class="legend-item"><div class="legend-dot" style="background:#1c1c1c;border-bottom:3px solid #282828;"></div>Taken</div>
                    </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── RIGHT: Sticky sidebar ── -->
        <div class="sidebar">

            <!-- Movie info -->
            <div class="panel">
                <div class="panel-header"><h2>🎬 Now Booking</h2></div>
                <div class="movie-card">
                    <div class="movie-thumb" style="background-image:url('/<?= htmlspecialchars($movieDetails['MoviePoster']) ?>');"></div>
                    <div>
                        <h3><?= htmlspecialchars($movieDetails['MovieName']) ?></h3>
                        <div class="movie-card-meta">
                            <span>📍 <?= htmlspecialchars($mallDetails['MallName']) ?></span>
                            <span>🏛 <?= htmlspecialchars($timeslotDetails['TheaterName']) ?></span>
                            <span>📅 <?= date('F d, Y', strtotime($Date)) ?></span>
                            <span>🕐 <?= date('g:i A', strtotime($timeslotDetails['StartTime'])) ?></span>
                        </div>
                        <span class="type-pill"><?= htmlspecialchars($timeslotDetails['ScreeningType']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Booking summary -->
            <div class="panel">
                <div class="panel-header"><h2>🎟 Booking Summary</h2></div>
                <div class="panel-body">
                    <div id="seatTagList" style="margin-bottom:14px;min-height:40px;">
                        <span class="no-seats">No seats selected yet.</span>
                    </div>
                    <div class="divider"></div>
                    <div class="price-row">
                        <span class="lbl">Seats Selected</span>
                        <span class="val" id="seatCount">0</span>
                    </div>
                    <div class="price-row total">
                        <span class="lbl">Total</span>
                        <span class="val">₱ <span id="seatPriceTotal">0.00</span></span>
                    </div>
                    <button type="submit" class="btn-proceed" id="confirmBtn" disabled>
                        Select seats to continue
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>
</form>

<script>
const selectedSeats = {};

function onSeatChange(checkbox) {
    const id    = checkbox.value;
    const price = parseFloat(checkbox.dataset.price) || 0;
    const label = checkbox.dataset.label;
    const btn   = document.getElementById('seat-' + id);

    if (checkbox.checked) {
        selectedSeats[id] = { label, price };
        btn?.classList.add('selected');
    } else {
        delete selectedSeats[id];
        btn?.classList.remove('selected');
    }
    updateSummary();
}

function updateSummary() {
    const keys  = Object.keys(selectedSeats);
    const total = keys.reduce((s, k) => s + selectedSeats[k].price, 0);

    document.getElementById('seatCount').textContent      = keys.length;
    document.getElementById('seatPriceTotal').textContent = total.toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('priceTotalHidden').value     = total.toFixed(2);

    const list = document.getElementById('seatTagList');
    list.innerHTML = keys.length === 0
        ? '<span class="no-seats">No seats selected yet.</span>'
        : keys.map(id =>
            `<span class="seat-tag">
                ${selectedSeats[id].label}
                <span style="font-size:0.62rem;opacity:0.55;">₱${selectedSeats[id].price.toLocaleString()}</span>
            </span>`
          ).join('');

    const btn = document.getElementById('confirmBtn');
    if (keys.length > 0) {
        btn.classList.add('active');
        btn.removeAttribute('disabled');
        btn.textContent = `Proceed to Payment (${keys.length} seat${keys.length > 1 ? 's' : ''}) →`;
    } else {
        btn.classList.remove('active');
        btn.setAttribute('disabled', true);
        btn.textContent = 'Select seats to continue';
    }
}

// ── Hide header on scroll down, show on scroll up ──
(function() {
    const header = document.querySelector('header');
    let lastY = window.scrollY, ticking = false;
    window.addEventListener('scroll', function() {
        if (!ticking) {
            requestAnimationFrame(function() {
                const cur = window.scrollY;
                if (cur > lastY && cur > 80) header.style.transform = 'translateY(-100%)';
                else                          header.style.transform = 'translateY(0)';
                lastY = cur; ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });
})();
</script>
</body>
</html>