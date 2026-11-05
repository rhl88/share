<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>我的分享 - 一键分享</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
</head>
<body>
<div class="pear-container">
    <div class="layui-card">
        <div class="layui-card-header">我的分享</div>
        <div class="layui-card-body">
            <table id="myShareTable" lay-filter="myShareTable"></table>
        </div>
    </div>
    <div class="layui-card">
        <div class="layui-card-header">我邀请的访客</div>
        <div class="layui-card-body">
            <table id="myInviteTable" lay-filter="myInviteTable"></table>
        </div>
    </div>
</div>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
layui.use(['table', 'jquery'], function () {
    var table = layui.table;
    var common = { page: true, limit: 10, parseData: function (res) {
        return { code: res.code, msg: res.message, count: res.data.pagination.total, data: res.data.items };
    } };

    table.render(Object.assign({ elem: '#myShareTable', url: '/api/user/cmspro/share/mine',
        cols: [[
            { field: 'code', title: '短码', width: 130 },
            { field: 'title', title: '分享内容', minWidth: 180 },
            { field: 'scene_code', title: '场景', width: 150 },
            { field: 'click_count', title: '点击', width: 90 },
            { field: 'visit_count', title: '落地', width: 90 },
            { field: 'uv_count', title: '访客', width: 90 },
            { field: 'convert_count', title: '转化', width: 90 },
            { field: 'create_time', title: '创建时间', width: 170 }
        ]] }, common));

    table.render(Object.assign({ elem: '#myInviteTable', url: '/api/user/cmspro/share/invites',
        cols: [[
            { field: 'link_code', title: '分享短码', width: 150 },
            { field: 'visitor_id', title: '访客用户ID', width: 130 },
            { field: 'level', title: '层级', width: 90 },
            { field: 'status', title: '状态', width: 90 },
            { field: 'create_time', title: '绑定时间', width: 170 }
        ]] }, common));
});
</script>
</body>
</html>
