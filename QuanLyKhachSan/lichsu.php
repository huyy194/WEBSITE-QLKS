<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

// Admin/nhanvien truy cap khachhang.php de xem lich su toan bo
if (isset($_SESSION['role']) && $_SESSION['role'] != 'khach') {
    header("Location: khachhang.php");
    exit;
}

$userid = $_SESSION['user_id'];
$kh_res = $conn->query("SELECT MaKH FROM KhachHang WHERE MaTK = $userid");
$makh = null;
if ($kh_res && $kh_res->num_rows > 0) {
    $makh = $kh_res->fetch_assoc()['MaKH'];
}

// Xử lý Gửi Đánh Giá
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'danhgia') {
    $maloai = (int) $_POST['maloai'];
    $sosao = (int) $_POST['sosao'];
    if ($sosao < 1)
        $sosao = 1;
    if ($sosao > 5)
        $sosao = 5;
    $nhanxet = $conn->real_escape_string($_POST['nhanxet']);

    // Đánh giá nhân viên
    $manv = isset($_POST['manv']) ? (int) $_POST['manv'] : 0;
    $sosao_nv = isset($_POST['sosao_nv']) ? (int) $_POST['sosao_nv'] : 5;
    $nhanxet_nv = isset($_POST['nhanxet_nv']) ? $conn->real_escape_string($_POST['nhanxet_nv']) : '';

    $madp_dg = (int)($_POST['madp'] ?? 0);
    if ($makh && $madp_dg > 0) {
        // 1. Đánh giá phòng — gắn MaDP, mỗi booking 1 lần
        $check_dg = $conn->query("SELECT MaDG FROM DanhGia WHERE MaDP = $madp_dg LIMIT 1");
        if (!$check_dg || $check_dg->num_rows == 0) {
            $conn->query("INSERT INTO DanhGia (MaKH, MaLoai, MaDP, SoSao, NhanXet) VALUES ($makh, $maloai, $madp_dg, $sosao, '$nhanxet')");
        }
        // 2. Đánh giá nhân viên (nếu có MaNV)
        if ($manv > 0) {
            $conn->query("INSERT INTO DanhGiaNhanVien (MaKH, MaNV, SoSao, NhanXet) VALUES ($makh, $manv, $sosao_nv, '$nhanxet_nv')");
        }
        $success = "Cảm ơn bạn đã gửi đánh giá trải nghiệm!";
    }
}

// Lấy danh sách lịch sử đặt phòng
$bookings = [];
if ($makh) {
    $b_sql = "SELECT dp.*, p.MaPhong, lp.TenLoai, lp.MaLoai, lp.GiaPhong, hd.TongTien, hd.MaNV, tk.HoTen as TenNV
              FROM DatPhong dp 
              JOIN Phong p ON dp.MaPhong = p.MaPhong 
              JOIN LoaiPhong lp ON p.MaLoai = lp.MaLoai 
              LEFT JOIN HoaDon hd ON dp.MaDP = hd.MaDP 
              LEFT JOIN NhanVien nv ON hd.MaNV = nv.MaNV
              LEFT JOIN TaiKhoan tk ON nv.MaTK = tk.MaTK
              WHERE dp.MaKH = $makh 
              GROUP BY dp.MaDP
              ORDER BY dp.NgayCheckIn DESC";
    $b_res = $conn->query($b_sql);
    if ($b_res) {
        while ($r = $b_res->fetch_assoc()) {
            $bookings[] = $r;
        }
    }
}

// Lấy danh sách lịch sử đánh giá của khácdánhh hàng này
$reviews = [];
$danhgia_da_gui = []; // Mảng phụ để lưu các loại phòng đã đánh giá
if ($makh) {
    $rv_sql = "SELECT dg.*, lp.TenLoai 
               FROM DanhGia dg 
               JOIN LoaiPhong lp ON dg.MaLoai = lp.MaLoai 
               WHERE dg.MaKH = $makh 
               ORDER BY dg.NgayDanhGia DESC";
    $rv_res = $conn->query($rv_sql);
    if ($rv_res) {
        while ($rv = $rv_res->fetch_assoc()) {
            $reviews[] = $rv;
            // Lưu lại MaLoai đã đánh giá để ẩn nút đánh giá sau này
            if (!empty($rv['MaDP'])) $danhgia_da_gui[] = (int)$rv['MaDP'];
        }
    }
}


// Kiểm tra khách VIP (đã đặt >= 2 lần thành công)
$so_lan_thanh_toan = 0;
if ($makh) {
    $vip_check = $conn->query("SELECT COUNT(*) as cnt FROM DatPhong WHERE MaKH = $makh AND TrangThai IN ('Đã thanh toán','Đã thanh toán (Online)')");
    $so_lan_thanh_toan = $vip_check->fetch_assoc()['cnt'];
}
$is_vip = $so_lan_thanh_toan >= 2;
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch Sử Đặt Phòng - K-Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary-color: #1e293b;
            --accent-color: #f59e0b;
        }

        body {
            background: #f1f5f9;
            font-family: 'Inter', sans-serif;
        }

        .navbar {
            transition: all 0.3s ease;
        }

        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .star-rating {
            direction: rtl;
            display: inline-flex;
            gap: 4px;
        }

        .star-rating input {
            display: none;
        }

        .star-rating label {
            font-size: 2.5rem;
            cursor: pointer;
            color: #cbd5e1;
            transition: color 0.2s;
        }

        .star-rating input:checked~label,
        .star-rating label:hover,
        .star-rating label:hover~label {
            color: var(--accent-color);
        }

        .voucher-card {
            background: linear-gradient(135deg, #ffffff 0%, #fffbeb 100%);
            border: 2px dashed var(--accent-color);
            position: relative;
            overflow: hidden;
        }

        .voucher-card::before,
        .voucher-card::after {
            content: '';
            position: absolute;
            width: 30px;
            height: 30px;
            background: #f1f5f9;
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
        }

        .voucher-card::before {
            left: -15px;
        }

        .voucher-card::after {
            right: -15px;
        }

        .table thead th {
            background-color: #f8fafc;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .review-card {
            transition: transform 0.2s;
        }

        .review-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>

<body>

    <?php include 'include/header_customer.php'; ?>

    <div class="container my-5 pt-4">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
                <i class="fa-solid fa-check-circle me-2"></i><?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Thông báo Ưu đãi & VIP -->
        <?php if (!$is_vip && $so_lan_thanh_toan == 1): ?>
            <div class="alert bg-white border-start border-4 border-warning d-flex align-items-center gap-3 mb-5 p-4 rounded-4 shadow-sm border-0">
                <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                    <i class="fa-solid fa-fire-pulse text-warning fs-3"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1 text-dark">Chỉ còn 1 bước nữa!</h6>
                    <p class="mb-0 text-muted small">Bạn đã có 1 lần đặt phòng thành công. Hoàn thành thêm <b>1 đơn đặt phòng</b> nữa để nhận ngay <b>ưu đãi giảm 10%</b> trọn đời!</p>
                </div>
                <a href="timkiem.php" class="btn btn-warning text-dark rounded-pill ms-auto fw-bold px-4 shadow-sm">Đặt Ngay</a>
            </div>
        <?php elseif ($is_vip): ?>
            <div class="alert bg-white border-start border-4 border-success d-flex align-items-center gap-3 mb-5 p-4 rounded-4 shadow-sm border-0">
                <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                    <i class="fa-solid fa-crown text-success fs-3"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1 text-success">Chúc mừng! Bạn là Khách hàng VIP</h6>
                    <p class="mb-0 text-muted small">Tận hưởng <b>ưu đãi 10%</b> dành riêng cho bạn. Hãy kiểm tra kho voucher ngay!</p>
                </div>
                <a href="voucher.php" class="btn btn-warning text-dark rounded-pill ms-auto fw-bold px-4 shadow-sm border-0">
                    <i class="fa-solid fa-ticket me-2"></i>Xem Voucher
                </a>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h2 class="fw-bold mb-1">Lịch Sử Đặt Phòng</h2>
                <p class="text-muted mb-0">Quản lý và xem lại tất cả các chuyến đi của bạn</p>
            </div>
        </div>

        <div class="card overflow-hidden shadow-sm border-0 mb-5">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-stackable">
                    <thead>
                        <tr>
                            <th class="ps-4">Mã Đơn</th>
                            <th>Loại Phòng</th>
                            <th>Nhận Phòng</th>
                            <th>Trả Phòng</th>
                            <th>Trạng Thái</th>
                            <th>Tổng Tiền</th>
                            <th class="text-center pe-4">Hành Động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $bk):
                            $ts = $bk['TrangThai'];
                            $bg_class = 'bg-secondary text-white';
                            if ($ts == 'Chờ xác nhận')
                                $bg_class = 'bg-warning text-dark';
                            elseif ($ts == 'Đã xác nhận')
                                $bg_class = 'bg-info text-dark';
                            elseif (strpos($ts, 'Đã thanh toán') === 0)
                                $bg_class = 'bg-success text-white';
                            elseif ($ts == 'Đã huỷ')
                                $bg_class = 'bg-danger text-white';
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary" data-label="Mã Đơn">#<?= $bk['MaDP'] ?></td>
                                <td data-label="Loại Phòng">
                                    <div class="fw-bold text-dark text-md-start text-end">
                                        <?= htmlspecialchars($bk['TenLoai']) ?>
                                    </div>
                                    <div class="small text-muted text-md-start text-end">Phòng: <?= $bk['MaPhong'] ?></div>
                                </td>
                                <td data-label="Nhận Phòng"><?= date('d/m/Y', strtotime($bk['NgayCheckIn'])) ?></td>
                                <td data-label="Trả Phòng"><?= date('d/m/Y', strtotime($bk['NgayCheckOut'])) ?></td>
                                <td data-label="Trạng Thái">
                                    <span class="status-badge <?= $bg_class ?>"><?= htmlspecialchars($ts) ?></span>
                                </td>
                                <td class="fw-bold text-dark" data-label="Tổng Tiền">
                                    <?php 
                                        if($bk['TongTien'] > 0) {
                                            echo number_format($bk['TongTien']) . ' ₫';
                                        } else {
                                            // Tính giá dự kiến nếu chưa có hóa đơn hoặc hóa đơn bị lỗi 0đ
                                            $ci = new DateTime($bk['NgayCheckIn']);
                                            $co = new DateTime($bk['NgayCheckOut']);
                                            $days = max(1, $co->diff($ci)->days);
                                            $est = $days * $bk['GiaPhong'];
                                            // Áp dụng giảm 10% nếu ở từ 2 đêm trở lên
                                            if ($days >= 2) $est = round($est * 0.9);
                                            echo '<span class="text-muted small">Dự kiến:</span><br>' . number_format($est) . ' ₫';
                                        }
                                    ?>
                                </td>
                                <td class="text-center pe-4" data-label="Hành Động">
                                    <?php
                                    $co_the_danh_gia = (strpos($bk['TrangThai'], 'Đã thanh toán') === 0);
                                    $da_danh_gia = in_array((int)$bk['MaDP'], $danhgia_da_gui);

                                    if ($co_the_danh_gia && !$da_danh_gia): ?>
                                        <button class="btn btn-sm btn-outline-warning fw-bold rounded-pill px-3"
                                            data-bs-toggle="modal" data-bs-target="#reviewModal"
                                            onclick="setReviewData(<?= $bk['MaLoai'] ?>, '<?= addslashes($bk['TenLoai']) ?>', <?= (int) $bk['MaNV'] ?>, '<?= addslashes($bk['TenNV'] ?: 'Nhân viên phục vụ') ?>', <?= (int)$bk['MaDP'] ?>)">
                                            <i class="fa-solid fa-star me-1"></i> Đánh giá
                                        </button>
                                    <?php elseif ($co_the_danh_gia && $da_danh_gia): ?>
                                        <span class="badge bg-light text-success border"><i
                                                class="fa-solid fa-check-circle me-1"></i>Đã gửi ĐG</span>
                                    <?php else: ?>
                                        <span class="text-muted small">Chờ hoàn tất</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($bookings) == 0): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <p class="text-muted mb-3">Bạn chưa có lịch sử đặt phòng nào.</p>
                                    <a href="timkiem.php" class="btn btn-primary rounded-pill px-4">Khám phá phòng ngay</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Lịch Sử Đánh Giá -->
        <div class="mb-5">
            <div class="d-flex justify-content-between align-items-end mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Đánh Giá Của Bạn</h3>
                    <p class="text-muted mb-0">Những chia sẻ của bạn giúp chúng tôi cải thiện dịch vụ tốt hơn</p>
                </div>
            </div>

            <div class="row">
                <?php if (count($reviews) > 0): ?>
                    <?php foreach ($reviews as $rv): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card review-card h-100 border-0 shadow-sm rounded-4">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($rv['TenLoai']) ?></h5>
                                            <div class="text-warning">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fa-<?= $i <= $rv['SoSao'] ? 'solid' : 'regular' ?> fa-star small"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <span class="small text-muted"><i
                                                class="fa-regular fa-calendar me-1"></i><?= date('d/m/Y', strtotime($rv['NgayDanhGia'])) ?></span>
                                    </div>
                                    <div class="bg-light p-3 rounded-3 position-relative">
                                        <i
                                            class="fa-solid fa-quote-left text-warning opacity-25 position-absolute top-0 start-0 m-2"></i>
                                        <p class="card-text text-secondary mb-0 ps-3 italic">
                                            <?= nl2br(htmlspecialchars($rv['NhanXet'])) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="text-center py-5 bg-white rounded-4 shadow-sm">
                            <i class="fa-solid fa-comments text-muted opacity-25 fs-1 mb-3"></i>
                            <p class="text-muted mb-0">Bạn chưa có đánh giá nào.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Đánh Giá -->
    <div class="modal fade" id="reviewModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" method="POST">
                <div class="modal-header border-0 bg-primary text-white p-4">
                    <h5 class="modal-title fw-bold mx-auto"><i class="fa-solid fa-star me-2"></i>Đánh Giá Trải Nghiệm
                    </h5>
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="danhgia">
                    <input type="hidden" name="maloai" id="rv_maloai">
                    <input type="hidden" name="madp" id="rv_madp">

                    <div class="text-center mb-4">
                        <h6 class="text-muted mb-2">Loại phòng:</h6>
                        <h4 class="fw-bold text-primary mb-0" id="rv_tenloai"></h4>
                    </div>

                    <div class="mb-4 text-center">
                        <label class="form-label fw-bold d-block mb-3">Bạn cảm thấy thế nào?</label>
                        <div class="star-rating justify-content-center">
                            <input type="radio" name="sosao" value="5" id="s5" checked><label for="s5"><i
                                    class="fa-solid fa-star"></i></label>
                            <input type="radio" name="sosao" value="4" id="s4"><label for="s4"><i
                                    class="fa-solid fa-star"></i></label>
                            <input type="radio" name="sosao" value="3" id="s3"><label for="s3"><i
                                    class="fa-solid fa-star"></i></label>
                            <input type="radio" name="sosao" value="2" id="s2"><label for="s2"><i
                                    class="fa-solid fa-star"></i></label>
                            <input type="radio" name="sosao" value="1" id="s1"><label for="s1"><i
                                    class="fa-solid fa-star"></i></label>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Nhận xét về phòng</label>
                        <textarea class="form-control rounded-3 border-2" name="nhanxet" rows="2"
                            placeholder="Hãy chia sẻ trải nghiệm của bạn tại đây..." required></textarea>
                    </div>

                    <hr class="my-4">

                    <div id="staff_rating_section" style="display:none;">
                        <input type="hidden" name="manv" id="rv_manv">
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 bg-light">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Bỏ
                        qua</button>
                    <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">Gửi đánh
                        giá</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Phiếu Ưu Đãi -->
    <?php if ($is_vip && $makh): ?>
        <?php
        $kh_info = $conn->query("SELECT HoTen FROM KhachHang WHERE MaKH = $makh")->fetch_assoc();
        // Lấy mã LOYAL thật từ DB (ưu tiên active, không thì lấy mới nhất)
        $loyal_res = $conn->query("SELECT Code, NgayHetHan, TrangThai FROM Voucher WHERE MaKH = $makh AND TenVoucher = 'Khách Hàng Thân Thiết 10%' ORDER BY FIELD(TrangThai,'active','inactive'), MaVC DESC LIMIT 1");
        $loyal_voucher = ($loyal_res && $loyal_res->num_rows > 0) ? $loyal_res->fetch_assoc() : null;
        $voucher_code = $loyal_voucher ? $loyal_voucher['Code'] : null;
        $voucher_expiry = $loyal_voucher ? date('d/m/Y', strtotime($loyal_voucher['NgayHetHan'])) : '—';
        $voucher_active = $loyal_voucher && $loyal_voucher['TrangThai'] == 'active';
        ?>
        <div class="modal fade" id="myVoucherModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
                    <div class="modal-header border-0 pb-0 bg-warning text-dark">
                        <div class="w-100 text-center py-4">
                            <i class="fa-solid fa-crown fs-1 mb-2 d-block"></i>
                            <h4 class="fw-bold mb-0">ĐẶC QUYỀN VIP</h4>
                            <p class="opacity-75 mb-0 small text-uppercase fw-bold">K-Hotel Loyalty Member</p>
                        </div>
                        <button type="button" class="btn-close position-absolute top-0 end-0 m-3"
                            data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center p-4">
                        <div class="border border-warning border-2 rounded-4 p-4 mb-3 position-relative"
                            style="background: #fffdf5;">
                            <p class="text-muted mb-1 small">Kính gửi Quý Khách:</p>
                            <h4 class="fw-bold text-dark mb-3"><?= htmlspecialchars($kh_info['HoTen']) ?></h4>
                            <?php if ($voucher_code): ?>
                            <div class="bg-white border border-warning text-dark d-inline-block px-4 py-2 rounded-3 fw-bold mb-3 shadow-sm"
                                style="font-size: 1.4rem; letter-spacing: 2px; font-family: monospace;"><?= htmlspecialchars($voucher_code) ?>
                            </div>
                            <?php if (!$voucher_active): ?>
                                <div class="badge bg-danger d-block mx-auto mb-2" style="max-width:fit-content;">Voucher đã được sử dụng</div>
                            <?php endif; ?>
                            <?php else: ?>
                            <div class="alert alert-info small py-2 mb-3">
                                <i class="fa-solid fa-info-circle me-1"></i>
                                Voucher đang xử lý. Vui lòng xem tại <a href="voucher.php" class="fw-bold">trang Voucher</a>.
                            </div>
                            <?php endif; ?>
                            <p class="fw-bold text-danger fs-5 mb-1">GIẢM NGAY <span class="display-5 fw-black">10%</span>
                            </p>
                            <p class="text-muted small">Cho đơn đặt phòng tiếp theo</p>
                            <div class="d-flex justify-content-between text-muted x-small mt-4 border-top pt-3">
                                <span><i class="fa-solid fa-clock me-1"></i> HSD: <?= $voucher_expiry ?></span>
                                <span><i class="fa-solid fa-location-dot me-1"></i> Toàn hệ thống</span>
                            </div>
                        </div>
                        <p class="text-muted small">Nhập mã khi đặt phòng online hoặc thông báo cho nhân viên khi check-in.</p>
                    </div>
                    <div class="modal-footer border-0 pb-4 justify-content-center gap-2">
                        <?php if ($voucher_code && $voucher_active): ?>
                        <button class="btn btn-outline-secondary rounded-pill px-4"
                            onclick="navigator.clipboard.writeText('<?= htmlspecialchars($voucher_code, ENT_QUOTES) ?>').then(()=>this.innerHTML='<i class=\'fa-solid fa-check me-2\'></i>Đã sao chép!').catch(()=>alert('Mã: <?= htmlspecialchars($voucher_code, ENT_QUOTES) ?>'))"
                        ><i class="fa-solid fa-copy me-2"></i>Sao chép mã</button>
                        <?php endif; ?>
                        <a href="voucher.php" class="btn btn-warning fw-bold rounded-pill px-4 shadow-sm">
                            <i class="fa-solid fa-ticket me-2"></i>Xem tất cả Voucher
                        </a>
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Đóng</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <footer class="bg-dark text-white py-5">
        <div class="container text-center">
            <h4 class="fw-bold mb-3">K-Hotel Việt Nam</h4>
            <div class="d-flex justify-content-center gap-4 mb-4">
                <a href="#" class="text-white opacity-75 text-decoration-none"><i
                        class="fa-brands fa-facebook-f fs-5"></i></a>
                <a href="#" class="text-white opacity-75 text-decoration-none"><i
                        class="fa-brands fa-instagram fs-5"></i></a>
                <a href="#" class="text-white opacity-75 text-decoration-none"><i
                        class="fa-brands fa-youtube fs-5"></i></a>
            </div>
            <p class="opacity-75 small">Hotline: 1900 1234 | Email: support@khotel.vn</p>
            <hr class="opacity-25 my-4">
            <p class="opacity-50 x-small m-0">&copy; 2026 K-Hotel Hospitality Group. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function setReviewData(maloai, tenloai, manv, tennv, madp) {
            document.getElementById('rv_maloai').value = maloai;
            document.getElementById('rv_madp').value = madp;
            document.getElementById('rv_tenloai').innerText = tenloai;

            if (manv > 0) {
                document.getElementById('staff_rating_section').style.display = 'block';
                document.getElementById('rv_manv').value = manv;
                document.getElementById('rv_tennv').innerText = tennv;
            } else {
                document.getElementById('staff_rating_section').style.display = 'none';
                document.getElementById('rv_manv').value = 0;
            }
        }
    </script>
</body>

</html>