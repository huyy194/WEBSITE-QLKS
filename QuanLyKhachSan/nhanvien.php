<?php
require_once 'config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Xử lý các hành động POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Lưu/Sửa nhân viên
    if (isset($_POST['action']) && $_POST['action'] == 'save_staff') {
        $hoten = $conn->real_escape_string($_POST['hoten']);
        $username = $conn->real_escape_string($_POST['username']);
        $password = $_POST['password'];
        $chucvu = $conn->real_escape_string($_POST['chucvu']);
        $calam = $conn->real_escape_string($_POST['calam']);
        $luong = (float)$_POST['luong'];
        $sdt = $conn->real_escape_string($_POST['sdt']);
        $email = $conn->real_escape_string($_POST['email']);
        $ngayvao = $_POST['ngayvao'];

        if (isset($_POST['matk']) && $_POST['matk'] > 0) {
            $matk = (int)$_POST['matk'];
            $manv = (int)$_POST['id'];
            $conn->query("UPDATE TaiKhoan SET HoTen='$hoten' WHERE MaTK=$matk");
            if($password) $conn->query("UPDATE TaiKhoan SET MatKhau='$password' WHERE MaTK=$matk");
            $conn->query("UPDATE NhanVien SET ChucVu='$chucvu', CaLamViec='$calam', Luong=$luong, SDT='$sdt', Email='$email', NgayVaoLam='$ngayvao' WHERE MaNV=$manv");
        } else {
            $conn->query("INSERT INTO TaiKhoan (TenDangNhap, MatKhau, HoTen, VaiTro) VALUES ('$username', '$password', '$hoten', 'nhanvien')");
            $matk = $conn->insert_id;
            $conn->query("INSERT INTO NhanVien (MaTK, ChucVu, CaLamViec, Luong, SDT, Email, NgayVaoLam) VALUES ($matk, '$chucvu', '$calam', $luong, '$sdt', '$email', '$ngayvao')");
        }
    }

    // 2. Thêm Thưởng/Phạt
    if (isset($_POST['action']) && $_POST['action'] == 'add_tp') {
        $manv = (int)$_POST['manv'];
        $loai = $conn->real_escape_string($_POST['loai']);
        $sotien = (float)$_POST['sotien'];
        $lydo = $conn->real_escape_string($_POST['lydo']);
        $ngay = $_POST['ngay'];
        $conn->query("INSERT INTO ThuongPhat (MaNV, Loai, SoTien, LyDo, Ngay) VALUES ($manv, '$loai', $sotien, '$lydo', '$ngay')");
    }

    // 3. Phân lịch làm việc
    if (isset($_POST['action']) && $_POST['action'] == 'add_schedule') {
        $manv = (int)$_POST['manv'];
        $maca = (int)$_POST['maca'];
        $ngay = $_POST['ngay'];
        $conn->query("INSERT INTO LichLamViec (MaNV, MaCa, NgayLam) VALUES ($manv, $maca, '$ngay')");
    }

    header("Location: nhanvien.php");
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM NhanVien WHERE MaNV = $id");
    header("Location: nhanvien.php");
    exit;
}

require_once 'include/header.php';

// Lấy danh sách nhân viên cùng các thống kê chi tiết
$sql_staff = "
    SELECT 
        nv.*, tk.HoTen, tk.TenDangNhap,
        (SELECT GROUP_CONCAT(CONCAT(CASE DAYOFWEEK(llv.NgayLam) 
            WHEN 1 THEN 'CN' WHEN 2 THEN 'T2' WHEN 3 THEN 'T3' 
            WHEN 4 THEN 'T4' WHEN 5 THEN 'T5' WHEN 6 THEN 'T6' WHEN 7 THEN 'T7' END, 
            '-', clv.TenCa) SEPARATOR ', ')
         FROM LichLamViec llv 
         JOIN CaLamViec clv ON llv.MaCa = clv.MaCa 
         WHERE llv.MaNV = nv.MaNV AND llv.NgayLam >= CURDATE() AND llv.NgayLam <= DATE_ADD(CURDATE(), INTERVAL 6 DAY)
        ) AS LichTuan,
        (SELECT SUM(SoTien) FROM ThuongPhat WHERE MaNV = nv.MaNV AND Loai = 'thuong') AS TongThuong,
        (SELECT SUM(SoTien) FROM ThuongPhat WHERE MaNV = nv.MaNV AND Loai = 'phat') AS TongPhat,
        (SELECT COUNT(*) FROM ChamCong WHERE MaNV = nv.MaNV AND TrangThai = 'dung_gio') AS DungGio,
        (SELECT COUNT(*) FROM ChamCong WHERE MaNV = nv.MaNV AND TrangThai IN ('tre', 'vang_mat')) AS TreVang
    FROM NhanVien nv 
    JOIN TaiKhoan tk ON nv.MaTK = tk.MaTK
";
$staff_res = $conn->query($sql_staff);

// Lấy danh sách ca làm
$ca_res = $conn->query("SELECT * FROM CaLamViec");
$shifts = [];
while($c = $ca_res->fetch_assoc()) $shifts[] = $c;

// Lấy danh sách thưởng phạt
$tp_res = $conn->query("SELECT tp.*, tk.HoTen FROM ThuongPhat tp JOIN NhanVien nv ON tp.MaNV = nv.MaNV JOIN TaiKhoan tk ON nv.MaTK = tk.MaTK ORDER BY tp.Ngay DESC LIMIT 50");

// Lấy chấm công gần đây
$cc_res = $conn->query("SELECT cc.*, tk.HoTen, cl.TenCa FROM ChamCong cc 
                         JOIN NhanVien nv ON cc.MaNV = nv.MaNV 
                         JOIN TaiKhoan tk ON nv.MaTK = tk.MaTK
                         LEFT JOIN LichLamViec llv ON cc.MaLich = llv.MaLich
                         LEFT JOIN CaLamViec cl ON llv.MaCa = cl.MaCa
                         ORDER BY cc.NgayCC DESC, cc.GioVao DESC LIMIT 50");


?>

<div class="container-fluid px-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h2 class="fw-bold m-0" style="color: #1e293b;"><i class="fa-solid fa-people-group text-primary me-2"></i> Hệ Thống Quản Trị Nhân Sự</h2>
            <div class="d-flex gap-2">
                <button class="btn btn-primary shadow-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#nvModal" onclick="resetForm()">
                    <i class="fa-solid fa-plus me-1"></i> Thêm Nhân Viên
                </button>
                <button class="btn btn-outline-warning shadow-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#tpModal">
                    <i class="fa-solid fa-hand-holding-dollar me-1"></i> Thưởng / Phạt
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row mb-4 g-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 bg-primary bg-opacity-10 p-3 me-3">
                        <i class="fa-solid fa-users text-primary fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Tổng nhân viên</div>
                        <div class="fs-4 fw-bold"><?= $staff_res->num_rows ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 bg-success bg-opacity-10 p-3 me-3">
                        <i class="fa-solid fa-clock text-success fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Đúng giờ hôm nay</div>
                        <div class="fs-4 fw-bold">
                            <?php 
                                $today = date('Y-m-d');
                                echo $conn->query("SELECT COUNT(*) FROM ChamCong WHERE NgayCC='$today' AND TrangThai='dung_gio'")->fetch_row()[0];
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 bg-danger bg-opacity-10 p-3 me-3">
                        <i class="fa-solid fa-triangle-exclamation text-danger fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Đi trễ / Vắng</div>
                        <div class="fs-4 fw-bold">
                            <?php 
                                echo $conn->query("SELECT COUNT(*) FROM ChamCong WHERE NgayCC='$today' AND TrangThai IN ('tre','vang_mat')")->fetch_row()[0];
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-0 p-0">
            <ul class="nav nav-tabs nav-fill" id="staffTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active fw-bold py-3 border-0" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-pane" type="button">
                        <i class="fa-solid fa-list me-2"></i>Danh sách nhân sự
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold py-3 border-0" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule-pane" type="button">
                        <i class="fa-solid fa-calendar-days me-2"></i>Lịch & Chấm công
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold py-3 border-0" id="bonus-tab" data-bs-toggle="tab" data-bs-target="#bonus-pane" type="button">
                        <i class="fa-solid fa-money-bill-trend-up me-2"></i>Thưởng & Phạt
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body p-4 bg-white">
            <div class="tab-content" id="staffTabsContent">
                
                <!-- Tab: Danh sách nhân sự -->
                <div class="tab-pane fade show active" id="list-pane">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" style="font-size: 0.9rem;">
                            <thead class="bg-light">
                                <tr>
                                    <th>Nhân viên</th>
                                    <th>Chức vụ</th>
                                    <th>Lịch tuần này</th>
                                    <th>Lương / Thưởng / Phạt</th>
                                    <th>Chuyên cần</th>

                                    <th class="text-end">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $staff_res->data_seek(0); while ($row = $staff_res->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3 bg-primary text-white d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width:40px; height:40px; border-radius:50%; flex-shrink:0;">
                                                <?= mb_substr($row['HoTen'], 0, 1) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?= $row['HoTen'] ?></div>
                                                <div class="text-muted small" style="font-size: 0.8rem;">@<?= $row['TenDangNhap'] ?></div>
                                                <div class="text-muted small mt-1" style="font-size: 0.75rem;"><i class="fa-solid fa-phone me-1"></i><?= $row['SDT'] ?: '—' ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info rounded-pill px-3 py-2 border border-info-subtle"><?= $row['ChucVu'] ?></span>
                                    </td>
                                    <td style="max-width: 180px;">
                                        <div class="small text-muted" title="<?= htmlspecialchars($row['LichTuan'] ?: 'Chưa có lịch') ?>" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                            <i class="fa-regular fa-calendar-check text-primary me-1"></i> 
                                            <?= $row['LichTuan'] ?: '<em>Chưa xếp lịch</em>' ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-success mb-1"><?= number_format($row['Luong']) ?> ₫</div>
                                        <div class="d-flex gap-3 small">
                                            <span class="text-success fw-bold" title="Tổng Thưởng"><i class="fa-solid fa-arrow-trend-up me-1"></i><?= number_format($row['TongThuong'] ?: 0) ?></span>
                                            <span class="text-danger fw-bold" title="Tổng Phạt"><i class="fa-solid fa-arrow-trend-down me-1"></i><?= number_format($row['TongPhat'] ?: 0) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1 small">
                                            <span class="text-success"><i class="fa-solid fa-check-circle me-1"></i>Đúng giờ: <strong><?= $row['DungGio'] ?: 0 ?></strong></span>
                                            <span class="text-danger"><i class="fa-solid fa-times-circle me-1"></i>Trễ/Vắng: <strong><?= $row['TreVang'] ?: 0 ?></strong></span>
                                        </div>
                                    </td>

                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-light btn-sm rounded-circle shadow-sm" data-bs-toggle="dropdown"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                                <li><a class="dropdown-item" href="javascript:void(0)" onclick='editData(<?= json_encode($row) ?>)' data-bs-toggle="modal" data-bs-target="#nvModal"><i class="fa-solid fa-pen me-2 text-primary"></i>Chỉnh sửa</a></li>
                                                <li><a class="dropdown-item" href="javascript:void(0)" onclick='setSch(<?= $row['MaNV'] ?>)' data-bs-toggle="modal" data-bs-target="#schModal"><i class="fa-solid fa-calendar-plus me-2 text-info"></i>Phân lịch</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="?delete=<?= $row['MaNV'] ?>" onclick="return confirm('Xóa nhân viên này?')"><i class="fa-solid fa-trash me-2"></i>Xóa hồ sơ</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Lịch & Chấm công -->
                <div class="tab-pane fade" id="schedule-pane">
                    <div class="row">
                        <div class="col-md-7">
                            <h5 class="fw-bold mb-3"><i class="fa-solid fa-fingerprint text-primary me-2"></i>Nhật ký chấm công gần đây</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle border">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Nhân viên</th>
                                            <th>Ngày</th>
                                            <th>Giờ vào/ra</th>
                                            <th>Trạng thái</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($cc = $cc_res->fetch_assoc()): 
                                            $stClass = 'bg-success';
                                            $stText = 'Đúng giờ';
                                            if($cc['TrangThai']=='tre') { $stClass='bg-warning text-dark'; $stText='Trễ'; }
                                            elseif($cc['TrangThai']=='vang_mat') { $stClass='bg-danger'; $stText='Vắng'; }
                                        ?>
                                        <tr>
                                            <td><strong><?= $cc['HoTen'] ?></strong><br><small class="text-muted"><?= $cc['TenCa'] ?></small></td>
                                            <td><?= date('d/m/Y', strtotime($cc['NgayCC'])) ?></td>
                                            <td>
                                                <span class="text-success"><?= $cc['GioVao'] ? date('H:i', strtotime($cc['GioVao'])) : '--:--' ?></span>
                                                <span class="mx-1">→</span>
                                                <span class="text-secondary"><?= $cc['GioRa'] ? date('H:i', strtotime($cc['GioRa'])) : '--:--' ?></span>
                                            </td>
                                            <td><span class="badge <?= $stClass ?>"><?= $stText ?></span></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <h5 class="fw-bold mb-3"><i class="fa-solid fa-clock text-warning me-2"></i>Quản lý ca làm việc</h5>
                            <div class="list-group">
                                <?php foreach($shifts as $s): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold"><?= $s['TenCa'] ?></div>
                                        <small class="text-muted"><?= date('H:i', strtotime($s['GioBatDau'])) ?> - <?= date('H:i', strtotime($s['GioKetThuc'])) ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="small text-primary"><?= $s['MoTa'] ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Thưởng & Phạt -->
                <div class="tab-pane fade" id="bonus-pane">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle border">
                            <thead class="table-light">
                                <tr>
                                    <th>Nhân viên</th>
                                    <th>Loại</th>
                                    <th>Số tiền</th>
                                    <th>Lý do</th>
                                    <th>Ngày ghi nhận</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($tp = $tp_res->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= $tp['HoTen'] ?></strong></td>
                                    <td>
                                        <span class="badge <?= $tp['Loai'] == 'thuong' ? 'bg-success' : 'bg-danger' ?> px-3">
                                            <?= $tp['Loai'] == 'thuong' ? 'THƯỞNG' : 'PHẠT' ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold <?= $tp['Loai'] == 'thuong' ? 'text-success' : 'text-danger' ?>">
                                        <?= $tp['Loai'] == 'thuong' ? '+' : '-' ?> <?= number_format($tp['SoTien']) ?> ₫
                                    </td>
                                    <td><?= $tp['LyDo'] ?></td>
                                    <td class="text-muted"><?= date('d/m/Y', strtotime($tp['Ngay'])) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>



            </div>
        </div>
    </div>
</div>

<!-- Modal: Thêm/Sửa nhân sự -->
<div class="modal fade" id="nvModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <input type="hidden" name="action" value="save_staff">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title fw-bold" id="modalTitle">Thông Tin Nhân Sự</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="nv_id">
                    <input type="hidden" name="matk" id="matk">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label text-secondary small fw-bold">Họ Tên (*)</label>
                            <input type="text" name="hoten" id="nv_hoten" class="form-control border-0 bg-light" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-bold">Chức Vụ</label>
                            <select name="chucvu" id="nv_chucvu" class="form-select border-0 bg-light">
                                <option value="Lễ tân">Lễ tân</option>
                                <option value="Thu ngân">Thu ngân</option>
                                <option value="Dọn phòng">Dọn phòng</option>
                                <option value="Bảo vệ">Bảo vệ</option>
                                <option value="Quản lý">Quản lý</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary small fw-bold">Số điện thoại</label>
                            <input type="text" name="sdt" id="nv_sdt" class="form-control border-0 bg-light">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary small fw-bold">Email</label>
                            <input type="email" name="email" id="nv_email" class="form-control border-0 bg-light">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary small fw-bold">Tên đăng nhập (*)</label>
                            <input type="text" name="username" id="nv_user" class="form-control border-0 bg-light" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary small fw-bold">Mật khẩu</label>
                            <input type="password" name="password" id="nv_pass" class="form-control border-0 bg-light" placeholder="Nhập để cập nhật MK">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-bold">Lương cơ bản (đ)</label>
                            <input type="number" name="luong" id="nv_luong" class="form-control border-0 bg-light" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-bold">Ca ưu tiên</label>
                            <select name="calam" id="nv_calam" class="form-select border-0 bg-light">
                                <option value="Ca Sáng">Ca Sáng</option>
                                <option value="Ca Chiều">Ca Chiều</option>
                                <option value="Ca Đêm">Ca Đêm</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-bold">Ngày vào làm</label>
                            <input type="date" name="ngayvao" id="nv_ngayvao" class="form-control border-0 bg-light" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm rounded-pill">LƯU HỒ SƠ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Thưởng / Phạt -->
<div class="modal fade" id="tpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <input type="hidden" name="action" value="add_tp">
                <div class="modal-header bg-warning text-dark border-0">
                    <h5 class="modal-title fw-bold">Ghi nhận Thưởng / Phạt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Chọn nhân viên</label>
                        <select name="manv" class="form-select border-0 bg-light" required>
                            <?php $staff_res->data_seek(0); while($s = $staff_res->fetch_assoc()): ?>
                                <option value="<?= $s['MaNV'] ?>"><?= $s['HoTen'] ?> (<?= $s['ChucVu'] ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold">Loại hình</label>
                            <select name="loai" class="form-select border-0 bg-light">
                                <option value="thuong">Thưởng (+)</option>
                                <option value="phat">Phạt (-)</option>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold">Số tiền (₫)</label>
                            <input type="number" name="sotien" class="form-control border-0 bg-light" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Lý do ghi nhận</label>
                        <input type="text" name="lydo" class="form-control border-0 bg-light" placeholder="VD: Đi làm trễ, Khen thưởng chuyên cần..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Ngày áp dụng</label>
                        <input type="date" name="ngay" class="form-control border-0 bg-light" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="submit" class="btn btn-warning w-100 fw-bold shadow-sm">XÁC NHẬN GHI NHẬN</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Phân lịch -->
<div class="modal fade" id="schModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <input type="hidden" name="action" value="add_schedule">
                <input type="hidden" name="manv" id="sch_nv_id">
                <div class="modal-header bg-info text-white border-0">
                    <h5 class="modal-title fw-bold">Phân lịch làm việc</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Chọn Ca Làm</label>
                        <select name="maca" class="form-select border-0 bg-light">
                            <?php foreach($shifts as $s): ?>
                                <option value="<?= $s['MaCa'] ?>"><?= $s['TenCa'] ?> (<?= substr($s['GioBatDau'],0,5) ?> - <?= substr($s['GioKetThuc'],0,5) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Chọn Ngày</label>
                        <input type="date" name="ngay" class="form-control border-0 bg-light" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-info w-100 text-white fw-bold shadow-sm">LƯU LỊCH TRÌNH</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('modalTitle').innerText = 'Thêm Nhân Sự Mới';
    document.getElementById('nv_id').value = '';
    document.getElementById('matk').value = '';
    document.getElementById('nv_hoten').value = '';
    document.getElementById('nv_user').value = '';
    document.getElementById('nv_user').readOnly = false;
    document.getElementById('nv_pass').required = true;
    document.getElementById('nv_chucvu').value = 'Lễ tân';
    document.getElementById('nv_calam').value = 'Ca Sáng';
    document.getElementById('nv_luong').value = '';
    document.getElementById('nv_sdt').value = '';
    document.getElementById('nv_email').value = '';
    document.getElementById('nv_ngayvao').value = '<?= date('Y-m-d') ?>';
}

function editData(data) {
    document.getElementById('modalTitle').innerText = 'Cập Nhật Nhân Sự';
    document.getElementById('nv_id').value = data.MaNV;
    document.getElementById('matk').value = data.MaTK;
    document.getElementById('nv_hoten').value = data.HoTen;
    document.getElementById('nv_user').value = data.TenDangNhap;
    document.getElementById('nv_user').readOnly = true;
    document.getElementById('nv_pass').required = false;
    document.getElementById('nv_chucvu').value = data.ChucVu;
    document.getElementById('nv_calam').value = data.CaLamViec;
    document.getElementById('nv_luong').value = data.Luong;
    document.getElementById('nv_sdt').value = data.SDT || '';
    document.getElementById('nv_email').value = data.Email || '';
    document.getElementById('nv_ngayvao').value = data.NgayVaoLam || '';
}

function setSch(id) {
    document.getElementById('sch_nv_id').value = id;
}
</script>

<?php require_once 'include/footer.php'; ?>
