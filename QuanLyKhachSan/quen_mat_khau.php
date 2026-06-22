<?php
/**
 * K-HOTEL — Quên mật khẩu
 * Bước 1: Khách nhập email → hệ thống gửi link reset về mail
 */
require_once 'config/database.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Vui lòng nhập địa chỉ email hợp lệ.';
    } else {
        $safe_email = $conn->real_escape_string($email);

        try {
            // Tìm tài khoản có email này (Kiểm tra cả bảng TaiKhoan và KhachHang)
            $res = $conn->query("
                SELECT tk.MaTK, tk.HoTen, 
                       COALESCE(kh.Email, '') as KhachEmail
                FROM TaiKhoan tk
                LEFT JOIN KhachHang kh ON kh.MaTK = tk.MaTK
                WHERE kh.Email = '$safe_email' OR tk.TenDangNhap = '$safe_email'
                LIMIT 1
            ");
            
            // Nếu không tìm thấy, ta vẫn báo "Đã gửi" để bảo mật, tránh lộ email tồn tại
            if (!$res || $res->num_rows == 0) {
                $success = 'Nếu email tồn tại trong hệ thống, chúng tôi đã gửi link đổi mật khẩu. Vui lòng kiểm tra hộp thư.';
            } else {
                $user = $res->fetch_assoc();
                $matk = (int)$user['MaTK'];

                // Tạo token ngẫu nhiên an toàn
                $token  = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Lưu token (Xóa cái cũ trước)
                $conn->query("DELETE FROM PasswordResetTokens WHERE MaTK = $matk");
                if (!$conn->query("INSERT INTO PasswordResetTokens (MaTK, Token, ThoiGianHetHan) VALUES ($matk, '$token', '$expiry')")) {
                    throw new Exception("Lỗi database: " . $conn->error);
                }

                // Tạo link reset
                $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host      = $_SERVER['HTTP_HOST'];
                $uri       = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $reset_url = "$protocol://$host$uri/reset_mat_khau.php?token=$token";

                // Gửi email
                require_once 'mailer.php';
                $sent = sendResetPasswordEmail($email, $user['HoTen'], $reset_url);

                if ($sent) {
                    $success = 'Link đổi mật khẩu đã được gửi đến <strong>' . htmlspecialchars($email) . '</strong>. Vui lòng kiểm tra hộp thư (có hiệu lực trong 1 giờ).';
                } else {
                    $error = 'Gửi email thất bại. Vui lòng thử lại sau hoặc liên hệ quản trị viên.';
                    $conn->query("DELETE FROM PasswordResetTokens WHERE MaTK = $matk");
                }
            }
        } catch (Exception $e) {
            $error = 'Có lỗi xảy ra: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên Mật Khẩu — K-Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); min-height: 100vh; display: flex; align-items: center; }
        .card { border-radius: 20px; border: 0; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .hotel-icon { width: 72px; height: 72px; background: linear-gradient(135deg, #2563eb, #1d4ed8); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
        .btn-primary { background: linear-gradient(135deg, #2563eb, #1d4ed8); border: 0; border-radius: 10px; padding: 12px; font-weight: 600; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); transition: all 0.2s; }
        .form-control { border-radius: 10px; padding: 12px 16px; border: 2px solid #e2e8f0; }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="hotel-icon">
                        <i class="fa-solid fa-hotel text-white fs-3"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">Quên mật khẩu?</h4>
                    <p class="text-muted small">Nhập email đăng ký để nhận link đặt lại mật khẩu</p>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger rounded-3 border-0 py-3">
                    <i class="fa-solid fa-circle-exclamation me-2"></i><?= $error ?>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success rounded-3 border-0 py-3">
                    <i class="fa-solid fa-envelope-circle-check me-2"></i><?= $success ?>
                </div>
                <div class="text-center mt-3">
                    <a href="index.php" class="btn btn-outline-primary rounded-3 px-4">
                        <i class="fa-solid fa-home me-2"></i>Về trang chủ
                    </a>
                </div>
                <?php else: ?>

                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-medium text-secondary small">Địa chỉ Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2 border-end-0 rounded-start-3">
                                <i class="fa-solid fa-envelope text-muted"></i>
                            </span>
                            <input type="email" name="email" class="form-control border-start-0 rounded-end-3"
                                   placeholder="example@gmail.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-paper-plane me-2"></i>Gửi link đặt lại mật khẩu
                    </button>
                </form>

                <div class="text-center mt-4">
                    <a href="index.php" class="text-muted small text-decoration-none">
                        <i class="fa-solid fa-arrow-left me-1"></i>Quay lại đăng nhập
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
