import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { ApiError, fetchSpaces } from "../services/api";
import { theme } from "../styles/theme";
import type { Space, User } from "../types/api";
import { getRoleLabel } from "../utils/roles";

type Props = {
  token: string;
  user: User;
  onSelectSpace: (space: Space) => void;
  onLogout: () => void;
  onBack: () => void;
};

export function SpacesScreen({ token, user, onSelectSpace, onLogout, onBack }: Props) {
  const [spaces, setSpaces] = useState<Space[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadSpaces = useCallback(async () => {
    try {
      setError(null);
      const data = await fetchSpaces(token);
      setSpaces(data);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de charger vos espaces.");
      }
    }
  }, [token]);

  useEffect(() => {
    const run = async () => {
      setLoading(true);
      await loadSpaces();
      setLoading(false);
    };
    void run();
  }, [loadSpaces]);

  const onRefresh = async () => {
    setRefreshing(true);
    await loadSpaces();
    setRefreshing(false);
  };

  if (loading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <View>
          <Pressable onPress={onBack}>
            <Text style={styles.back}>Retour</Text>
          </Pressable>
          <Text style={styles.title}>Vos espaces</Text>
          <Text style={styles.subtitle}>{user.username}</Text>
        </View>
        <Pressable onPress={onLogout}>
          <Text style={styles.logout}>Deconnexion</Text>
        </Pressable>
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <FlatList
        data={spaces}
        keyExtractor={(item) => String(item.id)}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
        ListEmptyComponent={<Text style={styles.empty}>Aucun espace disponible.</Text>}
        renderItem={({ item }) => (
          <Pressable style={styles.card} onPress={() => onSelectSpace(item)}>
            <View style={styles.cardHeader}>
              <Text style={styles.spaceName}>{item.name}</Text>
              {item.user_role ? (
                <View style={styles.roleBadge}>
                  <Text style={styles.roleBadgeText}>{getRoleLabel(item.user_role)}</Text>
                </View>
              ) : null}
            </View>
            {item.description ? <Text style={styles.description}>{item.description}</Text> : null}
          </Pressable>
        )}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  centered: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: theme.colors.background,
  },
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
    padding: 16,
  },
  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 14,
  },
  title: {
    fontSize: 24,
    fontWeight: "700",
    color: theme.colors.text,
  },
  back: {
    color: theme.colors.primary,
    fontWeight: "700",
    marginBottom: 6,
  },
  subtitle: {
    color: theme.colors.mutedText,
    marginTop: 2,
  },
  logout: {
    color: theme.colors.primary,
    fontWeight: "700",
  },
  error: {
    color: theme.colors.danger,
    marginBottom: 8,
  },
  empty: {
    color: theme.colors.mutedText,
    marginTop: 20,
    textAlign: "center",
  },
  card: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: 14,
    marginBottom: 10,
  },
  cardHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
  },
  spaceName: {
    color: theme.colors.text,
    fontSize: 17,
    fontWeight: "700",
    flexShrink: 1,
  },
  roleBadge: {
    backgroundColor: theme.colors.primarySoft,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 4,
    marginLeft: 8,
  },
  roleBadgeText: {
    color: theme.colors.primary,
    fontWeight: "700",
    fontSize: 12,
  },
  description: {
    marginTop: 4,
    color: theme.colors.mutedText,
  },
});
