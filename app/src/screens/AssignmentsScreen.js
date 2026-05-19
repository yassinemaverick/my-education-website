import React, { useEffect, useState, useCallback } from 'react';
import {
  View, Text, FlatList, StyleSheet, RefreshControl,
  TouchableOpacity, ActivityIndicator,
} from 'react-native';
import { colors, radius } from '../theme';
import { getAssignments } from '../api';

const STATUS_META = {
  pending:   { color: colors.yellow, label: 'Pending',   bg: 'rgba(251,191,36,0.12)'  },
  overdue:   { color: colors.red,    label: 'Overdue',   bg: 'rgba(248,113,113,0.12)' },
  submitted: { color: colors.green,  label: 'Submitted', bg: 'rgba(52,211,153,0.12)'  },
};

const FILTERS = ['all', 'pending', 'overdue', 'submitted'];

function Badge({ status }) {
  const m = STATUS_META[status] || STATUS_META.pending;
  return (
    <View style={[s.badge, { backgroundColor: m.bg, borderColor: m.color + '44' }]}>
      <Text style={[s.badgeText, { color: m.color }]}>{m.label}</Text>
    </View>
  );
}

function AssignmentItem({ item }) {
  const due = item.due_date
    ? new Date(item.due_date + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
    : null;

  return (
    <View style={s.item}>
      <View style={s.itemHeader}>
        <Text style={s.itemTitle} numberOfLines={2}>{item.title || 'Untitled'}</Text>
        <Badge status={item.status} />
      </View>
      {item.subject ? <Text style={s.itemSubject}>{item.subject}</Text> : null}
      {item.description ? (
        <Text style={s.itemDesc} numberOfLines={2}>{item.description}</Text>
      ) : null}
      <View style={s.itemMeta}>
        {due ? <Text style={s.itemDue}>📅 Due {due}</Text> : null}
        {item.score !== null && item.score !== undefined ? (
          <Text style={[s.itemScore, { color: colors.green }]}>Score: {item.score}</Text>
        ) : null}
      </View>
    </View>
  );
}

export default function AssignmentsScreen() {
  const [assignments, setAssignments] = useState([]);
  const [filter,      setFilter]      = useState('all');
  const [loading,     setLoading]     = useState(true);
  const [refreshing,  setRefreshing]  = useState(false);
  const [error,       setError]       = useState(null);

  const load = useCallback(async (isRefresh = false) => {
    if (isRefresh) setRefreshing(true); else setLoading(true);
    setError(null);
    try {
      const d = await getAssignments();
      if (d.ok) setAssignments(d.assignments || []);
      else setError(d.error || 'Failed to load assignments.');
    } catch {
      setError('Connection error. Pull down to retry.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const visible = filter === 'all'
    ? assignments
    : assignments.filter(a => a.status === filter);

  const counts = {
    all:       assignments.length,
    pending:   assignments.filter(a => a.status === 'pending').length,
    overdue:   assignments.filter(a => a.status === 'overdue').length,
    submitted: assignments.filter(a => a.status === 'submitted').length,
  };

  return (
    <View style={s.root}>
      {/* Filter tabs */}
      <View style={s.tabs}>
        {FILTERS.map(f => (
          <TouchableOpacity
            key={f}
            style={[s.tab, filter === f && s.tabActive]}
            onPress={() => setFilter(f)}
          >
            <Text style={[s.tabText, filter === f && s.tabTextActive]}>
              {f.charAt(0).toUpperCase() + f.slice(1)}
              {counts[f] > 0 ? ` (${counts[f]})` : ''}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      {loading ? (
        <ActivityIndicator color={colors.yellow} style={{ marginTop: 60 }} />
      ) : error ? (
        <View style={s.center}>
          <Text style={s.errorText}>{error}</Text>
          <TouchableOpacity onPress={() => load()} style={s.retryBtn}>
            <Text style={s.retryText}>Retry</Text>
          </TouchableOpacity>
        </View>
      ) : visible.length === 0 ? (
        <View style={s.center}>
          <Text style={{ fontSize: 36, marginBottom: 12 }}>📭</Text>
          <Text style={s.emptyText}>No {filter !== 'all' ? filter : ''} assignments.</Text>
        </View>
      ) : (
        <FlatList
          data={visible}
          keyExtractor={item => String(item.id)}
          renderItem={({ item }) => <AssignmentItem item={item} />}
          contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => load(true)}
              tintColor={colors.yellow}
              colors={[colors.yellow]}
            />
          }
        />
      )}
    </View>
  );
}

const s = StyleSheet.create({
  root:          { flex: 1, backgroundColor: colors.bg },
  tabs:          { flexDirection: 'row', borderBottomWidth: 1, borderColor: colors.border,
                   paddingHorizontal: 12 },
  tab:           { paddingHorizontal: 12, paddingVertical: 14, marginRight: 4 },
  tabActive:     { borderBottomWidth: 2, borderBottomColor: colors.yellow },
  tabText:       { fontSize: 13, fontWeight: '500', color: colors.muted },
  tabTextActive: { color: colors.yellow, fontWeight: '700' },
  item:          { backgroundColor: colors.card, borderRadius: radius.lg, padding: 16,
                   marginBottom: 10, borderWidth: 1, borderColor: colors.border },
  itemHeader:    { flexDirection: 'row', justifyContent: 'space-between',
                   alignItems: 'flex-start', gap: 8, marginBottom: 6 },
  itemTitle:     { flex: 1, fontSize: 15, fontWeight: '700', color: colors.text },
  badge:         { borderRadius: 100, paddingHorizontal: 8, paddingVertical: 3,
                   borderWidth: 1, flexShrink: 0 },
  badgeText:     { fontSize: 11, fontWeight: '700' },
  itemSubject:   { fontSize: 12, color: colors.blue, fontWeight: '600', marginBottom: 4 },
  itemDesc:      { fontSize: 13, color: colors.muted, lineHeight: 19, marginBottom: 6 },
  itemMeta:      { flexDirection: 'row', justifyContent: 'space-between', marginTop: 4 },
  itemDue:       { fontSize: 12, color: colors.muted2 },
  itemScore:     { fontSize: 12, fontWeight: '700' },
  center:        { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12, padding: 24 },
  errorText:     { color: colors.muted, fontSize: 14, textAlign: 'center' },
  emptyText:     { color: colors.muted, fontSize: 15, textAlign: 'center' },
  retryBtn:      { backgroundColor: colors.yellow, borderRadius: radius.sm,
                   paddingHorizontal: 20, paddingVertical: 10 },
  retryText:     { color: '#0d0d14', fontWeight: '700', fontSize: 14 },
});
