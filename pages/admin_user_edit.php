<?php
if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || (string)($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: home');
    exit();
}

$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if ($user_id <= 0) {
    header('Location: admin');
    exit();
}

$sql = "SELECT user_id, first_name, last_name, username, email, role FROM users WHERE user_id = $user_id LIMIT 1";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) !== 1) {
    $_SESSION['admin_error'] = 'Użytkownik o podanym identyfikatorze nie istnieje.';
    header('Location: admin');
    exit();
}

$user = mysqli_fetch_assoc($result);
$errors = [];
$first_name = $user['first_name'];
$last_name = $user['last_name'];
$username = $user['username'];
$email = $user['email'];
$role = $user['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = mysqli_real_escape_string($conn, trim($_POST['first_name'] ?? ''));
    $last_name = mysqli_real_escape_string($conn, trim($_POST['last_name'] ?? ''));
    $username = mysqli_real_escape_string($conn, trim($_POST['username'] ?? ''));
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $role = mysqli_real_escape_string($conn, trim($_POST['role'] ?? 'user'));
    $password = $_POST['password'] ?? '';

    if ($first_name === '' || $last_name === '' || $email === '' || $role === '') {
        $errors[] = 'Imię, nazwisko, email i rola są wymagane.';
    }

    if (!in_array($role, ['user', 'admin'], true)) {
        $errors[] = 'Wybrano nieprawidłową rolę.';
    }

    if (empty($errors)) {
        $checkSql = "SELECT user_id FROM users WHERE email = '$email' AND user_id != $user_id LIMIT 1";
        $checkResult = mysqli_query($conn, $checkSql);
        if ($checkResult && mysqli_num_rows($checkResult) > 0) {
            $errors[] = 'Ten email jest już zajęty przez innego użytkownika.';
        }
    }

    if (empty($errors)) {
        if ($password !== '') {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $updateSql = "UPDATE users SET first_name = '$first_name', last_name = '$last_name', username = '$username', email = '$email', pass = '$hashedPassword', role = '$role' WHERE user_id = $user_id";
        } else {
            $updateSql = "UPDATE users SET first_name = '$first_name', last_name = '$last_name', username = '$username', email = '$email', role = '$role' WHERE user_id = $user_id";
        }

        if (mysqli_query($conn, $updateSql)) {
            $_SESSION['admin_message'] = 'Dane użytkownika zostały zaktualizowane.';
            header('Location: admin');
            exit();
        }

        $errors[] = 'Wystąpił błąd podczas aktualizacji użytkownika.';
    }
}
?>

<div class="admin-container">
    <div class="admin-header">
        <div>
            <h1>Edytuj użytkownika</h1>
            <p>Zmodyfikuj dane i rolę wybranego użytkownika.</p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="flash-message error">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="admin-form">
        <label for="first_name">Imię:</label>
        <input type="text" id="first_name" name="first_name" required value="<?= htmlspecialchars($first_name) ?>">

        <label for="last_name">Nazwisko:</label>
        <input type="text" id="last_name" name="last_name" required value="<?= htmlspecialchars($last_name) ?>">

        <label for="username">Nazwa użytkownika:</label>
        <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>">

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required value="<?= htmlspecialchars($email) ?>">

        <label for="password">Hasło (pozostaw puste, aby zachować stare):</label>
        <input type="password" id="password" name="password">

        <label for="role">Rola:</label>
        <select id="role" name="role" required>
            <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>Użytkownik</option>
            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator</option>
        </select>

        <div class="form-buttons">
            <button type="submit" class="button-primary">Zapisz zmiany</button>
            <a href="admin" class="button-secondary">Anuluj i wróć</a>
        </div>
    </form>
</div>
