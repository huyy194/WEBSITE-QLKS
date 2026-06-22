<?php
$current_page = basename($_SERVER['PHP_SELF']);
if (isset($_SESSION['user_id']) && $current_page != 'login.php'):
?>
  </div> <!-- End Content -->
</div> <!-- End Main -->
<?php endif; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>