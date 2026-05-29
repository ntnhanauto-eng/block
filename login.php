<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tạo quyền bypass để ngăn chặn hàm checkLogin() trong config.php gây lỗi lặp chuyển hướng
$_SESSION['is_on_login_page'] = true; 

include 'config.php';

// Nếu đã đăng nhập rồi (kiểm tra bằng username đồng bộ với index.php) thì chuyển vào trang chủ luôn
if (isset($_SESSION['username'])) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = $_POST['password'];

    // Tìm kiếm tài khoản trong bảng users
    $query = mysqli_query($conn, "SELECT * FROM users WHERE username = '$user'");
    $userData = mysqli_fetch_assoc($query);

    // Xác thực mật khẩu dạng CHỮ THƯỜNG trực tiếp (Không dùng mã hóa băm)
    if ($userData && $pass === $userData['password']) {
        
        // Hủy bỏ quyền bypass trang login để các file khác (như config.php) kích hoạt lại bảo mật
        unset($_SESSION['is_on_login_page']); 
        
        // Lưu thông tin đăng nhập vào Session để index.php nhận diện
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['username'] = $userData['username'];
        $_SESSION['role'] = $userData['role'];
        
        // Chuyển hướng về trang chủ Dashboard khách sạn
        header('Location: index.php');
        exit();
    } else {
        // Nếu sai tài khoản hoặc sai mật khẩu chữ thường
        $error = "Tên đăng nhập hoặc mật khẩu không chính xác!";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Đăng nhập hệ thống quản lý</title>
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            background: #f4f7f6; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
            padding: 0;
            box-sizing: border-box;
        }
        
        .login-card { 
            background: white; 
            padding: 40px 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 25px rgba(0,0,0,0.08); 
            width: 100%;
            max-width: 360px; /* Độ rộng chuẩn, đẹp mắt trên máy tính */
            text-align: center; 
            box-sizing: border-box;
            margin: 15px; /* Giữ khoảng cách an toàn khi co về màn hình nhỏ */
        }
        
        .login-card h2 {
            margin: 0 0 25px 0;
            color: #2c3e50;
            font-size: 22px;
            letter-spacing: 0.5px;
        }
        
        input[type="text"], input[type="password"] { 
            width: 100%; 
            padding: 12px 15px; /* Tăng độ dày ô nhập giúp ngón tay dễ chạm trên điện thoại */
            margin: 10px 0; 
            border: 1px solid #cbd5e1; 
            border-radius: 6px; 
            box-sizing: border-box; 
            font-size: 16px; /* Để cỡ chữ 16px giúp iPhone/Android không bị tự động zoom phóng to màn hình khi gõ */
            background: #f8fafc;
            transition: all 0.2s ease;
        }

        input[type="text"]:focus, input[type="password"]:focus {
            border-color: #007bff;
            background: #fff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.15);
        }
        
        button { 
            width: 100%; 
            padding: 12px; 
            background: #007bff; 
            color: white; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            font-size: 16px; 
            font-weight: bold;
            margin-top: 15px;
            transition: background 0.2s ease;
        }
        
        button:hover { 
            background: #0056b3; 
        }
        
        .err { 
            color: #721c24; 
            background: #f8d7da;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #f5c6cb;
            font-size: 14px; 
            margin-bottom: 15px; 
            text-align: left;
        }

        /* TỐI ƯU RIÊNG CHO ĐIỆN THOẠI CÓ MÀN HÌNH SIÊU NHỎ (Dưới 360px) */
        @media (max-width: 360px) {
            .login-card {
                padding: 30px 20px;
                margin: 10px;
            }
            .login-card h2 {
                font-size: 19px;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>HỆ THỐNG SMART HOTEL</h2>
        
        <?php if (isset($error)): ?>
            <div class="err">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="text" name="username" placeholder="Tên đăng nhập" autocomplete="username" required>
            <input type="password" name="password" placeholder="Mật khẩu" autocomplete="current-password" required>
            <button type="submit">Đăng Nhập</button>
        </form>
    </div>
</body>
</html>
