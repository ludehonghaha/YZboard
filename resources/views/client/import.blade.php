<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="referrer" content="no-referrer">
  <title>一键导入订阅 · {{ $remark }}</title>
  <style>
    :root { color-scheme: light dark; font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
    * { box-sizing:border-box; }
    body { margin:0; background:#f5f7fb; color:#182033; padding:32px 16px; }
    main { max-width:580px; margin:0 auto; }
    .top { text-align:center; padding:10px 0 20px; }
    h1 { font-size:26px; margin:8px 0; }
    h2 { font-size:16px; margin:4px 0 16px; }
    p { color:#667085; line-height:1.6; font-size:14px; }
    .card { background:white; border:1px solid #e5eaf1; border-radius:18px; padding:24px; box-shadow:0 6px 24px #161b2620; margin-bottom:16px; }
    .buttons { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .btn { display:flex; align-items:center; justify-content:center; background:#2563eb; border:1px solid #2563eb; border-radius:11px; padding:14px 11px; font-size:15px; color:#fff; text-decoration:none; font-weight:600; cursor:pointer; min-height:48px; text-align:center; }
    .btn.secondary { background:#f3f6fc; color:#1946a5; border-color:#d2dff8; }
    label { display:block; font-size:13px; color:#475467; margin-bottom:8px; }
    .copyrow { display:flex; gap:8px; }
    input { width:100%; min-width:0; padding:12px; border:1px solid #d0d7e3; border-radius:9px; color:#3c4657; background:#f8fafc; }
    button { font:inherit; }
    .copy { background:#222f45; color:white; border:0; border-radius:9px; padding:9px 13px; cursor:pointer; flex-shrink:0; }
    .tip { font-size:12px; margin-bottom:0; }
    .qr { text-align:center; }
    .qr img { width:180px; height:180px; background:#fff; padding:8px; border:1px solid #e5eaf1; border-radius:12px; }
    #feedback { min-height:20px; color:#0b8457; font-size:13px; margin-top:8px; }
    @media(max-width:420px){.buttons{grid-template-columns:1fr}.card{padding:18px}h1{font-size:24px}}
    @media(prefers-color-scheme:dark) {
      body{background:#0c1422;color:#eef3ff}
      .card{background:#151f30;border-color:#29384f}
      p,label{color:#a7b5cd}
      input{background:#111b2b;border-color:#364860;color:#e5efff}
      .btn.secondary{background:#20334d;color:#cce1ff;border-color:#3a5882}
      .qr img{border-color:#29384f}
    }
  </style>
</head>
<body>
<main>
  <div class="top">
    <h1>一键导入订阅</h1>
    <p>{{ $remark }} · 选择客户端即可自动添加订阅，之后更新节点无需再次导入。</p>
  </div>
  <section class="card">
    <h2>打开客户端导入</h2>
    <div class="buttons">
      <a class="btn" href="{{ $shadowrocketLink }}">一键导入 Shadowrocket</a>
      <a class="btn secondary" href="{{ $clashLink }}">一键导入 Clash Verge</a>
    </div>
    <p class="tip">小火箭请在 iPhone 的 Safari 中点击；Clash Verge 使用已安装客户端的设备打开。首次跳转需允许浏览器打开应用。</p>
  </section>
  <section class="card">
    <h2>复制订阅地址</h2>
    <label for="universal">通用订阅链接（支持自动识别客户端）</label>
    <div class="copyrow">
      <input id="universal" readonly value="{{ $subscriptionUrl }}" aria-label="通用订阅链接">
      <button class="copy" type="button" data-copy="universal">复制</button>
    </div>
    <label for="mihomo" style="margin-top:18px">Mihomo / OpenClash 专用</label>
    <div class="copyrow">
      <input id="mihomo" readonly value="{{ $mihomoUrl }}" aria-label="Mihomo 订阅链接">
      <button class="copy" type="button" data-copy="mihomo">复制</button>
    </div>
    <label for="shadow" style="margin-top:18px">Shadowrocket 专用</label>
    <div class="copyrow">
      <input id="shadow" readonly value="{{ $shadowUrl }}" aria-label="Shadowrocket 订阅链接">
      <button class="copy" type="button" data-copy="shadow">复制</button>
    </div>
    <div id="feedback" role="status" aria-live="polite"></div>
  </section>
  <section class="card qr">
    <h2>手机扫码打开导入页面</h2>
    <img src="data:image/svg+xml;base64,{{ $qrCode }}" alt="订阅导入页二维码">
    <p class="tip">二维码由面板本地生成，不发送到第三方。订阅链接相当于访问密钥，请勿公开分享。</p>
  </section>
</main>
<script>
document.querySelectorAll('[data-copy]').forEach(button => {
  button.addEventListener('click', async () => {
    const el = document.getElementById(button.dataset.copy);
    try {
      if(navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(el.value);
      } else {
        el.select();
        if(!document.execCommand('copy')) throw new Error('copy failed');
      }
      document.getElementById('feedback').textContent = '复制成功，可以直接粘贴到客户端。';
    } catch {
      el.focus(); el.select();
      document.getElementById('feedback').textContent = '无法自动复制，请长按输入框复制。';
    }
  });
});
</script>
</body>
</html>
