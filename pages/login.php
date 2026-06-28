<?php
require_once __DIR__ . '/../db_connect.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $haslo = $_POST['haslo'] ?? '';

    if ($email === '' || $haslo === '') {
        $errors[] = 'Wprowadź email i hasło.';
    } else {
        $sql = "SELECT * FROM users WHERE email = '$email'";
        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) === 1) {
            $row = mysqli_fetch_assoc($result);
            if (password_verify($haslo, $row['pass'])) {
                $role = (string) $row['role'];

                $_SESSION['user'] = trim($row['first_name'] . ' ' . $row['last_name']);
                $_SESSION['role'] = $role;
                $_SESSION['user_id'] = (int) $row['user_id'];
                $_SESSION['user_balance'] = (int) $row['balance'];
                $_SESSION['logged_in'] = true;
                $_SESSION['profile_pic'] = $row['profile_pic'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['email'] = $email;

                header('Location: home');
                exit();
            }

            $errors[] = 'Błędne hasło.';
        } else {
            $errors[] = 'Użytkownik o takim emailu nie istnieje.';
        }
    }
}
?>

<div class="login-container">
    <h1> Logowanie </h1>
    <p>Witaj w Kasynie Królewskim! Zaloguj się, aby kontynuować.</p>

    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $error): ?>
            <p style="color:red;"><?= htmlspecialchars($error) ?></p>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['login_message'])): ?>
        <p style="color:green;"><?= htmlspecialchars($_SESSION['login_message']) ?></p>
        <?php unset($_SESSION['login_message']); ?>
    <?php endif; ?>

    <form action="login" method="post">
        <label for="email">Wpisz adres e-mail:</label><br>
        <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        <br>
        <label for="haslo">Wpisz hasło :</label><br>
        <input type="password" id="haslo" name="haslo" required>
        <br>
        <input type="submit" value="Zaloguj się">
        <br>
    </form>
    <a href="register" class="register-link">Nie masz konta? Zarejestruj się!</a>
</div>