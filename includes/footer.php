<?php
// includes/footer.php
?>
</div><!-- end .main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-hide alerts after 4 seconds
document.querySelectorAll('.alert-cv').forEach(function(alert) {
    setTimeout(function() {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(function() { alert.style.display = 'none'; }, 500);
    }, 4000);
});
</script>
</body>
</html>
