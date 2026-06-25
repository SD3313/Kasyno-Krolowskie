<?php
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit;
}

$active = isset($_GET['section']) ? $_GET['section'] : 'profil';
$allowed = ['profil', 'saldo', 'znajomi', 'historia'];
if (!in_array($active, $allowed)) $active = 'profil';

$user     = $_SESSION['user']         ?? 'Gracz';
$role     = $_SESSION['role']         ?? 'gracz';
$user_id  = $_SESSION['user_id']      ?? 0;
$balance  = $_SESSION['user_balance'] ?? 0;
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
        <div class="sidebar-name"><?= htmlspecialchars($user) ?></div>
        <div class="sidebar-role"><?= htmlspecialchars($role) ?></div>
    </div>

    <nav class="sidebar-nav">
        <a href="profil"   class="nav-item <?= $active==='profil'   ? 'active' : '' ?>">
            <span class="icon">👤</span> Profil
        </a>
        <a href="saldo"    class="nav-item <?= $active==='saldo'    ? 'active' : '' ?>">
            <span class="icon">💰</span> Saldo
        </a>
        <a href="znajomi" class="nav-item <?= $active==='znajomi'  ? 'active' : '' ?>">
            <span class="icon">🤝</span> Znajomi
        </a>
        <a href="historia" class="nav-item <?= $active==='historia' ? 'active' : '' ?>">
            <span class="icon">📋</span> Historia
        </a>
    </nav>

    <div class="sidebar-balance">
        <small>Twoje saldo</small>
        <strong><?= number_format($balance, 0, ',', ' ') ?> żetonów</strong>
    </div>

    <a href="index.php" class="sidebar-back">← Wróć do gier</a>
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