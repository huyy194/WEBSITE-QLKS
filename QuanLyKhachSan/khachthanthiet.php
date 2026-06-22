<?php
require_once 'config/database.php';
require_once 'include/header.php';

// === TRANG KHÁCH HÀNG THÂN THIẾT ===
// Tự động cộng dồn số lần đặt phòng thành công và cấp phiếu ưu đãi sau 2 lần

// --- Tự động cấp phiếu ưu đãi 10% nếu đạt 2 lần đặt phòng "Đã thanh toán" ---
$conn->query("
    UPDATE KhachHang kh
    SET kh.HangThanhVien = 'VIP'
    WHERE kh.HangThanhVien != 'VIP'
    AND (
        SELECT COUNT(*) FROM DatPhong dp 
        WHERE dp.MaKH = kh.MaKH 
        AND dp.TrangThai IN ('Đã thanh toán','Đã thanh toán (Online)')
    ) >= 2
");

// Lấy danh sách khách hàng thân thiết (VIP) kèm số lần đặt & tổng chi tiêu
$sql_vip = "
    SELECT kh.*, 
           COUNT(DISTINCT dp.MaDP) AS SoLanDat,
           SUM(CASE WHEN dp.TrangThai IN ('Đã thanh toán','Đã thanh toán (Online)') THEN 1 ELSE 0 END) AS SoLanThanhToan,
           COALESCE(SUM(hd.TongTien), 0) AS TongChiTieu,
           (SELECT Code FROM Voucher WHERE MaKH = kh.MaKH AND TenVoucher LIKE '%Thân Thiết%' AND TrangThai = 'active' LIMIT 1) AS RealCode
    FROM KhachHang kh
    LEFT JOIN DatPhong dp ON kh.MaKH = dp.MaKH
    LEFT JOIN HoaDon hd ON dp.MaDP = hd.MaDP
    GROUP BY kh.MaKH
    HAVING SoLanThanhToan >= 2
    ORDER BY SoLanThanhToan DESC, TongChiTieu DESC
";
$vip_res = $conn->query($sql_vip);

// Lấy tất cả khách để hiển thị tiến độ
$sql_all = "
    SELECT kh.*, 
           COUNT(dp.MaDP) AS SoLanDat,
           SUM(CASE WHEN dp.TrangThai IN ('Đã thanh toán','Đã thanh toán (Online)') THEN 1 ELSE 0 END) AS SoLanThanhToan,
           COALESCE(SUM(hd.TongTien), 0) AS TongChiTieu
    FROM KhachHang kh
    LEFT JOIN DatPhong dp ON kh.MaKH = dp.MaKH
    LEFT JOIN HoaDon hd ON dp.MaDP = hd.MaDP
    GROUP BY kh.MaKH
    ORDER BY SoLanThanhToan DESC
";
$all_res = $conn->query($sql_all);
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold m-0"><i class="fa-solid fa-crown text-warning me-2"></i> Khách Hàng Thân Thiết</h2>
            <p class="text-muted mb-0 mt-1">Khách đặt phòng từ 2 lần trở lên sẽ được nhận phiếu ưu đãi <strong class="text-success">giảm 10%</strong> cho lần tiếp theo.</p>
        </div>
        <div class="badge bg-warning text-dark fs-6 px-3 py-2">
            <i class="fa-solid fa-users me-1"></i> <?= $vip_res->num_rows ?> Khách VIP
        </div>
    </div>
</div>

<!-- Thống kê tóm tắt -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center gap-3" style="border-left: 4px solid #f59e0b !important;">
            <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width:56px;height:56px;flex-shrink:0;">
                <i class="fa-solid fa-crown fs-4"></i>
            </div>
            <div>
                <div class="fw-bold fs-4"><?= $vip_res->num_rows ?></div>
                <div class="text-muted small">Khách hàng VIP</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center gap-3" style="border-left: 4px solid #10b981 !important;">
            <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center" style="width:56px;height:56px;flex-shrink:0;">
                <i class="fa-solid fa-ticket fs-4"></i>
            </div>
            <div>
                <div class="fw-bold fs-4"><?= $vip_res->num_rows ?></div>
                <div class="text-muted small">Phiếu ưu đãi 10% đang hiệu lực</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center gap-3" style="border-left: 4px solid #2563eb !important;">
            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width:56px;height:56px;flex-shrink:0;">
                <i class="fa-solid fa-percent fs-4"></i>
            </div>
            <div>
                <div class="fw-bold fs-4">10%</div>
                <div class="text-muted small">Mức chiết khấu áp dụng</div>
            </div>
        </div>
    </div>
</div>

<!-- Danh sách Khách VIP có phiếu -->
<?php if ($vip_res->num_rows > 0): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-warning text-dark fw-bold py-3">
        <i class="fa-solid fa-crown me-2"></i> Danh Sách Khách Hàng VIP — Có Phiếu Ưu Đãi 10%
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Khách Hàng</th>
                    <th>CCCD / SĐT</th>
                    <th class="text-center">Số Lần Đặt</th>
                    <th class="text-center">Số Lần Thanh Toán</th>
                    <th>Tổng Chi Tiêu</th>
                    <th class="text-center">Phiếu Ưu Đãi</th>
                    <th class="text-center">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $vip_res->data_seek(0);
                while ($v = $vip_res->fetch_assoc()): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:38px;height:38px;font-size:1rem;flex-shrink:0;">
                                <?= mb_substr($v['HoTen'], 0, 1) ?>
                            </div>
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($v['HoTen']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($v['Email'] ?? '—') ?></small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div><?= htmlspecialchars($v['CCCD']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($v['SDT']) ?></small>
                    </td>
                    <td class="text-center"><span class="badge bg-secondary"><?= $v['SoLanDat'] ?></span></td>
                    <td class="text-center"><span class="badge bg-success"><?= $v['SoLanThanhToan'] ?></span></td>
                    <td class="fw-bold text-primary"><?= number_format($v['TongChiTieu']) ?> ₫</td>
                    <td class="text-center">
                        <?php if ($v['RealCode']): ?>
                        <button class="btn btn-sm btn-warning fw-bold" onclick="showVoucher('<?= htmlspecialchars($v['HoTen']) ?>', '<?= $v['RealCode'] ?>')" data-bs-toggle="modal" data-bs-target="#voucherModal">
                            <i class="fa-solid fa-ticket me-1"></i> Xem Phiếu
                        </button>
                        <?php else: ?>
                        <span class="text-muted small">Đã sử dụng / Chưa cấp</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <a href="khachhang.php" class="btn btn-sm btn-outline-primary">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Tất cả khách với tiến độ -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-bold py-3 border-bottom">
        <i class="fa-solid fa-list-check text-primary me-2"></i> Tiến Độ Tích Lũy Toàn Bộ Khách Hàng
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Mã KH</th>
                    <th>Họ Tên</th>
                    <th>SĐT</th>
                    <th class="text-center">Lần Thanh Toán</th>
                    <th class="text-center">Tiến Độ VIP</th>
                    <th class="text-center">Trạng Thái</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($r = $all_res->fetch_assoc()): 
                    $progress = min(100, ($r['SoLanThanhToan'] / 2) * 100);
                    $isVip = $r['SoLanThanhToan'] >= 2;
                ?>
                <tr>
                    <td><span class="fw-bold text-muted">KH<?= str_pad($r['MaKH'], 4, '0', STR_PAD_LEFT) ?></span></td>
                    <td class="fw-bold"><?= htmlspecialchars($r['HoTen']) ?></td>
                    <td><?= htmlspecialchars($r['SDT']) ?></td>
                    <td class="text-center">
                        <span class="badge <?= $isVip ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $r['SoLanThanhToan'] ?> / 2 lần
                        </span>
                    </td>
                    <td style="min-width:150px;">
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar <?= $isVip ? 'bg-warning' : 'bg-primary' ?>" style="width: <?= $progress ?>%;"></div>
                        </div>
                        <small class="text-muted"><?= round($progress) ?>%</small>
                    </td>
                    <td class="text-center">
                        <?php if ($isVip): ?>
                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-crown me-1"></i>VIP — Có ưu đãi</span>
                        <?php elseif ($r['SoLanThanhToan'] == 1): ?>
                            <span class="badge bg-info text-dark"><i class="fa-solid fa-fire me-1"></i>Còn 1 lần nữa</span>
                        <?php else: ?>
                            <span class="badge bg-light text-dark border">Chưa đủ điều kiện</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Phiếu Ưu Đãi -->
<div class="modal fade" id="voucherModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
      <div class="modal-header border-0 pb-0" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
        <div class="w-100 text-center py-3">
            <i class="fa-solid fa-crown text-white fs-1 mb-2 d-block"></i>
            <h4 class="fw-bold text-white mb-0">PHIẾU ƯU ĐÃI K-HOTEL</h4>
            <p class="text-white opacity-75 mb-0 small">Dành cho Khách Hàng Thân Thiết</p>
        </div>
        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-4">
          <div class="border border-warning border-3 rounded-4 p-4 mb-3" style="background: linear-gradient(135deg, #fffbeb, #fef3c7);">
              <p class="text-muted mb-1 fw-bold">Kính gửi Quý Khách:</p>
              <h3 class="fw-bold text-dark mb-3" id="voucherName">—</h3>
              <div class="bg-warning text-dark d-inline-block px-4 py-2 rounded-3 fw-bold mb-3" style="font-size: 2rem; letter-spacing: 3px;" id="voucherCode">—</div>
              <p class="fw-bold text-danger fs-4 mb-1">GIẢM <span class="display-4 fw-black">10%</span></p>
              <p class="text-muted small">Áp dụng cho lần đặt phòng tiếp theo tại K-Hotel</p>
              <hr class="border-warning">
              <div class="d-flex justify-content-around text-muted small">
                  <span><i class="fa-solid fa-calendar me-1"></i> HSD: 31/12/2026</span>
                  <span><i class="fa-solid fa-building me-1"></i> K-Hotel Việt Nam</span>
              </div>
          </div>
          <p class="text-muted small">Vui lòng trình phiếu này khi đặt phòng tại quầy lễ tân hoặc thông báo mã cho nhân viên.</p>
      </div>
      <div class="modal-footer border-0 pt-0 justify-content-center gap-2">
          <button class="btn btn-warning fw-bold rounded-pill px-4" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>In Phiếu</button>
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Đóng</button>
      </div>
    </div>
  </div>
</div>

<script>
function showVoucher(name, code) {
    document.getElementById('voucherName').innerText = name;
    document.getElementById('voucherCode').innerText = code;
}
</script>

<?php require_once 'include/footer.php'; ?>
