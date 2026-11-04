(function () {
  'use strict';

  var options = window.__XBOARD_ECH_KEYGEN__ || {};
  var pagePath = typeof options.pagePath === 'string' && options.pagePath ? options.pagePath : '/ech-keygen';

  var card = null;
  var mountTimer = null;

  // 定位仪表盘顶部指标区：找「总用户」标题所在的卡片网格（与在线用户插件相同的定位法）。
  function findMetricGrid() {
    var headings = document.querySelectorAll('h3');
    for (var i = 0; i < headings.length; i++) {
      var heading = headings[i];
      if (heading.textContent.trim() !== '总用户' || !heading.getClientRects().length) continue;
      var element = heading;
      for (var depth = 0; element && depth < 5; depth++, element = element.parentElement) {
        var grid = element.parentElement;
        if (!grid || !grid.classList.contains('grid') || grid.children.length < 6) continue;
        var labels = 0;
        ['今日收入', '月新增用户', '月上传', '月下载'].forEach(function (label) {
          if (grid.textContent.indexOf(label) !== -1) labels++;
        });
        if (labels >= 3) return { grid: grid, template: element };
      }
    }
    return null;
  }

  function createCard(template) {
    card = document.createElement('div');
    card.className = template.className;
    card.setAttribute('data-xboard-ech-keygen-card', '');
    var shadow = card.attachShadow({ mode: 'open' });
    shadow.innerHTML = [
      '<style>',
      ':host{display:block;min-width:0;color:inherit;font-family:inherit}',
      '*{box-sizing:border-box}',
      'a{display:flex;flex-direction:column;width:100%;min-height:110px;padding:18px 20px;',
      'background:transparent;color:inherit;text-align:left;font:inherit;text-decoration:none;cursor:pointer}',
      'a:hover{background:rgba(127,127,127,.045)}',
      'a:focus-visible{outline:2px solid #2563eb;outline-offset:-3px;border-radius:12px}',
      '.top{display:flex;align-items:center;justify-content:space-between;width:100%;gap:12px;',
      'font-size:14px;font-weight:500;line-height:1.35}',
      '.arrow{font-size:15px;opacity:.55}',
      '.main{margin-top:7px;font-size:24px;font-weight:700;line-height:1.1}',
      '.hint{margin-top:5px;font-size:12px;line-height:1.35;opacity:.68}',
      '</style>',
      '<a href="' + pagePath + '" target="_blank" rel="noopener" aria-label="打开 ECH 密钥生成页面">',
      '<span class="top"><span>ECH密钥生成</span><span class="arrow" aria-hidden="true">↗</span></span>',
      '<strong class="main">指定 public_name</strong>',
      '<span class="hint">生成 ECH 密钥对 · 不再是 ech.example.com</span>',
      '</a>'
    ].join('');
  }

  function mountCard() {
    var target = findMetricGrid();
    if (!target) {
      if (card && card.isConnected) card.remove();
      return;
    }
    if (!card) createCard(target.template);
    if (card.parentElement !== target.grid) target.grid.appendChild(card);
  }

  function scheduleMount() {
    if (mountTimer) clearTimeout(mountTimer);
    mountTimer = setTimeout(function () {
      mountTimer = null;
      mountCard();
    }, 60);
  }

  function boot() {
    mountCard();
    var observer = new MutationObserver(scheduleMount);
    observer.observe(document.body, { childList: true, subtree: true });
    window.addEventListener('hashchange', scheduleMount);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
