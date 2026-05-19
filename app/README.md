# Upskill Student App

React Native (Expo) mobile app for students. Android-first.

## Setup

### 1. Configure your domain
Open `src/config.js` and replace `YOUR_DOMAIN.com` with your actual domain:
```js
export const API_URL = 'https://yourdomain.com/study/api_mobile.php';
```

### 2. Install dependencies
```bash
cd app
npm install
```

### 3. Run on device / emulator
```bash
npx expo start
# Press 'a' to open on Android emulator
# Or scan the QR code with Expo Go app on your phone
```

### 4. Build a standalone APK (to share / install directly)
```bash
# Install EAS CLI once
npm install -g eas-cli
eas login

# Build preview APK
eas build --platform android --profile preview
```
The EAS dashboard will give you a download link for the APK.

## File structure
```
app/
  App.js                        — Root: auth gate + navigation
  src/
    config.js                   — API URL (edit this!)
    api.js                      — All fetch calls + token storage
    theme.js                    — Colors, spacing
    screens/
      LoginScreen.js
      HomeScreen.js
      AssignmentsScreen.js
      NotificationsScreen.js
```

## Backend
The app talks to `study/api_mobile.php` which was added to the same repo.
It issues 90-day bearer tokens stored in a `mobile_tokens` DB table.
No changes to the existing web dashboard are needed.
