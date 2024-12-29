<?php

namespace ImageService\Services;

use Exception;

require_once __DIR__ . '/ImageAuditLogger.php';

class BaiduImageCensor {
    private $accessToken;
    private $config;
    private $tokenFile;
    private $error;
    private $logger;
    private $enabled;

    public function __construct() {
        global $baiduConfig;
        
        if (empty($baiduConfig)) {
            $this->enabled = false;
            return;
        }
        
        $this->config = $baiduConfig;
        $this->enabled = $this->config['enabled'] ?? false;
        
        if (!$this->enabled) {
            return;
        }

        if (empty($this->config['client_id']) || empty($this->config['client_secret'])) {
            throw new Exception('百度API配置信息不完整');
        }

        $this->tokenFile = __DIR__ . '/../../cache/baidu_token.json';
        $this->initLogger();
        $this->initAccessToken();
    }

    /**
     * 审核图片内容
     * @param string $imagePath 图片路径
     * @return array|true 审核结果或true（未启用时）
     */
    public function censorImage($imagePath) {
        // 如果未启用审核，直接返回true
        if (!$this->enabled) {
            return true;
        }

        if (!$this->accessToken) {
            $this->error = '未获取到有效的访问令牌';
            return false;
        }

        // 读取图片内容并base64编码
        $imageContent = file_get_contents($imagePath);
        if ($imageContent === false) {
            $this->error = '无法读取图片文件';
            return false;
        }
        
        $base64Content = base64_encode($imageContent);

        // 准备请求参数
        $params = [
            'image' => $base64Content
        ];

        error_log("开始审核图片: " . $imagePath);

        // 发送审核请求
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->config['censor_url'] . "?access_token={$this->accessToken}",
            CURLOPT_TIMEOUT => 30,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        error_log("审核请求响应码: " . $httpCode);
        error_log("审核响应内容: " . $response);

        if ($httpCode !== 200) {
            $this->error = "图片审核请求失败，HTTP状态码：" . $httpCode;
            if ($response) {
                $this->error .= "，响应：" . $response;
            }
            curl_close($curl);
            return false;
        }
        
        curl_close($curl);
        $result = json_decode($response, true);
        
        // 处理审核结果
        if (isset($result['error_code'])) {
            $this->error = $result['error_msg'] ?? '审核失败';
            error_log("审核失败: " . $this->error);
            return false;
        }

        error_log("审核结果: " . json_encode($result, JSON_UNESCAPED_UNICODE));
        return $result;
    }

    /**
     * 获取最后的错误信息
     */
    public function getError() {
        return $this->error;
    }

    /**
     * 初始化日志记录器
     */
    private function initLogger() {
        try {
            global $uploadConfig;
            $this->logger = new ImageAuditLogger($uploadConfig);
        } catch (Exception $e) {
            error_log("Failed to initialize logger: " . $e->getMessage());
        }
    }

    /**
     * 初始化访问令牌
     */
    private function initAccessToken() {
        // 检查缓存的令牌是否有效
        if (file_exists($this->tokenFile)) {
            $tokenData = json_decode(file_get_contents($this->tokenFile), true);
            if ($tokenData && isset($tokenData['access_token']) && $tokenData['expires_time'] > time()) {
                $this->accessToken = $tokenData['access_token'];
                error_log("使用缓存的访问令牌");
                return;
            }
        }

        error_log("开始获取新的访问令牌");
        error_log("Token URL: " . $this->config['token_url']);
        error_log("Client ID: " . $this->config['client_id']);
        error_log("Client Secret: " . substr($this->config['client_secret'], 0, 4) . '...');

        // 获取新的访问令牌
        $params = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret']
        ];

        $url = $this->config['token_url'] . '?' . http_build_query($params);
        error_log("完整请求 URL: " . $url);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_VERBOSE => true
        ]);

        // 启用详细的 CURL 日志
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($curl, CURLOPT_STDERR, $verbose);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        $curlErrno = curl_errno($curl);

        // 记录详细的 CURL 日志
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        error_log("CURL 详细日志:\n" . $verboseLog);

        error_log("CURL 响应码: " . $httpCode);
        if ($curlError) {
            error_log("CURL 错误: " . $curlError);
            error_log("CURL 错误码: " . $curlErrno);
        }
        if ($response) {
            error_log("响应内容: " . $response);
        }

        curl_close($curl);

        if ($httpCode !== 200) {
            throw new Exception("获取访问令牌失败，HTTP状态码：" . $httpCode);
        }

        $result = json_decode($response, true);
        if (!isset($result['access_token'])) {
            error_log("获取访问令牌失败：" . json_encode($result));
            throw new Exception('无效的访问令牌响应');
        }

        // 缓存访问令牌
        $tokenData = [
            'access_token' => $result['access_token'],
            'expires_in' => $result['expires_in'],
            'expires_time' => time() + $result['expires_in']
        ];

        // 确保缓存目录存在
        $cacheDir = dirname($this->tokenFile);
        if (!file_exists($cacheDir)) {
            if (!mkdir($cacheDir, 0755, true)) {
                error_log("Failed to create cache directory: " . $cacheDir);
                throw new Exception('无法创建缓存目录');
            }
        }

        if (file_put_contents($this->tokenFile, json_encode($tokenData)) === false) {
            error_log("Failed to write token file: " . $this->tokenFile);
            throw new Exception('无法保存访问令牌');
        }

        error_log("成功获取并缓存新的访问令牌");
        $this->accessToken = $result['access_token'];
    }
}
