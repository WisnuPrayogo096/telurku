<?php
require_once 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

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

        if (empty($nama) || empty($username)) {
            $error = 'Nama dan Username tidak boleh kosong!';
        } else {
            $query_check = "SELECT id FROM users WHERE username = ? AND id != ?";
            $stmt_check = mysqli_prepare($conn, $query_check);
            mysqli_stmt_bind_param($stmt_check, "si", $username, $user_id);
            mysqli_stmt_execute($stmt_check);
            $result_check = mysqli_stmt_get_result($stmt_check);

            if (mysqli_num_rows($result_check) > 0) {
                $error = 'Username sudah digunakan!';
            } else {
                $query_update = "UPDATE users SET nama = ?, username = ? WHERE id = ?";
                $stmt_update = mysqli_prepare($conn, $query_update);
                mysqli_stmt_bind_param($stmt_update, "ssi", $nama, $username, $user_id);

                if (mysqli_stmt_execute($stmt_update)) {
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

        if (empty($password_lama) || empty($password_baru) || empty($password_konfirmasi)) {
            $error = 'Semua field password harus diisi!';
        } elseif (strlen($password_baru) < 6) {
            $error = 'Password baru minimal 6 karakter!';
        } elseif ($password_baru !== $password_konfirmasi) {
            $error = 'Konfirmasi password tidak sesuai!';
        } elseif (!password_verify($password_lama, $user['password'])) {
            $error = 'Password lama salah!';
        } else {
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
$pageTitle = 'Profil - Toko Rahmat Jaya';
require_once 'includes/head.php';
$navTitle = 'Profil Pengguna';
$navBackUrl = 'index';
require_once 'includes/navbar.php';
require_once 'includes/flash.php';
?>

<div class="app-container max-w-2xl">
    <div class="app-tabs mb-6">
        <button type="button" id="tab-profile" onclick="switchTab('profile')" class="app-tab active">
            <i class="ph ph-user"></i> Data Profil
        </button>
        <button type="button" id="tab-password" onclick="switchTab('password')" class="app-tab">
            <i class="ph ph-key"></i> Ubah Password
        </button>
    </div>

    <div id="profile-section" class="app-panel">
        <div class="app-panel-header">
            <span class="app-panel-title"><i class="ph ph-user-circle text-amber-600"></i> Data Profil</span>
        </div>
        <div class="app-panel-body">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_profile">
                <div>
                    <label class="app-label">Nama Lengkap</label>
                    <input type="text" name="nama" value="<?php echo htmlspecialchars($user['nama']); ?>" required class="app-input">
                </div>
                <div>
                    <label class="app-label">Username</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required class="app-input">
                </div>
                <button type="submit" class="btn btn-primary w-full py-3">
                    <i class="ph ph-floppy-disk"></i> Simpan Perubahan
                </button>
            </form>
        </div>
    </div>

    <div id="password-section" class="app-panel hidden mt-0">
        <div class="app-panel-header">
            <span class="app-panel-title"><i class="ph ph-lock text-amber-600"></i> Ubah Password</span>
        </div>
        <div class="app-panel-body">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_password">
                <div>
                    <label class="app-label">Password Lama</label>
                    <input type="password" name="password_lama" required class="app-input" placeholder="Password saat ini">
                </div>
                <div>
                    <label class="app-label">Password Baru</label>
                    <input type="password" name="password_baru" required class="app-input" placeholder="Min. 6 karakter">
                </div>
                <div>
                    <label class="app-label">Konfirmasi Password</label>
                    <input type="password" name="password_konfirmasi" required class="app-input" placeholder="Ulangi password baru">
                </div>
                <div class="app-alert app-alert-info">
                    <i class="ph ph-shield-check text-lg"></i>
                    <span>Gunakan password yang aman dan mudah diingat.</span>
                </div>
                <button type="submit" class="btn btn-primary w-full py-3">
                    <i class="ph ph-key"></i> Ubah Password
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.getElementById('profile-section').classList.toggle('hidden', tab !== 'profile');
    document.getElementById('password-section').classList.toggle('hidden', tab !== 'password');
    document.getElementById('tab-profile').classList.toggle('active', tab === 'profile');
    document.getElementById('tab-password').classList.toggle('active', tab === 'password');
}
</script>
<?php require_once 'includes/footer.php'; ?>
