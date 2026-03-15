<?php
include_once "peakscinemas_database.php";
session_start();

$profile_link = "personal_info_form.php";
$profile_photo = $_SESSION['profile_photo'] ?? null;

$user_initials = '';
if (isset($_SESSION['user_id'])) {
    $profile_link = "profile_edit.php";

    $stmt = $conn->prepare("SELECT Name, PhoneNumber, Email, ProfilePhoto FROM customer WHERE Customer_ID = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user_result = $stmt->get_result();
    if ($user_result->num_rows > 0) {
        $user = $user_result->fetch_assoc();
        if (!empty($user['ProfilePhoto'])) {
            $profile_photo = $user['ProfilePhoto'];
            $_SESSION['profile_photo'] = $profile_photo;
        }
        // Generate initials from Name
        $nameParts = explode(' ', trim($user['Name'] ?? ''));
        $user_initials = strtoupper(
            substr($nameParts[0] ?? '', 0, 1) .
            substr(end($nameParts) ?? '', 0, 1)
        );
        if (strlen($user_initials) === 1) $user_initials = strtoupper(substr($nameParts[0] ?? '', 0, 2));
    }
}

function getAvailableMovies($conn, $availability) {
    $stmt = $conn->prepare("SELECT Movie_ID, MovieName, MoviePoster, Genre, Rating, Runtime, Price, TrailerURL FROM movie WHERE MovieAvailability = ?");
    $stmt->bind_param("s", $availability);
    $stmt->execute();
    return $stmt->get_result();
}

$now_showing_results = getAvailableMovies($conn, 'Now Showing');
$coming_soon_results = getAvailableMovies($conn, 'Coming Soon');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
  <title>PeaksCinemas - Home</title>
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Outfit', sans-serif;
      background: #0f0f0f;
      color: #F9F9F9;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }
    body::before {
      content: '';
      position: fixed; inset: 0;
      background: url("movie-background-collage.jpg") center/cover no-repeat;
      opacity: 0.12;
      z-index: 0;
      pointer-events: none;
    }
    body::after {
      content: '';
      position: fixed; inset: 0;
      background: radial-gradient(ellipse at center, transparent 10%, rgba(15,15,15,0.55) 60%, #0f0f0f 100%);
      z-index: 1;
      pointer-events: none;
    }

    header {
      background: #1C1C1C;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 30px;
      position: fixed;
      top: 0; left: 0; width: 100%;
      height: 60px;
      z-index: 1000;
      border-bottom: 1px solid rgba(255,255,255,0.06);
      transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
    }
    header.header-hidden { transform: translateY(-100%); }
    body { padding-top: 70px; }
    .outer { position: relative; z-index: 10; }

    .logo img {
      height: 50px;
      cursor: pointer;
      transition: transform 0.2s ease;
      filter: invert(1);
    }

    .logo img:hover { transform: scale(1.05); }

    .search-container {
      position: relative;
      margin: 0 20px;
      flex: 1;
      max-width: 400px;
    }

    .search-container input {
      width: 100%;
      padding: 8px 18px;
      border-radius: 8px;
      border: 1px solid rgba(255,255,255,0.1);
      background: rgba(255,255,255,0.06);
      color: #F9F9F9;
      font-family: 'Outfit', sans-serif;
      font-size: 0.88rem;
      outline: none;
      transition: border-color 0.2s;
    }
    .search-container input:focus {
      border-color: rgba(255,77,77,0.4);
      background: rgba(255,255,255,0.09);
    }
    .search-container input::placeholder { color: rgba(249,249,249,0.3); }

    .header-actions {
      display: flex;
      align-items: center;
      gap: 10px;
    }

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

    .profile-btn img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 50%;
    }

    .profile-initials {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      background: linear-gradient(135deg, #ff4d4d, #c0392b);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.82rem;
      font-weight: 800;
      color: #fff;
      letter-spacing: 0.5px;
      font-family: 'Outfit', sans-serif;
    }

    .profile-btn:hover {
      transform: scale(1.1);
      box-shadow: 0 0 12px rgba(255,255,255,0.3);
    }

    .outer {
      position: relative; z-index: 10;
      margin: 28px auto;
      width: 95%;
      max-width: 1280px;
    }
    .page-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #ff4d4d; margin-bottom: 5px; text-align: center; }
    .page-title { font-size: 1.7rem; font-weight: 800; margin-bottom: 6px; text-align: center; }
    .page-sub   { font-size: 0.88rem; color: rgba(249,249,249,0.4); text-align: center; margin-bottom: 28px; }

    .tabs {
      display: flex;
      justify-content: center;
      margin-bottom: 28px;
      gap: 8px;
      flex-wrap: wrap;
    }

    .tab {
      background: rgba(255,255,255,0.04);
      color: rgba(249,249,249,0.55);
      padding: 8px 24px;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      font-size: 0.82rem;
      border: 1px solid rgba(255,255,255,0.1);
      transition: all 0.2s;
      white-space: nowrap;
      font-family: 'Outfit', sans-serif;
    }

    .tab.active {
      background: rgba(255,77,77,0.12);
      border-color: rgba(255,77,77,0.35);
      color: #ff4d4d;
    }

    .tab:hover:not(.active) {
      background: rgba(255,255,255,0.08);
      border-color: rgba(255,255,255,0.2);
      color: #F9F9F9;
    }

    .movies-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(185px, 1fr));
      gap: 16px;
    }

    .movie-card {
      background: #1a1a1a;
      border: 1px solid rgba(255,255,255,0.07);
      border-radius: 14px;
      padding: 10px;
      text-align: center;
      transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
      display: flex;
      flex-direction: column;
      cursor: pointer;
    }

    .movie-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 30px rgba(0,0,0,0.5);
      /* Red border hue removed here */
    }

    /* ── Poster Wrapper & Hover Overlay ── */
    .movie-poster-wrapper {
      position: relative;
      overflow: hidden;
      border-radius: 6px;
      cursor: pointer;
      width: 100%;
      padding-top: 148%;
    }

    .movie-poster-wrapper img {
      position: absolute;
      top: 0; left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      border-radius: 6px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.5);
      transition: transform 0.3s ease, filter 0.3s ease;
    }

    .movie-hover-overlay {
      position: absolute;
      bottom: 0; left: 0;
      width: 100%; height: 100%;
      background: linear-gradient(
        to top,
        rgba(0,0,0,0.95) 50%,
        rgba(0,0,0,0.3) 100%
      );
      color: white;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      padding: 15px;
      box-sizing: border-box;
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .movie-poster-wrapper:hover .movie-hover-overlay {
      opacity: 1;
    }

    .movie-poster-wrapper:hover img {
      transform: scale(1.05);
      filter: brightness(0.6);
    }

    .overlay-rating-badge {
      background: #f5a623;
      color: black;
      font-weight: bold;
      font-size: 10px;
      padding: 2px 7px;
      border-radius: 4px;
      width: fit-content;
      margin-bottom: 5px;
    }

    .overlay-genre,
    .overlay-runtime {
      margin: 2px 0;
      font-size: 11px;
      color: #ccc;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .overlay-price {
      font-size: 13px;
      font-weight: bold;
      color: #ff4444;
      margin: 4px 0;
    }

    .trailer-btn {
      margin-top: 6px;
      margin-left: auto;
      margin-right: auto;
      background: #ff4d4d;
      border: 2px solid #ff4d4d;
      color: white;
      padding: 5px 12px;
      border-radius: 20px;
      cursor: pointer;
      font-size: 11px;
      font-family: 'Outfit', sans-serif;
      transition: background 0.2s ease, border-color 0.2s ease;
      width: fit-content;
      display: block;
    }

    .trailer-btn:hover {
      background: #cc0000;
      border-color: #cc0000;
    }

    .trailer-modal {
      display: none;
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.85);
      z-index: 9999;
      justify-content: center;
      align-items: center;
    }

    .trailer-modal.active {
      display: flex;
    }

    .trailer-modal-content {
      position: relative;
      width: 80%;
      height: 500px;
      background: #000;
      border-radius: 10px;
      overflow: hidden;
    }

    .close-modal {
      position: absolute;
      top: 10px; right: 15px;
      color: white;
      font-size: 22px;
      cursor: pointer;
      z-index: 10;
      background: rgba(0,0,0,0.6);
      padding: 2px 9px;
      border-radius: 50%;
    }

    .movie-title {
      margin: 10px 0 8px;
      font-weight: 700;
      font-size: 1rem;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      line-height: 1.3;
      min-height: 2.6em;
      text-align: center;
      color: #F9F9F9;
      letter-spacing: 0.3px;
      text-shadow: 0 1px 4px rgba(0,0,0,0.5);
      flex: 1;
    }

    .buy-btn {
      background: rgba(255,77,77,0.12);
      color: #ff6b6b;
      border: 1px solid rgba(255,77,77,0.25);
      padding: 8px 12px;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s;
      font-family: 'Outfit', sans-serif;
      font-weight: 700;
      font-size: 0.82rem;
      width: 100%;
      margin-top: auto;
    }

    .buy-btn:hover {
      background: #ff4d4d;
      color: #fff;
      border-color: #ff4d4d;
      transform: translateY(-1px);
      box-shadow: 0 4px 14px rgba(255,77,77,0.3);
    }

    .tab-content { display: none; }
    .tab-content.active { display: block; }

    footer {
      background: transparent;
      border-top: 1px solid rgba(255,255,255,0.06);
      color: rgba(249,249,249,0.3);
      text-align: center;
      padding: 28px 20px;
      margin-top: auto;
      position: relative;
      z-index: 10;
      font-size: 0.78rem;
    }

    @media (max-width: 768px) {
      .movies-container { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
      .movie-poster-wrapper img { height: 160px; }
      header { flex-wrap: wrap; gap: 10px; }
      .search-container { order: 3; width: 100%; max-width: 100%; margin: 10px 0 0 0; }
      .header-actions { order: 2; }
      .trailer-modal-content { width: 95%; height: 250px; }
    }
  </style>
</head>
<body>

  <header>
    <div class="logo">
      <img src="peakscinematransparent.png" alt="PeaksCinemas Logo"
           onclick="window.location.href='home.php'">
    </div>
    <div class="search-container">
      <form id="searchForm" action="javascript:void(0);" method="get">
        <input type="text" id="searchInput" name="search" placeholder="Search Movies..." autocomplete="off">
      </form>
      <div id="searchResults" style="position:absolute;top:42px;width:100%;background:#1a1a1a;border:1px solid rgba(255,255,255,0.08);border-radius:10px;max-height:300px;overflow-y:auto;display:none;z-index:1000;box-shadow:0 8px 24px rgba(0,0,0,0.5);"></div>
    </div>
    <div class="header-actions">
      <button class="profile-btn" onclick="window.location.href='<?= $profile_link ?>'" title="Profile">
        <?php if (!empty($profile_photo)): ?>
          <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile" referrerpolicy="no-referrer">
        <?php elseif (!empty($user_initials)): ?>
          <div class="profile-initials"><?= htmlspecialchars($user_initials) ?></div>
        <?php else: ?>
          <div class="profile-initials">?</div>
        <?php endif; ?>
      </button>
    </div>
  </header>

  <div class="outer">
    <main id="home">
      <p class="page-label">Peak's Cinema</p>
      <h1 class="page-title"><?php if (!empty($user_initials) && isset($user['Name'])): ?>Welcome back, <?= htmlspecialchars(explode(' ', trim($user['Name']))[0]) ?>! 👋<?php else: ?>Welcome to Peak's Cinema 🎬<?php endif; ?></h1>
      <p class="page-sub">Your favourite movies are waiting. Book your seats today.</p>

      <div class="tabs">
        <div class="tab active" onclick="showTab(event, 'now-showing')">Now Showing</div>
        <div class="tab" onclick="showTab(event, 'coming-soon')">Coming Soon</div>
      </div>

      <div id="now-showing" class="tab-content active">
        <div class="movies-container">
          <?php while ($row = $now_showing_results->fetch_assoc()): ?>
            <div class="movie-card" tabindex="0">
              <div class="movie-poster-wrapper">
                <img src="/<?= htmlspecialchars($row['MoviePoster']) ?>" alt="<?= htmlspecialchars($row['MovieName']) ?>">
                <div class="movie-hover-overlay">
                  <?php if (!empty($row['Rating'])): ?>
                    <span class="overlay-rating-badge"><?= htmlspecialchars($row['Rating']) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($row['Genre'])): ?>
                    <p class="overlay-genre">🎬 <?= htmlspecialchars($row['Genre']) ?></p>
                  <?php endif; ?>
                  <?php if (!empty($row['Runtime'])): ?>
                    <p class="overlay-runtime">⏱ <?= htmlspecialchars($row['Runtime']) ?> mins</p>
                  <?php endif; ?>
                  <?php if (!empty($row['Price'])): ?>
                    <p class="overlay-price">From ₱<?= number_format($row['Price'], 2) ?></p>
                  <?php endif; ?>
                  <button class="trailer-btn" onclick="event.stopPropagation(); <?= !empty($row['TrailerURL']) ? "playTrailer('" . htmlspecialchars($row['TrailerURL']) . "')" : "" ?>">
                    ▶ Play Trailer
                  </button>
                </div>
              </div>
              <div class="movie-title"><?= htmlspecialchars($row['MovieName']) ?></div>
              <button class="buy-btn" data-id="<?= htmlspecialchars($row['Movie_ID']) ?>">Buy Tickets</button>
            </div>
          <?php endwhile; ?>
        </div>
      </div>

      <div id="coming-soon" class="tab-content">
        <div class="movies-container">
          <?php while ($row = $coming_soon_results->fetch_assoc()): ?>
            <div class="movie-card" tabindex="0">
              <div class="movie-poster-wrapper">
                <img src="/<?= htmlspecialchars($row['MoviePoster']) ?>" alt="<?= htmlspecialchars($row['MovieName']) ?>">
                <div class="movie-hover-overlay">
                  <?php if (!empty($row['Rating'])): ?>
                    <span class="overlay-rating-badge"><?= htmlspecialchars($row['Rating']) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($row['Genre'])): ?>
                    <p class="overlay-genre">🎬 <?= htmlspecialchars($row['Genre']) ?></p>
                  <?php endif; ?>
                  <?php if (!empty($row['Runtime'])): ?>
                    <p class="overlay-runtime">⏱ <?= htmlspecialchars($row['Runtime']) ?> mins</p>
                  <?php endif; ?>
                  <button class="trailer-btn" onclick="event.stopPropagation(); <?= !empty($row['TrailerURL']) ? "playTrailer('" . htmlspecialchars($row['TrailerURL']) . "')" : "" ?>">
                    ▶ Play Trailer
                  </button>
                </div>
              </div>
              <div class="movie-title"><?= htmlspecialchars($row['MovieName']) ?></div>
              <button class="buy-btn" data-id="<?= htmlspecialchars($row['Movie_ID']) ?>">Details</button>
            </div>
          <?php endwhile; ?>
        </div>
      </div>
    </main>
  </div>

  <div id="trailerModal" class="trailer-modal">
    <div class="trailer-modal-content">
      <span class="close-modal" onclick="closeTrailer()">✕</span>
      <iframe id="trailerFrame" width="100%" height="100%" frameborder="0" allow="autoplay; encrypted-media; fullscreen" allowfullscreen></iframe>
    </div>
  </div>

  <footer>
    <p>© <?= date('Y') ?> Peak's Cinema &nbsp;·&nbsp; All Rights Reserved</p>
  </footer>

  <script>
    function showTab(event, tabId) {
      const tabs = document.querySelectorAll('.tab');
      const tabContents = document.querySelectorAll('.tab-content');
      tabs.forEach(tab => tab.classList.toggle('active', tab === event.currentTarget));
      tabContents.forEach(content => content.classList.remove('active'));
      document.getElementById(tabId).classList.add('active');
    }

    function attachBuyButtons() {
      document.querySelectorAll('.buy-btn').forEach(button => {
        button.onclick = () => {
          window.location.href = `movie.php?movie_id=${button.getAttribute('data-id')}`;
        };
      });
    }
    attachBuyButtons();

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

    document.getElementById('trailerModal').onclick = function(e) { if (e.target === this) closeTrailer(); };
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeTrailer(); });

    const searchInput = document.getElementById("searchInput");
    const searchResults = document.getElementById("searchResults");
    let searchTimeout = null;

    searchInput.addEventListener("input", function() {
      const query = searchInput.value.trim();
      if (searchTimeout) clearTimeout(searchTimeout);
      if (query.length === 0) { searchResults.style.display = "none"; return; }
      searchTimeout = setTimeout(() => {
        fetch(`search_movies.php?ajax_search=${encodeURIComponent(query)}`)
          .then(res => res.json())
          .then(data => {
            if (!Array.isArray(data) || data.length === 0) {
              searchResults.innerHTML = "<div style='padding:15px; color:#888;'>No movies found</div>";
            } else {
              searchResults.innerHTML = data.map(movie =>
                `<div class='result-item' style='display:flex; align-items:center; padding:10px; cursor:pointer; border-bottom:1px solid #eee;'
                     onclick="window.location.href='movie.php?movie_id=${movie.Movie_ID}'">
                     <img src='/${movie.MoviePoster}' style='width:35px; height:48px; object-fit:cover; margin-right:10px; border-radius:4px;'>
                     <span style='font-weight:500;'>${movie.MovieName}</span>
                </div>`).join("");
            }
            searchResults.style.display = "block";
          });
      }, 300);
    });

    document.addEventListener("click", e => { if (!searchResults.contains(e.target) && e.target !== searchInput) searchResults.style.display = "none"; });

    (function() {
        const header = document.querySelector('header');
        let lastY = window.scrollY, ticking = false;
        window.addEventListener('scroll', () => {
            if (!ticking) {
                requestAnimationFrame(() => {
                    if (window.scrollY > lastY && window.scrollY > 80) header.classList.add('header-hidden');
                    else header.classList.remove('header-hidden');
                    lastY = window.scrollY; ticking = false;
                });
                ticking = true;
            }
        }, { passive: true });
    })();
  </script>
</body>
</html>