<?php
if (!defined('ABSPATH')) exit;
get_header();
$term = get_search_query();
?>
<section class="pagehead">
  <div class="wrap">
    <span class="eyebrow">Search</span>
    <h1><?php echo $term !== '' ? 'Results for &ldquo;' . esc_html($term) . '&rdquo;' : 'Search this site'; ?></h1>
  </div>
</section>
<section class="section">
  <div class="wrap prose">
    <form class="searchform" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
      <label class="sr-only" for="page-search">Search this site</label>
      <input type="search" id="page-search" name="s" value="<?php echo esc_attr($term); ?>" placeholder="Type a word or two">
      <button class="btn btn--navy" type="submit">Search</button>
    </form>
<?php if ($term !== '' && have_posts()) : ?>
    <p class="hint"><?php echo (int) $wp_query->found_posts; ?> <?php echo (int) $wp_query->found_posts === 1 ? 'result' : 'results'; ?></p>
    <ul class="results">
<?php while (have_posts()) : the_post(); $kind = get_post_type() === 'program' ? 'Program' : 'Page'; ?>
      <li>
        <span class="results__kind"><?php echo esc_html($kind); ?><?php if (get_post_type() === 'program' && aauw_fn('aauw_program')) { $pg = aauw_program(get_the_ID()); if ($pg['ts']) echo ' · ' . esc_html(date_i18n('F j, Y', $pg['ts'])); } ?></span>
        <a class="results__title" href="<?php the_permalink(); ?>"><?php echo esc_html(get_the_title()); ?></a>
        <?php $sn = aauw_search_snippet(get_post()); if ($sn) : ?><p><?php echo esc_html($sn); ?></p><?php endif; ?>
      </li>
<?php endwhile; ?>
    </ul>
    <?php the_posts_pagination(array('mid_size' => 1, 'prev_text' => 'Previous', 'next_text' => 'Next')); ?>
<?php elseif ($term !== '') : ?>
    <p>Nothing matched <strong><?php echo esc_html($term); ?></strong>. Try a different word, or use the menu at the top of the page.</p>
<?php endif; ?>
  </div>
</section>
<?php get_footer();
