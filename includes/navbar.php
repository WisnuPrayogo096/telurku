<?php
$navTitle = $navTitle ?? 'Toko Rahmat Jaya';
$navBackUrl = $navBackUrl ?? null;
$navBackLabel = $navBackLabel ?? 'Kembali';
$showUserGreeting = $showUserGreeting ?? false;
$showProfilLink = $showProfilLink ?? false;
$showLogout = $showLogout ?? false;
$brandUrl = $brandUrl ?? 'index';
$currentPage = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: 'index');
$navItems = [
    ['href' => 'index', 'icon' => 'ph-squares-four', 'label' => 'Dashboard'],
    ['href' => 'penjualan', 'icon' => 'ph-hand-coins', 'label' => 'Kasir'],
    ['href' => 'barang', 'icon' => 'ph-package', 'label' => 'Barang'],
    ['href' => 'stok_masuk', 'icon' => 'ph-archive-box', 'label' => 'Stok Masuk'],
    ['href' => 'pengurangan_stok', 'icon' => 'ph-arrow-down', 'label' => 'Stok Keluar'],
    ['href' => 'laporan', 'icon' => 'ph-chart-line', 'label' => 'Laporan'],
    ['href' => 'analisis_stok', 'icon' => 'ph-trend-up', 'label' => 'Analisis'],
];
?>
<aside class="app-sidebar" id="appSidebar">
    <a href="<?php echo htmlspecialchars($brandUrl); ?>" class="app-brand app-sidebar-brand">
        <span class="app-brand-icon"><?php $logoVariant = 'nav'; require __DIR__ . '/brand_logo.php'; ?></span>
        <span>
            <span class="app-brand-title d-block">Toko Rahmat Jaya</span>
            <span class="app-brand-sub">Kasir &amp; Stok</span>
        </span>
    </a>
    <div class="app-sidebar-label">Menu</div>
    <nav class="app-sidebar-menu">
        <?php foreach ($navItems as $item): ?>
            <?php $isActive = $currentPage === $item['href'] || ($currentPage === '' && $item['href'] === 'index'); ?>
            <a href="<?php echo $item['href']; ?>" class="app-sidebar-link <?php echo $isActive ? 'active' : ''; ?>">
                <i class="ph <?php echo $item['icon']; ?>"></i>
                <span><?php echo $item['label']; ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>

<header class="app-topbar">
    <div class="app-topbar-inner">
        <div class="app-topbar-left">
            <button type="button" id="sidebarToggle" class="btn-sidebar-toggle hidden md:inline-flex" title="Toggle Sidebar">
                <i class="ph ph-list"></i>
            </button>
            
            <?php if ($navBackUrl): ?>
                <a href="<?php echo htmlspecialchars($navBackUrl); ?>" class="btn btn-ghost-nav">
                    <i class="ph ph-arrow-left"></i> <?php echo htmlspecialchars($navBackLabel); ?>
                </a>
            <?php else: ?>
                <span class="app-topbar-title">
                    <span class="md:hidden"><?php $logoVariant = 'nav'; require __DIR__ . '/brand_logo.php'; ?></span>
                    <?php echo htmlspecialchars($navTitle); ?>
                </span>
            <?php endif; ?>
        </div>
        
        <div class="app-topbar-right">
            <?php if (isset($_SESSION['nama'])): ?>
                <div class="user-dropdown-wrap">
                    <button type="button" class="user-dropdown-btn" id="userDropdownBtn">
                        <i class="ph ph-user-circle"></i>
                        <span class="hidden sm:inline"><?php echo htmlspecialchars($_SESSION['nama'] ?? ''); ?></span>
                        <i class="ph ph-caret-down text-xs"></i>
                    </button>
                    <div class="user-dropdown-menu" id="userDropdownMenu">
                        <div class="px-3 py-2 border-b border-slate-100 mb-1">
                            <div class="font-bold text-sm text-slate-800"><?php echo htmlspecialchars($_SESSION['nama'] ?? ''); ?></div>
                            <div class="text-xs text-slate-500">Administrator</div>
                        </div>
                        <a href="profil" class="user-dropdown-item">
                            <i class="ph ph-user"></i> Profil Saya
                        </a>
                        <div class="user-dropdown-divider"></div>
                        <button type="button" id="btnLogout" class="user-dropdown-item user-dropdown-item--danger w-full text-left">
                            <i class="ph ph-sign-out"></i> Keluar
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <nav class="app-mobile-menu">
        <?php foreach ($navItems as $item): ?>
            <?php $isActive = $currentPage === $item['href'] || ($currentPage === '' && $item['href'] === 'index'); ?>
            <a href="<?php echo $item['href']; ?>" class="app-mobile-link <?php echo $isActive ? 'active' : ''; ?>">
                <i class="ph <?php echo $item['icon']; ?>"></i>
                <span><?php echo $item['label']; ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</header>
<?php $withMain = true; ?>
<main class="app-main">
