<?php
require_once 'config.php';
requireLogin();

$success = '';
$error = '';
$tanggal_penjualan = getCurrentDate();

// Proses Penjualan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['proses_jual'])) {
    $tanggal_penjualan = $_POST['tanggal_penjualan'] ?? getCurrentDate();
    $date = DateTime::createFromFormat('Y-m-d', $tanggal_penjualan);
    if (!$date || $date->format('Y-m-d') !== $tanggal_penjualan) {
        $error = 'Tanggal penjualan tidak valid!';
        $tanggal_penjualan = getCurrentDate();
    }

    $tanggal_waktu = $tanggal_penjualan . ' ' . date('H:i:s');
    $metode_bayar = 'tunai';
    $barang_ids = $_POST['barang_id'] ?? [];
    $units = $_POST['unit'] ?? [];
    $jumlahs = $_POST['jumlah'] ?? [];

    $total_bayar = 0;
    $items = [];

    // Validasi dan hitung total
    foreach ($barang_ids as $index => $barang_id) {
        if (empty($barang_id)) continue;

        $unit = $units[$index] ?? 'pcs';
        $jumlah = (float)$jumlahs[$index];
        if ($jumlah <= 0) continue;

        // Ambil data barang
        $barang_id = (int)$barang_id;
        $query = "SELECT * FROM barang WHERE id = $barang_id";
        $result = mysqli_query($conn, $query);
        $barang = mysqli_fetch_assoc($result);

        if (!$barang) {
            $error = "Barang tidak ditemukan!";
            break;
        }

        // Hitung harga berdasarkan unit
        $harga_satuan = $barang['harga_jual'];
        $jumlah_pcs = $jumlah;

        if ($unit === 'renteng' && ($barang['harga_jual_renteng'] ?? 0) > 0) {
            $harga_satuan = $barang['harga_jual_renteng'];
            $jumlah_pcs = $jumlah * ($barang['isi_renteng'] ?? 1);
        } elseif ($unit === 'renteng') {
            $isi = max((int)($barang['isi_renteng'] ?? 1), 1);
            $harga_satuan = (($barang['harga_jual_pcs'] ?? 0) > 0 ? $barang['harga_jual_pcs'] : $barang['harga_jual']) * $isi;
            $jumlah_pcs = $jumlah * $isi;
        } elseif ($unit === 'pcs' && ($barang['harga_jual_pcs'] ?? 0) > 0) {
            $harga_satuan = $barang['harga_jual_pcs'];
        } elseif ($unit === 'gram' || $unit === 'gram (custom)') {
            $harga_satuan = $barang['harga_jual'] / 1000;
            $jumlah_pcs = $jumlah;
            $unit = 'gram';
        } elseif ($unit === '1 kg') {
            $harga_satuan = $barang['harga_jual'];
            $jumlah_pcs = $jumlah * 1000;
            $unit = 'gram';
        }

        if ($barang['stok'] < $jumlah_pcs) {
            $error = "Stok {$barang['nama_barang']} tidak cukup!";
            break;
        }

        $subtotal = $harga_satuan * $jumlah;
        $total_bayar += $subtotal;

        $items[] = [
            'barang_id' => $barang_id,
            'unit' => $unit,
            'jumlah' => $jumlah,
            'harga_satuan' => $harga_satuan,
            'subtotal' => $subtotal,
            'jumlah_pcs' => $jumlah_pcs
        ];
    }

    // Simpan transaksi
    if (empty($error) && count($items) > 0) {
        mysqli_begin_transaction($conn);

        try {
            // Insert penjualan
            $query = "INSERT INTO penjualan (tanggal, total_bayar, metode_bayar) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sds", $tanggal_waktu, $total_bayar, $metode_bayar);
            mysqli_stmt_execute($stmt);
            $penjualan_id = mysqli_insert_id($conn);

            // Insert detail dan update stok
            foreach ($items as $item) {
                $query = "INSERT INTO detail_penjualan (penjualan_id, barang_id, unit, jumlah, harga_satuan, subtotal) 
                         VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param(
                    $stmt,
                    "iisddd",
                    $penjualan_id,
                    $item['barang_id'],
                    $item['unit'],
                    $item['jumlah'],
                    $item['harga_satuan'],
                    $item['subtotal']
                );
                mysqli_stmt_execute($stmt);

                // Update stok
                $query = "UPDATE barang SET stok = stok - ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "di", $item['jumlah_pcs'], $item['barang_id']);
                mysqli_stmt_execute($stmt);
            }

            mysqli_commit($conn);
            $success = "Penjualan berhasil disimpan! Total: " . formatRupiah($total_bayar);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Gagal menyimpan transaksi!";
        }
    }
}

// Ambil data barang
$barang_query = "SELECT b.* FROM barang b 
                 WHERE b.stok > 0 
                 ORDER BY b.nama_barang";
$barang_result = mysqli_query($conn, $barang_query);
$barang_list = [];
while ($row = mysqli_fetch_assoc($barang_result)) {
    $barang_list[] = $row;
}
$pageTitle = 'Transaksi Penjualan - Toko Rahmat Jaya';
$extraHead = '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="includes/select2_theme.css">';
require_once 'includes/head.php';
$navTitle = 'Transaksi Penjualan';
$navBackUrl = 'index';
require_once 'includes/navbar.php';
require_once 'includes/flash.php';
?>

<div class="app-container">
    <div class="app-panel">
        <div class="app-panel-header">
            <span class="app-panel-title"><i class="ph ph-hand-coins text-amber-600"></i> Kasir Penjualan</span>
            <span class="text-xs text-slate-500 hidden sm:inline">Enter: tambah → unit → qty → cari lagi</span>
        </div>
        <div class="app-panel-body">
            <form method="POST" action="" id="formPenjualan" class="space-y-4">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    <div class="lg:col-span-2 space-y-4">
                        <input type="hidden" name="metode_bayar" id="metodeBayar" value="tunai">

                        <div class="search-box">
                            <label class="app-label inline-flex items-center gap-2">
                                <i class="ph ph-magnifying-glass text-amber-600"></i> Cari &amp; Pilih Barang
                            </label>
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end mt-2">
                                <div class="md:col-span-9">
                                    <select id="pilihBarang" class="w-full">
                                        <option value="">-- Ketik untuk mencari barang --</option>
                                        <?php foreach ($barang_list as $barang): ?>
                                            <option value="<?php echo $barang['id']; ?>"
                                                data-harga="<?php echo $barang['harga_jual']; ?>"
                                                data-harga-renteng="<?php echo $barang['harga_jual_renteng'] ?? 0; ?>"
                                                data-harga-pcs="<?php echo $barang['harga_jual_pcs'] ?? 0; ?>"
                                                data-stok="<?php echo $barang['stok']; ?>"
                                                data-unit="<?php echo $barang['unit_type']; ?>"
                                                data-isi-renteng="<?php echo $barang['isi_renteng'] ?? 0; ?>"
                                                data-nama="<?php echo htmlspecialchars($barang['nama_barang']); ?>">
                                                <?php echo $barang['nama_barang']; ?> - Stok: <?php echo ($barang['unit_type'] === 'renteng' && (int)$barang['isi_renteng'] > 0) ? formatQty($barang['stok'] / max((int)$barang['isi_renteng'], 1)) . ' renteng' : formatQty($barang['stok']) . ' ' . unitLabel($barang['unit_type']); ?> - <?php echo formatRupiah($barang['harga_jual']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="app-panel !shadow-sm kasir-table-panel">
                            <div class="kasir-table-scroll">
                            <div class="hidden md:grid kasir-table-grid kasir-table-header">
                                <div>Barang</div>
                                <div class="text-center">Unit</div>
                                <div class="text-center">Qty</div>
                                <div class="text-right">Harga</div>
                                <div class="text-right">Subtotal</div>
                                <div class="text-center">Aksi</div>
                            </div>
                            <div id="itemContainer" class="divide-y divide-slate-100">
                                <div id="emptyState" class="empty-state">
                                    <i class="ph ph-shopping-cart text-3xl text-slate-300 block mb-2"></i>
                                    Belum ada item. Ketik nama barang di atas lalu Enter.
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>

                    <div class="kasir-summary flex flex-col justify-between gap-4 min-h-[280px]">
                        <div class="space-y-4">
                            <h3 class="text-lg font-bold flex items-center gap-2">
                                <i class="ph ph-receipt"></i> Ringkasan
                            </h3>
                            <div>
                                <label class="text-sm text-slate-300 block mb-1">Tanggal Penjualan</label>
                                <input type="date" name="tanggal_penjualan" class="app-input !bg-slate-800 !border-slate-600 !text-white"
                                    value="<?php echo htmlspecialchars($tanggal_penjualan); ?>" required>
                            </div>
                            <div class="flex justify-between items-center text-slate-300">
                                <span class="text-sm">Total Item</span>
                                <span class="text-lg font-bold text-white" id="totalItemDisplay">0</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-slate-300">Total Belanja</span>
                                <span class="total-display" id="totalBelanjaDisplay">Rp 0</span>
                            </div>
                            <input type="hidden" id="totalBelanjaInput" name="total_belanja_view" value="0">
                            <div id="tunaiSection" class="space-y-3 pt-2 border-t border-slate-600">
                                <div>
                                    <label class="text-sm text-slate-300 block mb-1">Tunai Diterima</label>
                                    <input type="number" step="100" min="0" id="tunaiDiterima" class="app-input !bg-slate-800 !border-slate-600 !text-white"
                                        placeholder="Opsional — kosongkan jika uang pas">
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-slate-300">Kembalian</span>
                                    <span class="text-lg font-bold text-emerald-400" id="kembalianDisplay">Rp 0</span>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="proses_jual" class="btn btn-primary w-full py-3.5 text-base !shadow-lg">
                            <i class="ph ph-check-circle"></i> Proses Penjualan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Initialize Select2
        $(document).ready(function() {
            $('#pilihBarang').select2({
                placeholder: '-- Ketik untuk mencari barang --',
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() {
                        return "Barang tidak ditemukan";
                    },
                    searching: function() {
                        return "Mencari...";
                    }
                }
            });

            // Fokus otomatis ke pencarian barang saat halaman pertama kali dibuka
            setTimeout(function() {
                $('#pilihBarang').select2('open');
            }, 100);

            // Memastikan kursor langsung siap dipakai mengetik ketika dropdown Select2 terbuka
            $('#pilihBarang').on('select2:open', function(e) {
                document.querySelector('.select2-search__field').focus();
            });
        });

        const rupiahFormatter = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0
        });

        function formatRupiahJs(val) {
            return rupiahFormatter.format(val || 0);
        }

        function updateRow(row) {
            const unitSelect = row.querySelector('.unit-select');
            const selectedUnit = unitSelect ? unitSelect.value : 'pcs';
            const hargaRenteng = parseFloat(row.dataset.hargaRenteng || 0);
            const hargaPcs = parseFloat(row.dataset.hargaPcs || 0);
            const hargaDefault = parseFloat(row.dataset.harga || 0);
            const isiRenteng = parseInt(row.dataset.isiRenteng || 0);

            let harga = hargaDefault;
            let multiplier = 1;
            if (selectedUnit === 'renteng' && hargaRenteng > 0) {
                harga = hargaRenteng;
            } else if (selectedUnit === 'renteng') {
                harga = (hargaPcs > 0 ? hargaPcs : hargaDefault) * Math.max(isiRenteng, 1);
            } else if (selectedUnit === 'pcs' && hargaPcs > 0) {
                harga = hargaPcs;
            } else if (selectedUnit === 'gram' || selectedUnit === 'gram (custom)') {
                multiplier = 0.001;
            }

            const qtyInput = row.querySelector('.jumlah-input');
            const hargaSpan = row.querySelector('.harga-satuan');
            const subtotalSpan = row.querySelector('.subtotal-item');
            const unitLabel = row.querySelector('.unit-label');

            const qty = parseFloat(qtyInput?.value || 0);

            if (unitLabel) {
                if (selectedUnit === 'renteng') unitLabel.textContent = 'renteng';
                else if (selectedUnit === 'gram' || selectedUnit === 'gram (custom)' || selectedUnit === '1 kg') unitLabel.textContent = 'gram';
                else unitLabel.textContent = 'pcs';
            }

            if (qtyInput) {
                if (selectedUnit === 'gram' || selectedUnit === 'gram (custom)') {
                    qtyInput.step = '1';
                    qtyInput.min = '1';
                } else {
                    qtyInput.step = '1';
                    qtyInput.min = '1';
                }
                qtyInput.placeholder = 'Qty';
            }

            if (hargaSpan) {
                hargaSpan.textContent = formatRupiahJs(harga * multiplier);
            }

            const subtotal = harga * multiplier * qty;
            if (subtotalSpan) {
                subtotalSpan.textContent = formatRupiahJs(subtotal);
            }

            return subtotal;
        }

        function updateTotals() {
            const rows = document.querySelectorAll('#itemContainer .item-row');
            let total = 0;
            let totalItem = 0;

            rows.forEach(row => {
                const qtyInput = row.querySelector('.jumlah-input');
                const qty = parseFloat(qtyInput?.value || 0);
                if (qty > 0) {
                    totalItem += 1;
                }
                total += updateRow(row);
            });

            const totalDisplay = document.getElementById('totalBelanjaDisplay');
            const totalInput = document.getElementById('totalBelanjaInput');
            const totalItemDisplay = document.getElementById('totalItemDisplay');

            if (totalDisplay) totalDisplay.textContent = formatRupiahJs(total);
            if (totalInput) totalInput.value = total;
            if (totalItemDisplay) totalItemDisplay.textContent = totalItem;

            updateKembalian();
        }

        function updateKembalian() {
            const tunaiInput = document.getElementById('tunaiDiterima');
            const kembalianDisplay = document.getElementById('kembalianDisplay');
            const total = parseFloat(document.getElementById('totalBelanjaInput')?.value || 0);

            let tunai = parseFloat(tunaiInput?.value);
            // Jika kosong (isNaN), anggap uang pas (sama dengan total)
            if (isNaN(tunai) || tunaiInput.value.trim() === '') {
                tunai = total;
            }

            const kembalian = Math.max(tunai - total, 0);
            if (kembalianDisplay) {
                kembalianDisplay.textContent = formatRupiahJs(kembalian);
            }
        }

        function createRow(data) {
            const container = document.getElementById('itemContainer');
            const emptyState = document.getElementById('emptyState');
            if (emptyState) emptyState.remove();

            const row = document.createElement('div');
            row.className = "item-row kasir-table-grid kasir-item-row";
            row.dataset.harga = data.harga;
            row.dataset.hargaRenteng = data.hargaRenteng;
            row.dataset.hargaPcs = data.hargaPcs;
            row.dataset.unit = data.unit;
            row.dataset.isiRenteng = data.isiRenteng;
            row.dataset.nama = data.nama;

            let unitOptions = '';
            if (data.unit === 'gram' || data.unit === 'kg') {
                unitOptions = `
                    <option value="gram">Gram</option>
                `;
            } else {
                unitOptions = `<option value="pcs">Per Pcs</option>`;
                if (data.unit === 'renteng' && data.isiRenteng > 0) {
                    unitOptions += `<option value="renteng">Per Renteng (${data.isiRenteng} pcs)</option>`;
                }
            }

            row.innerHTML = `
                <div>
                    <input type="hidden" name="barang_id[]" value="${data.id}">
                    <div class="text-sm font-semibold text-gray-800">${data.nama}</div>
                    <div class="text-xs text-gray-500">Stok: ${data.stokLabel}</div>
                </div>

                <div>
                    <select name="unit[]" class="unit-select app-input text-sm py-2">
                        ${unitOptions}
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <div class="flex-1">
                        <input type="number" name="jumlah[]" value="1"
                            class="app-input text-sm py-2 jumlah-input"
                            step="1" min="1" placeholder="Qty">
                    </div>
                    <span class="hidden md:inline text-xs text-gray-500 unit-label">${data.unit === 'gram' || data.unit === 'kg' ? 'gram' : 'pcs'}</span>
                </div>

                <div class="kasir-col-price">
                    <div class="text-sm font-medium harga-satuan">${formatRupiahJs(data.harga)}</div>
                </div>

                <div class="kasir-col-subtotal">
                    <div class="text-sm font-bold text-amber-600 subtotal-item">Rp 0</div>
                </div>

                <div class="kasir-col-action">
                    <button type="button" class="hapus-row text-red-600 hover:text-red-800 hover:bg-red-50" title="Hapus item">
                        <i class="ph ph-trash text-xl"></i>
                        <span class="kasir-hapus-label">Hapus</span>
                    </button>
                </div>
            `;

            container.appendChild(row);
            attachListenersToRow(row);
            updateTotals();
        }

        function focusPilihBarang() {
            const select = $('#pilihBarang');
            select.val(null).trigger('change');
            setTimeout(function() {
                select.select2('open');
                document.querySelector('.select2-search__field')?.focus();
            }, 50);
        }

        function focusUnitOnRow(row) {
            const unitSelect = row?.querySelector('.unit-select');
            if (unitSelect) {
                unitSelect.focus();
            }
        }

        function focusQtyOnRow(row) {
            const qtyInput = row?.querySelector('.jumlah-input');
            if (qtyInput) {
                qtyInput.focus();
                qtyInput.select();
            }
        }

        function attachListenersToRow(row) {
            const qtyInput = row.querySelector('.jumlah-input');
            const unitSelect = row.querySelector('.unit-select');
            const deleteBtn = row.querySelector('.hapus-row');

            if (qtyInput) {
                qtyInput.addEventListener('input', () => {
                    updateRow(row);
                    updateTotals();
                });
                qtyInput.addEventListener('keydown', (e) => {
                    if (e.key !== 'Enter') return;
                    e.preventDefault();
                    updateRow(row);
                    updateTotals();
                    focusPilihBarang();
                });
            }

            if (unitSelect) {
                unitSelect.addEventListener('change', () => {
                    updateRow(row);
                    updateTotals();
                });
                unitSelect.addEventListener('keydown', (e) => {
                    if (e.key !== 'Enter') return;
                    e.preventDefault();
                    updateRow(row);
                    updateTotals();
                    focusQtyOnRow(row);
                });
            }

            if (deleteBtn) {
                deleteBtn.addEventListener('click', () => {
                    row.remove();
                    if (!document.querySelector('#itemContainer .item-row')) {
                        const container = document.getElementById('itemContainer');
                        const empty = document.createElement('div');
                        empty.id = 'emptyState';
                        empty.className = 'empty-state';
                        empty.innerHTML = '<i class="ph ph-shopping-cart text-3xl text-slate-300 block mb-2"></i>Belum ada item. Ketik nama barang di atas lalu Enter.';
                        container.appendChild(empty);
                    }
                    updateTotals();
                });
            }
        }

        function addSelectedBarang() {
            const select = $('#pilihBarang');
            const selectedValue = select.val();

            if (!selectedValue) {
                return false;
            }

            const opt = select.find('option:selected')[0];
            const data = {
                id: selectedValue,
                nama: opt.dataset.nama,
                stok: opt.dataset.stok,
                stokLabel: opt.textContent.split('Stok: ')[1]?.split(' - ')[0] || opt.dataset.stok,
                unit: opt.dataset.unit,
                harga: opt.dataset.harga,
                hargaRenteng: opt.dataset.hargaRenteng,
                hargaPcs: opt.dataset.hargaPcs,
                isiRenteng: opt.dataset.isiRenteng
            };

            createRow(data);
            select.val(null).trigger('change');
            const rows = document.querySelectorAll('#itemContainer .item-row');
            focusUnitOnRow(rows[rows.length - 1]);
            return true;
        }

        $('#pilihBarang').on('select2:select', function() {
            addSelectedBarang();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && document.querySelector('.select2-container--open')) {
                e.preventDefault();
                addSelectedBarang();
            }
        });

        document.getElementById('metodeBayar')?.addEventListener('change', updateKembalian);
        document.getElementById('tunaiDiterima')?.addEventListener('input', updateKembalian);

        // init total awal (keranjang kosong)
        updateTotals();
    </script>
<?php require_once 'includes/footer.php'; ?>
