import { useEffect, useMemo, useState } from "react";
import { AppState, Pressable, StyleSheet, Text, View, ActivityIndicator, Platform } from "react-native";
import * as Notifications from "expo-notifications";
import Constants from "expo-constants";
import { StatusBar } from "expo-status-bar";
import * as NavigationBar from "expo-navigation-bar";
import { SafeAreaView } from "react-native-safe-area-context";
import { refreshAuthToken, registerDevicePushToken, unregisterDevicePushToken } from "./services/api";
import { registerForPushNotificationsAsync } from "./services/pushNotifications";
import { clearSession, loadSession, saveSession } from "./services/session";
import { loadAuthSession } from "./services/db";
import { GameDetailScreen } from "./screens/GameDetailScreen";
import { CompetitionDetailScreen } from "./screens/CompetitionDetailScreen";
import { CreateGameTypeScreen } from "./screens/CreateGameTypeScreen";
import { CreateSpaceScreen } from "./screens/CreateSpaceScreen";
import { CreatePlayerScreen } from "./screens/CreatePlayerScreen";
import { DashboardScreen } from "./screens/DashboardScreen";
import { LoginScreen } from "./screens/LoginScreen";
import { NotificationsScreen } from "./screens/NotificationsScreen";
import { ProfileScreen } from "./screens/ProfileScreen";
import { RefereeLoginScreen } from "./screens/RefereeLoginScreen";
import { RefereeDashboardScreen } from "./screens/RefereeDashboardScreen";
import { SplashScreen } from "./screens/SplashScreen";
import { SpaceScreen } from "./screens/SpaceScreen";
import { SpacesScreen } from "./screens/SpacesScreen";
import { WelcomeScreen } from "./screens/WelcomeScreen";
import { ThemeProvider, useAppTheme } from "./context/ThemeContext";
import { NotificationProvider, useNotifications } from "./context/NotificationContext";
import { NetworkProvider, useNetwork } from "./context/NetworkContext";
import type { RefereeSession, Space, User } from "./types/api";

type Route =
  | { name: "welcome" }
  | { name: "login" }
  | { name: "dashboard" }
  | { name: "spaces" }
  | { name: "notifications" }
  | { name: "create-space" }
  | { name: "create-player"; space: Space }
  | { name: "create-game-type"; space: Space }
  | { name: "profile" }
  | { name: "space"; space: Space }
  | { name: "game"; space: Space; gameId: number }
  | { name: "competition"; space: Space; competitionId: number }
  | { name: "referee-login"; space?: Space; competitionId?: number }
  | { name: "referee-dashboard"; refereeToken: string; session: RefereeSession; space?: Space };

const isExpoGo =
  Constants.executionEnvironment === "storeClient" ||
  Constants.appOwnership === "expo";

if (!isExpoGo) {
  Notifications.setNotificationHandler({
    handleNotification: async () => ({
      shouldShowBanner: true,
      shouldShowList: true,
      shouldPlaySound: false,
      shouldSetBadge: false,
    }),
  });
}

function MobileAppContent() {
  const { theme, preference, resolvedMode, setPreference } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);

  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<User | null>(null);
  const [route, setRoute] = useState<Route>({ name: "welcome" });
  const [previousRoute, setPreviousRoute] = useState<Route>({ name: "dashboard" });
  const [bootstrapping, setBootstrapping] = useState(true);
  const [showSplash, setShowSplash] = useState(true);
  const [registeredPushToken, setRegisteredPushToken] = useState<string | null>(null);

  const goToSpaces = () => setRoute({ name: "spaces" });
  const goToDashboard = () => setRoute({ name: "dashboard" });

  const logout = () => {
    const currentToken = token;
    const currentPushToken = registeredPushToken;

    setToken(null);
    setUser(null);
    setRoute({ name: "welcome" });
    setRegisteredPushToken(null);
    void clearSession();

    if (currentToken && currentPushToken) {
      void unregisterDevicePushToken(currentToken, currentPushToken).catch(() => undefined);
    }
  };

  useEffect(() => {
    void (async () => {
      if (Platform.OS !== "android") {
        return;
      }

      try {
        await NavigationBar.setVisibilityAsync("hidden");
      } catch {
        // Ignore les erreurs silencieusement selon le device.
      }
    })();
  }, []);

  useEffect(() => {
    const timeout = setTimeout(() => setShowSplash(false), 1500);
    return () => clearTimeout(timeout);
  }, []);

  useEffect(() => {
    const restore = async () => {
      try {
        const persisted = await loadSession();
        if (!persisted) {
          return;
        }

        try {
          const refreshed = await refreshAuthToken(persisted.token);
          setToken(refreshed.token);
          setUser(refreshed.user);
          setRoute({ name: "dashboard" });
          await saveSession(refreshed.token, refreshed.user);
        } catch {
          // Échec réseau : tenter le fallback sur la session SQLite locale
          const cached = await loadAuthSession();
          if (cached && cached.expiresAt > Date.now()) {
            setToken(cached.token);
            setUser(cached.user);
            setRoute({ name: "dashboard" });
          } else {
            // Token expiré ou absent : déconnecter proprement
            await clearSession();
          }
        }
      } catch {
        await clearSession();
      } finally {
        setBootstrapping(false);
      }
    };

    void restore();
  }, []);

  useEffect(() => {
    if (bootstrapping) {
      return;
    }

    const persist = async () => {
      if (token && user) {
        await saveSession(token, user);
      }
    };

    void persist();
  }, [bootstrapping, token, user]);

  useEffect(() => {
    const subscription = AppState.addEventListener("change", (state) => {
      if (state !== "active" || !token) {
        return;
      }

      void (async () => {
        try {
          const refreshed = await refreshAuthToken(token);
          setToken(refreshed.token);
          setUser(refreshed.user);
          await saveSession(refreshed.token, refreshed.user);
        } catch {
          setToken(null);
          setUser(null);
          setRoute({ name: "welcome" });
          await clearSession();
        }
      })();
    });

    return () => subscription.remove();
  }, [token]);

  useEffect(() => {
    if (isExpoGo) {
      return;
    }

    const responseSubscription = Notifications.addNotificationResponseReceivedListener((response) => {
      const notificationType = response.notification.request.content.data?.type;
      if (notificationType === "space_invitation") {
        setRoute({ name: "dashboard" });
      }
    });

    return () => responseSubscription.remove();
  }, []);

  useEffect(() => {
    if (!token) {
      return;
    }

    let isMounted = true;

    void (async () => {
      try {
        const pushToken = await registerForPushNotificationsAsync();
        if (!pushToken || !isMounted) {
          return;
        }

        await registerDevicePushToken(token, pushToken, Platform.OS);
        if (isMounted) {
          setRegisteredPushToken(pushToken);
        }
      } catch {
        if (isMounted) {
          setRegisteredPushToken(null);
        }
      }
    })();

    return () => {
      isMounted = false;
    };
  }, [token]);

  if (bootstrapping) {
    return showSplash ? (
      <SafeAreaView style={styles.safeArea}>
        <SplashScreen />
      </SafeAreaView>
    ) : (
      <SafeAreaView style={styles.safeArea}>
        <View style={styles.centered}>
          <ActivityIndicator />
        </View>
      </SafeAreaView>
    );
  }

  if (route.name === "welcome" && showSplash) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <SplashScreen />
      </SafeAreaView>
    );
  }

  return (
    <NotificationProvider token={token}>
      <NetworkProvider token={token}>
      <SafeAreaView style={styles.safeArea}>
        <StatusBar hidden style={resolvedMode === "dark" ? "light" : "dark"} />
        <OfflineBanner />
        <NotificationBell
          visible={
            !!token &&
            route.name !== "welcome" &&
            route.name !== "login" &&
            route.name !== "notifications" &&
            route.name !== "spaces" &&
            route.name !== "dashboard" &&
            route.name !== "space" &&
            route.name !== "referee-login" &&
            route.name !== "referee-dashboard"
          }
          onPress={() => {
            setPreviousRoute(route);
            setRoute({ name: "notifications" });
          }}
        />
      {route.name === "notifications" && token ? (
        <NotificationsScreen onBack={() => setRoute(previousRoute)} />
      ) : null}

      {route.name === "welcome" ? (
        <WelcomeScreen
          onLoginPress={() => setRoute({ name: "login" })}
          onRefereePress={() => setRoute({ name: "referee-login" })}
        />
      ) : null}

      {route.name === "login" ? (
        <LoginScreen
          onBack={() => setRoute({ name: "welcome" })}
          onLoginSuccess={({ token: authToken, user: authUser }) => {
            setToken(authToken);
            setUser(authUser);
            setRoute({ name: "dashboard" });
            void saveSession(authToken, authUser);
          }}
        />
      ) : null}

      {route.name === "dashboard" && token && user ? (
        <DashboardScreen
          token={token}
          user={user}
          onOpenSpaces={goToSpaces}
          onOpenProfile={() => {
            setPreviousRoute({ name: "dashboard" });
            setRoute({ name: "profile" });
          }}
          onOpenNotifications={() => {
            setPreviousRoute({ name: "dashboard" });
            setRoute({ name: "notifications" });
          }}
          onOpenReferee={() => {
            setPreviousRoute({ name: "dashboard" });
            setRoute({ name: "referee-login" });
          }}
          onLogout={logout}
        />
      ) : null}

      {route.name === "spaces" && token && user ? (
        <SpacesScreen
          token={token}
          user={user}
          onSelectSpace={(space) => setRoute({ name: "space", space })}
          onLogout={logout}
          onOpenDashboard={goToDashboard}
          onOpenProfile={() => {
            setPreviousRoute({ name: "spaces" });
            setRoute({ name: "profile" });
          }}
          onOpenCreateSpace={() => setRoute({ name: "create-space" })}
          onOpenNotifications={() => {
            setPreviousRoute(route);
            setRoute({ name: "notifications" });
          }}
        />
      ) : null}

      {route.name === "create-space" && token ? (
        <CreateSpaceScreen
          token={token}
          onBack={() => setRoute({ name: "spaces" })}
          onCreated={() => setRoute({ name: "spaces" })}
        />
      ) : null}

      {route.name === "profile" && token && user ? (
        <ProfileScreen
          token={token}
          fallbackUser={user}
          onBack={() => setRoute(previousRoute)}
          themePreference={preference}
          resolvedTheme={resolvedMode}
          onThemePreferenceChange={setPreference}
        />
      ) : null}

      {route.name === "space" && token && user ? (
        <SpaceScreen
          token={token}
          user={user}
          space={route.space}
          onBack={goToSpaces}
          onOpenProfile={() => {
            setPreviousRoute({ name: "space", space: route.space });
            setRoute({ name: "profile" });
          }}
          onOpenNotifications={() => {
            setPreviousRoute({ name: "space", space: route.space });
            setRoute({ name: "notifications" });
          }}
          onOpenGame={(gameId) => setRoute({ name: "game", space: route.space, gameId })}
          onOpenCompetition={(competitionId) =>
            setRoute({ name: "competition", space: route.space, competitionId })
          }
          onOpenCreatePlayer={() => setRoute({ name: "create-player", space: route.space })}
          onOpenCreateGameType={() => setRoute({ name: "create-game-type", space: route.space })}
        />
      ) : null}

      {route.name === "create-player" && token ? (
        <CreatePlayerScreen
          token={token}
          space={route.space}
          onBack={() => setRoute({ name: "space", space: route.space })}
          onCreated={() => setRoute({ name: "space", space: route.space })}
        />
      ) : null}

      {route.name === "create-game-type" && token ? (
        <CreateGameTypeScreen
          token={token}
          space={route.space}
          onBack={() => setRoute({ name: "space", space: route.space })}
          onCreated={() => setRoute({ name: "space", space: route.space })}
        />
      ) : null}

      {route.name === "game" && token && user ? (
        <GameDetailScreen
          token={token}
          user={user}
          space={route.space}
          gameId={route.gameId}
          onBack={() => setRoute({ name: "space", space: route.space })}
        />
      ) : null}

      {route.name === "competition" && token ? (
        <CompetitionDetailScreen
          token={token}
          space={route.space}
          competitionId={route.competitionId}
          onBack={() => setRoute({ name: "space", space: route.space })}
          onOpenReferee={(competitionId) =>
            setRoute({ name: "referee-login", space: route.space, competitionId })
          }
        />
      ) : null}

      {route.name === "referee-login" ? (
        <RefereeLoginScreen
          token={token}
          initialCompetitionId={route.competitionId}
          onLogin={(refereeToken, session) =>
            setRoute({ name: "referee-dashboard", refereeToken, session, space: route.space })
          }
          onBack={() =>
            route.space
              ? setRoute({ name: "space", space: route.space })
              : setRoute(previousRoute)
          }
        />
      ) : null}

      {route.name === "referee-dashboard" ? (
        <RefereeDashboardScreen
          refereeToken={route.refereeToken}
          session={route.session}
          onClose={() =>
            route.space
              ? setRoute({ name: "space", space: route.space })
              : setRoute(previousRoute)
          }
        />
      ) : null}
    </SafeAreaView>
    </NetworkProvider>
    </NotificationProvider>
  );
}

/** Bannière hors connexion avec compteur d'actions en attente */
function OfflineBanner() {
  const { theme } = useAppTheme();
  const { isOnline, queueLength, isSyncing } = useNetwork();

  // En ligne, sync terminée, file vide → rien à afficher
  if (isOnline && !isSyncing && queueLength === 0) return null;

  let message: string;
  let bgColor: string;

  if (!isOnline) {
    bgColor = theme.colors.warning;
    message = queueLength > 0
      ? `Hors connexion · ${queueLength} action${queueLength > 1 ? "s" : ""} en attente`
      : "Hors connexion";
  } else if (isSyncing) {
    bgColor = theme.colors.success;
    message = `Reconnecté — synchronisation en cours…`;
  } else {
    // En ligne, sync terminée, mais file non vide → les actions ont échoué
    bgColor = theme.colors.warning;
    message = `${queueLength} action${queueLength > 1 ? "s" : ""} en attente · nouvelle tentative dans 30s`;
  }

  return (
    <View style={{ backgroundColor: bgColor, paddingVertical: 4, paddingHorizontal: 12, alignItems: "center" }}>
      <Text style={{ color: "#000", fontSize: 12, fontWeight: "600" }}>
        {message}
      </Text>
    </View>
  );
}

/** Bouton cloche flottant avec badge non-lu */
function NotificationBell({ onPress, visible }: { onPress: () => void; visible: boolean }) {
  const { theme } = useAppTheme();
  const { unreadCount } = useNotifications();

  if (!visible) return null;

  return (
    <Pressable
      onPress={onPress}
      style={{
        position: "absolute",
        top: 14,
        right: 14,
        zIndex: 50,
        width: 38,
        height: 38,
        borderRadius: 19,
        backgroundColor: theme.colors.card,
        borderWidth: 1,
        borderColor: theme.colors.border,
        alignItems: "center",
        justifyContent: "center",
        ...theme.shadow.card,
      }}
    >
      <Text style={{ fontSize: 18 }}>🔔</Text>
      {unreadCount > 0 ? (
        <View
          style={{
            position: "absolute",
            top: 2,
            right: 2,
            minWidth: 16,
            height: 16,
            borderRadius: 8,
            backgroundColor: theme.colors.danger,
            alignItems: "center",
            justifyContent: "center",
            paddingHorizontal: 3,
          }}
        >
          <Text
            style={{
              color: "#fff",
              fontSize: 9,
              fontWeight: "800",
              lineHeight: 16,
            }}
          >
            {unreadCount > 99 ? "99+" : String(unreadCount)}
          </Text>
        </View>
      ) : null}
    </Pressable>
  );
}

export function MobileApp() {
  return (
    <ThemeProvider>
      <MobileAppContent />
    </ThemeProvider>
  );
}

const createStyles = (theme: ReturnType<typeof useAppTheme>["theme"]) =>
  StyleSheet.create({
    safeArea: {
      flex: 1,
      backgroundColor: theme.colors.background,
    },
    centered: {
      flex: 1,
      alignItems: "center",
      justifyContent: "center",
    },
  });
