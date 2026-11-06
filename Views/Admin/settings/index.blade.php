<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>应用设置 - 一键分享</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
</head>
<body>
<div class="pear-container">
    <div class="layui-card">
        <div class="layui-card-body">
            <blockquote class="layui-elem-quote">
                渠道、裂变、跟踪等全部能力均可在此自由开关；修改后即时生效，无需重启服务。
            </blockquote>
            <form class="layui-form" lay-filter="settingsForm" id="settingsForm">
                <div class="layui-tab layui-tab-brief" lay-filter="settingsTab" id="settingsTab">
                    <ul class="layui-tab-title" id="settingsTabTitle"></ul>
                    <div class="layui-tab-content" id="settingsBody"></div>
                </div>
            </form>
        </div>
    </div>
</div>

{{--
    底部操作栏放在 body 直接子级（与 .pear-container 同级），不嵌在 form / card 内：
    任一祖先若形成层叠上下文或 containing block，都会困住 fixed 定位与低 z-index，
    项目内其他设置页（CmsproLedger / CmsproAccountsync 等）的 bottom-bar 同样是 body 直接子级。
    按钮改用 click 事件收集表单（lay-submit 要求按钮在 form 内，移出后不可用），
    收集逻辑与原先 form.on('submit') 内完全一致。
--}}
<div class="share-settings-footer">
    <button type="button" class="layui-btn" id="btnSave"><i class="fa fa-save"></i> 保存设置</button>
    <button type="button" class="layui-btn layui-btn-primary" id="btnReset">重新加载</button>
</div>

<style>
    /*
     * 底部操作栏：浮动在右下角的独立按钮组（非通栏底条）。
     *
     * 设计要点：
     * 1. right/bottom 定位 + left:auto + 宽度自适应内容，使其成为右下角的浮动块，
     *    不横跨整行，避免遮挡表单内容、也不被通栏元素覆盖；
     * 2. z-index:999 高于 layui 组件层（.layui-tab-bar 为 10、表格浮层最高 1000 以下），
     *    与项目内其他设置页（CmsproAccountsync 等）取值一致；
     * 3. box-shadow + 圆角 + 白底，强化「浮动」视觉，与页面内容明确分层；
     * 4. bottom:24px 留出间隙，既不与视口底边粘连，也避开父窗口 layui-footer
     *    （44px 高、z-index 990）可能压住 iframe 下缘的区域。
     */
    .share-settings-footer {
        position: fixed; right: 24px; bottom: 24px; left: auto;
        z-index: 999;
        display: flex; align-items: center; gap: 10px;
        padding: 10px 14px;
        background: #fff;
        border: 1px solid #e6e6e6;
        border-radius: 6px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, .12);
    }
    .share-settings-footer .layui-btn { min-width: 110px; margin: 0; }

    /* 页面底部留白：保证滚到最后一屏时，末尾表单项不被浮动按钮组压住 */
    .pear-container { padding: 15px; padding-bottom: 90px; }

    .share-setting-tips { color: #999; font-size: 12px; line-height: 1.8; padding: 4px 0 12px 110px; }
    .share-tab-panel { padding: 18px 4px 8px; }
    .share-tab-empty { padding: 40px 0; color: #999; text-align: center; }
</style>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
layui.use(['form', 'layer', 'jquery', 'element'], function () {
    var form = layui.form, layer = layui.layer, $ = layui.jquery, element = layui.element;
    var apiBase = '/api/admin/cmspro/share/settings';
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

    // 按配置项类型渲染控件，Tab 内复用
    function controlHtml(item) {
        if (item.type === 'switch') {
            return '<div class="layui-input-block"><input type="checkbox" name="' + item.code + '" lay-skin="switch" lay-text="开启|关闭" ' + (item.value === '1' ? 'checked' : '') + '></div>';
        }
        if (item.type === 'select') {
            var opts = (item.options || []).map(function (o) {
                return '<option value="' + esc(o.value) + '"' + (String(o.value) === String(item.value) ? ' selected' : '') + '>' + esc(o.label) + '</option>';
            }).join('');
            return '<div class="layui-input-block"><select name="' + item.code + '">' + opts + '</select></div>';
        }
        if (item.type === 'textarea') {
            return '<div class="layui-input-block"><textarea name="' + item.code + '" class="layui-textarea" rows="2">' + esc(item.value) + '</textarea></div>';
        }
        var inputType = item.type === 'password' ? 'password' : 'text';
        return '<div class="layui-input-block"><input type="' + inputType + '" name="' + item.code + '" value="' + esc(item.value) + '" class="layui-input"></div>';
    }

    function load() {
        // 重新加载后停留在原来的分组
        var activeId = $('#settingsTabTitle li.layui-this').attr('lay-id') || '';
        $.getJSON(apiBase, function (res) {
            var items = res.data || [];
            var groups = {}, order = [];
            items.forEach(function (item) {
                var group = item.group || '基础设置';
                if (!groups[group]) { groups[group] = []; order.push(group); }
                groups[group].push(item);
            });

            if (!order.length) {
                $('#settingsTabTitle').html('');
                $('#settingsBody').html('<div class="layui-tab-item layui-show"><div class="share-tab-empty">暂无可配置项</div></div>');
                form.render();
                return;
            }

            var titleHtml = '', bodyHtml = '';
            order.forEach(function (group, i) {
                var tabId = 'share-tab-' + i;
                titleHtml += '<li lay-id="' + tabId + '"' + (i === 0 ? ' class="layui-this"' : '') + '>' + esc(group) + '</li>';
                bodyHtml += '<div class="layui-tab-item' + (i === 0 ? ' layui-show' : '') + '"><div class="share-tab-panel">';
                groups[group].forEach(function (item) {
                    bodyHtml += '<div class="layui-form-item"><label class="layui-form-label" title="' + esc(item.code) + '">' + esc(item.name) + '</label>' + controlHtml(item) + '</div>';
                    if (item.tips) { bodyHtml += '<div class="share-setting-tips">' + esc(item.tips) + '</div>'; }
                });
                bodyHtml += '</div></div>';
            });

            $('#settingsTabTitle').html(titleHtml);
            $('#settingsBody').html(bodyHtml);
            form.render();
            // 动态插入的 Tab 必须重新渲染 element 才能切换
            element.render('tab', 'settingsTab');

            if (activeId && $('#settingsTabTitle li[lay-id="' + activeId + '"]').length) {
                element.tabChange('settingsTab', activeId);
            }
        });
    }

    // 操作栏已移出 form（作为 body 直接子级，避免祖先层叠上下文困住 fixed 定位），
    // 故改用 click 事件收集；lay-submit 要求按钮在 form 内，此处不再适用。
    // Tab 只切换显示，所有字段仍在同一个 form 内，保存时整表收集；
    // 若只收集当前面板，其余分组的配置会被漏提交。
    $('#btnSave').on('click', function () {
        var settings = {};
        $('#settingsForm input[type=checkbox]').each(function () {
            if (this.name) { settings[this.name] = this.checked ? '1' : '0'; }
        });
        $('#settingsForm input[type=text], #settingsForm input[type=password], #settingsForm textarea, #settingsForm select').each(function () {
            // Layui 渲染 select / switch 时会生成无 name 的辅助控件，不能一起提交
            if (this.name) { settings[this.name] = $(this).val(); }
        });
        $.post(apiBase, { settings: settings }, function (res) { layer.msg(res.message || '已保存'); });
    });

    $('#btnReset').on('click', load);
    load();
});
</script>
</body>
</html>
