import React, { useEffect, useState, useCallback } from 'react';
import {
  View, Text, ScrollView, StyleSheet, RefreshControl,
  TouchableOpacity, ActivityIndicator,
} from 'react-native';
import { colors, radius } from '../theme';
import { getOverview, logout } from '../api';

function StatCard({ label, value, sub, accent }) {
  return (
    <View style={[s.statCard, { borderTopColor: accent, borderTopWidth: 3 }]}>
      <Text style={[s.statValue, { color: accent }]}>{value}</Text>
      <Text style={s.statLabel}>{label}</Text>
      {sub ? <Text style={s.statSub}>{sub}</Text> : null}
    </View>
  );
}

function AttBar({ rate }) {
  const pct  = rate ?? 0;
  const color = pct >= 80 ? colors.green : pct >= 60 ? colors.yellow : colors.red;
  return (
    <View style={s.attWrap}>
      <View style={s.attTrack}>
        <View style={[s.attFill, { width: `${pct}%`, backgroundColor: color }]} />
      </View>
      <Text style={[s.attPct, { color }]}>{rate !== null ? `${pct}%` : '—'}</Text>
    </View>
  );
}

export default function HomeScreen({ user, onLogout }) {
  const [data,       setData]       = useState(null);
  const [loading,    setLoading]    = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error,      setError]      = useState(null);

  const load = useCallback(async (isRefresh = false) => {
    if (isRefresh) setRefreshing(true); else setLoading(true);
    setError(null);
    try {
      const d = await getOverview();
      if (d.ok) setData(d);
      else setError(d.error || 'Failed to load data.');
    } catch {
      setError('Connection error. Pull down to retry.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  async function handleLogout() {
    await logout();
    onLogout();
  }

  const name = data?.full_name || user?.full_name || '';
  const firstName = name.split(' ')[0];

  return (
    <ScrollView
      style={s.root}
      contentContainerStyle={s.content}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          onRefresh={() => load(true)}
          tintColor={colors.yellow}
          colors={[colors.yellow]}
        />
      }
    >
      {/* Header */}
      <View style={s.header}>
        <View>
          <Text style={s.hello}>Hello, {firstName} 👋</Text>
          <Text style={s.sub}>Your learning dashboard</Text>
        </View>
        <TouchableOpacity onPress={handleLogout} style={s.logoutBtn}>
          <Text style={s.logoutText}>Sign out</Text>
        </TouchableOpacity>
      </View>

      {loading && !data ? (
        <ActivityIndicator color={colors.yellow} style={{ marginTop: 60 }} />
      ) : error ? (
        <View style={s.errorWrap}>
          <Text style={s.errorText}>{error}</Text>
          <TouchableOpacity onPress={() => load()} style={s.retryBtn}>
            <Text style={s.retryText}>Retry</Text>
          </TouchableOpacity>
        </View>
      ) : data ? (
        <>
          {/* Course card */}
          {data.course ? (
            <View style={s.courseCard}>
              <Text style={s.courseLabel}>YOUR CLASS</Text>
              <Text style={s.courseName}>{data.course.label_en || data.course.label_fr}</Text>
              {data.course.teacher_name ? (
                <Text style={s.courseMeta}>Teacher: {data.course.teacher_name}</Text>
              ) : null}
            </View>
          ) : (
            <View style={[s.courseCard, { borderColor: colors.border }]}>
              <Text style={s.courseLabel}>YOUR CLASS</Text>
              <Text style={s.muted}>No class assigned yet.</Text>
            </View>
          )}

          {/* Attendance */}
          <View style={s.card}>
            <Text style={s.sectionTitle}>Attendance</Text>
            <AttBar rate={data.att_rate} />
            <Text style={s.attDetail}>
              {data.att_present} present / {data.att_total} sessions
            </Text>
          </View>

          {/* Stats row */}
          <Text style={s.sectionTitle}>Assignments</Text>
          <View style={s.statsRow}>
            <StatCard
              label="Pending"
              value={data.pending_count ?? 0}
              accent={colors.yellow}
            />
            <StatCard
              label="Overdue"
              value={data.overdue_count ?? 0}
              accent={colors.red}
            />
            <StatCard
              label="Submitted"
              value={data.submitted_count ?? 0}
              accent={colors.green}
            />
          </View>
        </>
      ) : null}
    </ScrollView>
  );
}

const s = StyleSheet.create({
  root:        { flex: 1, backgroundColor: colors.bg },
  content:     { padding: 20, paddingBottom: 40 },
  header:      { flexDirection: 'row', justifyContent: 'space-between',
                 alignItems: 'flex-start', marginBottom: 24 },
  hello:       { fontSize: 26, fontWeight: '800', color: colors.text, letterSpacing: -0.5 },
  sub:         { fontSize: 13, color: colors.muted, marginTop: 2 },
  logoutBtn:   { backgroundColor: 'rgba(248,113,113,0.12)', borderRadius: radius.sm,
                 paddingHorizontal: 12, paddingVertical: 6, borderWidth: 1,
                 borderColor: 'rgba(248,113,113,0.25)' },
  logoutText:  { color: colors.red, fontSize: 12, fontWeight: '600' },
  courseCard:  { backgroundColor: colors.card, borderRadius: radius.lg, padding: 18,
                 marginBottom: 16, borderWidth: 1,
                 borderColor: 'rgba(167,139,250,0.35)' },
  courseLabel: { fontSize: 10, fontWeight: '700', color: colors.purple, letterSpacing: 1.2,
                 textTransform: 'uppercase', marginBottom: 6 },
  courseName:  { fontSize: 18, fontWeight: '700', color: colors.text, marginBottom: 4 },
  courseMeta:  { fontSize: 13, color: colors.muted },
  card:        { backgroundColor: colors.card, borderRadius: radius.lg, padding: 18,
                 marginBottom: 16, borderWidth: 1, borderColor: colors.border },
  sectionTitle:{ fontSize: 12, fontWeight: '700', color: colors.muted, letterSpacing: 0.8,
                 textTransform: 'uppercase', marginBottom: 12 },
  attWrap:     { flexDirection: 'row', alignItems: 'center', gap: 10 },
  attTrack:    { flex: 1, height: 8, backgroundColor: 'rgba(255,255,255,0.08)',
                 borderRadius: 100, overflow: 'hidden' },
  attFill:     { height: '100%', borderRadius: 100 },
  attPct:      { fontSize: 15, fontWeight: '700', minWidth: 42, textAlign: 'right' },
  attDetail:   { fontSize: 12, color: colors.muted, marginTop: 8 },
  statsRow:    { flexDirection: 'row', gap: 10, marginBottom: 16 },
  statCard:    { flex: 1, backgroundColor: colors.card, borderRadius: radius.md, padding: 14,
                 borderWidth: 1, borderColor: colors.border },
  statValue:   { fontSize: 28, fontWeight: '800', letterSpacing: -1 },
  statLabel:   { fontSize: 11, color: colors.muted, marginTop: 4, fontWeight: '600' },
  statSub:     { fontSize: 11, color: colors.muted2, marginTop: 2 },
  muted:       { color: colors.muted, fontSize: 14 },
  errorWrap:   { alignItems: 'center', paddingTop: 60, gap: 12 },
  errorText:   { color: colors.muted, fontSize: 14, textAlign: 'center' },
  retryBtn:    { backgroundColor: colors.yellow, borderRadius: radius.sm,
                 paddingHorizontal: 20, paddingVertical: 10 },
  retryText:   { color: '#0d0d14', fontWeight: '700', fontSize: 14 },
});
