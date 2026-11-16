<?php
if (!defined('ABSPATH')) exit;
get_header();
while (have_posts()) { the_post();
    $p = aauw_program(get_the_ID());
    $long = $p['ts'] ? date_i18n('l, F j, Y', $p['ts']) : '';
    if ($p['end'] && $p['end'] !== $p['date']) $long = date_i18n('F j', $p['ts']) . '–' . date_i18n('j, Y', strtotime($p['end']));
    $content = aauw_locked() ? get_the_password_form() : aauw_content();
    ?>
<section class="pagehead pagehead--navy">
  <div class="wrap">
    <span class="eyebrow eyebrow--light"><?php echo esc_html(trim($p['type'] . ($long ? ' · ' . $long : ''))); ?></span>
    <h1><?php echo esc_html(get_the_title()); ?></h1>
    <?php if ($p['speaker']) : ?><p class="lede"><?php echo esc_html($p['speaker']); ?><?php echo $p['location'] ? '. ' . esc_html($p['location']) . '.' : ''; ?></p><?php endif; ?>
  </div>
</section>
<?php aauw_subnav(); ?>
<section class="section">
  <div class="wrap two-col">
    <div class="prose">
      <?php if (has_post_thumbnail() && strpos($content, '<img') === false) echo '<p>' . get_the_post_thumbnail(null, 'aauw-wide', array('style' => 'border-radius:16px;width:100%;height:auto;max-height:440px;object-fit:cover')) . '</p>'; ?>
      <?php echo trim($content) !== '' ? $content : '<p>' . esc_html($p['excerpt']) . '</p>'; ?>
      <p><a class="btn btn--outline arrow" href="<?php echo esc_url(home_url('/programs/')); ?>">All programs</a></p>
    </div>
    <aside class="aside" aria-label="Event details">
      <h2>Details</h2>
      <dl class="facts">
        <?php if ($long) : ?><div><dt>Date</dt><dd><?php echo esc_html($long); ?></dd></div><?php endif; ?>
        <?php if ($p['time']) : ?><div><dt>Time</dt><dd><?php echo esc_html($p['time']); ?></dd></div><?php endif; ?>
        <?php if ($p['location']) : ?><div><dt>Where</dt><dd><?php echo esc_html($p['location']); ?><?php echo $p['address'] ? '<br>' . esc_html($p['address']) : ''; ?></dd></div><?php endif; ?>
        <?php if ($p['kind'] === 'program') : ?><div><dt>Lunch</dt><dd>Reserved, paid, registration required</dd></div><div><dt>Talk</dt><dd>Free and open to the public</dd></div><?php endif; ?>
      </dl>
      <?php if (!$p['past']) :
        if ($p['links']) { foreach ($p['links'] as $i => $l) echo '<a class="btn ' . ($i ? 'btn--outline' : 'btn--red') . '" href="' . esc_url(aauw_url($l['url'])) . '"' . ($l['blank'] ? ' target="_blank" rel="noopener"' : '') . '>' . esc_html($l['title']) . '</a>'; }
        elseif ($p['reserve_open']) echo '<a class="btn btn--red" href="#reserve">Reserve lunch</a>';
        elseif ($p['kind'] === 'program') echo '<a class="btn btn--red" href="' . esc_url(home_url('/contact/')) . '">Reserve lunch</a>';
      endif; ?>
      <?php $cal = aauw_fn('aauw_program_calendar_links') ? aauw_program_calendar_links($p) : null; if ($cal) : ?><p class="addcal"><strong>Add to my calendar:</strong> <a href="<?php echo esc_url($cal['ics']); ?>">Apple or Outlook</a> · <a href="<?php echo esc_url($cal['google']); ?>" target="_blank" rel="noopener">Google</a></p><?php endif; ?>
      <?php if ($p['map']) : ?><a class="btn btn--outline" href="<?php echo esc_url($p['map']); ?>" rel="noopener">Map and directions</a><?php endif; ?>
      <p class="hint" style="margin-top:1rem">Questions? Call <a href="<?php echo esc_attr(aauw_tel()); ?>"><?php echo esc_html(aauw_o('phone')); ?></a>.</p>
    </aside>
  </div>
</section>
<?php if ($p['reserve_open'] && aauw_fn('aauw_reserve_form')) echo aauw_reserve_form($p); ?>
<?php }
get_footer();
