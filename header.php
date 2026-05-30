<?php
// header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra nếu chưa đăng nhập thì đẩy về trang login (Đồng bộ cấu hình bảo mật)
if (!function_exists('checkLogin')) {
    include 'config.php';
}
checkLogin(); 

$current_user = $_SESSION['username'] ?? 'Nhân viên';
$current_role = $_SESSION['role'] ?? 'nhanvien';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>THÀNH NGHIÊM HOTEL - Hệ Thống Điều Hành Cao Cấp</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS KHỞI TẠO HỆ THỐNG GIAO DIỆN CHUNG */
        :root {
            --bg-header: #2c3e50;
            --text-header: #ffffff;
            --accent-color: #ffc107;
            --body-bg: #eef2f3;
            --card-bg: #ffffff;
            --text-color: #333333;
        }

        body.dark-mode {
            --bg-header: #1e293b;
            --body-bg: #111827;
            --card-bg: #1f2937;
            --text-color: #e5e7eb;
        }

        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            margin: 0; 
            padding: 0; 
            background: var(--body-bg); 
            color: var(--text-color); 
            transition: background 0.3s, color 0.3s; 
        }
        
        /* HEADER ĐA NĂNG RESPONSIVE */
        .hotel-header { 
            background: var(--bg-header); 
            color: var(--text-header); 
            padding: 12px 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); 
            flex-wrap: wrap; 
            gap: 12px;
        }
        
        /* ĐÃ SỬA: Biến khối logo thành nút bấm, xóa gạch chân và thêm hiệu ứng mượt mà khi hover */
        .header-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none !important;
            transition: opacity 0.2s ease;
            cursor: pointer;
        }
        .header-brand:hover {
            opacity: 0.85; /* Hiệu ứng mờ nhẹ sang trọng khi di chuột vào logo */
        }
        
        .header-brand h2 { 
            margin: 0; 
            font-size: 18px; 
            letter-spacing: 0.8px; 
            text-transform: uppercase;
            font-weight: 800;
            color: #ffffff;
        }

        .header-brand h2 span {
            color: var(--accent-color);
        }
        
        .header-controls { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn-header-darkmode { 
            background: #34495e; 
            color: #f1c40f; 
            border: 1px solid #f1c40f; 
            padding: 6px 12px; 
            border-radius: 6px; 
            cursor: pointer; 
            font-weight: bold; 
            font-size: 13px; 
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }
        .btn-header-darkmode:hover {
            background: #1e293b;
            transform: translateY(-1px);
        }
        
        .header-user-badge { 
            font-size: 13px; 
            text-align: right; 
            background: rgba(255,255,255,0.1);
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .header-user-badge a { 
            color: #ff6b6b; 
            text-decoration: none; 
            font-weight: bold; 
            margin-left: 8px; 
            border-left: 1px solid rgba(255,255,255,0.3);
            padding-left: 8px;
        }
        .header-user-badge a:hover {
            color: #ff4747;
            text-decoration: underline;
        }

        /* MÀN HÌNH LỚN (PC / TABLET) */
        @media (min-width: 768px) {
            .hotel-header { padding: 15px 30px; }
            .header-brand h2 { font-size: 22px; }
            .header-controls { width: auto; justify-content: flex-end; }
        }
        
        /* MÀN HÌNH ĐIỆN THOẠI NHỎ */
        @media (max-width: 480px) {
            .hotel-header { flex-direction: column; text-align: center; padding: 12px 10px; }
            .header-brand { justify-content: center; width: 100%; }
            .header-controls { width: 100%; justify-content: center; flex-direction: column; gap: 8px; }
            .header-user-badge { width: 100%; text-align: center; box-sizing: border-box; }
        }
    </style>
</head>
<body>

    <div class="hotel-header">
        <a href="index.php" class="header-brand" title="Quay về trang chủ điều hành">
            <i class="fa-solid fa-hotel" style="color: var(--accent-color); font-size: 20px;"></i>
            <h2>THÀNH NGHIÊM <span>HOTEL</span></h2>
        </a>
        
        <div class="header-controls">
            <button class="btn-header-darkmode" onclick="toggleHeaderDarkMode()">
                <i id="theme-icon" class="fa-solid fa-moon"></i> <span id="theme-text">Chế độ đêm</span>
            </button>
            <div class="header-user-badge">
                <i class="fa-solid fa-user-shield" style="margin-right: 3px; color: var(--accent-color);"></i>
                Chào: <b><?php echo htmlspecialchars($current_user); ?></b> 
                (<span style="color: var(--accent-color); font-weight: bold; font-size: 11px;"><?php echo strtoupper($current_role); ?></span>) 
                <a href="logout.php" title="Đăng xuất tài khoản hệ thống"><i class="fa-solid fa-right-from-bracket"></i> Thoát</a>
            </div>
        </div>
    </div>

    <script>
    // SCRIPT ĐỒNG BỘ DARK MODE TOÀN HỆ THỐNG PHÒNG KHÁCH SẠN
    function toggleHeaderDarkMode() {
        document.body.classList.toggle('dark-mode');
        const icon = document.getElementById('theme-icon');
        const text = document.getElementById('theme-text');

        if(document.body.classList.contains('dark-mode')) {
            localStorage.setItem('hotel-theme', 'dark');
            if(icon) icon.className = "fa-solid fa-sun";
            if(text) text.innerText = "Chế độ ngày";
        } else {
            localStorage.setItem('hotel-theme', 'light');
            if(icon) icon.className = "fa-solid fa-moon";
            if(text) text.innerText = "Chế độ đêm";
        }
    }

    // Tự động kích hoạt cấu hình theme cũ khi nhân viên tải trang hoặc chuyển hướng trang
    if(localStorage.getItem('hotel-theme') === 'dark') {
        document.body.classList.add('dark-mode');
        document.addEventListener("DOMContentLoaded", function() {
            const icon = document.getElementById('theme-icon');
            const text = document.getElementById('theme-text');
            if(icon) icon.className = "fa-solid fa-sun";
            if(text) text.innerText = "Chế độ ngày";
        });
    }
    </script>
