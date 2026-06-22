<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'khach') {
    header("Location: auth/login.php");
    exit;
}

$userid = $_SESSION['user_id'];
$kh_res = $conn->query("SELECT MaKH, HoTen, DiemTichLuy, HangThanhVien FROM KhachHang WHERE MaTK = $userid");
$khachhang = null;
$makh = null;
if ($kh_res && $kh_res->num_rows > 0) {
    $khachhang = $kh_res->fetch_assoc();
    $makh = $khachhang['MaKH'];
}

$today = date('Y-m-d');

// Lấy voucher dành riêng cho khách hàng này
$vouchers = [];
if ($makh) {
    $v_sql = "SELECT * FROM Voucher 
              WHERE MaKH = $makh
              AND TrangThai = 'active'
              AND NgayHetHan >= '$today'
              AND SoLanDaDung < GioiHanDung
              ORDER BY NgayHetHan ASC";
    $v_res = $conn->query($v_sql);
    if ($v_res) {
        while ($v = $v_res->fetch_assoc()) $vouchers[] = $v;
    }
}

// Lấy voucher đã hết hạn / đã dùng
$expired_vouchers = [];
if ($makh) {
    $ev_sql = "SELECT * FROM Voucher 
               WHERE MaKH = $makh
               AND (TrangThai != 'active' OR NgayHetHan < '$today' OR SoLanDaDung >= GioiHanDung)
               ORDER BY NgayHetHan DESC LIMIT 5";
    $ev_res = $conn->query($ev_sql);
    if ($ev_res) {
        while ($ev = $ev_res->fetch_assoc()) $expired_vouchers[] = $ev;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher Ưu Đãi - K-Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #f1f5f9; font-family: 'Inter', sans-serif; }
        .voucher-ticket {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .voucher-ticket:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        .voucher-left {
            background: linear-gradient(135deg, #1e3a5f 0%, #0d2137 100%);
            color: white;
            padding: 24px 20px;
            min-width: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
        }
        .voucher-left::after {
            content: '';
            position: absolute;
            right: -14px;
            top: 0;
            bottom: 0;
            width: 28px;
            background: radial-gradient(circle at left, #f1f5f9 50%, transparent 51%) center / 28px 28px repeat-y;
        }
        .voucher-discount {
            font-size: 2rem;
            font-weight: 900;
            line-height: 1;
            color: #f59e0b;
        }
        .voucher-type {
            font-size: 0.7rem;
            opacity: 0.7;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .voucher-right {
            padding: 20px 28px 20px 36px;
            flex: 1;
        }
        .voucher-code-box {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 8px 16px;
            font-family: monospace;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 2px;
            color: #1e293b;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .voucher-code-box:hover {
            border-color: #f59e0b;
            background: #fffbeb;
        }
        .voucher-code-box.copied {
            border-color: #22c55e;
            background: #f0fdf4;
            color: #16a34a;
        }
        .tag-personal { background: #fef3c7; color: #92400e; }
        .tag-public { background: #dbeafe; color: #1e40af; }
        .tag-expiring { background: #fee2e2; color: #991b1b; }
        .hero-voucher {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 80px 0 100px;
            position: relative;
        }
        .hero-voucher::before {
            content: '';
            position: absolute;
            top: -20%;
            right: -5%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(245,158,11,0.12), transparent 70%);
            border-radius: 50%;
        }
        .section-title {
            color: #1e293b;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .voucher-ticket {
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>

<?php include 'include/header_customer.php'; ?>

<!-- Hero Section -->
<div class="hero-voucher text-white">
    <div class="container position-relative">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="d-inline-block px-3 py-1 rounded-pill mb-3" style="background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3);">
                    <span class="text-warning fw-bold text-uppercase" style="letter-spacing:1px; font-size:0.75rem;">
                        <i class="fa-solid fa-star me-2"></i>Đặc quyền thành viên
                    </span>
                </div>
                <h1 class="display-4 fw-bold mb-3" style="text-shadow: 0 2px 10px rgba(0,0,0,0.2);">Voucher & Quà tặng</h1>
                <p class="opacity-75 fs-5 mb-0">Khám phá các mã ưu đãi độc quyền dành riêng cho kỳ nghỉ của bạn.</p>
            </div>
            <div class="col-lg-5 d-none d-lg-block">
                <div class="text-end opacity-25">
                    <i class="fa-solid fa-gift" style="font-size: 12rem;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container" style="margin-top: -50px; position: relative; z-index: 10;">

    <!-- Banner chính sách giảm giá 10% -->
    <div class="card border-0 shadow-lg rounded-4 mb-4 overflow-hidden">
        <div class="d-flex align-items-stretch">
            <div class="d-flex align-items-center justify-content-center px-4 py-3" style="background:linear-gradient(135deg,#f59e0b,#d97706); min-width:80px;">
                <i class="fa-solid fa-percent text-white" style="font-size:2.5rem;"></i>
            </div>
            <div class="p-4 flex-grow-1">
                <h5 class="fw-bold text-dark mb-1">Tự Động Giảm Giá <span class="text-warning">10%</span> — Không Cần Mã!</h5>
                <p class="text-muted mb-0 small">Giá vé của bạn sẽ tự động được giảm <strong>10%</strong> nếu:
                    <span class="badge bg-primary ms-1"><i class="fa-solid fa-moon me-1"></i>Đặt 2 đêm trở lên</span>
                    hoặc
                    <span class="badge bg-warning text-dark ms-1"><i class="fa-solid fa-repeat me-1"></i>Đã đặt phòng 2 lần thành công</span>
                    — Ưu đãi được áp dụng tự động khi thanh toán, không cần nhập mã.
                </p>
            </div>
            <div class="d-none d-md-flex align-items-center pe-4">
                <a href="timkiem.php" class="btn btn-warning fw-bold rounded-pill px-4 text-dark shadow-sm">
                    <i class="fa-solid fa-bed me-2"></i>Đặt ngay
                </a>
            </div>
        </div>
    </div>

    <!-- Thẻ thành viên nhanh -->
    <?php if($khachhang): ?>
    <div class="card border-0 shadow-lg rounded-4 mb-5 p-1" style="background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);">
        <div class="card-body p-4 d-flex flex-column flex-md-row align-items-center justify-content-between gap-4">
            <div class="d-flex align-items-center gap-4">
                <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width:64px;height:64px; border: 4px solid #fff;">
                    <i class="fa-solid fa-user-tie text-dark fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small text-uppercase fw-bold mb-1" style="letter-spacing:1px;">Xin chào,</div>
                    <h4 class="fw-bold text-dark mb-0"><?= htmlspecialchars($khachhang['HoTen']) ?></h4>
                </div>
            </div>
            <div class="d-flex gap-4 text-center text-md-start">
                <div class="border-start ps-4 d-none d-md-block">
                    <div class="text-muted small mb-1">Hạng thành viên</div>
                    <span class="badge bg-warning text-dark px-3 py-2 fs-6 fw-bold shadow-sm"><?= $khachhang['HangThanhVien'] ?></span>
                </div>
                <div class="border-start ps-4">
                    <div class="text-muted small mb-1">Điểm tích lũy</div>
                    <div class="fw-bold text-primary fs-5"><?= number_format($khachhang['DiemTichLuy']) ?> <small>điểm</small></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Voucher Cards Active -->
    <div class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h3 class="section-title mb-0">
                <i class="fa-solid fa-ticket text-primary me-2"></i>Voucher của bạn
                <span class="badge rounded-pill bg-primary ms-2 fs-6" style="padding: 5px 12px;"><?= count($vouchers) ?></span>
            </h3>
            <a href="timkiem.php" class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm">
                <i class="fa-solid fa-plus me-2"></i>Sử dụng ngay
            </a>
        </div>

        <?php if (count($vouchers) > 0): ?>
        <div class="row g-4">
            <?php foreach($vouchers as $vc): 
                $days_left = (strtotime($vc['NgayHetHan']) - time()) / 86400;
                $is_personal = !is_null($vc['MaKH']);
                $is_expiring = $days_left <= 7;
            ?>
            <div class="col-12 col-md-6">
                <div class="voucher-ticket">
                    <div class="d-flex">
                        <div class="voucher-left">
                            <div class="voucher-type mb-1"><?= $vc['LoaiGiam'] == 'phantram' ? 'Giảm' : 'Tiết kiệm' ?></div>
                            <div class="voucher-discount">
                                <?= $vc['LoaiGiam'] == 'phantram' ? $vc['GiaTriGiam'].'%' : number_format($vc['GiaTriGiam']).'₫' ?>
                            </div>
                            <div class="voucher-type mt-1">OFF</div>
                        </div>
                        <div class="voucher-right">
                            <div class="d-flex align-items-start justify-content-between mb-2">
                                <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($vc['TenVoucher']) ?></h6>
                                <?php if($is_personal): ?>
                                    <span class="badge tag-personal ms-2"><i class="fa-solid fa-user me-1"></i>Riêng tư</span>
                                <?php else: ?>
                                    <span class="badge tag-public ms-2"><i class="fa-solid fa-globe me-1"></i>Công khai</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if($vc['GhiChu']): ?>
                            <p class="text-muted small mb-2"><?= htmlspecialchars($vc['GhiChu']) ?></p>
                            <?php endif; ?>
                            
                            <?php if($vc['GiaTriToiThieu'] > 0): ?>
                            <p class="text-muted small mb-2"><i class="fa-solid fa-info-circle me-1"></i>Đơn tối thiểu: <?= number_format($vc['GiaTriToiThieu']) ?>₫</p>
                            <?php endif; ?>

                            <div class="d-flex align-items-center justify-content-between mt-2 flex-wrap gap-2">
                                <div class="voucher-code-box" onclick="copyCode(this, '<?= $vc['Code'] ?>')" title="Nhấn để sao chép">
                                    <?= htmlspecialchars($vc['Code']) ?>
                                    <i class="fa-regular fa-copy text-muted small"></i>
                                </div>
                                <div class="text-end">
                                    <?php if($is_expiring): ?>
                                        <span class="badge tag-expiring"><i class="fa-solid fa-fire me-1"></i>Còn <?= ceil($days_left) ?> ngày</span>
                                    <?php else: ?>
                                        <span class="small text-muted"><i class="fa-regular fa-calendar me-1"></i>HSD: <?= date('d/m/Y', strtotime($vc['NgayHetHan'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-voucher text-center py-5 bg-white rounded-4 shadow-sm">
            <i class="fa-solid fa-ticket-simple text-muted mb-4 d-block" style="font-size:4rem; opacity:0.2;"></i>
            <h5 class="fw-bold text-dark mb-2">Chưa có voucher nào</h5>
            <p class="text-muted mb-4">Bạn chưa có voucher khả dụng. Hãy đặt phòng để nhận ưu đãi đặc biệt!</p>
            <a href="timkiem.php" class="btn btn-primary rounded-pill px-5 fw-bold">
                <i class="fa-solid fa-hotel me-2"></i>Khám phá phòng ngay
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Cách nhận voucher -->
    <div class="bg-white rounded-4 shadow-sm p-4 p-md-5 mb-5">
        <h4 class="fw-bold mb-4"><i class="fa-solid fa-gift text-warning me-2"></i>Cách Nhận Thêm Voucher</h4>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="d-flex gap-3 align-items-start">
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3 flex-shrink-0">
                        <i class="fa-solid fa-bed fs-4"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">Đặt phòng 2+ lần</h6>
                        <p class="text-muted small mb-0">Hoàn thành từ 2 lần đặt phòng, nhận ngay voucher VIP giảm 10% cho lần sau.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-3 align-items-start">
                    <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-3 flex-shrink-0">
                        <i class="fa-solid fa-star fs-4"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">Gửi đánh giá</h6>
                        <p class="text-muted small mb-0">Chia sẻ trải nghiệm của bạn sau mỗi lần lưu trú để nhận điểm thưởng.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-3 align-items-start">
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-3 flex-shrink-0">
                        <i class="fa-solid fa-envelope fs-4"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">Nhận từ nhân viên</h6>
                        <p class="text-muted small mb-0">Nhân viên K-Hotel sẽ gửi voucher đặc biệt trực tiếp đến tài khoản của bạn.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Voucher hết hạn -->
    <?php if(count($expired_vouchers) > 0): ?>
    <div class="mb-5">
        <h4 class="fw-bold mb-4 text-muted"><i class="fa-solid fa-clock-rotate-left me-2"></i>Voucher Đã Hết Hạn / Đã Dùng</h4>
        <div class="row g-3">
            <?php foreach($expired_vouchers as $ev): ?>
            <div class="col-12 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 opacity-50">
                    <div class="card-body p-3 d-flex align-items-center gap-3">
                        <div class="text-center" style="min-width:70px;">
                            <div class="fw-bold text-muted fs-5"><?= $ev['LoaiGiam'] == 'phantram' ? $ev['GiaTriGiam'].'%' : number_format($ev['GiaTriGiam']).'₫' ?></div>
                            <div class="small text-muted">OFF</div>
                        </div>
                        <div class="border-start ps-3">
                            <div class="fw-bold text-muted small"><?= htmlspecialchars($ev['TenVoucher']) ?></div>
                            <div class="small text-danger">
                                <?php if($ev['SoLanDaDung'] >= $ev['GioiHanDung']): ?>
                                    <i class="fa-solid fa-check-circle me-1"></i>Đã sử dụng
                                <?php else: ?>
                                    <i class="fa-solid fa-xmark me-1"></i>Hết hạn: <?= date('d/m/Y', strtotime($ev['NgayHetHan'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Toast notification -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="copyToast" class="toast align-items-center border-0 bg-success text-white" role="alert">
        <div class="d-flex">
            <div class="toast-body"><i class="fa-solid fa-check-circle me-2"></i>Đã sao chép mã voucher!</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<footer class="bg-dark text-white py-5 mt-4">
    <div class="container text-center">
        <h4 class="fw-bold mb-3">K-Hotel Việt Nam</h4>
        <p class="opacity-75 small">Hotline: 1900 1234 | Email: support@khotel.vn</p>
        <p class="opacity-50 x-small m-0">&copy; 2026 K-Hotel Hospitality Group.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function copyCode(el, code) {
    navigator.clipboard.writeText(code).then(() => {
        el.classList.add('copied');
        el.innerHTML = '<i class="fa-solid fa-check me-1"></i>' + code + ' <i class="fa-solid fa-check text-success"></i>';
        const toast = new bootstrap.Toast(document.getElementById('copyToast'));
        toast.show();
        setTimeout(() => {
            el.classList.remove('copied');
            el.innerHTML = code + ' <i class="fa-regular fa-copy text-muted small"></i>';
        }, 3000);
    });
}
</script>
</body>
</html>
