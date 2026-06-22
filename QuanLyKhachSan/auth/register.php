<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

if (isset($_SESSION['user_id'])) {
    header($_SESSION['role'] == 'khach' ? "Location: ../index.php" : "Location: ../sodophong.php");
    exit;
}

$error = '';

if (isset($_POST['register'])) {
    $hoten    = $conn->real_escape_string(trim($_POST['hoten']));
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = $_POST['password'];
    $sdt      = $conn->real_escape_string(trim($_POST['sdt'] ?? ''));
    $email    = $conn->real_escape_string(trim($_POST['email'] ?? ''));
    $cccd     = $conn->real_escape_string(trim($_POST['cccd'] ?? ''));

    if (empty($hoten) || empty($username) || empty($password) || empty($cccd) || empty($email)) {
        $error = "Vui lòng điền đầy đủ thông tin bắt buộc!";
    } elseif (strlen($password) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự!";
    } else {
        // Kiểm tra tồn tại
        $check       = $conn->query("SELECT MaTK FROM TaiKhoan WHERE TenDangNhap = '$username'");
        $check_cccd  = $conn->query("SELECT MaKH FROM KhachHang WHERE CCCD = '$cccd'");
        $check_email = $conn->query("SELECT MaKH FROM KhachHang WHERE Email = '$email'");

        if ($check && $check->num_rows > 0) {
            $error = "Tên đăng nhập <strong>$username</strong> đã tồn tại!";
        } elseif ($check_cccd && $check_cccd->num_rows > 0) {
            $error = "Số CCCD <strong>$cccd</strong> đã được đăng ký!";
        } elseif ($check_email && $check_email->num_rows > 0) {
            $error = "Email <strong>$email</strong> đã được sử dụng!";
        } else {
            // Tạo mã OTP 6 số
            $otp = strval(random_int(100000, 999999));

            // Lưu thông tin tạm thời vào Session
            $_SESSION['otp_code']     = $otp;
            $_SESSION['otp_expiry']   = time() + 300; // 5 phút
            $_SESSION['otp_attempts'] = 0;
            $_SESSION['pending_reg']  = [
                'hoten'    => $hoten,
                'username' => $username,
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'sdt'      => $sdt,
                'email'    => $email,
                'cccd'     => $cccd,
            ];

            // Gửi OTP qua Email
            require_once '../mailer.php';
            if (sendOtpEmail($email, $hoten, $otp)) {
                header("Location: verify_otp.php");
                exit;
            } else {
                $error = "Không thể gửi mã OTP. Vui lòng kiểm tra lại email hoặc thử lại sau.";
                unset($_SESSION['otp_code'], $_SESSION['pending_reg']);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - K-Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1e3a5f;
            --accent: #2563eb;
            --glass: rgba(255, 255, 255, 0.95);
        }
        body {
            background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 50%, #818cf8 100%);
            background-attachment: fixed;
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif;
            padding: 20px;
        }
        .glass-panel {
            background: var(--glass);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 40px;
            width: 100%; max-width: 450px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.3);
        }
        .logo-box {
            border: 2.5px solid #1a1a1a; padding: 4px 12px;
            display: inline-block; margin-bottom: 20px;
        }
        .form-control {
            border-radius: 12px; padding: 12px 16px; border: 1.5px solid #e2e8f0;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border: none; padding: 14px; border-radius: 12px;
            font-weight: 700; transition: all 0.3s;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(37,99,235,0.3); }
    </style>
</head>
<body>
    <div class="glass-panel">
        <div class="text-center mb-4">
            <div class="logo-box">
                <span class="fw-bold fs-3" style="font-family: serif;">K-Hotel</span><br>
                <span style="font-size: 0.6rem; letter-spacing: 2px; font-weight: 800; border-top: 1.5px solid #1a1a1a; display: block;">FOR THE STAY</span>
            </div>
            <h4 class="fw-bold text-dark">Đăng ký tài khoản</h4>
            <p class="text-muted small">Xác thực OTP qua Email để hoàn tất</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger border-0 rounded-3 small p-3 mb-4">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label fw-bold small text-secondary">Họ và Tên <span class="text-danger">*</span></label>
                    <input type="text" name="hoten" class="form-control" placeholder="Nguyễn Văn A" required value="<?= htmlspecialchars($_POST['hoten'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold small text-secondary">Tên đăng nhập <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" placeholder="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold small text-secondary">Số CCCD <span class="text-danger">*</span></label>
                    <input type="text" name="cccd" class="form-control" placeholder="001..." required value="<?= htmlspecialchars($_POST['cccd'] ?? '') ?>">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label fw-bold small text-secondary">Email nhận OTP <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="mail@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label fw-bold small text-secondary">Số điện thoại</label>
                    <input type="text" name="sdt" class="form-control" placeholder="09..." value="<?= htmlspecialchars($_POST['sdt'] ?? '') ?>">
                </div>
                <div class="col-md-12 mb-4">
                    <label class="form-label fw-bold small text-secondary">Mật khẩu <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" placeholder="Tối thiểu 6 ký tự" required minlength="6">
                </div>
            </div>
            
            <button type="submit" name="register" class="btn btn-primary w-100">
                <i class="fa-solid fa-paper-plane me-2"></i>GỬI MÃ XÁC THỰC OTP
            </button>
            
            <div class="text-center mt-4">
                <span class="text-muted small">Đã có tài khoản?</span>
                <a href="login.php" class="text-accent text-decoration-none fw-bold small ms-1">Đăng nhập</a>
            </div>
        </form>
    </div>
</body>
</html>
