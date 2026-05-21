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
  <div class="footer-social">
    <a href="https://www.instagram.com/up_skill_education?igsh=NzRiZWJwaDhteGp6" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
    </a>
  </div>
</footer>
