<?php
require_once 'config.php';

$error = '';

if (isset($_GET['expired']) && $_GET['expired'] == 1) {
    $error = 'Sesi Anda telah berakhir (lebih dari 30 hari sejak login terakhir). Silakan login kembali!';
}

if (isLoggedIn()) {
    header('Location: index');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $query = "SELECT * FROM users WHERE username = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($user = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $user['password'])) {
            loginUser($conn, $user);

            header("Location: index");
            exit();
        }
        $error = 'Password salah!';
    } else {
        $error = 'Username tidak ditemukan!';
    }
}

$pageTitle = 'Login - Toko Rahmat Jaya';
$bodyClass = 'login-body';
require_once 'includes/head.php';
?>

<div class="min-h-screen flex items-center justify-center p-4 sm:p-6">
    <div class="login-card">
        <div class="login-logo"><?php $logoVariant = 'login'; require __DIR__ . '/includes/brand_logo.php'; ?></div>
        <h1 class="text-2xl font-extrabold text-center text-slate-800 tracking-tight">Toko Rahmat Jaya</h1>
        <p class="text-center text-sm text-slate-500 mt-2 mb-8">
            Masuk ke sistem kasir &amp; stok.<br>
            <span class="text-amber-700 font-medium">Perangkat ini tersimpan 30 hari</span> setelah login.
        </p>

        <form method="POST" action="" class="space-y-4">
            <div>
                <label class="app-label">Username</label>
                <input type="text" name="username" required autofocus class="app-input" placeholder="Masukkan username">
            </div>
            <div>
                <label class="app-label">Password</label>
                <input type="password" name="password" required class="app-input" placeholder="Masukkan password">
            </div>
            <button type="submit" class="btn btn-primary w-full py-3 text-base">
                <i class="ph ph-sign-in"></i> Masuk ke Aplikasi
            </button>
        </form>
    </div>
</div>

<?php require_once 'includes/flash.php'; ?>

<?php $withMain = false; require_once 'includes/footer.php'; ?>
