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
import { useAppTheme } from "../context/ThemeContext";
import { useNotifications } from "../context/NotificationContext";
import { ConfirmDialog } from "../components/ConfirmDialog";
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
  onOpenDashboard: () => void;
  onOpenProfile: () => void;
  onOpenCreateSpace: () => void;
  onOpenNotifications: () => void;
};

export function SpacesScreen({ token, user, onSelectSpace, onLogout, onOpenDashboard, onOpenProfile, onOpenCreateSpace, onOpenNotifications }: Props) {
  const { theme } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);
  const { unreadCount } = useNotifications();

  const [spaces, setSpaces] = useState<Space[]>([]);
  const [searchQuery, setSearchQuery] = useState("");
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [deletingSpaceId, setDeletingSpaceId] = useState<number | null>(null);
  const [leavingSpaceId, setLeavingSpaceId] = useState<number | null>(null);
  const [invitations, setInvitations] = useState<Invitation[]>([]);
  const [respondingInvitationId, setRespondingInvitationId] = useState<number | null>(null);
  const [confirmDialog, setConfirmDialog] = useState<{
    visible: boolean;
    title: string;
    message: string;
    confirmText: string;
    destructive: boolean;
    onConfirm: (() => void) | null;
  }>({
    visible: false,
    title: "",
    message: "",
    confirmText: "Confirmer",
    destructive: false,
    onConfirm: null,
  });

  const openConfirmDialog = (
    title: string,
    message: string,
    onConfirm: () => void,
    options?: { confirmText?: string; destructive?: boolean }
  ) => {
    setConfirmDialog({
      visible: true,
      title,
      message,
      confirmText: options?.confirmText ?? "Confirmer",
      destructive: options?.destructive ?? false,
      onConfirm,
    });
  };

  const closeConfirmDialog = () => {
    setConfirmDialog((current) => ({
      ...current,
      visible: false,
      onConfirm: null,
    }));
  };

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
    openConfirmDialog(
      "Refuser l'invitation ?",
      `Êtes-vous sûr de vouloir refuser l'invitation pour "${invitation.space_name}" ?`,
      async () => {
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
      { confirmText: "Refuser", destructive: true }
    );
  }, [loadSpaces, token]);

  const confirmAndDeleteSpace = (space: Space) => {
    if (space.created_by !== user.id) {
      setError("Seul le propriétaire peut supprimer cet espace.");
      return;
    }

    openConfirmDialog(
      "Supprimer cet espace ?",
      `Cette action supprimera définitivement "${space.name}" et toutes ses données.`,
      async () => {
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
      { confirmText: "Supprimer", destructive: true }
    );
  };

  const confirmAndLeaveSpace = (space: Space) => {
    openConfirmDialog(
      "Quitter cet espace ?",
      `Êtes-vous sûr de vouloir quitter "${space.name}" ?`,
      async () => {
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
      { confirmText: "Quitter", destructive: true }
    );
  };

  const avatarUri = getAvatarUri(user.avatar);

  const confirmLogout = useCallback(() => {
    openConfirmDialog(
      "Se déconnecter ?",
      "Voulez-vous vraiment vous déconnecter de l'application ?",
      onLogout,
      { confirmText: "Déconnexion", destructive: true }
    );
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
          <Pressable style={styles.backButton} onPress={onOpenDashboard}>
            <Text style={styles.backButtonText}>← Tableau de bord</Text>
          </Pressable>
          <Text style={styles.kicker}>Espace personnel</Text>
          <Text style={styles.title}>Mes espaces</Text>
          <Text style={styles.subtitle}>Gérez vos salons de jeu, {user.username}</Text>
        </View>
        <View style={styles.headerActions}>
          <View style={styles.headerTopRow}>
            <Pressable style={styles.profileButton} onPress={onOpenProfile}>
              {avatarUri ? (
                <Image source={{ uri: avatarUri }} style={styles.profileAvatar} />
              ) : (
                <Text style={styles.profileAvatarText}>{getInitials(user)}</Text>
              )}
            </Pressable>
            <Pressable style={styles.bellButton} onPress={onOpenNotifications}>
              <Text style={styles.bellIcon}>🔔</Text>
              {unreadCount > 0 ? (
                <View style={styles.bellBadge}>
                  <Text style={styles.bellBadgeText}>
                    {unreadCount > 99 ? "99+" : String(unreadCount)}
                  </Text>
                </View>
              ) : null}
            </Pressable>
          </View>
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

      <ConfirmDialog
        visible={confirmDialog.visible}
        title={confirmDialog.title}
        message={confirmDialog.message}
        confirmText={confirmDialog.confirmText}
        destructive={confirmDialog.destructive}
        onCancel={closeConfirmDialog}
        onConfirm={() => {
          const callback = confirmDialog.onConfirm;
          closeConfirmDialog();
          if (callback) {
            callback();
          }
        }}
      />
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
  backButton: {
    alignSelf: "flex-start",
    marginBottom: 8,
  },
  backButtonText: {
    color: theme.colors.primary,
    fontSize: theme.font.size.sm,
    fontWeight: theme.font.weight.semibold,
  },
  kicker: {
    color: theme.colors.primary,
    fontWeight: theme.font.weight.bold,
    fontSize: theme.font.size.xs,
    textTransform: "uppercase",
    letterSpacing: 0.8,
    marginBottom: 4,
  },
  title: {
    fontSize: theme.font.size.xxxl,
    fontWeight: theme.font.weight.heavy,
    color: theme.colors.text,
    letterSpacing: -0.5,
  },
  subtitle: {
    color: theme.colors.mutedText,
    fontSize: theme.font.size.sm,
    marginTop: 4,
  },
  logoutButton: {
    borderWidth: 1,
    borderColor: theme.colors.danger + "60",
    backgroundColor: theme.colors.dangerSoft,
    borderRadius: theme.radius.pill,
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  logoutButtonText: {
    color: theme.colors.danger,
    fontWeight: theme.font.weight.semibold,
    fontSize: theme.font.size.xs,
  },
  headerActions: {
    alignItems: "flex-end",
    gap: 8,
  },
  headerTopRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
  },
  bellButton: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: theme.colors.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    alignItems: "center",
    justifyContent: "center",
    ...theme.shadow.sm,
  },
  bellIcon: {
    fontSize: 18,
  },
  bellBadge: {
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
    borderWidth: 1.5,
    borderColor: theme.colors.card,
  },
  bellBadgeText: {
    color: "#fff",
    fontSize: 9,
    fontWeight: theme.font.weight.extrabold,
    lineHeight: 16,
  },
  profileButton: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: theme.colors.primarySoft,
    alignItems: "center",
    justifyContent: "center",
    overflow: "hidden",
    borderWidth: 2,
    borderColor: theme.colors.border,
  },
  profileAvatar: {
    width: "100%",
    height: "100%",
  },
  profileAvatarText: {
    color: theme.colors.primary,
    fontWeight: theme.font.weight.bold,
    fontSize: theme.font.size.sm,
  },
  error: {
    backgroundColor: theme.colors.dangerSoft,
    borderWidth: 1,
    borderColor: theme.colors.danger + "40",
    borderRadius: theme.radius.md,
    paddingHorizontal: 14,
    paddingVertical: 10,
    color: theme.colors.danger,
    fontSize: theme.font.size.sm,
    marginBottom: 10,
  },
  controlsPanel: {
    backgroundColor: theme.colors.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.lg,
    padding: 12,
    marginBottom: 12,
    ...theme.shadow.sm,
  },
  searchInput: {
    backgroundColor: theme.colors.backgroundSoft,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    paddingHorizontal: 14,
    paddingVertical: 11,
    color: theme.colors.text,
    fontSize: theme.font.size.md,
  },
  controlsFooter: {
    marginTop: 10,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  resultsCount: {
    color: theme.colors.mutedText,
    fontWeight: theme.font.weight.semibold,
    fontSize: theme.font.size.sm,
  },
  inlineCreateButton: {
    backgroundColor: theme.colors.primarySoft,
    borderRadius: theme.radius.md,
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderWidth: 1,
    borderColor: theme.colors.primary + "30",
  },
  inlineCreateButtonText: {
    color: theme.colors.primary,
    fontWeight: theme.font.weight.bold,
    fontSize: theme.font.size.sm,
  },
  listHeaderRow: {
    marginBottom: 8,
  },
  listHeaderTitle: {
    color: theme.colors.text,
    fontWeight: theme.font.weight.bold,
    fontSize: theme.font.size.lg,
  },
  disabled: {
    opacity: 0.5,
  },
  listContent: {
    paddingBottom: 96,
  },
  emptyWrap: {
    marginTop: 32,
    alignItems: "center",
    gap: 10,
  },
  emptyTitle: {
    color: theme.colors.text,
    fontWeight: theme.font.weight.bold,
    fontSize: theme.font.size.xl,
  },
  emptyText: {
    color: theme.colors.mutedText,
    fontSize: theme.font.size.md,
    maxWidth: 260,
    textAlign: "center",
    lineHeight: theme.font.lineHeight.normal,
  },
  emptyButton: {
    marginTop: 8,
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    paddingHorizontal: 20,
    paddingVertical: 12,
    ...theme.shadow.card,
  },
  emptyButtonText: {
    color: "#ffffff",
    fontWeight: theme.font.weight.bold,
    fontSize: theme.font.size.md,
  },
  card: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.xl,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: 16,
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
    gap: 6,
  },
  cardHeaderTextWrap: {
    flex: 1,
  },
  spaceAvatarCircle: {
    width: 46,
    height: 46,
    borderRadius: 23,
    backgroundColor: theme.colors.brandSoft,
    alignItems: "center",
    justifyContent: "center",
    marginRight: 12,
  },
  spaceAvatarText: {
    color: theme.colors.brand,
    fontWeight: theme.font.weight.heavy,
    fontSize: theme.font.size.sm,
  },
  deleteSpaceButton: {
    borderWidth: 1,
    borderColor: theme.colors.danger + "40",
    backgroundColor: theme.colors.dangerSoft,
    borderRadius: theme.radius.md,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  deleteSpaceText: {
    color: theme.colors.danger,
    fontWeight: theme.font.weight.semibold,
    fontSize: theme.font.size.xs,
  },
  leaveSpaceButton: {
    borderWidth: 1,
    borderColor: theme.colors.warning + "40",
    backgroundColor: theme.colors.warningSoft,
    borderRadius: theme.radius.md,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  leaveSpaceText: {
    color: theme.colors.warning,
    fontWeight: theme.font.weight.semibold,
    fontSize: theme.font.size.xs,
  },
  cardHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
  },
  spaceName: {
    color: theme.colors.text,
    fontSize: theme.font.size.lg,
    fontWeight: theme.font.weight.bold,
    flexShrink: 1,
  },
  roleBadge: {
    backgroundColor: theme.colors.primarySoft,
    borderRadius: theme.radius.pill,
    paddingHorizontal: 10,
    paddingVertical: 3,
    marginTop: 4,
    alignSelf: "flex-start",
  },
  roleBadgeText: {
    color: theme.colors.primary,
    fontWeight: theme.font.weight.semibold,
    fontSize: theme.font.size.xs,
  },
  description: {
    marginTop: 8,
    color: theme.colors.mutedText,
    fontSize: theme.font.size.sm,
    lineHeight: theme.font.lineHeight.normal,
  },
  cardFooter: {
    marginTop: 12,
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
  },
  statusBadgeActive: {
    backgroundColor: theme.colors.successSoft,
    borderRadius: theme.radius.pill,
    paddingHorizontal: 10,
    paddingVertical: 4,
  },
  statusBadgeActiveText: {
    color: theme.colors.success,
    fontWeight: theme.font.weight.semibold,
    fontSize: theme.font.size.xs,
  },
  gamesBadge: {
    backgroundColor: theme.colors.backgroundSoft,
    borderRadius: theme.radius.pill,
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  gamesBadgeText: {
    color: theme.colors.mutedText,
    fontWeight: theme.font.weight.semibold,
    fontSize: theme.font.size.xs,
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
    ...theme.shadow.strong,
  },
  fabIcon: {
    color: "#ffffff",
    fontSize: 30,
    fontWeight: theme.font.weight.regular,
    marginTop: -2,
  },
  invitationsPanel: {
    backgroundColor: theme.colors.warningSoft,
    borderWidth: 1,
    borderColor: theme.colors.warning + "40",
    borderRadius: theme.radius.xl,
    padding: 14,
    marginBottom: 12,
  },
  invitationsPanelTitle: {
    color: theme.colors.text,
    fontWeight: theme.font.weight.bold,
    fontSize: theme.font.size.md,
    marginBottom: 12,
  },
  invitationCard: {
    backgroundColor: theme.colors.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.lg,
    padding: 12,
    marginBottom: 8,
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    ...theme.shadow.sm,
  },
  invitationContent: {
    flex: 1,
    marginRight: 10,
  },
  invitationSpaceName: {
    color: theme.colors.text,
    fontWeight: theme.font.weight.bold,
    fontSize: theme.font.size.md,
    marginBottom: 2,
  },
  invitationMeta: {
    color: theme.colors.mutedText,
    fontSize: theme.font.size.xs,
  },
  invitationActions: {
    flexDirection: "row",
    gap: 6,
  },
  invitationAcceptButton: {
    backgroundColor: theme.colors.successSoft,
    borderWidth: 1,
    borderColor: theme.colors.success + "50",
    borderRadius: theme.radius.md,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  invitationAcceptButtonText: {
    color: theme.colors.success,
    fontWeight: theme.font.weight.bold,
    fontSize: theme.font.size.xs,
  },
  invitationDeclineButton: {
    backgroundColor: theme.colors.dangerSoft,
    borderWidth: 1,
    borderColor: theme.colors.danger + "40",
    borderRadius: theme.radius.md,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  invitationDeclineButtonText: {
    color: theme.colors.danger,
    fontWeight: theme.font.weight.bold,
    fontSize: theme.font.size.xs,
  },
});
