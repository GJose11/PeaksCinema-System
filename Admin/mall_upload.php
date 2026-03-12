<?php 
    include("peakscinemas_database.php");

    // empty variables for later use
    $MallName = $Location = "";
    $uploadMessage = "";

    // kung naupload na nung form ito mangyayari
    if($_SERVER["REQUEST_METHOD"] == "POST") {
        // to clean up the inputted information
        function input_cleanup($data) {
        $data = trim($data);
        $data = stripslashes($data);
        return $data;
        }

        // prepared statement for later use
        $stmt = $conn -> prepare("INSERT INTO mall(MallName, Location)
                                  VALUES (?, ?)");
        $stmt -> bind_param("ss", $MallName, $Location);
        
        // admin input cleanup
        $MallName = input_cleanup($_POST['mallName']);
        $Location = input_cleanup($_POST['location']);
        // tapos actual execution ng prepared statement
        try {
            $stmt -> execute();

            $uploadMessage = "Successfully uploaded " . htmlspecialchars($MallName);
        } catch (Exception $e) {
            $uploadMessage = "There was a problem with uploading the mall. Please try again and make sure you have completed all fields.";
        }
    }

    mysqli_close($conn);
?>

<!DOCTYPE html>
<html>
    <head>
        <style>
            body main{
                display: flex;
            }

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
            <section id = mallDetailsSection>
                <form id = "mallDetails" action="<?php htmlspecialchars($_SERVER["PHP_SELF"]) ?>" method="POST" enctype="multipart/form-data" autocomplete="off">
                    <div>
                        <label for="mallName">Mall Name: </label>
                        <input type="text" id="mallName" name="mallName" placeholder="Mall Name" required>
                    </div>
                    <br>

                    <div>
                        <label for="location">Mall Location: </label><br>
                        <textarea id="location" name="location" rows="10" cols="75" placeholder="Mall Location" required></textarea>
                    </div>
                    <br>

                    <div>
                        <button type="submit" name="movieDetails" value="movieDetails">Upload</button>
                    </div>
                </form>
                <br>
                
                <div>
                    <?php echo $uploadMessage; ?>
                </div>
            </section>
        </main>
    </body>
</html>