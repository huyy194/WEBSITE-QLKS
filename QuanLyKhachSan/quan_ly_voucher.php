<?php
session_start();
require_once 'config/database.php';
require_once 'include/header.php';

// Chỉ admin mới vào được
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

$success = '';
$error = '';

// Xử lý thêm voucher
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'them_voucher') {
        $code = strtoupper(trim($conn->real_escape_string($_POST['code'])));
        $ten = $conn->real_escape_string(trim($_POST['ten_voucher']));
        $loai = in_array($_POST['loai_giam'], ['phantram','sotien']) ? $_POST['loai_giam'] : 'phantram';
        $gia_tri = (float)$_POST['gia_tri'];
        $toi_thieu = (float)($_POST['gia_tri_toi_thieu'] ?? 0);
        $ngay_bd = !empty($_POST['ngay_bat_dau']) ? "'" . $_POST['ngay_bat_dau'] . "'" : 'NULL';
        $ngay_ht = $conn->real_escape_string($_POST['ngay_het_han']);
        $gioi_han = max(1, (int)($_POST['gioi_han_dung'] ?? 1));
        $ghi_chu = $conn->real_escape_string(trim($_POST['ghi_chu'] ?? ''));
        
        // Chọn khách hàng cụ thể hay tất cả
        $makh_target = !empty($_POST['makh_target']) ? (int)$_POST['makh_target'] : 'NULL';
        
        if (empty($code) || empty($ten) || $gia_tri <= 0 || empty($ngay_ht)) {
            $error = "Vui lòng điền đầy đủ thông tin bắt buộc!";
        } else {
            $check = $conn->query("SELECT MaVC FROM Voucher WHERE Code = '$code'");
            if ($check && $check->num_rows > 0) {
                $error = "Mã voucher '$code' đã tồn tại! Vui lòng chọn mã khác.";
            } else {
                $sql = "INSERT INTO Voucher (MaKH, Code, TenVoucher, LoaiGiam, GiaTriGiam, GiaTriToiThieu, NgayBatDau, NgayHetHan, GioiHanDung, GhiChu)
                        VALUES ($makh_target, '$code', '$ten', '$loai', $gia_tri, $toi_thieu, $ngay_bd, '$ngay_ht', $gioi_han, '$ghi_chu')";
                if ($conn->query($sql)) {
                    $success = "Thêm voucher <strong>$code</strong> thành công!";
                } else {
                    $error = "Lỗi CSDL: " . $conn->error;
                }
            }
        }
    } elseif ($_POST['action'] == 'xoa_voucher') {
        $mavc = (int)$_POST['mavc'];
        if ($conn->query("DELETE FROM Voucher WHERE MaVC = $mavc")) {
            $success = "Đã xóa voucher thành công!";
        } else {
            $error = "Không thể xóa voucher này.";
        }
    } elseif ($_POST['action'] == 'cap_nhat_trang_thai') {
        $mavc = (int)$_POST['mavc'];
        $trangthai = in_array($_POST['trang_thai'], ['active','inactive']) ? $_POST['trang_thai'] : 'inactive';
        $conn->query("UPDATE Voucher SET TrangThai = '$trangthai' WHERE MaVC = $mavc");
        $success = "Đã cập nhật trạng thái voucher!";
    }
}

// Lấy danh sách voucher
$all_vouchers = [];
$v_res = $conn->query("SELECT v.*, kh.HoTen as TenKhachHang 
                        FROM Voucher v 
                        LEFT JOIN KhachHang kh ON v.MaKH = kh.MaKH 
                        ORDER BY v.NgayTao DESC");
if ($v_res) while ($v = $v_res->fetch_assoc()) $all_vouchers[] = $v;

// Danh sách khách hàng để chọn
$all_kh = [];
$kh_res = $conn->query("SELECT kh.MaKH, kh.HoTen, tk.TenDangNhap FROM KhachHang kh LEFT JOIN TaiKhoan tk ON kh.MaTK = tk.MaTK ORDER BY kh.HoTen");
if ($kh_res) while ($kh = $kh_res->fetch_assoc()) $all_kh[] = $kh;

$today = date('Y-m-d');
?>

<!-- Nội dung trang quản lý voucher -->
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-ticket me-2 text-warning"></i>Quản Lý Voucher</h2>
            <p class="text-muted mb-0">Tạo và quản lý mã ưu đãi dành cho khách hàng</p>
        </div>
        <button class="btn btn-warning fw-bold rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addVoucherModal">
            <i class="fa-solid fa-plus me-2"></i>Thêm Voucher Mới
        </button>
    </div>

    <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="fa-solid fa-check-circle me-2"></i><?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <?php
        $total = count($all_vouchers);
        $active = count(array_filter($all_vouchers, fn($v) => $v['TrangThai']=='active' && $v['NgayHetHan'] >= $today));
        $expired = count(array_filter($all_vouchers, fn($v) => $v['NgayHetHan'] < $today));
        $personal = count(array_filter($all_vouchers, fn($v) => !is_null($v['MaKH'])));
        ?>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 text-center">
                <div class="fs-2 fw-bold text-primary"><?= $total ?></div>
                <div class="small text-muted">Tổng Voucher</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 text-center">
                <div class="fs-2 fw-bold text-success"><?= $active ?></div>
                <div class="small text-muted">Đang Hoạt Động</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 text-center">
                <div class="fs-2 fw-bold text-danger"><?= $expired ?></div>
                <div class="small text-muted">Đã Hết Hạn</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 text-center">
                <div class="fs-2 fw-bold text-warning"><?= $personal ?></div>
                <div class="small text-muted">Riêng Tư</div>
            </div>
        </div>
    </div>

    <!-- Danh sách voucher -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-3">
            <h5 class="fw-bold mb-0">Danh Sách Voucher</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Mã Voucher</th>
                        <th>Tên Ưu Đãi</th>
                        <th>Giá Trị Giảm</th>
                        <th>Đối Tượng</th>
                        <th>Ngày Hết Hạn</th>
                        <th>Lượt Dùng</th>
                        <th>Trạng Thái</th>
                        <th class="text-center pe-4">Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($all_vouchers as $vc): 
                        $is_expired = $vc['NgayHetHan'] < $today;
                        $is_active = $vc['TrangThai'] == 'active' && !$is_expired;
                    ?>
                    <tr class="<?= $is_expired ? 'opacity-50' : '' ?>">
                        <td class="ps-4">
                            <span class="badge bg-dark font-monospace fs-6 px-3 py-2"><?= htmlspecialchars($vc['Code']) ?></span>
                        </td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($vc['TenVoucher']) ?></div>
                            <?php if($vc['GhiChu']): ?>
                            <div class="small text-muted"><?= htmlspecialchars(substr($vc['GhiChu'],0,50)) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold text-danger fs-5">
                            <?= $vc['LoaiGiam'] == 'phantram' ? $vc['GiaTriGiam'].'%' : number_format($vc['GiaTriGiam']).'₫' ?>
                        </td>
                        <td>
                            <?php if($vc['MaKH']): ?>
                                <span class="badge bg-warning text-dark"><i class="fa-solid fa-user me-1"></i><?= htmlspecialchars($vc['TenKhachHang'] ?? 'KH#'.$vc['MaKH']) ?></span>
                            <?php else: ?>
                                <span class="badge bg-info text-dark"><i class="fa-solid fa-globe me-1"></i>Tất cả</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($is_expired): ?>
                                <span class="text-danger small"><i class="fa-solid fa-xmark me-1"></i><?= date('d/m/Y', strtotime($vc['NgayHetHan'])) ?></span>
                            <?php else: ?>
                                <span class="small"><i class="fa-regular fa-calendar me-1 text-muted"></i><?= date('d/m/Y', strtotime($vc['NgayHetHan'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="fw-bold"><?= $vc['SoLanDaDung'] ?></span>
                            <span class="text-muted">/ <?= $vc['GioiHanDung'] ?></span>
                        </td>
                        <td>
                            <?php if($is_expired): ?>
                                <span class="badge bg-secondary">Hết hạn</span>
                            <?php elseif($is_active): ?>
                                <span class="badge bg-success">Hoạt động</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Tắt</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center pe-4">
                            <div class="d-flex gap-1 justify-content-center">
                                <?php if(!$is_expired): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="cap_nhat_trang_thai">
                                    <input type="hidden" name="mavc" value="<?= $vc['MaVC'] ?>">
                                    <input type="hidden" name="trang_thai" value="<?= $is_active ? 'inactive' : 'active' ?>">
                                    <button type="submit" class="btn btn-sm <?= $is_active ? 'btn-outline-warning' : 'btn-outline-success' ?> rounded-pill" title="<?= $is_active ? 'Tắt' : 'Bật' ?>">
                                        <i class="fa-solid fa-<?= $is_active ? 'pause' : 'play' ?>"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Xóa voucher này?')">
                                    <input type="hidden" name="action" value="xoa_voucher">
                                    <input type="hidden" name="mavc" value="<?= $vc['MaVC'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" title="Xóa">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(count($all_vouchers) == 0): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-ticket-simple fs-1 d-block mb-3 opacity-25"></i>
                            Chưa có voucher nào. Hãy tạo voucher đầu tiên!
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Thêm Voucher -->
<div class="modal fade" id="addVoucherModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <form class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" method="POST">
      <input type="hidden" name="action" value="them_voucher">
      <div class="modal-header border-0 py-4" style="background: linear-gradient(135deg,#1e3a5f,#0f2444);">
        <h5 class="modal-title text-white fw-bold mx-auto">
            <i class="fa-solid fa-plus-circle me-2 text-warning"></i>Tạo Voucher Mới
        </h5>
        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-bold">Mã Voucher <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="text" name="code" id="vcCode" class="form-control rounded-start-3 fw-bold font-monospace" 
                           placeholder="VD: KHOTEL20" maxlength="50" style="text-transform:uppercase;" required>
                    <button type="button" class="btn btn-outline-secondary rounded-end-3" onclick="generateCode()" title="Tạo mã ngẫu nhiên">
                        <i class="fa-solid fa-dice"></i>
                    </button>
                </div>
                <div class="form-text">Chữ hoa, không dấu, không khoảng trắng</div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Tên Voucher <span class="text-danger">*</span></label>
                <input type="text" name="ten_voucher" class="form-control rounded-3" placeholder="VD: Ưu đãi thành viên thân thiết" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Loại Giảm <span class="text-danger">*</span></label>
                <select name="loai_giam" id="loaiGiam" class="form-select rounded-3" onchange="updateUnit()">
                    <option value="phantram">Phần trăm (%)</option>
                    <option value="sotien">Số tiền (₫)</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Giá Trị Giảm <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" name="gia_tri" id="giaTriInput" class="form-control rounded-start-3" 
                           min="1" max="100" step="1" placeholder="10" required>
                    <span class="input-group-text rounded-end-3" id="unitLabel">%</span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Đơn Tối Thiểu (₫)</label>
                <input type="number" name="gia_tri_toi_thieu" class="form-control rounded-3" min="0" step="10000" placeholder="0">
                <div class="form-text">0 = không giới hạn</div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Ngày Bắt Đầu</label>
                <input type="date" name="ngay_bat_dau" class="form-control rounded-3" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Ngày Hết Hạn <span class="text-danger">*</span></label>
                <input type="date" name="ngay_het_han" class="form-control rounded-3" min="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Giới Hạn Sử Dụng</label>
                <input type="number" name="gioi_han_dung" class="form-control rounded-3" min="1" value="1" max="9999">
                <div class="form-text">Số lần tối đa được sử dụng</div>
            </div>
            <div class="col-12">
                <label class="form-label fw-bold">Dành Cho Khách Hàng</label>
                <select name="makh_target" class="form-select rounded-3">
                    <option value="">-- Tất cả khách hàng (Voucher công khai) --</option>
                    <?php foreach($all_kh as $kh): ?>
                    <option value="<?= $kh['MaKH'] ?>"><?= htmlspecialchars($kh['HoTen']) ?> (<?= htmlspecialchars($kh['TenDangNhap'] ?? '#'.$kh['MaKH']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text"><i class="fa-solid fa-info-circle me-1 text-primary"></i>Để trống = voucher công khai cho tất cả khách đăng nhập</div>
            </div>
            <div class="col-12">
                <label class="form-label fw-bold">Ghi Chú</label>
                <textarea name="ghi_chu" class="form-control rounded-3" rows="2" placeholder="Điều kiện sử dụng, ghi chú thêm..."></textarea>
            </div>
        </div>
      </div>
      <div class="modal-footer border-0 p-4 bg-light">
        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-warning fw-bold rounded-pill px-5 shadow-sm">
            <i class="fa-solid fa-plus me-2"></i>Tạo Voucher
        </button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateUnit() {
    const loai = document.getElementById('loaiGiam').value;
    const unit = document.getElementById('unitLabel');
    const input = document.getElementById('giaTriInput');
    if (loai === 'phantram') {
        unit.textContent = '%';
        input.max = 100;
        input.step = 1;
    } else {
        unit.textContent = '₫';
        input.max = 99999999;
        input.step = 10000;
    }
}
function generateCode() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let code = 'KHOTEL';
    for (let i = 0; i < 6; i++) code += chars[Math.floor(Math.random() * chars.length)];
    document.getElementById('vcCode').value = code;
}
document.getElementById('vcCode').addEventListener('input', function(){
    this.value = this.value.toUpperCase().replace(/\s/g,'');
});
</script>

<?php require_once 'include/footer.php'; ?>
