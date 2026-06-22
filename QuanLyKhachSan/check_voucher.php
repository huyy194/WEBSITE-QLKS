<?php
/**
 * AJAX endpoint: kiểm tra voucher hợp lệ
 * Trả về JSON { valid: true|false, message: '...', discount: '...', type: '...', value: ... }
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'khach') {
    echo json_encode(['valid' => false, 'message' => 'Vui lòng đăng nhập để sử dụng voucher.']);
    exit;
}

$code = strtoupper(trim($_GET['code'] ?? ''));
if (!$code) {
    echo json_encode(['valid' => false, 'message' => 'Vui lòng nhập mã voucher.']);
    exit;
}

$today = date('Y-m-d');
$safe_code = $conn->real_escape_string($code);

// Lấy thông tin khách hàng
$userid = $_SESSION['user_id'];
$kh_res = $conn->query("SELECT MaKH FROM KhachHang WHERE MaTK = $userid LIMIT 1");
$makh = $kh_res && $kh_res->num_rows > 0 ? $kh_res->fetch_assoc()['MaKH'] : null;

// Tìm voucher
$v_res = $conn->query("SELECT * FROM Voucher 
                        WHERE Code = '$safe_code' 
                        AND TrangThai = 'active'
                        AND NgayHetHan >= '$today'
                        AND SoLanDaDung < GioiHanDung
                        AND (MaKH IS NULL OR MaKH = " . ($makh ?? 0) . ")
                        LIMIT 1");

if (!$v_res || $v_res->num_rows == 0) {
    // Thử tìm không filter khách để xem lỗi cụ thể
    $v_check = $conn->query("SELECT TrangThai, NgayHetHan, MaKH, SoLanDaDung, GioiHanDung FROM Voucher WHERE Code = '$safe_code' LIMIT 1");
    if ($v_check && $v_check->num_rows > 0) {
        $vc = $v_check->fetch_assoc();
        if ($vc['TrangThai'] != 'active') {
            echo json_encode(['valid' => false, 'message' => 'Mã voucher đã bị vô hiệu hóa.']);
        } elseif ($vc['NgayHetHan'] < $today) {
            echo json_encode(['valid' => false, 'message' => 'Mã voucher đã hết hạn.']);
        } elseif ($vc['SoLanDaDung'] >= $vc['GioiHanDung']) {
            echo json_encode(['valid' => false, 'message' => 'Mã voucher đã được sử dụng hết lượt.']);
        } elseif ($vc['MaKH'] && $vc['MaKH'] != $makh) {
            echo json_encode(['valid' => false, 'message' => 'Mã voucher này không dành cho tài khoản của bạn.']);
        } else {
            echo json_encode(['valid' => false, 'message' => 'Mã voucher không hợp lệ.']);
        }
    } else {
        echo json_encode(['valid' => false, 'message' => 'Mã voucher "' . htmlspecialchars($code) . '" không tồn tại.']);
    }
    exit;
}

$vc = $v_res->fetch_assoc();

$discount_text = $vc['LoaiGiam'] == 'phantram'
    ? 'Giảm ' . $vc['GiaTriGiam'] . '%'
    : 'Giảm ' . number_format($vc['GiaTriGiam']) . ' ₫';

$min_text = $vc['GiaTriToiThieu'] > 0
    ? ' (Đơn tối thiểu ' . number_format($vc['GiaTriToiThieu']) . ' ₫)'
    : '';

echo json_encode([
    'valid'   => true,
    'message' => '✅ ' . htmlspecialchars($vc['TenVoucher']) . ' — ' . $discount_text . $min_text,
    'type'    => $vc['LoaiGiam'],
    'value'   => (float)$vc['GiaTriGiam'],
    'minimum' => (float)($vc['GiaTriToiThieu'] ?? 0),
    'days_left' => ceil((strtotime($vc['NgayHetHan']) - time()) / 86400),
    'name'    => htmlspecialchars($vc['TenVoucher']),
]);
?>
