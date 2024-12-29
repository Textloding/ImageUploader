<?php if ($step === 'check'): ?>
<h3 class="mb-4">系统环境检查</h3>

<div class="mb-4">
    <h4>PHP环境检查</h4>
    <table class="table">
        <thead>
            <tr>
                <th>检查项目</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $check = checkRequirements();
            $allPassed = true;
            foreach ($check['requirements'] as $requirement => $passed): 
                $allPassed = $allPassed && $passed;
            ?>
            <tr>
                <td><?php echo htmlspecialchars($requirement); ?></td>
                <td>
                    <?php if ($passed): ?>
                    <span class="badge bg-success">通过</span>
                    <?php else: ?>
                    <span class="badge bg-danger">未通过</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$passed): ?>
                    <div class="alert alert-warning p-2 mb-0">
                        请安装或启用相应的PHP扩展
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="mb-4">
    <h4>目录权限检查</h4>
    <table class="table">
        <thead>
            <tr>
                <th>目录</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($check['directories'] as $directory => $path): 
                $exists = is_dir($path);
                $writable = $exists && is_writable($path);
                $allPassed = $allPassed && $exists && $writable;
            ?>
            <tr>
                <td><?php echo htmlspecialchars($directory); ?></td>
                <td>
                    <?php if (!$exists): ?>
                        <span class="badge bg-danger">不存在</span>
                    <?php elseif (!$writable): ?>
                        <span class="badge bg-warning">不可写</span>
                    <?php else: ?>
                        <span class="badge bg-success">正常</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$exists || !$writable): ?>
                        <form method="post" action="install.php?step=check" class="d-inline">
                            <input type="hidden" name="fix_directory" value="<?php echo htmlspecialchars($directory); ?>">
                            <button type="submit" class="btn btn-warning btn-sm">
                                <?php echo !$exists ? '创建目录' : '修复权限'; ?>
                            </button>
                        </form>
                        <div class="mt-1 small text-muted">
                            <?php if (!$exists): ?>
                                手动创建: <?php echo htmlspecialchars($path); ?>
                            <?php else: ?>
                                手动修复: chmod 755 <?php echo htmlspecialchars($path); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="text-center mt-4">
    <?php if (!$allPassed): ?>
    <div class="alert alert-warning">
        请修复上述问题后再继续安装。如果自动修复失败，请按照提示手动修复。
    </div>
    <form method="post" action="install.php?step=check" class="d-inline">
        <button type="submit" class="btn btn-primary">重新检查</button>
    </form>
    <?php else: ?>
    <a href="?step=database" class="btn btn-primary">继续安装</a>
    <?php endif; ?>
</div>
<?php endif; ?>
