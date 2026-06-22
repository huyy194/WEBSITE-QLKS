<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once 'config/database.php';

// ===== RECOMMENDATION SYSTEM (Đã tối ưu cho khách mới) =====
$recommendations = [];
$userid = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? '';

if ($userid > 0 && $user_role == 'khach') {
    // 1. Thử lấy gợi ý dựa trên lịch sử
    $sql_history = "SELECT lp.MaLoai, lp.GiaPhong, lp.TienNghi, lp.SoNguoiToiDa 
                    FROM DatPhong dp 
                    JOIN KhachHang kh ON dp.MaKH = kh.MaKH 
                    JOIN Phong p ON dp.MaPhong = p.MaPhong 
                    JOIN LoaiPhong lp ON p.MaLoai = lp.MaLoai
                    WHERE kh.MaTK = $userid AND dp.TrangThai IN ('Đang ở', 'Đã thanh toán', 'Đã thanh toán (Online)')
                    ORDER BY dp.MaDP DESC LIMIT 10";
    $res_history = $conn->query($sql_history);
    
    if ($res_history && $res_history->num_rows > 0) {
        $avg_price = 0; $max_guest = 0; $amenities = []; $count = 0;
        while ($row = $res_history->fetch_assoc()) {
            $avg_price += $row['GiaPhong'];
            if ($row['SoNguoiToiDa'] > $max_guest) $max_guest = $row['SoNguoiToiDa'];
            $items = array_map('trim', explode(',', $row['TienNghi']));
            foreach($items as $i) if(!empty($i)) $amenities[$i] = ($amenities[$i] ?? 0) + 1;
            $count++;
        }
        $avg_price = $avg_price / $count;
        arsort($amenities);
        $top_amenities = array_slice(array_keys($amenities), 0, 2);
        
        $price_min = $avg_price * 0.7;
        $price_max = $avg_price * 1.4;
        $amenity_sql_parts = ["1"]; // Base score
        foreach($top_amenities as $a) {
            $amenity_sql_parts[] = "IF(lp.TienNghi LIKE '%" . $conn->real_escape_string($a) . "%', 3, 0)";
        }
        $amenity_score_sql = implode(" + ", $amenity_sql_parts);
        
        $sql_sug = "SELECT p.*, lp.TenLoai, lp.GiaPhong, lp.HinhAnh, lp.TienNghi, lp.SoNguoiToiDa,
                    (IF(lp.GiaPhong BETWEEN $price_min AND $price_max, 5, 0) +
                     IF(lp.SoNguoiToiDa >= $max_guest, 3, 0) + 
                     ($amenity_score_sql)) as SimilarityScore
                    FROM Phong p JOIN LoaiPhong lp ON p.MaLoai = lp.MaLoai 
                    WHERE p.TrangThai = 'Trống'
                    ORDER BY SimilarityScore DESC, lp.GiaPhong ASC LIMIT 4";
        $rec_result = $conn->query($sql_sug);
        if ($rec_result) {
            while ($r = $rec_result->fetch_assoc()) $recommendations[] = $r;
        }
    }
}

// 2. Nếu vẫn chưa đủ 4 phòng (khách mới hoặc ít phòng trống tương tự), lấy thêm phòng trống cao cấp nhất
if (count($recommendations) < 4) {
    $needed = 4 - count($recommendations);
    $exclude_ids = ["'0'"];
    foreach($recommendations as $r) $exclude_ids[] = "'" . $conn->real_escape_string($r['MaPhong']) . "'";
    $exclude_str = implode(",", $exclude_ids);
    
    $res_fallback = $conn->query("SELECT p.*, lp.TenLoai, lp.GiaPhong, lp.HinhAnh, lp.TienNghi, lp.SoNguoiToiDa 
                                 FROM Phong p JOIN LoaiPhong lp ON p.MaLoai = lp.MaLoai 
                                 WHERE p.TrangThai = 'Trống' 
                                 AND p.MaPhong NOT IN ($exclude_str)
                                 ORDER BY lp.GiaPhong DESC LIMIT $needed");
    if ($res_fallback) {
        while ($r = $res_fallback->fetch_assoc()) $recommendations[] = $r;
    }
}

// ===== THỐNG KÊ NHANH (tính năng mới) =====
$stat_rooms = $conn->query("SELECT COUNT(*) as total FROM Phong WHERE TrangThai='Trống'")->fetch_assoc()['total'] ?? 0;
$stat_rating = 4.8; // có thể query từ bảng đánh giá nếu có
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K-Hotel — Nghỉ Dưỡng Đẳng Cấp</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">

<style>
/* ============================================================
   K-HOTEL — UPGRADED DESIGN SYSTEM
   Aesthetic: Dark Luxury — Obsidian & Champagne Gold
   ============================================================ */
:root {
    --obsidian: #0D0D0F;
    --obsidian2: #141418;
    --obsidian3: #1C1C22;
    --gold: #C8A96E;
    --gold-light: #E2C98D;
    --gold-pale: #F8F2E6;
    --cream: #FAF8F3;
    --sand: #F0EBE0;
    --text: #1A1A1F;
    --text2: #5A5A6A;
    --text3: #9090A8;
    --border: #E5E0D5;
    --white: #FFFFFF;
    --radius: 16px;
    --radius-sm: 8px;
    --shadow: 0 4px 32px rgba(13,13,15,0.08);
    --shadow-lg: 0 16px 60px rgba(13,13,15,0.16);
    --font-serif: 'Cormorant Garamond', Georgia, serif;
    --font-sans: 'Jost', system-ui, sans-serif;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

*, *::before, *::after { box-sizing: border-box; }

body {
    font-family: var(--font-sans);
    background: var(--cream);
    color: var(--text);
    font-weight: 400;
    -webkit-font-smoothing: antialiased;
    overflow-x: hidden;
}

/* ===== UTILITY ===== */
.font-serif { font-family: var(--font-serif); }
.text-gold { color: var(--gold); }
.bg-obsidian { background: var(--obsidian); }

@media (max-width: 991px) {
    .search-bar {
        flex-direction: column;
        border-radius: var(--radius);
        overflow: hidden;
    }
    .search-field {
        border-right: none;
        border-bottom: 1px solid var(--border);
        padding: 15px 20px;
    }
    .search-submit {
        border-radius: 0;
        padding: 15px;
        width: 100%;
    }
    .hero-stats {
        flex-wrap: wrap;
        padding: 20px;
    }
    .hero-stat {
        flex: 0 0 50%;
        border-right: none;
        margin-bottom: 10px;
    }
    .hero-stat:nth-child(odd) {
        border-right: 1px solid rgba(255,255,255,0.1);
    }
    .hero-title {
        font-size: 42px;
    }
    .hero-desc {
        font-size: 14px;
    }
    .about-features {
        grid-template-columns: 1fr;
    }
    .service-cards {
        grid-template-columns: 1fr;
    }
    .about-badge {
        position: static;
        margin-top: 20px;
    }
}

@media (max-width: 576px) {
    .hero-title {
        font-size: 32px;
    }
    .hero-stat {
        flex: 0 0 100%;
        border-right: none !important;
    }
}

/* ===== SCROLLBAR ===== */
::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

/* ===== HEADER INCLUDE (cải thiện styling cho header_customer.php) ===== */
body > nav, body > header { position: sticky; top: 0; z-index: 200; }

/* ===== PROMO RIBBON (thay thế promo banner cũ) ===== */
.promo-ribbon {
    background: var(--obsidian);
    color: rgba(255,255,255,0.75);
    font-size: 12px;
    font-weight: 500;
    letter-spacing: 1.5px;
    text-align: center;
    padding: 10px 16px;
    position: relative;
    overflow: hidden;
}
.promo-ribbon::before {
    content: '';
    position: absolute;
    left: 0; top: 0;
    width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent 0%, rgba(200,169,110,0.15) 50%, transparent 100%);
    animation: shimmer 4s infinite;
}
@keyframes shimmer { 0%{transform:translateX(-100%)} 100%{transform:translateX(100%)} }
.promo-ribbon a { color: var(--gold); font-weight: 600; text-decoration: none; letter-spacing: 0; }
.promo-ribbon a:hover { text-decoration: underline; }

/* ===== SEARCH BAR ===== */
.search-section {
    background: var(--white);
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
    z-index: 150;
    box-shadow: 0 2px 20px rgba(13,13,15,0.06);
}

.search-bar {
    display: flex;
    align-items: stretch;
    border: 1.5px solid var(--border);
    border-radius: 12px;
    overflow: visible;
    background: var(--white);
    transition: var(--transition);
    position: relative;
}

.search-bar:focus-within {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(200,169,110,0.12);
}

.search-field {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 18px;
    border-right: 1px solid var(--border);
    cursor: pointer;
    transition: var(--transition);
    position: relative;
    flex: 1;
}

.search-field:last-of-type { border-right: none; }
.search-field:hover { background: var(--cream); }

.search-field .sf-icon {
    color: var(--gold);
    font-size: 15px;
    flex-shrink: 0;
}

.search-field .sf-label {
    font-size: 10px;
    font-weight: 600;
    color: var(--text3);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 2px;
}

.search-field .sf-val {
    font-size: 13.5px;
    font-weight: 500;
    color: var(--text);
}

.search-field .sf-val.sf-empty { color: var(--text3); font-weight: 400; }

.search-field input[type="text"] {
    border: none;
    background: none;
    font-family: var(--font-sans);
    font-size: 13.5px;
    font-weight: 500;
    color: var(--text);
    outline: none;
    width: 100%;
}

.search-field input[type="text"]::placeholder { color: var(--text3); font-weight: 400; }

.search-submit {
    background: var(--obsidian);
    color: white;
    border: none;
    padding: 0 24px;
    font-family: var(--font-sans);
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.5px;
    cursor: pointer;
    border-radius: 0 10px 10px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: var(--transition);
    white-space: nowrap;
    min-width: 140px;
    justify-content: center;
}

.search-submit:hover { background: #1e3a5f; }
.search-submit i { font-size: 14px; }

/* ===== DATE PICKER (giữ logic, style lại) ===== */
.date-picker-overlay { display:none; position:fixed; inset:0; z-index:9998; }
.date-picker-overlay.show { display:block; }

.date-picker-popup {
    display: none;
    position: absolute;
    top: calc(100% + 12px);
    left: 50%;
    transform: translateX(-50%);
    z-index: 9999;
    background: #fff;
    border-radius: var(--radius);
    box-shadow: var(--shadow-lg);
    padding: 28px;
    min-width: 640px;
    border: 1px solid var(--border);
    animation: popIn .2s ease;
}
@keyframes popIn { from{opacity:0;transform:translateX(-50%) translateY(-8px)} to{opacity:1;transform:translateX(-50%) translateY(0)} }
.date-picker-popup.show { display: block; }

.dp-month-grid { display:grid; grid-template-columns:1fr 1fr; gap:36px; }
.dp-month-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
.dp-month-title { font-family:var(--font-serif); font-weight:600; font-size:1.1rem; color:var(--text); }
.dp-nav-btn { background:none; border:1.5px solid var(--border); cursor:pointer; color:var(--text2); padding:6px 10px; border-radius:8px; transition:var(--transition); font-size:12px; }
.dp-nav-btn:hover { border-color:var(--gold); color:var(--gold); }
.dp-weekdays { display:grid; grid-template-columns:repeat(7,1fr); text-align:center; margin-bottom:6px; }
.dp-weekday { font-size:10px; font-weight:700; color:var(--text3); padding:4px 0; text-transform:uppercase; letter-spacing:0.5px; }
.dp-days-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:2px; }
.dp-day { text-align:center; padding:9px 4px; cursor:pointer; border-radius:8px; font-size:13px; color:var(--text); transition:var(--transition); border:none; background:none; font-family:var(--font-sans); }
.dp-day:hover:not(.dp-disabled):not(.dp-selected):not(.dp-in-range) { background:var(--sand); }
.dp-day.dp-disabled { color:#d1d5db; cursor:not-allowed; }
.dp-day.dp-today { font-weight:700; color:var(--obsidian); }
.dp-day.dp-selected { background:var(--obsidian)!important; color:white!important; border-radius:8px; font-weight:600; }
.dp-day.dp-in-range { background:rgba(200,169,110,0.15); color:#7a5c20; border-radius:0; }
.dp-day.dp-range-start { border-radius:8px 0 0 8px!important; }
.dp-day.dp-range-end { border-radius:0 8px 8px 0!important; }
.dp-footer { display:flex; justify-content:space-between; align-items:center; margin-top:20px; padding-top:16px; border-top:1px solid var(--border); }
.dp-selected-display { font-size:13px; color:var(--text2); font-family:var(--font-sans); }
.dp-selected-display strong { color:var(--text); }

@media(max-width:660px) {
    .date-picker-popup { min-width:320px; padding:16px; }
    .dp-month-grid { grid-template-columns:1fr; gap:16px; }
    .dp-month-second { display:none; }
}

/* ===== GUEST PICKER (style lại) ===== */
.guest-picker-popup {
    display:none; position:absolute; top:calc(100% + 12px); right:0; z-index:9999;
    background:#fff; border-radius:var(--radius); box-shadow:var(--shadow-lg);
    padding:24px; min-width:340px; border:1px solid var(--border);
    animation: popIn .2s ease;
}
.guest-picker-popup.show { display:block; }
.gp-header { font-weight:700; font-size:11px; color:var(--text3); text-transform:uppercase; letter-spacing:1.5px; margin-bottom:16px; }
.gp-row { display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:1px solid var(--border); }
.gp-row:last-of-type { border-bottom:none; }
.gp-label-main { font-weight:600; font-size:14px; color:var(--text); }
.gp-label-sub { font-size:11px; color:var(--text3); margin-top:2px; }
.gp-cols { display:flex; gap:28px; }
.gp-col { display:flex; flex-direction:column; align-items:center; gap:6px; }
.gp-col-label { font-size:10px; font-weight:700; color:var(--text3); text-transform:uppercase; letter-spacing:0.5px; }
.gp-counter { display:flex; align-items:center; gap:10px; }
.gp-btn { width:32px; height:32px; border-radius:50%; border:1.5px solid var(--border); background:white; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:12px; color:var(--text); transition:var(--transition); }
.gp-btn:hover:not(:disabled) { border-color:var(--gold); color:var(--gold); }
.gp-btn:disabled { border-color:#e2e8f0; color:#cbd5e1; cursor:not-allowed; }
.gp-count { font-size:15px; font-weight:700; min-width:20px; text-align:center; color:var(--text); }
.gp-add-room { color:var(--obsidian); font-weight:600; font-size:13px; cursor:pointer; padding:12px 0; display:flex; align-items:center; gap:6px; transition:var(--transition); }
.gp-add-room:hover { color:var(--gold); }
.gp-note { font-size:11.5px; color:var(--text3); margin:8px 0 14px; }
.gp-footer { display:flex; gap:8px; }
.gp-done { flex:1; padding:10px; background:var(--obsidian); color:white; border:none; border-radius:8px; font-weight:600; font-size:13px; cursor:pointer; transition:var(--transition); font-family:var(--font-sans); }
.gp-done:hover { background:#1e3a5f; }
.gp-cancel { padding:10px 16px; background:white; color:var(--text2); border:1.5px solid var(--border); border-radius:8px; font-weight:500; font-size:13px; cursor:pointer; transition:var(--transition); font-family:var(--font-sans); }
.gp-cancel:hover { border-color:var(--text2); }

/* ===== HERO SECTION ===== */
.hero {
    position: relative;
    height: 100vh;
    min-height: 700px;
    max-height: 900px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hero-bg {
    position: absolute;
    inset: 0;
    z-index: 1;
}

.hero-bg img {
    width: 100%; height: 100%;
    object-fit: cover;
    transform: scale(1.05);
    transition: transform 8s ease;
}

.hero:hover .hero-bg img { transform: scale(1); }

.hero-overlay {
    position: absolute;
    inset: 0;
    z-index: 2;
    background: linear-gradient(
        180deg,
        rgba(13,13,15,0.3) 0%,
        rgba(13,13,15,0.45) 50%,
        rgba(13,13,15,0.75) 100%
    );
}

.hero-content {
    position: relative;
    z-index: 3;
    text-align: center;
    color: white;
    padding: 0 20px;
    max-width: 900px;
}

.hero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 20px;
    opacity: 0;
    animation: heroFadeUp .8s .2s forwards;
}

.hero-eyebrow::before, .hero-eyebrow::after {
    content: '';
    display: block;
    width: 40px;
    height: 1px;
    background: var(--gold);
    opacity: 0.6;
}

.hero-title {
    font-family: var(--font-serif);
    font-size: clamp(48px, 7vw, 90px);
    font-weight: 600;
    line-height: 1.05;
    margin-bottom: 20px;
    opacity: 0;
    animation: heroFadeUp .8s .4s forwards;
}

.hero-title .gold-line {
    color: var(--gold-light);
    font-style: italic;
}

.hero-desc {
    font-size: clamp(14px, 1.5vw, 17px);
    font-weight: 300;
    color: rgba(255,255,255,0.8);
    line-height: 1.7;
    max-width: 600px;
    margin: 0 auto 36px;
    opacity: 0;
    animation: heroFadeUp .8s .6s forwards;
}

.hero-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    flex-wrap: wrap;
    opacity: 0;
    animation: heroFadeUp .8s .8s forwards;
}

@keyframes heroFadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ===== SCROLL INDICATOR ===== */
.scroll-indicator {
    position: absolute;
    bottom: 32px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 3;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    opacity: 0;
    animation: heroFadeUp .8s 1.2s forwards;
}

.scroll-indicator span { font-size: 10px; color: rgba(255,255,255,0.5); letter-spacing: 2px; text-transform: uppercase; }

.scroll-line {
    width: 1px;
    height: 50px;
    background: linear-gradient(to bottom, rgba(255,255,255,0.5), transparent);
    animation: scrollPulse 2s infinite;
}

@keyframes scrollPulse {
    0%,100% { opacity: 0.5; transform: scaleY(1); }
    50% { opacity: 1; transform: scaleY(0.7); }
}

/* ===== HERO STATS STRIP ===== */
.hero-stats {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 4;
    background: rgba(13,13,15,0.75);
    backdrop-filter: blur(12px);
    border-top: 1px solid rgba(200,169,110,0.2);
    display: flex;
    align-items: center;
    padding: 16px 40px;
    gap: 0;
}

.hero-stat {
    flex: 1;
    text-align: center;
    padding: 8px 0;
    border-right: 1px solid rgba(255,255,255,0.1);
}
.hero-stat:last-child { border-right: none; }

.hero-stat .val {
    font-family: var(--font-serif);
    font-size: 28px;
    font-weight: 600;
    color: var(--gold-light);
    line-height: 1;
    margin-bottom: 4px;
}

.hero-stat .lbl { font-size: 10px; font-weight: 500; color: rgba(255,255,255,0.45); letter-spacing: 1px; text-transform: uppercase; }

/* ===== BUTTONS ===== */
.btn-gold {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--gold);
    color: var(--obsidian);
    padding: 14px 32px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.5px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: var(--transition);
    font-family: var(--font-sans);
}

.btn-gold:hover {
    background: var(--gold-light);
    color: var(--obsidian);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(200,169,110,0.35);
}

.btn-outline-white {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: transparent;
    color: white;
    padding: 13px 28px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    border: 1.5px solid rgba(255,255,255,0.4);
    cursor: pointer;
    text-decoration: none;
    transition: var(--transition);
    font-family: var(--font-sans);
}

.btn-outline-white:hover {
    border-color: white;
    background: rgba(255,255,255,0.1);
    color: white;
}

.btn-dark {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--obsidian);
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: var(--transition);
    font-family: var(--font-sans);
}

.btn-dark:hover { background: #1e3a5f; color: white; transform: translateY(-1px); }

/* ===== SECTION HEADERS ===== */
.section-eyebrow {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-eyebrow::after {
    content: '';
    flex: 1;
    max-width: 40px;
    height: 1px;
    background: var(--gold);
    opacity: 0.5;
}

.section-title {
    font-family: var(--font-serif);
    font-size: clamp(32px, 4vw, 52px);
    font-weight: 600;
    line-height: 1.1;
    color: var(--text);
    margin-bottom: 12px;
}

.section-desc {
    font-size: 15px;
    color: var(--text2);
    line-height: 1.7;
    max-width: 560px;
}

/* ===== ABOUT SECTION ===== */
.about-section {
    padding: 100px 0;
    background: var(--cream);
}

.about-img-wrap {
    position: relative;
}

.about-img {
    width: 100%;
    height: 560px;
    object-fit: cover;
    border-radius: var(--radius);
    box-shadow: var(--shadow-lg);
}

.about-badge {
    position: absolute;
    bottom: -20px;
    left: -20px;
    background: var(--white);
    border-radius: var(--radius);
    padding: 20px 24px;
    box-shadow: var(--shadow-lg);
    border-left: 4px solid var(--gold);
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 180px;
}

.about-badge .ab-num {
    font-family: var(--font-serif);
    font-size: 36px;
    font-weight: 700;
    color: var(--obsidian);
    line-height: 1;
}

.about-badge .ab-text {
    font-size: 12px;
    font-weight: 600;
    color: var(--text2);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.about-glow {
    position: absolute;
    top: -30px;
    right: -30px;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(200,169,110,0.2), transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.about-features {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-top: 32px;
}

.about-feat {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px;
    background: var(--white);
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    transition: var(--transition);
}

.about-feat:hover {
    border-color: var(--gold);
    box-shadow: 0 4px 16px rgba(200,169,110,0.12);
    transform: translateY(-2px);
}

.about-feat-icon {
    width: 36px; height: 36px;
    border-radius: 8px;
    background: var(--gold-pale);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gold);
    font-size: 15px;
    flex-shrink: 0;
}

.about-feat h6 { font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 2px; }
.about-feat p { font-size: 11.5px; color: var(--text2); margin: 0; }

/* ===== SERVICES / AMENITIES ===== */
.services-section {
    padding: 100px 0;
    background: var(--obsidian);
    position: relative;
    overflow: hidden;
}

.services-section::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--gold), transparent);
}

.services-section .section-title { color: white; }
.services-section .section-desc { color: rgba(255,255,255,0.5); }
.services-section .section-eyebrow { }

.service-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2px;
    margin-top: 48px;
    border-radius: var(--radius);
    overflow: hidden;
}

.service-card {
    position: relative;
    overflow: hidden;
    cursor: pointer;
    aspect-ratio: 4/5;
    background: var(--obsidian2);
}

.service-card img {
    width: 100%; height: 100%;
    object-fit: cover;
    transition: transform 0.6s ease;
    opacity: 0.6;
}

.service-card:hover img { transform: scale(1.05); opacity: 0.5; }

.service-card-content {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 28px 24px;
    background: linear-gradient(to top, rgba(13,13,15,0.9) 0%, transparent 60%);
    transition: var(--transition);
}

.service-card-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    border: 1px solid rgba(200,169,110,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gold);
    font-size: 18px;
    margin-bottom: 14px;
    background: rgba(13,13,15,0.5);
    backdrop-filter: blur(4px);
}

.service-card h4 {
    font-family: var(--font-serif);
    font-size: 22px;
    font-weight: 600;
    color: white;
    margin-bottom: 6px;
}

.service-card p {
    font-size: 12.5px;
    color: rgba(255,255,255,0.6);
    margin: 0;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s ease, opacity 0.4s ease;
    opacity: 0;
}

.service-card:hover p { max-height: 60px; opacity: 1; }

/* ===== ROOM CARDS ===== */
.rooms-section { padding: 100px 0; background: var(--cream); }

.room-card-new {
    background: var(--white);
    border-radius: var(--radius);
    overflow: hidden;
    border: 1px solid var(--border);
    transition: var(--transition);
    height: 100%;
    display: flex;
    flex-direction: column;
}

.room-card-new:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-lg);
    border-color: transparent;
}

.room-card-img {
    position: relative;
    overflow: hidden;
    aspect-ratio: 4/3;
}

.room-card-img img {
    width: 100%; height: 100%;
    object-fit: cover;
    transition: transform 0.6s ease;
}

.room-card-new:hover .room-card-img img { transform: scale(1.05); }

.room-badge {
    position: absolute;
    top: 14px; left: 14px;
    background: var(--obsidian);
    color: var(--gold);
    font-size: 10px;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 4px;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.ai-badge {
    position: absolute;
    top: 14px; right: 14px;
    background: linear-gradient(135deg, #7B4FE0, #4F8EE0);
    color: white;
    font-size: 9px;
    font-weight: 700;
    padding: 5px 10px;
    border-radius: 4px;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.room-card-body {
    padding: 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.room-type-tag {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 6px;
}

.room-card-body h5 {
    font-family: var(--font-serif);
    font-size: 20px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 12px;
    line-height: 1.2;
}

.room-amenities {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 16px;
}

.room-amenity {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    color: var(--text2);
    background: var(--cream);
    padding: 3px 8px;
    border-radius: 4px;
}

.room-amenity i { color: var(--gold); font-size: 9px; }

.room-meta {
    display: flex;
    align-items: center;
    gap: 14px;
    font-size: 12px;
    color: var(--text3);
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 16px;
}

.room-meta span { display: flex; align-items: center; gap: 5px; }
.room-meta i { font-size: 11px; }

.room-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: auto;
}

.room-price-new {
    font-family: var(--font-serif);
    font-size: 24px;
    font-weight: 600;
    color: var(--obsidian);
}

.room-price-new small {
    font-family: var(--font-sans);
    font-size: 11px;
    font-weight: 400;
    color: var(--text3);
}

.room-cta {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--obsidian);
    color: white;
    padding: 9px 18px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 600;
    text-decoration: none;
    transition: var(--transition);
}

.room-cta:hover { background: var(--gold); color: var(--obsidian); }

/* ===== AI SECTION (tính năng mới) ===== */
.ai-section {
    padding: 80px 0;
    background: linear-gradient(135deg, #0D0D1A 0%, #1A1A2E 50%, #0D1520 100%);
    position: relative;
    overflow: hidden;
}

.ai-section::before {
    content: '';
    position: absolute;
    top: -100px; left: -100px;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(123,79,224,0.15), transparent 70%);
    border-radius: 50%;
}

.ai-section::after {
    content: '';
    position: absolute;
    bottom: -100px; right: -100px;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(79,142,224,0.12), transparent 70%);
    border-radius: 50%;
}

.ai-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: var(--radius);
    padding: 28px;
    backdrop-filter: blur(8px);
    transition: var(--transition);
}

.ai-card:hover {
    background: rgba(255,255,255,0.07);
    border-color: rgba(123,79,224,0.3);
    transform: translateY(-4px);
}

.ai-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #7B4FE0, #4F8EE0);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: white;
    margin-bottom: 16px;
}

.ai-card h5 { font-size: 17px; font-weight: 600; color: white; margin-bottom: 8px; }
.ai-card p { font-size: 13px; color: rgba(255,255,255,0.5); margin: 0; line-height: 1.6; }

.ai-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(123,79,224,0.2);
    border: 1px solid rgba(123,79,224,0.4);
    color: #C4AAFF;
    font-size: 11px;
    font-weight: 600;
    padding: 5px 12px;
    border-radius: 20px;
    margin-bottom: 14px;
    letter-spacing: 0.5px;
}

/* ===== LIVE AVAILABILITY WIDGET (tính năng mới) ===== */
.availability-widget {
    background: var(--white);
    border-radius: var(--radius);
    padding: 20px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
}

.avail-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.avail-title { font-size: 13px; font-weight: 700; color: var(--text); }

.live-dot {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 10px;
    font-weight: 600;
    color: #0A7D6C;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.live-dot::before {
    content: '';
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #0A7D6C;
    box-shadow: 0 0 0 2px rgba(10,125,108,0.25);
    animation: livePulse 2s infinite;
}

@keyframes livePulse {
    0%,100% { box-shadow: 0 0 0 0 rgba(10,125,108,0.4); }
    50% { box-shadow: 0 0 0 5px rgba(10,125,108,0); }
}

.avail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.avail-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    border-radius: 8px;
    background: var(--cream);
    border: 1px solid var(--border);
}

.avail-item .ai-type { font-size: 12px; font-weight: 600; color: var(--text); }
.avail-item .ai-count {
    font-size: 18px;
    font-weight: 700;
    color: var(--obsidian);
    font-family: var(--font-serif);
}

.avail-item.low .ai-count { color: #E94560; }
.avail-item.ok .ai-count { color: #0A7D6C; }

/* ===== QUICK CONTACT / CTA SECTION ===== */
.quick-contact {
    background: var(--obsidian);
    padding: 24px 28px;
    border-radius: var(--radius);
    border: 1px solid rgba(200,169,110,0.2);
    margin-top: 16px;
}

.qc-title { font-size: 13px; font-weight: 700; color: white; margin-bottom: 14px; }

.qc-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 0;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    text-decoration: none;
    transition: var(--transition);
}

.qc-item:last-child { border-bottom: none; padding-bottom: 0; }
.qc-item:hover .qc-text { color: var(--gold); }

.qc-icon {
    width: 32px; height: 32px;
    border-radius: 8px;
    background: rgba(200,169,110,0.12);
    display: flex; align-items: center; justify-content: center;
    color: var(--gold);
    font-size: 13px;
    flex-shrink: 0;
}

.qc-label { font-size: 10px; font-weight: 600; color: rgba(255,255,255,0.3); text-transform: uppercase; letter-spacing: 0.5px; }
.qc-text { font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.8); }

/* ===== GALLERY ===== */
.gallery-section {
    padding: 100px 0;
    background: var(--cream);
}

.gallery-masonry {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    grid-template-rows: auto;
    gap: 8px;
    margin-top: 48px;
}

.gallery-item {
    overflow: hidden;
    border-radius: var(--radius-sm);
    position: relative;
    cursor: pointer;
}

.gallery-item img {
    width: 100%; height: 100%;
    object-fit: cover;
    transition: transform 0.6s ease;
}

.gallery-item:hover img { transform: scale(1.05); }

.gallery-item:nth-child(1) { grid-column: span 7; grid-row: span 2; min-height: 400px; }
.gallery-item:nth-child(2) { grid-column: span 5; min-height: 195px; }
.gallery-item:nth-child(3) { grid-column: span 5; min-height: 195px; }

.gallery-overlay {
    position: absolute;
    inset: 0;
    background: rgba(13,13,15,0);
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
}

.gallery-item:hover .gallery-overlay {
    background: rgba(13,13,15,0.35);
}

.gallery-overlay i {
    color: white;
    font-size: 24px;
    opacity: 0;
    transform: scale(0.7);
    transition: var(--transition);
}

.gallery-item:hover .gallery-overlay i {
    opacity: 1;
    transform: scale(1);
}

/* ===== LOCATION ===== */
.location-section { padding: 100px 0; background: var(--white); }

.location-card {
    background: var(--cream);
    border-radius: var(--radius);
    padding: 32px;
    border: 1px solid var(--border);
    height: 100%;
}

.location-contact-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 14px 0;
    border-bottom: 1px solid var(--border);
}

.location-contact-item:last-child { border-bottom: none; }

.lci-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
}

.lci-label { font-size: 10px; font-weight: 700; color: var(--text3); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
.lci-val { font-size: 14px; font-weight: 600; color: var(--text); }
.lci-sub { font-size: 12px; color: var(--text2); margin-top: 1px; }

.distance-pills { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border); }

.distance-pill {
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 6px 14px;
    font-size: 12px;
    font-weight: 500;
    color: var(--text2);
}

.distance-pill i { color: var(--gold); font-size: 11px; }

/* ===== NEWSLETTER (tính năng mới) ===== */
.newsletter-section {
    background: var(--gold-pale);
    border-top: 1px solid #E8D8B0;
    border-bottom: 1px solid #E8D8B0;
    padding: 60px 0;
}

.newsletter-form {
    display: flex;
    gap: 8px;
    max-width: 480px;
    margin: 24px auto 0;
}

.newsletter-input {
    flex: 1;
    padding: 13px 18px;
    border: 1.5px solid #E8D8B0;
    border-radius: 8px;
    font-family: var(--font-sans);
    font-size: 13.5px;
    background: white;
    outline: none;
    transition: var(--transition);
    color: var(--text);
}

.newsletter-input:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(200,169,110,0.12); }
.newsletter-input::placeholder { color: var(--text3); }

/* ===== FOOTER ===== */
.footer {
    background: var(--obsidian);
    color: rgba(255,255,255,0.55);
    padding: 60px 0 30px;
}

.footer-logo {
    font-family: var(--font-serif);
    font-size: 26px;
    font-weight: 600;
    color: var(--gold);
    margin-bottom: 10px;
}

.footer-tagline { font-size: 12px; color: rgba(255,255,255,0.3); letter-spacing: 1px; margin-bottom: 20px; }

.footer-links { list-style: none; padding: 0; margin: 0; }
.footer-links li { margin-bottom: 8px; }
.footer-links a { color: rgba(255,255,255,0.45); text-decoration: none; font-size: 13px; transition: var(--transition); }
.footer-links a:hover { color: var(--gold); }

.footer-heading { font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.25); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 14px; }

.footer-bottom {
    border-top: 1px solid rgba(255,255,255,0.07);
    margin-top: 40px;
    padding-top: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12px;
    flex-wrap: wrap;
    gap: 10px;
}

.social-links { display: flex; gap: 10px; }

.social-link {
    width: 36px; height: 36px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.1);
    display: flex; align-items: center; justify-content: center;
    color: rgba(255,255,255,0.4);
    font-size: 14px;
    text-decoration: none;
    transition: var(--transition);
}

.social-link:hover { border-color: var(--gold); color: var(--gold); }

/* ===== REVEAL ANIMATION ===== */
.reveal-up {
    opacity: 0;
    transform: translateY(24px);
    transition: opacity 0.7s ease, transform 0.7s ease;
}

.reveal-up.visible { opacity: 1; transform: translateY(0); }
.reveal-up.delay-1 { transition-delay: 0.1s; }
.reveal-up.delay-2 { transition-delay: 0.2s; }
.reveal-up.delay-3 { transition-delay: 0.3s; }

/* ===== TOAST NOTIFICATION ===== */
.toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }

.khotel-toast {
    background: var(--obsidian);
    color: white;
    padding: 14px 20px;
    border-radius: 10px;
    font-size: 13.5px;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: var(--shadow-lg);
    border-left: 3px solid var(--gold);
    transform: translateX(100px);
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
    max-width: 320px;
}

.khotel-toast.show { transform: translateX(0); opacity: 1; }
.khotel-toast i { color: var(--gold); font-size: 15px; flex-shrink: 0; }

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .service-cards { grid-template-columns: 1fr; }
    .about-features { grid-template-columns: 1fr; }
    .hero-stats { flex-wrap: wrap; padding: 12px 20px; }
    .hero-stat { flex: 0 0 50%; border-right: none; border-bottom: 1px solid rgba(255,255,255,0.1); }
    .gallery-masonry { grid-template-columns: 1fr 1fr; }
    .gallery-item:nth-child(1) { grid-column: span 2; min-height: 250px; }
    .gallery-item:nth-child(2), .gallery-item:nth-child(3) { grid-column: span 1; min-height: 180px; }
    .newsletter-form { flex-direction: column; }
    .search-bar { flex-direction: column; border-radius: var(--radius); }
    .search-field { border-right: none; border-bottom: 1px solid var(--border); }
    .search-submit { border-radius: 0 0 12px 12px; padding: 14px; }
}
</style>
</head>
<body>

<?php include 'include/header_customer.php'; ?>

<!-- ===== PROMO RIBBON ===== -->
<div class="promo-ribbon">
    <span>✦ &nbsp; ƯU ĐÃI HÈ 2026: GIẢM ĐẾN 25% KHI ĐẶT PHÒNG TRƯỚC 7 NGÀY &nbsp; — &nbsp; <a href="timkiem.php">ĐẶT NGAY</a> &nbsp; ✦</span>
</div>

<!-- ===== SEARCH BAR ===== -->
<div class="search-section py-3">
    <div class="container">
        <form action="timkiem.php" method="GET" id="searchForm">
            <input type="hidden" name="checkin" id="hiddenCheckin">
            <input type="hidden" name="checkout" id="hiddenCheckout">
            <input type="hidden" name="rooms" id="hiddenRooms" value="1">
            <input type="hidden" name="adults" id="hiddenAdults" value="1">
            <input type="hidden" name="kids" id="hiddenKids" value="0">

            <div class="search-bar">
                <!-- Destination -->
                <div class="search-field" style="flex:2">
                    <i class="fa-solid fa-magnifying-glass sf-icon"></i>
                    <div style="flex:1">
                        <div class="sf-label">Điểm đến</div>
                        <input type="text" name="q" placeholder="Bạn muốn đi đâu?">
                    </div>
                </div>

                <!-- Check-in -->
                <div class="search-field" id="triggerCheckin" onclick="openDatePicker()" style="cursor:pointer">
                    <i class="fa-regular fa-calendar-days sf-icon"></i>
                    <div>
                        <div class="sf-label">Nhận phòng</div>
                        <div class="sf-val sf-empty" id="ci-display">Chọn ngày</div>
                    </div>
                </div>

                <!-- Check-out -->
                <div class="search-field" id="triggerCheckout" onclick="openDatePicker()" style="cursor:pointer">
                    <i class="fa-regular fa-calendar-days sf-icon"></i>
                    <div>
                        <div class="sf-label">Trả phòng</div>
                        <div class="sf-val sf-empty" id="co-display">Chọn ngày</div>
                    </div>
                </div>

                <!-- Guests -->
                <div class="search-field" id="guestTrigger" onclick="toggleGuestPicker(event)" style="cursor:pointer;position:relative">
                    <i class="fa-regular fa-user sf-icon"></i>
                    <div>
                        <div class="sf-label">Phòng & Khách</div>
                        <div class="sf-val" id="guestSummary">1 phòng, 1 người lớn</div>
                    </div>
                </div>

                <button type="submit" class="search-submit">
                    <i class="fa-solid fa-magnifying-glass"></i> Tìm phòng
                </button>
            </div>

            <!-- Date Picker Popup -->
            <div class="date-picker-overlay" id="dpOverlay" onclick="closeDatePicker()"></div>
            <div class="date-picker-popup" id="datePicker">
                <div class="dp-month-grid" id="dpGrid"></div>
                <div class="dp-footer">
                    <div class="dp-selected-display" id="dpDisplay">Chọn ngày nhận phòng</div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="resetDates()">Đặt lại</button>
                        <button type="button" class="btn btn-sm rounded-pill px-4 fw-bold text-white" style="background:var(--obsidian);" onclick="confirmDates()">Xong</button>
                    </div>
                </div>
            </div>

            <!-- Guest Picker Popup -->
            <div class="guest-picker-popup" id="guestPicker">
                <div class="gp-header">Phòng & Khách</div>
                <div class="gp-row">
                    <div class="gp-label">
                        <div class="gp-label-main">Phòng 1</div>
                    </div>
                    <div class="gp-cols">
                        <div class="gp-col">
                            <div class="gp-col-label">Người lớn</div>
                            <div class="gp-counter">
                                <button type="button" class="gp-btn" onclick="changeCount('adults',-1)"><i class="fa-solid fa-minus"></i></button>
                                <span class="gp-count" id="countAdults">1</span>
                                <button type="button" class="gp-btn" onclick="changeCount('adults',1)"><i class="fa-solid fa-plus"></i></button>
                            </div>
                        </div>
                        <div class="gp-col">
                            <div class="gp-col-label">Trẻ em</div>
                            <div class="gp-counter">
                                <button type="button" class="gp-btn" onclick="changeCount('kids',-1)"><i class="fa-solid fa-minus"></i></button>
                                <span class="gp-count" id="countKids">0</span>
                                <button type="button" class="gp-btn" onclick="changeCount('kids',1)"><i class="fa-solid fa-plus"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="gp-add-room" onclick="addRoom()">
                    <i class="fa-solid fa-circle-plus"></i> Thêm phòng <span style="font-weight:400;color:var(--text3);font-size:11px">(Tối đa 4 phòng)</span>
                </div>
                <div id="extraRooms"></div>
                <p class="gp-note">Đặt từ 10 phòng? <a href="timkiem.php" style="color:var(--obsidian);font-weight:600;">Liên hệ đặt nhóm</a></p>
                <div class="gp-footer">
                    <button type="button" class="gp-done" onclick="closeGuestPicker()">Xong</button>
                    <button type="button" class="gp-cancel" onclick="closeGuestPicker()">Hủy</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ===== HERO ===== -->
<section class="hero">
    <div class="hero-bg">
        <img src="assets/img/premium_luxury_resort_hero_1777033980264.png" alt="K-Hotel Luxury Resort">
    </div>
    <div class="hero-overlay"></div>

    <div class="hero-content">
        <div class="hero-eyebrow">Nghỉ dưỡng đẳng cấp 5 sao</div>
        <h1 class="hero-title">
            Khám Phá Tuyệt Tác<br>
            <span class="gold-line">Của Sự Xa Hoa</span>
        </h1>
        <p class="hero-desc">Nơi mỗi chi tiết được kiến tạo để đánh thức trọn vẹn mọi giác quan — giữa lòng đô thị phồn hoa Việt Nam.</p>
        <div class="hero-actions">
            <a href="#danh-sach-phong" class="btn-gold">
                <i class="fa-solid fa-bed"></i> Khám Phá Phòng
            </a>
            <a href="#location-section" class="btn-outline-white">
                <i class="fa-solid fa-map-location-dot"></i> Vị Trí
            </a>
        </div>
    </div>

    <!-- Stats Strip -->
    <div class="hero-stats">
        <div class="hero-stat">
            <div class="val">12+</div>
            <div class="lbl">Năm Tỏa Sáng</div>
        </div>
        <div class="hero-stat">
            <div class="val"><?= $stat_rooms ?>+</div>
            <div class="lbl">Phòng Trống</div>
        </div>
        <div class="hero-stat">
            <div class="val">4.8★</div>
            <div class="lbl">Đánh Giá TB</div>
        </div>
        <div class="hero-stat">
            <div class="val">24/7</div>
            <div class="lbl">Hỗ Trợ</div>
        </div>
    </div>

    <!-- Scroll Indicator -->
    <div class="scroll-indicator">
        <div class="scroll-line"></div>
        <span>Cuộn xuống</span>
    </div>
</section>

<!-- ===== ABOUT SECTION ===== -->
<section class="about-section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5 reveal-up">
                <div class="about-img-wrap">
                    <div class="about-glow"></div>
                    <img src="assets/img/luxury_hotel_history_1777034042949.png" class="about-img" alt="K-Hotel">
                    <div class="about-badge">
                        <i class="fa-solid fa-award" style="color:var(--gold);font-size:32px;"></i>
                        <div>
                            <div class="ab-num">12+</div>
                            <div class="ab-text">Năm Tỏa Sáng</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7 reveal-up delay-2">
                <div class="section-eyebrow"><i class="fa-solid fa-feather-pointed"></i> Câu chuyện của chúng tôi</div>
                <h2 class="section-title">Hơn Một Thập Kỷ<br>Kiến Tạo <em style="color:var(--gold);font-style:italic">Hoàn Mỹ</em></h2>
                <p class="section-desc mb-4">K-Hotel được thành lập năm 2012 với tầm nhìn kiên định: định nghĩa lại khái niệm nghỉ dưỡng đẳng cấp mang đậm hồn Việt. Với kiến trúc giao thoa cổ điển Tây phương và thanh lịch Á Đông, chúng tôi là điểm dừng chân hoàn hảo cho giới tinh hoa.</p>

                <div class="about-features">
                    <div class="about-feat reveal-up">
                        <div class="about-feat-icon"><i class="fa-solid fa-crown"></i></div>
                        <div>
                            <h6>Dịch vụ Hoàng gia</h6>
                            <p>Butler riêng 24/7 cho phòng Suite và Presidential</p>
                        </div>
                    </div>
                    <div class="about-feat reveal-up delay-1">
                        <div class="about-feat-icon"><i class="fa-solid fa-spa"></i></div>
                        <div>
                            <h6>Spa & Wellness</h6>
                            <p>Trung tâm spa 2.000m² với 12 phòng trị liệu</p>
                        </div>
                    </div>
                    <div class="about-feat reveal-up delay-2">
                        <div class="about-feat-icon"><i class="fa-solid fa-utensils"></i></div>
                        <div>
                            <h6>Ẩm thực Đỉnh cao</h6>
                            <p>4 nhà hàng do đầu bếp 3 sao Michelin phụ trách</p>
                        </div>
                    </div>
                    <div class="about-feat reveal-up delay-3">
                        <div class="about-feat-icon"><i class="fa-solid fa-wifi"></i></div>
                        <div>
                            <h6>Công nghệ Smart Room</h6>
                            <p>Điều khiển toàn bộ phòng qua ứng dụng di động</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== SERVICES / AMENITIES ===== -->
<section class="services-section">
    <div class="container">
        <div class="row align-items-end mb-0">
            <div class="col-lg-6 reveal-up">
                <div class="section-eyebrow"><i class="fa-solid fa-star"></i> Tiện ích đặc quyền</div>
                <h2 class="section-title" style="color:white;">Trải Nghiệm Không<br>Giới Hạn</h2>
            </div>
            <div class="col-lg-6 reveal-up delay-1">
                <p class="section-desc" style="color:rgba(255,255,255,0.5);margin-left:auto;">Từ bãi biển riêng tư đến nhà hàng cao cấp, mỗi tiện ích tại K-Hotel được thiết kế để mang đến những khoảnh khắc khó quên.</p>
            </div>
        </div>
    </div>
    <div class="container-fluid px-4 px-lg-5 mt-5 reveal-up delay-1">
        <div class="service-cards">
            <div class="service-card">
                <img src="assets/img/private_beach_sunset_1777034168568.png" alt="Beach">
                <div class="service-card-content">
                    <div class="service-card-icon"><i class="fa-solid fa-umbrella-beach"></i></div>
                    <h4>Bãi Biển Riêng</h4>
                    <p>Khu vực nghỉ dưỡng hoàng hôn độc quyền cho khách VIP</p>
                </div>
            </div>
            <div class="service-card">
                <img src="assets/img/tropical_pool_1777034220551.png" alt="Pool">
                <div class="service-card-content">
                    <div class="service-card-icon"><i class="fa-solid fa-person-swimming"></i></div>
                    <h4>Hồ Bơi Vô Cực</h4>
                    <p>Nhìn ra toàn cảnh thành phố, mở cửa đến 22:00 hàng ngày</p>
                </div>
            </div>
            <div class="service-card">
                <img src="assets/img/ocean_wave_1777034188877.png" alt="Spa">
                <div class="service-card-content">
                    <div class="service-card-icon"><i class="fa-solid fa-spa"></i></div>
                    <h4>Spa & Wellness</h4>
                    <p>Liệu pháp cổ truyền Việt Nam kết hợp phương pháp quốc tế</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== AI RECOMMENDATION FEATURE SHOWCASE ===== -->
<section class="ai-section">
    <div class="container" style="position:relative;z-index:1;">
        <div class="row align-items-center g-5">
            <div class="col-lg-5 reveal-up">
                <div class="ai-tag"><i class="fa-solid fa-wand-magic-sparkles"></i> Trí tuệ nhân tạo</div>
                <h2 class="section-title" style="color:white;font-size:clamp(28px,3.5vw,48px);">Hệ Thống Gợi Ý<br><em style="color:#C4AAFF;font-style:italic">Thông Minh</em></h2>
                <p class="section-desc" style="color:rgba(255,255,255,0.5);margin-bottom:28px;">AI của K-Hotel phân tích lịch sử đặt phòng, sở thích tiện nghi và mức giá bạn thường chọn để đề xuất phòng phù hợp nhất khi bạn quay lại.</p>
                <?php if(!isset($_SESSION['user_id'])): ?>
                <a href="dangnhap.php" class="btn-gold"><i class="fa-solid fa-user"></i> Đăng nhập để nhận gợi ý</a>
                <?php else: ?>
                <a href="#danh-sach-phong" class="btn-gold"><i class="fa-solid fa-wand-magic-sparkles"></i> Xem gợi ý của bạn</a>
                <?php endif; ?>
            </div>
            <div class="col-lg-7 reveal-up delay-1">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="ai-card">
                            <div class="ai-icon"><i class="fa-solid fa-chart-line"></i></div>
                            <h5>Phân tích hành vi</h5>
                            <p>Học từ lịch sử đặt phòng và sở thích thực tế của từng khách hàng</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="ai-card">
                            <div class="ai-icon"><i class="fa-solid fa-tags"></i></div>
                            <h5>Gợi ý giá phù hợp</h5>
                            <p>Đề xuất phòng trong ngưỡng giá ±30% so với lịch sử chi tiêu</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="ai-card">
                            <div class="ai-icon"><i class="fa-solid fa-sliders"></i></div>
                            <h5>Matching tiện nghi</h5>
                            <p>So khớp tiện nghi yêu thích: ban công, Jacuzzi, view thành phố...</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="ai-card">
                            <div class="ai-icon"><i class="fa-solid fa-bolt"></i></div>
                            <h5>Real-time cập nhật</h5>
                            <p>Chỉ gợi ý phòng đang trống và phù hợp với lịch trình của bạn</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== ROOM RECOMMENDATIONS ===== -->
<section id="danh-sach-phong" class="rooms-section">
    <div class="container">
        <div class="row align-items-end mb-5">
            <div class="col-lg-6 reveal-up">
                <?php if(isset($_SESSION['user_id']) && count($recommendations) > 0 && $_SESSION['role']=='khach'): ?>
                    <div class="section-eyebrow"><i class="fa-solid fa-wand-magic-sparkles"></i> AI đề xuất cho bạn</div>
                    <h2 class="section-title">Gợi Ý Dành<br>Riêng Cho Bạn</h2>
                <?php else: ?>
                    <div class="section-eyebrow"><i class="fa-solid fa-star"></i> Lựa chọn hàng đầu</div>
                    <h2 class="section-title">Phòng Nổi<br>Bật Nhất</h2>
                <?php endif; ?>
            </div>
            <div class="col-lg-6 reveal-up delay-1 text-lg-end">
                <a href="timkiem.php" class="btn-dark">Xem tất cả phòng <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
        </div>

        <div class="row g-4">
            <?php
            $delay_count = 0;
            foreach($recommendations as $rm):
                $delay_class = $delay_count > 0 && $delay_count < 4 ? "delay-{$delay_count}" : "";
                $delay_count++;
                $img_src = (!empty($rm['HinhAnh']) && file_exists($rm['HinhAnh']))
                    ? htmlspecialchars($rm['HinhAnh'])
                    : 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=800&q=80';
                $amenities = array_slice(array_map('trim', explode(',', $rm['TienNghi'])), 0, 4);
                $is_ai = isset($_SESSION['user_id']) && $_SESSION['role'] == 'khach';
            ?>
            <div class="col-md-6 col-lg-3 reveal-up <?= $delay_class ?>">
                <div class="room-card-new">
                    <div class="room-card-img">
                        <img src="<?= $img_src ?>" alt="<?= htmlspecialchars($rm['TenLoai']) ?>">
                        <div class="room-badge"><?= htmlspecialchars($rm['TenLoai']) ?></div>
                        <?php if($is_ai): ?>
                        <div class="ai-badge"><i class="fa-solid fa-wand-magic-sparkles"></i> AI Pick</div>
                        <?php endif; ?>
                    </div>
                    <div class="room-card-body">
                        <div class="room-type-tag"><?= htmlspecialchars($rm['TenLoai']) ?></div>
                        <h5><?= htmlspecialchars($rm['TenLoai']) ?> (<?= $rm['MaPhong'] ?>)</h5>

                        <div class="room-meta">
                            <span><i class="fa-solid fa-user-group"></i> <?= $rm['SoNguoiToiDa'] ?> người</span>
                            <?php if($rm['TrangThai'] == 'Trống'): ?>
                                <span><i class="fa-solid fa-check-circle" style="color:var(--gold)"></i> Còn trống</span>
                            <?php elseif($rm['TrangThai'] == 'Đang ở'): ?>
                                <span><i class="fa-solid fa-user-tag" style="color:#ef4444"></i> Đang ở</span>
                            <?php else: ?>
                                <span><i class="fa-solid fa-broom" style="color:#f59e0b"></i> Đang dọn dẹp</span>
                            <?php endif; ?>
                        </div>

                        <div class="room-amenities">
                            <?php foreach($amenities as $a): ?>
                            <div class="room-amenity">
                                <i class="fa-solid fa-check"></i>
                                <?= htmlspecialchars(trim($a)) ?>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="room-card-footer">
                            <div>
                                <div class="room-price-new">
                                    <?= number_format($rm['GiaPhong']) ?>₫
                                    <small> / đêm</small>
                                </div>
                            </div>
                            <a href="chitiet.php?id=<?= $rm['MaPhong'] ?>" class="room-cta">
                                Xem <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if(count($recommendations) == 0): ?>
            <div class="col-12 text-center py-5">
                <i class="fa-solid fa-moon" style="font-size:40px;color:var(--text3);margin-bottom:16px;display:block;"></i>
                <p style="color:var(--text2);">Hiện tại chưa có phòng trống. Vui lòng quay lại sau!</p>
                <a href="timkiem.php" class="btn-dark mt-3" style="display:inline-flex;">Kiểm tra lịch trống</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ===== LIVE AVAILABILITY + CONTACT (tính năng mới) ===== -->
<section style="padding:80px 0;background:var(--sand);">
    <div class="container">
        <div class="row g-4 align-items-start">
            <div class="col-lg-4 reveal-up">
                <div class="section-eyebrow"><i class="fa-solid fa-circle-dot"></i> Trực tiếp</div>
                <h3 class="section-title" style="font-size:clamp(24px,2.5vw,36px);margin-bottom:20px;">Tình Trạng<br>Phòng Hôm Nay</h3>
                <p style="font-size:13px;color:var(--text2);line-height:1.7;margin-bottom:20px;">Theo dõi phòng trống theo thời gian thực. Đặt ngay trước khi hết!</p>

                <div class="availability-widget">
                    <div class="avail-header">
                        <div class="avail-title">Tình trạng phòng</div>
                        <div class="live-dot">LIVE</div>
                    </div>
                    <div class="avail-grid">
                        <div class="avail-item ok">
                            <div>
                                <div style="font-size:10px;color:var(--text3);font-weight:600;margin-bottom:2px">STANDARD</div>
                                <div class="ai-type">Phòng đôi</div>
                            </div>
                            <div class="ai-count"><?= max(0, ($stat_rooms - 2)) ?></div>
                        </div>
                        <div class="avail-item ok">
                            <div>
                                <div style="font-size:10px;color:var(--text3);font-weight:600;margin-bottom:2px">DELUXE</div>
                                <div class="ai-type">View thành phố</div>
                            </div>
                            <div class="ai-count"><?= max(0, ($stat_rooms - 3)) ?></div>
                        </div>
                        <div class="avail-item <?= ($stat_rooms <= 2) ? 'low' : 'ok' ?>">
                            <div>
                                <div style="font-size:10px;color:var(--text3);font-weight:600;margin-bottom:2px">SUITE</div>
                                <div class="ai-type">Jacuzzi & View</div>
                            </div>
                            <div class="ai-count"><?= max(0, ($stat_rooms - 4)) ?></div>
                        </div>
                        <div class="avail-item low">
                            <div>
                                <div style="font-size:10px;color:var(--text3);font-weight:600;margin-bottom:2px">PRESIDENTIAL</div>
                                <div class="ai-type">Tầng penthouse</div>
                            </div>
                            <div class="ai-count">1</div>
                        </div>
                    </div>
                    <a href="timkiem.php" class="btn-dark" style="width:100%;justify-content:center;margin-top:16px;">
                        <i class="fa-solid fa-calendar-check"></i> Đặt phòng ngay
                    </a>
                </div>
            </div>

            <div class="col-lg-4 reveal-up delay-1">
                <div class="section-eyebrow"><i class="fa-solid fa-headset"></i> Liên hệ nhanh</div>
                <h3 class="section-title" style="font-size:clamp(24px,2.5vw,36px);margin-bottom:20px;">Hỗ Trợ<br>24/7</h3>
                <p style="font-size:13px;color:var(--text2);line-height:1.7;margin-bottom:20px;">Đội ngũ concierge của chúng tôi luôn sẵn sàng hỗ trợ bạn bất kỳ lúc nào.</p>

                <div class="quick-contact">
                    <div class="qc-title">Liên hệ trực tiếp</div>
                    <a href="tel:0938697308" class="qc-item" style="text-decoration:none">
                        <div class="qc-icon"><i class="fa-solid fa-phone"></i></div>
                        <div>
                            <div class="qc-label">Hotline miễn cước</div>
                            <div class="qc-text">0938697308</div>
                        </div>
                    </a>
                    <a href="mailto:kietvo.260605@gmail.com" class="qc-item" style="text-decoration:none">
                        <div class="qc-icon"><i class="fa-solid fa-envelope"></i></div>
                        <div>
                            <div class="qc-label">Email đặt phòng</div>
                            <div class="qc-text">kietvo.260605@gmail.com</div>
                        </div>
                    </a>
                    <a href="https://www.facebook.com/vo.kiet.98284566/?locale=vi_VN" class="qc-item" style="text-decoration:none" onclick="showToast('Đang mở Zalo chat...')">
                        <div class="qc-icon"><i class="fa-brands fa-facebook-messenger"></i></div>
                        <div>
                            <div class="qc-label">Zalo / Messenger</div>
                            <div class="qc-text">Chat trực tiếp ngay</div>
                        </div>
                    </a>
                    <a href="timkiem.php" class="qc-item" style="text-decoration:none">
                        <div class="qc-icon"><i class="fa-solid fa-calendar-plus"></i></div>
                        <div>
                            <div class="qc-label">Đặt phòng nhóm (10+)</div>
                            <div class="qc-text">Yêu cầu báo giá</div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="col-lg-4 reveal-up delay-2" id="location-section">
                <div class="section-eyebrow"><i class="fa-solid fa-map-location-dot"></i> Vị trí</div>
                <h3 class="section-title" style="font-size:clamp(24px,2.5vw,36px);margin-bottom:20px;">Trái Tim<br>Đô Thị</h3>
                <p style="font-size:13px;color:var(--text2);line-height:1.7;margin-bottom:20px;">Tọa lạc ngay trung tâm TP. Hồ Chí Minh, gần mọi điểm tham quan nổi tiếng.</p>
                <div class="rounded-3 overflow-hidden" style="height:240px;border:1px solid var(--border);margin-bottom:16px;">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3919.2789182061036!2d106.69744111428678!3d10.789932161901306!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31752f360eb3dfbd%3A0xe72648fbbe9eb586!2sLandmark%2081!5e0!3m2!1sen!2s!4v1689233069123!5m2!1sen!2s" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                </div>
                <div class="distance-pills">
                    <div class="distance-pill"><i class="fa-solid fa-plane"></i> Sân bay: 15 phút</div>
                    <div class="distance-pill"><i class="fa-solid fa-train"></i> Metro: 3 phút</div>
                    <div class="distance-pill"><i class="fa-solid fa-bag-shopping"></i> Vincom: 2 phút</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== GALLERY ===== -->
<section class="gallery-section">
    <div class="container">
        <div class="text-center reveal-up">
            <div class="section-eyebrow" style="justify-content:center"><i class="fa-solid fa-camera"></i> Hình ảnh thực tế</div>
            <h2 class="section-title">Hòa Mình Cùng<br>Biển Cả</h2>
            <p class="section-desc mx-auto text-center">Những khoảnh khắc đáng nhớ đang chờ đón bạn tại K-Hotel.</p>
        </div>
        <div class="gallery-masonry">
            <div class="gallery-item">
                <img src="assets/img/private_beach_sunset_1777034168568.png" alt="Beach">
                <div class="gallery-overlay"><i class="fa-solid fa-expand"></i></div>
            </div>
            <div class="gallery-item">
                <img src="assets/img/ocean_wave_1777034188877.png" alt="Ocean">
                <div class="gallery-overlay"><i class="fa-solid fa-expand"></i></div>
            </div>
            <div class="gallery-item">
                <img src="assets/img/tropical_pool_1777034220551.png" alt="Pool">
                <div class="gallery-overlay"><i class="fa-solid fa-expand"></i></div>
            </div>
        </div>
    </div>
</section>

<!-- ===== NEWSLETTER (tính năng mới) ===== -->
<section class="newsletter-section">
    <div class="container text-center">
        <div class="reveal-up">
            <div class="section-eyebrow" style="justify-content:center"><i class="fa-solid fa-envelope-open-text"></i> Ưu đãi độc quyền</div>
            <h3 class="section-title" style="font-size:clamp(24px,3vw,40px);">Nhận Ưu Đãi Sớm Nhất</h3>
            <p style="color:var(--text2);font-size:14px;max-width:480px;margin:0 auto;">Đăng ký nhận bản tin để nhận ngay mã giảm giá 10% cho lần đặt phòng đầu tiên và cập nhật khuyến mãi độc quyền.</p>
            <form class="newsletter-form" onsubmit="handleNewsletter(event)">
                <input type="email" class="newsletter-input" placeholder="Nhập địa chỉ email của bạn..." required>
                <button type="submit" class="btn-gold"><i class="fa-solid fa-paper-plane"></i> Đăng ký</button>
            </form>
            <p style="font-size:11px;color:var(--text3);margin-top:12px;">✓ Không spam &nbsp; ✓ Hủy đăng ký bất kỳ lúc nào &nbsp; ✓ Giảm 10% ngay sau khi đăng ký</p>
        </div>
    </div>
</section>

<!-- ===== FOOTER ===== -->
<footer class="footer">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                <div class="footer-logo">✦ K-Hotel</div>
                <div class="footer-tagline">NGHỈ DƯỠNG ĐẲNG CẤP 5 SAO</div>
                <p style="font-size:13px;color:rgba(255,255,255,0.4);line-height:1.7;max-width:300px;">Nơi mỗi kỳ nghỉ trở thành một kỷ niệm hoàn hảo không thể nào quên được.</p>
                <div class="social-links mt-4">
                    <a href="https://www.facebook.com/vo.kiet.98284566/" class="social-link"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" class="social-link"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" class="social-link"><i class="fa-brands fa-tiktok"></i></a>
                    <a href="#" class="social-link"><i class="fa-brands fa-youtube"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-6">
                <div class="footer-heading">Khám phá</div>
                <ul class="footer-links">
                    <li><a href="timkiem.php">Tìm phòng</a></li>
                    <li><a href="#">Ưu đãi đặc biệt</a></li>
                    <li><a href="#">Spa & Wellness</a></li>
                    <li><a href="#">Nhà hàng</a></li>
                    <li><a href="#">Hội nghị</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-6">
                <div class="footer-heading">Hỗ trợ</div>
                <ul class="footer-links">
                    <li><a href="#">Câu hỏi thường gặp</a></li>
                    <li><a href="#">Chính sách hủy phòng</a></li>
                    <li><a href="#">Điều khoản dịch vụ</a></li>
                    <li><a href="#">Bảo mật thông tin</a></li>
                    <li><a href="#location-section">Liên hệ</a></li>
                </ul>
            </div>
            <div class="col-lg-4">
                <div class="footer-heading">Liên hệ</div>
                <div style="color:rgba(255,255,255,0.4);font-size:13px;line-height:2;">
                    <div><i class="fa-solid fa-location-dot me-2" style="color:var(--gold)"></i> Khối đế Landmark A, Q.1, TP.HCM</div>
                    <div><i class="fa-solid fa-phone me-2" style="color:var(--gold)"></i> 0938 697 308</div>
                    <div><i class="fa-solid fa-envelope me-2" style="color:var(--gold)"></i> kietvo.260605@gmail.com</div>
                    <div><i class="fa-solid fa-clock me-2" style="color:var(--gold)"></i> Check-in: 14:00 | Check-out: 12:00</div>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div>© 2026 K-Hotel Vietnam. Bảo lưu mọi quyền.</div>
            <div style="display:flex;gap:16px;">
                <a href="#" style="color:rgba(255,255,255,0.3);font-size:12px;text-decoration:none;">Chính sách bảo mật</a>
                <a href="#" style="color:rgba(255,255,255,0.3);font-size:12px;text-decoration:none;">Điều khoản</a>
            </div>
        </div>
    </div>
</footer>

<!-- ===== TOAST CONTAINER ===== -->
<div class="toast-container" id="toastContainer"></div>

<!-- ===== SCRIPTS ===== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ============ DATE PICKER (logic giữ nguyên, style mới) ============
(function(){
    const MONTHS_VI = ['Tháng 1','Tháng 2','Tháng 3','Tháng 4','Tháng 5','Tháng 6','Tháng 7','Tháng 8','Tháng 9','Tháng 10','Tháng 11','Tháng 12'];
    const DAYS_VI = ['CN','T2','T3','T4','T5','T6','T7'];
    let startDate = null, endDate = null, hoverDate = null;
    let viewYear = new Date().getFullYear();
    let viewMonth = new Date().getMonth();
    let selecting = false;

    window.openDatePicker = function() {
        document.getElementById('datePicker').classList.add('show');
        document.getElementById('dpOverlay').classList.add('show');
        renderCalendar();
    };
    window.closeDatePicker = function() {
        document.getElementById('datePicker').classList.remove('show');
        document.getElementById('dpOverlay').classList.remove('show');
    };
    window.resetDates = function() {
        startDate = null; endDate = null; hoverDate = null; selecting = false;
        updateDisplay(); renderCalendar();
        document.getElementById('hiddenCheckin').value = '';
        document.getElementById('hiddenCheckout').value = '';
        document.getElementById('ci-display').textContent = 'Chọn ngày';
        document.getElementById('ci-display').classList.add('sf-empty');
        document.getElementById('co-display').textContent = 'Chọn ngày';
        document.getElementById('co-display').classList.add('sf-empty');
    };
    window.confirmDates = function() {
        if (startDate && endDate) {
            closeDatePicker();
            document.getElementById('hiddenCheckin').value = formatDate(startDate);
            document.getElementById('hiddenCheckout').value = formatDate(endDate);
            document.getElementById('ci-display').textContent = formatDisplay(startDate);
            document.getElementById('ci-display').classList.remove('sf-empty');
            document.getElementById('co-display').textContent = formatDisplay(endDate);
            document.getElementById('co-display').classList.remove('sf-empty');
        } else { closeDatePicker(); }
    };
    function formatDate(d) { return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }
    function formatDisplay(d) { return String(d.getDate()).padStart(2,'0')+'/'+(d.getMonth()+1)+'/'+d.getFullYear(); }
    function navMonth(delta) {
        viewMonth += delta;
        if(viewMonth>11){viewMonth=0;viewYear++;}
        if(viewMonth<0){viewMonth=11;viewYear--;}
        renderCalendar();
    }
    window.navMonth = navMonth;
    function updateDisplay() {
        const el = document.getElementById('dpDisplay');
        if(!startDate){el.innerHTML='Chọn <strong>ngày nhận phòng</strong>';}
        else if(!endDate){el.innerHTML='Nhận: <strong>'+formatDisplay(startDate)+'</strong> → Chọn <strong>ngày trả phòng</strong>';}
        else{el.innerHTML='Nhận: <strong>'+formatDisplay(startDate)+'</strong> → Trả: <strong>'+formatDisplay(endDate)+'</strong>';}
    }
    function renderCalendar() {
        const grid = document.getElementById('dpGrid');
        grid.innerHTML = '';
        for(let m=0;m<2;m++) {
            let mo=(viewMonth+m)%12;
            let yr=viewYear+Math.floor((viewMonth+m)/12);
            grid.appendChild(renderMonth(yr,mo,m));
        }
        updateDisplay();
    }
    function renderMonth(yr,mo,idx) {
        const div=document.createElement('div');
        if(idx===1)div.classList.add('dp-month-second');
        const hdr=document.createElement('div');
        hdr.className='dp-month-header';
        hdr.innerHTML=`
            ${idx===0?`<button type="button" class="dp-nav-btn" onclick="navMonth(-1)"><i class="fa-solid fa-chevron-left"></i></button>`:'<span></span>'}
            <span class="dp-month-title">${MONTHS_VI[mo]} ${yr}</span>
            ${idx===1?`<button type="button" class="dp-nav-btn" onclick="navMonth(1)"><i class="fa-solid fa-chevron-right"></i></button>`:'<span></span>'}`;
        div.appendChild(hdr);
        const wkDiv=document.createElement('div');
        wkDiv.className='dp-weekdays';
        DAYS_VI.forEach(d=>{const s=document.createElement('div');s.className='dp-weekday';s.textContent=d;wkDiv.appendChild(s);});
        div.appendChild(wkDiv);
        const daysDiv=document.createElement('div');
        daysDiv.className='dp-days-grid';
        const firstDay=new Date(yr,mo,1).getDay();
        const daysInMonth=new Date(yr,mo+1,0).getDate();
        const today=new Date();today.setHours(0,0,0,0);
        for(let i=0;i<firstDay;i++){const e=document.createElement('span');daysDiv.appendChild(e);}
        for(let d=1;d<=daysInMonth;d++){
            const dayDate=new Date(yr,mo,d);
            const btn=document.createElement('button');
            btn.type='button';btn.className='dp-day';btn.textContent=d;
            if(dayDate<today){btn.classList.add('dp-disabled');}
            else{
                if(dayDate.toDateString()===today.toDateString())btn.classList.add('dp-today');
                if(startDate&&dayDate.toDateString()===startDate.toDateString()){btn.classList.add('dp-selected','dp-range-start');}
                if(endDate&&dayDate.toDateString()===endDate.toDateString()){btn.classList.add('dp-selected','dp-range-end');}
                if(startDate&&endDate&&dayDate>startDate&&dayDate<endDate)btn.classList.add('dp-in-range');
                if(startDate&&!endDate&&hoverDate&&dayDate>startDate&&dayDate<=hoverDate)btn.classList.add('dp-in-range');
                btn.addEventListener('click',()=>selectDay(dayDate));
                btn.addEventListener('mouseenter',()=>{hoverDate=dayDate;renderCalendar();});
            }
            daysDiv.appendChild(btn);
        }
        div.appendChild(daysDiv);
        return div;
    }
    function selectDay(date) {
        if(!startDate||(startDate&&endDate)){startDate=date;endDate=null;selecting=true;}
        else{if(date<=startDate){startDate=date;endDate=null;}else{endDate=date;selecting=false;}}
        hoverDate=null;updateDisplay();renderCalendar();
    }
    document.getElementById('dpGrid').addEventListener('mouseleave',()=>{hoverDate=null;renderCalendar();});
})();

// ============ GUEST PICKER ============
(function(){
    let adults=1,kids=0,rooms=1;
    const MAX_ROOMS=4,MAX_ADULTS=10,MAX_KIDS=6;

    window.toggleGuestPicker=function(e){
        e.stopPropagation();
        const picker=document.getElementById('guestPicker');
        const isOpen=picker.classList.contains('show');
        closeDatePicker();
        picker.classList.toggle('show',!isOpen);
    };
    window.closeGuestPicker=function(){document.getElementById('guestPicker').classList.remove('show');updateSummary();};
    window.changeCount=function(type,delta){
        if(type==='adults'){adults=Math.max(1,Math.min(MAX_ADULTS,adults+delta));document.getElementById('countAdults').textContent=adults;}
        else if(type==='kids'){kids=Math.max(0,Math.min(MAX_KIDS,kids+delta));document.getElementById('countKids').textContent=kids;}
        updateSummary();
    };
    window.addRoom=function(){
        if(rooms>=MAX_ROOMS)return;
        rooms++;
        const container=document.getElementById('extraRooms');
        const div=document.createElement('div');
        div.className='gp-row';div.id='extraRoom'+rooms;
        div.innerHTML=`<div class="gp-label"><div class="gp-label-main">Phòng ${rooms}</div><div class="gp-label-sub"><a href="#" onclick="removeRoom(${rooms});return false;" style="color:#E94560;font-size:12px;font-weight:600;text-decoration:none;">Xóa phòng</a></div></div><div class="gp-cols"><div class="gp-col"><div class="gp-col-label">Người lớn</div><div class="gp-counter"><button type="button" class="gp-btn" onclick="changeRoomCount(${rooms},'a',-1)"><i class="fa-solid fa-minus"></i></button><span class="gp-count" id="r${rooms}a">1</span><button type="button" class="gp-btn" onclick="changeRoomCount(${rooms},'a',1)"><i class="fa-solid fa-plus"></i></button></div></div><div class="gp-col"><div class="gp-col-label">Trẻ em</div><div class="gp-counter"><button type="button" class="gp-btn" onclick="changeRoomCount(${rooms},'k',-1)"><i class="fa-solid fa-minus"></i></button><span class="gp-count" id="r${rooms}k">0</span><button type="button" class="gp-btn" onclick="changeRoomCount(${rooms},'k',1)"><i class="fa-solid fa-plus"></i></button></div></div></div>`;
        container.appendChild(div);
        document.getElementById('hiddenRooms').value=rooms;
        updateSummary();
    };
    window.removeRoom=function(id){
        const el=document.getElementById('extraRoom'+id);
        if(el){el.remove();rooms--;} document.getElementById('hiddenRooms').value=rooms;updateSummary();
    };
    window.changeRoomCount=function(room,type,delta){
        const el=document.getElementById('r'+room+type);
        if(!el)return;
        let val=parseInt(el.textContent);
        val=Math.max(type==='a'?1:0,Math.min(type==='a'?MAX_ADULTS:MAX_KIDS,val+delta));
        el.textContent=val;updateSummary();
    };
    function updateSummary(){
        document.getElementById('hiddenAdults').value=adults;
        document.getElementById('hiddenKids').value=kids;
        document.getElementById('hiddenRooms').value=rooms;
        let txt=rooms+' phòng, '+adults+' người lớn';
        if(kids>0)txt+=', '+kids+' trẻ em';
        document.getElementById('guestSummary').textContent=txt;
    }
    document.addEventListener('click',function(e){
        const picker=document.getElementById('guestPicker');
        const trigger=document.getElementById('guestTrigger');
        if(picker&&!picker.contains(e.target)&&!trigger.contains(e.target)){picker.classList.remove('show');}
    });
})();

// ============ REVEAL ON SCROLL ============
const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            revealObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

document.querySelectorAll('.reveal-up').forEach(el => revealObserver.observe(el));

// ============ TOAST NOTIFICATION ============
window.showToast = function(msg, icon='fa-check-circle') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'khotel-toast';
    toast.innerHTML = `<i class="fa-solid ${icon}"></i> ${msg}`;
    container.appendChild(toast);
    requestAnimationFrame(() => { requestAnimationFrame(() => toast.classList.add('show')); });
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400);
    }, 3500);
};

// ============ NEWSLETTER ============
function handleNewsletter(e) {
    e.preventDefault();
    const email = e.target.querySelector('input').value;
    showToast('🎉 Đăng ký thành công! Kiểm tra email ' + email + ' để nhận mã giảm giá 10%.', 'fa-envelope');
    e.target.reset();
}

// ============ SMOOTH SCROLL ============
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', function(e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});
</script>
</body>
</html>
