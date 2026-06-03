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

// Hapus riwayat (kembalikan stok)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $check_stmt = mysqli_prepare($conn, "SELECT * FROM stok_keluar WHERE id = ?");
    mysqli_stmt_bind_param($check_stmt, "i", $id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $sk_data = mysqli_fetch_assoc($result);

    if (!$sk_data) {
        $error = 'Data pengurangan stok tidak ditemukan!';
    } else {
        mysqli_begin_transaction($conn);
        try {
            $query_update = "UPDATE barang SET stok = stok + ? WHERE id = ?";
            $stmt_stok = mysqli_prepare($conn, $query_update);
            mysqli_stmt_bind_param($stmt_stok, "di", $sk_data['jumlah_kurang'], $sk_data['barang_id']);
            mysqli_stmt_execute($stmt_stok);

            $del_stmt = mysqli_prepare($conn, "DELETE FROM stok_keluar WHERE id = ?");
            mysqli_stmt_bind_param($del_stmt, "i", $id);
            mysqli_stmt_execute($del_stmt);

            mysqli_commit($conn);
            $success = 'Data pengurangan stok dihapus (stok barang telah dikembalikan)!';
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = 'Gagal menghapus data pengurangan stok!';
        }
    }
}

$riwayat_query = "SELECT sk.*, b.nama_barang, b.unit_type, b.isi_renteng
                  FROM stok_keluar sk
                  JOIN barang b ON sk.barang_id = b.id
                  WHERE sk.tanggal = '$tanggal_filter'
                  ORDER BY sk.id DESC";
$riwayat_result = mysqli_query($conn, $riwayat_query);

$pageTitle = 'Pengurangan Stok - Toko Rahmat Jaya';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">';
require_once 'includes/head.php';
$navTitle = 'Pengurangan Stok';
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
            <a href="pengurangan_stok_tambah" class="btn btn-danger py-3 px-6 shadow-md">
                <i class="ph ph-minus-circle"></i> Kurangi Stok
            </a>
        </div>
    </div>

    <div class="app-alert app-alert-info mb-6">
        <i class="ph ph-info text-xl text-brand shrink-0"></i>
        <span>Catat barang yang <strong>keluar dari toko</strong> tanpa dijual — misalnya untuk keperluan pribadi atau dipakai sendiri.</span>
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
                            <tr class="border-b hover:bg-red-50/50">
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
            <h3 class="text-lg font-bold mb-2">Hapus Pengurangan?</h3>
            <p class="text-gray-500 text-sm mb-6">Menghapus riwayat pengurangan stok akan mengembalikan stok barang yang keluar. Lanjutkan?</p>
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
    initDefaultDataTable('#penguranganStokTable', {
        language: { emptyTable: 'Belum ada riwayat pengurangan stok.' }
    });

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
