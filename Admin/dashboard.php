<?php
    include("../peakscinemas_database.php");

    // Stats
    $totalMovies    = $conn->query("SELECT COUNT(*) AS c FROM movie")->fetch_assoc()['c'];
    $totalMalls     = $conn->query("SELECT COUNT(*) AS c FROM mall")->fetch_assoc()['c'];
    $totalTheaters  = $conn->query("SELECT COUNT(*) AS c FROM theater")->fetch_assoc()['c'];
    $totalScreenings= $conn->query("SELECT COUNT(*) AS c FROM timeslot WHERE Date >= CURDATE()")->fetch_assoc()['c'];
    $totalBookings  = $conn->query("SELECT COUNT(*) AS c FROM ticket")->fetch_assoc()['c'];
    // Dynamically find the numeric amount column in payment table
    $totalRevenue = 0;
    $colResult = $conn->query("SHOW COLUMNS FROM payment");
    $paymentCol = null;
    $numericTypes = ['int','bigint','decimal','float','double','numeric','mediumint','smallint','tinyint'];
    if ($colResult) {
        while ($col = $colResult->fetch_assoc()) {
            $baseType = strtolower(explode('(', $col['Type'])[0]);
            if (in_array($baseType, $numericTypes) && stripos($col['Field'], 'ID') === false) {
                $paymentCol = $col['Field'];
                break;
            }
        }
    }
    if ($paymentCol) {
        $revRow = $conn->query("SELECT COALESCE(SUM(`$paymentCol`),0) AS r FROM payment");
        if ($revRow) $totalRevenue = $revRow->fetch_assoc()['r'];
    }

    // Recent bookings (last 5)
    $recentBookings = $conn->query("
        SELECT t.Ticket_ID, c.Name AS CustomerName, m.MovieName, ts.Date, ts.StartTime
        FROM ticket t
        JOIN customer c ON t.Customer_ID = c.Customer_ID
        JOIN timeslot ts ON t.TimeSlot_ID = ts.TimeSlot_ID
        JOIN movie m ON ts.Movie_ID = m.Movie_ID
        ORDER BY t.Ticket_ID DESC
        LIMIT 5
    ");

    // Upcoming screenings (next 5)
    $upcomingScreenings = $conn->query("
        SELECT m.MovieName, ts.Date, ts.StartTime, ts.ScreeningType, th.TheaterName, ml.MallName
        FROM timeslot ts
        JOIN movie m ON ts.Movie_ID = m.Movie_ID
        JOIN theater th ON ts.Theater_ID = th.Theater_ID
        JOIN mall ml ON th.Mall_ID = ml.Mall_ID
        WHERE ts.Date >= CURDATE()
        ORDER BY ts.Date ASC, ts.StartTime ASC
        LIMIT 5
    ");

    // Now showing movies
    $nowShowing = $conn->query("SELECT MovieName, MoviePoster FROM movie WHERE MovieAvailability = 'Now Showing' LIMIT 6");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <title>Dashboard – PeaksCinemas Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

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
            background: url('../movie-background-collage.jpg') center/cover no-repeat;
            opacity: 0.04; z-index: 0; pointer-events: none;
        }
        body::after {
            content: '';
            position: fixed; inset: 0;
            background: radial-gradient(ellipse at center, transparent 20%, #0f0f0f 75%);
            z-index: 1; pointer-events: none;
        }
        header, nav, .page-wrapper { position: relative; z-index: 10; }

        /* ── Header ── */
        header {
            background: #1C1C1C;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            position: fixed;
            top: 0; left: 0; width: 100%;
            z-index: 1000;
            height: 60px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .logo { font-size: 1rem; font-weight: 700; letter-spacing: 2px; color: #F9F9F9; text-transform: uppercase; }

        nav { display: flex; gap: 4px; }

        nav a {
            color: rgba(249,249,249,0.5);
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            padding: 6px 14px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        nav a:hover { background: rgba(255,255,255,0.08); color: #F9F9F9; }
        nav a.active { background: rgba(255,77,77,0.12); color: #ff4d4d; }

        /* ── Page ── */
        .page-wrapper {
            width: 95%;
            max-width: 1200px;
            margin: 32px auto;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* ── Page header ── */
        .page-header { display: flex; align-items: flex-end; justify-content: space-between; }

        .page-label {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #ff4d4d;
            margin-bottom: 5px;
        }

        .page-title { font-size: 1.7rem; font-weight: 800; }

        .page-date {
            font-size: 0.8rem;
            color: rgba(249,249,249,0.3);
            text-align: right;
        }

        /* ── Stats grid ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        .stat-card {
            background: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 14px;
            padding: 20px 22px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: border-color 0.2s, transform 0.2s;
        }
        .stat-card:hover { border-color: rgba(255,77,77,0.25); transform: translateY(-2px); }

        .stat-icon {
            width: 46px; height: 46px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .stat-icon.red    { background: rgba(255,77,77,0.12);  border: 1px solid rgba(255,77,77,0.2); }
        .stat-icon.blue   { background: rgba(77,150,255,0.12); border: 1px solid rgba(77,150,255,0.2); }
        .stat-icon.green  { background: rgba(77,210,120,0.12); border: 1px solid rgba(77,210,120,0.2); }
        .stat-icon.yellow { background: rgba(255,200,50,0.12); border: 1px solid rgba(255,200,50,0.2); }
        .stat-icon.purple { background: rgba(180,100,255,0.12);border: 1px solid rgba(180,100,255,0.2); }
        .stat-icon.teal   { background: rgba(50,210,200,0.12); border: 1px solid rgba(50,210,200,0.2); }

        .stat-info { flex: 1; }

        .stat-label {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.35);
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 1.6rem;
            font-weight: 800;
            color: #F9F9F9;
            line-height: 1;
        }

        .stat-sub {
            font-size: 0.72rem;
            color: rgba(249,249,249,0.3);
            margin-top: 3px;
        }

        /* ── Two-col layout ── */
        .two-col { display: grid; grid-template-columns: 1.2fr 1fr; gap: 16px; }

        /* ── Panel ── */
        .panel {
            background: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 14px;
            overflow: hidden;
        }

        .panel-header {
            padding: 14px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .panel-header h2 {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.45);
        }

        .panel-link {
            font-size: 0.75rem;
            color: #ff4d4d;
            text-decoration: none;
            font-weight: 600;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        .panel-link:hover { opacity: 1; }

        .panel-body { padding: 16px 20px; }

        /* ── Table ── */
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }

        .data-table th {
            text-align: left;
            font-size: 0.66rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.3);
            padding: 6px 10px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .data-table td {
            padding: 10px 10px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            color: #F9F9F9;
            vertical-align: middle;
        }

        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover td { background: rgba(255,255,255,0.02); }

        .td-muted { color: rgba(249,249,249,0.4); font-size: 0.78rem; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 5px;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .badge-2d     { background: rgba(255,255,255,0.08); color: rgba(249,249,249,0.7); }
        .badge-3d     { background: rgba(77,150,255,0.12);  color: #7ec8ff; }
        .badge-imax   { background: rgba(255,200,50,0.12);  color: #ffc83d; }
        .badge-4dx    { background: rgba(180,100,255,0.12); color: #c87eff; }

        /* ── Quick actions ── */
        .quick-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .quick-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 10px;
            color: #F9F9F9;
            text-decoration: none;
            font-family: 'Outfit', sans-serif;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
            cursor: pointer;
        }
        .quick-btn:hover {
            background: rgba(255,77,77,0.08);
            border-color: rgba(255,77,77,0.3);
            color: #ff4d4d;
            transform: translateY(-1px);
        }

        .quick-btn-icon {
            width: 34px; height: 34px;
            background: rgba(255,77,77,0.1);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
            transition: background 0.2s;
        }
        .quick-btn:hover .quick-btn-icon { background: rgba(255,77,77,0.2); }

        /* ── Now Showing strip ── */
        .movies-strip {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding-bottom: 4px;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,77,77,0.3) transparent;
        }

        .movie-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            padding: 10px 14px;
            white-space: nowrap;
            flex-shrink: 0;
            font-size: 0.82rem;
            font-weight: 600;
            color: #F9F9F9;
        }

        .movie-dot { width: 8px; height: 8px; background: #ff4d4d; border-radius: 50%; flex-shrink: 0; }

        .empty-state {
            text-align: center;
            padding: 28px;
            color: rgba(249,249,249,0.2);
            font-size: 0.82rem;
        }
    </style>
</head>
<body>

<header>
    <div class="logo">🎬 PeaksCinemas Admin</div>
    <nav>
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="malls_selection_admin.php">Malls</a>
        <a href="malls_selection_admin.php">➕ Add Screenings</a>
        <a href="movie_upload.php">Movie Upload</a>
        <a href="theater_upload.php">Theater Upload</a>
        <a href="mall_upload.php">Mall Upload</a>
    </nav>
    <div style="display:flex;align-items:center;gap:8px;">
        <a href="../home.php"
           style="color:rgba(249,249,249,0.45);text-decoration:none;font-size:0.78rem;font-weight:500;
                  padding:6px 14px;border-radius:6px;border:1px solid rgba(255,255,255,0.1);
                  transition:all 0.2s;display:flex;align-items:center;gap:5px;"
           onmouseover="this.style.background='rgba(255,255,255,0.06)';this.style.color='#F9F9F9'"
           onmouseout="this.style.background='';this.style.color='rgba(249,249,249,0.45)'">
            &#8592; Customer Site
        </a>
        <a href="admin_logout.php"
           style="color:#ff4d4d;text-decoration:none;font-size:0.78rem;font-weight:600;
                  padding:6px 14px;border-radius:6px;border:1px solid rgba(255,77,77,0.3);
                  background:rgba(255,77,77,0.08);transition:all 0.2s;display:flex;align-items:center;gap:5px;"
           onmouseover="this.style.background='rgba(255,77,77,0.18)'"
           onmouseout="this.style.background='rgba(255,77,77,0.08)'"
           onclick="return confirm('Log out of admin panel?')">
            &#x2192; Log Out
        </a>
    </div>
</header>

<div class="page-wrapper">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <p class="page-label">Admin Panel</p>
            <h1 class="page-title">Dashboard</h1>
        </div>
        <div class="page-date">
            📅 <?= date('l, F j, Y') ?><br>
            <span style="font-size:0.72rem;"><?= date('g:i A') ?></span>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon red">🎬</div>
            <div class="stat-info">
                <div class="stat-label">Movies</div>
                <div class="stat-value"><?= $totalMovies ?></div>
                <div class="stat-sub">In library</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">🏬</div>
            <div class="stat-info">
                <div class="stat-label">Malls</div>
                <div class="stat-value"><?= $totalMalls ?></div>
                <div class="stat-sub"><?= $totalTheaters ?> theater<?= $totalTheaters != 1 ? 's' : '' ?> total</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">📅</div>
            <div class="stat-info">
                <div class="stat-label">Upcoming Screenings</div>
                <div class="stat-value"><?= $totalScreenings ?></div>
                <div class="stat-sub">From today onwards</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow">🎟</div>
            <div class="stat-info">
                <div class="stat-label">Total Bookings</div>
                <div class="stat-value"><?= $totalBookings ?></div>
                <div class="stat-sub">All time</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon teal">💰</div>
            <div class="stat-info">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value">₱<?= number_format($totalRevenue, 0) ?></div>
                <div class="stat-sub">From payments</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">🏛</div>
            <div class="stat-info">
                <div class="stat-label">Theaters</div>
                <div class="stat-value"><?= $totalTheaters ?></div>
                <div class="stat-sub">Across all malls</div>
            </div>
        </div>
    </div>

    <!-- Now Showing strip -->
    <div class="panel">
        <div class="panel-header">
            <h2>🔴 Now Showing</h2>
            <a href="movie_upload.php" class="panel-link">+ Add Movie</a>
        </div>
        <div class="panel-body">
            <?php if ($nowShowing && $nowShowing->num_rows > 0): ?>
            <div class="movies-strip">
                <?php while ($m = $nowShowing->fetch_assoc()): ?>
                <div class="movie-pill">
                    <span class="movie-dot"></span>
                    <?= htmlspecialchars($m['MovieName']) ?>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
                <div class="empty-state">No movies marked as "Now Showing".</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Bookings + Quick Actions -->
    <div class="two-col">

        <!-- Recent Bookings -->
        <div class="panel">
            <div class="panel-header">
                <h2>🎟 Recent Bookings</h2>
            </div>
            <?php if ($recentBookings && $recentBookings->num_rows > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Movie</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($b = $recentBookings->fetch_assoc()): ?>
                    <tr>
                        <td class="td-muted">#<?= $b['Ticket_ID'] ?></td>
                        <td><?= htmlspecialchars($b['CustomerName']) ?></td>
                        <td class="td-muted"><?= htmlspecialchars($b['MovieName']) ?></td>
                        <td class="td-muted"><?= date('M d', strtotime($b['Date'])) ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-state">No bookings yet.</div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="panel">
            <div class="panel-header"><h2>⚡ Quick Actions</h2></div>
            <div class="panel-body">
                <div class="quick-grid">
                    <a href="malls_selection_admin.php" class="quick-btn">
                        <div class="quick-btn-icon">🎭</div>
                        Manage Screenings
                    </a>
                    <a href="movie_upload.php" class="quick-btn">
                        <div class="quick-btn-icon">🎬</div>
                        Upload Movie
                    </a>
                    <a href="theater_upload.php" class="quick-btn">
                        <div class="quick-btn-icon">🏛</div>
                        Add Theater
                    </a>
                    <a href="mall_upload.php" class="quick-btn">
                        <div class="quick-btn-icon">🏬</div>
                        Add Mall
                    </a>
                </div>
            </div>
        </div>

    </div>

    <!-- Upcoming Screenings -->
    <div class="panel">
        <div class="panel-header">
            <h2>📅 Upcoming Screenings</h2>
            <a href="malls_selection_admin.php" class="panel-link">Manage →</a>
        </div>
        <?php if ($upcomingScreenings && $upcomingScreenings->num_rows > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Movie</th>
                    <th>Mall</th>
                    <th>Theater</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($s = $upcomingScreenings->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($s['MovieName']) ?></td>
                    <td class="td-muted"><?= htmlspecialchars($s['MallName']) ?></td>
                    <td class="td-muted"><?= htmlspecialchars($s['TheaterName']) ?></td>
                    <td class="td-muted"><?= date('M d, Y', strtotime($s['Date'])) ?></td>
                    <td class="td-muted"><?= date('g:i A', strtotime($s['StartTime'])) ?></td>
                    <td>
                        <?php
                            $typeKey = strtolower($s['ScreeningType']);
                            if      ($typeKey === '2d')   echo '<span class="badge badge-2d">2D</span>';
                            elseif  ($typeKey === '3d')   echo '<span class="badge badge-3d">3D</span>';
                            elseif  ($typeKey === 'imax') echo '<span class="badge badge-imax">IMAX</span>';
                            elseif  ($typeKey === '4dx')  echo '<span class="badge badge-4dx">4DX</span>';
                            else    echo '<span class="badge badge-2d">' . htmlspecialchars($s['ScreeningType']) . '</span>';
                        ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="empty-state">No upcoming screenings. Add one via Malls → Theater.</div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>