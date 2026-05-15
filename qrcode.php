<?php
/**
 * 二维码访问端点
 * 提供经营码二维码的HTTP访问
 */

/**
 * 输出一张可读的 SVG 错误图，避免前端 <img> 直接表现为损坏图片。
 */
function outputErrorImage(string $message, int $statusCode = 500): void
{
    http_response_code($statusCode);
    header('Content-Type: image/svg+xml; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="480" height="480" viewBox="0 0 480 480">
  <rect width="480" height="480" fill="#fff7ed"/>
  <rect x="20" y="20" width="440" height="440" rx="24" fill="#ffffff" stroke="#fdba74" stroke-width="4"/>
  <text x="240" y="170" text-anchor="middle" font-size="30" font-family="Arial, sans-serif" fill="#9a3412">二维码不可用</text>
  <text x="240" y="230" text-anchor="middle" font-size="18" font-family="Arial, sans-serif" fill="#7c2d12">{$safeMessage}</text>
  <text x="240" y="270" text-anchor="middle" font-size="16" font-family="Arial, sans-serif" fill="#9a3412">请检查 qrcode/business_qr.png</text>
</svg>
SVG;

    echo $svg;
    exit;
}

/**
 * 本地经营码不可用时跳转到远程默认二维码。
 */
function redirectToDefaultQrCode(array $config): void
{
    $defaultQrCodeUrl = trim($config['payment']['business_qr_mode']['default_qr_code_url'] ?? '');
    if ($defaultQrCodeUrl === '') {
        return;
    }

    if (!filter_var($defaultQrCodeUrl, FILTER_VALIDATE_URL)) {
        outputErrorImage('默认二维码URL配置无效', 500);
    }

    header('Location: ' . $defaultQrCodeUrl, true, 302);
    exit;
}

header('Cache-Control: public, max-age=3600'); // 缓存1小时

// 加载配置
$config = require __DIR__ . '/config/alipay.php';

// 获取二维码类型参数
$type = $_GET['type'] ?? 'business';
$token = $_GET['token'] ?? '';

// 验证token（简单的安全验证）
$expectedToken = md5('qrcode_access_' . date('Y-m-d'));
if ($token !== $expectedToken) {
    outputErrorImage('访问令牌无效或已过期', 403);
}

try {
    switch ($type) {
        case 'business':
            // 经营码二维码
            $qrCodePath = $config['payment']['business_qr_mode']['qr_code_path'];

            if (!file_exists($qrCodePath)) {
                redirectToDefaultQrCode($config);
                outputErrorImage('经营码文件不存在', 404);
            }

            if (!is_readable($qrCodePath)) {
                redirectToDefaultQrCode($config);
                outputErrorImage('经营码文件不可读', 500);
            }

            if (filesize($qrCodePath) === 0) {
                redirectToDefaultQrCode($config);
                outputErrorImage('经营码文件为空', 500);
            }

            // 读取并输出二维码文件
            $imageData = file_get_contents($qrCodePath);
            if ($imageData === false || $imageData === '') {
                redirectToDefaultQrCode($config);
                outputErrorImage('经营码文件读取失败', 500);
            }

            // 根据文件类型设置正确的Content-Type
            $imageInfo = getimagesizefromstring($imageData);
            if (!$imageInfo || empty($imageInfo['mime'])) {
                redirectToDefaultQrCode($config);
                outputErrorImage('经营码文件不是有效图片', 500);
            }

            header('Content-Type: ' . $imageInfo['mime']);
            echo $imageData;
            break;
            
        default:
            outputErrorImage('二维码类型无效', 400);
    }
    
} catch (Exception $e) {
    outputErrorImage('二维码加载失败: ' . $e->getMessage(), 500);
}
?>
