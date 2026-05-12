<?php
require_once 'config.php';
requireLogin();

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$success = '';
$error = '';

// Proses Tambah Stok Masuk
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_stok'])) {
    $barang_id = (int)$_POST['barang_id'];
    $tanggal = $_POST['tanggal'];
    $jumlah_tambah = (float)$_POST['jumlah_tambah'];
    
    // Opsional harga beli/jual baru
    $harga_beli_baru = $_POST['harga_beli_baru'] !== '' ? (float)$_POST['harga_beli_baru'] : -1;
    $harga_jual_baru = $_POST['harga_jual_baru'] !== '' ? (float)$_POST['harga_jual_baru'] : -1;

    // Ambil data barang saat ini
    $query_barang = mysqli_query($conn, "SELECT * FROM barang WHERE id = $barang_id");
    $barang = mysqli_fetch_assoc($query_barang);

    if (!$barang) {
        $error = "Barang tidak ditemukan!";
    } elseif (!checkPermission($barang['owner_id'])) {
        $error = "Anda tidak memiliki izin untuk menambah stok barang ini!";
    } else {
        $jumlah_input = $jumlah_tambah;
        if ($barang['unit_type'] === 'renteng') {
            $jumlah_tambah = $jumlah_input * max((int)$barang['isi_renteng'], 1);
        }
        $harga_beli_insert = $harga_beli_baru >= 0 ? $harga_beli_baru : $barang['harga_beli'];
        $harga_jual_insert = $harga_jual_baru >= 0 ? $harga_jual_baru : $barang['harga_jual'];
        $owner_id = $barang['owner_id'];

        mysqli_begin_transaction($conn);
        try {
            // Insert ke tabel stok_masuk
            $query_insert = "INSERT INTO stok_masuk (tanggal, barang_id, jumlah_tambah, harga_beli, harga_jual, owner_id) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query_insert);
            mysqli_stmt_bind_param($stmt, "sidddi", $tanggal, $barang_id, $jumlah_tambah, $harga_beli_insert, $harga_jual_insert, $owner_id);
            mysqli_stmt_execute($stmt);

            // Update stok di tabel barang
            $query_update_stok = "UPDATE barang SET stok = stok + ? WHERE id = ?";
            $stmt2 = mysqli_prepare($conn, $query_update_stok);
            mysqli_stmt_bind_param($stmt2, "di", $jumlah_tambah, $barang_id);
            mysqli_stmt_execute($stmt2);

            // Jika ada harga baru, update di tabel barang
            if ($harga_beli_baru >= 0 || $harga_jual_baru >= 0) {
                $query_update_harga = "UPDATE barang SET ";
                $updates = [];
                $params = [];
                $types = "";

                if ($harga_beli_baru >= 0) {
                    $updates[] = "harga_beli = ?";
                    $params[] = $harga_beli_baru;
                    $types .= "d";
                }
                if ($harga_jual_baru >= 0) {
                    $updates[] = "harga_jual = ?";
                    $params[] = $harga_jual_baru;
                    $types .= "d";
                }

                $params[] = $barang_id;
                $types .= "i";

                $query_update_harga .= implode(", ", $updates) . " WHERE id = ?";
                $stmt3 = mysqli_prepare($conn, $query_update_harga);
                mysqli_stmt_bind_param($stmt3, $types, ...$params);
                mysqli_stmt_execute($stmt3);
            }

            mysqli_commit($conn);
            $success = "Stok berhasil ditambahkan!";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Gagal menyimpan stok masuk!";
        }
    }
}

// Proses Hapus Stok Masuk
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Cek ownership
    $check = mysqli_query($conn, "SELECT sm.*, b.owner_id FROM stok_masuk sm JOIN barang b ON sm.barang_id = b.id WHERE sm.id=$id");
    $sm_data = mysqli_fetch_assoc($check);

    if (!$sm_data) {
        $error = 'Data stok masuk tidak ditemukan!';
    } elseif (checkPermission($sm_data['owner_id'])) {
        mysqli_begin_transaction($conn);
        try {
            // Kurangi stok barang karena data dihapus
            $query_update_stok = "UPDATE barang SET stok = stok - ? WHERE id = ?";
            $stmt_stok = mysqli_prepare($conn, $query_update_stok);
            mysqli_stmt_bind_param($stmt_stok, "di", $sm_data['jumlah_tambah'], $sm_data['barang_id']);
            mysqli_stmt_execute($stmt_stok);

            // Hapus record
            mysqli_query($conn, "DELETE FROM stok_masuk WHERE id=$id");
            
            mysqli_commit($conn);
            $success = 'Data stok masuk berhasil dihapus (stok barang telah disesuaikan kembali)!';
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Gagal menghapus data stok masuk!";
        }
    } else {
        $error = 'Anda tidak memiliki izin untuk menghapus!';
    }
}

// Ambil list barang untuk dropdown
if ($role == 'anak') {
    $barang_query = "SELECT id, nama_barang, unit_type, isi_renteng, stok, owner_id FROM barang ORDER BY nama_barang ASC";
} else {
    $barang_query = "SELECT id, nama_barang, unit_type, isi_renteng, stok, owner_id FROM barang WHERE owner_id = $user_id ORDER BY nama_barang ASC";
}
$barang_result = mysqli_query($conn, $barang_query);

// Ambil data riwayat stok masuk
if ($role == 'anak') {
    $riwayat_query = "SELECT sm.*, b.nama_barang, b.unit_type, b.isi_renteng, u.nama as owner_nama FROM stok_masuk sm 
                      JOIN barang b ON sm.barang_id = b.id 
                      JOIN users u ON sm.owner_id = u.id 
                      ORDER BY sm.tanggal DESC, sm.id DESC LIMIT 100";
} else {
    $riwayat_query = "SELECT sm.*, b.nama_barang, b.unit_type, b.isi_renteng, u.nama as owner_nama FROM stok_masuk sm 
                      JOIN barang b ON sm.barang_id = b.id 
                      JOIN users u ON sm.owner_id = u.id 
                      WHERE sm.owner_id = $user_id 
                      ORDER BY sm.tanggal DESC, sm.id DESC LIMIT 100";
}
$riwayat_result = mysqli_query($conn, $riwayat_query);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Masuk - TELURKU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css">
    <style>
        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.5rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 26px;
            padding-left: 8px;
            color: #374151;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
            right: 8px;
        }
    </style>
</head>

<body class="bg-gray-100">
    <nav class="bg-blue-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Stok Masuk (Restock)</h1>
            <a href="index" class="bg-blue-700 px-4 py-2 rounded hover:bg-blue-800 flex items-center gap-2">
                <i class="ph ph-arrow-left"></i> Kembali
            </a>
        </div>
    </nav>

    <div class="container mx-auto p-4 max-w-6xl">
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 shadow-sm">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 shadow-sm">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Form Input -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-bold mb-4 flex items-center gap-2 text-gray-800">
                        <i class="ph ph-package text-blue-600 text-xl"></i> Form Stok Masuk
                    </h2>
                    <form method="POST" action="">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-gray-700 font-medium mb-1">Tanggal Masuk</label>
                                <input type="date" name="tanggal" required value="<?php echo date('Y-m-d'); ?>"
                                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                            </div>

                            <div>
                                <label class="block text-gray-700 font-medium mb-1">Pilih Barang</label>
                                <select name="barang_id" id="barang_id" required class="w-full">
                                    <option value="">-- Cari Barang --</option>
                                    <?php while ($brg = mysqli_fetch_assoc($barang_result)): ?>
                                        <option value="<?php echo $brg['id']; ?>" data-unit="<?php echo $brg['unit_type']; ?>">
                                            <?php echo $brg['nama_barang']; ?> (Sisa: <?php echo ($brg['unit_type'] === 'renteng' && (int)$brg['isi_renteng'] > 0) ? formatQty($brg['stok'] / max((int)$brg['isi_renteng'], 1)) . ' renteng' : formatQty($brg['stok']) . ' ' . unitLabel($brg['unit_type']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-gray-700 font-medium mb-1">Jumlah Tambah <span id="unitLabel" class="text-sm text-blue-600"></span></label>
                                <input type="number" step="1" min="1" name="jumlah_tambah" required placeholder="Contoh: 2000"
                                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                            </div>

                            <div class="border-t pt-4 mt-4">
                                <label class="block text-gray-600 text-sm font-medium mb-2"><i class="ph ph-info"></i> Opsional: Update Harga (Biarkan kosong jika tetap)</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <input type="number" step="0.01" name="harga_beli_baru" placeholder="H. Beli Baru"
                                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 text-sm">
                                    </div>
                                    <div>
                                        <input type="number" step="0.01" name="harga_jual_baru" placeholder="H. Jual Baru"
                                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 text-sm">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" name="tambah_stok" class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 font-medium transition shadow flex items-center justify-center gap-2 mt-4">
                                <i class="ph ph-plus-circle text-lg"></i> Simpan Stok Masuk
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabel Riwayat -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-4 border-b bg-gray-50 flex justify-between items-center">
                        <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                            <i class="ph ph-clock-counter-clockwise text-blue-600 text-xl"></i> Riwayat Stok Masuk (100 Terakhir)
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-100 text-gray-700 text-sm border-b">
                                    <th class="px-4 py-3 font-semibold">Tanggal</th>
                                    <th class="px-4 py-3 font-semibold">Barang</th>
                                    <th class="px-4 py-3 font-semibold text-center">Jml Masuk</th>
                                    <th class="px-4 py-3 font-semibold text-right">Harga Beli</th>
                                    <th class="px-4 py-3 font-semibold text-right">Harga Jual</th>
                                    <th class="px-4 py-3 font-semibold text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                                <?php if (mysqli_num_rows($riwayat_result) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($riwayat_result)): ?>
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="px-4 py-3"><?php echo formatTanggal($row['tanggal']); ?></td>
                                            <td class="px-4 py-3">
                                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($row['nama_barang']); ?></span>
                                                <div class="text-xs text-gray-500"><?php echo $row['owner_nama']; ?></div>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="inline-block px-2 py-1 bg-green-100 text-green-800 rounded font-bold">
                                                    +<?php echo ($row['unit_type'] === 'renteng' && (int)$row['isi_renteng'] > 0) ? formatQty($row['jumlah_tambah'] / max((int)$row['isi_renteng'], 1)) . ' renteng' : formatQty($row['jumlah_tambah']) . ' ' . unitLabel($row['unit_type']); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right"><?php echo formatRupiah($row['harga_beli']); ?></td>
                                            <td class="px-4 py-3 text-right"><?php echo formatRupiah($row['harga_jual']); ?></td>
                                            <td class="px-4 py-3 text-center">
                                                <?php if (checkPermission($row['owner_id'])): ?>
                                                    <button type="button" data-id="<?php echo $row['id']; ?>" class="delete-btn text-red-500 hover:text-red-700 transition" title="Batal/Hapus">
                                                        <i class="ph ph-trash text-lg"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-gray-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                            Belum ada riwayat stok masuk.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#barang_id').select2({
                placeholder: '-- Cari Barang --',
                allowClear: true
            });

            $('#barang_id').on('change', function() {
                var selected = $(this).find('option:selected');
                var unit = selected.data('unit');
                if (unit) {
                    $('#unitLabel').text('(' + (unit === 'renteng' ? 'renteng' : (unit === 'gram' || unit === 'kg' ? 'gram' : 'pcs')) + ')');
                } else {
                    $('#unitLabel').text('');
                }
            });

            // Delete confirmation
            $('.delete-btn').on('click', async function() {
                const id = $(this).data('id');
                const result = await Swal.fire({
                    title: 'Hapus data stok masuk?',
                    text: 'Menghapus data ini juga akan MENGURANGI KEMBALI stok barang sejumlah data masuk tersebut.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, hapus',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280'
                });

                if (result.isConfirmed) {
                    window.location.href = `?delete=${id}`;
                }
            });
        });
    </script>
</body>

</html>
