import { Image, Pressable, StyleSheet, Text, View } from "react-native";
import { theme } from "../styles/theme";
import type { User } from "../types/api";
import { getAvatarUri, getInitials } from "../utils/avatar";

type Props = {
  user: User;
  onOpenSpaces: () => void;
  onOpenProfile: () => void;
  onLogout: () => void;
};

export function DashboardScreen({ user, onOpenSpaces, onOpenProfile, onLogout }: Props) {
  const avatarUri = getAvatarUri(user.avatar);

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>Bonjour {user.username}</Text>
        <Pressable onPress={onLogout}>
          <Text style={styles.logout}>Deconnexion</Text>
        </Pressable>
      </View>

      <Text style={styles.subtitle}>Choisis ce que tu veux faire.</Text>

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

      <Pressable style={styles.actionCard} onPress={onOpenSpaces}>
        <Text style={styles.cardTitle}>Mes espaces</Text>
        <Text style={styles.cardText}>Acceder a tous mes espaces et commencer une partie</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
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
  logout: {
    color: theme.colors.primary,
    fontWeight: "700",
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
