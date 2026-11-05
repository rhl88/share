<?php

namespace App\Apps\CmsproShare\Tests\Unit;

use App\Apps\CmsproShare\Services\ChannelService;
use PHPUnit\Framework\TestCase;

/**
 * 渠道注册表单元测试（纯逻辑，不启动框架容器）
 */
class ChannelServiceTest extends TestCase
{
    private ChannelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChannelService();
    }

    public function test_内置渠道覆盖国内海外与通用方式(): void
    {
        $codes = array_column($this->service->defaults(), 'code');

        foreach (['wechat', 'moments', 'qq', 'qzone', 'weibo', 'facebook', 'x', 'whatsapp', 'telegram', 'linkedin'] as $code) {
            $this->assertContains($code, $codes, "缺少渠道 {$code}");
        }

        foreach (['copy', 'qrcode', 'poster', 'native'] as $code) {
            $this->assertContains($code, $codes, "缺少通用方式 {$code}");
        }
    }

    public function test_每个内置渠道定义结构完整(): void
    {
        foreach ($this->service->defaults() as $channel) {
            foreach (['code', 'name', 'region', 'mode', 'icon', 'sort'] as $field) {
                $this->assertArrayHasKey($field, $channel, "渠道 {$channel['code']} 缺少字段 {$field}");
            }
            $this->assertContains($channel['region'], ['domestic', 'overseas', 'common']);
        }
    }

    public function test_链接模板占位符正确替换(): void
    {
        $channel = [
            'code' => 'weibo',
            'template' => 'https://service.weibo.com/share/share.php?url={url}&title={title}&pic={image}',
        ];
        $context = [
            'url' => 'https://example.com/a/1?x=1&y=2',
            'title' => '测试 标题',
            'summary' => '摘要',
            'image' => 'https://example.com/c.jpg',
        ];

        $url = $this->service->buildUrl($channel, $context);

        $this->assertStringContainsString('url=' . rawurlencode($context['url']), $url);
        $this->assertStringContainsString('title=' . rawurlencode('测试 标题'), $url);
        $this->assertStringContainsString('pic=' . rawurlencode($context['image']), $url);
    }

    public function test_空模板降级返回源链接(): void
    {
        $url = $this->service->buildUrl(['code' => 'copy', 'template' => ''], ['url' => 'https://example.com/a']);

        $this->assertSame('https://example.com/a', $url);
    }

    public function test_未提供的占位符替换为空串(): void
    {
        $url = $this->service->buildUrl(
            ['code' => 'x', 'template' => 'https://x.com/?url={url}&title={title}'],
            ['url' => 'https://example.com']
        );

        $this->assertStringContainsString('title=', $url);
        $this->assertStringNotContainsString('{title}', $url);
    }
}
