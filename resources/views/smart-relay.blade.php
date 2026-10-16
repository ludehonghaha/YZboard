<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>智能落地分流 · YZboard</title>
<style>
:root{color-scheme:dark;font-family:system-ui,-apple-system,Segoe UI,sans-serif}
*{box-sizing:border-box}body{background:#0b1019;color:#edf2fc;margin:0}
main{max-width:820px;margin:4vh auto;padding:24px}
a{color:#a2c2ff;text-decoration:none}
header{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:25px}
h1{font-size:25px;margin:0}p,.hint{color:#a5aec3;font-size:14px;line-height:1.6}
section{border:1px solid #283347;background:#151d2b;border-radius:18px;padding:24px;box-shadow:0 8px 24px #0003}
label{display:block;font-size:13px;margin-bottom:8px;color:#c4cde0}
select,textarea{width:100%;background:#0e1623;border:1px solid #36445d;border-radius:10px;color:#f5f7fe;padding:11px 12px;font:inherit}
textarea{min-height:160px;resize:vertical;line-height:1.6}
select:focus,textarea:focus{outline:2px solid #5c88ee}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.field{margin:18px 0}.switch{display:flex;align-items:center;gap:9px}
pre{white-space:pre-wrap;word-break:break-word;color:#c6d5fa;background:#0e1623;padding:16px;border-radius:10px;font-size:13px}
button{background:#4b7fe6;color:white;border:0;border-radius:10px;padding:12px 22px;font:inherit;cursor:pointer}
button:disabled{opacity:.45;cursor:not-allowed}
#message{min-height:28px;margin-top:15px;font-size:14px}
#message.error{color:#ff9c9c}
#message.success{color:#8fe9c3}
@media(max-width:620px){.grid{grid-template-columns:1fr}main{padding:15px}section{padding:17px}}
</style>
<script defer src="/assets/admin/smart-relay.js"></script>
</head>
<body data-secure-path="{{ $secure_path }}">
<main>
<header><div><h1>智能落地分流</h1><p>一个 VLESS / HY2 入口 · AI 使用 A，其他使用 B</p></div><a href="/{{ $secure_path }}">返回面板</a></header>
<section>
<div class="field"><label for="entry">入口节点（须有两个已启用落地）</label><select id="entry"><option value="">正在加载…</option></select></div>
<div class="field switch"><input type="checkbox" id="enabled"><label for="enabled" style="margin:0">启用智能分流</label></div>
<div class="grid">
<div class="field"><label for="ai">AI 专用落地 A</label><select id="ai"></select></div>
<div class="field"><label for="fallback">默认落地 B</label><select id="fallback"></select></div>
</div>
<div class="field"><label for="domains">AI 域名（每行一个后缀）</label><textarea id="domains" spellcheck="false"></textarea><p class="hint">不包含 http://、路径或端口。仅匹配入口收到的域名；仅收到目标 IP 时无法识别 AI 域名，会走 B。</p></div>
<label>配置预览（只作用于入口自身身份）</label>
<pre id="preview">正在读取…</pre>
<button id="save" disabled>保存并下发</button>
<p id="message" role="status" aria-live="polite"></p>
<p class="hint">安全限制：两条落地必须属于同一个入口，且不能相同；保留私有地址拦截。没有部署匹配版本 YZ-Agent 的节点不会执行智能分流。</p>
</section>
</main>
</body>
</html>
