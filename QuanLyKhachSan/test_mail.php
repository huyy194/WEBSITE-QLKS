<?php
require_once 'mailer.php';

$booking = [
    'ma_dat' => 123,
    'ten_phong' => 'Phòng Test (P999)',
    'ngay_checkin' => '2026-05-11 14:00:00',
    'ngay_checkout' => '2026-05-12 12:00:00',
    'tong_tien' => 500000,
    'phuong_thuc' => 'Test'
];

echo "Bắt đầu gửi thử mail xác nhận đặt phòng...\n";
$sent = sendBookingConfirmation('kietvo.260605@gmail.com', 'Kiet Test', $booking);

if ($sent) {
    echo "Thành công: Đã gửi mail xác nhận.\n";
} else {
    echo "Thất bại: Không gửi được mail xác nhận. Kiểm tra log.\n";
}

$invoice = [
    'tien_phong' => 500000,
    'tien_dichvu' => 0,
    'giam_gia' => 0,
    'tong_tien' => 500000,
    'so_ngay' => 1
];

echo "Bắt đầu gửi thử mail hóa đơn...\n";
$sentInv = sendInvoiceEmail('kietvo.260605@gmail.com', 'Kiet Test', $invoice);

if ($sentInv) {
    echo "Thành công: Đã gửi mail hóa đơn.\n";
} else {
    echo "Thất bại: Không gửi được mail hóa đơn. Kiểm tra log.\n";
}
?>
