<?php
    include("../peakscinemas_database.php");

    $posterFolder = $_SERVER['DOCUMENT_ROOT'] . '/PeaksCinema/MoviePosters';
    if (!is_dir($posterFolder)) mkdir($posterFolder, 0755, true);

    $successMsg = "";
    $errorMsg   = "";

    function input_cleanup($data) {
        return stripslashes(trim($data));
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["moviePosterUp"])) {

        $MovieName        = input_cleanup($_POST['movieName']);
        $MovieDescription = input_cleanup($_POST['movieDesc']);
        $Genre            = input_cleanup($_POST['movieGenre']);
        $Rating           = input_cleanup($_POST['movieRating']);
        $Runtime          = input_cleanup($_POST['movieRuntime']);
        $TrailerURL       = input_cleanup($_POST['TrailerURL']);
        $MovieAvailability= input_cleanup($_POST['movieAvailability']);
        $Price            = (float)$_POST['moviePrice'];
        $MoviePoster      = "";

        if ($_FILES['moviePosterUp']['error'] === UPLOAD_ERR_OK) {
            $temp     = $_FILES['moviePosterUp']['tmp_name'];
            $fileType = strtolower(pathinfo($_FILES['moviePosterUp']['name'], PATHINFO_EXTENSION));
            $allowed  = ['jpg','jpeg','png','webp'];

            if (!in_array($fileType, $allowed)) {
                $errorMsg = "Invalid file type. Please upload JPG, PNG, or WEBP.";
            } else {
                $safeMovieName = trim(preg_replace('/[\\\\\/:\*\?"<>\|]/', '', $MovieName));
                $fileName      = $safeMovieName . "." . $fileType;
                $endPath       = $posterFolder . "/" . $fileName;

                if (move_uploaded_file($temp, $endPath)) {
                    $MoviePoster = 'PeaksCinema/MoviePosters/' . $fileName;
                } else {
                    $errorMsg = "Failed to upload poster. Check folder permissions.";
                }
            }
        } else {
            $errorMsg = "No poster file received.";
        }

        if (!$errorMsg) {
            $check = $conn->prepare("SELECT Movie_ID FROM movie WHERE MovieName = ?");
            $check->bind_param("s", $MovieName); $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $errorMsg = "A movie with this name already exists.";
            } else {
                $stmt = $conn->prepare("INSERT INTO movie(MovieName, MovieDescription, Genre, Rating, Runtime, MoviePoster, MovieAvailability, TrailerURL, Price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssisssd", $MovieName, $MovieDescription, $Genre, $Rating, $Runtime, $MoviePoster, $MovieAvailability, $TrailerURL, $Price);
                if ($stmt->execute()) {
                    $successMsg = "\"$MovieName\" uploaded successfully!";
                } else {
                    $errorMsg = "Database error: " . $conn->error;
                }
            }
        }
    }

    $recentMovies = $conn->query("SELECT MovieName, MovieAvailability, Rating, Genre FROM movie ORDER BY Movie_ID DESC LIMIT 6");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <title>Movie Upload - PeaksCinemas Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: #0f0f0f; color: #F9F9F9; min-height: 100vh; padding-top: 70px; padding-bottom: 60px; }

        header { background: #1C1C1C; display: flex; align-items: center; justify-content: space-between; padding: 0 30px; position: fixed; top: 0; left: 0; width: 100%; z-index: 1000; height: 60px; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .logo { font-size: 1rem; font-weight: 700; letter-spacing: 2px; color: #F9F9F9; text-transform: uppercase; }
        nav { display: flex; gap: 4px; }
        nav a { color: rgba(249,249,249,0.5); text-decoration: none; font-size: 0.8rem; font-weight: 500; padding: 6px 14px; border-radius: 6px; transition: all 0.2s; }
        nav a:hover { background: rgba(255,255,255,0.08); color: #F9F9F9; }
        nav a.active { background: rgba(255,77,77,0.12); color: #ff4d4d; }

        .page-wrapper { width: 95%; max-width: 1100px; margin: 32px auto; display: flex; flex-direction: column; gap: 22px; }
        .page-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #ff4d4d; margin-bottom: 5px; }
        .page-title { font-size: 1.6rem; font-weight: 800; margin-bottom: 4px; }

        .upload-layout { display: grid; grid-template-columns: 1fr 320px; gap: 20px; align-items: start; }

        .panel { background: #1a1a1a; border: 1px solid rgba(255,255,255,0.07); border-radius: 14px; overflow: hidden; }
        .panel-header { padding: 14px 22px; border-bottom: 1px solid rgba(255,255,255,0.07); display: flex; align-items: center; gap: 8px; }
        .panel-header h2 { font-size: 0.78rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(249,249,249,0.45); }
        .panel-body { padding: 22px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: span 2; }
        .form-group label { font-size: 0.7rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.4); }
        .form-group input, .form-group select, .form-group textarea {
            background: #222; border: 1px solid rgba(255,255,255,0.1); border-radius: 9px;
            color: #F9F9F9; font-family: 'Outfit', sans-serif; font-size: 0.88rem;
            padding: 10px 13px; outline: none; transition: border-color 0.2s; width: 100%;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: rgba(255,77,77,0.5); background: #252525; }
        .form-group select option { background: #222; }
        .form-group input[type="number"] { color-scheme: dark; }
        .form-group textarea { resize: vertical; min-height: 110px; line-height: 1.6; }

        .genre-pills { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
        .genre-pill { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.04); color: rgba(249,249,249,0.5); cursor: pointer; transition: all 0.15s; user-select: none; }
        .genre-pill:hover, .genre-pill.selected { background: rgba(255,77,77,0.12); border-color: rgba(255,77,77,0.4); color: #ff4d4d; }

        .upload-zone { border: 2px dashed rgba(255,255,255,0.12); border-radius: 10px; padding: 24px; text-align: center; cursor: pointer; transition: all 0.2s; position: relative; }
        .upload-zone:hover, .upload-zone.drag-over { border-color: rgba(255,77,77,0.5); background: rgba(255,77,77,0.04); }
        .upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
        .upload-zone-icon { font-size: 2rem; margin-bottom: 8px; }
        .upload-zone-text { font-size: 0.82rem; color: rgba(249,249,249,0.4); }
        .upload-zone-text strong { color: #ff4d4d; }
        .upload-zone-hint { font-size: 0.72rem; color: rgba(249,249,249,0.25); margin-top: 4px; }

        .btn-submit { width: 100%; padding: 13px; margin-top: 10px; background: #ff4d4d; border: none; border-radius: 9px; color: #fff; font-family: 'Outfit', sans-serif; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: background 0.2s, transform 0.15s; }
        .btn-submit:hover { background: #e03c3c; transform: scale(1.01); }

        /* Preview panel */
        .preview-panel { position: sticky; top: 82px; }
        .poster-preview-box { aspect-ratio: 2/3; border-radius: 12px; overflow: hidden; background: #222; border: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: center; margin-bottom: 14px; }
        .poster-preview-box img { width: 100%; height: 100%; object-fit: cover; display: none; }
        .poster-placeholder { display: flex; flex-direction: column; align-items: center; gap: 10px; color: rgba(249,249,249,0.2); font-size: 0.82rem; }
        .poster-placeholder .ph-icon { font-size: 2.5rem; }
        .preview-movie-title { font-size: 1rem; font-weight: 800; margin-bottom: 6px; min-height: 24px; color: #F9F9F9; }
        .badge-preview { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 6px; }
        .badge-now  { background: rgba(77,210,100,0.15); color: #4dd264; border: 1px solid rgba(77,210,100,0.3); }
        .badge-soon { background: rgba(255,200,50,0.12);  color: #ffc83d; border: 1px solid rgba(255,200,50,0.3); }
        .preview-meta { font-size: 0.78rem; color: rgba(249,249,249,0.35); }

        /* Recent table */
        .recent-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        .recent-table th { text-align: left; font-size: 0.66rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.3); padding: 6px 12px; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .recent-table td { padding: 10px 12px; border-bottom: 1px solid rgba(255,255,255,0.04); color: #F9F9F9; vertical-align: middle; }
        .recent-table tr:last-child td { border-bottom: none; }
        .recent-table tr:hover td { background: rgba(255,255,255,0.02); }
        .td-muted { color: rgba(249,249,249,0.4); font-size: 0.78rem; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 5px; font-size: 0.68rem; font-weight: 700; }
        .badge-showing { background: rgba(77,210,100,0.12); color: #4dd264; }
        .badge-coming  { background: rgba(255,200,50,0.12);  color: #ffc83d; }
        .badge-rating  { background: rgba(255,77,77,0.1); color: #ff6b6b; border: 1px solid rgba(255,77,77,0.2); }

        .toast { position: fixed; bottom: 28px; right: 28px; background: #1a1a1a; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 14px 22px; font-size: 0.85rem; color: #F9F9F9; box-shadow: 0 8px 24px rgba(0,0,0,0.5); z-index: 9999; animation: slideIn 0.3s ease; }
        .toast.success { border-left: 3px solid #4caf50; }
        .toast.error   { border-left: 3px solid #ff4d4d; }
        @keyframes slideIn { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
    </style>
</head>
<body>

<header>
    <div class="logo">🎬 PeaksCinemas Admin</div>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="malls_selection_admin.php">Malls</a>
        <a href="movie_upload.php" class="active">Movie Upload</a>
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

    <div>
        <p class="page-label">Admin Panel</p>
        <h1 class="page-title">Upload a Movie</h1>
    </div>

    <div class="upload-layout">

        <!-- Form -->
        <div class="panel">
            <div class="panel-header"><h2>🎬 Movie Details</h2></div>
            <div class="panel-body">
                <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>" method="POST" enctype="multipart/form-data" autocomplete="off">
                    <div class="form-grid">

                        <div class="form-group full">
                            <label>Movie Name</label>
                            <input type="text" name="movieName" placeholder="e.g. The Batman" required oninput="updatePreviewTitle(this.value)">
                        </div>

                        <div class="form-group full">
                            <label>Synopsis</label>
                            <textarea name="movieDesc" placeholder="Write a short synopsis of the movie..." required></textarea>
                        </div>

                        <div class="form-group">
                            <label>Genre</label>
                            <input type="text" name="movieGenre" id="movieGenre" placeholder="e.g. Action/Crime" required oninput="updatePreviewMeta()">
                            <div class="genre-pills">
                                <?php foreach (['Action','Comedy','Drama','Horror','Romance','Thriller','Sci-Fi','Animation','Fantasy','Crime'] as $g): ?>
                                <span class="genre-pill" onclick="setGenre('<?= $g ?>')"><?= $g ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Age Rating</label>
                            <select name="movieRating" required>
                                <option value="">Select a rating</option>
                                <option value="G">Rated G</option>
                                <option value="PG">Rated PG</option>
                                <option value="R-13">Rated R-13</option>
                                <option value="R-16">Rated R-16</option>
                                <option value="R-18">Rated R-18</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Runtime (minutes)</label>
                            <input type="number" name="movieRuntime" placeholder="e.g. 176" min="1" max="999" required oninput="updatePreviewMeta()">
                        </div>

                        <div class="form-group">
                            <label>Base Ticket Price (&#8369;)</label>
                            <input type="number" name="moviePrice" placeholder="e.g. 350" min="1" step="0.01" required>
                        </div>

                        <div class="form-group">
                            <label>Availability</label>
                            <select name="movieAvailability" required onchange="updatePreviewBadge(this.value)">
                                <option value="Now Showing">Now Showing</option>
                                <option value="Coming Soon">Coming Soon</option>
                            </select>
                        </div>

                        <div class="form-group full">
                            <label>YouTube Trailer URL</label>
                            <input type="url" name="TrailerURL" placeholder="https://www.youtube.com/watch?v=..." required>
                        </div>

                        <div class="form-group full">
                            <label>Movie Poster</label>
                            <div class="upload-zone" id="uploadZone">
                                <input type="file" name="moviePosterUp" id="moviePosterUp" accept="image/png,image/jpeg,image/jpg,image/webp" required onchange="handlePosterChange(this)">
                                <div class="upload-zone-icon">🖼</div>
                                <div class="upload-zone-text"><strong>Click to upload</strong> or drag &amp; drop</div>
                                <div class="upload-zone-hint" id="uploadHint">JPG, PNG, WEBP &mdash; 2:3 ratio recommended</div>
                            </div>
                        </div>

                    </div>
                    <button type="submit" class="btn-submit">&#10003; Upload Movie</button>
                </form>
            </div>
        </div>

        <!-- Live Preview -->
        <div class="preview-panel">
            <div class="panel">
                <div class="panel-header"><h2>&#128065; Live Preview</h2></div>
                <div class="panel-body">
                    <div class="poster-preview-box">
                        <div class="poster-placeholder" id="posterPlaceholder">
                            <span class="ph-icon">🎞</span>
                            <span>Poster appears here</span>
                        </div>
                        <img id="posterPreview" src="" alt="Poster Preview">
                    </div>
                    <div class="preview-movie-title" id="previewTitle">Movie Title</div>
                    <div><span class="badge-preview badge-now" id="previewBadge">Now Showing</span></div>
                    <div class="preview-meta" id="previewMeta" style="margin-top:6px;">Fill in the form to preview</div>
                </div>
            </div>
        </div>

    </div>

    <!-- Recently Uploaded -->
    <div class="panel">
        <div class="panel-header"><h2>&#128336; Recently Uploaded</h2></div>
        <?php if ($recentMovies && $recentMovies->num_rows > 0): ?>
        <table class="recent-table">
            <thead><tr><th>Movie</th><th>Genre</th><th>Rating</th><th>Status</th></tr></thead>
            <tbody>
            <?php while ($m = $recentMovies->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($m['MovieName']) ?></td>
                <td class="td-muted"><?= htmlspecialchars($m['Genre']) ?></td>
                <td><span class="badge badge-rating"><?= htmlspecialchars($m['Rating']) ?></span></td>
                <td>
                    <?php if ($m['MovieAvailability'] === 'Now Showing'): ?>
                        <span class="badge badge-showing">Now Showing</span>
                    <?php else: ?>
                        <span class="badge badge-coming">Coming Soon</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div style="text-align:center;padding:30px;color:rgba(249,249,249,0.2);font-size:0.85rem;">No movies uploaded yet.</div>
        <?php endif; ?>
    </div>

</div>

<?php if ($successMsg): ?>
<div class="toast success" id="toast">&#10003; <?= htmlspecialchars($successMsg) ?></div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t)t.remove();},4000);</script>
<?php endif; ?>

<?php if ($errorMsg): ?>
<div class="toast error" id="toast">&#10005; <?= htmlspecialchars($errorMsg) ?></div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t)t.remove();},5000);</script>
<?php endif; ?>

<script>
    function handlePosterChange(input) {
        if (!input.files[0]) return;
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.getElementById('posterPreview');
            img.src = e.target.result;
            img.style.display = 'block';
            document.getElementById('posterPlaceholder').style.display = 'none';
            document.getElementById('uploadHint').textContent = '📎 ' + input.files[0].name;
        };
        reader.readAsDataURL(input.files[0]);
    }

    function updatePreviewTitle(val) {
        document.getElementById('previewTitle').textContent = val || 'Movie Title';
    }

    function updatePreviewBadge(val) {
        const badge = document.getElementById('previewBadge');
        if (val === 'Now Showing') {
            badge.textContent = 'Now Showing';
            badge.className = 'badge-preview badge-now';
        } else {
            badge.textContent = 'Coming Soon';
            badge.className = 'badge-preview badge-soon';
        }
    }

    function setGenre(genre) {
        const input = document.getElementById('movieGenre');
        if (!input.value) {
            input.value = genre;
        } else if (!input.value.includes(genre)) {
            input.value += '/' + genre;
        }
        document.querySelectorAll('.genre-pill').forEach(p => {
            p.classList.toggle('selected', input.value.includes(p.textContent));
        });
        updatePreviewMeta();
    }

    function updatePreviewMeta() {
        const genre   = document.getElementById('movieGenre').value;
        const runtime = document.querySelector('[name="movieRuntime"]')?.value;
        let parts = [];
        if (genre)   parts.push(genre);
        if (runtime) parts.push(runtime + ' mins');
        document.getElementById('previewMeta').textContent = parts.join(' · ') || 'Fill in the form to preview';
    }

    // Drag & drop highlight
    const zone = document.getElementById('uploadZone');
    zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', ()  => zone.classList.remove('drag-over'));
    zone.addEventListener('drop',      e  => { e.preventDefault(); zone.classList.remove('drag-over'); });
</script>
</body>
</html>