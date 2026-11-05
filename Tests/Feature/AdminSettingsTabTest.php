<?php

namespace App\Apps\CmsproShare\Tests\Feature;

use App\Apps\CmsproShare\Tests\ShareTestCase;

/**
 * 应用设置页按配置分组 Tab 展示的回归测试
 *
 * 设置项来自 SettingController::apiList()，分组字段为 item.group，
 * 对应 manifest.json 的四组 config_groups（基础设置 / 渠道与高级能力 /
 * 裂变与归因 / 统计与报表）。Tab 只负责切换显示，所有字段必须仍在同一个
 * form 内，保存时整表收集，避免只提交当前面板导致其它分组配置丢失。
 */
class AdminSettingsTabTest extends ShareTestCase
{
    private function renderSettings(): string
    {
        $this->withSession(['_token' => 'test-csrf-token']);

        return view('cmspro.share::Admin.settings.index')->render();
    }

    public function test_settings_page_uses_layui_tab_container(): void
    {
        $html = $this->renderSettings();
        $this->assertStringContainsString('class="layui-tab layui-tab-brief"', $html);
        $this->assertStringContainsString('lay-filter="settingsTab"', $html);
        $this->assertStringContainsString('layui-tab-title', $html);
        $this->assertStringContainsString('layui-tab-content', $html);
    }

    public function test_element_module_loaded_and_dynamic_tabs_rendered(): void
    {
        $html = $this->renderSettings();
        $this->assertStringContainsString("'element'", $html, '需加载 Layui element 模块');
        $this->assertStringContainsString(
            "element.render('tab', 'settingsTab')",
            $html,
            '动态插入 Tab 后必须重新渲染才能切换'
        );
    }

    public function test_tabs_are_built_from_config_group(): void
    {
        $html = $this->renderSettings();
        $this->assertStringContainsString('item.group', $html, 'Tab 维度应取接口返回的配置分组');
        $this->assertStringContainsString('lay-id=', $html, '每个 Tab 需要稳定 id 供切换定位');
        $this->assertStringNotContainsString(
            'share-group-title',
            $html,
            '旧版纵向平铺的分组小标题应移除'
        );
    }

    public function test_all_tabs_share_one_form_and_save_collects_every_field(): void
    {
        $html = $this->renderSettings();
        $this->assertStringContainsString('layui-tab-item', $html);
        $this->assertStringContainsString('#settingsForm input[type=checkbox]', $html);
        $this->assertStringNotContainsString(
            '.layui-show input',
            $html,
            '保存不得只收集当前显示的 Tab 面板，否则其余分组设置会丢失'
        );
    }

    public function test_empty_config_list_has_fallback(): void
    {
        $html = $this->renderSettings();
        $this->assertStringContainsString('暂无可配置项', $html, '无配置项时要有兜底提示');
    }

    public function test_active_tab_restored_after_reload(): void
    {
        $html = $this->renderSettings();
        $this->assertStringContainsString('activeId', $html);
        $this->assertStringContainsString('tabChange', $html);
    }

    public function test_save_skips_layui_helper_inputs_without_name(): void
    {
        $html = $this->renderSettings();
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($html, 'if (this.name)'),
            'Layui 渲染 select/switch 会生成无 name 的辅助控件，保存时必须跳过'
        );
    }
}
