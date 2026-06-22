<?php
require_once 'config/database.php';

// Xử lý Flag/Unflag buộc thanh toán trước
if (isset($_GET['flag'])) {
    $id = (int)$_GET['flag'];
    $lydo = $conn->real_escape_string($_GET['lydo'] ?? 'Khách từng không nhận phòng');
    $conn->query("UPDATE KhachHang SET BuocThanhToanTruoc = 1, LyDoFlag = '$lydo' WHERE MaKH = $id");
    $_SESSION['success'] = "Đã đánh dấu khách hàng phải thanh toán trước!";
    header("Location: khachhang.php");
    exit;
}
if (isset($_GET['unflag'])) {
    $id = (int)$_GET['unflag'];
    $conn->query("UPDATE KhachHang SET BuocThanhToanTruoc = 0, LyDoFlag = NULL WHERE MaKH = $id");
    $_SESSION['success'] = "Đã gỡ bỏ hạn chế thanh toán cho khách hàng!";
    header("Location: khachhang.php");
    exit;
}

// Xử lý Xóa
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Tìm MaTK liên kết trước khi xóa
    $res_tk = $conn->query("SELECT MaTK FROM KhachHang WHERE MaKH = $id");
    if ($res_tk && $res_tk->num_rows > 0) {
        $row_tk = $res_tk->fetch_assoc();
        $matk = $row_tk['MaTK'];
        
        // Xóa khách hàng trước
        $conn->query("DELETE FROM KhachHang WHERE MaKH = $id");
        
        // Nếu có tài khoản, xóa luôn tài khoản
        if ($matk) {
            $conn->query("DELETE FROM TaiKhoan WHERE MaTK = $matk");
        }
    } else {
        $conn->query("DELETE FROM KhachHang WHERE MaKH = $id");
    }
    
    $_SESSION['success'] = "Đã xóa khách hàng và tài khoản liên quan thành công!";
    header("Location: khachhang.php");
    exit;
}

// Xử lý Thêm/Sửa
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $hoten = $conn->real_escape_string(trim($_POST['hoten']));
    $cccd = $conn->real_escape_string(trim($_POST['cccd']));
    $sdt = $conn->real_escape_string(trim($_POST['sdt']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    try {
        // Kiểm tra trùng CCCD nếu là thêm mới hoặc thay đổi CCCD hiện tại
        $check_sql = "SELECT MaKH FROM KhachHang WHERE CCCD = '$cccd' AND MaKH != $id";
        $check_res = $conn->query($check_sql);
        if ($check_res && $check_res->num_rows > 0) {
            $_SESSION['error'] = "Lỗi: Số CCCD '$cccd' đã được sử dụng bởi khách hàng khác!";
        } else {
            if ($id > 0) {
                $sql = "UPDATE KhachHang SET HoTen='$hoten', CCCD='$cccd', SDT='$sdt', Email='$email' WHERE MaKH=$id";
                if ($conn->query($sql)) {
                    $_SESSION['success'] = "Cập nhật thông tin khách hàng thành công!";
                } else {
                    throw new Exception($conn->error);
                }
            } else {
                $sql = "INSERT INTO KhachHang (HoTen, CCCD, SDT, Email) VALUES ('$hoten', '$cccd', '$sdt', '$email')";
                if ($conn->query($sql)) {
                    $makh = $conn->insert_id;
                    // Tạo voucher khách hàng mới cho User này
                    $code_vc = 'NEW-' . strtoupper(substr(md5(uniqid()), 0, 6));
                    $ngay_het_han = date('Y-m-d', strtotime('+30 days'));
                    $sql_vc = "INSERT INTO Voucher (MaKH, Code, TenVoucher, LoaiGiam, GiaTriGiam, GiaTriToiThieu, NgayBatDau, NgayHetHan, GioiHanDung, GhiChu) 
                               VALUES ($makh, '$code_vc', 'Khách hàng mới', 'phantram', 10, 0, CURDATE(), '$ngay_het_han', 1, 'Voucher dành riêng cho khách hàng mới.')";
                    $conn->query($sql_vc);

                    $_SESSION['success'] = "Thêm khách hàng mới thành công và đã tặng thẻ Voucher!";
                } else {
                    throw new Exception($conn->error);
                }
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Lỗi hệ thống: " . $e->getMessage();
    }
    
    header("Location: khachhang.php");
    exit;
}

require_once 'include/header.php';

$search = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$base_sql = "SELECT kh.*, 
             COUNT(dp.MaDP) AS SoLanDat,
             SUM(CASE WHEN dp.TrangThai IN ('Đã thanh toán','Đã thanh toán (Online)') THEN 1 ELSE 0 END) AS SoLanThanhToan
             FROM KhachHang kh
             LEFT JOIN DatPhong dp ON kh.MaKH = dp.MaKH";
if ($search) {
    $sql = $base_sql . " WHERE kh.HoTen LIKE '%$search%' OR kh.CCCD LIKE '%$search%' OR kh.SDT LIKE '%$search%' GROUP BY kh.MaKH ORDER BY kh.MaKH DESC";
} else {
    $sql = $base_sql . " GROUP BY kh.MaKH ORDER BY kh.MaKH DESC";
}
$result = $conn->query($sql);
?>



<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2 class="fw-bold m-0"><i class="fa-solid fa-users text-primary me-2"></i> Quản lý Khách hàng</h2>
        <div class="d-flex gap-2">
            <a href="khachthanthiet.php" class="btn btn-warning shadow-sm"><i class="fa-solid fa-crown"></i> Khách Thân Thiết</a>
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#khModal" onclick="resetForm()"><i class="fa-solid fa-plus"></i> Thêm khách mới</button>
        </div>
    </div>
</div>

<div class="card p-4 border-0 shadow-sm">
    <div class="row mb-3">
        <div class="col-md-6">
            <form method="GET" class="d-flex">
                <input type="text" name="q" class="form-control me-2 bg-light border-0" placeholder="Tìm tên, CCCD, SĐT..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-secondary px-4"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th class="py-3">Mã KH</th>
                    <th class="py-3">Họ Tên</th>
                    <th class="py-3">CCCD</th>
                    <th class="py-3">Số Điện Thoại</th>
                    <th class="py-3">Email</th>
                    <th class="py-3 text-center">Lần Đặt</th>
                    <th class="py-3 text-center">Hạng</th>
                    <th class="py-3 text-center">Thanh toán</th>
                    <th class="py-3 text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td>KH<?= str_pad($row['MaKH'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td class="fw-bold text-dark"><?= htmlspecialchars($row['HoTen']) ?></td>
                    <td><?= htmlspecialchars($row['CCCD']) ?></td>
                    <td><?= htmlspecialchars($row['SDT']) ?></td>
                    <td><?= htmlspecialchars($row['Email'] ?? '') ?></td>
                    <td class="text-center">
                        <span class="badge bg-secondary"><?= (int)$row['SoLanDat'] ?> lần</span>
                    </td>
                    <td class="text-center">
                        <?php if((int)$row['SoLanThanhToan'] >= 2): ?>
                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-crown me-1"></i>VIP</span>
                        <?php elseif((int)$row['SoLanThanhToan'] == 1): ?>
                            <span class="badge bg-info text-dark">Đang tích lũy</span>
                        <?php else: ?>
                            <span class="badge bg-light text-dark border">Mới</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if($row['BuocThanhToanTruoc']): ?>
                            <span class="badge bg-danger" title="<?= htmlspecialchars($row['LyDoFlag'] ?? '') ?>">
                                <i class="fa-solid fa-lock me-1"></i>Bắt buộc Online
                            </span>
                        <?php else: ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                <i class="fa-solid fa-check me-1"></i>Bình thường
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-warning" onclick='editData(<?= json_encode($row) ?>)' data-bs-toggle="modal" data-bs-target="#khModal"><i class="fa-solid fa-pen"></i></button>
                        <?php if($row['BuocThanhToanTruoc']): ?>
                            <a href="?unflag=<?= $row['MaKH'] ?>" class="btn btn-sm btn-success ms-1" title="Gỡ hạn chế" onclick="return confirm('Gỡ bỏ hạn chế thanh toán cho khách này?')"><i class="fa-solid fa-lock-open"></i></a>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-danger ms-1" title="Đánh dấu không nhận phòng"
                                onclick="showFlagModal(<?= $row['MaKH'] ?>, '<?= htmlspecialchars(addslashes($row['HoTen'])) ?>')">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </button>
                        <?php endif; ?>
                        <a href="?delete=<?= $row['MaKH'] ?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Bạn có chắc muốn xóa khách hàng này?')"><i class="fa-solid fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if($result->num_rows == 0): ?>
                <tr><td colspan="9" class="text-center py-5 text-muted"><i class="fa-solid fa-box-open fs-1 mb-3 d-block"></i>Không tìm thấy khách hàng nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Thêm/Sửa -->
<div class="modal fade" id="khModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title fw-bold" id="modalTitle">Thêm Khách Hàng</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="kh_id">
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Họ Tên (*)</label>
                        <input type="text" name="hoten" id="kh_hoten" class="form-control bg-light border-0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">CCCD (*)</label>
                        <input type="text" name="cccd" id="kh_cccd" class="form-control bg-light border-0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Số Điện Thoại (*)</label>
                        <input type="text" name="sdt" id="kh_sdt" class="form-control bg-light border-0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Email</label>
                        <input type="email" name="email" id="kh_email" class="form-control bg-light border-0">
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary px-4">Lưu dữ liệu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('modalTitle').innerText = 'Thêm Khách Hàng';
    document.getElementById('kh_id').value = '';
    document.getElementById('kh_hoten').value = '';
    document.getElementById('kh_cccd').value = '';
    document.getElementById('kh_sdt').value = '';
    document.getElementById('kh_email').value = '';
}
function editData(data) {
    document.getElementById('modalTitle').innerText = 'Cập Nhật Khách Hàng';
    document.getElementById('kh_id').value = data.MaKH;
    document.getElementById('kh_hoten').value = data.HoTen;
    document.getElementById('kh_cccd').value = data.CCCD;
    document.getElementById('kh_sdt').value = data.SDT;
    document.getElementById('kh_email').value = data.Email;
}
function showFlagModal(id, name) {
    document.getElementById('flag_kh_id').value = id;
    document.getElementById('flag_kh_name').innerText = name;
    new bootstrap.Modal(document.getElementById('flagModal')).show();
}
</script>

<!-- Modal Flag -->
<div class="modal fade" id="flagModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i>Đánh Dấu Không Nhận Phòng</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p>Bạn đang đánh dấu khách hàng <strong id="flag_kh_name"></strong> phải <span class="text-danger fw-bold">thanh toán Online bắt buộc</span> cho các lần đặt tiếp theo.</p>
                <p class="text-muted small">Khách sẽ không thể chọn "Tiền mặt khi check-in" nữa.</p>
                <div class="mb-3">
                    <label class="form-label fw-medium">Lý do <span class="text-danger">*</span></label>
                    <select class="form-select bg-light border-0" id="flag_lydo_select" onchange="toggleCustomReason(this.value)">
                        <option value="Khách từng không nhận phòng">Khách từng không nhận phòng</option>
                        <option value="Khách hủy phòng nhiều lần">Khách hủy phòng nhiều lần</option>
                        <option value="Khách có lịch sử vi phạm">Khách có lịch sử vi phạm</option>
                        <option value="other">Lý do khác...</option>
                    </select>
                </div>
                <div class="mb-3 d-none" id="custom_reason_div">
                    <input type="text" class="form-control bg-light border-0" id="custom_reason" placeholder="Nhập lý do cụ thể...">
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-danger px-4 fw-bold" onclick="submitFlag()">
                    <i class="fa-solid fa-lock me-1"></i>Xác nhận đánh dấu
                </button>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="flag_kh_id">

<script>
function toggleCustomReason(val) {
    const div = document.getElementById('custom_reason_div');
    div.classList.toggle('d-none', val !== 'other');
}
function submitFlag() {
    const id = document.getElementById('flag_kh_id').value;
    const sel = document.getElementById('flag_lydo_select').value;
    const custom = document.getElementById('custom_reason').value.trim();
    const lydo = sel === 'other' ? (custom || 'Lý do khác') : sel;
    window.location.href = `?flag=${id}&lydo=${encodeURIComponent(lydo)}`;
}
</script>

<?php require_once 'include/footer.php'; ?>
