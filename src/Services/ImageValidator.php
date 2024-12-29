<?php

namespace ImageService\Services;

use Exception;
use finfo;

class ImageValidator {
    private $maxFileSize;
    private $allowedTypes;
    private $errors = [];
    private $censor;
    private $uploadDir;
    private $lastAuditResult = null;

    public function __construct($maxFileSize = null, $allowedTypes = null, $uploadDir = null) {
        global $uploadConfig;
        
        $this->maxFileSize = $maxFileSize ?? $uploadConfig['max_file_size'];
        $this->allowedTypes = $allowedTypes ?? $uploadConfig['allowed_types'];
        $this->uploadDir = $uploadDir ?? $uploadConfig['upload_dir'];
        
        $this->initBaiduCensor();
    }

    /**
     * 初始化百度内容审核服务
     */
    private function initBaiduCensor() {
        try {
            $this->censor = new BaiduImageCensor();
        } catch (Exception $e) {
            error_log("BaiduImageCensor initialization error: " . $e->getMessage());
            $this->errors[] = "内容审核服务初始化失败";
        }
    }

    /**
     * 验证上传的图片
     * @param array $file $_FILES 数组中的文件信息
     * @return bool 验证是否通过
     */
    public function validate($file) {
        $this->errors = [];

        // 基本验证
        if (!$this->validateBasics($file)) {
            return false;
        }

        // 内容审核
        $result = $this->validateContent($file['tmp_name']);
        if ($result === false) {
            $this->errors[] = '内容审核失败';
            return false;
        }

        // 如果结果是 true，说明审核未启用或已通过
        if ($result === true) {
            return empty($this->errors);
        }

        // 保存审核结果
        $this->lastAuditResult = $result;

        // 如果审核结果是不合规，返回 false 但不添加错误信息
        // 这样上层代码可以通过 getLastAuditResult 获取具体原因
        if ($result['conclusion'] !== '合规') {
            return false;
        }

        return empty($this->errors);
    }

    /**
     * 基本验证（文件类型、大小等）
     */
    private function validateBasics($file) {
        // 检查文件是否成功上传
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $this->errors[] = '没有文件被上传';
            return false;
        }

        // 检查上传错误
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->getUploadErrorMessage($file['error']);
            return false;
        }

        // 检查文件类型
        if (!$this->validateFileType($file)) {
            return false;
        }

        // 检查文件大小
        if (!$this->validateFileSize($file)) {
            return false;
        }

        // 检查图片完整性
        if (!$this->validateImageIntegrity($file['tmp_name'])) {
            return false;
        }

        // 检查图片尺寸
        if (!$this->validateImageDimensions($file['tmp_name'])) {
            return false;
        }

        return true;
    }

    /**
     * 验证图片内容（调用百度API）
     * @param string $filePath 图片路径
     * @return array|false 审核结果或失败
     */
    private function validateContent($filePath) {
        if (!$this->censor) {
            $this->errors[] = '内容审核服务不可用';
            return false;
        }

        error_log("开始内容审核，文件路径：" . $filePath);
        $result = $this->censor->censorImage($filePath);
        
        if ($result === false) {
            $this->errors[] = '图片审核失败：' . $this->censor->getError();
            error_log("内容审核失败：" . $this->censor->getError());
            return false;
        }

        error_log("获取到审核结果：" . json_encode($result, JSON_UNESCAPED_UNICODE));
        return $result;
    }

    /**
     * 验证文件类型
     * @param array $file
     * @return bool
     */
    private function validateFileType($file) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $this->allowedTypes)) {
            $this->errors[] = '不支持的文件类型。允许的类型: ' . implode(', ', $this->allowedTypes);
            return false;
        }

        return true;
    }

    /**
     * 验证文件大小
     * @param array $file
     * @return bool
     */
    private function validateFileSize($file) {
        if ($file['size'] > $this->maxFileSize) {
            $this->errors[] = sprintf('文件大小超过限制。最大允许: %dMB', $this->maxFileSize / 1024 / 1024);
            return false;
        }

        return true;
    }

    /**
     * 验证图片完整性
     * @param string $filePath
     * @return bool
     */
    private function validateImageIntegrity($filePath) {
        if (!getimagesize($filePath)) {
            $this->errors[] = '无效或损坏的图片文件';
            return false;
        }

        return true;
    }

    /**
     * 获取最后的审核结果
     * @return array|null
     */
    public function getLastAuditResult() {
        return $this->lastAuditResult;
    }

    /**
     * 获取错误信息
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * 获取上传错误信息
     * @param int $code
     * @return string
     */
    private function getUploadErrorMessage($code) {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                return '上传的文件超过了php.ini中upload_max_filesize的限制';
            case UPLOAD_ERR_FORM_SIZE:
                return '上传的文件超过了HTML表单中MAX_FILE_SIZE的限制';
            case UPLOAD_ERR_PARTIAL:
                return '文件只有部分被上传';
            case UPLOAD_ERR_NO_FILE:
                return '没有文件被上传';
            case UPLOAD_ERR_NO_TMP_DIR:
                return '找不到临时文件夹';
            case UPLOAD_ERR_CANT_WRITE:
                return '文件写入失败';
            case UPLOAD_ERR_EXTENSION:
                return '文件上传被PHP扩展程序中断';
            default:
                return '未知的上传错误';
        }
    }

    /**
     * 获取图片尺寸限制
     * @return array
     */
    private function getImageDimensionLimits() {
        return [
            'min_width' => 50,
            'min_height' => 50,
            'max_width' => 10000,
            'max_height' => 10000
        ];
    }

    /**
     * 验证图片尺寸
     * @param string $filePath
     * @return bool
     */
    private function validateImageDimensions($filePath) {
        $imageInfo = getimagesize($filePath);
        if ($imageInfo === false) {
            $this->errors[] = '无法获取图片尺寸信息';
            return false;
        }

        list($width, $height) = $imageInfo;
        $limits = $this->getImageDimensionLimits();

        if ($width < $limits['min_width'] || $height < $limits['min_height']) {
            $this->errors[] = sprintf('图片尺寸太小。最小要求：%dx%d像素', 
                $limits['min_width'], $limits['min_height']);
            return false;
        }

        if ($width > $limits['max_width'] || $height > $limits['max_height']) {
            $this->errors[] = sprintf('图片尺寸太大。最大允许：%dx%d像素', 
                $limits['max_width'], $limits['max_height']);
            return false;
        }

        return true;
    }

    /**
     * 获取第一个错误信息
     * @return string|null
     */
    public function getFirstError() {
        return !empty($this->errors) ? $this->errors[0] : null;
    }

    /**
     * 设置最大文件大小
     * @param int $maxFileSize 最大文件大小（MB）
     */
    public function setMaxFileSize($maxFileSize) {
        $this->maxFileSize = $maxFileSize * 1024 * 1024;
    }

    /**
     * 设置允许的文件类型
     * @param array $allowedTypes
     */
    public function setAllowedTypes($allowedTypes) {
        $this->allowedTypes = $allowedTypes;
    }
}
