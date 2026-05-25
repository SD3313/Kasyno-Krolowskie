<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Gier</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="container">
        <div class="top-bar">
            <div class="bonus">+200 (saldo dziś)</div>
            <div class="profil">
                <span>10 gemów (środki)</span>
                <div class="avatar"></div>
                Username
            </div>
        </div>

        <div class="games-grid">
            
            <div class="game-card" onclick="window.location.href='pages/bomb_sweep.php'">
                <span class="game-title">bomb sweep</span>
            </div>

            <div class="game-card" onclick="window.location.href='pages/dice.php'">
                <span class="game-title">dice</span>
            </div>

            <div class="game-card" onclick="window.location.href='pages/ruletka.php'">
                <span class="game-title">ruletka</span>
            </div>

            <div class="game-card" onclick="window.location.href='pages/crash.php'">
                <span class="game-title">crash</span>
            </div>

            <div class="game-card" onclick="window.location.href='pages/coin_flip.php'">
                <span class="game-title">coin flip</span>
            </div>

        </div>
    </div>

    <div class="footer-bar"></div>

</body>
</html>