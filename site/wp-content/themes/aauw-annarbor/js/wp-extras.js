/* Click a gallery or photo-row picture to see it larger. Esc, the X, or a click outside closes it. */
(function () {
  'use strict';
  var box;
  function close() { if (box) { box.remove(); box = null; document.documentElement.style.overflow = ''; } }
  function open(src, caption) {
    close();
    box = document.createElement('div'); box.className = 'lightbox'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true'); box.setAttribute('aria-label', caption || 'Photo');
    var fig = document.createElement('figure'); var img = document.createElement('img'); img.src = src; img.alt = caption || '';
    var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'lightbox__close'; btn.setAttribute('aria-label', 'Close'); btn.textContent = '\u00d7';
    fig.appendChild(img);
    if (caption) { var cap = document.createElement('figcaption'); cap.textContent = caption; fig.appendChild(cap); }
    box.appendChild(btn); box.appendChild(fig); document.body.appendChild(box); document.documentElement.style.overflow = 'hidden'; btn.focus();
    box.addEventListener('click', function (e) { if (e.target === box || e.target === btn) close(); });
  }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('.gallery a, .photo-row a') : null; if (!a) return;
    var href = a.getAttribute('href') || ''; if (!/\.(jpe?g|png|webp|gif)(\?.*)?$/i.test(href)) return;
    e.preventDefault(); var fig = a.closest('figure'); var cap = fig ? fig.querySelector('figcaption') : null;
    open(href, cap ? cap.textContent.trim() : (a.querySelector('img') || {}).alt || '');
  });
})();
