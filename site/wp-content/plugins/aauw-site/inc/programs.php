<?php
if (!defined('ABSPATH')) exit;

add_action('init', 'aauw_register_program_type');
function aauw_register_program_type() {
    register_post_type('program', array(
        'labels' => array(
            'name' => 'Programs', 'singular_name' => 'Program', 'add_new' => 'Add New', 'add_new_item' => 'Add New Program',
            'edit_item' => 'Edit Program', 'new_item' => 'New Program', 'view_item' => 'View Program', 'search_items' => 'Search Programs',
            'not_found' => 'No programs found', 'all_items' => 'All Programs', 'menu_name' => 'Programs',
        ),
        'public' => true, 'has_archive' => false, 'show_in_rest' => true, 'menu_position' => 5, 'menu_icon' => 'dashicons-calendar-alt',
        'rewrite' => array('slug' => 'programs', 'with_front' => false),
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'revisions'),
    ));
    register_taxonomy('program_type', 'program', array(
        'labels' => array('name' => 'Program Types', 'singular_name' => 'Program Type', 'menu_name' => 'Program Types', 'add_new_item' => 'Add New Program Type'),
        'public' => true, 'publicly_queryable' => true, 'show_ui' => true, 'show_in_nav_menus' => true, 'show_admin_column' => true, 'hierarchical' => true, 'show_in_rest' => true,
        'rewrite' => array('slug' => 'program-type', 'with_front' => false),
    ));
}

function aauw_program_fields() {
    return array(
        'date'       => array('Date', 'date', 'The day of the program.'),
        'end_date'   => array('Last day (multi-day events only)', 'date', 'Leave blank for a one-day program.'),
        'time_text'  => array('Time', 'text', 'Example: Lunch 11:30 am (by reservation) · Speaker 12:30 pm (free)'),
        'speaker'    => array('Speaker / short line', 'text', 'Example: Eli Neiburger, Director, Ann Arbor District Library'),
        'reserve'    => array('Lunch reservations', 'check', 'Show a lunch reservation form on this program\'s page. Requests are emailed to the address in Site Settings and kept under Messages.'),
        'reserve_by' => array('Reservations close after', 'date', 'Optional. The form disappears after this day. Leave blank to keep it open until the program.'),
        'location'   => array('Location', 'text', 'Leave blank to use the usual venue from Site Settings.'),
        'location_address' => array('Location address', 'text', 'Leave blank to use the usual venue address.'),
        'map_link'   => array('Link to location (map)', 'url', 'Leave blank to use the usual map link.'),
        'card_word'  => array('Big word on the photo', 'text', 'One or two words shown large on the homepage card. Example: Your library'),
        'card_sub'   => array('Small line on the photo', 'text', 'Example: With AADL Director Eli Neiburger'),
        'link_override' => array('Send visitors to a different page', 'text', 'Optional. Example: /stem/ for the Mah-Jongg Tournament. Leave blank to use this program\'s own page.'),
        'link_text'  => array('Link wording', 'text', 'Optional. Default: About this program'),
    );
}

add_action('add_meta_boxes', function () {
    add_meta_box('aauw_program_info', 'Program Information', 'aauw_program_box', 'program', 'normal', 'high');
});
function aauw_program_box($post) {
    wp_nonce_field('aauw_program_save', 'aauw_program_nonce');
    echo '<p style="margin-top:0">Fill in the date and the short details. The homepage, the calendar, and the menu update on their own. Put the full description in the big box below, pick a Program Type on the right, and set a Featured Image for the photo.</p><table class="form-table" role="presentation">';
    foreach (aauw_program_fields() as $k => $f) {
        $v = get_post_meta($post->ID, '_aauw_' . $k, true);
        if ($k === 'location') aauw_program_links_rows($post);   // same place as on the AAUW-hosted sites: after the time, before the location
        echo '<tr><th scope="row"><label for="aauw_p_' . esc_attr($k) . '">' . esc_html($f[0]) . '</label></th><td>';
        if ($f[1] === 'check') echo '<label><input type="checkbox" id="aauw_p_' . esc_attr($k) . '" name="aauw_p[' . esc_attr($k) . ']" value="1"' . checked($v, '1', false) . '> Yes</label>';
        else echo '<input type="' . ($f[1] === 'date' ? 'date' : 'text') . '" id="aauw_p_' . esc_attr($k) . '" name="aauw_p[' . esc_attr($k) . ']" value="' . esc_attr($v) . '" class="' . ($f[1] === 'date' ? '' : 'large-text') . '">';
        echo '<p class="description">' . esc_html($f[2]) . '</p></td></tr>';
    }
    echo '</table>';
}
add_action('save_post_program', function ($post_id) {
    if (!isset($_POST['aauw_program_nonce']) || !wp_verify_nonce($_POST['aauw_program_nonce'], 'aauw_program_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    foreach (aauw_program_fields() as $k => $f) {
        $v = isset($_POST['aauw_p'][$k]) ? sanitize_text_field(wp_unslash($_POST['aauw_p'][$k])) : '';
        if ($f[1] === 'date' && $v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) $v = '';
        if ($f[1] === 'check') $v = $v === '1' ? '1' : '';
        update_post_meta($post_id, '_aauw_' . $k, $v);
    }
    // Registration / Important Links rows
    $links = array();
    if (isset($_POST['aauw_link_title']) && is_array($_POST['aauw_link_title'])) {
        foreach ($_POST['aauw_link_title'] as $i => $t) {
            $t = sanitize_text_field(wp_unslash($t)); $u = isset($_POST['aauw_link_url'][$i]) ? sanitize_text_field(wp_unslash($_POST['aauw_link_url'][$i])) : '';
            if ($t === '' && $u === '') continue;
            if ($u !== '' && !preg_match('#^(https?://|mailto:|tel:|/|\#)#i', $u)) $u = 'https://' . $u;
            $links[] = array('title' => $t !== '' ? $t : 'More information', 'url' => $u, 'blank' => (isset($_POST['aauw_link_blank'][$i]) && $_POST['aauw_link_blank'][$i] === '1') ? '1' : '');
        }
    }
    update_post_meta($post_id, '_aauw_links', $links);
    update_post_meta($post_id, '_aauw_reg_text', $links ? $links[0]['title'] : '');
    update_post_meta($post_id, '_aauw_reg_url', $links ? $links[0]['url'] : '');
});

/** A program's registration / important links (falls back to the single link older programs stored). */
function aauw_program_links($post_id) {
    $links = get_post_meta($post_id, '_aauw_links', true);
    if (is_array($links) && $links) return array_values(array_filter($links, function ($l) { return !empty($l['url']); }));
    $u = (string) get_post_meta($post_id, '_aauw_reg_url', true);
    if ($u === '') return array();
    return array(array('title' => (string) get_post_meta($post_id, '_aauw_reg_text', true) ?: 'Register', 'url' => $u, 'blank' => preg_match('#^https?://#i', $u) ? '1' : ''));
}
function aauw_program_links_rows($post) {
    $links = aauw_program_links($post->ID); if (!$links) $links = array(array('title' => '', 'url' => '', 'blank' => ''));
    $row = function ($l) {
        return '<div class="aauw-linkrow" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 8px;padding:8px 10px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px">'
            . '<input type="text" name="aauw_link_title[]" value="' . esc_attr($l['title']) . '" placeholder="Link Title, for example Reserve lunch" style="flex:1 1 200px">'
            . '<input type="text" name="aauw_link_url[]" value="' . esc_attr($l['url']) . '" placeholder="Link, for example https://... or /contact/" style="flex:2 1 260px">'
            . '<select name="aauw_link_blank[]" aria-label="Open link in"><option value=""' . selected($l['blank'], '', false) . '>Same window</option><option value="1"' . selected($l['blank'], '1', false) . '>New window</option></select>'
            . '<button type="button" class="button-link aauw-linkrow-remove" style="color:#b32d2e">Remove</button></div>';
    };
    echo '<tr><th scope="row">Registration / Important Links</th><td><div id="aauw-linkrows">';
    foreach ($links as $l) echo $row($l);
    echo '</div><button type="button" class="button" id="aauw-linkrow-add">Add Row</button>';
    echo '<p class="description">Buttons shown with the program. The first one also appears on the homepage card. If on-site, select same window. If off-site, select new window.</p>';
    echo '<script type="text/html" id="aauw-linkrow-tpl">' . $row(array('title' => '', 'url' => '', 'blank' => '')) . '</script>';
    echo "<script>(function(){var box=document.getElementById('aauw-linkrows');document.getElementById('aauw-linkrow-add').addEventListener('click',function(){var d=document.createElement('div');d.innerHTML=document.getElementById('aauw-linkrow-tpl').innerHTML;box.appendChild(d.firstChild);});box.addEventListener('click',function(e){if(e.target.classList.contains('aauw-linkrow-remove')){var r=e.target.closest('.aauw-linkrow');if(box.querySelectorAll('.aauw-linkrow').length>1)r.remove();else r.querySelectorAll('input').forEach(function(i){i.value='';});}});})();</script>";
    echo '</td></tr>';
}

/** Admin list: show the program photo and date, and sort by date. */
add_filter('manage_program_posts_columns', function ($cols) {
    $new = array();
    foreach ($cols as $k => $v) { if ($k === 'date') continue; if ($k === 'title') $new['aauw_thumb'] = 'Photo'; $new[$k] = $v; if ($k === 'title') $new['aauw_date'] = 'Program date'; }
    return $new;
});
add_action('manage_program_posts_custom_column', function ($col, $post_id) {
    if ($col === 'aauw_thumb') { echo has_post_thumbnail($post_id) ? get_the_post_thumbnail($post_id, array(64, 44), array('style' => 'border-radius:4px;object-fit:cover;width:64px;height:44px')) : '<span style="color:#8c8f94">None</span>'; return; }
    if ($col !== 'aauw_date') return;
    $d = get_post_meta($post_id, '_aauw_date', true);
    echo $d ? esc_html(date_i18n('D, M j, Y', strtotime($d))) : '<span style="color:#b32d2e">No date set</span>';
}, 10, 2);
add_filter('manage_edit-program_sortable_columns', function ($cols) { $cols['aauw_date'] = 'aauw_date'; return $cols; });
add_action('pre_get_posts', function ($q) {
    if (!is_admin() || !$q->is_main_query() || $q->get('post_type') !== 'program') return;
    if (!$q->get('orderby') || $q->get('orderby') === 'aauw_date') {
        $q->set('meta_key', '_aauw_date'); $q->set('orderby', 'meta_value');
        if (!$q->get('order')) $q->set('order', 'DESC');
    }
});

/* ---------- Helpers used by the theme ---------- */
function aauw_program($post) {
    $post = get_post($post);
    $m = function ($k) use ($post) { return (string) get_post_meta($post->ID, '_aauw_' . $k, true); };
    $terms = get_the_terms($post, 'program_type');
    $term = ($terms && !is_wp_error($terms)) ? $terms[0] : null;
    $slug = $term ? $term->slug : 'branch-program';
    $kind = $slug === 'fundraiser' ? 'fundraiser' : ($slug === 'book-sale' ? 'sale' : 'program');
    $date = $m('date'); $ts = $date ? strtotime($date . ' 12:00:00') : 0;
    $end = $m('end_date');
    $override = $m('link_override');
    $out = array(
        'id' => $post->ID, 'title' => get_the_title($post), 'date' => $date, 'ts' => $ts, 'end' => $end,
        'kind' => $kind, 'type' => $term ? $term->name : 'Branch program',
        'time' => $m('time_text'), 'speaker' => $m('speaker'),
        'location' => $m('location') !== '' ? $m('location') : aauw_opt('venue_name'),
        'address' => $m('location_address') !== '' ? $m('location_address') : ($m('location') !== '' ? '' : aauw_opt('venue_address')),
        'map' => $m('map_link') !== '' ? $m('map_link') : ($m('location') !== '' ? '' : aauw_opt('venue_map')),
        'links' => aauw_program_links($post->ID),
        'reg_text' => '', 'reg_url' => '',
        'reserve' => $m('reserve') === '1', 'reserve_by' => $m('reserve_by'),
        'card_word' => $m('card_word'), 'card_sub' => $m('card_sub'),
        'url' => $override !== '' ? $override : get_permalink($post),
        'link_text' => $m('link_text') !== '' ? $m('link_text') : 'About this program',
        'excerpt' => $post->post_password !== '' ? 'For branch members. Enter the members password to read more.' : (has_excerpt($post) ? get_the_excerpt($post) : wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), 45)),
        'past' => $date && (($end ?: $date) < current_time('Y-m-d')),
    );
    if ($out['links']) { $out['reg_text'] = $out['links'][0]['title']; $out['reg_url'] = $out['links'][0]['url']; }
    $today = current_time('Y-m-d');
    $out['reserve_open'] = $out['reserve'] && $date && !$out['past'] && $post->post_password === '' && ($out['reserve_by'] === '' || $today <= $out['reserve_by']);
    return $out;
}
function aauw_programs($args = array()) {
    $q = new WP_Query(array_merge(array(
        'post_type' => 'program', 'post_status' => 'publish', 'posts_per_page' => 100, 'no_found_rows' => true,
        'meta_key' => '_aauw_date', 'orderby' => 'meta_value', 'order' => 'ASC',
    ), $args));
    return array_map('aauw_program', $q->posts);
}
function aauw_upcoming_programs($limit = 3, $only_kind = '') {
    $today = current_time('Y-m-d'); $out = array();
    foreach (aauw_programs() as $p) {
        if (!$p['date'] || ($p['end'] ?: $p['date']) < $today) continue;
        if ($only_kind && $p['kind'] !== $only_kind) continue;
        $out[] = $p; if (count($out) >= $limit) break;
    }
    return $out;
}
