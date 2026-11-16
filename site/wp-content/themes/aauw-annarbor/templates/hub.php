<?php
/* Template Name: Section landing page (photo header) */
if (!defined('ABSPATH')) exit;
get_header();
while (have_posts()) { the_post();
    $id = get_the_ID();
    $head = aauw_m('headline'); if ($head === '') $head = get_the_title();
    $key = aauw_m('art'); if ($key === '') $key = get_post_field('post_name', $id);
    $facts = aauw_fn('aauw_pairs') ? array_slice(aauw_pairs(aauw_m('facts')), 0, 4) : array();
    $pos = aauw_m('img_pos');
    ?>
<section class="hub hub--<?php echo esc_attr($key); ?>" aria-labelledby="hub-h">
  <div class="hub__panel">
    <div class="hub__text">
      <?php if (aauw_m('eyebrow') !== '') : ?><span class="eyebrow"><?php echo esc_html(aauw_m('eyebrow')); ?></span><?php endif; ?>
      <h1 id="hub-h"><?php echo aauw_hl($head); ?></h1>
      <?php if (aauw_m('intro') !== '') : ?><p class="lede"><?php echo wp_kses_post(aauw_m('intro')); ?></p><?php endif; ?>
      <div class="btn-row"><?php
        for ($i = 1; $i <= 2; $i++) {
            $t = aauw_m('btn' . $i . '_text'); $u = aauw_m('btn' . $i . '_url');
            if ($t === '' || $u === '') continue;
            $ext = preg_match('#^https?://#', $u) && strpos($u, home_url()) !== 0;
            echo '<a class="btn ' . ($i === 1 ? 'btn--red' : 'btn--outline') . '" href="' . esc_url(aauw_url($u)) . '"' . ($ext ? ' rel="noopener"' : '') . '>' . wp_kses($t, array('em' => array())) . '</a>';
        } ?></div>
    </div>
    <?php echo aauw_art($key); ?>
  </div>
  <figure class="hub__media"><?php
    if (has_post_thumbnail()) echo wp_get_attachment_image(get_post_thumbnail_id(), 'full', false, array('sizes' => '(max-width: 1000px) 100vw, 54vw', 'fetchpriority' => 'high', 'loading' => 'eager', 'style' => $pos ? 'object-position:' . esc_attr($pos) : ''));
    if ($facts) { echo '<figcaption class="hub__facts"><ul>'; foreach ($facts as $f) echo '<li><strong>' . esc_html($f[0]) . '</strong><span>' . esc_html($f[1]) . '</span></li>'; echo '</ul></figcaption>'; }
  ?></figure>
</section>
<?php
    aauw_subnav();
    // Explore grid: built from this section's entries in the main menu.
    $xt = aauw_m('explore_title'); $top = aauw_current_section();
    if ($xt !== '' && $top && $top->kids) {
        $cards = array();
        foreach ($top->kids as $k) {
            if ($k->object !== 'page' || (int) $k->object_id === $id) continue;
            $cards[] = $k;
        }
        if ($cards) {
            echo '<section class="section section--explore" aria-labelledby="explore-h"><div class="wrap"><div class="section__head reveal"><span class="eyebrow eyebrow--blue">Explore</span><h2 id="explore-h">' . esc_html($xt) . '</h2>';
            if (aauw_m('explore_intro') !== '') echo '<p>' . esc_html(aauw_m('explore_intro')) . '</p>';
            echo '</div><div class="xgrid reveal" data-delay="1">';
            foreach ($cards as $k) {
                $img = get_the_post_thumbnail_url((int) $k->object_id, 'aauw-card'); if (!$img) $img = home_url('/img/programs.jpg');
                echo '<a class="xcard" href="' . esc_url($k->url) . '"><span class="xcard__img"><img src="' . esc_url($img) . '" alt="" loading="lazy" width="800" height="520"></span><span class="xcard__body">';
                if ($k->attr_title) echo '<span class="xcard__k">' . esc_html($k->attr_title) . '</span>';
                echo '<strong>' . esc_html($k->title) . '</strong>' . ($k->description ? '<span>' . esc_html($k->description) . '</span>' : '') . '<em>Read more →</em></span></a>';
            }
            echo '</div></div></section>';
        }
    }
    aauw_page_body();
}
get_footer();
