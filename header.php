<?php
require_once 'init_session.php';
$balance = $_SESSION['user_balance'] ?? 0;
$logged_in = isset($_SESSION['user_id']);
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
    

    <link rel="stylesheet" href="style.css?1">
</head>
<body>
    <div class="container">
        <div class="top-bar">
            <a href="home" class="logo">
                <img src="/kasyno/Kasyno-Krolowskie/photos/logo.svg" alt="Logo Kasyno Królewskie">
            </a>

            <?php if ($logged_in): ?>
                <a href="profil" class="profil-link">    
                    <div class="profil" >
                        <div class="avatar"></div>
                        <div class="username"><?= htmlspecialchars($_SESSION['user'] ?? 'Użytkownik') ?></div>
                    </div>
                </a>
            <?php else: ?>
                <a href="login" class="profil-link">    
                    <div class="profil" >
                        <div class="username">Zaloguj się</div>
                    </div>
                </a>
            <?php endif; ?>
        </div>