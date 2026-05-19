# Session 14 — May 19, 2026

## Summary

One reverted experiment, then a full standalone mobile app built from scratch.

---

## Reverted — PWA attempt on existing dashboard

Tried adding a manifest + service worker + dark theme directly to `study/dashboard-student.php`. Reverted immediately when the goal was clarified: the app should be **separate** from the dashboard, not a skin on top of it.

Commits involved: `5467f7b` (add) → `506ebcb` (revert). Net effect on main: zero.

---

## Built — Upskill Student Mobile App (React Native / Expo)

A standalone Android app that connects to the existing PHP backend via a new token-based API.

### Backend — `study/api_mobile.php` (NEW)

| Action | Method | Description |
|---|---|---|
| `login` | POST | Validates credentials, creates 90-day bearer token in `mobile_tokens` DB table |
| `logout` | POST | Deletes token from DB |
| `overview` | GET | Course info, attendance rate, assignment counts (pending / overdue / submitted) |
| `assignments` | GET | Full assignment list with status, due date, score, teacher comment |
| `notifications` | GET | Notifications list + unread count |
| `mark_read` | POST | Mark one or all notifications read |

- Auth: `Authorization: Bearer <token>` header on all routes except login
- Tokens stored hashed (SHA-256) in new `mobile_tokens` table; max 5 active per student
- Rate-limited per action (10 logins per 5 min per IP; 60 req/min for data endpoints)
- CORS headers set for mobile fetch compatibility
- No changes made to the existing web dashboard

### App — `app/` (NEW)

Expo SDK 52, managed workflow, Android-first.

```
app/
  App.js                         Root: auth gate + navigation
  app.json                       Expo config (package name: com.upskill.student)
  package.json                   Dependencies (Expo 52, React Navigation, AsyncStorage)
  babel.config.js
  README.md                      Setup + build instructions
  src/
    config.js                    API base URL — edit this to set your domain
    api.js                       Fetch wrapper: login, logout, getOverview, getAssignments,
                                 getNotifications, markRead; token stored in AsyncStorage
    theme.js                     Dark colour palette (#0d0d14 bg, #fbbf24 accent)
    screens/
      LoginScreen.js             Username + password → bearer token; error alerts
      HomeScreen.js              Attendance progress bar, 3 stat cards, course card; pull-to-refresh
      AssignmentsScreen.js       Filterable list (All / Pending / Overdue / Submitted); badges
      NotificationsScreen.js     Tap-to-mark-read; mark-all-read; unread dot + count
```

**Navigation structure:** unauthenticated → LoginScreen; authenticated → bottom tab navigator (Home / Assignments / Notifications)

**Token lifecycle:** On login the raw 64-char hex token is stored in AsyncStorage. On logout it is deleted from both AsyncStorage and the server DB. App boot checks for a saved token and skips the login screen if one exists.

### Setup steps (for later)

1. Edit `app/src/config.js` — replace `YOUR_DOMAIN.com` with the live domain
2. `cd app && npm install`
3. `npx expo start` — scan QR with Expo Go app on Android phone to test
4. `eas build --platform android --profile preview` — produces a downloadable APK

---

## Files changed

| File | Status |
|---|---|
| `study/api_mobile.php` | New |
| `app/App.js` | New |
| `app/app.json` | New |
| `app/package.json` | New |
| `app/babel.config.js` | New |
| `app/README.md` | New |
| `app/src/config.js` | New |
| `app/src/api.js` | New |
| `app/src/theme.js` | New |
| `app/src/screens/LoginScreen.js` | New |
| `app/src/screens/HomeScreen.js` | New |
| `app/src/screens/AssignmentsScreen.js` | New |
| `app/src/screens/NotificationsScreen.js` | New |

Commit: `a8bf1a8`
