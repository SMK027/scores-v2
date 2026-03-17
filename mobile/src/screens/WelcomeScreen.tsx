import { Linking, Pressable, StyleSheet, Text, View } from "react-native";
import { REGISTER_URL } from "../config/constants";
import { theme } from "../styles/theme";

type Props = {
  onLoginPress: () => void;
};

export function WelcomeScreen({ onLoginPress }: Props) {
  const openRegister = async () => {
    await Linking.openURL(REGISTER_URL);
  };

  return (
    <View style={styles.container}>
      <View style={styles.heroCard}>
        <Text style={styles.badge}>Application mobile</Text>
        <Text style={styles.title}>Scores</Text>
        <Text style={styles.subtitle}>
          Gérez vos espaces, joueurs et parties avec une interface mobile fluide.
        </Text>

        <View style={styles.benefits}>
          <Text style={styles.benefit}>• Création de parties en quelques secondes</Text>
          <Text style={styles.benefit}>• Suivi des performances en direct</Text>
          <Text style={styles.benefit}>• Gestion collaborative des espaces</Text>
        </View>
      </View>

      <View style={styles.actions}>
        <Pressable style={styles.primaryButton} onPress={onLoginPress}>
          <Text style={styles.primaryButtonText}>Se connecter</Text>
        </Pressable>

        <Pressable style={styles.secondaryButton} onPress={openRegister}>
          <Text style={styles.secondaryButtonText}>S'inscrire</Text>
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    padding: 22,
    justifyContent: "space-between",
    backgroundColor: theme.colors.background,
  },
  heroCard: {
    marginTop: 36,
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.xl,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: 20,
    ...theme.shadow.card,
  },
  badge: {
    alignSelf: "flex-start",
    backgroundColor: theme.colors.primarySoft,
    color: theme.colors.primary,
    fontWeight: "700",
    fontSize: 12,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: theme.radius.pill,
    marginBottom: 12,
  },
  title: {
    fontSize: 38,
    fontWeight: "800",
    color: theme.colors.text,
  },
  subtitle: {
    marginTop: 12,
    marginBottom: 16,
    fontSize: 17,
    lineHeight: 24,
    color: theme.colors.mutedText,
  },
  benefits: {
    gap: 8,
  },
  benefit: {
    color: theme.colors.text,
    fontSize: 14,
  },
  actions: {
    marginBottom: 8,
  },
  primaryButton: {
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    paddingVertical: 15,
    alignItems: "center",
    marginBottom: 12,
  },
  primaryButtonText: {
    color: "#ffffff",
    fontWeight: "700",
    fontSize: 16,
  },
  secondaryButton: {
    backgroundColor: theme.colors.primarySoft,
    borderRadius: theme.radius.md,
    paddingVertical: 14,
    alignItems: "center",
  },
  secondaryButtonText: {
    color: theme.colors.primary,
    fontWeight: "700",
    fontSize: 16,
  },
});
