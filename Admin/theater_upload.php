<?php
    include("peakscinemas_database.php");
    // makes a query to the database to check for existing malls from the mall table
    $mall_search = $conn -> query("SELECT Mall_ID, MallName FROM mall");
    // creates empty variables for later use yadda yadda
    $Mall_ID = 0;
    $TheaterName = $TheaterType = $TotalSeats = "";
    $Theater_ID = 0;
    $SeatRow = $SeatType = "";
    $SeatColumn = 0;

    // kung nagsubmit na nung admin at kung naupload ng admin nung theater layout properly all of this will run
    if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["theaterLayoutUp"])) {
        // this is for the json theater layout. it basically checks the uploaded file that the admin has uploaded.
        $jsonLocation = file_get_contents($_FILES["theaterLayoutUp"]["tmp_name"]);
        $seatLayout = json_decode($jsonLocation, true);
        
        // input cleanup so that the inputted data will be clean (unless the admin themself spams random letters. cant do anything about that... (i mean you can its just another can of worms))
        function input_cleanup($data) {
        $data = trim($data);
        $data = stripslashes($data);
        return $data;
        }

        // prepared statement for the theater table. basically it prepares the insertion of values to the table so that it's safe to upload
        $theater_to_db_stmt = $conn -> prepare("INSERT INTO theater(Mall_ID, TheaterName, TheaterType, TotalSeats)
                                                VALUES (?, ?, ?, ?)");
        $theater_to_db_stmt -> bind_param("issi", $Mall_ID, $TheaterName, $TheaterType, $TotalSeats);
        
        // input cleanup for the form stuff
        $Mall_ID = $_POST['mall_id'];
        $TheaterName = input_cleanup($_POST['theaterName']);
        $TheaterType = input_cleanup($_POST['theaterType']);
        $TotalSeats = input_cleanup($_POST['totalSeats']);

        // executes the prepared statement. basically this is where the actual insertion to the theater table happens.
        $theater_to_db_stmt -> execute();

        // this line of code gets the new theater_id. so lets say the last inputted theater id is 1, once the admin uploads another one the new theater id would be 2
        $Theater_ID = $conn -> insert_id;

        // another prepared statement but this time for the seats
        $seats_to_db_stmt = $conn -> prepare("INSERT INTO seats(SeatRow, SeatColumn, SeatType, Theater_ID)
                                              VALUES (?, ?, ?, ?)");
        $seats_to_db_stmt -> bind_param("sisi", $SeatRow, $SeatColumn, $SeatType, $Theater_ID);

        // this is where the seats table gets inserted
        foreach ($seatLayout['seats'] as $SeatRow => $cols) {
            foreach ($cols as $seat) {
                $SeatColumn = $seat['SeatColumn'];
                $SeatType = $seat['SeatType'];

                $seats_to_db_stmt -> execute();
            }
        }
    }

    mysqli_close($conn);
?>

<!DOCTYPE html>
<html>
    <head>
        <style>
            body.theaterUpload main{
                display: flex;
            }

            body.theaterUpload section {
                width:50%;
                border: 2px solid black;
            }

            body.theaterUpload #theaterFormSection {
                width: auto;
                padding: 20px;
            }

            body.theaterUpload #theaterLayoutSection {
                width: auto;
                padding: 20px;
            }

            body.theaterUpload #posterPreview {
                border: 0;
                background-position: right;
                background-size: cover;
            }

            .theaterSeat {
                border: 1px solid black;
                background-color: #be6363ff;
                width: 25px;
                height: 25px;
                text-align: center;
                vertical-align: middle;
                border-radius: 20%;
            }

            .emptySeat {                
                width: 25px;
                height: 25px;
                background-color: transparent;
                border: 1px solid transparent;
            }

            .screenCSS {
                border: 3px solid black;
                background-color: #9eb0ecff;
                width: 100%;
                text-align: center;
                font-weight: bold;
                margin-bottom: 10px;
                border-radius: 5%;
            }
        </style>
    </head>
    <body class = "theaterUpload">
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
            <section id = "theaterFormSection">
                <form id = "theaterDetails" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data" autocomplete="off">
                    <div>
                        <label for="theaterName">Theater Name: </label>
                        <input type="text" id="theaterName" name="theaterName" placeholder="Theater Name" required>
                        <h> located in </h>

                        <label for="mall_id">Mall Name: </label>
                        <select id = "mall_id" name = "mall_id" required>
                            <option value="">Select an existing mall</option>
                            <?php
                                // kung may mall nahanap sa database ilalapag nung mga available malls to the dropdown
                                if ($mall_search -> num_rows > 0) {
                                    while ($row = $mall_search -> fetch_assoc()) {
                                        echo "<option value ='" . htmlspecialchars($row['Mall_ID']) . "'>" . htmlspecialchars($row['MallName']) . "</option>";
                                    }
                                } else { // pero kung wala this will show up
                                    echo "<option disabled>No malls found</option>";
                                }                                
                            ?>

                        </select>
                    </div>
                    <br>

                    <div>
                        <label for="theaterType">Theater Type: </label>
                        <input type="text" id="theaterType" name="theaterType" placeholder="Theater Type" required>
                    </div>
                    <br>

                    <div>
                        <label for="theaterLayoutUp">Theater Layout: </label><br>
                        <input type="file" id="theaterLayoutUp" name="theaterLayoutUp" accept=".json" required>
                    </div>
                    <br>

                    <div>
                        <label for="totalSeats">Total Seats: <input type="hidden" id="totalSeats" name="totalSeats">
                        <span id = "totalSeatsNum"></span></label>
                    </div>
                    <br>

                    <div>
                        <button type="submit" name="movieDetails" value="movieDetails">Upload</button>
                    </div>
                </form>
            </section>
            <section id = "theaterLayoutSection">
                <span>Layout Preview:</span><br><br>
                <div id = "theaterScreen"></div>
                <div id = "theaterLayoutPreview"></div>
                <table id = 'tablelayout'></table>
            </section>
            
        </main>

        <script>
            const theaterScreen = document.getElementById("theaterScreen");

            const theaterLayoutUp = document.getElementById("theaterLayoutUp");
            const theaterLayoutPreview = document.getElementById("theaterLayoutPreview");

            const totalSeatsNum = document.getElementById("totalSeatsNum");
            const totalSeats = document.getElementById("totalSeats");

            var theaterLayoutJSON = "";

            theaterLayoutUp.addEventListener('change', function() {                        
                const tableLayout = document.getElementById('tablelayout');
                tableLayout.innerHTML = '';

                const reader = new FileReader();
                reader.addEventListener('load', () => {
                    try {
                        theaterLayoutJSON = JSON.parse(reader.result);
                        const seatLayout = theaterLayoutJSON.seats;
                        let seatCount = 0;
                        
                        for (const seatRow in seatLayout) {
                            const seatCols = seatLayout[seatRow]
                                .map(col => {
                                    const seatCol = col.SeatColumn;
                                    const seatType = col.SeatType.toLowerCase();

                                    if (Number(seatCol) == 0 || seatType === "Empty") {
                                        return `<td class = "theaterSeat emptySeat"></td>`;
                                    }                                    
                                    seatCount += 1;
                                    return `<td class = "theaterSeat">${seatCol}</td>`;
                                })
                                .join('');

                            tableLayout.innerHTML += `<tr><th>${seatRow}</th><td>${seatCols}</td><th>${seatRow}</th></tr>`;                  
                        }

                        theaterScreen.classList.add("screenCSS");
                        theaterScreen.innerHTML = "SCREEN";

                        totalSeatsNum.innerHTML = `${seatCount}`;
                        totalSeats.value = seatCount;
                        

                    } catch (error) {
                        theaterLayoutPreview.innerText = "There was an error with the JSON file. Please try another or fix the errors within this one.";
                        console.error(error);
                    }
                })

                reader.readAsText(this.files[0]);
            })
        </script>
    </body>
</html>