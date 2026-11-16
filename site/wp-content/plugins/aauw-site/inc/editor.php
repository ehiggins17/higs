<?php
if (!defined('ABSPATH')) exit;

/**
 * Pages and programs keep their HTML exactly as written (no automatic paragraph rewriting),
 * so designed sections survive a trip through the Visual editor.
 */
function aauw_raw_types() { return array('page', 'program'); }

add_filter('wp_editor_settings', function ($settings, $editor_id) {
    if ($editor_id === 'content') {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && in_array($screen->post_type, aauw_raw_types(), true)) $settings['wpautop'] = false;
    }
    return $settings;
}, 10, 2);

add_filter('tiny_mce_before_init', function ($init) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && in_array($screen->post_type, aauw_raw_types(), true)) {
        $init['wpautop'] = false;
        $init['indent'] = true;
        $init['verify_html'] = false;
        $init['extended_valid_elements'] = '*[*]';
        $init['valid_children'] = '+a[div|h1|h2|h3|h4|h5|h6|p|span|img|strong|em|figure|figcaption|ul|ol|li|svg],+body[style],+span[span|strong|em|img|svg],+dl[div|dt|dd],+div[dt|dd]';
        $init['entity_encoding'] = 'raw';
        $init['block_formats'] = 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4';
        // Pasted text (Word, email, other websites) keeps its structure, bold, links and lists, but loses foreign fonts, sizes and Office markup.
        $init['paste_preprocess'] = aauw_paste_cleaner_js();
    }
    return $init;
});

add_filter('the_content', function ($content) {
    if (in_array(get_post_type(), aauw_raw_types(), true)) remove_filter('the_content', 'wpautop');
    return $content;
}, 1);
add_filter('the_content', function ($content) {
    if (!has_filter('the_content', 'wpautop')) add_filter('the_content', 'wpautop');
    return $content;
}, 99);
add_filter('run_wptexturize', '__return_false');

/** Use the familiar Word-style editor for pages and programs (matches AAUW National's Site Resources). */
add_filter('use_block_editor_for_post_type', function ($use, $type) {
    return in_array($type, array('page', 'program', 'post', 'aauw_message'), true) ? false : $use;
}, 20, 2);

/* Toolbar extras: the "Add Button" button, and text colours limited to the AAUW palette. */
add_filter('mce_external_plugins', function ($plugins) {
    $plugins['aauw_tools'] = AAUW_SITE_URL . 'js/editor-tools.js?v=' . substr(md5_file(AAUW_SITE_DIR . 'js/editor-tools.js'), 0, 8);
    return $plugins;
});
add_filter('mce_buttons', function ($buttons) {
    foreach (array('aauw_button', 'aauw_blocks') as $b) {
        if (in_array($b, $buttons, true)) continue;
        $at = array_search('table', $buttons, true);
        if ($at === false) $buttons[] = $b; else array_splice($buttons, $at, 0, array($b));
    }
    return $buttons;
}, 10001);
add_filter('tiny_mce_before_init', function ($init) {
    $init['textcolor_map'] = wp_json_encode(array('0B2346', 'AAUW navy', '1B528A', 'AAUW blue', 'C23A09', 'AAUW red', '16181D', 'Black', '5C6370', 'Grey', 'FFFFFF', 'White'));
    $init['textcolor_rows'] = 1; $init['textcolor_cols'] = 6;
    $init['aauw_placeholder'] = wp_make_link_relative(get_theme_file_uri('art/placeholder.svg'));
    return $init;
}, 20);

/**
 * Runs on everything pasted into the Visual editor. Keeps structure (paragraphs, bold, links, lists, tables)
 * and removes foreign fonts, sizes, fixed widths and Office markup. Wrapped in try/catch: a paste is never blocked.
 */
function aauw_paste_cleaner_js() {
    return <<<'JS'
function(plugin, args){ try {
  var d = document.createElement('div'); d.innerHTML = args.content;
  var strip = /^(font|font-family|font-size|line-height|letter-spacing|mso-.*|tab-stops|text-indent|margin.*|background.*|width|min-width|max-width|height|position|left|top|float)$/i;
  var all = Array.prototype.slice.call(d.getElementsByTagName('*'));
  all.forEach(function(el){
    var style = el.getAttribute('style');
    if (style !== null) { var keep = []; style.split(';').forEach(function(r){ var k = r.split(':')[0].trim(); if (k && !strip.test(k)) keep.push(r.trim()); }); if (keep.length) el.setAttribute('style', keep.join('; ')); else el.removeAttribute('style'); }
    var cls = el.getAttribute('class'); if (cls && /Mso|gmail_|Apple-/i.test(cls)) el.removeAttribute('class');
    if (el.tagName === 'TABLE' || el.tagName === 'TD' || el.tagName === 'TH' || el.tagName === 'COL') { el.removeAttribute('width'); el.removeAttribute('height'); }
  });
  var unwrap = function(el){ if (!el.parentNode) return; while (el.firstChild) el.parentNode.insertBefore(el.firstChild, el); el.parentNode.removeChild(el); };
  all.forEach(function(el){ var t = el.tagName.toLowerCase(); if (t === 'font' || t === 'o:p' || (t === 'span' && !el.attributes.length)) unwrap(el); });
  args.content = d.innerHTML;
} catch (e) { if (window.console) console.warn('paste cleanup skipped', e); } }
JS;
}
