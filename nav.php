<?php
/**
 * partials/nav.php
 * Expects $lang ('en'|'fr'|'ar') and $t (translations array) to be set by the caller.
 */
$isRtl = ($lang === 'ar');
?>
<nav>
  <div style="display:flex;align-items:center;gap:0.75rem;<?= $isRtl ? 'flex-direction:row-reverse;' : '' ?>">
    <a href="<?= $lang === 'en' ? 'index.php' : ($lang === 'fr' ? 'index-fr.php' : 'index-ar.php') ?>" class="nav-logo">
      <svg viewBox="0 0 160 50" fill="none" xmlns="http://www.w3.org/2000/svg">
        <text x="36" y="36" font-family="Sora, sans-serif" font-size="28" font-weight="700" fill="white">pskill</text>
        <path d="M14 12 Q14 28 26 28 Q38 28 38 12" stroke="#3ecf78" stroke-width="3.5" fill="none" stroke-linecap="round"/>
        <path d="M22 6 L26 2 L30 6" stroke="#3ecf78" stroke-width="3.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
        <line x1="26" y1="2" x2="26" y2="18" stroke="#3ecf78" stroke-width="3.5" stroke-linecap="round"/>
      </svg>
      <span><em>Up</em>skill Education</span>
    </a>
    <?php
    $langLinks = [
      'en' => ['href' => 'index.php',    'label' => '🇬🇧 EN'],
      'fr' => ['href' => 'index-fr.php', 'label' => '🇫🇷 FR'],
      'ar' => ['href' => 'index-ar.php', 'label' => '🇲🇦 عربي'],
    ];
    foreach ($langLinks as $code => $link):
      $active = ($code === $lang);
      $style  = $active
        ? 'background:var(--green-glow);border:1px solid rgba(62,207,120,0.4);color:var(--green);font-weight:600;'
        : 'background:transparent;border:1px solid var(--border);color:var(--muted);font-weight:500;';
    ?>
    <a href="<?= $link['href'] ?>" style="display:inline-flex;align-items:center;gap:0.3rem;<?= $style ?>font-family:var(--font);font-size:0.73rem;padding:0.3rem 0.75rem;border-radius:100px;text-decoration:none;">
      <?= $link['label'] ?>
    </a>
    <?php endforeach; ?>
  </div>
  <div class="nav-links">
    <?php if ($isRtl): ?>
      <a href="<?= $t['portal_url'] ?>" class="nav-cta"><?= $t['nav_login'] ?></a>
      <a href="#enroll"><?= $t['nav_enroll'] ?></a>
      <a href="#how"><?= $t['nav_how'] ?></a>
      <a href="#courses"><?= $t['nav_courses'] ?></a>
    <?php else: ?>
      <a href="#courses"><?= $t['nav_courses'] ?></a>
      <a href="#how"><?= $t['nav_how'] ?></a>
      <a href="#enroll"><?= $t['nav_enroll'] ?></a>
      <a href="<?= $t['portal_url'] ?>" class="nav-cta"><?= $t['nav_login'] ?></a>
    <?php endif; ?>
  </div>
</nav>
