<?php
require_once 'config/database.php';

$room_id = isset($_GET['room']) ? $conn->real_escape_string($_GET['room']) : '';
if (!$room_id) { header("Location: sodophong.php"); exit; }

// get current booking DP
$b_res = $conn->query("SELECT MaDP FROM DatPhong WHERE MaPhong = '$room_id' AND TrangThai = 'Đang ở' ORDER BY MaDP DESC LIMIT 1");
if ($b_res->num_rows == 0) { header("Location: sodophong.php"); exit; }
$booking = $b_res->fetch_assoc();
$madp = $booking['MaDP'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $madv = (int)$_POST['madv'];
    $sl = (int)$_POST['soluong'];

    $dv_res = $conn->query("SELECT GiaDV FROM DichVu WHERE MaDV = $madv");
    $gia = $dv_res->fetch_assoc()['GiaDV'];
    $thanhtien = $gia * $sl;

    $conn->query("INSERT INTO SuDungDichVu (MaDP, MaDV, SoLuong, ThanhTien) VALUES ($madp, $madv, $sl, $thanhtien)");
    header("Location: sodophong.php");
    exit;
}

require_once 'include/header.php';

$dichvu_res = $conn->query("SELECT * FROM DichVu");
?>



<div class="row">
    <div class="col-md-6 offset-md-3">
        <div class="card p-4 shadow-sm border-0">
            <h4 class="fw-bold text-success mb-4"><i class="fa-solid fa-bell-concierge"></i> Thêm Dịch Vụ: Phòng <?= $room_id ?></h4>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-medium">Chọn Dịch Vụ</label>
                    <select name="madv" class="form-select bg-light border-0" required>
                        <?php while($dv = $dichvu_res->fetch_assoc()): ?>
                            <option value="<?= $dv['MaDV'] ?>"><?= $dv['TenDV'] ?> - <?= number_format($dv['GiaDV']) ?> VNĐ</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-medium">Số Lượng</label>
                    <input type="number" name="soluong" class="form-control bg-light border-0" value="1" min="1" required>
                </div>
                <button class="btn btn-success w-100 fw-bold">Thêm Dịch Vụ</button>
                <a href="sodophong.php" class="btn btn-outline-secondary w-100 mt-2">Hủy quay về</a>
            </form>
        </div>
    </div>
</div>

<?php require_once 'include/footer.php'; ?>
