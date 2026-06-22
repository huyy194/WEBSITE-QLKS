USE quanlykhachsan;

-- Thêm tài khoản và nhân viên
INSERT IGNORE INTO TaiKhoan (TenDangNhap, MatKhau, HoTen, VaiTro) VALUES 
('nv_an', '123456', 'Nguyễn Văn An', 'nhanvien'),
('nv_binh', '123456', 'Trần Thị Bình', 'nhanvien'),
('nv_cuc', '123456', 'Lê Thu Cúc', 'nhanvien');

INSERT IGNORE INTO NhanVien (MaTK, ChucVu, CaLamViec, Luong, SDT, Email, NgayVaoLam) 
SELECT MaTK, 'Lễ tân', 'Ca Sáng', 8000000, '0988123456', 'an@gmail.com', '2023-05-01' FROM TaiKhoan WHERE TenDangNhap='nv_an';

INSERT IGNORE INTO NhanVien (MaTK, ChucVu, CaLamViec, Luong, SDT, Email, NgayVaoLam) 
SELECT MaTK, 'Dọn phòng', 'Ca Chiều', 7000000, '0977654321', 'binh@gmail.com', '2023-06-15' FROM TaiKhoan WHERE TenDangNhap='nv_an';

-- Thêm khách hàng để đánh giá
INSERT IGNORE INTO KhachHang (HoTen, CCCD, SDT, Email) VALUES ('Khách Hàng Vip', '012345678901', '0999888777', 'vip@khachhang.com');

-- Tạo lịch làm việc cho tuần này
INSERT INTO LichLamViec (MaNV, MaCa, NgayLam) 
SELECT nv.MaNV, 1, DATE_ADD(CURDATE(), INTERVAL seq DAY) 
FROM NhanVien nv 
JOIN (SELECT 0 AS seq UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) seqs
WHERE nv.ChucVu = 'Lễ tân';

-- Đánh giá
INSERT INTO DanhGiaNhanVien (MaNV, MaKH, SoSao, NhanXet, NgayDanhGia) 
SELECT nv.MaNV, kh.MaKH, 5, 'Phục vụ rất nhiệt tình, vui vẻ.', CURDATE() 
FROM NhanVien nv, KhachHang kh 
WHERE nv.ChucVu = 'Lễ tân' LIMIT 1;

-- Thưởng phạt
INSERT INTO ThuongPhat (MaNV, Loai, SoTien, LyDo, Ngay) 
SELECT nv.MaNV, 'thuong', 500000, 'Làm việc xuất sắc, khách hàng khen ngợi', CURDATE() 
FROM NhanVien nv 
WHERE nv.ChucVu = 'Lễ tân' LIMIT 1;

-- Chấm công
INSERT INTO ChamCong (MaNV, NgayCC, GioVao, GioRa, TrangThai)
SELECT nv.MaNV, CURDATE(), '06:00:00', '14:00:00', 'dung_gio'
FROM NhanVien nv
WHERE nv.ChucVu = 'Lễ tân' LIMIT 1;

