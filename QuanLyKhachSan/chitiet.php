<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once 'config/database.php';
require_once 'mailer.php';

$id = isset($_GET['id']) ? $conn->real_escape_string($_GET['id']) : '';
if(!$id) { header("Location: index.php"); exit; }

$sql = "SELECT p.*, lp.TenLoai, lp.GiaPhong, lp.SoNguoiToiDa, lp.TienNghi, lp.MaLoai, lp.KhuVuc 
        FROM Phong p JOIN LoaiPhong lp ON p.MaLoai = lp.MaLoai 
        WHERE p.MaPhong = '$id'";
$res = $conn->query($sql);
if(!$res || $res->num_rows == 0) { header("Location: index.php"); exit; }
$room = $res->fetch_assoc();

// Lấy lịch đặt phòng hiện tại (để hiển thị cho khách xem)
$schedules = [];
$sch_sql = "SELECT dp.NgayCheckIn, dp.NgayCheckOut, dp.TrangThai, kh.HoTen
            FROM DatPhong dp 
            JOIN KhachHang kh ON dp.MaKH = kh.MaKH
            WHERE dp.MaPhong = '$id' 
            AND dp.TrangThai NOT IN ('Đã huỷ', 'Đã thanh toán', 'Đã thanh toán (Online)')
            AND (dp.NgayCheckOut IS NULL OR dp.NgayCheckOut >= NOW())
            ORDER BY dp.NgayCheckIn ASC";
$sch_res = $conn->query($sch_sql);
if ($sch_res) {
    while ($s = $sch_res->fetch_assoc()) $schedules[] = $s;
}

$success = '';
$error = '';
$conflict_info = '';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id']) && $_SESSION['role'] == 'khach') {
    _logMail("POST request detected in chitiet.php. User ID: " . $_SESSION['user_id']);
    $userid = $_SESSION['user_id'];
    $ngayvao = $conn->real_escape_string($_POST['ngayvao']);
    $ngayra = !empty($_POST['ngayra']) ? $conn->real_escape_string($_POST['ngayra']) : null;
    $ghichu = $conn->real_escape_string($_POST['ghichu'] ?? '');
    $voucher_code = strtoupper(trim($conn->real_escape_string($_POST['voucher_code'] ?? '')));
    $voucher_id   = 0;
    $voucher_giam = 0;
    $voucher_info = '';
    
    // === XỬ LÝ VOUCHER ===
    if ($voucher_code) {
        $today_v = date('Y-m-d');
        $v_res = $conn->query("SELECT * FROM Voucher WHERE Code='$voucher_code' AND TrangThai='active' AND NgayHetHan>='$today_v' AND (MaKH IS NULL OR MaKH = (SELECT MaKH FROM KhachHang WHERE MaTK = $userid)) LIMIT 1");
        if ($v_res && $v_res->num_rows > 0) {
            $vc = $v_res->fetch_assoc();
            if ($vc['SoLanDaDung'] < $vc['GioiHanDung']) {
                $voucher_id = $vc['MaVC'];
                $voucher_info = $vc;
            }
        }
    }
    
    // === VALIDATE THỜI GIAN (với 15 phút grace period) ===
    $now = new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
    $checkin_dt = new DateTime($ngayvao, new DateTimeZone('Asia/Ho_Chi_Minh'));
    $limit = (clone $now)->modify('-15 minutes'); // Cho phép trễ 15 phút so với lúc load trang
    
    if ($checkin_dt < $limit) {
        $error = "❌ Thời gian check-in không hợp lệ! Bạn đã chọn " . $checkin_dt->format('H:i d/m/Y') . " nhưng bây giờ đã là " . $now->format('H:i d/m/Y') . ". Vui lòng chọn thời gian trong tương lai.";
    } elseif ($ngayra) {
        $checkout_dt = new DateTime($ngayra, new DateTimeZone('Asia/Ho_Chi_Minh'));
        if ($checkout_dt <= $checkin_dt) {
            $error = "❌ Thời gian check-out phải sau thời gian check-in!";
        }
    }
    
    // Validate số người (tối thiểu 1, tối đa giới hạn phòng)
    $so_nguoi = (int)($_POST['so_nguoi'] ?? 1);
    $so_nguoi_toi_da = (int)($room['SoNguoiToiDa'] ?? 99);
    if ($so_nguoi < 1) {
        $so_nguoi = 1; // tự động sửa về 1 nếu gửi < 1
    }
    if ($so_nguoi > $so_nguoi_toi_da) {
        $error = "❌ Phòng <strong>{$id}</strong> chỉ tối đa <strong>{$so_nguoi_toi_da} người</strong>! Bạn nhập {$so_nguoi} người. Vui lòng giảm số người hoặc chọn phòng lớn hơn.";
    }

    if (empty($error)) {
        try {
            // Lấy thông tin gửi mail
            _logMail("Bắt đầu truy vấn tìm KhachHang với UserID: $userid");
            $kh_res = $conn->query("SELECT MaKH, HoTen, Email FROM KhachHang WHERE MaTK = $userid");
            if(!$kh_res || $kh_res->num_rows == 0) {
                _logMail("Không tìm thấy KhachHang cho UserID: $userid, tạo mới...");
                $kh_hoten = $conn->real_escape_string($_SESSION['user_name']);
                $temp_cccd = 'TEMP_' . time() . rand(100, 999);
                $conn->query("INSERT INTO KhachHang (MaTK, HoTen, CCCD, SDT) VALUES ($userid, '$kh_hoten', '$temp_cccd', 'Đang cập nhật')");
                $makh = $conn->insert_id;
                $kh_email = ''; 
            } else {
                $kh_data = $kh_res->fetch_assoc();
                $makh = $kh_data['MaKH'];
                $kh_email = $kh_data['Email'];
                $kh_hoten = $kh_data['HoTen'];
            }
            
            error_log("Booking attempt: User $userid, Customer ID $makh, Email: " . ($kh_email ?? 'N/A'));

        // Kiểm tra khách có bị flag buộc thanh toán online không
        $flag_res = $conn->query("SELECT BuocThanhToanTruoc, LyDoFlag FROM KhachHang WHERE MaKH = $makh");
        $flag_data = $flag_res ? $flag_res->fetch_assoc() : null;
        $buoc_online = $flag_data && $flag_data['BuocThanhToanTruoc'];

        $payment = isset($_POST['payment']) ? $conn->real_escape_string($_POST['payment']) : 'Tiền mặt';
        
        // Nếu bị flag mà cố gửi tiền mặt → ép thành online
        if ($buoc_online && $payment == 'Tiền mặt') {
            $error = "❌ Tài khoản của bạn bắt buộc phải thanh toán Online. Vui lòng chọn phương thức thanh toán Online.";
        }
        
        $final_ghichu = $ghichu . " [Phương thức thanh toán: $payment]";
        
        // === KIỂM TRA TRÙNG LỊCH ===
        $ckIn  = $conn->real_escape_string($ngayvao);
        $ckOut = $ngayra ? $conn->real_escape_string($ngayra) : null;
        $ckOut_val = $ckOut ?? date('Y-m-d H:i:s', strtotime('+10 years'));
        
        $conflict_sql = "SELECT dp.MaDP, dp.NgayCheckIn, dp.NgayCheckOut, dp.TrangThai, kh.HoTen
                         FROM DatPhong dp 
                         JOIN KhachHang kh ON dp.MaKH = kh.MaKH
                         WHERE dp.MaPhong = '$id' 
                         AND dp.TrangThai NOT IN ('Đã huỷ') 
                         AND dp.NgayCheckIn < '$ckOut_val'
                         AND (dp.NgayCheckOut IS NULL OR dp.NgayCheckOut > '$ckIn') 
                         LIMIT 1";
        $conflict = $conn->query($conflict_sql);
        
        if ($conflict && $conflict->num_rows > 0) {
            $cf = $conflict->fetch_assoc();
            $ci_str = date('H:i, d/m/Y', strtotime($cf['NgayCheckIn']));
            $co_str = $cf['NgayCheckOut'] ? date('H:i, d/m/Y', strtotime($cf['NgayCheckOut'])) : 'Chưa xác định';
            $error = "⚠️ Phòng này đã có lịch đặt trùng thời gian bạn chọn!";
            $conflict_info = "Phòng <strong>$id</strong> đã được đặt từ <strong>$ci_str</strong> đến <strong>$co_str</strong> (Trạng thái: {$cf['TrangThai']}). Vui lòng chọn thời gian khác hoặc chọn phòng khác.";
        } else {
            // Luôn đặt trạng thái chờ — Lễ tân phải bấm nhận phòng tại Sơ đồ phòng khi khách đến
            if ($payment == 'Thanh toán Online') {
                $trang_thai = 'Đã thanh toán (Online)';
            } else {
                $trang_thai = 'Chờ xác nhận';
            }

            $sql_mvc = $voucher_id > 0 ? $voucher_id : "NULL";
            if ($ngayra) {
                $sql_book = "INSERT INTO DatPhong (MaKH, MaPhong, NgayCheckIn, NgayCheckOut, GhiChu, TrangThai, NguonDat, MaVC, SoNguoi) 
                             VALUES ($makh, '$id', '$ngayvao', '$ngayra', '$final_ghichu', '$trang_thai', 'BanOnline', $sql_mvc, $so_nguoi)";
            } else {
                $sql_book = "INSERT INTO DatPhong (MaKH, MaPhong, NgayCheckIn, GhiChu, TrangThai, NguonDat, MaVC, SoNguoi) 
                             VALUES ($makh, '$id', '$ngayvao', '$final_ghichu', '$trang_thai', 'BanOnline', $sql_mvc, $so_nguoi)";
            }
            if($conn->query($sql_book)) {
                $madp_new = $conn->insert_id;

                // === VÔ HIỆU HÓA VOUCHER NGAY SAU KHI ĐẶT (dù tiền mặt hay online) ===
                if ($voucher_id > 0 && $voucher_info) {
                    $vc_data_book = $voucher_info;
                    $du_dieu_kien = false;
                    $ngay_ci_tmp = new DateTime($ngayvao);
                    $ngay_co_tmp = $ngayra ? new DateTime($ngayra) : (clone $ngay_ci_tmp)->modify('+1 day');
                    $so_dem_tmp  = max(1, (new DateTime($ngay_ci_tmp->format('Y-m-d')))->diff(new DateTime($ngay_co_tmp->format('Y-m-d')))->days);
                    $tien_tam    = $room['GiaPhong'] * $so_dem_tmp;
                    if ($tien_tam >= (float)($vc_data_book['GiaTriToiThieu'] ?? 0)) {
                        $du_dieu_kien = true;
                        $conn->query("UPDATE Voucher SET SoLanDaDung = SoLanDaDung + 1 WHERE MaVC = $voucher_id");
                        if (($vc_data_book['SoLanDaDung'] + 1) >= $vc_data_book['GioiHanDung']) {
                            $conn->query("UPDATE Voucher SET TrangThai = 'inactive' WHERE MaVC = $voucher_id");
                        }
                    }
                }
                // === KẾT THÚC VÔ HIỆU HÓA VOUCHER ===

                // === TÍNH TIỀN VÀ GHI HÓA ĐƠN (áp dụng cho cả hai phương thức) ===
                $ngay_ci    = new DateTime($ngayvao);
                $ngay_co    = $ngayra ? new DateTime($ngayra) : (clone $ngay_ci)->modify('+1 day');
                $so_dem     = max(1, (new DateTime($ngay_ci->format('Y-m-d')))->diff(new DateTime($ngay_co->format('Y-m-d')))->days);
                $gia_phong  = $room['GiaPhong'];
                $tien_phong = $gia_phong * $so_dem;

                // Giảm 10% tự động nếu đặt từ 2 đêm trở lên (tách biệt với voucher)
                $giam_10pct = ($so_dem >= 2) ? round($tien_phong * 0.10) : 0;

                // Giảm từ voucher (tính trên giá gốc, tách biệt)
                $voucher_giam = 0;
                if ($voucher_id > 0 && $voucher_info && ($du_dieu_kien ?? false)) {
                    $vc_data = $voucher_info;
                    if ($vc_data['LoaiGiam'] == 'phantram') {
                        $voucher_giam = round($tien_phong * $vc_data['GiaTriGiam'] / 100);
                    } else {
                        $voucher_giam = (float)$vc_data['GiaTriGiam'];
                    }
                }

                $tong_giam = $giam_10pct + $voucher_giam;
                $tong_tien = max(0, $tien_phong - $tong_giam);

                // Ghi HoaDon (tiền mặt = hóa đơn tạm; online = đã thanh toán)
                $conn->query("INSERT INTO HoaDon (MaDP, TienPhong, TienDichVu, TongTien, GiamGiaThanhVien) VALUES ($madp_new, $tien_phong, 0, $tong_tien, $tong_giam)");

                if ($payment == 'Thanh toán Online') {
                    // === TẶNG VOUCHER THÂN THIẾT sau mỗi 2 lần đặt online thành công ===
                    $total_paid       = (int)$conn->query("SELECT COUNT(*) as cnt FROM DatPhong WHERE MaKH = $makh AND TrangThai IN ('Đã thanh toán','Đã thanh toán (Online)','Đang ở')")->fetch_assoc()['cnt'];
                    $expected_loyal   = floor($total_paid / 2);
                    $given_loyal_count = (int)$conn->query("SELECT COUNT(*) as cnt FROM Voucher WHERE MaKH = $makh AND GhiChu LIKE '%tự động sau khi đủ 2 lần%'")->fetch_assoc()['cnt'];

                    if ($expected_loyal > $given_loyal_count) {
                        $loyal_code   = 'LOYAL-' . strtoupper(substr(md5(uniqid($makh . time(), true)), 0, 8));
                        $loyal_expiry = date('Y-m-d', strtotime('+1 year'));
                        $conn->query("INSERT INTO Voucher (MaKH, Code, TenVoucher, LoaiGiam, GiaTriGiam, GiaTriToiThieu, NgayHetHan, GioiHanDung, TrangThai, GhiChu)
                                      VALUES ($makh, '$loyal_code', 'Khách Hàng Thân Thiết 10%', 'phantram', 10, 0, '$loyal_expiry', 1, 'active', 'Tặng tự động sau khi đủ 2 lần đặt phòng thành công')");
                        $success_voucher = " 🎁 Bạn vừa nhận được Voucher <strong>Khách Hàng Thân Thiết 10%</strong> (mã: <code>$loyal_code</code>, hiệu lực 1 năm)!";
                    }
                    // === KẾT THÚC TẶNG VOUCHER THÂN THIẾT ===

                    $success = "✅ Thanh toán thành công! Phòng đã được xác nhận.";
                    if ($tong_giam > 0) {
                        $success .= " Bạn tiết kiệm <strong>" . number_format($tong_giam) . " ₫</strong>";
                        if ($giam_10pct > 0 && $voucher_giam > 0) {
                            $success .= " (giảm 10% dài ngày + voucher)";
                        } elseif ($giam_10pct > 0) {
                            $success .= " (giảm 10% khi ở 2 đêm trở lên)";
                        } elseif ($voucher_giam > 0) {
                            $success .= " (voucher ưu đãi)";
                        }
                        $success .= "!";
                    }
                    if (!empty($success_voucher)) $success .= $success_voucher;
                } else {
                    // Thanh toán tiền mặt
                    $success = "✅ Yêu cầu đặt phòng đã gửi thành công! Vui lòng chờ lễ tân xác nhận.";
                    if ($giam_10pct > 0) {
                        $success .= " 🏷️ Bạn được giảm <strong>" . number_format($giam_10pct) . " ₫</strong> (10% cho " . $so_dem . " đêm).";
                    }
                    if ($voucher_id > 0 && ($du_dieu_kien ?? false)) {
                        $success .= " Voucher của bạn đã được áp dụng và khóa lại.";
                    }
                }

                // === GỬI MAIL THÔNG BÁO ===
                if (!empty($kh_email)) {
                    _logMail("Bắt đầu quy trình gửi mail từ chitiet.php cho $kh_email (MaKH: $makh)");

                    $ten_vc_mail = ($voucher_id > 0 && ($du_dieu_kien ?? false) && $voucher_info) ? $voucher_info['TenVoucher'] : '';

                    $booking_data = [
                        'ma_dat'       => $madp_new,
                        'ten_phong'    => $room['TenLoai'] . " (" . $room['MaPhong'] . ")",
                        'ngay_checkin' => $ngayvao,
                        'ngay_checkout'=> $ngayra ?? $ngay_co->format('Y-m-d H:i:s'),
                        'tong_tien'    => $tong_tien,
                        'gia_goc'      => $tien_phong,
                        'giam_gia'     => $tong_giam,
                        'ten_voucher'  => $ten_vc_mail,
                        'so_ngay'      => $so_dem,
                        'phuong_thuc'  => ($payment == 'Thanh toán Online' ? 'Thanh toán Trực tuyến (Đã trả)' : 'Tiền mặt (Tại quầy)')
                    ];

                    // Gửi mail hóa đơn nếu thanh toán online
                    if ($payment == 'Thanh toán Online') {
                        $invoice_data = [
                            'tien_phong'  => $tien_phong,
                            'tien_dichvu' => 0,
                            'giam_gia'    => $tong_giam,
                            'tong_tien'   => $tong_tien,
                            'so_ngay'     => $so_dem
                        ];
                        sendInvoiceEmail($kh_email, $kh_hoten, $invoice_data);
                    }
                } else {
                    _logMail("KHÔNG gửi mail từ chitiet.php vì Email rỗng. MaKH: $makh, HoTen: $kh_hoten");
                }
            } else {
                $error = "❌ Có lỗi xảy ra: " . $conn->error;
            }
        }
        } catch (Exception $e) {
            $error = "❌ Lỗi hệ thống khi xử lý: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Kiểm tra flag thanh toán của khách đang login để hiển thị cảnh báo
$khach_buoc_online = false;
$khach_lydo_flag = '';
if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'khach') {
    $uid = (int)$_SESSION['user_id'];
    $fl = $conn->query("SELECT BuocThanhToanTruoc, LyDoFlag FROM KhachHang WHERE MaTK = $uid LIMIT 1");
    if ($fl && $fl->num_rows > 0) {
        $fd = $fl->fetch_assoc();
        $khach_buoc_online = (bool)$fd['BuocThanhToanTruoc'];
        $khach_lydo_flag   = $fd['LyDoFlag'] ?? '';
    }
}

// Lấy khu vực
$khu_vuc = $room['KhuVuc'] ?? 'Hồ Chí Minh';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Phòng <?= $room['MaPhong'] ?> - K-Hotel <?= $khu_vuc ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg bg-white border-bottom hilton-nav sticky-top shadow-sm">
  <div class="container px-4">
    <a class="navbar-brand fw-bold fs-3 text-dark text-center" style="font-family: serif; border: 2px solid #000; padding: 0 5px; line-height: 1.2;" href="index.php">K-Hotel<br><span style="font-size: 0.6rem; letter-spacing: 1px; display: block; border-top: 1px solid #000; font-family: sans-serif; font-weight: bold; margin-top: 2px;">FOR THE STAY</span></a>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-outline-dark btn-sm rounded-pill px-3"><i class="fa-solid fa-house me-1"></i>Trang chủ</a>
        <a href="timkiem.php" class="btn btn-outline-dark btn-sm rounded-pill px-3"><i class="fa-solid fa-magnifying-glass me-1"></i>Tìm phòng</a>
        <?php if(isset($_SESSION['user_id'])): ?>
        <a href="lichsu.php" class="btn btn-outline-primary btn-sm rounded-pill px-3"><i class="fa-solid fa-clock-rotate-left me-1"></i>Lịch sử</a>
        <?php endif; ?>
    </div>
  </div>
</nav>

<div class="container my-5">
    <?php if($success): ?>
        <div class="alert alert-success fs-5 shadow-sm border-0 rounded-4"><i class="fa-solid fa-check-circle me-2"></i><?= $success ?>
        <a href="lichsu.php" class="btn btn-success btn-sm ms-3 rounded-pill">Xem lịch sử đặt</a></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger shadow-sm border-0 rounded-4"><i class="fa-solid fa-circle-xmark me-2"></i><?= $error ?>
        <?php if($conflict_info): ?>
            <div class="mt-2 p-3 bg-white rounded-3 border border-danger border-opacity-25">
                <i class="fa-solid fa-calendar-xmark text-danger me-2"></i><?= $conflict_info ?>
            </div>
        <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="row bg-white shadow-sm p-4 rounded-4">
        <div class="col-md-7 mb-4 mb-md-0">
            <div id="roomGallery" class="carousel slide border-0 rounded-4 overflow-hidden shadow-sm h-100" data-bs-ride="carousel">
                <div class="carousel-indicators">
                    <button type="button" data-bs-target="#roomGallery" data-bs-slide-to="0" class="active"></button>
                    <button type="button" data-bs-target="#roomGallery" data-bs-slide-to="1"></button>
                    <button type="button" data-bs-target="#roomGallery" data-bs-slide-to="2"></button>
                </div>
                <div class="carousel-inner h-100" style="min-height: 450px;">
                    <div class="carousel-item active h-100">
                        <img src="https://images.unsplash.com/photo-1611892440504-42a792e24d32?q=80&w=2070&auto=format&fit=crop" class="d-block w-100 h-100" style="object-fit: cover;" alt="Phòng">
                    </div>
                    <div class="carousel-item h-100">
                        <img src="https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?q=80&w=2070&auto=format&fit=crop" class="d-block w-100 h-100" style="object-fit: cover;" alt="Nội thất">
                    </div>
                    <div class="carousel-item h-100">
                        <img src="https://images.unsplash.com/photo-1564013799919-ab600027ffc6?q=80&w=2070&auto=format&fit=crop" class="d-block w-100 h-100" style="object-fit: cover;" alt="Phòng tắm">
                    </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#roomGallery" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon rounded-circle bg-dark bg-opacity-75 p-3"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#roomGallery" data-bs-slide="next">
                    <span class="carousel-control-next-icon rounded-circle bg-dark bg-opacity-75 p-3"></span>
                </button>
            </div>
        </div>
        <div class="col-md-5 d-flex flex-column justify-content-center">
            <span class="badge bg-primary bg-opacity-10 text-primary mb-2 align-self-start px-3 py-2 rounded-pill"><i class="fa-solid fa-location-dot me-1"></i><?= htmlspecialchars($khu_vuc) ?></span>
            <h1 class="fw-bold mb-3"><?= htmlspecialchars($room['TenLoai']) ?></h1>
            <h4 class="text-danger fw-bold mb-4"><?= number_format($room['GiaPhong']) ?> VNĐ <small class="text-muted fs-6 fw-normal">/ đêm</small></h4>
            
            <div class="mb-3">
                <p class="mb-2"><i class="fa-solid fa-bed text-primary" style="width:20px"></i> Bố trí: <strong><?= $room['SoNguoiToiDa'] ?> người</strong></p>
                <p class="mb-2"><i class="fa-solid fa-star text-warning" style="width:20px"></i> Tiện nghi: <strong><?= htmlspecialchars($room['TienNghi']) ?></strong></p>
                <p class="mb-2"><i class="fa-solid fa-door-open text-primary" style="width:20px"></i> Mã phòng: <strong><?= $room['MaPhong'] ?></strong></p>
            </div>

            <!-- Hiển thị lịch đặt phòng hiện tại -->
            <?php if (count($schedules) > 0): ?>
            <div class="alert alert-warning border-0 mb-3 rounded-3 p-3">
                <h6 class="fw-bold mb-2"><i class="fa-solid fa-calendar-days me-1"></i>Lịch đặt phòng hiện tại:</h6>
                <?php foreach($schedules as $sch): ?>
                <div class="d-flex align-items-center gap-2 mb-1 small">
                    <i class="fa-solid fa-circle text-danger" style="font-size:6px"></i>
                    <span><strong><?= date('H:i d/m/Y', strtotime($sch['NgayCheckIn'])) ?></strong> → <strong><?= $sch['NgayCheckOut'] ? date('H:i d/m/Y', strtotime($sch['NgayCheckOut'])) : '—' ?></strong></span>
                    <span class="badge bg-secondary"><?= $sch['TrangThai'] ?></span>
                </div>
                <?php endforeach; ?>
                <small class="text-muted d-block mt-1">Vui lòng chọn thời gian không trùng với lịch trên.</small>
            </div>
            <?php endif; ?>
            
            <hr>
            
            <?php if(!isset($_SESSION['user_id'])): ?>
                <div class="alert alert-warning d-flex align-items-center gap-3">
                    <i class="fa-solid fa-lock fs-4 text-warning"></i>
                    <div>Vui lòng <a href="auth/login.php" class="fw-bold">Đăng nhập</a> hoặc <a href="auth/register.php" class="fw-bold">Đăng ký</a> để đặt phòng!</div>
                </div>
            <?php elseif(isset($_SESSION['role']) && $_SESSION['role'] != 'khach'): ?>
                <div class="alert alert-info d-flex align-items-center gap-3">
                    <i class="fa-solid fa-user-shield fs-4"></i>
                    <div>
                        <strong>Tài khoản Quản trị</strong><br>
                        <a href="datphong.php?room=<?= $room['MaPhong'] ?>" class="btn btn-primary mt-2 rounded-pill fw-bold">
                            <i class="fa-solid fa-calendar-check me-1"></i> Lập phiếu đặt phòng (Admin)
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php if($khach_buoc_online): ?>
                <div class="alert alert-danger border-0 rounded-3 mb-3 d-flex gap-3 align-items-start">
                    <i class="fa-solid fa-lock fs-4 mt-1 text-danger"></i>
                    <div>
                        <strong>Tài khoản bị hạn chế thanh toán tiền mặt</strong><br>
                        <small class="text-muted">Lý do: <?= htmlspecialchars($khach_lydo_flag) ?></small><br>
                        <small>Bạn chỉ có thể đặt phòng với phương thức <strong>Thanh toán Online</strong>.</small>
                    </div>
                </div>
                <?php endif; ?>
                <h5 class="fw-bold mb-3">Xác Nhận Đặt Phòng Online</h5>
                <!-- Đồng hồ thời gian thật HCM -->
                <div class="alert alert-light border mb-3 d-flex align-items-center gap-2 py-2">
                    <i class="fa-solid fa-clock text-primary fs-5"></i>
                    <div>
                        <small class="text-muted">Giờ hiện tại (TP. Hồ Chí Minh)</small>
                        <div class="fw-bold text-dark" id="live-clock"><?= date('H:i:s — d/m/Y') ?></div>
                    </div>
                </div>
                <form method="POST" id="bookingForm" onsubmit="return validateBooking()">
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label text-muted">Check-in Dự Kiến</label>
                            <input type="datetime-local" class="form-control bg-light" name="ngayvao" required value="<?= date('Y-m-d\TH:i', strtotime('+1 day')) ?>" id="ci">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label text-muted">Check-out Dự Kiến</label>
                            <input type="datetime-local" class="form-control bg-light" name="ngayra" required value="<?= date('Y-m-d\TH:i', strtotime('+2 days')) ?>" id="co">
                        </div>
                    </div>
                    <div id="time-error" class="alert alert-danger d-none mb-3 py-2 small"></div>
                    <div class="mb-3">
                        <label class="form-label text-muted">Số người <span class="text-danger">*</span> <small class="text-muted">(1 – <?= $room['SoNguoiToiDa'] ?> người)</small></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="fa-solid fa-users text-primary"></i></span>
                            <input type="number" name="so_nguoi" id="so_nguoi"
                                   class="form-control bg-light border-0"
                                   min="1" max="<?= $room['SoNguoiToiDa'] ?>" value="1" required
                                   oninput="if(this.value<1)this.value=1; if(this.value>this.max)this.value=this.max;">
                            <span class="input-group-text bg-light border-0 text-muted">/ <?= $room['SoNguoiToiDa'] ?> tối đa</span>
                        </div>
                        <div id="nguoi_error" class="text-danger small mt-1 d-none">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>Số người phải từ 1 đến <?= $room['SoNguoiToiDa'] ?> người!
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted small">Tiền phòng:</span>
                                <span id="est_base" class="fw-bold"><?= number_format($room['GiaPhong']) ?> đ</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-1" id="row_discount_2ngay" style="display:none!important">
                                <span class="text-success small"><i class="fa-solid fa-tag me-1"></i>Giảm 10% (ở ≥2 đêm):</span>
                                <span id="est_discount2" class="text-success fw-bold">-0 đ</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-1" id="row_discount_vc" style="display:none!important">
                                <span class="text-warning small"><i class="fa-solid fa-ticket me-1"></i>Voucher:</span>
                                <span id="est_discountvc" class="text-warning fw-bold">-0 đ</span>
                            </div>
                            <hr class="my-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-success">Tổng ước tính:</span>
                                <span id="est_price" class="fw-bold text-danger fs-5"><?= number_format($room['GiaPhong']) ?> đ</span>
                            </div>
                        </div>
                    </div>

                    <!-- VOUCHER -->
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted"><i class="fa-solid fa-ticket text-warning me-1"></i> Mã Voucher Ưu Đãi</label>
                        <div class="input-group">
                            <input type="text" name="voucher_code" id="voucher_code" class="form-control bg-light border-0"
                                   placeholder="Nhập mã voucher (nếu có)" style="text-transform:uppercase; letter-spacing:1px;" maxlength="50">
                            <button type="button" class="btn btn-outline-warning fw-bold" onclick="applyVoucher()">
                                <i class="fa-solid fa-tag"></i> Áp dụng
                            </button>
                        </div>
                        <div id="voucher_result" class="mt-2"></div>
                    </div>
                    <!-- END VOUCHER -->
                    <div class="mb-4">
                        <label class="form-label text-muted fw-bold"><i class="fa-solid fa-wallet text-primary"></i> Hình thức thanh toán</label>
                        <div class="d-flex flex-column gap-3 mt-1">
                            <label class="border p-3 rounded-4 d-flex align-items-center gap-3 mb-0 shadow-sm" style="cursor: pointer; background: #fff;" onclick="toggleCC(true)">
                                <input type="radio" name="payment" value="Thanh toán Online" class="form-check-input" checked>
                                <div class="d-flex gap-1 ms-1">
                                    <i class="fa-brands fa-cc-visa fs-1 text-primary"></i>
                                    <i class="fa-brands fa-cc-mastercard fs-1 text-danger"></i>
                                </div>
                                <div class="ms-2">
                                    <strong class="d-block text-dark" style="font-size: 0.95rem;">Thanh toán An toàn (Thẻ / VNPay)</strong>
                                    <small class="text-success fw-bold"><i class="fa-solid fa-bolt"></i> Xác nhận phòng lập tức</small>
                                </div>
                            </label>
                            
                            <div id="cc-form" class="bg-light border rounded-3 p-3 ms-4 me-2" style="transition: opacity 0.3s;">
                                <div class="mb-3">
                                    <label class="form-label small text-muted mb-1">Số thẻ tín dụng / Ghi nợ (*)</label>
                                    <input type="text" class="form-control" placeholder="0000 0000 0000 0000">
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <label class="form-label small text-muted mb-1">Ngày hết hạn</label>
                                        <input type="text" class="form-control" placeholder="MM/YY">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small text-muted mb-1">Mã CVC</label>
                                        <input type="password" class="form-control" placeholder="***">
                                    </div>
                                </div>
                            </div>

                            <?php if(!$khach_buoc_online): ?>
                            <label class="border p-3 rounded-4 d-flex align-items-center gap-3 mb-0" style="cursor: pointer; background: #fafafa;" onclick="toggleCC(false)">
                                <input type="radio" name="payment" value="Tiền mặt" class="form-check-input">
                                <i class="fa-solid fa-money-bill-wave fs-1 text-success ms-2"></i>
                                <div class="ms-2">
                                    <strong class="d-block text-dark" style="font-size: 0.95rem;">Thanh toán Tiền mặt khi check-in</strong>
                                    <small class="text-muted">Lưu ý: Không đảm bảo giữ phòng >24h</small>
                                </div>
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted">Ghi chú thêm</label>
                        <textarea class="form-control bg-light" name="ghichu" rows="2" placeholder="Ví dụ: Xin thêm gối mềm..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-3 rounded-pill shadow-lg mt-2" style="font-size: 1.1rem;"><i class="fa-solid fa-check-double me-2"></i> Xác Nhận Đặt và Thanh Toán</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section Đánh Giá -->
    <div class="row bg-white shadow-sm p-4 rounded-4 mt-4">
        <div class="col-12">
            <h3 class="fw-bold mb-4"><i class="fa-solid fa-comments text-primary"></i> Đánh Giá Từ Khách Hàng</h3>

            <?php
            // Kiểm tra booking đã thanh toán chưa đánh giá của khách hiện tại
            $co_the_danh_gia = [];
            if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'khach') {
                $uid_dg = (int)$_SESSION['user_id'];
                $kh_dg  = $conn->query("SELECT MaKH FROM KhachHang WHERE MaTK = $uid_dg LIMIT 1");
                if ($kh_dg && $kh_dg->num_rows > 0) {
                    $makh_dg   = (int)$kh_dg->fetch_assoc()['MaKH'];
                    $maloai_dg = (int)$room['MaLoai'];
                    $eligible  = $conn->query("
                        SELECT dp.MaDP, dp.NgayCheckIn, dp.MaPhong
                        FROM DatPhong dp
                        JOIN Phong p ON dp.MaPhong = p.MaPhong
                        LEFT JOIN DanhGia dg ON dg.MaDP = dp.MaDP
                        WHERE dp.MaKH = $makh_dg
                          AND p.MaLoai = $maloai_dg
                          AND dp.TrangThai IN ('Đã thanh toán','Đã thanh toán (Online)')
                          AND dg.MaDG IS NULL
                        ORDER BY dp.MaDP DESC
                    ");
                    if ($eligible) while ($e = $eligible->fetch_assoc()) $co_the_danh_gia[] = $e;
                }
            }

            // Xử lý POST gửi đánh giá
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['danh_gia_madp'])) {
                $dg_madp  = (int)$_POST['danh_gia_madp'];
                $dg_sao   = max(1, min(5, (int)($_POST['so_sao'] ?? 5)));
                $dg_nxet  = $conn->real_escape_string(trim($_POST['nhan_xet'] ?? ''));
                $maloai_p = (int)$room['MaLoai'];
                $uid_post = (int)$_SESSION['user_id'];
                $verify   = $conn->query("
                    SELECT dp.MaDP FROM DatPhong dp
                    JOIN KhachHang kh ON dp.MaKH = kh.MaKH
                    LEFT JOIN DanhGia dg ON dg.MaDP = dp.MaDP
                    WHERE dp.MaDP = $dg_madp AND kh.MaTK = $uid_post
                      AND dp.TrangThai IN ('Đã thanh toán','Đã thanh toán (Online)')
                      AND dg.MaDG IS NULL LIMIT 1
                ");
                if ($verify && $verify->num_rows > 0) {
                    $makh_post = (int)$conn->query("SELECT MaKH FROM KhachHang WHERE MaTK = $uid_post LIMIT 1")->fetch_assoc()['MaKH'];
                    $conn->query("INSERT INTO DanhGia (MaKH, MaLoai, MaDP, SoSao, NhanXet) VALUES ($makh_post, $maloai_p, $dg_madp, $dg_sao, '$dg_nxet')");
                    $co_the_danh_gia = array_filter($co_the_danh_gia, fn($x) => $x['MaDP'] != $dg_madp);
                    echo '<div class="alert alert-success border-0 rounded-3 mb-3"><i class="fa-solid fa-check-circle me-2"></i>Cảm ơn bạn đã đánh giá!</div>';
                }
            }
            ?>

            <?php if (!empty($co_the_danh_gia)): ?>
            <div class="card border-warning border-2 rounded-4 p-4 mb-4 bg-warning bg-opacity-10">
                <h6 class="fw-bold mb-3"><i class="fa-solid fa-star text-warning me-2"></i>Bạn có thể đánh giá các lần đặt phòng sau:</h6>
                <?php foreach ($co_the_danh_gia as $bdg): ?>
                <form method="POST" class="mb-3 p-3 bg-white rounded-3 border">
                    <input type="hidden" name="danh_gia_madp" value="<?= $bdg['MaDP'] ?>">
                    <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
                        <span class="badge bg-primary">Đơn #<?= str_pad($bdg['MaDP'], 4, '0', STR_PAD_LEFT) ?></span>
                        <span class="text-muted small"><i class="fa-solid fa-calendar me-1"></i><?= date('d/m/Y', strtotime($bdg['NgayCheckIn'])) ?></span>
                        <span class="text-muted small">Phòng <?= $bdg['MaPhong'] ?></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium small">Số sao</label>
                        <div class="d-flex gap-1" id="stars_<?= $bdg['MaDP'] ?>">
                            <?php for ($ss = 1; $ss <= 5; $ss++): ?>
                            <span class="star-btn" data-val="<?= $ss ?>" data-group="<?= $bdg['MaDP'] ?>"
                                  style="font-size:32px;cursor:pointer;color:<?= $ss<=5?'#f59e0b':'#d1d5db' ?>;"
                                  onclick="selectStar(<?= $bdg['MaDP'] ?>,<?= $ss ?>)">★</span>
                            <?php endfor; ?>
                            <input type="hidden" name="so_sao" id="sao_val_<?= $bdg['MaDP'] ?>" value="5">
                        </div>
                    </div>
                    <div class="mb-3">
                        <textarea name="nhan_xet" class="form-control bg-light border-0" rows="2" placeholder="Chia sẻ trải nghiệm..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning fw-bold rounded-pill px-4">
                        <i class="fa-solid fa-paper-plane me-2"></i>Gửi đánh giá
                    </button>
                </form>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php
            $maloai = $room['MaLoai'];
            $dg_sql = "SELECT dg.*, kh.HoTen FROM DanhGia dg JOIN KhachHang kh ON dg.MaKH = kh.MaKH WHERE dg.MaLoai = $maloai ORDER BY dg.NgayDanhGia DESC LIMIT 10";
            $dg_res = $conn->query($dg_sql);
            
            if($dg_res && $dg_res->num_rows > 0): 
                $avg_res = $conn->query("SELECT AVG(SoSao) as tb FROM DanhGia WHERE MaLoai = $maloai");
                $avg = round($avg_res->fetch_assoc()['tb'], 1);
            ?>
            <div class="d-flex align-items-center mb-4">
                <h1 class="text-warning fw-bold m-0 me-3"><?= $avg ?><i class="fa-solid fa-star fs-3"></i></h1>
                <p class="m-0 text-muted">Dựa trên <?= $dg_res->num_rows ?> đánh giá loại phòng này.</p>
            </div>
            
            <div class="list-group list-group-flush">
                <?php while($dg = $dg_res->fetch_assoc()): ?>
                <div class="list-group-item px-0 py-3">
                    <div class="d-flex justify-content-between">
                        <strong class="d-block text-primary"><i class="fa-solid fa-user-circle"></i> <?= htmlspecialchars($dg['HoTen']) ?></strong>
                        <small class="text-muted"><?= date('d/m/Y', strtotime($dg['NgayDanhGia'])) ?></small>
                    </div>
                    <div class="text-warning small my-1">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <i class="fa-<?= $i <= $dg['SoSao'] ? 'solid' : 'regular' ?> fa-star"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="m-0 text-dark"><?= nl2br(htmlspecialchars($dg['NhanXet'])) ?></p>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="fa-solid fa-comment-slash fs-1 opacity-50 mb-3"></i>
                    <h5>Chưa có đánh giá nào cho loại phòng này!</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="bg-dark text-white py-4 mt-5">
    <div class="container text-center">
        <p class="opacity-50 small m-0">&copy; 2026 K-Hotel Việt Nam</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Đồng hồ thời gian thực HCM
    function updateClock() {
        const now = new Date();
        const options = { timeZone: 'Asia/Ho_Chi_Minh', hour: '2-digit', minute: '2-digit', second: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' };
        const formatter = new Intl.DateTimeFormat('vi-VN', options);
        const parts = formatter.formatToParts(now);
        let h='', m='', s='', d='', mo='', y='';
        parts.forEach(p => {
            if(p.type=='hour') h=p.value;
            if(p.type=='minute') m=p.value;
            if(p.type=='second') s=p.value;
            if(p.type=='day') d=p.value;
            if(p.type=='month') mo=p.value;
            if(p.type=='year') y=p.value;
        });
        const el = document.getElementById('live-clock');
        if(el) el.innerText = `${h}:${m}:${s} — ${d}/${mo}/${y}`;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // ===== Star Rating =====
    function selectStar(group, val) {
        document.getElementById('sao_val_' + group).value = val;
        document.querySelectorAll('#stars_' + group + ' .star-btn').forEach((s, i) => {
            s.style.color = i < val ? '#f59e0b' : '#d1d5db';
        });
    }

    // ===== Validate số người realtime =====
    const soNguoiInput = document.getElementById('so_nguoi');
    const nguoiError   = document.getElementById('nguoi_error');
    const maxNguoi     = <?= (int)($room['SoNguoiToiDa'] ?? 99) ?>;
    if (soNguoiInput) {
        soNguoiInput.addEventListener('input', function() {
            const val = parseInt(this.value) || 1;
            if (val > maxNguoi) {
                nguoiError.classList.remove('d-none');
                this.classList.add('is-invalid');
            } else {
                nguoiError.classList.add('d-none');
                this.classList.remove('is-invalid');
            }
        });
    }

    // Tính giá
    const ci = document.getElementById('ci');
    const co = document.getElementById('co');
    const est = document.getElementById('est_price');
    const pricePerDay = <?= $room['GiaPhong'] ?>;

    // Validate booking trước khi submit
    function validateBooking() {
        const errDiv = document.getElementById('time-error');
        if(!ci || !co) return true;
        
        const now = new Date();
        const checkinDate = new Date(ci.value);
        const checkoutDate = new Date(co.value);
        
        if (checkinDate < now) {
            errDiv.classList.remove('d-none');
            errDiv.innerHTML = '<i class="fa-solid fa-exclamation-triangle me-1"></i> Thời gian check-in đã qua! Bây giờ là ' + now.toLocaleString('vi-VN', {timeZone:'Asia/Ho_Chi_Minh'}) + '. Vui lòng chọn thời gian trong tương lai.';
            return false;
        }
        if (checkoutDate <= checkinDate) {
            errDiv.classList.remove('d-none');
            errDiv.innerHTML = '<i class="fa-solid fa-exclamation-triangle me-1"></i> Thời gian check-out phải sau check-in!';
            return false;
        }
        errDiv.classList.add('d-none');
        return true;
    }
    
    function toggleCC(show) {
        const form = document.getElementById('cc-form');
        if(!form) return;
        if (show) {
            form.style.display = 'block';
            form.style.opacity = '0';
            setTimeout(() => form.style.opacity = '1', 50);
        } else {
            form.style.display = 'none';
        }
    }

    // Voucher apply via AJAX
    let appliedVoucher = null;

    function applyVoucher() {
        const code = document.getElementById('voucher_code').value.trim().toUpperCase();
        const resultDiv = document.getElementById('voucher_result');
        if (!code) {
            resultDiv.innerHTML = '<div class="alert alert-warning py-2 small mb-0"><i class="fa-solid fa-exclamation-circle me-1"></i>Vui lòng nhập mã voucher!</div>';
            return;
        }
        resultDiv.innerHTML = '<div class="text-muted small"><i class="fa-solid fa-spinner fa-spin me-1"></i>Đang kiểm tra...</div>';
        fetch('check_voucher.php?code=' + encodeURIComponent(code))
            .then(r => r.json())
            .then(data => {
                if (data.valid) {
                    appliedVoucher = data;
                    const daysLeft = data.days_left > 0 ? `<span class="badge bg-warning text-dark ms-2">Còn ${data.days_left} ngày</span>` : '';
                    resultDiv.innerHTML = `<div class="alert alert-success py-2 small mb-0 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-check-circle text-success"></i>
                        <div><strong>${data.message}</strong>${daysLeft}</div>
                    </div>`;
                    updateEstimate();
                } else {
                    appliedVoucher = null;
                    resultDiv.innerHTML = `<div class="alert alert-danger py-2 small mb-0"><i class="fa-solid fa-xmark-circle me-1"></i>${data.message}</div>`;
                    updateEstimate();
                }
            })
            .catch(() => {
                resultDiv.innerHTML = '<div class="alert alert-warning py-2 small mb-0">Không thể kết nối để kiểm tra voucher.</div>';
            });
    }

    document.getElementById('voucher_code')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); applyVoucher(); }
        this.value = this.value.toUpperCase();
    });

    // Tính giá tách biệt: 10% tự động (≥2 đêm) + voucher riêng
    const fmt = v => new Intl.NumberFormat('vi-VN').format(v);

    function calcP() {
        if(!ci || !co || !ci.value || !co.value) return;
        const d1 = new Date(ci.value);
        const d2 = new Date(co.value);
        let diff = Math.ceil((d2 - d1) / (1000 * 60 * 60 * 24));
        if(diff <= 0) diff = 1;

        const base = diff * pricePerDay;

        // Giảm 10% tự động nếu ở từ 2 đêm trở lên
        const discount10 = (diff >= 2) ? Math.round(base * 0.10) : 0;

        // Giảm từ voucher (tính trên giá gốc, tách biệt với 10%)
        let discountVc = 0;
        if (appliedVoucher && base >= (appliedVoucher.minimum || 0)) {
            if (appliedVoucher.type === 'phantram') {
                discountVc = Math.round(base * appliedVoucher.value / 100);
            } else {
                discountVc = appliedVoucher.value;
            }
        }

        const totalDiscount = discount10 + discountVc;
        const final = Math.max(0, base - totalDiscount);

        // Cập nhật dòng tiền phòng
        const estBase = document.getElementById('est_base');
        if(estBase) estBase.textContent = fmt(base) + ' đ';

        // Dòng giảm 10%
        const row2 = document.getElementById('row_discount_2ngay');
        const val2 = document.getElementById('est_discount2');
        if(row2 && val2) {
            if(discount10 > 0) {
                row2.style.removeProperty('display');
                val2.textContent = '-' + fmt(discount10) + ' đ';
            } else {
                row2.style.display = 'none';
            }
        }

        // Dòng voucher
        const rowVc = document.getElementById('row_discount_vc');
        const valVc = document.getElementById('est_discountvc');
        if(rowVc && valVc) {
            if(discountVc > 0) {
                rowVc.style.removeProperty('display');
                valVc.textContent = '-' + fmt(discountVc) + ' đ';
            } else {
                rowVc.style.display = 'none';
            }
        }

        // Tổng
        if(est) est.textContent = fmt(final) + ' đ';
    }

    function updateEstimate() { calcP(); }
    if(ci) ci.addEventListener('change', calcP);
    if(co) co.addEventListener('change', calcP);
    // Gọi ngay khi tải trang để hiển thị đúng
    calcP();
</script>
</body>
</html>
