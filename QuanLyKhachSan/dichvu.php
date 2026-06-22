<?php
require_once 'config/database.php';

// Xử lý Xóa
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM DichVu WHERE MaDV = $id");
    header("Location: dichvu.php");
    exit;
}

// Xử lý Thêm/Sửa
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tendv = $conn->real_escape_string($_POST['tendv']);
    $giadv = (float)$_POST['giadv'];
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id > 0) {
        $conn->query("UPDATE DichVu SET TenDV='$tendv', GiaDV=$giadv WHERE MaDV=$id");
    } else {
        $conn->query("INSERT INTO DichVu (TenDV, GiaDV) VALUES ('$tendv', $giadv)");
    }
    header("Location: dichvu.php");
    exit;
}

require_once 'include/header.php';

$sql = "SELECT * FROM DichVu ORDER BY MaDV DESC";
$result = $conn->query($sql);
?>



<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2 class="fw-bold m-0"><i class="fa-solid fa-concierge-bell text-primary me-2"></i> Quản lý Dịch vụ</h2>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#dvModal" onclick="resetForm()"><i class="fa-solid fa-plus"></i> Thêm dịch vụ</button>
    </div>
</div>

<div class="card p-4 border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th class="py-3">Mã Dịch Vụ</th>
                    <th class="py-3">Tên Dịch Vụ</th>
                    <th class="py-3">Đơn Giá (VNĐ)</th>
                    <th class="py-3 text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td>DV<?= str_pad($row['MaDV'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td class="fw-bold text-dark"><?= $row['TenDV'] ?></td>
                    <td class="text-danger fw-bold"><?= number_format($row['GiaDV']) ?> ₫</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-warning" onclick='editData(<?= json_encode($row) ?>)' data-bs-toggle="modal" data-bs-target="#dvModal"><i class="fa-solid fa-pen"></i></button>
                        <a href="?delete=<?= $row['MaDV'] ?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Xóa dịch vụ này?')"><i class="fa-solid fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if($result->num_rows == 0): ?>
                <tr><td colspan="4" class="text-center py-5 text-muted"><i class="fa-solid fa-box-open fs-1 mb-3 d-block"></i>Chưa có dịch vụ nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Thêm/Sửa -->
<div class="modal fade" id="dvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title fw-bold" id="modalTitle">Thêm Dịch Vụ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="dv_id">
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Tên Dịch Vụ (*)</label>
                        <input type="text" name="tendv" id="dv_tendv" class="form-control bg-light border-0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Giá Dịch Vụ (VNĐ) (*)</label>
                        <input type="number" name="giadv" id="dv_giadv" class="form-control bg-light border-0" min="0" required>
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
    document.getElementById('modalTitle').innerText = 'Thêm Dịch Vụ';
    document.getElementById('dv_id').value = '';
    document.getElementById('dv_tendv').value = '';
    document.getElementById('dv_giadv').value = '';
}
function editData(data) {
    document.getElementById('modalTitle').innerText = 'Cập Nhật Dịch Vụ';
    document.getElementById('dv_id').value = data.MaDV;
    document.getElementById('dv_tendv').value = data.TenDV;
    document.getElementById('dv_giadv').value = data.GiaDV;
}
</script>

<?php require_once 'include/footer.php'; ?>
