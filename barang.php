<?php
require_once 'config.php';
requireLogin();

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

    if ($error) {
        // Error validasi sudah diset di atas.
    } else {
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // Update
            $id = $_POST['id'];
            $query = "UPDATE barang 
                      SET nama_barang=?, unit_type=?, isi_renteng=?, harga_beli=?, harga_jual=?, harga_jual_renteng=?, harga_jual_pcs=?, stok=? 
                      WHERE id=?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ssidddddi", $nama_barang, $unit_type, $isi_renteng, $harga_beli, $harga_jual, $harga_jual_renteng, $harga_jual_pcs, $stok, $id);
        } else {
            // Insert
            $query = "INSERT INTO barang (nama_barang, unit_type, isi_renteng, harga_beli, harga_jual, harga_jual_renteng, harga_jual_pcs, stok) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ssiddddd", $nama_barang, $unit_type, $isi_renteng, $harga_beli, $harga_jual, $harga_jual_renteng, $harga_jual_pcs, $stok);
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

    $check = mysqli_query($conn, "SELECT id FROM barang WHERE id=$id");
    $barang = mysqli_fetch_assoc($check);

    if (!$barang) {
        $error = 'Data barang tidak ditemukan!';
    } else {
        mysqli_query($conn, "DELETE FROM barang WHERE id=$id");
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
require_once 'includes/swal_flash.php';
?>

<div class="app-container">
    <div class="app-panel overflow-hidden">
        <div class="app-panel-header">
            <span class="app-panel-title"><i class="ph ph-package text-amber-600"></i> Daftar Barang</span>
            <button type="button" onclick="openModal()" class="btn btn-primary">
                <i class="ph ph-plus-circle"></i> Tambah Barang
            </button>
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
                        <tr class="border-b hover:bg-amber-50/40 barang-row">
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
                                <button type="button"
                                    class="inline-flex items-center gap-1 text-blue-600 hover:underline mr-2"
                                    onclick='editBarang(<?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                    <i class="ph ph-pencil-simple"></i> Edit
                                </button>
                                <button type="button"
                                    data-delete-id="<?php echo $row['id']; ?>"
                                    class="delete-btn inline-flex items-center gap-1 text-red-600 hover:underline">
                                    <i class="ph ph-trash"></i> Hapus
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
    <div class="app-modal max-w-4xl max-h-[90vh] flex flex-col" onclick="event.stopPropagation()">
        <div class="app-modal-header shrink-0">
            <h2 id="modalTitle" class="text-xl font-bold flex items-center gap-2">
                <i class="ph ph-plus-circle text-amber-600"></i> Tambah Barang
            </h2>
            <button type="button" onclick="closeModal()" class="text-slate-400 hover:text-slate-600"><i class="ph ph-x text-xl"></i></button>
        </div>
        <div class="p-6 overflow-y-auto flex-1">
            <form method="POST" action="" id="formBarang">
                <input type="hidden" name="id" id="formId">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="app-label">Nama Barang</label>
                        <input type="text" name="nama_barang" id="nama_barang" required class="app-input">
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
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Simpan</button>
                </div>
            </form>
        </div>
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
        stokGram: document.getElementById('stokGram'),
        isiRenteng: document.getElementById('isiRenteng')
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
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
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
        stokFinal.value = data.stok || 0;

        updateVisibility();
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
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
</script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="assets/js/datatables-default.js"></script>
<script>
    $(function() {
        initDefaultDataTable('#barangTable');
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
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280'
            });

            if (result.isConfirmed) {
                window.location.href = `?delete=${encodeURIComponent(id)}`;
            }
        });
    });
</script>
<?php require_once 'includes/footer.php'; ?>
