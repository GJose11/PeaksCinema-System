<?php
    include("peakscinemas_database.php");
    session_start();
    
    $profile_link = "personal_info_form.php";
    $profile_photo = $_SESSION['profile_photo'] ?? null;

    if (isset($_SESSION['user_id'])) {
        $profile_link = "profile_edit.php";
        
        // Fetch profile photo from DB if logged in
        $stmt = $conn->prepare("SELECT ProfilePhoto FROM customer WHERE Customer_ID = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $user_result = $stmt->get_result();
        if ($user_result->num_rows > 0) {
            $user = $user_result->fetch_assoc();
            if (!empty($user['ProfilePhoto'])) {
                $profile_photo = $user['ProfilePhoto'];
                $_SESSION['profile_photo'] = $profile_photo;
            }
        }
    }

    $Movie_ID = filter_input(INPUT_GET, 'movie_id', FILTER_VALIDATE_INT);
    if (!$Movie_ID) { header("Location: home.php"); exit; }

    $stmt = $conn->prepare("SELECT * FROM movie WHERE Movie_ID = ?");
    $stmt->bind_param("i", $Movie_ID);
    $stmt->execute();
    $movieDetails = ($stmt->get_result())->fetch_assoc();
    if (!$movieDetails) { header("Location: home.php"); exit; }
?>
<!DOCTYPE html>
<html>
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
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
        }

        .logo img {
            height: 50px;
            cursor: pointer;
            transition: transform 0.2s ease;
            filter: invert(1);
        }
        .logo img:hover { transform: scale(1.05); }

        .profile-btn {
            background-color: transparent;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 50%;
            width: 45px; height: 45px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            overflow: hidden; /* Added to clip image into circle */
            padding: 0; /* Removed padding so image spans full width */
        }
        .profile-btn:hover { transform: scale(1.1); box-shadow: 0 0 12px rgba(255,255,255,0.3); }

        /* New rule specifically for the profile image */
        .profile-btn img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        /* ── Hero Banner ── */
        .hero-banner {
            position: relative;
            width: 100%;
            height: 420px;
            overflow: hidden;
        }

        .hero-backdrop {
            position: absolute;
            inset: 0;
            background-image: url('/<?= htmlspecialchars($movieDetails['MoviePoster']) ?>');
            background-size: cover;
            background-position: center top;
            filter: blur(18px) brightness(0.35);
            transform: scale(1.08);
        }

        .hero-gradient {
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.2) 0%, rgba(17,17,17,1) 100%);
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

        .hero-poster {
            width: 160px;
            min-width: 160px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(255,255,255,0.2);
            
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
            background-color: #ff3333;
            transform: scale(1.03);
            box-shadow: 0 0 18px rgba(255, 75, 75, 0.6);
        }

        /* ── Main Content ── */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            padding: 40px 60px;
            display: flex;
            gap: 50px;
        }

        .left-column { flex: 1; }
        .right-column { width: 320px; min-width: 320px; }

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
            .hero-poster { width: 110px; min-width: 110px; }
            .main-content { flex-direction: column; padding: 25px 20px; gap: 25px; }
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

<div class="hero-banner">
    <div class="hero-backdrop"></div>
    <div class="hero-gradient"></div>
    <div class="hero-content">
        <img class="hero-poster"
             src="/<?= htmlspecialchars($movieDetails['MoviePoster']) ?>"
             alt="<?= htmlspecialchars($movieDetails['MovieName']) ?>">
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
            </div>
            <?php if (!empty($movieDetails['TrailerURL'])): ?>
            <button class="hero-trailer-btn" onclick="playTrailer('<?= htmlspecialchars($movieDetails['TrailerURL']) ?>')">
                ▶ &nbsp;Watch Trailer
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="main-content">
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

    <div class="right-column">
        <div class="booking-box">
            <h3>🎟 Book Your Tickets</h3>

            <div class="booking-step">
                <label>1. Select a Date</label>
                <input type="date" id="dateSelection" onchange="getDate(this.value)">
                <span id="mallAdvice"></span>
            </div>

            <div class="booking-step" id="step-mall" style="display:none;">
                <label>2. Select a Mall</label>
                <select id="mallSelection" onchange="getMall(this.value)"></select>
            </div>

            <div class="booking-step" id="step-theater" style="display:none;">
                <label>3. Select Theater Type</label>
                <select id="theaterSelection" onchange="getTheaterType(this.value)"></select>
            </div>

            <div class="booking-step" id="step-time" style="display:none;">
                <label>4. Select a Time</label>
                <select id="timeslotSelection" onchange="getTimeslot(this.value)"></select>
            </div>

            <button id="nextButton">Get Tickets →</button>
        </div>
    </div>
</div>

<div id="trailerModal" class="trailer-modal">
    <div class="trailer-modal-content">
        <button class="close-modal" onclick="closeTrailer()">✕ Close</button>
        <iframe id="trailerFrame" width="100%" height="100%" frameborder="0"
            allow="autoplay; encrypted-media; fullscreen; picture-in-picture" allowfullscreen></iframe>
    </div>
</div>

<script>
    const dateSelection     = document.getElementById("dateSelection");
    const mallSelection     = document.getElementById("mallSelection");
    const theaterSelection  = document.getElementById("theaterSelection");
    const timeslotSelection = document.getElementById("timeslotSelection");
    const mallAdvice        = document.getElementById("mallAdvice");
    const nextButton        = document.getElementById("nextButton");

    // Set min date to today
    dateSelection.setAttribute('min', new Date().toISOString().split('T')[0]);

    function getDate(date) {
        mallAdvice.innerHTML = "";
        document.getElementById('step-mall').style.display = "none";
        document.getElementById('step-theater').style.display = "none";
        document.getElementById('step-time').style.display = "none";
        nextButton.style.visibility = "hidden";

        if (date) {
            const xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    if (this.responseText.includes("<option")) {
                        mallSelection.innerHTML = this.responseText;
                        mallAdvice.innerHTML = "";
                        document.getElementById('step-mall').style.display = "block";
                    } else {
                        mallAdvice.innerHTML = this.responseText;
                    }
                }
            };
            xhr.open("GET", "queries.php?q=mall&dateSelection=" + date + "&movie_id=<?= json_encode($Movie_ID) ?>", true);
            xhr.send();
        }
    }

    function getMall(mall_id) {
        document.getElementById('step-theater').style.display = "none";
        document.getElementById('step-time').style.display = "none";
        nextButton.style.visibility = "hidden";

        if (mall_id) {
            const xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    theaterSelection.innerHTML = this.responseText;
                    document.getElementById('step-theater').style.display = "block";
                }
            };
            xhr.open("GET", "queries.php?q=theater&dateSelection=" + dateSelection.value + "&mall_id=" + mall_id + "&movie_id=<?= json_encode($Movie_ID) ?>", true);
            xhr.send();
        }
    }

    function getTheaterType(type) {
        document.getElementById('step-time').style.display = "none";
        nextButton.style.visibility = "hidden";

        if (type) {
            const xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    timeslotSelection.innerHTML = this.responseText;
                    document.getElementById('step-time').style.display = "block";
                }
            };
            xhr.open("GET", "queries.php?q=timeslot&dateSelection=" + dateSelection.value + "&mall_id=" + mallSelection.value + "&movie_id=<?= json_encode($Movie_ID) ?>&type=" + encodeURIComponent(type), true);
            xhr.send();
        }
    }

    function getTimeslot(timeslot_id) {
        nextButton.style.visibility = timeslot_id ? "visible" : "hidden";
    }

    nextButton.addEventListener("click", function() {
        window.location.href = "seat_selection.php?movie_id=<?= json_encode($Movie_ID) ?>&mall_id=" + mallSelection.value + "&date=" + dateSelection.value + "&timeslot_id=" + timeslotSelection.value;
    });

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
</script>
</body>
</html>