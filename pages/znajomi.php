<?php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
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
foreach ($parts as $p) {
    $initials .= strtoupper(mb_substr($p, 0, 1));
}
$initials = mb_substr($initials, 0, 2);

$message = '';
$error = '';

function escape($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$user_today_games = 0;
$todaySql = "SELECT COUNT(*) AS total FROM game_history WHERE user_id = ? AND DATE(played_at) = CURDATE()";
if ($stmt = mysqli_prepare($conn, $todaySql)) {
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $user_today_games);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_friend_request') {
        $friend_email = trim((string)($_POST['friend_email'] ?? ''));
        $friend_email = preg_replace('/\s+/u', '', $friend_email);

        if ($friend_email === '') {
            $error = 'Wpisz adres e-mail znajomego.';
        } elseif ($friend_email === $email) {
            $error = 'Nie możesz zaprosić siebie.';
        } else {
            $selectSql = 'SELECT user_id, first_name, last_name, username FROM users WHERE email = ? LIMIT 1';
            $target = null;
            if ($stmt = mysqli_prepare($conn, $selectSql)) {
                mysqli_stmt_bind_param($stmt, 's', $friend_email);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $target_id, $target_first_name, $target_last_name, $target_username);
                if (mysqli_stmt_fetch($stmt)) {
                    $target = [
                        'user_id' => $target_id,
                        'display_name' => trim($target_username !== '' ? $target_username : $target_first_name . ' ' . $target_last_name),
                    ];
                }
                mysqli_stmt_close($stmt);
            }

            if (!$target) {
                $error = 'Użytkownik o takim adresie e-mail nie istnieje.';
            } else {
                $friend_id = (int)$target['user_id'];
                $friend_id = max(0, $friend_id);

                $friendExistsSql = "SELECT 1 FROM friendships WHERE (user_one = ? AND user_two = ?) OR (user_one = ? AND user_two = ?) LIMIT 1";
                $pendingExistsSql = "SELECT 1 FROM friend_requests WHERE ((from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)) AND status = 'pending' LIMIT 1";

                if ($stmt = mysqli_prepare($conn, $friendExistsSql)) {
                    $a = min($user_id, $friend_id);
                    $b = max($user_id, $friend_id);
                    mysqli_stmt_bind_param($stmt, 'iiii', $a, $b, $b, $a);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_store_result($stmt);
                    if (mysqli_stmt_num_rows($stmt) > 0) {
                        $error = 'Jesteście już znajomymi.';
                    }
                    mysqli_stmt_close($stmt);
                }

                if ($error === '') {
                    if ($stmt = mysqli_prepare($conn, $pendingExistsSql)) {
                        mysqli_stmt_bind_param($stmt, 'iiii', $user_id, $friend_id, $friend_id, $user_id);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_store_result($stmt);
                        if (mysqli_stmt_num_rows($stmt) > 0) {
                            $error = 'Zaproszenie już istnieje lub jest w trakcie oczekiwania.';
                        }
                        mysqli_stmt_close($stmt);
                    }
                }

                if ($error === '') {
                    $insertSql = "INSERT INTO friend_requests (from_user_id, to_user_id, status, created_at) VALUES (?, ?, 'pending', NOW())";
                    if ($stmt = mysqli_prepare($conn, $insertSql)) {
                        mysqli_stmt_bind_param($stmt, 'ii', $user_id, $friend_id);
                        if (mysqli_stmt_execute($stmt)) {
                            $message = 'Zaproszenie zostało wysłane.';
                        } else {
                            $error = 'Nie udało się wysłać zaproszenia. Spróbuj ponownie.';
                        }
                        mysqli_stmt_close($stmt);
                    }
                }
            }
        }
    }

    if ($action === 'respond_friend_request') {
        $request_id = (int)($_POST['request_id'] ?? 0);
        $response   = ($_POST['response'] ?? '') === 'accept' ? 'accepted' : 'rejected';

        if ($request_id <= 0) {
            $error = 'Nieprawidłowe zaproszenie.';
        } else {
            $request_id = intval($request_id);
            $checkSql = "SELECT from_user_id, to_user_id FROM friend_requests WHERE id = ? AND to_user_id = ? AND status = 'pending' LIMIT 1";
            $request = null;
            if ($stmt = mysqli_prepare($conn, $checkSql)) {
                mysqli_stmt_bind_param($stmt, 'ii', $request_id, $user_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $from_user_id, $to_user_id);
                if (mysqli_stmt_fetch($stmt)) {
                    $request = [
                        'from_user_id' => $from_user_id,
                        'to_user_id' => $to_user_id,
                    ];
                }
                mysqli_stmt_close($stmt);
            }

            if (!$request) {
                $error = 'Zaproszenie nie zostało odnalezione lub zostało już obsłużone.';
            } else {
                $updateSql = "UPDATE friend_requests SET status = ?, responded_at = NOW() WHERE id = ?";
                if ($stmt = mysqli_prepare($conn, $updateSql)) {
                    mysqli_stmt_bind_param($stmt, 'si', $response, $request_id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }

                if ($response === 'accepted') {
                    $a = min($user_id, $request['from_user_id']);
                    $b = max($user_id, $request['from_user_id']);
                    $friendInsertSql = "INSERT IGNORE INTO friendships (user_one, user_two, created_at) VALUES (?, ?, NOW())";
                    if ($stmt = mysqli_prepare($conn, $friendInsertSql)) {
                        mysqli_stmt_bind_param($stmt, 'ii', $a, $b);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    }
                    $message = 'Zaproszenie zostało zaakceptowane.';
                } else {
                    $message = 'Zaproszenie zostało odrzucone.';
                }
            }
        }
    }
}

$incomingRequests = [];
$incomingSql = "SELECT fr.id, u.first_name, u.last_name, u.username, u.email, fr.created_at FROM friend_requests fr JOIN users u ON u.user_id = fr.from_user_id WHERE fr.to_user_id = ? AND fr.status = 'pending' ORDER BY fr.created_at DESC";
if ($stmt = mysqli_prepare($conn, $incomingSql)) {
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $req_id, $req_first_name, $req_last_name, $req_username, $req_email, $req_created_at);
    while (mysqli_stmt_fetch($stmt)) {
        $incomingRequests[] = [
            'id' => $req_id,
            'name' => trim($req_username !== '' ? $req_username : $req_first_name . ' ' . $req_last_name),
            'email' => $req_email,
            'created_at' => $req_created_at,
        ];
    }
    mysqli_stmt_close($stmt);
}

$sentRequests = [];
$sentSql = "SELECT fr.id, u.first_name, u.last_name, u.username, u.email, fr.created_at FROM friend_requests fr JOIN users u ON u.user_id = fr.to_user_id WHERE fr.from_user_id = ? AND fr.status = 'pending' ORDER BY fr.created_at DESC";
if ($stmt = mysqli_prepare($conn, $sentSql)) {
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $req_id, $req_first_name, $req_last_name, $req_username, $req_email, $req_created_at);
    while (mysqli_stmt_fetch($stmt)) {
        $sentRequests[] = [
            'id' => $req_id,
            'name' => trim($req_username !== '' ? $req_username : $req_first_name . ' ' . $req_last_name),
            'email' => $req_email,
            'created_at' => $req_created_at,
        ];
    }
    mysqli_stmt_close($stmt);
}

$friends = [];
$friendsSql = "SELECT u.user_id, u.first_name, u.last_name, u.username, u.balance, COALESCE(SUM(CASE WHEN DATE(gh.played_at) = CURDATE() THEN 1 ELSE 0 END), 0) AS today_games FROM friendships f JOIN users u ON (f.user_one = ? AND u.user_id = f.user_two) OR (f.user_two = ? AND u.user_id = f.user_one) LEFT JOIN game_history gh ON gh.user_id = u.user_id GROUP BY u.user_id ORDER BY u.first_name, u.last_name";
if ($stmt = mysqli_prepare($conn, $friendsSql)) {
    mysqli_stmt_bind_param($stmt, 'ii', $user_id, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $friend_id, $friend_first_name, $friend_last_name, $friend_username, $friend_balance, $friend_today_games);
    while (mysqli_stmt_fetch($stmt)) {
        $friends[] = [
            'id' => $friend_id,
            'name' => trim($friend_username !== '' ? $friend_username : $friend_first_name . ' ' . $friend_last_name),
            'balance' => $friend_balance,
            'today_games' => $friend_today_games,
        ];
    }
    mysqli_stmt_close($stmt);
}
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
                <img src="<?= escape($pic) ?>" alt="avatar">
            <?php else: ?>
                <?= escape($initials) ?>
            <?php endif; ?>
        </div>
        <?php if (isset($username) && !empty($username)): ?>
            <div class="sidebar-name"><?= escape($username) ?></div>
        <?php else: ?>
            <div class="sidebar-name"><?= escape($user) ?></div>
        <?php endif; ?>
        
    </div>

    <nav class="sidebar-nav">
        <a href="profil"   class="nav-item ">
            <span class="icon">👤</span> Profil
        </a>
        <a href="saldo"    class="nav-item ">
            <span class="icon">💰</span> Saldo
        </a>
        <a href="znajomi" class="nav-item active">
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
<div class="znajomi-wrapper">
    <div class="friends-main">
        <div class="friends-panel">
            <div class="card">
                <div class="card-title">Dodaj znajomego</div>

                <?php if ($message): ?>
                    <div class="friend-message friend-message--success"><?= escape($message) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="friend-message friend-message--error"><?= escape($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="znajomi" class="friend-form">
                    <input type="hidden" name="action" value="send_friend_request">
                    <label for="friend_email">Wpisz e-mail znajomego</label>
                    <input type="email" id="friend_email" name="friend_email" placeholder="np. przyklad@mail.pl" required>
                    <button type="submit" class="btn btn-green">Wyślij zaproszenie</button>
                </form>
            </div>

            <div class="card request-card">
                <div class="card-title">Oczekujące zaproszenia</div>
                <?php if (count($incomingRequests) === 0): ?>
                    <div class="empty-state">
                        <div class="icon">🤝</div>
                        <p>Brak nowych zaproszeń.</p>
                    </div>
                <?php else: ?>
                    <div class="request-list">
                        <?php foreach ($incomingRequests as $request): ?>
                            <div class="request-item">
                                <div>
                                    <strong><?= escape($request['name']) ?></strong>
                                    <div class="request-meta">E-mail: <?= escape($request['email']) ?></div>
                                    <div class="request-meta">Wysłano: <?= escape($request['created_at']) ?></div>
                                </div>
                                <div class="request-actions">
                                    <form method="POST" action="" class="inline-form">
                                        <input type="hidden" name="action" value="respond_friend_request">
                                        <input type="hidden" name="request_id" value="<?= intval($request['id']) ?>">
                                        <input type="hidden" name="response" value="accept">
                                        <button type="submit" class="btn btn-green">Zaakceptuj</button>
                                    </form>
                                    <form method="POST" action="" class="inline-form">
                                        <input type="hidden" name="action" value="respond_friend_request">
                                        <input type="hidden" name="request_id" value="<?= intval($request['id']) ?>">
                                        <input type="hidden" name="response" value="reject">
                                        <button type="submit" class="btn btn-outline">Odrzuć</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (count($sentRequests) > 0): ?>
                <div class="card request-card">
                    <div class="card-title">Wysłane zaproszenia</div>
                    <div class="request-list">
                        <?php foreach ($sentRequests as $request): ?>
                            <div class="request-item request-item--sent">
                                <div>
                                    <strong><?= escape($request['name']) ?></strong>
                                    <div class="request-meta">E-mail: <?= escape($request['email']) ?></div>
                                    <div class="request-meta">Wysłano: <?= escape($request['created_at']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="friends-list-card card">
            <div class="card-title">Lista znajomych</div>
            <?php if (count($friends) === 0): ?>
                <div class="empty-state">
                    <div class="icon">👥</div>
                    <p>Nie masz jeszcze żadnych znajomych.</p>
                    <small>Wyślij zaproszenie, aby dodać pierwszą osobę.</small>
                </div>
            <?php else: ?>
                <table class="friends-table">
                    <thead>
                        <tr>
                            <th>Znajomy</th>
                            <th>Gry dziś (Ty / On)</th>
                            <th>Bilans (Ty / On)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($friends as $friend): ?>
                            <tr>
                                <td><?= escape($friend['name']) ?></td>
                                <td><?= escape($user_today_games) ?> / <?= escape($friend['today_games']) ?></td>
                                <td><?= number_format($balance, 0, ',', ' ') ?> / <?= number_format($friend['balance'], 0, ',', ' ') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>