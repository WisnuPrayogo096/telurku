<?php
require_once 'config.php';
requireLogin();

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$success = '';
$error = '';

// Proses Tambah/Edit Barang
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_barang = trim($_POST['nama_barang']);
    $unit_type = $_POST['unit_type'] ?? 'pcs';
    $harga_beli = $_POST['harga_beli'] === '' ? 0 : $_POST['harga_beli']; // Harga beli opsional, 0 jika tidak diisi
    if ($unit_type === 'kg') {
        $unit_type = 'gram';
    }

    $harga_jual = 0;
    $harga_jual_renteng = 0;
    $harga_jual_pcs = 0;
    $isi_renteng = 0;
    $isi_pax = 0;
    $isi_slop = 0;
    $stok = 0;

    if ($unit_type === 'gram') {
        $harga_jual = $_POST['harga_jual'] === '' ? 0 : (float)$_POST['harga_jual'];
        $stok = (float)($_POST['stok_gram'] ?? ($_POST['stok_kg'] ?? 0));
        if ($harga_jual <= 0 || $stok < 0) {
            $error = 'Harga jual dan stok gram wajib diisi dengan benar!';
        }
    } elseif ($unit_type === 'renteng') {
        $harga_jual_renteng = $_POST['harga_jual_renteng'] === '' ? 0 : (float)$_POST['harga_jual_renteng'];
        $harga_jual_pcs = $_POST['harga_jual_pcs'] === '' ? 0 : (float)$_POST['harga_jual_pcs'];
        $harga_jual = $harga_jual_pcs;
        $isi_renteng = (int)($_POST['isi_renteng'] ?? 0);
        $stok_renteng = (float)($_POST['stok_renteng'] ?? 0);
        $stok = $stok_renteng * max($isi_renteng, 1);
        if ($harga_jual_renteng <= 0 || $harga_jual_pcs <= 0 || $isi_renteng <= 0 || $stok_renteng < 0) {
            $error = 'Harga renteng, harga ecer, isi ecer per renteng/slop, dan stok renteng wajib diisi dengan benar!';
        }
    } else {
        $unit_type = 'pcs';
        $harga_jual_pcs = $_POST['harga_jual_pcs'] === '' ? 0 : (float)$_POST['harga_jual_pcs'];
        $harga_jual = $harga_jual_pcs;
        $stok = (float)($_POST['stok_ecer'] ?? 0);
        if ($harga_jual_pcs <= 0 || $stok < 0) {
            $error = 'Harga jual per pcs dan stok pcs wajib diisi dengan benar!';
        }
    }

    $owner_id = $user_id;
    if (!empty($_POST['id'])) {
        $id_for_owner = (int)$_POST['id'];
        $owner_query = mysqli_query($conn, "SELECT owner_id FROM barang WHERE id = $id_for_owner");
        if ($owner_row = mysqli_fetch_assoc($owner_query)) {
            $owner_id = (int)$owner_row['owner_id'];
        }
    }

    // Cek permission
    if ($error) {
        // Error validasi sudah diset di atas.
    } elseif (!checkPermission($owner_id)) {
        $error = 'Anda tidak memiliki izin untuk ini!';
    } else {
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // Update
            $id = $_POST['id'];
            $query = "UPDATE barang 
                      SET nama_barang=?, unit_type=?, isi_renteng=?, isi_pax=?, isi_slop=?, harga_beli=?, harga_jual=?, harga_jual_renteng=?, harga_jual_pcs=?, stok=?, owner_id=? 
                      WHERE id=?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ssiiidddddii", $nama_barang, $unit_type, $isi_renteng, $isi_pax, $isi_slop, $harga_beli, $harga_jual, $harga_jual_renteng, $harga_jual_pcs, $stok, $owner_id, $id);
        } else {
            // Insert
            $query = "INSERT INTO barang (nama_barang, unit_type, isi_renteng, isi_pax, isi_slop, harga_beli, harga_jual, harga_jual_renteng, harga_jual_pcs, stok, owner_id) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ssiiidddddi", $nama_barang, $unit_type, $isi_renteng, $isi_pax, $isi_slop, $harga_beli, $harga_jual, $harga_jual_renteng, $harga_jual_pcs, $stok, $owner_id);
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
    $id = (int)$_GET['delete'];

    // Cek ownership
    $check = mysqli_query($conn, "SELECT owner_id FROM barang WHERE id=$id");
    $barang = mysqli_fetch_assoc($check);

    if (!$barang) {
        $error = 'Data barang tidak ditemukan!';
    } elseif (checkPermission($barang['owner_id'])) {
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

// Ambil list users untuk dropdown owner
$users_query = mysqli_query($conn, "SELECT id, nama FROM users");
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="16x16" href="icons/16×16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icons/32×32.png">
    <link rel="icon" type="image/png" sizes="48x48" href="icons/48×48.png">
    <link rel="icon" type="image/png" sizes="192x192" href="icons/192×192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="icons/512×512.png">
    <link rel="apple-touch-icon" href="icons/180×180.png">
    <title>Data Barang - Toko Rahmat Jaya</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css">
    <style>
        .modal-active {
            overflow: hidden;
        }
    </style>
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

        <!-- Tabel Data -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-4 border-b bg-gray-50 flex flex-col md:flex-row justify-between items-center gap-4">
                <button type="button" onclick="openModal()" class="flex items-center gap-2 bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 shadow">
                    <i class="ph ph-plus-circle"></i> Tambah Barang
                </button>
                <div class="w-full md:w-1/3 relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="ph ph-magnifying-glass text-gray-400"></i>
                    </div>
                    <input type="text" id="searchInput" placeholder="Cari barang..." class="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full" id="barangTable">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left">No</th>
                            <th class="px-4 py-3 text-left">Nama Barang</th>
                            <th class="px-4 py-3 text-center">Satuan</th>
                            <th class="px-4 py-3 text-right">Harga Beli</th>
                            <th class="px-4 py-3 text-right">Harga Jual</th>
                            <th class="px-4 py-3 text-right">Harga Renteng/Slop</th>
                            <th class="px-4 py-3 text-right">Harga Pcs</th>
                            <th class="px-4 py-3 text-center">Stok</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        while ($row = mysqli_fetch_assoc($result)):
                        ?>
                            <tr class="border-b hover:bg-gray-50 barang-row">
                                <td class="px-4 py-3"><?php echo $no++; ?></td>
                                <td class="px-4 py-3 font-medium text-gray-800 row-nama"><?php echo htmlspecialchars($row['nama_barang']); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                        <?php echo unitTypeLabel($row['unit_type'] ?? 'pcs'); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <?php echo ($row['harga_beli'] ?? 0) > 0 ? formatRupiah($row['harga_beli']) : '-'; ?>
                                </td>
                                <td class="px-4 py-3 text-right"><?php echo formatRupiah($row['harga_jual']); ?></td>
                                <td class="px-4 py-3 text-right">
                                    <?php echo ($row['harga_jual_renteng'] ?? 0) > 0 ? formatRupiah($row['harga_jual_renteng']) : '-'; ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <?php echo ($row['harga_jual_pcs'] ?? 0) > 0 ? formatRupiah($row['harga_jual_pcs']) : '-'; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="<?php echo $row['stok'] < 10 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?> px-3 py-1 rounded-full text-sm font-medium inline-block">
                                        <?php echo (($row['unit_type'] ?? 'pcs') === 'renteng' && (int)$row['isi_renteng'] > 0) ? formatQty($row['stok'] / max((int)$row['isi_renteng'], 1)) . ' renteng' : formatQty($row['stok']) . ' ' . unitLabel($row['unit_type'] ?? 'pcs'); ?>
                                    </span>
                                    <?php if (($row['unit_type'] ?? 'pcs') === 'renteng' && $row['isi_renteng']): ?>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <?php if ($row['isi_renteng']): ?>Per Renteng/Slop isi <?php echo $row['isi_renteng']; ?> pcs<?php endif; ?>
                                            <?php if ($row['isi_pax']): ?><?php echo $row['isi_renteng'] ? ' · ' : ''; ?>Pax: <?php echo $row['isi_pax']; ?> pcs<?php endif; ?>
                                            <?php if ($row['isi_slop']): ?><?php echo ($row['isi_renteng'] || $row['isi_pax']) ? ' · ' : ''; ?>Slop: <?php echo $row['isi_slop']; ?> pcs<?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if (checkPermission($row['owner_id'])): ?>
                                        <button type="button"
                                            class="inline-flex items-center gap-1 text-blue-600 hover:underline mr-2"
                                            onclick="editBarang(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                            <i class="ph ph-pencil-simple"></i> Edit
                                        </button>
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

    <!-- Modal Form -->
    <div id="modalForm" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
            <div class="p-4 border-b flex justify-between items-center bg-gray-50 rounded-t-lg">
                <h2 id="modalTitle" class="text-xl font-bold flex items-center gap-2">
                    <i class="ph ph-plus-circle"></i> Tambah Barang
                </h2>
                <button type="button" onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="ph ph-x text-2xl"></i>
                </button>
            </div>
            <div class="p-6 overflow-y-auto">
                <form method="POST" action="" id="formBarang">
                    <input type="hidden" name="id" id="formId">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Nama Barang</label>
                            <input type="text" name="nama_barang" id="nama_barang" required
                                class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Satuan Utama</label>
                            <select name="unit_type" id="unitType"
                                class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                                <option value="renteng">Ecer dan Renteng/Slop</option>
                                <option value="gram">Gram/Timbang</option>
                                <option value="pcs">PCS Kemasan</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Harga Beli (opsional)</label>
                            <input type="number" name="harga_beli" id="harga_beli" step="0.01" placeholder="Biarkan kosong jika tidak tahu"
                                class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                        </div>

                        <div id="gramPriceSection">
                            <label class="block text-gray-700 font-medium mb-2" id="hargaJualLabel">Harga Jual per Pcs</label>
                            <input type="number" name="harga_jual" id="harga_jual" step="0.01"
                                class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                        </div>

                        <div class="renteng-only">
                            <label class="block text-gray-700 font-medium mb-2">Harga Jual per Renteng/Slop</label>
                            <input type="number" name="harga_jual_renteng" id="harga_jual_renteng" step="0.01" placeholder="Contoh: 11000"
                                class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                        </div>

                        <div class="pcs-price-section">
                            <label class="block text-gray-700 font-medium mb-2" id="hargaPcsLabel">Harga Jual Ecer</label>
                            <input type="number" name="harga_jual_pcs" id="harga_jual_pcs" step="0.01" placeholder="Contoh: 1500"
                                class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                        </div>
                    </div>

                    <div id="packConfigSection" class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Isi Ecer per Renteng/Slop</label>
                            <input type="number" name="isi_renteng" id="isiRenteng" min="0" placeholder="contoh: 12" value="0"
                                class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                        </div>
                        <div class="hidden">
                            <label class="block text-gray-700 font-medium mb-2">Isi per Pax</label>
                            <input type="number" name="isi_pax" id="isiPax" min="0" placeholder="contoh: 6" value="0"
                                class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                        </div>
                        <div class="hidden">
                            <label class="block text-gray-700 font-medium mb-2">Isi per Slop</label>
                            <input type="number" name="isi_slop" id="isiSlop" min="0" placeholder="contoh: 10" value="0"
                                class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4">
                        <div id="stokPcsSection" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div id="stokEcerWrap">
                                <label class="block text-gray-700 font-medium mb-1" id="stokPcsLabel">Stok Pcs</label>
                                <input type="number" min="0" name="stok_ecer" id="stokEcer" value="0"
                                    class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                            </div>
                            <div class="renteng-only">
                                <label class="block text-gray-700 font-medium mb-1">Stok Renteng</label>
                                <input type="number" min="0" step="0.001" name="stok_renteng" id="stokRenteng" value="0"
                                    class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                            </div>
                            <div class="hidden">
                                <label class="block text-gray-700 font-medium mb-1">Stok Pax</label>
                                <input type="number" min="0" name="stok_pax" id="stokPax" value="0"
                                    class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                            </div>
                            <div class="hidden">
                                <label class="block text-gray-700 font-medium mb-1">Stok Slop</label>
                                <input type="number" min="0" name="stok_slop" id="stokSlop" value="0"
                                    class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                            </div>
                        </div>

                        <div id="stokGramSection" class="hidden">
                            <label class="block text-gray-700 font-medium mb-1">Stok (gram)</label>
                            <input type="number" min="0" step="1" name="stok_gram" id="stokGram" value="0" placeholder="Contoh: 2000"
                                class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:border-blue-500 shadow-sm">
                        </div>

                        <div class="text-sm text-gray-600 bg-blue-50 border border-blue-200 rounded-lg p-3">
                            Pilih <b>Ecer dan Renteng/Slop</b> untuk barang seperti kopi sachet: isi harga renteng/slop, harga ecer, isi per renteng/slop, dan stok renteng/slop.
                            Pilih <b>Gram / Timbang</b> untuk beras/telur, atau <b>PCS Kemasan</b> untuk barang kemasan biasa.
                        </div>
                    </div>

                    <input type="hidden" name="stok" id="stokFinal" value="0">

                    <div class="mt-6 flex gap-2 justify-end border-t pt-4">
                        <button type="button" onclick="closeModal()" class="px-6 py-2 rounded-lg bg-gray-200 text-gray-800 hover:bg-gray-300">
                            Batal
                        </button>
                        <button type="submit" class="flex items-center gap-2 bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 shadow">
                            <i class="ph ph-floppy-disk"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Form & Modal logic
        const modal = document.getElementById('modalForm');
        const form = document.getElementById('formBarang');
        const modalTitle = document.getElementById('modalTitle');
        const unitTypeEl = document.getElementById('unitType');
        const stokPcsSection = document.getElementById('stokPcsSection');
        const stokGramSection = document.getElementById('stokGramSection');
        const packConfigSection = document.getElementById('packConfigSection');
        const gramPriceSection = document.getElementById('gramPriceSection');
        const hargaJualLabel = document.getElementById('hargaJualLabel');
        const hargaPcsLabel = document.getElementById('hargaPcsLabel');
        const stokPcsLabel = document.getElementById('stokPcsLabel');
        const stokEcerWrap = document.getElementById('stokEcerWrap');
        const rentengOnlyEls = document.querySelectorAll('.renteng-only');
        const pcsPriceSection = document.querySelector('.pcs-price-section');
        const stokFinal = document.getElementById('stokFinal');

        const inputs = {
            stokEcer: document.getElementById('stokEcer'),
            stokRenteng: document.getElementById('stokRenteng'),
            stokPax: document.getElementById('stokPax'),
            stokSlop: document.getElementById('stokSlop'),
            stokGram: document.getElementById('stokGram'),
            isiRenteng: document.getElementById('isiRenteng'),
            isiPax: document.getElementById('isiPax'),
            isiSlop: document.getElementById('isiSlop')
        };

        function openModal() {
            form.reset();
            document.getElementById('formId').value = '';
            modalTitle.innerHTML = '<i class="ph ph-plus-circle"></i> Tambah Barang';

            // Reset stok to 0
            Object.values(inputs).forEach(el => {
                if (el) el.value = '0';
            });
            stokFinal.value = '0';

            updateVisibility();
            modal.classList.remove('hidden');
            document.body.classList.add('modal-active');
        }

        function closeModal() {
            modal.classList.add('hidden');
            document.body.classList.remove('modal-active');
        }

        function editBarang(data) {
            form.reset();
            modalTitle.innerHTML = '<i class="ph ph-pencil-simple"></i> Edit Barang';

            document.getElementById('formId').value = data.id;
            document.getElementById('nama_barang').value = data.nama_barang;
            unitTypeEl.value = data.unit_type === 'kg' ? 'gram' : data.unit_type;
            if (unitTypeEl.value === 'pcs' && (parseInt(data.isi_renteng || 0) > 0)) {
                unitTypeEl.value = 'renteng';
            }
            document.getElementById('harga_beli').value = data.harga_beli > 0 ? data.harga_beli : '';
            document.getElementById('harga_jual').value = data.harga_jual;
            document.getElementById('harga_jual_renteng').value = data.harga_jual_renteng > 0 ? data.harga_jual_renteng : '';
            document.getElementById('harga_jual_pcs').value = data.harga_jual_pcs > 0 ? data.harga_jual_pcs : data.harga_jual;

            inputs.isiRenteng.value = data.isi_renteng || 0;
            inputs.isiPax.value = data.isi_pax || 0;
            inputs.isiSlop.value = data.isi_slop || 0;

            // Mapping total stock based on unit_type
            if (unitTypeEl.value === 'gram') {
                inputs.stokGram.value = data.stok || 0;
                inputs.stokEcer.value = 0;
            } else if (unitTypeEl.value === 'renteng') {
                const isi = parseInt(data.isi_renteng || 0);
                inputs.stokRenteng.value = isi > 0 ? (parseFloat(data.stok || 0) / isi) : 0;
                inputs.stokEcer.value = 0;
                inputs.stokGram.value = 0;
            } else {
                inputs.stokEcer.value = data.stok || 0;
                inputs.stokGram.value = 0;
            }
            inputs.stokPax.value = 0;
            inputs.stokSlop.value = 0;

            stokFinal.value = data.stok || 0;

            updateVisibility();
            modal.classList.remove('hidden');
            document.body.classList.add('modal-active');
        }

        function updateVisibility() {
            const isGram = unitTypeEl.value === 'gram';
            const isRenteng = unitTypeEl.value === 'renteng';
            const isPcs = unitTypeEl.value === 'pcs';
            stokPcsSection.classList.toggle('hidden', isGram);
            stokGramSection.classList.toggle('hidden', !isGram);
            packConfigSection.classList.toggle('hidden', !isRenteng);
            gramPriceSection.classList.toggle('hidden', !isGram);
            pcsPriceSection.classList.toggle('hidden', isGram);
            rentengOnlyEls.forEach(el => el.classList.toggle('hidden', !isRenteng));
            stokEcerWrap.classList.toggle('hidden', !isPcs);
            if (hargaJualLabel) {
                hargaJualLabel.textContent = 'Harga Jual per 1000 Gram';
            }
            if (hargaPcsLabel) {
                hargaPcsLabel.textContent = isRenteng ? 'Harga Jual Ecer' : 'Harga Jual per Pcs';
            }
            if (stokPcsLabel) {
                stokPcsLabel.textContent = isPcs ? 'Stok Pcs' : 'Stok Ecer';
            }
            document.getElementById('harga_jual').required = isGram;
            document.getElementById('harga_jual_renteng').required = isRenteng;
            document.getElementById('harga_jual_pcs').required = isRenteng || isPcs;
            inputs.isiRenteng.required = isRenteng;
            inputs.stokRenteng.required = isRenteng;
            inputs.stokEcer.required = isPcs;
            inputs.stokGram.required = isGram;
            computeStok();
        }

        function computeStok() {
            if (!stokFinal) return;
            const isGram = unitTypeEl.value === 'gram';
            if (isGram) {
                const gram = parseInt(inputs.stokGram.value || 0);
                stokFinal.value = gram;
                return;
            }
            if (unitTypeEl.value === 'pcs') {
                stokFinal.value = parseFloat(inputs.stokEcer.value || 0);
                return;
            }
            const renteng = parseFloat(inputs.stokRenteng.value || 0);

            const isiRenteng = parseInt(inputs.isiRenteng.value || 0);
            const total = renteng * (isiRenteng || 0);
            stokFinal.value = total;
        }

        unitTypeEl?.addEventListener('change', updateVisibility);
        Object.values(inputs).forEach(el => el?.addEventListener('input', computeStok));

        // Search logic
        const searchInput = document.getElementById('searchInput');
        searchInput?.addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.barang-row');

            rows.forEach(row => {
                const nama = row.querySelector('.row-nama').textContent.toLowerCase();
                if (nama.includes(term)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
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