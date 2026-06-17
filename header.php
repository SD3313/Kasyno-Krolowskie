<?php
require_once 'init_session.php';
$balance = $_SESSION['user_balance'] ?? 0;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasyno Królewskie | Strona główna</title>

    <!-- Ikona strony -->
    <link rel="icon" type="image/svg" href="photos/icona_korona.svg">
    <link rel="icon" type="image/png" href="photos/icona_korona.png">
    

    <link rel="stylesheet" href="style.css?233">
</head>
<body>
    <div class="container">
        <div class="top-bar">
            <a href="?page=home" class="logo">
                <img src="/kasyno/Kasyno-Krolowskie/photos/logo.svg" alt="Logo Kasyno Królewskie">
            </a>
            <div class="saldo" id="headerBalance" onclick="window.location.href='?page=saldo'">
                💰 <span id="headerBalanceValue"><?= $balance ?></span> żetonów (saldo)
            </div>
            <div class="profil" onclick="window.location.href='?page=profil'">
                <span>10 gemów (środki)</span>
                <div class="avatar"></div>
                <div class="username">Username</div>
            </div>
        </div>