<?php if ($step === 'baidu'): ?>
<h3 class="mb-4">百度AI配置</h3>

<form method="post" class="needs-validation" novalidate action="install.php?step=baidu">
    <div class="mb-4">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="enable_baidu" name="enable_baidu" onchange="toggleBaiduForm(this.checked)">
            <label class="form-check-label" for="enable_baidu">启用百度内容审核</label>
        </div>
        <div class="form-text">如果不启用，图片将不会进行内容审核</div>
    </div>

    <div id="baiduConfigForm" style="display: none;">
        <div class="alert alert-info mb-4">
            <h5>如何获取百度AI API密钥？</h5>
            <ol>
                <li>访问 <a href="https://ai.baidu.com/" target="_blank">百度AI开放平台</a></li>
                <li>注册/登录账号</li>
                <li>创建应用，获取API Key和Secret Key</li>
                <li>开通内容审核服务</li>
            </ol>
        </div>

        <div class="mb-3">
            <label for="client_id" class="form-label">API Key</label>
            <input type="text" class="form-control" id="client_id" name="client_id">
            <div class="form-text">百度AI平台的API Key</div>
        </div>

        <div class="mb-3">
            <label for="client_secret" class="form-label">Secret Key</label>
            <input type="text" class="form-control" id="client_secret" name="client_secret">
            <div class="form-text">百度AI平台的Secret Key</div>
        </div>
    </div>

    <div class="text-center">
        <button type="submit" class="btn btn-primary">下一步</button>
    </div>
</form>

<script>
function toggleBaiduForm(enabled) {
    const form = document.getElementById('baiduConfigForm');
    form.style.display = enabled ? 'block' : 'none';
    
    // 启用/禁用表单验证
    const inputs = form.getElementsByTagName('input');
    for (let input of inputs) {
        input.required = enabled;
    }
}

// 表单验证
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const enableBaidu = document.getElementById('enable_baidu').checked;
            if (enableBaidu) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>
<?php endif; ?>
