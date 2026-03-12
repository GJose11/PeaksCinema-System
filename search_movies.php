<?php
include_once "peakscinemas_database.php";

if (isset($_GET['ajax_search'])) {
    $search = trim($_GET['ajax_search']);
    $search = $conn->real_escape_string($search) . "%";

    $stmt = $conn->prepare("SELECT Movie_ID, MovieName, MoviePoster FROM movie WHERE MovieName LIKE ? LIMIT 6");
    $stmt->bind_param("s", $search);
    $stmt->execute();
    $result = $stmt->get_result();

    $movies = [];
    while ($row = $result->fetch_assoc()) {
        $movies[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode($movies);
}
?>