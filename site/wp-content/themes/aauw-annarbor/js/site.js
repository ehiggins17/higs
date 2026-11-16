(function () {
  'use strict';

  // Mobile navigation
  var toggle = document.getElementById('navtoggle');
  var nav = document.getElementById('sitenav');
  if (toggle && nav) {
    var closeBtn = document.getElementById('navclose');
    function setNav(open) {
      toggle.setAttribute('aria-expanded', String(open));
      document.body.classList.toggle('nav-open', open);
      if (open && closeBtn) closeBtn.focus();
    }
    toggle.addEventListener('click', function () { setNav(toggle.getAttribute('aria-expanded') !== 'true'); });
    if (closeBtn) closeBtn.addEventListener('click', function () { setNav(false); toggle.focus(); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && document.body.classList.contains('nav-open')) {
        toggle.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('nav-open');
        toggle.focus();
      }
    });
  }

  // Larger-text preference (per visitor, stored locally)
  var ts = document.getElementById('textsize');
  var KEY = 'aauw-large-text';
  function applyLarge(on) {
    document.documentElement.classList.toggle('large-text', on);
    if (ts) ts.setAttribute('aria-pressed', String(on));
  }
  try { applyLarge(localStorage.getItem(KEY) === '1'); } catch (e) {}
  if (ts) {
    ts.addEventListener('click', function () {
      var on = !document.documentElement.classList.contains('large-text');
      applyLarge(on);
      try { localStorage.setItem(KEY, on ? '1' : '0'); } catch (e) {}
    });
  }

  // Form success notice (Netlify redirects back with ?sent=1)
  if (/[?&]sent=1/.test(location.search)) {
    var note = document.getElementById('form-success');
    if (note) { note.hidden = false; note.focus(); }
  }

  // Mark past events (data-date="YYYY-MM-DD") without removing them
  var today = new Date(); today.setHours(0, 0, 0, 0);
  document.querySelectorAll('[data-date]').forEach(function (el) {
    var d = new Date(el.getAttribute('data-date') + 'T00:00:00');
    if (d < today) el.classList.add('is-past');
  });
})();

/* Homepage v2 behaviours */
(function () {
  'use strict';
  document.documentElement.classList.add('js');
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Reveal on scroll
  var revealEls = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting || e.boundingClientRect.top < 0) { e.target.classList.add('in'); io.unobserve(e.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
    revealEls.forEach(function (el) { io.observe(el); });
    // Anchor jumps can skip past elements without ever intersecting; sweep on scroll.
    var sweeping = false;
    window.addEventListener('scroll', function () {
      if (sweeping) return; sweeping = true;
      requestAnimationFrame(function () {
        document.querySelectorAll('.reveal:not(.in)').forEach(function (el) {
          if (el.getBoundingClientRect().top < window.innerHeight * 0.92) { el.classList.add('in'); io.unobserve(el); }
        });
        sweeping = false;
      });
    }, { passive: true });
  } else {
    revealEls.forEach(function (el) { el.classList.add('in'); });
  }

  // Count-up numbers
  document.querySelectorAll('[data-count]').forEach(function (el) {
    var target = parseFloat(el.getAttribute('data-count'));
    var prefix = el.getAttribute('data-prefix') || '';
    var suffix = el.getAttribute('data-suffix') || '';
    var fmt = function (n) { return prefix + Math.round(n).toLocaleString('en-US') + suffix; };
    if (reduce || !('IntersectionObserver' in window)) { el.textContent = fmt(target); return; }
    el.textContent = fmt(0);
    var seen = new IntersectionObserver(function (entries) {
      if (!entries[0].isIntersecting) return;
      seen.disconnect();
      var t0 = performance.now(), dur = 1500;
      var step = function (now) {
        var p = Math.min(1, (now - t0) / dur), e = 1 - Math.pow(1 - p, 3);
        el.textContent = fmt(target * e);
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    }, { threshold: 0.5 });
    seen.observe(el);
  });

  // Seamless ticker: duplicate the track content once
  document.querySelectorAll('.ticker__track').forEach(function (t) {
    var clone = t.cloneNode(true);
    Array.prototype.forEach.call(clone.children, function (c) { c.classList.add('dup'); c.setAttribute('aria-hidden', 'true'); });
    while (clone.firstChild) t.appendChild(clone.firstChild);
  });
})();

/* Dropdown menus: click to toggle, Escape and outside-click to close */
(function () {
  'use strict';
  var items = document.querySelectorAll('.has-menu');
  function closeAll(except) {
    items.forEach(function (li) {
      if (li !== except) { li.classList.remove('is-open'); var b = li.querySelector('.nav__btn'); if (b) b.setAttribute('aria-expanded', 'false'); }
    });
  }
  items.forEach(function (li) {
    var btn = li.querySelector('.nav__btn');
    var mobile = window.matchMedia('(max-width: 900px)');
    btn.addEventListener('click', function (e) {
      if (!mobile.matches) return; /* desktop: the link navigates; the mega menu opens on hover */
      e.preventDefault();
      var open = li.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', String(open));
      closeAll(li);
    });
    /* Hover intent: open quickly, close only after the pointer has been away for a moment,
       so a diagonal path from the item down into the menu never closes it. */
    var openT, closeT;
    function openNow() { clearTimeout(closeT); closeAll(li); li.classList.add('is-open'); btn.setAttribute('aria-expanded', 'true'); }
    function closeSoon() { clearTimeout(openT); clearTimeout(closeT); closeT = setTimeout(function () { li.classList.remove('is-open'); btn.setAttribute('aria-expanded', 'false'); }, 420); }
    li.addEventListener('mouseenter', function () { if (mobile.matches) return; clearTimeout(closeT); openT = setTimeout(openNow, 60); });
    li.addEventListener('mouseleave', function () { if (mobile.matches) return; closeSoon(); });
    li.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { li.classList.remove('is-open'); btn.setAttribute('aria-expanded', 'false'); btn.focus(); }
    });
  });
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.has-menu')) closeAll(null);
  });
})();

/* Programs calendar: builds a month grid from the .event articles (data-date, data-end, data-kind) */
(function () {
  'use strict';
  var cal = document.getElementById('cal'); if (!cal) return;
  var list = document.getElementById('events-list'), grid = document.getElementById('cal-grid'), title = document.getElementById('cal-month'), detail = document.getElementById('cal-detail'), monthsEl = document.getElementById('cal-months');
  var MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  var DOW = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  var today = new Date(); today.setHours(0,0,0,0);
  function parse(s) { var p = s.split('-'); return new Date(+p[0], +p[1]-1, +p[2]); }
  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function key(d) { return d.getFullYear() + '-' + (d.getMonth()+1) + '-' + d.getDate(); }
  var events = Array.prototype.map.call(list.querySelectorAll('.event[data-date]'), function (el, i) {
    var start = parse(el.getAttribute('data-date')), end = el.getAttribute('data-end') ? parse(el.getAttribute('data-end')) : start;
    var link = el.querySelector('a'); var h = el.querySelector('h3'); var meta = el.querySelector('.meta'); var ps = el.querySelectorAll('div > p:not(.meta)');
    var desc = ''; Array.prototype.forEach.call(ps, function (p) { if (!p.querySelector('a') && !desc) desc = p.textContent.trim(); });
    return { i: i, el: el, start: start, end: end, kind: el.getAttribute('data-kind') || 'program', title: h ? h.textContent.trim() : '', meta: meta ? meta.textContent.trim() : '', desc: desc, href: link ? link.getAttribute('href') : '', linkText: link ? link.textContent.replace(/→/, '').trim() : '', past: end < today, note: el.getAttribute('data-note') };
  });
  if (!events.length) return;
  var first = new Date(events[0].start.getFullYear(), events[0].start.getMonth(), 1);
  var last = new Date(events[events.length-1].start.getFullYear(), events[events.length-1].start.getMonth(), 1);
  // start on the current month if it falls inside the season, otherwise on the first upcoming event's month
  var upcoming = events.filter(function (e) { return !e.past; })[0] || events[events.length-1];
  var cur = new Date(upcoming.start.getFullYear(), upcoming.start.getMonth(), 1);
  if (today >= first && today <= new Date(last.getFullYear(), last.getMonth()+1, 0)) cur = new Date(today.getFullYear(), today.getMonth(), 1);
  var selected = upcoming;
  function sameMonth(a, b) { return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth(); }
  function eventsOn(d) { return events.filter(function (e) { return d >= e.start && d <= e.end; }); }
  function fmt(e) {
    var s = e.start, en = e.end;
    if (s.getTime() === en.getTime()) return DOW[s.getDay()] + ', ' + MONTHS[s.getMonth()] + ' ' + s.getDate() + ', ' + s.getFullYear();
    return DOW[s.getDay()] + '–' + DOW[en.getDay()] + ', ' + MONTHS[s.getMonth()] + ' ' + s.getDate() + '–' + en.getDate() + ', ' + s.getFullYear();
  }
  function renderDetail() {
    var e = selected; if (!e) { detail.innerHTML = ''; return; }
    var kindLabel = e.kind === 'sale' ? 'Used Book Sale' : e.kind === 'fundraiser' ? 'Fundraiser' : 'Branch program';
    var note = e.note != null ? e.note : (e.kind === 'program' ? 'Optional reserved lunch first; the talk is free and open to the public. Ann Arbor City Club, 1830 Washtenaw Ave.' : '');
    var others = eventsOn(e.start).filter(function (o) { return o !== e; });
    detail.innerHTML = '<span class="cal__kind cal__kind--' + esc(e.kind) + '">' + kindLabel + (e.past ? ' · past' : '') + '</span>' +
      '<time class="cal__when" datetime="' + esc(e.el.getAttribute('data-date')) + '">' + fmt(e) + '</time>' +
      '<h3 class="cal__title">' + esc(e.title) + '</h3>' +
      (e.meta ? '<p class="cal__meta">' + esc(e.meta) + '</p>' : '') +
      (e.desc ? '<p class="cal__desc">' + esc(e.desc) + '</p>' : '') +
      (note ? '<p class="cal__note">' + esc(note) + '</p>' : '') +
      (e.href ? '<a class="btn btn--navy arrow" href="' + esc(e.href) + '">' + esc(e.linkText || 'Learn more') + '</a>' : '') +
      (others.length ? '<p class="cal__note" style="margin-top:1rem"><strong>Also on this day:</strong> ' + others.map(function (o) { return o.href ? '<a href="' + esc(o.href) + '">' + esc(o.title) + '</a>' : esc(o.title); }).join(', ') + '</p>' : '');
  }
  function render() {
    title.textContent = MONTHS[cur.getMonth()] + ' ' + cur.getFullYear();
    cal.querySelector('[data-cal="prev"]').disabled = sameMonth(cur, first);
    cal.querySelector('[data-cal="next"]').disabled = sameMonth(cur, last);
    var frag = document.createDocumentFragment();
    var startDow = cur.getDay(), days = new Date(cur.getFullYear(), cur.getMonth()+1, 0).getDate();
    var prevDays = new Date(cur.getFullYear(), cur.getMonth(), 0).getDate();
    var cells = Math.ceil((startDow + days) / 7) * 7;
    for (var c = 0; c < cells; c++) {
      var n = c - startDow + 1, d, other = false;
      if (n < 1) { d = new Date(cur.getFullYear(), cur.getMonth()-1, prevDays + n); other = true; }
      else if (n > days) { d = new Date(cur.getFullYear(), cur.getMonth()+1, n - days); other = true; }
      else d = new Date(cur.getFullYear(), cur.getMonth(), n);
      var evs = eventsOn(d), cell = document.createElement(evs.length && !other ? 'button' : 'div');
      cell.className = 'cal__day' + (other ? ' is-other' : '') + (d.getTime() === today.getTime() ? ' is-today' : '') + (evs.length && !other ? ' has-event' : '') + (d < today ? ' is-past' : '');
            if (evs.length && !other) {
        var e = evs[0]; cell.type = 'button'; cell.setAttribute('data-ev', e.i);
        cell.classList.add('kind-' + e.kind); if (selected === e) cell.classList.add('is-selected');
        cell.innerHTML = '<span class="cal__num">' + d.getDate() + '</span><span class="sr-only">' + MONTHS[d.getMonth()] + ': </span><span class="cal__chip">' + esc(e.title) + (evs.length > 1 ? ' +' + (evs.length - 1) : '') + '</span>';
      } else {
        cell.innerHTML = '<span class="cal__num">' + d.getDate() + '</span>';
      }
      frag.appendChild(cell);
    }
    grid.innerHTML = ''; grid.appendChild(frag);
    // month strip
    var m = new Date(first), html = '';
    while (m <= last) {
      var has = events.some(function (e) { return sameMonth(e.start, m); });
      html += '<li><button type="button" data-ym="' + m.getFullYear() + '-' + m.getMonth() + '"' + (sameMonth(m, cur) ? ' class="is-on" aria-current="true"' : '') + '>' + MONTHS[m.getMonth()].slice(0,3) + (has ? '<i></i>' : '') + '</button></li>';
      m = new Date(m.getFullYear(), m.getMonth()+1, 1);
    }
    monthsEl.innerHTML = html;
    renderDetail();
  }
  cal.addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-cal], [data-ym], [data-ev], [data-view]'); if (!t) return;
    if (t.hasAttribute('data-cal')) { cur = new Date(cur.getFullYear(), cur.getMonth() + (t.getAttribute('data-cal') === 'next' ? 1 : -1), 1); var inM = events.filter(function (e) { return sameMonth(e.start, cur); })[0]; if (inM) selected = inM; render(); }
    else if (t.hasAttribute('data-ym')) { var p = t.getAttribute('data-ym').split('-'); cur = new Date(+p[0], +p[1], 1); var inM2 = events.filter(function (e) { return sameMonth(e.start, cur); })[0]; if (inM2) selected = inM2; render(); }
    else if (t.hasAttribute('data-ev')) { selected = events[+t.getAttribute('data-ev')]; render(); if (window.matchMedia('(max-width: 900px)').matches) detail.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
    else if (t.hasAttribute('data-view')) {
      var v = t.getAttribute('data-view');
      cal.querySelectorAll('[data-view]').forEach(function (b) { var on = b === t; b.classList.toggle('is-on', on); b.setAttribute('aria-pressed', String(on)); });
      cal.querySelector('.cal__body').hidden = v === 'list'; list.hidden = v !== 'list';
    }
  });
  cal.addEventListener('keydown', function (ev) {
    if (ev.key === 'ArrowLeft' && !cal.querySelector('[data-cal="prev"]').disabled && ev.target.closest('.cal__grid')) { cal.querySelector('[data-cal="prev"]').click(); }
    if (ev.key === 'ArrowRight' && !cal.querySelector('[data-cal="next"]').disabled && ev.target.closest('.cal__grid')) { cal.querySelector('[data-cal="next"]').click(); }
  });
  render();
})();
