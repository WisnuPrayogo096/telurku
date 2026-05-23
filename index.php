<?php
require_once 'config.php';
requireLogin();

$stats = getDashboardStats($conn);
extract($stats);

$pageTitle = 'Dashboard - Toko Rahmat Jaya';
require_once 'includes/head.php';
$navTitle = 'Dashboard';
$showUserGreeting = true;
$showProfilLink = true;
$showLogout = true;
require_once 'includes/navbar.php';
?>

<div class="app-container">
    <div class="app-alert app-alert-info">
        <i class="ph ph-clock text-xl text-amber-600 shrink-0"></i>
        <span>Sesi login aktif <strong>30 hari</strong>. Setelah itu, masukkan username &amp; password lagi.</span>
    </div>

    <div class="stat-grid">
        <div class="stat-card stat-card--blue">
            <div class="stat-card-icon"><i class="ph ph-currency-circle-dollar"></i></div>
            <div class="stat-card-label">Penjualan Hari Ini</div>
            <div class="stat-card-value"><?php echo formatRupiah($total_today); ?></div>
            <div class="stat-card-hint">Total transaksi hari ini</div>
        </div>
        <div class="stat-card stat-card--green">
            <div class="stat-card-icon"><i class="ph ph-trend-up"></i></div>
            <div class="stat-card-label">Keuntungan Hari Ini</div>
            <div class="stat-card-value <?php echo $total_keuntungan_today < 0 ? 'text-red-600' : ''; ?>"><?php echo formatRupiah($total_keuntungan_today); ?></div>
            <div class="stat-card-hint">Penjualan − modal (HPP)</div>
        </div>
        <div class="stat-card stat-card--purple">
            <div class="stat-card-icon"><i class="ph ph-package"></i></div>
            <div class="stat-card-label">Jumlah Item Stok</div>
            <div class="stat-card-value"><?php echo $total_stok; ?> <span class="text-lg font-semibold text-slate-500">item</span></div>
            <div class="stat-card-hint">Jenis barang terdaftar</div>
        </div>
        <div class="stat-card stat-card--orange">
            <div class="stat-card-icon"><i class="ph ph-calendar"></i></div>
            <div class="stat-card-label">Penjualan Bulan Ini</div>
            <div class="stat-card-value"><?php echo formatRupiah($total_month); ?></div>
            <div class="stat-card-hint"><?php echo date('F Y'); ?></div>
        </div>
        <div class="stat-card stat-card--rose">
            <div class="stat-card-icon"><i class="ph ph-wallet"></i></div>
            <div class="stat-card-label">Total Aset (Beli)</div>
            <div class="stat-card-value text-xl sm:text-2xl"><?php echo formatRupiah($aset_beli); ?></div>
            <div class="stat-card-hint">Nilai modal stok saat ini</div>
        </div>
        <div class="stat-card stat-card--indigo">
            <div class="stat-card-icon"><i class="ph ph-chart-bar"></i></div>
            <div class="stat-card-label">Potensi Omset (Jual)</div>
            <div class="stat-card-value text-xl sm:text-2xl"><?php echo formatRupiah($aset_jual); ?></div>
            <div class="stat-card-hint">Estimasi jika stok habis terjual</div>
        </div>
    </div>

    <h2 class="text-lg font-bold text-slate-800 mb-3 flex items-center gap-2">
        <i class="ph ph-squares-four text-amber-600"></i> Menu Utama
    </h2>
    <div class="menu-grid">
        <a href="barang" class="menu-card menu-card--blue">
            <div class="menu-card-icon"><i class="ph ph-package"></i></div>
            <div class="menu-card-title">Data Barang</div>
            <div class="menu-card-desc">Kelola master stok &amp; harga</div>
        </a>
        <a href="stok_masuk" class="menu-card menu-card--green">
            <div class="menu-card-icon"><i class="ph ph-archive-box"></i></div>
            <div class="menu-card-title">Stok Masuk</div>
            <div class="menu-card-desc">Restock &amp; riwayat masuk</div>
        </a>
        <a href="pengurangan_stok" class="menu-card menu-card--rose">
            <div class="menu-card-icon"><i class="ph ph-arrow-down"></i></div>
            <div class="menu-card-title">Pengurangan Stok</div>
            <div class="menu-card-desc">Keluar untuk keperluan pribadi</div>
        </a>
        <a href="penjualan" class="menu-card menu-card--emerald">
            <div class="menu-card-icon"><i class="ph ph-hand-coins"></i></div>
            <div class="menu-card-title">Kasir Penjualan</div>
            <div class="menu-card-desc">Catat transaksi harian</div>
        </a>
        <a href="laporan" class="menu-card menu-card--purple">
            <div class="menu-card-icon"><i class="ph ph-chart-line"></i></div>
            <div class="menu-card-title">Laporan Penjualan</div>
            <div class="menu-card-desc">Rekapan &amp; detail item</div>
        </a>
        <a href="analisis_stok" class="menu-card menu-card--amber">
            <div class="menu-card-icon"><i class="ph ph-trend-up"></i></div>
            <div class="menu-card-title">Analisis Stok</div>
            <div class="menu-card-desc">Barang laris &amp; stok menipis</div>
        </a>
    </div>
</div>

<?php
require_once 'includes/logout_script.php';
require_once 'includes/footer.php';
