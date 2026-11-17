<?php
if (!defined('ABSPATH')) exit;

/**
 * Help and Guide: the help screen, laid out like "Theme Support" on the AAUW-hosted sites,
 * with the same four tabs: Documentation, FAQs, Video Tutorials, Contact Support.
 */
function aauw_help_faqs() {
    return array(
        array('I made a change but the site looks the same.', 'First, check that you clicked <strong>Update</strong>. Then refresh the public page: hold the Shift key and click your browser\'s reload button.'),
        array('The text I pasted looks strange.', 'Select it and click the eraser in the toolbar (Clear formatting). To paste with no formatting at all, press Ctrl + Shift + V (Cmd + Shift + V on a Mac).'),
        array('A page looks broken after I saved.', 'Edit the page. In the Publish box, click <strong>Browse</strong> beside Revisions, slide back to the earlier version, and click <strong>Restore This Revision</strong>.'),
        array('The homepage is showing the wrong program.', 'Open that program and check its <strong>Date</strong>. The homepage always shows the nearest date in the future.'),
        array('My photo is too big, too small, or on the wrong side.', 'Click the photo in the editor and use the small buttons that appear above it.'),
        array('It says someone else is editing this page.', 'Another editor has it open. Click <strong>Go back</strong> and try later, or give them a call. Taking over can lose their unsaved work.'),
        array('I do not see Appearance or Site Settings.', 'Your login is an Editor, not a Super Editor. Ask the website coordinator if you need menus or settings.'),
        array('My new page is not in the menu.', 'Publishing a page does not add it to a menu. Go to <strong>Appearance</strong>, then <strong>Menus</strong>, tick the page, click <strong>Add to Menu</strong>, drag it into place, and click <strong>Save Menu</strong>.'),
        array('A member says the password does not work.', 'Edit the page, open <strong>Visibility</strong> in the Publish box, and check the password for stray spaces or capital letters. It must match exactly.'),
        array('I was logged out.', 'For safety the site logs you out after two weeks, or after 30 days if you ticked Remember Me. Simply log in again.'),
        array('I cannot move the Home page to the Trash.', 'That is deliberate. The Home page and the Programs page are protected because the rest of the site depends on them.'),
        array('We hold the same event every year. Do I have to type it all again?', 'No. Click <strong>Programs</strong>, move your mouse over last year\'s program, and click <strong>Copy to a new draft</strong>. Change the title, pick the new date, and click <strong>Publish</strong>.'),
        array('Can I prepare something now and have it appear later?', 'Yes. In the Publish box, click <strong>Edit</strong> beside <em>Publish immediately</em>, choose the day and time, click <strong>OK</strong>, and then click <strong>Schedule</strong>.'),
        array('How many people visit the website?', 'Click <strong>Dashboard</strong>, then <strong>Analytics</strong>. Your own visits are not counted while you are logged in.'),
        array('I deleted a page by mistake.', 'It waits in the Trash for 30 days. Click <strong>Pages</strong>, click <strong>Trash</strong> above the list, move your mouse over the page, and click <strong>Restore</strong>.'),
    );
}

function aauw_help_page() {
    $tabs = array('guide' => 'Documentation', 'faq' => 'FAQs', 'videos' => 'Video Tutorials', 'ask' => 'Contact Support');
    $tab = isset($_GET['tab']) && isset($tabs[$_GET['tab']]) ? sanitize_key($_GET['tab']) : 'guide';
    echo '<div class="wrap" style="max-width:900px"><h1>Help and Guide</h1>';
    echo '<h2 class="nav-tab-wrapper">';
    foreach ($tabs as $k => $label) echo '<a class="nav-tab' . ($k === $tab ? ' nav-tab-active' : '') . '" href="' . esc_url(admin_url('admin.php?page=aauw-guide&tab=' . $k)) . '">' . esc_html($label) . '</a>';
    echo '</h2><div style="background:#fff;border:1px solid #c3c4c7;border-top:0;padding:20px 26px 26px;font-size:14px;line-height:1.65">';
    if ($tab === 'guide') {
        $pdf = AAUW_SITE_DIR . 'guide/AAUW-Website-Editors-Guide.pdf';
        if (file_exists($pdf)) {
            echo '<p style="font-size:15px">The <strong>Website Editor\'s Guide</strong> walks through every task one step at a time, with a picture of each screen. It is made to be printed.</p>';
            echo '<p><a class="button button-primary button-hero" href="' . esc_url(AAUW_SITE_URL . 'guide/AAUW-Website-Editors-Guide.pdf') . '" target="_blank" rel="noopener">Open the Website Editor\'s Guide (PDF)</a></p><hr style="margin:22px 0">';
        }
        echo '<h2 style="margin-top:0">The short version</h2>';
        $file = AAUW_SITE_DIR . 'guide.html';
        if (file_exists($file)) echo wp_kses_post(file_get_contents($file));
    } elseif ($tab === 'faq') {
        echo '<h2 style="margin-top:0">Frequently asked questions</h2>';
        foreach (aauw_help_faqs() as $qa) echo '<details style="border-bottom:1px solid #dcdcde;padding:10px 0"><summary style="cursor:pointer;font-weight:600;font-size:15px">' . esc_html($qa[0]) . '</summary><p style="margin:8px 0 2px 18px">' . wp_kses_post($qa[1]) . '</p></details>';
    } elseif ($tab === 'videos') {
        echo '<h2 style="margin-top:0">Video tutorials</h2>';
        $list = AAUW_SITE_DIR . 'guide/videos/videos.json';
        $videos = file_exists($list) ? json_decode(file_get_contents($list), true) : array();
        if ($videos) {
            echo '<p>Short videos recorded on this website. Each one shows the screen while a task is done, with a voice explaining each step and the same words written at the bottom. Turn your sound on, or read along.</p>';
            foreach ($videos as $v) {
                if (empty($v['file']) || !file_exists(AAUW_SITE_DIR . 'guide/videos/' . basename($v['file']))) continue;
                echo '<h3 style="margin:26px 0 4px">' . esc_html($v['title']) . '</h3>';
                if (!empty($v['blurb'])) echo '<p style="margin:0 0 8px;color:#50575e">' . esc_html($v['blurb']) . '</p>';
                echo '<video controls preload="metadata" style="width:100%;max-width:820px;border:1px solid #c3c4c7;border-radius:6px;background:#000" src="' . esc_url(AAUW_SITE_URL . 'guide/videos/' . basename($v['file'])) . '"></video>';
            }
        } else echo '<p>Short videos recorded on this website will appear here. Until then, the <a href="' . esc_url(admin_url('admin.php?page=aauw-guide&tab=guide')) . '">Website Editor\'s Guide</a> shows every step with pictures.</p>';
    } elseif (function_exists('aauw_requests_ready') && aauw_requests_ready()) {
        echo '<h2 style="margin-top:0">Ask for help or a change</h2><p style="font-size:15px">Use the <strong>Help Desk</strong> to ask a question, report a problem, or ask for a change. You can follow each request from start to finish, and you get an email at every step.</p>';
        echo '<p><a class="button button-primary button-hero" href="' . esc_url(admin_url('admin.php?page=aauw-requests&tab=new')) . '">Submit a request</a> <a class="button button-hero" href="' . esc_url(admin_url('admin.php?page=aauw-requests')) . '">See my requests</a></p>';
    } else {
        if (isset($_GET['sent'])) echo '<div class="notice notice-success inline"><p>Thank you. Your request was sent, and a copy is saved under Messages.</p></div>';
        echo '<h2 style="margin-top:0">Ask for help</h2><p>Describe what you were trying to do and what happened. Your request goes to the site administrator, and a copy is kept under <strong>Messages</strong>.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="aauw_help">';
        wp_nonce_field('aauw_help', 'aauw_help_nonce');
        echo '<p><label for="aauw-help-page"><strong>Which page or program?</strong></label><br><input type="text" id="aauw-help-page" name="help_page" class="regular-text" style="width:100%;max-width:520px"></p>';
        echo '<p><label for="aauw-help-msg"><strong>What happened?</strong></label><br><textarea id="aauw-help-msg" name="help_message" rows="7" style="width:100%;max-width:700px" required></textarea></p>';
        echo '<p><button class="button button-primary button-large" type="submit">Send request</button></p></form>';
    }
    echo '</div></div>';
}

add_action('admin_post_aauw_help', function () {
    if (!current_user_can('edit_pages') || !isset($_POST['aauw_help_nonce']) || !wp_verify_nonce($_POST['aauw_help_nonce'], 'aauw_help')) wp_die('Not allowed.');
    $u = wp_get_current_user();
    $where = sanitize_text_field(wp_unslash($_POST['help_page'] ?? ''));
    $msg = sanitize_textarea_field(wp_unslash($_POST['help_message'] ?? ''));
    if ($msg !== '') {
        $subject = 'Help request from ' . $u->display_name;
        $body = "From: {$u->display_name} ({$u->user_email})\nPage or program: {$where}\n\n{$msg}";
        wp_insert_post(array('post_type' => 'aauw_message', 'post_status' => 'private', 'post_title' => $subject, 'post_content' => nl2br(esc_html($body))));
        wp_mail(get_option('admin_email'), '[AAUW Ann Arbor website] ' . $subject, $body, array('Reply-To: ' . $u->display_name . ' <' . $u->user_email . '>'));
    }
    wp_safe_redirect(admin_url('admin.php?page=aauw-guide&tab=ask&sent=1')); exit;
});
