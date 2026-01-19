<?php
require_once 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$success = '';
$error = '';

// Proses Tambah/Edit Barang
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_barang = trim($_POST['nama_barang']);
    $unit_type = $_POST['unit_type'] ?? 'pcs';
    $harga_beli = $_POST['harga_beli'] === '' ? 0 : $_POST['harga_beli']; // Harga beli opsional, 0 jika tidak diisi
    $harga_jual = $_POST['harga_jual'];
    $owner_id = $_POST['owner_id'];

    // Pack size (opsional)
    $isi_renteng = (int)($_POST['isi_renteng'] ?? 0);
    $isi_pax = (int)($_POST['isi_pax'] ?? 0);
    $isi_slop = (int)($_POST['isi_slop'] ?? 0);

    // Hitung stok total berdasar satuan
    $stok = 0;
    if ($unit_type === 'kg') {
        $stok = (float)($_POST['stok_kg'] ?? 0);
    } else {
        $stok_ecer = (int)($_POST['stok_ecer'] ?? 0);
        $stok_renteng = (int)($_POST['stok_renteng'] ?? 0);
        $stok_pax = (int)($_POST['stok_pax'] ?? 0);
        $stok_slop = (int)($_POST['stok_slop'] ?? 0);

        $stok += $stok_ecer;
        $stok += $stok_renteng * max($isi_renteng, 1);
        $stok += $stok_pax * max($isi_pax, 1);
        $stok += $stok_slop * max($isi_slop, 1);
    }

    // Cek permission
    if (!checkPermission($owner_id)) {
        $error = 'Anda tidak memiliki izin untuk ini!';
    } else {
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // Update
            $id = $_POST['id'];
            $query = "UPDATE barang 
                      SET nama_barang=?, unit_type=?, isi_renteng=?, isi_pax=?, isi_slop=?, harga_beli=?, harga_jual=?, stok=?, owner_id=? 
                      WHERE id=?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ssiiidddii", $nama_barang, $unit_type, $isi_renteng, $isi_pax, $isi_slop, $harga_beli, $harga_jual, $stok, $owner_id, $id);
        } else {
            // Insert
            $query = "INSERT INTO barang (nama_barang, unit_type, isi_renteng, isi_pax, isi_slop, harga_beli, harga_jual, stok, owner_id) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ssiiidddi", $nama_barang, $unit_type, $isi_renteng, $isi_pax, $isi_slop, $harga_beli, $harga_jual, $stok, $owner_id);
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css">
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
            <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
                <i class="ph <?php echo $edit_data ? 'ph-pencil-simple' : 'ph-plus-circle'; ?>"></i>
                <?php echo $edit_data ? 'Edit' : 'Tambah'; ?> Barang
            </h2>
            <form method="POST" action="">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Nama Barang</label>
                        <input type="text" name="nama_barang" required
                            value="<?php echo $edit_data['nama_barang'] ?? ''; ?>"
                            class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                    </div>

                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Pemilik Stok</label>
                        <select name="owner_id" required
                            class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                            <?php mysqli_data_seek($users_query, 0);
                            while ($user = mysqli_fetch_assoc($users_query)): ?>
                                <option value="<?php echo $user['id']; ?>"
                                    <?php echo (isset($edit_data) && $edit_data['owner_id'] == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo $user['nama']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Satuan Utama</label>
                        <select name="unit_type" id="unitType"
                            class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                            <?php $unitNow = $edit_data['unit_type'] ?? 'pcs'; ?>
                            <option value="pcs" <?php echo $unitNow == 'pcs' ? 'selected' : ''; ?>>PCS / Pack</option>
                            <option value="kg" <?php echo $unitNow == 'kg' ? 'selected' : ''; ?>>Kilogram (kg)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Harga Beli (opsional)</label>
                        <input type="number" name="harga_beli" step="0.01" placeholder="Biarkan kosong jika tidak tahu"
                            value="<?php echo isset($edit_data['harga_beli']) ? $edit_data['harga_beli'] : ''; ?>"
                            class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                    </div>

                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Harga Jual</label>
                        <input type="number" name="harga_jual" required step="0.01"
                            value="<?php echo $edit_data['harga_jual'] ?? ''; ?>"
                            class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Isi per Renteng</label>
                        <input type="number" name="isi_renteng" id="isiRenteng" min="0" placeholder="contoh: 12"
                            value="<?php echo $edit_data['isi_renteng'] ?? 0; ?>"
                            class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Isi per Pax</label>
                        <input type="number" name="isi_pax" id="isiPax" min="0" placeholder="contoh: 6"
                            value="<?php echo $edit_data['isi_pax'] ?? 0; ?>"
                            class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Isi per Slop</label>
                        <input type="number" name="isi_slop" id="isiSlop" min="0" placeholder="contoh: 10"
                            value="<?php echo $edit_data['isi_slop'] ?? 0; ?>"
                            class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4">
                    <div id="stokPcsSection" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-gray-700 font-medium mb-1">Stok Ecer (pcs)</label>
                            <input type="number" min="0" name="stok_ecer" id="stokEcer"
                                value="<?php echo ($edit_data['unit_type'] ?? 'pcs') === 'pcs' ? ($edit_data['stok'] ?? 0) : 0; ?>"
                                class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-1">Stok Renteng</label>
                            <input type="number" min="0" name="stok_renteng" id="stokRenteng" value="0"
                                class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-1">Stok Pax</label>
                            <input type="number" min="0" name="stok_pax" id="stokPax" value="0"
                                class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-1">Stok Slop</label>
                            <input type="number" min="0" name="stok_slop" id="stokSlop" value="0"
                                class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                        </div>
                    </div>

                    <div id="stokKgSection" class="<?php echo ($edit_data['unit_type'] ?? 'pcs') === 'kg' ? '' : 'hidden'; ?>">
                        <label class="block text-gray-700 font-medium mb-1">Stok (kg)</label>
                        <input type="number" min="0" step="0.01" name="stok_kg" id="stokKg"
                            value="<?php echo ($edit_data['unit_type'] ?? 'pcs') === 'kg' ? ($edit_data['stok'] ?? 0) : 0; ?>"
                            class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                    </div>

                    <div class="text-sm text-gray-600 bg-blue-50 border border-blue-200 rounded-lg p-3">
                        Stok total dihitung otomatis dari ecer + renteng/pax/slop (dikonversi ke pcs).
                        Untuk barang kiloan (telur, gula, tepung, dll), pilih satuan <b>kg</b> dan isi stok di kolom kg.
                    </div>
                </div>

                <input type="hidden" name="stok" id="stokFinal" value="<?php echo $edit_data['stok'] ?? 0; ?>">

                <div class="mt-4 flex gap-2 flex-wrap">
                    <button type="submit" class="flex items-center gap-2 bg-blue-500 text-white px-6 py-3 rounded-lg hover:bg-blue-600 shadow">
                        <i class="ph <?php echo $edit_data ? 'ph-floppy-disk' : 'ph-check-circle'; ?>"></i>
                        <?php echo $edit_data ? 'Update' : 'Simpan'; ?>
                    </button>
                    <?php if ($edit_data): ?>
                        <a href="barang" class="flex items-center gap-2 bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 shadow">
                            <i class="ph ph-arrow-u-down-left"></i> Batal
                        </a>
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
                            <th class="px-4 py-3 text-center">Satuan</th>
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
                                <td class="px-4 py-3 text-center">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                        <?php echo strtoupper($row['unit_type'] ?? 'pcs'); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <?php echo ($row['harga_beli'] ?? 0) > 0 ? formatRupiah($row['harga_beli']) : '-'; ?>
                                </td>
                                <td class="px-4 py-3 text-right"><?php echo formatRupiah($row['harga_jual']); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="<?php echo $row['stok'] < 10 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?> px-3 py-1 rounded-full text-sm font-medium inline-block">
                                        <?php echo $row['unit_type'] === 'kg' ? $row['stok'] . ' kg' : $row['stok'] . ' pcs'; ?>
                                    </span>
                                    <?php if ($row['unit_type'] !== 'kg' && ($row['isi_renteng'] || $row['isi_pax'] || $row['isi_slop'])): ?>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <?php if ($row['isi_renteng']): ?>Renteng: <?php echo $row['isi_renteng']; ?> pcs<?php endif; ?>
                                            <?php if ($row['isi_pax']): ?><?php echo $row['isi_renteng'] ? ' · ' : ''; ?>Pax: <?php echo $row['isi_pax']; ?> pcs<?php endif; ?>
                                            <?php if ($row['isi_slop']): ?><?php echo ($row['isi_renteng'] || $row['isi_pax']) ? ' · ' : ''; ?>Slop: <?php echo $row['isi_slop']; ?> pcs<?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if (checkPermission($row['owner_id'])): ?>
                                        <a href="?edit=<?php echo $row['id']; ?>" class="inline-flex items-center gap-1 text-blue-600 hover:underline mr-2">
                                            <i class="ph ph-pencil-simple"></i> Edit
                                        </a>
                                        <button type="button"
                                            data-delete-id="<?php echo $row['id']; ?>"
                                            class="delete-btn inline-flex items-center gap-1 text-red-600 hover:underline">
                                            <i class="ph ph-trash"></i> Hapus
                                        </button>
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

    <script>
        const unitTypeEl = document.getElementById('unitType');
        const stokPcsSection = document.getElementById('stokPcsSection');
        const stokKgSection = document.getElementById('stokKgSection');
        const stokFinal = document.getElementById('stokFinal');

        const inputs = {
            stokEcer: document.getElementById('stokEcer'),
            stokRenteng: document.getElementById('stokRenteng'),
            stokPax: document.getElementById('stokPax'),
            stokSlop: document.getElementById('stokSlop'),
            stokKg: document.getElementById('stokKg'),
            isiRenteng: document.getElementById('isiRenteng'),
            isiPax: document.getElementById('isiPax'),
            isiSlop: document.getElementById('isiSlop')
        };

        function updateVisibility() {
            const isKg = unitTypeEl.value === 'kg';
            stokPcsSection.classList.toggle('hidden', isKg);
            stokKgSection.classList.toggle('hidden', !isKg);
            computeStok();
        }

        function computeStok() {
            if (!stokFinal) return;
            const isKg = unitTypeEl.value === 'kg';
            if (isKg) {
                const kg = parseFloat(inputs.stokKg.value) || 0;
                stokFinal.value = kg;
                return;
            }
            const ecer = parseInt(inputs.stokEcer.value || 0);
            const renteng = parseInt(inputs.stokRenteng.value || 0);
            const pax = parseInt(inputs.stokPax.value || 0);
            const slop = parseInt(inputs.stokSlop.value || 0);

            const isiRenteng = parseInt(inputs.isiRenteng.value || 0);
            const isiPax = parseInt(inputs.isiPax.value || 0);
            const isiSlop = parseInt(inputs.isiSlop.value || 0);

            const total = ecer +
                (renteng * (isiRenteng || 0)) +
                (pax * (isiPax || 0)) +
                (slop * (isiSlop || 0));
            stokFinal.value = total;
        }

        unitTypeEl?.addEventListener('change', updateVisibility);
        Object.values(inputs).forEach(el => el?.addEventListener('input', computeStok));

        // Init
        updateVisibility();
    </script>

    <script>
        // SweetAlert2: Konfirmasi hapus
        document.querySelectorAll('.delete-btn').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.deleteId;
                const result = await Swal.fire({
                    title: 'Hapus data?',
                    text: 'Data yang dihapus tidak bisa dikembalikan.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, hapus',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280'
                });

                if (result.isConfirmed) {
                    window.location.href = `?delete=${encodeURIComponent(id)}`;
                }
            });
        });
    </script>
</body>

</html>