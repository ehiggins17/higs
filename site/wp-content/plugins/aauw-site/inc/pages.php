<?php
if (!defined('ABSPATH')) exit;

function aauw_page_templates_list() { return array('templates/hub.php' => 'Section landing page (photo header)', 'templates/programs.php' => 'Programs calendar'); }

add_action('add_meta_boxes', function () {
    add_meta_box('aauw_page_head', 'Page Header', 'aauw_page_head_box', 'page', 'normal', 'high');
});

function aauw_page_head_box($post) {
    wp_nonce_field('aauw_page_save', 'aauw_page_nonce');
    $g = function ($k) use ($post) { return get_post_meta($post->ID, '_aauw_' . $k, true); };
    $is_front = ((int) get_option('page_on_front') === (int) $post->ID);
    $tpl = get_page_template_slug($post->ID);
    $row = function ($label, $name, $value, $help = '', $type = 'text') {
        echo '<tr><th scope="row"><label for="aauw_' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        if ($type === 'textarea') echo '<textarea class="large-text" rows="3" id="aauw_' . esc_attr($name) . '" name="aauw_pg[' . esc_attr($name) . ']">' . esc_textarea($value) . '</textarea>';
        else echo '<input type="text" class="large-text" id="aauw_' . esc_attr($name) . '" name="aauw_pg[' . esc_attr($name) . ']" value="' . esc_attr($value) . '">';
        if ($help) echo '<p class="description">' . esc_html($help) . '</p>';
        echo '</td></tr>';
    };
    echo '<table class="form-table" role="presentation">';
    if ($is_front) {
        $row('Small label above the headline', 'eyebrow', $g('eyebrow'));
        $row('Headline, line 1', 'hero_line1', $g('hero_line1'));
        $row('Headline, line 2 (highlighted)', 'hero_line2', $g('hero_line2'));
        $row('Headline, line 3', 'hero_line3', $g('hero_line3'));
        $row('Intro paragraph', 'intro', $g('intro'), '', 'textarea');
        $row('"Coming up" label', 'month_eyebrow', $g('month_eyebrow'));
        $row('"Coming up" headline', 'month_title', $g('month_title'));
        $row('"Coming up" paragraph', 'month_intro', $g('month_intro'), '', 'textarea');
        $row('Numbers band', 'stats', $g('stats'), 'One per line as: number | description. Example: 300 | Members across Washtenaw County', 'textarea');
    } else {
        $row('Small label above the headline', 'eyebrow', $g('eyebrow'), 'Example: About');
        $row('Headline', 'headline', $g('headline'), 'Shown large at the top. Leave blank to use the page title. Put *asterisks* around words to highlight them.');
        $row('Intro paragraph', 'intro', $g('intro'), 'The larger paragraph under the headline.', 'textarea');
        echo '<tr><th scope="row"><label for="aauw_headstyle">Header style</label></th><td><select id="aauw_headstyle" name="aauw_pg[headstyle]"><option value=""' . selected($g('headstyle'), '', false) . '>Light</option><option value="navy"' . selected($g('headstyle'), 'navy', false) . '>Navy</option><option value="photo"' . selected($g('headstyle'), 'photo', false) . '>Photo: use the Featured image as the band at the top</option></select><p class="description">For the photo style, set a Featured image on the right. A wide photo, at least 2000 pixels across, works best.</p></td></tr>';
        if ($tpl === 'templates/hub.php') {
            echo '<tr><th colspan="2"><h3 style="margin:1em 0 0">Photo header</h3><p class="description" style="font-weight:400">The photo is this page\'s Featured Image (right-hand column).</p></th></tr>';
            for ($i = 1; $i <= 2; $i++) {
                $row('Button ' . $i . ' wording', 'btn' . $i . '_text', $g('btn' . $i . '_text'));
                $row('Button ' . $i . ' link', 'btn' . $i . '_url', $g('btn' . $i . '_url'), 'Example: /about/history/ or a full web address');
            }
            $row('Facts on the photo', 'facts', $g('facts'), 'Up to four, one per line as: number | label. Example: 1902 | Founded', 'textarea');
            $row('Photo focus', 'img_pos', $g('img_pos'), 'Optional. Which part of the photo to keep in view, as two percentages. Example: 50% 30%');
            $row('"Explore" heading', 'explore_title', $g('explore_title'), 'The grid of cards below the header is built from the main menu. Leave blank to hide it.');
            $row('"Explore" paragraph', 'explore_intro', $g('explore_intro'));
        }
    }
    $row('Search engine description', 'description', $g('description'), 'One or two sentences shown under the page title in Google results.', 'textarea');
    echo '</table>';
}

add_action('save_post_page', function ($post_id) {
    if (!isset($_POST['aauw_page_nonce']) || !wp_verify_nonce($_POST['aauw_page_nonce'], 'aauw_page_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    $in = isset($_POST['aauw_pg']) && is_array($_POST['aauw_pg']) ? wp_unslash($_POST['aauw_pg']) : array();
    $multi = array('intro', 'month_intro', 'stats', 'facts', 'description');
    $allowed = array('eyebrow', 'headline', 'intro', 'hero_line1', 'hero_line2', 'hero_line3', 'month_eyebrow', 'month_title', 'month_intro', 'stats', 'btn1_text', 'btn1_url', 'btn2_text', 'btn2_url', 'facts', 'img_pos', 'explore_title', 'explore_intro', 'description');
    foreach ($allowed as $k) {
        if (!array_key_exists($k, $in)) continue;
        $v = in_array($k, $multi, true) ? ($k === 'intro' || $k === 'month_intro' ? wp_kses_post($in[$k]) : sanitize_textarea_field($in[$k])) : sanitize_text_field($in[$k]);
        update_post_meta($post_id, '_aauw_' . $k, $v);
    }
    if ((int) get_option('page_on_front') !== (int) $post_id) update_post_meta($post_id, '_aauw_headstyle', isset($in['headstyle']) && in_array($in['headstyle'], array('navy', 'photo'), true) ? $in['headstyle'] : '');
});

/** "*word*" → highlighted. Used for headlines. */
function aauw_headline_html($text) {
    $safe = esc_html($text);
    return preg_replace('/\*([^*]+)\*/', '<em class="accent">$1</em>', $safe);
}
function aauw_meta($post_id, $key) { return (string) get_post_meta($post_id, '_aauw_' . $key, true); }
function aauw_pairs($text) {
    $out = array();
    foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
        if (strpos($line, '|') === false) continue;
        list($a, $b) = array_map('trim', explode('|', $line, 2));
        if ($a !== '') $out[] = array($a, $b);
    }
    return $out;
}
