<?php
/**
 * index-fr.php — Landing page (Français)
 */
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true, 'samesite' => 'Lax',
]);
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$lang = 'fr';
$t = [
    'nav_courses'   => 'Cours',
    'nav_how'       => 'Comment ça marche',
    'nav_enroll'    => 'Inscription',
    'nav_login'     => 'Espace étudiant →',
    'portal_url'    => 'https://study.upskill-edu.com',
    'footer_portal' => 'Espace étudiant',
    'footer_contact'=> 'Contact',
    'footer_copy'   => '© 2026 Upskill Education · upskill-edu.com · Tous droits réservés.',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upskill Education – Cours d'anglais en ligne</title>
<meta name="description" content="Apprenez l'anglais en ligne avec Upskill Education. Cours en direct sur Zoom, petits groupes, suivi personnalisé. 599 DH/session. Inscrivez-vous dès maintenant.">
<link rel="canonical" href="https://upskill-edu.com/fr">
<meta property="og:type"        content="website">
<meta property="og:url"         content="https://upskill-edu.com/fr">
<meta property="og:title"       content="Upskill Education – Cours d'anglais en ligne">
<meta property="og:description" content="Cours d'anglais en direct sur Zoom, petits groupes, suivi personnalisé. 599 DH/session.">
<meta property="og:image"       content="https://upskill-edu.com/assets/img/1.png">
<meta property="og:image:width"  content="1200">
<meta property="og:image:height" content="630">
<link rel="alternate" hreflang="en" href="https://upskill-edu.com/en">
<link rel="alternate" hreflang="fr" href="https://upskill-edu.com/fr">
<link rel="alternate" hreflang="x-default" href="https://upskill-edu.com/en">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/landing.css">
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "name": "Upskill Education",
      "url": "https://upskill-edu.com",
      "logo": "https://upskill-edu.com/assets/img/logo.png",
      "contactPoint": { "@type": "ContactPoint", "contactType": "service client", "availableLanguage": ["French","English","Arabic"] }
    },
    {
      "@type": "Course",
      "name": "Anglais des affaires",
      "description": "Anglais professionnel pour le monde du travail — emails, réunions, présentations.",
      "provider": { "@type": "Organization", "name": "Upskill Education" },
      "educationalLevel": "Intermédiaire à Avancé",
      "inLanguage": "fr",
      "offers": { "@type": "Offer", "priceCurrency": "MAD", "price": "599", "availability": "https://schema.org/LimitedAvailability" }
    },
    {
      "@type": "Course",
      "name": "Anglais général",
      "description": "Anglais conversationnel et écrit pour la communication quotidienne.",
      "provider": { "@type": "Organization", "name": "Upskill Education" },
      "educationalLevel": "Débutant à Intermédiaire",
      "inLanguage": "fr"
    },
    {
      "@type": "Course",
      "name": "Anglais Baccalauréat",
      "description": "Préparation ciblée pour l'examen du Bac marocain — grammaire, lecture et rédaction.",
      "provider": { "@type": "Organization", "name": "Upskill Education" },
      "educationalLevel": "Lycée",
      "inLanguage": "fr"
    }
  ]
}
</script>
</head>
<body>

<nav>
  <div style="display:flex;align-items:center;gap:0.75rem;">
    <a href="/fr" class="nav-logo">
      <svg viewBox="0 0 160 50" fill="none" xmlns="http://www.w3.org/2000/svg">
        <text x="36" y="36" font-family="Sora, sans-serif" font-size="28" font-weight="700" fill="white">pskill</text>
        <path d="M14 12 Q14 28 26 28 Q38 28 38 12" stroke="#3ecf78" stroke-width="3.5" fill="none" stroke-linecap="round"/>
        <path d="M22 6 L26 2 L30 6" stroke="#3ecf78" stroke-width="3.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
        <line x1="26" y1="2" x2="26" y2="18" stroke="#3ecf78" stroke-width="3.5" stroke-linecap="round"/>
      </svg>
      <span><em>Up</em>skill Education</span>
    </a>
    <div class="lang-switch">
      <div class="lang-current">FR <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg></div>
      <div class="lang-dropdown">
        <div class="lang-dropdown-inner">
          <a href="/en" class="lang-opt">EN</a>
          <a href="/fr" class="lang-opt active">FR</a>
        </div>
      </div>
    </div>
  </div>
  <button class="hamburger-btn" id="hamburger" onclick="toggleNav()" aria-label="Ouvrir le menu" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
  <div class="nav-links" id="nav-links">
    <a href="#courses">Cours</a>
    <a href="#how">Comment ça marche</a>
    <a href="#enroll">Inscription</a>
    <a target="_blank" rel="noopener noreferrer" href="https://study.upskill-edu.com" class="nav-cta">Espace étudiant →</a>
  </div>
</nav>



<div class="hero" id="home">
  <div class="hero-badge"><span></span> Inscriptions ouvertes — 2026</div>
  <h1>Maîtrisez l'anglais.<br><span>Ouvrez toutes les portes.</span></h1>
  <p>Des cours d'anglais animés par des experts, conçus pour des résultats concrets — que vous construisiez votre carrière, prépariez le Baccalauréat ou souhaitiez simplement progresser.</p>
  <div class="hero-actions">
    <a href="#courses" class="btn-primary">Découvrir les cours</a>
    <a target="_blank" rel="noopener noreferrer" href="https://wa.me/212702099967" class="btn-outline">Plus d'informations →</a>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="stat-num">3<span>+</span></div><div class="stat-label">Programmes spécialisés</div></div>
  <div class="stat"><div class="stat-num">599<span> Dh</span></div><div class="stat-label">Pour 2,5 mois</div></div>
  <div class="stat"><div class="stat-num">29<span>h</span></div><div class="stat-label">Heures d'enseignement</div></div>
  <div class="stat"><div class="stat-num">9–12</div><div class="stat-label">Étudiants par classe</div></div>
  <div class="stat"><div class="stat-num" id="stat-student-count">—</div><div class="stat-label">Apprenants inscrits</div></div>
</div>
<script>
(function(){
  fetch('https://study.upskill-edu.com/api_public_stats.php')
    .then(r => r.json())
    .then(d => {
      if (d.ok && d.display_count > 0) {
        var el = document.getElementById('stat-student-count');
        if (el) el.innerHTML = d.display_count + '<span>+</span>';
      }
    })
    .catch(function(){});
})();
</script>

<div class="photo-strip">
  <div class="photo-strip-header">
    <span class="section-label">L'apprentissage réinventé</span>
    <h2 class="section-title">Votre salle de classe, où que vous soyez.</h2>
  </div>
  <div class="photos-track">
    <div class="photo-item"><img src="assets/img/5.png" alt="Petit groupe en ligne" width="300" height="220" loading="lazy"><span class="photo-item-caption">Apprenez chez vous</span></div>
    <div class="photo-item"><img src="assets/img/3.png" alt="Groupe d'étudiants" width="300" height="220" loading="lazy"><span class="photo-item-caption">Apprenez partout</span></div>
    <div class="photo-item"><img src="assets/img/1.png" alt="Étudiant avec ordinateur" width="300" height="220" loading="lazy"><span class="photo-item-caption">Professeurs experts</span></div>
    <div class="photo-item"><img src="assets/img/4.png" alt="Cours en ligne sur écran" width="300" height="220" loading="lazy"><span class="photo-item-caption">Étude ciblée</span></div>
    <div class="photo-item"><img src="assets/img/2.png" alt="Étudiants en cours en ligne" width="300" height="220" loading="lazy"><span class="photo-item-caption">Séances en direct</span></div>
    <div class="photo-item"><img src="assets/img/5.png" alt="Petit groupe en ligne" width="300" height="220" loading="lazy"><span class="photo-item-caption">Apprenez chez vous</span></div>
    <div class="photo-item"><img src="assets/img/3.png" alt="Groupe d'étudiants" width="300" height="220" loading="lazy"><span class="photo-item-caption">Apprenez partout</span></div>
    <div class="photo-item"><img src="assets/img/1.png" alt="Étudiant avec ordinateur" width="300" height="220" loading="lazy"><span class="photo-item-caption">Professeurs experts</span></div>
    <div class="photo-item"><img src="assets/img/4.png" alt="Cours en ligne sur écran" width="300" height="220" loading="lazy"><span class="photo-item-caption">Étude ciblée</span></div>
    <div class="photo-item"><img src="assets/img/2.png" alt="Étudiants en cours en ligne" width="300" height="220" loading="lazy"><span class="photo-item-caption">Séances en direct</span></div>
  </div>
</div>

<section id="courses">
  <div class="courses-header">
    <div class="section-label">Nos programmes</div>
    <h2 class="section-title">Trouvez le cours<br>qui vous correspond.</h2>
    <p class="section-sub">Trois programmes ciblés, chacun conçu autour d'un objectif précis. Choisissez celui qui correspond à votre projet.</p>
  </div>
  <div class="courses-grid">

    <div class="course-card" data-course="Anglais des affaires" onclick="selectCourse(this)" tabindex="0" role="button" onkeydown="if(event.key==='Enter'||event.key===' '){selectCourse(this);event.preventDefault();}">
      <div class="course-header">
        <div class="course-icon">💼</div>
        <div class="course-check"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7L5.5 10.5L12 4" stroke="#0f1d2e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      </div>
      <div class="course-title">Anglais des affaires</div>
      <div class="course-desc">Communiquez avec assurance dans un cadre professionnel. Maîtrisez les e-mails, présentations, négociations et conversations en milieu de travail.</div>
      <div class="course-price"><span class="price-amount">599 Dh</span><span class="price-period">/ session &nbsp;·&nbsp; ~2,5 mois</span></div>
      <hr class="course-divider">
      <div class="features-label">Ce qui est inclus</div>
      <ul class="course-features">
        <li>Vocabulaire professionnel pour e-mails, réunions &amp; négociations</li>
        <li>Techniques de présentation et prise de parole en milieu pro</li>
        <li>Terminologie spécifique à votre secteur d'activité</li>
        <li>~29 heures d'enseignement (deux séances de 1h30 par semaine)</li>
        <li>9 à 12 étudiants par classe — séances Zoom en direct</li>
        <li>Exercices, devoirs et évaluations</li>
        <li>Certificat à l'issue du cours</li>
      </ul>
      <div class="course-tags"><span class="tag">Communication pro</span><span class="tag">Présentations</span><span class="tag">Rédaction</span><span class="tag">Négociation</span></div>
    </div>

    <div class="course-card" data-course="Anglais général" onclick="selectCourse(this)" tabindex="0" role="button" onkeydown="if(event.key==='Enter'||event.key===' '){selectCourse(this);event.preventDefault();}">
      <div class="course-header">
        <div class="course-icon">🌍</div>
        <div class="course-check"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7L5.5 10.5L12 4" stroke="#0f1d2e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      </div>
      <div class="course-title">Anglais général</div>
      <div class="course-desc">Construisez de solides bases à l'oral, à l'écoute, en lecture et en écriture. Idéal pour gagner en aisance et en confiance au quotidien.</div>
      <div class="course-price"><span class="price-amount">599 Dh</span><span class="price-period">/ session &nbsp;·&nbsp; ~2,5 mois</span></div>
      <hr class="course-divider">
      <div class="features-label">Ce qui est inclus</div>
      <ul class="course-features">
        <li>Les quatre compétences : oral, écoute, lecture &amp; écriture</li>
        <li>Exercices de grammaire et enrichissement du vocabulaire</li>
        <li>Pratique conversationnelle pour les situations du quotidien</li>
        <li>~29 heures d'enseignement (deux séances de 1h30 par semaine)</li>
        <li>9 à 12 étudiants par classe — séances Zoom en direct</li>
        <li>Exercices, devoirs et évaluations</li>
        <li>Certificat à l'issue du cours</li>
      </ul>
      <div class="course-tags"><span class="tag">Tous niveaux</span><span class="tag">Expression orale</span><span class="tag">Grammaire</span><span class="tag">Vocabulaire</span></div>
    </div>

    <div class="course-card" data-course="Anglais Baccalauréat" onclick="selectCourse(this)" tabindex="0" role="button" onkeydown="if(event.key==='Enter'||event.key===' '){selectCourse(this);event.preventDefault();}">
      <div class="course-header">
        <div class="course-icon">🎓</div>
        <div class="course-check"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7L5.5 10.5L12 4" stroke="#0f1d2e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      </div>
      <div class="course-title">Anglais Baccalauréat</div>
      <div class="course-desc">Préparation ciblée à l'examen du Bac. Entraînez-vous sur de vrais sujets d'examens, rédactions chronométrées et techniques d'oral.</div>
      <div class="course-price"><span class="price-amount">599 Dh</span><span class="price-period">/ session &nbsp;·&nbsp; ~2,5 mois</span></div>
      <hr class="course-divider">
      <div class="features-label">Ce qui est inclus</div>
      <ul class="course-features">
        <li>Vrais sujets du Bac avec entraînements chronométrés</li>
        <li>Techniques de rédaction adaptées au format de l'examen national</li>
        <li>Coaching en compréhension et expression orales</li>
        <li>~29 heures d'enseignement (deux séances de 1h30 par semaine)</li>
        <li>9 à 12 étudiants par classe — séances Zoom en direct</li>
        <li>Exercices, devoirs et évaluations</li>
        <li>Certificat à l'issue du cours</li>
      </ul>
      <div class="course-tags"><span class="tag">Prépa examen</span><span class="tag">Sujets corrigés</span><span class="tag">Oral</span><span class="tag">Rédaction</span></div>
    </div>

  </div>
</section>

<section id="how" style="background: var(--navy-mid); max-width: 100%; padding: 6rem 2rem;">
  <div style="max-width: 1100px; margin: 0 auto;">
    <div class="section-label text-center" style="text-align:center;">La démarche</div>
    <h2 class="section-title text-center" style="text-align:center;">Comment ça marche</h2>
    <p class="section-sub" style="margin: 0 auto; text-align:center;">De l'inscription à la maîtrise — voici votre parcours.</p>
    <div class="steps">
      <div class="step"><div class="step-num">01</div><h3>Choisissez votre cours</h3><p>Parcourez nos trois programmes et sélectionnez celui qui correspond à vos objectifs et à votre niveau actuel.</p></div>
      <div class="step"><div class="step-num">02</div><h3>Soumettez vos informations</h3><p>Renseignez votre nom, e-mail et téléphone. Notre équipe vous contactera sous 24 heures pour confirmer votre inscription.</p></div>
      <div class="step"><div class="step-num">03</div><h3>Recevez vos identifiants</h3><p>Vous recevrez vos identifiants personnels pour accéder à la plateforme étudiant depuis notre équipe administrative.</p></div>
      <div class="step"><div class="step-num">04</div><h3>Commencez à apprendre</h3><p>Connectez-vous à votre tableau de bord, accédez aux ressources, devoirs et suivez votre progression en temps réel.</p></div>
    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<div class="testimonials">
  <div class="testimonials-inner">
    <div class="section-label text-center" style="text-align:center;">Ce que disent nos étudiants</div>
    <h2 class="section-title text-center" style="text-align:center;">Des résultats réels, des expériences formidables.</h2>
    <div class="testimonials-grid">
      <div class="testimonial-card">
        <div class="testimonial-header">
          <img class="testimonial-avatar" src="/assets/img/testimonial-yasmine.png" alt="Yasmine B." width="54" height="54" loading="lazy">
          <div class="testimonial-author">
            <span class="testimonial-name">Yasmine B.</span>
            <span class="testimonial-badge">Étudiante Baccalauréat</span>
          </div>
        </div>
        <div class="testimonial-stars">★★★★★</div>
        <div class="testimonial-quote">"</div>
        <p class="testimonial-text">Ma note d'anglais au Bac est passée de 8/20 à 16/20 après une seule session avec Upskill. Le petit groupe a tout changé.</p>
      </div>
      <div class="testimonial-card">
        <div class="testimonial-header">
          <img class="testimonial-avatar" src="/assets/img/testimonial-karim.jpg" alt="Karim M." width="54" height="54" loading="lazy">
          <div class="testimonial-author">
            <span class="testimonial-name">Karim M.</span>
            <span class="testimonial-badge">Étudiant Anglais des affaires</span>
          </div>
        </div>
        <div class="testimonial-stars">★★★★★</div>
        <div class="testimonial-quote">"</div>
        <p class="testimonial-text">Je me sens enfin à l'aise en réunion. Le cours d'anglais des affaires m'a donné du vocabulaire réel que j'utilise chaque jour.</p>
      </div>
      <div class="testimonial-card">
        <div class="testimonial-header">
          <img class="testimonial-avatar" src="/assets/img/testimonial-nadia.png" alt="Nadia F." width="54" height="54" loading="lazy">
          <div class="testimonial-author">
            <span class="testimonial-name">Nadia F.</span>
            <span class="testimonial-badge">Parent · Anglais général</span>
          </div>
        </div>
        <div class="testimonial-stars">★★★★★</div>
        <div class="testimonial-quote">"</div>
        <p class="testimonial-text">La prononciation de ma fille s'est nettement améliorée. Les professeurs sont patients et la plateforme facilite le suivi de sa progression.</p>
      </div>
    </div>
  </div>
</div>

<div class="enroll-section" id="enroll">
  <div class="enroll-inner">
    <div class="section-label text-center" style="text-align:center;">Inscription</div>
    <h2 class="section-title text-center" style="text-align:center;">Prêt à commencer ?</h2>
    <p class="section-sub" style="margin:0 auto;text-align:center;">Remplissez le formulaire et nous vous recontacterons sous 24 heures pour confirmer votre place.</p>
    <div class="form-card">
      <div id="form-area">
        <div id="selected-pill" style="display:none;" class="selected-course-pill">
          <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M1.5 6L4.5 9L10.5 3" stroke="#3ecf78" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span id="pill-text">Anglais des affaires</span>
        </div>
        <div class="form-group">
          <label for="course-select">CHOISIR UN COURS</label>
          <select id="course-select" required>
            <option value="">— Choisissez un programme —</option>
            <option value="Anglais des affaires">Anglais des affaires</option>
            <option value="Anglais général">Anglais général</option>
            <option value="Anglais Baccalauréat">Anglais Baccalauréat</option>
          </select>
        </div>
        <div class="form-group">
          <label for="name">NOM COMPLET</label>
          <input type="text" id="name" placeholder="Votre nom complet" required>
        </div>
        <div class="form-group">
          <label for="level">NIVEAU ACTUEL</label>
          <select id="level" required>
            <option value="">— Sélectionnez votre niveau —</option>
            <option value="Beginner">Débutant — peu ou pas d'anglais</option>
            <option value="Elementary">Élémentaire — expressions et vocabulaire de base</option>
            <option value="Intermediate">Intermédiaire — conversations simples possibles</option>
            <option value="Advanced">Avancé — courant mais perfectionnement souhaité</option>
          </select>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label for="email">ADRESSE E-MAIL</label>
            <input type="email" id="email" placeholder="vous@exemple.com" required>
          </div>
          <div class="form-group">
            <label for="phone">NUMÉRO DE TÉLÉPHONE</label>
            <input type="tel" id="phone" placeholder="+212 6XX XXX XXX" required>
          </div>
        </div>
        <button class="btn-submit" onclick="submitForm()">Envoyer ma demande d'inscription →</button>
        <p class="form-notice">Vos informations sont enregistrées de manière sécurisée et transmises directement à notre équipe. Nous vous contacterons sous 24 heures.</p>
      </div>
      <div class="success-msg" id="success-msg">
        <div class="success-icon">✓</div>
        <h3>Demande reçue !</h3>
        <p>Merci pour votre inscription. Notre équipe examinera votre demande et vous contactera sous 24 heures pour confirmer votre place et vous envoyer vos identifiants de connexion.</p>
      </div>
    </div>
  </div>
</div>



<footer>
  <div class="footer-grid">
    <!-- Marque -->
    <div class="footer-brand">
      <span class="footer-logo"><em>Up</em>skill Education</span>
      <p>Formation anglais professionnelle pour les apprenants marocains. Sessions Zoom en direct, enseignants experts, résultats concrets.</p>
    </div>
    <!-- Liens rapides -->
    <div class="footer-col">
      <h4>Liens rapides</h4>
      <div class="footer-links">
        <a href="#courses">Cours</a>
        <a href="#enroll">S'inscrire</a>
        <a href="#how">Comment ça marche</a>
        <a target="_blank" rel="noopener noreferrer" href="https://study.upskill-edu.com">Espace étudiant</a>
        <a href="#" onclick="openContact();return false;">Nous contacter</a>
      </div>
    </div>
    <!-- Contact -->
    <div class="footer-col">
      <h4>Contact</h4>
      <!-- TODO: remplacer par l'adresse réelle -->
      <div class="footer-contact-item">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        <span>Casablanca, Maroc</span>
      </div>
      <div class="footer-contact-item">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.07 12 19.79 19.79 0 0 1 1 3.18 2 2 0 0 1 3 1h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        <a href="https://wa.me/212702099967" target="_blank" rel="noopener noreferrer">+212 702 099 967</a>
      </div>
      <!-- TODO: remplacer par l'email réel -->
      <div class="footer-contact-item">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        <a href="mailto:contact@upskill-edu.com">contact@upskill-edu.com</a>
      </div>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="footer-copy">© 2026 Upskill Education &middot; upskill-edu.com &middot; Tous droits réservés.</div>
    <!-- TODO: ajouter les vrais liens sociaux -->
    <div class="footer-social">
      <a href="#" aria-label="Facebook" title="Facebook"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg></a>
      <a href="#" aria-label="Instagram" title="Instagram"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg></a>
      <a href="https://wa.me/212702099967" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp" title="WhatsApp">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.656 1.438 5.168L2 22l4.975-1.395A9.96 9.96 0 0 0 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm5.093 13.677c-.22.616-1.287 1.174-1.762 1.214-.476.04-.923.214-3.1-.648-2.606-1.038-4.267-3.7-4.396-3.872-.128-.172-1.053-1.402-1.053-2.676 0-1.273.665-1.9.9-2.16.236-.258.516-.322.687-.322.172 0 .344.002.494.008.159.006.37-.06.58.458.215.518.731 1.79.795 1.921.064.13.107.282.021.452-.086.172-.13.28-.258.43-.13.15-.272.337-.386.45-.13.13-.264.27-.113.527.15.258.666 1.098 1.43 1.778.982.874 1.815 1.143 2.072 1.273.257.13.408.108.558-.065.15-.172.63-.737.797-.99.168-.257.336-.215.565-.13.23.086 1.452.685 1.702.81.25.128.416.192.479.3.063.107.063.579-.157 1.2z"/></svg>
      </a>
    </div>
  </div>
</footer>

<div id="toast"></div>

<script>
  const CSRF_TOKEN = <?= json_encode($csrf) ?>;
  let selectedCourse = '';

  function selectCourse(card) {
    document.querySelectorAll('.course-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    selectedCourse = card.dataset.course;
    document.getElementById('course-select').value = selectedCourse;
    document.getElementById('pill-text').textContent = selectedCourse;
    document.getElementById('selected-pill').style.display = 'inline-flex';
    document.getElementById('enroll').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  document.getElementById('course-select').addEventListener('change', function() {
    selectedCourse = this.value;
    if (selectedCourse) {
      document.getElementById('pill-text').textContent = selectedCourse;
      document.getElementById('selected-pill').style.display = 'inline-flex';
      document.querySelectorAll('.course-card').forEach(c => {
        c.classList.toggle('selected', c.dataset.course === selectedCourse);
      });
    }
  });

  function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3500);
  }

  async function submitForm() {
    const name   = document.getElementById('name').value.trim();
    const email  = document.getElementById('email').value.trim();
    const phone  = document.getElementById('phone').value.trim();
    const course = document.getElementById('course-select').value;
    const level  = document.getElementById('level').value;

    if (!course) { showToast('Veuillez d\'abord choisir un cours.'); return; }
    if (!name)   { showToast('Veuillez saisir votre nom complet.'); return; }
    if (!level)  { showToast('Veuillez sélectionner votre niveau actuel.'); return; }
    if (!email || !email.includes('@')) { showToast('Veuillez saisir une adresse e-mail valide.'); return; }
    if (!phone)  { showToast('Veuillez saisir votre numéro de téléphone.'); return; }

    const btn = document.querySelector('.btn-submit');
    btn.disabled = true;
    btn.textContent = 'Envoi en cours…';

    try {
      const body = new URLSearchParams({ csrf_token: CSRF_TOKEN, name, email, phone, course, level, lang: 'fr' });
      const res  = await fetch('/enroll.php', { method: 'POST', body });
      const data = await res.json();

      if (data.ok) {
        document.getElementById('form-area').style.display = 'none';
        document.getElementById('success-msg').style.display = 'block';
      } else {
        showToast(data.error || 'Une erreur s\'est produite. Veuillez réessayer.');
        btn.disabled = false;
        btn.textContent = 'Envoyer ma demande d\'inscription →';
      }
    } catch (err) {
      showToast('Erreur réseau. Vérifiez votre connexion et réessayez.');
      btn.disabled = false;
      btn.textContent = 'Envoyer ma demande d\'inscription →';
    }
  }
</script>

<!-- ── CONTACT MODAL ── -->
<div id="contact-overlay" onclick="if(event.target===this)closeContact()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#162436;border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:2rem;width:100%;max-width:480px;margin:1rem;position:relative;">
    <button onclick="closeContact()" style="position:absolute;top:1rem;right:1rem;background:none;border:none;color:rgba(255,255,255,.4);font-size:1.3rem;cursor:pointer;line-height:1;">✕</button>
    <div style="font-family:'Sora',sans-serif;font-size:1.2rem;font-weight:700;margin-bottom:.3rem;">Nous contacter</div>
    <div style="color:rgba(255,255,255,.55);font-size:.88rem;margin-bottom:1.5rem;font-family:'DM Sans',sans-serif;">Envoyez-nous un message, nous vous répondrons sous 24 heures.</div>
    <div style="margin-bottom:1rem;">
      <label style="display:block;font-family:'Sora',sans-serif;font-size:.75rem;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:.4rem;letter-spacing:.04em;text-transform:uppercase;">VOTRE NOM</label>
      <input id="c-name" type="text" placeholder="Votre nom complet" maxlength="120"
        style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#fff;font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;box-sizing:border-box;">
    </div>
    <div style="margin-bottom:1rem;">
      <label style="display:block;font-family:'Sora',sans-serif;font-size:.75rem;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:.4rem;letter-spacing:.04em;text-transform:uppercase;">ADRESSE E-MAIL</label>
      <input id="c-email" type="email" placeholder="vous@exemple.com" maxlength="180"
        style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#fff;font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;box-sizing:border-box;">
    </div>
    <div style="margin-bottom:1rem;">
      <label style="display:block;font-family:'Sora',sans-serif;font-size:.75rem;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:.4rem;letter-spacing:.04em;text-transform:uppercase;">
        NUMÉRO DE TÉLÉPHONE <span style="font-weight:400;font-size:.7rem;color:rgba(255,255,255,.35);text-transform:none;letter-spacing:0;">(optionnel)</span>
      </label>
      <input id="c-phone" type="tel" placeholder="Ex: 0612345678" maxlength="30"
        style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#fff;font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;box-sizing:border-box;">
    </div>
    <div style="margin-bottom:1.5rem;">
      <label style="display:block;font-family:'Sora',sans-serif;font-size:.75rem;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:.4rem;letter-spacing:.04em;text-transform:uppercase;">VOTRE MESSAGE</label>
      <textarea id="c-msg" placeholder="Écrivez votre message ici…" maxlength="2000" rows="4"
        style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#fff;font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;resize:vertical;box-sizing:border-box;"></textarea>
    </div>
    <div id="c-error" style="display:none;color:#f87171;font-size:.83rem;margin-bottom:.8rem;font-family:'DM Sans',sans-serif;"></div>
    <button id="c-btn" onclick="sendContact()"
      style="width:100%;padding:.9rem;background:#3ecf78;color:#0f1d2e;font-family:'Sora',sans-serif;font-weight:700;font-size:.95rem;border:none;border-radius:12px;cursor:pointer;transition:background .2s;">
      Envoyer le message →
    </button>
    <div id="c-success" style="display:none;text-align:center;padding:1.5rem 0;">
      <div style="font-size:2.5rem;margin-bottom:.5rem;">✅</div>
      <div style="font-family:'Sora',sans-serif;font-weight:600;margin-bottom:.3rem;">Message envoyé !</div>
      <div style="color:rgba(255,255,255,.55);font-size:.88rem;font-family:'DM Sans',sans-serif;">Nous vous répondrons bientôt.</div>
    </div>
  </div>
</div>

<script>
function openContact() {
  const o = document.getElementById('contact-overlay');
  o.style.display = 'flex';
  document.getElementById('c-name').focus();
  document.getElementById('c-error').style.display = 'none';
  document.getElementById('c-success').style.display = 'none';
  document.getElementById('c-btn').style.display = '';
  document.getElementById('c-btn').disabled = false;
  document.getElementById('c-btn').textContent = 'Envoyer le message →';
}
function closeContact() {
  document.getElementById('contact-overlay').style.display = 'none';
  ['c-name','c-email','c-phone','c-msg'].forEach(id => document.getElementById(id).value = '');
  const s = document.getElementById('c-success'); if (s) s.style.display = 'none';
  const e = document.getElementById('c-error');   if (e) e.style.display = 'none';
}
async function sendContact() {
  const name  = document.getElementById('c-name').value.trim();
  const email = document.getElementById('c-email').value.trim();
  const phone = document.getElementById('c-phone').value.trim();
  const msg   = document.getElementById('c-msg').value.trim();
  const errEl = document.getElementById('c-error');
  errEl.style.display = 'none';

  if (!name)  { errEl.textContent = 'Veuillez saisir votre nom.'; errEl.style.display = ''; return; }
  if (!email || !email.includes('@')) { errEl.textContent = 'Adresse e-mail invalide.'; errEl.style.display = ''; return; }
  if (!msg)   { errEl.textContent = 'Veuillez saisir votre message.'; errEl.style.display = ''; return; }

  const btn = document.getElementById('c-btn');
  btn.disabled = true; btn.textContent = 'Envoi…';

  try {
    const body = new URLSearchParams({ csrf_token: CSRF_TOKEN, name, email, phone, message: msg, lang: 'fr' });
    const res  = await fetch('/contact.php', { method: 'POST', body });
    const data = await res.json();
    if (data.ok) {
      document.getElementById('c-success').style.display = '';
      btn.style.display = 'none';
    } else {
      errEl.textContent = data.error || 'Erreur. Veuillez réessayer.';
      errEl.style.display = '';
      btn.disabled = false; btn.textContent = 'Envoyer le message →';
    }
  } catch(e) {
    errEl.textContent = 'Erreur réseau. Vérifiez votre connexion.';
    errEl.style.display = '';
    btn.disabled = false; btn.textContent = 'Envoyer le message →';
  }
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeContact(); });

function toggleNav() {
  const links = document.getElementById('nav-links');
  const btn   = document.getElementById('hamburger');
  const open  = links.classList.toggle('open');
  btn.setAttribute('aria-expanded', open);
}
document.addEventListener('click', e => {
  const links = document.getElementById('nav-links');
  const btn   = document.getElementById('hamburger');
  if (links && btn && !links.contains(e.target) && !btn.contains(e.target)) {
    links.classList.remove('open');
    btn.setAttribute('aria-expanded', 'false');
  }
});
</script>

<a href="https://wa.me/212702099967" target="_blank" rel="noopener noreferrer"
   style="position:fixed;bottom:2rem;left:2rem;z-index:998;background:#25d366;color:white;width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(37,211,102,.45);text-decoration:none;transition:transform .2s;"
   onmouseenter="this.style.transform='scale(1.12)'" onmouseleave="this.style.transform=''"
   title="Contacter sur WhatsApp">
  <svg width="26" height="26" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
    <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.656 1.438 5.168L2 22l4.975-1.395A9.96 9.96 0 0 0 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm5.093 13.677c-.22.616-1.287 1.174-1.762 1.214-.476.04-.923.214-3.1-.648-2.606-1.038-4.267-3.7-4.396-3.872-.128-.172-1.053-1.402-1.053-2.676 0-1.273.665-1.9.9-2.16.236-.258.516-.322.687-.322.172 0 .344.002.494.008.159.006.37-.06.58.458.215.518.731 1.79.795 1.921.064.13.107.282.021.452-.086.172-.13.28-.258.43-.13.15-.272.337-.386.45-.13.13-.264.27-.113.527.15.258.666 1.098 1.43 1.778.982.874 1.815 1.143 2.072 1.273.257.13.408.108.558-.065.15-.172.63-.737.797-.99.168-.257.336-.215.565-.13.23.086 1.452.685 1.702.81.25.128.416.192.479.3.063.107.063.579-.157 1.2z"/>
  </svg>
</a>

</body>
</html>
