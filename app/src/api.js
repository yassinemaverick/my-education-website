import AsyncStorage from '@react-native-async-storage/async-storage';
import { API_URL } from './config';

const TOKEN_KEY = 'upskill_token';

export async function getToken() {
  return AsyncStorage.getItem(TOKEN_KEY);
}

export async function saveToken(token) {
  return AsyncStorage.setItem(TOKEN_KEY, token);
}

export async function clearToken() {
  return AsyncStorage.removeItem(TOKEN_KEY);
}

async function request(action, method = 'GET', body = null) {
  const token = await getToken();
  const headers = { 'Content-Type': 'application/json' };
  if (token) headers['Authorization'] = `Bearer ${token}`;

  const opts = { method, headers };
  if (body) opts.body = JSON.stringify(body);

  const res = await fetch(`${API_URL}?action=${action}`, opts);
  const data = await res.json();
  return data;
}

export async function login(username, password) {
  const data = await request('login', 'POST', { username, password });
  if (data.ok && data.token) await saveToken(data.token);
  return data;
}

export async function logout() {
  await request('logout', 'POST');
  await clearToken();
}

export const getOverview      = ()  => request('overview');
export const getAssignments   = ()  => request('assignments');
export const getNotifications = ()  => request('notifications');
export const markRead         = (id) => request('mark_read', 'POST', id ? { id } : {});
