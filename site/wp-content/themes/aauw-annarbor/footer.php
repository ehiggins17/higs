<?php if (!defined('ABSPATH')) exit; ?>
</main>

<footer class="footer">
  <div class="wrap">
    <div class="footer__brandrow">
      <a class="footer__brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="AAUW Ann Arbor (MI) Branch home"><img src="<?php echo esc_url(home_url('/img/logo-lockup.png')); ?>" width="300" height="143" alt="AAUW Ann Arbor Inc. (MI) Branch" loading="lazy"></a>
      <p class="footer__tag"><?php echo esc_html(aauw_o('footer_tag')); ?></p>
      <ul class="footer__social" aria-label="Social and partner links">
        <?php aauw_social_items('footer'); ?>
        <li><a href="https://www.aauw.org/" rel="noopener">AAUW National</a></li>
        <li><a href="https://aauwmi.org/" rel="noopener">AAUW of Michigan</a></li>
      </ul>
    </div>
    <div class="footer__grid">
      <section class="footer__org" aria-labelledby="orgdetails">
        <h2 id="orgdetails" class="footer__h"><?php echo esc_html(aauw_o('short_name')); ?></h2>
        <p>A branch of the <a href="https://www.aauw.org/" rel="noopener">American Association of University Women</a>, advancing equity for women and girls through advocacy, education, and research since 1902.</p>
        <dl class="orgdl">
          <div><dt>Legal name</dt><dd><?php echo esc_html(aauw_o('legal_name')); ?></dd></div>
          <div><dt>Status</dt><dd><?php echo esc_html(aauw_o('status')); ?></dd></div>
          <div><dt>EIN</dt><dd><?php echo esc_html(aauw_o('ein')); ?></dd></div>
          <div><dt>Address</dt><dd><address><?php echo esc_html(aauw_o('address')); ?></address></dd></div>
          <div><dt>Mail</dt><dd><?php echo esc_html(aauw_o('mail')); ?></dd></div>
          <div><dt>Phone</dt><dd><a href="<?php echo esc_attr(aauw_tel()); ?>"><?php echo esc_html(aauw_o('phone')); ?></a></dd></div>
        </dl>
        <p class="footer__note">aauw-aami.org is owned and operated by the Ann Arbor Branch. Our official AAUW-hosted branch site is <a href="<?php echo esc_url(aauw_o('official_url')); ?>" rel="noopener"><?php echo esc_html(preg_replace('#^https?://|/$#', '', aauw_o('official_url'))); ?></a>. <a href="<?php echo esc_url(home_url('/organization/')); ?>">Organization details</a>.</p>
      </section>
      <nav class="footer__col" aria-label="Explore">
        <h2 class="footer__h">Explore</h2>
        <ul>
          <?php aauw_footer_list('footer-explore'); ?>
        </ul>
      </nav>
      <nav class="footer__col" aria-label="Get involved">
        <h2 class="footer__h">Get involved</h2>
        <ul>
          <?php aauw_footer_list('footer-involved'); ?>
        </ul>
      </nav>
      <section class="footer__col" aria-labelledby="footer-connect">
        <h2 id="footer-connect" class="footer__h">Connect</h2>
        <ul>
          <?php aauw_footer_list('footer-connect'); ?>
        </ul>
        <p class="footer__meet"><strong>Branch meetings:</strong> <?php echo esc_html(aauw_o('footer_meet')); ?></p>
      </section>
    </div>
    <div class="footer__bottom">
      <p>© <?php echo esc_html(date('Y')); ?> <?php echo esc_html(aauw_o('short_name')); ?>. AAUW and the AAUW logo are trademarks of the American Association of University Women, used by its Ann Arbor Branch.</p>
      <ul>
        <li><a href="<?php echo esc_url(home_url('/organization/')); ?>">Organization details</a></li>
        <li><a href="<?php echo esc_url(home_url('/accessibility/')); ?>">Accessibility</a></li>
        <li><a href="<?php echo esc_url(home_url('/privacy/')); ?>">Privacy</a></li>
              <li><a href="<?php echo esc_url(is_user_logged_in() ? admin_url() : wp_login_url()); ?>" rel="nofollow"><?php echo is_user_logged_in() ? 'Dashboard' : 'Editor login'; ?></a></li>
      </ul>
    </div>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
