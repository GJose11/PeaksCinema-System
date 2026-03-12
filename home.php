<?php
include_once "peakscinemas_database.php";
session_start();

$profile_link = "personal_info_form.php";
$profile_photo = $_SESSION['profile_photo'] ?? null;

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
    * { margin: 0; padding: 0; box-sizing: border-box; scroll-behavior: smooth; }

    body {
      font-family: 'Outfit', sans-serif;
      background: url("movie-background-collage.jpg") no-repeat center center fixed;
      background-size: cover;
      color: #F9F9F9;
      background-color: #1C1C1C;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    header {
      background-color: #1C1C1C;
      color: #F9F9F9;
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

    /* ── FIXED: search bar width ── */
    .search-container {
      position: relative;
      margin: 0 20px;
      flex: 1;
      max-width: 400px;
    }

    .search-container input {
      width: 100%;
      padding: 8px 18px;
      border-radius: 25px;
      border: none;
      background: #F9F9F9;
      color: #1C1C1C;
      font-family: 'Outfit', sans-serif;
      font-size: 0.95rem;
      outline: none;
      box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }

    .header-actions {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .profile-btn {
      background-color: transparent;
      border: 1px solid rgba(255,255,255,0.1);
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

    .profile-btn:hover {
      transform: scale(1.1);
      box-shadow: 0 0 12px rgba(255,255,255,0.3);
    }

    .glassbox {
      margin: 30px auto;
      width: 90%;
      max-width: 1200px;
      padding: 30px 20px;
      backdrop-filter: blur(3px);
      background-color: rgba(0,0,0,0.5);
      border-radius: 8px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.6);
      color: #F9F9F9;
    }

    .section-title {
      font-size: 1.4rem;
      font-weight: 700;
      margin-bottom: 10px;
      text-align: center;
    }

    .section-subtitle {
      font-size: 0.9rem;
      color: #e0e0e0;
      text-align: center;
      margin-bottom: 20px;
    }

    .tabs {
      display: flex;
      justify-content: center;
      margin-bottom: 25px;
      gap: 10px;
      flex-wrap: wrap;
    }

    .tab {
      background: rgba(255,255,255,0.05);
      color: #F9F9F9;
      padding: 8px 22px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      font-size: 0.9rem;
      border: 1px solid rgba(255,255,255,0.1);
      transition: all 0.3s ease;
      white-space: nowrap;
    }

    .tab.active {
      background: #ff4d4d;
      border-color: #ff4d4d;
      box-shadow: 0 0 15px rgba(255,77,77,0.3);
    }

    .tab:hover:not(.active) {
      background: rgba(255,255,255,0.15);
      transform: translateY(-2px);
    }

    .movies-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(185px, 1fr));
      gap: 16px;
    }

    .movie-card {
      background: rgba(255,255,255,0.03);
      border-radius: 8px;
      padding: 10px;
      text-align: center;
      transition: all 0.3s ease;
      display: flex;
      flex-direction: column;
    }

    .movie-card:hover {
      background: rgba(255,255,255,0.08);
      transform: translateY(-5px);
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
      background-color: #ff3333;
      transform: scale(1.03);
      box-shadow: 0 0 18px rgba(255, 75, 75, 0.6);
    }

    /* ── Trailer Modal ── */
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

    /* ── Movie Title ── */
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
      background: #F9F9F9;
      color: #1C1C1C;
      border: none;
      padding: 7px 12px;
      border-radius: 6px;
      cursor: pointer;
      transition: 0.3s;
      font-family: "Outfit", sans-serif;
      font-weight: 700;
      font-size: 0.85rem;
      width: 100%;
      margin-top: auto;
    }

    .buy-btn:hover {
      background: #ffffff;
      transform: scale(1.02);
      box-shadow: 0 4px 12px rgba(255,255,255,0.2);
    }

    .tab-content { display: none; }
    .tab-content.active { display: block; }

    footer {
      background-color: #1F1F1F;
      color: #F9F9F9;
      text-align: center;
      padding: 30px 20px;
      margin-top: auto;
      border-top: 1px solid rgba(255,255,255,0.05);
    }

    footer h2 { color: #F9F9F9; font-size: 1.3rem; margin-bottom: 8px; }
    footer p { font-size: 0.85rem; max-width: 600px; margin: 0 auto; line-height: 1.6; }

    @media (max-width: 768px) {
      .movies-container { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
      .movie-poster-wrapper img { height: 160px; }
      .glassbox { padding: 15px 10px; }
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
           onclick="window.location.href='home.php'" tabindex="0"
           onkeydown="if(event.key==='Enter' || event.key===' '){ event.preventDefault(); window.location.href='home.php'; }">
    </div>
    <div class="search-container">
      <form id="searchForm" action="javascript:void(0);" method="get">
        <input type="text" id="searchInput" name="search" placeholder="Search Movies..." autocomplete="off">
      </form>
      <div id="searchResults" style="position:absolute; top:45px; width:100%; background:#fff; color:#000; border-radius:8px; max-height:300px; overflow-y:auto; display:none; z-index:1000; box-shadow: 0 4px 15px rgba(0,0,0,0.3);"></div>
    </div>
    <div class="header-actions">
      <button class="profile-btn" onclick="window.location.href='<?= $profile_link ?>'" title="Profile">
        <?php if (!empty($profile_photo)): ?>
          <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile" referrerpolicy="no-referrer">
        <?php else: ?>
          👤
        <?php endif; ?>
      </button>
    </div>
  </header>

  <div class="glassbox">
    <main id="home">
      <h1 class="section-title">Browse Movies</h1>
      <p class="section-subtitle">Discover what&apos;s now showing or coming soon at PeaksCinemas.</p>

      <div class="tabs" role="tablist" aria-label="Movie availability">
        <div class="tab active" role="tab" aria-selected="true" aria-controls="now-showing" tabindex="0" onclick="showTab(event, 'now-showing')">Now Showing</div>
        <div class="tab" role="tab" aria-selected="false" aria-controls="coming-soon" tabindex="-1" onclick="showTab(event, 'coming-soon')">Coming Soon</div>
      </div>

      <!-- NOW SHOWING -->
      <div id="now-showing" class="tab-content active" role="tabpanel">
        <div class="movies-container">
          <?php while ($row = $now_showing_results->fetch_assoc()): ?>
            <div class="movie-card" tabindex="0"
                 onkeydown="if(event.key==='Enter' || event.key===' '){ event.preventDefault(); this.querySelector('.buy-btn').click(); }">
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
                  <button class="trailer-btn"
                          onclick="event.stopPropagation(); <?= !empty($row['TrailerURL']) ? "playTrailer('" . htmlspecialchars($row['TrailerURL']) . "')" : "" ?>">
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

      <!-- COMING SOON -->
      <div id="coming-soon" class="tab-content" role="tabpanel">
        <div class="movies-container">
          <?php while ($row = $coming_soon_results->fetch_assoc()): ?>
            <div class="movie-card" tabindex="0"
                 onkeydown="if(event.key==='Enter' || event.key===' '){ event.preventDefault(); this.querySelector('.buy-btn').click(); }">
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
                  <button class="trailer-btn"
                          onclick="event.stopPropagation(); <?= !empty($row['TrailerURL']) ? "playTrailer('" . htmlspecialchars($row['TrailerURL']) . "')" : "" ?>">
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

  <!-- Trailer Modal -->
  <div id="trailerModal" class="trailer-modal">
    <div class="trailer-modal-content">
      <span class="close-modal" onclick="closeTrailer()">✕</span>
      <iframe
        id="trailerFrame"
        width="100%"
        height="100%"
        frameborder="0"
        allow="autoplay; encrypted-media; fullscreen; picture-in-picture"
        allowfullscreen>
      </iframe>
    </div>
  </div>

  <footer>
    <h2>About Us</h2>
    <p>Welcome to <strong>Peak'sCinemas</strong>, where Peak Movies meet Peak Experiences. Immerse yourself in the ultimate cinematic journey with state-of-the-art visuals and sound.</p>
  </footer>

  <script>
    // Tab switching
    function showTab(event, tabId) {
      const tabs = document.querySelectorAll('.tab');
      const tabContents = document.querySelectorAll('.tab-content');
      tabs.forEach(tab => {
        const isActive = tab === event.currentTarget;
        tab.classList.toggle('active', isActive);
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        tab.setAttribute('tabindex', isActive ? '0' : '-1');
      });
      tabContents.forEach(content => content.classList.remove('active'));
      document.getElementById(tabId).classList.add('active');
    }

    // Buy / Details buttons
    function attachBuyButtons() {
      document.querySelectorAll('.buy-btn').forEach(button => {
        button.onclick = () => {
          const movieId = button.getAttribute('data-id');
          window.location.href = `movie.php?movie_id=${movieId}`;
        };
      });
    }
    attachBuyButtons();

    // Trailer modal
    function playTrailer(url) {
      let videoId = '';
      if (url.includes('watch?v=')) {
        videoId = url.split('watch?v=')[1].split('&')[0];
      } else if (url.includes('youtu.be/')) {
        videoId = url.split('youtu.be/')[1].split('?')[0];
      }
      if (videoId) {
        document.getElementById('trailerFrame').src =
          `https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0`;
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

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeTrailer();
    });

    // Search
    const searchInput = document.getElementById("searchInput");
    const searchResults = document.getElementById("searchResults");
    let searchTimeout = null;

    searchInput.addEventListener("input", function() {
      const query = searchInput.value.trim();
      if (searchTimeout) clearTimeout(searchTimeout);
      if (query.length === 0) {
        searchResults.style.display = "none";
        searchResults.innerHTML = "";
        return;
      }
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
                </div>`
              ).join("");
            }
            searchResults.style.display = "block";
          })
          .catch(() => {
            searchResults.innerHTML = "<div style='padding:15px; color:#888;'>Unable to search at the moment</div>";
            searchResults.style.display = "block";
          });
      }, 300);
    });

    document.addEventListener("click", function(e) {
      if (!searchResults.contains(e.target) && e.target !== searchInput) {
        searchResults.style.display = "none";
      }
    });
  </script>
</body>
</html>