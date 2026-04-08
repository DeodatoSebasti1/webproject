<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include("../bancodedados/db.php");

$sql = "SELECT * FROM traffic";
$result = $conn->query($sql);

$traffic = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $traffic[] = $row;
    }
}

echo json_encode($traffic);

$conn->close();

?>