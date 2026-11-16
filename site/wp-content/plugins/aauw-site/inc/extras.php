<?php
if (!defined('ABSPATH')) exit;

/* =====================================================================================
 * 1. "Copy to a new draft": start next month's program from an existing one.
 * ===================================================================================== */
function aauw_copy_program_url($post_id) {
    return wp_nonce_url(admin_url('admin-post.php?action=aauw_copy_program&post=' . (int) $post_id), 'aauw_copy_program_' . (int) $post_id);
}
add_filter('post_row_actions', function ($actions, $post) {
    if ($post->post_type === 'program' && current_user_can('edit_posts')) $actions['aauw_copy'] = '<a href="' . esc_url(aauw_copy_program_url($post->ID)) . '">Copy to a new draft</a>';
    return $actions;
}, 10, 2);
add_action('post_submitbox_misc_actions', function ($post) {
    if ($post->post_type !== 'program' || $post->post_status === 'auto-draft') return;
    echo '<div class="misc-pub-section"><span class="dashicons dashicons-admin-page" style="color:#8c8f94;margin-right:4px"></span><a href="' . esc_url(aauw_copy_program_url($post->ID)) . '">Copy to a new program</a></div>';
});
add_action('admin_post_aauw_copy_program', function () {
    $id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    if (!$id || get_post_type($id) !== 'program' || !current_user_can('edit_posts') || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'aauw_copy_program_' . $id)) wp_die('Not allowed.');
    $src = get_post($id);
    $new = wp_insert_post(wp_slash(array('post_type' => 'program', 'post_status' => 'draft', 'post_title' => 'Copy of ' . $src->post_title, 'post_content' => $src->post_content, 'post_excerpt' => $src->post_excerpt)), true);
    if (is_wp_error($new)) wp_die('The copy could not be made.');
    foreach (get_post_meta($id) as $k => $vals) {
        if (strpos($k, '_aauw_') !== 0 && $k !== '_thumbnail_id') continue;
        if (in_array($k, array('_aauw_date', '_aauw_end_date', '_aauw_reserve_by'), true)) continue;   // the new program needs its own dates
        foreach ($vals as $v) add_post_meta($new, $k, wp_slash(maybe_unserialize($v)));
    }
    $terms = wp_get_object_terms($id, 'program_type', array('fields' => 'ids'));
    if ($terms && !is_wp_error($terms)) wp_set_object_terms($new, $terms, 'program_type');
    wp_safe_redirect(admin_url('post.php?post=' . $new . '&action=edit&aauw_copied=1')); exit;
});
add_action('admin_notices', function () {
    if (!empty($_GET['aauw_copied'])) echo '<div class="notice notice-success is-dismissible"><p><strong>This is a copy, saved as a draft.</strong> Change the title, pick the new date, update the details, and click Publish. Nothing shows on the site until you do.</p></div>';
});

/* =====================================================================================
 * 2. "Add to my calendar": a calendar file for each program, and a Google Calendar link.
 * ===================================================================================== */
function aauw_program_times($p) {
    // Returns array(start DateTime, end DateTime, all_day bool) in the site's time zone, read from the Time field as typed.
    $tz = wp_timezone(); $day = $p['date']; $last = $p['end'] ?: $p['date'];
    preg_match_all('/\b(\d{1,2})(?::(\d{2}))?\s*(a\.?m\.?|p\.?m\.?)/i', (string) $p['time'], $m, PREG_SET_ORDER);
    $at = function ($d, $h, $min, $ap) use ($tz) { $h = (int) $h % 12; if (stripos($ap, 'p') === 0) $h += 12; return new DateTime(sprintf('%s %02d:%02d:00', $d, $h, (int) $min), $tz); };
    if ($m && $last === $day) {
        $start = $at($day, $m[0][1], $m[0][2] ?? 0, $m[0][3]);
        $end = count($m) > 1 ? $at($day, $m[count($m) - 1][1], $m[count($m) - 1][2] ?? 0, $m[count($m) - 1][3])->modify('+1 hour') : (clone $start)->modify('+2 hours');
        if ($end <= $start) $end = (clone $start)->modify('+2 hours');
        return array($start, $end, false);
    }
    return array(new DateTime($day . ' 00:00:00', $tz), (new DateTime($last . ' 00:00:00', $tz))->modify('+1 day'), true);
}
function aauw_program_calendar_links($p) {
    if (!$p['date'] || $p['past']) return null;
    list($s, $e, $all) = aauw_program_times($p);
    $utc = new DateTimeZone('UTC');
    $dates = $all ? $s->format('Ymd') . '/' . $e->format('Ymd') : (clone $s)->setTimezone($utc)->format('Ymd\THis\Z') . '/' . (clone $e)->setTimezone($utc)->format('Ymd\THis\Z');
    $where = trim($p['location'] . ($p['address'] ? ', ' . $p['address'] : ''));
    $google = add_query_arg(array('action' => 'TEMPLATE', 'text' => rawurlencode(wp_strip_all_tags($p['title'])), 'dates' => $dates, 'location' => rawurlencode($where), 'details' => rawurlencode(get_permalink($p['id']))), 'https://calendar.google.com/calendar/render');
    return array('ics' => add_query_arg('aauw_ics', (int) $p['id'], home_url('/')), 'google' => $google);
}
add_action('template_redirect', function () {
    if (empty($_GET['aauw_ics'])) return;
    $id = (int) $_GET['aauw_ics'];
    if (get_post_type($id) !== 'program' || get_post_status($id) !== 'publish') { status_header(404); exit; }
    $p = aauw_program($id); if (!$p['date']) { status_header(404); exit; }
    list($s, $e, $all) = aauw_program_times($p); $utc = new DateTimeZone('UTC');
    $esc = function ($t) { return str_replace(array('\\', ';', ',', "\r\n", "\n"), array('\\\\', '\;', '\,', '\n', '\n'), wp_strip_all_tags((string) $t)); };
    $locked = get_post_field('post_password', $id) !== '';
    $desc = trim(($p['speaker'] ? $p['speaker'] . "\n" : '') . ($p['time'] ? $p['time'] . "\n" : '') . ($locked ? '' : $p['excerpt'] . "\n") . get_permalink($id));
    $lines = array('BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//AAUW Ann Arbor//Programs//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
        'UID:program-' . $id . '@' . wp_parse_url(home_url(), PHP_URL_HOST), 'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        $all ? 'DTSTART;VALUE=DATE:' . $s->format('Ymd') : 'DTSTART:' . (clone $s)->setTimezone($utc)->format('Ymd\THis\Z'),
        $all ? 'DTEND;VALUE=DATE:' . $e->format('Ymd') : 'DTEND:' . (clone $e)->setTimezone($utc)->format('Ymd\THis\Z'),
        'SUMMARY:' . $esc($p['title']), 'LOCATION:' . $esc(trim($p['location'] . ($p['address'] ? ', ' . $p['address'] : ''))), 'DESCRIPTION:' . $esc($desc), 'URL:' . get_permalink($id), 'END:VEVENT', 'END:VCALENDAR');
    $out = '';
    foreach ($lines as $l) { while (strlen($l) > 73) { $cut = 73; while ($cut > 60 && (ord($l[$cut]) & 0xC0) === 0x80) $cut--; $out .= substr($l, 0, $cut) . "\r\n "; $l = substr($l, $cut); } $out .= $l . "\r\n"; }
    nocache_headers(); header('Content-Type: text/calendar; charset=utf-8'); header('Content-Disposition: attachment; filename="aauw-program-' . $p['date'] . '.ics"');
    echo $out; exit;
});

/* =====================================================================================
 * 3. Photos look after themselves: very large uploads are scaled down, and the sizes the
 *    site actually shows are saved in a smaller modern format. Nothing to click.
 * ===================================================================================== */
add_filter('big_image_size_threshold', function () { return 2000; });
add_filter('wp_editor_set_quality', function ($q, $mime = '') { return $mime === 'image/webp' ? 80 : 82; }, 10, 2);
add_filter('image_editor_output_format', function ($formats) {
    if (wp_image_editor_supports(array('mime_type' => 'image/webp'))) $formats['image/jpeg'] = 'image/webp';
    return $formats;
});

/* =====================================================================================
 * 4. Visitor statistics: let editors see them (the Koko Analytics plugin keeps the numbers on this site, without cookies).
 * ===================================================================================== */
add_action('admin_init', function () {
    if (get_option('aauw_stats_caps') === '1') return;
    foreach (array('editor', 'super_editor') as $r) { $role = get_role($r); if ($role) $role->add_cap('view_koko_analytics'); }
    update_option('aauw_stats_caps', '1');
});
