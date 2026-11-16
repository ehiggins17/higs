<?php
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    register_post_type('aauw_message', array(
        'labels' => array('name' => 'Messages', 'singular_name' => 'Message', 'all_items' => 'All Messages', 'menu_name' => 'Messages', 'edit_item' => 'Message', 'search_items' => 'Search Messages', 'not_found' => 'No messages yet'),
        'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'menu_position' => 26, 'menu_icon' => 'dashicons-email-alt',
        'supports' => array('title', 'editor'), 'capability_type' => 'post', 'map_meta_cap' => true,
        'capabilities' => array('create_posts' => 'do_not_allow'),
    ));
});

function aauw_form_shortcode($atts) {
    $atts = shortcode_atts(array('type' => 'contact'), $atts);
    $type = $atts['type'] === 'membership' ? 'membership' : 'contact';
    $action = esc_url(admin_url('admin-post.php'));
    ob_start();
    if ($type === 'contact') { ?>
<div class="notice" id="form-success" hidden tabindex="-1">Thank you. Your message was sent to the branch and we will reply soon.</div>
<form name="contact" method="POST" action="<?php echo $action; ?>">
  <input type="hidden" name="action" value="aauw_form"><input type="hidden" name="form_type" value="contact">
  <p class="honeypot"><label>Leave this field empty <input name="company" tabindex="-1" autocomplete="off"></label></p>
  <div class="field field--row">
    <div><label for="c-first">First name</label><input id="c-first" name="first_name" type="text" required autocomplete="given-name"></div>
    <div><label for="c-last">Last name</label><input id="c-last" name="last_name" type="text" required autocomplete="family-name"></div>
  </div>
  <div class="field field--row">
    <div><label for="c-email">Email</label><input id="c-email" name="email" type="email" required autocomplete="email"></div>
    <div><label for="c-phone">Phone (optional)</label><input id="c-phone" name="phone" type="tel" autocomplete="tel"></div>
  </div>
  <div class="field">
    <label for="c-to">I would like to contact</label>
    <select id="c-to" name="recipient" required>
      <option value="">Choose one</option>
      <?php foreach (aauw_opt_lines('recipients') as $r) echo '<option>' . esc_html($r) . '</option>'; ?>
    </select>
  </div>
  <div class="field"><label for="c-msg">Message</label><textarea id="c-msg" name="message" required></textarea></div>
  <button class="btn btn--red btn--lg" type="submit">Send message</button>
</form>
<?php } else { ?>
<div class="notice" id="form-success" hidden tabindex="-1">Thank you. We received your note and will be in touch soon.</div>
<form name="membership-interest" method="POST" action="<?php echo $action; ?>">
  <input type="hidden" name="action" value="aauw_form"><input type="hidden" name="form_type" value="membership">
  <p class="honeypot"><label>Leave this field empty <input name="company" tabindex="-1" autocomplete="off"></label></p>
  <div class="field"><label for="mi-name">Your name</label><input id="mi-name" name="name" type="text" required autocomplete="name"></div>
  <div class="field"><label for="mi-email">Email</label><input id="mi-email" name="email" type="email" required autocomplete="email"></div>
  <div class="field"><label for="mi-phone">Phone <span class="hint" style="display:inline">(if you'd like a call)</span></label><input id="mi-phone" name="phone" type="tel" autocomplete="tel"></div>
  <div class="field"><label for="mi-interest">What interests you most?</label>
    <select id="mi-interest" name="interest">
      <option>Monthly programs</option><option>Interest groups</option><option>The Used Book Sale</option><option>Scholarships and STEM</option><option>Not sure yet</option>
    </select></div>
  <div class="field"><label class="check"><input type="checkbox" name="guest" value="yes"> Invite me as a guest to the next branch program</label></div>
  <button class="btn btn--red" type="submit">Send</button>
</form>
<?php }
    return ob_get_clean();
}
add_shortcode('aauw_form', 'aauw_form_shortcode');

add_action('admin_post_nopriv_aauw_form', 'aauw_handle_form');
add_action('admin_post_aauw_form', 'aauw_handle_form');
function aauw_handle_form() {
    $type = (isset($_POST['form_type']) && in_array($_POST['form_type'], array('membership', 'reserve'), true)) ? $_POST['form_type'] : 'contact';
    $back = $type === 'membership' ? home_url('/join/?sent=1#interest') : home_url('/contact/?sent=1');
    $program = null;
    if ($type === 'reserve') {
        $pid = isset($_POST['program_id']) ? (int) $_POST['program_id'] : 0;
        $program = ($pid && get_post_type($pid) === 'program' && get_post_status($pid) === 'publish') ? aauw_program($pid) : null;
        if (!$program || !$program['reserve_open']) { wp_safe_redirect(home_url('/programs/')); exit; }
        $back = add_query_arg('sent', '1', get_permalink($pid)) . '#reserve';
    }
    $f = function ($k) { return isset($_POST[$k]) ? trim(wp_unslash($_POST[$k])) : ''; };
    // Spam checks: hidden field must be empty, and no more than a few messages per hour from one address.
    if ($f('company') !== '') { wp_safe_redirect($back); exit; }
    $ip_key = 'aauw_form_' . md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'x');
    $count = (int) get_transient($ip_key);
    if ($count >= 6) { wp_safe_redirect($back); exit; }
    set_transient($ip_key, $count + 1, HOUR_IN_SECONDS);

    $email = sanitize_email($f('email'));
    $name = $type !== 'contact' ? sanitize_text_field($f('name')) : trim(sanitize_text_field($f('first_name')) . ' ' . sanitize_text_field($f('last_name')));
    if (!$email || !is_email($email) || $name === '') { wp_safe_redirect(remove_query_arg('sent', $back)); exit; }
    $phone = sanitize_text_field($f('phone'));
    if ($type === 'reserve') {
        $n = max(1, min(12, (int) $f('lunches')));
        $subject = 'Lunch reservation (' . $n . '): ' . $name . ', ' . wp_strip_all_tags($program['title']);
        $lines = array('Program: ' . wp_strip_all_tags($program['title']), 'Date: ' . date_i18n('l, F j, Y', $program['ts']), 'Lunches: ' . $n, 'Name: ' . $name, 'Email: ' . $email, 'Phone: ' . $phone, 'Guests: ' . sanitize_text_field($f('guests')), '', sanitize_textarea_field($f('note')));
    } elseif ($type === 'membership') {
        $subject = 'Membership interest: ' . $name;
        $lines = array('Name: ' . $name, 'Email: ' . $email, 'Phone: ' . $phone, 'Most interested in: ' . sanitize_text_field($f('interest')), 'Wants a guest invitation: ' . ($f('guest') === 'yes' ? 'Yes' : 'No'));
    } else {
        $to_whom = sanitize_text_field($f('recipient'));
        $subject = 'Website message for ' . ($to_whom ?: 'the branch') . ': ' . $name;
        $lines = array('For: ' . $to_whom, 'Name: ' . $name, 'Email: ' . $email, 'Phone: ' . $phone, '', sanitize_textarea_field($f('message')));
    }
    $body = implode("\n", $lines);
    $kses_on = has_filter('content_save_pre', 'wp_filter_post_kses');
    wp_insert_post(array('post_type' => 'aauw_message', 'post_status' => 'private', 'post_title' => $subject, 'post_content' => nl2br(esc_html($body))));
    $to = ($type === 'reserve' && aauw_opt('reserve_email')) ? aauw_opt('reserve_email') : (aauw_opt('notify_email') ?: get_option('admin_email'));
    wp_mail($to, '[AAUW Ann Arbor website] ' . $subject, $body . "\n\n-- \nSent from the form on " . home_url('/'), array('Reply-To: ' . $name . ' <' . $email . '>'));
    wp_safe_redirect($back); exit;
}

/** The lunch reservation form shown on a program's own page when reservations are switched on for it. */
function aauw_reserve_form($p) {
    ob_start(); ?>
<section class="section section--mist" id="reserve" aria-labelledby="reserve-h">
  <div class="wrap prose">
    <h2 id="reserve-h">Reserve lunch</h2>
    <p><?php echo esc_html(aauw_opt('reserve_note')); ?><?php if ($p['reserve_by']) echo ' Reservations close after ' . esc_html(date_i18n('l, F j', strtotime($p['reserve_by'] . ' 12:00:00'))) . '.'; ?></p>
    <div class="notice" id="form-success" hidden tabindex="-1">Thank you. Your lunch request was sent to the branch, and you will hear back soon.</div>
    <form name="reserve" method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="aauw_form"><input type="hidden" name="form_type" value="reserve"><input type="hidden" name="program_id" value="<?php echo (int) $p['id']; ?>">
      <p class="honeypot"><label>Leave this field empty <input name="company" tabindex="-1" autocomplete="off"></label></p>
      <div class="field field--row">
        <div><label for="r-name">Your name</label><input id="r-name" name="name" type="text" required autocomplete="name"></div>
        <div><label for="r-email">Email</label><input id="r-email" name="email" type="email" required autocomplete="email"></div>
      </div>
      <div class="field field--row">
        <div><label for="r-phone">Phone (optional)</label><input id="r-phone" name="phone" type="tel" autocomplete="tel"></div>
        <div><label for="r-n">Number of lunches</label><select id="r-n" name="lunches"><?php for ($i = 1; $i <= 8; $i++) echo '<option>' . $i . '</option>'; ?></select></div>
      </div>
      <div class="field"><label for="r-guests">Names of guests (optional)</label><input id="r-guests" name="guests" type="text"></div>
      <div class="field"><label for="r-note">Anything we should know? (optional)</label><textarea id="r-note" name="note" rows="3"></textarea></div>
      <button class="btn btn--red btn--lg" type="submit">Send lunch request</button>
    </form>
  </div>
</section>
<?php return ob_get_clean();
}
