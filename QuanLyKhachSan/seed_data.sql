USE quanlykhachsan;

SET @manv_lan = (SELECT MaNV FROM NhanVien JOIN TaiKhoan ON NhanVien.MaTK=TaiKhoan.MaTK WHERE TenDangNhap='nv_lan' LIMIT 1);
SET @manv_tuan = (SELECT MaNV FROM NhanVien JOIN TaiKhoan ON NhanVien.MaTK=TaiKhoan.MaTK WHERE TenDangNhap='nv_tuan' LIMIT 1);
SET @manv_mai = (SELECT MaNV FROM NhanVien JOIN TaiKhoan ON NhanVien.MaTK=TaiKhoan.MaTK WHERE TenDangNhap='nv_mai' LIMIT 1);

SET @makh_1 = (SELECT MaKH FROM KhachHang WHERE SDT='0987654321' LIMIT 1);
SET @makh_2 = (SELECT MaKH FROM KhachHang WHERE SDT='0987654322' LIMIT 1);

-- Phân lịch làm việc (Tuần này)
INSERT INTO LichLamViec (MaNV, MaCa, NgayLam) VALUES
(@manv_lan, 1, CURDATE()),
(@manv_lan, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY)),
(@manv_lan, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY)),
(@manv_lan, 2, DATE_ADD(CURDATE(), INTERVAL 3 DAY)),

(@manv_mai, 2, CURDATE()),
(@manv_mai, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY)),
(@manv_mai, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY)),

(@manv_tuan, 3, CURDATE()),
(@manv_tuan, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY)),
(@manv_tuan, 3, DATE_ADD(CURDATE(), INTERVAL 2 DAY));

-- Thêm Chấm Công
INSERT INTO ChamCong (MaNV, NgayCC, GioVao, GioRa, TrangThai) VALUES
(@manv_lan, CURDATE(), '05:55:00', '14:05:00', 'dung_gio'),
(@manv_lan, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '05:50:00', '14:10:00', 'dung_gio'),
(@manv_lan, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '06:15:00', '14:00:00', 'tre'),

(@manv_mai, CURDATE(), '13:55:00', '22:00:00', 'dung_gio'),
(@manv_mai, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '13:58:00', '22:05:00', 'dung_gio'),

(@manv_tuan, CURDATE(), '22:15:00', '06:00:00', 'tre');

-- Thêm Thưởng Phạt
INSERT INTO ThuongPhat (MaNV, Loai, SoTien, LyDo, Ngay) VALUES
(@manv_lan, 'thuong', 500000, 'Thái độ phục vụ xuất sắc', CURDATE()),
(@manv_lan, 'phat', 50000, 'Đi trễ 15 phút', DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
(@manv_mai, 'thuong', 300000, 'Khách hàng khen ngợi', DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
(@manv_tuan, 'phat', 100000, 'Ngủ gật trong ca trực', CURDATE());

-- Thêm Đánh Giá Nhân Viên
INSERT INTO DanhGiaNhanVien (MaNV, MaKH, SoSao, NhanXet, NgayDanhGia) VALUES
(@manv_lan, @makh_1, 5, 'Bạn Lan thu ngân rất dễ thương, thủ tục nhanh gọn.', CURDATE()),
(@manv_lan, @makh_2, 4, 'Phục vụ tốt, nhiệt tình.', DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
(@manv_mai, @makh_1, 5, 'Lễ tân rất nhiệt tình tư vấn phòng cho gia đình tôi.', CURDATE());

