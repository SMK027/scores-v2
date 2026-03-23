import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Image,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useAppTheme } from "../context/ThemeContext";
import type { AppTheme } from "../styles";
import { ApiError, deleteSpace, leaveSpace, fetchSpaces, acceptInvitation, declineInvitation } from "../services/api";
import type { Space, User, Invitation } from "../types/api";
import { getAvatarUri, getInitials } from "../utils/avatar";
import { getRoleLabel } from "../utils/roles";

type Props = {
  token: string;
  user: User;
  onSelectSpace: (space: Space) => void;
  onLogout: () => void;
  onOpenProfile: () => void;
  onOpenCreateSpace: () => void;
};

export function SpacesScreen({ token, user, onSelectSpace, onLogout, onOpenProfile, onOpenCreateSpace }: Props) {
  const { theme } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);

  const [spaces, setSpaces] = useState<Space[]>([]);
  const [searchQuery, setSearchQuery] = useState("");
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [deletingSpaceId, setDeletingSpaceId] = useState<number | null>(null);
  const [leavingSpaceId, setLeavingSpaceId] = useState<number | null>(null);
  const [invitations, setInvitations] = useState<Invitation[]>([]);
  const [respondingInvitationId, setRespondingInvitationId] = useState<number | null>(null);

  const loadSpaces = useCallback(async () => {
    try {
      setError(null);
      const response = await fetchSpaces(token);
      setSpaces(response.spaces);
      setInvitations(response.pending_invitations || []);
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

  const handleAcceptInvitation = useCallback(async (invitation: Invitation) => {
    try {
      setRespondingInvitationId(invitation.id);
      setError(null);
      await acceptInvitation(token, invitation.id);
      await loadSpaces();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible d'accepter l'invitation.");
      }
    } finally {
      setRespondingInvitationId(null);
    }
  }, [loadSpaces, token]);

  const handleDeclineInvitation = useCallback((invitation: Invitation) => {
    Alert.alert(
      "Refuser l'invitation ?",
      `Êtes-vous sûr de vouloir refuser l'invitation pour "${invitation.space_name}" ?`,
      [
        { text: "Annuler", style: "cancel" },
        {
          text: "Refuser",
          style: "destructive",
          onPress: async () => {
            try {
              setRespondingInvitationId(invitation.id);
              setError(null);
              await declineInvitation(token, invitation.id);
              await loadSpaces();
            } catch (err) {
              if (err instanceof ApiError) {
                setError(err.message);
              } else {
                setError("Impossible de refuser l'invitation.");
              }
            } finally {
              setRespondingInvitationId(null);
            }
          },
        },
      ]
    );
  }, [loadSpaces, token]);

  const confirmAndDeleteSpace = (space: Space) => {
    if (space.created_by !== user.id) {
      setError("Seul le propriétaire peut supprimer cet espace.");
      return;
    }

    Alert.alert(
      "Supprimer cet espace ?",
      `Cette action supprimera définitivement \"${space.name}\" et toutes ses données.`,
      [
        { text: "Annuler", style: "cancel" },
        {
          text: "Supprimer",
          style: "destructive",
          onPress: async () => {
            try {
              setDeletingSpaceId(space.id);
              setError(null);
              await deleteSpace(token, space.id);
              await loadSpaces();
            } catch (err) {
              if (err instanceof ApiError) {
                setError(err.message);
              } else {
                setError("Impossible de supprimer cet espace.");
              }
            } finally {
              setDeletingSpaceId(null);
            }
          },
        },
      ]
    );
  };

  const confirmAndLeaveSpace = (space: Space) => {
    Alert.alert(
      "Quitter cet espace ?",
      `Êtes-vous sûr de vouloir quitter \"${space.name}\" ?`,
      [
        { text: "Annuler", style: "cancel" },
        {
          text: "Quitter",
          style: "destructive",
          onPress: async () => {
            try {
              setLeavingSpaceId(space.id);
              setError(null);
              await leaveSpace(token, space.id);
              await loadSpaces();
            } catch (err) {
              if (err instanceof ApiError) {
                setError(err.message);
              } else {
                setError("Impossible de quitter cet espace.");
              }
            } finally {
              setLeavingSpaceId(null);
            }
          },
        },
      ]
    );
  };

  const avatarUri = getAvatarUri(user.avatar);

  const confirmLogout = useCallback(() => {
    Alert.alert("Se déconnecter ?", "Voulez-vous vraiment vous déconnecter de l'application ?", [
      { text: "Annuler", style: "cancel" },
      { text: "Déconnexion", style: "destructive", onPress: onLogout },
    ]);
  }, [onLogout]);

  const filteredSpaces = useMemo(() => {
    const query = searchQuery.trim().toLowerCase();
    if (!query) {
      return spaces;
    }
    return spaces.filter((space) => space.name.toLowerCase().includes(query));
  }, [searchQuery, spaces]);

  const invitationsHeader = useMemo(() => {
    if (invitations.length === 0) {
      return null;
    }

    return (
      <View style={styles.invitationsPanel}>
        <Text style={styles.invitationsPanelTitle}>Invitations en attente ({invitations.length})</Text>
        {invitations.map((invitation) => (
          <View key={invitation.id} style={styles.invitationCard}>
            <View style={styles.invitationContent}>
              <Text style={styles.invitationSpaceName}>{invitation.space_name}</Text>
              <Text style={styles.invitationMeta}>Invité par {invitation.invited_by_name}</Text>
            </View>
            <View style={styles.invitationActions}>
              <Pressable
                style={[
                  styles.invitationAcceptButton,
                  respondingInvitationId === invitation.id ? styles.disabled : undefined,
                ]}
                disabled={respondingInvitationId === invitation.id}
                onPress={() => handleAcceptInvitation(invitation)}
              >
                <Text style={styles.invitationAcceptButtonText}>
                  {respondingInvitationId === invitation.id ? "..." : "Accepter"}
                </Text>
              </Pressable>
              <Pressable
                style={[
                  styles.invitationDeclineButton,
                  respondingInvitationId === invitation.id ? styles.disabled : undefined,
                ]}
                disabled={respondingInvitationId === invitation.id}
                onPress={() => handleDeclineInvitation(invitation)}
              >
                <Text style={styles.invitationDeclineButtonText}>
                  {respondingInvitationId === invitation.id ? "..." : "Refuser"}
                </Text>
              </Pressable>
            </View>
          </View>
        ))}
      </View>
    );
  }, [handleAcceptInvitation, handleDeclineInvitation, invitations, respondingInvitationId]);

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
          <Text style={styles.subtitle}>Gérez vos salons de jeu, {user.username}</Text>
        </View>
        <View style={styles.headerActions}>
          <Pressable style={styles.profileButton} onPress={onOpenProfile}>
            {avatarUri ? (
              <Image source={{ uri: avatarUri }} style={styles.profileAvatar} />
            ) : (
              <Text style={styles.profileAvatarText}>{getInitials(user)}</Text>
            )}
          </Pressable>
          <Pressable style={styles.logoutButton} onPress={confirmLogout}>
            <Text style={styles.logoutButtonText}>Déconnexion</Text>
          </Pressable>
        </View>
      </View>

      <View style={styles.controlsPanel}>
        <TextInput
          value={searchQuery}
          onChangeText={setSearchQuery}
          placeholder="Rechercher un espace..."
          placeholderTextColor={theme.colors.mutedText}
          style={styles.searchInput}
          autoCorrect={false}
        />
        <View style={styles.controlsFooter}>
          <Text style={styles.resultsCount}>
            {filteredSpaces.length} espace{filteredSpaces.length > 1 ? "s" : ""}
          </Text>
          <Pressable style={styles.inlineCreateButton} onPress={onOpenCreateSpace}>
            <Text style={styles.inlineCreateButtonText}>Créer un espace</Text>
          </Pressable>
        </View>
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <View style={styles.listHeaderRow}>
        <Text style={styles.listHeaderTitle}>Espaces disponibles</Text>
      </View>

      <FlatList
        data={filteredSpaces}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={styles.listContent}
        extraData={{ deletingSpaceId, leavingSpaceId, respondingInvitationId }}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
        ListHeaderComponent={invitationsHeader}
        ListEmptyComponent={
          <View style={styles.emptyWrap}>
            <Text style={styles.emptyTitle}>Aucun espace</Text>
            <Text style={styles.emptyText}>
              {searchQuery.trim()
                ? "Aucun espace ne correspond a votre recherche."
                : "Commencez par créer votre premier espace de jeu."}
            </Text>
            {!searchQuery.trim() ? (
              <Pressable style={styles.emptyButton} onPress={onOpenCreateSpace}>
                <Text style={styles.emptyButtonText}>Créer mon premier espace</Text>
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

              <View style={styles.cardHeaderActions}>
                {item.created_by === user.id ? (
                  <Pressable
                    style={[styles.deleteSpaceButton, deletingSpaceId === item.id ? styles.disabled : undefined]}
                    disabled={deletingSpaceId === item.id}
                    onPress={(event) => {
                      event.stopPropagation();
                      confirmAndDeleteSpace(item);
                    }}
                  >
                    <Text style={styles.deleteSpaceText}>
                      {deletingSpaceId === item.id ? "Suppression..." : "Supprimer"}
                    </Text>
                  </Pressable>
                ) : (
                  <Pressable
                    style={[styles.leaveSpaceButton, leavingSpaceId === item.id ? styles.disabled : undefined]}
                    disabled={leavingSpaceId === item.id}
                    onPress={(event) => {
                      event.stopPropagation();
                      confirmAndLeaveSpace(item);
                    }}
                  >
                    <Text style={styles.leaveSpaceText}>
                      {leavingSpaceId === item.id ? "Départ..." : "Quitter"}
                    </Text>
                  </Pressable>
                )}
              </View>
            </View>
            {item.description ? <Text style={styles.description}>{item.description}</Text> : null}
            <View style={styles.cardFooter}>
              <View style={styles.gamesBadge}>
                <Text style={styles.gamesBadgeText}>Parties en cours: {item.games_count ?? 0}</Text>
              </View>
            </View>
          </Pressable>
        )}
      />

      <Pressable style={styles.fab} onPress={onOpenCreateSpace}>
        <Text style={styles.fabIcon}>+</Text>
      </Pressable>
    </View>
  );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
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
  logoutButton: {
    borderWidth: 1,
    borderColor: theme.colors.danger,
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.pill,
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  logoutButtonText: {
    color: theme.colors.danger,
    fontWeight: "700",
    fontSize: 12,
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
  controlsPanel: {
    backgroundColor: theme.colors.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.lg,
    padding: 10,
    marginBottom: 10,
  },
  searchInput: {
    backgroundColor: theme.colors.backgroundSoft,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    paddingHorizontal: 12,
    paddingVertical: 10,
    color: theme.colors.text,
  },
  controlsFooter: {
    marginTop: 8,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  resultsCount: {
    color: theme.colors.mutedText,
    fontWeight: "600",
  },
  inlineCreateButton: {
    backgroundColor: theme.colors.primarySoft,
    borderRadius: theme.radius.md,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  inlineCreateButtonText: {
    color: theme.colors.primary,
    fontWeight: "700",
    fontSize: 12,
  },
  listHeaderRow: {
    marginBottom: 8,
  },
  listHeaderTitle: {
    color: theme.colors.text,
    fontWeight: "700",
    fontSize: 16,
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
  cardHeaderActions: {
    gap: 8,
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
  deleteSpaceButton: {
    borderWidth: 1,
    borderColor: "#f2c3c3",
    backgroundColor: "#fff3f3",
    borderRadius: theme.radius.md,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  deleteSpaceText: {
    color: theme.colors.danger,
    fontWeight: "700",
    fontSize: 12,
  },
  leaveSpaceButton: {
    borderWidth: 1,
    borderColor: "#f4d5a3",
    backgroundColor: "#fffaf0",
    borderRadius: theme.radius.md,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  leaveSpaceText: {
    color: "#d98d1a",
    fontWeight: "700",
    fontSize: 12,
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
  invitationsPanel: {
    backgroundColor: theme.colors.card,
    borderWidth: 1,
    borderColor: "#e8d5b7",
    borderRadius: theme.radius.lg,
    padding: 12,
    marginBottom: 12,
  },
  invitationsPanelTitle: {
    color: theme.colors.text,
    fontWeight: "700",
    fontSize: 14,
    marginBottom: 12,
  },
  invitationCard: {
    backgroundColor: "#fffaf0",
    borderWidth: 1,
    borderColor: "#f4d5a3",
    borderRadius: theme.radius.md,
    padding: 10,
    marginBottom: 10,
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
  },
  invitationContent: {
    flex: 1,
    marginRight: 10,
  },
  invitationSpaceName: {
    color: theme.colors.text,
    fontWeight: "700",
    fontSize: 14,
    marginBottom: 2,
  },
  invitationMeta: {
    color: theme.colors.mutedText,
    fontSize: 11,
  },
  invitationActions: {
    flexDirection: "row",
    gap: 6,
  },
  invitationAcceptButton: {
    backgroundColor: "#e8f5e9",
    borderWidth: 1,
    borderColor: "#81c784",
    borderRadius: theme.radius.md,
    paddingHorizontal: 8,
    paddingVertical: 5,
  },
  invitationAcceptButtonText: {
    color: "#2e7d32",
    fontWeight: "700",
    fontSize: 11,
  },
  invitationDeclineButton: {
    backgroundColor: "#ffebee",
    borderWidth: 1,
    borderColor: "#ef5350",
    borderRadius: theme.radius.md,
    paddingHorizontal: 8,
    paddingVertical: 5,
  },
  invitationDeclineButtonText: {
    color: "#c62828",
    fontWeight: "700",
    fontSize: 11,
  },
});
