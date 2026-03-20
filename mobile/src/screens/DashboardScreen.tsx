import { useMemo } from "react";
import { Alert, Image, Pressable, StyleSheet, Text, View } from "react-native";
import { useAppTheme } from "../context/ThemeContext";
import type { AppTheme } from "../styles";
import type { User } from "../types/api";
import { getAvatarUri, getInitials } from "../utils/avatar";

type Props = {
  user: User;
  onOpenSpaces: () => void;
  onOpenProfile: () => void;
  onLogout: () => void;
};

export function DashboardScreen({ user, onOpenSpaces, onOpenProfile, onLogout }: Props) {
  const { theme } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);

  const avatarUri = getAvatarUri(user.avatar);

  const confirmLogout = () => {
    Alert.alert("Se déconnecter ?", "Voulez-vous vraiment vous déconnecter de l'application ?", [
      { text: "Annuler", style: "cancel" },
      { text: "Déconnexion", style: "destructive", onPress: onLogout },
    ]);
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>Bonjour {user.username}</Text>
        <Pressable style={styles.logoutButton} onPress={confirmLogout}>
          <Text style={styles.logoutButtonText}>Déconnexion</Text>
        </Pressable>
      </View>

      <Text style={styles.subtitle}>Choisis ce que tu veux faire.</Text>

      <Text style={styles.sectionLabel}>Mon compte</Text>

      <Pressable style={styles.avatarCard} onPress={onOpenProfile}>
        <View style={styles.avatarCircle}>
          {avatarUri ? (
            <Image source={{ uri: avatarUri }} style={styles.avatarImage} />
          ) : (
            <Text style={styles.avatarText}>{getInitials(user)}</Text>
          )}
        </View>
        <View style={styles.avatarContent}>
          <Text style={styles.cardTitle}>Mon profil</Text>
          <Text style={styles.cardText}>Consulter mes informations via ma photo de profil</Text>
        </View>
      </Pressable>

      <Text style={styles.sectionLabel}>Navigation</Text>

      <Pressable style={styles.actionCard} onPress={onOpenSpaces}>
        <Text style={styles.cardTitle}>Mes espaces</Text>
        <Text style={styles.cardText}>Accéder à tous mes espaces et commencer une partie</Text>
      </Pressable>
    </View>
  );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
    padding: 16,
  },
  header: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 10,
  },
  title: {
    color: theme.colors.text,
    fontSize: 24,
    fontWeight: "700",
  },
  subtitle: {
    color: theme.colors.mutedText,
    marginBottom: 16,
  },
  sectionLabel: {
    color: theme.colors.mutedText,
    fontWeight: "600",
    fontSize: 13,
    marginBottom: 8,
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
  avatarCard: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: theme.colors.card,
    borderColor: theme.colors.border,
    borderWidth: 1,
    borderRadius: theme.radius.lg,
    padding: 16,
    marginBottom: 12,
  },
  avatarCircle: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: theme.colors.primarySoft,
    alignItems: "center",
    justifyContent: "center",
    marginRight: 14,
  },
  avatarText: {
    color: theme.colors.primary,
    fontSize: 22,
    fontWeight: "700",
  },
  avatarImage: {
    width: "100%",
    height: "100%",
    borderRadius: 32,
  },
  avatarContent: {
    flex: 1,
  },
  actionCard: {
    backgroundColor: theme.colors.card,
    borderColor: theme.colors.border,
    borderWidth: 1,
    borderRadius: theme.radius.lg,
    padding: 16,
  },
  cardTitle: {
    color: theme.colors.text,
    fontSize: 18,
    fontWeight: "700",
  },
  cardText: {
    color: theme.colors.mutedText,
    marginTop: 4,
  },
});
