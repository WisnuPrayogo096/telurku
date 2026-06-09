<?php
require_once 'config.php';
requireLogin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['kurangi_stok'])) {
    $tanggal = getCurrentDate();
    $barang_ids = $_POST['barang_id'] ?? [];
    $saved = 0;

    mysqli_begin_transaction($conn);
    try {
        foreach ($barang_ids as $idx => $raw_barang_id) {
            $barang_id = (int)$raw_barang_id;
            if ($barang_id <= 0) continue;

            $jumlah_kurang = (float)($_POST['jumlah_kurang'][$idx] ?? 0);
            $keterangan = trim($_POST['keterangan'][$idx] ?? '');
            if ($keterangan === '') $keterangan = 'Keperluan pribadi';

            $query_barang = mysqli_query($conn, "SELECT * FROM barang WHERE id = $barang_id");
            $barang = mysqli_fetch_assoc($query_barang);
            if (!$barang) throw new Exception('Barang tidak ditemukan!');
            if ($jumlah_kurang <= 0) throw new Exception('Jumlah pengurangan harus lebih dari 0!');

            $jumlah_input = $jumlah_kurang;
            if ($barang['unit_type'] === 'renteng') {
                $jumlah_kurang = $jumlah_input * max((int)$barang['isi_renteng'], 1);
            }

            if ((float)$barang['stok'] < $jumlah_kurang) {
                throw new Exception('Stok tidak cukup untuk barang ' . $barang['nama_barang'] . '!');
            }

            $query_insert = "INSERT INTO stok_keluar (tanggal, barang_id, jumlah_kurang, keterangan) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query_insert);
            mysqli_stmt_bind_param($stmt, "sids", $tanggal, $barang_id, $jumlah_kurang, $keterangan);
            mysqli_stmt_execute($stmt);

            $query_update = "UPDATE barang SET stok = stok - ? WHERE id = ?";
            $stmt2 = mysqli_prepare($conn, $query_update);
            mysqli_stmt_bind_param($stmt2, "di", $jumlah_kurang, $barang_id);
            mysqli_stmt_execute($stmt2);
            $saved++;
        }

        if ($saved === 0) throw new Exception('Minimal pilih 1 barang.');

        mysqli_commit($conn);
        $_SESSION['flash_success'] = $saved . ' pengurangan stok berhasil disimpan!';
        header("Location: pengurangan_stok");
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = $e->getMessage();
    }
}

// Ambil list barang
$barang_query = "SELECT id, nama_barang, unit_type, isi_renteng, stok FROM barang WHERE stok > 0 ORDER BY nama_barang ASC";
$barang_result = mysqli_query($conn, $barang_query);
$barang_options = '';
while ($brg = mysqli_fetch_assoc($barang_result)) {
    $sisa_label = ($brg['unit_type'] === 'renteng' && (int)$brg['isi_renteng'] > 0)
        ? formatQty($brg['stok'] / max((int)$brg['isi_renteng'], 1)) . ' renteng'
        : formatQty($brg['stok']) . ' ' . unitLabel($brg['unit_type']);

    $barang_options .= sprintf(
        '<option value="%s" data-unit="%s">%s (Sisa: %s)</option>',
        $brg['id'],
        htmlspecialchars($brg['unit_type']),
        htmlspecialchars($brg['nama_barang']),
        $sisa_label
    );
}

$pageTitle = 'Tambah Pengurangan Stok - Toko Rahmat Jaya';
$extraHead = '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="includes/select2_theme.css">';
require_once 'includes/head.php';
$navTitle = 'Tambah Pengurangan Stok';
$navBackUrl = 'pengurangan_stok';
require_once 'includes/navbar.php';
?>

<div class="app-container max-w-4xl">
    <div class="form-page-header">
        <h1 class="form-page-title"><i class="ph ph-minus-circle text-red-600"></i> Form Tambah Pengurangan Stok</h1>
    </div>

    <?php if ($error): ?>
        <div class="app-alert app-alert-error mb-4 border-red-200 bg-red-50 text-red-800">
            <i class="ph ph-warning-circle text-xl shrink-0 text-red-500"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div id="kurangContainer" class="space-y-4">
            <!-- Items injected by JS -->
        </div>

        <div class="flex gap-3 justify-between mt-6 pt-4 border-t border-slate-200">
            <button type="button" class="btn btn-secondary" onclick="addKurangItem()">
                <i class="ph ph-plus"></i> Tambah Item Lagi
            </button>
            <div class="flex gap-2">
                <a href="pengurangan_stok" class="btn btn-secondary">Batal</a>
                <button type="submit" name="kurangi_stok" class="btn btn-danger shadow-md">
                    <i class="ph ph-floppy-disk"></i> Simpan Pengurangan Stok
                </button>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    let itemCounter = 0;
    const barangOptions = `<?php echo $barang_options; ?>`;

    function updateUnitLabel(selectElem, idx) {
        const selected = $(selectElem).find('option:selected');
        const unit = selected.data('unit');
        if (unit) {
            $(`#unitLabel_${idx}`).text('(' + (unit === 'renteng' ? 'renteng' : (unit === 'gram' || unit === 'kg' ? 'gram' : 'pcs')) + ')');
        } else {
            $(`#unitLabel_${idx}`).text('');
        }
    }

    function addKurangItem() {
        const idx = itemCounter++;
        const container = document.getElementById('kurangContainer');
        const block = document.createElement('div');
        block.className = 'app-panel mb-4 item-block border-l-4 border-l-red-500';

        block.innerHTML = `
            <div class="app-panel-header bg-red-50">
                <span class="app-panel-title text-red-800"><i class="ph ph-minus-circle text-red-500"></i> Pengeluaran #${idx + 1}</span>
                <button type="button" class="btn-icon btn-icon-delete" onclick="this.closest('.item-block').remove()" title="Hapus item">
                    <i class="ph ph-trash"></i>
                </button>
            </div>
            <div class="app-panel-body p-4 space-y-4">
                <div class="form-row">
                    <div class="form-row-full">
                        <label class="app-label">Pilih Barang</label>
                        <select name="barang_id[${idx}]" class="w-full kurang-barang" id="select_${idx}" required>
                            <option value="">-- Cari Barang --</option>
                            ${barangOptions}
                        </select>
                    </div>
                    <div class="form-row-full">
                        <div class="form-row">
                            <div>
                                <label class="app-label">Jumlah Keluar <span id="unitLabel_${idx}" class="text-red-600 font-normal"></span></label>
                                <input type="number" step="1" min="1" name="jumlah_kurang[${idx}]" class="app-input" required placeholder="Contoh: 2">
                            </div>
                            <div>
                                <label class="app-label">Keterangan</label>
                                <input type="text" name="keterangan[${idx}]" maxlength="255" value="Keperluan pribadi" placeholder="Contoh: Keperluan pribadi" class="app-input">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.appendChild(block);

        const $select = $(`#select_${idx}`);
        $select.select2({
            placeholder: '-- Cari Barang --',
            allowClear: true
        });

        $select.on('change', function() {
            updateUnitLabel(this, idx);
        });

        // Don't auto-open — let user click manually
    }

    // Add first item on load
    $(document).ready(function() {
        addKurangItem();
    });
</script>

<?php require_once 'includes/footer.php'; ?>