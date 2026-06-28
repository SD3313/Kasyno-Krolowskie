<?php
if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || (string)($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: home');
    exit();
}

$errors = [];
$first_name = '';
$last_name = '';
$username = '';
$email = '';
$role = 'user';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = mysqli_real_escape_string($conn, trim($_POST['first_name'] ?? ''));
    $last_name = mysqli_real_escape_string($conn, trim($_POST['last_name'] ?? ''));
    $username = mysqli_real_escape_string($conn, trim($_POST['username'] ?? ''));
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $role = mysqli_real_escape_string($conn, trim($_POST['role'] ?? 'user'));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($first_name === '' || $last_name === '' || $email === '' || $password === '' || $confirm_password === '' || $role === '') {
        $errors[] = 'Wszystkie pola obowiązkowe muszą zostać uzupełnione.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Hasła muszą być identyczne.';
    }

    if (!in_array($role, ['user', 'admin'], true)) {
        $errors[] = 'Wybrano nieprawidłową rolę.';
    }

    if (empty($errors)) {
        $checkSql = "SELECT user_id FROM users WHERE email = '$email' LIMIT 1";
        $checkResult = mysqli_query($conn, $checkSql);

        if ($checkResult && mysqli_num_rows($checkResult) > 0) {
            $errors[] = 'Ten email już istnieje w bazie.';
        }
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $insertSql = "INSERT INTO users (first_name, last_name, username, email, pass, role, balance, registration_date) VALUES ('$first_name', '$last_name', '$username', '$email', '$hashedPassword', '$role', 1000, NOW())";

        if (mysqli_query($conn, $insertSql)) {
            $_SESSION['admin_message'] = 'Użytkownik został dodany.';
            header('Location: admin');
            exit();
        }

        $errors[] = 'Wystąpił błąd podczas zapisu użytkownika.';
    }
}
?>

<div class="admin-container">
    <div class="admin-header">
        <div>
            <h1>Dodaj użytkownika</h1>
            <p>Uzupełnij dane nowego użytkownika i przydziel mu rolę.</p>
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

        <label for="password">Hasło:</label>
        <input type="password" id="password" name="password" required>

        <label for="confirm_password">Powtórz hasło:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>

        <label for="role">Rola:</label>
        <select id="role" name="role" required>
            <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>Użytkownik</option>
            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator</option>
        </select>

        <div class="form-buttons">
            <button type="submit" class="button-primary">Dodaj użytkownika</button>
            <a href="admin" class="button-secondary">Anuluj i wróć</a>
        </div>
    </form>
</div>
