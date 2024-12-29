<?php if ($step === 'welcome'): ?>
<div class="text-center">
    <h2>欢迎使用图片上传服务安装向导</h2>
    <p>这个向导将帮助您完成必要的配置和安装。</p>
</div>

<div class="mb-4">
    <h4>安装步骤：</h4>
    <ol>
        <li>检查系统要求</li>
        <li>配置数据库连接</li>
        <li>设置百度AI API</li>
        <li>完成安装</li>
    </ol>
</div>

<div class="alert alert-info">
    <strong>提示：</strong> 在开始安装之前，请确保您已经：
    <ul class="mb-0">
        <li>准备好MySQL数据库的连接信息</li>
        <li>获取了百度AI API的密钥</li>
        <li>确保所有目录具有写入权限</li>
    </ul>
</div>

<div class="text-center">
    <a href="?step=check" class="btn btn-primary btn-lg">开始安装</a>
</div>
<?php endif; ?>
