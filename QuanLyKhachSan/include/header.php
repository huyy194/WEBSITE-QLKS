<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);

// === PHÂN QUYỀN TRUY CẬP ===
// Danh sách trang chỉ dành cho admin & nhanvien (không cho khách hàng)
$admin_pages = ['sodophong.php', 'dashboard.php', 'datphong.php', 'hoadon.php', 'khachhang.php', 
                'nhanvien.php', 'xacnhan.php', 'dichvu.php', 'themdichvu.php', 'khachthanthiet.php', 'quan_ly_voucher.php', 'quan_ly_danhgia.php'];

// Redirect to login if not logged in and not on login page
if (!isset($_SESSION['user_id']) && $current_page != 'login.php') {
    $login_path = (file_exists('auth/login.php')) ? 'auth/login.php' : '../auth/login.php';
    header("Location: " . $login_path);
    exit;
}

// Nếu đã đăng nhập nhưng là khách hàng mà cố vào trang admin → redirect về trangchu
if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'khach' && in_array($current_page, $admin_pages)) {
    $trangchu_path = (file_exists('index.php')) ? 'index.php' : '../index.php';
    header("Location: " . $trangchu_path . "?denied=1");
    exit;
}

// Adjust base url for assets
$base_url = (file_exists('assets/css/style.css')) ? '' : '../';

// Xác định role badge hiển thị
$role = $_SESSION['role'] ?? 'khach';
$role_badge = '';
$role_icon = 'fa-user-circle';
if ($role == 'admin') {
    $role_badge = '<span class="badge bg-danger ms-1 small">Admin</span>';
    $role_icon = 'fa-user-shield';
} elseif ($role == 'nhanvien') {
    $role_badge = '<span class="badge bg-info ms-1 small">Nhân viên</span>';
    $role_icon = 'fa-user-tie';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K-Hotel | Hệ thống Quản lý Khách sạn</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/admin-luxestay.css">
</head>
<body>
<?php if (isset($_SESSION['user_id']) && $current_page != 'login.php'): ?>
<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-text">✦ K-Hotel</div>
    <div class="logo-sub">Hotel Management</div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Tổng quan</div>
    <a href="<?= $base_url ?>dashboard.php" class="nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" style="text-decoration:none;">
      <span class="icon">📊</span> Dashboard
    </a>

    <div class="nav-section-label">Quản lý</div>
    <a href="<?= $base_url ?>sodophong.php" class="nav-item <?= $current_page == 'sodophong.php' ? 'active' : '' ?>" style="text-decoration:none;">
      <span class="icon">🏨</span> Sơ đồ phòng
    </a>
    <a href="<?= $base_url ?>xacnhan.php" class="nav-item <?= $current_page == 'xacnhan.php' ? 'active' : '' ?>" style="text-decoration:none;">
      <span class="icon">📅</span> Duyệt đơn Online
    </a>
    <a href="<?= $base_url ?>khachhang.php" class="nav-item <?= $current_page == 'khachhang.php' ? 'active' : '' ?>" style="text-decoration:none;">
      <span class="icon">👥</span> Khách hàng
    </a>
    <a href="<?= $base_url ?>khachthanthiet.php" class="nav-item <?= $current_page == 'khachthanthiet.php' ? 'active' : '' ?>" style="text-decoration:none;">
      <span class="icon">👑</span> Khách Thân Thiết
    </a>
    <a href="<?= $base_url ?>dichvu.php" class="nav-item <?= $current_page == 'dichvu.php' ? 'active' : '' ?>" style="text-decoration:none;">
      <span class="icon">🛎️</span> Dịch vụ
    </a>
    <a href="<?= $base_url ?>hoadon.php" class="nav-item <?= $current_page == 'hoadon.php' ? 'active' : '' ?>" style="text-decoration:none;">
      <span class="icon">💳</span> Hóa đơn
    </a>
    <?php if ($role == 'admin'): ?>
    <a href="<?= $base_url ?>nhanvien.php" class="nav-item <?= $current_page == 'nhanvien.php' ? 'active' : '' ?>" style="text-decoration:none;">
      <span class="icon">👔</span> Nhân viên
    </a>
    <a href="<?= $base_url ?>quan_ly_voucher.php" class="nav-item <?= $current_page == 'quan_ly_voucher.php' ? 'active' : '' ?>" style="text-decoration:none;">
      <span class="icon">🎟️</span> Quản lý Voucher
    </a>
    <a href="<?= $base_url ?>quan_ly_danhgia.php" class="nav-item <?= $current_page == 'quan_ly_danhgia.php' ? 'active' : '' ?>" style="text-decoration:none;">
      <span class="icon">⭐</span> Quản lý Đánh Giá
    </a>
    <?php endif; ?>

    <div class="nav-section-label">Hệ thống</div>
    <a href="<?= $base_url ?>index.php" class="nav-item" style="text-decoration:none; opacity:0.8;">
      <span class="icon">🌐</span> Cổng Khách Hàng
    </a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-card">
      <div class="avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></div>
      <div class="user-info">
        <div class="name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></div>
        <div class="role"><?= $role == 'admin' ? 'Quản trị viên' : 'Nhân viên' ?></div>
      </div>
    </div>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <header class="topbar">
    <button id="sidebarToggle" class="topbar-btn btn-ghost d-lg-none" style="padding: 0; width: 40px; height: 40px; font-size: 20px;">
      <i class="fa-solid fa-bars"></i>
    </button>
    <div class="topbar-title">K-Hotel <span>✦</span> Admin</div>
    
    <?php
    $pending_count = 0;
    if (isset($conn)) {
        $res_pending = $conn->query("SELECT COUNT(*) as cnt FROM DatPhong WHERE TrangThai = 'Chờ xác nhận'");
        if ($res_pending) {
            $pending_count = $res_pending->fetch_assoc()['cnt'];
        }
    }
    ?>
    <a href="<?= $base_url ?>xacnhan.php" class="notif-btn" style="text-decoration: none; position: relative;" title="<?= $pending_count > 0 ? "Có $pending_count đơn chờ duyệt" : "Không có thông báo mới" ?>">
      🔔
      <?php if ($pending_count > 0): ?>
        <div class="notif-dot" style="display: flex; align-items: center; justify-content: center; width: 16px; height: 16px; top: -2px; right: -2px; font-size: 9px; font-weight: bold; color: white; text-decoration: none;"><?= $pending_count ?></div>
      <?php endif; ?>
    </a>
    <a href="<?= $base_url ?>auth/logout.php" class="topbar-btn btn-ghost" style="text-decoration:none; color:inherit;">Đăng xuất</a>
  </header>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        const main = document.querySelector('.main');
        
        if (toggle && sidebar) {
            toggle.addEventListener('click', function() {
                sidebar.classList.toggle('sidebar-open');
            });
        }
    });
  </script>

  <div class="content">
    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-4" role="alert" style="border-left: 5px solid #198754 !important;">
        <i class="fa-solid fa-circle-check me-2"></i> <?= $_SESSION['success'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert" style="border-left: 5px solid #dc3545 !important;">
        <i class="fa-solid fa-circle-exclamation me-2"></i> <?= $_SESSION['error'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
<?php endif; ?>