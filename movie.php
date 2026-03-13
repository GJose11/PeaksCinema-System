<?php
    include("peakscinemas_database.php");
    session_start();
    $profile_link  = "personal_info_form.php";
    $profile_photo = $_SESSION['profile_photo'] ?? null;

    if (isset($_SESSION['user_id'])) {
        $profile_link = "profile_edit.php";
        $ps = $conn->prepare("SELECT ProfilePhoto FROM customer WHERE Customer_ID = ?");
        $ps->bind_param("i", $_SESSION['user_id']); $ps->execute();
        $pr = $ps->get_result()->fetch_assoc();
        if (!empty($pr['ProfilePhoto'])) {
            $profile_photo = $pr['ProfilePhoto'];
            $_SESSION['profile_photo'] = $profile_photo;
        }
    }

    $Movie_ID = filter_input(INPUT_GET, 'movie_id', FILTER_VALIDATE_INT);
    if (!$Movie_ID) { header("Location: home.php"); exit; }

    $stmt = $conn->prepare("SELECT * FROM movie WHERE Movie_ID = ?");
    $stmt->bind_param("i", $Movie_ID);
    $stmt->execute();
    $movieDetails = ($stmt->get_result())->fetch_assoc();
    if (!$movieDetails) { header("Location: home.php"); exit; }

    // ── Fetch all upcoming screenings grouped by date → mall → screeningType (SM Cinema style) ──
    $sched_stmt = $conn->prepare("
        SELECT t.TimeSlot_ID, t.Date, t.StartTime, t.ScreeningType,
               th.TheaterName, th.Theater_ID,
               m.MallName, m.Mall_ID
        FROM timeslot t
        JOIN theater th ON t.Theater_ID = th.Theater_ID
        JOIN mall m ON th.Mall_ID = m.Mall_ID
        WHERE t.Movie_ID = ? AND t.Date >= CURDATE()
        ORDER BY t.Date ASC, m.MallName ASC, t.ScreeningType ASC, t.StartTime ASC
    ");
    $sched_stmt->bind_param("i", $Movie_ID);
    $sched_stmt->execute();
    $schedResult = $sched_stmt->get_result();

    // [date][mall_id] => { mall_name, mall_id, types: { '2D'=>[slots], 'IMAX'=>[slots] } }
    $scheduleData   = [];
    $availableDates = [];
    while ($row = $schedResult->fetch_assoc()) {
        $d    = $row['Date'];
        $mi   = $row['Mall_ID'];
        $type = $row['ScreeningType'];
        if (!isset($scheduleData[$d])) { $scheduleData[$d] = []; $availableDates[] = $d; }
        if (!isset($scheduleData[$d][$mi])) {
            $scheduleData[$d][$mi] = ['mall_name' => $row['MallName'], 'mall_id' => $mi, 'types' => []];
        }
        if (!isset($scheduleData[$d][$mi]['types'][$type])) {
            $scheduleData[$d][$mi]['types'][$type] = [];
        }
        $scheduleData[$d][$mi]['types'][$type][] = [
            'id'           => $row['TimeSlot_ID'],
            'time'         => $row['StartTime'],
            'theater_name' => $row['TheaterName'],
            'theater_id'   => $row['Theater_ID'],
        ];
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
    <title><?= htmlspecialchars($movieDetails['MovieName']) ?> - PeaksCinemas</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: #111;
            color: #F9F9F9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Header ── */
        header {
            background-color: #1C1C1C;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 30px;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.35s ease;
        }
        header.header-hidden {
            transform: translateY(-100%);
            opacity: 0;
        }
        /* Push body down since header is now fixed */
        body { padding-top: 70px; }

        .logo img {
            height: 50px;
            cursor: pointer;
            transition: transform 0.2s ease;
            filter: invert(1);
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
        }
        .profile-btn:hover { transform: scale(1.1); box-shadow: 0 0 12px rgba(255,255,255,0.3); }

        /* ── Hero Banner ── */
        .hero-banner {
            position: relative;
            width: 100%;
            height: 480px;
            overflow: hidden;
        }

        .hero-backdrop {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center top;
            filter: blur(18px) brightness(0.4);
            transform: scale(1.08);
        }

        .hero-gradient {
            position: absolute;
            inset: 0;
            background: linear-gradient(
                to right,
                rgba(0,0,0,0.92) 0%,
                rgba(0,0,0,0.6) 50%,
                rgba(0,0,0,0.2) 100%
            );
        }

        .hero-gradient::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 120px;
            background: linear-gradient(to bottom, transparent, #111);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            padding: 0 60px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            gap: 30px;
        }

        .hero-info {
            padding-bottom: 5px;
            max-width: 520px;
        }

        .hero-poster-right {
            height: 380px;
            width: auto;
            border-radius: 10px;
            box-shadow: 0 10px 50px rgba(0,0,0,0.9);
            border: 2px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
            object-fit: cover;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: flex-end;
            gap: 30px;
            height: 100%;
            padding: 0 60px 40px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .hero-info {
            padding-bottom: 5px;
        }

        .hero-availability {
            font-size: 0.8rem;
            font-weight: 600;
            color: #aaa;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .hero-title {
            font-size: 2.8rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 12px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.8);
        }

        .hero-badges {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .badge-rating {
            background: #f5a623;
            color: #000;
            font-weight: 700;
            font-size: 0.75rem;
            padding: 3px 10px;
            border-radius: 4px;
        }

        .badge-genre {
            background: rgba(255,255,255,0.1);
            color: #ddd;
            font-size: 0.8rem;
            padding: 3px 10px;
            border-radius: 4px;
            border: 1px solid rgba(255,255,255,0.15);
        }

        .badge-runtime {
            color: #aaa;
            font-size: 0.85rem;
        }

        /* ── Trailer Button on Hero ── */
        .hero-trailer-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.25);
            color: white;
            padding: 9px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            transition: all 0.2s ease;
            backdrop-filter: blur(4px);
        }
        .hero-trailer-btn:hover {
            background: rgba(255,77,77,0.8);
            border-color: #ff4d4d;
        }

        /* ── Centered Play Button Overlay ── */
        .hero-play-overlay {
            position: absolute;
            inset: 0;
            z-index: 3;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        .hero-play-btn {
            pointer-events: all;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 77, 77, 0.85);
            border: 3px solid rgba(255,255,255,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 0 30px rgba(255,77,77,0.5);
            backdrop-filter: blur(4px);
        }

        .hero-play-btn:hover {
            transform: scale(1.12);
            background: rgba(255,77,77,1);
            box-shadow: 0 0 50px rgba(255,77,77,0.8);
        }

        .hero-play-btn svg {
            width: 32px;
            height: 32px;
            fill: white;
            margin-left: 4px;
        }

        .badge-price {
            color: #ff4d4d;
            font-size: 0.95rem;
            font-weight: 700;
        }

        /* ── Main Content ── */
        .main-content-wrapper {
            background: url('movie-background-collage.jpg') center center / cover no-repeat fixed;
            position: relative;
        }

        .main-content-wrapper::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(10, 10, 10, 0.88);
        }

        .main-content {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            padding: 40px 60px;
            display: flex;
            gap: 40px;
        }

        .poster-column {
            width: 200px;
            min-width: 200px;
        }

        .poster-column img {
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.7);
            border: 2px solid rgba(255,255,255,0.08);
        }

        .left-column { flex: 1; }

        /* ── Schedule Section ── */
        .schedule-wrapper {
            background: url('movie-background-collage.jpg') center center / cover no-repeat fixed;
            position: relative;
        }
        .schedule-wrapper::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(8,8,8,0.92);
        }
        .schedule-inner {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 60px 60px;
        }
        .schedule-title {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #ff4d4d;
            margin-bottom: 20px;
        }

        /* ── Date tabs ── */
        .date-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 32px;
        }
        .date-tab {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px 20px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.04);
            color: rgba(249,249,249,0.55);
            font-family: 'Outfit', sans-serif;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 90px;
            text-align: center;
        }
        .date-tab .tab-day   { display: block; font-size: 0.68rem; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; }
        .date-tab .tab-date  { display: block; font-size: 1.1rem; font-weight: 800; line-height: 1.2; }
        .date-tab .tab-month { display: block; font-size: 0.7rem; font-weight: 500; opacity: 0.7; }
        .date-tab:hover { border-color: rgba(255,77,77,0.4); color: #F9F9F9; }
        .date-tab.active { background: #ff4d4d; border-color: #ff4d4d; color: #fff; }

        /* Date panels */
        .date-panel { display: none; }
        .date-panel.active { display: block; }

        /* Mall block */
        .mall-block { margin-bottom: 40px; padding-bottom: 32px; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .mall-block:last-child { border-bottom: none; margin-bottom: 0; }

        .mall-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .mall-icon {
            width: 32px; height: 32px;
            background: rgba(255,77,77,0.12);
            border: 1px solid rgba(255,77,77,0.25);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        .mall-name {
            font-size: 1rem;
            font-weight: 700;
            color: #F9F9F9;
        }

        /* Screening type group */
        .type-group {
            margin-bottom: 20px;
            padding: 16px 18px;
            background: rgba(255,255,255,0.035);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
        }
        .type-group:last-child { margin-bottom: 0; }

        .type-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .type-label-text {
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.4);
        }

        .type-badge {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 5px;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        .type-badge-2d     { background: rgba(255,255,255,0.08); color: #F9F9F9; }
        .type-badge-3d     { background: rgba(100,180,255,0.12); color: #7ec8ff; border: 1px solid rgba(100,180,255,0.25); }
        .type-badge-imax   { background: rgba(255,200,50,0.1);   color: #ffc83d; border: 1px solid rgba(255,200,50,0.25); }
        .type-badge-4dx    { background: rgba(180,100,255,0.1);  color: #c87eff; border: 1px solid rgba(180,100,255,0.25); }
        .type-badge-screenx{ background: rgba(50,220,150,0.1);   color: #3de0a0; border: 1px solid rgba(50,220,150,0.25); }
        .type-badge-other  { background: rgba(255,255,255,0.06); color: rgba(249,249,249,0.6); }

        /* Time slot buttons */
        .time-slots { display: flex; gap: 10px; flex-wrap: wrap; }

        .time-btn {
            display: block;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.06);
            color: #F9F9F9;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            min-width: 115px;
        }
        .time-btn:hover {
            border-color: #ff4d4d;
            background: rgba(255,77,77,0.12);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(255,77,77,0.2);
            color: #F9F9F9;
        }
        .t-time {
            display: block;
            font-size: 1.05rem;
            font-weight: 800;
            color: #F9F9F9;
            line-height: 1.3;
            letter-spacing: -0.3px;
        }
        .t-ampm {
            font-size: 0.65rem;
            font-weight: 600;
            color: rgba(249,249,249,0.55);
            margin-left: 2px;
            vertical-align: middle;
        }
        .t-theater {
            display: block;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.4);
            margin-top: 4px;
        }
        .time-btn:hover .t-theater { color: rgba(255,100,100,0.8); }

        /* No screenings */
        .no-screenings {
            text-align: center;
            padding: 50px 20px;
            color: rgba(249,249,249,0.25);
            font-size: 0.9rem;
        }

        /* Synopsis */
        .section-label {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #ff4d4d;
            margin-bottom: 12px;
        }

        .synopsis-text {
            font-size: 0.95rem;
            line-height: 1.8;
            color: #ccc;
            margin-bottom: 35px;
        }

        /* Movie Details Grid */
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 35px;
        }

        .detail-item label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #888;
            margin-bottom: 4px;
        }

        .detail-item span {
            font-size: 0.95rem;
            color: #F9F9F9;
            font-weight: 500;
        }

        /* ── Booking Box ── */
        .booking-box {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 24px;
            position: sticky;
            top: 90px;
        }

        .booking-box h3 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: #F9F9F9;
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .booking-step {
            margin-bottom: 16px;
        }

        .booking-step label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #aaa;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 7px;
        }

        .booking-step select,
        .booking-step input[type="date"] {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 0.9rem;
            border: 1px solid rgba(255,255,255,0.1);
            background-color: rgba(255,255,255,0.07);
            color: #F9F9F9;
            outline: none;
            font-family: 'Outfit', sans-serif;
            transition: border-color 0.2s ease;
            cursor: pointer;
        }

        .booking-step select:focus,
        .booking-step input[type="date"]:focus {
            border-color: #ff4d4d;
        }

        .booking-step select option {
            background: #1C1C1C;
            color: #F9F9F9;
        }

        #mallAdvice {
            color: #ff6b6b;
            font-size: 0.8rem;
            margin-top: 6px;
            font-weight: 600;
            display: block;
        }

        #nextButton {
            width: 100%;
            margin-top: 20px;
            padding: 12px;
            font-size: 1rem;
            font-weight: 700;
            border-radius: 8px;
            border: none;
            background-color: #ff4d4d;
            color: #F9F9F9;
            cursor: pointer;
            visibility: hidden;
            font-family: 'Outfit', sans-serif;
            transition: background-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }

        #nextButton:hover {
            background-color: #ff3333;
            transform: translateY(-2px);
            box-shadow: 0 0 18px rgba(255,75,75,0.5);
        }

        /* ── Trailer Modal ── */
        .trailer-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.9);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .trailer-modal.active { display: flex; }
        .trailer-modal-content {
            position: relative;
            width: 80%;
            max-width: 900px;
            aspect-ratio: 16/9;
            background: #000;
            border-radius: 10px;
            overflow: hidden;
        }
        .close-modal {
            position: absolute;
            top: -38px; right: 0;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            background: none;
            border: none;
            font-family: 'Outfit', sans-serif;
        }

        @media (max-width: 900px) {
            .hero-content { padding: 0 20px 30px; }
            .hero-title { font-size: 1.8rem; }
            .main-content { flex-direction: column; padding: 25px 20px; gap: 25px; }
            .poster-column { width: 140px; min-width: unset; margin: 0 auto; }
            .right-column { width: 100%; min-width: unset; }
            .booking-box { position: static; }
            .details-grid { grid-template-columns: 1fr; }
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
        <?php else: ?>
            👤
        <?php endif; ?>
    </button>
</header>

<!-- Hero Banner -->
<div class="hero-banner">
    <div class="hero-backdrop" style="background-image: url('/<?= str_replace(' ', '%20', htmlspecialchars($movieDetails['MoviePoster'])) ?>');"></div>
    <div class="hero-gradient"></div>

    <?php if (!empty($movieDetails['TrailerURL'])): ?>
    <div class="hero-play-overlay">
        <button class="hero-play-btn" onclick="playTrailer('<?= htmlspecialchars($movieDetails['TrailerURL']) ?>')" title="Watch Trailer">
            <svg viewBox="0 0 24 24"><polygon points="5,3 19,12 5,21"/></svg>
        </button>
    </div>
    <?php endif; ?>

    <div class="hero-content">
        <div class="hero-info">
            <p class="hero-availability">
                <?= htmlspecialchars($movieDetails['MovieAvailability']) ?>
            </p>
            <h1 class="hero-title"><?= htmlspecialchars($movieDetails['MovieName']) ?></h1>
            <div class="hero-badges">
                <?php if (!empty($movieDetails['Rating'])): ?>
                    <span class="badge-rating"><?= htmlspecialchars($movieDetails['Rating']) ?></span>
                <?php endif; ?>
                <?php if (!empty($movieDetails['Genre'])): ?>
                    <span class="badge-genre"><?= htmlspecialchars($movieDetails['Genre']) ?></span>
                <?php endif; ?>
                <?php if (!empty($movieDetails['Runtime'])): ?>
                    <span class="badge-runtime">⏱ <?= htmlspecialchars($movieDetails['Runtime']) ?> mins</span>
                <?php endif; ?>
                <?php if (!empty($movieDetails['Price']) && $movieDetails['Price'] > 0): ?>
                    <span class="badge-price">From ₱<?= number_format($movieDetails['Price'], 2) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($movieDetails['TrailerURL'])): ?>
            <button class="hero-trailer-btn" onclick="scrollToShowtimes()">
                🎭 &nbsp;View Showtimes
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="main-content-wrapper">
<div class="main-content">
    <div class="poster-column">
        <img src="/<?= htmlspecialchars($movieDetails['MoviePoster']) ?>"
             alt="<?= htmlspecialchars($movieDetails['MovieName']) ?>">
    </div>
    <div class="left-column">
        <p class="section-label">Synopsis</p>
        <p class="synopsis-text"><?= htmlspecialchars($movieDetails['MovieDescription']) ?></p>

        <p class="section-label">Details</p>
        <div class="details-grid">
            <div class="detail-item">
                <label>Genre</label>
                <span><?= htmlspecialchars($movieDetails['Genre']) ?></span>
            </div>
            <div class="detail-item">
                <label>Rating</label>
                <span><?= htmlspecialchars($movieDetails['Rating']) ?></span>
            </div>
            <div class="detail-item">
                <label>Runtime</label>
                <span><?= htmlspecialchars($movieDetails['Runtime']) ?> minutes</span>
            </div>
            <div class="detail-item">
                <label>Availability</label>
                <span><?= htmlspecialchars($movieDetails['MovieAvailability']) ?></span>
            </div>
        </div>
    </div>
</div>
</div><!-- end main-content-wrapper -->

<!-- ── Schedule Section ── -->
<div class="schedule-wrapper" id="showtimes-section">
<div class="schedule-inner">
    <p class="schedule-title">🎬 Choose Your Showtime</p>

    <?php if (empty($availableDates)): ?>
        <div class="no-screenings">No screenings available for this movie yet. Check back soon!</div>
    <?php else: ?>

        <!-- Date tabs -->
        <div class="date-tabs">
            <?php foreach ($availableDates as $i => $d): ?>
                <?php
                    $ts       = strtotime($d);
                    $today    = strtotime('today');
                    $tomorrow = strtotime('tomorrow');
                    if ($ts === $today)        $dayLabel = 'TODAY';
                    elseif ($ts === $tomorrow) $dayLabel = 'TOMORROW';
                    else                       $dayLabel = strtoupper(date('D', $ts));
                ?>
                <button class="date-tab <?= $i === 0 ? 'active' : '' ?>"
                        onclick="switchDate('<?= $d ?>')"
                        id="tab-<?= $d ?>">
                    <span class="tab-day"><?= $dayLabel ?></span>
                    <span class="tab-date"><?= date('d', $ts) ?></span>
                    <span class="tab-month"><?= date('M', $ts) ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Date panels -->
        <?php foreach ($availableDates as $i => $d): ?>
        <div class="date-panel <?= $i === 0 ? 'active' : '' ?>" id="panel-<?= $d ?>">

            <?php foreach ($scheduleData[$d] as $mall_id => $mall): ?>
            <div class="mall-block">

                <!-- Mall header -->
                <div class="mall-header">
                    <div class="mall-icon">📍</div>
                    <span class="mall-name"><?= htmlspecialchars($mall['mall_name']) ?></span>
                </div>

                <!-- Grouped by screening type -->
                <?php foreach ($mall['types'] as $type => $slots): ?>
                <?php
                    $typeKey = strtolower(str_replace(' ', '', $type));
                    if      ($typeKey === '2d')      $badgeClass = 'type-badge-2d';
                    elseif  ($typeKey === '3d')      $badgeClass = 'type-badge-3d';
                    elseif  ($typeKey === 'imax')    $badgeClass = 'type-badge-imax';
                    elseif  ($typeKey === '4dx')     $badgeClass = 'type-badge-4dx';
                    elseif  ($typeKey === 'screenx') $badgeClass = 'type-badge-screenx';
                    else                             $badgeClass = 'type-badge-other';
                ?>
                <div class="type-group">
                    <div class="type-header">
                        <span class="type-label-text">All showtimes</span>
                        <span class="type-badge <?= $badgeClass ?>"><?= htmlspecialchars($type) ?></span>
                    </div>
                    <div class="time-slots">
                        <?php foreach ($slots as $slot):
                            $timeFormatted = date('g:i', strtotime($slot['time']));
                            $ampm          = date('A',   strtotime($slot['time']));
                        ?>
                        <a class="time-btn"
                           href="seat_selection.php?movie_id=<?= $Movie_ID ?>&mall_id=<?= $mall_id ?>&date=<?= urlencode($d) ?>&timeslot_id=<?= $slot['id'] ?>">
                            <span class="t-time"><?= $timeFormatted ?><span class="t-ampm"><?= $ampm ?></span></span>
                            <span class="t-theater"><?= htmlspecialchars($slot['theater_name']) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>
            <?php endforeach; ?>

        </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>
</div><!-- end schedule-wrapper -->

<!-- Trailer Modal -->
<div id="trailerModal" class="trailer-modal">
    <div class="trailer-modal-content">
        <button class="close-modal" onclick="closeTrailer()">✕ Close</button>
        <iframe id="trailerFrame" width="100%" height="100%" frameborder="0"
            allow="autoplay; encrypted-media; fullscreen; picture-in-picture" allowfullscreen></iframe>
    </div>
</div>

<script>
    function switchDate(date) {
        // Hide all panels and deactivate all tabs
        document.querySelectorAll('.date-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.date-tab').forEach(t => t.classList.remove('active'));
        // Show selected
        const panel = document.getElementById('panel-' + date);
        const tab   = document.getElementById('tab-'   + date);
        if (panel) panel.classList.add('active');
        if (tab)   tab.classList.add('active');
    }

    function playTrailer(url) {
        let videoId = '';
        if (url.includes('watch?v=')) videoId = url.split('watch?v=')[1].split('&')[0];
        else if (url.includes('youtu.be/')) videoId = url.split('youtu.be/')[1].split('?')[0];
        if (videoId) {
            document.getElementById('trailerFrame').src = `https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0`;
            document.getElementById('trailerModal').classList.add('active');
        }
    }

    function closeTrailer() {
        document.getElementById('trailerModal').classList.remove('active');
        document.getElementById('trailerFrame').src = "";
    }

    document.getElementById('trailerModal').addEventListener('click', function(e) {
        if (e.target === this) closeTrailer();
    });

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeTrailer(); });

    // ── Scroll to showtimes ──
    function scrollToShowtimes() {
        const el = document.getElementById('showtimes-section');
        if (el) {
            const headerH = document.querySelector('header')?.offsetHeight || 70;
            const top = el.getBoundingClientRect().top + window.scrollY - headerH;
            window.scrollTo({ top, behavior: 'smooth' });
        }
    }

    // ── Hide header on scroll down, show on scroll up ──
    (function() {
        const header = document.querySelector('header');
        let lastY    = window.scrollY;
        let ticking  = false;

        window.addEventListener('scroll', function() {
            if (!ticking) {
                requestAnimationFrame(function() {
                    const currentY = window.scrollY;
                    if (currentY > lastY && currentY > 80) {
                        // Scrolling DOWN — hide
                        header.classList.add('header-hidden');
                    } else {
                        // Scrolling UP — show
                        header.classList.remove('header-hidden');
                    }
                    lastY = currentY;
                    ticking = false;
                });
                ticking = true;
            }
        }, { passive: true });
    })();
</script>
</body>
</html>