<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg bg-white border-bottom hilton-nav sticky-top shadow-sm">
  <div class="container px-4">
    <a class="navbar-brand fw-bold fs-3 text-dark text-center" style="font-family: serif; border: 2px solid #000; padding: 0 5px; line-height: 1.2;" href="index.php">K-Hotel<br><span style="font-size: 0.6rem; letter-spacing: 1px; display: block; border-top: 1px solid #000; font-family: sans-serif; font-weight: bold; margin-top: 2px;">FOR THE STAY</span></a>
    
    <button class="navbar-toggler text-dark border-0 shadow-none p-2" type="button" data-bs-toggle="collapse" data-bs-target="#navCustomer">
      <i class="fa-solid fa-bars fs-1"></i>
    </button>
    
    <div class="collapse navbar-collapse py-3 py-lg-0" id="navCustomer">
        <ul class="navbar-nav me-auto ms-lg-4 gap-3">
            <li class="nav-item">
                <a class="nav-link text-dark <?= $current_page == 'index.php' ? 'fw-bold active' : '' ?>" href="index.php">Trang chủ</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-dark <?= $current_page == 'timkiem.php' ? 'fw-bold active' : '' ?>" href="timkiem.php">Tìm phòng & Đặt chỗ</a>
            </li>
            <?php if(isset($_SESSION['user_id']) && $_SESSION['role']=='khach'): ?>
            <li class="nav-item">
                <a class="nav-link text-dark <?= $current_page == 'lichsu.php' ? 'fw-bold active' : '' ?>" href="lichsu.php"><i class="fa-solid fa-clock-rotate-left me-1"></i>Lịch sử đặt phòng</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-dark <?= $current_page == 'voucher.php' ? 'fw-bold active' : '' ?>" href="voucher.php"><i class="fa-solid fa-ticket me-1"></i>Voucher Ưu Đãi</a>
            </li>
            <?php endif; ?>
        </ul>
        
        <div class="d-flex align-items-center gap-3 mt-3 mt-lg-0">
            <?php if(isset($_SESSION['user_id'])): ?>
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle fw-bold" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fa-regular fa-user-circle fs-5 me-1 align-middle"></i> <?= htmlspecialchars($_SESSION['user_name']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm rounded-3">
                        <li><a class="dropdown-item" href="lichsu.php"><i class="fa-solid fa-clock-rotate-left me-2 text-muted"></i>Lịch sử lưu trú</a></li>
                        <li><a class="dropdown-item" href="voucher.php"><i class="fa-solid fa-ticket me-2 text-warning"></i>Voucher Ưu Đãi</a></li>
                        <?php if($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'nhanvien'): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-primary fw-bold" href="dashboard.php"><i class="fa-solid fa-gauge me-2"></i>Trang Quản Trị</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="auth/logout.php"><i class="fa-solid fa-sign-out-alt me-2"></i>Đăng xuất</a></li>
                    </ul>
                </div>
            <?php else: ?>
                <a href="auth/login.php" class="btn btn-dark-blue fw-bold rounded-1 px-4">Đăng nhập</a>
            <?php endif; ?>
        </div>
    </div>
  </div>
</nav>
