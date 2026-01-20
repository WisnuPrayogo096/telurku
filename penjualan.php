<?php
require_once 'config.php';
requireLogin();

$success = '';
$error = '';

// Proses Penjualan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['proses_jual'])) {
    $tanggal = $_POST['tanggal'];
    $metode_bayar = $_POST['metode_bayar'];
    $barang_ids = $_POST['barang_id'];
    $units = $_POST['unit'];
    $jumlahs = $_POST['jumlah'];

    $total_bayar = 0;
    $items = [];

    // Validasi dan hitung total
    foreach ($barang_ids as $index => $barang_id) {
        if (empty($barang_id)) continue;

        $unit = $units[$index] ?? 'pcs';
        $jumlah = (float)$jumlahs[$index];
        if ($jumlah <= 0) continue;

        // Ambil data barang
        $query = "SELECT * FROM barang WHERE id = $barang_id";
        $result = mysqli_query($conn, $query);
        $barang = mysqli_fetch_assoc($result);

        if (!$barang) {
            $error = "Barang tidak ditemukan!";
            break;
        }

        // Hitung harga berdasarkan unit
        $harga_satuan = $barang['harga_jual'];
        if ($unit === 'renteng' && ($barang['harga_jual_renteng'] ?? 0) > 0) {
            $harga_satuan = $barang['harga_jual_renteng'];
        } elseif ($unit === 'pcs' && ($barang['harga_jual_pcs'] ?? 0) > 0) {
            $harga_satuan = $barang['harga_jual_pcs'];
        }

        // Hitung jumlah dalam pcs untuk cek stok
        $jumlah_pcs = $jumlah;
        if ($unit === 'renteng') {
            $jumlah_pcs = $jumlah * ($barang['isi_renteng'] ?? 1);
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
            'owner_id' => $barang['owner_id'],
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
            mysqli_stmt_bind_param($stmt, "sds", $tanggal, $total_bayar, $metode_bayar);
            mysqli_stmt_execute($stmt);
            $penjualan_id = mysqli_insert_id($conn);

            // Insert detail dan update stok
            foreach ($items as $item) {
                $query = "INSERT INTO detail_penjualan (penjualan_id, barang_id, unit, jumlah, harga_satuan, subtotal, owner_id) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param(
                    $stmt,
                    "iisdddi",
                    $penjualan_id,
                    $item['barang_id'],
                    $item['unit'],
                    $item['jumlah'],
                    $item['harga_satuan'],
                    $item['subtotal'],
                    $item['owner_id']
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
$barang_query = "SELECT b.*, u.nama as owner_nama FROM barang b 
                 JOIN users u ON b.owner_id = u.id 
                 WHERE b.stok > 0 
                 ORDER BY b.nama_barang";
$barang_result = mysqli_query($conn, $barang_query);
$barang_list = [];
while ($row = mysqli_fetch_assoc($barang_result)) {
    $barang_list[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Penjualan - TELURKU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css">
    <style>
        /* Custom styling untuk Select2 */
        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.5rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 26px;
            padding-left: 8px;
            color: #374151;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
            right: 8px;
        }

        .select2-dropdown {
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #3b82f6;
        }

        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.5rem;
        }

        .select2-container {
            width: 100% !important;
        }
    </style>
</head>

<body class="bg-gray-100">
    <nav class="bg-blue-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Transaksi Penjualan</h1>
            <a href="index" class="bg-blue-700 px-4 py-2 rounded hover:bg-blue-800 flex items-center gap-2 text-sm md:text-base">
                <i class="ph ph-arrow-left"></i> Kembali
            </a>
        </div>
    </nav>

    <div class="container mx-auto p-4">
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-4 md:p-6">
            <h2 class="text-xl font-bold mb-4">Kasir Penjualan</h2>
            <form method="POST" action="" id="formPenjualan" class="space-y-4">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- Kolom kiri: daftar item -->
                    <div class="lg:col-span-2">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-gray-700 text-sm md:text-base font-medium mb-1">Tanggal</label>
                                <input type="date" name="tanggal" required
                                    value="<?php echo date('Y-m-d'); ?>"
                                    class="w-full px-3 py-2 md:px-4 md:py-2 border rounded-lg focus:outline-none focus:border-blue-500 text-sm md:text-base">
                            </div>

                            <div>
                                <label class="block text-gray-700 text-sm md:text-base font-medium mb-1">Metode Pembayaran</label>
                                <select name="metode_bayar" id="metodeBayar" required
                                    class="w-full px-3 py-2 md:px-4 md:py-2 border rounded-lg focus:outline-none focus:border-blue-500 text-sm md:text-base">
                                    <option value="tunai">Tunai</option>
                                    <option value="qris">QRIS</option>
                                </select>
                            </div>
                        </div>

                        <div class="bg-gray-50 border rounded-lg p-4 mb-4">
                            <label class="block text-gray-700 text-sm font-medium mb-2 inline-flex items-center gap-2">
                                <i class="ph ph-magnifying-glass"></i>
                                Cari & Pilih Barang
                            </label>
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
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
                                                data-owner="<?php echo $barang['owner_nama']; ?>"
                                                data-nama="<?php echo htmlspecialchars($barang['nama_barang']); ?>">
                                                <?php echo $barang['nama_barang']; ?> (<?php echo $barang['owner_nama']; ?>) - Stok: <?php echo $barang['unit_type'] === 'kg' ? $barang['stok'] . ' kg' : $barang['stok'] . ' pcs'; ?> - <?php echo formatRupiah($barang['harga_jual']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="md:col-span-3">
                                    <button type="button" id="btnTambahList"
                                        class="w-full bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 text-sm md:text-base font-medium transition inline-flex items-center justify-center gap-2">
                                        <i class="ph ph-plus-circle"></i> Tambah
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="border rounded-lg overflow-hidden">
                            <div class="hidden md:grid grid-cols-12 bg-gray-100 text-xs font-semibold text-gray-700 px-3 py-2">
                                <div class="col-span-4">Barang</div>
                                <div class="col-span-2 text-center">Unit</div>
                                <div class="col-span-2 text-center">Qty</div>
                                <div class="col-span-2 text-right">Harga</div>
                                <div class="col-span-1 text-right">Subtotal</div>
                                <div class="col-span-1 text-center">Aksi</div>
                            </div>
                            <div id="itemContainer" class="divide-y">
                                <div id="emptyState" class="px-4 py-6 text-center text-sm text-gray-500">
                                    Belum ada item. Tambahkan barang melalui dropdown di atas.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kolom kanan: ringkasan belanja -->
                    <div class="border rounded-lg p-4 bg-gray-50 flex flex-col justify-between gap-4">
                        <div class="space-y-3">
                            <h3 class="text-lg font-semibold text-gray-800">Ringkasan Belanja</h3>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Total Item</span>
                                <span class="text-base font-semibold" id="totalItemDisplay">0</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Total Belanja</span>
                                <span class="text-xl font-bold text-blue-600" id="totalBelanjaDisplay">Rp 0</span>
                            </div>

                            <input type="hidden" id="totalBelanjaInput" name="total_belanja_view" value="0">

                            <div id="tunaiSection" class="space-y-3">
                                <div>
                                    <label class="block text-gray-700 text-sm font-medium mb-1">Tunai Diterima</label>
                                    <input type="number" step="100" min="0" id="tunaiDiterima"
                                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 text-sm"
                                        placeholder="Masukkan uang tunai (opsional)">
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Kembalian</span>
                                    <span class="text-lg font-semibold text-green-600" id="kembalianDisplay">Rp 0</span>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500">
                                Untuk pembayaran QRIS, isi item saja lalu proses penjualan. Tunai & kembalian hanya untuk metode tunai.
                            </p>
                        </div>

                        <div class="border-t pt-3 mt-2">
                            <button type="submit" name="proses_jual"
                                class="w-full bg-blue-500 text-white px-4 py-3 rounded-lg hover:bg-blue-600 text-base font-semibold transition inline-flex items-center justify-center gap-2">
                                <i class="ph ph-hand-coins"></i> Proses Penjualan
                            </button>
                        </div>
                    </div>
                </div>
            </form>
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
            if (selectedUnit === 'renteng' && hargaRenteng > 0) {
                harga = hargaRenteng;
            } else if (selectedUnit === 'pcs' && hargaPcs > 0) {
                harga = hargaPcs;
            }

            const qtyInput = row.querySelector('.jumlah-input');
            const hargaSpan = row.querySelector('.harga-satuan');
            const subtotalSpan = row.querySelector('.subtotal-item');
            const unitLabel = row.querySelector('.unit-label');

            const qty = parseFloat(qtyInput?.value || 0);

            if (unitLabel) {
                unitLabel.textContent = selectedUnit === 'renteng' ? 'renteng' : 'pcs';
            }

            if (qtyInput) {
                qtyInput.step = '1';
                qtyInput.min = '1';
                qtyInput.placeholder = 'Qty';
            }

            if (hargaSpan) {
                hargaSpan.textContent = formatRupiahJs(harga);
            }

            const subtotal = harga * qty;
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
            const metode = document.getElementById('metodeBayar')?.value || 'tunai';
            const tunaiSection = document.getElementById('tunaiSection');
            const tunaiInput = document.getElementById('tunaiDiterima');
            const kembalianDisplay = document.getElementById('kembalianDisplay');
            const total = parseFloat(document.getElementById('totalBelanjaInput')?.value || 0);

            if (metode === 'tunai') {
                tunaiSection?.classList.remove('hidden');
                const tunai = parseFloat(tunaiInput?.value || 0);
                const kembalian = Math.max(tunai - total, 0);
                if (kembalianDisplay) {
                    kembalianDisplay.textContent = formatRupiahJs(kembalian);
                }
            } else {
                tunaiSection?.classList.add('hidden');
                if (kembalianDisplay) {
                    kembalianDisplay.textContent = formatRupiahJs(0);
                }
            }
        }

        function createRow(data) {
            const container = document.getElementById('itemContainer');
            const emptyState = document.getElementById('emptyState');
            if (emptyState) emptyState.remove();

            const row = document.createElement('div');
            row.className = "item-row px-3 py-3 grid grid-cols-1 md:grid-cols-12 gap-3 items-center hover:bg-gray-50";
            row.dataset.harga = data.harga;
            row.dataset.hargaRenteng = data.hargaRenteng;
            row.dataset.hargaPcs = data.hargaPcs;
            row.dataset.unit = data.unit;
            row.dataset.isiRenteng = data.isiRenteng;
            row.dataset.nama = data.nama;

            const unitOptions = data.isiRenteng > 0 ? `
                <option value="pcs">Per Pcs</option>
                <option value="renteng">Per Renteng (${data.isiRenteng} pcs)</option>
            ` : `
                <option value="pcs">Per Pcs</option>
            `;

            row.innerHTML = `
                <input type="hidden" name="barang_id[]" value="${data.id}">
                <div class="md:col-span-4">
                    <div class="text-sm font-semibold text-gray-800">${data.nama}</div>
                    <div class="text-xs text-gray-500">${data.owner} · Stok: ${data.stok} ${data.unit === 'kg' ? 'kg' : 'pcs'}</div>
                </div>

                <div class="md:col-span-2">
                    <select name="unit[]" class="unit-select w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 text-sm">
                        ${unitOptions}
                    </select>
                </div>

                <div class="md:col-span-2 flex items-center gap-2">
                    <div class="flex-1">
                        <input type="number" name="jumlah[]" value="${data.unit === 'kg' ? '0.5' : '1'}"
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 text-sm jumlah-input"
                            step="1" min="1" placeholder="Qty">
                    </div>
                    <span class="hidden md:inline text-xs text-gray-500 unit-label">pcs</span>
                </div>

                <div class="md:col-span-2 md:text-right">
                    <div class="text-sm font-medium harga-satuan">${formatRupiahJs(data.harga)}</div>
                </div>

                <div class="md:col-span-1 md:text-right">
                    <div class="text-sm font-bold text-blue-600 subtotal-item">Rp 0</div>
                </div>

                <div class="md:col-span-1 text-center">
                    <button type="button" class="hapus-row text-red-600 hover:text-red-800 hover:underline text-sm font-medium inline-flex items-center gap-1">
                        <i class="ph ph-trash"></i> Hapus
                    </button>
                </div>
            `;

            container.appendChild(row);
            attachListenersToRow(row);
            updateTotals();
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
            }

            if (unitSelect) {
                unitSelect.addEventListener('change', () => {
                    updateRow(row);
                    updateTotals();
                });
            }

            if (deleteBtn) {
                deleteBtn.addEventListener('click', () => {
                    row.remove();
                    if (!document.querySelector('#itemContainer .item-row')) {
                        const container = document.getElementById('itemContainer');
                        const empty = document.createElement('div');
                        empty.id = 'emptyState';
                        empty.className = 'px-4 py-6 text-center text-sm text-gray-500';
                        empty.textContent = 'Belum ada item. Tambahkan barang melalui dropdown di atas.';
                        container.appendChild(empty);
                    }
                    updateTotals();
                });
            }
        }

        document.getElementById('btnTambahList')?.addEventListener('click', () => {
            const select = $('#pilihBarang');
            const selectedValue = select.val();

            if (!selectedValue) {
                alert('Pilih barang terlebih dahulu!');
                return;
            }

            const opt = select.find('option:selected')[0];
            const data = {
                id: selectedValue,
                nama: opt.dataset.nama,
                owner: opt.dataset.owner,
                stok: opt.dataset.stok,
                unit: opt.dataset.unit,
                harga: opt.dataset.harga,
                hargaRenteng: opt.dataset.hargaRenteng,
                hargaPcs: opt.dataset.hargaPcs,
                isiRenteng: opt.dataset.isiRenteng
            };

            createRow(data);
            select.val(null).trigger('change'); // Reset Select2
        });

        document.getElementById('metodeBayar')?.addEventListener('change', updateKembalian);
        document.getElementById('tunaiDiterima')?.addEventListener('input', updateKembalian);

        // init total awal (keranjang kosong)
        updateTotals();
    </script>
</body>

</html>