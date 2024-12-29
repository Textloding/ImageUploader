<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    global $config, $uploadConfig;
    // 验证请求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('无效的请求方法');
    }

    // 获取参数
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }

    $filename = $data['filename'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($filename) || empty($password)) {
        throw new Exception('缺少必要参数');
    }

    // 连接数据库
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 查询图片信息
    $stmt = $pdo->prepare("SELECT * FROM images WHERE filename = ?");
    $stmt->execute([$filename]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$image) {
        throw new Exception('图片不存在');
    }

    // 验证密码
    if (!password_verify($password, $image['delete_password'])) {
        throw new Exception('删除密码错误');
    }

    // 开始删除操作
    $pdo->beginTransaction();

    try {
        // 从数据库中删除记录
        $stmt = $pdo->prepare("DELETE FROM images WHERE filename = ?");
        $stmt->execute([$filename]);

        // 删除文件
        $filePath = $uploadConfig['upload_dir'] . '/' . $filename;
        if (file_exists($filePath)) {
            if (!unlink($filePath)) {
                throw new Exception('删除文件失败');
            }
        }

        $pdo->commit();
        echo json_encode(['code' => 200, 'message' => '删除成功']);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    echo json_encode([
        'code' => 400,
        'message' => $e->getMessage()
    ]);
}
