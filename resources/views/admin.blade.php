<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $title }}</title>
  <script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
    window.settings = {
      base_url: "/",
      title: "{{ $title }}",
      version: "{{ $version }}",
      logo: "{{ $logo }}",
      secure_path: "{{ $secure_path }}",
    };
  </script>
  @php
    $manifestPath = public_path('assets/admin/manifest.json');
    $manifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null;
    $entry = is_array($manifest) ? ($manifest['index.html'] ?? null) : null;
    $scripts = [];
    $styles = [];
    $locales = [];

    if (is_array($entry)) {
      $visited = [];
      $collectAssets = function ($chunkName) use (&$collectAssets, &$manifest, &$visited, &$scripts, &$styles) {
        if (isset($visited[$chunkName]) || !isset($manifest[$chunkName]) || !is_array($manifest[$chunkName])) {
          return;
        }

        $visited[$chunkName] = true;
        $chunk = $manifest[$chunkName];

        if (!empty($chunk['css']) && is_array($chunk['css'])) {
          foreach ($chunk['css'] as $cssFile) {
            $styles[$cssFile] = $cssFile;
          }
        }

        if (!empty($chunk['imports']) && is_array($chunk['imports'])) {
          foreach ($chunk['imports'] as $import) {
            $collectAssets($import);
          }
        }

        if (!empty($chunk['isEntry']) && !empty($chunk['file'])) {
          $scripts[$chunk['file']] = $chunk['file'];
        }
      };

      $collectAssets('index.html');
    }

    foreach (glob(public_path('assets/admin/locales/*.js')) ?: [] as $localeFile) {
      $locales[] = 'locales/' . basename($localeFile) . '?v=' . hash_file('sha256', $localeFile);
    }
    sort($locales);
  @endphp

  @if($entry && count($scripts) > 0)
    @foreach($styles as $css)
      <link rel="stylesheet" crossorigin href="/assets/admin/{{ $css }}" />
    @endforeach
    @foreach($locales as $locale)
      <script src="/assets/admin/{{ $locale }}"></script>
    @endforeach
    @foreach($scripts as $js)
      <script type="module" crossorigin src="/assets/admin/{{ $js }}"></script>
    @endforeach
  @else
    {{-- Fallback: hardcoded paths for backward compatibility --}}
    <script type="module" crossorigin src="/assets/admin/assets/index.js"></script>
    <link rel="stylesheet" crossorigin href="/assets/admin/assets/index.css" />
    <link rel="stylesheet" crossorigin href="/assets/admin/assets/vendor.css">
    <script src="/assets/admin/locales/en-US.js"></script>
    <script src="/assets/admin/locales/zh-CN.js"></script>
    <script src="/assets/admin/locales/ko-KR.js"></script>
  @endif
  <script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
    // 在管理端初始化前统一固定上游语言包中的节点程序名称。
    for (const locale of Object.values(window.XBOARD_TRANSLATIONS || {})) {
      for (const section of Object.values(locale?.machine || {})) {
        if (!section || typeof section !== 'object') continue;
        for (const [key, text] of Object.entries(section)) {
          if (typeof text === 'string') {
            section[key] = text.replaceAll('xboard-node', 'YZ-Agent');
          }
        }
      }
    }
  </script>
  <link rel="stylesheet" href="/assets/admin/quick-import.css?v=1"/>
  <script defer src="/assets/admin/quick-import.js?v=1"></script>
</head>

<body>
  <a href="/{{ $secure_path }}/smart-relay"
   title="智能落地分流设置"
   style="position:fixed;right:16px;bottom:68px;z-index:35;display:inline-flex;align-items:center;gap:6px;
     padding:9px 13px;background:#1b293d;color:#d7e8ff;text-decoration:none;border:1px solid #3a526f;
     box-shadow:0 4px 12px #0004;border-radius:999px;font:13px system-ui,sans-serif">智能分流 ↗</a>
  <button id="yz-quick-launch" type="button" title="直接导入 Shadowrocket / Clash">一键订阅</button>
  <div id="yz-quick-overlay" hidden role="presentation">
    <section id="yz-quick-panel" role="dialog" aria-modal="true" aria-labelledby="yz-quick-title">
      <div class="yz-quick-header">
        <div><h2 id="yz-quick-title">一键导入订阅</h2>
        <p>在 YZboard 直接选择用户并导入客户端，无需跳转到中间页面。</p></div>
        <button id="yz-quick-close" aria-label="关闭" type="button">×</button>
      </div>
      <label for="yz-quick-user">选择测试用户</label>
      <select id="yz-quick-user"><option value="">正在加载…</option></select>
      <div id="yz-quick-result" hidden>
        <div class="yz-quick-grid">
          <a id="yz-quick-open-shadow" class="yz-quick-primary" href="#">导入 Shadowrocket</a>
          <a id="yz-quick-open-clash" class="yz-quick-secondary" href="#">导入 Clash Verge</a>
        </div>
        <p>在 iPhone 上登录本后台后点击小火箭按钮；在电脑上可用二维码扫描，再于手机打开导入页。</p>
        <label for="yz-quick-universal">通用订阅地址</label>
        <div class="yz-quick-row"><input id="yz-quick-universal" type="text" readonly/>
          <button type="button" class="yz-quick-copy" data-yz-copy="yz-quick-universal">复制</button></div>
        <label for="yz-quick-mihomo">OpenClash / Mihomo</label>
        <div class="yz-quick-row"><input id="yz-quick-mihomo" type="text" readonly/>
          <button type="button" class="yz-quick-copy" data-yz-copy="yz-quick-mihomo">复制</button></div>
        <label for="yz-quick-shadow">Shadowrocket 专用地址</label>
        <div class="yz-quick-row"><input id="yz-quick-shadow" type="text" readonly/>
          <button type="button" class="yz-quick-copy" data-yz-copy="yz-quick-shadow">复制</button></div>
        <img id="yz-quick-qr" alt="手机扫描后打开导入页的二维码"/>
        <p style="text-align:center">在电脑打开管理后台时，用手机扫描上方二维码。</p>
      </div>
      <div id="yz-quick-status" role="status" aria-live="polite"></div>
      <p>订阅地址具有账号访问权限，请勿将按钮弹窗或二维码截图公开。</p>
    </section>
  </div>
  <div id="root"></div>
</body>

</html>
