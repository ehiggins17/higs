<?php if (!defined('ABSPATH')) exit; ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0B2346">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">Skip to content</a>

<div class="utility" role="region" aria-label="Quick links">
  <div class="wrap utility__inner">
    <p class="utility__tag"><?php echo esc_html(aauw_o('utility_tag')); ?></p>
    <ul class="utility__links">
      <li><a href="<?php echo esc_attr(aauw_tel()); ?>"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.9.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg> <?php echo esc_html(aauw_o('phone')); ?></a></li>
      <?php aauw_social_items('utility'); aauw_utility_links(); ?>
      <li class="utility__search"><form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>"><label class="sr-only" for="site-search">Search this site</label><input type="search" id="site-search" name="s" placeholder="Search" value="<?php echo esc_attr(get_search_query()); ?>"><button type="submit" aria-label="Search"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg></button></form></li>
      <li><button type="button" class="textsize" id="textsize" aria-pressed="false" title="Toggle larger text">A<span aria-hidden="true">A</span><span class="sr-only"> Larger text</span></button></li>
    </ul>
  </div>
</div>

<header class="header">
  <div class="wrap header__inner">
    <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="AAUW Ann Arbor (MI) Branch home">
      <img src="<?php echo esc_url(home_url('/img/logo-lockup.png')); ?>" srcset="<?php echo esc_url(home_url('/img/logo-lockup-sm.png')); ?> 640w, <?php echo esc_url(home_url('/img/logo-lockup.png')); ?> 1600w" sizes="(max-width: 700px) 170px, 230px" width="230" height="109" alt="AAUW Ann Arbor Inc. (MI) Branch">
    </a>
    <button class="navtoggle" type="button" aria-expanded="false" aria-controls="sitenav" id="navtoggle">
      <span class="navtoggle__label">Menu</span><span class="navtoggle__bars" aria-hidden="true"></span>
    </button>
    <nav class="nav" id="sitenav" aria-label="Main">
      <div class="nav__top"><span class="nav__brand">AAUW Ann Arbor</span><button type="button" class="nav__close" id="navclose" aria-label="Close menu">Close <span aria-hidden="true">×</span></button></div>
      <?php aauw_primary_nav(); ?>
      <div class="nav__actions">
        <a class="btn btn--outline" href="<?php echo esc_url(aauw_url(aauw_o('join_url'))); ?>">Join</a>
        <a class="btn btn--red" href="<?php echo esc_url(aauw_url(aauw_o('donate_url'))); ?>">Donate</a>
      </div>
      <form class="nav__search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>"><label class="sr-only" for="nav-search">Search this site</label><input type="search" id="nav-search" name="s" placeholder="Search this site"><button type="submit">Search</button></form>
      <ul class="nav__utility">
        <li><a href="<?php echo esc_attr(aauw_tel()); ?>"><?php echo esc_html(aauw_o('phone')); ?></a></li>
        <?php aauw_utility_links(); aauw_social_items('nav'); ?>
      </ul>
    </nav>
  </div>
</header>

<main id="main" tabindex="-1">
