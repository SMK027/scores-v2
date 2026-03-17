import { useState } from "react";
import { SafeAreaView, StyleSheet } from "react-native";
import { GameDetailScreen } from "./screens/GameDetailScreen";
import { LoginScreen } from "./screens/LoginScreen";
import { ProfileScreen } from "./screens/ProfileScreen";
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

  const goToSpaces = () => setRoute({ name: "spaces" });

  const logout = () => {
    setToken(null);
    setUser(null);
    setRoute({ name: "welcome" });
  };

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
});
