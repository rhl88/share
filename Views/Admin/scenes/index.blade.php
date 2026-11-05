<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>场景管理 - 一键分享</title>
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
                分享场景由业务应用通过 <code>share.types</code> 钩子注册，本页只做启用状态与排序覆盖。新增业务类型无需修改本应用代码。
            </blockquote>
            <button type="button" class="layui-btn" id="btnSync"><i class="fa fa-refresh"></i> 同步已注册场景</button>
        </div>
    </div>
    <div class="layui-card">
        <div class="layui-card-body">
            <table id="sceneTable" lay-filter="sceneTable"></table>
        </div>
    </div>
</div>

@verbatim
<script type="text/html" id="sceneStatusTpl">
    {{# if(d.status === 1){ }}
    <span class="layui-badge layui-bg-green">启用</span>
    {{# } else { }}
    <span class="layui-badge layui-bg-gray">禁用</span>
    {{# } }}
</script>
<script type="text/html" id="sceneActionTpl">
    <button class="layui-btn layui-btn-xs {{ d.status === 1 ? 'layui-btn-danger' : 'layui-btn-normal' }}" lay-event="toggle">{{ d.status === 1 ? '停用' : '启用' }}</button>
</script>
@endverbatim

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
layui.use(['table', 'layer', 'jquery'], function () {
    var table = layui.table, layer = layui.layer, $ = layui.jquery;
    var apiBase = '/api/admin/cmspro/share/scenes';
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

    function load() {
        $.getJSON(apiBase, function (res) {
            table.reload('sceneTable', { data: res.data || [] });
        });
    }

    // 表格列定义：resolver 不设 width 作为弹性列，避免表头与数据列错位
    var cols = [[
        { field: 'code', title: '场景标识', width: 200 },
        { field: 'name', title: '场景名称', width: 150 },
        { field: 'app_id', title: '注册应用', width: 160 },
        { field: 'resolver', title: '元数据解析器', minWidth: 200 },
        { field: 'status', title: '状态', width: 90, templet: '#sceneStatusTpl' },
        { title: '操作', width: 110, fixed: 'right', templet: '#sceneActionTpl' }
    ]];

    table.render({
        id: 'sceneTable',
        elem: '#sceneTable',
        page: {
            layout: ['count', 'prev', 'page', 'next', 'limit'],
            groups: 5,
            limit: 20,
            limits: [10, 20, 50, 100]
        },
        cols: cols,
        skin: false,
        data: []
    });

    table.on('tool(sceneTable)', function (obj) {
        if (obj.event === 'toggle') {
            $.post(apiBase + '/save', { code: obj.data.code, status: obj.data.status === 1 ? 0 : 1 }, function () { load(); });
        }
    });

    $('#btnSync').on('click', function () {
        $.post(apiBase + '/sync', {}, function (res) { layer.msg(res.message || '已同步'); load(); });
    });

    load();
});
</script>
</body>
</html>
