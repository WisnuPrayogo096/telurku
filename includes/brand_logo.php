<?php

/** Logo toko: icons/logo.png — set $logoVariant ke 'nav' atau 'login' */
$logoVariant = $logoVariant ?? 'nav';
$logoAlt = $logoAlt ?? 'Toko Rahmat Jaya';
$logoClass = $logoVariant === 'login' ? 'app-logo-login' : 'app-logo-nav';
?>
<img src="icons/logo.png" alt="<?php echo htmlspecialchars($logoAlt); ?>" class="<?php echo $logoClass; ?>">