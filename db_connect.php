<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); 
define('DB_NAME', 'kasyno_db');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Błąd połączenia z bazą danych: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

?>