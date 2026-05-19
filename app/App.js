import React, { useEffect, useState } from 'react';
import { View, ActivityIndicator, StyleSheet } from 'react-native';
import { NavigationContainer, DefaultTheme } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { colors } from './src/theme';
import { getToken } from './src/api';
import LoginScreen        from './src/screens/LoginScreen';
import HomeScreen         from './src/screens/HomeScreen';
import AssignmentsScreen  from './src/screens/AssignmentsScreen';
import NotificationsScreen from './src/screens/NotificationsScreen';

const Stack = createNativeStackNavigator();
const Tab   = createBottomTabNavigator();

const navTheme = {
  ...DefaultTheme,
  colors: {
    ...DefaultTheme.colors,
    background: colors.bg,
    card:       colors.bgMid,
    text:       colors.text,
    border:     colors.border,
    primary:    colors.yellow,
  },
};

function TabIcon({ name, focused }) {
  const icons = { Home: '🏠', Assignments: '📋', Notifications: '🔔' };
  return (
    <View style={{ opacity: focused ? 1 : 0.45 }}>
      <View style={[{ padding: 2 }, focused && { borderBottomWidth: 2, borderColor: colors.yellow }]}>
        {/* Simple text emoji icon */}
      </View>
    </View>
  );
}

function MainTabs({ user, onLogout }) {
  return (
    <Tab.Navigator
      screenOptions={{
        headerShown:        false,
        tabBarStyle:        {
          backgroundColor:  colors.bgMid,
          borderTopColor:   colors.border,
          borderTopWidth:   1,
          paddingTop:       6,
          paddingBottom:    8,
          height:           60,
        },
        tabBarActiveTintColor:   colors.yellow,
        tabBarInactiveTintColor: colors.muted,
        tabBarLabelStyle:        { fontSize: 11, fontWeight: '600' },
      }}
    >
      <Tab.Screen
        name="Home"
        options={{ tabBarIcon: ({ focused }) => <TabIcon name="Home" focused={focused} />, tabBarLabel: 'Home' }}
      >
        {() => <HomeScreen user={user} onLogout={onLogout} />}
      </Tab.Screen>
      <Tab.Screen
        name="Assignments"
        component={AssignmentsScreen}
        options={{ tabBarLabel: 'Assignments' }}
      />
      <Tab.Screen
        name="Notifications"
        component={NotificationsScreen}
        options={{ tabBarLabel: 'Notifications' }}
      />
    </Tab.Navigator>
  );
}

export default function App() {
  const [user,    setUser]    = useState(null);
  const [booting, setBooting] = useState(true);

  // Restore session on launch
  useEffect(() => {
    getToken()
      .then(t => { if (!t) setBooting(false); else setBooting(false); })
      .catch(() => setBooting(false));
  }, []);

  if (booting) {
    return (
      <View style={s.boot}>
        <ActivityIndicator color={colors.yellow} size="large" />
      </View>
    );
  }

  return (
    <SafeAreaProvider>
      <NavigationContainer theme={navTheme}>
        {user ? (
          <Stack.Navigator screenOptions={{ headerShown: false }}>
            <Stack.Screen name="Main">
              {() => <MainTabs user={user} onLogout={() => setUser(null)} />}
            </Stack.Screen>
          </Stack.Navigator>
        ) : (
          <Stack.Navigator screenOptions={{ headerShown: false }}>
            <Stack.Screen name="Login">
              {() => <LoginScreen onLogin={setUser} />}
            </Stack.Screen>
          </Stack.Navigator>
        )}
      </NavigationContainer>
    </SafeAreaProvider>
  );
}

const s = StyleSheet.create({
  boot: { flex: 1, backgroundColor: colors.bg, alignItems: 'center', justifyContent: 'center' },
});
