<?php
require_once 'config.php';
requireLogin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_tambah'])) {
    $names = $_POST['nama_barang'] ?? [];
    $saved = 0;

    mysqli_begin_transaction($conn);
    try {
        foreach ($names as $idx => $raw_name) {
            $nama_barang = trim($raw_name);
            if ($nama_barang === '') continue;

            $unit_type = $_POST['unit_type'][$idx] ?? 'pcs';
            $harga_beli = (float)($_POST['harga_beli'][$idx] ?? 0);
            
            $harga_jual = 0;
            $harga_jual_renteng = 0;
            $harga_jual_pcs = 0;
            $isi_renteng = 0;
            $stok = 0;

            if ($unit_type === 'gram') {
                $harga_jual = (float)($_POST['harga_jual'][$idx] ?? 0);
                $stok = (float)($_POST['stok_gram'][$idx] ?? 0);
                if ($harga_jual <= 0 || $stok < 0) throw new Exception('Harga jual dan stok gram wajib diisi dengan benar!');
            } elseif ($unit_type === 'renteng') {
                $harga_jual_renteng = (float)($_POST['harga_jual_renteng'][$idx] ?? 0);
                $harga_jual_pcs = (float)($_POST['harga_jual_pcs'][$idx] ?? 0);
                $isi_renteng = (int)($_POST['isi_renteng'][$idx] ?? 0);
                $stok_raw = (float)($_POST['stok_renteng'][$idx] ?? 0);
                $harga_jual = $harga_jual_pcs;
                $stok = $stok_raw * max($isi_renteng, 1);
                if ($harga_jual_renteng <= 0 || $harga_jual_pcs <= 0 || $isi_renteng <= 0 || $stok_raw < 0) {
                    throw new Exception('Harga renteng, harga ecer, isi ecer per renteng/slop, dan stok renteng wajib diisi dengan benar!');
                }
            } else {
                $unit_type = 'pcs';
                $harga_jual_pcs = (float)($_POST['harga_jual_pcs'][$idx] ?? 0);
                $stok = (float)($_POST['stok_ecer'][$idx] ?? 0);
                $harga_jual = $harga_jual_pcs;
                if ($harga_jual_pcs <= 0 || $stok < 0) throw new Exception('Harga jual per pcs dan stok pcs wajib diisi dengan benar!');
            }

            $query = "INSERT INTO barang (nama_barang, unit_type, isi_renteng, harga_beli, harga_jual, harga_jual_renteng, harga_jual_pcs, stok)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ssiddddd", $nama_barang, $unit_type, $isi_renteng, $harga_beli, $harga_jual, $harga_jual_renteng, $harga_jual_pcs, $stok);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Gagal menyimpan data!');
            }
            $saved++;
        }

        if ($saved === 0) {
            throw new Exception('Minimal isi 1 barang.');
        }

        mysqli_commit($conn);
        $_SESSION['flash_success'] = $saved . ' data barang berhasil ditambahkan!';
        header("Location: barang");
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = $e->getMessage();
    }
}

$pageTitle = 'Tambah Barang Baru - Toko Rahmat Jaya';
require_once 'includes/head.php';
$navTitle = 'Tambah Barang Baru';
$navBackUrl = 'barang';
require_once 'includes/navbar.php';
require_once 'includes/flash.php';
?>

<div class="app-container max-w-4xl">
    <div class="form-page-header">
        <h1 class="form-page-title"><i class="ph ph-plus-circle"></i> Form Tambah Barang</h1>
    </div>

    <?php if ($error): ?>
        <div class="app-alert app-alert-error mb-4 border-red-200 bg-red-50 text-red-800">
            <i class="ph ph-warning-circle text-xl shrink-0 text-red-500"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="formTambahBarang">
        <div id="formContainer">
            <!-- Form items will be injected here via JS -->
        </div>

        <div class="flex gap-3 justify-between mt-6 pt-4 border-t border-slate-200">
            <button type="button" class="btn btn-secondary" onclick="addFormItem()">
                <i class="ph ph-plus"></i> Tambah Item Lagi
            </button>
            <div class="flex gap-2">
                <a href="barang" class="btn btn-secondary">Batal</a>
                <button type="submit" name="submit_tambah" class="btn btn-primary shadow-md">
                    <i class="ph ph-floppy-disk"></i> Simpan Semua Barang
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    let itemCounter = 0;

    function renderUnitFields(container, unitType, idx) {
        let html = '';
        if (unitType === 'renteng') {
            html = `
                <div class="form-item-card-header bg-brand-lighter">Detail Satuan: Renteng/Slop</div>
                <div class="form-item-card-body form-row">
                    <div>
                        <label class="app-label">Harga Beli per Renteng/Slop</label>
                        <input type="number" step="0.01" name="harga_beli[${idx}]" id="hb_renteng_${idx}" class="app-input" required>
                    </div>
                    <div>
                        <label class="app-label">Isi pcs per Renteng/Slop</label>
                        <input type="number" name="isi_renteng[${idx}]" id="isi_renteng_${idx}" class="app-input" required>
                    </div>
                    <div class="form-row-full mt-2 mb-2">
                        <button type="button" class="btn-generate" onclick="generateRenteng(${idx})"><i class="ph ph-magic-wand"></i> Generate Harga Jual</button>
                    </div>
                    <div>
                        <label class="app-label">Harga Jual per Renteng/Slop</label>
                        <input type="number" step="0.01" name="harga_jual_renteng[${idx}]" id="hj_renteng_${idx}" class="app-input generated-field" required>
                    </div>
                    <div>
                        <label class="app-label">Harga Jual per Pcs</label>
                        <input type="number" step="0.01" name="harga_jual_pcs[${idx}]" id="hj_pcs_${idx}" class="app-input generated-field" required>
                    </div>
                    <div>
                        <label class="app-label">Stok (Renteng/Slop)</label>
                        <input type="number" step="0.01" name="stok_renteng[${idx}]" class="app-input" required>
                    </div>
                </div>
            `;
        } else if (unitType === 'gram') {
            html = `
                <div class="form-item-card-header bg-brand-lighter">Detail Satuan: Gram</div>
                <div class="form-item-card-body form-row">
                    <div>
                        <label class="app-label">Harga Beli</label>
                        <input type="number" step="0.01" name="harga_beli[${idx}]" id="hb_gram_${idx}" class="app-input" required>
                    </div>
                    <div>
                        <label class="app-label">Stok (gram)</label>
                        <input type="number" name="stok_gram[${idx}]" id="stok_gram_${idx}" class="app-input" required>
                    </div>
                    <div class="form-row-full mt-2 mb-2">
                        <button type="button" class="btn-generate" onclick="generateGram(${idx})"><i class="ph ph-magic-wand"></i> Generate Harga Jual</button>
                    </div>
                    <div class="form-row-full">
                        <label class="app-label">Harga Jual per Gram</label>
                        <input type="number" step="0.0001" name="harga_jual[${idx}]" id="hj_gram_${idx}" class="app-input generated-field" required>
                        <p class="text-xs text-gray-500 mt-1">Bisa pakai koma desimal.</p>
                    </div>
                </div>
            `;
        } else {
            html = `
                <div class="form-item-card-header bg-brand-lighter">Detail Satuan: PCS Kemasan</div>
                <div class="form-item-card-body form-row">
                    <div class="form-row-full">
                        <label class="app-label">Harga Beli</label>
                        <input type="number" step="0.01" name="harga_beli[${idx}]" class="app-input" required>
                    </div>
                    <div>
                        <label class="app-label">Harga Jual per Pcs</label>
                        <input type="number" step="0.01" name="harga_jual_pcs[${idx}]" class="app-input" required>
                    </div>
                    <div>
                        <label class="app-label">Stok Pcs</label>
                        <input type="number" step="0.01" name="stok_ecer[${idx}]" class="app-input" required>
                    </div>
                </div>
            `;
        }
        container.innerHTML = html;
    }

    function generateRenteng(idx) {
        const hb = parseFloat(document.getElementById(`hb_renteng_${idx}`).value) || 0;
        const isi = parseInt(document.getElementById(`isi_renteng_${idx}`).value) || 1;
        document.getElementById(`hj_renteng_${idx}`).value = hb;
        document.getElementById(`hj_pcs_${idx}`).value = hb / isi;
    }

    function generateGram(idx) {
        const hb = parseFloat(document.getElementById(`hb_gram_${idx}`).value) || 0;
        const stok = parseInt(document.getElementById(`stok_gram_${idx}`).value) || 1;
        document.getElementById(`hj_gram_${idx}`).value = hb / stok;
    }

    function addFormItem() {
        const idx = itemCounter++;
        const container = document.getElementById('formContainer');
        
        const card = document.createElement('div');
        card.className = 'app-panel mb-6 border-brand border-2 overflow-hidden item-block';
        
        card.innerHTML = `
            <div class="app-panel-header bg-brand-light">
                <span class="app-panel-title">Barang #${idx + 1}</span>
                <button type="button" class="btn-icon btn-icon-delete" onclick="this.closest('.item-block').remove()">
                    <i class="ph ph-trash"></i>
                </button>
            </div>
            <div class="app-panel-body p-4 space-y-4">
                <div class="form-row">
                    <div class="form-row-full">
                        <label class="app-label">Nama Barang <span class="text-red-500">*</span></label>
                        <input type="text" name="nama_barang[${idx}]" class="app-input focus:ring focus:ring-brand focus:ring-opacity-50" required placeholder="Contoh: Indomie Goreng">
                    </div>
                    <div class="form-row-full">
                        <label class="app-label">Satuan Utama <span class="text-red-500">*</span></label>
                        <select name="unit_type[${idx}]" class="app-input unit-select" required>
                            <option value="renteng">Ecer dan Renteng/Slop</option>
                            <option value="gram">Gram</option>
                            <option value="pcs">PCS Kemasan</option>
                        </select>
                    </div>
                </div>
                <div class="form-item-card unit-fields-container shadow-none border-dashed border-2">
                    <!-- Fields here -->
                </div>
            </div>
        `;
        
        container.appendChild(card);
        
        const unitSelect = card.querySelector('.unit-select');
        const unitContainer = card.querySelector('.unit-fields-container');
        
        unitSelect.addEventListener('change', (e) => {
            renderUnitFields(unitContainer, e.target.value, idx);
        });
        
        // Initial render
        renderUnitFields(unitContainer, 'renteng', idx);
        
        // Focus on first input
        card.querySelector('input[type="text"]').focus();
    }

    // Add first item on load
    document.addEventListener('DOMContentLoaded', () => {
        addFormItem();
    });
</script>

<?php require_once 'includes/footer.php'; ?>
