/* AK DevLab - Free Shipping Bar */
(function () {
  'use strict';

  var CFG = window.akdlFreeship;
  if (!CFG || !(CFG.threshold > 0)) { return; }

  var root = document.documentElement;
  if (CFG.colors) {
    root.style.setProperty('--akdl-fs-accent', CFG.colors.accent);
    root.style.setProperty('--akdl-fs-bg', CFG.colors.bg);
    root.style.setProperty('--akdl-fs-text', CFG.colors.text);
  }

  function money(v) {
    try {
      return new Intl.NumberFormat(CFG.locale, { style: 'currency', currency: CFG.currency }).format(v);
    } catch (e) {
      return v.toFixed(2) + ' ' + CFG.currency;
    }
  }

  function productsTotal(cart) {
    try { return parseFloat(cart.subtotals.products.amount); } catch (e) { return null; }
  }

  // Builds the message from text, putting amounts in <b>. Text from translations
  // is inserted as text, never as HTML.
  function message(parent, template, values) {
    var parts = String(template).split(/(%amount%|%threshold%)/);
    for (var i = 0; i < parts.length; i++) {
      if (values[parts[i]] !== undefined) {
        var b = document.createElement('b');
        b.textContent = values[parts[i]];
        parent.appendChild(b);
      } else if (parts[i]) {
        parent.appendChild(document.createTextNode(parts[i]));
      }
    }
  }

  function build(total) {
    var left = Math.max(0, CFG.threshold - total);
    var pct = left <= 0 ? 100 : Math.min(100, Math.round(total / CFG.threshold * 100));
    var box = document.createElement('div');
    box.className = 'akdl-fs' + (left <= 0 ? ' akdl-fs--done' : '');
    box.setAttribute('role', 'status');
    if (left <= 0) {
      var b = document.createElement('b');
      b.textContent = CFG.textDone;
      box.appendChild(b);
    } else {
      message(box, CFG.textLeft, { '%amount%': money(left), '%threshold%': money(CFG.threshold) });
    }
    var bar = document.createElement('div');
    bar.className = 'akdl-fs__bar';
    var fill = document.createElement('div');
    fill.className = 'akdl-fs__fill';
    fill.style.width = pct + '%';
    bar.appendChild(fill);
    box.appendChild(bar);
    return { el: box, key: left.toFixed(2) + '|' + pct };
  }

  var lastCart = null;

  function render(cart) {
    cart = cart || lastCart || (window.prestashop && window.prestashop.cart);
    if (!cart) { return; }
    lastCart = cart;
    var total = productsTotal(cart);
    var old;
    if (total === null || isNaN(total) || !(cart.products_count > 0)) {
      old = document.querySelectorAll('.akdl-fs-wrap');
      for (var k = 0; k < old.length; k++) { old[k].parentNode.removeChild(old[k]); }
      return;
    }
    var modalDone = false;
    for (var i = 0; i < CFG.targets.length; i++) {
      var sel = CFG.targets[i];
      var isModal = sel.indexOf('#blockcart-modal') === 0;
      if (isModal && modalDone) { continue; }
      var els = document.querySelectorAll(sel);
      for (var j = 0; j < els.length; j++) {
        var host = els[j];
        var wrap = null;
        for (var c = 0; c < host.children.length; c++) {
          if (host.children[c].className === 'akdl-fs-wrap') { wrap = host.children[c]; break; }
        }
        if (!wrap) {
          wrap = document.createElement('div');
          wrap.className = 'akdl-fs-wrap';
          host.insertBefore(wrap, host.firstChild);
        }
        var out = build(total);
        if (wrap.getAttribute('data-key') !== out.key) {
          wrap.setAttribute('data-key', out.key);
          wrap.innerHTML = '';
          wrap.appendChild(out.el);
        }
        if (isModal) { modalDone = true; }
      }
    }
  }

  function init() {
    render();
    var ps = window.prestashop;
    if (ps && typeof ps.on === 'function') {
      ps.on('updateCart', function (ev) {
        var c = ev && ev.resp && ev.resp.cart ? ev.resp.cart : null;
        if (c) { lastCart = c; }
        setTimeout(function () { render(c); }, 50);
        setTimeout(function () { render(c); }, 800);
      });
      ps.on('updatedCart', function (ev) {
        setTimeout(function () { render(ev && ev.cart ? ev.cart : null); }, 50);
      });
    }
    // The theme may re-draw the cart summary or popup; put the bar back when it does.
    if ('MutationObserver' in window) {
      var t = null;
      new MutationObserver(function () {
        clearTimeout(t);
        t = setTimeout(function () { render(); }, 120);
      }).observe(document.body, { childList: true, subtree: true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
