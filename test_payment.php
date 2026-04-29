<?php
ini_set("display_errors", 1);
error_reporting(E_ALL);
require "config/db.php";
require "config/constants.php";
require "api/services/email_service.php";
$conn = getDbConnection();
$total_calculated = 35000;
$discount_amount = 0;
$final_total = 35000;
$voucher_code = null;
$order_id = 45; // dummy
$items = [ ["name"=>"Test", "quantity"=>1, "price"=>35000] ];
$payment_method = "bank_transfer";
$user_id = 1;

// Simulate email sending
$emailStmt = $conn->prepare("SELECT email, name FROM users WHERE id = ?");
$emailStmt->bind_param("i", $user_id);
$emailStmt->execute();
$emailRes = $emailStmt->get_result();

if ($uRow = $emailRes->fetch_assoc()) {
    $uEmail = $uRow["email"];
    $uName = $uRow["name"];
    if ($uEmail) {
        $subject = "[Du?ng B?u] Xác nh?n don hàng #$order_id";
        $body = "<h2>C?m on b?n dã d?t món!</h2>";
        try {
            EmailService::send($uEmail, $subject, $body);
            echo "EMAIL_SENT\n";
        } catch (Exception $e) {
            echo "EMAIL_FAIL: " . $e->getMessage() . "\n";
        }
    }
}
$emailStmt->close();

$response = [ "success" => true, "order_id" => $order_id ];
if ($payment_method === "bank_transfer") {
    $sepay_va_account_id = defined("SEPAY_VA_ACCOUNT") ? SEPAY_VA_ACCOUNT : "";
    $sepay_bank_name     = defined("SEPAY_BANK_NAME")  ? SEPAY_BANK_NAME  : "MBBank";
    $payment_content = "DH" . $order_id;
    $amount          = $final_total;

    $qrUrl = "https://qr.sepay.vn/img"
           . "?acc=" . urlencode($sepay_va_account_id)
           . "&bank=" . urlencode($sepay_bank_name)
           . "&amount=" . urlencode($amount)
           . "&des=" . urlencode($payment_content);

    $response["payUrl"]  = $qrUrl;
    $response["message"] = "Vui lòng quét mã QR d? thanh toán.";
}
echo json_encode($response);
?>
