<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Cấu hình Database của bạn trên 123HOST
$db_host = "localhost";
$db_user = "ten_user_db";      // Thay bằng User DB của bạn
$db_pass = "mat_khau_db";      // Thay bằng Mật khẩu DB của bạn
$db_name = "ten_database";     // Thay bằng Tên DB của bạn

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
if (!$conn) {
    die("Kết nối cơ sở dữ liệu thất bại: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");

// 2. Cấu hình API Tuya IoT (Dùng cho script lấy dữ liệu ngầm)
define('TUYA_CLIENT_ID', 'DÁN_CLIENT_ID_TUYA_VÀO_ĐÂY');
define('TUYA_SECRET', 'DÁN_SECRET_KEY_TUYA_VÀO_ĐÂY');
define('TUYA_API_URL', 'https://openapi.tuyaus.com'); // Thay đổi vùng tùy tài khoản Tuya của bạn (us/eu/cn)

// 3. Các hàm kiểm tra quyền truy cập của hệ thống
function checkLogin() {
    if (!isset($_SESSION['username'])) {
        header("Location: login.php");
        exit();
    }
}

function isAdmin() {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
}
?>
