<?php
require_once 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Ambil data user saat ini
$query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action == 'update_profile') {
        $nama = $_POST['nama'] ?? '';
        $username = $_POST['username'] ?? '';

        // Validasi
        if (empty($nama) || empty($username)) {
            $error = 'Nama dan Username tidak boleh kosong!';
        } else {
            // Cek apakah username sudah digunakan user lain
            $query_check = "SELECT id FROM users WHERE username = ? AND id != ?";
            $stmt_check = mysqli_prepare($conn, $query_check);
            mysqli_stmt_bind_param($stmt_check, "si", $username, $user_id);
            mysqli_stmt_execute($stmt_check);
            $result_check = mysqli_stmt_get_result($stmt_check);

            if (mysqli_num_rows($result_check) > 0) {
                $error = 'Username sudah digunakan!';
            } else {
                // Update nama dan username
                $query_update = "UPDATE users SET nama = ?, username = ? WHERE id = ?";
                $stmt_update = mysqli_prepare($conn, $query_update);
                mysqli_stmt_bind_param($stmt_update, "ssi", $nama, $username, $user_id);

                if (mysqli_stmt_execute($stmt_update)) {
                    // Update session
                    $_SESSION['nama'] = $nama;
                    $_SESSION['username'] = $username;
                    $user['nama'] = $nama;
                    $user['username'] = $username;
                    $success = 'Profil berhasil diubah!';
                } else {
                    $error = 'Gagal mengubah profil!';
                }
            }
        }
    } elseif ($action == 'update_password') {
        $password_lama = $_POST['password_lama'] ?? '';
        $password_baru = $_POST['password_baru'] ?? '';
        $password_konfirmasi = $_POST['password_konfirmasi'] ?? '';

        // Validasi
        if (empty($password_lama) || empty($password_baru) || empty($password_konfirmasi)) {
            $error = 'Semua field password harus diisi!';
        } elseif (strlen($password_baru) < 6) {
            $error = 'Password baru minimal 6 karakter!';
        } elseif ($password_baru !== $password_konfirmasi) {
            $error = 'Konfirmasi password tidak sesuai!';
        } else {
            // Cek password lama
            if (!password_verify($password_lama, $user['password'])) {
                $error = 'Password lama salah!';
            } else {
                // Update password
                $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
                $query_update = "UPDATE users SET password = ? WHERE id = ?";
                $stmt_update = mysqli_prepare($conn, $query_update);
                mysqli_stmt_bind_param($stmt_update, "si", $password_hash, $user_id);

                if (mysqli_stmt_execute($stmt_update)) {
                    $success = 'Password berhasil diubah!';
                } else {
                    $error = 'Gagal mengubah password!';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Toko Rahmat Jaya</title>
    <link rel="icon" type="image/png" sizes="16x16" href="icons/16×16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icons/32×32.png">
    <link rel="icon" type="image/png" sizes="48x48" href="icons/48×48.png">
    <link rel="icon" type="image/png" sizes="192x192" href="icons/192×192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="icons/512×512.png">
    <link rel="apple-touch-icon" href="icons/180×180.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css">
</head>

<body class="bg-gray-100">
    <!-- Navbar -->
    <nav class="bg-blue-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center gap-4">
                <a href="index" class="hover:text-blue-200 transition">
                    <i class="ph ph-arrow-left text-xl"></i>
                </a>
                <h1 class="text-xl font-bold">Profil Pengguna</h1>
            </div>
            <span class="text-sm">Halo, <?php echo $_SESSION['nama']; ?></span>
        </div>
    </nav>

    <div class="container mx-auto p-4 max-w-2xl">
        <!-- Pesan Success -->
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="ph ph-check-circle inline-block mr-2"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Pesan Error -->
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="ph ph-warning-circle inline-block mr-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="flex gap-2 mb-6">
            <button type="button" id="tab-profile" onclick="switchTab('profile')"
                class="px-6 py-3 rounded-lg font-medium transition bg-blue-600 text-white tab-btn">
                <i class="ph ph-user inline-block mr-2"></i>Data Profil
            </button>
            <button type="button" id="tab-password" onclick="switchTab('password')"
                class="px-6 py-3 rounded-lg font-medium transition bg-gray-300 text-gray-700 tab-btn hover:bg-gray-400">
                <i class="ph ph-key inline-block mr-2"></i>Ubah Password
            </button>
        </div>

        <!-- Form Update Profil -->
        <div id="profile-section" class="tab-content bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Data Profil</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_profile">

                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-2">Nama Lengkap</label>
                    <input type="text" name="nama" value="<?php echo htmlspecialchars($user['nama']); ?>" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-2">Username</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">Role</label>
                    <input type="text" value="<?php echo $user['role'] == 'anak' ? 'Anak (Admin)' : 'Ibu (User)'; ?>" disabled
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                </div>

                <button type="submit" class="w-full bg-blue-600 text-white font-medium py-3 rounded-lg hover:bg-blue-700 transition">
                    <i class="ph ph-save inline-block mr-2"></i>Simpan Perubahan
                </button>
            </form>
        </div>

        <!-- Form Update Password -->
        <div id="password-section" class="tab-content bg-white rounded-lg shadow p-6 mb-6 hidden">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Ubah Password</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_password">

                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-2">Password Lama</label>
                    <input type="password" name="password_lama" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                        placeholder="Masukkan password saat ini">
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-2">Password Baru</label>
                    <input type="password" name="password_baru" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                        placeholder="Minimal 6 karakter">
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-2">Konfirmasi Password Baru</label>
                    <input type="password" name="password_konfirmasi" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                        placeholder="Ulangi password baru">
                </div>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <i class="ph ph-info inline-block mr-2 text-yellow-600"></i>
                    <span class="text-sm text-yellow-800">Pastikan password baru Anda aman dan mudah diingat.</span>
                </div>

                <button type="submit" class="w-full bg-blue-600 text-white font-medium py-3 rounded-lg hover:bg-blue-700 transition">
                    <i class="ph ph-key inline-block mr-2"></i>Ubah Password
                </button>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('bg-blue-600', 'text-white');
                el.classList.add('bg-gray-300', 'text-gray-700', 'hover:bg-gray-400');
            });

            // Show selected tab
            if (tab === 'profile') {
                document.getElementById('profile-section').classList.remove('hidden');
                document.getElementById('tab-profile').classList.add('bg-blue-600', 'text-white');
                document.getElementById('tab-profile').classList.remove('bg-gray-300', 'text-gray-700', 'hover:bg-gray-400');
            } else {
                document.getElementById('password-section').classList.remove('hidden');
                document.getElementById('tab-password').classList.add('bg-blue-600', 'text-white');
                document.getElementById('tab-password').classList.remove('bg-gray-300', 'text-gray-700', 'hover:bg-gray-400');
            }
        }
    </script>
</body>

</html>