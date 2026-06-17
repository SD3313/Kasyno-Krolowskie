<?php
session_start();
$page = $_GET['page'] ?? 'home';

// Routing dla podstron w podfolderach
$routes = [
    'saldo' => 'pages/podfoldery_profil/saldo.php',
    'home' => 'pages/home.php',
    'profil' => 'pages/profil.php',
    'crash' => 'pages/crash.php',
    'coin_flip' => 'pages/coin_flip.php',
    'bomb_sweep' => 'pages/bomb_sweep.php',
    'dice' => 'pages/dice.php',
    'ruletka' => 'pages/ruletka.php',
    'battle' => 'pages/case battle.php',
];

$filePath = $routes[$page] ?? 'pages/' . $page . '.php';

include 'header.php';

if (file_exists($filePath)) {
    include $filePath;
} else {
    echo "<h1>Błąd 404</h1><p>Strona o podanym adresie nie istnieje.</p>";
}

include 'footer.php';
?>