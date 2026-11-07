(function () {
  document.getElementById('year').textContent = new Date().getFullYear();


  // Cursor spotlight on glass cards
  document.addEventListener('pointermove', function (e) {
    var el = e.target.closest && e.target.closest('.spot'); if (!el) return;
    var r = el.getBoundingClientRect();
    el.style.setProperty('--mx', (e.clientX - r.left) + 'px'); el.style.setProperty('--my', (e.clientY - r.top) + 'px');
  });

  // Clients marquee: duplicate the logos for a seamless loop
  var cl = document.querySelector('.clients');
  if (cl) Array.prototype.slice.call(cl.children).forEach(function (li) { var c = li.cloneNode(true); c.setAttribute('aria-hidden', 'true'); cl.appendChild(c); });

  // Count-up numbers when they come into view
  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var counter = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return; counter.unobserve(en.target);
        var node = Array.prototype.filter.call(en.target.childNodes, function (n) { return n.nodeType === 3 && /\d/.test(n.nodeValue); })[0];
        if (!node) return;
        var m = node.nodeValue.match(/(\d+)/), end = +m[1], t0 = null, pre = node.nodeValue.slice(0, m.index), post = node.nodeValue.slice(m.index + m[1].length);
        var step = function (t) { t0 = t0 || t; var k = Math.min(1, (t - t0) / 1400); node.nodeValue = pre + Math.round(end * (1 - Math.pow(1 - k, 3))) + post; if (k < 1) requestAnimationFrame(step); };
        requestAnimationFrame(step);
      });
    }, { threshold: .6 });
    document.querySelectorAll('.numbers b, .stat b').forEach(function (b) { counter.observe(b); });
  }

  // Current page in the menu
  var pageKey = document.body.getAttribute('data-page');
  document.querySelectorAll('[data-nav]').forEach(function (a) { if (a.getAttribute('data-nav') === pageKey) a.setAttribute('aria-current', 'page'); });

  // Header turns to glass once the page scrolls
  var header = document.querySelector('.site-header');
  var onScroll = function () { header.classList.toggle('scrolled', window.scrollY > 8); };
  window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
  if ('IntersectionObserver' in window) {

    // Gentle lift-in for blocks below the fold (they stay fully visible)
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      var lifter = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); lifter.unobserve(en.target); } });
      }, { rootMargin: '0px 0px -8% 0px' });
      document.querySelectorAll('.head, .svc-card, .hurdles li, .order-strip, .cta-band, .stat, .pa-card, .stage, .pkg, .steps6 li, .method, .feature, .pre-card, .sector, .role, .clients li, .contact-side, form.enquiry').forEach(function (el, i) {
        if (el.getBoundingClientRect().top < window.innerHeight) return;
        el.classList.add('lift');
        el.style.transitionDelay = (i % 3) * 70 + 'ms';
        lifter.observe(el);
      });
    }
  }

  var nav = document.querySelector('.nav'), menuBtn = document.getElementById('menu-btn');
  menuBtn.addEventListener('click', function () {
    var open = nav.classList.toggle('open');
    menuBtn.setAttribute('aria-expanded', open);
  });
  document.querySelectorAll('#mobile-menu a').forEach(function (a) {
    a.addEventListener('click', function () { nav.classList.remove('open'); menuBtn.setAttribute('aria-expanded', 'false'); });
  });

  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var select = function () {
        var r = document.createRange(); r.selectNodeContents(document.getElementById(b.dataset.target));
        var s = window.getSelection(); s.removeAllRanges(); s.addRange(r); b.textContent = 'Selected';
      };
      try {
        navigator.clipboard.writeText(b.dataset.copy).then(function () {
          b.textContent = 'Copied'; setTimeout(function () { b.textContent = 'Copy'; }, 1800);
        }, select);
      } catch (e) { select(); }
    });
  });

  var form = document.getElementById('enquiry');
  if (form) {
  var status = document.getElementById('form-status'),
      submit = document.getElementById('f-submit'), label = submit.querySelector('span');
  function show(msg, ok) { status.textContent = msg; status.className = 'form-status ' + (ok ? 'ok' : 'err'); status.hidden = false; }
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var name = form.elements.name.value.trim(), email = form.elements.email.value.trim(), msg = form.elements.message.value.trim();
    if (!name || !msg || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      show('Please add your name, a valid email address and a short description of your project.', false); return;
    }
    submit.disabled = true; label.textContent = 'Sending…';
    fetch('contact.php', { method: 'POST', body: new FormData(form) })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.ok) { form.reset(); show('Thank you. Your request has been sent and we will reply within one business day.', true); }
        else show(d.error || 'Your request could not be sent. Please email saadsr@redoceanconsult.com.', false);
      })
      .catch(function () { show('Your request could not be sent from here. Please email saadsr@redoceanconsult.com or call +966 58 290 7870.', false); })
      .then(function () { submit.disabled = false; label.textContent = 'Send request'; });
  });
  }
})();

// ── Online ordering ──
(function () {
  var cfg = null, svcId = null, step = 0, units = 1, band = null, express = false, picked = {}, client = {}, sending = false, result = null;
  var dlg = document.getElementById('wiz'), body = document.getElementById('wiz-body'), totalEl = document.getElementById('wiz-total'),
      next = document.getElementById('wiz-next'), back = document.getElementById('wiz-back'), steps = document.querySelectorAll('#wiz-steps li');
  var sar = function (n) { return 'SAR ' + Math.round(n).toLocaleString('en-US'); };
  var esc = function (t) { return String(t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };

  fetch('pricing.json').then(function (r) { return r.json(); }).then(function (c) {
    cfg = c;
    document.querySelectorAll('[data-from]').forEach(function (el) {
      var sv = c.services[el.dataset.from], u = sv.unit ? sv.unit.min : 1, t = 0;
      if (sv.choice === 'single') t = Math.min.apply(null, sv.items.map(function (it) { return it.price; }));
      else sv.items.forEach(function (it) { if (it.required) t += it.price * (it.perUnit ? u : 1); });
      el.textContent = sar(t);
    });
  }).catch(function () {});

  function calc() {
    var sv = cfg.services[svcId], mult = band ? band.multiplier : 1, lines = [], sub = 0, days = 0;
    sv.items.forEach(function (it) {
      if (!it.required && !picked[it.id]) return;
      var q = it.perUnit ? units : 1, amt = Math.round(it.price * q * mult);
      lines.push([it.label + (q > 1 ? ' × ' + q : ''), amt]); sub += amt; days += it.days * q;
    });
    if (express) { var x = Math.round(sub * (cfg.express.multiplier - 1)); lines.push([cfg.express.label, x]); sub += x; }
    days = Math.max(1, Math.ceil(Math.max(days, sv.baseDays) * (express ? cfg.express.daysFactor : 1)));
    var vat = Math.round(sub * cfg.vatRate);
    return { lines: lines, sub: sub, vat: vat, total: sub + vat, days: days };
  }

  function open(id) {
    if (!cfg) { location.href = 'contact.html'; return; }
    svcId = id; step = 0; units = cfg.services[id].unit ? cfg.services[id].unit.default : 1; band = null; express = false; result = null; picked = {};
    cfg.services[id].items.forEach(function (it) { if (it.default) picked[it.id] = true; });
    document.getElementById('wiz-title').textContent = 'Order: ' + cfg.services[id].title;
    document.getElementById('wiz-tag').textContent = cfg.services[id].tagline;
    render();
    if (dlg.showModal) dlg.showModal(); else dlg.setAttribute('open', '');
  }

  function render(err) {
    var sv = cfg.services[svcId], q = calc(), h = '';
    steps.forEach(function (li, i) { li.className = i === step ? 'on' : i < step ? 'done' : ''; });
    totalEl.textContent = sar(q.sub);
    back.hidden = step === 0 || step === 3;
    next.disabled = sending;
    next.textContent = ['Continue to project details', 'See my quotation', 'Approve & pay', 'Close'][step];
    if (err) h += '<p class="wiz-error" role="alert">' + esc(err) + '</p>';

    if (step === 0) {
      var single = sv.choice === 'single';
      h += '<h3>' + (single ? 'Choose your option' : 'What do you need?') + '</h3><p class="wiz-hint">' + (single ? 'Select one option.' : 'Tick every deliverable you want included.') + ' Prices are for projects up to SAR 10 million and adjust to your project value in the next step.</p><div class="opts">';
      sv.items.forEach(function (it) {
        var on = it.required || picked[it.id];
        h += '<label class="opt"><input type="' + (single ? 'radio' : 'checkbox') + '" name="wiz-opt" data-item="' + it.id + '"' + (on ? ' checked' : '') + (it.required ? ' disabled' : '') + '>' +
             '<span><b>' + esc(it.label) + (it.required ? '<em>Included</em>' : '') + '</b><small>' + esc(it.desc) + '</small></span>' +
             '<span class="price">' + sar(it.price) + (it.perUnit ? '/event' : '') + '</span></label>';
      });
      h += '</div>';
    }
    if (step === 1) {
      h += '<h3>About the project</h3><div class="field field--full"><label>Contract value</label><div class="seg">';
      cfg.sizeBands.forEach(function (b) { h += '<label><input type="radio" name="band" value="' + b.id + '"' + (band && band.id === b.id ? ' checked' : '') + '>' + esc(b.label) + '</label>'; });
      h += '</div></div>';
      if (sv.unit) h += '<div class="field"><label for="w-units">' + esc(sv.unit.label) + '</label><div class="stepper"><button type="button" data-step="-1" aria-label="Fewer">−</button><input id="w-units" type="number" min="' + sv.unit.min + '" max="' + sv.unit.max + '" value="' + units + '"><button type="button" data-step="1" aria-label="More">+</button></div><small class="wiz-hint" style="margin:0">' + esc(sv.unit.help) + '</small></div>';
      h += '<label class="switch"><input type="checkbox" id="w-express"' + (express ? ' checked' : '') + '><span>' + esc(cfg.express.label) + '<small>Faster delivery for urgent deadlines, +' + Math.round((cfg.express.multiplier - 1) * 100) + '% on the quotation.</small></span></label>';
      h += '<div class="wiz-grid">' +
        fld('name', 'Full name *', 'text', 'name') + fld('company', 'Company', 'text', 'organization') +
        fld('email', 'Email *', 'email', 'email') + fld('phone', 'Phone', 'tel', 'tel') +
        '<div class="field field--full"><label for="w-project">Project name *</label><input id="w-project" data-c="project" type="text" value="' + esc(client.project || '') + '" placeholder="e.g. Office tower, Riyadh"></div>' +
        '<div class="field field--full"><label for="w-notes">Notes (optional)</label><textarea id="w-notes" data-c="notes" style="min-height:80px" placeholder="Deadlines, contract type, anything we should know">' + esc(client.notes || '') + '</textarea></div></div>';
    }
    if (step === 2) {
      var today = new Date(), valid = new Date(today.getTime() + cfg.validityDays * 864e5), f = function (d) { return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }); };
      h += '<div class="quote"><div class="quote-top"><div><b>' + esc(sv.title) + '</b>' + esc(client.project) + (client.company ? ' · ' + esc(client.company) : '') + '</div><div class="mono">Quotation date ' + f(today) + '<br>Valid until ' + f(valid) + '</div></div><table>';
      q.lines.forEach(function (l) { h += '<tr><td>' + esc(l[0]) + '</td><td>' + sar(l[1]) + '</td></tr>'; });
      h += '<tr class="sum"><td>Subtotal</td><td>' + sar(q.sub) + '</td></tr><tr class="sum"><td>VAT ' + Math.round(cfg.vatRate * 100) + '%</td><td>' + sar(q.vat) + '</td></tr>' +
           '<tr class="total"><td>Total</td><td>' + sar(q.total) + '</td></tr></table>' +
           '<div class="quote-terms"><div><b>' + q.days + ' working days</b>Estimated delivery</div><div><b>' + esc(band.label) + '</b>Project value</div><div><b>100% upfront</b>Payment terms</div></div></div>' +
           '<p class="fineprint">Delivery time starts when we receive the project documents. If the documents show the scope is materially different, we will confirm any change with you before continuing. A copy of this quotation is emailed to ' + esc(client.email) + '.</p>';
    }
    if (step === 3) {
      h += '<div class="done-box"><svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="32" cy="32" r="28"/><path d="M20 33l8 8 16-17"/></svg>';
      if (result.paid) {
        h += '<h3>Payment received</h3><p>Thank you. Your payment for order <b class="mono">' + esc(result.ref) + '</b> is complete and a receipt has been emailed to ' + esc(client.email) + '.</p>' +
             '<p class="fineprint">PayPal transaction ' + esc(result.paid) + '. Our team will contact you within one business day to collect the contract, programme and records.</p></div>';
      } else {
        h += '<h3>Quotation approved</h3><p>Order reference <b class="mono">' + esc(result.ref) + '</b>. Total due <b>' + sar(result.total) + '</b>.</p>';
        if (result.paypal) {
          h += '<p class="fineprint">PayPal charges in US dollars: <b>USD ' + Number(result.paypal.usd).toLocaleString('en-US', { minimumFractionDigits: 2 }) + '</b> (fixed rate ' + esc(result.paypal.rate) + ' SAR = 1 USD). Pay with your PayPal account or any debit or credit card.</p>' +
               '<div class="pp-box" id="pp-buttons"><p class="fineprint">Loading secure payment…</p></div><p class="pp-msg" id="pp-msg" role="alert" hidden></p>';
        }
        var bank = result.bank || {};
        if (Object.keys(bank).length) {
          h += (result.paypal ? '<details class="pay-alt"><summary>Prefer bank transfer?</summary>' : '<p>Please transfer the total to the account below. These details have also been emailed to you.</p>') + '<div class="bank">';
          Object.keys(bank).forEach(function (k) { h += '<div><span>' + esc(k) + '</span><b>' + esc(bank[k]) + '</b></div>'; });
          h += '</div>' + (result.paypal ? '</details>' : '');
        }
        if (!result.paypal && !Object.keys(bank).length) h += '<p>We will email you payment instructions within one business day.</p>';
        h += '<p class="fineprint">Once payment is received, we will contact you to collect the contract, programme and records.</p></div>';
      }
    }
    body.innerHTML = h;
    body.scrollTop = 0;
    if (step === 3 && result.paypal && !result.paid) mountPayPal();
  }
  function post(url, data) {
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
      .then(function (r) { return r.json(); });
  }
  function ppMessage(text) { var m = document.getElementById('pp-msg'); if (m) { m.textContent = text; m.hidden = !text; } }
  function mountPayPal() {
    var box = document.getElementById('pp-buttons');
    var go = function () {
      box.innerHTML = '';
      window.paypal.Buttons({
        style: { layout: 'vertical', color: 'gold', shape: 'rect', label: 'pay' },
        createOrder: function () {
          ppMessage('');
          return post('paypal.php', { action: 'create', ref: result.ref }).then(function (d) {
            if (!d.ok) throw new Error(d.error); return d.id;
          });
        },
        onApprove: function (data) {
          ppMessage('Confirming your payment…');
          return post('paypal.php', { action: 'capture', ref: result.ref, orderID: data.orderID }).then(function (d) {
            if (d.ok) { result.paid = d.captureId; render(); }
            else ppMessage(d.error || 'The payment was not completed. Please try again.');
          });
        },
        onCancel: function () { ppMessage('Payment cancelled. You have not been charged. You can try again whenever you are ready.'); },
        onError: function (err) { ppMessage((err && err.message) || 'PayPal is not available right now. Please try again or contact us.'); }
      }).render('#pp-buttons');
    };
    if (window.paypal) return go();
    var sdk = document.createElement('script');
    sdk.src = 'https://www.paypal.com/sdk/js?client-id=' + encodeURIComponent(result.paypal.clientId) + '&currency=USD&intent=capture';
    sdk.onload = go;
    sdk.onerror = function () { box.innerHTML = ''; ppMessage('PayPal could not load. Check your connection or use the bank transfer option.'); };
    document.head.appendChild(sdk);
  }
  function fld(k, label, type, ac) {
    return '<div class="field"><label for="w-' + k + '">' + label + '</label><input id="w-' + k + '" data-c="' + k + '" type="' + type + '" autocomplete="' + ac + '" value="' + esc(client[k] || '') + '"></div>';
  }

  body.addEventListener('change', function (e) {
    var t = e.target;
    if (t.dataset.item) { if (t.type === 'radio') picked = {}; picked[t.dataset.item] = t.checked; }
    if (t.name === 'band') band = cfg.sizeBands.filter(function (b) { return b.id === t.value; })[0];
    if (t.id === 'w-express') express = t.checked;
    if (t.id === 'w-units') { var u = cfg.services[svcId].unit; units = Math.min(u.max, Math.max(u.min, parseInt(t.value, 10) || u.min)); t.value = units; }
    totalEl.textContent = sar(calc().sub);
  });
  body.addEventListener('input', function (e) { if (e.target.dataset.c) client[e.target.dataset.c] = e.target.value; });
  body.addEventListener('click', function (e) {
    var b = e.target.closest('[data-step]'); if (!b) return;
    var u = cfg.services[svcId].unit; units = Math.min(u.max, Math.max(u.min, units + +b.dataset.step));
    document.getElementById('w-units').value = units; totalEl.textContent = sar(calc().sub);
  });

  next.addEventListener('click', function () {
    if (step === 0) { step = 1; return render(); }
    if (step === 1) {
      if (!band) return render('Please choose the contract value of the project.');
      if (!(client.name || '').trim() || !(client.project || '').trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test((client.email || '').trim())) return render('Please add your name, a valid email address and the project name.');
      step = 2; return render();
    }
    if (step === 2) {
      sending = true; next.textContent = 'Processing…'; next.disabled = true;
      var items = Object.keys(picked).filter(function (k) { return picked[k]; });
      fetch('order.php', { method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ service: svcId, items: items, band: band.id, units: units, express: express, client: client }) })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          sending = false;
          if (!d.ok) return render(d.error || 'Your order could not be processed.');
          result = d; step = 3; render();
        })
        .catch(function () { sending = false; render('Online ordering is not available right now. Please email this quotation request to saadsr@redoceanconsult.com or call +966 58 290 7870.'); });
      return;
    }
    if (step === 3) dlg.close();
  });
  back.addEventListener('click', function () { if (step > 0) { step--; render(); } });
  document.getElementById('wiz-close').addEventListener('click', function () { dlg.close(); });
  dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
  document.querySelectorAll('[data-order]').forEach(function (b) { b.addEventListener('click', function () { open(b.dataset.order); }); });
})();
