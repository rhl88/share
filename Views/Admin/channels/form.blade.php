{{-- 渠道编辑表单片段：由列表页 layer.open(type: 1) 取 DOM 加载 --}}
<div id="channelForm" class="share-form" style="display:none;">
    <form class="layui-form" lay-filter="channelForm" style="height:100%;">
        <div class="share-form-body">
            <div class="layui-form-item">
                <label class="layui-form-label">渠道标识</label>
                <div class="layui-input-block">
                    <input type="text" name="code" class="layui-input" readonly>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">显示名称</label>
                <div class="layui-input-block">
                    <input type="text" name="name" maxlength="100" class="layui-input">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">排序</label>
                <div class="layui-input-block">
                    <input type="number" name="sort" min="0" max="9999" class="layui-input">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">状态</label>
                <div class="layui-input-block">
                    <select name="status">
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
                    </select>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">图标类名</label>
                <div class="layui-input-block">
                    <input type="text" name="icon" maxlength="100" class="layui-input">
                </div>
            </div>
            <div class="layui-form-item layui-form-text">
                <label class="layui-form-label">链接模板</label>
                <div class="layui-input-block">
                    <textarea name="template" rows="4" class="layui-textarea" placeholder="支持 {url} {title} {summary} {image} 占位符"></textarea>
                </div>
            </div>
        </div>
        <div class="share-form-footer">
            <button type="submit" class="layui-btn" lay-submit lay-filter="saveChannel">保存</button>
            <button type="button" class="layui-btn layui-btn-primary" id="btnCancelChannel">取消</button>
        </div>
    </form>
</div>
<style>
    .share-form { height: 100%; }
    .share-form-body { position: absolute; top: 0; left: 0; right: 0; bottom: 56px; overflow-y: auto; padding: 20px 20px 0 0; }
    .share-form-footer { position: absolute; left: 0; right: 0; bottom: 0; height: 56px; padding: 10px 20px; text-align: right; border-top: 1px solid #eee; background: #fff; }
</style>
