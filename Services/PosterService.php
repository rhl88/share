<?php

namespace App\Apps\CmsproShare\Services;

use App\Apps\CmsproShare\Models\ShareLink;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * 二维码与分享海报服务
 *
 * 使用 endroid/qr-code 的 SvgWriter 输出矢量图，海报亦为纯 SVG 合成，
 * 不依赖 GD / Imagick 扩展，也不写入磁盘（无运行时文件残留）。
 */
class PosterService
{
    /**
     * 生成二维码 SVG
     */
    public function qrcode(string $data, int $size = 300): string
    {
        if (!class_exists(QrCode::class) || $data === '') {
            return $this->placeholder('二维码组件不可用');
        }

        try {
            $qrCode = new QrCode($data, size: max(120, min(800, $size)), margin: 10);
            $result = (new SvgWriter())->write($qrCode, null, null, [
                SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true,
            ]);

            return $result->getString();
        } catch (\Throwable $e) {
            return $this->placeholder('二维码生成失败');
        }
    }

    /**
     * 生成分享海报 SVG
     *
     * @param string $code 分享短码
     */
    public function poster(string $code): string
    {
        $link = ShareLink::where('code', $code)->first();
        if ($link === null) {
            return $this->placeholder('分享链接不存在');
        }

        $path = trim(app(ShareConfig::class)->string('access_path', 'share'), '/');
        $shareUrl = url('/' . ($path !== '' ? $path . '/' : '') . $code);
        $qr = $this->qrcode($shareUrl, 320);
        $qrHref = 'data:image/svg+xml;base64,' . base64_encode($qr);

        $title = $this->xml((string) $link->title);
        $summary = $this->xml((string) $link->summary);
        $titleLines = mb_str_split($title, 16);
        $titleSvg = '';
        foreach (array_slice($titleLines, 0, 3) as $index => $line) {
            $y = 150 + $index * 44;
            $titleSvg .= '<text x="48" y="' . $y . '" font-size="34" font-weight="500" fill="#1F2937" font-family="sans-serif">' . $line . '</text>';
        }

        $summaryLines = mb_str_split($summary, 24);
        $summaryText = $summaryLines !== [] ? implode('', array_slice($summaryLines, 0, 2)) : '';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="600" height="900" viewBox="0 0 600 900">'
            . '<rect width="600" height="900" fill="#FFFFFF"/>'
            . '<rect width="600" height="12" fill="#185FA5"/>'
            . $titleSvg
            . '<text x="48" y="310" font-size="22" fill="#6B7280" font-family="sans-serif">' . $summaryText . '</text>'
            . '<rect x="140" y="360" width="320" height="320" rx="16" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="2"/>'
            . '<image href="' . $qrHref . '" xlink:href="' . $qrHref . '" x="150" y="370" width="300" height="300"/>'
            . '<text x="300" y="740" text-anchor="middle" font-size="24" fill="#1F2937" font-family="sans-serif">长按识别二维码，查看精彩内容</text>'
            . '<text x="300" y="790" text-anchor="middle" font-size="18" fill="#94A3B8" font-family="sans-serif">' . $this->xml($shareUrl) . '</text>'
            . '</svg>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function placeholder(string $message): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="120" viewBox="0 0 300 120">'
            . '<rect width="300" height="120" fill="#F1F5F9"/>'
            . '<text x="150" y="66" text-anchor="middle" font-size="16" fill="#64748B" font-family="sans-serif">'
            . $this->xml($message) . '</text></svg>';
    }
}
