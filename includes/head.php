<?php
$pageTitle = $pageTitle ?? 'Toko Rahmat Jaya';
$extraHead = $extraHead ?? '';
$bodyClass = $bodyClass ?? 'app-body';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" type="image/png" sizes="16x16" href="icons/16×16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icons/32×32.png">
    <link rel="icon" type="image/png" sizes="48x48" href="icons/48×48.png">
    <link rel="icon" type="image/png" sizes="192x192" href="icons/192×192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="icons/512×512.png">
    <link rel="apple-touch-icon" href="icons/180×180.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'system-ui', 'sans-serif'] },
                    colors: {
                        brand: {
                            DEFAULT: '#00BCFF',
                            dark: '#228BBA',
                            deep: '#2963A2',
                            light: '#A7F5E9',
                            soft: '#D7EFF8'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="assets/css/app.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css">
    <?php echo $extraHead; ?>
</head>

<body class="<?php echo htmlspecialchars($bodyClass); ?>">
