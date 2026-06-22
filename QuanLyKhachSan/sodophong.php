<?php
require_once 'config/database.php';
// Tự động cập nhật trạng thái phòng/booking mỗi khi admin xem sơ đồ phòng
require_once 'config/auto_update.php';

// Cập nhật trạng thái phòng
$now = date('Y-m-d H:i:s');
if (isset($_GET['action']) && isset($_GET['id']) && $_GET['action'] === 'checkin') {
    $id = $conn->real_escape_string($_GET['id']);
    // Tìm đơn đặt phòng hợp lệ (đã tới giờ check-in)
    $booking = $conn->query("SELECT MaDP FROM DatPhong WHERE MaPhong = '$id' AND TrangThai IN ('Đã xác nhận', 'Đã thanh toán (Online)', 'Chờ xác nhận') AND NgayCheckIn <= '$now' ORDER BY MaDP ASC LIMIT 1")->fetch_assoc();
    if ($booking) {
        $madp = $booking['MaDP'];
        $conn->query("UPDATE DatPhong SET TrangThai = 'Đang ở' WHERE MaDP = $madp");
        // Cập nhật luôn bảng Phong để thẻ phòng chuyển đỏ ngay trên Sơ đồ
        $conn->query("UPDATE Phong SET TrangThai = 'Đang ở' WHERE MaPhong = '$id'");
    }
    header("Location: sodophong.php");
    exit;
} elseif (isset($_GET['action']) && isset($_GET['id']) && isset($_GET['status'])) {
    $id = $conn->real_escape_string($_GET['id']);
    $status = $conn->real_escape_string($_GET['status']);

    if ($status === 'Trống') {
        // Huỷ khẩn cấp: cập nhật đơn đặt phòng đang active và trả phòng về Trống
        $conn->query("UPDATE DatPhong SET TrangThai = 'Đã huỷ' WHERE MaPhong = '$id' AND TrangThai IN ('Đang ở', 'Đã xác nhận', 'Chờ xác nhận') ORDER BY MaDP DESC LIMIT 1");
        $conn->query("UPDATE Phong SET TrangThai = 'Trống' WHERE MaPhong = '$id'");
    } elseif ($status === 'Đang dọn dẹp') {
        $conn->query("UPDATE Phong SET TrangThai = 'Đang dọn dẹp' WHERE MaPhong = '$id'");
    }
    header("Location: sodophong.php");
    exit;
}

require_once 'include/header.php';

$sql = "SELECT p.*, lp.TenLoai, lp.GiaPhong, lp.KhuVuc,
        (SELECT NgayCheckOut FROM DatPhong dp WHERE dp.MaPhong = p.MaPhong AND dp.TrangThai IN ('Đang ở', 'Đã xác nhận', 'Đã thanh toán (Online)', 'Chờ xác nhận') ORDER BY MaDP DESC LIMIT 1) as NgayCheckOut 
        FROM Phong p 
        JOIN LoaiPhong lp ON p.MaLoai = lp.MaLoai 
        ORDER BY lp.KhuVuc ASC, p.MaPhong ASC";
$result = $conn->query($sql);

$rooms_by_region = [];
$counts = ['Tổng' => 0, 'Trống' => 0, 'Đang ở' => 0, 'Đang dọn dẹp' => 0];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $region = $row['KhuVuc'] ?? 'Khác';
        $rooms_by_region[$region][] = $row;
        $counts['Tổng']++;
        $counts[$row['TrangThai']] = ($counts[$row['TrangThai']] ?? 0) + 1;
    }
}

$region_icons = [
    'Phan Thiết' => 'fa-umbrella-beach',
    'Hà Nội' => 'fa-landmark',
    'Hồ Chí Minh' => 'fa-city',
    'Tây Ninh' => 'fa-mountain-sun',
    'Đà Nẵng' => 'fa-water',
];
$region_colors = [
    'Phan Thiết' => '#0ea5e9',
    'Hà Nội' => '#ef4444',
    'Hồ Chí Minh' => '#8b5cf6',
    'Tây Ninh' => '#22c55e',
    'Đà Nẵng' => '#f59e0b',
];
$region_images = [
    'Phan Thiết' => 'assets/img/phanthiet.png',
    'Tây Ninh' => 'assets/img/tayninh.png',
    'Hà Nội' => 'assets/img/hanoi.png',
    'Hồ Chí Minh' => 'https://images.unsplash.com/photo-1583417319070-4a69db38a482?q=80&w=800&auto=format&fit=crop',
    'Đà Nẵng' => 'https://images.unsplash.com/photo-1559592413-7cec4d0cae2b?q=80&w=800&auto=format&fit=crop',
];
?>

<style>
@keyframes pulse {
    0%   { opacity: 1; transform: scale(1); }
    50%  { opacity: 0.65; transform: scale(1.06); }
    100% { opacity: 1; transform: scale(1); }
}
@keyframes borderGlow {
    0%   { box-shadow: 0 0 0px 0px rgba(245, 158, 11, 0.8); }
    50%  { box-shadow: 0 0 16px 6px rgba(245, 158, 11, 0.9); }
    100% { box-shadow: 0 0 0px 0px rgba(245, 158, 11, 0.8); }
}
.room-cho-nhan {
    border: 2px solid #f59e0b !important;
    animation: borderGlow 1.4s ease-in-out infinite;
}
.room-cho-nhan .fa-bell {
    animation: pulse 1s ease-in-out infinite;
}
</style>




<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="fw-bold m-0"><i class="fa-solid fa-bed text-primary me-2"></i> Sơ đồ phòng <small class="text-muted fs-6">(<?= $counts['Tổng'] ?> phòng)</small></h2>
        <div class="d-flex gap-2 flex-wrap">
            <span class="badge bg-success p-2 fs-6 shadow-sm"><i class="fa-solid fa-door-open"></i> Trống (<?= $counts['Trống'] ?>)</span>
            <span class="badge bg-danger p-2 fs-6 shadow-sm"><i class="fa-solid fa-user"></i> Đang ở (<?= $counts['Đang ở'] ?>)</span>
            <span class="badge bg-warning text-dark p-2 fs-6 shadow-sm"><i class="fa-solid fa-broom"></i> Đang dọn (<?= $counts['Đang dọn dẹp'] ?>)</span>
        </div>
    </div>
</div>

<?php foreach ($rooms_by_region as $region => $rooms): 
    $icon = $region_icons[$region] ?? 'fa-map-marker-alt';
    $color = $region_colors[$region] ?? '#64748b';
    $bg_img = $region_images[$region] ?? '';
    $region_free = 0;
    foreach ($rooms as $r) { if ($r['TrangThai'] == 'Trống') $region_free++; }
?>


<div class="mb-4">
    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
        <i class="fa-solid <?= $icon ?> fs-4" style="color: <?= $color ?>"></i>
        <h4 class="fw-bold m-0"><?= htmlspecialchars($region) ?></h4>
        <span class="badge bg-light text-dark border ms-2"><?= count($rooms) ?> phòng</span>
        <span class="badge bg-success-subtle text-success"><?= $region_free ?> trống</span>
    </div>
    <div class="row g-3">
        <?php foreach ($rooms as $room): 
            // Kiểm tra trước: phòng này có khách cần nhận phòng ngay không?
            $checkin_pending = $conn->query("
                SELECT NgayCheckIn FROM DatPhong 
                WHERE MaPhong = '{$room['MaPhong']}' 
                AND TrangThai IN ('Đã xác nhận', 'Đã thanh toán (Online)', 'Chờ xác nhận') 
                AND NgayCheckIn <= '$now'
                ORDER BY NgayCheckIn ASC LIMIT 1
            ")->fetch_assoc();

            $statusClass   = '';
            $iconClass     = '';
            $statusOverlay = '';
            $extraBadge    = '';

            if ($room['TrangThai'] == 'Trống' && $checkin_pending) {
                // Trạng thái đặc biệt: Đến giờ nhận phòng nhưng chưa bấm xác nhận
                $statusClass   = 'room-cho-nhan';
                $iconClass     = 'fa-bell text-warning';
                $statusOverlay = 'rgba(180, 83, 9, 0.65)';
                $extraBadge    = '<span class="badge bg-warning text-dark position-absolute top-0 end-0 m-2" style="font-size:0.65rem;z-index:3;animation:pulse 1s infinite;">🔑 Chờ nhận phòng</span>';
            } elseif ($room['TrangThai'] == 'Trống') {
                $statusClass   = 'room-trong';
                $iconClass     = 'fa-door-open text-success';
                $statusOverlay = 'rgba(0, 0, 0, 0.4)';
            } elseif ($room['TrangThai'] == 'Đang ở') {
                $statusClass   = 'room-dang-o';
                $iconClass     = 'fa-user text-danger';
                $statusOverlay = 'rgba(0, 0, 0, 0.5)';
            } else {
                $statusClass   = 'room-don-dep';
                $iconClass     = 'fa-broom text-warning';
                $statusOverlay = 'rgba(0, 0, 0, 0.5)';
            }
        ?>
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card h-100 <?= $statusClass ?> shadow-sm dropdown border-0" style="background-image: url('<?= $bg_img ?>'); background-size: cover; background-position: center; position: relative; border-radius: 12px; overflow: visible !important;">
                <div class="position-absolute top-0 start-0 w-100 h-100" style="background: <?= $statusOverlay ?>; backdrop-filter: blur(2px); border-radius: 12px;"></div>
                <?= $extraBadge ?>
                <div data-bs-toggle="dropdown" aria-expanded="false" class="room-card text-decoration-none position-relative text-white" style="z-index: 2; padding: 20px 10px; background: transparent; border: none; box-shadow: none;">
                    <h3 class="fw-bold mb-1" style="text-shadow: 1px 1px 3px rgba(0,0,0,0.5);"><?= $room['MaPhong'] ?></h3>
                    <i class="fa-solid <?= $iconClass ?> fs-1 my-2" style="filter: drop-shadow(1px 1px 2px rgba(0,0,0,0.5));"></i>
                    <h6 class="mb-0 fw-medium" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.5);"><?= $room['TenLoai'] ?></h6>
                    <small style="text-shadow: 1px 1px 2px rgba(0,0,0,0.5);"><?= number_format($room['GiaPhong']) ?>đ</small>
                    <?php if ($checkin_pending): ?>
                        <div class="mt-2 text-warning fw-bold" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-clock-rotate-left me-1"></i> C/I: <?= date('d/m H:i', strtotime($checkin_pending['NgayCheckIn'])) ?>
                        </div>
                    <?php elseif ($room['TrangThai'] == 'Đang ở' && $room['NgayCheckOut']): ?>
                        <div class="mt-2 text-warning" style="font-size: 0.8rem;">
                            <i class="fa-regular fa-clock"></i> C/O: <?= date('d/m H:i', strtotime($room['NgayCheckOut'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 text-center p-2 rounded-3">
                    <li class="mb-2"><span class="badge bg-secondary mb-1 w-100">Chi tiết phòng <?= $room['MaPhong'] ?></span></li>
                    <?php if ($room['TrangThai'] == 'Trống'): ?>
                        <?php if ($checkin_pending): ?>
                            <li><a class="dropdown-item fw-bold text-white bg-warning rounded mb-2" href="?action=checkin&id=<?= $room['MaPhong'] ?>"><i class="fa-solid fa-key me-2"></i> Cho khách nhận phòng</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item fw-medium text-primary rounded" href="datphong.php?room=<?= $room['MaPhong'] ?>"><i class="fa-solid fa-plus-circle me-2"></i> Đặt phòng này</a></li>
                        <li><a class="dropdown-item fw-medium text-warning rounded mt-1" href="?action=update&id=<?= $room['MaPhong'] ?>&status=Đang dọn dẹp"><i class="fa-solid fa-broom me-2"></i> Báo dọn dẹp</a></li>
                    <?php elseif ($room['TrangThai'] == 'Đang ở'): ?>
                        <li><a class="dropdown-item fw-medium text-danger rounded" href="hoadon.php?room=<?= $room['MaPhong'] ?>"><i class="fa-solid fa-money-bill me-2"></i> Thanh toán / Trả phòng</a></li>
                        <li><a class="dropdown-item fw-medium text-success rounded mt-1" href="themdichvu.php?room=<?= $room['MaPhong'] ?>"><i class="fa-solid fa-concierge-bell me-2"></i> Thêm dịch vụ</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item fw-bold text-danger bg-danger bg-opacity-10 rounded mt-1" href="?action=update&id=<?= $room['MaPhong'] ?>&status=Trống" onclick="return confirm('CẢNH BÁO: BẠN CÓ CHẮC MUỐN HỦY PHÒNG KHẨN CẤP?')"><i class="fa-solid fa-triangle-exclamation me-2"></i> Hủy phòng khẩn cấp</a></li>
                    <?php elseif ($room['TrangThai'] == 'Đang dọn dẹp'): ?>
                        <li><a class="dropdown-item fw-medium text-success rounded" href="?action=update&id=<?= $room['MaPhong'] ?>&status=Trống"><i class="fa-solid fa-check-circle me-2"></i> Đã dọn xong (Sẵn sàng)</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
</div>
<?php endforeach; ?>

<?php require_once 'include/footer.php'; ?>
