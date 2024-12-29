<?php

namespace ImageService\Services;

use Exception;

class ImageAuditLogger {
    private $logDir;
    private $hiddenDir;
    private $logFile;

    public function __construct() {
        global $uploadConfig;
        
        if (empty($uploadConfig)) {
            throw new Exception('上传配置不存在');
        }

        $this->logDir = rtrim($uploadConfig['log_dir'], '/\\');
        $this->hiddenDir = rtrim($uploadConfig['hidden_dir'], '/\\');

        // 确保目录存在
        if (!is_dir($this->logDir)) {
            if (!mkdir($this->logDir, 0755, true)) {
                throw new Exception('创建日志目录失败');
            }
        }

        if (!is_dir($this->hiddenDir)) {
            if (!mkdir($this->hiddenDir, 0755, true)) {
                throw new Exception('创建隐藏目录失败');
            }
            
            // 创建 .htaccess 文件
            $htaccess = $this->hiddenDir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Deny from all");
            }
        }

        // 检查目录权限
        if (!is_writable($this->logDir)) {
            throw new Exception('日志目录没有写入权限');
        }

        if (!is_writable($this->hiddenDir)) {
            throw new Exception('隐藏目录没有写入权限');
        }

        // 使用一个统一的日志文件，按月份归档
        $this->logFile = $this->logDir . '/tuku_' . date('Y-m') . '.log';
    }

    /**
     * 移动文件到隐藏目录
     * @param string $sourcePath 源文件路径
     * @param array $fileInfo 文件信息
     * @return array|false 移动后的文件信息或失败
     */
    public function moveToHidden($sourcePath, $fileInfo) {
        try {
            if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
                throw new Exception("源文件不存在或不可读");
            }

            // 确保隐藏目录存在
            if (!is_dir($this->hiddenDir)) {
                if (!mkdir($this->hiddenDir, 0755, true)) {
                    throw new Exception("创建隐藏目录失败");
                }
                
                // 创建 .htaccess 文件
                $htaccess = $this->hiddenDir . '/.htaccess';
                if (!file_exists($htaccess)) {
                    file_put_contents($htaccess, "Deny from all");
                }
            }

            // 生成唯一文件名
            $extension = $fileInfo['extension'] ?? 'jpg';
            $newFilename = date('Ymd_His_') . bin2hex(random_bytes(8)) . '.' . $extension;
            $targetPath = $this->hiddenDir . '/' . $newFilename;

            // 复制文件
            if (!copy($sourcePath, $targetPath)) {
                throw new Exception("复制文件失败");
            }

            // 验证复制是否成功
            if (!file_exists($targetPath) || filesize($targetPath) !== filesize($sourcePath)) {
                throw new Exception("文件复制验证失败");
            }

            // 删除源文件
            if (file_exists($sourcePath) && !unlink($sourcePath)) {
                $this->log('ERROR', '删除源文件失败', ['source' => $sourcePath]);
            }

            $this->log('OPERATION', '文件已移动到隐藏目录', [
                'source' => $sourcePath,
                'target' => $targetPath
            ]);

            return ['path' => $targetPath];
        } catch (Exception $e) {
            $this->log('ERROR', '移动文件失败: ' . $e->getMessage(), [
                'source' => $sourcePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 记录日志
     * @param string $type 日志类型 (AUDIT/ERROR/OPERATION)
     * @param string $message 日志消息
     * @param array $context 上下文信息
     */
    private function log($type, $message, array $context = []) {
        $date = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = "[$date][$type] $message$contextStr" . PHP_EOL;
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * 记录审核结果
     * @param array $imageInfo 图片信息
     * @param array $auditResult 审核结果
     * @param string $hiddenPath 隐藏文件的路径
     * @return bool
     */
    public function logAuditResult($imageInfo, $auditResult, $hiddenPath = null) {
        try {
            // 基本审核信息
            $context = [
                'file' => $imageInfo,
                'conclusion' => $auditResult['conclusion'],
                'conclusion_type' => $auditResult['conclusionType'] ?? ''
            ];

            // 如果是违规图片，记录详细信息
            if ($auditResult['conclusion'] !== '合规') {
                $context['details'] = $auditResult['data'] ?? [];
                if ($hiddenPath) {
                    $context['hidden_path'] = $hiddenPath;
                }
                // 记录违规详情
                $this->log('AUDIT', '图片审核不通过', $context);
            } else {
                // 合规图片只记录基本信息
                $this->log('AUDIT', '图片审核通过', $context);
            }

            return true;
        } catch (Exception $e) {
            $this->log('ERROR', '记录审核结果失败: ' . $e->getMessage(), [
                'file' => $imageInfo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
