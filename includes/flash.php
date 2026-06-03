<?php if (!empty($success) || !empty($error)): ?>
<div class="app-toast <?php echo !empty($error) ? 'app-toast-error' : 'app-toast-success'; ?>" id="appToast">
    <?php echo htmlspecialchars(!empty($error) ? $error : $success); ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toast = document.getElementById('appToast');
    if (!toast) return;
    setTimeout(() => toast.classList.add('show'), 30);
    setTimeout(() => toast.classList.remove('show'), 2600);
    setTimeout(() => toast.remove(), 3200);
});
</script>
<?php endif; ?>
