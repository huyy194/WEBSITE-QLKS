<?php
session_start();
require_once 'config/database.php';
require_once 'mailer.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');


$room_id = isset($_GET['room']) ? $conn->real_escape_string($_GET['room']) : '';

// Xử lý Thanh Toán
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'pay') {
    $madp = (int)$_POST['madp'];
    $maphong = $conn->real_escape_string($_POST['maphong']);
    $userid = $_SESSION['user_id'] ?? 1;

    // Lấy thông tin phòng và số ngày ở  
    $dp_query = $conn->query("SELECT dp.*, p.MaLoai, kh.MaKH FROM DatPhong dp JOIN Phong p ON dp.MaPhong = p.MaPhong JOIN KhachHang kh ON dp.MaKH = kh.MaKH WHERE dp.MaDP = $madp");
    
    if (!$dp_query || $dp_query->num_rows == 0) {
        echo '<div class="alert alert-danger m-4">Không tìm thấy đơn đặt phòng!</div>';
    } else {
        $dp = $dp_query->fetch_assoc();

        // Sử dụng ngày check-out đã đặt hoặc thời điểm hiện tại (chọn cái nào lớn hơn)
        $ngayvao = new DateTime($dp['NgayCheckIn']);
        $booked_checkout = new DateTime($dp['NgayCheckOut']);
        $now = new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
        $ngayra = ($now > $booked_checkout) ? $now : $booked_checkout;
        $songay = max(1, $ngayra->diff($ngayvao)->days);

        $gia_res = $conn->query("SELECT GiaPhong FROM LoaiPhong WHERE MaLoai = {$dp['MaLoai']}");
        $gia = $gia_res ? $gia_res->fetch_assoc()['GiaPhong'] : 0;
        $tienphong = $songay * $gia;

        $tiendv = 0;
        $dv_res = $conn->query("SELECT SUM(ThanhTien) as Total FROM SuDungDichVu WHERE MaDP = $madp");
        if ($dv_res && $dv_res->num_rows > 0) {
            $tiendv = (int)($dv_res->fetch_assoc()['Total'] ?? 0);
        }

        $tongtien_tam = $tienphong + $tiendv;

        // Định nghĩa $makh và tính $vip_cnt để dùng cho Voucher và tặng quà
        $makh = (int)$dp['MaKH'];
        $vip_check = $conn->query("SELECT COUNT(*) as cnt FROM DatPhong WHERE MaKH = $makh AND TrangThai IN ('Đã thanh toán','Đã thanh toán (Online)') AND MaDP != $madp");
        $vip_cnt = $vip_check ? (int)$vip_check->fetch_assoc()['cnt'] : 0;

        // Giảm giá 10%: chỉ áp dụng khi đặt từ 2 ngày trở lên
        $vip_giam = ($songay >= 2) ? round($tongtien_tam * 0.10) : 0;
        
        // 2. Check Voucher
        $voucher_giam = 0;
        $mavc = $dp['MaVC'];
        
        // Ưu tiên voucher nhập tay tại quầy
        $voucher_code_input = strtoupper(trim($_POST['voucher_code'] ?? ''));
        if ($voucher_code_input) {
            $today_v = date('Y-m-d');
            $safe_vc = $conn->real_escape_string($voucher_code_input);
            $vc_res = $conn->query("SELECT * FROM Voucher WHERE Code='$safe_vc' AND TrangThai='active' AND NgayHetHan>='$today_v' AND SoLanDaDung < GioiHanDung AND (MaKH IS NULL OR MaKH = $makh) LIMIT 1");
            if ($vc_res && $vc_res->num_rows > 0) {
                $vc_data = $vc_res->fetch_assoc();
                if ($tongtien_tam >= (float)($vc_data['GiaTriToiThieu'] ?? 0)) {
                    if ($vc_data['LoaiGiam'] == 'phantram') {
                        $voucher_giam = round($tongtien_tam * $vc_data['GiaTriGiam'] / 100);
                    } else {
                        $voucher_giam = (float)$vc_data['GiaTriGiam'];
                    }
                    $mavc = $vc_data['MaVC']; // Cập nhật MaVC để track trong DB
                }
            }
        } elseif ($mavc) {
            // Nếu không nhập tay, dùng voucher đã gán từ lúc đặt phòng
            $v_res = $conn->query("SELECT * FROM Voucher WHERE MaVC = $mavc LIMIT 1");
            if ($v_res && $v_res->num_rows > 0) {
                $vc = $v_res->fetch_assoc();
                if ($tongtien_tam >= (float)($vc['GiaTriToiThieu'] ?? 0)) {
                    if ($vc['LoaiGiam'] == 'phantram') {
                        $voucher_giam = round($tongtien_tam * $vc['GiaTriGiam'] / 100);
                    } else {
                        $voucher_giam = (float)$vc['GiaTriGiam'];
                    }
                }
            }
        }

        // Cập nhật lượt dùng voucher nếu có áp dụng
        if ($voucher_giam > 0 && $mavc) {
            $conn->query("UPDATE Voucher SET SoLanDaDung = SoLanDaDung + 1 WHERE MaVC = $mavc");
            $check_limit = $conn->query("SELECT SoLanDaDung, GioiHanDung FROM Voucher WHERE MaVC = $mavc")->fetch_assoc();
            if ($check_limit['SoLanDaDung'] >= $check_limit['GioiHanDung']) {
                $conn->query("UPDATE Voucher SET TrangThai = 'inactive' WHERE MaVC = $mavc");
            }
        }
        
        $sotien_giam = $vip_giam + $voucher_giam;
        $tongtien = max(0, $tongtien_tam - $sotien_giam);

        // Ghi lại thời điểm thanh toán thực tế
        $ngayra_str = $now->format('Y-m-d H:i:s');

        // Cập nhật Booking + Phòng
        $conn->query("UPDATE DatPhong SET NgayCheckOut = '$ngayra_str', TrangThai = 'Đã thanh toán' WHERE MaDP = $madp");
        $conn->query("UPDATE Phong SET TrangThai = 'Trống' WHERE MaPhong = '$maphong'");
        
        // 3. Tự động tặng voucher "Khách Hàng Thân Thiết 10%" MỖI 2 lần đặt phòng thành công
        // Đếm lại tổng lần thanh toán SAU KHI đã cập nhật trạng thái (bao gồm cả đơn hiện tại)
        $total_paid = $conn->query("SELECT COUNT(*) as cnt FROM DatPhong WHERE MaKH = $makh AND TrangThai IN ('Đã thanh toán','Đã thanh toán (Online)')")->fetch_assoc()['cnt'];
        $expected_vouchers = floor($total_paid / 2);
        $existing_loyal_count = (int)$conn->query("SELECT COUNT(*) as cnt FROM Voucher WHERE MaKH = $makh AND TenVoucher = 'Khách Hàng Thân Thiết 10%'")->fetch_assoc()['cnt'];
        
        // Tặng voucher mới nếu số lượng voucher khách có nhỏ hơn số lượng đáng lẽ được nhận
        if ($expected_vouchers > $existing_loyal_count) {
            $new_code = 'LOYAL-' . strtoupper(substr(md5(uniqid($makh . time(), true)), 0, 8));
            $expiry   = date('Y-m-d', strtotime('+1 year'));
            $conn->query("INSERT INTO Voucher (MaKH, Code, TenVoucher, LoaiGiam, GiaTriGiam, GiaTriToiThieu, NgayHetHan, GioiHanDung, TrangThai, GhiChu) 
                          VALUES ($makh, '$new_code', 'Khách Hàng Thân Thiết 10%', 'phantram', 10, 0, '$expiry', 1, 'active', 'Tặng tự động sau khi đủ 2 lần đặt phòng thành công')");
        }

        // Tìm nhân viên (nếu có)
        $manv = 'NULL';
        $nv_query = $conn->query("SELECT MaNV FROM NhanVien WHERE MaTK = $userid");
        if ($nv_query && $nv_query->num_rows > 0) {
            $manv = $nv_query->fetch_assoc()['MaNV'];
        }

        $conn->query("INSERT INTO HoaDon (MaDP, MaNV, TienPhong, TienDichVu, TongTien, GiamGiaThanhVien) VALUES ($madp, $manv, $tienphong, $tiendv, $tongtien, $sotien_giam)");

        // Gửi mail hóa đơn
        $mail_info = $conn->query("SELECT kh.HoTen, kh.Email FROM KhachHang kh WHERE kh.MaKH = $makh")->fetch_assoc();
        if ($mail_info && $mail_info['Email']) {
            $ten_vc_mail = '';
            if (isset($vc_data['TenVoucher'])) {
                $ten_vc_mail = $vc_data['TenVoucher'];
            } elseif (isset($vc['TenVoucher'])) {
                $ten_vc_mail = $vc['TenVoucher'];
            }
            
            $invoice_data = [
                'tien_phong' => $tienphong,
                'tien_dichvu' => $tiendv,
                'giam_gia' => $sotien_giam,
                'tong_tien' => $tongtien,
                'so_ngay' => $songay,
                'ten_voucher' => $ten_vc_mail
            ];
            sendInvoiceEmail($mail_info['Email'], $mail_info['HoTen'], $invoice_data);
        }


        header("Location: hoadon.php");
        exit;
    }
}

require_once 'include/header.php';

// Load thanh toán cho 1 phòng cụ thể
$booking = null;
$dichvu_used = [];
if ($room_id) {
    $b_res = $conn->query("SELECT dp.*, kh.HoTen, p.MaLoai FROM DatPhong dp JOIN KhachHang kh ON dp.MaKH = kh.MaKH JOIN Phong p ON dp.MaPhong = p.MaPhong WHERE dp.MaPhong = '$room_id' AND dp.TrangThai IN ('Đang ở', 'Đã thanh toán (Online)', 'Đã xác nhận', 'Chờ xác nhận') ORDER BY dp.MaDP DESC LIMIT 1");
    if ($b_res && $b_res->num_rows > 0) {
        $booking = $b_res->fetch_assoc();
        $md = $booking['MaDP'];
        $dv_rs = $conn->query("SELECT sd.*, dv.TenDV FROM SuDungDichVu sd JOIN DichVu dv ON sd.MaDV = dv.MaDV WHERE sd.MaDP = $md");
        if ($dv_rs) { while($r = $dv_rs->fetch_assoc()) $dichvu_used[] = $r; }
    }
}

// Danh sách Hoá Đơn 
$hoadon_res = $conn->query("SELECT hd.*, dp.MaPhong, kh.HoTen 
                            FROM HoaDon hd 
                            JOIN DatPhong dp ON hd.MaDP = dp.MaDP 
                            JOIN KhachHang kh ON dp.MaKH = kh.MaKH 
                            ORDER BY hd.NgayLanhToan DESC
                            LIMIT 50");
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2 class="fw-bold m-0"><i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i> Quản lý Hóa Đơn</h2>
    </div>
</div>

<?php if ($room_id && $booking): 
    $ngayvao = new DateTime($booking['NgayCheckIn']);
    $booked_co = new DateTime($booking['NgayCheckOut']);
    $now_display = new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
    // Hiển thị số ngày theo ngày checkout đã đặt (nếu checkout trước hiện tại thì dùng hiện tại)
    $ngayra = ($now_display > $booked_co) ? $now_display : $booked_co;
    $songay = max(1, $ngayra->diff($ngayvao)->days);

    $gia_res = $conn->query("SELECT GiaPhong FROM LoaiPhong WHERE MaLoai = {$booking['MaLoai']}");
    $gia = $gia_res ? $gia_res->fetch_assoc()['GiaPhong'] : 0;
    $tienphong = $songay * $gia;
    $tiendv = 0;
    foreach($dichvu_used as $d) $tiendv += $d['ThanhTien'];

    $tongtien_tam = $tienphong + $tiendv;

    // Giảm giá 10%: chỉ áp dụng khi đặt từ 2 ngày trở lên
    $vip_giam = ($songay >= 2) ? round($tongtien_tam * 0.10) : 0;
    $ly_do_giam = $songay >= 2 ? 'Đặt '.$songay.' đêm' : '';

    // Voucher check
    $voucher_giam = 0;
    $voucher_name = '';
    if ($booking['MaVC']) {
        $v_res = $conn->query("SELECT * FROM Voucher WHERE MaVC = {$booking['MaVC']}");
        if ($v_res && $v_res->num_rows > 0) {
            $vc = $v_res->fetch_assoc();
            if ($tongtien_tam >= (float)($vc['GiaTriToiThieu'] ?? 0)) {
                if ($vc['LoaiGiam'] == 'phantram') {
                    $voucher_giam = round($tongtien_tam * $vc['GiaTriGiam'] / 100);
                } else {
                    $voucher_giam = (float)$vc['GiaTriGiam'];
                }
                $voucher_name = $vc['TenVoucher'];
            }
        }
    }

    $sotien_giam = $vip_giam + $voucher_giam;
    $tongtien = max(0, $tongtien_tam - $sotien_giam);
?>
<div class="card p-4 border-0 shadow-sm border-top border-warning border-4 mb-4">
    <h4 class="fw-bold text-warning mb-4">Xác Nhận Trả Phòng: <?= $room_id ?></h4>
    <div class="row">
        <div class="col-md-6">
            <p><strong>Khách hàng:</strong> <?= htmlspecialchars($booking['HoTen']) ?>
                <?php if($vip_cnt >= 2): ?><span class="badge bg-warning text-dark"><i class="fa-solid fa-crown"></i> VIP</span><?php endif; ?>
            </p>
            <p><strong>Check-in:</strong> <?= date('H:i d/m/Y', strtotime($booking['NgayCheckIn'])) ?></p>
            <p><strong>Check-out (Đã đặt):</strong> <?= date('H:i d/m/Y', strtotime($booking['NgayCheckOut'])) ?></p>
            <p><strong>Số ngày ở:</strong> <span class="badge bg-primary"><?= $songay ?> ngày</span>
              <small class="text-muted ms-1">(<?= date('d/m/Y', strtotime($booking['NgayCheckIn'])) ?> → <?= $ngayra->format('d/m/Y') ?>)</small></p>
            <p><strong>Đơn giá phòng:</strong> <?= number_format($gia) ?> đ/ngày</p>
            <h5 class="text-secondary mt-3">Tiền phòng: <?= number_format($tienphong) ?> đ</h5>
        </div>
        <div class="col-md-6">
            <h5 class="fw-bold">Dịch vụ đã sử dụng:</h5>
            <ul class="list-group mb-3">
                <?php if (count($dichvu_used) == 0): ?> 
                    <li class='list-group-item text-muted border-0 p-0'>Không có dịch vụ nào</li>
                <?php endif; ?>
                <?php foreach($dichvu_used as $d): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center bg-light border-0 mb-1 rounded">
                    <?= htmlspecialchars($d['TenDV']) ?> <span class="text-muted">(x<?= $d['SoLuong'] ?>)</span>
                    <span class="fw-bold"><?= number_format($d['ThanhTien']) ?> đ</span>
                </li>
                <?php endforeach; ?>
            </ul>
            <h5 class="text-secondary">Tiền dịch vụ: <?= number_format($tiendv) ?> đ</h5>
        </div>
    </div>
    <hr>
    <?php if ($vip_giam > 0): ?>
    <div class="d-flex justify-content-between align-items-center mt-2">
        <h5 class="text-success m-0"><i class="fa-solid fa-percent text-warning me-1"></i>Giảm giá 10% (<?= htmlspecialchars($ly_do_giam ?? '') ?>): <i class="fa-solid fa-arrow-down"></i> <?= number_format($vip_giam) ?> VNĐ</h5>
    </div>
    <?php endif; ?>
    <?php if ($voucher_giam > 0): ?>
    <div class="d-flex justify-content-between align-items-center mt-2">
        <h5 class="text-primary m-0"><i class="fa-solid fa-ticket text-warning me-1"></i>Voucher (<?= htmlspecialchars($voucher_name) ?>): <i class="fa-solid fa-arrow-down"></i> <?= number_format($voucher_giam) ?> VNĐ</h5>
    </div>
    <?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <h3 class="text-danger fw-bold m-0">Tổng thanh toán: <?= number_format($tongtien) ?> VNĐ</h3>
        <form method="POST">
            <input type="hidden" name="action" value="pay">
            <input type="hidden" name="madp" value="<?= $booking['MaDP'] ?>">
            <input type="hidden" name="maphong" value="<?= $room_id ?>">
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">Áp dụng mã Voucher (nếu có):</label>
                <div class="input-group input-group-sm" style="max-width: 300px;">
                    <input type="text" name="voucher_code" class="form-control" placeholder="Nhập mã voucher..." style="text-transform: uppercase;">
                    <span class="input-group-text"><i class="fa-solid fa-tag"></i></span>
                </div>
            </div>
            <button type="submit" class="btn btn-success px-4 py-2 fw-bold" onclick="return confirm('Xác nhận thanh toán và trả phòng?')"><i class="fa-solid fa-check-circle"></i> Xác nhận Thanh toán</button>
        </form>
    </div>
</div>
<?php elseif ($room_id && !$booking): ?>
<div class="alert alert-info mb-4">
    <i class="fa-solid fa-info-circle me-2"></i>Phòng <strong><?= $room_id ?></strong> hiện không có khách đang ở hoặc đã được thanh toán.
</div>
<?php endif; ?>

<div class="card p-4 border-0 shadow-sm">
    <h5 class="fw-bold mb-4">Lịch sử Hóa đơn</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle table-stackable">
            <thead class="table-light">
                <tr>
                    <th>Mã HĐ</th>
                    <th>Ngày Lập</th>
                    <th>Khách Hàng</th>
                    <th>Phòng</th>
                    <th>Tiền Phòng</th>
                    <th>Tiền Dịch Vụ</th>
                    <th>Giảm Giá</th>
                    <th class="text-danger">Tổng Cộng</th>
                    <th class="text-center">In HĐ</th>
                </tr>
            </thead>
            <tbody>
                <?php if($hoadon_res && $hoadon_res->num_rows > 0): ?>
                <?php while ($row = $hoadon_res->fetch_assoc()): ?>
                <tr>
                    <td data-label="Mã HĐ">HD<?= str_pad($row['MaHD'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td data-label="Ngày Lập"><?= date('d/m/Y H:i', strtotime($row['NgayLanhToan'])) ?></td>
                    <td class="fw-bold text-dark" data-label="Khách Hàng"><?= htmlspecialchars($row['HoTen']) ?></td>
                    <td data-label="Phòng">Phòng <?= $row['MaPhong'] ?></td>
                    <td data-label="Tiền Phòng"><?= number_format($row['TienPhong']) ?> ₫</td>
                    <td data-label="Tiền Dịch Vụ"><?= number_format($row['TienDichVu']) ?> ₫</td>
                    <td class="text-success" data-label="Giảm Giá">-<?= number_format($row['GiamGiaThanhVien']) ?> ₫</td>
                    <td class="text-danger fw-bold" data-label="Tổng Cộng"><?= number_format($row['TongTien']) ?> ₫</td>
                    <td class="text-center" data-label="In HĐ">
                        <button class="btn btn-sm btn-outline-primary rounded-pill" 
                                onclick="printHoaDon(<?= htmlspecialchars(json_encode($row)) ?>)"
                                title="In hóa đơn">
                            <i class="fa-solid fa-print"></i>
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php else: ?>
                <tr><td colspan="8" class="text-center py-5 text-muted"><i class="fa-solid fa-box-open fs-1 mb-3 d-block"></i>Chưa có hóa đơn nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal xem trước khi in -->
<div class="modal fade" id="printModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-print me-2"></i>Xem Trước Hóa Đơn</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="printPreview"></div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary fw-bold" onclick="doPrint()">
                    <i class="fa-solid fa-print me-2"></i>In Hóa Đơn
                </button>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body * { visibility: hidden !important; }
    #printArea, #printArea * { visibility: visible !important; }
    #printArea { position: fixed !important; inset: 0; z-index: 9999; background: white; padding: 30px; }
}
.invoice-print { font-family: 'DM Sans', sans-serif; padding: 24px; }
.invoice-print .inv-header { text-align: center; border-bottom: 2px solid #1a237e; padding-bottom: 16px; margin-bottom: 20px; }
.invoice-print .inv-logo { font-size: 26px; font-weight: 800; color: #1a237e; letter-spacing: -1px; }
.invoice-print .inv-sub { font-size: 12px; color: #666; margin-top: 2px; }
.invoice-print .inv-title { font-size: 16px; font-weight: 700; text-align: center; letter-spacing: 2px; text-transform: uppercase; margin: 16px 0; color: #333; }
.invoice-print .inv-row { display: flex; justify-content: space-between; font-size: 13px; padding: 4px 0; border-bottom: 1px dashed #eee; }
.invoice-print .inv-row:last-child { border-bottom: none; }
.invoice-print .inv-total { display: flex; justify-content: space-between; font-size: 18px; font-weight: 800; color: #c00; margin-top: 16px; padding-top: 12px; border-top: 2px solid #333; }
.invoice-print .inv-footer { text-align: center; font-size: 11px; color: #999; margin-top: 20px; border-top: 1px solid #eee; padding-top: 12px; }
.inv-discount { display: flex; justify-content: space-between; font-size: 13px; padding: 4px 0; color: green; }
</style>

<div id="printArea" style="display:none;"></div>

<script>
let currentHD = null;

function printHoaDon(row) {
    currentHD = row;
    const madh = 'HD' + String(row.MaHD).padStart(4, '0');
    const ngay  = row.NgayLanhToan ? new Date(row.NgayLanhToan).toLocaleString('vi-VN') : '';
    const tp    = parseInt(row.TienPhong || 0).toLocaleString('vi-VN');
    const tdv   = parseInt(row.TienDichVu || 0).toLocaleString('vi-VN');
    const gg    = parseInt(row.GiamGiaThanhVien || 0).toLocaleString('vi-VN');
    const tt    = parseInt(row.TongTien || 0).toLocaleString('vi-VN');
    const html = `
    <div class="invoice-print">
        <div class="inv-header">
            <div class="inv-logo">✦ K-Hotel</div>
            <div class="inv-sub">Hệ thống khách sạn K-Hotel Việt Nam</div>
            <div class="inv-sub">📞 1900 1234 | 📧 support@khotel.vn</div>
        </div>
        <div class="inv-title">Hóa Đơn Thanh Toán</div>
        <div class="inv-row"><span>Mã Hóa Đơn:</span><strong>${madh}</strong></div>
        <div class="inv-row"><span>Ngày Lập:</span><span>${ngay}</span></div>
        <div class="inv-row"><span>Khách Hàng:</span><strong>${row.HoTen || ''}</strong></div>
        <div class="inv-row"><span>Phòng:</span><span>Phòng ${row.MaPhong || ''}</span></div>
        <div class="inv-row" style="margin-top:12px;padding-top:12px;border-top:1px solid #ddd;">
            <span>Tiền Phòng:</span><span>${tp} ₫</span>
        </div>
        <div class="inv-row"><span>Tiền Dịch Vụ:</span><span>${tdv} ₫</span></div>
        ${parseInt(row.GiamGiaThanhVien) > 0 ? `<div class="inv-discount"><span>🎁 Giảm Giá VIP (10%):</span><span>- ${gg} ₫</span></div>` : ''}
        <div class="inv-total"><span>TỔNG THANH TOÁN:</span><span>${tt} ₫</span></div>
        <div class="inv-footer">
            Cảm ơn quý khách đã sử dụng dịch vụ K-Hotel!<br>
            <em>Hóa đơn được in lúc: ${new Date().toLocaleString('vi-VN')}</em>
        </div>
    </div>`;
    document.getElementById('printPreview').innerHTML = html;
    document.getElementById('printArea').innerHTML = html;
    new bootstrap.Modal(document.getElementById('printModal')).show();
}

function doPrint() {
    document.getElementById('printArea').style.display = 'block';
    window.print();
    document.getElementById('printArea').style.display = 'none';
}
</script>

<?php require_once 'include/footer.php'; ?>
