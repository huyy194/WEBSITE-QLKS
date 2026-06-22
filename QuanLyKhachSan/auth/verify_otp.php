<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Kiểm tra nếu không có dữ liệu chờ đăng ký
if (empty($_SESSION['pending_reg']) || empty($_SESSION['otp_code'])) {
    header("Location: register.php");
    exit;
}

$error   = '';
$success = '';
$reg     = $_SESSION['pending_reg'];

// Che email: abcd***@gmail.com
$email_parts = explode('@', $reg['email']);
$masked_email = substr($email_parts[0], 0, 3) . '***@' . $email_parts[1];

// 1. Gửi lại OTP
if (isset($_GET['resend'])) {
    if (time() < ($_SESSION['otp_resend_wait'] ?? 0)) {
        $error = "Vui lòng đợi 1 phút trước khi yêu cầu gửi lại mã.";
    } else {
        $otp = strval(random_int(100000, 999999));
        $_SESSION['otp_code']        = $otp;
        $_SESSION['otp_expiry']      = time() + 300;
        $_SESSION['otp_attempts']    = 0;
        $_SESSION['otp_resend_wait'] = time() + 60;

        require_once '../mailer.php';
        if (sendOtpEmail($reg['email'], $reg['hoten'], $otp)) {
            $success = "Mã xác thực mới đã được gửi tới <strong>$masked_email</strong>";
        } else {
            $error = "Gửi email thất bại. Vui lòng thử lại sau.";
        }
    }
}

// 2. Xác thực OTP
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['otp'])) {
    $input_otp = trim($_POST['otp']);

    if (time() > $_SESSION['otp_expiry']) {
        $error = "Mã OTP đã hết hạn. Vui lòng nhấn gửi lại mã.";
    } elseif ($_SESSION['otp_attempts'] >= 5) {
        $error = "Bạn đã nhập sai quá 5 lần. Vui lòng yêu cầu mã mới.";
    } elseif ($input_otp !== $_SESSION['otp_code']) {
        $_SESSION['otp_attempts']++;
        $error = "Mã OTP không chính xác. Bạn còn " . (5 - $_SESSION['otp_attempts']) . " lần thử.";
    } else {
        // OTP chính xác -> Tạo tài khoản chính thức
        $conn->begin_transaction();
        try {
            $sql_tk = "INSERT INTO TaiKhoan (TenDangNhap, MatKhau, HoTen, VaiTro) VALUES ('{$reg['username']}', '{$reg['password']}', '{$reg['hoten']}', 'khach')";
            if ($conn->query($sql_tk)) {
                $matk = $conn->insert_id;
                $sdt_val = !empty($reg['sdt']) ? $reg['sdt'] : 'Chưa cập nhật';
                $sql_kh = "INSERT INTO KhachHang (MaTK, HoTen, CCCD, SDT, Email) VALUES ($matk, '{$reg['hoten']}', '{$reg['cccd']}', '$sdt_val', '{$reg['email']}')";
                
                if ($conn->query($sql_kh)) {
                    $makh = $conn->insert_id;
                    
                    // Tạo ĐÚNG 1 voucher khách hàng mới (kiểm tra chưa có trước)
                    $existing_new_vc = (int)$conn->query("SELECT COUNT(*) as cnt FROM Voucher WHERE MaKH = $makh AND TenVoucher = 'Khách hàng mới'")->fetch_assoc()['cnt'];
                    if ($existing_new_vc == 0) {
                        $code_vc = 'NEW-' . strtoupper(substr(md5(uniqid($makh, true)), 0, 6));
                        $ngay_het_han = date('Y-m-d', strtotime('+30 days'));
                        $sql_vc = "INSERT INTO Voucher (MaKH, Code, TenVoucher, LoaiGiam, GiaTriGiam, GiaTriToiThieu, NgayBatDau, NgayHetHan, GioiHanDung, TrangThai, GhiChu) 
                                   VALUES ($makh, '$code_vc', 'Khách hàng mới', 'phantram', 10, 0, CURDATE(), '$ngay_het_han', 1, 'active', 'Voucher chào mừng - chỉ dùng 1 lần cho khách hàng mới.')";
                        $conn->query($sql_vc);
                    }

                    $conn->commit();
                    
                    // Xóa session OTP
                    unset($_SESSION['otp_code'], $_SESSION['otp_expiry'], $_SESSION['otp_attempts'], $_SESSION['pending_reg']);
                    
                    // Login tự động
                    $_SESSION['user_id']   = $matk;
                    $_SESSION['user_name'] = $reg['hoten'];
                    $_SESSION['role']      = 'khach';
                    
                    header("Location: ../index.php");
                    exit;
                }
            }
            throw new Exception("Lỗi database: " . $conn->error);
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Lỗi tạo tài khoản: " . $e->getMessage();
        }
    }
}

$remaining = max(0, $_SESSION['otp_expiry'] - time());
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xác thực OTP - K-Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 50%, #818cf8 100%);
            display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: system-ui;
        }
        .glass-panel {
            background: white; border-radius: 24px; padding: 40px; width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        .otp-box {
            display: flex; gap: 10px; justify-content: center; margin: 30px 0;
        }
        .otp-input {
            width: 50px; height: 60px; text-align: center; font-size: 24px; font-weight: bold; border: 2px solid #e2e8f0; border-radius: 12px;
        }
        .otp-input:focus { border-color: #2563eb; outline: none; box-shadow: 0 0 0 4px rgba(37,99,235,0.1); }
        #timer { font-weight: bold; color: #2563eb; font-size: 1.2rem; }
    </style>
</head>
<body>
<div class="glass-panel text-center">
    <h3 class="fw-bold">Xác thực Email</h3>
    <p class="text-muted">Mã OTP đã được gửi tới <strong><?= $masked_email ?></strong></p>

    <?php if ($error): ?> <div class="alert alert-danger small"><?= $error ?></div> <?php endif; ?>
    <?php if ($success): ?> <div class="alert alert-success small"><?= $success ?></div> <?php endif; ?>

    <form method="POST" id="otpForm">
        <div class="otp-box">
            <?php for($i=0; $i<6; $i++): ?>
                <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
            <?php endfor; ?>
        </div>
        <input type="hidden" name="otp" id="full_otp">

        <div class="mb-4">
            <span class="text-muted">Mã hết hạn sau: </span>
            <span id="timer"><?= gmdate('i:s', $remaining) ?></span>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-pill">XÁC NHẬN ĐĂNG KÝ</button>
    </form>

    <div class="mt-4">
        <p class="small text-muted">Không nhận được mã? 
            <a href="?resend=1" class="fw-bold text-primary text-decoration-none">Gửi lại ngay</a>
        </p>
        <a href="register.php" class="small text-secondary text-decoration-none"><i class="fa-solid fa-arrow-left me-1"></i>Quay lại</a>
    </div>
</div>

<script>
    const inputs = document.querySelectorAll('.otp-input');
    const fullOtp = document.getElementById('full_otp');

    inputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            if (e.target.value.length > 0 && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
            updateFullOtp();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && e.target.value.length === 0 && index > 0) {
                inputs[index - 1].focus();
            }
        });
    });

    function updateFullOtp() {
        fullOtp.value = Array.from(inputs).map(i => i.value).join('');
    }

    // Timer
    let seconds = <?= $remaining ?>;
    const timerDisplay = document.getElementById('timer');
    const interval = setInterval(() => {
        seconds--;
        if (seconds <= 0) {
            clearInterval(interval);
            timerDisplay.textContent = "00:00";
            return;
        }
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        timerDisplay.textContent = `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
    }, 1000);
</script>
</body>
</html>
