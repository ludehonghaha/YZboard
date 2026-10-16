(() => {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const secure = document.body.dataset.securePath;
  const base = '/api/v2/' + encodeURIComponent(secure) + '/server/smart-relay';
  const saved = localStorage.getItem('XBOARD_ACCESS_TOKEN');
  let token = '';
  try { token = JSON.parse(saved || '{}').value || ''; } catch {}
  if (!token) {
    $('message').textContent = '请先在 YZboard 管理后台登录，再打开此页面。';
    $('message').className = 'error';
    return;
  }
  const defaults = ['openai.com','chatgpt.com','oaistatic.com','oaiusercontent.com',
    'anthropic.com','claude.ai','claude.com','gemini.google.com','generativelanguage.googleapis.com'];
  let entries = [];
  const msg = (s, error = false) => {
    $('message').textContent = s;
    $('message').className = error ? 'error' : 'success';
  };
  const api = async (path, options = {}) => {
    const r = await fetch(base + path, {
      ...options,
      headers: {'Authorization': token, 'Accept':'application/json', 'Content-Type':'application/json'}
    });
    let body;
    try { body = await r.json(); } catch { throw new Error('面板返回非 JSON 响应'); }
    if (!r.ok) throw new Error(body.message || Object.values(body.errors || {})[0]?.[0] || 'HTTP ' + r.status);
    return body.data;
  };
  const option = (id, label) => {
    const o = document.createElement('option');
    o.value = String(id); o.textContent = label; return o;
  };
  const chosen = () => entries.find((e) => String(e.id) === $('entry').value);
  function preview() {
    const selected = chosen();
    const ai = $('ai').selectedOptions[0]?.textContent || '未选择';
    const fallback = $('fallback').selectedOptions[0]?.textContent || '未选择';
    const lines = $('domains').value.split(/\r?\n/).map((x) => x.trim()).filter(Boolean);
    $('preview').textContent = selected
      ? '入口：' + selected.name + '\nAI 域名 → ' + ai +
        '\n其余流量 → ' + fallback + '\n域名规则：' + lines.length + ' 条\n' +
        ($('enabled').checked ? '保存后启用' : '当前停用')
      : '请先在节点管理中创建 VLESS/HY2 入口与两条落地';
  }
  function render() {
    const entry = chosen();
    for (const id of ['ai','fallback']) {
      const select = $(id);
      select.replaceChildren();
      if (entry) for (const child of entry.children) select.append(option(child.id, child.name));
    }
    const settings = entry?.smart_relay;
    $('ai').value = String(settings?.ai_node_id || entry?.children[0]?.id || '');
    $('fallback').value = String(settings?.default_node_id || entry?.children[1]?.id || '');
    $('enabled').checked = !!settings?.enabled;
    $('domains').value = (settings?.domains?.length ? settings.domains : defaults).join('\n');
    $('save').disabled = !entry || entry.children.length < 2;
    preview();
  }
  for (const id of ['ai','fallback','enabled','domains']) $(id).addEventListener('input', preview);
  $('entry').addEventListener('change', render);
  $('save').addEventListener('click', async () => {
    const entry = chosen();
    if (!entry) return;
    $('save').disabled = true;
    try {
      await api('/save', {method:'POST',body:JSON.stringify({
        entry_id: entry.id,
        enabled: $('enabled').checked,
        ai_node_id: Number($('ai').value),
        default_node_id: Number($('fallback').value),
        domains: $('domains').value.split(/\r?\n/).map(s => s.trim()).filter(Boolean)
      })});
      msg('已保存配置。在线且兼容的 YZ-Agent 会收到更新；请再实测出口 IP。');
      const data = await api('/fetch'); entries = data.entries || [];
      render();
    } catch (e) { msg(e.message, true); }
    finally { $('save').disabled = false; }
  });
  api('/fetch').then((data) => {
    entries = data.entries || [];
    $('entry').replaceChildren(option('', entries.length ? '请选择入口' : '暂无可用入口'));
    for (const entry of entries) $('entry').append(option(entry.id, entry.name));
    if (entries.length) $('entry').value = String(entries[0].id);
    render();
    if (entries.length === 0) msg('尚未创建可用 VLESS/HY2 入口和两条落地。', true);
  }).catch((e) => msg('读取失败：' + e.message, true));
})();
