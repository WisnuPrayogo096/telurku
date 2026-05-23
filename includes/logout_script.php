<?php require_once __DIR__ . '/swal_lib.php'; ?>
<script>
document.getElementById('btnLogout')?.addEventListener('click', async function() {
    const result = await Swal.fire({
        title: 'Keluar dari aplikasi?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, keluar',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#94a3b8'
    });
    if (result.isConfirmed) {
        window.location.href = 'logout';
    }
});
</script>
