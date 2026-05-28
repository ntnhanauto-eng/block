<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Cấu hình Database của bạn trên 123HOST
$db_host = "localhost";
$db_user = "nacwxjyg_qlks";      // Thay bằng User DB của bạn
$db_pass = "mDNshduHEJwB2REtKeWU";      // Thay bằng Mật khẩu DB của bạn
$db_name = "nacwxjyg_qlks";     // Thay bằng Tên DB của bạn

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
if (!$conn) {
    die("Kết nối cơ sở dữ liệu thất bại: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");

// 2. Cấu hình API Tuya IoT (Dùng cho script lấy dữ liệu ngầm)
define('TUYA_CLIENT_ID', 'qap98nweqkmufpdp5d3r');
define('TUYA_SECRET', 'cb7684adc56045bdb5f77c1d7a541d48');
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
