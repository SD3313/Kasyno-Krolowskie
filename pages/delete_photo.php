<?php
if (!isset($_SESSION['user'])) {
    header("Location: login");
    exit();
}
$user_id = $_SESSION["user_id"];


if (isset($_POST['id'])) {

    $id = (int)$_POST['id'];
    $result = mysqli_query($conn, "SELECT profile_pic FROM users WHERE user_id=$id");
    $row = mysqli_fetch_assoc($result); 
    if ($row) {
        $file = $row['profile_pic']; 
        echo $file;
        $null = NULL;
        $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE user_id = ?");
        $stmt->bind_param("si", $null, $user_id);
        $stmt->execute();
        $stmt->close();
        if (file_exists($file)) {
            unlink($file);
        }
        $_SESSION['profile_pic'] = '';
    }
}

if ($_SESSION['role'] == 1) {
        header("Location: admin");
        exit();
}
else {
        header("Location: profil");
        exit();
}

?>