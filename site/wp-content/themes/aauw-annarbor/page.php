<?php
if (!defined('ABSPATH')) exit;
get_header();
while (have_posts()) { the_post();
    aauw_pagehead();
    aauw_subnav();
    aauw_page_body();
}
get_footer();
