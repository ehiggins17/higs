<?php
if (!defined('ABSPATH')) exit;

/** Defaults for every site-wide detail. Edited at Dashboard → Site Settings. */
function aauw_setting_fields() {
    return array(
        // The first two groups follow the Site Settings screen on the AAUW-hosted sites.
        'Main links and address' => array(
            'join_url'    => array('Join Us Link', '/join/', 'text'),
            'donate_url'  => array('Donate Link', '/give/', 'text'),
            'address'     => array('Branch Address', '162 Kingbrook Ave, Ann Arbor, MI 48103', 'text'),
            'mail'        => array('Mailing address', 'P.O. Box 2806, Ann Arbor, MI 48106', 'text'),
            'phone'       => array('Phone', '844-973-6287', 'text'),
        ),
        'Social Media' => array(
            'social'      => array('Social channels', 'Facebook | https://www.facebook.com/AAUWAnnArbor/', 'social'),
        ),
        'Organization' => array(
            'legal_name'  => array('Legal name', 'American Association of University Women – Ann Arbor Inc.', 'text'),
            'short_name'  => array('Branch name', 'AAUW Ann Arbor Inc. (MI) Branch', 'text'),
            'ein'         => array('EIN (tax ID)', '38-6083405', 'text'),
            'status'      => array('Nonprofit status line', '501(c)(3) nonprofit organization. Contributions are tax-deductible to the extent allowed by law.', 'text'),
        ),
        'Other links' => array(
            'official_url' => array('AAUW-hosted site address', 'https://annarbor-mi.aauw.net/', 'text'),
            'official_label' => array('AAUW-hosted site link label', 'Official AAUW-hosted site', 'text'),
            'shop_url'     => array('Merchandise store', 'https://matohash.com/collections/aauw-ann-arbor-storefront', 'text'),
        ),
        'Header and footer text' => array(
            'utility_tag'  => array('Top bar line', 'The Ann Arbor branch of the American Association of University Women', 'text'),
            'footer_tag'   => array('Footer tagline', 'Advancing equity for women and girls through advocacy, education, philanthropy, and research. Ann Arbor, Michigan, since 1902.', 'textarea'),
            'footer_meet'  => array('Footer meeting note', 'third Wednesday, September–May, Ann Arbor City Club, 1830 Washtenaw Ave. Programs are free and open to the public.', 'textarea'),
        ),
        'Programs' => array(
            'venue_name'    => array('Usual venue', 'Ann Arbor City Club', 'text'),
            'venue_address' => array('Usual venue address', '1830 Washtenaw Ave', 'text'),
            'venue_map'     => array('Usual venue map link', 'https://maps.google.com/?q=Ann+Arbor+City+Club+1830+Washtenaw+Ave+Ann+Arbor+MI', 'text'),
            'lunch_note'    => array('Usual lunch note', 'Lunch by reservation, then the talk', 'text'),
            'talk_note'     => array('Usual talk note', 'Talk is free and open to the public', 'text'),
            'reserve_note'  => array('Note above the lunch reservation form', 'Lunch is by reservation. Send your request here and the branch will reply with the details.', 'textarea'),
        ),
        'Homepage' => array(
            'ticker' => array('Scrolling banner (one interest group per line)', "Afternoon Book Review\nGreat Decisions\nAfternoon Bridge\nGardening\nMystery Readers\nMah-Jongg", 'textarea'),
        ),
        'Contact forms' => array(
            'notify_email' => array('Send form messages to (email)', '', 'text'),
            'reserve_email' => array('Send lunch reservations to (email)', '', 'text'),
            'recipients'   => array('Contact form "I would like to contact" choices (one per line)', "President\nMembership Vice Presidents\nAAUW Funds Directors\nDues Treasurer\nFinance Treasurer\nBook Sale Co-Chairs\nSTEM Initiatives / Digital Divas\nWebsite and Social Media Coordinator\nCollege/University Liaison\nNCCWSL Director\nDirectory advertising\nSomething else", 'textarea'),
        ),
    );
}

function aauw_opt($key) {
    static $defaults = null;
    if ($defaults === null) {
        $defaults = array();
        foreach (aauw_setting_fields() as $group) foreach ($group as $k => $f) $defaults[$k] = $f[1];
    }
    $saved = get_option('aauw_settings', array());
    if (is_array($saved) && isset($saved[$key]) && $saved[$key] !== '') return $saved[$key];
    return isset($defaults[$key]) ? $defaults[$key] : '';
}
function aauw_phone_href() { return 'tel:+1' . preg_replace('/\D+/', '', aauw_opt('phone')); }
function aauw_opt_lines($key) { return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) aauw_opt($key))))); }

add_action('admin_menu', function () {
    add_menu_page('Site Settings', 'Site Settings', 'manage_aauw_settings', 'aauw-settings', 'aauw_settings_page', 'dashicons-admin-settings', 61);
});

function aauw_settings_page() {
    if (!current_user_can('manage_aauw_settings')) return;
    if (isset($_POST['aauw_settings_nonce']) && wp_verify_nonce($_POST['aauw_settings_nonce'], 'aauw_settings_save')) {
        $out = array();
        foreach (aauw_setting_fields() as $group) {
            foreach ($group as $k => $f) {
                if ($f[2] === 'social') {
                    $lines = array();
                    if (isset($_POST['aauw_social_channel']) && is_array($_POST['aauw_social_channel'])) foreach ($_POST['aauw_social_channel'] as $i => $ch) {
                        $ch = sanitize_text_field(wp_unslash($ch)); $u = isset($_POST['aauw_social_url'][$i]) ? esc_url_raw(trim(wp_unslash($_POST['aauw_social_url'][$i]))) : '';
                        if ($u !== '' && in_array($ch, aauw_social_channels(), true)) $lines[] = $ch . ' | ' . $u;
                    }
                    $out[$k] = $lines ? implode("\n", $lines) : 'none';
                    continue;
                }
                $raw = isset($_POST['aauw'][$k]) ? str_replace(array("\r\n", "\r"), "\n", wp_unslash($_POST['aauw'][$k])) : '';
                $out[$k] = $f[2] === 'textarea' ? sanitize_textarea_field($raw) : sanitize_text_field($raw);
            }
        }
        update_option('aauw_settings', $out);
        echo '<div class="notice notice-success is-dismissible"><p>Site settings saved. The changes are live on every page.</p></div>';
    }
    echo '<div class="wrap"><h1>Site Settings</h1><p style="max-width:760px">These details appear in the header, footer, and organization block on every page. Change them here once and the whole site updates.</p><form method="post">';
    wp_nonce_field('aauw_settings_save', 'aauw_settings_nonce');
    foreach (aauw_setting_fields() as $title => $group) {
        echo '<h2>' . esc_html($title) . '</h2><table class="form-table" role="presentation">';
        foreach ($group as $k => $f) {
            $val = aauw_opt($k);
            echo '<tr><th scope="row"><label for="aauw_' . esc_attr($k) . '">' . esc_html($f[0]) . '</label></th><td>';
            if ($f[2] === 'social') { aauw_social_rows(); echo '</td></tr>'; continue; }
            if ($f[2] === 'textarea') echo '<textarea class="large-text" rows="' . ($k === 'recipients' || $k === 'ticker' ? 10 : 3) . '" id="aauw_' . esc_attr($k) . '" name="aauw[' . esc_attr($k) . ']">' . esc_textarea($val) . '</textarea>';
            else echo '<input class="regular-text" style="width:100%;max-width:640px" type="text" id="aauw_' . esc_attr($k) . '" name="aauw[' . esc_attr($k) . ']" value="' . esc_attr($val) . '">';
            echo '</td></tr>';
        }
        echo '</table>';
    }
    submit_button('Save settings');
    echo '</form></div>';
}

/* ---------- Social media rows (channel + address), as on the AAUW-hosted sites ---------- */
function aauw_social_channels() { return array('Facebook', 'Instagram', 'LinkedIn', 'YouTube', 'X', 'Bluesky', 'Threads'); }
function aauw_social() {
    $saved = get_option('aauw_settings', array());
    if (is_array($saved) && !isset($saved['social']) && !empty($saved['facebook_url'])) $raw = 'Facebook | ' . $saved['facebook_url'];
    else $raw = aauw_opt('social');
    $out = array();
    foreach (preg_split('/\r\n|\r|\n/', (string) $raw) as $line) {
        $parts = array_map('trim', explode('|', $line, 2));
        if (count($parts) === 2 && $parts[1] !== '' && in_array($parts[0], aauw_social_channels(), true)) $out[] = array('channel' => $parts[0], 'url' => $parts[1]);
    }
    return $out;
}
function aauw_social_rows() {
    $rows = aauw_social(); if (!$rows) $rows = array(array('channel' => 'Facebook', 'url' => ''));
    $row = function ($r) {
        $h = '<div class="aauw-socialrow" style="display:flex;gap:8px;align-items:center;margin:0 0 8px"><select name="aauw_social_channel[]" aria-label="Social channel">';
        foreach (aauw_social_channels() as $c) $h .= '<option' . selected($r['channel'], $c, false) . '>' . esc_html($c) . '</option>';
        return $h . '</select><input type="url" name="aauw_social_url[]" value="' . esc_attr($r['url']) . '" placeholder="Social URL, for example https://www.facebook.com/..." style="flex:1;max-width:520px"><button type="button" class="button-link aauw-socialrow-remove" style="color:#b32d2e">Remove</button></div>';
    };
    echo '<div id="aauw-socialrows">'; foreach ($rows as $r) echo $row($r); echo '</div><button type="button" class="button" id="aauw-socialrow-add">Add Row</button>';
    echo '<p class="description">Each channel appears as a small icon in the top bar and in the footer.</p>';
    echo '<script type="text/html" id="aauw-socialrow-tpl">' . $row(array('channel' => 'Instagram', 'url' => '')) . '</script>';
    echo "<script>(function(){var box=document.getElementById('aauw-socialrows');document.getElementById('aauw-socialrow-add').addEventListener('click',function(){var d=document.createElement('div');d.innerHTML=document.getElementById('aauw-socialrow-tpl').innerHTML;box.appendChild(d.firstChild);});box.addEventListener('click',function(e){if(e.target.classList.contains('aauw-socialrow-remove')){e.target.closest('.aauw-socialrow').remove();}});})();</script>";
}
