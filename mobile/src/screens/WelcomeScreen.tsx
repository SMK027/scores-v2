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
      {/* Hero */}
      <View style={styles.hero}>
        <View style={styles.logoWrap}>
          <View style={styles.logoCircle}>
            <Text style={styles.logoGlyph}>#</Text>
          </View>
          <View style={styles.logoPulse} />
        </View>
        <Text style={styles.appName}>Scores</Text>
        <Text style={styles.tagline}>Gérez vos parties de jeux en toute simplicité.</Text>

        <View style={styles.featureRow}>
          <View style={styles.featurePill}>
            <Text style={styles.featurePillText}>🏆 Classements</Text>
          </View>
          <View style={styles.featurePill}>
            <Text style={styles.featurePillText}>🎮 Parties</Text>
          </View>
          <View style={styles.featurePill}>
            <Text style={styles.featurePillText}>👥 Espaces</Text>
          </View>
        </View>
      </View>

      {/* Actions */}
      <View style={styles.actions}>
        <Pressable style={({ pressed }) => [styles.primaryButton, pressed && styles.pressed]} onPress={onLoginPress}>
          <Text style={styles.primaryButtonText}>Se connecter</Text>
        </Pressable>

        <Pressable style={({ pressed }) => [styles.secondaryButton, pressed && styles.pressed]} onPress={openRegister}>
          <Text style={styles.secondaryButtonText}>Créer un compte</Text>
        </Pressable>

        <View style={styles.dividerRow}>
          <View style={styles.dividerLine} />
          <Text style={styles.dividerText}>Accès spécial</Text>
          <View style={styles.dividerLine} />
        </View>

        <Pressable style={({ pressed }) => [styles.refereeButton, pressed && styles.pressed]} onPress={onRefereePress}>
          <View style={styles.refereeButtonInner}>
            <View style={styles.refereeDot} />
            <Text style={styles.refereeButtonText}>Accéder à l'arbitrage</Text>
          </View>
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
    paddingHorizontal: 24,
    paddingTop: 16,
    paddingBottom: 20,
    justifyContent: "space-between",
    backgroundColor: theme.colors.background,
  },

  /* Hero */
  hero: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 24,
  },
  logoWrap: {
    position: "relative",
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 20,
  },
  logoCircle: {
    width: 88,
    height: 88,
    borderRadius: 44,
    backgroundColor: theme.colors.brand,
    alignItems: "center",
    justifyContent: "center",
    shadowColor: theme.colors.brand,
    shadowOpacity: 0.35,
    shadowRadius: 16,
    shadowOffset: { width: 0, height: 6 },
    elevation: 8,
  },
  logoPulse: {
    position: "absolute",
    width: 108,
    height: 108,
    borderRadius: 54,
    backgroundColor: theme.colors.brandSoft,
    zIndex: -1,
  },
  logoGlyph: {
    color: "#ffffff",
    fontSize: 40,
    fontWeight: theme.font.weight.heavy,
  },
  appName: {
    fontSize: theme.font.size.xxxl,
    fontWeight: theme.font.weight.heavy,
    color: theme.colors.text,
    letterSpacing: -0.5,
  },
  tagline: {
    marginTop: 10,
    fontSize: theme.font.size.md,
    lineHeight: theme.font.lineHeight.relaxed,
    color: theme.colors.mutedText,
    textAlign: "center",
    maxWidth: 260,
  },
  featureRow: {
    flexDirection: "row",
    gap: 8,
    marginTop: 20,
    flexWrap: "wrap",
    justifyContent: "center",
  },
  featurePill: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.pill,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadow.sm,
  },
  featurePillText: {
    color: theme.colors.text,
    fontSize: theme.font.size.sm,
    fontWeight: theme.font.weight.semibold,
  },

  /* Actions */
  actions: {
    gap: 10,
    paddingBottom: 4,
  },
  primaryButton: {
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    paddingVertical: 16,
    alignItems: "center",
    ...theme.shadow.card,
  },
  primaryButtonText: {
    color: "#ffffff",
    fontWeight: theme.font.weight.bold,
    fontSize: theme.font.size.lg,
  },
  secondaryButton: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.md,
    paddingVertical: 15,
    alignItems: "center",
    borderWidth: 1.5,
    borderColor: theme.colors.border,
  },
  secondaryButtonText: {
    color: theme.colors.text,
    fontWeight: theme.font.weight.bold,
    fontSize: theme.font.size.lg,
  },
  dividerRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    marginVertical: 2,
  },
  dividerLine: {
    flex: 1,
    height: 1,
    backgroundColor: theme.colors.border,
  },
  dividerText: {
    color: theme.colors.mutedText,
    fontSize: theme.font.size.xs,
    fontWeight: theme.font.weight.semibold,
    textTransform: "uppercase",
    letterSpacing: 0.8,
  },
  refereeButton: {
    backgroundColor: theme.colors.successSoft,
    borderRadius: theme.radius.md,
    paddingVertical: 14,
    alignItems: "center",
    borderWidth: 1,
    borderColor: theme.colors.success + "40",
  },
  refereeButtonInner: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
  },
  refereeDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: theme.colors.success,
  },
  refereeButtonText: {
    color: theme.colors.success,
    fontWeight: theme.font.weight.bold,
    fontSize: theme.font.size.md,
  },
  termsButton: {
    alignItems: "center",
    paddingVertical: 6,
  },
  termsText: {
    color: theme.colors.mutedText,
    fontSize: theme.font.size.sm,
    fontWeight: theme.font.weight.medium,
    textDecorationLine: "underline",
  },
  pressed: {
    opacity: 0.82,
  },
});
