<?php 
    include("peakscinemas_database.php");
    
    $stmt = $conn -> prepare("SELECT * FROM mall");
    $stmt -> execute();
    $existingMalls = $stmt -> get_result();
    
    mysqli_close($conn);
?>

<!DOCTYPE html>
<html>
    <head>
        <style>
            body #mallDetailsSection {
                width:35%;
                border: 2px solid black;
                padding: 20px;
            }
        </style>
    </head>
    <body>
        <header>
            <nav>
                <a href="dashboard.php" target="_self">Dashboard</a>
                <a href="malls_selection_admin.php" target="_self">Malls</a>
                <a href="movie_upload.php" target="_self">Movie Upload</a>
                <a href="theater_upload.php" target="_self">Theater Upload</a>
                <a href="mall_upload.php" target="_self">Mall Upload</a>
            </nav>            
        </header>
        <main>
            <?php
                while($row = $existingMalls -> fetch_assoc()) {
                    echo "<a href ='mall_admin.php?mall_id=" . urlencode($row['Mall_ID']) . "'>" . htmlspecialchars($row['MallName']) . "</a><br>";
                }
            ?>
        </main>
    </body>
</html>