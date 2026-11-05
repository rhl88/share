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
        $this->assertStringContainsString('apps/cmspro/share/css/share.css', $html);
        $this->assertStringContainsString('apps/cmspro/share/js/share.js', $html);
        $this->assertStringContainsString('真实分享记录', $html, '需明确告知预览会写入真实数据');
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
