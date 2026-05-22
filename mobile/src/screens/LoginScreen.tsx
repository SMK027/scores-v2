import { useMemo, useState } from "react";
import {
  ActivityIndicator,
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { Feather } from "@expo/vector-icons";
import { ApiError, login } from "../services/api";
import { FORGOT_PASSWORD_URL, REGISTER_URL, TERMS_URL } from "../config/constants";
import type { User } from "../types/api";
import { useAppTheme } from "../context/ThemeContext";
import type { AppTheme } from "../styles";

type Props = {
  onBack: () => void;
  onLoginSuccess: (payload: { token: string; user: User }) => void;
};

export function LoginScreen({ onBack, onLoginSuccess }: Props) {
  const { theme } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [showPassword, setShowPassword] = useState(false);

  const submit = async () => {
    try {
      setLoading(true);
      setError(null);
      const payload = await login(email.trim(), password);
      onLoginSuccess(payload);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de se connecter pour le moment.");
      }
    } finally {
      setLoading(false);
    }
  };

  const openForgotPassword = async () => {
    await Linking.openURL(FORGOT_PASSWORD_URL);
  };

  const openRegister = async () => {
    await Linking.openURL(REGISTER_URL);
  };

  const openTerms = async () => {
    await Linking.openURL(TERMS_URL);
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      {/* En-tête */}
      <View style={styles.headerArea}>
        <Pressable style={styles.backChip} onPress={onBack}>
          <Text style={styles.backChipText}>← Retour</Text>
        </Pressable>
        <View style={styles.logoCircle}>
          <Text style={styles.logoGlyph}>#</Text>
        </View>
        <Text style={styles.title}>Connexion</Text>
        <Text style={styles.subtitle}>Accédez à vos espaces et parties.</Text>
      </View>

      {/* Formulaire */}
      <View style={styles.card}>
        {error ? (
          <View style={styles.errorBox}>
            <Text style={styles.errorBoxText}>{error}</Text>
          </View>
        ) : null}

        <Text style={styles.fieldLabel}>Adresse e-mail</Text>
        <TextInput
          value={email}
          onChangeText={setEmail}
          keyboardType="email-address"
          autoCapitalize="none"
          autoComplete="email"
          placeholder="vous@exemple.com"
          placeholderTextColor={theme.colors.mutedText}
          style={styles.input}
        />

        <Text style={styles.fieldLabel}>Mot de passe</Text>
        <View style={styles.passwordRow}>
          <TextInput
            value={password}
            onChangeText={setPassword}
            secureTextEntry={!showPassword}
            placeholder="••••••••"
            placeholderTextColor={theme.colors.mutedText}
            style={[styles.input, styles.passwordInput]}
          />
          <Pressable style={styles.toggleButton} onPress={() => setShowPassword((current) => !current)}>
            <Feather
              name={showPassword ? "eye-off" : "eye"}
              size={18}
              color={theme.colors.mutedText}
            />
          </Pressable>
        </View>

        <Pressable style={styles.forgotLink} onPress={openForgotPassword}>
          <Text style={styles.forgotLinkText}>Mot de passe oublié ?</Text>
        </Pressable>

        <Pressable
          style={[styles.primaryButton, loading ? styles.disabledButton : undefined]}
          disabled={loading}
          onPress={submit}
        >
          {loading ? <ActivityIndicator color="#ffffff" /> : <Text style={styles.primaryText}>Se connecter</Text>}
        </Pressable>
      </View>

      {/* Inscription */}
      <View style={styles.footerCard}>
        <Text style={styles.footerLabel}>Pas encore de compte ?</Text>
        <Pressable style={styles.secondaryButton} onPress={openRegister}>
          <Text style={styles.secondaryButtonText}>Créer un compte</Text>
        </Pressable>
      </View>

      <Pressable style={styles.termsButton} onPress={openTerms}>
        <Text style={styles.termsText}>Conditions d'utilisation</Text>
      </Pressable>
    </ScrollView>
  );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  content: {
    padding: 20,
    paddingBottom: 32,
    gap: 16,
  },

  /* En-tête */
  headerArea: {
    alignItems: "flex-start",
    marginTop: 8,
  },
  backChip: {
    backgroundColor: theme.colors.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.pill,
    paddingHorizontal: 14,
    paddingVertical: 7,
    marginBottom: 20,
    ...theme.shadow.sm,
  },
  backChipText: {
    color: theme.colors.text,
    fontWeight: theme.font.weight.semibold,
    fontSize: theme.font.size.sm,
  },
  logoCircle: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: theme.colors.brand,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 14,
    shadowColor: theme.colors.brand,
    shadowOpacity: 0.3,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 4 },
    elevation: 5,
  },
  logoGlyph: {
    color: "#ffffff",
    fontSize: 28,
    fontWeight: theme.font.weight.heavy,
  },
  title: {
    fontSize: theme.font.size.xxxl,
    fontWeight: theme.font.weight.heavy,
    color: theme.colors.text,
    letterSpacing: -0.5,
  },
  subtitle: {
    marginTop: 6,
    color: theme.colors.mutedText,
    fontSize: theme.font.size.md,
    lineHeight: theme.font.lineHeight.normal,
  },

  /* Formulaire */
  card: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.xl,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: 20,
    ...theme.shadow.card,
  },
  errorBox: {
    backgroundColor: theme.colors.dangerSoft,
    borderWidth: 1,
    borderColor: theme.colors.danger + "40",
    borderRadius: theme.radius.md,
    paddingHorizontal: 14,
    paddingVertical: 10,
    marginBottom: 14,
  },
  errorBoxText: {
    color: theme.colors.danger,
    fontSize: theme.font.size.sm,
    fontWeight: theme.font.weight.medium,
    lineHeight: theme.font.lineHeight.normal,
  },
  fieldLabel: {
    color: theme.colors.text,
    fontSize: theme.font.size.sm,
    fontWeight: theme.font.weight.semibold,
    marginBottom: 7,
    marginTop: 12,
  },
  input: {
    backgroundColor: theme.colors.backgroundSoft,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    paddingHorizontal: 14,
    paddingVertical: 13,
    color: theme.colors.text,
    fontSize: theme.font.size.md,
  },
  passwordRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
  },
  passwordInput: {
    flex: 1,
  },
  toggleButton: {
    width: 44,
    height: 44,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.backgroundSoft,
    borderWidth: 1,
    borderColor: theme.colors.border,
    alignItems: "center",
    justifyContent: "center",
  },
  forgotLink: {
    alignSelf: "flex-end",
    marginTop: 8,
    paddingVertical: 4,
  },
  forgotLinkText: {
    color: theme.colors.primary,
    fontSize: theme.font.size.sm,
    fontWeight: theme.font.weight.semibold,
  },
  primaryButton: {
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    paddingVertical: 15,
    alignItems: "center",
    marginTop: 16,
    ...theme.shadow.card,
  },
  disabledButton: {
    opacity: 0.6,
  },
  primaryText: {
    color: "#ffffff",
    fontWeight: theme.font.weight.bold,
    fontSize: theme.font.size.lg,
  },

  /* Inscription */
  footerCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.xl,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: 18,
    alignItems: "center",
    gap: 12,
    ...theme.shadow.sm,
  },
  footerLabel: {
    color: theme.colors.mutedText,
    fontSize: theme.font.size.md,
    fontWeight: theme.font.weight.medium,
  },
  secondaryButton: {
    width: "100%",
    borderRadius: theme.radius.md,
    borderWidth: 1.5,
    borderColor: theme.colors.primary,
    backgroundColor: theme.colors.primarySoft,
    paddingVertical: 12,
    alignItems: "center",
  },
  secondaryButtonText: {
    color: theme.colors.primary,
    fontWeight: theme.font.weight.bold,
    fontSize: theme.font.size.md,
  },

  /* Pied */
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
});
