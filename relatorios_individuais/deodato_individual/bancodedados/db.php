<?php

$host = "localhost";
$user = "root";
$password = "";
$db = "urban_traffic";

$conn = new mysqli($host, $user, $password, $db);

if ($conn->connect_error) {
    die("Erro na ligação: " . $conn->connect_error);
}

?>