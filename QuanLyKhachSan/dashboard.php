<?php
require_once 'config/database.php';
require_once 'include/header.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

// ====== THỐNG KÊ CƠ BẢN ======
$doanhthu_thang = $conn->query("SELECT SUM(TongTien) as Tong FROM HoaDon WHERE MONTH(NgayLanhToan)=MONTH(CURDATE()) AND YEAR(NgayLanhToan)=YEAR(CURDATE())")->fetch_assoc()['Tong'] ?? 0;
$doanhthu_hom_nay = $conn->query("SELECT SUM(TongTien) as Tong FROM HoaDon WHERE DATE(NgayLanhToan)=CURDATE()")->fetch_assoc()['Tong'] ?? 0;
$doanhthu_thang_truoc = $conn->query("SELECT SUM(TongTien) as Tong FROM HoaDon WHERE MONTH(NgayLanhToan)=MONTH(CURDATE()-INTERVAL 1 MONTH) AND YEAR(NgayLanhToan)=YEAR(CURDATE()-INTERVAL 1 MONTH)")->fetch_assoc()['Tong'] ?? 1;
$growth = $doanhthu_thang_truoc > 0 ? round((($doanhthu_thang - $doanhthu_thang_truoc) / $doanhthu_thang_truoc) * 100, 1) : 0;

$phong_stats = [];
$r = $conn->query("SELECT TrangThai, COUNT(*) as c FROM Phong GROUP BY TrangThai");
while($row = $r->fetch_assoc()) $phong_stats[$row['TrangThai']] = $row['c'];
$tong_phong   = array_sum($phong_stats);
$phong_trong  = $phong_stats['Trống'] ?? 0;
$phong_dang_o = $phong_stats['Đang ở'] ?? 0;
$phong_don_dep= $phong_stats['Đang dọn dẹp'] ?? 0;
$occupancy    = $tong_phong > 0 ? round(($phong_dang_o / $tong_phong) * 100) : 0;

$tong_kh    = $conn->query("SELECT COUNT(*) as c FROM KhachHang")->fetch_assoc()['c'] ?? 0;
$kh_vip     = $conn->query("SELECT COUNT(*) as cnt FROM (SELECT MaKH FROM DatPhong WHERE TrangThai IN ('\u0110\u00e3 thanh to\u00e1n','\u0110\u00e3 thanh to\u00e1n (Online)') GROUP BY MaKH HAVING COUNT(*)>=2) t")->fetch_assoc()['cnt'] ?? 0;
$don_cho    = $conn->query("SELECT COUNT(*) as c FROM DatPhong WHERE TrangThai='Ch\u1edd x\u00e1c nh\u1eadn'")->fetch_assoc()['c'] ?? 0;
$don_hom_nay= $conn->query("SELECT COUNT(*) as c FROM DatPhong WHERE DATE(NgayCheckIn)=CURDATE()")->fetch_assoc()['c'] ?? 0;
$don_huy_thang=$conn->query("SELECT COUNT(*) as c FROM DatPhong WHERE TrangThai='\u0110\u00e3 hu\u1ef7' AND MONTH(NgayCheckIn)=MONTH(CURDATE()) AND YEAR(NgayCheckIn)=YEAR(CURDATE())")->fetch_assoc()['c'] ?? 0;
$don_dat_thang=$conn->query("SELECT COUNT(*) as c FROM DatPhong WHERE MONTH(NgayCheckIn)=MONTH(CURDATE()) AND YEAR(NgayCheckIn)=YEAR(CURDATE())")->fetch_assoc()['c'] ?? 0;
$no_show=$conn->query("SELECT COUNT(*) as c FROM DatPhong WHERE TrangThai='Ch\u1edd x\u00e1c nh\u1eadn' AND NgayCheckIn < NOW()")->fetch_assoc()['c'] ?? 0;
// Phương thức thanh toán chủ yếu
$pt_res = $conn->query("SELECT NguonDat, COUNT(*) as c FROM DatPhong WHERE TrangThai IN ('Đã thanh toán','Đã thanh toán (Online)') GROUP BY NguonDat ORDER BY c DESC LIMIT 1");
$pt_row = $pt_res ? $pt_res->fetch_assoc() : null;
$pt_map = ['TaiQuay' => 'Tại Quầy', 'BanOnline' => 'Trực Tuyến'];
$phuong_thuc_chu_yeu = $pt_row ? ($pt_map[$pt_row['NguonDat']] ?? $pt_row['NguonDat']) : 'Chưa có';
$growth = ($doanhthu_thang_truoc > 1000) ? round((($doanhthu_thang - $doanhthu_thang_truoc) / $doanhthu_thang_truoc) * 100, 1) : 0;

// ====== BIỂU ĐỒ 30 NGÀY ======
$chart30_labels = []; $chart30_data = [];
$q30 = $conn->query("SELECT DATE(NgayLanhToan) as Ngay, SUM(TongTien) as DT FROM HoaDon WHERE NgayLanhToan >= DATE_SUB(CURDATE(),INTERVAL 29 DAY) GROUP BY DATE(NgayLanhToan) ORDER BY Ngay ASC");
$map30 = [];
while($r30 = $q30->fetch_assoc()) $map30[$r30['Ngay']] = $r30['DT'];
for($i=29;$i>=0;$i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart30_labels[] = date('d/m', strtotime($d));
    $chart30_data[]   = $map30[$d] ?? 0;
}

// ====== BIỂU ĐỒ DOANH THU 12 THÁNG ======
$chart12_labels=[]; $chart12_data=[]; $chart12_orders=[];
$yr = date('Y');
$q12=$conn->query("SELECT MONTH(NgayLanhToan) as M, SUM(TongTien) as DT, COUNT(*) as SD FROM HoaDon WHERE YEAR(NgayLanhToan)=$yr GROUP BY MONTH(NgayLanhToan)");
$map12=[]; $mapSD12=[];
if($q12) while($r12=$q12->fetch_assoc()){$map12[(int)$r12['M']]=$r12['DT']; $mapSD12[(int)$r12['M']]=$r12['SD'];}
$mn_vi=['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'];
for($m=1;$m<=12;$m++){$chart12_labels[]=$mn_vi[$m-1]; $chart12_data[]=$map12[$m]??0; $chart12_orders[]=$mapSD12[$m]??0;}
$doanhthu_nam=array_sum($chart12_data);

// ====== BIỂU ĐỒ PHÒNG (Donut) ======
$donut_labels = ['Trống','Đang ở','Đang dọn dẹp'];
$donut_data   = [$phong_trong, $phong_dang_o, $phong_don_dep];

// ====== TOP PHÒNG DOANH THU ======
$top_phong = [];
$tp = $conn->query("SELECT p.MaPhong, lp.TenLoai, COUNT(dp.MaDP) as SoDon, SUM(hd.TongTien) as DoanhThu FROM DatPhong dp JOIN Phong p ON dp.MaPhong=p.MaPhong JOIN LoaiPhong lp ON p.MaLoai=lp.MaLoai JOIN HoaDon hd ON dp.MaDP=hd.MaDP GROUP BY p.MaPhong, lp.TenLoai ORDER BY DoanhThu DESC LIMIT 5");
if($tp) while($r=$tp->fetch_assoc()) $top_phong[]=$r;

// ====== BOOKING GẦN ĐÂY ======
$recent = $conn->query("SELECT dp.MaDP, dp.TrangThai, kh.HoTen, p.MaPhong, lp.TenLoai, dp.NgayCheckIn, dp.NgayCheckOut FROM DatPhong dp JOIN KhachHang kh ON dp.MaKH=kh.MaKH JOIN Phong p ON dp.MaPhong=p.MaPhong JOIN LoaiPhong lp ON p.MaLoai=lp.MaLoai ORDER BY dp.MaDP DESC LIMIT 8");

// ====== ĐÁNH GIÁ GẦN ĐÂY ======
$reviews = $conn->query("SELECT dg.SoSao, dg.NhanXet, dg.NgayDanhGia, kh.HoTen, lp.TenLoai FROM DanhGia dg JOIN KhachHang kh ON dg.MaKH=kh.MaKH JOIN LoaiPhong lp ON dg.MaLoai=lp.MaLoai ORDER BY dg.NgayDanhGia DESC LIMIT 4");
$avg_star = $conn->query("SELECT ROUND(AVG(SoSao),1) as Avg FROM DanhGia")->fetch_assoc()['Avg'] ?? 0;
?>

<style>
.stat-card { border-radius: 16px; padding: 24px; border: none; position: relative; overflow: hidden; transition: transform .2s, box-shadow .2s; }
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(0,0,0,.15)!important; }
.stat-card .icon-wrap { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
.stat-card .badge-trend { font-size: 0.72rem; padding: 3px 8px; border-radius: 20px; font-weight: 600; }
.stat-card .bg-wave { position: absolute; right: -20px; bottom: -20px; opacity: .07; font-size: 7rem; }
.chart-card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
.booking-row { padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
.booking-row:last-child { border-bottom: none; }
.status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
.occupancy-bar { height: 8px; border-radius: 4px; background: #e2e8f0; overflow: hidden; }
.occupancy-fill { height: 100%; border-radius: 4px; transition: width .8s ease; }
.review-star { color: #f59e0b; font-size: 0.8rem; }
.section-title { font-size: 0.95rem; font-weight: 700; color: #1e293b; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.section-title .title-line { width: 4px; height: 18px; border-radius: 2px; }
</style>

<div class="container-fluid px-4 pb-5">

<!-- PAGE HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4 pt-2">
    <div>
        <h2 class="fw-bold mb-1" style="color:#1e293b;">Tổng Quan Hệ Thống</h2>
        <p class="text-muted mb-0 small"><i class="fa-regular fa-clock me-1"></i>Cập nhật lúc <?= date('H:i, d/m/Y') ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="xacnhan.php" class="btn btn-sm btn-warning fw-bold rounded-pill px-3 position-relative">
            <i class="fa-solid fa-bell me-1"></i>Đơn Chờ Duyệt
            <?php if($don_cho > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.65rem;"><?= $don_cho ?></span>
            <?php endif; ?>
        </a>
        <a href="sodophong.php" class="btn btn-sm btn-primary fw-bold rounded-pill px-3">
            <i class="fa-solid fa-bed me-1"></i>Sơ Đồ Phòng
        </a>
    </div>
</div>

<!-- ===== KPI CARDS ROW ===== -->
<div class="row g-3 mb-4">
    <!-- Doanh thu hôm nay -->
    <div class="col-6 col-xl-3">
        <div class="stat-card shadow-sm" style="background:linear-gradient(135deg,#3b6fd4,#5b8dee);color:white;">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="icon-wrap" style="background:rgba(255,255,255,.2);"><i class="fa-solid fa-coins"></i></div>
                <span class="badge-trend" style="background:rgba(255,255,255,.2);">
                    <?= $growth >= 0 ? '▲' : '▼' ?> <?= abs($growth) ?>%
                </span>
            </div>
            <div class="fs-4 fw-black mb-1"><?= number_format($doanhthu_thang) ?>₫</div>
            <div style="font-size:.82rem;opacity:.8;">Doanh Thu Tháng <?= date('m') ?></div>
            <div style="font-size:.75rem;opacity:.65;margin-top:4px;">Hôm nay: <?= number_format($doanhthu_hom_nay) ?>₫</div>
            <i class="fa-solid fa-chart-line bg-wave"></i>
        </div>
    </div>

    <!-- Công suất phòng -->
    <div class="col-6 col-xl-3">
        <div class="stat-card shadow-sm" style="background:linear-gradient(135deg,#059669,#10b981);color:white;">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="icon-wrap" style="background:rgba(255,255,255,.2);"><i class="fa-solid fa-door-open"></i></div>
                <span class="badge-trend" style="background:rgba(255,255,255,.2);"><?= $occupancy ?>% CS</span>
            </div>
            <div class="fs-4 fw-black mb-1"><?= $phong_trong ?> / <?= $tong_phong ?></div>
            <div style="font-size:.82rem;opacity:.8;">Phòng Còn Trống</div>
            <div style="font-size:.75rem;opacity:.65;margin-top:4px;"><?= $phong_dang_o ?> đang có khách · <?= $phong_don_dep ?> dọn dẹp</div>
            <i class="fa-solid fa-hotel bg-wave"></i>
        </div>
    </div>

    <!-- Khách hàng -->
    <div class="col-6 col-xl-3">
        <div class="stat-card shadow-sm" style="background:linear-gradient(135deg,#7c3aed,#a78bfa);color:white;">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="icon-wrap" style="background:rgba(255,255,255,.2);"><i class="fa-solid fa-users"></i></div>
                <span class="badge-trend" style="background:rgba(255,255,255,.2);"><i class="fa-solid fa-crown" style="font-size:.7rem;"></i> <?= $kh_vip ?> VIP</span>
            </div>
            <div class="fs-4 fw-black mb-1"><?= $tong_kh ?></div>
            <div style="font-size:.82rem;opacity:.8;">Tổng Khách Hàng</div>
            <div style="font-size:.75rem;opacity:.65;margin-top:4px;">Check-in hôm nay: <?= $don_hom_nay ?> đơn</div>
            <i class="fa-solid fa-people-group bg-wave"></i>
        </div>
    </div>

    <!-- Đánh giá -->
    <div class="col-6 col-xl-3">
        <div class="stat-card shadow-sm" style="background:linear-gradient(135deg,#d97706,#f59e0b);color:white;">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="icon-wrap" style="background:rgba(255,255,255,.2);"><i class="fa-solid fa-star"></i></div>
                <span class="badge-trend" style="background:rgba(255,255,255,.2);">/ 5 ⭐</span>
            </div>
            <div class="fs-4 fw-black mb-1"><?= $avg_star ?: '–' ?></div>
            <div style="font-size:.82rem;opacity:.8;">Đánh Giá Trung Bình</div>
            <div style="font-size:.75rem;opacity:.65;margin-top:4px;"><?= $don_cho ?> đơn chờ · <?= $don_huy_thang ?> huỷ tháng này</div>
            <i class="fa-solid fa-star bg-wave"></i>
        </div>
    </div>
</div>

<!-- ===== MINI STATS ROW 2 ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="chart-card text-center">
            <div class="fw-bold text-primary fs-3"><?= $don_dat_thang ?></div>
            <div class="small text-muted mt-1"><i class="fa-solid fa-calendar-check me-1"></i>Lượt đặt tháng này</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="chart-card text-center">
            <div class="fw-bold text-danger fs-3"><?= $don_huy_thang ?></div>
            <div class="small text-muted mt-1"><i class="fa-solid fa-ban me-1"></i>Lượt huỷ tháng này</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="chart-card text-center">
            <div class="fw-bold text-warning fs-3"><?= $no_show ?></div>
            <div class="small text-muted mt-1"><i class="fa-solid fa-user-slash me-1"></i>Không nhận phòng</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="chart-card text-center">
            <div class="fw-bold text-success" style="font-size:1.1rem;"><?= $phuong_thuc_chu_yeu ?></div>
            <div class="small text-muted mt-1"><i class="fa-solid fa-credit-card me-1"></i>Thanh toán chủ yếu</div>
        </div>
    </div>
</div>

<!-- ===== DOANH THU NĂM + THÁNG ===== -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="chart-card">
            <div class="section-title">
                <div class="title-line" style="background:#d97706;"></div>
                Doanh Thu Theo Tháng — Năm <?= $yr ?>
                <span class="badge bg-light text-dark fw-normal ms-auto">Tổng năm: <strong><?= number_format($doanhthu_nam) ?>₫</strong></span>
            </div>
            <div style="height:220px;"><canvas id="chart12"></canvas></div>
        </div>
    </div>
</div>

<!-- ===== OCCUPANCY + MINI STATS ===== -->
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="chart-card h-100">
            <div class="section-title">
                <div class="title-line" style="background:#3b6fd4;"></div>
                Biểu Đồ Doanh Thu 30 Ngày Qua
                <span class="badge bg-light text-muted fw-normal ms-auto">VNĐ</span>
            </div>
            <div style="height:280px;"><canvas id="chart30"></canvas></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-card h-100 d-flex flex-column">
            <div class="section-title">
                <div class="title-line" style="background:#059669;"></div>
                Tình Trạng Phòng
            </div>
            <div style="height:180px;" class="d-flex align-items-center justify-content-center">
                <canvas id="donutChart"></canvas>
            </div>
            <div class="mt-3 d-flex flex-column gap-2">
                <?php
                $donut_colors = ['#22c55e','#3b6fd4','#f59e0b'];
                $donut_labels_vi = ['Trống','Đang có khách','Đang dọn'];
                for($i=0;$i<3;$i++): ?>
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="status-dot" style="background:<?= $donut_colors[$i] ?>;"></span>
                        <span class="small text-muted"><?= $donut_labels_vi[$i] ?></span>
                    </div>
                    <span class="fw-bold small"><?= $donut_data[$i] ?> phòng</span>
                </div>
                <?php endfor; ?>
                <div class="mt-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Công suất</span>
                        <span class="fw-bold text-primary"><?= $occupancy ?>%</span>
                    </div>
                    <div class="occupancy-bar">
                        <div class="occupancy-fill" style="width:<?= $occupancy ?>%;background:linear-gradient(90deg,#3b6fd4,#5b8dee);"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== RECENT BOOKINGS + TOP ROOMS ===== -->
<div class="row g-3 mb-4">
    <!-- Booking gần đây -->
    <div class="col-xl-7">
        <div class="chart-card">
            <div class="section-title">
                <div class="title-line" style="background:#7c3aed;"></div>
                Đơn Đặt Phòng Gần Đây
                <a href="hoadon.php" class="btn btn-sm btn-outline-primary rounded-pill ms-auto px-3" style="font-size:.75rem;">Xem tất cả</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:.85rem;">
                    <thead>
                        <tr style="font-size:.72rem;text-transform:uppercase;color:#94a3b8;">
                            <th class="border-0 pb-3">Mã Đơn</th>
                            <th class="border-0 pb-3">Khách Hàng</th>
                            <th class="border-0 pb-3">Phòng</th>
                            <th class="border-0 pb-3">Check-in</th>
                            <th class="border-0 pb-3">Trạng Thái</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($row = $recent->fetch_assoc()):
                        $ts = $row['TrangThai'];
                        $dotColor = '#94a3b8';
                        $badgeStyle = 'background:#f1f5f9;color:#64748b;';
                        if($ts=='Đang ở') { $dotColor='#22c55e'; $badgeStyle='background:#dcfce7;color:#166534;'; }
                        elseif($ts=='Chờ xác nhận') { $dotColor='#f59e0b'; $badgeStyle='background:#fef9c3;color:#92400e;'; }
                        elseif(str_starts_with($ts,'Đã thanh toán')) { $dotColor='#3b6fd4'; $badgeStyle='background:#dbeafe;color:#1e40af;'; }
                        elseif($ts=='Đã huỷ') { $dotColor='#ef4444'; $badgeStyle='background:#fee2e2;color:#991b1b;'; }
                    ?>
                    <tr>
                        <td class="fw-bold text-primary">#<?= $row['MaDP'] ?></td>
                        <td><?= htmlspecialchars($row['HoTen']) ?></td>
                        <td><span class="fw-bold"><?= $row['MaPhong'] ?></span> <span class="text-muted small d-none d-md-inline">· <?= htmlspecialchars($row['TenLoai']) ?></span></td>
                        <td class="text-muted"><?= date('d/m/Y', strtotime($row['NgayCheckIn'])) ?></td>
                        <td>
                            <span style="<?= $badgeStyle ?>padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600;">
                                <span class="status-dot me-1" style="background:<?= $dotColor ?>;"></span><?= $ts ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Top phòng + Đánh giá -->
    <div class="col-xl-5 d-flex flex-column gap-3">
        <!-- Top phòng -->
        <div class="chart-card">
            <div class="section-title">
                <div class="title-line" style="background:#d97706;"></div>
                Top Phòng Doanh Thu Cao
            </div>
            <?php if(count($top_phong) > 0):
                $max_dt = max(array_column($top_phong,'DoanhThu')) ?: 1;
                foreach($top_phong as $i => $tp): ?>
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-bold"><?= $tp['MaPhong'] ?> · <?= htmlspecialchars($tp['TenLoai']) ?></span>
                    <span class="small text-muted"><?= number_format($tp['DoanhThu']) ?>₫</span>
                </div>
                <div class="occupancy-bar">
                    <div class="occupancy-fill" style="width:<?= round(($tp['DoanhThu']/$max_dt)*100) ?>%;background:<?= ['#3b6fd4','#059669','#7c3aed','#d97706','#ef4444'][$i] ?>;"></div>
                </div>
            </div>
            <?php endforeach; else: ?>
            <p class="text-muted small text-center py-2">Chưa có dữ liệu thanh toán</p>
            <?php endif; ?>
        </div>

        <!-- Đánh giá gần đây -->
        <div class="chart-card flex-grow-1">
            <div class="section-title">
                <div class="title-line" style="background:#f59e0b;"></div>
                Đánh Giá Gần Đây
                <span class="ms-auto fw-bold text-warning">⭐ <?= $avg_star ?: '–' ?>/5</span>
            </div>
            <?php if($reviews && $reviews->num_rows > 0):
                while($rv = $reviews->fetch_assoc()): ?>
            <div class="booking-row">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-bold small"><?= htmlspecialchars($rv['HoTen']) ?></div>
                        <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($rv['TenLoai']) ?></div>
                    </div>
                    <div class="review-star">
                        <?php for($s=1;$s<=5;$s++) echo $s<=$rv['SoSao'] ? '★' : '☆'; ?>
                    </div>
                </div>
                <?php if($rv['NhanXet']): ?>
                <p class="text-muted small mb-0 mt-1" style="line-height:1.4;">"<?= htmlspecialchars(mb_substr($rv['NhanXet'],0,70)) ?>..."</p>
                <?php endif; ?>
            </div>
            <?php endwhile; else: ?>
            <p class="text-muted small text-center py-3">Chưa có đánh giá nào</p>
            <?php endif; ?>
        </div>
    </div>
</div>

</div><!-- end container -->

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartOpts = { responsive:true, maintainAspectRatio:false, interaction:{mode:'index',intersect:false} };
    const tooltipDark = { backgroundColor:'#1e293b', padding:12, callbacks:{ label: c => ' '+c.parsed.y.toLocaleString('vi-VN')+' ₫' }};

    // ---- 30-day line chart ----
    const ctx30 = document.getElementById('chart30').getContext('2d');
    const grad30 = ctx30.createLinearGradient(0,0,0,280);
    grad30.addColorStop(0,'rgba(59,111,212,0.25)'); grad30.addColorStop(1,'rgba(59,111,212,0)');
    new Chart(ctx30, { type:'line', data:{ labels:<?= json_encode($chart30_labels) ?>, datasets:[{ label:'Doanh Thu', data:<?= json_encode($chart30_data) ?>, borderColor:'#3b6fd4', borderWidth:2.5, backgroundColor:grad30, fill:true, tension:0.4, pointRadius:0, pointHoverRadius:5, pointHoverBackgroundColor:'#3b6fd4' }] }, options:{...chartOpts, scales:{ y:{ beginAtZero:true, grid:{color:'#f1f5f9',drawBorder:false}, ticks:{callback:v=>(v/1000000).toFixed(1)+'M₫',font:{size:11}} }, x:{ grid:{display:false}, ticks:{maxTicksLimit:10,font:{size:11}} } }, plugins:{ legend:{display:false}, tooltip:tooltipDark } } });

    // ---- 12-month bar chart ----
    const curMonth = <?= (int)date('m') ?>;
    const barColors = <?= json_encode($chart12_data) ?>.map((_,i) => i+1===curMonth ? '#d97706' : '#3b6fd4');
    new Chart(document.getElementById('chart12'), { type:'bar', data:{ labels:<?= json_encode($chart12_labels) ?>, datasets:[{ label:'Doanh Thu', data:<?= json_encode($chart12_data) ?>, backgroundColor:barColors, borderRadius:6, borderSkipped:false }] }, options:{ ...chartOpts, scales:{ y:{ beginAtZero:true, grid:{color:'#f1f5f9',drawBorder:false}, ticks:{callback:v=>v>=1000000?(v/1000000).toFixed(0)+'M':v/1000+'K',font:{size:11}} }, x:{ grid:{display:false}, ticks:{font:{size:12}} } }, plugins:{ legend:{display:false}, tooltip:{ backgroundColor:'#1e293b', padding:12, callbacks:{ label: c => ' Doanh thu: '+c.parsed.y.toLocaleString('vi-VN')+'₫', afterLabel: (c) => { const orders=<?= json_encode($chart12_orders) ?>; return ' Số đơn: '+orders[c.dataIndex]; } } } } } });

    // ---- Donut chart ----
    new Chart(document.getElementById('donutChart'), { type:'doughnut', data:{ labels:['Trống','Đang ở','Dọn dẹp'], datasets:[{ data:<?= json_encode($donut_data) ?>, backgroundColor:['#22c55e','#3b6fd4','#f59e0b'], borderWidth:0, hoverOffset:8 }] }, options:{ responsive:true, maintainAspectRatio:false, cutout:'72%', plugins:{ legend:{display:false}, tooltip:{ backgroundColor:'#1e293b', callbacks:{label:ctx=>' '+ctx.label+': '+ctx.raw+' phòng'} } } } });
});
</script>

<?php require_once 'include/footer.php'; ?>