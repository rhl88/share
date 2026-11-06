<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>分享预览 - 一键分享</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <link rel="stylesheet" href="{{ share_asset('css/share.css') }}">
    <style>
        .share-hint { color: #999; font-size: 12px; line-height: 1.8; margin-top: 10px; }
        #sharePanel {
            min-height: 64px;
            padding: 12px;
            background: #f8fafc;
            border: 1px dashed #e2e8f0;
            border-radius: 8px;
        }
        #sharePanel .cmspro-share { margin: 0; }
        .share-info td:first-child { width: 100px; color: #666; }
        .share-media { display: flex; gap: 24px; flex-wrap: wrap; }
        .share-media-item { flex: 1 1 200px; text-align: center; }
        .share-media-item h4 { font-size: 13px; color: #666; font-weight: 400; margin-bottom: 10px; }
        .share-media-item img { border: 1px solid #eee; border-radius: 6px; background: #fff; object-fit: contain; }
        #pvQrcode { width: 220px; height: 220px; }
        #pvPoster { width: 190px; height: 285px; }
        .share-link-cell { word-break: break-all; }
    </style>
</head>
<body>
<div class="pear-container">
    <div class="layui-card">
        <div class="layui-card-header">分享预览</div>
        <div class="layui-card-body">
            <blockquote class="layui-elem-quote">
                选择场景并填写对象 ID 后生成<strong>真实分享链接</strong>；每次生成都会写入一条真实分享记录与 create 事件，会计入数据看板。演示场景不对应真实业务内容，仅用于查看效果。
            </blockquote>
            <form class="layui-form" lay-filter="previewForm" id="previewForm">
                <div class="layui-form-item">
                    <div class="layui-inline">
                        <label class="layui-form-label">分享场景</label>
                        <div class="layui-input-inline" style="width: 320px;">
                            <select name="scene_code" id="sceneCode"></select>
                        </div>
                    </div>
                    <div class="layui-inline">
                        <label class="layui-form-label">对象 ID</label>
                        <div class="layui-input-inline" style="width: 140px;">
                            <input type="text" name="target_id" value="1" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-inline">
                        <label class="layui-form-label">展现方式</label>
                        <div class="layui-input-inline" style="width: 160px;">
                            <select name="layout" id="layoutSelect" lay-filter="layoutSelect">
                                <option value="default">图标+文字（默认）</option>
                                <option value="icon">纯图标</option>
                                <option value="text">纯文字</option>
                                <option value="popup">点击弹出</option>
                            </select>
                        </div>
                    </div>
                    <div class="layui-inline">
                        <button type="button" class="layui-btn" id="btnBuild"><i class="fa fa-eye"></i> 生成预览</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="previewResult" style="display: none;">
        <div class="layui-card">
            <div class="layui-card-header">分享面板（真实交互，可点击体验）</div>
            <div class="layui-card-body">
                <div id="sharePanel"></div>
                <p class="share-hint">展现方式支持四种形态：图标+文字（默认）、纯图标、纯文字、点击弹出（弹层内为完整图标+文字面板）。按钮图标取自渠道 icon 配置；在电脑上点击「微信好友 / 朋友圈」会弹出扫码分享层（手机扫码转发 + 复制文字 / 链接）；点击会上报 click 事件。</p>
            </div>
        </div>

        <div class="layui-row layui-col-space15">
            <div class="layui-col-md7">
                <div class="layui-card">
                    <div class="layui-card-header">分享信息</div>
                    <div class="layui-card-body">
                        <table class="layui-table layui-table-cell share-info" lay-size="sm">
                            <tbody>
                                <tr><td>短码</td><td id="pvCode" class="share-link-cell"></td></tr>
                                <tr><td>中转链接</td><td id="pvUrl" class="share-link-cell"></td></tr>
                                <tr><td>目标链接</td><td id="pvTarget" class="share-link-cell"></td></tr>
                                <tr><td>标题</td><td id="pvTitle"></td></tr>
                                <tr><td>摘要</td><td id="pvSummary"></td></tr>
                                <tr><td>场景</td><td id="pvScene"></td></tr>
                            </tbody>
                        </table>
                        <a id="pvRedirect" class="layui-btn layui-btn-normal" target="_blank" rel="noopener" href="#">
                            <i class="fa fa-external-link"></i> 打开中转链接（记录一次落地）
                        </a>
                    </div>
                </div>
            </div>
            <div class="layui-col-md5">
                <div class="layui-card">
                    <div class="layui-card-header">二维码与海报</div>
                    <div class="layui-card-body share-media">
                        <div class="share-media-item">
                            <h4>二维码（SVG）</h4>
                            <img id="pvQrcode" alt="分享二维码">
                            <p id="pvQrcodeTip" class="share-hint"></p>
                        </div>
                        <div class="share-media-item">
                            <h4>分享海报（SVG）</h4>
                            <img id="pvPoster" alt="分享海报">
                            <p id="pvPosterTip" class="share-hint"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="layui-card">
            <div class="layui-card-header">渠道分享链接</div>
            <div class="layui-card-body"><table id="channelTable" lay-filter="channelTable"></table></div>
        </div>
    </div>
</div>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script src="{{ share_asset('js/share.js') }}" defer></script>
<script>
layui.use(['form', 'layer', 'table', 'jquery'], function () {
    var form = layui.form, layer = layui.layer, table = layui.table, $ = layui.jquery;
    var apiBase = '/api/admin/cmspro/share/preview';
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

    // 属性与文本统一转义，避免渠道名 / 链接中的引号破坏 DOM
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // 渠道表遵循《应用视图规范》7.5：弹性列 + skin:false + 显式 id + 禁用分页简写
    var channelCols = [[
        { field: 'name', title: '渠道', minWidth: 160 },
        { field: 'code', title: '标识', width: 140 },
        { field: 'region', title: '区域', width: 100 },
        { field: 'mode', title: '方式', width: 110 },
        { field: 'url', title: '最终分享链接', minWidth: 320 }
    ]];
    table.render({
        id: 'channelTable', elem: '#channelTable', cols: channelCols,
        skin: false, page: false, data: []
    });

    $.getJSON(apiBase + '/scenes', function (res) {
        var opts = (res.data || []).map(function (s) {
            return '<option value="' + esc(s.code) + '">' + esc(s.name)
                + (s.app_id ? ' · ' + esc(s.app_id) : '') + '</option>';
        }).join('');
        $('#sceneCode').html(opts);
        form.render('select');
    });

    // 按展现方式渲染面板，DOM 结构与 share_render() 的 layout 参数输出完全一致，交由前端 SDK 接管点击
    function renderPanel(d, layout) {
        var buttons = '';
        (d.channels || []).forEach(function (c) {
            buttons += '<button type="button" class="cmspro-share-btn" data-channel="' + esc(c.code)
                + '" data-mode="' + esc(c.mode) + '" data-href="' + esc(c.url)
                + '" title="' + esc(c.name) + '"><i class="' + esc(c.icon || 'fa fa-share-alt')
                + '"></i><span>' + esc(c.name) + '</span></button>';
        });

        var inner;
        if (layout === 'popup') {
            inner = '<button type="button" class="cmspro-share-trigger"><i class="fa fa-share-alt"></i><span>分享</span></button>'
                + '<div class="cmspro-share-popup-body" hidden>' + buttons + '</div>';
        } else {
            inner = buttons;
        }

        $('#sharePanel').html('<div class="cmspro-share" data-layout="' + esc(layout)
            + '" data-share-code="' + esc(d.code)
            + '" data-share-url="' + esc(d.url)
            + '" data-share-title="' + esc(d.title) + '">' + inner + '</div>');
    }

    var lastData = null;

    function render(d) {
        $('#previewResult').show();
        lastData = d;
        renderPanel(d, $('#layoutSelect').val() || 'default');

        $('#pvCode').text(d.code);
        $('#pvUrl').text(d.url);
        $('#pvTarget').text(d.target_url || '');
        $('#pvTitle').text(d.title || '');
        $('#pvSummary').text(d.summary || '');
        $('#pvScene').text((d.scene ? d.scene.name : '') + ' (' + (d.scene ? d.scene.code : '') + ')');
        $('#pvRedirect').attr('href', d.url);

        var flags = d.flags || {};
        if (flags.qrcode_enabled) {
            $('#pvQrcode').show().attr('src', '/api/cmspro/share/qrcode?data=' + encodeURIComponent(d.url) + '&size=240');
            $('#pvQrcodeTip').text('');
        } else {
            $('#pvQrcode').hide();
            $('#pvQrcodeTip').text('二维码未启用，可在「应用设置 → 渠道与高级能力」开启。');
        }
        if (flags.poster_enabled) {
            $('#pvPoster').show().attr('src', d.sdk ? d.sdk.poster_url : '');
            $('#pvPosterTip').text('');
        } else {
            $('#pvPoster').hide();
            $('#pvPosterTip').text('分享海报未启用，可在「应用设置 → 渠道与高级能力」开启 poster_enabled 后重新生成。');
        }

        table.reload('channelTable', { data: d.channels || [] });
    }

    function build() {
        layer.load(2);
        $.post(apiBase, {
            scene_code: $('#sceneCode').val(),
            target_id: $('input[name=target_id]').val()
        }, function (res) {
            layer.closeAll('loading');
            if (res.code !== 0) {
                layer.msg(res.message || '生成失败', { icon: 2 });
                return;
            }
            render(res.data);
        }, 'json');
    }

    $('#btnBuild').on('click', build);
    $('#previewForm').on('submit', function () { return false; });

    // 切换展现方式时用最近一次生成结果即时重渲染
    form.on('select(layoutSelect)', function () {
        if (lastData) { renderPanel(lastData, $('#layoutSelect').val() || 'default'); }
    });
});
</script>
</body>
</html>
