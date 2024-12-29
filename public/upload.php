<?php
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../src/Services/ImageValidator.php';
require_once __DIR__ . '/../src/Services/BaiduImageCensor.php';
require_once __DIR__ . '/../src/Services/ImageAuditLogger.php';

// 设置错误处理
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/error.log');

// 确保必要的目录存在
$directories = [
    $uploadConfig['log_dir'],
    $uploadConfig['upload_dir'],
    $uploadConfig['hidden_dir'],
    $uploadConfig['cache_dir']
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0755, true)) {
            error_log("Failed to create directory: " . $dir);
            sendJsonResponse(500, '系统配置错误：无法创建必要的目录');
        }
    }
    if (!is_writable($dir)) {
        error_log("Directory not writable: " . $dir);
        sendJsonResponse(500, '系统配置错误：目录没有写入权限');
    }
}

function sendJsonResponse($code, $message, $data = null) {
    $response = [
        'code' => $code,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 检查是否有文件上传
    if (!isset($_FILES['image'])) {
        sendJsonResponse(400, '没有选择文件');
    }

    // 检查上传错误
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        switch ($_FILES['image']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                sendJsonResponse(400, '文件大小超过限制');
            case UPLOAD_ERR_PARTIAL:
                sendJsonResponse(400, '文件上传不完整');
            case UPLOAD_ERR_NO_FILE:
                sendJsonResponse(400, '没有文件被上传');
            case UPLOAD_ERR_NO_TMP_DIR:
                sendJsonResponse(500, '临时文件夹不存在');
            case UPLOAD_ERR_CANT_WRITE:
                sendJsonResponse(500, '文件写入失败');
            default:
                sendJsonResponse(500, '文件上传失败');
        }
    }

    // 获取数据库连接
    try {
        $conn = getDbConnection();
    } catch(PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        sendJsonResponse(500, '数据库连接失败');
    }

    // 初始化服务
    try {
        $validator = new ImageService\Services\ImageValidator();
        $imageAuditLogger = new ImageService\Services\ImageAuditLogger();
    } catch (Exception $e) {
        error_log("Service initialization error: " . $e->getMessage());
        sendJsonResponse(500, '服务初始化失败：' . $e->getMessage());
    }

    // 验证图片
    if (!$validator->validate($_FILES['image'])) {
        $result = $validator->getLastAuditResult();
        $errors = $validator->getErrors();
        
        // 只有在有审核结果且是不合规的情况下才移到隐藏目录
        if ($result && !is_bool($result) && isset($result['conclusion']) && $result['conclusion'] === '不合规') {
            try {
                error_log("开始处理违规图片");
                error_log("审核结果: " . json_encode($result, JSON_UNESCAPED_UNICODE));
                error_log("文件信息: " . json_encode([
                    'name' => $_FILES['image']['name'],
                    'size' => $_FILES['image']['size'],
                    'type' => $_FILES['image']['type'],
                    'tmp_name' => $_FILES['image']['tmp_name']
                ], JSON_UNESCAPED_UNICODE));

                // 确保文件扩展名正确
                $extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                if (empty($extension)) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($_FILES['image']['tmp_name']);
                    $extensions = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/gif' => 'gif'
                    ];
                    $extension = $extensions[$mimeType] ?? 'jpg';
                }

                // 移动到隐藏目录
                $hiddenResult = $imageAuditLogger->moveToHidden($_FILES['image']['tmp_name'], [
                    'name' => $_FILES['image']['name'],
                    'size' => $_FILES['image']['size'],
                    'type' => $_FILES['image']['type'],
                    'extension' => $extension
                ]);

                if ($hiddenResult === false) {
                    error_log("移动文件到隐藏目录失败");
                    throw new Exception('移动文件到隐藏目录失败');
                }

                error_log("文件已成功移动到隐藏目录: " . $hiddenResult['path']);

                // 记录审核结果
                if (!$imageAuditLogger->logAuditResult([
                    'name' => $_FILES['image']['name'],
                    'size' => $_FILES['image']['size'],
                    'type' => $_FILES['image']['type'],
                    'extension' => $extension
                ], $result, $hiddenResult['path'])) {
                    error_log("记录审核结果失败");
                }

                // 获取违规原因
                $messages = [];
                if (isset($result['data']) && is_array($result['data'])) {
                    foreach ($result['data'] as $item) {
                        if (isset($item['msg'])) {
                            $messages[] = $item['msg'];
                        }
                    }
                }

                error_log("违规图片处理完成，原因: " . implode(', ', $messages));

                sendJsonResponse(400, '图片审核未通过：' . ($messages ? implode('，', $messages) : '内容不合规'), [
                    'conclusion' => $result['conclusion'],
                    'conclusion_type' => $result['conclusionType'] ?? '',
                    'details' => $messages
                ]);
            } catch (Exception $e) {
                error_log("处理违规图片失败: " . $e->getMessage());
                error_log("错误堆栈: " . $e->getTraceAsString());
                sendJsonResponse(500, '处理违规图片失败：' . $e->getMessage());
            }
        } else {
            // 其他验证错误
            sendJsonResponse(400, implode('，', $errors));
        }
    }

    // 验证删除密码
    $deletePassword = $_POST['delete_password'] ?? '';
    if (empty($deletePassword)) {
        sendJsonResponse(400, '请设置删除密码');
    }

    if (strlen($deletePassword) < 6) {
        sendJsonResponse(400, '删除密码至少需要6个字符');
    }

    // 图片审核通过，保存到上传目录
    $file = $_FILES['image'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $newFilename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    
    $uploadPath = $uploadConfig['upload_dir'] . '/' . $newFilename;
    
    // 移动文件
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        sendJsonResponse(500, '保存文件失败');
    }

    try {
        // 保存图片信息到数据库
        $stmt = $conn->prepare("INSERT INTO images (filename, original_name, file_size, mime_type, file_path, upload_time, delete_password) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
        $stmt->execute([
            $newFilename,
            $file['name'],
            $file['size'],
            $file['type'],
            'uploads/' . $newFilename,
            password_hash($deletePassword, PASSWORD_DEFAULT)
        ]);

        sendJsonResponse(200, '上传成功', [
            'url' => '/uploads/' . $newFilename,
            'filename' => $newFilename
        ]);
    } catch (Exception $e) {
        // 如果数据库操作失败，删除已上传的文件
        @unlink($uploadPath);
        sendJsonResponse(500, '保存图片信息失败');
    }

} catch (Throwable $e) {
    error_log("Upload error: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
    sendJsonResponse(500, '图片上传失败：' . $e->getMessage());
}
