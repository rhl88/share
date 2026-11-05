<?php

namespace App\Apps\CmsproShare\Tests\Feature;

use App\Apps\CmsproShare\Models\ShareLink;
use App\Apps\CmsproShare\Services\ChannelService;
use App\Apps\CmsproShare\Services\ShareService;
use App\Apps\CmsproShare\Services\StatService;
use App\Apps\CmsproShare\Services\TrackService;
use App\Apps\CmsproShare\Tests\ShareTestCase;
use App\Services\HookManager;
use Illuminate\Support\Facades\DB;

/**
 * 一键分享主流程测试：扩展契约、渠道、链接、跟踪、裂变、归因、报表
 */
class ShareFlowTest extends ShareTestCase
{
    public function test_业务应用注册场景后可生成分享链接并落库(): void
    {
        $this->registerScene();

        $result = app(ShareService::class)->build('blog.article', 12, ['sharer_id' => 7]);

        $this->assertTrue($result['success']);
        $data = $result['data'];
        $this->assertNotEmpty($data['code']);
        $this->assertSame('文章标题-12', $data['title']);
        $this->assertSame('https://example.com/article/12', $data['target_url']);
        $this->assertStringContainsString('/share/' . $data['code'], $data['url']);
        $this->assertNotEmpty($data['channels']);

        $this->assertDatabaseHas('app_cmspro_share_links', [
            'code' => $data['code'],
            'scene_code' => 'blog.article',
            'target_id' => '12',
            'sharer_id' => 7,
        ]);
        $this->assertDatabaseHas('app_cmspro_share_events', [
            'link_code' => $data['code'],
            'event_type' => 'create',
        ]);
    }

    public function test_渠道列表同时包含国内与海外渠道(): void
    {
        $this->registerScene();

        $data = app(ShareService::class)->build('blog.article', 1)['data'];
        $codes = array_column($data['channels'], 'code');

        $this->assertContains('wechat', $codes);
        $this->assertContains('facebook', $codes);
        $this->assertContains('copy', $codes);
    }

    public function test_自定义渠道可通过钩子追加(): void
    {
        $this->registerScene();
        app(HookManager::class)->registerFilter('share.channels', function ($channels) {
            $channels['my_app'] = [
                'code' => 'my_app',
                'name' => '我的渠道',
                'region' => 'common',
                'mode' => 'link',
                'icon' => 'fa fa-paper-plane',
                'sort' => 99,
                'template' => 'myapp://share?url={url}',
                'status' => 1,
            ];
            return $channels;
        }, 10, 'test.blog');

        $data = app(ShareService::class)->build('blog.article', 1)['data'];

        $this->assertContains('my_app', array_column($data['channels'], 'code'));
    }

    public function test_分享文案可由钩子改写(): void
    {
        $this->registerScene();
        app(HookManager::class)->registerFilter('share.context', function ($context) {
            $context['title'] = '【改写】' . $context['title'];
            return $context;
        }, 10, 'test.blog');

        $data = app(ShareService::class)->build('blog.article', 3)['data'];

        $this->assertSame('【改写】文章标题-3', $data['title']);
    }

    public function test_未注册场景返回明确错误(): void
    {
        $result = app(ShareService::class)->build('unknown.scene', 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('未注册', $result['message']);
        $this->assertNull($result['data']);
    }

    public function test_链接生成可挂载自定义短链钩子(): void
    {
        $this->registerScene();
        app(HookManager::class)->registerFilter('share.link.build', function ($url, $context) {
            return 'https://short.example.com/' . $context['code'];
        }, 10, 'test.blog');

        $data = app(ShareService::class)->build('blog.article', 1)['data'];

        $this->assertStringStartsWith('https://short.example.com/', $data['url']);
    }

    public function test_中转跳转记录落地事件并累加访问数(): void
    {
        $this->registerScene();
        $code = app(ShareService::class)->build('blog.article', 5)['data']['code'];

        $response = $this->get('/share/' . $code);

        $response->assertRedirect('https://example.com/article/5');
        $this->assertDatabaseHas('app_cmspro_share_events', ['link_code' => $code, 'event_type' => 'landing']);
        $this->assertSame(1, (int) ShareLink::where('code', $code)->value('visit_count'));
    }

    public function test_同一访客重复落地不重复计独立访客(): void
    {
        $this->registerScene();
        $code = app(ShareService::class)->build('blog.article', 5)['data']['code'];

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.8'])
            ->withHeader('User-Agent', 'ShareTest/1.0')
            ->get('/share/' . $code);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.8'])
            ->withHeader('User-Agent', 'ShareTest/1.0')
            ->get('/share/' . $code);

        $this->assertSame(2, (int) ShareLink::where('code', $code)->value('visit_count'));
        $this->assertSame(1, (int) ShareLink::where('code', $code)->value('uv_count'));
    }

    public function test_邀请关系绑定受后台开关控制(): void
    {
        $this->registerScene();
        $this->setShareConfig('invite_enabled', '0');
        $code = app(ShareService::class)->build('blog.article', 9, ['sharer_id' => 3])['data']['code'];

        $this->get('/share/' . $code);
        $this->assertDatabaseCount('app_cmspro_share_invites', 0);

        $this->setShareConfig('invite_enabled', '1');
        $this->get('/share/' . $code);
        $this->assertDatabaseHas('app_cmspro_share_invites', [
            'link_code' => $code,
            'inviter_id' => 3,
            'level' => 1,
        ]);
    }

    public function test_转化上报归因成功并触发奖励事件(): void
    {
        $this->registerScene();
        $this->setShareConfig('convert_enabled', '1');
        $this->setShareConfig('reward_event_enabled', '1');
        $code = app(ShareService::class)->build('blog.article', 11, ['sharer_id' => 4])['data']['code'];

        $called = false;
        app(HookManager::class)->registerAction('share.converted', function ($payload) use (&$called) {
            $called = true;
        }, 10, 'test.blog');

        $result = app(ShareService::class)->convert($code, 'register', ['user_id' => 88]);

        $this->assertTrue($result['success']);
        $this->assertTrue($called);
        $this->assertDatabaseHas('app_cmspro_share_events', [
            'link_code' => $code,
            'event_type' => 'convert',
        ]);
        $this->assertSame(1, (int) ShareLink::where('code', $code)->value('convert_count'));
    }

    public function test_未开启转化归因时上报被拒绝(): void
    {
        $this->registerScene();
        $this->setShareConfig('convert_enabled', '0');
        $code = app(ShareService::class)->build('blog.article', 1)['data']['code'];

        $result = app(ShareService::class)->convert($code, 'order');

        $this->assertFalse($result['success']);
        $this->assertDatabaseCount('app_cmspro_share_events', 1); // 仅 create 事件
    }

    public function test_报表漏斗按事件类型汇总(): void
    {
        $this->registerScene();
        $code = app(ShareService::class)->build('blog.article', 1)['data']['code'];
        app(TrackService::class)->record($code, 'click', null, ['channel_code' => 'weibo']);

        app(TrackService::class)->aggregate(now()->toDateString());
        $funnel = app(StatService::class)->funnel(now()->toDateString(), now()->toDateString());

        $this->assertSame(1, $funnel['create']);
        $this->assertSame(1, $funnel['click']);
    }

    public function test_日期字段输出符合规范格式(): void
    {
        $this->registerScene();
        $code = app(ShareService::class)->build('blog.article', 1)['data']['code'];

        $link = ShareLink::where('code', $code)->first();

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $link->create_time);
    }

    public function test_后台接口未登录被拦截(): void
    {
        $this->getJson('/api/admin/cmspro/share/channels')->assertUnauthorized();
    }

    public function test_二维码接口返回SVG内容(): void
    {
        $response = $this->get('/api/cmspro/share/qrcode?data=' . urlencode('https://example.com'));

        $response->assertOk();
        $this->assertStringContainsString('<svg', $response->getContent());
    }

    public function test_渠道种子初始化后可被后台停用(): void
    {
        $this->registerScene();
        app(ChannelService::class)->seedDefaults();

        $this->assertDatabaseHas('app_cmspro_share_channels', ['code' => 'weibo', 'status' => 1]);

        app(ChannelService::class)->saveOverride('weibo', ['status' => 0]);
        $channels = app(ChannelService::class)->all();

        $this->assertNotContains('weibo', array_column($channels, 'code'));
    }

    public function test_事件扩展参数可持久化(): void
    {
        $this->registerScene();
        $code = app(ShareService::class)->build('blog.article', 1)['data']['code'];

        app(TrackService::class)->record($code, 'click', null, ['channel_code' => 'x', 'utm_source' => 'campaign_a']);

        $extra = json_decode((string) DB::table('app_cmspro_share_events')
            ->where('link_code', $code)->where('event_type', 'click')->value('extra'), true);
        $this->assertSame('campaign_a', $extra['utm_source']);
    }
}
