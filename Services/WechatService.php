<?php

namespace App\Apps\CmsproShare\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * 微信 JS-SDK 签名服务
 *
 * 未启用或未配置公众号密钥时返回 configured=false，前端自动降级为「点右上角分享」引导，
 * 不会因外部接口不可用而阻断分享功能（对应开发文档第二十章第 19 条）。
 */
class WechatService
{
    public function __construct(private ShareConfig $config)
    {
    }

    public function isConfigured(): bool
    {
        return $this->config->bool('wechat_sdk_enabled', false)
            && $this->config->string('wechat_app_id') !== ''
            && $this->config->string('wechat_app_secret') !== '';
    }

    /**
     * 生成微信 JS-SDK 配置
     *
     * @param string $url 当前页面完整 URL（必须与签名时浏览器地址一致，不含 # 及其后部分）
     * @return array<string,mixed>
     */
    public function signature(string $url): array
    {
        if (!$this->isConfigured()) {
            return ['configured' => false];
        }

        $ticket = $this->ticket();
        if ($ticket === null || $ticket === '') {
            return ['configured' => false, 'reason' => 'jsapi_ticket 获取失败'];
        }

        $nonce = Str::random(16);
        $timestamp = time();
        $raw = 'jsapi_ticket=' . $ticket
            . '&noncestr=' . $nonce
            . '&timestamp=' . $timestamp
            . '&url=' . $url;

        return [
            'configured' => true,
            'appId' => $this->config->string('wechat_app_id'),
            'timestamp' => $timestamp,
            'nonceStr' => $nonce,
            'signature' => sha1($raw),
            'url' => $url,
        ];
    }

    /**
     * 获取并缓存 jsapi_ticket（有效期 7200 秒，提前 300 秒刷新）
     */
    private function ticket(): ?string
    {
        $appId = $this->config->string('wechat_app_id');
        $secret = $this->config->string('wechat_app_secret');
        $cacheKey = 'cmspro.share.wechat.ticket.' . md5($appId);

        return Cache::remember($cacheKey, 6900, function () use ($appId, $secret) {
            try {
                $tokenResponse = Http::timeout(5)->get('https://api.weixin.qq.com/cgi-bin/token', [
                    'grant_type' => 'client_credential',
                    'appid' => $appId,
                    'secret' => $secret,
                ]);

                $accessToken = $tokenResponse->json('access_token');
                if (!is_string($accessToken) || $accessToken === '') {
                    return null;
                }

                $ticketResponse = Http::timeout(5)->get('https://api.weixin.qq.com/cgi-bin/ticket/getticket', [
                    'access_token' => $accessToken,
                    'type' => 'jsapi',
                ]);

                $ticket = $ticketResponse->json('ticket');
                return is_string($ticket) && $ticket !== '' ? $ticket : null;
            } catch (\Throwable $e) {
                return null;
            }
        });
    }
}
