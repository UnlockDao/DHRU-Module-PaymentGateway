<?php

define("DEFINE_MY_ACCESS", true);
define("DEFINE_DHRU_FILE", true);
include '../../../comm.php';
require '../../../includes/fun.inc.php';
include '../../../includes/gateway.fun.php';
include '../../../includes/invoice.fun.php';

$GATEWAY = loadGatewayModule('vnpay');
$vnp_HashSecret = $GATEWAY['secret'];
$inputData = array();
$returnData = array();
$data = $_REQUEST;
foreach ($data as $key => $value) {
    if (substr($key, 0, 4) == "vnp_") {
        $inputData[$key] = $value;
    }
}

$vnp_SecureHash = $inputData['vnp_SecureHash'];
unset($inputData['vnp_SecureHashType']);
unset($inputData['vnp_SecureHash']);
ksort($inputData);
$i = 0;
$hashData = "";
foreach ($inputData as $key => $value) {
    if ($i == 1) {
        $hashData = $hashData . '&' . $key . "=" . $value;
    }
    else {
        $hashData = $hashData . $key . "=" . $value;
        $i = 1;
    }
}
$txn_id = $inputData['vnp_TransactionNo']; // Mã giao dịch tại VNPAY
$vnp_BankCode = $inputData['vnp_BankCode']; // Ngân hàng thanh toán
$secureHash = hash('sha256', $vnp_HashSecret . $hashData);
$Status = 0;
$mc_gross = ($inputData['vnp_Amount'] / 100);
$mc_fee = 0;
$orderId = explode("_", $inputData['vnp_TxnRef'])[0];

logTransaction('vnpay', $hashData, 'Callback Received');

try {
    if ($secureHash == $vnp_SecureHash) {
        if ($inputData['vnp_ResponseCode'] == '00') {
			if (mysqli_num_rows(dquery("select id from tbl_invoices where (id)='$orderId'"))) {
				$row = mysqli_fetch_assoc(dquery("select * from tbl_invoices where (id)='$orderId'"));
				if ($row['status'] != 'Paid') {
					$userid = $row['userid'];
					$invoiceid = $orderId;
					$vncurrency = $GATEWAY['curcode'];
					$result = select_query('tbl_currencies', '', array('code' => $vncurrency));
					$data = mysqli_fetch_assoc($result);
					$vncurrencyid = $data['id'];
					$currencyconvrate = $data['rate'];
					$currency = getCurrency('', $userid);

					if ($vncurrencyid != $currency['id']) {
						$mc_gross = convertCurrency($mc_gross, $vncurrencyid, $currency['id']);
						$mc_fee = convertCurrency($mc_fee, $vncurrencyid, $currency['id']);
						$result = select_query('tbl_invoices', 'total', array('id' => $invoiceid));
						$data = mysqli_fetch_assoc($result);
						$total = round($data['total'], 2);
						if (($total < $mc_gross + 1 and $mc_gross - 1 < $total)) {
							$mc_gross = $total;
						}
					}
					addPayment($invoiceid, $txn_id, $mc_gross, $mc_fee, 'vnpay', "", "", "", "");
					
					logTransaction('vnpay', $hashData, 'Payment success #00');
					$returnData['RspCode'] = '00';
					$returnData['Message'] = 'Payment success';
				}
				else {
					logTransaction('vnpay', $hashData, 'Invoice already paid #02');
					$returnData['RspCode'] = '02';
					$returnData['Message'] = 'Invoice already paid';
				}
			}
			else {
				logTransaction('vnpay', $hashData, 'Invoice not found #01');
				$returnData['RspCode'] = '01';
				$returnData['Message'] = 'Invoice not found';
			}
        }
        logTransaction('vnpay', $hashData, 'Error #'.$inputData['vnp_ResponseCode']);
        $returnData['RspCode'] = $inputData['vnp_ResponseCode'];
        $returnData['Message'] = 'Error #'.$inputData['vnp_ResponseCode'];
    }
    else {
        logTransaction('vnpay', $hashData, 'Secure hash invalid #97');
		$returnData['RspCode'] = '97';
        $returnData['Message'] = 'Secure hash invalid';
    }
}
catch (Exception $e) {
    logTransaction('vnpay', $hashData, 'Unknown Error #99');
    $returnData['RspCode'] = '99';
    $returnData['Message'] = 'Unknow error';
}

echo json_encode($returnData);

if ($returnData['RspCode'] == '00') {
	header("Location: ../../../viewinvoice.php?id=" . md5($orderId) . "&paymentsuccess=true");
	exit();
} 
else {
	header("Location: ../../../viewinvoice.php?id=" . md5($orderId) . "&paymentfailed=true");
	exit();
}