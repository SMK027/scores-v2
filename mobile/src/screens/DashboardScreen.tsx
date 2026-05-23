import { useCallback, useEffect, useMemo, useState } from "react";
import { ActivityIndicator, Image, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { ConfirmDialog } from "../components/ConfirmDialog";
import { useAppTheme } from "../context/ThemeContext";
import { useNotifications } from "../context/NotificationContext";
import { ApiError, fetchProfileStats, fetchSpaces } from "../services/api";
import type { AppTheme } from "../styles";
import type { Invitation, ProfileStats, User } from "../types/api";
import { getAvatarUri, getInitials } from "../utils/avatar";

type Props = {
  token: string;
  user: User;
  onOpenSpaces: () => void;
  onOpenProfile: () => void;
  onOpenNotifications: () => void;
  onOpenReferee: () => void;
  onLogout: () => void;
};

export function DashboardScreen({
  token,
  user,
  onOpenSpaces,
  onOpenProfile,
  onOpenNotifications,
  onOpenReferee,
  onLogout,
}: Props) {
  const { theme } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);
  const { unreadCount } = useNotifications();

  const [isLogoutConfirmOpen, setIsLogoutConfirmOpen] = useState(false);
  const [stats, setStats] = useState<ProfileStats | null>(null);
  const [pendingInvitations, setPendingInvitations] = useState<Invitation[]>([]);
  const [activeGamesCount, setActiveGamesCount] = useState(0);
  const [loadingStats, setLoadingStats] = useState(true);

  const avatarUri = getAvatarUri(user.avatar);
  const hour = new Date().getHours();
  const greeting = hour < 12 ? "Bonjour" : hour < 18 ? "Bon après-midi" : "Bonsoir";

  const loadData = useCallback(async () => {
    try {
      setLoadingStats(true);
      const [profileStats, spacesResponse] = await Promise.all([
        fetchProfileStats(token),
        fetchSpaces(token),
      ]);
      setStats(profileStats);
      setPendingInvitations(spacesResponse.pending_invitations ?? []);
      const count = spacesResponse.spaces.reduce(
        (acc, space) => acc + (space.games_count ?? 0),
        0
      );
      setActiveGamesCount(count);
    } catch (err) {
      if (!(err instanceof ApiError)) {
        // silencieux
      }
    } finally {
      setLoadingStats(false);
    }
  }, [token]);

  useEffect(() => {
    void loadData();
  }, [loadData]);

  const hasAlerts = pendingInvitations.length > 0 || unreadCount > 0 || activeGamesCount > 0;

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>

      {/* Greeting */}
      <View style={styles.greetingCard}>
        <View style={styles.greetingLeft}>
          <Text style={styles.greetingLabel}>{greeting} 👋</Text>
          <Text style={styles.greetingName}>{user.username}</Text>
          <Text style={styles.greetingHint}>Voici votre résumé d&apos;activité</Text>
        </View>
        <Pressable onPress={onOpenProfile} style={styles.avatarCircle}>
          {avatarUri ? (
            <Image source={{ uri: avatarUri }} style={styles.avatarImage} />
          ) : (
            <Text style={styles.avatarText}>{getInitials(user)}</Text>
          )}
        </Pressable>
      </View>

      {/* Statistiques */}
      <Text style={styles.sectionLabel}>Statistiques</Text>
      <View style={styles.statsCard}>
        {loadingStats ? (
          <View style={styles.statsLoading}>
            <ActivityIndicator />
          </View>
        ) : (
          <View style={styles.statsRow}>
            <View style={styles.statTile}>
              <Text style={styles.statValue}>{stats?.total_rounds ?? 0}</Text>
              <Text style={styles.statLabel}>Manches jouées</Text>
            </View>
            <View style={[styles.statTile, styles.statTileMiddle]}>
              <Text style={[styles.statValue, styles.statValueAccent]}>
                {stats ? `${Math.round(stats.win_rate)}%` : "—"}
              </Text>
              <Text style={styles.statLabel}>Taux de victoire</Text>
            </View>
            <View style={styles.statTile}>
              <Text style={styles.statValue}>{stats?.total_spaces ?? 0}</Text>
              <Text style={styles.statLabel}>Espaces</Text>
            </View>
          </View>
        )}
      </View>

      {/* Alertes d'activité */}
      {hasAlerts ? (
        <>
          <Text style={styles.sectionLabel}>Activité</Text>
          <View style={styles.alertsColumn}>
            {activeGamesCount > 0 ? (
              <Pressable style={styles.alertRow} onPress={onOpenSpaces}>
                <Text style={styles.alertIcon}>🎮</Text>
                <View style={styles.alertContent}>
                  <Text style={styles.alertText}>
                    {activeGamesCount} partie{activeGamesCount > 1 ? "s" : ""} en cours
                  </Text>
                  <Text style={styles.alertSub}>Dans vos espaces</Text>
                </View>
                <Text style={styles.alertChevron}>›</Text>
              </Pressable>
            ) : null}
            {pendingInvitations.length > 0 ? (
              <Pressable style={[styles.alertRow, styles.alertRowWarning]} onPress={onOpenSpaces}>
                <Text style={styles.alertIcon}>📨</Text>
                <View style={styles.alertContent}>
                  <Text style={styles.alertText}>
                    {pendingInvitations.length} invitation{pendingInvitations.length > 1 ? "s" : ""} en attente
                  </Text>
                  <Text style={styles.alertSub}>Répondez depuis Mes espaces</Text>
                </View>
                <Text style={styles.alertChevron}>›</Text>
              </Pressable>
            ) : null}
            {unreadCount > 0 ? (
              <Pressable style={[styles.alertRow, styles.alertRowNotif]} onPress={onOpenNotifications}>
                <Text style={styles.alertIcon}>🔔</Text>
                <View style={styles.alertContent}>
                  <Text style={styles.alertText}>
                    {unreadCount} notification{unreadCount > 1 ? "s" : ""} non lue{unreadCount > 1 ? "s" : ""}
                  </Text>
                  <Text style={styles.alertSub}>Appuyez pour consulter</Text>
                </View>
                <Text style={styles.alertChevron}>›</Text>
              </Pressable>
            ) : null}
          </View>
        </>
      ) : null}

      {/* Navigation */}
      <Text style={styles.sectionLabel}>Navigation</Text>
      <View style={styles.navGrid}>
        <Pressable
          style={({ pressed }) => [styles.navCard, pressed && styles.pressed]}
          onPress={onOpenSpaces}
        >
          <View style={[styles.navIconWrap, styles.navIcon_primary]}>
            <Text style={styles.navIcon}>🏠</Text>
          </View>
          <Text style={styles.navCardTitle}>Mes espaces</Text>
          <Text style={styles.navCardDesc}>Accédez à vos espaces et lancez une partie</Text>
        </Pressable>
        <Pressable
          style={({ pressed }) => [styles.navCard, pressed && styles.pressed]}
          onPress={onOpenProfile}
        >
          <View style={[styles.navIconWrap, styles.navIcon_brand]}>
            <Text style={styles.navIcon}>👤</Text>
          </View>
          <Text style={styles.navCardTitle}>Mon profil</Text>
          <Text style={styles.navCardDesc}>Consultez et modifiez vos informations</Text>
        </Pressable>
      </View>

      {/* Accès arbitre */}
      <Pressable
        style={({ pressed }) => [styles.refereeButton, pressed && styles.pressed]}
        onPress={onOpenReferee}
      >
        <View style={styles.refereeIconWrap}>
          <Text style={styles.refereeIcon}>🏁</Text>
        </View>
        <View style={styles.refereeTextWrap}>
          <Text style={styles.refereeTitle}>Mode arbitre</Text>
          <Text style={styles.refereeDesc}>Gérez les scores en temps réel</Text>
        </View>
        <Text style={styles.alertChevron}>›</Text>
      </Pressable>

      {/* Déconnexion */}
      <View style={styles.dangerSection}>
        <Pressable
          style={({ pressed }) => [styles.logoutButton, pressed && styles.pressed]}
          onPress={() => setIsLogoutConfirmOpen(true)}
        >
          <Text style={styles.logoutButtonText}>Se déconnecter</Text>
        </Pressable>
      </View>

      <ConfirmDialog
        visible={isLogoutConfirmOpen}
        title="Se déconnecter ?"
        message="Voulez-vous vraiment vous déconnecter de l'application ?"
        confirmText="Déconnexion"
        destructive
        onCancel={() => setIsLogoutConfirmOpen(false)}
        onConfirm={() => {
          setIsLogoutConfirmOpen(false);
          onLogout();
        }}
      />
    </ScrollView>
  );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  content: {
    padding: theme.spacing.lg,
    paddingBottom: theme.spacing.xxl,
    gap: theme.spacing.lg,
  },

  /* Greeting */
  greetingCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.xl,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.xl,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    ...theme.shadow.card,
  },
  greetingLeft: {
    flex: 1,
    marginRight: 12,
  },
  greetingLabel: {
    fontSize: theme.font.size.sm,
    fontWeight: theme.font.weight.semibold,
    color: theme.colors.mutedText,
    marginBottom: 4,
  },
  greetingName: {
    fontSize: theme.font.size.xxl,
    fontWeight: theme.font.weight.heavy,
    color: theme.colors.text,
    letterSpacing: -0.3,
  },
  greetingHint: {
    fontSize: theme.font.size.sm,
    color: theme.colors.mutedText,
    marginTop: 4,
  },
  avatarCircle: {
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: theme.colors.primarySoft,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 2,
    borderColor: theme.colors.border,
    overflow: "hidden",
  },
  avatarText: {
    color: theme.colors.primary,
    fontSize: theme.font.size.lg,
    fontWeight: theme.font.weight.bold,
  },
  avatarImage: {
    width: "100%",
    height: "100%",
  },

  /* Section label */
  sectionLabel: {
    color: theme.colors.mutedText,
    fontWeight: theme.font.weight.semibold,
    fontSize: theme.font.size.xs,
    textTransform: "uppercase",
    letterSpacing: 0.8,
    marginBottom: -4,
  },

  /* Stats */
  statsCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.xl,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadow.card,
  },
  statsLoading: {
    paddingVertical: 24,
    alignItems: "center",
  },
  statsRow: {
    flexDirection: "row",
    paddingVertical: theme.spacing.lg,
  },
  statTile: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    paddingHorizontal: 8,
  },
  statTileMiddle: {
    borderLeftWidth: 1,
    borderRightWidth: 1,
    borderColor: theme.colors.border,
  },
  statValue: {
    fontSize: theme.font.size.xxl,
    fontWeight: theme.font.weight.heavy,
    color: theme.colors.text,
    letterSpacing: -0.5,
  },
  statValueAccent: {
    color: theme.colors.primary,
  },
  statLabel: {
    fontSize: theme.font.size.xs,
    color: theme.colors.mutedText,
    marginTop: 3,
    fontWeight: theme.font.weight.semibold,
    textAlign: "center",
  },

  /* Alertes */
  alertsColumn: {
    gap: theme.spacing.sm,
  },
  alertRow: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    paddingVertical: 14,
    paddingHorizontal: theme.spacing.lg,
    gap: theme.spacing.md,
    ...theme.shadow.sm,
  },
  alertRowWarning: {
    backgroundColor: theme.colors.warningSoft,
    borderColor: theme.colors.warning + "50",
  },
  alertRowNotif: {
    backgroundColor: theme.colors.primarySoft,
    borderColor: theme.colors.primary + "40",
  },
  alertIcon: {
    fontSize: 20,
  },
  alertContent: {
    flex: 1,
  },
  alertText: {
    fontSize: theme.font.size.sm,
    fontWeight: theme.font.weight.bold,
    color: theme.colors.text,
  },
  alertSub: {
    fontSize: theme.font.size.xs,
    color: theme.colors.mutedText,
    marginTop: 2,
  },
  alertChevron: {
    fontSize: theme.font.size.xl,
    color: theme.colors.mutedText,
    fontWeight: theme.font.weight.bold,
  },

  /* Navigation */
  navGrid: {
    flexDirection: "row",
    gap: 12,
  },
  navCard: {
    flex: 1,
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.xl,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.lg,
    ...theme.shadow.card,
  },
  navIconWrap: {
    width: 44,
    height: 44,
    borderRadius: theme.radius.md,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 12,
  },
  navIcon_primary: {
    backgroundColor: theme.colors.primarySoft,
  },
  navIcon_brand: {
    backgroundColor: theme.colors.brandSoft,
  },
  navIcon: {
    fontSize: 22,
  },
  navCardTitle: {
    color: theme.colors.text,
    fontSize: theme.font.size.md,
    fontWeight: theme.font.weight.bold,
    marginBottom: 4,
  },
  navCardDesc: {
    color: theme.colors.mutedText,
    fontSize: theme.font.size.xs,
    lineHeight: theme.font.lineHeight.tight,
  },

  /* Accès arbitre */
  refereeButton: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    paddingVertical: 14,
    paddingHorizontal: theme.spacing.lg,
    gap: theme.spacing.md,
    ...theme.shadow.sm,
  },
  refereeIconWrap: {
    width: 40,
    height: 40,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.successSoft,
    alignItems: "center",
    justifyContent: "center",
  },
  refereeIcon: {
    fontSize: 20,
  },
  refereeTextWrap: {
    flex: 1,
  },
  refereeTitle: {
    fontSize: theme.font.size.md,
    fontWeight: theme.font.weight.bold,
    color: theme.colors.text,
  },
  refereeDesc: {
    fontSize: theme.font.size.xs,
    color: theme.colors.mutedText,
    marginTop: 2,
  },

  /* Déconnexion */
  dangerSection: {
    marginTop: theme.spacing.sm,
  },
  logoutButton: {
    borderWidth: 1,
    borderColor: theme.colors.danger + "60",
    backgroundColor: theme.colors.dangerSoft,
    borderRadius: theme.radius.md,
    paddingVertical: 13,
    alignItems: "center",
  },
  logoutButtonText: {
    color: theme.colors.danger,
    fontWeight: theme.font.weight.semibold,
    fontSize: theme.font.size.md,
  },
  pressed: {
    opacity: 0.8,
  },
});
