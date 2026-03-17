import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Image,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { ApiError, fetchSpaces } from "../services/api";
import { theme } from "../styles/theme";
import type { Space, User } from "../types/api";
import { getAvatarUri, getInitials } from "../utils/avatar";
import { getRoleLabel } from "../utils/roles";

type Props = {
  token: string;
  user: User;
  onSelectSpace: (space: Space) => void;
  onLogout: () => void;
  onOpenProfile: () => void;
};

export function SpacesScreen({ token, user, onSelectSpace, onLogout, onOpenProfile }: Props) {
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

  const avatarUri = getAvatarUri(user.avatar);

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
          <Text style={styles.title}>Vos espaces</Text>
          <Text style={styles.subtitle}>{user.username}</Text>
        </View>
        <View style={styles.headerActions}>
          <Pressable style={styles.profileButton} onPress={onOpenProfile}>
            {avatarUri ? (
              <Image source={{ uri: avatarUri }} style={styles.profileAvatar} />
            ) : (
              <Text style={styles.profileAvatarText}>{getInitials(user)}</Text>
            )}
          </Pressable>
          <Pressable onPress={onLogout}>
            <Text style={styles.logout}>Deconnexion</Text>
          </Pressable>
        </View>
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
  subtitle: {
    color: theme.colors.mutedText,
    marginTop: 2,
  },
  logout: {
    color: theme.colors.primary,
    fontWeight: "700",
  },
  headerActions: {
    alignItems: "flex-end",
    gap: 8,
  },
  profileButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: theme.colors.primarySoft,
    alignItems: "center",
    justifyContent: "center",
    overflow: "hidden",
  },
  profileAvatar: {
    width: "100%",
    height: "100%",
  },
  profileAvatarText: {
    color: theme.colors.primary,
    fontWeight: "700",
    fontSize: 12,
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
