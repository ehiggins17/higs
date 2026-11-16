<?php
if (!defined('ABSPATH')) exit;

/**
 * Help Desk (formerly Website Requests): editors ask for changes (words, photos, design, new videos, fixes) and follow each one
 * from Submitted to In progress to Completed, without leaving the dashboard. The request desk itself is the
 * SupportCandy plugin; this screen frames it inside wp-admin and adds the questions and answers.
 */
function aauw_requests_page_id() { return (int) get_option('aauw_requests_page_id'); }
function aauw_requests_ready() { return class_exists('WPSC_Functions') && aauw_requests_page_id(); }

add_action('admin_menu', function () {
    add_menu_page('Help Desk', 'Help Desk', 'edit_pages', 'aauw-requests', 'aauw_requests_screen', 'dashicons-format-chat', 98);
});

/* The request page is for logged-in editors only, never listed or indexed. */
add_action('template_redirect', function () {
    if (aauw_requests_page_id() && is_page(aauw_requests_page_id()) && !is_user_logged_in()) auth_redirect();
});
add_action('wp_head', function () {
    if (aauw_requests_page_id() && is_page(aauw_requests_page_id())) echo '<meta name="robots" content="noindex, nofollow">' . "\n";
}, 1);
add_filter('wp_sitemaps_posts_query_args', function ($args) {
    if (aauw_requests_page_id()) $args['post__not_in'] = array_merge($args['post__not_in'] ?? array(), array(aauw_requests_page_id()));
    return $args;
});

/* Inside the dashboard the page is shown without the public site's header and footer. */
function aauw_requests_is_embed() { return isset($_GET['aauw-embed']) && aauw_requests_page_id() && is_page(aauw_requests_page_id()); }
add_filter('show_admin_bar', function ($show) { return (isset($_GET['aauw-embed'])) ? false : $show; });
add_filter('template_include', function ($tpl) {
    return aauw_requests_is_embed() ? AAUW_SITE_DIR . 'inc/requests-embed.php' : $tpl;
}, 99);

/* Say "request" instead of "ticket" everywhere the request desk shows text. */
add_filter('gettext_supportcandy', function ($t) {
    return strtr($t, array('Tickets' => 'Requests', 'tickets' => 'requests', 'Ticket' => 'Request', 'ticket' => 'request', 'TICKETS' => 'REQUESTS', 'TICKET' => 'REQUEST'));
}, 20);
add_filter('ngettext_supportcandy', function ($t) {
    return strtr($t, array('Tickets' => 'Requests', 'tickets' => 'requests', 'Ticket' => 'Request', 'ticket' => 'request'));
}, 20);

function aauw_requests_faqs() {
    return array(
        'Making a request' => array(
            array('What can I ask for?', 'Anything to do with the website: changing words, dates or links, adding or swapping photos, design and layout changes, a new page, a new how-to video, or something that is not working.'),
            array('Should I make a request, or change it myself?', 'Small things you are comfortable with, like fixing a typo, updating a program, or adding a photo, you can do yourself. The Website Editor\'s Guide shows how. Use a request for anything you are unsure about, design changes, new features, or anything you would rather have done for you.'),
            array('How do I write a good request?', 'Say which page it is on, what should change, and the exact wording if there is any. One change per request makes each one easier to follow.'),
            array('Can I attach photos or documents?', 'Yes. Use the attachment button under <strong>Details</strong>. Photos straight from your phone are fine.'),
            array('Something is wrong on the site and it cannot wait.', 'Choose <strong>Urgent</strong> under <strong>How soon?</strong>, for example a wrong date for this week\'s meeting. Urgent requests are handled first.'),
            array('Can I ask for a new how-to video?', 'Yes. Choose <strong>New how-to video</strong> as the type and describe the task you would like to see. It will be added under Help and Guide when it is ready.'),
        ),
        'After you submit' => array(
            array('What happens after I submit?', 'You get an email right away confirming the request was received. You get another when work starts, and another when it is completed. Every request also stays listed here.'),
            array('What do the statuses mean?', '<strong>Submitted:</strong> received and in the queue. <strong>In progress:</strong> being worked on. <strong>Waiting on you:</strong> there is a question for you; open the request to answer it. <strong>Completed:</strong> done; please take a look on the website.'),
            array('How long will it take?', 'Small changes are usually done within a few days, and urgent requests come first. If a request is bigger, you will get a reply with what is involved.'),
            array('Can I add more information later?', 'Yes. Open the request from the list and add a reply. You can attach more files there too.'),
            array('It is marked Completed but something is not right.', 'Open the request and reply with what still needs fixing. It goes back into the queue.'),
            array('Can I cancel a request?', 'Open it and add a reply saying you no longer need it, or click <strong>Close</strong>.'),
        ),
        'Good to know' => array(
            array('Who can see my requests?', 'Only you and the people who maintain the website.'),
            array('I did not get an email.', 'Check your spam or junk folder, and mark the message as not spam so the next one arrives normally. Your requests and their status are always listed here, email or not.'),
            array('Where do I find the instructions and videos?', 'Open <strong>Help and Guide</strong> in the left menu for the Website Editor\'s Guide, the how-to videos, and answers to common editing questions.'),
        ),
    );
}

function aauw_requests_counts() {
    global $wpdb; $p = $wpdb->prefix . 'psmsc_';
    $cid = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}customers WHERE user=%d", get_current_user_id()));
    if (!$cid) return array('open' => 0, 'done' => 0);
    return array(
        'open' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}tickets WHERE customer=%d AND is_active=1 AND status<>4", $cid)),
        'done' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}tickets WHERE customer=%d AND is_active=1 AND status=4", $cid)),
    );
}

function aauw_requests_screen() {
    $tabs = array('home' => 'Welcome', 'new' => 'Submit a request', 'list' => 'My requests', 'faq' => 'Questions and answers');
    $tab = isset($_GET['tab']) && isset($tabs[$_GET['tab']]) ? sanitize_key($_GET['tab']) : (isset($_GET['ticket-id']) ? 'list' : 'home');
    $url = function ($t) { return esc_url(admin_url('admin.php?page=aauw-requests&tab=' . $t)); };
    $u = wp_get_current_user();
    $first = $u->first_name ? $u->first_name : $u->display_name;
    $c = aauw_requests_ready() ? aauw_requests_counts() : array('open' => 0, 'done' => 0);
    ?>
<div class="wrap hd">
<style>
.hd{max-width:1120px;--navy:#0B2346;--navy2:#14335F;--red:#C23A09;--salmon:#F0A07E;--ink:#161B26;--muted:#50575e;--line:#dcdfe5;--mist:#f4f6f9}
.hd *{box-sizing:border-box}
.hd-hero{position:relative;overflow:hidden;background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 100%);color:#fff;border-radius:14px;padding:34px 38px 30px;margin:14px 0 0}
.hd-hero:after{content:"";position:absolute;right:-60px;top:-60px;width:260px;height:260px;border-radius:50%;background:rgba(240,160,126,.14)}
.hd-kick{font-size:11px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--salmon);margin:0 0 8px}
.hd-hero h1{color:#fff;font-size:34px;line-height:1.1;font-weight:600;margin:0 0 8px;padding:0}
.hd-hero p.sub{font-size:16px;line-height:1.55;color:#DCE4EF;max-width:640px;margin:0 0 20px}
.hd-btn{display:inline-flex;align-items:center;gap:8px;padding:11px 20px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;transition:transform .12s,box-shadow .12s}
.hd-btn:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(0,0,0,.18)}
.hd-btn.primary{background:var(--red);color:#fff}.hd-btn.primary:hover,.hd-btn.primary:focus{color:#fff}
.hd-btn.ghost{border:1.5px solid rgba(255,255,255,.55);color:#fff}.hd-btn.ghost:hover,.hd-btn.ghost:focus{color:#fff}
.hd-btn.dark{background:var(--navy);color:#fff}.hd-btn.dark:hover,.hd-btn.dark:focus{color:#fff}
.hd-tabs{display:flex;gap:4px;margin:22px 0 0;border-bottom:1px solid var(--line)}
.hd-tabs a{padding:10px 16px;font-size:14px;font-weight:600;color:var(--muted);text-decoration:none;border-bottom:3px solid transparent;margin-bottom:-1px}
.hd-tabs a.on{color:var(--navy);border-bottom-color:var(--red)}.hd-tabs a:hover{color:var(--navy)}
.hd-tabs .badge{display:inline-block;min-width:20px;padding:1px 7px;margin-left:6px;border-radius:999px;background:var(--red);color:#fff;font-size:11px;line-height:18px;text-align:center}
.hd-panel{background:#fff;border:1px solid var(--line);border-top:0;border-radius:0 0 12px 12px;padding:26px 28px 30px}
.hd h2.sec{font-size:19px;color:var(--navy);margin:0 0 4px}.hd p.secsub{color:var(--muted);margin:0 0 16px;font-size:14px}
.hd-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:0 0 28px}
.hd-step{background:var(--mist);border-radius:12px;padding:20px 20px 18px;position:relative}
.hd-step .n{width:34px;height:34px;border-radius:50%;background:var(--red);color:#fff;font-weight:700;font-size:15px;display:flex;align-items:center;justify-content:center;margin-bottom:10px}
.hd-step h3{margin:0 0 6px;font-size:15px;color:var(--ink)}.hd-step p{margin:0;font-size:13.5px;line-height:1.6;color:#3c434a}
.hd-status{display:flex;flex-wrap:wrap;gap:10px 18px;align-items:center;background:#fff;border:1px solid var(--line);border-radius:12px;padding:14px 18px;margin:0 0 28px}
.hd-chip{display:inline-block;padding:4px 11px;border-radius:999px;font-size:12px;font-weight:700}
.hd-status span.d{font-size:13px;color:var(--muted)}
.hd-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:0 0 28px}
.hd-card{display:block;border:1px solid var(--line);border-radius:12px;padding:18px;text-decoration:none;color:var(--ink);background:#fff;transition:border-color .12s,box-shadow .12s,transform .12s}
.hd-card:hover{border-color:var(--navy);box-shadow:0 6px 18px rgba(11,35,70,.10);transform:translateY(-2px);color:var(--ink)}
.hd-card .ic{width:40px;height:40px;border-radius:10px;background:#FBEDE7;color:var(--red);display:flex;align-items:center;justify-content:center;margin-bottom:12px}
.hd-card .ic .dashicons{font-size:22px;width:22px;height:22px}
.hd-card b{display:block;font-size:15px;margin-bottom:4px}.hd-card span.t{font-size:13px;line-height:1.5;color:var(--muted)}
.hd details{border-bottom:1px solid var(--line);padding:13px 2px}.hd details:first-of-type{border-top:1px solid var(--line)}
.hd summary{cursor:pointer;font-weight:600;font-size:15px;color:var(--ink);list-style:none;display:flex;justify-content:space-between;gap:12px}
.hd summary::-webkit-details-marker{display:none}.hd summary:after{content:"+";font-size:20px;line-height:1;color:var(--red)}.hd details[open] summary:after{content:"\2212"}
.hd details p{margin:8px 0 2px;font-size:14px;line-height:1.65;max-width:820px;color:#3c434a}
.hd .faq h2.grp{font-size:13px;letter-spacing:.14em;text-transform:uppercase;color:var(--red);margin:26px 0 8px}.hd .faq h2.grp:first-child{margin-top:0}
.hd iframe{width:100%;border:0;min-height:420px;display:block}
.hd .tip{background:#FBEDE7;border-radius:10px;padding:12px 16px;font-size:13.5px;margin:0 0 18px;color:#5a2a12}
@media (max-width:960px){.hd-steps{grid-template-columns:1fr}.hd-cards{grid-template-columns:1fr 1fr}}
</style>
<div class="hd-hero">
  <p class="hd-kick">AAUW Ann Arbor website</p>
  <h1>Help Desk</h1>
  <p class="sub"><?php echo esc_html($first ? "Hi $first. " : ''); ?>Need something changed on the website, a new how-to video, or help with a problem? Send a request here and follow it from start to finish.</p>
  <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center"><a class="hd-btn primary" href="<?php echo $url('new'); ?>"><span class="dashicons dashicons-plus-alt2"></span> Submit a request</a>
  <a class="hd-btn ghost" href="<?php echo $url('list'); ?>">My requests<?php echo $c['open'] ? ' (' . (int) $c['open'] . ' open)' : ''; ?></a></div>
</div>
<nav class="hd-tabs">
<?php foreach ($tabs as $k => $label) {
    $b = ($k === 'list' && $c['open']) ? '<span class="badge">' . (int) $c['open'] . '</span>' : '';
    echo '<a class="' . ($k === $tab ? 'on' : '') . '" href="' . $url($k) . '">' . esc_html($label) . $b . '</a>';
} ?>
</nav>
<div class="hd-panel">
<?php if ($tab === 'home') { ?>
  <h2 class="sec">How it works</h2>
  <p class="secsub">Three steps. You'll get an email at each one, and every request stays listed under My requests.</p>
  <div class="hd-steps">
    <div class="hd-step"><div class="n">1</div><h3>Submit a request</h3><p>Click <b>Submit a request</b>. Say what you need and which page it's on, choose the type and how soon, and attach any photos or files.</p></div>
    <div class="hd-step"><div class="n">2</div><h3>We get to work</h3><p>You get an email confirming it was received, and another when work starts. If there's a question, the request shows <b>Waiting on you</b>.</p></div>
    <div class="hd-step"><div class="n">3</div><h3>Check it's right</h3><p>When it's done you get a <b>Completed</b> email. Take a look on the website. If anything isn't quite right, reply on the request.</p></div>
  </div>
  <div class="hd-status">
    <b style="font-size:13px;color:#161B26">What the statuses mean</b>
    <span><span class="hd-chip" style="background:#1B528A;color:#fff">Submitted</span> <span class="d">received, in the queue</span></span>
    <span><span class="hd-chip" style="background:#C27A0A;color:#fff">In progress</span> <span class="d">being worked on</span></span>
    <span><span class="hd-chip" style="background:#FFE6C7;color:#7A3E00">Waiting on you</span> <span class="d">there's a question for you</span></span>
    <span><span class="hd-chip" style="background:#2E7D32;color:#fff">Completed</span> <span class="d">done, take a look</span></span>
  </div>
  <h2 class="sec">What would you like to do?</h2>
  <p class="secsub">Pick one to get started.</p>
  <div class="hd-cards">
    <a class="hd-card" href="<?php echo $url('new'); ?>"><div class="ic"><span class="dashicons dashicons-edit"></span></div><b>Submit a request</b><span class="t">Changes to words, photos or design, a new video, or a fix.</span></a>
    <a class="hd-card" href="<?php echo $url('list'); ?>"><div class="ic"><span class="dashicons dashicons-list-view"></span></div><b>My requests</b><span class="t"><?php echo $c['open'] || $c['done'] ? esc_html($c['open'] . ' open, ' . $c['done'] . ' completed.') : 'See the status of everything you\'ve asked for.'; ?></span></a>
    <a class="hd-card" href="<?php echo esc_url(admin_url('admin.php?page=aauw-guide&tab=videos')); ?>"><div class="ic"><span class="dashicons dashicons-video-alt3"></span></div><b>How-to videos</b><span class="t">Watch common tasks done step by step.</span></a>
    <a class="hd-card" href="<?php echo esc_url(admin_url('admin.php?page=aauw-guide')); ?>"><div class="ic"><span class="dashicons dashicons-book-alt"></span></div><b>Editor's Guide</b><span class="t">Every task with a picture of each screen.</span></a>
  </div>
  <h2 class="sec">Popular questions</h2>
  <p class="secsub">More under <a href="<?php echo $url('faq'); ?>">Questions and answers</a>.</p>
  <?php $all = aauw_requests_faqs(); $pick = array($all['Making a request'][0], $all['Making a request'][1], $all['After you submit'][0], $all['After you submit'][2], $all['Good to know'][1]);
  foreach ($pick as $qa) echo '<details><summary>' . esc_html($qa[0]) . '</summary><p>' . wp_kses_post($qa[1]) . '</p></details>'; ?>
<?php } elseif ($tab === 'faq') {
    echo '<div class="faq">';
    foreach (aauw_requests_faqs() as $group => $items) {
        echo '<h2 class="grp">' . esc_html($group) . '</h2>';
        foreach ($items as $qa) echo '<details><summary>' . esc_html($qa[0]) . '</summary><p>' . wp_kses_post($qa[1]) . '</p></details>';
    }
    echo '<p style="margin-top:24px"><a class="hd-btn dark" href="' . $url('new') . '">Submit a request</a></p></div>';
} elseif (!aauw_requests_ready()) {
    echo '<p>The Help Desk is being set up. Please check back shortly.</p>';
} else {
    if ($tab === 'new') echo '<div class="tip"><b>Tip:</b> say which page it\'s on, what should change, and the exact wording if there is any. One change per request is easiest to follow.</div>';
    $args = array('aauw-embed' => 1, 'wpsc-section' => $tab === 'new' ? 'new-ticket' : 'ticket-list');
    if ($tab === 'list' && isset($_GET['ticket-id'])) $args['ticket-id'] = absint($_GET['ticket-id']);
    $src = add_query_arg($args, get_permalink(aauw_requests_page_id()));
    echo '<iframe id="aauw-req-frame" title="Help Desk" src="' . esc_url($src) . '"></iframe>';
    echo '<script>(function(){var f=document.getElementById("aauw-req-frame");function fit(){try{var d=f.contentWindow.document;var h=Math.max(d.body.scrollHeight,d.documentElement.scrollHeight);if(h>200)f.style.height=(h+30)+"px";}catch(e){}}f.addEventListener("load",fit);setInterval(fit,700);})();</script>';
} ?>
</div></div>
<?php
}

/* Send request emails right away instead of waiting for the five-minute queue (this site has few visitors to trigger it). */
add_action('shutdown', function () {
    if (!class_exists('WPSC_Email_Notifications')) return;
    $a = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
    if ((defined('DOING_AJAX') && DOING_AJAX && strpos($a, 'wpsc_') === 0) || (defined('WP_CLI') && WP_CLI)) WPSC_Email_Notifications::send_background_emails();
}, 1);

/* Dashboard: a small "Help Desk" box with the editor's open requests. */
add_action('wp_dashboard_setup', function () {
    if (!aauw_requests_ready()) return;
    wp_add_dashboard_widget('aauw_requests', 'Help Desk', function () {
        global $wpdb; $p = $wpdb->prefix . 'psmsc_';
        $u = wp_get_current_user();
        $cid = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}customers WHERE user=%d", $u->ID));
        $rows = $cid ? $wpdb->get_results($wpdb->prepare("SELECT t.id,t.subject,s.name status FROM {$p}tickets t LEFT JOIN {$p}statuses s ON s.id=t.status WHERE t.customer=%d AND t.is_active=1 ORDER BY t.date_updated DESC LIMIT 5", $cid)) : array();
        if ($rows) {
            echo '<ul style="margin:0">';
            foreach ($rows as $r) echo '<li style="display:flex;justify-content:space-between;gap:10px;border-bottom:1px solid #f0f0f1;padding:6px 0"><a href="' . esc_url(admin_url('admin.php?page=aauw-requests&tab=list&ticket-id=' . (int) $r->id)) . '">#' . (int) $r->id . ' ' . esc_html($r->subject) . '</a><span style="color:#50575e">' . esc_html($r->status) . '</span></li>';
            echo '</ul>';
        } else echo '<p style="font-size:14px">Need something changed on the website? Send a request to the Help Desk and follow it from start to finish.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=aauw-requests&tab=new')) . '">Submit a request</a> <a class="button" href="' . esc_url(admin_url('admin.php?page=aauw-requests')) . '">See all</a></p>';
    });
}, 1000);
