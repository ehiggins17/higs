<?php
if (!defined('ABSPATH')) exit;
/* The request desk, shown inside the dashboard without the public site's header and footer. */
?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<?php wp_head(); ?>
<style>html{margin-top:0!important}body{background:#fff;margin:0;padding:6px 2px 30px;font-size:15px}#wpadminbar,.skip-link,.wpsc-header,.wpsc-humbargar-menu{display:none!important}.wpsc-shortcode-container{border:0!important;margin:0!important}</style>
<base target="_self">
</head>
<body <?php body_class('aauw-embed'); ?>>
<?php while (have_posts()) { the_post(); the_content(); } ?>
<?php wp_footer(); ?>
</body></html>
