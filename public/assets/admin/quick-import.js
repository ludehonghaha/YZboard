(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  const prefix = '/api/v2/' + encodeURIComponent(window.settings.secure_path) + '/quick-import';
  let selected = null;
  let pending = 0;

  function adminToken() {
    try {
      return JSON.parse(localStorage.getItem('XBOARD_ACCESS_TOKEN') || '{}').value || '';
    } catch { return ''; }
  }

  async function request(path, payload) {
    const token = adminToken();
    if (!token) throw new Error('请先登录 YZboard 管理后台');
    const response = await fetch(prefix + path, {
      method: payload === undefined ? 'GET' : 'POST',
      cache: 'no-store',
      credentials: 'same-origin',
      headers: {
        'Authorization': token,
        'Accept': 'application/json',
        ...(payload === undefined ? {} : {'Content-Type': 'application/json'})
      },
      ...(payload === undefined ? {} : {body: JSON.stringify(payload)})
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok || body.status !== 'success') {
      throw new Error(body.message || '操作失败（HTTP ' + response.status + '）');
    }
    return body.data;
  }

  function status(message) { $('yz-quick-status').textContent = message; }
  function clear() {
    selected = null;
    $('yz-quick-result').hidden = true;
    $('yz-quick-qr').removeAttribute('src');
    $('yz-quick-universal').value = '';
    $('yz-quick-mihomo').value = '';
    $('yz-quick-shadow').value = '';
    $('yz-quick-open-shadow').removeAttribute('href');
    $('yz-quick-open-clash').removeAttribute('href');
  }
  async function fetchLinks() {
    const userId = Number($('yz-quick-user').value);
    const generation = ++pending;
    clear();
    if (!userId) return;
    status('正在生成该用户的导入配置…');
    try {
      const data = await request('/links', {user_id: userId});
      if (generation !== pending || $('yz-quick-overlay').hidden) return;
      selected = data;
      $('yz-quick-open-shadow').href = data.shadowrocket_import;
      $('yz-quick-open-clash').href = data.clash_import;
      $('yz-quick-universal').value = data.subscription_url;
      $('yz-quick-shadow').value = data.shadowrocket_url;
      $('yz-quick-mihomo').value = data.mihomo_url;
      $('yz-quick-qr').src = data.mobile_qr;
      $('yz-quick-result').hidden = false;
      status('准备完成，直接点击客户端按钮即可导入。');
    } catch (error) {
      if (generation === pending) status(error.message);
    }
  }
  async function open() {
    $('yz-quick-overlay').hidden = false;
    clear();
    status('正在加载测试用户…');
    $('yz-quick-user').replaceChildren();
    try {
      const users = await request('/users');
      const choices = document.createDocumentFragment();
      const placeholder = new Option('请选择测试用户', '');
      choices.append(placeholder);
      for (const user of users) {
        const item = new Option(user.email + (user.active ? '' : '（已停用或无流量）'), String(user.id));
        item.disabled = !user.active;
        choices.append(item);
      }
      if ($('yz-quick-overlay').hidden) return;
      $('yz-quick-user').replaceChildren(choices);
      const enabled = users.filter(u => u.active);
      if (enabled.length === 1) {
        $('yz-quick-user').value = String(enabled[0].id);
        await fetchLinks();
      } else {
        status(users.length ? '请选择需要导入订阅的用户。' : '尚无测试用户，请先在用户管理里创建。');
      }
    } catch (error) { status(error.message); }
  }
  function close() {
    ++pending;
    $('yz-quick-overlay').hidden = true;
    clear();
  }

  document.addEventListener('DOMContentLoaded', () => {
    $('yz-quick-launch').addEventListener('click', open);
    $('yz-quick-close').addEventListener('click', close);
    $('yz-quick-overlay').addEventListener('click', e => {
      if (e.target === $('yz-quick-overlay')) close();
    });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && !$('yz-quick-overlay').hidden) close();
    });
    $('yz-quick-user').addEventListener('change', fetchLinks);
    document.querySelectorAll('[data-yz-copy]').forEach(button => {
      button.addEventListener('click', async () => {
        const input = $(button.dataset.yzCopy);
        if (!selected || !input.value) return;
        try {
          await navigator.clipboard.writeText(input.value);
          status('订阅地址已复制。');
        } catch {
          input.select();
          status('请长按选中的链接复制。');
        }
      });
    });
  });
})();
