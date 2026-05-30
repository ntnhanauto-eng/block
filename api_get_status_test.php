// ========================================================
// 3. TỐI ƯU: LẤY TRẠNG THÁI HÀNG LOẠT (SỬA LẠI CHỮ KÝ)
// ========================================================
$all_tuya_statuses = [];
if (!empty($token)) {
    // Gom tất cả ID thiết bị thành chuỗi cách nhau bởi dấu phẩy
    $device_ids_string = implode(',', array_values($devices));
    
    $endpoint = "/v1.0/devices/status";
    $queryParams = "device_ids=" . $device_ids_string; // Không để dấu ? ở đây
    
    $timestamp = round(microtime(true) * 1000);
    
    // SỬA Ở ĐÂY: Tạo mã băm SHA256 cho phần Query String theo đúng chuẩn Tuya
    $urlStringToHash = $endpoint . "?" . $queryParams;
    $strToSign = "GET\n" . hash('sha256', "") . "\n" . "" . "\n" . $urlStringToHash;
    
    $source = $accessId . $token . $timestamp . $strToSign;
    $sign_batch = strtoupper(hash_hmac('sha256', $source, $secret));

    // Gọi API với URL đầy đủ
    $ch_batch = curl_init($baseUrl . $endpoint . "?" . $queryParams);
    curl_setopt($ch_batch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_batch, CURLOPT_HTTPHEADER, [
        "client_id: $accessId", 
        "access_token: $token", 
        "sign: $sign_batch", 
        "t: $timestamp", 
        "sign_method: HMAC-SHA256"
    ]);
    $batchResponse = curl_exec($ch_batch);
    curl_close($ch_batch);

    $batchData = json_decode($batchResponse, true);
    
    // Duyệt kết quả trả về từ Tuya
    if (isset($batchData['success']) && $batchData['success'] == true && isset($batchData['result'])) {
        foreach ($batchData['result'] as $dev) {
            $dev_id = $dev['id'];
            $status_val = 'Đóng'; // Mặc định của thiết bị này là Đóng
            
            if (isset($dev['status']) && is_array($dev['status'])) {
                foreach ($dev['status'] as $s) {
                    if ($s['code'] == 'doorcontact_state' || $s['code'] == 'switch') {
                        // Nếu Tuya trả về true hoặc 'open' thì mới ghi nhận là Mở
                        if ($s['value'] === true || $s['value'] === 'open') {
                            $status_val = 'Mở';
                        }
                    }
                }
            }
            // Lưu trạng thái thực tế vào mảng tạm
            $all_tuya_statuses[$dev_id] = $status_val;
        }
    }
}
