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
                <div class="share-settings-footer">
                    <button type="submit" class="layui-btn" lay-submit lay-filter="saveSettings"><i class="fa fa-save"></i> 保存设置</button>
                    <button type="button" class="layui-btn layui-btn-primary" id="btnReset">重新加载</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .share-settings-footer { position: fixed; right: 24px; bottom: 0; left: 24px; z-index: 10; padding: 12px 20px; text-align: right; background: #fff; border-top: 1px solid #eee; }
    .share-setting-tips { color: #999; font-size: 12px; line-height: 1.8; padding: 4px 0 12px 110px; }
    #settingsBody { padding-bottom: 70px; }
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

    // Tab 只切换显示，所有字段仍在同一个 form 内，保存时整表收集；
    // 若只收集当前面板，其余分组的配置会被漏提交。
    form.on('submit(saveSettings)', function () {
        var settings = {};
        $('#settingsForm input[type=checkbox]').each(function () {
            if (this.name) { settings[this.name] = this.checked ? '1' : '0'; }
        });
        $('#settingsForm input[type=text], #settingsForm input[type=password], #settingsForm textarea, #settingsForm select').each(function () {
            // Layui 渲染 select / switch 时会生成无 name 的辅助控件，不能一起提交
            if (this.name) { settings[this.name] = $(this).val(); }
        });
        $.post(apiBase, { settings: settings }, function (res) { layer.msg(res.message || '已保存'); });
        return false;
    });

    $('#btnReset').on('click', load);
    load();
});
</script>
</body>
</html>
