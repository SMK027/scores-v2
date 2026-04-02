import { useCallback, useEffect, useMemo, useState } from "react";
import { ActivityIndicator, Image, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from "react-native";
import { ApiError, fetchProfile, fetchProfileStats, updateProfile } from "../services/api";
import { useAppTheme } from "../context/ThemeContext";
import type { AppTheme, ResolvedTheme, ThemePreference } from "../styles";
import type { ProfileStats, User } from "../types/api";
import { getAvatarUri, getInitials } from "../utils/avatar";
import { getRoleLabel, getRoleBadgeColors } from "../utils/roles";

type Props = {
  token: string;
  fallbackUser: User;
  onBack: () => void;
  themePreference: ThemePreference;
  resolvedTheme: ResolvedTheme;
  onThemePreferenceChange: (next: ThemePreference) => void;
};

function formatDate(value?: string): string {
  if (!value) {
    return "Date inconnue";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat("fr-FR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  }).format(date);
}

function maskEmail(email: string): string {
  const [localPart, domain] = email.split("@");
  if (!localPart || !domain) {
    return email;
  }
  if (localPart.length <= 2) {
    return `${localPart[0] ?? "*"}***@${domain}`;
  }
  return `${localPart.slice(0, 3)}***@${domain}`;
}

export function ProfileScreen({
  token,
  fallbackUser,
  onBack,
  themePreference,
  resolvedTheme,
  onThemePreferenceChange,
}: Props) {
  const { theme } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);

  const [profile, setProfile] = useState<User>(fallbackUser);
  const [stats, setStats] = useState<ProfileStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [editingBio, setEditingBio] = useState(false);
  const [bioDraft, setBioDraft] = useState(fallbackUser.bio ?? "");

  const loadProfile = useCallback(async () => {
    try {
      setError(null);
      const [user, globalStats] = await Promise.all([
        fetchProfile(token),
        fetchProfileStats(token),
      ]);
      setProfile(user);
      setBioDraft(user.bio ?? "");
      setStats(globalStats);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de charger le profil.");
      }
    }
  }, [token]);

  useEffect(() => {
    const run = async () => {
      setLoading(true);
      await loadProfile();
      setLoading(false);
    };
    void run();
  }, [loadProfile]);

  const joinedLabel = useMemo(() => formatDate(profile.created_at), [profile.created_at]);
  const avatarUri = useMemo(() => getAvatarUri(profile.avatar), [profile.avatar]);
  const roleBadgeStyle = useMemo(() => getRoleBadgeColors(theme, profile.global_role), [profile.global_role, theme]);

  const saveBio = async () => {
    try {
      setSaving(true);
      setError(null);
      const updatedUser = await updateProfile(token, { bio: bioDraft.trim() });
      setProfile(updatedUser);
      setBioDraft(updatedUser.bio ?? "");
      setEditingBio(false);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de mettre à jour la bio.");
      }
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.header}>
        <Pressable style={styles.navButton} onPress={onBack}>
          <Text style={styles.navButtonText}>← Retour</Text>
        </Pressable>
        <Text style={styles.headerTitle}>Profil</Text>
        <Pressable style={styles.navButton} onPress={loadProfile}>
          <Text style={styles.navButtonText}>↻ Rafraîchir</Text>
        </Pressable>
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <View style={styles.card}>
        <View style={styles.avatarCircle}>
          {avatarUri ? (
            <Image source={{ uri: avatarUri }} style={styles.avatarImage} />
          ) : (
            <Text style={styles.avatarText}>{getInitials(profile)}</Text>
          )}
        </View>
        <Text style={styles.username}>{profile.username}</Text>
        <Text style={styles.email}>{maskEmail(profile.email)}</Text>
      </View>

      <View style={styles.card}>
        <Text style={styles.sectionTitle}>Informations</Text>
        <View style={styles.roleRow}>
          <Text style={styles.metaLabel}>Rôle global</Text>
          <View style={[styles.roleBadge, { backgroundColor: roleBadgeStyle.bg, borderColor: roleBadgeStyle.border, borderWidth: 1 }]}>
            <Text style={[styles.roleBadgeText, { color: roleBadgeStyle.text }]}>
              {getRoleLabel(profile.global_role)}
            </Text>
          </View>
        </View>
        <Text style={styles.meta}>Inscrit depuis: {joinedLabel}</Text>

        {stats ? (
          <View style={styles.statsRow}>
            <View style={styles.statBox}>
              <Text style={styles.statValue}>{stats.total_rounds}</Text>
              <Text style={styles.statLabel}>Manches</Text>
            </View>
            <View style={styles.statBox}>
              <Text style={styles.statValue}>{stats.rounds_won}</Text>
              <Text style={styles.statLabel}>Victoires</Text>
            </View>
            <View style={styles.statBox}>
              <Text style={[styles.statValue, styles.statValueAccent]}>{stats.win_rate}%</Text>
              <Text style={styles.statLabel}>Taux de victoire</Text>
            </View>
            <View style={styles.statBox}>
              <Text style={styles.statValue}>{stats.total_spaces}</Text>
              <Text style={styles.statLabel}>Espaces</Text>
            </View>
          </View>
        ) : null}

        <View style={styles.bioHeader}>
          <Text style={styles.bioLabel}>Bio</Text>
          {!editingBio ? (
            <Pressable onPress={() => setEditingBio(true)}>
              <Text style={styles.editLink}>Modifier</Text>
            </Pressable>
          ) : null}
        </View>

        {editingBio ? (
          <>
            <TextInput
              value={bioDraft}
              onChangeText={setBioDraft}
              placeholder="Parlez un peu de vous"
              style={styles.bioInput}
              multiline
              textAlignVertical="top"
            />
            <Pressable
              style={[styles.primaryButton, saving ? styles.disabledButton : undefined]}
              disabled={saving}
              onPress={saveBio}
            >
              <Text style={styles.primaryButtonText}>{saving ? "Enregistrement..." : "Enregistrer la bio"}</Text>
            </Pressable>
            <Pressable
              style={styles.cancelButton}
              onPress={() => {
                setBioDraft(profile.bio ?? "");
                setEditingBio(false);
              }}
            >
              <Text style={styles.cancelButtonText}>Annuler</Text>
            </Pressable>
          </>
        ) : (
          <Text style={styles.bio}>{profile.bio?.trim() ? profile.bio : "Aucune bio renseignée."}</Text>
        )}
      </View>

      <View style={styles.card}>
        <Text style={styles.sectionTitle}>Actions rapides</Text>
        <Text style={styles.metaLabel}>Apparence</Text>
        <View style={styles.themeSelectorRow}>
          {([
            ["system", "Système"],
            ["light", "Clair"],
            ["dark", "Sombre"],
          ] as const).map(([value, label]) => (
            <Pressable
              key={value}
              style={[styles.themeOption, themePreference === value ? styles.themeOptionActive : undefined]}
              onPress={() => onThemePreferenceChange(value)}
            >
              <Text style={[styles.themeOptionText, themePreference === value ? styles.themeOptionTextActive : undefined]}>
                {label}
              </Text>
            </Pressable>
          ))}
        </View>
        <Text style={styles.themeHint}>Thème actuellement appliqué : {resolvedTheme === "dark" ? "Sombre" : "Clair"}</Text>

        <Pressable style={styles.quickActionDanger} onPress={onBack}>
          <Text style={styles.quickActionDangerText}>Retour à l’application</Text>
        </Pressable>
      </View>
    </ScrollView>
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
  },
  content: {
    padding: 16,
    paddingBottom: 22,
  },
  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 12,
  },
  headerTitle: {
    color: theme.colors.text,
    fontWeight: "800",
    fontSize: 18,
  },
  navButton: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.md,
    paddingHorizontal: 10,
    paddingVertical: 7,
  },
  navButtonText: {
    color: theme.colors.primary,
    fontWeight: "700",
    fontSize: 12,
  },
  error: {
    color: theme.colors.danger,
    marginBottom: 10,
  },
  card: {
    backgroundColor: theme.colors.card,
    borderColor: theme.colors.border,
    borderWidth: 1,
    borderRadius: theme.radius.lg,
    padding: 16,
    marginBottom: 12,
    ...theme.shadow.card,
  },
  avatarCircle: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: theme.colors.primarySoft,
    alignItems: "center",
    justifyContent: "center",
    alignSelf: "center",
    marginBottom: 12,
  },
  avatarText: {
    color: theme.colors.primary,
    fontWeight: "700",
    fontSize: 24,
  },
  avatarImage: {
    width: "100%",
    height: "100%",
    borderRadius: 36,
  },
  username: {
    color: theme.colors.text,
    fontSize: 24,
    fontWeight: "800",
    textAlign: "center",
  },
  email: {
    color: theme.colors.mutedText,
    marginTop: 4,
    textAlign: "center",
  },
  sectionTitle: {
    color: theme.colors.text,
    fontSize: 18,
    fontWeight: "700",
    marginBottom: 10,
  },
  meta: {
    color: theme.colors.text,
    marginBottom: 6,
  },
  metaLabel: {
    color: theme.colors.mutedText,
    fontWeight: "600",
  },
  roleRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 10,
  },
  roleBadge: {
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 7,
  },
  roleBadgeText: {
    fontWeight: "700",
    fontSize: 13,
  },
  bioHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginTop: 8,
    marginBottom: 4,
  },
  bioLabel: {
    color: theme.colors.mutedText,
    fontWeight: "600",
  },
  editLink: {
    color: theme.colors.primary,
    fontWeight: "700",
  },
  bio: {
    color: theme.colors.text,
  },
  bioInput: {
    minHeight: 110,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.card,
    paddingHorizontal: 12,
    paddingVertical: 10,
    color: theme.colors.text,
  },
  primaryButton: {
    marginTop: 10,
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    paddingVertical: 12,
    alignItems: "center",
  },
  primaryButtonText: {
    color: "#ffffff",
    fontWeight: "700",
  },
  disabledButton: {
    opacity: 0.6,
  },
  cancelButton: {
    marginTop: 8,
    alignItems: "center",
    paddingVertical: 8,
  },
  cancelButtonText: {
    color: theme.colors.mutedText,
    fontWeight: "600",
  },
  statsRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    marginTop: 12,
    marginBottom: 8,
    gap: 8,
  },
  statBox: {
    flex: 1,
    backgroundColor: theme.colors.background,
    borderRadius: theme.radius.md,
    paddingVertical: 10,
    alignItems: "center",
  },
  statValue: {
    color: theme.colors.text,
    fontWeight: "700",
    fontSize: 18,
  },
  statValueAccent: {
    color: theme.colors.primary,
  },
  statLabel: {
    color: theme.colors.mutedText,
    fontSize: 11,
    marginTop: 2,
    textAlign: "center",
  },
  quickActionButton: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    paddingVertical: 12,
    paddingHorizontal: 12,
    marginBottom: 8,
    backgroundColor: theme.colors.background,
  },
  quickActionText: {
    color: theme.colors.text,
    fontWeight: "600",
  },
  themeSelectorRow: {
    flexDirection: "row",
    gap: 8,
    marginTop: 8,
    marginBottom: 8,
  },
  themeOption: {
    flex: 1,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.background,
    paddingVertical: 10,
    alignItems: "center",
  },
  themeOptionActive: {
    borderColor: theme.colors.primary,
    backgroundColor: theme.colors.primarySoft,
  },
  themeOptionText: {
    color: theme.colors.text,
    fontWeight: "600",
  },
  themeOptionTextActive: {
    color: theme.colors.primary,
    fontWeight: "700",
  },
  themeHint: {
    color: theme.colors.mutedText,
    fontSize: 12,
    marginBottom: 10,
  },
  quickActionDanger: {
    borderWidth: 1,
    borderColor: theme.colors.danger,
    borderRadius: theme.radius.md,
    paddingVertical: 12,
    paddingHorizontal: 12,
    backgroundColor: theme.colors.backgroundSoft,
  },
  quickActionDangerText: {
    color: theme.colors.danger,
    fontWeight: "700",
    textAlign: "center",
  },
});
