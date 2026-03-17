import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Image,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
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
  const [searchQuery, setSearchQuery] = useState("");
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

  const filteredSpaces = useMemo(() => {
    const query = searchQuery.trim().toLowerCase();
    if (!query) {
      return spaces;
    }
    return spaces.filter((space) => space.name.toLowerCase().includes(query));
  }, [searchQuery, spaces]);

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
        <View style={styles.headerMain}>
          <Text style={styles.kicker}>Espace personnel</Text>
          <Text style={styles.title}>Vos espaces</Text>
          <Text style={styles.subtitle}>Bonjour {user.username}</Text>
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

      <TextInput
        value={searchQuery}
        onChangeText={setSearchQuery}
        placeholder="Filtrer les espaces par nom"
        style={styles.searchInput}
        autoCorrect={false}
      />

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <FlatList
        data={filteredSpaces}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={styles.listContent}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
        ListEmptyComponent={
          <Text style={styles.empty}>
            {searchQuery.trim() ? "Aucun espace ne correspond a ce filtre." : "Aucun espace disponible."}
          </Text>
        }
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
    alignItems: "flex-start",
    marginBottom: 12,
  },
  headerMain: {
    flex: 1,
    marginRight: 10,
  },
  kicker: {
    color: theme.colors.primary,
    fontWeight: "700",
    fontSize: 12,
    marginBottom: 3,
  },
  title: {
    fontSize: 30,
    fontWeight: "800",
    color: theme.colors.text,
  },
  subtitle: {
    color: theme.colors.mutedText,
    marginTop: 4,
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
    width: 40,
    height: 40,
    borderRadius: 20,
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
  searchInput: {
    backgroundColor: theme.colors.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    paddingHorizontal: 12,
    paddingVertical: 10,
    marginBottom: 10,
  },
  listContent: {
    paddingBottom: 18,
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
    padding: 15,
    marginBottom: 10,
    ...theme.shadow.card,
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
