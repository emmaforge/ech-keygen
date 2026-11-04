<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ECH 密钥生成器</title>
<style>
    body { font-family: -apple-system, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif; background: #f5f6f8; margin: 0; padding: 24px 12px; color: #222; }
    .card { max-width: 760px; margin: 0 auto; background: #fff; border-radius: 10px; padding: 24px 28px 28px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
    h1 { font-size: 20px; margin: 0 0 6px; }
    .sub { color: #666; font-size: 13px; margin-bottom: 18px; line-height: 1.6; }
    label { display: block; font-size: 14px; font-weight: 600; margin: 16px 0 6px; }
    .hint { font-weight: 400; color: #888; font-size: 12px; }
    input[type=text], input[type=password] { width: 100%; box-sizing: border-box; padding: 9px 10px; border: 1px solid #d5d8de; border-radius: 6px; font-size: 14px; }
    textarea { width: 100%; box-sizing: border-box; border: 1px solid #d5d8de; border-radius: 6px; font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12px; padding: 10px; background: #fafbfc; }
    button { margin-top: 14px; background: #2563eb; color: #fff; border: 0; border-radius: 6px; padding: 9px 18px; font-size: 14px; cursor: pointer; }
    button.copy { background: #eef1f5; color: #333; padding: 5px 12px; font-size: 12px; margin: 6px 0 2px; }
    .error { background: #fdecec; color: #a11; border: 1px solid #f3c2c2; border-radius: 6px; padding: 10px 12px; font-size: 14px; margin-bottom: 14px; }
    .warn { background: #fff7e0; color: #7a5b00; border: 1px solid #eed88a; border-radius: 6px; padding: 10px 12px; font-size: 14px; margin-bottom: 14px; line-height: 1.6; }
    .ok { background: #ecfdf0; color: #14532d; border: 1px solid #b7e4c7; border-radius: 6px; padding: 10px 12px; font-size: 14px; margin: 18px 0 4px; line-height: 1.6; }
</style>
</head>
<body>
<div class="card">
    <h1>ECH 密钥生成器</h1>
    <div class="sub">面板自带的「自动生成 ECH 密钥对」固定使用 <code>ech.example.com</code> 作为 public_name，这里可以自己指定。生成算法与面板自带接口完全一致，生成的两段分别贴回节点的「ECH 配置 (PEM)」和「ECH Key」即可。</div>

    @if($error)
        <div class="error">{{ $error }}</div>
    @endif

    @if(!$configured)
        <div class="warn">插件还没有设置访问密码，生成页面暂不可用。请先到管理后台「插件管理 → ECH 密钥生成器」的设置里填写「页面访问密码」，保存后刷新本页。</div>
    @elseif(!$authed)
        <form method="POST" action="/ech-keygen">
            @csrf
            <label for="access_key">页面访问密码</label>
            <input type="password" id="access_key" name="access_key" autocomplete="current-password" required>
            <button type="submit">进入</button>
        </form>
    @else
        <form method="POST" action="/ech-keygen">
            @csrf
            <label for="public_name">public_name <span class="hint">一般填这个节点的外层 TLS SNI / 伪装域名，例如节点的 TLS 域名</span></label>
            <input type="text" id="public_name" name="public_name" value="{{ $publicName }}" placeholder="example.com" required>
            <button type="submit">生成密钥对</button>
        </form>

        @if($result)
            <div class="ok">已生成（public_name：{{ $publicName }}）。两段必须配套使用：先贴配置再贴 Key，节点保存后客户端更新一次订阅。</div>

            <label>ECH 配置 (PEM) <span class="hint">贴到节点设置的「ECH 配置 (PEM)」</span></label>
            <textarea id="ech_config" rows="6" readonly>{{ $result['config'] }}</textarea>
            <button type="button" class="copy" data-copy="ech_config">复制配置</button>

            <label>ECH Key <span class="hint">贴到节点设置的「ECH Key」；含私钥，不要外发</span></label>
            <textarea id="ech_key" rows="6" readonly>{{ $result['key'] }}</textarea>
            <button type="button" class="copy" data-copy="ech_key">复制 Key</button>
        @endif
    @endif
</div>
<script>
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var el = document.getElementById(btn.getAttribute('data-copy'));
            el.select();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(el.value);
            } else {
                document.execCommand('copy');
            }
            btn.textContent = '已复制';
            setTimeout(function () { btn.textContent = btn.getAttribute('data-copy') === 'ech_key' ? '复制 Key' : '复制配置'; }, 1500);
        });
    });
</script>
</body>
</html>
