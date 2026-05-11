<?php

require_once 'vendor/autoload.php';

use AliMPay\Core\CodePay;
use AliMPay\Utils\Logger;

// Set headers for CodePay API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

try {
    $codePay = new CodePay();
    $logger = Logger::getInstance();
    
    // Get payment parameters according to CodePay protocol
    $params = array_merge($_GET, $_POST);
    $params['sign_type'] = $params['sign_type'] ?? 'MD5';
    
    $logger->info('CodePay Payment Request', [
        'params' => array_merge($params, ['sign' => '***']), // Hide signature in logs
        'method' => $_SERVER['REQUEST_METHOD'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    // Validate required parameters according to CodePay protocol
    $requiredParams = ['pid', 'type', 'out_trade_no', 'notify_url', 'return_url', 'name', 'money', 'sign'];
    $missingParams = [];
    
    foreach ($requiredParams as $param) {
        if (empty($params[$param])) {
            $missingParams[] = $param;
        }
    }
    
    if (!empty($missingParams)) {
        http_response_code(400);
        echo json_encode([
            'code' => -1,
            'msg' => 'Missing required parameters: ' . implode(', ', $missingParams)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Validate payment type (CodePay protocol only supports alipay)
    if ($params['type'] !== 'alipay') {
        http_response_code(400);
        echo json_encode([
            'code' => -1,
            'msg' => 'Unsupported payment type. Only alipay is supported.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Validate amount format
    if (!is_numeric($params['money']) || (float)$params['money'] <= 0) {
        http_response_code(400);
        echo json_encode([
            'code' => -1,
            'msg' => 'Invalid amount format'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Create payment according to CodePay protocol
    $result = $codePay->createPayment($params);
    
    // Return result according to CodePay protocol
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if (!headers_sent()) {
        http_response_code(500);
    }
    
    Logger::getInstance()->error('CodePay Payment Error', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    echo json_encode([
        'code' => -1,
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} 
