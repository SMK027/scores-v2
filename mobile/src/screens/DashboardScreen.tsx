import { useMemo, useState } from "react";
import { Image, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { ConfirmDialog } from "../components/ConfirmDialog";
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

const NAV_CARDS = [
  {
    key: "spaces",
    icon: "🏠",
    title: "Mes espaces",
    description: "Accédez à vos espaces et lancez une partie",
    accent: "primary" as const,
  },
  {
    key: "profile",
    icon: "👤",
    title: "Mon profil",
    description: "Consultez et modifiez vos informations",
    accent: "brand" as const,
  },
] as const;

export function DashboardScreen({ user, onOpenSpaces, onOpenProfile, onLogout }: Props) {
  const { theme } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);
  const [isLogoutConfirmOpen, setIsLogoutConfirmOpen] = useState(false);

  const avatarUri = getAvatarUri(user.avatar);
  const hour = new Date().getHours();
  const greeting = hour < 12 ? "Bonjour" : hour < 18 ? "Bon après-midi" : "Bonsoir";

  const handleNav = (key: string) => {
    if (key === "spaces") onOpenSpaces();
    else if (key === "profile") onOpenProfile();
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      {/* Bannière de bienvenue */}
      <View style={styles.greetingCard}>
        <View style={styles.greetingLeft}>
          <Text style={styles.greetingLabel}>{greeting} 👋</Text>
          <Text style={styles.greetingName}>{user.username}</Text>
          <Text style={styles.greetingHint}>Que souhaitez-vous faire ?</Text>
        </View>
        <Pressable onPress={onOpenProfile} style={styles.avatarCircle}>
          {avatarUri ? (
            <Image source={{ uri: avatarUri }} style={styles.avatarImage} />
          ) : (
            <Text style={styles.avatarText}>{getInitials(user)}</Text>
          )}
        </Pressable>
      </View>

      {/* Navigation */}
      <Text style={styles.sectionLabel}>Navigation</Text>
      <View style={styles.navGrid}>
        {NAV_CARDS.map((card) => (
          <Pressable
            key={card.key}
            style={({ pressed }) => [styles.navCard, pressed && styles.pressed]}
            onPress={() => handleNav(card.key)}
          >
            <View style={[styles.navIconWrap, styles[`navIcon_${card.accent}`]]}>
              <Text style={styles.navIcon}>{card.icon}</Text>
            </View>
            <Text style={styles.navCardTitle}>{card.title}</Text>
            <Text style={styles.navCardDesc}>{card.description}</Text>
          </Pressable>
        ))}
      </View>

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

  /* Greeting card */
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

  /* Section */
  sectionLabel: {
    color: theme.colors.mutedText,
    fontWeight: theme.font.weight.semibold,
    fontSize: theme.font.size.xs,
    textTransform: "uppercase",
    letterSpacing: 0.8,
    marginBottom: -4,
  },

  /* Nav grid */
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
