    
    
<?php
    
    session_start();
    $page = $_GET['page'] ?? 'home';
    $filePath = "pages/" . $page . ".php";


    include 'header.php';

    if (file_exists($filePath)) {
        include $filePath;
    } else {
        echo "<h1>Błąd 404</h1><p>Strona o podanym adresie nie istnieje.</p>";
    }

    include 'footer.php';
?>