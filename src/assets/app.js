(function () {
  'use strict';
  var form = document.getElementById('lines-form');
  if (!form) return;

  var loss = parseFloat(form.dataset.loss || '0') || 0;
  var uniformLabor = form.dataset.labor === '' ? null : parseFloat(form.dataset.labor);
  var uniformRate = parseFloat(form.dataset.rate || '0.8') || 0.8;
  var rows = Array.prototype.slice.call(document.querySelectorAll('#lines tbody tr'));

  function roundUp(v, digits) {
    var m = Math.pow(10, digits), x = v * m;
    var r = Math.abs(x - Math.round(x)) < 1e-9 ? Math.round(x) : (x >= 0 ? Math.ceil(x) : Math.floor(x));
    return r / m;
  }
  function num(v) { var s = String(v || '').trim(); if (s === '') return null; var f = parseFloat(s); return isNaN(f) ? null : f; }
  function fmt(v, d) { if (v === null || v === undefined || isNaN(v)) return ''; return v.toLocaleString('ja-JP', { minimumFractionDigits: d || 0, maximumFractionDigits: d || 0 }); }
  function q(tr, cls) { return tr.querySelector('.' + cls); }

  function recalc() {
    var data = [], merged = {}, tMat = 0, tLc = 0, tLab = 0, tQ = 0;
    rows.forEach(function (tr, i) {
      var qty = num(q(tr, 'quantity').value);
      var len = num(q(tr, 'length').value) || 0, wid = num(q(tr, 'width').value) || 0;
      var area = (len > 0 ? len : 1) * (wid > 0 ? wid : 1);
      var mp = num(q(tr, 'material_price').value) || 0, lr = num(q(tr, 'labor_rate').value) || 0;
      var lp = uniformLabor !== null ? uniformLabor : (num(q(tr, 'labor_unit_price').value) || 0);
      var mc = qty === null ? null : roundUp(qty * (1 + loss) / area, 0);
      var mcost = mc === null ? null : mc * mp;
      var lc = qty === null ? null : qty * lr;
      var lcost = lc === null ? null : roundUp(lc * lp, 0);
      var uc = (qty === null || qty === 0) ? null : ((mcost || 0) + (lcost || 0)) / qty;
      var isQ = q(tr, 'is_quote').checked;
      var mergeNo = parseInt(q(tr, 'merge_no').value || '0', 10);
      var d = { qty: qty, mc: mc, mcost: mcost, lc: lc, lcost: lcost, uc: uc, isQ: isQ, mergeNo: mergeNo, rate: num(q(tr, 'rate_override').value) || uniformRate };
      data.push(d);
      q(tr, 'mat_count').textContent = fmt(mc, 0);
      q(tr, 'mat_cost').textContent = fmt(mcost, 0);
      q(tr, 'labor_count').textContent = fmt(lc, 2);
      q(tr, 'labor_cost').textContent = fmt(lcost, 0);
      q(tr, 'unit_cost').textContent = fmt(uc, 1);
      tMat += mcost || 0; tLc += lc || 0; tLab += lcost || 0;
      tr.classList.toggle('quote', isQ);
    });
    data.forEach(function (d, i) {
      var target = null;
      if (d.isQ) target = i;
      else if (d.mergeNo > 0 && data[d.mergeNo - 1] && data[d.mergeNo - 1].isQ) target = d.mergeNo - 1;
      if (target !== null && d.uc !== null) merged[target] = (merged[target] || 0) + d.uc;
      rows[i].classList.toggle('merged', !d.isQ && target !== null);
    });
    data.forEach(function (d, i) {
      var tr = rows[i], mu = null, qp = null, amt = null;
      if (d.isQ) {
        mu = merged[i] === undefined ? null : merged[i];
        qp = (mu === null || d.rate <= 0) ? null : roundUp(mu / d.rate, -2);
        amt = (qp === null || d.qty === null) ? null : qp * d.qty;
        tQ += amt || 0;
      }
      q(tr, 'merged_unit_cost').textContent = fmt(mu, 1);
      q(tr, 'quote_price').textContent = fmt(qp, 0);
      q(tr, 'quote_amount').textContent = fmt(amt, 0);
    });
    document.getElementById('t_mat').textContent = fmt(tMat, 0);
    document.getElementById('t_labor_count').textContent = fmt(tLc, 2);
    document.getElementById('t_labor').textContent = fmt(tLab, 0);
    document.getElementById('t_quote').textContent = fmt(tQ, 0);
  }
  form.addEventListener('input', recalc);
  form.addEventListener('change', recalc);

  // ---- 単価マスタ検索 ----
  var cache = {};
  function search(qs, cat, cb) {
    var key = cat + '|' + qs;
    if (cache[key]) return cb(cache[key]);
    fetch(window.CANAME_SEARCH_URL + '?q=' + encodeURIComponent(qs) + '&category_id=' + cat, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (list) { cache[key] = list; cb(list); })
      .catch(function () { cb([]); });
  }
  function applyItem(tr, it) {
    q(tr, 'code').value = it.code;
    q(tr, 'name').value = it.name || '';
    q(tr, 'material').value = it.material || '';
    q(tr, 'length').value = it.length === null ? '' : parseFloat(it.length);
    q(tr, 'width').value = it.width === null ? '' : parseFloat(it.width);
    q(tr, 'thickness').value = it.thickness === null ? '' : parseFloat(it.thickness);
    q(tr, 'area_unit').value = it.area_unit || '';
    q(tr, 'material_price').value = it.material_price === null ? '' : parseFloat(it.material_price);
    q(tr, 'labor_rate').value = it.labor_rate === null ? '' : parseFloat(it.labor_rate);
    q(tr, 'labor_unit_price').value = it.labor_unit_price === null ? '' : parseFloat(it.labor_unit_price);
    recalc();
  }

  // コード欄で Enter / blur → 完全一致で転記
  rows.forEach(function (tr) {
    var code = q(tr, 'code');
    if (!code || code.disabled) return;
    code.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); lookup(tr); } });
    code.addEventListener('blur', function () { lookup(tr); });
    var btn = tr.querySelector('.pick');
    if (btn) btn.addEventListener('click', function () { openPicker(tr); });
  });
  function lookup(tr) {
    var c = q(tr, 'code').value.trim();
    if (c === '' || c === tr.dataset.lastCode) return;
    search(c, 0, function (list) {
      var hit = list.filter(function (x) { return x.code === c; })[0];
      if (hit) { tr.dataset.lastCode = c; applyItem(tr, hit); }
    });
  }

  var picker = document.getElementById('picker'), pkQ = document.getElementById('pk-q'), pkCat = document.getElementById('pk-cat'), pkBody = document.getElementById('pk-body');
  var target = null, timer = null;
  function openPicker(tr) { target = tr; picker.classList.add('open'); pkQ.value = q(tr, 'name').value || ''; pkQ.focus(); render(); }
  function render() {
    search(pkQ.value.trim(), pkCat.value, function (list) {
      pkBody.innerHTML = '';
      list.forEach(function (it) {
        var tr = document.createElement('tr');
        [it.code, it.category_name, it.name, it.material, it.length, it.width, it.area_unit, it.material_price === null ? '' : fmt(parseFloat(it.material_price)), it.labor_rate, it.labor_unit_price === null ? '' : fmt(parseFloat(it.labor_unit_price))]
          .forEach(function (v, i) { var td = document.createElement('td'); td.textContent = v === null || v === undefined ? '' : (typeof v === 'string' && /^\d+\.\d+$/.test(v) ? String(parseFloat(v)) : v); if (i >= 4) td.className = 'num'; tr.appendChild(td); });
        tr.addEventListener('click', function () { if (target) { target.dataset.lastCode = it.code; applyItem(target, it); } picker.classList.remove('open'); });
        pkBody.appendChild(tr);
      });
      if (!list.length) pkBody.innerHTML = '<tr><td colspan="10" class="muted">該当なし</td></tr>';
    });
  }
  if (picker) {
    pkQ.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(render, 200); });
    pkCat.addEventListener('change', render);
    document.getElementById('pk-close').addEventListener('click', function () { picker.classList.remove('open'); });
    picker.addEventListener('click', function (e) { if (e.target === picker) picker.classList.remove('open'); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') picker.classList.remove('open'); });
  }
})();
