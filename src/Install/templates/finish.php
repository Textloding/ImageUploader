<?php if ($step === 'finish'): ?>
<div class="text-center">
    <h3 class="mb-4">🎉 准备完成安装！</h3>
    
    <div class="alert alert-success mb-4">
        <p class="mb-0">所有配置已准备就绪，点击下方按钮完成安装。</p>
    </div>

    <div class="mb-4">
        <h4>安装完成后</h4>
        <p>为了安全起见，我们建议您：</p>
        <ul class="list-unstyled">
            <li>✔️ 删除 install.php 文件</li>
            <li>✔️ 确保配置文件权限正确</li>
            <li>✔️ 定期备份数据库</li>
        </ul>
    </div>

    <form method="post" action="install.php?step=finish">
        <button type="submit" class="btn btn-primary btn-lg">完成安装</button>
    </form>
</div>
<?php endif; ?>
