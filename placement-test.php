<?php
/**
 * placement-test.php — English Placement Test (60 marks)
 */
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>isset($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);
session_start();
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>English Placement Test – Upskill Education</title>
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap"></noscript>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --navy: #0f1d2e; --navy-mid: #162436; --navy-light: #1e3248;
  --green: #3ecf78; --green-dark: #28a85c; --green-glow: rgba(62,207,120,0.15);
  --white: #ffffff; --muted: rgba(255,255,255,0.55); --muted2: rgba(255,255,255,0.35);
  --border: rgba(255,255,255,0.1); --card-bg: rgba(255,255,255,0.04);
  --font: 'Sora', sans-serif; --font-body: 'DM Sans', sans-serif;
  --red: #e85d75; --yellow: #f5c542; --blue: #5b9cf6;
}
html { scroll-behavior: smooth; background: var(--navy); }
body { background: var(--navy); color: var(--white); font-family: var(--font-body); min-height: 100vh; }

/* NAV */
.nav { display: flex; align-items: center; justify-content: space-between; padding: 1rem 2rem; background: rgba(15,29,46,0.9); backdrop-filter: blur(12px); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 50; }
.nav-logo { display: flex; align-items: center; gap: .5rem; text-decoration: none; font-family: var(--font); font-weight: 700; font-size: 1rem; color: var(--white); }
.nav-logo em { color: var(--green); font-style: normal; }
.nav-badge-pill { background: var(--green-glow); border: 1px solid rgba(62,207,120,.35); color: var(--green); font-family: var(--font); font-size: .72rem; font-weight: 600; padding: .25rem .75rem; border-radius: 100px; }

/* PROGRESS BAR */
.progress-wrap { background: var(--navy-mid); border-bottom: 1px solid var(--border); padding: .75rem 2rem; display: flex; align-items: center; gap: 1rem; }
.progress-bar-bg { flex: 1; height: 6px; background: rgba(255,255,255,.08); border-radius: 3px; overflow: hidden; }
.progress-bar-fill { height: 100%; background: var(--green); border-radius: 3px; transition: width .3s; }
.progress-label { font-family: var(--font); font-size: .78rem; color: var(--muted); white-space: nowrap; }

/* WRAPPER */
.wrap { max-width: 820px; margin: 0 auto; padding: 2rem 1.5rem 5rem; }

/* INTRO CARD */
.intro-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 20px; padding: 2rem; margin-bottom: 2rem; }
.intro-card h1 { font-family: var(--font); font-size: 1.6rem; font-weight: 700; margin-bottom: .5rem; }
.intro-card p { color: var(--muted); font-size: .93rem; line-height: 1.7; }
.intro-meta { display: flex; gap: 1.5rem; margin-top: 1.25rem; flex-wrap: wrap; }
.meta-chip { display: inline-flex; align-items: center; gap: .4rem; background: rgba(255,255,255,.06); border: 1px solid var(--border); border-radius: 8px; padding: .35rem .8rem; font-size: .8rem; color: var(--muted); font-family: var(--font); }
.meta-chip svg { color: var(--green); }

/* STUDENT INFO FORM */
.info-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 20px; padding: 2rem; margin-bottom: 2rem; }
.info-card h2 { font-family: var(--font); font-size: 1rem; font-weight: 700; margin-bottom: 1.25rem; letter-spacing: -.01em; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
.fg { margin-bottom: 1rem; }
.fg label { display: block; font-family: var(--font); font-size: .78rem; font-weight: 600; color: var(--muted); margin-bottom: .45rem; letter-spacing: .04em; text-transform: uppercase; }
.fg input { width: 100%; padding: .8rem 1rem; background: rgba(255,255,255,.05); border: 1px solid var(--border); border-radius: 10px; color: var(--white); font-family: var(--font-body); font-size: .95rem; outline: none; transition: border-color .2s, background .2s; }
.fg input::placeholder { color: rgba(255,255,255,.25); }
.fg input:focus { border-color: var(--green); background: rgba(62,207,120,.05); }
.fg-note { font-size: .75rem; color: var(--muted2); margin-top: .3rem; }

/* SECTION HEADER */
.section-hdr { display: flex; align-items: center; gap: .75rem; margin: 2.5rem 0 1.25rem; }
.section-badge { font-family: var(--font); font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; background: var(--green-glow); border: 1px solid rgba(62,207,120,.35); color: var(--green); padding: .3rem .75rem; border-radius: 100px; white-space: nowrap; }
.section-line { flex: 1; height: 1px; background: var(--border); }
.section-info { font-size: .78rem; color: var(--muted2); white-space: nowrap; font-family: var(--font); }

/* QUESTION CARD */
.q-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem; margin-bottom: 1rem; transition: border-color .2s; }
.q-card.answered { border-color: rgba(62,207,120,.3); }
.q-num { font-family: var(--font); font-size: .7rem; font-weight: 700; color: var(--green); letter-spacing: .06em; text-transform: uppercase; margin-bottom: .6rem; }
.q-text { font-size: .95rem; line-height: 1.6; margin-bottom: 1.1rem; color: var(--white); font-weight: 400; }
.q-text strong { color: var(--green); }
.options { display: flex; flex-direction: column; gap: .5rem; }
.opt { display: flex; align-items: flex-start; gap: .75rem; padding: .65rem .9rem; border: 1px solid var(--border); border-radius: 10px; cursor: pointer; transition: all .18s; }
.opt:hover { border-color: rgba(62,207,120,.4); background: rgba(62,207,120,.04); }
.opt input[type="radio"] { display: none; }
.opt.selected { border-color: var(--green); background: rgba(62,207,120,.08); }
.opt-letter { font-family: var(--font); font-size: .78rem; font-weight: 700; color: var(--green); flex-shrink: 0; margin-top: .1rem; }
.opt-text { font-size: .88rem; line-height: 1.5; color: rgba(255,255,255,.8); }

/* SUBMIT AREA */
.submit-area { margin-top: 2.5rem; }
.unanswered-warn { background: rgba(245,197,66,.08); border: 1px solid rgba(245,197,66,.3); border-radius: 12px; padding: .9rem 1.1rem; font-size: .84rem; color: var(--yellow); display: none; margin-bottom: 1rem; font-family: var(--font); }
.btn-finish { width: 100%; padding: 1.1rem; background: var(--green); color: var(--navy); font-family: var(--font); font-weight: 700; font-size: 1.05rem; border: none; border-radius: 14px; cursor: pointer; transition: background .2s, transform .15s; letter-spacing: -.01em; }
.btn-finish:hover { background: var(--green-dark); transform: translateY(-2px); }
.btn-finish:disabled { opacity: .5; cursor: not-allowed; transform: none; }

/* RESULT OVERLAY */
#result-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.75); backdrop-filter: blur(6px); z-index: 200; align-items: center; justify-content: center; padding: 1rem; }
#result-overlay.show { display: flex; }
.result-card { background: var(--navy-mid); border: 1px solid rgba(62,207,120,.3); border-radius: 24px; padding: 2.5rem; max-width: 520px; width: 100%; text-align: center; position: relative; animation: popIn .35s cubic-bezier(.34,1.56,.64,1); }
@keyframes popIn { from { opacity:0; transform:scale(.85); } to { opacity:1; transform:scale(1); } }
.result-icon { width: 72px; height: 72px; border-radius: 50%; background: var(--green-glow); border: 2px solid var(--green); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; font-size: 2rem; }
.result-score { font-family: var(--font); font-size: 3rem; font-weight: 700; color: var(--green); line-height: 1; margin-bottom: .25rem; }
.result-score-sub { font-size: .85rem; color: var(--muted); margin-bottom: 1.5rem; font-family: var(--font); }
.result-placement-label { font-family: var(--font); font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); margin-bottom: .5rem; }
.result-level { font-family: var(--font); font-size: 1.9rem; font-weight: 700; color: var(--white); letter-spacing: -.02em; margin-bottom: .5rem; }
.result-desc { color: var(--muted); font-size: .88rem; line-height: 1.65; margin-bottom: 2rem; }
.result-actions { display: flex; flex-direction: column; gap: .75rem; }
.btn-wa { display: flex; align-items: center; justify-content: center; gap: .6rem; background: #25d366; color: white; font-family: var(--font); font-weight: 700; font-size: .95rem; padding: .85rem 1.5rem; border-radius: 12px; text-decoration: none; transition: background .2s; }
.btn-wa:hover { background: #1dab52; }
.btn-home { display: block; color: var(--muted); font-size: .85rem; text-decoration: none; font-family: var(--font); transition: color .2s; }
.btn-home:hover { color: var(--white); }
.result-saving { font-size: .78rem; color: var(--muted2); margin-top: .5rem; }

/* TOAST */
#toast { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 999; background: #1a2f21; border: 1px solid var(--green); color: var(--white); font-family: var(--font); font-size: .85rem; padding: .8rem 1.2rem; border-radius: 12px; transform: translateY(100px); opacity: 0; transition: all .35s; max-width: 300px; }
#toast.show { transform: translateY(0); opacity: 1; }
#toast.error { background: rgba(232,93,117,.15); border-color: var(--red); }
</style>
</head>
<body>

<!-- NAV -->
<nav class="nav">
  <a href="/en" class="nav-logo"><em>Up</em>skill Education</a>
  <span class="nav-badge-pill">Placement Test</span>
</nav>

<!-- PROGRESS BAR -->
<div class="progress-wrap">
  <div class="progress-bar-bg"><div class="progress-bar-fill" id="prog-fill" style="width:0%"></div></div>
  <span class="progress-label" id="prog-label">0 / 60 answered</span>
</div>

<div class="wrap">

  <!-- INTRO -->
  <div class="intro-card">
    <h1>English Placement Test</h1>
    <p>This test helps us place you in the right level. Answer all 60 questions as best you can — there is no time limit. Your result is instant and will be sent to our team automatically.</p>
    <div class="intro-meta">
      <span class="meta-chip"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> No time limit</span>
      <span class="meta-chip"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg> 60 questions</span>
      <span class="meta-chip"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg> Grammar · Vocabulary · Function</span>
      <span class="meta-chip"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Instant results</span>
    </div>
  </div>

  <!-- STUDENT INFO -->
  <div class="info-card">
    <h2>Your Information</h2>
    <div class="fg">
      <label for="s-name">Full Name *</label>
      <input type="text" id="s-name" placeholder="Your full name" maxlength="120" autocomplete="name">
    </div>
    <div class="form-row">
      <div class="fg">
        <label for="s-email">Email Address</label>
        <input type="email" id="s-email" placeholder="you@example.com" maxlength="180" autocomplete="email">
        <div class="fg-note">Email or phone required</div>
      </div>
      <div class="fg">
        <label for="s-phone">Phone Number</label>
        <input type="tel" id="s-phone" placeholder="+212 6XX XXX XXX" maxlength="40" autocomplete="tel">
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════ GRAMMAR ═══════════════════════════════════════ -->
  <div class="section-hdr">
    <span class="section-badge">Part 1 — Grammar</span>
    <div class="section-line"></div>
    <span class="section-info">25 questions</span>
  </div>

  <?php
  $grammar = [
    [1,  'She ___ a teacher.',                                ['am','is','are','be'], 1],
    [2,  'They ___ football every Saturday.',                 ['plays','play','playing','played'], 1],
    [3,  'There ___ a lot of students in the class.',        ['is','are','am','be'], 1],
    [4,  'I ___ my homework yesterday.',                      ['do','does','did','done'], 2],
    [5,  'She ___ TV when I called her.',                     ['watched','is watching','was watching','watches'], 2],
    [6,  'He ___ to Paris three times.',                      ['went','has been','goes','had gone'], 1],
    [7,  'This film is ___ than the last one.',               ['more interesting','interestinger','most interesting','interestingly'], 0],
    [8,  'If I ___ more money, I would buy a car.',           ['have','had','will have','would have'], 1],
    [9,  'By the time she arrived, the meeting ___ already started.', ['has','have','had','was'], 2],
    [10, 'The report ___ by the manager last week.',          ['wrote','was written','is writing','has written'], 1],
    [11, 'You ___ wear a seatbelt — it\'s the law.',         ['should','must','might','would'], 1],
    [12, 'She asked me ___ I had finished the project.',      ['if','that','what','which'], 0],
    [13, 'Not only ___ late, but he also forgot his report.', ['he arrived','arrived he','did he arrive','he did arrive'], 2],
    [14, 'The man ___ lives next door is a doctor.',          ['which','who','what','whose'], 1],
    [15, 'I wish I ___ the answer, but I don\'t.',           ['know','knew','had known','will know'], 1],
    [16, '___ she studied hard, she failed the exam.',        ['Because','Since','Although','So'], 2],
    [17, 'She would rather ___ at home than go out.',         ['staying','to stay','stay','stayed'], 2],
    [18, 'The new law ___ into effect next month.',           ['comes','come','coming','came'], 0],
    [19, 'I ___ here since 2015.',                            ['live','lived','have lived','was living'], 2],
    [20, '"___ I were you, I would apologise immediately."',  ['Should','Had','If','Were'], 2],
    [21, 'He suggested that she ___ early.',                  ['arrives','arrived','arrive','will arrive'], 2],
    [22, '___ been warned, she still made the same mistake.', ['Despite','Having','Although','Even'], 1],
    [23, 'It was only after he left ___ she realised the truth.', ['when','which','that','then'], 2],
    [24, 'The earlier you start, ___ you will finish.',       ['sooner','the sooner','the more soon','soon'], 1],
    [25, 'Had she known about the meeting, she ___ attended.', ['would have','will have','would','had'], 0],
  ];
  foreach ($grammar as [$num, $q, $opts, $ans]):
  ?>
  <div class="q-card" id="qc-<?= $num ?>" data-q="<?= $num ?>" data-ans="<?= $ans ?>">
    <div class="q-num">Question <?= $num ?></div>
    <div class="q-text"><?= htmlspecialchars($q) ?></div>
    <div class="options">
      <?php foreach ($opts as $i => $opt): $letter = chr(65+$i); ?>
      <label class="opt" id="opt-<?= $num ?>-<?= $i ?>" onclick="selectOpt(<?= $num ?>,<?= $i ?>,this)">
        <input type="radio" name="q<?= $num ?>" value="<?= $i ?>">
        <span class="opt-letter"><?= $letter ?></span>
        <span class="opt-text"><?= htmlspecialchars($opt) ?></span>
      </label>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- ═══════════════════════════════════════ VOCABULARY ═══════════════════════════════════════ -->
  <div class="section-hdr">
    <span class="section-badge">Part 2 — Vocabulary</span>
    <div class="section-line"></div>
    <span class="section-info">20 questions</span>
  </div>

  <?php
  $vocabulary = [
    [26, 'The opposite of "cheap" is:',                      ['free','expensive','priceless','costly'], 1],
    [27, 'Which word means "very tired"?',                   ['nervous','excited','exhausted','relaxed'], 2],
    [28, 'A person who designs buildings is called an:',     ['engineer','lawyer','architect','accountant'], 2],
    [29, 'Can you ___ me a favour?',                         ['make','do','give','have'], 1],
    [30, '"To be under the weather" means:',                 ['to be outdoors','to feel ill','to feel cold','to feel confused'], 1],
    [31, 'Which word means "to officially end a contract"?', ['negotiate','draft','terminate','sign'], 2],
    [32, 'The word "renovate" most closely means:',          ['destroy','purchase','restore','construct'], 2],
    [33, '"The company went ___ — it had no money left."',   ['bankrupt','retired','dismissed','resigned'], 0],
    [34, 'A "tentative" plan is one that is:',               ['final','not yet confirmed','long-lasting','legally binding'], 1],
    [35, 'Which adjective means "very generous with money or help"?', ['greedy','ambitious','lavish','stubborn'], 2],
    [36, '"Meticulous" means:',                              ['careless','very slow','unreliable','showing great attention to detail'], 3],
    [37, 'Which word does NOT collocate with "decision"?',   ['make','reach','take','do'], 3],
    [38, '"The new policy will ___ effect from January."',   ['make','take','do','have'], 1],
    [39, '"Ambiguous" means:',                               ['having one clear meaning','open to more than one interpretation','complicated but correct','clear and direct'], 1],
    [40, 'A "catalyst" in a non-scientific context is something that:', ['slows progress','causes or accelerates change','prevents change','measures progress'], 1],
    [41, 'Which word means to officially cancel a law?',     ['enforce','amend','legislate','repeal'], 3],
    [42, '"Rhetoric" refers to:',                            ['physical exercise','scientific research','the art of persuasive speaking or writing','mathematical logic'], 2],
    [43, '"Notwithstanding" is closest in meaning to:',      ['therefore','in addition','as a result','in spite of'], 3],
    [44, '"Ubiquitous" means:',                              ['unique','rare','found everywhere','extremely large'], 2],
    [45, 'A "paradigm shift" refers to:',                    ['a minor adjustment','a temporary setback','a statistical error','a fundamental change in approach'], 3],
  ];
  foreach ($vocabulary as [$num, $q, $opts, $ans]):
  ?>
  <div class="q-card" id="qc-<?= $num ?>" data-q="<?= $num ?>" data-ans="<?= $ans ?>">
    <div class="q-num">Question <?= $num ?></div>
    <div class="q-text"><?= htmlspecialchars($q) ?></div>
    <div class="options">
      <?php foreach ($opts as $i => $opt): $letter = chr(65+$i); ?>
      <label class="opt" id="opt-<?= $num ?>-<?= $i ?>" onclick="selectOpt(<?= $num ?>,<?= $i ?>,this)">
        <input type="radio" name="q<?= $num ?>" value="<?= $i ?>">
        <span class="opt-letter"><?= $letter ?></span>
        <span class="opt-text"><?= htmlspecialchars($opt) ?></span>
      </label>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- ═══════════════════════════════════════ FUNCTION ═══════════════════════════════════════ -->
  <div class="section-hdr">
    <span class="section-badge">Part 3 — Language Function</span>
    <div class="section-line"></div>
    <span class="section-info">15 questions</span>
  </div>

  <?php
  $function = [
    [46, 'You want to politely ask someone to repeat what they said. You say:',
         ['"What?"','"I beg your pardon?"','"Repeat that."','"Speak louder."'], 1],
    [47, 'Which phrase is used to make a suggestion?',
         ['"You must go."','"I want you to go."','"Why don\'t we go?"','"Go now."'], 2],
    [48, 'You disagree politely with a colleague\'s idea. Which is most appropriate?',
         ['"That\'s completely wrong."','"I see your point, but I think…"','"No, you\'re wrong."','"That\'s not right at all."'], 1],
    [49, '"Would you mind ___ing the window?" — this phrase makes:',
         ['an order','a polite request','an offer','an apology'], 1],
    [50, 'Which response best shows active listening in a conversation?',
         ['"OK."','"Whatever."','"Fine."','"That\'s a valid point — could you elaborate?"'], 3],
    [51, 'Which phrase best introduces an opinion in a formal discussion?',
         ['"I reckon…"','"I think maybe…"','"Honestly speaking…"','"In my view, it would appear that…"'], 3],
    [52, 'To steer a meeting back on topic, you say:',
         ['"Let\'s not talk about that."','"Can we return to the main point?"','"Stop talking."','"That\'s irrelevant."'], 1],
    [53, 'How do you formally interrupt someone?',
         ['"Hey!"','"Stop!"','"Wait, let me talk."','"If I may just add something here…"'], 3],
    [54, 'You want to soften a criticism professionally. Which works best?',
         ['"This is terrible."','"One area for improvement might be…"','"You failed completely."','"This isn\'t good enough."'], 1],
    [55, 'Which phrase best hedges a statement in an academic or business context?',
         ['"It is definitely true that…"','"Everyone knows that…"','"It would appear that…"','"Obviously,…"'], 2],
    [56, 'To express that something is non-negotiable in a formal tone, you say:',
         ['"No way."','"You have to."','"We want this."','"It is imperative that we…"'], 3],
    [57, 'Which linking phrase introduces a concession?',
         ['"Therefore"','"Moreover"','"Subsequently"','"Admittedly"'], 3],
    [58, 'Which phrase is used to conclude a formal presentation?',
         ['"That\'s it."','"OK, I\'m done."','"Anyway, that\'s all."','"To sum up, the key findings suggest…"'], 3],
    [59, '"The proposal faced resistance, ___ it was ultimately approved."',
         ['so','because','since','yet'], 3],
    [60, 'Which phrase best demonstrates diplomatic language in a negotiation?',
         ['"Take it or leave it."','"That\'s your problem."','"We don\'t care about your position."','"I understand your concerns; perhaps we could explore a middle ground."'], 3],
  ];
  foreach ($function as [$num, $q, $opts, $ans]):
  ?>
  <div class="q-card" id="qc-<?= $num ?>" data-q="<?= $num ?>" data-ans="<?= $ans ?>">
    <div class="q-num">Question <?= $num ?></div>
    <div class="q-text"><?= htmlspecialchars($q) ?></div>
    <div class="options">
      <?php foreach ($opts as $i => $opt): $letter = chr(65+$i); ?>
      <label class="opt" id="opt-<?= $num ?>-<?= $i ?>" onclick="selectOpt(<?= $num ?>,<?= $i ?>,this)">
        <input type="radio" name="q<?= $num ?>" value="<?= $i ?>">
        <span class="opt-letter"><?= $letter ?></span>
        <span class="opt-text"><?= htmlspecialchars($opt) ?></span>
      </label>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- SUBMIT -->
  <div class="submit-area">
    <div class="unanswered-warn" id="warn-unanswered"></div>
    <button class="btn-finish" onclick="finishTest()">Finish Test &amp; See My Level →</button>
  </div>

</div><!-- /wrap -->

<!-- RESULT OVERLAY -->
<div id="result-overlay">
  <div class="result-card">
    <div class="result-icon">🎓</div>
    <div class="result-score" id="res-score">0</div>
    <div class="result-score-sub">out of 60</div>
    <div class="result-placement-label">Your English level</div>
    <div class="result-level" id="res-level">—</div>
    <div class="result-desc" id="res-desc"></div>
    <div class="result-actions">
      <a href="https://wa.me/212702099967" target="_blank" rel="noopener noreferrer" class="btn-wa">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.656 1.438 5.168L2 22l4.975-1.395A9.96 9.96 0 0 0 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm5.093 13.677c-.22.616-1.287 1.174-1.762 1.214-.476.04-.923.214-3.1-.648-2.606-1.038-4.267-3.7-4.396-3.872-.128-.172-1.053-1.402-1.053-2.676 0-1.273.665-1.9.9-2.16.236-.258.516-.322.687-.322.172 0 .344.002.494.008.159.006.37-.06.58.458.215.518.731 1.79.795 1.921.064.13.107.282.021.452-.086.172-.13.28-.258.43-.13.15-.272.337-.386.45-.13.13-.264.27-.113.527.15.258.666 1.098 1.43 1.778.982.874 1.815 1.143 2.072 1.273.257.13.408.108.558-.065.15-.172.63-.737.797-.99.168-.257.336-.215.565-.13.23.086 1.452.685 1.702.81.25.128.416.192.479.3.063.107.063.579-.157 1.2z"/></svg>
        Contact us on WhatsApp
      </a>
      <a href="/en" class="btn-home">← Back to homepage</a>
    </div>
    <div class="result-saving" id="res-saving">Saving your result…</div>
  </div>
</div>

<div id="toast"></div>

<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;
const answers = {}; // q_num → selected option index
let totalAnswered = 0;

// Answer key (0-based option index per question)
const ANSWER_KEY = {
  1:1,2:1,3:1,4:2,5:2,6:1,7:0,8:1,9:2,10:1,
  11:1,12:0,13:2,14:1,15:1,16:2,17:2,18:0,19:2,20:2,
  21:2,22:1,23:2,24:1,25:0,
  26:1,27:2,28:2,29:1,30:1,31:2,32:2,33:0,34:1,35:2,
  36:3,37:3,38:1,39:1,40:1,41:3,42:2,43:3,44:2,45:3,
  46:1,47:2,48:1,49:1,50:3,51:3,52:1,53:3,54:1,55:2,
  56:3,57:3,58:3,59:3,60:3
};

function selectOpt(qNum, optIdx, labelEl) {
  const wasAnswered = answers.hasOwnProperty(qNum);
  answers[qNum] = optIdx;
  // Clear siblings
  const card = document.getElementById('qc-' + qNum);
  card.querySelectorAll('.opt').forEach(o => o.classList.remove('selected'));
  labelEl.classList.add('selected');
  card.classList.add('answered');
  if (!wasAnswered) { totalAnswered++; updateProgress(); }
}

function updateProgress() {
  const pct = Math.round(totalAnswered / 60 * 100);
  document.getElementById('prog-fill').style.width = pct + '%';
  document.getElementById('prog-label').textContent = totalAnswered + ' / 60 answered';
}

function getPlacement(score) {
  if (score <=  4) return 'Beginner 1';
  if (score <=  9) return 'Beginner 2';
  if (score <= 14) return 'Beginner 3';
  if (score <= 19) return 'Pre-Intermediate 1';
  if (score <= 24) return 'Pre-Intermediate 2';
  if (score <= 29) return 'Pre-Intermediate 3';
  if (score <= 34) return 'Intermediate 1';
  if (score <= 39) return 'Intermediate 2';
  if (score <= 44) return 'Intermediate 3';
  if (score <= 48) return 'Upper-Intermediate 1';
  if (score <= 52) return 'Upper-Intermediate 2';
  if (score <= 56) return 'Upper-Intermediate 3';
  if (score <= 58) return 'Advanced 1';
  return 'Advanced 2';
}

function getDesc(placement) {
  const map = {
    'Beginner 1':       'You are just starting your English journey. We will build your foundations step by step.',
    'Beginner 2':       'You know some basic words and phrases. Our Beginner course will help you build confidence.',
    'Beginner 3':       'You have basic English knowledge. A structured beginner course will accelerate your progress.',
    'Pre-Intermediate 1':'You can handle simple everyday English. Time to strengthen your grammar and vocabulary.',
    'Pre-Intermediate 2':'You have a solid foundation. Our pre-intermediate course will unlock more complex structures.',
    'Pre-Intermediate 3':'You are approaching intermediate level — great progress! This course will prepare you for the next step.',
    'Intermediate 1':   'You can communicate in most everyday situations. Let\'s refine your accuracy and fluency.',
    'Intermediate 2':   'Good command of English. Our intermediate course will push your skills to the next level.',
    'Intermediate 3':   'Strong intermediate English. Almost ready for upper-intermediate challenges!',
    'Upper-Intermediate 1':'Impressive! You handle complex language well. Let\'s sharpen your professional English.',
    'Upper-Intermediate 2':'Very strong English. Our upper-intermediate course will perfect your fluency and precision.',
    'Upper-Intermediate 3':'Near-advanced level. Fine-tuning your language will open every door.',
    'Advanced 1':       'Excellent command of English! Our advanced course will give you the edge in any setting.',
    'Advanced 2':       'Outstanding! You have near-native proficiency. Our advanced programme will perfect the final details.',
  };
  return map[placement] || '';
}

async function finishTest() {
  // Validate student info
  const name  = document.getElementById('s-name').value.trim();
  const email = document.getElementById('s-email').value.trim();
  const phone = document.getElementById('s-phone').value.trim();

  if (!name)            { showToast('Please enter your full name.', true); document.getElementById('s-name').focus(); return; }
  if (!email && !phone) { showToast('Please enter your email or phone number.', true); document.getElementById('s-email').focus(); return; }

  // Check unanswered
  const unanswered = 60 - totalAnswered;
  if (unanswered > 0) {
    const warn = document.getElementById('warn-unanswered');
    warn.textContent = `⚠ You have ${unanswered} unanswered question${unanswered > 1 ? 's' : ''}. You can still submit — unanswered questions count as 0.`;
    warn.style.display = 'block';
    // Ask confirmation only if many unanswered
    if (unanswered > 10) {
      const go = confirm(`You have ${unanswered} unanswered questions. Submit anyway?`);
      if (!go) return;
    }
  }

  // Calculate score
  let score = 0;
  for (let q = 1; q <= 60; q++) {
    if (answers[q] !== undefined && answers[q] === ANSWER_KEY[q]) score++;
  }

  const placement = getPlacement(score);

  // Show result
  document.getElementById('res-score').textContent = score;
  document.getElementById('res-level').textContent = placement;
  document.getElementById('res-desc').textContent  = getDesc(placement);
  document.getElementById('res-saving').textContent = 'Saving your result…';
  document.getElementById('result-overlay').classList.add('show');

  // Save to server
  try {
    const res  = await fetch('/study/api_placement.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, email, phone, score, placement, csrf_token: CSRF_TOKEN })
    });
    const data = await res.json();
    document.getElementById('res-saving').textContent = data.ok
      ? '✓ Result saved — our team has been notified.'
      : 'Result ready. (Could not save to server — please screenshot this page.)';
  } catch(e) {
    document.getElementById('res-saving').textContent = 'Result ready. (Offline — please screenshot this page.)';
  }
}

function showToast(msg, isError = false) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className   = 'show' + (isError ? ' error' : '');
  setTimeout(() => t.className = '', 3500);
}

// Close result with Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape' && document.getElementById('result-overlay').classList.contains('show')) { /* keep open */ } });
</script>
</body>
</html>
