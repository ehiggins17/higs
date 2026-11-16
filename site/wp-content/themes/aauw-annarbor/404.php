<?php
if (!defined('ABSPATH')) exit;
get_header(); ?>
<section class="wrap notfound">
  <span class="eyebrow">404</span>
  <h1>We could not find that page</h1>
  <p class="lede center">Try one of these, or call <?php echo esc_html(aauw_o('phone')); ?>.</p>
  <div class="btn-row" style="justify-content:center">
    <a class="btn btn--navy" href="<?php echo esc_url(home_url('/')); ?>">Home</a>
    <a class="btn btn--outline" href="<?php echo esc_url(home_url('/programs/')); ?>">Programs</a>
    <a class="btn btn--outline" href="<?php echo esc_url(home_url('/join/')); ?>">Join</a>
    <a class="btn btn--outline" href="<?php echo esc_url(home_url('/contact/')); ?>">Contact</a>
  </div>
</section>
<?php get_footer();
