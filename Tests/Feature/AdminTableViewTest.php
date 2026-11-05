<?php

namespace App\Apps\CmsproShare\Tests\Feature;

use App\Apps\CmsproShare\Tests\ShareTestCase;

/**
 * 列表页表格对齐规范回归测试
 *
 * 依据《应用视图规范》7.5 节：以下三点叠加会导致 Layui 表头与数据列错位
 * ① 所有列固定 width、缺少弹性列；② 缺少 skin: false；③ page: true 简写。
 * 另依据《应用开发常见问题》第 13 章：分页参数名必须与后端实际读取的一致，
 * 本应用 links / mine / invites 接口读 limit，不得误套框架的 per_page 约定。
 */
class AdminTableViewTest extends ShareTestCase
{
    /**
     * 视图名 => [表格数量, 是否需要分页配置]
     */
    private function tableViews(): array
    {
        return [
            'cmspro.share::Admin.channels.index' => [1, true],
            'cmspro.share::Admin.scenes.index' => [1, true],
            'cmspro.share::Admin.links.index' => [1, true],
            'cmspro.share::Admin.stats.index' => [3, false],
            'cmspro.share::Admin.preview.index' => [1, false],
            'cmspro.share::User.shares.index' => [2, true],
        ];
    }

    private function renderView(string $name): string
    {
        // 列表页含 csrf_token()，渲染前需准备会话
        $this->withSession(['_token' => 'test-csrf-token']);

        $data = $name === 'cmspro.share::Admin.stats.index' ? ['defaultDays' => 30] : [];

        return view($name, $data)->render();
    }

    public function test_all_table_views_render_without_error(): void
    {
        foreach (array_keys($this->tableViews()) as $name) {
            $html = $this->renderView($name);
            $this->assertStringContainsString('layui.use(', $html, $name . ' 应正常渲染出 Layui 初始化脚本');
            $this->assertStringContainsString('table.render(', $html, $name . ' 应包含表格渲染');
        }
    }

    public function test_every_table_uses_skin_false_to_unify_header_and_body_box_model(): void
    {
        foreach ($this->tableViews() as $name => [$tableCount, $paginated]) {
            $html = $this->renderView($name);
            // 用户端两个表格共享 common 配置，因此按「至少出现一次」断言；
            // 看板三个表格各自独立配置，必须出现三次。
            $expected = $name === 'cmspro.share::Admin.stats.index' ? 3 : 1;
            $this->assertGreaterThanOrEqual(
                $expected,
                substr_count($html, 'skin: false'),
                $name . ' 必须使用 skin: false 统一 th/td 盒模型'
            );
        }
    }

    public function test_no_page_true_shorthand(): void
    {
        foreach (array_keys($this->tableViews()) as $name) {
            $html = $this->renderView($name);
            $this->assertStringNotContainsString(
                'page: true',
                $html,
                $name . ' 不得使用 page: true 简写（影响表格总宽度计算）'
            );
        }
    }

    public function test_paginated_tables_use_full_page_config(): void
    {
        foreach ($this->tableViews() as $name => [$tableCount, $paginated]) {
            if (!$paginated) {
                continue;
            }
            $html = $this->renderView($name);
            $this->assertStringContainsString('layout:', $html, $name . ' 需要完整 page.layout 配置');
            $this->assertStringContainsString('limits:', $html, $name . ' 需要完整 page.limits 配置');
        }
    }

    public function test_every_table_keeps_at_least_one_elastic_column(): void
    {
        foreach ($this->tableViews() as $name => [$tableCount, $paginated]) {
            $html = $this->renderView($name);
            $this->assertGreaterThanOrEqual(
                1,
                substr_count($html, 'minWidth'),
                $name . ' 至少需要一列不设 width（用 minWidth 作弹性列）'
            );
        }
    }

    public function test_every_table_declares_explicit_id_for_reload(): void
    {
        foreach ($this->tableViews() as $name => [$tableCount, $paginated]) {
            $html = $this->renderView($name);
            $this->assertStringContainsString("id: '", $html, $name . ' 应显式声明表格 id 供 reload 定位');
        }
    }

    public function test_stats_tables_render_once_and_reload_on_query(): void
    {
        $html = $this->renderView('cmspro.share::Admin.stats.index');

        // 看板每次查询只应更新数据，重复 render 会叠加多套表头容器
        $this->assertSame(3, substr_count($html, 'table.render('), '看板三张表应各初始化一次');
        $this->assertSame(3, substr_count($html, 'table.reload('), '看板三张表应通过 reload 更新数据');
    }

    public function test_pagination_parameter_stays_limit(): void
    {
        foreach ([
            'cmspro.share::Admin.links.index',
            'cmspro.share::User.shares.index',
        ] as $name) {
            $html = $this->renderView($name);
            // 仅拦截配置用法（limitName 映射 / 字符串参数名），注释中的说明文字不算
            $this->assertStringNotContainsString(
                'limitName',
                $html,
                $name . ' 对应后端读取 limit，不得声明 limitName 映射'
            );
            $this->assertStringNotContainsString(
                "'per_page'",
                $html,
                $name . ' 对应后端读取 limit，不得把分页参数名改成 per_page'
            );
        }
    }
}
