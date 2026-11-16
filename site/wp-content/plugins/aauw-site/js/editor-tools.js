/* AAUW Ann Arbor editor tools: the "Add Button" toolbar button (as on the AAUW-hosted sites). */
(function () {
  if (!window.tinymce) return;
  tinymce.PluginManager.add('aauw_tools', function (editor) {
    function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    // "Add Block": ready-made layouts, like the Modular Sections on the AAUW-hosted sites, but visible while you edit.
    var ph = (editor.settings.aauw_placeholder || '') ;
    var img = '<img src="' + ph + '" alt="" width="800" height="600" />';
    var blocks = [
      ['Picture on the left, words on the right', '<div class="split"><figure>' + img + '</figure><div><h2>Heading</h2><p>Write a few sentences here.</p></div></div>'],
      ['Picture on the right, words on the left', '<div class="split split--reverse"><figure>' + img + '</figure><div><h2>Heading</h2><p>Write a few sentences here.</p></div></div>'],
      ['Row of three photos with captions', '<div class="photo-row"><figure>' + img + '<figcaption>Caption</figcaption></figure><figure>' + img + '<figcaption>Caption</figcaption></figure><figure>' + img + '<figcaption>Caption</figcaption></figure></div>'],
      ['List of people', '<ul class="people"><li>' + img + '<strong>Name</strong><span>Position</span><a href="mailto:name@example.org">name@example.org</a></li><li>' + img + '<strong>Name</strong><span>Position</span><a href="mailto:name@example.org">name@example.org</a></li><li>' + img + '<strong>Name</strong><span>Position</span><a href="mailto:name@example.org">name@example.org</a></li></ul>'],
      ['Three boxes side by side', '<div class="cards"><div class="card"><h3>Title</h3><p>A sentence or two.</p></div><div class="card"><h3>Title</h3><p>A sentence or two.</p></div><div class="card"><h3>Title</h3><p>A sentence or two.</p></div></div>'],
      ['Highlighted note', '<div class="callout"><p><strong>Please note.</strong> Write the note here.</p></div>'],
      ['Section heading with a line above', '<hr /><h2>Section heading</h2>'],
      ['Two buttons', '<p class="btn-row"><a class="btn btn--red" href="/contact/">First button</a> <a class="btn btn--outline" href="/programs/">Second button</a></p>']
    ];
    editor.addButton('aauw_blocks', {
      type: 'menubutton', text: 'Add Block', icon: false, tooltip: 'Add a ready-made layout',
      menu: blocks.map(function (b) { return { text: b[0], onclick: function () { editor.insertContent(b[1] + '<p>&nbsp;</p>'); } }; })
    });
    editor.addButton('aauw_button', {
      text: 'Add Button', icon: false, tooltip: 'Add a button that links somewhere',
      onclick: function () {
        var sel = editor.selection.getContent({ format: 'text' });
        editor.windowManager.open({
          title: 'Add a button',
          body: [
            { type: 'textbox', name: 'text', label: 'Words on the button', value: sel || '' },
            { type: 'textbox', name: 'url', label: 'Web address it goes to', value: '' },
            { type: 'listbox', name: 'style', label: 'Style', values: [{ text: 'Red (main action)', value: 'btn btn--red' }, { text: 'Navy', value: 'btn btn--navy' }, { text: 'Outline', value: 'btn btn--outline' }] },
            { type: 'checkbox', name: 'blank', label: ' ', text: 'Open in a new tab (for other websites)' }
          ],
          onsubmit: function (e) {
            var d = e.data; if (!d.text || !d.url) { editor.windowManager.alert('Please type the words for the button and the web address.'); return false; }
            var url = d.url.trim(); if (!/^(https?:|mailto:|tel:|\/|#)/i.test(url)) url = 'https://' + url;
            editor.insertContent('<p><a class="' + d.style + '" href="' + esc(url) + '"' + (d.blank ? ' target="_blank" rel="noopener"' : '') + '>' + esc(d.text) + '</a></p>');
          }
        });
      }
    });
  });
})();
