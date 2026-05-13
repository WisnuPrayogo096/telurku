<?php
require_once 'config.php';

// Clear last_login dari database saat logout
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $update_query = "UPDATE users SET last_login = NULL WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, "i", $user_id);
    mysqli_stmt_execute($update_stmt);
}

session_destroy();
header("Location: login");
exit();
