<?php
/**
 * K-HOTEL — Hệ thống gửi email tự động
 * Dùng PHPMailer + Gmail SMTP
 * 
 * CÁCH DÙNG: require_once 'mailer.php'; rồi gọi hàm bên dưới
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Fallback: Nếu chưa chạy composer install, thông báo lỗi nhẹ nhàng thay vì die
    error_log('[KHotel Mailer] CẢNH BÁO: Chưa tìm thấy thư mục vendor. Hãy chạy "composer require phpmailer/phpmailer".');
}

/**
 * Hàm ghi log mail (dùng để debug)
 */
function _logMail($message) {
    $logFile = __DIR__ . '/mailer_log.txt';
    $time = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$time] $message\n", FILE_APPEND);
}

/**
 * Hàm tạo đối tượng mailer (dùng nội bộ)
 */
function _createMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'kietvo.260605@gmail.com';
    $mail->Password   = 'aetirvhkgbucwuyb';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom('kietvo.260605@gmail.com', 'K-Hotel');
    return $mail;
}

// =====================================================================
// 1. EMAIL XÁC NHẬN ĐẶT PHÒNG (gửi khi khách đặt xong)
// =====================================================================
function sendBookingConfirmation(string $toEmail, string $toName, array $booking): bool {
    _logMail("GỌI HÀM sendBookingConfirmation cho: $toEmail");
    try {
        $mail = _createMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = '[K-Hotel] Xác nhận đặt phòng #' . $booking['ma_dat'];

        $checkin  = date('H:i d/m/Y', strtotime($booking['ngay_checkin']));
        $checkout = date('H:i d/m/Y', strtotime($booking['ngay_checkout']));
        $tongtien = number_format($booking['tong_tien'] ?? 0) . ' ₫';
        $so_ngay  = $booking['so_ngay'] ?? '';
        $gia_goc  = isset($booking['gia_goc']) ? number_format($booking['gia_goc']).' ₫' : '';
        $giam_gia = $booking['giam_gia'] ?? 0;
        $ten_vc   = $booking['ten_voucher'] ?? '';

        $discount_rows = '';
        if ($giam_gia > 0) {
            $discount_rows .= "<tr style='border-top: 1px solid #f1f5f9;'><td style='padding: 10px 0; color: #64748b;'>Giá gốc</td><td style='padding: 10px 0;'>{$gia_goc}</td></tr>";
            if ($ten_vc) {
                $discount_rows .= "<tr style='border-top: 1px solid #f1f5f9;'><td style='padding: 10px 0; color: #16a34a;'>🎫 Voucher ({$ten_vc})</td><td style='padding: 10px 0; color: #16a34a; font-weight:bold;'>- ".number_format($giam_gia)." ₫</td></tr>";
            } else {
                $discount_rows .= "<tr style='border-top: 1px solid #f1f5f9;'><td style='padding: 10px 0; color: #16a34a;'>👑 Giảm giá (10%)</td><td style='padding: 10px 0; color: #16a34a; font-weight:bold;'>- ".number_format($giam_gia)." ₫</td></tr>";
            }
        }
        $ngay_row = $so_ngay ? "<tr style='border-top: 1px solid #f1f5f9;'><td style='padding: 10px 0; color: #64748b;'>Số ngày ở</td><td style='padding: 10px 0;'>{$so_ngay} ngày</td></tr>" : '';

        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto;'>
            <div style='background: #1e40af; padding: 32px; text-align: center; border-radius: 12px 12px 0 0;'>
                <h1 style='color: white; margin: 0; font-size: 28px;'>🏨 K-Hotel</h1>
                <p style='color: #bfdbfe; margin: 8px 0 0;'>Xác nhận đặt phòng thành công</p>
            </div>
            <div style='background: #f8fafc; padding: 32px; border-radius: 0 0 12px 12px; border: 1px solid #e2e8f0;'>
                <p style='font-size: 16px; color: #1e293b;'>Xin chào <strong>{$toName}</strong>,</p>
                <p style='color: #475569;'>Cảm ơn bạn đã đặt phòng tại K-Hotel. Đây là thông tin xác nhận:</p>
                
                <div style='background: white; border-radius: 10px; padding: 20px; margin: 20px 0; border: 1px solid #e2e8f0;'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td style='padding: 10px 0; color: #64748b; width: 140px;'>Mã đặt phòng</td><td style='padding: 10px 0; font-weight: bold; color: #1e40af;'>#{$booking['ma_dat']}</td></tr>
                        <tr style='border-top: 1px solid #f1f5f9;'><td style='padding: 10px 0; color: #64748b;'>Loại phòng</td><td style='padding: 10px 0; font-weight: bold;'>{$booking['ten_phong']}</td></tr>
                        <tr style='border-top: 1px solid #f1f5f9;'><td style='padding: 10px 0; color: #64748b;'>Check-in</td><td style='padding: 10px 0;'>{$checkin}</td></tr>
                        <tr style='border-top: 1px solid #f1f5f9;'><td style='padding: 10px 0; color: #64748b;'>Check-out</td><td style='padding: 10px 0;'>{$checkout}</td></tr>
                        {$ngay_row}
                        {$discount_rows}
                        <tr style='border-top: 1px solid #f1f5f9;'><td style='padding: 10px 0; color: #64748b;'>Thanh toán</td><td style='padding: 10px 0;'>{$booking['phuong_thuc']}</td></tr>
                        <tr style='border-top: 2px solid #e2e8f0;'><td style='padding: 12px 0; color: #1e293b; font-weight: bold;'>Tổng thanh toán</td><td style='padding: 12px 0; font-size: 20px; font-weight: bold; color: #dc2626;'>{$tongtien}</td></tr>
                    </table>
                </div>

                <div style='background: #eff6ff; border-left: 4px solid #1e40af; padding: 14px 18px; border-radius: 6px; margin: 16px 0;'>
                    <strong style='color: #1e4  0af;'>📋 Lưu ý khi nhận phòng:</strong><br>
                    <span style='color: #475569; font-size: 14px;'>Vui lòng mang theo CCCD/Hộ chiếu và mã đặt phòng khi làm thủ tục.</span>
                </div>

                <p style='color: #64748b; font-size: 14px;'>Nếu cần hỗ trợ, vui lòng liên hệ: <a href='mailto:kietvo.260605@gmail.com' style='color: #1e40af;'>kietvo.260605@gmail.com</a></p>
                <p style='color: #94a3b8; font-size: 13px; margin-top: 24px; text-align: center;'>© K-Hotel — Trân trọng phục vụ quý khách</p>
            </div>
        </div>";

        $mail->AltBody = "Xác nhận đặt phòng #{$booking['ma_dat']} tại K-Hotel\nPhòng: {$booking['ten_phong']}\nCheck-in: {$checkin}\nCheck-out: {$checkout}\nTổng tiền: {$tongtien}";
        
        _logMail("ĐANG GỬI mail xác nhận cho: $toEmail...");
        $mail->send();
        _logMail("THÀNH CÔNG: Đã gửi mail xác nhận cho $toEmail");
        return true;
    } catch (Exception $e) {
        _logMail("THẤT BẠI: Lỗi gửi mail xác nhận cho $toEmail. Lỗi: " . $e->getMessage());
        error_log('[KHotel Mailer] Lỗi gửi xác nhận: ' . $e->getMessage());
        return false;
    }
}

// =====================================================================
// 2. EMAIL HÓA ĐƠN THANH TOÁN (gửi khi checkout)
// =====================================================================
function sendInvoiceEmail(string $toEmail, string $toName, array $invoice): bool {
    _logMail("GỌI HÀM sendInvoiceEmail cho: $toEmail");
    try {
        $mail = _createMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = '[K-Hotel] Hóa đơn thanh toán - Cảm ơn quý khách!';

        $tienPhong  = number_format($invoice['tien_phong']) . ' ₫';
        $tienDV     = number_format($invoice['tien_dichvu']) . ' ₫';
        $giamGia = number_format($invoice['giam_gia'] ?? 0) . ' ₫';
        $tongTien = number_format($invoice['tong_tien'] ?? 0) . ' ₫';
        $ngayLap = date('H:i d/m/Y');
        $ten_vc = $invoice['ten_voucher'] ?? '';

        $vipRow = '';
        if (($invoice['giam_gia'] ?? 0) > 0) {
            if ($ten_vc) {
                $vipRow = "<tr style='border-top: 1px solid #f1f5f9;'><td style='padding: 10px 0; color: #16a34a;'>🎫 Voucher ({$ten_vc})</td><td style='padding: 10px 0; text-align: right; color: #16a34a; font-weight: bold;'>- {$giamGia}</td></tr>";
            } else {
                $vipRow = "<tr style='border-top: 1px solid #f1f5f9;'><td style='padding: 10px 0; color: #16a34a;'>👑 Giảm giá VIP (10%)</td><td style='padding: 10px 0; text-align: right; color: #16a34a; font-weight: bold;'>- {$giamGia}</td></tr>";
            }
        }

        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto;'>
            <div style='background: #065f46; padding: 32px; text-align: center; border-radius: 12px 12px 0 0;'>
                <h1 style='color: white; margin: 0; font-size: 28px;'>🏨 K-Hotel</h1>
                <p style='color: #a7f3d0; margin: 8px 0 0;'>Hóa đơn thanh toán</p>
            </div>
            <div style='background: #f8fafc; padding: 32px; border-radius: 0 0 12px 12px; border: 1px solid #e2e8f0;'>
                <p style='font-size: 16px; color: #1e293b;'>Xin chào <strong>{$toName}</strong>,</p>
                <p style='color: #475569;'>Cảm ơn quý khách đã lưu trú tại K-Hotel. Đây là hóa đơn của bạn:</p>
                
                <div style='background: white; border-radius: 10px; padding: 20px; margin: 20px 0; border: 1px solid #e2e8f0;'>
                    <div style='font-size: 12px; color: #94a3b8; margin-bottom: 12px;'>Ngày lập: {$ngayLap}</div>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td style='padding: 10px 0; color: #64748b;'>Tiền phòng ({$invoice['so_ngay']} ngày)</td><td style='padding: 10px 0; text-align: right;'>{$tienPhong}</td></tr>
                        <tr style='border-top: 1px solid #f1f5f9;'><td style='padding: 10px 0; color: #64748b;'>Phí dịch vụ</td><td style='padding: 10px 0; text-align: right;'>" . (isset($invoice['tien_dichvu']) ? number_format($invoice['tien_dichvu']) . ' ₫' : '0 ₫') . "</td></tr>
                        {$vipRow}
                        <tr style='border-top: 2px solid #e2e8f0;'><td style='padding: 14px 0; font-size: 18px; font-weight: bold; color: #1e293b;'>TỔNG THANH TOÁN</td><td style='padding: 14px 0; font-size: 22px; font-weight: bold; color: #dc2626; text-align: right;'>{$tongTien}</td></tr>
                    </table>
                </div>

                <div style='background: #f0fdf4; border-left: 4px solid #16a34a; padding: 14px 18px; border-radius: 6px;'>
                    <strong style='color: #16a34a;'>🙏 Cảm ơn quý khách!</strong><br>
                    <span style='color: #475569; font-size: 14px;'>Rất vui được phục vụ bạn. Hẹn gặp lại tại K-Hotel!</span>
                </div>

                <p style='color: #94a3b8; font-size: 13px; margin-top: 24px; text-align: center;'>© K-Hotel — kietvo.260605@gmail.com</p>
            </div>
        </div>";

        $mail->AltBody = "Hóa đơn K-Hotel\nTiền phòng: {$tienPhong}\nDịch vụ: {$tienDV}\nTổng: {$tongTien}";
        _logMail("ĐANG GỬI mail hóa đơn cho: $toEmail...");
        $mail->send();
        _logMail("THÀNH CÔNG: Đã gửi mail hóa đơn cho $toEmail");
        return true;
    } catch (Exception $e) {
        _logMail("THẤT BẠI: Lỗi gửi mail hóa đơn cho $toEmail. Lỗi: " . $e->getMessage());
        error_log('[KHotel Mailer] Lỗi gửi hóa đơn: ' . $e->getMessage());
        return false;
    }
}

// =====================================================================
// 3. EMAIL HỦY ĐẶT PHÒNG
// =====================================================================
function sendCancellationEmail(string $toEmail, string $toName, string $maDat, string $lyDo = ''): bool {
    _logMail("GỌI HÀM sendCancellationEmail cho: $toEmail (Đơn #$maDat)");
    try {
        $mail = _createMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = '[K-Hotel] Thông báo hủy đặt phòng #' . $maDat;

        $lyDoHtml = $lyDo ? "<p style='color: #475569;'><strong>Lý do:</strong> {$lyDo}</p>" : '';

        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto;'>
            <div style='background: #dc2626; padding: 32px; text-align: center; border-radius: 12px 12px 0 0;'>
                <h1 style='color: white; margin: 0; font-size: 28px;'>🏨 K-Hotel</h1>
                <p style='color: #fecaca; margin: 8px 0 0;'>Thông báo hủy đặt phòng</p>
            </div>
            <div style='background: #f8fafc; padding: 32px; border-radius: 0 0 12px 12px; border: 1px solid #e2e8f0;'>
                <p style='font-size: 16px; color: #1e293b;'>Xin chào <strong>{$toName}</strong>,</p>
                <p style='color: #475569;'>Đơn đặt phòng <strong>#{$maDat}</strong> của bạn đã bị hủy.</p>
                {$lyDoHtml}
                <div style='background: #fff7ed; border-left: 4px solid #f97316; padding: 14px 18px; border-radius: 6px;'>
                    <strong style='color: #ea580c;'>Cần hỗ trợ?</strong><br>
                    <span style='color: #475569; font-size: 14px;'>Liên hệ ngay: <a href='mailto:kietvo.260605@gmail.com'>kietvo.260605@gmail.com</a></span>
                </div>
                <p style='color: #94a3b8; font-size: 13px; margin-top: 24px; text-align: center;'>© K-Hotel</p>
            </div>
        </div>";

        $mail->AltBody = "Đơn #{$maDat} đã bị hủy. Liên hệ: kietvo.260605@gmail.com";
        _logMail("BẮT ĐẦU gửi mail hủy đơn cho: $toEmail (Đơn #$maDat)");
        $mail->send();
        _logMail("THÀNH CÔNG: Đã gửi mail hủy đơn cho $toEmail");
        return true;
    } catch (Exception $e) {
        _logMail("THẤT BẠI: Lỗi gửi mail hủy đơn cho $toEmail. Lỗi: " . $e->getMessage());
        error_log('[KHotel Mailer] Lỗi gửi hủy: ' . $e->getMessage());
        return false;
    }
}

// =====================================================================
// 4. EMAIL ĐẶT LẠI MẬT KHẨU (gửi link reset)
// =====================================================================
function sendResetPasswordEmail(string $toEmail, string $toName, string $resetUrl): bool {
    _logMail("GỌI HÀM sendResetPasswordEmail cho: $toEmail");
    try {
        $mail = _createMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = '[K-Hotel] Yêu cầu đặt lại mật khẩu';

        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto;'>
            <div style='background: linear-gradient(135deg, #1e3a5f, #2563eb); padding: 32px; text-align: center; border-radius: 12px 12px 0 0;'>
                <h1 style='color: white; margin: 0; font-size: 28px;'>🏨 K-Hotel</h1>
                <p style='color: #bfdbfe; margin: 8px 0 0;'>Yêu cầu đặt lại mật khẩu</p>
            </div>
            <div style='background: #f8fafc; padding: 32px; border-radius: 0 0 12px 12px; border: 1px solid #e2e8f0;'>
                <p style='font-size: 16px; color: #1e293b;'>Xin chào <strong>{$toName}</strong>,</p>
                <p style='color: #475569; line-height: 1.7;'>
                    Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản K-Hotel của bạn.<br>
                    Nhấn vào nút bên dưới để đặt lại mật khẩu:
                </p>

                <div style='text-align: center; margin: 32px 0;'>
                    <a href='{$resetUrl}'
                       style='display: inline-block; background: linear-gradient(135deg, #2563eb, #1d4ed8);
                              color: white; padding: 16px 40px; border-radius: 12px;
                              text-decoration: none; font-weight: bold; font-size: 16px;
                              box-shadow: 0 4px 15px rgba(37,99,235,0.4);'>
                        🔑 Đặt lại mật khẩu ngay
                    </a>
                </div>

                <div style='background: #fff7ed; border-left: 4px solid #f97316; padding: 14px 18px; border-radius: 8px; margin: 20px 0;'>
                    <strong style='color: #ea580c;'>⏰ Lưu ý quan trọng:</strong><br>
                    <span style='color: #475569; font-size: 14px;'>
                        Link này chỉ có hiệu lực trong <strong>1 giờ</strong> và chỉ dùng được <strong>1 lần</strong>.
                    </span>
                </div>

                <p style='color: #64748b; font-size: 14px;'>
                    Nếu nút không hoạt động, sao chép đường dẫn này vào trình duyệt:<br>
                    <a href='{$resetUrl}' style='color: #2563eb; word-break: break-all; font-size: 13px;'>{$resetUrl}</a>
                </p>

                <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;'>
                <p style='color: #94a3b8; font-size: 13px;'>
                    Nếu bạn <strong>không</strong> yêu cầu đổi mật khẩu, hãy bỏ qua email này —
                    tài khoản của bạn vẫn an toàn.
                </p>
                <p style='color: #94a3b8; font-size: 13px; text-align: center;'>© K-Hotel — kietvo.260605@gmail.com</p>
            </div>
        </div>";

        $mail->AltBody = "Đặt lại mật khẩu K-Hotel\nLink: {$resetUrl}\nLink có hiệu lực trong 1 giờ.";
        _logMail("ĐANG GỬI mail reset password cho: $toEmail...");
        $mail->send();
        _logMail("THÀNH CÔNG: Đã gửi mail reset password cho $toEmail");
        return true;
    } catch (Exception $e) {
        _logMail("THẤT BẠI: Lỗi gửi mail reset password cho $toEmail. Lỗi: " . $e->getMessage());
        error_log('[KHotel Mailer] Lỗi gửi reset password: ' . $e->getMessage());
        return false;
    }
}

// =====================================================================
// 5. EMAIL GỬI MÃ OTP XÁC THỰC ĐĂNG KÝ
// =====================================================================
function sendOtpEmail(string $toEmail, string $toName, string $otp): bool {
    _logMail("GỌI HÀM sendOtpEmail cho: $toEmail");
    try {
        $mail = _createMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = '[K-Hotel] Mã xác thực OTP đăng ký tài khoản';

        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto;'>
            <div style='background: linear-gradient(135deg, #1e3a5f, #2563eb); padding: 32px; text-align: center; border-radius: 12px 12px 0 0;'>
                <h1 style='color: white; margin: 0; font-size: 28px;'>🏨 K-Hotel</h1>
                <p style='color: #bfdbfe; margin: 8px 0 0;'>Xác thực tài khoản đăng ký</p>
            </div>
            <div style='background: #f8fafc; padding: 32px; border-radius: 0 0 12px 12px; border: 1px solid #e2e8f0;'>
                <p style='font-size: 16px; color: #1e293b;'>Xin chào <strong>{$toName}</strong>,</p>
                <p style='color: #475569;'>Đây là mã OTP xác thực đăng ký tài khoản K-Hotel của bạn:</p>

                <div style='text-align: center; margin: 32px 0;'>
                    <div style='display: inline-block; background: #eff6ff; border: 3px dashed #2563eb;
                                border-radius: 16px; padding: 20px 40px;'>
                        <div style='font-size: 48px; font-weight: 900; letter-spacing: 12px;
                                    color: #1d4ed8; font-family: monospace;'>{$otp}</div>
                    </div>
                </div>

                <div style='background: #fff7ed; border-left: 4px solid #f97316; padding: 14px 18px; border-radius: 8px; margin: 20px 0;'>
                    <strong style='color: #ea580c;'>⏰ Lưu ý:</strong><br>
                    <span style='color: #475569; font-size: 14px;'>
                        Mã có hiệu lực trong <strong>5 phút</strong> và chỉ dùng được <strong>1 lần</strong>.<br>
                        Nếu bạn không yêu cầu đăng ký, hãy bỏ qua email này.
                    </span>
                </div>

                <p style='color: #94a3b8; font-size: 13px; text-align: center; margin-top: 24px;'>© K-Hotel — kietvo.260605@gmail.com</p>
            </div>
        </div>";

        $mail->AltBody = "Mã OTP K-Hotel: {$otp}\nHiệu lực 5 phút. Không chia sẻ mã này cho ai.";
        _logMail("ĐANG GỬI mail OTP cho: $toEmail...");
        $mail->send();
        _logMail("THÀNH CÔNG: Đã gửi mail OTP cho $toEmail");
        return true;
    } catch (Exception $e) {
        _logMail("THẤT BẠI: Lỗi gửi mail OTP cho $toEmail. Lỗi: " . $e->getMessage());
        error_log('[KHotel Mailer] Lỗi gửi OTP: ' . $e->getMessage());
        return false;
    }
}
