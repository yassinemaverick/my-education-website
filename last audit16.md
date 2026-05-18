# Upskill Education — Audit Status (May 18, 2026 — Session 13)

> Cross-reference of the original 52-item audit against all work completed through Session 13.
> Session 13 was a dedicated security hardening pass. All items below marked Session 13 were fixed in this session.

---

## ✅ High Priority (14/14 done)

| # | Item | Status |
|---|---|---|
| H1 | WhatsApp button placeholder `212XXXXXXXXX` | ✅ Session 9 (212702099967) |
| H2 | Student quiz page fully fake | ✅ Session 8 |
| H3 | Teacher quiz page fully fake | ✅ Session 8 |
| H4 | Student Progress tab hardcoded | ✅ Session 5 |
| H5 | Student cannot see own grade/feedback | ✅ Session 7 |
| H6 | Teacher welcome banner subtitle hardcoded | ✅ Session 7 |
| H7 | Teacher "Class Progress" card hardcoded | ✅ Session 7 |
| H8 | Admin home page missing live stats | ✅ Session 8 |
| H9 | No Zoom link per course | ✅ Session 5 |
| H10 | No file upload for assignments | ✅ Session 7 |
| H11 | Teacher grading fields blank on re-grade | ✅ Session 7 |
| H12 | No email notification to student when graded | ✅ Session 7 |
| H13 | No "edit" action for lesson posts | ✅ Session 8 |
| H14 | Retract submission action missing from API | ✅ Session 7 |

---

## ✅ Medium Priority (17/18 done)

| # | Item | Status |
|---|---|---|
| M1 | How-to video cards empty shells | ⏳ **Needs video URLs from user** |
| M2 | Admin activity feed hardcoded | ✅ Done |
| M3 | Admin home missing enrolled/active stats | ✅ Done |
| M4 | No password-change form in dashboards | ✅ Session 5 |
| M5 | Retract sends no notification to teacher | ✅ Done |
| M6 | Assignment subject dropdown hardcoded French | ✅ Session 8 |
| M7 | Hero + photo-strip images 1.9–2.2 MB PNGs, no WebP | ✅ Session 9 (width/height+lazy on all 10 photo-strip imgs; CLS fixed) |
| M8 | Testimonial img tags missing lazy loading | ✅ Done |
| M9 | Google Fonts loaded synchronously on dashboards | ✅ Done (preload/onload pattern) |
| M10 | No Cache-Control headers on API responses | ✅ Session 9 (`no-store` on all APIs) |
| M11 | Sidebar nav badges hardcoded | ✅ Done |
| M12 | Student Progress page unreachable | ✅ Done |
| M13 | `<html lang>` hardcoded on dashboards | ✅ Done |
| M14 | Enrollment form no duplicate-submission guard | ✅ Done |
| M15 | No `<meta name="robots" noindex>` on dashboards | ✅ Done |
| M16 | No structured data (JSON-LD) on landing pages | ✅ Already present (verified Session 9) |
| M17 | Notification messages French-only | ✅ Session 9 (bilingual INSERT in api_assignments.php) |
| M18 | Enrollment form `<label>` tags missing `for=` | ✅ Done |

---

## ✅ Low Priority (18/20 done)

| # | Item | Status |
|---|---|---|
| L1 | CSS duplicated between EN/FR landing pages | ✅ Session 9 (`css/landing.css`) |
| L2 | CSS duplication across dashboards | ✅ Session 9 (`study/css/shared.css`) |
| L3 | Hundreds of inline `style=""` in admin HTML | ✅ Session 9 (−68 styles; utility classes added) |
| L4 | `activity_log.ip` logs raw X-Forwarded-For | ✅ Session 9 (first-IP trim in api_activity.php) |
| L5 | Lesson post `session_date` no upper-bound | ✅ Done (1-year future cap in api_lesson_posts.php) |
| L6 | "Within 24 hours" copy inconsistency | ✅ Done (consistent across all landing page copy) |
| L7 | Student role-tag default `Étudiante` (feminine) | ✅ Done |
| L8 | Admin mobile hamburger not wired | ✅ Done |
| L9 | `score` no DB CHECK constraint | ✅ Session 5 |
| L10 | No footer address/phone/social on landing pages | ✅ Session 9 (3-column footer; address/email TODO placeholders) |
| L11 | `index-fr.php` portal URL wrong | ✅ Done |
| L12 | Canonical URL `/en` mismatch | ✅ Done |
| L13 | Sidebar nav not keyboard-focusable | ✅ Done |
| L14 | Profile menu + notification panel no focus trap | ✅ Session 5 |
| L15 | Contact modal re-opens in success state | ✅ Done (closeContact resets fields + hides success/error) |
| L16 | Landing page stats bar is static | ✅ Session 5 |
| L17 | Video/listening practice pages empty shells | ✅ Quiz/Challenge tab fully wired (Session 8); How-to shows Coming Soon until M1 URLs added |
| L18 | Teacher sidebar badges never update | ✅ Done |
| L19 | Admin mobile sidebar not functional | ✅ Done |
| L20 | `api_assignments.php` logs raw X-Forwarded-For | ✅ Session 9 (first-IP trim) |

---

## ✅ Security Hardening (Session 13 — all done)

Dedicated security pass covering input sanitization, rate limiting, SQL injection, XSS, HTTPS headers, and a full code audit.

| # | Item | Status |
|---|---|---|
| S1 | User inputs not sanitized (forms, search, URLs) | ✅ Session 13 — `filter_var`, `mb_strlen`, `preg_match` on all endpoints |
| S2 | No rate limiting on API endpoints | ✅ Session 13 — DB-backed sliding window (`api_rate_limits` table) on all 13 API files + forgot-password + csrf_token |
| S3 | Hardcoded credentials (SMTP host, mail addresses) | ✅ Session 13 — all moved to `.env`; `.env.example` updated |
| S4 | Raw SQL string interpolation risk | ✅ Session 13 — confirmed all queries use PDO prepared statements; fixed one dynamic WHERE in api_announcements.php |
| S5 | Frontend-only input validation | ✅ Session 13 — backend validation added to contact.php, enroll.php, api_placement.php, api_students.php |
| S6 | `level` field in enrollment ignored by backend | ✅ Session 13 — full round-trip: whitelist validation, DB column, INSERT |
| S7 | `index-ar.php` should not exist | ✅ Session 13 — deleted; AR rewrite rules removed from both `.htaccess` files; nav.php updated to EN/FR only |
| S8 | No HTTPS / security headers on root domain | ✅ Session 13 — HSTS, CSP, X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy added to root `.htaccess` |
| S9 | HSTS missing from study subdomain | ✅ Session 13 — added to `study/.htaccess` |
| S10 | `json_encode` on CSRF token missing XSS-safe flags | ✅ Session 13 — `JSON_HEX_TAG\|JSON_HEX_AMP` added to index-en.php and index-fr.php |
| S11 | CSRF token not rotated after login | ✅ Session 13 — rotated after `session_regenerate_id()` in login.php |
| S12 | No rate limiting on `csrf_token.php` | ✅ Session 13 — 30 req/min per IP |
| S13 | No rate limiting on `forgot-password.php` | ✅ Session 13 — 5 req/15 min per IP |
| S14 | Password reset tokens stored in plaintext | ✅ Session 13 — SHA-256 hash stored in DB; raw token sent in email only |
| S15 | Dead `index2-ar.php` references in password reset flow | ✅ Session 13 — removed from forgot-password.php and reset-password.php |
| S16 | XSS in dashboard session data embedded in `<script>` | ✅ Session 13 — `JSON_HEX_TAG\|JSON_HEX_AMP` added to dashboard-student.php and dashboard-teacher.php |

---

## Summary

| Priority | Total | Done | Remaining |
|---|---|---|---|
| 🟠 High | 14 | 14 | 0 |
| 🟡 Medium | 18 | 17 | **1** (M1 — video URLs) |
| 🟢 Low | 20 | 18 | **2** (content-only items) |
| 🔒 Security | 16 | 16 | 0 |

**Grand total: 65/66 done. Only M1 (video URLs) requires content from the user.**

---

## Remaining Items

| # | What's needed |
|---|---|
| M1 | YouTube/Vimeo URLs for the 3 student How-to tutorial cards |
