<?php
/**
 * index.php — Landing page (English)
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

$lang = 'en';
$t = [
    'nav_courses'   => 'Courses',
    'nav_how'       => 'How it works',
    'nav_enroll'    => 'Enroll',
    'nav_login'     => 'Student Login →',
    'portal_url'    => 'https://study.upskill-edu.com',
    'footer_portal' => 'Student Portal',
    'footer_contact'=> 'Contact',
    'footer_copy'   => '© 2026 Upskill Education · upskill-edu.com · All rights reserved.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upskill Education – Online English Courses</title>
<meta name="description" content="Learn English online with Upskill Education. Live Zoom classes, small groups, personalised progress tracking. 599 DH per 2.5 months. Enroll now.">
<link rel="canonical" href="https://upskill-edu.com/en">
<meta property="og:type"        content="website">
<meta property="og:url"         content="https://upskill-edu.com/en">
<meta property="og:title"       content="Upskill Education – Online English Courses">
<meta property="og:description" content="Live Zoom English classes, small groups, personalised tracking. 599 DH per 2.5 months.">
<meta property="og:image"       content="https://upskill-edu.com/assets/img/1.png">
<meta property="og:image:width"  content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale"       content="en_US">
<link rel="alternate" hreflang="en" href="https://upskill-edu.com/en">
<link rel="alternate" hreflang="fr" href="https://upskill-edu.com/fr">
<link rel="alternate" hreflang="x-default" href="https://upskill-edu.com/en">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap"></noscript>
<link rel="stylesheet" href="/css/landing.css?v=2">
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "name": "Upskill Education",
      "url": "https://upskill-edu.com",
      "logo": "https://upskill-edu.com/assets/img/logo.png",
      "contactPoint": { "@type": "ContactPoint", "contactType": "customer service", "availableLanguage": ["French","English"] }
    },
    {
      "@type": "Course",
      "name": "Business English",
      "description": "Professional English for the workplace — emails, meetings, presentations.",
      "provider": { "@type": "Organization", "name": "Upskill Education" },
      "educationalLevel": "Intermediate to Advanced",
      "inLanguage": "en",
      "offers": { "@type": "Offer", "priceCurrency": "MAD", "price": "599", "availability": "https://schema.org/LimitedAvailability" }
    },
    {
      "@type": "Course",
      "name": "General English",
      "description": "Conversational and written English for everyday communication and confidence.",
      "provider": { "@type": "Organization", "name": "Upskill Education" },
      "educationalLevel": "Beginner to Intermediate",
      "inLanguage": "en",
      "offers": { "@type": "Offer", "priceCurrency": "MAD", "price": "599", "availability": "https://schema.org/LimitedAvailability" }
    },
    {
      "@type": "Course",
      "name": "English for Baccalaureate",
      "description": "Targeted preparation for the Moroccan Bac exam — grammar, reading and writing.",
      "provider": { "@type": "Organization", "name": "Upskill Education" },
      "educationalLevel": "Secondary",
      "inLanguage": "en",
      "offers": { "@type": "Offer", "priceCurrency": "MAD", "price": "599", "availability": "https://schema.org/LimitedAvailability" }
    },
    {
      "@type": "Course",
      "name": "English for Kids",
      "description": "Interactive English lessons for children aged 7–14 — vocabulary, speaking, and confidence through games, stories, and creative activities.",
      "provider": { "@type": "Organization", "name": "Upskill Education" },
      "educationalLevel": "Primary",
      "inLanguage": "en",
      "offers": { "@type": "Offer", "priceCurrency": "MAD", "price": "599", "availability": "https://schema.org/LimitedAvailability" }
    }
  ]
}
</script>
</head>
<body>

<nav>
  <div style="display:flex;align-items:center;gap:0.75rem;">
    <a href="/en" class="nav-logo">
      <svg viewBox="0 0 160 50" fill="none" xmlns="http://www.w3.org/2000/svg">
        <text x="36" y="36" font-family="Sora, sans-serif" font-size="28" font-weight="700" fill="white">pskill</text>
        <path d="M14 12 Q14 28 26 28 Q38 28 38 12" stroke="#3ecf78" stroke-width="3.5" fill="none" stroke-linecap="round"/>
        <path d="M22 6 L26 2 L30 6" stroke="#3ecf78" stroke-width="3.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
        <line x1="26" y1="2" x2="26" y2="18" stroke="#3ecf78" stroke-width="3.5" stroke-linecap="round"/>
      </svg>
      <span><em>Up</em>skill Education</span>
    </a>
    <div class="lang-switch">
      <div class="lang-current">EN <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg></div>
      <div class="lang-dropdown">
        <div class="lang-dropdown-inner">
          <a href="/en" class="lang-opt active">EN</a>
          <a href="/fr" class="lang-opt">FR</a>
        </div>
      </div>
    </div>
  </div>
  <button class="hamburger-btn" id="hamburger" onclick="toggleNav()" aria-label="Open menu" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
  <div class="nav-links" id="nav-links">
    <a href="#courses">Courses</a>
    <a href="#how">How it works</a>
    <a href="#enroll">Enroll</a>
    <a target="_blank" rel="noopener noreferrer" href="https://study.upskill-edu.com" class="nav-cta">Student Login &rarr;</a>
  </div>
</nav>

<main>
<div class="hero" id="home">
  <div class="hero-badge"><span></span> Enrolling Now — 2026</div>
  <h1>Master English.<br><span>Unlock Every Door.</span></h1>
  <p>Expert-led English courses designed for real results — whether you're building a career, acing your Baccalaureate, or simply leveling up.</p>
  <div class="hero-actions">
    <a href="#courses" class="btn-primary">Explore Courses</a>
    <a target="_blank" rel="noopener noreferrer" href="https://wa.me/212702099967" class="btn-outline">More information on WhatsApp →</a>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="stat-num">4<span>+</span></div><div class="stat-label">Specialized programs</div></div>
  <div class="stat"><div class="stat-num">599<span> Dh</span></div><div class="stat-label">Per 2.5 months</div></div>
  <div class="stat"><div class="stat-num">30<span>h</span></div><div class="stat-label">Hours of instruction</div></div>
  <div class="stat"><div class="stat-num">9–12</div><div class="stat-label">Students per class</div></div>
  <div class="stat"><div class="stat-num" id="stat-student-count">—</div><div class="stat-label">Enrolled learners</div></div>
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
    <span class="section-label">Learning Reimagined</span>
    <h2 class="section-title">Your classroom, anywhere in the world.</h2>
  </div>
  <div class="photos-track">
    <div class="photo-item"><picture><source srcset="assets/img/5.webp" type="image/webp"><img src="assets/img/5.png" alt="Student in online class" width="300" height="220" loading="lazy"></picture><span class="photo-item-caption">Learn from home</span></div>
    <div class="photo-item"><picture><source srcset="assets/img/3.webp" type="image/webp"><img src="assets/img/3.png" alt="Students studying together" width="300" height="220" loading="lazy"></picture><span class="photo-item-caption">Learn anywhere</span></div>
    <div class="photo-item"><picture><source srcset="assets/img/10.webp" type="image/webp"><img src="assets/img/10.png" alt="Individual student learning" width="300" height="220" loading="lazy"></picture><span class="photo-item-caption">Expert teachers</span></div>
    <div class="photo-item"><picture><source srcset="assets/img/4.webp" type="image/webp"><img src="assets/img/4.png" alt="Online lesson on screen" width="300" height="220" loading="lazy"></picture><span class="photo-item-caption">Focused study</span></div>
    <div class="photo-item"><picture><source srcset="assets/img/2.webp" type="image/webp"><img src="assets/img/2.png" alt="Students collaborating online" width="300" height="220" loading="lazy"></picture><span class="photo-item-caption">Live sessions</span></div>
    <div class="photo-item" aria-hidden="true"><picture><source srcset="assets/img/5.webp" type="image/webp"><img src="assets/img/5.png" alt="" width="300" height="220" loading="lazy"></picture><span class="photo-item-caption">Learn from home</span></div>
    <div class="photo-item" aria-hidden="true"><picture><source srcset="assets/img/3.webp" type="image/webp"><img src="assets/img/3.png" alt="" width="300" height="220" loading="lazy"></picture><span class="photo-item-caption">Learn anywhere</span></div>
    <div class="photo-item" aria-hidden="true"><picture><source srcset="assets/img/10.webp" type="image/webp"><img src="assets/img/10.png" alt="" width="300" height="220" loading="lazy"></picture><span class="photo-item-caption">Expert teachers</span></div>
    <div class="photo-item" aria-hidden="true"><picture><source srcset="assets/img/4.webp" type="image/webp"><img src="assets/img/4.png" alt="" width="300" height="220" loading="lazy"></picture><span class="photo-item-caption">Focused study</span></div>
    <div class="photo-item" aria-hidden="true"><picture><source srcset="assets/img/2.webp" type="image/webp"><img src="assets/img/2.png" alt="" width="300" height="220" loading="lazy"></picture><span class="photo-item-caption">Live sessions</span></div>
  </div>
</div>

<section id="courses">
  <div class="courses-header">
    <div class="section-label">Our Programs</div>
    <h2 class="section-title">Find the right<br>course for you.</h2>
    <p class="section-sub">Four focused programs, each built around a specific goal. Select the one that matches where you're headed.</p>
  </div>
  <div class="courses-grid">

    <div class="course-card" data-course="Business English" onclick="selectCourse(this)" tabindex="0" role="button" onkeydown="if(event.key==='Enter'||event.key===' '){selectCourse(this);event.preventDefault();}">
      <div class="course-header">
        <div class="course-icon">💼</div>
        <div class="course-check"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7L5.5 10.5L12 4" stroke="#0f1d2e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      </div>
      <div class="course-title">Business English</div>
      <div class="course-desc">Communicate with confidence in professional settings. Master emails, presentations, negotiations, and workplace conversations.</div>
      <div class="course-price"><span class="price-amount">599 Dh</span><span class="price-period">/ 2.5 months</span></div>
      <hr class="course-divider">
      <div class="features-label">What's included</div>
      <ul class="course-features">
        <li>Professional vocabulary for emails, meetings &amp; negotiations</li>
        <li>Presentation skills and workplace speaking confidence</li>
        <li>Industry-specific language for your sector</li>
        <li>~30 hours of instruction (two 1.5-hour sessions per week)</li>
        <li>9 to 12 students per class — live Zoom sessions</li>
        <li>Exercises, homework, and tests</li>
        <li>Certificate upon completion</li>
      </ul>
      <div class="course-tags"><span class="tag">Corporate Communication</span><span class="tag">Presentations</span><span class="tag">Writing</span><span class="tag">Negotiation</span></div>
    </div>

    <div class="course-card" data-course="General English" onclick="selectCourse(this)" tabindex="0" role="button" onkeydown="if(event.key==='Enter'||event.key===' '){selectCourse(this);event.preventDefault();}">
      <div class="course-header">
        <div class="course-icon">🌍</div>
        <div class="course-check"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7L5.5 10.5L12 4" stroke="#0f1d2e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      </div>
      <div class="course-title">General English</div>
      <div class="course-desc">Build a strong foundation in speaking, listening, reading, and writing. Ideal for everyday fluency and confidence at any level.</div>
      <div class="course-price"><span class="price-amount">599 Dh</span><span class="price-period">/ 2.5 months</span></div>
      <hr class="course-divider">
      <div class="features-label">What's included</div>
      <ul class="course-features">
        <li>All four skills: speaking, listening, reading &amp; writing</li>
        <li>Grammar drills and practical vocabulary expansion</li>
        <li>Conversational practice for everyday real-life situations</li>
        <li>~30 hours of instruction (two 1.5-hour sessions per week)</li>
        <li>9 to 12 students per class — live Zoom sessions</li>
        <li>Exercises, homework, and tests</li>
        <li>Certificate upon completion</li>
      </ul>
      <div class="course-tags"><span class="tag">All Levels</span><span class="tag">Speaking</span><span class="tag">Grammar</span><span class="tag">Vocabulary</span></div>
    </div>

    <div class="course-card" data-course="English for Baccalaureate" onclick="selectCourse(this)" tabindex="0" role="button" onkeydown="if(event.key==='Enter'||event.key===' '){selectCourse(this);event.preventDefault();}">
      <div class="course-header">
        <div class="course-icon">🎓</div>
        <div class="course-check"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7L5.5 10.5L12 4" stroke="#0f1d2e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      </div>
      <div class="course-title">English for Baccalaureate</div>
      <div class="course-desc">Targeted exam preparation for Bac students. Practice with real past papers, timed essays, comprehension exercises and oral techniques.</div>
      <div class="course-price"><span class="price-amount">599 Dh</span><span class="price-period">/ 2.5 months</span></div>
      <hr class="course-divider">
      <div class="features-label">What's included</div>
      <ul class="course-features">
        <li>Real past Bac papers with timed practice exams</li>
        <li>Essay writing techniques for the national exam format</li>
        <li>Oral comprehension and expression coaching</li>
        <li>~30 hours of instruction (two 1.5-hour sessions per week)</li>
        <li>9 to 12 students per class — live Zoom sessions</li>
        <li>Exercises, homework, and tests</li>
      </ul>
      <div class="course-tags"><span class="tag">Exam Prep</span><span class="tag">Past Papers</span><span class="tag">Oral Practice</span><span class="tag">Writing</span></div>
    </div>

    <div class="course-card" data-course="English for Kids" onclick="selectCourse(this)" tabindex="0" role="button" onkeydown="if(event.key==='Enter'||event.key===' '){selectCourse(this);event.preventDefault();}">
      <div class="course-header">
        <div class="course-icon">🎨</div>
        <div class="course-check"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7L5.5 10.5L12 4" stroke="#0f1d2e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      </div>
      <div class="course-title">English for Kids</div>
      <div class="course-desc">Fun, interactive English lessons designed for children aged 7–14. Build vocabulary, confidence, and a genuine love for the language through games, stories, and creative activities.</div>
      <div class="course-price"><span class="price-amount">599 Dh</span><span class="price-period">/ 3 months</span></div>
      <hr class="course-divider">
      <div class="features-label">What's included</div>
      <ul class="course-features">
        <li>Interactive lessons through games, stories and role-play</li>
        <li>Speaking, listening and vocabulary building</li>
        <li>Age-appropriate materials designed for children</li>
        <li>~30 hours of instruction (two 1h15 sessions per week)</li>
        <li>9 to 12 students per class — live Zoom sessions</li>
        <li>Exercises, homework, and tests</li>
        <li>Certificate upon completion</li>
      </ul>
      <div class="course-tags"><span class="tag">Ages 7–14</span><span class="tag">Games &amp; Stories</span><span class="tag">Vocabulary</span><span class="tag">Speaking</span></div>
    </div>

  </div>
</section>

<section id="how" style="background: var(--navy-mid); max-width: 100%; padding: 6rem 2rem;">
  <div style="max-width: 1100px; margin: 0 auto;">
    <div class="section-label text-center" style="text-align:center;">The Process</div>
    <h2 class="section-title text-center" style="text-align:center;">How it works</h2>
    <p class="section-sub" style="margin: 0 auto; text-align:center;">From sign-up to fluency — here's your journey.</p>
    <div class="steps">
      <div class="step"><div class="step-num">01</div><h3>Choose your course</h3><p>Browse our three programs and select the one that fits your goals and current level.</p></div>
      <div class="step"><div class="step-num">02</div><h3>Submit your details</h3><p>Fill in your name, email and phone. Our team will reach out within 24 hours to confirm your enrollment.</p></div>
      <div class="step"><div class="step-num">03</div><h3>Get your login</h3><p>Receive your personal credentials for the student platform from our admin team — via WhatsApp or email within 24 hours.</p></div>
      <div class="step"><div class="step-num">04</div><h3>Start learning</h3><p>Log in to your dashboard, access materials, assignments, and track your progress live.</p></div>
    </div>
  </div>
</section>

<!-- OUR APPROACH -->
<div style="padding:6rem 2rem;background:#162436;border-top:1px solid rgba(255,255,255,0.1);border-bottom:1px solid rgba(255,255,255,0.1);">
  <div style="max-width:1100px;margin:0 auto;">
    <div class="section-label" style="text-align:center;">Our Approach</div>
    <h2 class="section-title" style="text-align:center;">We don't teach English.<br>We help students live it.</h2>
    <p style="color:rgba(255,255,255,0.55);font-size:1rem;line-height:1.75;max-width:580px;margin:.75rem auto 3rem;text-align:center;font-weight:300;">Our teaching is student-centered at every step — because we believe real progress only happens when students feel involved, understood, and challenged in the right way.</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.5rem;">
      <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:1.8rem;display:flex;flex-direction:column;gap:.75rem;">
        <div style="font-size:1.75rem;line-height:1;">🎯</div>
        <h3 style="font-family:'Sora',sans-serif;font-size:.97rem;font-weight:600;color:#fff;line-height:1.3;margin:0;">Student-centered from day one</h3>
        <p style="color:rgba(255,255,255,0.55);font-size:.87rem;line-height:1.75;font-weight:300;margin:0;">Every student arrives with a different goal — a better Bac grade, a job interview, a promotion, a dream of studying abroad. We start there. Our teachers adapt the lesson to the student, not the other way around.</p>
      </div>
      <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:1.8rem;display:flex;flex-direction:column;gap:.75rem;">
        <div style="font-size:1.75rem;line-height:1;">💬</div>
        <h3 style="font-family:'Sora',sans-serif;font-size:.97rem;font-weight:600;color:#fff;line-height:1.3;margin:0;">English in context, not in isolation</h3>
        <p style="color:rgba(255,255,255,0.55);font-size:.87rem;line-height:1.75;font-weight:300;margin:0;">Grammar rules are easy to forget by Friday. Real conversations aren't. We build lessons around situations students actually face — presenting at work, writing emails, talking about their passions — so the language sticks because it matters.</p>
      </div>
      <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:1.8rem;display:flex;flex-direction:column;gap:.75rem;">
        <div style="font-size:1.75rem;line-height:1;">👥</div>
        <h3 style="font-family:'Sora',sans-serif;font-size:.97rem;font-weight:600;color:#fff;line-height:1.3;margin:0;">Small groups, real voices</h3>
        <p style="color:rgba(255,255,255,0.55);font-size:.87rem;line-height:1.75;font-weight:300;margin:0;">With 9 to 12 students per group, no one hides at the back. Everyone speaks. Everyone is heard. The small format creates a low-pressure environment where students take risks with the language — and that's where real progress happens.</p>
      </div>
      <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:1.8rem;display:flex;flex-direction:column;gap:.75rem;">
        <div style="font-size:1.75rem;line-height:1;">📈</div>
        <h3 style="font-family:'Sora',sans-serif;font-size:.97rem;font-weight:600;color:#fff;line-height:1.3;margin:0;">Progress you can see</h3>
        <p style="color:rgba(255,255,255,0.55);font-size:.87rem;line-height:1.75;font-weight:300;margin:0;">Students track their attendance, grades, and lesson notes directly on their dashboard after every session. Learning feels less like a course and more like a journey with a clear direction.</p>
      </div>
    </div>
  </div>
</div>

<!-- FAQ -->
<div style="padding:6rem 2rem;background:#0f1d2e;">
  <div style="max-width:760px;margin:0 auto;">
    <div class="section-label" style="text-align:center;">FAQ</div>
    <h2 class="section-title" style="text-align:center;margin-bottom:3rem;">Common questions</h2>
    <div style="display:flex;flex-direction:column;gap:.75rem;">

      <details style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:14px;overflow:hidden;">
        <summary style="font-family:'Sora',sans-serif;font-size:.97rem;font-weight:600;color:#fff;padding:1.4rem 1.6rem;cursor:pointer;user-select:none;">Do I need a computer, or can I use my phone?</summary>
        <p style="color:rgba(255,255,255,0.55);font-size:.87rem;line-height:1.75;font-weight:300;margin:0;padding:.25rem 1.6rem 1.4rem;">You just need Zoom — any device works: computer, tablet, or smartphone. A stable internet connection is all you need.</p>
      </details>

      <details style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:14px;overflow:hidden;">
        <summary style="font-family:'Sora',sans-serif;font-size:.97rem;font-weight:600;color:#fff;padding:1.4rem 1.6rem;cursor:pointer;user-select:none;">What happens if I miss a session?</summary>
        <p style="color:rgba(255,255,255,0.55);font-size:.87rem;line-height:1.75;font-weight:300;margin:0;padding:.25rem 1.6rem 1.4rem;">Your teacher posts session notes and materials to your student dashboard after every class, so you can catch up at any time.</p>
      </details>

      <details style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:14px;overflow:hidden;">
        <summary style="font-family:'Sora',sans-serif;font-size:.97rem;font-weight:600;color:#fff;padding:1.4rem 1.6rem;cursor:pointer;user-select:none;">How do I know which level I am?</summary>
        <p style="color:rgba(255,255,255,0.55);font-size:.87rem;line-height:1.75;font-weight:300;margin:0;padding:.25rem 1.6rem 1.4rem;">Select your approximate level when you enroll — our team will confirm the right group for you. You can also take our free placement test after submitting your enrollment request.</p>
      </details>

      <details style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:14px;overflow:hidden;">
        <summary style="font-family:'Sora',sans-serif;font-size:.97rem;font-weight:600;color:#fff;padding:1.4rem 1.6rem;cursor:pointer;user-select:none;">How soon can I start?</summary>
        <p style="color:rgba(255,255,255,0.55);font-size:.87rem;line-height:1.75;font-weight:300;margin:0;padding:.25rem 1.6rem 1.4rem;">Our team contacts you within 24 hours to confirm your place and share your login credentials. New groups start regularly — we'll let you know when the next one begins.</p>
      </details>

      <details style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:14px;overflow:hidden;">
        <summary style="font-family:'Sora',sans-serif;font-size:.97rem;font-weight:600;color:#fff;padding:1.4rem 1.6rem;cursor:pointer;user-select:none;">Can I pay in instalments?</summary>
        <p style="color:rgba(255,255,255,0.55);font-size:.87rem;line-height:1.75;font-weight:300;margin:0;padding:.25rem 1.6rem 1.4rem;">Payment options are confirmed when our admin team reaches out after your enrollment. Message us on WhatsApp before enrolling if you have questions.</p>
      </details>

      <details style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:14px;overflow:hidden;">
        <summary style="font-family:'Sora',sans-serif;font-size:.97rem;font-weight:600;color:#fff;padding:1.4rem 1.6rem;cursor:pointer;user-select:none;">Is there a long-term commitment?</summary>
        <p style="color:rgba(255,255,255,0.55);font-size:.87rem;line-height:1.75;font-weight:300;margin:0;padding:.25rem 1.6rem 1.4rem;">Each session is 2.5 months (~30 hours). There is no long-term contract — you decide whether to continue at the end of each session.</p>
      </details>

    </div>
  </div>
</div>

<div class="enroll-section" id="enroll">
  <div class="enroll-inner">
    <div class="section-label text-center" style="text-align:center;">Enrollment</div>
    <h2 class="section-title text-center" style="text-align:center;">Ready to start?</h2>
    <p class="section-sub" style="margin:0 auto;text-align:center;">Fill in your details and we'll be in touch within 24 hours to confirm your place.</p>
    <div class="form-card">
      <form id="form-area" onsubmit="submitForm(event)">
        <div id="selected-pill" style="display:none;" class="selected-course-pill">
          <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M1.5 6L4.5 9L10.5 3" stroke="#3ecf78" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span id="pill-text">Business English</span>
        </div>
        <div class="form-group">
          <label for="course-select">SELECT COURSE</label>
          <select id="course-select" required>
            <option value="">— Choose a program —</option>
            <option value="Business English">Business English</option>
            <option value="General English">General English</option>
            <option value="English for Baccalaureate">English for Baccalaureate</option>
            <option value="English for Kids">English for Kids</option>
          </select>
        </div>
        <div class="form-group">
          <label for="name">FULL NAME</label>
          <input type="text" id="name" placeholder="Your full name" maxlength="120" autocomplete="name" required>
        </div>
        <div class="form-group">
          <label for="level">CURRENT LEVEL</label>
          <select id="level" required>
            <option value="">— Select your level —</option>
            <option value="Beginner">Beginner — little or no English</option>
            <option value="Elementary">Elementary — basic phrases and vocabulary</option>
            <option value="Intermediate">Intermediate — can hold simple conversations</option>
            <option value="Advanced">Advanced — fluent but refining skills</option>
            <option value="Baccalaureate Student">Baccalaureate student</option>
          </select>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label for="email">EMAIL ADDRESS</label>
            <input type="email" id="email" placeholder="you@example.com" maxlength="180" autocomplete="email" required>
          </div>
          <div class="form-group">
            <label for="phone">PHONE NUMBER</label>
            <input type="tel" id="phone" placeholder="+212 6XX XXX XXX" maxlength="30" autocomplete="tel" required>
          </div>
        </div>
        <button type="submit" class="btn-submit">Send Enrollment Request →</button>
        <p class="form-notice">Your information is saved securely and sent directly to our admin team. We'll contact you within 24 hours.</p>
      </form>
      <div class="success-msg" id="success-msg">
        <div class="success-icon">✓</div>
        <h3>Request received!</h3>
        <p>Thank you for enrolling. Our team will review your request and contact you within 24 hours to confirm your place and send your login credentials.</p>
        <a href="https://wa.me/212702099967" target="_blank" rel="noopener noreferrer" class="btn-primary" style="margin-top:1.2rem;display:inline-block;">Message us on WhatsApp →</a>
        <a href="/placement-test.php" class="btn-primary" style="margin-top:.75rem;display:inline-block;background:#10b981;">Placement Test →</a>
      </div>
    </div>
  </div>
</div>

</main>
<footer>
  <div class="footer-grid">
    <!-- Brand -->
    <div class="footer-brand">
      <span class="footer-logo"><em>Up</em>skill Education</span>
      <p>Professional English training for Moroccan learners. Live Zoom sessions, expert teachers, real results.</p>
    </div>
    <!-- Quick links -->
    <div class="footer-col">
      <h4>Quick links</h4>
      <div class="footer-links">
        <a href="#courses">Courses</a>
        <a href="#enroll">Enroll now</a>
        <a href="#how">How it works</a>
        <a target="_blank" rel="noopener noreferrer" href="https://study.upskill-edu.com">Student Portal</a>
        <a href="#" onclick="openContact();return false;">Contact us</a>
      </div>
    </div>
    <!-- Contact -->
    <div class="footer-col">
      <h4>Contact</h4>
      <div class="footer-contact-item">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        <span>Casablanca, Maroc</span>
      </div>
      <div class="footer-contact-item">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        <a href="mailto:admin@upskill-edu.com">admin@upskill-edu.com</a>
      </div>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="footer-copy">&copy; 2026 Upskill Education &middot; upskill-edu.com &middot; All rights reserved.</div>
    <div class="footer-social">
      <a href="https://www.facebook.com/profile.php?id=61589477986844" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg></a>
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

  async function submitForm(event) {
    event.preventDefault();
    const name   = document.getElementById('name').value.trim();
    const email  = document.getElementById('email').value.trim();
    const phone  = document.getElementById('phone').value.trim();
    const course = document.getElementById('course-select').value;
    const level  = document.getElementById('level').value;

    if (!course) { showToast('Please select a course first.'); return; }
    if (!name)   { showToast('Please enter your full name.'); return; }
    if (!level)  { showToast('Please select your current level.'); return; }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showToast('Please enter a valid email address.'); return; }
    if (!phone)  { showToast('Please enter your phone number.'); return; }

    const btn = document.querySelector('.btn-submit');
    btn.disabled = true;
    btn.textContent = 'Sending…';

    try {
      const body = new URLSearchParams({ csrf_token: CSRF_TOKEN, name, email, phone, course, level, lang: 'en' });
      const res  = await fetch('/enroll.php', { method: 'POST', body });
      const data = await res.json();

      if (data.ok) {
        document.getElementById('form-area').style.display = 'none';
        document.getElementById('success-msg').style.display = 'block';
      } else {
        showToast(data.error || 'Something went wrong. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Send Enrollment Request →';
      }
    } catch (err) {
      showToast('Network error. Please check your connection and try again.');
      btn.disabled = false;
      btn.textContent = 'Send Enrollment Request →';
    }
  }
</script>

<!-- ── CONTACT MODAL ── -->
<div id="contact-overlay" onclick="if(event.target===this)closeContact()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;">
  <div role="dialog" aria-modal="true" aria-labelledby="contact-modal-title" style="background:#162436;border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:2rem;width:100%;max-width:480px;margin:1rem;position:relative;">
    <button onclick="closeContact()" style="position:absolute;top:1rem;right:1rem;background:none;border:none;color:rgba(255,255,255,.4);font-size:1.3rem;cursor:pointer;line-height:1;">✕</button>
    <div id="contact-modal-title" style="font-family:'Sora',sans-serif;font-size:1.2rem;font-weight:700;margin-bottom:.3rem;">Get in touch</div>
    <div style="color:rgba(255,255,255,.55);font-size:.88rem;margin-bottom:1.5rem;font-family:'DM Sans',sans-serif;">Send us a message and we'll get back to you within 24 hours.</div>
    <div style="margin-bottom:1rem;">
      <label style="display:block;font-family:'Sora',sans-serif;font-size:.75rem;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:.4rem;letter-spacing:.04em;text-transform:uppercase;">YOUR NAME</label>
      <input id="c-name" type="text" placeholder="Your full name" maxlength="120"
        style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#fff;font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;box-sizing:border-box;">
    </div>
    <div style="margin-bottom:1rem;">
      <label style="display:block;font-family:'Sora',sans-serif;font-size:.75rem;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:.4rem;letter-spacing:.04em;text-transform:uppercase;">EMAIL ADDRESS</label>
      <input id="c-email" type="email" placeholder="you@example.com" maxlength="180"
        style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#fff;font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;box-sizing:border-box;">
    </div>
    <div style="margin-bottom:1rem;">
      <label style="display:block;font-family:'Sora',sans-serif;font-size:.75rem;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:.4rem;letter-spacing:.04em;text-transform:uppercase;">
        PHONE NUMBER <span style="font-weight:400;font-size:.7rem;color:rgba(255,255,255,.35);text-transform:none;letter-spacing:0;">(optional)</span>
      </label>
      <input id="c-phone" type="tel" placeholder="e.g. 0612345678" maxlength="30"
        style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#fff;font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;box-sizing:border-box;">
    </div>
    <div style="margin-bottom:1.5rem;">
      <label style="display:block;font-family:'Sora',sans-serif;font-size:.75rem;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:.4rem;letter-spacing:.04em;text-transform:uppercase;">YOUR MESSAGE</label>
      <textarea id="c-msg" placeholder="Write your message here…" maxlength="2000" rows="4"
        style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#fff;font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;resize:vertical;box-sizing:border-box;"></textarea>
    </div>
    <div id="c-error" style="display:none;color:#f87171;font-size:.83rem;margin-bottom:.8rem;font-family:'DM Sans',sans-serif;"></div>
    <button id="c-btn" onclick="sendContact()"
      style="width:100%;padding:.9rem;background:#3ecf78;color:#0f1d2e;font-family:'Sora',sans-serif;font-weight:700;font-size:.95rem;border:none;border-radius:12px;cursor:pointer;transition:background .2s;">
      Send message →
    </button>
    <div id="c-success" style="display:none;text-align:center;padding:1.5rem 0;">
      <div style="font-size:2.5rem;margin-bottom:.5rem;">✅</div>
      <div style="font-family:'Sora',sans-serif;font-weight:600;margin-bottom:.3rem;">Message sent!</div>
      <div style="color:rgba(255,255,255,.55);font-size:.88rem;font-family:'DM Sans',sans-serif;">We'll get back to you soon.</div>
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
  document.getElementById('c-btn').textContent = 'Send message →';
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

  if (!name)  { errEl.textContent = 'Please enter your name.'; errEl.style.display = ''; return; }
  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { errEl.textContent = 'Invalid email address.'; errEl.style.display = ''; return; }
  if (phone && !/^[+\d\s\-(). ]{1,30}$/.test(phone)) { errEl.textContent = 'Invalid phone number.'; errEl.style.display = ''; return; }
  if (!msg)   { errEl.textContent = 'Please enter your message.'; errEl.style.display = ''; return; }

  const btn = document.getElementById('c-btn');
  btn.disabled = true; btn.textContent = 'Sending…';

  try {
    const body = new URLSearchParams({ csrf_token: CSRF_TOKEN, name, email, phone, message: msg, lang: 'en' });
    const res  = await fetch('/contact.php', { method: 'POST', body });
    const data = await res.json();
    if (data.ok) {
      document.getElementById('c-success').style.display = '';
      btn.style.display = 'none';
    } else {
      errEl.textContent = data.error || 'Something went wrong. Please try again.';
      errEl.style.display = '';
      btn.disabled = false; btn.textContent = 'Send message →';
    }
  } catch(e) {
    errEl.textContent = 'Network error. Check your connection.';
    errEl.style.display = '';
    btn.disabled = false; btn.textContent = 'Send message →';
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
   style="position:fixed;bottom:2rem;right:2rem;z-index:998;background:#25d366;color:white;width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(37,211,102,.45);text-decoration:none;transition:transform .2s;"
   onmouseenter="this.style.transform='scale(1.12)'" onmouseleave="this.style.transform=''"
   title="Chat on WhatsApp">
  <svg width="26" height="26" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
    <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.656 1.438 5.168L2 22l4.975-1.395A9.96 9.96 0 0 0 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm5.093 13.677c-.22.616-1.287 1.174-1.762 1.214-.476.04-.923.214-3.1-.648-2.606-1.038-4.267-3.7-4.396-3.872-.128-.172-1.053-1.402-1.053-2.676 0-1.273.665-1.9.9-2.16.236-.258.516-.322.687-.322.172 0 .344.002.494.008.159.006.37-.06.58.458.215.518.731 1.79.795 1.921.064.13.107.282.021.452-.086.172-.13.28-.258.43-.13.15-.272.337-.386.45-.13.13-.264.27-.113.527.15.258.666 1.098 1.43 1.778.982.874 1.815 1.143 2.072 1.273.257.13.408.108.558-.065.15-.172.63-.737.797-.99.168-.257.336-.215.565-.13.23.086 1.452.685 1.702.81.25.128.416.192.479.3.063.107.063.579-.157 1.2z"/>
  </svg>
</a>

</body>
</html>
