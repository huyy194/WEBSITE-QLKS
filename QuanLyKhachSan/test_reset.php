<?php
require_once 'config/database.php';
$username = 'admin'; // wait, admin doesn't have a KhachHang record.
$res = $conn->query("SELECT * FROM TaiKhoan WHERE TenDangNhap='admin'");
print_r($res->fetch_assoc());
