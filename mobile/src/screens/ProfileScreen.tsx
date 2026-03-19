import { useCallback, useEffect, useMemo, useState } from "react";
import { ActivityIndicator, Image, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from "react-native";
import { ApiError, fetchProfile, fetchProfileStats, updateProfile } from "../services/api";
import { theme } from "../styles/theme";
import type { ProfileStats, User } from "../types/api";
import { getAvatarUri, getInitials } from "../utils/avatar";
import { getRoleLabel } from "../utils/roles";

function getRoleBadgeStyle(role?: string): { backgroundColor: string; textColor: string } {
  switch (role) {
    case "superadmin":
      return { backgroundColor: "#ffe3e3", textColor: "#b42318" };
    case "admin":
      return { backgroundColor: "#e9f1ff", textColor: "#1f6feb" };
    case "moderator":
      return { backgroundColor: "#efe4ff", textColor: "#6f42c1" };
    case "manager":
      return { backgroundColor: "#e6f6ec", textColor: "#1a7f37" };
    case "member":
      return { backgroundColor: "#eef2f8", textColor: "#5f6b85" };
    case "guest":
      return { backgroundColor: "#fff4d6", textColor: "#9a6700" };
    case "user":
    default:
      return { backgroundColor: "#eef2f8", textColor: "#5f6b85" };
  }
}

type Props = {
  token: string;
  fallbackUser: User;
  onBack: () => void;
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

export function ProfileScreen({ token, fallbackUser, onBack }: Props) {
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
  const roleBadgeStyle = useMemo(() => getRoleBadgeStyle(profile.global_role), [profile.global_role]);

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
        setError("Impossible de mettre a jour la bio.");
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
        <Pressable onPress={onBack}>
          <Text style={styles.back}>Retour</Text>
        </Pressable>
        <Text style={styles.headerTitle}>Profil</Text>
        <Pressable onPress={loadProfile}>
          <Text style={styles.back}>Rafraichir</Text>
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
          <Text style={styles.metaLabel}>Role global</Text>
          <View style={[styles.roleBadge, { backgroundColor: roleBadgeStyle.backgroundColor }]}>
            <Text style={[styles.roleBadgeText, { color: roleBadgeStyle.textColor }]}>
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
          <Text style={styles.bio}>{profile.bio?.trim() ? profile.bio : "Aucune bio renseignee."}</Text>
        )}
      </View>

      <View style={styles.card}>
        <Text style={styles.sectionTitle}>Actions rapides</Text>
        <Pressable style={styles.quickActionButton} onPress={loadProfile}>
          <Text style={styles.quickActionText}>Parametres du compte</Text>
        </Pressable>
        <Pressable style={styles.quickActionButton} onPress={loadProfile}>
          <Text style={styles.quickActionText}>Aide et FAQ</Text>
        </Pressable>
        <Pressable style={styles.quickActionDanger} onPress={onBack}>
          <Text style={styles.quickActionDangerText}>Retour a l'application</Text>
        </Pressable>
      </View>
    </ScrollView>
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
  back: {
    color: theme.colors.primary,
    fontWeight: "700",
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
  quickActionDanger: {
    borderWidth: 1,
    borderColor: "#f2c7c3",
    borderRadius: theme.radius.md,
    paddingVertical: 12,
    paddingHorizontal: 12,
    backgroundColor: "#fff1f0",
  },
  quickActionDangerText: {
    color: theme.colors.danger,
    fontWeight: "700",
    textAlign: "center",
  },
});
