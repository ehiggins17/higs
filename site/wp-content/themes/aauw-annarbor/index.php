<?php
if (!defined('ABSPATH')) exit;
get_header(); ?>
<section class="pagehead"><div class="wrap"><span class="eyebrow">News</span><h1><?php echo is_singular() ? esc_html(get_the_title()) : 'Branch news'; ?></h1></div></section>
<section class="section"><div class="wrap prose">
<?php if (have_posts()) : while (have_posts()) : the_post();
    if (is_singular()) { echo '<p class="hint">' . esc_html(get_the_date()) . '</p>'; the_content(); }
    else { echo '<h2><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h2><p class="hint">' . esc_html(get_the_date()) . '</p>'; the_excerpt(); }
endwhile; the_posts_pagination(); else : echo '<p>Nothing has been posted yet.</p>'; endif; ?>
</div></section>
<?php get_footer();
