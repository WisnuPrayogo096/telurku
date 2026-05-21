<?php
$navTitle = $navTitle ?? 'Toko Rahmat Jaya';
$navBackUrl = $navBackUrl ?? null;
$navBackLabel = $navBackLabel ?? 'Kembali';
$showUserGreeting = $showUserGreeting ?? false;
$showProfilLink = $showProfilLink ?? false;
$showLogout = $showLogout ?? false;
$brandUrl = $brandUrl ?? 'index';
?>
<header class="app-nav">
    <div class="app-container !py-3 !pb-3 flex flex-wrap justify-between items-center gap-3">
        <a href="<?php echo htmlspecialchars($brandUrl); ?>" class="app-brand">
            <span class="app-brand-icon"><?php $logoVariant = 'nav'; require __DIR__ . '/brand_logo.php'; ?></span>
            <span>
                <span class="app-brand-title block"><?php echo htmlspecialchars($navTitle); ?></span>
                <span class="app-brand-sub">Toko Rahmat Jaya</span>
            </span>
        </a>
        <div class="flex items-center gap-2 flex-wrap">
            <?php if ($showUserGreeting): ?>
                <span class="hidden sm:inline text-sm font-medium text-slate-600 bg-slate-100 px-3 py-1.5 rounded-full">
                    <i class="ph ph-hand-waving text-amber-600"></i>
                    <?php echo htmlspecialchars($_SESSION['nama'] ?? ''); ?>
                </span>
            <?php endif; ?>
            <?php if ($showProfilLink): ?>
                <a href="profil" class="btn btn-ghost-nav">
                    <i class="ph ph-user"></i> Profil
                </a>
            <?php endif; ?>
            <?php if ($navBackUrl): ?>
                <a href="<?php echo htmlspecialchars($navBackUrl); ?>" class="btn btn-ghost-nav">
                    <i class="ph ph-arrow-left"></i> <?php echo htmlspecialchars($navBackLabel); ?>
                </a>
            <?php endif; ?>
            <?php if ($showLogout): ?>
                <button type="button" id="btnLogout" class="btn btn-danger-nav">
                    <i class="ph ph-sign-out"></i> Keluar
                </button>
            <?php endif; ?>
        </div>
    </div>
</header>
<?php $withMain = true; ?>
<main class="app-main">
