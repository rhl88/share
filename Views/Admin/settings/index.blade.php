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
                <div id="settingsBody" style="padding-bottom:70px;"></div>
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
    .share-group-title { font-size: 15px; margin: 18px 0 12px; padding-left: 8px; border-left: 3px solid #1e9fff; }
</style>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
layui.use(['form', 'layer', 'jquery'], function () {
    var form = layui.form, layer = layui.layer, $ = layui.jquery;
    var apiBase = '/api/admin/cmspro/share/settings';
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

    function load() {
        $.getJSON(apiBase, function (res) {
            var groups = {}, html = '';
            (res.data || []).forEach(function (item) {
                (groups[item.group] = groups[item.group] || []).push(item);
            });
            Object.keys(groups).forEach(function (group) {
                html += '<h3 class="share-group-title">' + esc(group) + '</h3>';
                groups[group].forEach(function (item) {
                    var id = 'cfg_' + item.code;
                    var control;
                    if (item.type === 'switch') {
                        control = '<div class="layui-input-block"><input type="checkbox" name="' + item.code + '" lay-skin="switch" lay-text="开启|关闭" ' + (item.value === '1' ? 'checked' : '') + '></div>';
                    } else if (item.type === 'select') {
                        var opts = (item.options || []).map(function (o) {
                            return '<option value="' + esc(o.value) + '"' + (String(o.value) === String(item.value) ? ' selected' : '') + '>' + esc(o.label) + '</option>';
                        }).join('');
                        control = '<div class="layui-input-block"><select name="' + item.code + '">' + opts + '</select></div>';
                    } else if (item.type === 'textarea') {
                        control = '<div class="layui-input-block"><textarea name="' + item.code + '" class="layui-textarea" rows="2">' + esc(item.value) + '</textarea></div>';
                    } else {
                        var inputType = item.type === 'password' ? 'password' : 'text';
                        control = '<div class="layui-input-block"><input type="' + inputType + '" name="' + item.code + '" value="' + esc(item.value) + '" class="layui-input"></div>';
                    }
                    html += '<div class="layui-form-item"><label class="layui-form-label" title="' + esc(item.code) + '">' + esc(item.name) + '</label>' + control + '</div>';
                    if (item.tips) { html += '<div class="share-setting-tips">' + esc(item.tips) + '</div>'; }
                });
            });
            $('#settingsBody').html(html);
            form.render();
        });
    }

    form.on('submit(saveSettings)', function (data) {
        var settings = {};
        $('#settingsForm input[type=checkbox]').each(function () { settings[this.name] = this.checked ? '1' : '0'; });
        $('#settingsForm input[type=text], #settingsForm input[type=password], #settingsForm textarea, #settingsForm select').each(function () {
            settings[this.name] = $(this).val();
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
