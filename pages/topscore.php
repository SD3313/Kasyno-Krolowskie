<?php
// Pobiera 5 największych dzisiejszych wygranych z bazy danych
$topscores = [];
$sql = "SELECT gh.win AS wynik, gh.game, COALESCE(NULLIF(u.username, ''), CONCAT(u.first_name, ' ', u.last_name), 'Gracz') AS username
        FROM game_history gh
        LEFT JOIN users u ON gh.user_id = u.user_id
        WHERE gh.win > 0
          AND DATE(gh.played_at) = CURDATE()
        ORDER BY gh.win DESC
        LIMIT 5";

$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $topscores[] = $row;
    }
}
?>

<div class="topscore-bar">
    <div class="topscore-header">
        <h2>TOP 5 dzisiejszych wygranych</h2>
    </div>
    <div class="topscore-list">
        <?php if (count($topscores) === 0): ?>
            <div class="topscore-empty">Brak dzisiejszych wygranych do wyświetlenia.</div>
        <?php else: ?>
            <div class="topscore-row topscore-row--head">
                <span>miejsce</span>
                <span>wygrana</span>
                <span>gra</span>
                <span>username</span>
            </div>
            <?php foreach ($topscores as $index => $row): ?>
                <div class="topscore-row<?= $index === 0 ? ' topscore-row--first' : '' ?>">
                    <span><?= $index + 1 ?></span>
                    <span><?= number_format((float) $row['wynik'], 0, ',', ' ') ?></span>
                    <span><?= htmlspecialchars($row['game']) ?></span>
                    <span><?= htmlspecialchars($row['username']) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
