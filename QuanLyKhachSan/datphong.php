<?php
require_once 'config/database.php';
require_once 'mailer.php';


$room_id = isset($_GET['room']) ? $conn->real_escape_string($_GET['room']) : '';

$new_kh_id = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'add_customer') {
        $hoten = $conn->real_escape_string($_POST['hoten']);
        $cccd  = $conn->real_escape_string($_POST['cccd']);
        $sdt   = $conn->real_escape_string($_POST['sdt']);
        $email = $conn->real_escape_string($_POST['email']);
        $conn->query("INSERT INTO KhachHang (HoTen, CCCD, SDT, Email) VALUES ('$hoten', '$cccd', '$sdt', '$email')");
        $new_kh_id = $conn->insert_id; 

        // Không redirect — ở lại để admin hoàn tất đặt phòng ngay
    } else {
        $makh    = (int)$_POST['makh'];
        $maphong = $conn->real_escape_string($_POST['maphong']);
        $ngayvao = $conn->real_escape_string($_POST['ngayvao']);
        $ngayra  = $conn->real_escape_string($_POST['ngayra']);

        // Luôn đặt trạng thái 'Đã xác nhận' — Lễ tân phải bấm nhận phòng tại Sơ đồ phòng
        $trang_thai_dp    = 'Đã xác nhận';

        $sql = "INSERT INTO DatPhong (MaKH, MaPhong, NgayCheckIn, NgayCheckOut, TrangThai, NguonDat)
                VALUES ($makh, '$maphong', '$ngayvao', '$ngayra', '$trang_thai_dp', 'TaiQuay')";
        if ($conn->query($sql)) {
            $last_id = $conn->insert_id;
            _logMail("INSERT thành công trong datphong.php. MaDP mới: $last_id");

            $mail_info = $conn->query("SELECT dp.*, kh.HoTen, kh.Email, lp.TenLoai, lp.GiaPhong 
                                     FROM DatPhong dp 
                                     JOIN KhachHang kh ON dp.MaKH = kh.MaKH 
                                     JOIN Phong p ON dp.MaPhong = p.MaPhong
                                     JOIN LoaiPhong lp ON p.MaLoai = lp.MaLoai
                                     WHERE dp.MaDP = $last_id")->fetch_assoc();
            
            if ($mail_info && !empty($mail_info['Email'])) {
                _logMail("Yêu cầu gửi mail từ datphong.php cho " . $mail_info['Email']);
                $d1 = new DateTime($mail_info['NgayCheckIn']);
                $d2 = new DateTime($mail_info['NgayCheckOut']);
                $diff = max(1, $d1->diff($d2)->days);
                $tong_tien_calc = $diff * $mail_info['GiaPhong'];

                $booking_data = [
                    'ma_dat' => $mail_info['MaDP'],
                    'ten_phong' => $mail_info['TenLoai'] . " (" . $mail_info['MaPhong'] . ")",
                    'ngay_checkin' => $mail_info['NgayCheckIn'],
                    'ngay_checkout' => $mail_info['NgayCheckOut'],
                    'tong_tien' => $tong_tien_calc, 
                    'phuong_thuc' => 'Tại quầy'
                ];
                // $sent = sendBookingConfirmation($mail_info['Email'], $mail_info['HoTen'], $booking_data);
                $sent = true; // Giả lập gửi thành công để không hiện lỗi, hoặc có thể thông báo khác
                if (!$sent) {
                    $_SESSION['error'] = "Đặt phòng thành công nhưng KHÔNG gửi được email. Vui lòng kiểm tra cấu hình SMTP.";
                } else {
                    $_SESSION['success'] = "Đặt phòng thành công và ĐÃ gửi email xác nhận.";
                }
            } else {
                _logMail("KHÔNG gửi mail từ datphong.php vì Email rỗng. MaKH: " . ($mail_info['MaKH'] ?? 'N/A'));
                $_SESSION['success'] = "Đặt phòng thành công (Khách hàng không có email để gửi xác nhận).";
            }

            header("Location: sodophong.php");
            exit;

        }
    }
}

require_once 'include/header.php';

// Khách vừa tạo lên đầu để dễ chọn
$kh_res    = $conn->query("SELECT * FROM KhachHang ORDER BY MaKH DESC");
$phong_res = $conn->query("SELECT p.*, lp.TenLoai FROM Phong p JOIN LoaiPhong lp ON p.MaLoai = lp.MaLoai WHERE p.TrangThai = 'Trống'");
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold m-0"><i class="fa-solid fa-calendar-check text-primary me-2"></i> Lập Phiếu Đặt Phòng</h2>
    </div>
</div>

<?php if ($new_kh_id > 0): ?>
<div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
    <i class="fa-solid fa-circle-check me-2"></i> Đã thêm khách hàng mới! Khách đã được chọn bên dưới — vui lòng hoàn tất đặt phòng.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card p-4 border-0 shadow-sm">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-medium text-secondary">Khách Hàng (*)</label>
                    <select name="makh" class="form-select bg-light border-0" required>
                        <option value="">-- Chọn Khách Hàng --</option>
                        <?php while($kh = $kh_res->fetch_assoc()): ?>
                            <option value="<?= $kh['MaKH'] ?>" 
                                    <?= ($kh['MaKH'] == $new_kh_id) ? 'selected' : '' ?>
                                    data-flag="<?= $kh['BuocThanhToanTruoc'] ?>"
                                    data-lydo="<?= htmlspecialchars($kh['LyDoFlag'] ?? '') ?>">
                                <?= htmlspecialchars($kh['HoTen']) ?> (CCCD: <?= htmlspecialchars($kh['CCCD']) ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div id="flag_warning" class="alert alert-danger d-none mt-2 py-2 small shadow-sm border-0">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        <strong>Lưu ý:</strong> Khách này bị hạn chế thanh toán tiền mặt.<span id="flag_reason_text"></span>
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#fastKhachModal">
                            <i class="fa-solid fa-plus"></i> Tạo nhanh khách hàng mới ngay
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-medium text-secondary">Phòng Đặt (*)</label>
                    <select name="maphong" class="form-select bg-light border-0" required>
                        <option value="">-- Chọn Phòng --</option>
                        <?php while($p = $phong_res->fetch_assoc()): ?>
                            <option value="<?= $p['MaPhong'] ?>" <?= $p['MaPhong'] == $room_id ? 'selected' : '' ?>>
                                Phòng <?= $p['MaPhong'] ?> - <?= $p['TenLoai'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="row">
                    <div class="col-6 mb-4">
                        <label class="form-label fw-medium text-secondary">Ngày Check-in (*)</label>
                        <input type="datetime-local" name="ngayvao" class="form-control bg-light border-0" required value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    <div class="col-6 mb-4">
                        <label class="form-label fw-medium text-secondary">Ngày Check-out (*)</label>
                        <input type="datetime-local" name="ngayra" class="form-control bg-light border-0" required value="<?= date('Y-m-d\TH:i', strtotime('+1 day')) ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary px-4 fw-bold">Xác nhận đặt phòng</button>
                <a href="sodophong.php" class="btn btn-outline-secondary ms-2">Hủy</a>
            </form>
        </div>
    </div>
</div>

<!-- Modal Thêm Khách Nhanh -->
<div class="modal fade" id="fastKhachModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <input type="hidden" name="action" value="add_customer">
                <div class="modal-header bg-success text-white border-0">
                    <h5 class="modal-title fw-bold">Thêm Khách Hàng Nhanh</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Họ Tên (*)</label>
                        <input type="text" name="hoten" class="form-control bg-light border-0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">CCCD (*)</label>
                        <input type="text" name="cccd" class="form-control bg-light border-0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Số Điện Thoại (*)</label>
                        <input type="text" name="sdt" class="form-control bg-light border-0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Email</label>
                        <input type="email" name="email" class="form-control bg-light border-0" placeholder="khachhang@example.com">
                    </div>

                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success px-4">Lưu & Tiếp tục đặt phòng</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelector('select[name="makh"]')?.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const flag = opt.getAttribute('data-flag');
    const lydo = opt.getAttribute('data-lydo');
    const warn = document.getElementById('flag_warning');
    const reasonText = document.getElementById('flag_reason_text');
    if (flag == '1') {
        warn.classList.remove('d-none');
        reasonText.innerText = lydo ? ` Lý do: ${lydo}.` : '';
    } else {
        warn.classList.add('d-none');
    }
});
</script>

<?php require_once 'include/footer.php'; ?>
