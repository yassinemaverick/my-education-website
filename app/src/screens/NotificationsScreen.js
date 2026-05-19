import React, { useEffect, useState, useCallback } from 'react';
import {
  View, Text, FlatList, StyleSheet, RefreshControl,
  TouchableOpacity, ActivityIndicator,
} from 'react-native';
import { colors, radius } from '../theme';
import { getNotifications, markRead } from '../api';

const TYPE_ICONS = {
  new_assignment: '📚',
  overdue:        '⚠️',
  submission:     '✅',
  announcement:   '📢',
  quiz:           '🧠',
  message:        '💬',
  lesson_note:    '📝',
  info:           '🔔',
};

function timeAgo(ts) {
  if (!ts) return '';
  const d    = new Date(ts.replace(' ', 'T'));
  const diff = Math.floor((Date.now() - d.getTime()) / 1000);
  if (diff < 60)   return 'Just now';
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  return `${Math.floor(diff / 86400)}d ago`;
}

function NotifItem({ item, onPress }) {
  return (
    <TouchableOpacity
      style={[s.item, !item.is_read && s.itemUnread]}
      onPress={() => onPress(item)}
      activeOpacity={0.75}
    >
      <View style={s.iconWrap}>
        <Text style={s.icon}>{TYPE_ICONS[item.type] || '🔔'}</Text>
      </View>
      <View style={s.content}>
        <Text style={s.title} numberOfLines={1}>{item.title || 'Notification'}</Text>
        <Text style={s.body} numberOfLines={2}>{item.body}</Text>
        <Text style={s.time}>{timeAgo(item.created_at)}</Text>
      </View>
      {!item.is_read && <View style={s.dot} />}
    </TouchableOpacity>
  );
}

export default function NotificationsScreen() {
  const [notifs,     setNotifs]     = useState([]);
  const [loading,    setLoading]    = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error,      setError]      = useState(null);

  const load = useCallback(async (isRefresh = false) => {
    if (isRefresh) setRefreshing(true); else setLoading(true);
    setError(null);
    try {
      const d = await getNotifications();
      if (d.ok) setNotifs(d.notifications || []);
      else setError(d.error || 'Failed to load notifications.');
    } catch {
      setError('Connection error. Pull down to retry.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  async function handlePress(item) {
    if (!item.is_read) {
      setNotifs(prev => prev.map(n => n.id === item.id ? { ...n, is_read: 1 } : n));
      await markRead(item.id).catch(() => {});
    }
  }

  async function handleMarkAll() {
    setNotifs(prev => prev.map(n => ({ ...n, is_read: 1 })));
    await markRead(null).catch(() => {});
  }

  const unread = notifs.filter(n => !n.is_read).length;

  return (
    <View style={s.root}>
      {/* Header row */}
      <View style={s.bar}>
        <Text style={s.barTitle}>
          Notifications {unread > 0 ? <Text style={s.barBadge}>({unread} new)</Text> : null}
        </Text>
        {unread > 0 && (
          <TouchableOpacity onPress={handleMarkAll}>
            <Text style={s.markAll}>Mark all read</Text>
          </TouchableOpacity>
        )}
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
      ) : notifs.length === 0 ? (
        <View style={s.center}>
          <Text style={{ fontSize: 40, marginBottom: 12 }}>🔔</Text>
          <Text style={s.emptyText}>No notifications yet.</Text>
        </View>
      ) : (
        <FlatList
          data={notifs}
          keyExtractor={item => String(item.id)}
          renderItem={({ item }) => <NotifItem item={item} onPress={handlePress} />}
          contentContainerStyle={{ paddingVertical: 8, paddingBottom: 40 }}
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
  root:       { flex: 1, backgroundColor: colors.bg },
  bar:        { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
                paddingHorizontal: 16, paddingVertical: 14,
                borderBottomWidth: 1, borderColor: colors.border },
  barTitle:   { fontSize: 15, fontWeight: '700', color: colors.text },
  barBadge:   { color: colors.blue },
  markAll:    { fontSize: 12, color: colors.blue, fontWeight: '600' },
  item:       { flexDirection: 'row', alignItems: 'flex-start', gap: 12,
                paddingHorizontal: 16, paddingVertical: 14,
                borderBottomWidth: 1, borderColor: colors.border2 },
  itemUnread: { backgroundColor: 'rgba(96,165,250,0.06)',
                borderLeftWidth: 3, borderLeftColor: colors.blue },
  iconWrap:   { width: 38, height: 38, borderRadius: 10, backgroundColor: colors.card,
                alignItems: 'center', justifyContent: 'center', flexShrink: 0 },
  icon:       { fontSize: 18 },
  content:    { flex: 1 },
  title:      { fontSize: 13, fontWeight: '700', color: colors.text, marginBottom: 2 },
  body:       { fontSize: 12, color: colors.muted, lineHeight: 17, marginBottom: 4 },
  time:       { fontSize: 11, color: colors.muted2 },
  dot:        { width: 7, height: 7, borderRadius: 4, backgroundColor: colors.blue,
                marginTop: 6, flexShrink: 0 },
  center:     { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12, padding: 24 },
  errorText:  { color: colors.muted, fontSize: 14, textAlign: 'center' },
  emptyText:  { color: colors.muted, fontSize: 15, textAlign: 'center' },
  retryBtn:   { backgroundColor: colors.yellow, borderRadius: radius.sm,
                paddingHorizontal: 20, paddingVertical: 10 },
  retryText:  { color: '#0d0d14', fontWeight: '700', fontSize: 14 },
});
