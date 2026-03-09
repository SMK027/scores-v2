import React, { useEffect } from 'react';
import { Platform, StatusBar } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { NavigationContainer, DefaultTheme } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { COLORS } from './src/utils/config';
import { AuthProvider, useAuth } from './src/context/AuthContext';
import LoadingScreen from './src/components/LoadingScreen';

// Écrans Auth
import LoginScreen from './src/screens/LoginScreen';
import RegisterScreen from './src/screens/RegisterScreen';

// Écrans Espaces
import SpacesListScreen from './src/screens/SpacesListScreen';
import SpaceCreateScreen from './src/screens/SpaceCreateScreen';
import SpaceDashboardScreen from './src/screens/SpaceDashboardScreen';
import SpaceEditScreen from './src/screens/SpaceEditScreen';

// Écrans Membres
import MembersScreen from './src/screens/MembersScreen';

// Écrans Types de jeux
import GameTypesListScreen from './src/screens/GameTypesListScreen';
import GameTypeFormScreen from './src/screens/GameTypeFormScreen';

// Écrans Joueurs
import PlayersListScreen from './src/screens/PlayersListScreen';

// Écrans Parties
import GamesListScreen from './src/screens/GamesListScreen';
import GameCreateScreen from './src/screens/GameCreateScreen';
import GameDetailScreen from './src/screens/GameDetailScreen';
import ScoreEntryScreen from './src/screens/ScoreEntryScreen';

// Écrans Stats
import StatsScreen from './src/screens/StatsScreen';

// Profil
import ProfileScreen from './src/screens/ProfileScreen';

const AuthStack = createNativeStackNavigator();
const SpacesStack = createNativeStackNavigator();
const ProfileStack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();

const navTheme = {
  ...DefaultTheme,
  colors: {
    ...DefaultTheme.colors,
    background: COLORS.background,
    card: COLORS.surface,
    text: COLORS.text,
    border: COLORS.border,
    primary: COLORS.primary,
  },
};

const screenOptions = {
  headerStyle: { backgroundColor: COLORS.surface },
  headerTintColor: COLORS.white,
  headerTitleStyle: { fontWeight: '600' as const },
};

function AuthNavigator() {
  return (
    <AuthStack.Navigator screenOptions={{ ...screenOptions, headerShown: false }}>
      <AuthStack.Screen name="Login" component={LoginScreen} />
      <AuthStack.Screen name="Register" component={RegisterScreen} />
    </AuthStack.Navigator>
  );
}

function SpacesNavigator() {
  return (
    <SpacesStack.Navigator screenOptions={screenOptions}>
      <SpacesStack.Screen
        name="SpacesList"
        component={SpacesListScreen}
        options={{ title: 'Mes Espaces' }}
      />
      <SpacesStack.Screen
        name="SpaceCreate"
        component={SpaceCreateScreen}
        options={{ title: 'Nouvel Espace' }}
      />
      <SpacesStack.Screen
        name="SpaceDashboard"
        component={SpaceDashboardScreen}
        options={{ title: 'Espace' }}
      />
      <SpacesStack.Screen
        name="SpaceEdit"
        component={SpaceEditScreen}
        options={{ title: 'Modifier l\'Espace' }}
      />
      <SpacesStack.Screen
        name="Members"
        component={MembersScreen}
        options={{ title: 'Membres' }}
      />
      <SpacesStack.Screen
        name="GameTypesList"
        component={GameTypesListScreen}
        options={{ title: 'Types de Jeux' }}
      />
      <SpacesStack.Screen
        name="GameTypeForm"
        component={GameTypeFormScreen}
        options={({ route }: any) => ({
          title: route.params?.gameType ? 'Modifier le Type' : 'Nouveau Type',
        })}
      />
      <SpacesStack.Screen
        name="PlayersList"
        component={PlayersListScreen}
        options={{ title: 'Joueurs' }}
      />
      <SpacesStack.Screen
        name="GamesList"
        component={GamesListScreen}
        options={{ title: 'Parties' }}
      />
      <SpacesStack.Screen
        name="GameCreate"
        component={GameCreateScreen}
        options={{ title: 'Nouvelle Partie' }}
      />
      <SpacesStack.Screen
        name="GameDetail"
        component={GameDetailScreen}
        options={{ title: 'Détail de la Partie' }}
      />
      <SpacesStack.Screen
        name="ScoreEntry"
        component={ScoreEntryScreen}
        options={({ route }: any) => ({
          title: `Scores · Manche ${route.params?.roundNumber || ''}`,
        })}
      />
      <SpacesStack.Screen
        name="Stats"
        component={StatsScreen}
        options={{ title: 'Statistiques' }}
      />
    </SpacesStack.Navigator>
  );
}

function ProfileNavigator() {
  return (
    <ProfileStack.Navigator screenOptions={screenOptions}>
      <ProfileStack.Screen
        name="ProfileMain"
        component={ProfileScreen}
        options={{ title: 'Mon Profil' }}
      />
    </ProfileStack.Navigator>
  );
}

function MainTabs() {
  return (
    <Tab.Navigator
      screenOptions={{
        headerShown: false,
        tabBarStyle: {
          backgroundColor: COLORS.surface,
          borderTopColor: COLORS.border,
          height: 60,
          paddingBottom: 8,
          paddingTop: 4,
        },
        tabBarActiveTintColor: COLORS.primary,
        tabBarInactiveTintColor: COLORS.textMuted,
        tabBarLabelStyle: { fontSize: 12, fontWeight: '600' },
      }}
    >
      <Tab.Screen
        name="SpacesTab"
        component={SpacesNavigator}
        options={{
          tabBarLabel: 'Espaces',
          tabBarIcon: ({ color }) => (
            <TabIcon label="🏠" color={color} />
          ),
        }}
      />
      <Tab.Screen
        name="ProfileTab"
        component={ProfileNavigator}
        options={{
          tabBarLabel: 'Profil',
          tabBarIcon: ({ color }) => (
            <TabIcon label="👤" color={color} />
          ),
        }}
      />
    </Tab.Navigator>
  );
}

// Icône simple basée sur emoji (pas de dépendance icon)
function TabIcon({ label }: { label: string; color: string }) {
  const { Text } = require('react-native');
  return <Text style={{ fontSize: 20 }}>{label}</Text>;
}

function RootNavigator() {
  const { user, isLoading } = useAuth();

  if (isLoading) return <LoadingScreen />;

  return user ? <MainTabs /> : <AuthNavigator />;
}

export default function App() {
  useEffect(() => {
    if (Platform.OS === 'android') {
      const NavigationBar = require('expo-navigation-bar');
      NavigationBar.setPositionAsync('absolute');
      NavigationBar.setBackgroundColorAsync('#1a1a2e00');
      NavigationBar.setVisibilityAsync('hidden');
    }
  }, []);

  return (
    <SafeAreaProvider>
      <AuthProvider>
        <NavigationContainer theme={navTheme}>
          <StatusBar barStyle="light-content" backgroundColor="transparent" translucent />
          <RootNavigator />
        </NavigationContainer>
      </AuthProvider>
    </SafeAreaProvider>
  );
}
