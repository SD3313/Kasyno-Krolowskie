<?php
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login');
    exit;
}


$user     = $_SESSION['user']         ?? 'Gracz';
$role     = $_SESSION['role']         ?? 'gracz';
$user_id  = $_SESSION['user_id']      ?? 0;
$balance  = $_SESSION['user_balance'] ?? 0;
$username = $_SESSION['username']     ?? '';
$email    = $_SESSION['email']        ?? '';
$pic      = $_SESSION['profile_pic']  ?? '';

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
        <a href="profil"   class="nav-item active">
            <span class="icon">👤</span> Profil
        </a>
        <a href="saldo"    class="nav-item ">
            <span class="icon">💰</span> Saldo
        </a>
        <a href="znajomi" class="nav-item ">
            <span class="icon">🤝</span> Znajomi
        </a>
        <a href="historia" class="nav-item ">
            <span class="icon">📋</span> Historia
        </a>
    </nav>

    <div class="sidebar-balance">
        <small>Twoje saldo</small>
        <strong><?= number_format($balance, 0, ',', ' ') ?> żetonów</strong>
    </div>

    <a href="home" class="sidebar-back">← Wróć do gier</a>
</div>

<div class="profile-main">
    <div class="page-header">
        <h1>Twój profil</h1>
        <p>Dane konta i informacje o graczu</p>
    </div>

    <div class="stat-grid">
        <div class="stat-card accent-gold">
            <small>Saldo</small>
            <span class="val"><?= number_format($balance, 0, ',', ' ') ?> <small style="font-size:.7rem;color:var(--muted)">żetonów</small></span>
        </div>
    <div class="stat-card accent-green">
        <small>ID gracza</small>
        <span class="val">#<?= (int)$user_id ?></span>
            </div>
            <div class="stat-card">
                <small>Rola</small>
                <span class="val" style="font-size:1.1rem"><?= htmlspecialchars($role) ?></span>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Dane konta</div>

            <div class="field-row">
                <div class="field-label">Imię i nazwisko</div>
                <div class="field-value"><?= htmlspecialchars($user) ?></div>
            </div>
            <div class="field-row">
                <div class="field-label">Nazwa użytkownika</div>
                <div class="field-value"><?= htmlspecialchars($username) ?></div>
            </div>
            <div class="field-row">
                <div class="field-label">E-mail</div>
                <div class="field-value"><?= htmlspecialchars($email ?: '—') ?></div>
            </div>
            <div class="field-row">
                <div class="field-label">ID użytkownika</div>
                <div class="field-value">#<?= (int)$user_id ?></div>
            </div>
            <div class="field-row">
                <div class="field-label">Rola</div>
                <div class="field-value">
                    <?php
                    $roleClass = match(strtolower($role)) {
                        'admin'  => 'badge-gold',
                        'vip'    => 'badge-blue',
                        default  => 'badge-green',
                    };
                    ?>
                    <span class="badge <?= $roleClass ?>"><?= htmlspecialchars($role) ?></span>
                </div>
            </div>
            <div class="field-row">
                <div class="field-label">Status</div>
                <div class="field-value"><span class="badge badge-green">Aktywny</span></div>
            </div>
        </div>
        
        <a  href="logout">
            <div class="logout-section">
                <p>Wyloguj się</p>
            </div>
        </a>
    </div>
</div>