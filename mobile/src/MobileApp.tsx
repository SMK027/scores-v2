import { useEffect, useState } from "react";
import { AppState, SafeAreaView, StyleSheet, View, ActivityIndicator } from "react-native";
import { refreshAuthToken } from "./services/api";
import { clearSession, loadSession, saveSession } from "./services/session";
import { GameDetailScreen } from "./screens/GameDetailScreen";
import { LoginScreen } from "./screens/LoginScreen";
import { ProfileScreen } from "./screens/ProfileScreen";
import { SplashScreen } from "./screens/SplashScreen";
import { SpaceScreen } from "./screens/SpaceScreen";
import { SpacesScreen } from "./screens/SpacesScreen";
import { WelcomeScreen } from "./screens/WelcomeScreen";
import { theme } from "./styles/theme";
import type { Space, User } from "./types/api";

type Route =
  | { name: "welcome" }
  | { name: "login" }
  | { name: "spaces" }
  | { name: "profile" }
  | { name: "space"; space: Space }
  | { name: "game"; space: Space; gameId: number };

export function MobileApp() {
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<User | null>(null);
  const [route, setRoute] = useState<Route>({ name: "welcome" });
  const [previousRoute, setPreviousRoute] = useState<Route>({ name: "spaces" });
  const [bootstrapping, setBootstrapping] = useState(true);
  const [showSplash, setShowSplash] = useState(true);

  const goToSpaces = () => setRoute({ name: "spaces" });

  const logout = () => {
    setToken(null);
    setUser(null);
    setRoute({ name: "welcome" });
    void clearSession();
  };

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
      {route.name === "welcome" ? <WelcomeScreen onLoginPress={() => setRoute({ name: "login" })} /> : null}

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
        />
      ) : null}

      {route.name === "profile" && token && user ? (
        <ProfileScreen token={token} fallbackUser={user} onBack={() => setRoute(previousRoute)} />
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
        />
      ) : null}

      {route.name === "game" && token ? (
        <GameDetailScreen
          token={token}
          space={route.space}
          gameId={route.gameId}
          onBack={() => setRoute({ name: "space", space: route.space })}
        />
      ) : null}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
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
