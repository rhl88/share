<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>分享记录 - 一键分享</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
<style>
    /* 详情弹层：分组两列小表格，左列字段名右列值 */
    .share-detail-table { width: 100%; margin: 0; table-layout: fixed; }
    .share-detail-table td { padding: 7px 12px; font-size: 13px; word-break: break-all; }
    .share-detail-table .detail-label { width: 96px; color: #666; background: #fafafa; }
    .share-detail-table .detail-group td { color: #16b777; font-weight: 600; background: #f8f8f8; padding: 6px 12px; }
    .share-detail-table a { color: #01aaed; }
</style>
</head>
<body>
<div class="pear-container">
    <div class="layui-card">
        <div class="layui-card-body">
            <form class="layui-form" lay-filter="searchForm">
                <div class="layui-inline"><input type="text" name="keyword" placeholder="短码 / 标题 / 对象ID" class="layui-input"></div>
                <div class="layui-inline"><input type="text" name="scene_code" placeholder="场景标识" class="layui-input"></div>
                <div class="layui-inline"><button type="button" class="layui-btn" id="btnSearch"><i class="fa fa-search"></i> 查询</button></div>
            </form>
        </div>
    </div>
    <div class="layui-card">
        <div class="layui-card-body">
            <table id="linkTable" lay-filter="linkTable"></table>
        </div>
    </div>
</div>

@verbatim
<script type="text/html" id="linkStatusTpl">
    {{# if(d.status === 1){ }}
    <span class="layui-badge layui-bg-green">正常</span>
    {{# } else { }}
    <span class="layui-badge layui-bg-gray">已停用</span>
    {{# } }}
</script>
<script type="text/html" id="linkActionTpl">
    <button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="detail">详情</button>
    <button class="layui-btn layui-btn-xs {{ d.status === 1 ? 'layui-btn-danger' : 'layui-btn-normal' }}" lay-event="toggle">{{ d.status === 1 ? '停用' : '启用' }}</button>
</script>
@endverbatim

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
layui.use(['table', 'layer', 'jquery'], function () {
    var table = layui.table, layer = layui.layer, $ = layui.jquery;
    var apiBase = '/api/admin/cmspro/share/links';
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

    // 表格列定义：title 不设 width 作为弹性列，避免表头与数据列错位
    var cols = [[
        { field: 'id', title: 'ID', width: 80 },
        { field: 'code', title: '短码', width: 130 },
        { field: 'title', title: '分享标题', minWidth: 180 },
        { field: 'scene_code', title: '场景', width: 150 },
        { field: 'channel_code', title: '渠道', width: 110 },
        { field: 'sharer_id', title: '分享者', width: 100 },
        { field: 'click_count', title: '点击', width: 80 },
        { field: 'visit_count', title: '落地', width: 80 },
        { field: 'uv_count', title: 'UV', width: 80 },
        { field: 'convert_count', title: '转化', width: 80 },
        { field: 'create_time', title: '创建时间', width: 170 },
        { field: 'status', title: '状态', width: 100, templet: '#linkStatusTpl' },
        { title: '操作', width: 140, fixed: 'right', templet: '#linkActionTpl' }
    ]];

    table.render({
        id: 'linkTable',
        elem: '#linkTable',
        url: apiBase,
        // 本应用 links 接口读取 limit（非框架 per_page 约定），保持 Layui 默认参数名
        page: {
            layout: ['count', 'prev', 'page', 'next', 'limit'],
            groups: 5,
            limit: 20,
            limits: [10, 20, 50, 100]
        },
        cols: cols,
        skin: false,
        parseData: function (res) {
            return {
                code: res.code === 0 ? 0 : 1,
                msg: res.message || '',
                count: res.data && res.data.pagination ? res.data.pagination.total : 0,
                data: res.data && res.data.items ? res.data.items : []
            };
        }
    });

    // 详情弹层：把接口数据渲染为分组可读表格（不得直接输出原始 JSON 文本）
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function detailRow(label, value) {
        return '<tr><td class="detail-label">' + label + '</td><td>' + (value == null || value === '' ? '-' : value) + '</td></tr>';
    }

    function detailGroup(title) {
        return '<tr class="detail-group"><td colspan="2">' + title + '</td></tr>';
    }

    function detailLink(url) {
        if (!url) { return '-'; }
        return '<a href="' + esc(url) + '" target="_blank" rel="noopener">' + esc(url) + '</a>';
    }

    function sharerTypeName(code) {
        return { guest: '游客', user: '前台用户', admin: '管理员' }[code] || esc(code || '-');
    }

    function formatParams(raw) {
        if (!raw) { return '无'; }
        try {
            var parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
            var parts = [];
            Object.keys(parsed).forEach(function (k) { parts.push(esc(k) + '：' + esc(parsed[k])); });
            return parts.length ? parts.join('<br>') : '无';
        } catch (e) {
            return esc(raw);
        }
    }

    function shareDetailHtml(d) {
        var html = '<table class="layui-table share-detail-table" lay-size="sm" lay-skin="line"><tbody>';

        html += detailGroup('基本信息');
        html += detailRow('短码', esc(d.code));
        html += detailRow('状态', d.status === 1
            ? '<span class="layui-badge layui-bg-green">正常</span>'
            : '<span class="layui-badge layui-bg-gray">已停用</span>');
        html += detailRow('分享标题', esc(d.title));
        html += detailRow('摘要', esc(d.summary));
        html += detailRow('场景', esc(d.scene_code));
        html += detailRow('对象 ID', esc(d.target_id));
        html += detailRow('目标链接', detailLink(d.target_url));
        html += detailRow('生成渠道', esc(d.channel_code) || '-');
        html += detailRow('分享者', sharerTypeName(d.sharer_type) + (d.sharer_id != null ? '（ID：' + esc(d.sharer_id) + '）' : ''));
        html += detailRow('上级分享码', esc(d.parent_code));

        html += detailGroup('数据统计');
        html += detailRow('分享点击', d.click_count);
        html += detailRow('落地访问', d.visit_count);
        html += detailRow('独立访客', d.uv_count);
        html += detailRow('转化次数', d.convert_count);

        html += detailGroup('时间与扩展');
        html += detailRow('创建时间', esc(d.create_time));
        html += detailRow('更新时间', esc(d.update_time));
        html += detailRow('失效时间', d.expire_time ? esc(d.expire_time) : '长期有效');
        html += detailRow('扩展参数', formatParams(d.params));

        return html + '</tbody></table>';
    }

    table.on('tool(linkTable)', function (obj) {
        if (obj.event === 'toggle') {
            $.post(apiBase + '/' + obj.data.id + '/toggle', {}, function () { table.reload('linkTable'); });
        } else if (obj.event === 'detail') {
            $.getJSON(apiBase + '/' + obj.data.id, function (res) {
                if (!res || res.code !== 0 || !res.data) {
                    layer.msg((res && res.message) || '详情获取失败', { icon: 2 });
                    return;
                }
                layer.open({
                    type: 1,
                    title: '分享详情 · ' + res.data.code,
                    area: ['680px', '560px'],
                    maxWidth: 680,
                    content: '<div style="padding:12px 4px 4px;">' + shareDetailHtml(res.data) + '</div>'
                });
            });
        }
    });

    $('#btnSearch').on('click', function () {
        table.reload('linkTable', {
            where: { keyword: $('input[name=keyword]').val(), scene_code: $('input[name=scene_code]').val() },
            page: { curr: 1 }
        });
    });
});
</script>
</body>
</html>
