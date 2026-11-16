<?php
if (!defined('ABSPATH')) exit;
get_header();
$term = get_queried_object();
$all = aauw_fn('aauw_programs') ? aauw_programs(array('tax_query' => array(array('taxonomy' => 'program_type', 'field' => 'term_id', 'terms' => $term->term_id)))) : array();
$up = array(); $past = array();
foreach ($all as $p) { if (!$p['date']) continue; if ($p['past']) $past[] = $p; else $up[] = $p; }
$past = array_reverse($past);
$card = function ($p) { ?>
      <article class="event<?php echo $p['past'] ? ' is-past' : ''; ?>">
        <div class="date"><small><?php echo esc_html(date_i18n('M', $p['ts'])); ?></small><b><?php echo esc_html(date_i18n('j', $p['ts'])); ?></b><small><?php echo esc_html(date_i18n('Y', $p['ts'])); ?></small></div>
        <div>
          <h3><?php echo esc_html($p['title']); ?></h3>
          <?php $meta = trim($p['speaker'] . ($p['time'] ? ($p['speaker'] ? ' · ' : '') . $p['time'] : '')); if ($meta) : ?><p class="meta"><?php echo esc_html($meta); ?></p><?php endif; ?>
          <p><?php echo esc_html($p['excerpt']); ?></p>
          <p><a href="<?php echo esc_url(aauw_url($p['url'])); ?>"><strong><?php echo esc_html($p['link_text']); ?> →</strong></a></p>
        </div>
      </article>
<?php };
?>
<section class="pagehead">
  <div class="wrap">
    <span class="eyebrow">Programs</span>
    <h1><?php echo esc_html($term->name); ?></h1>
    <?php if ($term->description) : ?><p class="lede"><?php echo esc_html($term->description); ?></p><?php endif; ?>
  </div>
</section>
<section class="section">
  <div class="wrap">
<?php if ($up) : ?>
    <h2>Coming up</h2>
    <div class="events"><?php foreach ($up as $p) $card($p); ?></div>
<?php else : ?>
    <p>Nothing of this kind is on the calendar right now. <a href="<?php echo esc_url(home_url('/programs/')); ?>">See all programs</a>.</p>
<?php endif; ?>
<?php if ($past) : ?>
    <h2 style="margin-top:3rem">Earlier this year</h2>
    <div class="events"><?php foreach ($past as $p) $card($p); ?></div>
<?php endif; ?>
    <p style="margin-top:2.5rem"><a class="btn btn--outline arrow" href="<?php echo esc_url(home_url('/programs/')); ?>">All programs</a></p>
  </div>
</section>
<?php get_footer();
