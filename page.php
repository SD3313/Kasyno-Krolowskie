<?php
$page = $_GET['page'] ?? '';
$titles = [
    'regulamin' => ['Regulamin', '📄'],
    'kontakt' => ['Kontakt', '📧'],
    'pytania' => ['Pytania', '❓'],
    'kontrola-rodzicielska' => ['Kontrola rodzicielska', '👨‍👩‍👧']
];
$title = $titles[$page][0] ?? 'Strona';
$icon = $titles[$page][1] ?? '📄';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> - Strona w przygotowaniu</title>
    <!-- reszta stylów jak wyżej -->
</head>
<body>
    <div class="container">
        <div class="icon"><?= $icon ?></div>
        <h1><?= $title ?></h1>
        <p class="subtitle">Strona w przygotowaniu</p>
        <div class="loader"></div>
        <p style="color: rgba(255,255,255,0.5); font-size: 14px;">
            Przepraszamy za utrudnienia. Pracujemy nad treścią tej podstrony.
        </p>
        <a href="index.php" class="back-link">← Powrót do strony głównej</a>
    </div>
</body>
</html>