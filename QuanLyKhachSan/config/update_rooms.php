<?php
/**
 * Script fix: Chèn phòng theo khu vực vào database  
 * Chạy trực tiếp qua browser
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/database.php';

echo "<pre>\n";

// 1. Đảm bảo cột KhuVuc tồn tại
$check = $conn->query("SHOW COLUMNS FROM LoaiPhong LIKE 'KhuVuc'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE LoaiPhong ADD COLUMN KhuVuc VARCHAR(100) DEFAULT 'Hồ Chí Minh' AFTER TenLoai");
    echo "✅ Đã thêm cột KhuVuc\n";
} else {
    echo "ℹ️ Cột KhuVuc đã tồn tại\n";
}

// 2. Đảm bảo cột TuKhoa tồn tại
$check2 = $conn->query("SHOW COLUMNS FROM LoaiPhong LIKE 'TuKhoa'");
if ($check2->num_rows == 0) {
    $conn->query("ALTER TABLE LoaiPhong ADD COLUMN TuKhoa TEXT DEFAULT NULL");
    echo "✅ Đã thêm cột TuKhoa\n";
} else {
    echo "ℹ️ Cột TuKhoa đã tồn tại\n";
}

// 3. Cập nhật KhuVuc cho các LoaiPhong cũ (HCM)
$conn->query("UPDATE LoaiPhong SET KhuVuc = 'Hồ Chí Minh', TuKhoa = 'thành phố, phố, trung tâm, mua sắm, city, sài gòn, hồ chí minh' WHERE KhuVuc IS NULL OR KhuVuc = ''");
echo "✅ Đã cập nhật KhuVuc cho loại phòng cũ\n";

// 4. Xóa phòng mới cũ (nếu migration trước tạo dở)
$conn->query("DELETE FROM Phong WHERE MaPhong LIKE 'PT%' OR MaPhong LIKE 'HN%' OR MaPhong LIKE 'TN%'");
$conn->query("DELETE FROM LoaiPhong WHERE KhuVuc IN ('Phan Thiết', 'Hà Nội', 'Tây Ninh')");
echo "✅ Đã dọn dẹp data cũ (nếu có)\n";

// 5. Thêm Phan Thiết
$conn->query("INSERT INTO LoaiPhong (TenLoai, KhuVuc, GiaPhong, SoNguoiToiDa, TienNghi, TuKhoa) VALUES 
    ('Phòng Biển Standard', 'Phan Thiết', 600000, 2, 'View biển, Máy lạnh, Tivi, Ban công', 'biển, beach, phan thiết, nghỉ dưỡng'),
    ('Phòng Biển Deluxe', 'Phan Thiết', 900000, 2, 'View biển trực diện, Bồn tắm, Ban công rộng, Minibar', 'biển, beach, phan thiết, view'),
    ('Phòng Biển Suite', 'Phan Thiết', 1500000, 3, 'View biển panorama, Phòng khách riêng, Jacuzzi', 'biển, beach, phan thiết, suite'),
    ('Phòng Biển Gia Đình', 'Phan Thiết', 1200000, 5, '2 Phòng ngủ, View biển, Bếp nhỏ, Sân vườn riêng', 'biển, beach, phan thiết, gia đình'),
    ('Bungalow Biển VIP', 'Phan Thiết', 2500000, 4, 'Bungalow riêng, Hồ bơi riêng, View biển 360°', 'biển, beach, phan thiết, vip, bungalow')
");
if ($conn->error) { echo "❌ Lỗi LoaiPhong PT: " . $conn->error . "\n"; }
$last_id = $conn->insert_id;
echo "✅ Thêm 5 LoaiPhong Phan Thiết (ID bắt đầu: $last_id)\n";

// Insert phòng PT
for ($i = 0; $i < 5; $i++) {
    $ma = 'PT' . (101 + $i);
    $loai = $last_id + $i;
    $r = $conn->query("INSERT INTO Phong (MaPhong, MaLoai, TrangThai) VALUES ('$ma', $loai, 'Trống')");
    if (!$r) echo "❌ Lỗi Phong $ma: " . $conn->error . "\n";
    else echo "  ✅ Phòng $ma → LoaiPhong $loai\n";
}

// 6. Thêm Hà Nội
$conn->query("INSERT INTO LoaiPhong (TenLoai, KhuVuc, GiaPhong, SoNguoiToiDa, TienNghi, TuKhoa) VALUES 
    ('Phòng Phố Cổ Classic', 'Hà Nội', 450000, 2, 'View phố cổ, Nội thất gỗ, Máy lạnh, Tivi', 'hà nội, phố cổ, văn hóa, hanoi'),
    ('Phòng Hồ Gươm View', 'Hà Nội', 800000, 2, 'View Hồ Gươm, Ban công, Bồn tắm, Minibar', 'hà nội, hồ gươm, view, hanoi'),
    ('Phòng Heritage Suite', 'Hà Nội', 1400000, 3, 'Nội thất Đông Dương, Phòng khách rộng, Spa trong phòng', 'hà nội, di sản, heritage, hanoi'),
    ('Phòng Gia Đình Hà Nội', 'Hà Nội', 1000000, 5, '2 Giường đôi, Phòng rộng, View thành phố', 'hà nội, gia đình, hanoi'),
    ('Penthouse Royal Hà Nội', 'Hà Nội', 3000000, 4, 'Tầng thượng, View toàn thành phố, Hồ bơi trên mái', 'hà nội, penthouse, royal, vip, hanoi')
");
if ($conn->error) { echo "❌ Lỗi LoaiPhong HN: " . $conn->error . "\n"; }
$last_id = $conn->insert_id;
echo "✅ Thêm 5 LoaiPhong Hà Nội (ID bắt đầu: $last_id)\n";

for ($i = 0; $i < 5; $i++) {
    $ma = 'HN' . (101 + $i);
    $loai = $last_id + $i;
    $r = $conn->query("INSERT INTO Phong (MaPhong, MaLoai, TrangThai) VALUES ('$ma', $loai, 'Trống')");
    if (!$r) echo "❌ Lỗi Phong $ma: " . $conn->error . "\n";
    else echo "  ✅ Phòng $ma → LoaiPhong $loai\n";
}

// 7. Thêm Tây Ninh
$conn->query("INSERT INTO LoaiPhong (TenLoai, KhuVuc, GiaPhong, SoNguoiToiDa, TienNghi, TuKhoa) VALUES 
    ('Phòng Núi Standard', 'Tây Ninh', 400000, 2, 'View núi Bà Đen, Máy lạnh, Tivi, Không gian thoáng', 'núi, mountain, tây ninh, thiên nhiên'),
    ('Phòng Núi Deluxe', 'Tây Ninh', 700000, 2, 'View núi panorama, Ban công rộng, Võng thư giãn, Trà đạo', 'núi, mountain, tây ninh, thư giãn'),
    ('Phòng Rừng Xanh Eco', 'Tây Ninh', 550000, 2, 'Eco-lodge, Gần rừng, Xe đạp miễn phí, BBQ', 'núi, mountain, tây ninh, eco, rừng'),
    ('Phòng Gia Đình Núi', 'Tây Ninh', 900000, 5, '2 Phòng ngủ, View núi, Sân vườn rộng, Lò sưởi', 'núi, mountain, tây ninh, gia đình'),
    ('Villa Đỉnh Núi VIP', 'Tây Ninh', 2000000, 6, 'Villa riêng trên đồi, Hồ bơi vô cực, View 360° núi rừng', 'núi, mountain, tây ninh, villa, vip')
");
if ($conn->error) { echo "❌ Lỗi LoaiPhong TN: " . $conn->error . "\n"; }
$last_id = $conn->insert_id;
echo "✅ Thêm 5 LoaiPhong Tây Ninh (ID bắt đầu: $last_id)\n";

for ($i = 0; $i < 5; $i++) {
    $ma = 'TN' . (101 + $i);
    $loai = $last_id + $i;
    $r = $conn->query("INSERT INTO Phong (MaPhong, MaLoai, TrangThai) VALUES ('$ma', $loai, 'Trống')");
    if (!$r) echo "❌ Lỗi Phong $ma: " . $conn->error . "\n";
    else echo "  ✅ Phòng $ma → LoaiPhong $loai\n";
}

// 8. Kiểm tra tổng
$total = $conn->query("SELECT COUNT(*) as cnt FROM Phong")->fetch_assoc()['cnt'];
$by_region = $conn->query("SELECT lp.KhuVuc, COUNT(*) as cnt FROM Phong p JOIN LoaiPhong lp ON p.MaLoai = lp.MaLoai GROUP BY lp.KhuVuc");
echo "\n=============================\n";
echo "📊 TỔNG SỐ PHÒNG: $total\n";
while ($kv = $by_region->fetch_assoc()) {
    echo "  📍 " . $kv['KhuVuc'] . ": " . $kv['cnt'] . " phòng\n";
}
echo "=============================\n";
echo "✅ HOÀN TẤT!\n";
echo "</pre>";
?>
