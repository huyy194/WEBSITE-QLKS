<?php
require_once 'config/database.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'nhanvien'])) {
    header("Location: dashboard.php");
    exit;
}

// Xóa đánh giá
if (isset($_GET['delete']) && $_SESSION['role'] == 'admin') {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM DanhGia WHERE MaDG = $id");
    header("Location: quan_ly_danhgia.php?deleted=1");
    exit;
}

// Phản hồi đánh giá (lưu vào bảng GhiChu bổ sung qua cột PhanHoi nếu có, hoặc dùng session flash)
$reply_success = isset($_GET['deleted']) ? 'Đã xóa đánh giá!' : '';

// Bộ lọc
$filter_sao   = isset($_GET['sao'])  ? (int)$_GET['sao']  : 0;
$filter_loai  = isset($_GET['loai']) ? (int)$_GET['loai'] : 0;
$filter_kw    = isset($_GET['kw'])   ? $conn->real_escape_string(trim($_GET['kw'])) : '';

$where = "WHERE 1=1";
if ($filter_sao > 0)  $where .= " AND dg.SoSao = $filter_sao";
if ($filter_loai > 0) $where .= " AND dg.MaLoai = $filter_loai";
if ($filter_kw)       $where .= " AND (kh.HoTen LIKE '%$filter_kw%' OR dg.NhanXet LIKE '%$filter_kw%')";

$dg_res = $conn->query("
    SELECT dg.*, kh.HoTen, lp.TenLoai
    FROM DanhGia dg
    JOIN KhachHang kh ON dg.MaKH = kh.MaKH
    JOIN LoaiPhong lp ON dg.MaLoai = lp.MaLoai
    $where
    ORDER BY dg.NgayDanhGia DESC
    LIMIT 200
");
$all_dg = [];
if ($dg_res) while ($r = $dg_res->fetch_assoc()) $all_dg[] = $r;

// Thống kê tổng
$stats_res = $conn->query("SELECT SoSao, COUNT(*) as cnt FROM DanhGia GROUP BY SoSao ORDER BY SoSao DESC");
$stats = [5=>0,4=>0,3=>0,2=>0,1=>0];
$total_reviews = 0;
$avg_star = 0;
if ($stats_res) {
    while ($s = $stats_res->fetch_assoc()) {
        $stats[$s['SoSao']] = (int)$s['cnt'];
        $total_reviews += $s['cnt'];
        $avg_star += $s['SoSao'] * $s['cnt'];
    }
}
$avg_star = $total_reviews > 0 ? round($avg_star / $total_reviews, 1) : 0;

// Danh sách loại phòng
$loai_res = $conn->query("SELECT MaLoai, TenLoai FROM LoaiPhong ORDER BY TenLoai");
$all_loai = [];
if ($loai_res) while ($r = $loai_res->fetch_assoc()) $all_loai[] = $r;

require_once 'include/header.php';
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2 class="fw-bold m-0"><i class="fa-solid fa-star text-warning me-2"></i> Quản lý Đánh Giá</h2>
        <span class="badge bg-secondary fs-6"><?= $total_reviews ?> đánh giá</span>
    </div>
</div>

<?php if ($reply_success): ?>
<div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-3">
    <i class="fa-solid fa-check-circle me-2"></i><?= $reply_success ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Thống kê tổng quan -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-3 text-center h-100">
            <div class="display-5 fw-bold text-warning mb-1">
                <?= $avg_star ?><span class="fs-4">★</span>
            </div>
            <div class="small text-muted">Điểm trung bình</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-3 text-center h-100">
            <div class="display-5 fw-bold text-primary mb-1"><?= $total_reviews ?></div>
            <div class="small text-muted">Tổng đánh giá</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-3 text-center h-100">
            <div class="display-5 fw-bold text-success mb-1"><?= ($stats[5] + $stats[4]) ?></div>
            <div class="small text-muted">Đánh giá tốt (4-5★)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-3 text-center h-100">
            <div class="display-5 fw-bold text-danger mb-1"><?= ($stats[1] + $stats[2]) ?></div>
            <div class="small text-muted">Đánh giá thấp (1-2★)</div>
        </div>
    </div>
</div>

<!-- Phân bổ sao -->
<div class="card border-0 shadow-sm p-4 mb-4">
    <h6 class="fw-bold mb-3"><i class="fa-solid fa-chart-bar me-2 text-primary"></i>Phân Bổ Đánh Giá</h6>
    <?php foreach([5,4,3,2,1] as $s): 
        $pct = $total_reviews > 0 ? round($stats[$s]/$total_reviews*100) : 0;
        $color = $s >= 4 ? 'success' : ($s == 3 ? 'warning' : 'danger');
    ?>
    <div class="d-flex align-items-center gap-3 mb-2">
        <div style="width:60px; text-align:right; font-size:13px;">
            <?= $s ?> <i class="fa-solid fa-star text-warning" style="font-size:11px;"></i>
        </div>
        <div class="flex-grow-1">
            <div class="progress" style="height:8px; border-radius:10px;">
                <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%; border-radius:10px;"></div>
            </div>
        </div>
        <div style="width:60px; font-size:13px;" class="text-muted"><?= $stats[$s] ?> (<?= $pct ?>%)</div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Bộ lọc -->
<div class="card border-0 shadow-sm p-3 mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small fw-medium text-muted">Tìm kiếm</label>
            <input type="text" name="kw" class="form-control form-control-sm bg-light border-0" 
                   placeholder="Tên khách, nội dung..." value="<?= htmlspecialchars($filter_kw) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-medium text-muted">Số sao</label>
            <select name="sao" class="form-select form-select-sm bg-light border-0">
                <option value="0">Tất cả</option>
                <?php for($i=5;$i>=1;$i--): ?>
                <option value="<?=$i?>" <?=$filter_sao==$i?'selected':''?>><?=$i?> sao ★</option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-medium text-muted">Loại phòng</label>
            <select name="loai" class="form-select form-select-sm bg-light border-0">
                <option value="0">Tất cả loại phòng</option>
                <?php foreach($all_loai as $l): ?>
                <option value="<?=$l['MaLoai']?>" <?=$filter_loai==$l['MaLoai']?'selected':''?>><?= htmlspecialchars($l['TenLoai']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="fa-solid fa-search"></i> Lọc</button>
            <a href="quan_ly_danhgia.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-xmark"></i></a>
        </div>
    </form>
</div>

<!-- Danh sách đánh giá -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (count($all_dg) == 0): ?>
        <div class="text-center py-5 text-muted">
            <i class="fa-solid fa-star-half-stroke fs-1 d-block mb-3 opacity-25"></i>
            Không tìm thấy đánh giá nào.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">#</th>
                        <th>Khách Hàng</th>
                        <th>Loại Phòng</th>
                        <th>Đánh Giá</th>
                        <th>Nội Dung</th>
                        <th>Ngày</th>
                        <?php if($_SESSION['role'] == 'admin'): ?><th class="text-center pe-4">Xóa</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($all_dg as $i => $dg):
                        $stars = $dg['SoSao'];
                        $star_color = $stars >= 4 ? 'text-success' : ($stars == 3 ? 'text-warning' : 'text-danger');
                    ?>
                    <tr>
                        <td class="ps-4 text-muted small"><?= $i+1 ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-bold" 
                                     style="width:34px;height:34px;font-size:13px;flex-shrink:0;">
                                    <?= strtoupper(mb_substr($dg['HoTen'], 0, 1)) ?>
                                </div>
                                <span class="fw-bold text-dark small"><?= htmlspecialchars($dg['HoTen']) ?></span>
                            </div>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($dg['TenLoai']) ?></span></td>
                        <td>
                            <div class="d-flex align-items-center gap-1 <?= $star_color ?> fw-bold">
                                <?php for($s=1;$s<=5;$s++): ?>
                                    <i class="fa-solid fa-star" style="font-size:13px; opacity:<?= $s<=$stars?'1':'0.2' ?>;"></i>
                                <?php endfor; ?>
                                <span class="ms-1 text-dark"><?= $stars ?>/5</span>
                            </div>
                        </td>
                        <td style="max-width:250px;">
                            <?php if($dg['NhanXet']): ?>
                            <span class="text-dark small" title="<?= htmlspecialchars($dg['NhanXet']) ?>">
                                <?= htmlspecialchars(mb_substr($dg['NhanXet'], 0, 80)) ?><?= mb_strlen($dg['NhanXet']) > 80 ? '...' : '' ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted fst-italic small">Không có nhận xét</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($dg['NgayDanhGia'])) ?></td>
                        <?php if($_SESSION['role'] == 'admin'): ?>
                        <td class="text-center pe-4">
                            <a href="?delete=<?= $dg['MaDG'] ?>" class="btn btn-sm btn-outline-danger rounded-pill"
                               onclick="return confirm('Xóa đánh giá này?')">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php if($dg['NhanXet'] && mb_strlen($dg['NhanXet']) > 80): ?>
                    <tr class="bg-light border-0">
                        <td colspan="<?= $_SESSION['role'] == 'admin' ? 7 : 6 ?>" class="py-1 ps-5 pe-4">
                            <small class="text-muted fst-italic">"<?= htmlspecialchars($dg['NhanXet']) ?>"</small>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'include/footer.php'; ?>
