<?php

function vnpayvisa_config()
{
    $configarray = array(
        "name" => array("Type" => "System", "Value" => "Visa/Master Card"),
        "webcode" => array("Name" => "Web Code", "Type" => "text", "Size" => "20",),
        "secret" => array("Name" => "Secret Key", "Type" => "text", "Size" => "50"),
        "mode" => array("Name" => "Process Mode", "Type" => "dropdown", "Options" => "Live,Sandbox"),
        'info' => array('Name' => 'Information','Type' => 'textarea','Cols' => '5','Rows' => '10')
    );
    return $configarray;
}

function vnpayvisa_link($params)
{
    global $config;
    $langpaynow = $params['langpaynow'];

    $vnp_TxnRef = $params['invoiceid'] . "_" . uniqid();
    $vnp_OrderInfo = $params['description'] . ", Username: ".$params['clientdetails']['username'];
    $vnp_OrderType = '130005'; // Danh mục phần mềm
    $vnp_Amount = $params['amount'] * 100;
    $vnp_Locale = 'en';
    if($params['clientdetails']['language'] == "Vietnamese") {
        $vnp_Locale = 'vn';
    }
    $vnp_BankCode = "VISA";
    $vnp_IpAddr = "157.245.194.20";
    $siteAddress = $config['site_address'];
    if ($config['config_ssl_allow']) {
        $siteAddress = $config['config_ssl_allow'];
    }
    $params['returnurl'] = $siteAddress . '/modules/gateways/callback/vnpay.php';

    $inputData = array(
        "vnp_Version" => "2.0.0",
        "vnp_TmnCode" => $params['webcode'],
        "vnp_Amount" => $vnp_Amount,
        "vnp_Command" => "pay",
        "vnp_CreateDate" => date('YmdHis'),
        "vnp_CurrCode" => "VND",
        "vnp_IpAddr" => $vnp_IpAddr,
        "vnp_Locale" => $vnp_Locale,
        "vnp_OrderInfo" => $vnp_OrderInfo,
        "vnp_OrderType" => $vnp_OrderType,
        "vnp_ReturnUrl" => $params['returnurl'],
        "vnp_TxnRef" => $vnp_TxnRef,
    );

    if (isset($vnp_BankCode) && $vnp_BankCode != "") {
        $inputData['vnp_BankCode'] = $vnp_BankCode;
    }
    ksort($inputData);
    $query = "";
    $i = 0;
    $hashdata = "";
    foreach ($inputData as $key => $value) {
        if ($i == 1) {
            $hashdata .= '&' . $key . "=" . $value;
        }
        else {
            $hashdata .= $key . "=" . $value;
            $i = 1;
        }
        $query .= urlencode($key) . "=" . urlencode($value) . '&';
    }

    $vnp_Url = "https://pay.vnpay.vn/vpcpay.html";

    if ($params['mode'] == 'Sandbox') {
        $vnp_Url = "http://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
    }

    $vnp_HashSecret = $params['secret'];
    $vnp_Url = $vnp_Url . "?" . $query;
    if (isset($vnp_HashSecret)) {
        $vnpSecureHash = hash('sha256', $vnp_HashSecret . $hashdata);
        $vnp_Url .= 'vnp_SecureHashType=SHA256&vnp_SecureHash=' . $vnpSecureHash;
    }

    $code = "<form method='post' action='$vnp_Url'><input class='btn btn-success' type='submit' name='submit' value='$langpaynow' /></form>";
    return $code;
}