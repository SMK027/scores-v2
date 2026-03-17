import { useCallback, useEffect, useMemo, useState } from "react";
import { ActivityIndicator, Image, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { ApiError, fetchProfile } from "../services/api";
import { theme } from "../styles/theme";
import type { User } from "../types/api";
import { getAvatarUri, getInitials } from "../utils/avatar";
import { getRoleLabel } from "../utils/roles";

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

export function ProfileScreen({ token, fallbackUser, onBack }: Props) {
  const [profile, setProfile] = useState<User>(fallbackUser);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadProfile = useCallback(async () => {
    try {
      setError(null);
      const user = await fetchProfile(token);
      setProfile(user);
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
        <Text style={styles.email}>{profile.email}</Text>
      </View>

      <View style={styles.card}>
        <Text style={styles.sectionTitle}>Informations</Text>
        <Text style={styles.meta}>Role global: {getRoleLabel(profile.global_role)}</Text>
        <Text style={styles.meta}>Inscrit depuis: {joinedLabel}</Text>
        <Text style={styles.bioLabel}>Bio</Text>
        <Text style={styles.bio}>{profile.bio?.trim() ? profile.bio : "Aucune bio renseignee."}</Text>
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
  },
  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    marginBottom: 12,
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
  },
  avatarCircle: {
    width: 72,
    height: 72,
    borderRadius: 36,
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
    fontSize: 22,
    fontWeight: "700",
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
  bioLabel: {
    color: theme.colors.mutedText,
    fontWeight: "600",
    marginTop: 8,
    marginBottom: 4,
  },
  bio: {
    color: theme.colors.text,
  },
});
