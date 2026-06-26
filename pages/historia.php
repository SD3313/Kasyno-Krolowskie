<?php
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$user     = $_SESSION['user']         ?? 'Gracz';
$role     = $_SESSION['role']         ?? 'gracz';
$user_id  = $_SESSION['user_id']      ?? 0;
$balance  = $_SESSION['user_balance'] ?? 0;
$email    = $_SESSION['email']        ?? '';
$username = $_SESSION['username']     ?? '';
$pic      = $_SESSION['profile_pic']  ?? '';


$sql    = "SELECT game, bet, win, balance_after, played_at FROM game_history WHERE user_id = '$user_id' ORDER BY played_at DESC";
$result = mysqli_query($conn, $sql);
$rows   = mysqli_fetch_all($result, MYSQLI_ASSOC);

$initials = '';
$parts = explode(' ', $user);
foreach ($parts as $p) $initials .= strtoupper(mb_substr($p, 0, 1));
$initials = mb_substr($initials, 0, 2);
?>
<div class="profile-container">
<div class="sidebar">
    <div class="sidebar-logo">
        <span>🎲 Spróbuj szczęścia</span>
        <small>Panel gracza</small>
    </div>

    <div class="sidebar-avatar">
        <div class="avatar-circle">
            <?php if ($pic): ?>
                <img src="<?= htmlspecialchars($pic) ?>" alt="avatar">
            <?php else: ?>
                <?= htmlspecialchars($initials) ?>
            <?php endif; ?>
        </div>
        <?php if (isset($username) && !empty($username)): ?>
            <div class="sidebar-name"><?= htmlspecialchars($username) ?></div>
        <?php else: ?>
            <div class="sidebar-name"><?= htmlspecialchars($user) ?></div>
        <?php endif; ?>
        <div class="sidebar-role"><?= htmlspecialchars($role) ?></div>
    </div>

    <nav class="sidebar-nav">
        <a href="profil"   class="nav-item ">
            <span class="icon">👤</span> Profil
        </a>
        <a href="saldo"    class="nav-item ">
            <span class="icon">💰</span> Saldo
        </a>
        <a href="znajomi" class="nav-item ">
            <span class="icon">🤝</span> Znajomi
        </a>
        <a href="historia" class="nav-item active">
            <span class="icon">📋</span> Historia
        </a>
    </nav>

    <div class="sidebar-balance">
        <small>Twoje saldo</small>
        <strong><?= number_format($balance, 0, ',', ' ') ?> żetonów</strong>
    </div>

    <a href="home" class="sidebar-back">← Wróć do gier</a>
</div>
<div class="historia-wrapper">

    <div class="historia-header">
        <span class="historia-title">Historia gier</span>
        <span class="historia-count"><?= count($rows) ?> rozgrywek</span>
    </div>

    <?php if (empty($rows)): ?>
        <div class="historia-empty">
            <div class="historia-empty-icon">🎲</div>
            <p>Brak rozgrywek w historii.</p>
            <small>Zagraj w jedną z naszych gier, aby zobaczyć wyniki tutaj.</small>
        </div>

    <?php else: ?>
        <div class="historia-list">
            <?php foreach ($rows as $r):
                $win      = (float) $r['win'];
                $bet      = (float) $r['bet'];
                $balance  = (float) $r['balance_after'];
                $isWin    = $win >= 0;
                $sign     = $isWin ? '+' : '';
                $tileClass = $isWin ? 'tile-win' : 'tile-lose';

                $dt   = new DateTime($r['played_at']);
                $date = $dt->format('d.m.Y');
                $time = $dt->format('H:i');
            ?>
            <div class="historia-tile <?= $tileClass ?>">

                <div class="tile-game">
                    <span class="tile-game-name"><?= htmlspecialchars($r['game']) ?></span>
                    <span class="tile-datetime">
                        <span><?= $date ?></span>
                        <span class="tile-time"><?= $time ?></span>
                    </span>
                </div>

                <div class="tile-stats">
                    <div class="tile-stat">
                        <span class="tile-stat-label">Stawka</span>
                        <span class="tile-stat-val"><?= number_format($bet, 0, ',', ' ') ?> </span>
                    </div>
                    <div class="tile-stat">
                        <span class="tile-stat-label">Wynik</span>
                        <span class="tile-stat-val tile-result <?= $tileClass ?>">
                            <?= $sign . number_format($win, 0, ',', ' ') ?> 
                        </span>
                    </div>
                    <div class="tile-stat">
                        <span class="tile-stat-label">Saldo po</span>
                        <span class="tile-stat-val"><?= number_format($balance, 0, ',', ' ') ?> </span>
                    </div>
                </div>

                <div class="tile-indicator <?= $tileClass ?>">
                    <?= $isWin ? '▲ Wygrana' : '▼ Przegrana' ?>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var list  = document.querySelector('.historia-list');
    if (!list) return;
    var tiles = list.querySelectorAll('.historia-tile');
    if (tiles.length < 8) return;
    var gap = 10;
    var total = 0;
    for (var i = 0; i < 8; i++) {
        total += tiles[i].getBoundingClientRect().height;
    }
    list.style.maxHeight = (total + 7 * gap) + 'px';
});
</script>