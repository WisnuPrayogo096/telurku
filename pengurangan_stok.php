<?php
require_once 'config.php';
requireLogin();

$success = '';
$error = '';
$tanggal_filter = mysqli_real_escape_string($conn, $_GET['tanggal'] ?? getCurrentDate());

// Proses pengurangan stok
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['kurangi_stok'])) {
    $barang_id = (int)$_POST['barang_id'];
    $jumlah_kurang = (float)$_POST['jumlah_kurang'];
    $keterangan = trim($_POST['keterangan'] ?? '');
    $tanggal = getCurrentDate();

    if ($keterangan === '') {
        $keterangan = 'Keperluan pribadi';
    }

    $query_barang = mysqli_query($conn, "SELECT * FROM barang WHERE id = $barang_id");
    $barang = mysqli_fetch_assoc($query_barang);

    if (!$barang) {
        $error = 'Barang tidak ditemukan!';
    } elseif ($jumlah_kurang <= 0) {
        $error = 'Jumlah pengurangan harus lebih dari 0!';
    } else {
        $jumlah_input = $jumlah_kurang;
        if ($barang['unit_type'] === 'renteng') {
            $jumlah_kurang = $jumlah_input * max((int)$barang['isi_renteng'], 1);
        }

        if ((float)$barang['stok'] < $jumlah_kurang) {
            $error = 'Stok tidak cukup untuk barang ' . $barang['nama_barang'] . '!';
        } else {
            mysqli_begin_transaction($conn);
            try {
                $query_insert = "INSERT INTO stok_keluar (tanggal, barang_id, jumlah_kurang, keterangan) VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $query_insert);
                mysqli_stmt_bind_param($stmt, "sids", $tanggal, $barang_id, $jumlah_kurang, $keterangan);
                mysqli_stmt_execute($stmt);

                $query_update = "UPDATE barang SET stok = stok - ? WHERE id = ?";
                $stmt2 = mysqli_prepare($conn, $query_update);
                mysqli_stmt_bind_param($stmt2, "di", $jumlah_kurang, $barang_id);
                mysqli_stmt_execute($stmt2);

                mysqli_commit($conn);
                $success = 'Stok berhasil dikurangi!';
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = 'Gagal menyimpan pengurangan stok!';
            }
        }
    }
}

// Hapus riwayat (kembalikan stok)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $check = mysqli_query($conn, "SELECT * FROM stok_keluar WHERE id = $id");
    $sk_data = mysqli_fetch_assoc($check);

    if (!$sk_data) {
        $error = 'Data pengurangan stok tidak ditemukan!';
    } else {
        mysqli_begin_transaction($conn);
        try {
            $query_update = "UPDATE barang SET stok = stok + ? WHERE id = ?";
            $stmt_stok = mysqli_prepare($conn, $query_update);
            mysqli_stmt_bind_param($stmt_stok, "di", $sk_data['jumlah_kurang'], $sk_data['barang_id']);
            mysqli_stmt_execute($stmt_stok);

            mysqli_query($conn, "DELETE FROM stok_keluar WHERE id = $id");

            mysqli_commit($conn);
            $success = 'Data pengurangan stok dihapus (stok barang telah dikembalikan)!';
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = 'Gagal menghapus data pengurangan stok!';
        }
    }
}

$barang_query = "SELECT id, nama_barang, unit_type, isi_renteng, stok FROM barang WHERE stok > 0 ORDER BY nama_barang ASC";
$barang_result = mysqli_query($conn, $barang_query);

$riwayat_query = "SELECT sk.*, b.nama_barang, b.unit_type, b.isi_renteng
                  FROM stok_keluar sk
                  JOIN barang b ON sk.barang_id = b.id
                  WHERE sk.tanggal = '$tanggal_filter'
                  ORDER BY sk.id DESC";
$riwayat_result = mysqli_query($conn, $riwayat_query);

$pageTitle = 'Pengurangan Stok - Toko Rahmat Jaya';
$extraHead = '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="includes/select2_theme.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">';
require_once 'includes/head.php';
$navTitle = 'Pengurangan Stok';
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
            <button type="button" id="openKurangModal" class="btn btn-danger py-3">
                <i class="ph ph-minus-circle"></i> Kurangi Stok
            </button>
        </div>
    </div>

    <div class="app-alert app-alert-info mb-6">
        <i class="ph ph-info text-xl text-amber-600 shrink-0"></i>
        <span>Catat barang yang <strong>keluar dari toko</strong> tanpa dijual — misalnya untuk keperluan pribadi atau dipakai sendiri.</span>
    </div>

    <div id="kurangModal" class="app-modal-backdrop hidden">
        <div class="app-modal max-w-xl" onclick="event.stopPropagation()">
            <div class="app-modal-header">
                <h2 class="font-bold flex items-center gap-2"><i class="ph ph-arrow-down text-red-600"></i> Form Pengurangan Stok</h2>
                <button type="button" id="closeKurangModal" class="text-slate-400 hover:text-slate-600 p-1"><i class="ph ph-x text-xl"></i></button>
            </div>
            <div class="p-5">
                <form method="POST" action="">
                    <div class="space-y-4">
                        <div>
                            <label class="app-label">Pilih Barang</label>
                            <select name="barang_id" id="barang_kurang_id" required class="w-full">
                                <option value="">-- Cari Barang --</option>
                                <?php while ($brg = mysqli_fetch_assoc($barang_result)): ?>
                                    <option value="<?php echo $brg['id']; ?>" data-unit="<?php echo htmlspecialchars($brg['unit_type']); ?>">
                                        <?php echo $brg['nama_barang']; ?> (Sisa: <?php echo ($brg['unit_type'] === 'renteng' && (int)$brg['isi_renteng'] > 0) ? formatQty($brg['stok'] / max((int)$brg['isi_renteng'], 1)) . ' renteng' : formatQty($brg['stok']) . ' ' . unitLabel($brg['unit_type']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div>
                            <label class="app-label">Jumlah Keluar <span id="unitLabelKurang" class="text-red-600 font-normal"></span></label>
                            <input type="number" step="1" min="1" name="jumlah_kurang" required placeholder="Contoh: 2" class="app-input">
                        </div>

                        <div>
                            <label class="app-label">Keterangan</label>
                            <input type="text" name="keterangan" maxlength="255" value="Keperluan pribadi" placeholder="Contoh: Keperluan pribadi" class="app-input">
                        </div>

                        <button type="submit" name="kurangi_stok" class="btn btn-danger w-full py-3 mt-2">
                            <i class="ph ph-floppy-disk"></i> Simpan Pengurangan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="app-panel overflow-hidden">
        <div class="app-panel-header">
            <span class="app-panel-title"><i class="ph ph-clock-counter-clockwise text-red-600"></i> Riwayat Pengurangan Stok</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="penguranganStokTable">
                <thead>
                    <tr class="bg-gray-100 text-gray-700 text-sm border-b">
                        <th class="px-4 py-3 font-semibold">Tanggal</th>
                        <th class="px-4 py-3 font-semibold">Barang</th>
                        <th class="px-4 py-3 font-semibold text-center">Jml Keluar</th>
                        <th class="px-4 py-3 font-semibold">Keterangan</th>
                        <th class="px-4 py-3 font-semibold text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <?php if (mysqli_num_rows($riwayat_result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($riwayat_result)): ?>
                            <tr class="border-b hover:bg-red-50/40">
                                <td class="px-4 py-3"><?php echo formatTanggal($row['tanggal']); ?></td>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-gray-800"><?php echo htmlspecialchars($row['nama_barang']); ?></span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge badge-red font-bold">
                                        −<?php echo ($row['unit_type'] === 'renteng' && (int)$row['isi_renteng'] > 0) ? formatQty($row['jumlah_kurang'] / max((int)$row['isi_renteng'], 1)) . ' renteng' : formatQty($row['jumlah_kurang']) . ' ' . unitLabel($row['unit_type']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600"><?php echo htmlspecialchars($row['keterangan']); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" data-id="<?php echo $row['id']; ?>" class="delete-btn text-red-500 hover:text-red-700 transition" title="Batal/Hapus">
                                        <i class="ph ph-trash text-lg"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/swal_lib.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="assets/js/datatables-default.js"></script>
<script>
$(document).ready(function() {
    $('#barang_kurang_id').select2({
        placeholder: '-- Cari Barang --',
        allowClear: true
    });

    initDefaultDataTable('#penguranganStokTable', {
        language: { emptyTable: 'Belum ada riwayat pengurangan stok.' }
    });

    $('#openKurangModal').on('click', function() {
        $('#kurangModal').removeClass('hidden');
        setTimeout(function() {
            $('#barang_kurang_id').select2('open');
            document.querySelector('.select2-search__field')?.focus();
        }, 100);
    });

    $('#closeKurangModal').on('click', function() {
        $('#kurangModal').addClass('hidden');
    });
    $('#kurangModal').on('click', function(e) {
        if (e.target === this) $('#kurangModal').addClass('hidden');
    });

    $('#barang_kurang_id').on('select2:open', function() {
        document.querySelector('.select2-search__field')?.focus();
    });

    $('#barang_kurang_id').on('change', function() {
        const unit = $(this).find('option:selected').data('unit');
        if (unit) {
            $('#unitLabelKurang').text('(' + (unit === 'renteng' ? 'renteng' : (unit === 'gram' || unit === 'kg' ? 'gram' : 'pcs')) + ')');
        } else {
            $('#unitLabelKurang').text('');
        }
    });

    $('#penguranganStokTable').on('click', '.delete-btn', async function() {
        const id = $(this).data('id');
        const result = await Swal.fire({
            title: 'Hapus data pengurangan?',
            text: 'Stok barang akan dikembalikan sejumlah data keluar tersebut.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280'
        });

        if (result.isConfirmed) {
            window.location.href = `?delete=${id}&tanggal=<?php echo urlencode($tanggal_filter); ?>`;
        }
    });
});
</script>
<?php require_once 'includes/footer.php'; ?>
