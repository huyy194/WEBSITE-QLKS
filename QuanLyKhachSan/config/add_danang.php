<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/database.php';

echo "<pre>\n";

// Xóa nếu đã có Đà Nẵng
$conn->query("DELETE FROM Phong WHERE MaPhong LIKE 'DN%'");
$conn->query("DELETE FROM LoaiPhong WHERE KhuVuc = 'Đà Nẵng'");

// Thêm Đà Nẵng (5 phòng - Biển + Phố)
$conn->query("INSERT INTO LoaiPhong (TenLoai, KhuVuc, GiaPhong, SoNguoiToiDa, TienNghi, TuKhoa) VALUES 
    ('Phòng Biển Mỹ Khê', 'Đà Nẵng', 650000, 2, 'View biển Mỹ Khê, Máy lạnh, Tivi, Ban công', 'đà nẵng, biển, mỹ khê, beach, miền trung, danang'),
    ('Phòng Sơn Trà View', 'Đà Nẵng', 950000, 2, 'View bán đảo Sơn Trà, Bồn tắm, Ban công rộng', 'đà nẵng, sơn trà, view, miền trung, danang'),
    ('Phòng Suite Hàn River', 'Đà Nẵng', 1600000, 3, 'View sông Hàn, Phòng khách riêng, Minibar cao cấp', 'đà nẵng, sông hàn, suite, sang trọng, danang'),
    ('Phòng Gia Đình Đà Nẵng', 'Đà Nẵng', 1100000, 5, '2 Phòng ngủ, View biển, Bếp nhỏ, Khu vui chơi', 'đà nẵng, gia đình, rộng, miền trung, danang'),
    ('Penthouse Ngũ Hành Sơn', 'Đà Nẵng', 2800000, 4, 'Tầng cao nhất, View Ngũ Hành Sơn, Hồ bơi riêng', 'đà nẵng, ngũ hành sơn, penthouse, vip, danang')
");

if ($conn->error) {
    echo "❌ Lỗi LoaiPhong: " . $conn->error . "\n";
} else {
    $last_id = $conn->insert_id;
    echo "✅ Thêm 5 LoaiPhong Đà Nẵng (ID bắt đầu: $last_id)\n";
    
    for ($i = 0; $i < 5; $i++) {
        $ma = 'DN' . (101 + $i);
        $loai = $last_id + $i;
        $r = $conn->query("INSERT INTO Phong (MaPhong, MaLoai, TrangThai) VALUES ('$ma', $loai, 'Trống')");
        if (!$r) echo "❌ Lỗi Phòng $ma: " . $conn->error . "\n";
        else echo "  ✅ Phòng $ma → LoaiPhong $loai\n";
    }
}

// Kiểm tra tổng
$total = $conn->query("SELECT COUNT(*) as cnt FROM Phong")->fetch_assoc()['cnt'];
$by_region = $conn->query("SELECT lp.KhuVuc, COUNT(*) as cnt FROM Phong p JOIN LoaiPhong lp ON p.MaLoai = lp.MaLoai GROUP BY lp.KhuVuc ORDER BY lp.KhuVuc");
echo "\n=============================\n";
echo "📊 TỔNG SỐ PHÒNG: $total\n";
while ($kv = $by_region->fetch_assoc()) {
    echo "  📍 " . $kv['KhuVuc'] . ": " . $kv['cnt'] . " phòng\n";
}
echo "=============================\n";
echo "</pre>";
?>
