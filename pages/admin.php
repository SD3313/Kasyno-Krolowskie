<?php
if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || (string)($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: home');
    exit();
}

$message = $_SESSION['admin_message'] ?? '';
$error = $_SESSION['admin_error'] ?? '';
unset($_SESSION['admin_message'], $_SESSION['admin_error']);

$sql = "SELECT user_id, first_name, last_name, username, email, role FROM users ORDER BY last_name ASC";
$result = mysqli_query($conn, $sql);

$users = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
}
?>

<div class="admin-container">
    <div class="admin-header">
        <div>
            <h1>Panel administratora</h1>
            <p>Zarządzanie użytkownikami kasyna</p>
        </div>
        <a href="admin_user_add" class="admin-add-button">Dodaj nowego użytkownika</a>
    </div>

    <?php if ($message): ?>
        <div class="flash-message success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="flash-message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Nazwisko</th>
                <th>Imię</th>
                <th>Nazwa użytkownika</th>
                <th>Email</th>
                <th>Rola</th>
                <th>Akcje</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="6">Brak użytkowników w bazie.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['last_name']) ?></td>
                        <td><?= htmlspecialchars($user['first_name']) ?></td>
                        <td><?= htmlspecialchars($user['username'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['role']) ?></td>
                        <td class="admin-actions-cell">
                            <a href="admin_user_edit?user_id=<?= urlencode($user['user_id']) ?>">Edytuj</a>
                            <a href="admin_user_delete?user_id=<?= urlencode($user['user_id']) ?>" class="delete-user-link">Usuń</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    document.querySelectorAll('.delete-user-link').forEach(link => {
        link.addEventListener('click', function (event) {
            if (!confirm('Czy na pewno chcesz usunąć tego użytkownika?')) {
                event.preventDefault();
            }
        });
    });
</script>
