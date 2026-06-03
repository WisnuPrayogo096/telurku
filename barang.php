<?php
require_once 'config.php';
requireLogin();

$success = '';
$error = '';

// Proses Edit Barang (hanya dari modal Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $nama_barang = trim($_POST['nama_barang'] ?? '');
    $unit_type = $_POST['unit_type'] ?? 'pcs';
    $harga_beli = (float)($_POST['harga_beli'] ?? 0);
    
    $harga_jual = 0;
    $harga_jual_renteng = 0;
    $harga_jual_pcs = 0;
    $isi_renteng = 0;
    $stok = 0;

    mysqli_begin_transaction($conn);
    try {
        if ($nama_barang === '') {
            throw new Exception('Nama barang wajib diisi!');
        }
        
        if ($unit_type === 'gram') {
            $harga_jual = (float)($_POST['harga_jual'] ?? 0);
            $stok = (float)($_POST['stok_gram'] ?? 0);
            if ($harga_jual <= 0 || $stok < 0) throw new Exception('Harga jual dan stok gram wajib diisi dengan benar!');
        } elseif ($unit_type === 'renteng') {
            $harga_jual_renteng = (float)($_POST['harga_jual_renteng'] ?? 0);
            $harga_jual_pcs = (float)($_POST['harga_jual_pcs'] ?? 0);
            $isi_renteng = (int)($_POST['isi_renteng'] ?? 0);
            $stok_raw = (float)($_POST['stok_renteng'] ?? 0);
            $harga_jual = $harga_jual_pcs;
            $stok = $stok_raw * max($isi_renteng, 1);
            if ($harga_jual_renteng <= 0 || $harga_jual_pcs <= 0 || $isi_renteng <= 0 || $stok_raw < 0) {
                throw new Exception('Harga renteng, harga ecer, isi ecer per renteng/slop, dan stok renteng wajib diisi dengan benar!');
            }
        } else {
            $unit_type = 'pcs';
            $harga_jual_pcs = (float)($_POST['harga_jual_pcs'] ?? 0);
            $stok = (float)($_POST['stok_ecer'] ?? 0);
            $harga_jual = $harga_jual_pcs;
            if ($harga_jual_pcs <= 0 || $stok < 0) throw new Exception('Harga jual per pcs dan stok pcs wajib diisi dengan benar!');
        }

        $query = "UPDATE barang SET nama_barang=?, unit_type=?, isi_renteng=?, harga_beli=?, harga_jual=?, harga_jual_renteng=?, harga_jual_pcs=?, stok=? WHERE id=?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssidddddi", $nama_barang, $unit_type, $isi_renteng, $harga_beli, $harga_jual, $harga_jual_renteng, $harga_jual_pcs, $stok, $id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Gagal menyimpan perubahan!');
        }
        
        mysqli_commit($conn);
        $success = 'Data barang berhasil diperbarui!';
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = $e->getMessage();
    }
}

// Proses Hapus
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $check_stmt = mysqli_prepare($conn, "SELECT id FROM barang WHERE id=?");
    mysqli_stmt_bind_param($check_stmt, "i", $id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    
    if (!mysqli_fetch_assoc($result)) {
        $error = 'Data barang tidak ditemukan!';
    } else {
        $del_stmt = mysqli_prepare($conn, "DELETE FROM barang WHERE id=?");
        mysqli_stmt_bind_param($del_stmt, "i", $id);
        mysqli_stmt_execute($del_stmt);
        $success = 'Data berhasil dihapus!';
    }
}

// Ambil data barang
$query = "SELECT b.* FROM barang b ORDER BY b.id DESC";
$result = mysqli_query($conn, $query);
$pageTitle = 'Data Barang - Toko Rahmat Jaya';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">';
require_once 'includes/head.php';
$navTitle = 'Data Barang';
$navBackUrl = 'index';
require_once 'includes/navbar.php';
require_once 'includes/flash.php';
?>

<div class="app-container">
    <div class="app-panel overflow-hidden">
        <div class="app-panel-header">
            <span class="app-panel-title"><i class="ph ph-package text-brand"></i> Daftar Barang</span>
            <a href="barang_tambah" class="btn btn-primary">
                <i class="ph ph-plus-circle"></i> Tambah Barang
            </a>
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
                        <tr class="border-b hover:bg-blue-50 barang-row">
                            <td class="px-4 py-3"><?php echo $no++; ?></td>
                            <td class="px-4 py-3 font-medium text-gray-800 row-nama"><?php echo htmlspecialchars($row['nama_barang']); ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="badge badge-blue">
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
                                <span class="badge <?php echo $row['stok'] < 10 ? 'badge-red' : 'badge-green'; ?>">
                                    <?php echo (($row['unit_type'] ?? 'pcs') === 'renteng' && (int)$row['isi_renteng'] > 0) ? formatQty($row['stok'] / max((int)$row['isi_renteng'], 1)) . ' renteng' : formatQty($row['stok']) . ' ' . unitLabel($row['unit_type'] ?? 'pcs'); ?>
                                </span>
                                <?php if (($row['unit_type'] ?? 'pcs') === 'renteng' && $row['isi_renteng']): ?>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <?php if ($row['isi_renteng']): ?>Per Renteng/Slop isi <?php echo $row['isi_renteng']; ?> pcs<?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <button type="button" class="btn-icon btn-icon-edit mr-1" title="Edit"
                                    onclick='editBarang(<?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                    <i class="ph ph-pencil-simple"></i>
                                </button>
                                <button type="button" class="btn-icon btn-icon-delete delete-btn" title="Hapus"
                                    data-delete-id="<?php echo $row['id']; ?>">
                                    <i class="ph ph-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modalForm" class="app-modal-backdrop hidden">
    <div class="app-modal" onclick="event.stopPropagation()">
        <div class="app-modal-header shrink-0">
            <h2 class="text-xl font-bold flex items-center gap-2">
                <i class="ph ph-pencil-simple text-brand"></i> Edit Barang
            </h2>
            <button type="button" onclick="closeModal()" class="btn btn-secondary"><i class="ph ph-x"></i></button>
        </div>
        <div class="p-5 overflow-y-auto">
            <form method="POST" action="" id="formBarang">
                <input type="hidden" name="id" id="editId">
                <div class="space-y-4">
                    <div class="form-row">
                        <div class="form-row-full">
                            <label class="app-label">Nama Barang <span class="text-red-500">*</span></label>
                            <input type="text" name="nama_barang" id="editNama" class="app-input" required>
                        </div>
                        <div class="form-row-full">
                            <label class="app-label">Satuan Utama <span class="text-red-500">*</span></label>
                            <select name="unit_type" id="editUnitType" class="app-input" required>
                                <option value="renteng">Ecer dan Renteng/Slop</option>
                                <option value="gram">Gram</option>
                                <option value="pcs">PCS Kemasan</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="unitFields" class="form-item-card">
                        <!-- Fields diisi via JS -->
                    </div>

                    <div class="d-flex gap-2 justify-content-end pt-3">
                        <button type="button" onclick="closeModal()" class="btn btn-secondary">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Simpan Perubahan</button>
                    </div>
                </div>
            </form>
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
            <h3 class="text-lg font-bold mb-2">Hapus Barang?</h3>
            <p class="text-gray-500 text-sm mb-6">Barang yang dihapus tidak dapat dikembalikan. Lanjutkan?</p>
            <div class="flex gap-3 justify-center">
                <button type="button" onclick="document.getElementById('deleteModal').classList.add('hidden')" class="btn btn-secondary flex-1">Batal</button>
                <button type="button" id="confirmDeleteBtn" class="btn btn-danger flex-1">Ya, Hapus</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="assets/js/datatables-default.js"></script>
<script>
    $(function() {
        initDefaultDataTable('#barangTable');
    });

    const modal = document.getElementById('modalForm');
    const unitSelect = document.getElementById('editUnitType');
    const unitFields = document.getElementById('unitFields');

    function renderFields(unitType, data = {}) {
        let html = '';
        if (unitType === 'renteng') {
            html = `
                <div class="form-item-card-header">Detail Satuan: Renteng/Slop</div>
                <div class="form-item-card-body form-row">
                    <div>
                        <label class="app-label">Harga Beli per Renteng/Slop</label>
                        <input type="number" step="0.01" name="harga_beli" id="hb_renteng" class="app-input" value="${data.harga_beli || ''}" required>
                    </div>
                    <div>
                        <label class="app-label">Isi pcs per Renteng/Slop</label>
                        <input type="number" name="isi_renteng" id="isi_renteng" class="app-input" value="${data.isi_renteng || ''}" required>
                    </div>
                    <div class="form-row-full mt-2 mb-2">
                        <button type="button" class="btn-generate" onclick="generateRenteng()"><i class="ph ph-magic-wand"></i> Generate Harga Jual</button>
                    </div>
                    <div>
                        <label class="app-label">Harga Jual per Renteng/Slop</label>
                        <input type="number" step="0.01" name="harga_jual_renteng" id="hj_renteng" class="app-input generated-field" value="${data.harga_jual_renteng || ''}" required>
                    </div>
                    <div>
                        <label class="app-label">Harga Jual per Pcs</label>
                        <input type="number" step="0.01" name="harga_jual_pcs" id="hj_pcs" class="app-input generated-field" value="${data.harga_jual_pcs || ''}" required>
                    </div>
                    <div>
                        <label class="app-label">Stok (Renteng/Slop)</label>
                        <input type="number" step="0.01" name="stok_renteng" class="app-input" value="${(data.stok && data.isi_renteng) ? (data.stok / data.isi_renteng) : 0}" required>
                    </div>
                </div>
            `;
        } else if (unitType === 'gram') {
            html = `
                <div class="form-item-card-header">Detail Satuan: Gram</div>
                <div class="form-item-card-body form-row">
                    <div>
                        <label class="app-label">Harga Beli</label>
                        <input type="number" step="0.01" name="harga_beli" id="hb_gram" class="app-input" value="${data.harga_beli || ''}" required>
                    </div>
                    <div>
                        <label class="app-label">Stok (gram)</label>
                        <input type="number" name="stok_gram" id="stok_gram" class="app-input" value="${data.stok || ''}" required>
                    </div>
                    <div class="form-row-full mt-2 mb-2">
                        <button type="button" class="btn-generate" onclick="generateGram()"><i class="ph ph-magic-wand"></i> Generate Harga Jual</button>
                    </div>
                    <div class="form-row-full">
                        <label class="app-label">Harga Jual per Gram</label>
                        <input type="number" step="0.0001" name="harga_jual" id="hj_gram" class="app-input generated-field" value="${data.harga_jual || ''}" required>
                        <p class="text-xs text-gray-500 mt-1">Bisa pakai koma desimal.</p>
                    </div>
                </div>
            `;
        } else {
            html = `
                <div class="form-item-card-header">Detail Satuan: PCS Kemasan</div>
                <div class="form-item-card-body form-row">
                    <div class="form-row-full">
                        <label class="app-label">Harga Beli</label>
                        <input type="number" step="0.01" name="harga_beli" class="app-input" value="${data.harga_beli || ''}" required>
                    </div>
                    <div>
                        <label class="app-label">Harga Jual per Pcs</label>
                        <input type="number" step="0.01" name="harga_jual_pcs" class="app-input" value="${data.harga_jual_pcs || data.harga_jual || ''}" required>
                    </div>
                    <div>
                        <label class="app-label">Stok Pcs</label>
                        <input type="number" step="0.01" name="stok_ecer" class="app-input" value="${data.stok || ''}" required>
                    </div>
                </div>
            `;
        }
        unitFields.innerHTML = html;
    }

    unitSelect.addEventListener('change', (e) => {
        renderFields(e.target.value);
    });

    function generateRenteng() {
        const hb = parseFloat(document.getElementById('hb_renteng').value) || 0;
        const isi = parseInt(document.getElementById('isi_renteng').value) || 1;
        document.getElementById('hj_renteng').value = hb;
        document.getElementById('hj_pcs').value = hb / isi;
    }

    function generateGram() {
        const hb = parseFloat(document.getElementById('hb_gram').value) || 0;
        const stok = parseInt(document.getElementById('stok_gram').value) || 1;
        document.getElementById('hj_gram').value = hb / stok;
    }

    function editBarang(data) {
        document.getElementById('editId').value = data.id;
        document.getElementById('editNama').value = data.nama_barang;
        
        let unit = data.unit_type;
        if (unit === 'kg') unit = 'gram';
        unitSelect.value = unit;
        
        renderFields(unit, data);
        
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    // Custom Delete Modal Logic
    let deleteId = null;
    document.querySelectorAll('.delete-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            deleteId = btn.dataset.deleteId;
            document.getElementById('deleteModal').classList.remove('hidden');
        });
    });

    document.getElementById('confirmDeleteBtn').addEventListener('click', () => {
        if (deleteId) {
            window.location.href = `?delete=${encodeURIComponent(deleteId)}`;
        }
    });

    document.querySelectorAll('.app-modal-backdrop').forEach(m => {
        m.addEventListener('click', function(e) {
            if (e.target === this) this.classList.add('hidden');
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>
