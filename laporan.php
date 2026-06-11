<?php
require_once 'config.php';
requireLogin();

$success = '';
$error = '';

// Filter (escape untuk prevent SQL injection)
$dari_tanggal = mysqli_real_escape_string($conn, $_GET['dari'] ?? getCurrentDate());
$sampai_tanggal = mysqli_real_escape_string($conn, $_GET['sampai'] ?? getCurrentDate());

// Batalkan satu item
if (isset($_GET['delete'])) {
    $detail_id = (int)$_GET['delete'];
    $result_hapus = hapusDetailPenjualan($conn, $detail_id);

    if ($result_hapus['ok']) {
        $success = $result_hapus['message'];
    } else {
        $error = $result_hapus['message'];
    }
}

// Batalkan seluruh transaksi
if (isset($_GET['delete_penjualan'])) {
    $penjualan_id = (int)$_GET['delete_penjualan'];
    $result_hapus = hapusPenjualan($conn, $penjualan_id);

    if ($result_hapus['ok']) {
        $success = $result_hapus['message'];
    } else {
        $error = $result_hapus['message'];
    }
}

// Batalkan beberapa item sekaligus
if (isset($_GET['delete_bulk'])) {
    $detail_ids = array_map('intval', explode(',', (string)$_GET['delete_bulk']));
    $result_hapus = hapusDetailPenjualanBulk($conn, $detail_ids);

    if ($result_hapus['ok']) {
        $success = $result_hapus['message'];
    } else {
        $error = $result_hapus['message'];
    }
}

$query = "SELECT 
            dp.id AS detail_id,
            p.id AS penjualan_id,
            p.tanggal,
            b.nama_barang,
            dp.unit,
            dp.jumlah,
            b.harga_beli,
            b.unit_type,
            b.isi_renteng,
            dp.harga_satuan,
            dp.subtotal,
            (SELECT COUNT(*) FROM detail_penjualan dp2 WHERE dp2.penjualan_id = p.id) AS item_count
          FROM detail_penjualan dp
          JOIN penjualan p ON dp.penjualan_id = p.id
          JOIN barang b ON dp.barang_id = b.id
          WHERE DATE(p.tanggal) BETWEEN ? AND ?
          ORDER BY p.tanggal DESC, p.id DESC, dp.id DESC";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ss", $dari_tanggal, $sampai_tanggal);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pageTitle = 'Laporan Penjualan - Toko Rahmat Jaya';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">';
require_once 'includes/head.php';
$navTitle = 'Laporan Penjualan';
$navBackUrl = 'index';
require_once 'includes/navbar.php';
require_once 'includes/flash.php';
?>

<div class="app-container">
    <div class="app-panel mb-6">
        <div class="app-panel-header">
            <span class="app-panel-title"><i class="ph ph-funnel text-amber-600"></i> Filter Laporan</span>
        </div>
        <div class="app-panel-body">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="app-label">Dari Tanggal</label>
                    <input type="date" name="dari" value="<?php echo htmlspecialchars($dari_tanggal); ?>" class="app-input">
                </div>
                <div>
                    <label class="app-label">Sampai Tanggal</label>
                    <input type="date" name="sampai" value="<?php echo htmlspecialchars($sampai_tanggal); ?>" class="app-input">
                </div>
                <button type="submit" class="btn btn-primary w-full md:w-auto"><i class="ph ph-magnifying-glass"></i> Tampilkan</button>
            </form>
        </div>
    </div>

    <div class="app-alert app-alert-info mb-6">
        <i class="ph ph-info text-xl text-brand shrink-0"></i>
        <span>Batalkan <strong>per item</strong> (ikon trash), <strong>seluruh transaksi</strong> (TRX-ID), atau centang beberapa baris lalu <strong>Batalkan Terpilih</strong>. Stok dikembalikan otomatis.</span>
    </div>

    <div id="bulkToolbar" class="app-panel mb-4 hidden">
        <div class="app-panel-body flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <span class="text-sm font-medium text-slate-700">
                <span id="selectedCount">0</span> item terpilih
            </span>
            <button type="button" id="bulkDeleteBtn" class="btn btn-danger w-full sm:w-auto">
                <i class="ph ph-trash"></i> Batalkan Terpilih
            </button>
        </div>
    </div>

    <div class="app-panel overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full mobile-card-table" id="laporanTable">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="px-3 py-3 text-center w-10">
                            <input type="checkbox" id="selectAllRows" class="w-4 h-4 rounded border-gray-300" title="Pilih semua">
                        </th>
                        <th class="px-4 py-3 text-left">Waktu Transaksi</th>
                        <th class="px-4 py-3 text-left">No. Transaksi</th>
                        <th class="px-4 py-3 text-left">Nama Barang</th>
                        <th class="px-4 py-3 text-center">Unit/Satuan</th>
                        <th class="px-4 py-3 text-center">Qty</th>
                        <th class="px-4 py-3 text-right">H. Beli</th>
                        <th class="px-4 py-3 text-right">Subtotal H. Beli</th>
                        <th class="px-4 py-3 text-right">H. Jual</th>
                        <th class="px-4 py-3 text-right">Subtotal H. Jual</th>
                        <th class="px-4 py-3 text-right">Keuntungan</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $grand_total_penjualan = 0;
                    $grand_total_beli = 0;
                    $grand_total_keuntungan = 0;
                    $row_count = 0;
                    $shown_penjualan = [];

                    while ($row = mysqli_fetch_assoc($result)):
                        $row_count++;
                        $subtotal_beli = hitungModalDetail($row);
                        $keuntungan = hitungKeuntunganDetail($row);
                        $grand_total_penjualan += $row['subtotal'];
                        $grand_total_beli += $subtotal_beli;
                        $grand_total_keuntungan += $keuntungan;
                        $penjualan_id = (int)$row['penjualan_id'];
                        $is_first_of_transaksi = !isset($shown_penjualan[$penjualan_id]);
                        $shown_penjualan[$penjualan_id] = true;
                    ?>
                        <tr class="border-b hover:bg-amber-50/40">
                            <td class="px-3 py-3 text-center" data-label="Pilih">
                                <input type="checkbox"
                                    class="row-checkbox w-4 h-4 rounded border-gray-300"
                                    value="<?php echo (int)$row['detail_id']; ?>"
                                    data-penjualan-id="<?php echo $penjualan_id; ?>">
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600" data-label="Waktu"><?php echo date('d/m/Y H:i', strtotime($row['tanggal'])); ?></td>
                            <td class="px-4 py-3 text-sm" data-label="No. Transaksi">
                                <div class="font-semibold text-slate-600">TRX-<?php echo $penjualan_id; ?></div>
                                <?php if ($is_first_of_transaksi && (int)$row['item_count'] > 0): ?>
                                    <button type="button"
                                        class="delete-transaksi-btn mt-1 text-xs text-red-600 hover:text-red-800 font-medium inline-flex items-center gap-1"
                                        data-penjualan-id="<?php echo $penjualan_id; ?>"
                                        data-item-count="<?php echo (int)$row['item_count']; ?>">
                                        <i class="ph ph-x-circle"></i> Batalkan TRX-<?php echo $penjualan_id; ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3" data-label="Barang">
                                <div class="font-medium text-gray-800"><?php echo htmlspecialchars($row['nama_barang']); ?></div>
                            </td>
                            <td class="px-4 py-3 text-center" data-label="Unit">
                                <span class="badge badge-blue"><?php echo htmlspecialchars($row['unit']); ?></span>
                            </td>
                            <td class="px-4 py-3 text-center font-medium" data-label="Qty"><?php echo formatQty($row['jumlah']); ?></td>
                            <td class="px-4 py-3 text-right text-gray-600" data-label="H. Beli"><?php echo formatRupiah($row['harga_beli']); ?></td>
                            <td class="px-4 py-3 text-right text-gray-600" data-label="Sub. H. Beli"><?php echo formatRupiah($subtotal_beli); ?></td>
                            <td class="px-4 py-3 text-right text-gray-600" data-label="H. Jual"><?php echo formatRupiah($row['harga_satuan']); ?></td>
                            <td class="px-4 py-3 text-right font-bold text-amber-700" data-label="Sub. H. Jual"><?php echo formatRupiah($row['subtotal']); ?></td>
                            <td class="px-4 py-3 text-right font-bold <?php echo $keuntungan >= 0 ? 'text-green-600' : 'text-red-600'; ?>" data-label="Keuntungan"><?php echo formatRupiah($keuntungan); ?></td>
                            <td class="px-4 py-3 text-center" data-label="Aksi">
                                <button type="button"
                                    class="btn-icon btn-icon-delete delete-btn"
                                    title="Batalkan item ini"
                                    data-delete-id="<?php echo (int)$row['detail_id']; ?>"
                                    data-nama-barang="<?php echo htmlspecialchars($row['nama_barang'], ENT_QUOTES); ?>"
                                    data-qty="<?php echo htmlspecialchars(formatQty($row['jumlah']) . ' ' . $row['unit']); ?>"
                                    data-penjualan-id="<?php echo $penjualan_id; ?>">
                                    <i class="ph ph-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($row_count === 0): ?>
                        <tr>
                            <td colspan="12" class="px-4 py-8 text-center text-gray-500">
                                Tidak ada data barang keluar untuk periode ini
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <?php if ($row_count > 0): ?>
                    <tfoot class="bg-gray-100">
                        <tr class="font-bold">
                            <td colspan="7" class="px-4 py-3 text-right" data-label="">TOTAL MODAL (H. BELI)</td>
                            <td class="px-4 py-3 text-right text-amber-700 text-lg" data-label="Total Modal"><?php echo formatRupiah($grand_total_beli); ?></td>
                            <td class="px-4 py-3 text-right" data-label="">TOTAL PENJUALAN</td>
                            <td class="px-4 py-3 text-right text-amber-700 text-lg" data-label="Total Penjualan"><?php echo formatRupiah($grand_total_penjualan); ?></td>
                            <td class="px-4 py-3" data-label=""></td>
                            <td class="px-4 py-3" data-label=""></td>
                        </tr>
                        <tr class="font-bold border-t-2">
                            <td colspan="10" class="px-4 py-3 text-right" data-label="">TOTAL KEUNTUNGAN / KERUGIAN</td>
                            <td colspan="2" class="px-4 py-3 text-right text-lg" data-label="Keuntungan">
                                <span class="<?php echo $grand_total_keuntungan >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo formatRupiah($grand_total_keuntungan); ?>
                                    <span class="text-sm">(<?php echo $grand_total_keuntungan >= 0 ? 'SURPLUS' : 'DEFISIT'; ?>)</span>
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<div id="deleteModal" class="app-modal-backdrop hidden">
    <div class="app-modal max-w-sm w-[calc(100%-2rem)]" onclick="event.stopPropagation()">
        <div class="p-5 text-center">
            <div class="w-16 h-16 rounded-full bg-red-100 text-red-600 flex items-center justify-center mx-auto mb-4 text-3xl">
                <i class="ph ph-warning-circle"></i>
            </div>
            <h3 class="text-lg font-bold mb-2" id="deleteModalTitle">Batalkan Penjualan?</h3>
            <p class="text-gray-500 text-sm mb-2" id="deleteModalDesc">Item berikut akan dihapus dari laporan:</p>
            <p class="font-semibold text-gray-800 mb-1" id="deleteItemName">—</p>
            <p class="text-xs text-slate-500 mb-1" id="deleteItemQty">—</p>
            <p class="text-xs text-slate-500 mb-6" id="deleteItemTransaksi">—</p>
            <p class="text-gray-500 text-sm mb-6">Stok barang akan dikembalikan. Tindakan ini tidak dapat dibatalkan.</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <button type="button" onclick="document.getElementById('deleteModal').classList.add('hidden')" class="btn btn-secondary flex-1">Batal</button>
                <button type="button" id="confirmDeleteBtn" class="btn btn-danger flex-1">Ya, Batalkan</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="assets/js/datatables-default.js"></script>
<script>
    $(function() {
        const filterParams = {
            dari: <?php echo json_encode($dari_tanggal); ?>,
            sampai: <?php echo json_encode($sampai_tanggal); ?>
        };

        initDefaultDataTable('#laporanTable', {
            order: [[1, 'desc']],
            columnDefs: [{
                orderable: false,
                targets: [0, -1]
            }]
        });

        let deleteMode = null;
        let deleteId = null;
        let deletePenjualanId = null;
        let deleteBulkIds = [];

        function openDeleteModal(options) {
            deleteMode = options.mode;
            deleteId = options.detailId || null;
            deletePenjualanId = options.penjualanId || null;
            deleteBulkIds = options.bulkIds || [];

            $('#deleteModalTitle').text(options.title || 'Batalkan Penjualan?');
            $('#deleteModalDesc').text(options.desc || 'Item berikut akan dihapus dari laporan:');
            $('#deleteItemName').text(options.itemName || '—').toggle(!!options.itemName);
            $('#deleteItemQty').text(options.itemQty || '—').toggle(!!options.itemQty);
            $('#deleteItemTransaksi').text(options.transaksi || '—').toggle(!!options.transaksi);
            $('#deleteModal').removeClass('hidden');
        }

        function updateBulkToolbar() {
            const checked = $('.row-checkbox:checked');
            const count = checked.length;
            $('#selectedCount').text(count);
            $('#bulkToolbar').toggleClass('hidden', count === 0);
            $('#selectAllRows').prop('checked', count > 0 && count === $('.row-checkbox').length);
        }

        $('#laporanTable').on('change', '.row-checkbox', updateBulkToolbar);

        $('#selectAllRows').on('change', function() {
            const checked = this.checked;
            $('.row-checkbox').prop('checked', checked);
            updateBulkToolbar();
        });

        $('#laporanTable').on('click', '.delete-btn', function() {
            openDeleteModal({
                mode: 'item',
                detailId: $(this).data('delete-id'),
                title: 'Batalkan Item?',
                desc: 'Item berikut akan dihapus dari laporan:',
                itemName: $(this).data('nama-barang'),
                itemQty: 'Qty: ' + $(this).data('qty'),
                transaksi: 'Transaksi TRX-' + $(this).data('penjualan-id')
            });
        });

        $('#laporanTable').on('click', '.delete-transaksi-btn', function() {
            const penjualanId = $(this).data('penjualan-id');
            const itemCount = $(this).data('item-count');
            openDeleteModal({
                mode: 'transaksi',
                penjualanId: penjualanId,
                title: 'Batalkan Seluruh Transaksi?',
                desc: 'Semua item dalam transaksi ini akan dihapus:',
                itemName: 'Transaksi TRX-' + penjualanId,
                itemQty: itemCount + ' item',
                transaksi: 'Stok semua barang akan dikembalikan'
            });
        });

        $('#bulkDeleteBtn').on('click', function() {
            const ids = $('.row-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (!ids.length) {
                return;
            }

            openDeleteModal({
                mode: 'bulk',
                bulkIds: ids,
                title: 'Batalkan Item Terpilih?',
                desc: 'Item yang dipilih akan dihapus dari laporan:',
                itemName: ids.length + ' item terpilih',
                itemQty: '',
                transaksi: 'Stok barang terkait akan dikembalikan'
            });
        });

        $('#confirmDeleteBtn').on('click', function() {
            const params = new URLSearchParams(filterParams);

            if (deleteMode === 'item' && deleteId) {
                params.set('delete', deleteId);
            } else if (deleteMode === 'transaksi' && deletePenjualanId) {
                params.set('delete_penjualan', deletePenjualanId);
            } else if (deleteMode === 'bulk' && deleteBulkIds.length) {
                params.set('delete_bulk', deleteBulkIds.join(','));
            } else {
                return;
            }

            window.location.href = '?' + params.toString();
        });

        document.querySelectorAll('.app-modal-backdrop').forEach(function(modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                }
            });
        });
    });
</script>
<?php require_once 'includes/footer.php'; ?>
