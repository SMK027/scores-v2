import { useEffect, useMemo, useState } from "react";
import { AppState, StyleSheet, View, ActivityIndicator, Platform } from "react-native";
import * as Notifications from "expo-notifications";
import Constants from "expo-constants";
import { StatusBar } from "expo-status-bar";
import * as NavigationBar from "expo-navigation-bar";
import { SafeAreaView } from "react-native-safe-area-context";
import { refreshAuthToken, registerDevicePushToken, unregisterDevicePushToken } from "./services/api";
import { registerForPushNotificationsAsync } from "./services/pushNotifications";
import { clearSession, loadSession, saveSession } from "./services/session";
import { GameDetailScreen } from "./screens/GameDetailScreen";
import { CompetitionDetailScreen } from "./screens/CompetitionDetailScreen";
import { CreateGameTypeScreen } from "./screens/CreateGameTypeScreen";
import { CreateSpaceScreen } from "./screens/CreateSpaceScreen";
import { CreatePlayerScreen } from "./screens/CreatePlayerScreen";
import { LoginScreen } from "./screens/LoginScreen";
import { ProfileScreen } from "./screens/ProfileScreen";
import { RefereeLoginScreen } from "./screens/RefereeLoginScreen";
import { RefereeDashboardScreen } from "./screens/RefereeDashboardScreen";
import { SplashScreen } from "./screens/SplashScreen";
import { SpaceScreen } from "./screens/SpaceScreen";
import { SpacesScreen } from "./screens/SpacesScreen";
import { WelcomeScreen } from "./screens/WelcomeScreen";
import { ThemeProvider, useAppTheme } from "./context/ThemeContext";
import type { RefereeSession, Space, User } from "./types/api";

type Route =
  | { name: "welcome" }
  | { name: "login" }
  | { name: "spaces" }
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
  const [previousRoute, setPreviousRoute] = useState<Route>({ name: "spaces" });
  const [bootstrapping, setBootstrapping] = useState(true);
  const [showSplash, setShowSplash] = useState(true);
  const [registeredPushToken, setRegisteredPushToken] = useState<string | null>(null);

  const goToSpaces = () => setRoute({ name: "spaces" });

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

        const refreshed = await refreshAuthToken(persisted.token);
        setToken(refreshed.token);
        setUser(refreshed.user);
        setRoute({ name: "spaces" });
        await saveSession(refreshed.token, refreshed.user);
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
        setRoute({ name: "spaces" });
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
    <SafeAreaView style={styles.safeArea}>
      <StatusBar hidden style={resolvedMode === "dark" ? "light" : "dark"} />
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
            setRoute({ name: "spaces" });
            void saveSession(authToken, authUser);
          }}
        />
      ) : null}

      {route.name === "spaces" && token && user ? (
        <SpacesScreen
          token={token}
          user={user}
          onSelectSpace={(space) => setRoute({ name: "space", space })}
          onLogout={logout}
          onOpenProfile={() => {
            setPreviousRoute({ name: "spaces" });
            setRoute({ name: "profile" });
          }}
          onOpenCreateSpace={() => setRoute({ name: "create-space" })}
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
              : setRoute({ name: "welcome" })
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
              : setRoute({ name: "welcome" })
          }
        />
      ) : null}
    </SafeAreaView>
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
