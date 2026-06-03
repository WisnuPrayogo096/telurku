<?php
require_once 'config.php';
requireLogin();

$success = '';
$error = '';
$tanggal_filter = mysqli_real_escape_string($conn, $_GET['tanggal'] ?? getCurrentDate());

if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Proses Hapus Stok Masuk
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $check_stmt = mysqli_prepare($conn, "SELECT sm.* FROM stok_masuk sm WHERE sm.id=?");
    mysqli_stmt_bind_param($check_stmt, "i", $id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $sm_data = mysqli_fetch_assoc($result);

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
            $del_stmt = mysqli_prepare($conn, "DELETE FROM stok_masuk WHERE id=?");
            mysqli_stmt_bind_param($del_stmt, "i", $id);
            mysqli_stmt_execute($del_stmt);

            mysqli_commit($conn);
            $success = 'Data stok masuk berhasil dihapus (stok barang telah disesuaikan kembali)!';
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Gagal menghapus data stok masuk!";
        }
    }
}

// Ambil data riwayat stok masuk
$riwayat_query = "SELECT sm.*, b.nama_barang, b.unit_type, b.isi_renteng FROM stok_masuk sm 
                  JOIN barang b ON sm.barang_id = b.id 
                  WHERE sm.tanggal = '$tanggal_filter'
                  ORDER BY sm.tanggal DESC, sm.id DESC";
$riwayat_result = mysqli_query($conn, $riwayat_query);
$total_harga_beli = 0;
$pageTitle = 'Stok Masuk - Toko Rahmat Jaya';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">';
require_once 'includes/head.php';
$navTitle = 'Stok Masuk (Restock)';
$navBackUrl = 'index';
require_once 'includes/navbar.php';
require_once 'includes/flash.php';
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
            <a href="stok_masuk_tambah" class="btn btn-primary py-3 px-6 shadow-md">
                <i class="ph ph-plus-circle"></i> Tambah Stok Masuk
            </a>
        </div>
    </div>

    <div class="app-panel overflow-hidden">
        <div class="app-panel-header">
            <span class="app-panel-title"><i class="ph ph-clock-counter-clockwise text-brand"></i> Riwayat Stok Masuk</span>
            <span class="text-sm font-bold text-slate-700">Total Beli: <span class="text-brand" id="totalHargaBeliDisplay">Rp 0</span></span>
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
                            <tr class="border-b hover:bg-blue-50/50">
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
                                    <button type="button" data-delete-id="<?php echo $row['id']; ?>" class="btn-icon btn-icon-delete delete-btn" title="Batal/Hapus">
                                        <i class="ph ph-trash"></i>
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

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="app-modal-backdrop hidden">
    <div class="app-modal max-w-sm" onclick="event.stopPropagation()">
        <div class="p-5 text-center">
            <div class="w-16 h-16 rounded-full bg-red-100 text-red-600 flex items-center justify-center mx-auto mb-4 text-3xl">
                <i class="ph ph-warning-circle"></i>
            </div>
            <h3 class="text-lg font-bold mb-2">Hapus Riwayat?</h3>
            <p class="text-gray-500 text-sm mb-6">Menghapus riwayat akan mengurangi kembali stok barang yang sudah ditambahkan. Lanjutkan?</p>
            <div class="flex gap-3 justify-center">
                <button type="button" onclick="document.getElementById('deleteModal').classList.add('hidden')" class="btn btn-secondary flex-1">Batal</button>
                <button type="button" id="confirmDeleteBtn" class="btn btn-danger flex-1">Ya, Hapus</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="assets/js/datatables-default.js"></script>
<script>
$(document).ready(function() {
    initDefaultDataTable('#stokMasukTable', {
        language: { emptyTable: 'Belum ada riwayat stok masuk.' }
    });

    $('#totalHargaBeliDisplay').text(new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0
    }).format(<?php echo (float)$total_harga_beli; ?>));

    let deleteId = null;
    $('.delete-btn').on('click', function() {
        deleteId = $(this).data('delete-id');
        $('#deleteModal').removeClass('hidden');
    });

    $('#confirmDeleteBtn').on('click', function() {
        if (deleteId) {
            window.location.href = `?delete=${deleteId}&tanggal=<?php echo urlencode($tanggal_filter); ?>`;
        }
    });

    document.querySelectorAll('.app-modal-backdrop').forEach(m => {
        m.addEventListener('click', function(e) {
            if (e.target === this) this.classList.add('hidden');
        });
    });
});
</script>
<?php require_once 'includes/footer.php'; ?>
