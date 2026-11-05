<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>数据看板 - 一键分享</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <style>
        .share-stat-grid { display: flex; flex-wrap: wrap; gap: 16px; }
        .share-stat-card { flex: 1 1 160px; border: 1px solid #e6e6e6; border-radius: 8px; padding: 18px; background: #fff; }
        .share-stat-card h3 { font-size: 13px; color: #666; font-weight: 400; margin-bottom: 8px; }
        .share-stat-card strong { font-size: 26px; color: #1f2937; }
    </style>
</head>
<body>
<div class="pear-container">
    <div class="layui-card">
        <div class="layui-card-body">
            <form class="layui-form" lay-filter="rangeForm">
                <div class="layui-inline"><input type="text" name="start" id="startDate" placeholder="开始日期" class="layui-input" readonly></div>
                <div class="layui-inline"><input type="text" name="end" id="endDate" placeholder="结束日期" class="layui-input" readonly></div>
                <div class="layui-inline"><button type="button" class="layui-btn" id="btnQuery"><i class="fa fa-search"></i> 查询</button></div>
            </form>
        </div>
    </div>

    <div class="layui-card">
        <div class="layui-card-body">
            <div class="share-stat-grid" id="statCards"></div>
        </div>
    </div>

    <div class="layui-row layui-col-space15">
        <div class="layui-col-md6">
            <div class="layui-card"><div class="layui-card-header">渠道效果</div>
                <div class="layui-card-body"><table id="channelStatTable"></table></div>
            </div>
        </div>
        <div class="layui-col-md6">
            <div class="layui-card"><div class="layui-card-header">场景效果</div>
                <div class="layui-card-body"><table id="sceneStatTable"></table></div>
            </div>
        </div>
    </div>

    <div class="layui-card">
        <div class="layui-card-header">热门分享链接</div>
        <div class="layui-card-body"><table id="topLinkTable"></table></div>
    </div>
</div>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
layui.use(['table', 'laydate', 'jquery'], function () {
    var table = layui.table, laydate = layui.laydate, $ = layui.jquery;
    var defaultDays = {{ (int) $defaultDays }};

    function dateStr(offset) {
        var d = new Date(); d.setDate(d.getDate() + offset);
        return d.toISOString().slice(0, 10);
    }

    $('#startDate').val(dateStr(-defaultDays + 1));
    $('#endDate').val(dateStr(0));
    laydate.render({ elem: '#startDate' });
    laydate.render({ elem: '#endDate' });

    // 三张报表各保留一列不设 width 作为弹性列，skin:false 统一表头与数据行盒模型
    var channelCols = [[
        { field: 'name', title: '渠道', minWidth: 140 },
        { field: 'click', title: '点击', width: 90 },
        { field: 'landing', title: '落地', width: 90 },
        { field: 'convert', title: '转化', width: 90 }
    ]];
    var sceneCols = [[
        { field: 'name', title: '场景', minWidth: 140 },
        { field: 'click', title: '点击', width: 90 },
        { field: 'landing', title: '落地', width: 90 },
        { field: 'convert', title: '转化', width: 90 }
    ]];
    var topLinkCols = [[
        { field: 'code', title: '短码', width: 130 },
        { field: 'title', title: '标题', minWidth: 200 },
        { field: 'scene_code', title: '场景', width: 150 },
        { field: 'visit_count', title: '落地', width: 90 },
        { field: 'convert_count', title: '转化', width: 90 },
        { field: 'create_time', title: '创建时间', width: 170 }
    ]];

    // 表格只初始化一次，后续查询用 reload 更新数据；重复 render 会叠加多套表头容器
    table.render({ id: 'channelStatTable', elem: '#channelStatTable', cols: channelCols, skin: false, page: false, data: [] });
    table.render({ id: 'sceneStatTable', elem: '#sceneStatTable', cols: sceneCols, skin: false, page: false, data: [] });
    table.render({ id: 'topLinkTable', elem: '#topLinkTable', cols: topLinkCols, skin: false, page: false, data: [] });

    function load() {
        $.getJSON('/api/admin/cmspro/share/stats', { start: $('#startDate').val(), end: $('#endDate').val() }, function (res) {
            var o = res.data.overview;
            var cards = [
                ['分享链接数', o.link_count], ['渠道点击', o.click], ['落地访问', o.landing],
                ['独立访客', o.uv], ['邀请关系', o.invite_count], ['转化次数', o.convert],
                ['点击率', o.click_rate + '%'], ['转化率', o.convert_rate + '%']
            ];
            $('#statCards').html(cards.map(function (c) {
                return '<div class="share-stat-card"><h3>' + c[0] + '</h3><strong>' + c[1] + '</strong></div>';
            }).join(''));

            table.reload('channelStatTable', { data: res.data.by_channel || [] });
            table.reload('sceneStatTable', { data: res.data.by_scene || [] });
            table.reload('topLinkTable', { data: res.data.top_links || [] });
        });
    }

    $('#btnQuery').on('click', load);
    load();
});
</script>
</body>
</html>
