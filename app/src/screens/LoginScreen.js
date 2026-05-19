import React, { useState } from 'react';
import {
  View, Text, TextInput, TouchableOpacity, StyleSheet,
  KeyboardAvoidingView, Platform, ActivityIndicator, Alert,
} from 'react-native';
import { colors, radius } from '../theme';
import { login } from '../api';

export default function LoginScreen({ onLogin }) {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [loading,  setLoading]  = useState(false);

  async function handleLogin() {
    if (!username.trim() || !password) {
      Alert.alert('Missing fields', 'Please enter your username and password.');
      return;
    }
    setLoading(true);
    try {
      const data = await login(username.trim(), password);
      if (data.ok) {
        onLogin(data.user);
      } else {
        Alert.alert('Login failed', data.error || 'Invalid credentials.');
      }
    } catch {
      Alert.alert('Connection error', 'Could not reach the server. Check your internet connection.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <KeyboardAvoidingView
      style={s.root}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <View style={s.inner}>
        {/* Logo area */}
        <View style={s.logoWrap}>
          <Text style={s.logoText}>
            Up<Text style={s.logoAccent}>skill</Text>
          </Text>
          <Text style={s.logoSub}>Student Portal</Text>
        </View>

        {/* Card */}
        <View style={s.card}>
          <Text style={s.cardTitle}>Welcome back</Text>
          <Text style={s.cardSub}>Sign in to your account</Text>

          <Text style={s.label}>USERNAME</Text>
          <TextInput
            style={s.input}
            placeholder="Your username"
            placeholderTextColor={colors.muted2}
            autoCapitalize="none"
            autoCorrect={false}
            value={username}
            onChangeText={setUsername}
            returnKeyType="next"
          />

          <Text style={s.label}>PASSWORD</Text>
          <TextInput
            style={s.input}
            placeholder="Your password"
            placeholderTextColor={colors.muted2}
            secureTextEntry
            value={password}
            onChangeText={setPassword}
            returnKeyType="done"
            onSubmitEditing={handleLogin}
          />

          <TouchableOpacity
            style={[s.btn, loading && s.btnDisabled]}
            onPress={handleLogin}
            disabled={loading}
            activeOpacity={0.85}
          >
            {loading
              ? <ActivityIndicator color="#fff" />
              : <Text style={s.btnText}>Sign in →</Text>
            }
          </TouchableOpacity>
        </View>

        <Text style={s.footer}>Upskill Education</Text>
      </View>
    </KeyboardAvoidingView>
  );
}

const s = StyleSheet.create({
  root:       { flex: 1, backgroundColor: colors.bg },
  inner:      { flex: 1, justifyContent: 'center', paddingHorizontal: 24 },
  logoWrap:   { alignItems: 'center', marginBottom: 36 },
  logoText:   { fontSize: 36, fontWeight: '800', color: colors.text, letterSpacing: -1 },
  logoAccent: { color: colors.yellow },
  logoSub:    { fontSize: 14, color: colors.muted, marginTop: 4 },
  card:       { backgroundColor: colors.card, borderRadius: radius.xl, padding: 24,
                borderWidth: 1, borderColor: colors.border },
  cardTitle:  { fontSize: 22, fontWeight: '700', color: colors.text, marginBottom: 4 },
  cardSub:    { fontSize: 14, color: colors.muted, marginBottom: 24 },
  label:      { fontSize: 11, fontWeight: '600', color: colors.muted, letterSpacing: 1,
                textTransform: 'uppercase', marginBottom: 6, marginTop: 12 },
  input:      { backgroundColor: 'rgba(255,255,255,0.06)', borderWidth: 1, borderColor: colors.border,
                borderRadius: radius.md, padding: 14, color: colors.text, fontSize: 15 },
  btn:        { marginTop: 24, backgroundColor: colors.yellow, borderRadius: radius.md,
                paddingVertical: 15, alignItems: 'center' },
  btnDisabled:{ opacity: 0.6 },
  btnText:    { color: '#0d0d14', fontSize: 15, fontWeight: '700' },
  footer:     { textAlign: 'center', color: colors.muted2, fontSize: 12, marginTop: 32 },
});
