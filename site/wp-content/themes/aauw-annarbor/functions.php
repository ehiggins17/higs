<?php
if (!defined('ABSPATH')) exit;

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array('search-form', 'gallery', 'caption', 'style', 'script'));
    register_nav_menus(array(
        'primary' => 'Main menu (top of every page)',
        'utility' => 'Utility menu (small links in the top bar)',
        'footer-explore' => 'Footer: Explore',
        'footer-involved' => 'Footer: Get involved',
        'footer-connect' => 'Footer: Connect',
    ));
    add_editor_style(array('css/site.css', 'css/wp.css', 'css/editor.css'));
    add_image_size('aauw-card', 800, 520, true);
    add_image_size('aauw-wide', 1600, 0, false);
});

function aauw_asset_ver($rel) { $f = get_theme_file_path($rel); return file_exists($f) ? substr(md5_file($f), 0, 8) : '1'; }

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('aauw-fonts', 'https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;500;600;700;800&family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400&display=swap', array(), null);
    wp_enqueue_style('aauw-site', get_theme_file_uri('css/site.css'), array(), aauw_asset_ver('css/site.css'));
    wp_enqueue_style('aauw-wp', get_theme_file_uri('css/wp.css'), array('aauw-site'), aauw_asset_ver('css/wp.css'));
    wp_enqueue_script('aauw-site', get_theme_file_uri('js/site.js'), array(), aauw_asset_ver('js/site.js'), array('strategy' => 'defer', 'in_footer' => true));
    wp_enqueue_script('aauw-extras', get_theme_file_uri('js/wp-extras.js'), array(), aauw_asset_ver('js/wp-extras.js'), array('strategy' => 'defer', 'in_footer' => true));
    wp_dequeue_style('wp-block-library'); wp_dequeue_style('classic-theme-styles'); wp_dequeue_style('global-styles');
}, 20);
add_filter('wp_resource_hints', function ($urls, $rel) {
    if ($rel === 'preconnect') { $urls[] = 'https://fonts.googleapis.com'; $urls[] = array('href' => 'https://fonts.gstatic.com', 'crossorigin'); }
    return $urls;
}, 10, 2);
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('wp_head', 'wp_generator');

/* Browser title: "Page · AAUW Ann Arbor (MI) Branch" */
add_filter('document_title_separator', function () { return '·'; });
add_filter('document_title_parts', function ($parts) {
    if (is_front_page()) return array('title' => 'AAUW Ann Arbor (MI) Branch', 'tagline' => 'Empowering women since 1902');
    $parts['site'] = 'AAUW Ann Arbor (MI) Branch'; unset($parts['tagline']);
    return $parts;
});

function aauw_fn($name) { return function_exists($name); }
function aauw_o($key, $fallback = '') { return aauw_fn('aauw_opt') ? aauw_opt($key) : $fallback; }
function aauw_m($key, $post_id = 0) { return (string) get_post_meta($post_id ?: get_the_ID(), '_aauw_' . $key, true); }
function aauw_hl($text) { return aauw_fn('aauw_headline_html') ? aauw_headline_html($text) : esc_html($text); }
function aauw_url($u) { return (strlen($u) && $u[0] === '/') ? home_url($u) : $u; }

/* Description, social tags, and organization data in <head> */
add_action('wp_head', function () {
    $desc = '';
    if (is_singular()) $desc = aauw_m('description', get_queried_object_id());
    if (!$desc && is_singular('program') && aauw_fn('aauw_program')) { $p = aauw_program(get_queried_object_id()); $desc = $p['excerpt']; }
    if (!$desc) $desc = 'AAUW Ann Arbor Inc. (MI) Branch: monthly programs, 35+ interest groups, the annual Used Book Sale, and scholarships for women in Washtenaw County. A 501(c)(3) nonprofit.';
    $title = wp_get_document_title();
    $url = is_singular() ? get_permalink(get_queried_object_id()) : home_url('/');
    $img = home_url('/img/book-sale.jpg');
    if (is_singular() && has_post_thumbnail(get_queried_object_id())) $img = get_the_post_thumbnail_url(get_queried_object_id(), 'full');
    echo '<meta name="description" content="' . esc_attr(wp_strip_all_tags($desc)) . '">' . "\n";
    echo '<meta property="og:type" content="website"><meta property="og:site_name" content="AAUW Ann Arbor (MI) Branch">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '"><meta property="og:description" content="' . esc_attr(wp_strip_all_tags($desc)) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($url) . '"><meta property="og:image" content="' . esc_url($img) . '"><meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<link rel="icon" href="' . esc_url(home_url('/favicon.svg')) . '" type="image/svg+xml">' . "\n";
    $addr = array_map('trim', explode(',', aauw_o('address')));
    $state_zip = isset($addr[2]) ? preg_split('/\s+/', $addr[2]) : array('MI', '');
    $ld = array(
        '@context' => 'https://schema.org', '@type' => 'NGO', 'name' => aauw_o('short_name'),
        'alternateName' => array('AAUW Ann Arbor', 'AAUW Ann Arbor Branch', aauw_o('legal_name')), 'legalName' => aauw_o('legal_name'),
        'taxID' => aauw_o('ein'), 'nonprofitStatus' => 'https://schema.org/Nonprofit501c3', 'url' => home_url('/'), 'logo' => home_url('/img/logo-lockup.png'),
        'foundingDate' => '1902', 'telephone' => '+1-' . aauw_o('phone'),
        'address' => array('@type' => 'PostalAddress', 'streetAddress' => $addr[0], 'addressLocality' => isset($addr[1]) ? $addr[1] : 'Ann Arbor', 'addressRegion' => $state_zip[0], 'postalCode' => isset($state_zip[1]) ? $state_zip[1] : '', 'addressCountry' => 'US'),
        'areaServed' => 'Washtenaw County, Michigan',
        'parentOrganization' => array('@type' => 'NGO', 'name' => 'American Association of University Women', 'url' => 'https://www.aauw.org/'),
        'sameAs' => array_values(array_filter(array(aauw_o('official_url'), aauw_o('facebook_url')))),
        'description' => 'AAUW Ann Arbor Inc. (MI) Branch advances equity for women and girls through advocacy, education, and research. Founded 1902.',
    );
    echo '<script type="application/ld+json">' . wp_json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}, 5);

/* ---------- Menus ---------- */
function aauw_menu_tree($location = 'primary') {
    static $cache = array();
    if (isset($cache[$location])) return $cache[$location];
    $tree = array(); $locs = get_nav_menu_locations();
    if (!empty($locs[$location])) {
        $items = wp_get_nav_menu_items($locs[$location]); if (!$items) $items = array();
        $by = array(); $keep = array();
        foreach ($items as $it) {
            if (trim(wp_strip_all_tags((string) $it->title)) === '') continue;                       // blank label
            if (!empty($it->_invalid)) continue;                                                      // page was deleted
            if ($it->type === 'post_type' && get_post_status($it->object_id) !== 'publish') continue; // draft, private or trashed page
            $it->kids = array(); $by[$it->ID] = $it; $keep[] = $it;
        }
        foreach ($keep as $it) {
            $parent = (int) $it->menu_item_parent;
            if (!$parent) { $tree[] = $it; continue; }
            if (!isset($by[$parent])) continue;                                                       // its parent is hidden, so hide it too
            $top = $by[$parent]; $guard = 0;
            while ((int) $top->menu_item_parent && isset($by[(int) $top->menu_item_parent]) && $guard++ < 10) $top = $by[(int) $top->menu_item_parent];
            $top->kids[] = $it;                                                                       // deeper levels sit in their section's list
        }
    }
    return $cache[$location] = $tree;
}
/** The top-level menu item (section) the current page belongs to. */
function aauw_current_section() {
    static $done = false, $found = null;
    if ($done) return $found; $done = true;
    if (!is_singular()) return null;
    $qid = get_queried_object_id();
    if (is_singular('program')) { $pg = get_page_by_path('programs'); $ids = $pg ? array($pg->ID) : array(); }
    else $ids = array_merge(array($qid), get_post_ancestors($qid));
    $tree = aauw_menu_tree();
    foreach ($ids as $id) {
        foreach ($tree as $top) if ($top->object === 'page' && (int) $top->object_id === (int) $id) return $found = $top;
        foreach ($tree as $top) foreach ($top->kids as $kid) if ($kid->object === 'page' && (int) $kid->object_id === (int) $id) return $found = $top;
    }
    return null;
}
function aauw_item_is_internal_page($it) {
    $u = $it->url; $home = home_url('/');
    if ($it->object === 'page') return true;
    if (strpos($u, '#') !== false) return false;
    return (strlen($u) && $u[0] === '/') || strpos($u, $home) === 0;
}
function aauw_subnav() {
    $top = aauw_current_section();
    if (!$top || !$top->kids) return;
    $cur = trailingslashit(get_permalink(get_queried_object_id()));
    $links = array($top);
    foreach ($top->kids as $k) if (aauw_item_is_internal_page($k)) $links[] = $k;
    if (count($links) < 2) return;
    echo '<nav class="subnav" aria-label="In this section"><div class="wrap"><ul>';
    foreach ($links as $l) {
        $is = trailingslashit($l->url) === $cur;
        echo '<li><a href="' . esc_url($l->url) . '"' . ($is ? ' aria-current="page"' : '') . '>' . esc_html($l->title) . '</a></li>';
    }
    echo '</ul></div></nav>';
}
function aauw_menu_feature($top) {
    $path = wp_parse_url($top->url, PHP_URL_PATH);
    $prog_page = get_page_by_path('programs');
    $has_programs = false;
    foreach ($top->kids as $k) if ($prog_page && $k->object === 'page' && (int) $k->object_id === (int) $prog_page->ID) $has_programs = true;
    if ($has_programs && aauw_fn('aauw_upcoming_programs')) {
        $next = aauw_upcoming_programs(1);
        if ($next) {
            $p = $next[0];
            $img = get_the_post_thumbnail_url($p['id'], 'aauw-card'); if (!$img) $img = home_url('/img/programs.jpg');
            return array('href' => $p['url'], 'img' => $img, 'title' => 'Next program · ' . date_i18n('M j', $p['ts']), 'text' => wp_trim_words($p['excerpt'], 22), 'cta' => 'Learn more');
        }
    }
    $all = get_option('aauw_menu_features', array());
    if (is_array($all) && isset($all[$path])) {
        $f = $all[$path];
        // A phone card always shows the number from Site Settings.
        if (strpos($f['href'], 'tel:') === 0) { $phone = aauw_o('phone', '844-973-6287'); $f['text'] = str_replace('844-973-6287', $phone, $f['text']); $f['cta'] = str_replace('844-973-6287', $phone, $f['cta']); $f['href'] = aauw_tel(); return array_merge($f, array('img' => aauw_url($f['img']))); }
        $f['img'] = aauw_url($f['img']); $f['href'] = aauw_url($f['href']); return $f;
    }
    return null;
}
function aauw_primary_nav() {
    $tree = aauw_menu_tree(); $section = aauw_current_section();
    $chev = '<svg class="chev" aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
    echo '<ul class="nav__list">';
    foreach ($tree as $top) {
        $slug = sanitize_title($top->title);
        if (!$top->kids) {
            $active = (is_front_page() && untrailingslashit($top->url) === untrailingslashit(home_url())) || ($section && $section->ID === $top->ID);
            echo '<li><a class="nav__home" href="' . esc_url($top->url) . '"' . ($active ? ' data-active="true"' : '') . '>' . esc_html($top->title) . '</a></li>';
            continue;
        }
        $active = $section && $section->ID === $top->ID;
        echo '<li class="has-menu"><a class="nav__btn" href="' . esc_url($top->url) . '"' . ($active ? ' data-active="true"' : '') . ' aria-haspopup="true" aria-expanded="false" aria-controls="menu-' . esc_attr($slug) . '">' . esc_html($top->title) . ' ' . $chev . '</a>';
        echo '<div class="menu mega" id="menu-' . esc_attr($slug) . '"><div class="wrap mega__grid">';
        echo '<div class="mega__intro"><h3>' . esc_html($top->attr_title ? $top->attr_title : $top->title) . '</h3>' . ($top->description ? '<p>' . esc_html($top->description) . '</p>' : '') . '<a class="mega__overview" href="' . esc_url($top->url) . '">Overview →</a></div>';
        $half = (int) ceil(count($top->kids) / 2);
        echo '<div class="mega__links">';
        foreach (array_chunk($top->kids, max(1, $half)) as $col) {
            echo '<ul class="mega__col">';
            foreach ($col as $k) {
                $ext = !aauw_item_is_internal_page($k) && strpos($k->url, '#') === false && strpos($k->url, 'tel:') !== 0 && strpos($k->url, 'mailto:') !== 0;
                echo '<li><a href="' . esc_url($k->url) . '"' . ($ext ? ' rel="noopener"' : '') . '><strong>' . esc_html($k->title) . '</strong>' . ($k->description ? '<span>' . esc_html($k->description) . '</span>' : '') . '</a></li>';
            }
            echo '</ul>';
        }
        echo '</div>';
        $f = aauw_menu_feature($top);
        if ($f) echo '<a class="mega__feature" href="' . esc_url($f['href']) . '"><img src="' . esc_url($f['img']) . '" alt="" loading="lazy"><span class="mega__ftitle">' . esc_html($f['title']) . '</span><span class="mega__ftext">' . esc_html($f['text']) . '</span><span class="mega__fcta">' . esc_html($f['cta']) . ' →</span></a>';
        echo '</div></div></li>';
    }
    echo '</ul>';
}
function aauw_footer_list($location) {
    foreach (aauw_menu_tree($location) as $it) {
        $ext = !aauw_item_is_internal_page($it) && strpos($it->url, '#') === false;
        echo '<li><a href="' . esc_url($it->url) . '"' . ($ext ? ' rel="noopener"' : '') . '>' . esc_html($it->title) . '</a></li>';
    }
}

/* ---------- Page pieces ---------- */
function aauw_pagehead($post_id = 0) {
    $post_id = $post_id ?: get_the_ID();
    if (aauw_m('nohead', $post_id) === '1') return;
    $style = aauw_m('headstyle', $post_id);
    $photo = ($style === 'photo' && has_post_thumbnail($post_id)) ? get_the_post_thumbnail_url($post_id, 'aauw-wide') : '';
    $navy = $style === 'navy' || $photo;
    $head = aauw_m('headline', $post_id); if ($head === '') $head = get_the_title($post_id);
    $eb = aauw_m('eyebrow', $post_id); $intro = aauw_m('intro', $post_id);
    echo '<section class="pagehead' . ($navy ? ' pagehead--navy' : '') . ($photo ? ' pagehead--photo' : '') . '"' . ($photo ? ' style="background-image:url(\'' . esc_url($photo) . '\')"' : '') . '><div class="wrap">';
    if ($eb !== '') echo '<span class="eyebrow' . ($navy ? ' eyebrow--light' : '') . '">' . esc_html($eb) . '</span>';
    echo '<h1>' . aauw_hl($head) . '</h1>';
    if ($intro !== '') echo '<p class="lede">' . wp_kses_post($intro) . '</p>';
    echo '</div></section>';
}
/** Page body: imported pages remember their wrapper; new pages get the standard reading column. */
function aauw_content($post_id = 0) {
    $post_id = $post_id ?: get_the_ID();
    // force_balance_tags closes anything left open (or drops a stray closing tag) so a slip in the Code tab cannot swallow the footer.
    return apply_filters('the_content', force_balance_tags(get_post_field('post_content', $post_id)));
}
/** True when a page has a password and this visitor has not entered it yet. */
function aauw_locked($post_id = 0) { return post_password_required($post_id ?: get_the_ID()); }
function aauw_password_box($post_id = 0) {
    return '<section class="section"><div class="wrap prose aauw-locked">' . get_the_password_form($post_id ?: get_the_ID()) . '</div></section>';
}
add_filter('protected_title_format', function () { return '%s'; });
add_filter('the_password_form', function ($form) {
    return str_replace('This content is password-protected. To view it, please enter the password below.', 'This page is for branch members. Please enter the members password to continue.', $form);
});
function aauw_page_body($post_id = 0) {
    $post_id = $post_id ?: get_the_ID();
    if (aauw_locked($post_id)) { echo aauw_password_box($post_id); return; }
    $content = aauw_content($post_id);
    if (trim($content) === '') return;
    $layout = aauw_m('layout', $post_id);
    if ($layout === 'full') { echo $content; return; }
    $wrap = aauw_m('wrap_class', $post_id);
    if ($layout === '') $wrap = 'prose';
    echo '<section class="section"><div class="wrap' . ($wrap ? ' ' . esc_attr($wrap) : '') . '">' . $content . '</div></section>';
}
function aauw_art($key) {
    $f = get_theme_file_path('art/' . sanitize_file_name($key) . '.svg');
    return file_exists($f) ? file_get_contents($f) : '';
}
function aauw_month_abbr($ts) { return date_i18n('M', $ts); }
function aauw_tel() { return 'tel:+1' . preg_replace('/\D+/', '', aauw_o('phone', '844-973-6287')); }

/** Load the web-font stylesheet without holding up the first paint (same technique as the static site). */
function aauw_async_fonts($html, $handle, $href) {
    if ($handle !== 'aauw-fonts' || is_admin()) return $html;
    $href = esc_url($href);
    return "<link rel='stylesheet' id='aauw-fonts-css' href='{$href}' media='print' onload=\"this.media='all'\" />\n<noscript><link rel='stylesheet' href='{$href}' /></noscript>\n";
}
add_filter('style_loader_tag', 'aauw_async_fonts', 10, 3);

/* Site search covers pages and programs (members-only pages are left out for visitors by WordPress itself). */
function aauw_search_scope($q) {
    if (is_admin() || !$q->is_main_query() || !$q->is_search()) return;
    $q->set('post_type', array('page', 'program'));
    $q->set('posts_per_page', 20);
}
add_action('pre_get_posts', 'aauw_search_scope');
function aauw_search_snippet($post) {
    $d = get_post_meta($post->ID, '_aauw_description', true);
    if (!$d) $d = get_post_meta($post->ID, '_aauw_intro', true);
    if (!$d && $post->post_password === '') $d = wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), 32);
    return wp_strip_all_tags((string) $d);
}

/* ---------- Social channels (from Site Settings) and the Utility menu (from Appearance > Menus) ---------- */
function aauw_social_list() { return aauw_fn('aauw_social') ? aauw_social() : array(); }
function aauw_social_badge($channel) {
    $map = array('Instagram' => 'IG', 'LinkedIn' => 'in', 'YouTube' => 'YT', 'X' => 'X', 'Bluesky' => 'Bs', 'Threads' => '@');
    return isset($map[$channel]) ? $map[$channel] : substr($channel, 0, 2);
}
function aauw_social_items($where) {
    foreach (aauw_social_list() as $r) {
        $label = 'AAUW Ann Arbor on ' . $r['channel'];
        if ($where === 'utility') {
            $icon = $r['channel'] === 'Facebook' ? '<svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 22v-8.2h2.8l.4-3.3h-3.2V8.4c0-.9.3-1.6 1.6-1.6h1.7V3.9c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.4H7.3v3.3h2.8V22h3.4z"/></svg>' : '<span aria-hidden="true">' . esc_html(aauw_social_badge($r['channel'])) . '</span>';
            echo '<li><a class="fbicon" href="' . esc_url($r['url']) . '" rel="noopener" aria-label="' . esc_attr($label) . '">' . $icon . '</a></li>';
        } elseif ($where === 'footer') {
            $icon = $r['channel'] === 'Facebook' ? '<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>' : esc_html($r['channel']);
            echo '<li><a href="' . esc_url($r['url']) . '" rel="noopener" aria-label="' . esc_attr($label) . '">' . $icon . '</a></li>';
        } else {
            echo '<li><a href="' . esc_url($r['url']) . '" rel="noopener">' . esc_html($r['channel']) . '</a></li>';
        }
    }
}
/** The small links in the top bar: an editable menu, with sensible defaults until one is assigned. */
function aauw_utility_links() {
    $locs = get_nav_menu_locations(); $items = !empty($locs['utility']) ? wp_get_nav_menu_items($locs['utility']) : array();
    $out = array();
    foreach ((array) $items as $it) {
        if ((int) $it->menu_item_parent || trim(wp_strip_all_tags((string) $it->title)) === '' || !empty($it->_invalid)) continue;
        if ($it->type === 'post_type' && get_post_status($it->object_id) !== 'publish') continue;
        $out[] = array('title' => $it->title, 'url' => $it->url, 'blank' => $it->target === '_blank');
    }
    if (!$out) $out = array(
        array('title' => 'Members', 'url' => home_url('/members/'), 'blank' => false),
        array('title' => 'AAUW National', 'url' => 'https://www.aauw.org/', 'blank' => false),
        array('title' => aauw_o('official_label'), 'url' => aauw_o('official_url'), 'blank' => false),
    );
    foreach ($out as $l) echo '<li><a href="' . esc_url($l['url']) . '"' . ($l['blank'] ? ' target="_blank"' : '') . (strpos($l['url'], home_url()) === 0 ? '' : ' rel="noopener"') . '>' . esc_html(wp_strip_all_tags($l['title'])) . '</a></li>';
}

/* Galleries made with Add Media > Create gallery: three across, captions shown, and each photo opens larger when clicked. */
add_filter('shortcode_atts_gallery', function ($out, $pairs, $atts) {
    if (empty($atts['link'])) $out['link'] = 'file';
    if (empty($atts['size'])) $out['size'] = 'medium_large';
    return $out;
}, 10, 3);
add_filter('use_default_gallery_style', '__return_false');
