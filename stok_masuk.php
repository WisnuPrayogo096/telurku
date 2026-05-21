<?php
require_once 'config.php';
requireLogin();

$success = '';
$error = '';
$tanggal_filter = mysqli_real_escape_string($conn, $_GET['tanggal'] ?? getCurrentDate());

// Proses Tambah Stok Masuk
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_stok'])) {
    $barang_id = (int)$_POST['barang_id'];
    $tanggal = getCurrentDate(); // Otomatis tanggal dengan timezone GMT+7
    $jumlah_tambah = (float)$_POST['jumlah_tambah'];

    // Opsional harga beli/jual baru
    $harga_beli_baru = $_POST['harga_beli_baru'] !== '' ? (float)$_POST['harga_beli_baru'] : -1;
    $harga_jual_baru = $_POST['harga_jual_baru'] !== '' ? (float)$_POST['harga_jual_baru'] : -1;

    // Ambil data barang saat ini
    $query_barang = mysqli_query($conn, "SELECT * FROM barang WHERE id = $barang_id");
    $barang = mysqli_fetch_assoc($query_barang);

    if (!$barang) {
        $error = "Barang tidak ditemukan!";
    } else {
        $jumlah_input = $jumlah_tambah;
        if ($barang['unit_type'] === 'renteng') {
            $jumlah_tambah = $jumlah_input * max((int)$barang['isi_renteng'], 1);
        }
        $harga_beli_insert = $harga_beli_baru >= 0 ? $harga_beli_baru : $barang['harga_beli'];
        $harga_jual_insert = $harga_jual_baru >= 0 ? $harga_jual_baru : $barang['harga_jual'];

        mysqli_begin_transaction($conn);
        try {
            // Insert ke tabel stok_masuk
            $query_insert = "INSERT INTO stok_masuk (tanggal, barang_id, jumlah_tambah, harga_beli, harga_jual) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query_insert);
            mysqli_stmt_bind_param($stmt, "siddd", $tanggal, $barang_id, $jumlah_tambah, $harga_beli_insert, $harga_jual_insert);
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

    $check = mysqli_query($conn, "SELECT sm.* FROM stok_masuk sm WHERE sm.id=$id");
    $sm_data = mysqli_fetch_assoc($check);

    if (!$sm_data) {
        $error = 'Data stok masuk tidak ditemukan!';
    } else {
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
    }
}

// Ambil list barang untuk dropdown
$barang_query = "SELECT id, nama_barang, unit_type, isi_renteng, stok FROM barang ORDER BY nama_barang ASC";
$barang_result = mysqli_query($conn, $barang_query);

// Ambil data riwayat stok masuk
$riwayat_query = "SELECT sm.*, b.nama_barang, b.unit_type, b.isi_renteng FROM stok_masuk sm 
                  JOIN barang b ON sm.barang_id = b.id 
                  WHERE sm.tanggal = '$tanggal_filter'
                  ORDER BY sm.tanggal DESC, sm.id DESC";
$riwayat_result = mysqli_query($conn, $riwayat_query);
$total_harga_beli = 0;
$pageTitle = 'Stok Masuk - Toko Rahmat Jaya';
$extraHead = '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="includes/select2_theme.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">';
require_once 'includes/head.php';
$navTitle = 'Stok Masuk (Restock)';
$navBackUrl = 'index';
require_once 'includes/navbar.php';
require_once 'includes/swal_flash.php';
?>

<div class="app-container max-w-6xl">
    <div class="app-panel mb-6">
        <div class="app-panel-body flex flex-col md:flex-row md:items-end justify-between gap-4">
            <form method="GET" class="flex flex-col sm:flex-row gap-3 sm:items-end flex-1">
                <div class="flex-1 max-w-xs">
                    <label class="app-label">Tanggal Riwayat</label>
                    <input type="date" name="tanggal" value="<?php echo htmlspecialchars($tanggal_filter); ?>" class="app-input">
                </div>
                <button type="submit" class="btn btn-secondary"><i class="ph ph-funnel"></i> Filter</button>
            </form>
            <button type="button" id="openStokModal" class="btn btn-primary py-3">
                <i class="ph ph-plus-circle"></i> Tambah Stok Masuk
            </button>
        </div>
    </div>

    <div id="stokModal" class="app-modal-backdrop hidden">
        <div class="app-modal max-w-xl" onclick="event.stopPropagation()">
            <div class="app-modal-header">
                <h2 class="font-bold flex items-center gap-2"><i class="ph ph-package text-amber-600"></i> Form Stok Masuk</h2>
                <button type="button" id="closeStokModal" class="text-slate-400 hover:text-slate-600 p-1"><i class="ph ph-x text-xl"></i></button>
            </div>
            <div class="p-5">
                    <form method="POST" action="">
                        <div class="space-y-4">
                            <div>
                                <label class="app-label">Pilih Barang</label>
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
                                <label class="app-label">Jumlah Tambah <span id="unitLabel" class="text-amber-600 font-normal"></span></label>
                                <input type="number" step="1" min="1" name="jumlah_tambah" required placeholder="Contoh: 10" class="app-input">
                            </div>
                            <div class="section-card p-4">
                                <label class="text-sm font-medium text-slate-600 mb-2 block"><i class="ph ph-info text-amber-600"></i> Opsional: update harga</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <input type="number" step="0.01" name="harga_beli_baru" placeholder="H. Beli Baru" class="app-input text-sm">
                                    <input type="number" step="0.01" name="harga_jual_baru" placeholder="H. Jual Baru" class="app-input text-sm">
                                </div>
                            </div>
                            <button type="submit" name="tambah_stok" class="btn btn-primary w-full py-3 mt-2">
                                <i class="ph ph-floppy-disk"></i> Simpan Stok Masuk
                            </button>
                        </div>
                    </form>
            </div>
        </div>
    </div>

    <div class="app-panel overflow-hidden">
        <div class="app-panel-header">
            <span class="app-panel-title"><i class="ph ph-clock-counter-clockwise text-amber-600"></i> Riwayat Stok Masuk</span>
            <span class="text-sm font-bold text-slate-700">Total Beli: <span class="text-amber-600" id="totalHargaBeliDisplay">Rp 0</span></span>
        </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse" id="stokMasukTable">
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
                                        <?php $total_harga_beli += ((float)$row['harga_beli'] * (float)$row['jumlah_tambah']); ?>
                                        <tr class="border-b hover:bg-amber-50/40">
                                            <td class="px-4 py-3"><?php echo formatTanggal($row['tanggal']); ?></td>
                                            <td class="px-4 py-3">
                                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($row['nama_barang']); ?></span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="badge badge-green font-bold">
                                                    +<?php echo ($row['unit_type'] === 'renteng' && (int)$row['isi_renteng'] > 0) ? formatQty($row['jumlah_tambah'] / max((int)$row['isi_renteng'], 1)) . ' renteng' : formatQty($row['jumlah_tambah']) . ' ' . unitLabel($row['unit_type']); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right"><?php echo formatRupiah($row['harga_beli']); ?></td>
                                            <td class="px-4 py-3 text-right"><?php echo formatRupiah($row['harga_jual']); ?></td>
                                            <td class="px-4 py-3 text-center">
                                                <button type="button" data-id="<?php echo $row['id']; ?>" class="delete-btn text-red-500 hover:text-red-700 transition" title="Batal/Hapus">
                                                    <i class="ph ph-trash text-lg"></i>
                                                </button>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#barang_id').select2({
                placeholder: '-- Cari Barang --',
                allowClear: true
            });
            $('#stokMasukTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/id.json' }
            });

            $('#totalHargaBeliDisplay').text(new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0
            }).format(<?php echo (float)$total_harga_beli; ?>));

            $('#openStokModal').on('click', function() {
                $('#stokModal').removeClass('hidden');
                setTimeout(function() {
                    $('#barang_id').select2('open');
                    document.querySelector('.select2-search__field')?.focus();
                }, 100);
            });

            $('#closeStokModal').on('click', function() {
                $('#stokModal').addClass('hidden');
            });
            $('#stokModal').on('click', function(e) {
                if (e.target === this) $('#stokModal').addClass('hidden');
            });

            $('#barang_id').on('select2:open', function() {
                document.querySelector('.select2-search__field')?.focus();
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
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6b7280'
                });

                if (result.isConfirmed) {
                    window.location.href = `?delete=${id}`;
                }
            });
        });
    </script>
<?php require_once 'includes/footer.php'; ?>
