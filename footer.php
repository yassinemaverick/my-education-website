<?php
/**
 * partials/footer.php
 * Expects $lang and $t (translations array).
 */
?>
<footer>
  <div class="footer-logo"><em>Up</em>skill Education</div>
  <div class="footer-links">
    <a href="#courses"><?= $t['nav_courses'] ?></a>
    <a href="#enroll"><?= $t['nav_enroll'] ?></a>
    <a href="<?= $t['portal_url'] ?>"><?= $t['footer_portal'] ?></a>
    <a href="mailto:Admin@upskill-edu.com"><?= $t['footer_contact'] ?></a>
  </div>
  <div class="footer-copy"><?= $t['footer_copy'] ?></div>
</footer>
