<?php
if (!defined('ABSPATH')) exit;
get_header();
$id = get_queried_object_id();
$l1 = aauw_m('hero_line1', $id); $l2 = aauw_m('hero_line2', $id); $l3 = aauw_m('hero_line3', $id);
$next = aauw_fn('aauw_upcoming_programs') ? aauw_upcoming_programs(1, 'program') : array();
$coming = aauw_fn('aauw_upcoming_programs') ? aauw_upcoming_programs(3) : array();
?>
<section class="hero2 hero2--angle" aria-labelledby="hero-h">
  <div class="hero2__bg" aria-hidden="true"></div><div class="hero2__angle" aria-hidden="true"></div>
  <div class="wrap hero2__grid">
    <div>
      <span class="eyebrow"><?php echo esc_html(aauw_m('eyebrow', $id)); ?></span>
      <h1 id="hero-h" class="display"><?php echo esc_html($l1); ?><br><span class="display__accent"><?php echo esc_html($l2); ?></span><br><?php echo esc_html($l3); ?></h1>
      <p class="lede"><?php echo wp_kses_post(aauw_m('intro', $id)); ?></p>
      <div class="btn-row">
        <a class="btn btn--red btn--lg" href="<?php echo esc_url(aauw_url(aauw_o('join_url'))); ?>">Become a member</a>
        <a class="btn btn--outline btn--lg" href="<?php echo esc_url(home_url('/programs/')); ?>">See all programs</a>
      </div>
      <p class="hero2__call">Prefer to talk? Call <a href="<?php echo esc_attr(aauw_tel()); ?>"><?php echo esc_html(aauw_o('phone')); ?></a> and a member will call you back.</p>
    </div>
<?php if ($next) : $p = $next[0]; $img = get_the_post_thumbnail_url($p['id'], 'aauw-card'); ?>
    <aside class="nextcard" aria-labelledby="next-label" data-date="<?php echo esc_attr($p['date']); ?>">
      <div class="nextcard__top"><span id="next-label" class="nextcard__label">Next branch program</span><span class="nextcard__date"><?php echo esc_html(date_i18n('D, M j', $p['ts'])); ?></span></div>
      <?php if ($img) : ?><img src="<?php echo esc_url($img); ?>" width="800" height="520" alt="" fetchpriority="high"><?php endif; ?>
      <h2><?php echo esc_html($p['title']); ?></h2>
      <?php if ($p['speaker']) : ?><p><?php echo esc_html($p['speaker']); ?></p><?php endif; ?>
      <ul>
        <li><?php echo esc_html(trim($p['location'] . ($p['address'] ? ', ' . $p['address'] : ''))); ?></li>
        <li><?php echo esc_html($p['time'] ? $p['time'] : aauw_o('lunch_note')); ?></li>
        <li><?php echo esc_html(aauw_o('talk_note')); ?></li>
      </ul>
      <div class="btn-row"><a class="btn btn--white" href="<?php echo esc_url(aauw_url($p['url'])); ?>">Program details</a><?php
        if ($p['links']) echo '<a class="btn btn--ghost" href="' . esc_url(aauw_url($p['links'][0]['url'])) . '"' . ($p['links'][0]['blank'] ? ' target="_blank" rel="noopener"' : '') . '>' . esc_html($p['links'][0]['title']) . '</a>';
        elseif ($p['reserve_open']) echo '<a class="btn btn--ghost" href="' . esc_url(get_permalink($p['id'])) . '#reserve">Reserve lunch</a>';
        else echo '<a class="btn btn--ghost" href="' . esc_url(home_url('/contact/')) . '">Reserve lunch</a>';
      ?></div>
    </aside>
<?php else : ?>
    <aside class="nextcard nextcard--empty" aria-labelledby="next-label">
      <div class="nextcard__top"><span id="next-label" class="nextcard__label">Branch programs</span></div>
      <h2>Third Wednesdays, September through May</h2>
      <p>Speakers on community, culture, history, and health at the <?php echo esc_html(aauw_o('venue_name')); ?>.</p>
      <ul><li><?php echo esc_html(aauw_o('lunch_note')); ?></li><li><?php echo esc_html(aauw_o('talk_note')); ?></li></ul>
      <div class="btn-row"><a class="btn btn--white" href="<?php echo esc_url(home_url('/programs/')); ?>">Program calendar</a></div>
    </aside>
<?php endif; ?>
  </div>
</section>

<?php // The full write-up of the next branch program, the way the old homepage showed it.
if ($next && !post_password_required($next[0]['id'])) : $p = $next[0]; $body = trim(aauw_content($p['id']));
  $label = date_i18n('F', $p['ts']) . ($p['type'] === 'Branch program' ? ' Branch Meeting' : ' · ' . $p['type']);
  $times = array_filter(array_map('trim', preg_split('/\s*[·|;]\s*/u', (string) $p['time']))); ?>
<section class="section meeting" id="this-month" aria-labelledby="meeting-h">
  <div class="wrap two-col">
    <div class="prose">
      <span class="eyebrow eyebrow--blue"><?php echo esc_html($label); ?></span>
      <h2 id="meeting-h" style="margin-top:.4rem"><?php echo esc_html($p['title']); ?></h2>
      <?php if ($p['speaker']) : ?><p class="lede" style="margin-top:.2rem"><strong>Speaker:</strong> <?php echo esc_html($p['speaker']); ?></p><?php endif; ?>
      <?php echo $body !== '' ? $body : '<p>' . esc_html($p['excerpt']) . '</p>'; ?>
    </div>
    <aside class="aside" aria-label="Meeting details">
      <h3><?php echo esc_html(date_i18n('l, F j', $p['ts'])); ?></h3>
      <dl class="facts">
        <div><dt>Where</dt><dd><?php echo esc_html($p['location']); ?><?php if ($p['address']) echo '<br>' . esc_html($p['address']); ?></dd></div>
        <?php if ($times) : ?><div><dt>Time</dt><dd><?php echo implode('<br>', array_map('esc_html', $times)); ?></dd></div><?php endif; ?>
        <div><dt>Open to</dt><dd><?php echo esc_html(aauw_o('talk_note')); ?></dd></div>
      </dl>
      <?php if ($p['links']) : foreach ($p['links'] as $i => $l) : ?><a class="btn <?php echo $i ? 'btn--outline' : 'btn--red'; ?>" href="<?php echo esc_url(aauw_url($l['url'])); ?>"<?php if ($l['blank']) echo ' target="_blank" rel="noopener"'; ?>><?php echo esc_html($l['title']); ?></a><?php endforeach;
      else : ?><a class="btn btn--red" href="<?php echo esc_url($p['reserve_open'] ? get_permalink($p['id']) . '#reserve' : home_url('/contact/')); ?>">Reserve lunch</a><?php endif; ?>
      <a class="btn btn--outline" href="<?php echo esc_url(aauw_url($p['url'])); ?>">Program details</a>
    </aside>
  </div>
</section>
<?php endif; ?>

<?php $ticker = aauw_fn('aauw_opt_lines') ? aauw_opt_lines('ticker') : array(); if ($ticker) : ?>
<div class="ticker" aria-label="Some of our interest groups">
  <div class="ticker__track">
    <?php foreach ($ticker as $t) echo '<span>' . esc_html($t) . '</span>'; ?>
  </div>
</div>
<?php endif; ?>

<?php $stats = aauw_fn('aauw_pairs') ? aauw_pairs(aauw_m('stats', $id)) : array(); if ($stats) : ?>
<section class="section section--tight" aria-label="Branch at a glance">
  <div class="wrap stats2 reveal">
<?php foreach ($stats as $s) {
    $num = $s[0]; $attrs = '';
    if (preg_match('/^(\$?)([\d,]+)(\+?)$/', $num, $mm)) {
        $n = (int) str_replace(',', '', $mm[2]);
        $is_year = ($mm[1] === '' && $mm[3] === '' && $n >= 1800 && $n <= 2100);
        if (!$is_year) $attrs = ' data-count="' . $n . '"' . ($mm[1] ? ' data-prefix="$"' : '') . ($mm[3] ? ' data-suffix="+"' : '');
    }
    echo '    <div class="stat"><strong' . $attrs . '>' . esc_html($num) . '</strong><span>' . esc_html($s[1]) . '</span></div>' . "\n";
} ?>
  </div>
</section>
<?php endif; ?>

<?php if ($coming) : ?>
<section class="section" aria-labelledby="month-h">
  <div class="wrap">
    <div class="section__head reveal">
      <span class="eyebrow eyebrow--blue"><?php echo esc_html(aauw_m('month_eyebrow', $id)); ?></span>
      <h2 id="month-h"><?php echo esc_html(aauw_m('month_title', $id)); ?></h2>
      <p><?php echo wp_kses_post(aauw_m('month_intro', $id)); ?></p>
    </div>
    <div class="month">
<?php foreach ($coming as $i => $p) :
    $img = get_the_post_thumbnail_url($p['id'], 'aauw-card'); if (!$img) $img = home_url('/img/programs.jpg');
    $href = aauw_url($p['url']); ?>
      <article class="month__card reveal"<?php echo $i ? ' data-delay="' . (int) $i . '"' : ''; ?> data-date="<?php echo esc_attr($p['end'] ? $p['end'] : $p['date']); ?>">
        <div class="month__media month__media--photo<?php echo $p['kind'] === 'fundraiser' ? ' month__media--red' : ''; ?>"><img src="<?php echo esc_url($img); ?>" width="800" height="520" alt="" loading="lazy"><div class="month__badge"><small><?php echo esc_html(date_i18n('M', $p['ts'])); ?></small><b><?php echo esc_html(date_i18n('j', $p['ts'])); ?></b></div><?php if ($p['card_word'] || $p['card_sub']) : ?><div class="month__label"><strong><?php echo esc_html($p['card_word']); ?></strong><span><?php echo esc_html($p['card_sub']); ?></span></div><?php endif; ?></div>
        <div class="month__body">
          <div class="month__kicker"><span><?php echo esc_html($p['type']); ?></span><time datetime="<?php echo esc_attr($p['date']); ?>"><?php echo esc_html(date_i18n('D, M j', $p['ts'])); ?></time></div>
          <h3><?php echo esc_html($p['title']); ?></h3>
          <p><?php echo esc_html($p['excerpt']); ?></p>
          <a class="more" href="<?php echo esc_url($href); ?>"><?php echo esc_html($p['link_text']); ?> →</a>
        </div>
      <a class="cover" href="<?php echo esc_url($href); ?>" aria-label="<?php echo esc_attr($p['link_text'] . ': ' . $p['title']); ?>"></a></article>
<?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
while (have_posts()) { the_post(); echo aauw_locked() ? aauw_password_box() : aauw_content(); }
get_footer();
