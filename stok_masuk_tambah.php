<?php
require_once 'config.php';
requireLogin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_stok'])) {
    $tanggal = getCurrentDate();
    $barang_ids = $_POST['barang_id'] ?? [];
    $saved = 0;

    mysqli_begin_transaction($conn);
    try {
        foreach ($barang_ids as $idx => $raw_barang_id) {
            $barang_id = (int)$raw_barang_id;
            if ($barang_id <= 0) continue;

            $jumlah_tambah = (float)($_POST['jumlah_tambah'][$idx] ?? 0);
            if ($jumlah_tambah <= 0) throw new Exception('Jumlah stok masuk harus lebih dari 0.');

            $harga_beli_raw = $_POST['harga_beli_baru'][$idx] ?? '';
            $harga_jual_raw = $_POST['harga_jual_baru'][$idx] ?? '';
            $harga_beli_baru = $harga_beli_raw !== '' ? (float)$harga_beli_raw : -1;
            $harga_jual_baru = $harga_jual_raw !== '' ? (float)$harga_jual_raw : -1;

            $query_barang = mysqli_query($conn, "SELECT * FROM barang WHERE id = $barang_id");
            $barang = mysqli_fetch_assoc($query_barang);
            if (!$barang) throw new Exception('Barang tidak ditemukan!');

            $jumlah_input = $jumlah_tambah;
            if ($barang['unit_type'] === 'renteng') {
                $jumlah_tambah = $jumlah_input * max((int)$barang['isi_renteng'], 1);
            }

            $harga_beli_insert = $harga_beli_baru >= 0 ? $harga_beli_baru : $barang['harga_beli'];
            $harga_jual_insert = $harga_jual_baru >= 0 ? $harga_jual_baru : $barang['harga_jual'];

            $query_insert = "INSERT INTO stok_masuk (tanggal, barang_id, jumlah_tambah, harga_beli, harga_jual) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query_insert);
            mysqli_stmt_bind_param($stmt, "siddd", $tanggal, $barang_id, $jumlah_tambah, $harga_beli_insert, $harga_jual_insert);
            mysqli_stmt_execute($stmt);

            $query_update_stok = "UPDATE barang SET stok = stok + ? WHERE id = ?";
            $stmt2 = mysqli_prepare($conn, $query_update_stok);
            mysqli_stmt_bind_param($stmt2, "di", $jumlah_tambah, $barang_id);
            mysqli_stmt_execute($stmt2);

            if ($harga_beli_baru >= 0 || $harga_jual_baru >= 0) {
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

                $query_update_harga = "UPDATE barang SET " . implode(", ", $updates) . " WHERE id = ?";
                $stmt3 = mysqli_prepare($conn, $query_update_harga);
                mysqli_stmt_bind_param($stmt3, $types, ...$params);
                mysqli_stmt_execute($stmt3);
            }
            $saved++;
        }

        if ($saved === 0) throw new Exception('Minimal pilih 1 barang.');

        mysqli_commit($conn);
        $_SESSION['flash_success'] = $saved . " stok masuk berhasil disimpan!";
        header("Location: stok_masuk");
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = $e->getMessage();
    }
}

// Ambil list barang
$barang_query = "SELECT id, nama_barang, unit_type, isi_renteng, stok, harga_beli, harga_jual FROM barang ORDER BY nama_barang ASC";
$barang_result = mysqli_query($conn, $barang_query);
$barang_options = '';
while ($brg = mysqli_fetch_assoc($barang_result)) {
    $sisa_label = ($brg['unit_type'] === 'renteng' && (int)$brg['isi_renteng'] > 0)
        ? formatQty($brg['stok'] / max((int)$brg['isi_renteng'], 1)) . ' renteng'
        : formatQty($brg['stok']) . ' ' . unitLabel($brg['unit_type']);

    $barang_options .= sprintf(
        '<option value="%s" data-unit="%s" data-harga-beli="%s" data-harga-jual="%s">%s (Sisa: %s)</option>',
        $brg['id'],
        htmlspecialchars($brg['unit_type']),
        (float)$brg['harga_beli'],
        (float)$brg['harga_jual'],
        htmlspecialchars($brg['nama_barang']),
        $sisa_label
    );
}

$pageTitle = 'Tambah Stok Masuk - Toko Rahmat Jaya';
$extraHead = '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="includes/select2_theme.css">';
require_once 'includes/head.php';
$navTitle = 'Tambah Stok Masuk';
$navBackUrl = 'stok_masuk';
require_once 'includes/navbar.php';
?>

<div class="app-container max-w-4xl">
    <div class="form-page-header">
        <h1 class="form-page-title"><i class="ph ph-archive-box"></i> Form Tambah Stok Masuk</h1>
    </div>

    <?php if ($error): ?>
        <div class="app-alert app-alert-error mb-4 border-red-200 bg-red-50 text-red-800">
            <i class="ph ph-warning-circle text-xl shrink-0 text-red-500"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div id="stokContainer" class="space-y-4">
            <!-- Items injected by JS -->
        </div>

        <div class="flex gap-3 justify-between mt-6 pt-4 border-t border-slate-200">
            <button type="button" class="btn btn-secondary" onclick="addStokItem()">
                <i class="ph ph-plus"></i> Tambah Item Lagi
            </button>
            <div class="flex gap-2">
                <a href="stok_masuk" class="btn btn-secondary">Batal</a>
                <button type="submit" name="tambah_stok" class="btn btn-primary shadow-md">
                    <i class="ph ph-floppy-disk"></i> Simpan Stok Masuk
                </button>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    const rupiahFmt = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    let itemCounter = 0;
    const barangOptions = `<?php echo $barang_options; ?>`;

    function updateHargaSaatIni(selectElem, idx) {
        const selected = $(selectElem).find('option:selected');
        const val = $(selectElem).val();
        const box = $(`#infoBox_${idx}`);

        if (!val) {
            box.addClass('hidden');
            $(`#unitLabel_${idx}`).text('');
            return;
        }

        const unit = selected.data('unit');
        $(`#unitLabel_${idx}`).text('(' + (unit === 'renteng' ? 'renteng' : (unit === 'gram' || unit === 'kg' ? 'gram' : 'pcs')) + ')');

        const hargaBeli = parseFloat(selected.data('harga-beli'));
        const hargaJual = parseFloat(selected.data('harga-jual'));

        box.removeClass('hidden');
        $(`#hargaBeliSaatIni_${idx}`).text(hargaBeli > 0 ? rupiahFmt.format(hargaBeli) : 'Belum diisi');
        $(`#hargaJualSaatIni_${idx}`).text(hargaJual > 0 ? rupiahFmt.format(hargaJual) : 'Belum diisi');
    }

    function addStokItem() {
        const idx = itemCounter++;
        const container = document.getElementById('stokContainer');
        const block = document.createElement('div');
        block.className = 'app-panel mb-4 item-block border-l-4 border-l-brand';

        block.innerHTML = `
            <div class="app-panel-header bg-brand-light">
                <span class="app-panel-title"><i class="ph ph-archive-box text-brand"></i> Stok Item #${idx + 1}</span>
                <button type="button" class="btn-icon btn-icon-delete" onclick="this.closest('.item-block').remove()" title="Hapus item">
                    <i class="ph ph-trash"></i>
                </button>
            </div>
            <div class="app-panel-body p-4 space-y-4">
                <div class="form-row-full">
                    <label class="app-label">Pilih Barang</label>
                    <select name="barang_id[${idx}]" class="w-full stok-barang" id="select_${idx}" required>
                        <option value="">-- Cari Barang --</option>
                        ${barangOptions}
                    </select>
                </div>
                <div class="form-row-full hidden" id="infoBox_${idx}">
                    <div class="p-3 bg-brand-lighter border border-blue-200 rounded-lg">
                        <p class="text-xs font-bold text-slate-700 mb-2 uppercase tracking-wide"><i class="ph ph-tag text-brand"></i> Harga saat ini</p>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div class="bg-white rounded border border-slate-200 px-3 py-2">
                                <span class="text-slate-500 block text-xs">Harga Beli</span>
                                <span id="hargaBeliSaatIni_${idx}" class="font-bold text-slate-800">—</span>
                            </div>
                            <div class="bg-white rounded border border-slate-200 px-3 py-2">
                                <span class="text-slate-500 block text-xs">Harga Jual</span>
                                <span id="hargaJualSaatIni_${idx}" class="font-bold text-brand-dark">—</span>
                            </div>
                        </div>
                        <p class="text-[0.7rem] text-slate-500 mt-2">Opsional: Isi form bawah jika ingin update harga.</p>
                    </div>
                </div>
                <div class="form-row-full">
                    <div class="form-row">
                        <div>
                            <label class="app-label">Jumlah Tambah <span id="unitLabel_${idx}" class="text-brand font-normal"></span></label>
                            <input type="number" step="1" min="1" name="jumlah_tambah[${idx}]" class="app-input" required placeholder="Contoh: 10">
                        </div>
                        <div>
                            <label class="app-label">Harga Beli Baru <span class="text-xs text-gray-400 font-normal">(Opsional)</span></label>
                            <input type="number" step="0.01" name="harga_beli_baru[${idx}]" class="app-input" placeholder="Opsional">
                        </div>
                        <div>
                            <label class="app-label">Harga Jual Baru <span class="text-xs text-gray-400 font-normal">(Opsional)</span></label>
                            <input type="number" step="0.01" name="harga_jual_baru[${idx}]" class="app-input" placeholder="Opsional">
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
            updateHargaSaatIni(this, idx);
        });

        // Auto open select2 on new item
        setTimeout(() => $select.select2('open'), 50);
    }

    // Add first item on load
    $(document).ready(function() {
        addStokItem();
    });
</script>

<?php require_once 'includes/footer.php'; ?>