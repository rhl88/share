<?php

namespace App\Apps\CmsproShare\Tests\Feature;

use App\Apps\CmsproShare\Services\ShareService;
use App\Apps\CmsproShare\Tests\ShareTestCase;

/**
 * F61 回归：前台公开 API 校验失败时必须返回 JSON 契约而非 302 重定向 HTML
 *
 * 对应常见问题第 1 章：`validate()` 失败 + `expectsJson()` 为假
 * → `ValidationException` 被转成 `redirect()`，普通 POST（无 Accept: application/json）
 *   拿到 302 重定向 HTML，接口契约被破坏。
 *
 * 框架统一契约（ApiResponse）：HTTP 200 + body.code 表达业务状态（422=校验失败）。
 * 因此断言核心为：Content-Type 是 JSON、body.code === 422，而非 302 HTML。
 * 前台 track/convert 的调用方（JS SDK fetch 分支、第三方宿主）不带 Accept JSON 头，
 * 因此这两个端点必须采用手动校验，保证任何请求形态下都返回 JSON。
 */
class TrackApiValidationTest extends ShareTestCase
{
    public function test_事件上报缺少参数时返回422JSON而非302重定向(): void
    {
        $response = $this->post('/api/cmspro/share/track', []);

        $this->assertJsonContract($response, 422);
    }

    public function test_事件上报非法事件类型返回422JSON(): void
    {
        $response = $this->post('/api/cmspro/share/track', [
            'code' => str_repeat('a', 32),
            'event_type' => 'evil',
        ]);

        $this->assertJsonContract($response, 422);
    }

    public function test_转化上报缺少参数时返回422JSON而非302重定向(): void
    {
        $response = $this->post('/api/cmspro/share/convert', []);

        $this->assertJsonContract($response, 422);
    }

    public function test_转化上报非法参数返回422JSON(): void
    {
        $response = $this->post('/api/cmspro/share/convert', [
            'code' => 'not-exist-code',
            'event' => str_repeat('x', 65),
        ]);

        $this->assertJsonContract($response, 422);
    }

    public function test_转化上报合法参数仍正常返回JSON(): void
    {
        $this->registerScene();
        $code = app(ShareService::class)->build('blog.article', 1)['data']['code'];

        $response = $this->post('/api/cmspro/share/convert', [
            'code' => $code,
            'event' => 'register',
        ]);

        $this->assertJsonContract($response, 0);
    }

    public function test_事件上报合法参数仍正常返回JSON(): void
    {
        $this->registerScene();
        $code = app(ShareService::class)->build('blog.article', 1)['data']['code'];

        $response = $this->post('/api/cmspro/share/track', [
            'code' => $code,
            'event_type' => 'click',
            'channel_code' => 'weibo',
        ]);

        $this->assertJsonContract($response, 0);
    }

    /**
     * 断言响应符合框架 JSON 契约：Content-Type 为 JSON 且 body.code 等于期望业务码
     */
    private function assertJsonContract($response, int $expectedCode): void
    {
        $contentType = (string) $response->headers->get('Content-Type');
        $this->assertStringContainsString('application/json', $contentType, '响应必须是 JSON 而非重定向 HTML');

        $body = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($body, '响应体必须是合法 JSON');
        $this->assertSame($expectedCode, (int) ($body['code'] ?? -1));
        $this->assertArrayHasKey('message', $body);
    }
}
