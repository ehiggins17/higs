<?php
/* Template Name: Programs calendar */
if (!defined('ABSPATH')) exit;
get_header();
while (have_posts()) { the_post();
    aauw_pagehead();
    aauw_subnav();
    $programs = aauw_fn('aauw_programs') ? aauw_programs() : array();
    $arrow_l = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>';
    $arrow_r = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>';
    ?>
<section class="section">
  <div class="wrap">
<?php if ($programs) : ?>
    <div class="cal" id="cal">
      <div class="cal__top">
        <div class="cal__nav">
          <button type="button" class="cal__arrow" data-cal="prev" aria-label="Previous month"><?php echo $arrow_l; ?></button>
          <h2 class="cal__month" id="cal-month" aria-live="polite">&nbsp;</h2>
          <button type="button" class="cal__arrow" data-cal="next" aria-label="Next month"><?php echo $arrow_r; ?></button>
        </div>
        <div class="cal__tools">
          <ul class="cal__legend" aria-label="Legend"><li><i class="cal__dot cal__dot--program"></i>Branch program</li><li><i class="cal__dot cal__dot--fundraiser"></i>Fundraiser</li><li><i class="cal__dot cal__dot--sale"></i>Book Sale</li></ul>
          <div class="cal__view" role="group" aria-label="View"><button type="button" class="is-on" data-view="calendar" aria-pressed="true">Calendar</button><button type="button" data-view="list" aria-pressed="false">List</button></div>
        </div>
      </div>
      <div class="cal__body">
        <div class="cal__gridwrap">
          <div class="cal__dow" aria-hidden="true"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
          <div class="cal__grid" id="cal-grid" aria-labelledby="cal-month"></div>
          <ol class="cal__months" id="cal-months" aria-label="Jump to month"></ol>
        </div>
        <aside class="cal__detail" id="cal-detail" aria-live="polite"></aside>
      </div>
    </div>
    <div class="events" id="events-list" hidden>
<?php foreach ($programs as $p) : if (!$p['date']) continue; ?>
      <article class="event" data-date="<?php echo esc_attr($p['date']); ?>"<?php echo $p['end'] ? ' data-end="' . esc_attr($p['end']) . '"' : ''; ?> data-kind="<?php echo esc_attr($p['kind']); ?>" data-note="<?php echo esc_attr($p['kind'] === 'program' ? trim('Optional reserved lunch first; the talk is free and open to the public. ' . $p['location'] . ($p['address'] ? ', ' . $p['address'] : '')) . '.' : ''); ?>">
        <div class="date"><small><?php echo esc_html(date_i18n('M', $p['ts'])); ?></small><b><?php echo esc_html(date_i18n('j', $p['ts'])); ?></b><small><?php echo esc_html(date_i18n('Y', $p['ts'])); ?></small></div>
        <div>
          <?php if ($p['kind'] !== 'program') : ?><span class="tag"><?php echo esc_html($p['type']); ?></span><?php endif; ?>
          <h3><?php echo esc_html($p['title']); ?></h3>
          <?php $meta = trim($p['speaker'] . ($p['time'] ? ($p['speaker'] ? ' · ' : '') . $p['time'] : '')); if ($meta) : ?><p class="meta"><?php echo esc_html($meta); ?></p><?php endif; ?>
          <p><?php echo esc_html($p['excerpt']); ?></p>
          <p><a href="<?php echo esc_url(aauw_url($p['url'])); ?>"><strong><?php echo esc_html($p['link_text']); ?> →</strong></a></p>
        </div>
      </article>
<?php endforeach; ?>
    </div>
    <hr>
<?php endif;
    $content = aauw_locked() ? get_the_password_form() : aauw_content();
    if (trim($content) !== '') echo '<div class="two-col">' . $content . '</div>';
?>
  </div>
</section>
<?php }
get_footer();
