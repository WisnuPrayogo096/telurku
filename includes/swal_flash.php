<?php if (!empty($success) || !empty($error)): ?>
<?php require_once __DIR__ . '/swal_lib.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($success)): ?>
    Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: <?php echo json_encode($success, JSON_UNESCAPED_UNICODE); ?>,
        confirmButtonColor: '#228BBA'
    });
    <?php endif; ?>
    <?php if (!empty($error)): ?>
    Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: <?php echo json_encode($error, JSON_UNESCAPED_UNICODE); ?>,
        confirmButtonColor: '#228BBA'
    });
    <?php endif; ?>
});
</script>
<?php endif; ?>
