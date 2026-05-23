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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <?php echo $extraHead; ?>
</head>

<body class="<?php echo htmlspecialchars($bodyClass); ?>">
