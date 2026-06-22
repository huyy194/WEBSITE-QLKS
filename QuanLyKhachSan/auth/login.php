<?php
session_start();
require_once '../config/database.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'khach') header("Location: ../index.php");
    else header("Location: ../sodophong.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // trim() để tránh khoảng trắng vô tình
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = $_POST['password'];
    // BINARY: so sánh chính xác hoa/thường — 'Admin' khác 'admin'
    $sql = "SELECT * FROM TaiKhoan WHERE BINARY TenDangNhap = '$username'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        error_log("Login attempt for user: " . $user['TenDangNhap']);
        // Chỉ dùng password_verify (bcrypt) — bỏ fallback plaintext vì không an toàn
        if (password_verify($password, $user['MatKhau'])) {
            error_log("Password verify success for user: " . $user['TenDangNhap']);
            $_SESSION['user_id'] = $user['MaTK'];
            $_SESSION['user_name'] = $user['HoTen'];
            $_SESSION['role'] = $user['VaiTro'];
            if ($user['VaiTro'] == 'khach') header("Location: ../index.php");
            else header("Location: ../sodophong.php");
            exit;
        } else {
            error_log("Password verify FAILED for user: " . $user['TenDangNhap']);
            $error = "Sai mật khẩu!";
        }
    } else {
        error_log("Username not found in DB: " . $username);
        $error = "Tên đăng nhập không tồn tại!";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập — K-Hotel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    /* ============================================================
       K-HOTEL LOGIN — Obsidian & Gold Luxury
    ============================================================ */
    :root {
        --obsidian:   #0C0C0E;
        --obsidian2:  #131318;
        --obsidian3:  #1A1A22;
        --obsidian4:  #22222D;
        --gold:       #C8A96E;
        --gold-light: #E2C98D;
        --gold-dim:   rgba(200,169,110,0.18);
        --gold-pale:  #F8F2E6;
        --cream:      #FAF8F3;
        --white:      #FFFFFF;
        --text-muted: rgba(255,255,255,0.38);
        --text-soft:  rgba(255,255,255,0.62);
        --text-main:  rgba(255,255,255,0.88);
        --border-dim: rgba(255,255,255,0.07);
        --border-gold:rgba(200,169,110,0.25);
        --radius:     14px;
        --radius-sm:  8px;
        --serif:      'Cormorant Garamond', Georgia, serif;
        --sans:       'DM Sans', system-ui, sans-serif;
        --ease:       cubic-bezier(0.4, 0, 0.2, 1);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
        height: 100%;
        font-family: var(--sans);
        background: var(--obsidian);
        color: var(--text-main);
        -webkit-font-smoothing: antialiased;
        overflow: hidden;
    }

    /* ===== LAYOUT ===== */
    .page {
        display: flex;
        min-height: 100vh;
        position: relative;
    }

    /* ===== BACKGROUND TEXTURE ===== */
    .bg-texture {
        position: fixed;
        inset: 0;
        pointer-events: none;
        z-index: 0;
    }

    /* Noise grain overlay */
    .bg-texture::before {
        content: '';
        position: absolute;
        inset: 0;
        background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.035'/%3E%3C/svg%3E");
        opacity: 0.4;
    }

    /* Gold glow blobs */
    .bg-texture::after {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 600px 500px at 15% 80%, rgba(200,169,110,0.07) 0%, transparent 70%),
            radial-gradient(ellipse 400px 400px at 85% 20%, rgba(200,169,110,0.05) 0%, transparent 70%);
    }

    /* ===== LEFT VISUAL PANEL ===== */
    .panel-left {
        flex: 1;
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 0 0 60px 60px;
    }

    /* Hero image */
    .panel-left-img {
        position: absolute;
        inset: 0;
        z-index: 1;
    }

    .panel-left-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        filter: brightness(0.45) saturate(0.8);
        transform: scale(1.03);
        animation: slowZoom 20s ease-in-out infinite alternate;
    }

    @keyframes slowZoom {
        from { transform: scale(1.03); }
        to   { transform: scale(1.08); }
    }

    /* Gradient overlays on image */
    .panel-left-img::after {
        content: '';
        position: absolute;
        inset: 0;
        background:
            linear-gradient(to right, rgba(12,12,14,0.85) 0%, transparent 60%),
            linear-gradient(to top,   rgba(12,12,14,0.9)  0%, transparent 50%);
    }

    /* Left content (on top of image) */
    .panel-left-content {
        position: relative;
        z-index: 2;
        max-width: 500px;
    }

    /* Decorative line */
    .deco-line {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 28px;
    }

    .deco-line span {
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 3.5px;
        text-transform: uppercase;
        color: var(--gold);
    }

    .deco-line::before {
        content: '';
        width: 32px;
        height: 1px;
        background: var(--gold);
        opacity: 0.6;
    }

    /* Logo */
    .brand-mark {
        margin-bottom: 20px;
    }

    .brand-mark .symbol {
        font-family: var(--serif);
        font-size: 13px;
        color: var(--gold);
        letter-spacing: 4px;
        text-transform: uppercase;
        display: block;
        margin-bottom: 6px;
        opacity: 0.7;
    }

    .brand-mark .name {
        font-family: var(--serif);
        font-size: 52px;
        font-weight: 600;
        color: var(--white);
        line-height: 1;
        letter-spacing: -1px;
    }

    .brand-mark .name em {
        color: var(--gold);
        font-style: italic;
    }

    .panel-left-content p {
        font-size: 15px;
        font-weight: 300;
        color: rgba(255,255,255,0.55);
        line-height: 1.75;
        max-width: 380px;
        margin-bottom: 36px;
    }

    /* Stat row */
    .stat-row {
        display: flex;
        gap: 36px;
        padding-top: 28px;
        border-top: 1px solid rgba(255,255,255,0.08);
    }

    .stat-item .val {
        font-family: var(--serif);
        font-size: 30px;
        font-weight: 600;
        color: var(--gold-light);
        line-height: 1;
        margin-bottom: 4px;
    }

    .stat-item .lbl {
        font-size: 10px;
        font-weight: 500;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 1.5px;
    }

    /* Floating award badge */
    .award-badge {
        position: absolute;
        top: 44px;
        left: 44px;
        z-index: 3;
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(12,12,14,0.65);
        backdrop-filter: blur(12px);
        border: 1px solid var(--border-gold);
        border-radius: var(--radius);
        padding: 12px 16px;
    }

    .award-badge .ab-icon {
        width: 36px; height: 36px;
        border-radius: 8px;
        background: var(--gold-dim);
        display: flex; align-items: center; justify-content: center;
        color: var(--gold);
        font-size: 16px;
        flex-shrink: 0;
    }

    .award-badge .ab-text {
        font-size: 11px;
        font-weight: 600;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .award-badge .ab-text strong {
        display: block;
        color: var(--gold);
        font-size: 13px;
        font-weight: 700;
    }

    /* ===== RIGHT FORM PANEL ===== */
    .panel-right {
        width: 480px;
        flex-shrink: 0;
        background: var(--obsidian2);
        border-left: 1px solid var(--border-dim);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 48px 44px;
        position: relative;
        z-index: 1;
        overflow-y: auto;
    }

    /* Subtle top gold accent */
    .panel-right::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent, var(--gold), transparent);
        opacity: 0.6;
    }

    .form-wrap {
        width: 100%;
        max-width: 360px;
    }

    /* Form header */
    .form-eyebrow {
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: var(--gold);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-eyebrow::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--gold);
        opacity: 0.3;
    }

    .form-title {
        font-family: var(--serif);
        font-size: 42px;
        font-weight: 600;
        color: var(--white);
        line-height: 1.05;
        margin-bottom: 6px;
    }

    .form-subtitle {
        font-size: 13.5px;
        font-weight: 300;
        color: var(--text-muted);
        margin-bottom: 36px;
        line-height: 1.6;
    }

    /* Error alert */
    .error-alert {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(239,68,68,0.08);
        border: 1px solid rgba(239,68,68,0.2);
        border-left: 3px solid #ef4444;
        border-radius: var(--radius-sm);
        padding: 12px 14px;
        margin-bottom: 24px;
        font-size: 13px;
        color: #fca5a5;
        animation: shakeIn 0.4s var(--ease);
    }

    @keyframes shakeIn {
        0%   { transform: translateX(-6px); opacity: 0; }
        40%  { transform: translateX(4px); }
        70%  { transform: translateX(-2px); }
        100% { transform: translateX(0);   opacity: 1; }
    }

    .error-alert i { color: #ef4444; font-size: 14px; flex-shrink: 0; }

    /* Form fields */
    .field {
        margin-bottom: 18px;
        position: relative;
    }

    .field-label {
        display: block;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 8px;
        transition: color 0.2s;
    }

    .field:focus-within .field-label { color: var(--gold); }

    .input-row {
        display: flex;
        align-items: center;
        background: var(--obsidian3);
        border: 1px solid var(--border-dim);
        border-radius: var(--radius-sm);
        overflow: hidden;
        transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    }

    .input-row:focus-within {
        border-color: var(--border-gold);
        box-shadow: 0 0 0 3px rgba(200,169,110,0.08);
        background: var(--obsidian4);
    }

    .input-icon {
        width: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgba(255,255,255,0.2);
        font-size: 14px;
        flex-shrink: 0;
        transition: color 0.2s;
    }

    .input-row:focus-within .input-icon { color: var(--gold); }

    .input-row input {
        flex: 1;
        border: none;
        outline: none;
        background: transparent;
        font-family: var(--sans);
        font-size: 14px;
        font-weight: 400;
        color: var(--text-main);
        padding: 14px 14px 14px 0;
        min-width: 0;
    }

    .input-row input::placeholder { color: rgba(255,255,255,0.18); }

    .pw-toggle {
        width: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: rgba(255,255,255,0.2);
        font-size: 14px;
        flex-shrink: 0;
        transition: color 0.2s;
    }

    .pw-toggle:hover { color: var(--gold); }

    /* Forgot link */
    .forgot-wrap {
        text-align: right;
        margin-bottom: 28px;
        margin-top: -6px;
    }

    .forgot-link {
        font-size: 12px;
        color: rgba(255,255,255,0.3);
        text-decoration: none;
        transition: color 0.2s;
    }

    .forgot-link:hover { color: var(--gold); }

    /* Submit button */
    .btn-submit {
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, var(--gold) 0%, #b8922a 100%);
        border: none;
        border-radius: var(--radius-sm);
        color: var(--obsidian);
        font-family: var(--sans);
        font-size: 13.5px;
        font-weight: 700;
        letter-spacing: 0.5px;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        transition: all 0.3s var(--ease);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-submit::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 100%);
        opacity: 0;
        transition: opacity 0.3s;
    }

    .btn-submit:hover::before { opacity: 1; }
    .btn-submit:hover { box-shadow: 0 8px 28px rgba(200,169,110,0.35); transform: translateY(-1px); }
    .btn-submit:active { transform: scale(0.98); }

    .btn-submit span, .btn-submit i { position: relative; z-index: 1; }

    /* Loading state */
    .btn-submit.loading { pointer-events: none; opacity: 0.8; }
    .btn-submit .spinner {
        display: none;
        width: 16px; height: 16px;
        border: 2px solid rgba(13,13,14,0.3);
        border-top-color: var(--obsidian);
        border-radius: 50%;
        animation: spin 0.7s linear infinite;
    }
    .btn-submit.loading .spinner { display: block; }
    .btn-submit.loading .btn-icon { display: none; }

    @keyframes spin { to { transform: rotate(360deg); } }

    /* Divider */
    .divider {
        display: flex;
        align-items: center;
        gap: 14px;
        color: rgba(255,255,255,0.15);
        font-size: 11px;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin: 24px 0;
    }

    .divider::before, .divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: rgba(255,255,255,0.07);
    }

    /* Register + back links */
    .form-footer {
        text-align: center;
    }

    .form-footer p {
        font-size: 13px;
        color: var(--text-muted);
        margin-bottom: 14px;
    }

    .form-footer a.register-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: transparent;
        border: 1px solid var(--border-gold);
        color: var(--gold);
        padding: 10px 24px;
        border-radius: var(--radius-sm);
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s var(--ease);
        letter-spacing: 0.3px;
    }

    .form-footer a.register-btn:hover {
        background: var(--gold-dim);
        border-color: var(--gold);
    }

    .back-home {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 20px;
        font-size: 12px;
        color: rgba(255,255,255,0.2);
        text-decoration: none;
        transition: color 0.2s;
    }

    .back-home:hover { color: rgba(255,255,255,0.5); }

    /* ===== ANIMATED ENTRY ===== */
    .panel-left-content > * {
        opacity: 0;
        transform: translateY(16px);
        animation: fadeUp 0.7s var(--ease) forwards;
    }

    .panel-left-content .deco-line   { animation-delay: 0.1s; }
    .panel-left-content .brand-mark  { animation-delay: 0.2s; }
    .panel-left-content p            { animation-delay: 0.35s; }
    .panel-left-content .stat-row    { animation-delay: 0.5s; }

    .form-wrap > * {
        opacity: 0;
        transform: translateY(14px);
        animation: fadeUp 0.6s var(--ease) forwards;
    }

    .form-eyebrow  { animation-delay: 0.15s; }
    .form-title    { animation-delay: 0.25s; }
    .form-subtitle { animation-delay: 0.35s; }
    .error-alert   { animation-delay: 0s !important; }
    .field:nth-child(1)  { animation-delay: 0.45s; }
    .field:nth-child(2)  { animation-delay: 0.52s; }
    .forgot-wrap   { animation-delay: 0.58s; }
    .btn-submit    { animation-delay: 0.65s; }
    .divider       { animation-delay: 0.72s; }
    .form-footer   { animation-delay: 0.78s; }

    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(14px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .award-badge {
        opacity: 0;
        animation: fadeUp 0.7s 0.4s var(--ease) forwards;
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 860px) {
        html, body { overflow-y: auto; }
        .panel-left { display: none; }
        .panel-right { width: 100%; min-height: 100vh; }
    }
    </style>
</head>
<body>

<div class="bg-texture"></div>

<div class="page">

    <!-- ===== LEFT VISUAL PANEL ===== -->
    <div class="panel-left">

        <!-- Background image -->
        <div class="panel-left-img">
            <img src="../assets/img/premium_luxury_resort_hero_1777033980264.png" alt="K-Hotel">
        </div>

        <!-- Floating award badge -->
        <div class="award-badge">
            <div class="ab-icon"><i class="fa-solid fa-award"></i></div>
            <div class="ab-text">
                <strong>5 Sao Quốc Tế</strong>
                Được công nhận năm 2024
            </div>
        </div>

        <!-- Bottom content -->
        <div class="panel-left-content">
            <div class="deco-line">
                <span>Nghỉ dưỡng đẳng cấp</span>
            </div>

            <div class="brand-mark">
                <span class="symbol">✦ &nbsp; Est. 2012</span>
                <div class="name">K-<em>Hotel</em></div>
            </div>

            <p>Nơi mỗi chi tiết được kiến tạo để đánh thức trọn vẹn mọi giác quan — đặt phòng, theo dõi lịch sử và nhận ưu đãi độc quyền ngay tại đây.</p>

            <div class="stat-row">
                <div class="stat-item">
                    <div class="val">12+</div>
                    <div class="lbl">Năm tỏa sáng</div>
                </div>
                <div class="stat-item">
                    <div class="val">60+</div>
                    <div class="lbl">Phòng cao cấp</div>
                </div>
                <div class="stat-item">
                    <div class="val">4.8★</div>
                    <div class="lbl">Đánh giá TB</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== RIGHT FORM PANEL ===== -->
    <div class="panel-right">
        <div class="form-wrap">

            <div class="form-eyebrow">Cổng thành viên</div>
            <h1 class="form-title">Chào<br>Mừng<br>Trở Lại</h1>
            <p class="form-subtitle">Nhập thông tin tài khoản để tiếp tục trải nghiệm dịch vụ K-Hotel.</p>

            <?php if ($error): ?>
            <div class="error-alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" id="loginForm" onsubmit="handleSubmit(this)">

                <div class="field">
                    <label class="field-label">Tên đăng nhập</label>
                    <div class="input-row">
                        <div class="input-icon"><i class="fa-regular fa-user"></i></div>
                        <input
                            type="text"
                            name="username"
                            placeholder="Nhập tên đăng nhập"
                            required
                            autocomplete="username"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">Mật khẩu</label>
                    <div class="input-row">
                        <div class="input-icon"><i class="fa-solid fa-lock"></i></div>
                        <input
                            type="password"
                            name="password"
                            id="pwInput"
                            placeholder="Nhập mật khẩu"
                            required
                            autocomplete="current-password"
                        >
                        <div class="pw-toggle" onclick="togglePw()" title="Hiện / Ẩn mật khẩu">
                            <i class="fa-regular fa-eye" id="eyeIcon"></i>
                        </div>
                    </div>
                </div>

                <div class="forgot-wrap">
                    <a href="../quen_mat_khau.php" class="forgot-link">Quên mật khẩu?</a>
                </div>

                <button type="submit" name="login" class="btn-submit" id="submitBtn">
                    <div class="spinner"></div>
                    <i class="fa-solid fa-right-to-bracket btn-icon"></i>
                    <span>Đăng Nhập</span>
                </button>
            </form>

            <div class="divider">hoặc</div>

            <div class="form-footer">
                <p>Chưa có tài khoản?</p>
                <a href="register.php" class="register-btn">
                    <i class="fa-solid fa-user-plus"></i> Đăng ký ngay
                </a>
                <br>
                <a href="../index.php" class="back-home">
                    <i class="fa-solid fa-arrow-left"></i> Về trang chủ K-Hotel
                </a>
            </div>

        </div>
    </div>

</div>

<script>
function togglePw() {
    const input = document.getElementById('pwInput');
    const icon  = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fa-regular fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fa-regular fa-eye';
    }
}

function handleSubmit(form) {
    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading');
    // Form sẽ submit bình thường, chỉ show loading spinner
}
</script>

</body>
</html>
