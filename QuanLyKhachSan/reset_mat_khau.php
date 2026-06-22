<?php
/**
 * K-HOTEL — Đặt lại mật khẩu
 * Bước 2: Khách bấm link trong email → nhập mật khẩu mới
 */
require_once 'config/database.php';

$token   = trim($_GET['token'] ?? '');
$error   = '';
$success = '';
$valid   = false;
$user    = null;

// Kiểm tra token hợp lệ
if (empty($token)) {
    $error = 'Link không hợp lệ. Vui lòng yêu cầu lại.';
} else {
    $safe_token = $conn->real_escape_string($token);
    $now        = date('Y-m-d H:i:s');

    $res = $conn->query("
        SELECT prt.MaTK, prt.Token, tk.TenDangNhap, tk.HoTen
        FROM PasswordResetTokens prt
        JOIN TaiKhoan tk ON tk.MaTK = prt.MaTK
        WHERE prt.Token = '$safe_token'
          AND prt.ThoiGianHetHan > '$now'
        LIMIT 1
    ");

    if (!$res || $res->num_rows == 0) {
        $error = 'Link đã hết hạn hoặc không hợp lệ. Vui lòng <a href="quen_mat_khau.php" class="alert-link">yêu cầu link mới</a>.';
    } else {
        $valid = true;
        $user  = $res->fetch_assoc();
    }
}

// Xử lý form đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid) {
    $new_pass     = $_POST['new_password']     ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (strlen($new_pass) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
    } elseif ($new_pass !== $confirm_pass) {
        $error = 'Xác nhận mật khẩu không khớp.';
    } else {
        // Project của bạn dùng password_hash trong query (hoặc MD5? Trong auth/login.php thường dùng password_verify nếu là PHP mới)
        // Tôi sẽ dùng password_hash để an toàn nhất.
        $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
        $matk   = (int)$user['MaTK'];
        $conn->query("UPDATE TaiKhoan SET MatKhau = '$hashed' WHERE MaTK = $matk");
        
        // Xóa token sau khi dùng (dùng 1 lần)
        $conn->query("DELETE FROM PasswordResetTokens WHERE MaTK = $matk");
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt Lại Mật Khẩu — K-Hotel</title>
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
        .strength-bar { height: 6px; border-radius: 99px; background: #e2e8f0; overflow: hidden; margin-top: 8px; }
        .strength-fill { height: 100%; border-radius: 99px; transition: all 0.3s; width: 0%; }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="hotel-icon">
                        <i class="fa-solid fa-key text-white fs-3"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">Đặt lại mật khẩu</h4>
                    <?php if ($valid && !$success): ?>
                    <p class="text-muted small">Xin chào <strong><?= htmlspecialchars($user['HoTen']) ?></strong>, nhập mật khẩu mới của bạn</p>
                    <?php endif; ?>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger rounded-3 border-0 py-3">
                    <i class="fa-solid fa-circle-exclamation me-2"></i><?= $error ?>
                </div>
                <?php if (!$valid): ?>
                <div class="text-center mt-2">
                    <a href="quen_mat_khau.php" class="btn btn-outline-primary rounded-3 px-4">
                        <i class="fa-solid fa-rotate-left me-2"></i>Yêu cầu link mới
                    </a>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="text-center py-3">
                    <div style="width:72px;height:72px;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                        <i class="fa-solid fa-check text-success fs-3"></i>
                    </div>
                    <h5 class="fw-bold text-success mb-2">Đổi mật khẩu thành công!</h5>
                    <p class="text-muted small mb-4">Bạn có thể đăng nhập bằng mật khẩu mới ngay bây giờ.</p>
                    <a href="index.php" class="btn btn-primary w-100">
                        <i class="fa-solid fa-right-to-bracket me-2"></i>Đăng nhập ngay
                    </a>
                </div>

                <?php elseif ($valid): ?>
                <form method="POST">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary small">Mật khẩu mới</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2 border-end-0 rounded-start-3">
                                <i class="fa-solid fa-lock text-muted"></i>
                            </span>
                            <input type="password" name="new_password" id="new_password"
                                   class="form-control border-start-0"
                                   placeholder="Tối thiểu 6 ký tự" required autofocus
                                   oninput="checkStrength(this.value)">
                            <button class="btn btn-light border border-start-0 rounded-end-3" type="button"
                                    onclick="togglePass('new_password', this)">
                                <i class="fa-solid fa-eye text-muted"></i>
                            </button>
                        </div>
                        <div class="strength-bar mt-2">
                            <div class="strength-fill" id="strength-fill"></div>
                        </div>
                        <small class="text-muted" id="strength-text"></small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-medium text-secondary small">Xác nhận mật khẩu</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2 border-end-0 rounded-start-3">
                                <i class="fa-solid fa-shield-halved text-muted"></i>
                            </span>
                            <input type="password" name="confirm_password" id="confirm_password"
                                   class="form-control border-start-0 rounded-end-3"
                                   placeholder="Nhập lại mật khẩu mới" required>
                            <button class="btn btn-light border border-start-0 rounded-end-3" type="button"
                                    onclick="togglePass('confirm_password', this)">
                                <i class="fa-solid fa-eye text-muted"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Lưu mật khẩu mới
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

<script>
function togglePass(id, btn) {
    const input = document.getElementById(id);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function checkStrength(val) {
    const fill = document.getElementById('strength-fill');
    const text = document.getElementById('strength-text');
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { pct: '0%',   color: '#e2e8f0', label: '' },
        { pct: '25%',  color: '#ef4444', label: 'Rất yếu' },
        { pct: '50%',  color: '#f97316', label: 'Yếu' },
        { pct: '75%',  color: '#eab308', label: 'Trung bình' },
        { pct: '90%',  color: '#22c55e', label: 'Mạnh' },
        { pct: '100%', color: '#16a34a', label: 'Rất mạnh' },
    ];
    fill.style.width      = levels[score].pct;
    fill.style.background = levels[score].color;
    text.textContent      = levels[score].label;
    text.style.color      = levels[score].color;
}
</script>
</body>
</html>
