import { useMemo } from "react";
import { Linking, Pressable, StyleSheet, Text, View } from "react-native";
import { REGISTER_URL, TERMS_URL } from "../config/constants";
import { useAppTheme } from "../context/ThemeContext";
import type { AppTheme } from "../styles";

type Props = {
  onLoginPress: () => void;
  onRefereePress: () => void;
};

export function WelcomeScreen({ onLoginPress, onRefereePress }: Props) {
  const { theme } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);

  const openRegister = async () => {
    await Linking.openURL(REGISTER_URL);
  };

  const openTerms = async () => {
    await Linking.openURL(TERMS_URL);
  };

  return (
    <View style={styles.container}>
      <View style={styles.heroCard}>
        <View style={styles.logoCircle}>
          <Text style={styles.logoGlyph}>#</Text>
        </View>
        <Text style={styles.title}>Scores</Text>
        <Text style={styles.subtitle}>Gérez vos parties de jeux en toute simplicité.</Text>
      </View>

      <View style={styles.actions}>
        <Pressable style={styles.primaryButton} onPress={onLoginPress}>
          <Text style={styles.primaryButtonText}>Se connecter</Text>
        </Pressable>

        <Pressable style={styles.secondaryButton} onPress={openRegister}>
          <Text style={styles.secondaryButtonText}>S'inscrire</Text>
        </Pressable>

        <Pressable style={styles.refereeButton} onPress={onRefereePress}>
          <Text style={styles.refereeButtonText}>Accéder à l'arbitrage</Text>
        </Pressable>

        <Pressable style={styles.termsButton} onPress={openTerms}>
          <Text style={styles.termsText}>Conditions d'utilisation</Text>
        </Pressable>
      </View>
    </View>
  );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
  container: {
    flex: 1,
    padding: 22,
    justifyContent: "space-between",
    backgroundColor: theme.colors.background,
  },
  heroCard: {
    marginTop: 28,
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.xl,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: 22,
    alignItems: "center",
    ...theme.shadow.card,
  },
  logoCircle: {
    width: 76,
    height: 76,
    borderRadius: 38,
    backgroundColor: "#635bff",
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 14,
  },
  logoGlyph: {
    color: "#ffffff",
    fontSize: 34,
    fontWeight: "900",
  },
  title: {
    fontSize: 34,
    fontWeight: "800",
    color: theme.colors.text,
    textAlign: "center",
  },
  subtitle: {
    marginTop: 10,
    fontSize: 15,
    lineHeight: 22,
    color: theme.colors.mutedText,
    textAlign: "center",
  },
  actions: {
    marginTop: 20,
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
  refereeButton: {
    marginTop: 12,
    backgroundColor: theme.colors.success + "18",
    borderRadius: theme.radius.md,
    paddingVertical: 14,
    alignItems: "center",
    borderWidth: 1,
    borderColor: theme.colors.success + "55",
  },
  refereeButtonText: {
    color: theme.colors.success,
    fontWeight: "700",
    fontSize: 15,
  },
  termsButton: {
    marginTop: 12,
    alignItems: "center",
    paddingVertical: 8,
  },
  termsText: {
    color: theme.colors.mutedText,
    fontWeight: "600",
    textDecorationLine: "underline",
  },
});
