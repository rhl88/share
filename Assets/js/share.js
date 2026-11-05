/**
 * 一键分享前端 SDK
 *
 * 两种接入方式：
 * 1. 服务端直出：业务模板调用 {!! share_render('类型', $id) !!}，本脚本接管交互
 * 2. 纯前端：放置 <div class="cmspro-share" data-share-widget="/api/cmspro/share/widget/短码"></div>
 *
 * 依赖：无（原生 JS）。微信 JS-SDK 需业务页面自行引入微信 JSSDK 并把配置挂到
 * window.cmsproShareSdk.wechat，SDK 会自动调用分享接口；未配置时降级为引导提示。
 */
(function () {
    'use strict';

    var TRACK_URL = '/api/cmspro/share/track';

    function post(url, payload) {
        try {
            var body = JSON.stringify(payload);
            if (navigator.sendBeacon) {
                navigator.sendBeacon(url, new Blob([body], { type: 'application/json' }));
            } else {
                fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true });
            }
        } catch (e) { /* 上报失败不影响分享主流程 */ }
    }

    function toast(message) {
        var el = document.createElement('div');
        el.className = 'cmspro-share-toast';
        el.textContent = message;
        document.body.appendChild(el);
        setTimeout(function () { el.classList.add('is-visible'); }, 10);
        setTimeout(function () {
            el.classList.remove('is-visible');
            setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 300);
        }, 2600);
    }

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function () { toast('链接已复制，去粘贴分享吧'); }, function () { fallbackCopy(text); });
        } else {
            fallbackCopy(text);
        }
    }

    function fallbackCopy(text) {
        var input = document.createElement('textarea');
        input.value = text;
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.select();
        try { document.execCommand('copy'); toast('链接已复制，去粘贴分享吧'); }
        catch (e) { toast('复制失败，请长按链接手动复制'); }
        document.body.removeChild(input);
    }

    function showQrcode(holder, href) {
        var url = '/api/cmspro/share/qrcode?data=' + encodeURIComponent(href);
        var mask = document.createElement('div');
        mask.className = 'cmspro-share-mask';
        var panel = document.createElement('div');
        panel.className = 'cmspro-share-panel';
        var img = document.createElement('img');
        img.src = url;
        img.alt = '分享二维码';
        var tip = document.createElement('p');
        tip.textContent = '扫码打开分享内容';
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'cmspro-share-close';
        close.textContent = '关闭';
        close.onclick = function () { document.body.removeChild(mask); };
        panel.appendChild(img); panel.appendChild(tip); panel.appendChild(close);
        mask.appendChild(panel);
        mask.onclick = function (e) { if (e.target === mask) { document.body.removeChild(mask); } };
        document.body.appendChild(mask);
    }

    function wechatShare(holder, button) {
        var holderSdk = null;
        try { holderSdk = JSON.parse(holder.getAttribute('data-sdks') || 'null'); } catch (e) { /* 忽略 */ }
        var sdk = window.cmsproShareSdk || {};
        var conf = sdk.wechat || holderSdk || {};
        var ua = navigator.userAgent.toLowerCase();
        var inWechat = ua.indexOf('micromessenger') >= 0;

        if (inWechat && conf.configured && window.wx) {
            var data = {
                title: holder.getAttribute('data-share-title') || document.title,
                link: holder.getAttribute('data-share-url') || location.href,
                imgUrl: conf.imgUrl || '',
                desc: conf.desc || ''
            };
            try {
                if (window.wx.updateAppMessageShareData) { window.wx.updateAppMessageShareData(data); }
                if (window.wx.updateTimelineShareData) {
                    window.wx.updateTimelineShareData({ title: data.title, link: data.link, imgUrl: data.imgUrl });
                }
            } catch (e) { /* SDK 未就绪时降级为引导 */ }
            toast('已设置分享内容，请点右上角菜单分享');
            return;
        }

        if (inWechat) {
            toast(conf.guide || '请点击右上角菜单，选择「发送给朋友」或「分享到朋友圈」');
            return;
        }

        copyText(holder.getAttribute('data-share-url') || location.href);
    }

    function handleClick(button) {
        var holder = button.closest('.cmspro-share');
        if (!holder) { return; }
        var code = holder.getAttribute('data-share-code');
        var channel = button.getAttribute('data-channel');
        var mode = button.getAttribute('data-mode');
        var href = button.getAttribute('data-href') || holder.getAttribute('data-share-url');

        if (code) {
            post(TRACK_URL, { code: code, event_type: 'click', channel_code: channel });
        }

        switch (mode) {
            case 'link':
                window.open(href, '_blank', 'noopener,noreferrer');
                break;
            case 'copy':
                copyText(href);
                break;
            case 'qrcode':
                showQrcode(holder, href);
                break;
            case 'poster':
                window.open('/api/cmspro/share/poster/' + encodeURIComponent(code), '_blank', 'noopener,noreferrer');
                break;
            case 'native':
                if (navigator.share) {
                    navigator.share({
                        title: holder.getAttribute('data-share-title') || document.title,
                        text: holder.getAttribute('data-share-title') || document.title,
                        url: href
                    }).catch(function () { /* 用户取消 */ });
                } else {
                    copyText(href);
                }
                break;
            case 'wechat_sdk':
                wechatShare(holder, button);
                break;
            default:
                window.open(href, '_blank', 'noopener,noreferrer');
        }
    }

    function renderButtons(holder, channels) {
        holder.innerHTML = '';
        channels.forEach(function (channel) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'cmspro-share-btn';
            btn.setAttribute('data-channel', channel.code);
            btn.setAttribute('data-mode', channel.mode);
            btn.setAttribute('data-href', channel.url);
            btn.title = channel.name;
            var icon = document.createElement('i');
            icon.className = channel.icon || 'fa fa-share-alt';
            var label = document.createElement('span');
            label.textContent = channel.name;
            btn.appendChild(icon);
            btn.appendChild(label);
            holder.appendChild(btn);
        });
    }

    function initWidget(holder) {
        var endpoint = holder.getAttribute('data-share-widget');
        if (!endpoint) { return; }
        fetch(endpoint, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var data = res.data || {};
                if (!data.code) { return; }
                holder.setAttribute('data-share-code', data.code);
                holder.setAttribute('data-share-url', data.url);
                holder.setAttribute('data-share-title', data.title || '');
                holder.setAttribute('data-sdks', JSON.stringify({ wechat: data.sdk ? data.sdk.wechat : {}, guide: data.sdk ? data.sdk.wechat_guide : '' }));
                renderButtons(holder, data.channels || []);
            })
            .catch(function () { /* 组件数据加载失败时保持容器为空 */ });
    }

    document.addEventListener('click', function (e) {
        var button = e.target.closest ? e.target.closest('.cmspro-share-btn') : null;
        if (button) {
            e.preventDefault();
            handleClick(button);
        }
    });

    function boot() {
        var holders = document.querySelectorAll('.cmspro-share[data-share-widget]');
        for (var i = 0; i < holders.length; i++) { initWidget(holders[i]); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
