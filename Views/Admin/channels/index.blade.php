<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>渠道管理 - 一键分享</title>
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
                <div class="layui-form-item">
                    <div class="layui-inline">
                        <input type="text" name="keyword" placeholder="渠道标识 / 名称" class="layui-input">
                    </div>
                    <div class="layui-inline">
                        <button type="button" class="layui-btn" id="btnSearch"><i class="fa fa-search"></i> 查询</button>
                        <button type="button" class="layui-btn layui-btn-primary" id="btnSeed"><i class="fa fa-refresh"></i> 同步内置渠道</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="layui-card">
        <div class="layui-card-body">
            <table id="channelTable" lay-filter="channelTable"></table>
        </div>
    </div>
</div>

@verbatim
<script type="text/html" id="statusTpl">
    {{# if(d.status === 1){ }}
    <span class="layui-badge layui-bg-green">启用</span>
    {{# } else { }}
    <span class="layui-badge layui-bg-gray">禁用</span>
    {{# } }}
</script>
<script type="text/html" id="sourceTpl">
    {{# if(d.source === '钩子注册'){ }}
    <span class="layui-badge layui-bg-blue">{{ d.source }}</span>
    {{# } else { }}
    <span>{{ d.source }}</span>
    {{# } }}
</script>
<script type="text/html" id="actionTpl">
    <button class="layui-btn layui-btn-xs" lay-event="edit">编辑</button>
    <button class="layui-btn layui-btn-xs {{ d.status === 1 ? 'layui-btn-danger' : 'layui-btn-normal' }}" lay-event="toggle">{{ d.status === 1 ? '停用' : '启用' }}</button>
</script>
@endverbatim

@include('cmspro.share::Admin.channels.form')

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
layui.use(['table', 'form', 'layer', 'jquery'], function () {
    var table = layui.table, form = layui.form, layer = layui.layer, $ = layui.jquery;
    var apiBase = '/api/admin/cmspro/share/channels';
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

    var allRows = [];
    function load() {
        $.getJSON(apiBase, function (res) {
            allRows = res.data || [];
            table.reload('channelTable', { data: allRows });
        });
    }

    // 表格列定义：至少保留一列不设 width 作为弹性列，避免表头与数据列错位
    var cols = [[
        { field: 'code', title: '渠道标识', width: 150 },
        { field: 'name', title: '名称', minWidth: 150 },
        { field: 'region', title: '区域', width: 100 },
        { field: 'mode', title: '方式', width: 110 },
        { field: 'sort', title: '排序', width: 90, sort: true },
        { field: 'status', title: '状态', width: 90, templet: '#statusTpl' },
        { field: 'source', title: '来源', width: 110, templet: '#sourceTpl' },
        { title: '操作', width: 150, fixed: 'right', templet: '#actionTpl' }
    ]];

    table.render({
        id: 'channelTable',
        elem: '#channelTable',
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

    table.on('tool(channelTable)', function (obj) {
        var row = obj.data;
        if (obj.event === 'edit') {
            form.val('channelForm', {
                code: row.code, name: row.name, sort: row.sort,
                status: row.status, template: row.template, icon: row.icon
            });
            layer.open({
                type: 1, title: '编辑渠道 - ' + row.name, area: ['640px', '520px'],
                content: $('#channelForm'), success: function () { $('#channelForm').show(); }
            });
        } else if (obj.event === 'toggle') {
            $.post(apiBase + '/save', { code: row.code, status: row.status === 1 ? 0 : 1 }, function () {
                load();
            });
        }
    });

    $('#btnSearch').on('click', function () {
        var kw = $('input[name=keyword]').val().toLowerCase();
        table.reload('channelTable', {
            data: allRows.filter(function (r) {
                return !kw || (r.code + r.name).toLowerCase().indexOf(kw) >= 0;
            })
        });
    });

    $('#btnSeed').on('click', function () {
        $.post(apiBase + '/seed', {}, function (res) {
            layer.msg(res.message || '已同步');
            load();
        });
    });

    form.on('submit(saveChannel)', function (data) {
        $.post(apiBase + '/save', data.field, function (res) {
            layer.msg(res.message || '已保存');
            layer.closeAll('page');
            load();
        });
        return false;
    });

    $('#btnCancelChannel').on('click', function () { layer.closeAll('page'); });

    load();
});
</script>
</body>
</html>
