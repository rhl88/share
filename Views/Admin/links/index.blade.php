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

    table.on('tool(linkTable)', function (obj) {
        if (obj.event === 'toggle') {
            $.post(apiBase + '/' + obj.data.id + '/toggle', {}, function () { table.reload('linkTable'); });
        } else if (obj.event === 'detail') {
            $.getJSON(apiBase + '/' + obj.data.id, function (res) {
                layer.open({ type: 1, title: '分享详情', area: ['600px', '460px'],
                    content: '<pre style="padding:16px;white-space:pre-wrap;">' + JSON.stringify(res.data, null, 2) + '</pre>' });
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
