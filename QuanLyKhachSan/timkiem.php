<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once 'config/database.php';

$keyword  = isset($_GET['q'])       ? $conn->real_escape_string(trim($_GET['q'])) : '';
$max_price= isset($_GET['price'])   ? (int)$_GET['price']  : 5000000;
$guest    = isset($_GET['guest'])   ? (int)$_GET['guest']  : 0;
$amenity  = isset($_GET['amenity']) ? $conn->real_escape_string($_GET['amenity']) : '';
$khu_vuc  = isset($_GET['khuvuc']) ? $conn->real_escape_string($_GET['khuvuc'])  : '';

// Keyword → khu vực (giữ nguyên logic gốc)
$keyword_map = [
    'biển'=>'Phan Thiết','beach'=>'Phan Thiết','phan thiết'=>'Phan Thiết','cát'=>'Phan Thiết','bãi biển'=>'Phan Thiết',
    'núi'=>'Tây Ninh','mountain'=>'Tây Ninh','tây ninh'=>'Tây Ninh','rừng'=>'Tây Ninh','thiên nhiên'=>'Tây Ninh','bà đen'=>'Tây Ninh',
    'phố'=>'Hồ Chí Minh','thành phố'=>'Hồ Chí Minh','sài gòn'=>'Hồ Chí Minh','hồ chí minh'=>'Hồ Chí Minh','trung tâm'=>'Hồ Chí Minh','city'=>'Hồ Chí Minh','mua sắm'=>'Hồ Chí Minh',
    'hà nội'=>'Hà Nội','hanoi'=>'Hà Nội','phố cổ'=>'Hà Nội','hồ gươm'=>'Hà Nội','thủ đô'=>'Hà Nội','văn hóa'=>'Hà Nội','di sản'=>'Hà Nội',
    'đà nẵng'=>'Đà Nẵng','danang'=>'Đà Nẵng','mỹ khê'=>'Đà Nẵng','sơn trà'=>'Đà Nẵng','miền trung'=>'Đà Nẵng','hàn'=>'Đà Nẵng','ngũ hành sơn'=>'Đà Nẵng',
];

$detected_region = '';
$keyword_lower = mb_strtolower($keyword, 'UTF-8');
foreach ($keyword_map as $kw => $region) {
    if (mb_strpos($keyword_lower, $kw) !== false) { $detected_region = $region; break; }
}

$sql = "SELECT p.*, lp.TenLoai, lp.GiaPhong, lp.SoNguoiToiDa, lp.TienNghi, lp.KhuVuc, lp.TuKhoa, lp.HinhAnh
        FROM Phong p JOIN LoaiPhong lp ON p.MaLoai = lp.MaLoai
        WHERE lp.GiaPhong <= $max_price";
if ($detected_region)         $sql .= " AND lp.KhuVuc = '$detected_region'";
elseif ($khu_vuc)             $sql .= " AND lp.KhuVuc = '$khu_vuc'";
if ($keyword && !$detected_region) $sql .= " AND (lp.TenLoai LIKE '%$keyword%' OR p.MaPhong LIKE '%$keyword%' OR lp.TuKhoa LIKE '%$keyword%' OR lp.KhuVuc LIKE '%$keyword%')";
if ($guest > 0)               $sql .= " AND lp.SoNguoiToiDa >= $guest";
if ($amenity)                 $sql .= " AND lp.TienNghi LIKE '%$amenity%'";
$sql .= " ORDER BY lp.KhuVuc ASC, lp.GiaPhong ASC";
$result = $conn->query($sql);

$kv_res = $conn->query("SELECT DISTINCT KhuVuc FROM LoaiPhong WHERE KhuVuc IS NOT NULL ORDER BY KhuVuc");
$all_regions = [];
while ($kv = $kv_res->fetch_assoc()) $all_regions[] = $kv['KhuVuc'];

$rooms_by_region = []; $all_rooms = [];
if ($result) {
    while ($rm = $result->fetch_assoc()) {
        $region = $rm['KhuVuc'] ?? 'Khác';
        $rooms_by_region[$region][] = $rm;
        $all_rooms[] = $rm;
    }
}

// Ảnh phòng khách sạn theo khu vực (Unsplash - ảnh thật của mỗi vùng)
$region_hotel_images = [
    'Hà Nội'      => 'https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=800&q=80',   // Hotel Hà Nội - kiến trúc Pháp
    'Hồ Chí Minh' => 'https://images.unsplash.com/photo-1583417319070-4a69db38a482?w=800&q=80',   // Hotel HCM - hiện đại
    'Đà Nẵng'     => 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80',   // Hotel biển Đà Nẵng
    'Phan Thiết'  => 'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=800&q=80',   // Resort biển Phan Thiết
    'Tây Ninh'    => 'https://images.unsplash.com/photo-1596178065887-1198b6148b2b?w=800&q=80',   // Resort thiên nhiên
];

// Fallback mặc định nếu DB không có ảnh
$region_fallback_images = $region_hotel_images;

$region_icons = [
    'Phan Thiết'=>'fa-umbrella-beach','Hà Nội'=>'fa-landmark',
    'Hồ Chí Minh'=>'fa-city','Tây Ninh'=>'fa-mountain-sun','Đà Nẵng'=>'fa-water',
];
$region_colors = [
    'Phan Thiết'=>'#0ea5e9','Hà Nội'=>'#ef4444',
    'Hồ Chí Minh'=>'#8b5cf6','Tây Ninh'=>'#22c55e','Đà Nẵng'=>'#f59e0b',
];
$region_desc = [
    'Hà Nội'      => 'Phố cổ, Hồ Gươm & văn hóa ngàn năm',
    'Hồ Chí Minh' => 'Trung tâm sầm uất, mua sắm & ẩm thực',
    'Đà Nẵng'     => 'Bãi Mỹ Khê, Sơn Trà & cầu Rồng',
    'Phan Thiết'  => 'Bãi biển riêng tư, resort nghỉ dưỡng',
    'Tây Ninh'    => 'Núi Bà Đen, thiên nhiên hùng vĩ',
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tìm Phòng — K-Hotel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<style>
/* ============================================================
   K-HOTEL TIMKIEM — Obsidian & Gold Design System
============================================================ */
:root {
    --obsidian:  #0C0C0E;
    --obsidian2: #131318;
    --obsidian3: #1A1A22;
    --dark-bg:   #F2EFE9;
    --cream:     #FAF8F3;
    --white:     #FFFFFF;
    --gold:      #C8A96E;
    --gold-l:    #E2C98D;
    --gold-dim:  rgba(200,169,110,0.14);
    --gold-pale: #F8F2E6;
    --text:      #1A1A1F;
    --text2:     #5A5A6A;
    --text3:     #9898AA;
    --border:    #E5E0D5;
    --radius:    14px;
    --radius-sm: 8px;
    --serif:     'Cormorant Garamond', Georgia, serif;
    --sans:      'DM Sans', system-ui, sans-serif;
    --ease:      cubic-bezier(0.4,0,0.2,1);
    --shadow:    0 4px 24px rgba(13,13,15,0.08);
    --shadow-lg: 0 12px 48px rgba(13,13,15,0.14);
}

*, *::before, *::after { box-sizing: border-box; }

body {
    font-family: var(--sans);
    background: var(--cream);
    color: var(--text);
    -webkit-font-smoothing: antialiased;
    margin: 0;
}

/* ===== NAVBAR ===== */
.kh-nav {
    background: var(--obsidian);
    border-bottom: 1px solid rgba(200,169,110,0.15);
    padding: 0 0;
    position: sticky;
    top: 0;
    z-index: 200;
}

.kh-nav .container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 62px;
    padding: 0 28px;
}

.kh-brand {
    font-family: var(--serif);
    font-size: 22px;
    font-weight: 600;
    color: var(--gold);
    text-decoration: none;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.kh-brand::before { content: '✦'; font-size: 12px; opacity: 0.6; }

.kh-nav-links { display: flex; align-items: center; gap: 8px; }

.kh-nav-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 500;
    text-decoration: none;
    transition: all .2s var(--ease);
    font-family: var(--sans);
    border: 1px solid transparent;
    cursor: pointer;
    background: none;
}

.kh-nav-btn.ghost {
    color: rgba(255,255,255,0.55);
    border-color: rgba(255,255,255,0.08);
}

.kh-nav-btn.ghost:hover {
    color: rgba(255,255,255,0.9);
    border-color: rgba(255,255,255,0.2);
    background: rgba(255,255,255,0.04);
}

.kh-nav-btn.gold {
    background: var(--gold);
    color: var(--obsidian);
    font-weight: 700;
}

.kh-nav-btn.gold:hover {
    background: var(--gold-l);
    box-shadow: 0 4px 14px rgba(200,169,110,0.3);
}

/* ===== HERO SEARCH BANNER ===== */
.search-hero {
    background: var(--obsidian);
    padding: 36px 0 32px;
    position: relative;
    overflow: hidden;
}

.search-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse 500px 300px at 10% 100%, rgba(200,169,110,0.07) 0%, transparent 70%),
        radial-gradient(ellipse 400px 300px at 90% 0%,   rgba(200,169,110,0.05) 0%, transparent 70%);
    pointer-events: none;
}

.search-hero::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(200,169,110,0.3), transparent);
}

.search-hero-inner { position: relative; z-index: 1; }

.search-eyebrow {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.search-eyebrow::before {
    content: '';
    width: 24px; height: 1px;
    background: var(--gold);
    opacity: 0.5;
}

.search-hero h1 {
    font-family: var(--serif);
    font-size: clamp(28px, 4vw, 44px);
    font-weight: 600;
    color: white;
    line-height: 1.1;
    margin-bottom: 6px;
}

.search-hero h1 em { color: var(--gold); font-style: italic; }

.search-hero p {
    font-size: 13.5px;
    font-weight: 300;
    color: rgba(255,255,255,0.45);
    margin-bottom: 0;
}

/* ===== REGION PILL TABS ===== */
.region-tabs {
    background: var(--obsidian2);
    border-bottom: 1px solid rgba(255,255,255,0.05);
    padding: 14px 0;
}

.region-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    border-radius: 20px;
    font-size: 12.5px;
    font-weight: 500;
    text-decoration: none;
    transition: all .2s var(--ease);
    font-family: var(--sans);
    border: 1px solid transparent;
    white-space: nowrap;
}

.region-tab.all {
    background: rgba(255,255,255,0.05);
    color: rgba(255,255,255,0.55);
    border-color: rgba(255,255,255,0.08);
}

.region-tab.all.active, .region-tab.all:hover {
    background: rgba(255,255,255,0.1);
    color: white;
    border-color: rgba(255,255,255,0.15);
}

.region-tab.colored {
    background: rgba(255,255,255,0.04);
    color: rgba(255,255,255,0.5);
    border-color: rgba(255,255,255,0.06);
}

.region-tab.colored.active {
    color: white;
}

.region-tab.colored:hover {
    background: rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.85);
}

/* ===== QUICK SUGGESTION CARDS (giữ nguyên ảnh gốc, chỉ đổi layout wrapper) ===== */
.suggest-section {
    padding: 40px 0;
}

.suggest-title {
    font-family: var(--serif);
    font-size: 32px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 4px;
}

.suggest-sub {
    font-size: 13px;
    color: var(--text2);
    margin-bottom: 24px;
}

/* --- Ô card địa điểm (GIỮ NGUYÊN ảnh gốc, chỉ style wrapper) --- */
.region-card {
    border-radius: var(--radius);
    overflow: hidden;
    cursor: pointer;
    transition: all 0.35s var(--ease);
    text-decoration: none;
    display: block;
    position: relative;
    height: 200px;
    box-shadow: var(--shadow);
}

.region-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-lg);
}

.region-card-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.82) 0%, rgba(0,0,0,0.1) 55%, transparent 100%);
    transition: background 0.3s;
    z-index: 1;
}

.region-card:hover .region-card-overlay {
    background: linear-gradient(to top, rgba(0,0,0,0.88) 0%, rgba(0,0,0,0.25) 55%, transparent 100%);
}

.region-card-bg {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
    transition: transform 0.6s var(--ease);
}

.region-card:hover .region-card-bg { transform: scale(1.06); }

.region-card-body {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    padding: 18px 16px;
    z-index: 2;
    text-align: center;
}

.region-card-icon {
    font-size: 20px;
    color: white;
    margin-bottom: 6px;
    display: block;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.8));
}

.region-card-label {
    font-size: 14px;
    font-weight: 700;
    color: white;
    display: block;
    margin-bottom: 3px;
    text-shadow: 0 2px 6px rgba(0,0,0,0.9);
    letter-spacing: 0.2px;
}

.region-card-desc {
    font-size: 11px;
    color: rgba(255,255,255,0.75);
    display: block;
    text-shadow: 0 1px 4px rgba(0,0,0,0.9);
    line-height: 1.4;
}

/* Arrow hint */
.region-card-arrow {
    position: absolute;
    top: 14px; right: 14px;
    z-index: 2;
    width: 28px; height: 28px;
    border-radius: 50%;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center;
    color: white;
    font-size: 11px;
    opacity: 0;
    transform: translateY(4px);
    transition: all 0.25s;
}

.region-card:hover .region-card-arrow { opacity: 1; transform: translateY(0); }

/* ===== FILTER SIDEBAR ===== */
.filter-panel {
    background: var(--white);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    overflow: hidden;
    position: sticky;
    top: 80px;
}

.filter-header {
    background: var(--obsidian);
    padding: 18px 20px;
    border-bottom: 1px solid rgba(200,169,110,0.15);
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-header h5 {
    font-family: var(--serif);
    font-size: 18px;
    font-weight: 600;
    color: white;
    margin: 0;
}

.filter-header i { color: var(--gold); }

.filter-body { padding: 20px; }

.filter-label {
    display: block;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--text3);
    margin-bottom: 7px;
}

.filter-input {
    width: 100%;
    padding: 10px 12px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: var(--sans);
    font-size: 13.5px;
    color: var(--text);
    background: var(--cream);
    outline: none;
    transition: border-color .2s, box-shadow .2s;
    appearance: none;
}

.filter-input:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(200,169,110,0.1);
    background: white;
}

.filter-hint {
    font-size: 11px;
    color: var(--text3);
    margin-top: 5px;
    line-height: 1.5;
}

.price-display {
    font-size: 15px;
    font-weight: 700;
    color: var(--obsidian);
    font-family: var(--serif);
}

.filter-range {
    -webkit-appearance: none;
    appearance: none;
    width: 100%;
    height: 4px;
    background: linear-gradient(to right, var(--gold) 0%, var(--gold) var(--pct, 80%), var(--border) var(--pct, 80%));
    border-radius: 2px;
    outline: none;
    cursor: pointer;
}

.filter-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 18px; height: 18px;
    border-radius: 50%;
    background: var(--gold);
    border: 2px solid white;
    box-shadow: 0 2px 8px rgba(200,169,110,0.4);
    cursor: pointer;
}

.filter-range::-moz-range-thumb {
    width: 18px; height: 18px;
    border-radius: 50%;
    background: var(--gold);
    border: 2px solid white;
    cursor: pointer;
}

.filter-divider { border: none; border-top: 1px solid var(--border); margin: 16px 0; }

.btn-filter {
    width: 100%;
    padding: 12px;
    background: var(--obsidian);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-family: var(--sans);
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s var(--ease);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
}

.btn-filter:hover { background: #1e3a5f; box-shadow: 0 4px 16px rgba(13,13,15,0.2); }

.btn-reset {
    display: block;
    width: 100%;
    text-align: center;
    padding: 9px;
    margin-top: 8px;
    font-size: 12.5px;
    color: var(--text3);
    text-decoration: none;
    border-radius: var(--radius-sm);
    transition: all .2s;
    border: 1px solid var(--border);
    background: var(--cream);
    font-family: var(--sans);
    font-weight: 500;
}

.btn-reset:hover { color: #ef4444; border-color: #fecaca; background: #fef2f2; }

/* ===== RESULTS AREA ===== */
.results-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 10px;
}

.results-title {
    font-family: var(--serif);
    font-size: 28px;
    font-weight: 600;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 10px;
}

.count-badge {
    display: inline-flex;
    align-items: center;
    background: var(--obsidian);
    color: var(--gold);
    font-size: 12px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 20px;
    font-family: var(--sans);
    vertical-align: middle;
}

/* Smart detection alert */
.detect-alert {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--gold-pale);
    border: 1px solid rgba(200,169,110,0.3);
    border-left: 3px solid var(--gold);
    border-radius: var(--radius-sm);
    padding: 12px 16px;
    margin-bottom: 20px;
    font-size: 13.5px;
    color: var(--text);
}

.detect-alert i { color: var(--gold); font-size: 16px; flex-shrink: 0; }
.detect-alert a { color: var(--gold); text-decoration: none; font-weight: 600; margin-left: 8px; }
.detect-alert a:hover { text-decoration: underline; }

/* ===== REGION GROUP HEADER ===== */
.region-group { margin-bottom: 40px; }

.region-group-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--border);
}

.region-group-icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.region-group-title {
    font-family: var(--serif);
    font-size: 22px;
    font-weight: 600;
    color: var(--text);
    margin: 0;
}

.region-group-count {
    font-size: 12px;
    color: var(--text3);
    font-weight: 400;
    font-family: var(--sans);
}

/* ===== ROOM CARDS ===== */
.room-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    transition: all .3s var(--ease);
    height: 100%;
    display: flex;
    flex-direction: column;
}

.room-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: transparent;
}

.room-card-img {
    position: relative;
    overflow: hidden;
    aspect-ratio: 4/3;
    flex-shrink: 0;
}

.room-card-img img {
    width: 100%; height: 100%;
    object-fit: cover;
    transition: transform .6s var(--ease);
}

.room-card:hover .room-card-img img { transform: scale(1.06); }

.room-type-badge {
    position: absolute;
    top: 12px; left: 12px;
    background: var(--obsidian);
    color: var(--gold);
    font-size: 9.5px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 4px;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.room-id-badge {
    position: absolute;
    top: 12px; right: 12px;
    font-size: 10.5px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 4px;
    letter-spacing: 0.5px;
}

.room-status-bar {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    padding: 8px 12px;
    font-size: 11px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
}

.room-status-bar.available {
    background: linear-gradient(to top, rgba(10,125,108,0.9), rgba(10,125,108,0));
    color: #6EE7D8;
}

.room-status-bar.occupied {
    background: linear-gradient(to top, rgba(180,30,30,0.88), rgba(180,30,30,0));
    color: #FCA5A5;
}

.room-card-body {
    padding: 16px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.room-type-label {
    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 4px;
}

.room-card-body h6 {
    font-family: var(--serif);
    font-size: 17px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 10px;
    line-height: 1.2;
}

.room-price {
    font-family: var(--serif);
    font-size: 20px;
    font-weight: 600;
    color: var(--obsidian);
    margin-bottom: 10px;
}

.room-price small {
    font-family: var(--sans);
    font-size: 11px;
    color: var(--text3);
    font-weight: 400;
}

.room-meta {
    display: flex;
    gap: 12px;
    font-size: 11.5px;
    color: var(--text2);
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.room-meta span { display: flex; align-items: center; gap: 4px; }
.room-meta i { font-size: 10px; color: var(--text3); }

.room-amenities {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-bottom: 14px;
    flex: 1;
}

.amenity-chip {
    font-size: 10px;
    font-weight: 500;
    padding: 3px 8px;
    border-radius: 4px;
    background: var(--cream);
    color: var(--text2);
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 3px;
}

.amenity-chip i { font-size: 8px; color: var(--gold); }

.btn-book {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    padding: 10px;
    border-radius: var(--radius-sm);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: all .2s var(--ease);
    font-family: var(--sans);
    border: none;
    cursor: pointer;
    margin-top: auto;
    position: relative;
    z-index: 10;
    pointer-events: auto !important;
}

.btn-book.available {
    background: var(--obsidian);
    color: white;
}

.btn-book.available:hover {
    background: var(--gold);
    color: var(--obsidian);
    box-shadow: 0 4px 16px rgba(200,169,110,0.3);
}

.btn-book.occupied-btn {
    background: var(--cream);
    color: var(--text);
    border: 1px solid var(--border);
}

.btn-book.occupied-btn:hover {
    background: var(--white);
    border-color: var(--gold);
    color: var(--gold);
}

.btn-book.disabled-btn {
    background: var(--cream);
    color: var(--text3);
    border: 1px solid var(--border);
    cursor: not-allowed;
}

/* ===== EMPTY STATE ===== */
.empty-state {
    text-align: center;
    padding: 80px 20px;
}

.empty-icon {
    font-size: 52px;
    margin-bottom: 20px;
    opacity: 0.25;
    display: block;
    color: var(--text);
}

.empty-state h4 {
    font-family: var(--serif);
    font-size: 26px;
    color: var(--text);
    margin-bottom: 10px;
}

.empty-state p { font-size: 14px; color: var(--text2); }

.empty-suggest { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-top: 20px; }

.empty-suggest a {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 16px;
    border-radius: 20px;
    border: 1px solid var(--border);
    font-size: 13px;
    color: var(--text2);
    text-decoration: none;
    background: white;
    transition: all .2s;
}

.empty-suggest a:hover { border-color: var(--gold); color: var(--gold); background: var(--gold-pale); }

/* ===== FOOTER ===== */
.kh-footer {
    background: var(--obsidian);
    border-top: 1px solid rgba(200,169,110,0.12);
    padding: 28px 0;
    margin-top: 60px;
}

.kh-footer-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}

.kh-footer-brand {
    font-family: var(--serif);
    font-size: 18px;
    font-weight: 600;
    color: var(--gold);
}

.kh-footer-copy {
    font-size: 12px;
    color: rgba(255,255,255,0.25);
}

/* ===== REVEAL ANIMATION ===== */
.fade-in {
    opacity: 0;
    animation: fadeUp .55s var(--ease) forwards;
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
}

.fade-in.d1 { animation-delay: .08s; }
.fade-in.d2 { animation-delay: .16s; }
.fade-in.d3 { animation-delay: .24s; }
.fade-in.d4 { animation-delay: .32s; }

/* ===== RESPONSIVE ===== */
@media (max-width: 991px) {
    .search-hero { padding: 30px 0 20px; }
    .search-hero h1 { font-size: 28px; }
    
    .filter-panel { 
        display: none; 
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: 1000;
        border-radius: 0;
        max-height: 100vh;
        overflow-y: auto;
    }
    .filter-panel.show { display: block; }
    .filter-close { display: block !important; position: absolute; top: 15px; right: 20px; color: white; font-size: 20px; cursor: pointer; }

    .results-title { font-size: 22px; }
    .region-tabs .container > div {
        overflow-x: auto;
        padding-bottom: 10px;
    }
    .region-tab { padding: 6px 12px; font-size: 11.5px; }
}

@media (max-width: 576px) {
    .suggest-section .row > div {
        flex: 0 0 100%;
        max-width: 100%;
    }
    .region-card { height: 160px; }
}
</style>
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="kh-nav">
    <div class="container">
        <a href="index.php" class="kh-brand">K-Hotel</a>
        <div class="kh-nav-links">
            <a href="index.php" class="kh-nav-btn ghost">
                <i class="fa-solid fa-house"></i> Trang chủ
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="lichsu.php" class="kh-nav-btn ghost">
                <i class="fa-solid fa-clock-rotate-left"></i> Lịch sử
            </a>
            <a href="auth/logout.php" class="kh-nav-btn ghost">
                <i class="fa-solid fa-right-from-bracket"></i> Đăng xuất
            </a>
            <?php else: ?>
            <a href="auth/login.php" class="kh-nav-btn gold">
                <i class="fa-solid fa-right-to-bracket"></i> Đăng nhập
            </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- ===== HERO SEARCH BANNER ===== -->
<div class="search-hero">
    <div class="container search-hero-inner">
        <div class="row align-items-center g-4">
            <div class="col-lg-5">
                <div class="search-eyebrow">Tìm kiếm phòng</div>
                <h1>
                    <?php if($khu_vuc || $detected_region): ?>
                        Phòng tại<br><em><?= htmlspecialchars($khu_vuc ?: $detected_region) ?></em>
                    <?php elseif($keyword): ?>
                        Kết quả cho<br><em>"<?= htmlspecialchars($keyword) ?>"</em>
                    <?php else: ?>
                        Bạn muốn<br><em>đi đâu?</em>
                    <?php endif; ?>
                </h1>
                <p>
                    <?php if(count($all_rooms) > 0): ?>
                        Tìm thấy <strong style="color:var(--gold)"><?= count($all_rooms) ?> phòng</strong> phù hợp
                    <?php else: ?>
                        Khám phá các điểm đến tuyệt vời cùng K-Hotel
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- ===== REGION PILL TABS ===== -->
<div class="region-tabs">
    <div class="container">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a href="timkiem.php" class="region-tab all <?= (empty($khu_vuc) && empty($keyword)) ? 'active' : '' ?>">
                <i class="fa-solid fa-globe"></i> Tất cả
            </a>
            <?php foreach ($all_regions as $r):
                $ic = $region_icons[$r] ?? 'fa-map-marker';
                $cl = $region_colors[$r] ?? '#aaa';
                $active = ($khu_vuc == $r || $detected_region == $r);
            ?>
            <a href="timkiem.php?khuvuc=<?= urlencode($r) ?>"
               class="region-tab colored <?= $active ? 'active' : '' ?>"
               style="<?= $active ? "background:rgba(200,169,110,0.12);border-color:rgba(200,169,110,0.3);color:var(--gold);" : '' ?>">
                <i class="fa-solid <?= $ic ?>"></i> <?= $r ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ===== MAIN CONTENT ===== -->
<div class="container" style="padding-top:40px;padding-bottom:20px;">

    <!-- Quick Suggest (chỉ hiện khi chưa lọc) -->
    <?php if (empty($keyword) && empty($khu_vuc)): ?>
    <div class="suggest-section fade-in">
        <div class="suggest-title">Bạn muốn đi đâu?</div>
        <p class="suggest-sub">Chọn điểm đến yêu thích để khám phá các phòng phù hợp</p>
        <div class="row g-3">
            <?php
            $suggestions = [
                ['q'=>'biển',    'label'=>'Biển — Phan Thiết', 'icon'=>'fa-umbrella-beach','image'=>'assets/img/phanthiet.png',    'desc'=>'Bãi biển riêng tư, sóng biển êm đềm'],
                ['q'=>'núi',     'label'=>'Núi — Tây Ninh',   'icon'=>'fa-mountain-sun',  'image'=>'assets/img/tayninh.png',       'desc'=>'Thiên nhiên hùng vĩ, núi Bà Đen'],
                ['q'=>'phố cổ',  'label'=>'Phố Cổ — Hà Nội', 'icon'=>'fa-landmark',      'image'=>'https://vj-prod-website-cms.s3.ap-southeast-1.amazonaws.com/shutterstock1391898416-1646649508378.png','desc'=>'Văn hóa, di sản, Hồ Gươm'],
                ['q'=>'thành phố','label'=>'Thành Phố — HCM', 'icon'=>'fa-city',          'image'=>'https://images.unsplash.com/photo-1583417319070-4a69db38a482?q=80&w=800&auto=format&fit=crop','desc'=>'Trung tâm sầm uất, mua sắm'],
            ];
            foreach ($suggestions as $i => $s): ?>
            <div class="col-md-3 col-6 fade-in d<?= $i+1 ?>">
                <a href="timkiem.php?q=<?= urlencode($s['q']) ?>" class="region-card">
                    <div class="region-card-bg" style="background-image:url('<?= $s['image'] ?>')"></div>
                    <div class="region-card-overlay"></div>
                    <div class="region-card-arrow"><i class="fa-solid fa-arrow-right"></i></div>
                    <div class="region-card-body">
                        <i class="fa-solid <?= $s['icon'] ?> region-card-icon"></i>
                        <span class="region-card-label"><?= $s['label'] ?></span>
                        <span class="region-card-desc"><?= $s['desc'] ?></span>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Mobile Filter Toggle -->
    <div class="d-lg-none mb-3">
        <button class="btn-filter" id="mobileFilterToggle">
            <i class="fa-solid fa-sliders"></i> Lọc kết quả
        </button>
    </div>

    <div class="row g-4">

        <!-- ===== FILTER SIDEBAR ===== -->
        <div class="col-md-3">
            <div class="filter-panel fade-in">
                <div class="filter-header">
                    <i class="fa-solid fa-sliders"></i>
                    <h5>Bộ lọc</h5>
                    <i class="fa-solid fa-xmark filter-close d-none" id="filterClose"></i>
                </div>
                <div class="filter-body">
                    <form method="GET">

                        <div class="mb-3">
                            <label class="filter-label">Điểm đến</label>
                            <input type="text" name="q" class="filter-input"
                                value="<?= htmlspecialchars($keyword) ?>"
                                placeholder="biển, núi, phố cổ, sài gòn...">
                            <div class="filter-hint">Gợi ý: <em>biển, núi, hà nội, sài gòn</em></div>
                        </div>

                        <div class="mb-3">
                            <label class="filter-label">Khu vực</label>
                            <select name="khuvuc" class="filter-input">
                                <option value="">Tất cả khu vực</option>
                                <?php foreach ($all_regions as $r): ?>
                                <option value="<?= $r ?>" <?= $khu_vuc == $r ? 'selected' : '' ?>><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="filter-label">Số lượng người</label>
                            <input type="number" name="guest" class="filter-input"
                                value="<?= $guest ?>" min="1" placeholder="Số khách">
                        </div>

                        <div class="mb-3">
                            <label class="filter-label" style="display:flex;justify-content:space-between">
                                <span>Giá tối đa</span>
                                <span class="price-display" id="priceDisplay"><?= number_format($max_price) ?>₫</span>
                            </label>
                            <input type="range" class="filter-range" name="price"
                                id="priceRange"
                                min="200000" max="5000000" step="100000"
                                value="<?= $max_price ?>">
                        </div>

                        <div class="mb-3">
                            <label class="filter-label">Tiện nghi</label>
                            <select name="amenity" class="filter-input">
                                <option value="">Tất cả tiện nghi</option>
                                <option value="Ban công"  <?= $amenity=='Ban công'  ?'selected':''?>>🌅 Ban công</option>
                                <option value="Bồn tắm"   <?= $amenity=='Bồn tắm'   ?'selected':''?>>🛁 Bồn tắm</option>
                                <option value="View biển" <?= $amenity=='View biển' ?'selected':''?>>🌊 View biển</option>
                                <option value="View núi"  <?= $amenity=='View núi'  ?'selected':''?>>⛰️ View núi</option>
                                <option value="Hồ bơi"    <?= $amenity=='Hồ bơi'    ?'selected':''?>>🏊 Hồ bơi</option>
                            </select>
                        </div>

                        <hr class="filter-divider">

                        <button type="submit" class="btn-filter">
                            <i class="fa-solid fa-magnifying-glass"></i> Tìm phòng
                        </button>
                        <a href="timkiem.php" class="btn-reset">
                            <i class="fa-solid fa-xmark me-1"></i> Xóa bộ lọc
                        </a>
                    </form>
                </div>
            </div>
        </div>

        <!-- ===== RESULTS ===== -->
        <div class="col-md-9">

            <!-- Smart detection alert -->
            <?php if ($detected_region): ?>
            <div class="detect-alert fade-in">
                <i class="fa-solid <?= $region_icons[$detected_region] ?? 'fa-map' ?>"></i>
                <div>
                    Tìm kiếm "<strong><?= htmlspecialchars($keyword) ?></strong>"
                    → Đề xuất phòng tại <strong><?= $detected_region ?></strong>
                    <a href="timkiem.php">Xóa tìm kiếm</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Results header -->
            <div class="results-header fade-in">
                <div class="results-title">
                    <?php if ($khu_vuc || $detected_region): ?>
                        <i class="fa-solid <?= $region_icons[$khu_vuc ?: $detected_region] ?? 'fa-map-marker' ?>"
                           style="color:<?= $region_colors[$khu_vuc ?: $detected_region] ?? '#aaa' ?>;font-size:22px"></i>
                        <?= htmlspecialchars($khu_vuc ?: $detected_region) ?>
                    <?php else: ?>
                        Kết quả tìm kiếm
                    <?php endif; ?>
                    <span class="count-badge"><?= count($all_rooms) ?> phòng</span>
                </div>
            </div>

            <!-- Room groups by region -->
            <?php if (count($rooms_by_region) > 0): ?>
                <?php foreach ($rooms_by_region as $region => $rooms):
                    $icon  = $region_icons[$region]  ?? 'fa-map-marker';
                    $color = $region_colors[$region]  ?? '#aaa';
                    $desc  = $region_desc[$region]    ?? '';
                    $hotel_img = $region_hotel_images[$region] ?? 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=800&q=80';
                ?>
                <div class="region-group fade-in">
                    <div class="region-group-header">
                        <div class="region-group-icon" style="background:<?= $color ?>18;color:<?= $color ?>">
                            <i class="fa-solid <?= $icon ?>"></i>
                        </div>
                        <div>
                            <div class="region-group-title">
                                <?= htmlspecialchars($region) ?>
                                <span class="region-group-count">&nbsp;— <?= count($rooms) ?> phòng<?= $desc ? " · $desc" : '' ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <?php foreach ($rooms as $idx => $rm):
                            // Ảnh phòng: ưu tiên DB, fallback theo khu vực
                            $img = (!empty($rm['HinhAnh']) && file_exists($rm['HinhAnh']))
                                ? htmlspecialchars($rm['HinhAnh'])
                                : $hotel_img;
                            $amenities_arr = array_slice(array_map('trim', explode(',', $rm['TienNghi'])), 0, 4);
                            $is_avail = ($rm['TrangThai'] == 'Trống');
                        ?>
                        <div class="col-md-6 col-lg-4 fade-in d<?= min($idx+1,4) ?>">
                            <div class="room-card">
                                <div class="room-card-img">
                                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($rm['TenLoai']) ?>">

                                    <div class="room-type-badge"><?= htmlspecialchars($rm['TenLoai']) ?></div>

                                    <div class="room-id-badge"
                                         style="background:<?= $color ?>22;color:<?= $color ?>">
                                        <i class="fa-solid <?= $icon ?> me-1" style="font-size:9px"></i><?= $rm['MaPhong'] ?>
                                    </div>

                                    <div class="room-status-bar <?= $is_avail ? 'available' : 'occupied' ?>">
                                        <?php if($is_avail): ?>
                                            <i class="fa-solid fa-circle-check" style="font-size:10px"></i> Còn trống
                                        <?php else: ?>
                                            <i class="fa-solid fa-lock" style="font-size:10px"></i> Đang có khách
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="room-card-body">
                                    <div class="room-type-label"><?= htmlspecialchars($region) ?></div>
                                    <h6><?= htmlspecialchars($rm['TenLoai']) ?></h6>

                                    <div class="room-price">
                                        <?= number_format($rm['GiaPhong']) ?>₫
                                        <small> / đêm</small>
                                    </div>

                                    <div class="room-meta">
                                        <span><i class="fa-solid fa-user-group"></i> <?= $rm['SoNguoiToiDa'] ?> người</span>
                                        <span><i class="fa-solid fa-map-location-dot" style="color:<?= $color ?>"></i> <?= htmlspecialchars($region) ?></span>
                                    </div>

                                    <div class="room-amenities">
                                        <?php foreach ($amenities_arr as $a): ?>
                                        <div class="amenity-chip">
                                            <i class="fa-solid fa-check"></i>
                                            <?= htmlspecialchars($a) ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <a href="chitiet.php?id=<?= $rm['MaPhong'] ?>" class="btn-book <?= $is_avail ? 'available' : 'occupied-btn' ?>">
                                        <i class="fa-solid <?= $is_avail ? 'fa-calendar-check' : 'fa-circle-info' ?>"></i> 
                                        <?= $is_avail ? 'Xem & Đặt phòng' : 'Xem & Đặt lịch' ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

            <?php else: ?>
            <!-- Empty state -->
            <div class="empty-state fade-in">
                <i class="fa-solid fa-magnifying-glass empty-icon"></i>
                <h4>Không tìm thấy phòng phù hợp</h4>
                <p>Thử điều chỉnh bộ lọc hoặc chọn điểm đến khác</p>
                <div class="empty-suggest">
                    <a href="?q=biển"><i class="fa-solid fa-umbrella-beach"></i> Biển</a>
                    <a href="?q=núi"><i class="fa-solid fa-mountain-sun"></i> Núi</a>
                    <a href="?q=phố cổ"><i class="fa-solid fa-landmark"></i> Phố cổ</a>
                    <a href="?q=thành phố"><i class="fa-solid fa-city"></i> Thành phố</a>
                    <a href="timkiem.php"><i class="fa-solid fa-xmark"></i> Xóa bộ lọc</a>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /col results -->
    </div><!-- /row -->
</div><!-- /container -->

<!-- ===== FOOTER ===== -->
<footer class="kh-footer">
    <div class="container">
        <div class="kh-footer-inner">
            <div class="kh-footer-brand">✦ K-Hotel</div>
            <div class="kh-footer-copy">© 2026 K-Hotel Việt Nam. Bảo lưu mọi quyền.</div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Price range slider
(function(){
    const range   = document.getElementById('priceRange');
    const display = document.getElementById('priceDisplay');
    if (!range) return;

    function update() {
        const min = parseInt(range.min), max = parseInt(range.max), val = parseInt(range.value);
        const pct = ((val - min) / (max - min) * 100).toFixed(1);
        range.style.setProperty('--pct', pct + '%');
        display.textContent = Number(val).toLocaleString('vi-VN') + '₫';
    }

    range.addEventListener('input', update);
    update(); // init

    // Mobile Filter Toggle
    const mobileToggle = document.getElementById('mobileFilterToggle');
    const filterPanel = document.querySelector('.filter-panel');
    const filterClose = document.getElementById('filterClose');

    if (mobileToggle && filterPanel) {
        mobileToggle.addEventListener('click', () => {
            filterPanel.classList.add('show');
            document.body.style.overflow = 'hidden';
        });
    }

    if (filterClose && filterPanel) {
        filterClose.addEventListener('click', () => {
            filterPanel.classList.remove('show');
            document.body.style.overflow = 'auto';
        });
    }
})();
</script>
</body>
</html>
