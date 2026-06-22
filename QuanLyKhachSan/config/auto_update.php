<?php
// Tự động kiểm tra và cập nhật trạng thái phòng/booking mỗi khi trang được tải.
$now = date('Y-m-d H:i:s');

//  TẠO BẢNG MỚI NẾU CHƯA CÓ 
$conn->query("CREATE TABLE IF NOT EXISTS Voucher (
    MaVC INT AUTO_INCREMENT PRIMARY KEY,
    MaKH INT NULL,
    Code VARCHAR(50) UNIQUE NOT NULL,
    TenVoucher VARCHAR(100) NOT NULL,
    LoaiGiam ENUM('phantram','sotien') DEFAULT 'phantram',
    GiaTriGiam DECIMAL(10,2) NOT NULL,
    GiaTriToiThieu DECIMAL(15,2) DEFAULT 0,
    NgayBatDau DATE NULL,
    NgayHetHan DATE NOT NULL,
    GioiHanDung INT DEFAULT 1,
    SoLanDaDung INT DEFAULT 0,
    TrangThai ENUM('active','inactive') DEFAULT 'active',
    GhiChu TEXT NULL,
    NgayTao DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (MaKH) REFERENCES KhachHang(MaKH) ON DELETE SET NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS CaLamViec (
    MaCa INT AUTO_INCREMENT PRIMARY KEY,
    TenCa VARCHAR(50) NOT NULL,
    GioBatDau TIME NOT NULL,
    GioKetThuc TIME NOT NULL,
    MoTa VARCHAR(255) NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS LichLamViec (
    MaLich INT AUTO_INCREMENT PRIMARY KEY,
    MaNV INT NOT NULL,
    MaCa INT NOT NULL,
    NgayLam DATE NOT NULL,
    GhiChu VARCHAR(255) NULL,
    FOREIGN KEY (MaNV) REFERENCES NhanVien(MaNV) ON DELETE CASCADE,
    FOREIGN KEY (MaCa) REFERENCES CaLamViec(MaCa) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS ChamCong (
    MaCC INT AUTO_INCREMENT PRIMARY KEY,
    MaNV INT NOT NULL,
    MaLich INT NULL,
    NgayCC DATE NOT NULL,
    GioVao TIME NULL,
    GioRa TIME NULL,
    TrangThai ENUM('dung_gio','tre','vang_mat','nghi_phep') DEFAULT 'dung_gio',
    GhiChu TEXT NULL,
    FOREIGN KEY (MaNV) REFERENCES NhanVien(MaNV) ON DELETE CASCADE,
    FOREIGN KEY (MaLich) REFERENCES LichLamViec(MaLich) ON DELETE SET NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS ThuongPhat (
    MaTP INT AUTO_INCREMENT PRIMARY KEY,
    MaNV INT NOT NULL,
    Loai ENUM('thuong','phat') NOT NULL,
    SoTien DECIMAL(15,2) NOT NULL,
    LyDo VARCHAR(255) NOT NULL,
    Ngay DATE NOT NULL,
    GhiChu TEXT NULL,
    FOREIGN KEY (MaNV) REFERENCES NhanVien(MaNV) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS DanhGiaNhanVien (
    MaDGNV INT AUTO_INCREMENT PRIMARY KEY,
    MaKH INT NOT NULL,
    MaNV INT NOT NULL,
    SoSao INT DEFAULT 5,
    NhanXet TEXT NULL,
    NgayDanhGia DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (MaKH) REFERENCES KhachHang(MaKH) ON DELETE CASCADE,
    FOREIGN KEY (MaNV) REFERENCES NhanVien(MaNV) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS PasswordResetTokens (
    MaToken INT AUTO_INCREMENT PRIMARY KEY,
    MaTK INT NOT NULL,
    Token VARCHAR(255) NOT NULL,
    ThoiGianHetHan DATETIME NOT NULL,
    NgayTao DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (MaTK) REFERENCES TaiKhoan(MaTK) ON DELETE CASCADE
)");

// Thêm cột SDT, Email, NgayVaoLam vào NhanVien nếu chưa có
$cols = $conn->query("SHOW COLUMNS FROM NhanVien");
$existing = [];
if ($cols) { while($c = $cols->fetch_assoc()) $existing[] = $c['Field']; }
if (!in_array('SDT', $existing))         $conn->query("ALTER TABLE NhanVien ADD COLUMN SDT VARCHAR(20) NULL");
if (!in_array('Email', $existing))       $conn->query("ALTER TABLE NhanVien ADD COLUMN Email VARCHAR(100) NULL");
if (!in_array('NgayVaoLam', $existing))  $conn->query("ALTER TABLE NhanVien ADD COLUMN NgayVaoLam DATE NULL");

// === TẠO DỮ LIỆU MẪU CHO CA LÀM VIỆC ===
$ca_count = $conn->query("SELECT COUNT(*) as cnt FROM CaLamViec")->fetch_assoc()['cnt'];
if ($ca_count == 0) {
    $conn->query("INSERT INTO CaLamViec (TenCa, GioBatDau, GioKetThuc, MoTa) VALUES
        ('Ca Sáng', '06:00:00', '14:00:00', 'Lễ tân và phục vụ buổi sáng'),
        ('Ca Chiều', '14:00:00', '22:00:00', 'Lễ tân và phục vụ buổi chiều tối'),
        ('Ca Đêm',  '22:00:00', '06:00:00', 'Trực đêm và bảo vệ')");
}

// === TẠO NHÂN VIÊN MẪU NẾU CHƯA CÓ ===
$nv_count = $conn->query("SELECT COUNT(*) as cnt FROM NhanVien WHERE MaNV > 1")->fetch_assoc()['cnt'];
if ($nv_count < 4) {
    $sample_staff = [
        ['Nguyen Thi Lan',  'letanlan',   'lan123',   'Lễ tân',    'Ca Sáng',   8500000,  '0901111222', '2024-01-15'],
        ['Tran Van Minh',   'minhtrv',    'minh123',  'Lễ tân',    'Ca Chiều',  8000000,  '0902222333', '2024-02-01'],
        ['Le Thi Hoa',      'hoale',      'hoa123',   'Thu ngân',  'Ca Sáng',   9000000,  '0903333444', '2023-11-20'],
        ['Pham Van Hung',   'hungpv',     'hung123',  'Bảo vệ',   'Ca Đêm',    7500000,  '0904444555', '2024-03-10'],
        ['Vo Thi Mai',      'maivo',      'mai123',   'Dọn phòng','Ca Chiều',  7000000,  '0905555666', '2024-04-05'],
    ];
    foreach ($sample_staff as $s) {
        [$hoten, $user, $pass, $chucvu, $ca, $luong, $sdt, $ngay] = $s;
        $check = $conn->query("SELECT MaTK FROM TaiKhoan WHERE TenDangNhap = '$user'");
        if ($check && $check->num_rows == 0) {
            $conn->query("INSERT INTO TaiKhoan (TenDangNhap, MatKhau, HoTen, VaiTro) VALUES ('$user','$pass','$hoten','nhanvien')");
            $matk = $conn->insert_id;
            $conn->query("INSERT INTO NhanVien (MaTK, ChucVu, CaLamViec, Luong, SDT, NgayVaoLam) VALUES ($matk,'$chucvu','$ca',$luong,'$sdt','$ngay')");
        }
    }

    // Tạo lịch làm việc cho tuần này
    $monday = date('Y-m-d', strtotime('monday this week'));
    $nv_ids = [];
    $nv_res = $conn->query("SELECT MaNV, CaLamViec FROM NhanVien WHERE MaNV > 1 LIMIT 5");
    if ($nv_res) { while($r = $nv_res->fetch_assoc()) $nv_ids[] = $r; }

    $ca_ids = [];
    $ca_res = $conn->query("SELECT MaCa, TenCa FROM CaLamViec");
    if ($ca_res) { while($r = $ca_res->fetch_assoc()) $ca_ids[$r['TenCa']] = $r['MaCa']; }

    $lich_count = $conn->query("SELECT COUNT(*) as cnt FROM LichLamViec")->fetch_assoc()['cnt'];
    if ($lich_count == 0 && !empty($nv_ids) && !empty($ca_ids)) {
        foreach ($nv_ids as $nv) {
            $ca_ten = $nv['CaLamViec'];
            $ca_id  = $ca_ids[$ca_ten] ?? 1;
            for ($d = 0; $d < 7; $d++) {
                if ($d == 6) continue; // Chủ nhật nghỉ
                $ngay = date('Y-m-d', strtotime($monday . " +$d days"));
                $exist = $conn->query("SELECT 1 FROM LichLamViec WHERE MaNV={$nv['MaNV']} AND NgayLam='$ngay'")->num_rows;
                if (!$exist) {
                    $conn->query("INSERT INTO LichLamViec (MaNV, MaCa, NgayLam) VALUES ({$nv['MaNV']}, $ca_id, '$ngay')");
                }
            }
        }

        // Tạo chấm công mẫu cho ngày hôm nay
        $today = date('Y-m-d');
        foreach ($nv_ids as $i => $nv) {
            $status = ($i == 1) ? 'tre' : 'dung_gio';
            $gio_vao = ($i == 1) ? '14:25:00' : ($nv['CaLamViec'] == 'Ca Sáng' ? '06:02:00' : '14:01:00');
            $gio_vao = ($nv['CaLamViec'] == 'Ca Đêm') ? '22:00:00' : $gio_vao;
            $lich_res = $conn->query("SELECT MaLich FROM LichLamViec WHERE MaNV={$nv['MaNV']} AND NgayLam='$today' LIMIT 1");
            $malich = ($lich_res && $lich_res->num_rows > 0) ? $lich_res->fetch_assoc()['MaLich'] : 'NULL';
            $exist_cc = $conn->query("SELECT 1 FROM ChamCong WHERE MaNV={$nv['MaNV']} AND NgayCC='$today'")->num_rows;
            if (!$exist_cc) {
                $conn->query("INSERT INTO ChamCong (MaNV, MaLich, NgayCC, GioVao, TrangThai) VALUES ({$nv['MaNV']}, $malich, '$today', '$gio_vao', '$status')");
            }
        }

        // Tạo thưởng/phạt mẫu
        $tp_count = $conn->query("SELECT COUNT(*) as cnt FROM ThuongPhat")->fetch_assoc()['cnt'];
        if ($tp_count == 0 && !empty($nv_ids)) {
            $nv1 = $nv_ids[0]['MaNV'] ?? 2;
            $nv2 = $nv_ids[1]['MaNV'] ?? 3;
            $conn->query("INSERT INTO ThuongPhat (MaNV, Loai, SoTien, LyDo, Ngay) VALUES
                ($nv1, 'thuong', 500000, 'Nhân viên xuất sắc tháng 4/2026', '".date('Y-m-d')."'),
                ($nv2, 'phat',   200000, 'Đi làm trễ 3 lần trong tuần',     '".date('Y-m-d')."')");
        }
    }
}

// === CẬP NHẬT TRẠNG THÁI BOOKING & PHÒNG ===

// 1. (Đã vô hiệu hóa) Tự động Check-in. Giữ nguyên trạng thái để Lễ tân Check-in thủ công tại sơ đồ phòng.
/* 
$conn->query("
    UPDATE DatPhong 
    SET TrangThai = 'Đang ở' 
    WHERE TrangThai IN ('Đã xác nhận', 'Đã thanh toán (Online)', 'Chờ xác nhận')
    AND '$now' >= NgayCheckIn 
    AND (NgayCheckOut IS NULL OR '$now' < NgayCheckOut)
");
*/

// 2. Tự động trả phòng nếu quá hạn checkout 2 tiếng (Dành cho khách quên trả)
$conn->query("
    UPDATE DatPhong 
    SET TrangThai = 'Đã thanh toán' 
    WHERE TrangThai = 'Đang ở'
    AND NgayCheckOut IS NOT NULL 
    AND DATE_ADD(NgayCheckOut, INTERVAL 2 HOUR) <= '$now'
");

// 3. Huỷ các booking quá hạn check-in 12 tiếng mà không đến
$conn->query("
    UPDATE DatPhong 
    SET TrangThai = 'Đã huỷ' 
    WHERE TrangThai IN ('Chờ xác nhận', 'Đã xác nhận')
    AND DATE_ADD(NgayCheckIn, INTERVAL 12 HOUR) <= '$now'
");

// 4. ĐỒNG BỘ TRẠNG THÁI BẢNG PHÒNG (Sử dụng subquery để chính xác tuyệt đối)
// Bước A: Set tất cả phòng về 'Trống' nếu KHÔNG có booking nào đang ở
$conn->query("
    UPDATE Phong p 
    SET p.TrangThai = 'Trống' 
    WHERE p.TrangThai = 'Đang ở' 
    AND NOT EXISTS (
        SELECT 1 FROM DatPhong dp 
        WHERE dp.MaPhong = p.MaPhong 
        AND dp.TrangThai = 'Đang ở'
    )
");

// Bước B: Set phòng thành 'Đang ở' nếu CÓ booking đang ở
$conn->query("
    UPDATE Phong p 
    SET p.TrangThai = 'Đang ở' 
    WHERE p.TrangThai != 'Đang ở' 
    AND EXISTS (
        SELECT 1 FROM DatPhong dp 
        WHERE dp.MaPhong = p.MaPhong 
        AND dp.TrangThai = 'Đang ở'
    )
");

// 5. DỌN DẸP TÀI KHOẢN "MỒ CÔI" (Fix lỗi Tên đăng nhập đã tồn tại)
// Xóa các tài khoản có vai trò 'khach' mà không còn nằm trong bảng KhachHang
$conn->query("
    DELETE FROM TaiKhoan 
    WHERE VaiTro = 'khach' 
    AND MaTK NOT IN (SELECT MaTK FROM KhachHang WHERE MaTK IS NOT NULL)
");
?>
