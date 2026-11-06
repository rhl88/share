<?php

namespace App\Apps\CmsproShare\Tests\Feature;

use App\Apps\CmsproShare\Tests\ShareTestCase;
use Illuminate\Support\Facades\DB;

/**
 * 后台「分享预览」功能回归测试
 *
 * 预览按用户确认采用「完整真实生成」：调用 ShareService::build() 生成真实
 * 短链，因此每次预览都会写入一条分享记录 + 一条 create 事件，会计入数据看板。
 * 内置演示场景用于没有任何业务应用注册 share.types 时也能预览。
 */
class AdminPreviewTest extends ShareTestCase
{
    private const DEMO_SCENE = 'cmspro.share.preview_demo';
    private const API = '/api/admin/cmspro/share/preview';

    private function renderPage(): string
    {
        $this->withSession(['_token' => 'test-csrf-token']);

        return view('cmspro.share::Admin.preview.index')->render();
    }

    public function test_manifest_declares_preview_menu(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(base_path('app/Apps/CmsproShare/manifest.json')),
            true
        );

        $menu = null;
        foreach ($manifest['menus'][0]['children'] ?? [] as $item) {
            if (($item['code'] ?? '') === 'admin_cmspro_share_preview') {
                $menu = $item;
                break;
            }
        }

        $this->assertNotNull($menu, 'manifest 需声明「分享预览」后台子菜单');
        $this->assertSame('分享预览', $menu['title']);
        $this->assertSame('/admin/cmspro/share/preview', $menu['path']);
    }

    public function test_admin_preview_routes_require_authentication(): void
    {
        $this->getJson(self::API . '/scenes')->assertUnauthorized();
        $this->postJson(self::API, ['scene_code' => self::DEMO_SCENE, 'target_id' => '1'])
            ->assertUnauthorized();
    }

    public function test_preview_page_renders_real_sdk_assets_and_notice(): void
    {
        $html = $this->renderPage();

        $this->assertStringContainsString('cmspro-share', $html);
        $this->assertStringContainsString('apps/cmspro.share/css/share.css', $html);
        $this->assertStringContainsString('apps/cmspro.share/js/share.js', $html);
        $this->assertStringNotContainsString(
            'apps/cmspro/share/',
            $html,
            '资源必须按 app_id 发布目录引用，旧路径会返回 HTML 错误页导致样式与 SDK 全部失效'
        );
        $this->assertStringContainsString('真实分享记录', $html, '需明确告知预览会写入真实数据');
    }

    public function test_share_asset_points_to_published_app_directory(): void
    {
        $css = share_asset('css/share.css');
        $js = share_asset('js/share.js');

        $this->assertStringContainsString('apps/cmspro.share/css/share.css', $css);
        $this->assertStringContainsString('apps/cmspro.share/js/share.js', $js);
        $this->assertStringNotContainsString('apps/cmspro/share/', $css);
        $this->assertStringNotContainsString('apps/cmspro/share/', $js);

        // 组件源文件必须存在；public/apps/{appId}/ 由框架安装时发布，不纳入应用测试的环境依赖
        $this->assertFileExists(base_path('app/Apps/CmsproShare/Assets/css/share.css'));
        $this->assertFileExists(base_path('app/Apps/CmsproShare/Assets/js/share.js'));
    }

    public function test_share_render_buttons_carry_channel_icons(): void
    {
        $this->withoutMiddleware();
        $this->registerScene('blog.article');

        $html = share_render('blog.article', 7);

        $this->assertStringContainsString('class="cmspro-share-btn', $html);
        $this->assertStringContainsString('data-channel="wechat"', $html);
        $this->assertMatchesRegularExpression(
            '/<i class="fa fa-[a-z-]+"/',
            $html,
            '服务端直出的按钮必须带渠道图标类名'
        );
    }

    public function test_preview_build_returns_channel_icons_and_styles_define_brand_colors(): void
    {
        $this->withoutMiddleware();

        $response = $this->postJson(self::API, [
            'scene_code' => self::DEMO_SCENE,
            'target_id' => '1',
        ]);

        $response->assertOk()->assertJson(['code' => 0]);
        $channels = $response->json('data.channels');
        $this->assertNotEmpty($channels);
        foreach ($channels as $channel) {
            $this->assertStringStartsWith('fa fa-', (string) ($channel['icon'] ?? ''), '每个渠道需返回图标类名');
        }

        $html = $this->renderPage();
        $this->assertStringContainsString("c.icon || 'fa fa-share-alt'", $html, '预览面板需渲染 <i> 图标并保留兜底图标');

        $css = (string) file_get_contents(base_path('app/Apps/CmsproShare/Assets/css/share.css'));
        $this->assertStringContainsString('.cmspro-share-btn i', $css, '组件样式需定义按钮图标');
        $this->assertStringContainsString('[data-channel="wechat"] i', $css, '按钮图标需按渠道着色，便于辨识');
    }

    public function test_wechat_pc_fallback_offers_scan_qrcode_and_copy_text(): void
    {
        // PC（非微信环境）点击「微信好友 / 朋友圈」必须弹出扫码分享层，
        // 本地电脑未登录微信时也能用手机扫码打开中转链接完成分享（F-微信弹层）
        $js = (string) file_get_contents(base_path('app/Apps/CmsproShare/Assets/js/share.js'));
        $css = (string) file_get_contents(base_path('app/Apps/CmsproShare/Assets/css/share.css'));

        $this->assertStringContainsString('wechatScanPanel', $js, 'PC 端微信渠道需弹出扫码分享层');
        $this->assertStringContainsString('/api/cmspro/share/qrcode', $js, '弹层二维码需复用公开二维码端点');
        $this->assertStringContainsString('复制文字', $js, '弹层需提供复制文字（标题+链接）能力');
        $this->assertStringContainsString('复制链接', $js, '弹层需提供复制链接能力');

        $this->assertStringContainsString('.cmspro-share-wechat-title', $css, '弹层需有标题样式');
        $this->assertStringContainsString('.cmspro-share-copy-btn', $css, '弹层需有复制按钮样式');
    }

    public function test_preview_page_offers_layout_switcher(): void
    {
        $html = $this->renderPage();

        $this->assertStringContainsString('layoutSelect', $html, '预览页需提供展现方式选择器');
        $this->assertStringContainsString('纯图标', $html, '需支持纯图标布局预览');
        $this->assertStringContainsString('纯文字', $html, '需支持纯文字布局预览');
        $this->assertStringContainsString('点击弹出', $html, '需支持点击弹出面板布局预览');
    }

    public function test_scene_list_includes_demo_and_registered_scenes(): void
    {
        $this->withoutMiddleware();
        $this->registerScene('blog.article');

        $response = $this->getJson(self::API . '/scenes');

        $response->assertOk()->assertJson(['code' => 0]);
        $items = $response->json('data');
        $this->assertSame(self::DEMO_SCENE, $items[0]['code'], '演示场景应作为默认项');
        $this->assertContains('blog.article', array_column($items, 'code'));
    }

    public function test_build_creates_real_share_link_and_create_event(): void
    {
        $this->withoutMiddleware();

        $response = $this->postJson(self::API, [
            'scene_code' => self::DEMO_SCENE,
            'target_id' => '1',
        ]);

        $response->assertOk()->assertJson(['code' => 0]);
        $data = $response->json('data');
        $this->assertNotEmpty($data['code']);
        $this->assertNotEmpty($data['url']);
        $this->assertNotEmpty($data['channels']);
        $this->assertSame(self::DEMO_SCENE, $data['scene']['code']);

        $this->assertSame(1, DB::table('app_cmspro_share_links')->count());
        $this->assertSame(
            self::DEMO_SCENE,
            DB::table('app_cmspro_share_links')->value('scene_code')
        );
        $this->assertSame('preview', DB::table('app_cmspro_share_links')->value('sharer_type'));
        $this->assertSame(1, DB::table('app_cmspro_share_events')->where('event_type', 'create')->count());
    }

    public function test_build_uses_registered_scene_context(): void
    {
        $this->withoutMiddleware();
        $this->registerScene('blog.article');

        $response = $this->postJson(self::API, [
            'scene_code' => 'blog.article',
            'target_id' => '42',
        ]);

        $response->assertOk()->assertJson(['code' => 0]);
        $this->assertSame('文章标题-42', $response->json('data.title'));
    }

    public function test_build_rejects_unknown_scene_without_writing_data(): void
    {
        $this->withoutMiddleware();

        $response = $this->postJson(self::API, [
            'scene_code' => 'not.registered',
            'target_id' => '1',
        ]);

        $response->assertOk();
        $this->assertNotSame(0, $response->json('code'), '未注册场景必须返回错误');
        $this->assertSame(0, DB::table('app_cmspro_share_links')->count());
    }

    public function test_build_validates_required_input(): void
    {
        $this->withoutMiddleware();

        $response = $this->postJson(self::API, []);

        $response->assertStatus(422);
        $this->assertSame(0, DB::table('app_cmspro_share_links')->count());
    }
}
