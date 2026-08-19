<script>
    const CSRF   = '<?= SecurityHelper::safeOut($csrfToken) ?>';
    const API_URL = 'api.php';
</script>
<?php $jsVer = @filemtime(DPS_ROOT . '/assets/js/dps-mvc.js') ?: time(); ?>
<script src="assets/js/dps-mvc.js?v=<?= (int)$jsVer ?>"></script>
</body>
</html>
