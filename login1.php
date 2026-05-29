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
    <title>Đăng nhập hệ thống quản lý</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 320px; text-align: center; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
        .err { color: red; font-size: 14px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>HỆ THỐNG SMART HOTEL</h2>
        <?php if (isset($error)) echo "<div class='err'>$error</div>"; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Tên đăng nhập" required>
            <input type="password" name="password" placeholder="Mật khẩu" required>
            <button type="submit">Đăng Nhập</button>
        </form>
    </div>
</body>
</html>
