CREATE DATABASE IF NOT EXISTS quanlykhachsan DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quanlykhachsan;

-- TÀI KHOẢN
CREATE TABLE IF NOT EXISTS TaiKhoan (
    MaTK INT AUTO_INCREMENT PRIMARY KEY,
    TenDangNhap VARCHAR(50) NOT NULL UNIQUE,
    MatKhau VARCHAR(255) NOT NULL,
    HoTen VARCHAR(100) NOT NULL,
    VaiTro VARCHAR(20) DEFAULT 'khach' -- 'khach', 'nhanvien', 'admin'
);

-- NHÂN VIÊN
CREATE TABLE IF NOT EXISTS NhanVien (
    MaNV INT AUTO_INCREMENT PRIMARY KEY,
    MaTK INT UNIQUE,
    ChucVu VARCHAR(50),
    CaLamViec VARCHAR(50),
    Luong DECIMAL(15,2),
    FOREIGN KEY (MaTK) REFERENCES TaiKhoan(MaTK) ON DELETE CASCADE
);

-- KHÁCH HÀNG
CREATE TABLE IF NOT EXISTS KhachHang (
    MaKH INT AUTO_INCREMENT PRIMARY KEY,
    MaTK INT UNIQUE NULL, -- Có thể không có tài khoản (khách vãng lai đến quầy)
    HoTen VARCHAR(100) NOT NULL,
    CCCD VARCHAR(20) UNIQUE NOT NULL,
    SDT VARCHAR(20) NOT NULL,
    Email VARCHAR(100) DEFAULT NULL,
    DiemTichLuy INT DEFAULT 0,
    HangThanhVien VARCHAR(20) DEFAULT 'Đồng', -- 'Đồng', 'Bạc', 'Vàng'
    FOREIGN KEY (MaTK) REFERENCES TaiKhoan(MaTK) ON DELETE SET NULL
);

-- LOẠI PHÒNG
CREATE TABLE IF NOT EXISTS LoaiPhong (
    MaLoai INT AUTO_INCREMENT PRIMARY KEY,
    TenLoai VARCHAR(50) NOT NULL,
    GiaPhong DECIMAL(15,2) NOT NULL,
    SoNguoiToiDa INT DEFAULT 2,
    TienNghi TEXT, -- VD: "Ban công, Bồn tắm, View biển, Tivi"
    HinhAnh VARCHAR(255) DEFAULT 'default_room.jpg'
);

-- PHÒNG
CREATE TABLE IF NOT EXISTS Phong (
    MaPhong VARCHAR(10) PRIMARY KEY,
    MaLoai INT NOT NULL,
    TrangThai VARCHAR(20) DEFAULT 'Trống', -- 'Trống', 'Đang ở', 'Đang dọn dẹp'
    FOREIGN KEY (MaLoai) REFERENCES LoaiPhong(MaLoai) ON DELETE CASCADE
);

-- DỊCH VỤ
CREATE TABLE IF NOT EXISTS DichVu (
    MaDV INT AUTO_INCREMENT PRIMARY KEY,
    TenDV VARCHAR(100) NOT NULL,
    GiaDV DECIMAL(15,2) NOT NULL
);

-- ĐẶT PHÒNG
CREATE TABLE IF NOT EXISTS DatPhong (
    MaDP INT AUTO_INCREMENT PRIMARY KEY,
    MaKH INT NOT NULL,
    MaPhong VARCHAR(10) NOT NULL,
    NgayCheckIn DATETIME NOT NULL,
    NgayCheckOut DATETIME NULL,
    GhiChu TEXT,
    TrangThai VARCHAR(20) DEFAULT 'Chờ xác nhận', -- 'Chờ xác nhận', 'Đã xác nhận', 'Đang ở', 'Đã thanh toán', 'Đã huỷ'
    NguonDat VARCHAR(20) DEFAULT 'Trực tiếp', -- 'Trực tiếp', 'BanOnline'
    FOREIGN KEY (MaKH) REFERENCES KhachHang(MaKH) ON DELETE CASCADE,
    FOREIGN KEY (MaPhong) REFERENCES Phong(MaPhong) ON DELETE CASCADE
);

-- CHI TIẾT SỬ DỤNG DỊCH VỤ
CREATE TABLE IF NOT EXISTS SuDungDichVu (
    MaSD INT AUTO_INCREMENT PRIMARY KEY,
    MaDP INT NOT NULL,
    MaDV INT NOT NULL,
    SoLuong INT DEFAULT 1,
    ThanhTien DECIMAL(15,2) NOT NULL,
    ThoiGian DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (MaDP) REFERENCES DatPhong(MaDP) ON DELETE CASCADE,
    FOREIGN KEY (MaDV) REFERENCES DichVu(MaDV) ON DELETE CASCADE
);

-- ĐÁNH GIÁ (REVIEWS)
CREATE TABLE IF NOT EXISTS DanhGia (
    MaDG INT AUTO_INCREMENT PRIMARY KEY,
    MaKH INT NOT NULL,
    MaLoai INT NOT NULL,
    SoSao INT DEFAULT 5, -- 1-5
    NhanXet TEXT,
    NgayDanhGia DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (MaKH) REFERENCES KhachHang(MaKH) ON DELETE CASCADE,
    FOREIGN KEY (MaLoai) REFERENCES LoaiPhong(MaLoai) ON DELETE CASCADE
);

-- HÓA ĐƠN
CREATE TABLE IF NOT EXISTS HoaDon (
    MaHD INT AUTO_INCREMENT PRIMARY KEY,
    MaDP INT NOT NULL,
    MaNV INT NULL, -- Nhân viên thanh toán
    TienPhong DECIMAL(15,2) NOT NULL,
    TienDichVu DECIMAL(15,2) NOT NULL,
    TongTien DECIMAL(15,2) NOT NULL,
    GiamGiaThanhVien DECIMAL(15,2) DEFAULT 0,
    NgayLanhToan DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (MaDP) REFERENCES DatPhong(MaDP) ON DELETE CASCADE,
    FOREIGN KEY (MaNV) REFERENCES NhanVien(MaNV) ON DELETE SET NULL
);

-- Dữ liệu mẫu ban đầu
INSERT IGNORE INTO TaiKhoan (TenDangNhap, MatKhau, HoTen, VaiTro) VALUES ('admin', 'admin', 'Quản trị viên', 'admin');
INSERT IGNORE INTO NhanVien (MaTK, ChucVu, CaLamViec, Luong) VALUES (1, 'Quản lý', 'Hành chính', 20000000);

-- Các loại phòng phong phú để test AI Gợi ý
INSERT IGNORE INTO LoaiPhong (TenLoai, GiaPhong, SoNguoiToiDa, TienNghi) VALUES 
('Phòng Đơn Tiêu Chuẩn', 300000, 1, 'Tivi, Máy Lạnh, Cửa Sổ Nhỏ'), 
('Phòng Đôi Tiêu Chuẩn', 500000, 2, 'Tivi, Tủ Lạnh, Cửa Sổ Hướng Phố'), 
('Phòng VIP Hướng Biển', 1200000, 2, 'Tivi OLED, Bồn tắm, Ban công siêu rộng, View Biển trực tiếp'),
('Phòng Gia Đình', 850000, 4, '2 Giường Đôi cỡ lớn, Không gian sinh hoạt, Trò chơi điện tử');

INSERT IGNORE INTO Phong (MaPhong, MaLoai, TrangThai) VALUES ('P101', 1, 'Trống'), ('P102', 1, 'Trống'), ('P201', 2, 'Trống'), ('P202', 2, 'Trống'), ('P301', 3, 'Trống'), ('P302', 3, 'Trống'), ('P401', 4, 'Trống');
INSERT IGNORE INTO KhachHang (HoTen, CCCD, SDT, Email) VALUES 
('Lê Thị D', '045612378945', '0933456789', 'ltd@gmail.com');
INSERT IGNORE INTO DichVu (TenDV, GiaDV) VALUES ('Nước suối', 15000), ('Bò húc', 25000), ('Mì xào hải sản', 55000), ('Spa Toàn Thân', 300000), ('Giặt là cao cấp', 50000);

-- BẢNG LƯU TOKEN ĐỔI MẬT KHẨU
CREATE TABLE IF NOT EXISTS PasswordResetTokens (
    MaToken INT AUTO_INCREMENT PRIMARY KEY,
    MaTK INT NOT NULL,
    Token VARCHAR(255) NOT NULL,
    ThoiGianHetHan DATETIME NOT NULL,
    FOREIGN KEY (MaTK) REFERENCES TaiKhoan(MaTK) ON DELETE CASCADE
);