<?php
// 检查是否已安装
if (!file_exists(__DIR__ . '/../src/Config/installed.lock')) {
    header('Location: install.php');
    exit;
}

session_start();
require_once __DIR__ . '/config.php';

// 获取数据库连接
try {
    $conn = getDbConnection();
} catch(PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    $conn = null;
}

// 处理图片预览请求
if (isset($_GET['view']) && isset($_GET['id'])) {
    try {
        $stmt = $conn->prepare("SELECT * FROM images WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($image) {
            // 更新查看次数
            $stmt = $conn->prepare("UPDATE images SET views = views + 1 WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            
            // 重定向到实际图片
            header('Location: ' . $image['file_path']);
            exit;
        }
    } catch(PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
    }
    header('Location: index.php');
    exit;
}

// 从数据库获取所有图片并按日期分组
$groupedImages = [];
if ($conn) {
    try {
        $stmt = $conn->query("SELECT *, DATE(upload_time) as upload_date FROM images ORDER BY upload_time DESC");
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 按日期分组
        foreach ($images as $image) {
            $date = $image['upload_date'];
            if (!isset($groupedImages[$date])) {
                $groupedImages[$date] = [];
            }
            $groupedImages[$date][] = $image;
        }
    } catch(PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>简单图床</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --text-color: #2c3e50;
            --border-radius: 12px;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
            padding: 2rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 2rem;
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }

        .header p {
            color: #666;
            font-size: 1.1rem;
        }

        .upload-container {
            background-color: var(--card-bg);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            transition: transform 0.2s;
        }

        .upload-area {
            border: 2px dashed #ccc;
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
            position: relative;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .upload-area.drag-over {
            border-color: var(--primary-color);
            background-color: rgba(52, 152, 219, 0.1);
        }

        .upload-area i {
            font-size: 3rem;
            color: #666;
            margin-bottom: 1rem;
        }

        .upload-area p {
            margin: 0.5rem 0;
            color: #666;
        }

        .upload-area .shortcuts {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #888;
        }

        .upload-area .shortcuts kbd {
            background-color: #f8f9fa;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            border: 1px solid #ddd;
            margin: 0 0.2rem;
        }

        .gallery {
            margin-top: 2rem;
        }

        .date-group {
            margin-bottom: 2rem;
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .date-header {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            background: var(--primary-color);
            color: white;
            cursor: pointer;
            user-select: none;
        }

        .date-header h2 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 500;
        }

        .date-header .toggle-icon {
            margin-left: auto;
            transition: transform 0.3s ease;
            font-size: 1.2rem;
        }

        .date-header.collapsed .toggle-icon {
            transform: rotate(-90deg);
        }

        .date-content {
            padding: 1.5rem;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            background: var(--bg-color);
        }

        .date-content.collapsed {
            display: none;
        }

        .image-container {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .image-container:hover {
            transform: translateY(-4px);
        }

        .image-preview {
            position: relative;
            padding-top: 75%;
            overflow: hidden;
            background: #f8f9fa;
        }

        .image-link {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: block;
        }

        .image-link img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .image-link:hover img {
            transform: scale(1.05);
        }

        .image-info {
            padding: 1rem;
        }

        .image-url-container {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .image-url {
            flex: 1;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #666;
            background: #f8f9fa;
            cursor: default;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            user-select: none;
            -webkit-user-select: none;
        }

        .copy-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            white-space: nowrap;
            min-width: 100px;
            justify-content: center;
        }

        .copy-btn:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        .copy-btn:active {
            transform: translateY(0);
        }

        .copy-btn.copied {
            background: var(--success-color);
        }

        .copy-btn .copy-icon {
            font-size: 1rem;
        }

        .copy-btn.copied .copy-icon {
            animation: popIn 0.3s ease;
        }

        @keyframes popIn {
            0% { transform: scale(0.5); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        .stats {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: #666;
        }

        .image-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        
        .image-actions .btn-delete {
            background-color: transparent;
            color: #dc3545;
            border: 1px solid #dc3545;
            padding: 4px 12px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .image-actions .btn-delete:hover {
            background-color: #dc3545;
            color: white;
        }

        .image-actions .left-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .image-actions .right-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .view-count {
            color: #666;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .date-content {
                grid-template-columns: 1fr;
                padding: 1rem;
            }

            .date-header {
                padding: 0.8rem 1rem;
            }

            .image-url-container {
                flex-direction: column;
                align-items: stretch;
            }

            .copy-btn {
                width: 100%;
                margin-top: 0.5rem;
            }
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>简单图床</h1>
            <p>支持拖拽上传、复制粘贴、快捷键操作</p>
        </div>

        <div class="upload-container">
            <div id="uploadArea" class="upload-area">
                <i>📁</i>
                <p>拖拽图片到这里 或 点击上传</p>
                <p class="shortcuts">
                    支持快捷键：<kbd>Ctrl</kbd> + <kbd>V</kbd> 粘贴图片
                </p>
                <input type="file" id="fileInput" style="display: none" accept="image/*">
            </div>
        </div>

        <div class="gallery">
            <?php foreach ($groupedImages as $date => $dateImages): ?>
            <div class="date-group">
                <div class="date-header" onclick="toggleDateGroup(this)">
                    <h2><?php echo date('Y年m月d日', strtotime($date)); ?></h2>
                    <span class="toggle-icon">▼</span>
                </div>
                <div class="date-content">
                    <?php foreach ($dateImages as $image): ?>
                    <div class="image-container">
                        <div class="image-preview">
                            <a href="<?php echo htmlspecialchars($image['file_path']); ?>" 
                               target="_blank" 
                               class="image-link">
                                <img src="<?php echo htmlspecialchars($image['file_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($image['original_name']); ?>" 
                                     loading="lazy">
                            </a>
                        </div>
                        <div class="image-info">
                            <div class="image-url-container">
                                <input type="text" class="image-url" readonly
                                       value="<?php echo htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/' . $image['file_path']); ?>"
                                       data-clipboard="true">
                                <button class="copy-btn" data-clipboard-target=".image-url">
                                    <span class="btn-text">复制链接</span>
                                    <span class="copy-icon">📋</span>
                                </button>
                            </div>
                            <div class="image-actions">
                                <div class="left-actions">
                                    <span class="view-count" id="views_<?php echo $image['id']; ?>">
                                        查看：<?php echo $image['views']; ?>次
                                    </span>
                                </div>
                                <div class="right-actions">
                                    <button class="btn btn-delete btn-sm" onclick="deleteImage('<?php echo $image['filename']; ?>')">
                                        删除
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="loading-overlay">
        <div>
            <h3>正在审核图片...</h3>
            <p>请稍候</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/clipboard@2.0.11/dist/clipboard.min.js"></script>
    <script>
        // 初始化clipboard.js
        let clipboard = null;
        
        function initClipboard() {
            if (clipboard) {
                clipboard.destroy();
            }
            
            clipboard = new ClipboardJS('.copy-btn', {
                text: function(trigger) {
                    const container = trigger.closest('.image-url-container');
                    return container.querySelector('.image-url').value;
                }
            });

            clipboard.on('success', function(e) {
                showCopySuccess(e.trigger);
                e.clearSelection();
            });

            clipboard.on('error', function() {
                showToast('复制失败，请刷新页面重试', 'error');
            });
        }

        // 显示复制成功的动画和提示
        function showCopySuccess(button) {
            showToast('链接已复制！', 'success');
            animateCopyButton(button);
        }

        // 复制按钮动画
        function animateCopyButton(button) {
            const originalText = button.querySelector('.btn-text');
            const icon = button.querySelector('.copy-icon');
            
            button.classList.add('copied');
            originalText.textContent = '已复制！';
            icon.textContent = '✓';
            
            setTimeout(() => {
                button.classList.remove('copied');
                originalText.textContent = '复制链接';
                icon.textContent = '📋';
            }, 2000);
        }

        // 显示提示消息
        function showToast(message, type = 'info') {
            Toastify({
                text: message,
                duration: 2000,
                gravity: "top",
                position: 'right',
                style: {
                    background: type === 'success' ? 'var(--success-color)' : 
                               type === 'error' ? 'var(--error-color)' : 'var(--primary-color)',
                    borderRadius: '4px',
                    boxShadow: '0 2px 4px rgba(0,0,0,0.1)'
                }
            }).showToast();
        }

        // 折叠/展开日期组
        function toggleDateGroup(header) {
            const content = header.nextElementSibling;
            header.classList.toggle('collapsed');
            content.classList.toggle('collapsed');
        }

        document.addEventListener('DOMContentLoaded', function() {
            // 初始化clipboard.js
            initClipboard();
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('fileInput');
            const loadingOverlay = document.querySelector('.loading-overlay');
            let isUploading = false;

            // 处理文件上传
            function handleFiles(files) {
                if (isUploading) {
                    showToast('正在上传中，请稍候...', 'info');
                    return;
                }

                if (!files || files.length === 0) {
                    showToast('请选择图片文件！', 'error');
                    return;
                }

                const file = files[0];
                if (!file.type.startsWith('image/')) {
                    showToast('请选择正确的图片文件！', 'error');
                    return;
                }

                // 检查文件大小（10MB限制）
                const maxSize = 10 * 1024 * 1024;
                if (file.size > maxSize) {
                    showToast('图片大小不能超过10MB！', 'error');
                    return;
                }

                const formData = new FormData();
                formData.append('image', file);
                uploadFile(formData);
            }

            // 上传文件到服务器
            async function uploadFile(formData) {
                try {
                    // 弹出密码设置对话框
                    const { value: password } = await Swal.fire({
                        title: '设置删除密码',
                        input: 'password',
                        inputLabel: '请设置图片删除密码（至少6个字符）',
                        inputPlaceholder: '请输入密码',
                        inputAttributes: {
                            minlength: 6,
                            autocapitalize: 'off',
                            autocorrect: 'off'
                        },
                        inputValidator: (value) => {
                            if (!value) {
                                return '请输入密码！';
                            }
                            if (value.length < 6) {
                                return '密码长度至少6个字符！';
                            }
                        },
                        showCancelButton: true,
                        confirmButtonText: '确定',
                        cancelButtonText: '取消',
                        reverseButtons: true
                    });

                    if (!password) {
                        return; // 用户取消了操作
                    }

                    // 添加密码到表单数据
                    formData.append('delete_password', password);

                    isUploading = true;
                    loadingOverlay.style.display = 'flex';

                    const response = await fetch('upload.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    if (!response.ok) {
                        throw new Error(result.message || '上传失败');
                    }

                    if (result.code === 200) {
                        await Swal.fire({
                            icon: 'success',
                            title: '上传成功',
                            html: `
                                <p>${result.message}</p>
                                <p class="text-warning">删除密码：${password}</p>
                                <p class="small">请保存好删除密码，删除图片时需要验证。</p>
                            `,
                            confirmButtonText: '确定'
                        });
                        window.location.reload();
                    } else {
                        throw new Error(result.message || '上传失败');
                    }
                } catch (error) {
                    console.error('Upload error:', error);
                    await Swal.fire({
                        icon: 'error',
                        title: '上传失败',
                        text: error.message || '上传过程中发生错误，请稍后重试',
                        confirmButtonText: '确定'
                    });
                } finally {
                    isUploading = false;
                    loadingOverlay.style.display = 'none';
                }
            }

            // 显示删除确认对话框
            window.deleteImage = async function(filename) {
                const result = await Swal.fire({
                    title: '删除图片',
                    input: 'password',
                    inputLabel: '请输入删除密码',
                    inputPlaceholder: '请输入密码',
                    showCancelButton: true,
                    confirmButtonText: '删除',
                    cancelButtonText: '取消',
                    confirmButtonColor: '#dc3545'
                });

                if (result.value) {
                    try {
                        const response = await fetch('/delete.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                filename: filename,
                                password: result.value
                            })
                        });

                        const data = await response.json();
                        if (data.code === 200) {
                            await Swal.fire('删除成功', '', 'success');
                            window.location.reload();
                        } else {
                            throw new Error(data.message || '删除失败');
                        }
                    } catch (error) {
                        await Swal.fire('错误', error.message, 'error');
                    }
                }
            };

            // 点击上传区域触发文件选择
            uploadArea.addEventListener('click', () => {
                if (!isUploading) {
                    fileInput.click();
                }
            });

            // 文件选择处理
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    handleFiles(e.target.files);
                }
                // 清空input，允许选择相同文件
                e.target.value = '';
            });

            // 拖放处理
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                if (!isUploading) {
                    uploadArea.classList.add('drag-over');
                }
            });

            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('drag-over');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('drag-over');
                if (!isUploading && e.dataTransfer.files.length > 0) {
                    handleFiles(e.dataTransfer.files);
                }
            });

            // 粘贴处理
            document.addEventListener('paste', (e) => {
                if (isUploading) return;
                
                const items = e.clipboardData.items;
                for (let item of items) {
                    if (item.type.startsWith('image/')) {
                        const file = item.getAsFile();
                        handleFiles([file]);
                        break;
                    }
                }
            });

            // 防止页面关闭时中断上传
            window.addEventListener('beforeunload', (e) => {
                if (isUploading) {
                    e.preventDefault();
                    e.returnValue = '文件正在上传中，确定要离开吗？';
                }
            });
        });
    </script>
</body>
</html>
