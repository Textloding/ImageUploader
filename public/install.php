<?php
session_start();

// 启用错误显示（仅在开发阶段使用，生产环境请禁用）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 检查是否已安装
$lockFile = __DIR__ . '/../src/Config/installed.lock';
if (file_exists($lockFile) && !isset($_GET['force'])) {
    header('Location: index.php');
    exit;
}

// 定义安装步骤
$steps = ['welcome', 'check', 'database', 'baidu', 'finish'];
$currentStep = isset($_GET['step']) ? $_GET['step'] : 'welcome';
$error = '';
$success = '';

// 辅助函数：设置目录权限
function setDirectoryPermissions($path, $permissions = 0755) {
    if (file_exists($path)) {
        return chmod($path, $permissions);
    }
    return false;
}

// 检查PHP版本和扩展，并确保目录存在且可写
function checkRequirements() {
    $requirements = [
        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'PDO Extension' => extension_loaded('pdo'),
        'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
        'GD Extension' => extension_loaded('gd'),
        'JSON Extension' => extension_loaded('json'),
        'cURL Extension' => extension_loaded('curl'),
        'FileInfo Extension' => extension_loaded('fileinfo'),
    ];

    // 定义需要检查的目录及其路径
    $directories = [
        'src/Config' => __DIR__ . '/../src/Config',
        'logs' => __DIR__ . '/../logs',
        'cache' => __DIR__ . '/../cache',
        'public/uploads' => __DIR__ . '/../public/uploads',
        'hidden_images' => __DIR__ . '/../hidden_images',
    ];

    $missingDirectories = [];
    $unwritableDirectories = [];

    foreach ($directories as $name => $path) {
        if (!is_dir($path)) {
            $missingDirectories[] = $name;
        } elseif (!is_writable($path)) {
            $unwritableDirectories[] = $name;
        }
    }

    return [
        'requirements' => $requirements,
        'directories' => $directories,
        'missingDirectories' => $missingDirectories,
        'unwritableDirectories' => $unwritableDirectories
    ];
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($currentStep) {
        case 'check':
            if (isset($_POST['fix'])) {
                // 尝试修复目录问题
                $directories = [
                    'src/Config' => __DIR__ . '/../src/Config',
                    'logs' => __DIR__ . '/../logs',
                    'cache' => __DIR__ . '/../cache',
                    'public/uploads' => __DIR__ . '/../public/uploads',
                    'hidden_images' => __DIR__ . '/../hidden_images',
                ];

                $errors = [];
                $warnings = [];

                // 创建缺失的目录
                if (isset($_SESSION['missingDirectories']) && !empty($_SESSION['missingDirectories'])) {
                    foreach ($_SESSION['missingDirectories'] as $dirName) {
                        $path = $directories[$dirName];
                        if (mkdir($path, 0755, true)) {
                            $warnings[] = "目录 <strong>{$dirName}</strong> 已成功创建。";
                            // 移除已修复的目录
                            $key = array_search($dirName, $_SESSION['missingDirectories']);
                            if ($key !== false) {
                                unset($_SESSION['missingDirectories'][$key]);
                            }
                        } else {
                            $errors[] = "无法创建目录 <strong>{$dirName}</strong>。请手动创建并赋予写权限。";
                        }
                    }
                }

                // 修改不可写目录权限
                if (isset($_SESSION['unwritableDirectories']) && !empty($_SESSION['unwritableDirectories'])) {
                    foreach ($_SESSION['unwritableDirectories'] as $dirName) {
                        $path = $directories[$dirName];
                        if (setDirectoryPermissions($path, 0755)) {
                            $warnings[] = "目录 <strong>{$dirName}</strong> 的权限已成功设置为 0755。";
                            // 移除已修复的目录
                            $key = array_search($dirName, $_SESSION['unwritableDirectories']);
                            if ($key !== false) {
                                unset($_SESSION['unwritableDirectories'][$key]);
                            }
                        } else {
                            $errors[] = "无法修改目录 <strong>{$dirName}</strong> 的权限。请手动设置为可写（例如 0755）。";
                        }
                    }
                }

                // 更新会话中的未修复目录
                $_SESSION['missingDirectories'] = isset($_SESSION['missingDirectories']) ? array_values($_SESSION['missingDirectories']) : [];
                $_SESSION['unwritableDirectories'] = isset($_SESSION['unwritableDirectories']) ? array_values($_SESSION['unwritableDirectories']) : [];

                // 反馈信息
                if (!empty($errors)) {
                    $_SESSION['error_message'] = implode('<br>', $errors);
                }
                if (!empty($warnings)) {
                    $_SESSION['success_message'] = implode('<br>', $warnings);
                }

                // 检查是否所有问题已修复
                if (empty($_SESSION['missingDirectories']) && empty($_SESSION['unwritableDirectories'])) {
                    $_SESSION['requirements_passed'] = true;
                    header('Location: install.php?step=database');
                    exit;
                } else {
                    header('Location: install.php?step=check');
                    exit;
                }
            } elseif (isset($_POST['fix_directory'])) {
                // 获取要修复的目录
                $dirName = $_POST['fix_directory'];
                $directories = [
                    'src/Config' => __DIR__ . '/../src/Config',
                    'logs' => __DIR__ . '/../logs',
                    'cache' => __DIR__ . '/../cache',
                    'public/uploads' => __DIR__ . '/../public/uploads',
                    'hidden_images' => __DIR__ . '/../hidden_images',
                ];

                if (!isset($directories[$dirName])) {
                    $_SESSION['error_message'] = "无效的目录: {$dirName}";
                    header('Location: install.php?step=check');
                    exit;
                }

                $path = $directories[$dirName];
                $success = false;
                $message = '';

                // 如果目录不存在，尝试创建
                if (!is_dir($path)) {
                    if (mkdir($path, 0755, true)) {
                        $success = true;
                        $message = "目录 {$dirName} 创建成功！";
                    } else {
                        $message = "无法创建目录 {$dirName}，请手动创建并设置权限：<br>" .
                                 "1. 创建目录：{$path}<br>" .
                                 "2. 设置权限：chmod 755 {$path}";
                    }
                }
                // 如果目录存在但不可写，尝试修改权限
                else if (!is_writable($path)) {
                    if (chmod($path, 0755)) {
                        $success = true;
                        $message = "目录 {$dirName} 权限修复成功！";
                    } else {
                        $message = "无法修改目录 {$dirName} 的权限，请手动设置：<br>" .
                                 "chmod 755 {$path}";
                    }
                }

                if ($success) {
                    $_SESSION['success_message'] = $message;
                } else {
                    $_SESSION['error_message'] = $message;
                }

                header('Location: install.php?step=check');
                exit;
            } else {
                // 初始检查
                $req = checkRequirements();
                $errors = [];

                // 检查PHP版本和扩展
                foreach ($req['requirements'] as $requirement => $passed) {
                    if (!$passed) {
                        $errors[] = "<strong>{$requirement}</strong> 不满足。";
                    }
                }

                // 检查目录
                if (!empty($req['missingDirectories'])) {
                    $errors[] = "以下目录不存在：<ul>";
                    foreach ($req['missingDirectories'] as $dir) {
                        $errors[] = "<li>{$dir}</li>";
                    }
                    $errors[] = "</ul>";
                }

                if (!empty($req['unwritableDirectories'])) {
                    $errors[] = "以下目录不可写：<ul>";
                    foreach ($req['unwritableDirectories'] as $dir) {
                        $errors[] = "<li>{$dir}</li>";
                    }
                    $errors[] = "</ul>";
                }

                if (empty($errors)) {
                    $_SESSION['requirements_passed'] = true;
                    header('Location: install.php?step=database');
                    exit;
                } else {
                    $error = implode('', $errors);
                    // 保存未满足的目录到会话，以便修复步骤使用
                    $_SESSION['missingDirectories'] = $req['missingDirectories'];
                    $_SESSION['unwritableDirectories'] = $req['unwritableDirectories'];
                    $_SESSION['error_message'] = $error;
                    header('Location: install.php?step=check');
                    exit;
                }
            }
            break;

        case 'database':
            try {
                // 获取数据库配置
                $db_host = trim($_POST['db_host']);
                $db_port = trim($_POST['db_port']);
                $db_name = trim($_POST['db_name']);
                $db_user = trim($_POST['db_user']);
                $db_pass = trim($_POST['db_pass']);

                // 验证输入
                if (empty($db_host) || empty($db_port) || empty($db_name) || empty($db_user)) {
                    throw new Exception('请填写所有必填字段。');
                }

                // 创建数据库连接
                $dsn = "mysql:host={$db_host};port={$db_port}";
                $pdo = new PDO($dsn, $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                // 创建数据库
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$db_name}`");

                // 创建表，包括 status 列和删除密码
                $pdo->exec("CREATE TABLE IF NOT EXISTS `images` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `filename` varchar(255) NOT NULL,
                    `original_name` varchar(255) NOT NULL,
                    `file_size` int(11) NOT NULL,
                    `mime_type` varchar(100) NOT NULL,
                    `file_path` varchar(255) NOT NULL,
                    `upload_time` datetime NOT NULL,
                    `views` int(11) NOT NULL DEFAULT '0',
                    `status` varchar(20) NOT NULL DEFAULT 'active',
                    `delete_password` varchar(255) NOT NULL COMMENT '删除密码',
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

                // 保存数据库配置到会话
                $_SESSION['db_host'] = $db_host;
                $_SESSION['db_port'] = $db_port;
                $_SESSION['db_name'] = $db_name;
                $_SESSION['db_user'] = $db_user;
                $_SESSION['db_pass'] = $db_pass;

                $_SESSION['installed_db'] = true;
                header('Location: install.php?step=baidu');
                exit;
            } catch (Exception $e) {
                $error = '数据库连接失败：' . htmlspecialchars($e->getMessage());
                $_SESSION['error_message'] = $error;
                header('Location: install.php?step=check');
                exit;
            }
            break;

        case 'baidu':
            // 检查是否启用百度审核
            $enable_baidu = isset($_POST['enable_baidu']);
            
            if ($enable_baidu) {
                // 验证百度API凭据
                $client_id = trim($_POST['client_id']);
                $client_secret = trim($_POST['client_secret']);

                if (empty($client_id) || empty($client_secret)) {
                    $error = '百度API的 Client ID 和 Client Secret 不能为空。';
                    $_SESSION['error_message'] = $error;
                    header('Location: install.php?step=baidu');
                    exit;
                }

                $url = "https://aip.baidubce.com/oauth/2.0/token?grant_type=client_credentials&client_id={$client_id}&client_secret={$client_secret}";

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $response = curl_exec($ch);
                $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);

                if ($status !== 200) {
                    $error = '验证百度API凭据失败。';
                    if (!empty($curl_error)) {
                        $error .= " cURL 错误：{$curl_error}";
                    }
                    $_SESSION['error_message'] = $error;
                    header('Location: install.php?step=baidu');
                    exit;
                }

                $result = json_decode($response, true);
                if (!isset($result['access_token'])) {
                    $error = '无法获取百度API访问令牌。请检查API Key和Secret Key是否正确。';
                    $_SESSION['error_message'] = $error;
                    header('Location: install.php?step=baidu');
                    exit;
                }
            }

            // 保存配置
            $config = [
                'enabled' => $enable_baidu,
                'client_id' => $enable_baidu ? $client_id : '',
                'client_secret' => $enable_baidu ? $client_secret : '',
                'grant_type' => 'client_credentials',
                'token_url' => 'https://aip.baidubce.com/oauth/2.0/token',
                'censor_url' => 'https://aip.baidubce.com/rest/2.0/solution/v1/img_censor/v2/user_defined'
            ];

            $_SESSION['baidu_config'] = $config;
            header('Location: install.php?step=finish');
            exit;
            break;

        case 'finish':
            // 如果是 GET 请求，只显示完成页面
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                if (!isset($_SESSION['db_host']) || !isset($_SESSION['baidu_config'])) {
                    header('Location: install.php?step=welcome');
                    exit;
                }
                break;
            }

            // POST 请求时执行实际的安装操作
            if (!isset($_SESSION['db_host']) || !isset($_SESSION['baidu_config'])) {
                error_log("Missing required session data");
                header('Location: install.php?step=welcome');
                exit;
            }

            // 获取项目根目录的绝对路径
            $baseDir = realpath(__DIR__ . '/..');
            $publicDir = realpath(__DIR__);
            
            error_log("Base Dir: " . $baseDir);
            error_log("Public Dir: " . $publicDir);

            // 确保所有必要的目录存在
            $directories = [
                $baseDir . '/src/Config',
                $baseDir . '/logs',
                $baseDir . '/cache',
                $publicDir . '/uploads',
                $baseDir . '/hidden_images'
            ];

            foreach ($directories as $dir) {
                if (!file_exists($dir)) {
                    error_log("Creating directory: " . $dir);
                    if (!mkdir($dir, 0755, true)) {
                        $error = "无法创建目录：{$dir}";
                        error_log($error);
                        $_SESSION['error_message'] = $error;
                        header('Location: install.php?step=check');
                        exit;
                    }
                }
            }

            // 创建配置文件内容
            $configContent = "<?php\n\n" .
                "// 数据库配置\n" .
                "\$dbConfig = " . var_export([
                    'host' => $_SESSION['db_host'],
                    'port' => $_SESSION['db_port'],
                    'name' => $_SESSION['db_name'],
                    'user' => $_SESSION['db_user'],
                    'pass' => $_SESSION['db_pass']
                ], true) . ";\n\n" .
                "// 百度API配置\n" .
                "\$baiduConfig = " . var_export($_SESSION['baidu_config'], true) . ";\n\n" .
                "// 上传配置\n" .
                "\$uploadConfig = " . var_export([
                    'max_file_size' => 10 * 1024 * 1024, // 10MB
                    'allowed_types' => ['image/jpeg', 'image/png', 'image/gif'],
                    'upload_dir' => $publicDir . '/uploads',
                    'hidden_dir' => $baseDir . '/hidden_images',
                    'log_dir' => $baseDir . '/logs',
                    'cache_dir' => $baseDir . '/cache',
                    'base_dir' => $baseDir,
                    'public_dir' => $publicDir
                ], true) . ";\n\n" .
                "// 获取数据库连接\n" .
                "function getDbConnection() {\n" .
                "    global \$dbConfig;\n" .
                "    \$dsn = \"mysql:host={\$dbConfig['host']};port={\$dbConfig['port']};dbname={\$dbConfig['name']};charset=utf8mb4\";\n" .
                "    return new PDO(\$dsn, \$dbConfig['user'], \$dbConfig['pass'], [\n" .
                "        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n" .
                "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n" .
                "        PDO::ATTR_EMULATE_PREPARES => false,\n" .
                "    ]);\n" .
                "}\n";

            $configFile = $publicDir . '/config.php';
            error_log("Writing config file to: " . $configFile);
            error_log("Config content length: " . strlen($configContent));

            // 写入配置文件
            $writeResult = file_put_contents($configFile, $configContent);
            if ($writeResult === false) {
                $error = "无法创建配置文件。请检查目录权限。路径：{$configFile}";
                error_log($error);
                error_log("Write result: " . var_export($writeResult, true));
                $_SESSION['error_message'] = $error;
                header('Location: install.php?step=check');
                exit;
            }
            error_log("Config file written successfully: " . $writeResult . " bytes");

            // 创建安装锁定文件
            error_log("Creating lock file: " . $lockFile);
            $lockResult = file_put_contents($lockFile, date('Y-m-d H:i:s'));
            if ($lockResult === false) {
                // 如果创建锁定文件失败，删除配置文件
                error_log("Failed to create lock file");
                @unlink($configFile);
                $error = "无法创建安装锁定文件。请检查目录权限。路径：{$lockFile}";
                error_log($error);
                $_SESSION['error_message'] = $error;
                header('Location: install.php?step=check');
                exit;
            }
            error_log("Lock file created successfully");

            // 验证文件是否真实存在
            if (!file_exists($configFile)) {
                error_log("Config file does not exist after creation!");
                $_SESSION['error_message'] = "配置文件创建失败，文件不存在。";
                header('Location: install.php?step=check');
                exit;
            }
            
            if (!file_exists($lockFile)) {
                error_log("Lock file does not exist after creation!");
                @unlink($configFile);
                $_SESSION['error_message'] = "锁定文件创建失败，文件不存在。";
                header('Location: install.php?step=check');
                exit;
            }

            error_log("Installation completed successfully");
            
            // 保存会话数据
            $sessionData = $_SESSION;
            
            // 清除安装会话数据
            session_unset();
            session_destroy();
            
            // 启动新会话
            session_start();
            
            // 重定向到首页
            header('Location: index.php');
            exit;
            break;

        // 其他步骤不处理
    }
}

// 获取当前步骤的模板
function getStepTemplate($step, $error = '', $success = '') {
    ob_start();
    include __DIR__ . "/../src/Install/templates/{$step}.php";
    return ob_get_clean();
}

// HTML模板
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装向导 - 图片上传服务</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .install-container {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .step-indicator {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
            margin: 0 5px;
            background: #e9ecef;
            border-radius: 5px;
        }
        .step.active {
            background: #007bff;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="step-indicator">
            <?php foreach ($steps as $step): ?>
            <div class="step <?php 
                if ($step === $currentStep) echo 'active';
                elseif (array_search($step, $steps) < array_search($currentStep, $steps)) echo 'completed';
            ?>">
                <?php echo ucfirst($step); ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php 
        // 显示错误和警告信息
        if (isset($_SESSION['error_message']) && !empty($_SESSION['error_message'])):
            echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
            unset($_SESSION['error_message']);
        endif;

        if (isset($_SESSION['success_message']) && !empty($_SESSION['success_message'])):
            echo '<div class="alert alert-warning">' . $_SESSION['success_message'] . '</div>';
            unset($_SESSION['success_message']);
        endif;
        ?>

        <?php echo getStepTemplate($currentStep, $error, $success); ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
