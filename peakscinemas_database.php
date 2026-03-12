<?php
    $servername = "localhost";
    $user = "root";
    $pass = "";
    $db = "peakscinemadb";

    try {        
        $conn = mysqli_connect($servername, $user, $pass, $db);
    }
    catch(mysqli_sql_exception) {
        echo"Could not connect to the database. Please try again or message the database administrator.";
    }
?>    