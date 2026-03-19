import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Image,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { ApiError, createSpace, fetchSpaces } from "../services/api";
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
  const [showCreatePanel, setShowCreatePanel] = useState(false);
  const [newSpaceName, setNewSpaceName] = useState("");
  const [newSpaceDescription, setNewSpaceDescription] = useState("");
  const [creatingSpace, setCreatingSpace] = useState(false);

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

  const submitCreateSpace = async () => {
    const name = newSpaceName.trim();
    if (!name) {
      setError("Le nom de l'espace est requis.");
      return;
    }

    try {
      setCreatingSpace(true);
      setError(null);
      await createSpace(token, {
        name,
        description: newSpaceDescription.trim() || undefined,
      });
      setNewSpaceName("");
      setNewSpaceDescription("");
      setShowCreatePanel(false);
      await loadSpaces();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de creer un espace.");
      }
    } finally {
      setCreatingSpace(false);
    }
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
          <Text style={styles.title}>Mes espaces</Text>
          <Text style={styles.subtitle}>Gerez vos salons de jeu, {user.username}</Text>
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
        placeholder="Rechercher un espace..."
        style={styles.searchInput}
        autoCorrect={false}
      />

      {showCreatePanel ? (
        <ScrollView style={styles.createPanel} keyboardShouldPersistTaps="handled">
          <Text style={styles.createPanelTitle}>Creer un espace</Text>
          <TextInput
            value={newSpaceName}
            onChangeText={setNewSpaceName}
            placeholder="Nom de l'espace"
            style={styles.createInput}
          />
          <TextInput
            value={newSpaceDescription}
            onChangeText={setNewSpaceDescription}
            placeholder="Description (optionnelle)"
            style={[styles.createInput, styles.createNotes]}
            multiline
          />
          <View style={styles.createActions}>
            <Pressable
              style={[styles.createPrimary, creatingSpace ? styles.disabled : undefined]}
              disabled={creatingSpace}
              onPress={submitCreateSpace}
            >
              <Text style={styles.createPrimaryText}>{creatingSpace ? "Creation..." : "Creer"}</Text>
            </Pressable>
            <Pressable style={styles.createGhost} onPress={() => setShowCreatePanel(false)}>
              <Text style={styles.createGhostText}>Annuler</Text>
            </Pressable>
          </View>
        </ScrollView>
      ) : null}

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <FlatList
        data={filteredSpaces}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={styles.listContent}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
        ListEmptyComponent={
          <View style={styles.emptyWrap}>
            <Text style={styles.emptyTitle}>Aucun espace</Text>
            <Text style={styles.emptyText}>
              {searchQuery.trim()
                ? "Aucun espace ne correspond a votre recherche."
                : "Commencez par creer votre premier espace de jeu."}
            </Text>
            {!searchQuery.trim() ? (
              <Pressable style={styles.emptyButton} onPress={() => setShowCreatePanel(true)}>
                <Text style={styles.emptyButtonText}>Creer mon premier espace</Text>
              </Pressable>
            ) : null}
          </View>
        }
        renderItem={({ item }) => (
          <Pressable style={styles.card} onPress={() => onSelectSpace(item)}>
            <View style={styles.cardHeader}>
              <View style={styles.cardHeaderLeft}>
                <View style={styles.spaceAvatarCircle}>
                  <Text style={styles.spaceAvatarText}>{item.name.slice(0, 2).toUpperCase()}</Text>
                </View>
                <View style={styles.cardHeaderTextWrap}>
                  <Text style={styles.spaceName}>{item.name}</Text>
                  {item.user_role ? (
                    <View style={styles.roleBadge}>
                      <Text style={styles.roleBadgeText}>{getRoleLabel(item.user_role)}</Text>
                    </View>
                  ) : null}
                </View>
              </View>

              <Pressable onPress={() => {}}>
                <Text style={styles.contextMenuIcon}>⋮</Text>
              </Pressable>
            </View>
            {item.description ? <Text style={styles.description}>{item.description}</Text> : null}
            <View style={styles.cardFooter}>
              <View style={styles.statusBadgeActive}>
                <Text style={styles.statusBadgeActiveText}>Actif</Text>
              </View>
              <View style={styles.gamesBadge}>
                <Text style={styles.gamesBadgeText}>Parties en cours: {item.games_count ?? 0}</Text>
              </View>
            </View>
          </Pressable>
        )}
      />

      <Pressable style={styles.fab} onPress={() => setShowCreatePanel((current) => !current)}>
        <Text style={styles.fabIcon}>+</Text>
      </Pressable>
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
  createPanel: {
    maxHeight: 240,
    backgroundColor: theme.colors.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.lg,
    padding: 12,
    marginBottom: 10,
  },
  createPanelTitle: {
    color: theme.colors.text,
    fontWeight: "700",
    fontSize: 16,
    marginBottom: 8,
  },
  createInput: {
    backgroundColor: theme.colors.backgroundSoft,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    paddingHorizontal: 12,
    paddingVertical: 10,
    marginBottom: 8,
  },
  createNotes: {
    minHeight: 70,
    textAlignVertical: "top",
  },
  createActions: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
  },
  createPrimary: {
    flex: 1,
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    paddingVertical: 11,
    alignItems: "center",
  },
  createPrimaryText: {
    color: "#ffffff",
    fontWeight: "700",
  },
  createGhost: {
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  createGhostText: {
    color: theme.colors.mutedText,
    fontWeight: "700",
  },
  disabled: {
    opacity: 0.6,
  },
  listContent: {
    paddingBottom: 90,
  },
  emptyWrap: {
    marginTop: 28,
    alignItems: "center",
    gap: 8,
  },
  emptyTitle: {
    color: theme.colors.text,
    fontWeight: "700",
    fontSize: 18,
  },
  emptyText: {
    color: theme.colors.mutedText,
    maxWidth: 260,
    textAlign: "center",
  },
  emptyButton: {
    marginTop: 6,
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  emptyButtonText: {
    color: "#ffffff",
    fontWeight: "700",
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
  cardHeaderLeft: {
    flexDirection: "row",
    alignItems: "center",
    flex: 1,
    marginRight: 10,
  },
  cardHeaderTextWrap: {
    flex: 1,
  },
  spaceAvatarCircle: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: theme.colors.primarySoft,
    alignItems: "center",
    justifyContent: "center",
    marginRight: 10,
  },
  spaceAvatarText: {
    color: theme.colors.primary,
    fontWeight: "800",
  },
  contextMenuIcon: {
    color: theme.colors.mutedText,
    fontSize: 20,
    fontWeight: "700",
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
    marginTop: 8,
    color: theme.colors.mutedText,
  },
  cardFooter: {
    marginTop: 10,
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
  },
  statusBadgeActive: {
    backgroundColor: "#e8f9ef",
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 5,
  },
  statusBadgeActiveText: {
    color: "#087443",
    fontWeight: "700",
    fontSize: 12,
  },
  gamesBadge: {
    backgroundColor: theme.colors.primarySoft,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 5,
  },
  gamesBadgeText: {
    color: theme.colors.primary,
    fontWeight: "700",
    fontSize: 12,
  },
  fab: {
    position: "absolute",
    right: 16,
    bottom: 20,
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: theme.colors.primary,
    alignItems: "center",
    justifyContent: "center",
    ...theme.shadow.card,
  },
  fabIcon: {
    color: "#ffffff",
    fontSize: 30,
    fontWeight: "400",
    marginTop: -2,
  },
});
