<?php if ($step === 'database'): ?>
<h3 class="mb-4">数据库配置</h3>

<form method="post" class="needs-validation" novalidate action="install.php?step=database">
    <div class="mb-3">
        <label for="db_host" class="form-label">数据库主机</label>
        <input type="text" class="form-control" id="db_host" name="db_host" required value="localhost">
        <div class="form-text">通常是 localhost 或 127.0.0.1</div>
    </div>

    <div class="mb-3">
        <label for="db_port" class="form-label">端口</label>
        <input type="number" class="form-control" id="db_port" name="db_port" required value="3306">
        <div class="form-text">默认MySQL端口是 3306</div>
    </div>

    <div class="mb-3">
        <label for="db_name" class="form-label">数据库名</label>
        <input type="text" class="form-control" id="db_name" name="db_name" required>
        <div class="form-text">如果数据库不存在，将自动创建</div>
    </div>

    <div class="mb-3">
        <label for="db_user" class="form-label">用户名</label>
        <input type="text" class="form-control" id="db_user" name="db_user" required>
    </div>

    <div class="mb-3">
        <label for="db_pass" class="form-label">密码</label>
        <input type="password" class="form-control" id="db_pass" name="db_pass" required>
    </div>

    <div class="text-center">
        <button type="submit" class="btn btn-primary">下一步</button>
    </div>
</form>

<script>
// 表单验证
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>
<?php endif; ?>
