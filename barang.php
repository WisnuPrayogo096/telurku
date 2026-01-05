<?php
require_once 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$success = '';
$error = '';

// Proses Tambah/Edit Barang
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_barang = $_POST['nama_barang'];
    $harga_beli = $_POST['harga_beli'];
    $harga_jual = $_POST['harga_jual'];
    $stok = $_POST['stok'];
    $owner_id = $_POST['owner_id'];

    // Cek permission
    if (!checkPermission($owner_id)) {
        $error = 'Anda tidak memiliki izin untuk ini!';
    } else {
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // Update
            $id = $_POST['id'];
            $query = "UPDATE barang SET nama_barang=?, harga_beli=?, harga_jual=?, stok=?, owner_id=? WHERE id=?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sdddii", $nama_barang, $harga_beli, $harga_jual, $stok, $owner_id, $id);
        } else {
            // Insert
            $query = "INSERT INTO barang (nama_barang, harga_beli, harga_jual, stok, owner_id) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sdddi", $nama_barang, $harga_beli, $harga_jual, $stok, $owner_id);
        }

        if (mysqli_stmt_execute($stmt)) {
            $success = 'Data berhasil disimpan!';
        } else {
            $error = 'Gagal menyimpan data!';
        }
    }
}

// Proses Hapus
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    // Cek ownership
    $check = mysqli_query($conn, "SELECT owner_id FROM barang WHERE id=$id");
    $barang = mysqli_fetch_assoc($check);

    if (checkPermission($barang['owner_id'])) {
        mysqli_query($conn, "DELETE FROM barang WHERE id=$id");
        $success = 'Data berhasil dihapus!';
    } else {
        $error = 'Anda tidak memiliki izin untuk menghapus!';
    }
}

// Ambil data barang
if ($role == 'anak') {
    $query = "SELECT b.*, u.nama as owner_nama FROM barang b JOIN users u ON b.owner_id = u.id ORDER BY b.id DESC";
} else {
    $query = "SELECT b.*, u.nama as owner_nama FROM barang b JOIN users u ON b.owner_id = u.id WHERE b.owner_id = $user_id ORDER BY b.id DESC";
}
$result = mysqli_query($conn, $query);

// Data untuk edit
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $edit_query = mysqli_query($conn, "SELECT * FROM barang WHERE id=$edit_id");
    $edit_data = mysqli_fetch_assoc($edit_query);
}

// Ambil list users untuk dropdown owner
$users_query = mysqli_query($conn, "SELECT id, nama FROM users");
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Barang - TELURKU</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <nav class="bg-blue-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Data Barang</h1>
            <a href="index" class="bg-blue-700 px-4 py-2 rounded hover:bg-blue-800">Kembali</a>
        </div>
    </nav>

    <div class="container mx-auto p-4">
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Form Tambah/Edit -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold mb-4"><?php echo $edit_data ? 'Edit' : 'Tambah'; ?> Barang</h2>
            <form method="POST" action="">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Nama Barang</label>
                        <input type="text" name="nama_barang" required
                            value="<?php echo $edit_data['nama_barang'] ?? ''; ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Pemilik Stok</label>
                        <select name="owner_id" required
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            <?php while ($user = mysqli_fetch_assoc($users_query)): ?>
                                <option value="<?php echo $user['id']; ?>"
                                    <?php echo (isset($edit_data) && $edit_data['owner_id'] == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo $user['nama']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Harga Beli</label>
                        <input type="number" name="harga_beli" required step="0.01"
                            value="<?php echo $edit_data['harga_beli'] ?? ''; ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Harga Jual</label>
                        <input type="number" name="harga_jual" required step="0.01"
                            value="<?php echo $edit_data['harga_jual'] ?? ''; ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Stok</label>
                        <input type="number" name="stok" required
                            value="<?php echo $edit_data['stok'] ?? '0'; ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                </div>

                <div class="mt-4 flex gap-2">
                    <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                        <?php echo $edit_data ? 'Update' : 'Simpan'; ?>
                    </button>
                    <?php if ($edit_data): ?>
                        <a href="barang" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabel Data -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left">No</th>
                            <th class="px-4 py-3 text-left">Nama Barang</th>
                            <th class="px-4 py-3 text-left">Pemilik</th>
                            <th class="px-4 py-3 text-right">Harga Beli</th>
                            <th class="px-4 py-3 text-right">Harga Jual</th>
                            <th class="px-4 py-3 text-center">Stok</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        while ($row = mysqli_fetch_assoc($result)):
                        ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3"><?php echo $no++; ?></td>
                                <td class="px-4 py-3"><?php echo $row['nama_barang']; ?></td>
                                <td class="px-4 py-3"><?php echo $row['owner_nama']; ?></td>
                                <td class="px-4 py-3 text-right"><?php echo formatRupiah($row['harga_beli']); ?></td>
                                <td class="px-4 py-3 text-right"><?php echo formatRupiah($row['harga_jual']); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="<?php echo $row['stok'] < 10 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?> px-3 py-1 rounded-full text-sm font-medium">
                                        <?php echo $row['stok']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if (checkPermission($row['owner_id'])): ?>
                                        <a href="?edit=<?php echo $row['id']; ?>" class="text-blue-600 hover:underline mr-2">Edit</a>
                                        <a href="?delete=<?php echo $row['id']; ?>"
                                            onclick="return confirm('Yakin ingin menghapus?')"
                                            class="text-red-600 hover:underline">Hapus</a>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>