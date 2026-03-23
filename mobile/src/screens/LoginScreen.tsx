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
      <View>
        <View style={styles.logoCircle}>
          <Text style={styles.logoGlyph}>#</Text>
        </View>
        <Text style={styles.title}>Scores</Text>
        <Text style={styles.subtitle}>Connectez-vous pour accéder à vos espaces et vos parties.</Text>
      </View>

      <View style={styles.card}>
        <Text style={styles.fieldLabel}>Email</Text>
        <TextInput
          value={email}
          onChangeText={setEmail}
          keyboardType="email-address"
          autoCapitalize="none"
          placeholder="Email"
          placeholderTextColor={theme.colors.mutedText}
          style={styles.input}
        />

        <Text style={styles.fieldLabel}>Mot de passe</Text>
        <View style={styles.passwordRow}>
          <TextInput
            value={password}
            onChangeText={setPassword}
            secureTextEntry={!showPassword}
            placeholder="Mot de passe"
            placeholderTextColor={theme.colors.mutedText}
            style={[styles.input, styles.passwordInput]}
          />
          <Pressable style={styles.toggleButton} onPress={() => setShowPassword((current) => !current)}>
            <Text style={styles.toggleText}>{showPassword ? "Masquer" : "Afficher"}</Text>
          </Pressable>
        </View>

        {error ? <Text style={styles.error}>{error}</Text> : null}

        <Pressable
          style={[styles.primaryButton, loading ? styles.disabledButton : undefined]}
          disabled={loading}
          onPress={submit}
        >
          {loading ? <ActivityIndicator color="#ffffff" /> : <Text style={styles.primaryText}>Se connecter</Text>}
        </Pressable>

        <Pressable style={styles.textLinkButton} onPress={openForgotPassword}>
          <Text style={styles.textLink}>Mot de passe oublie ?</Text>
        </Pressable>

        <View style={styles.separatorRow}>
          <View style={styles.separatorLine} />
          <Text style={styles.separatorText}>ou</Text>
          <View style={styles.separatorLine} />
        </View>

        <Pressable style={styles.secondaryButton} onPress={openRegister}>
          <Text style={styles.secondaryButtonText}>S'inscrire</Text>
        </Pressable>
      </View>

      <Pressable style={styles.backButton} onPress={onBack}>
        <Text style={styles.backText}>Retour</Text>
      </Pressable>

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
    paddingBottom: 28,
  },
  logoCircle: {
    marginTop: 10,
    width: 68,
    height: 68,
    borderRadius: 34,
    backgroundColor: "#635bff",
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 14,
  },
  logoGlyph: {
    color: "#ffffff",
    fontSize: 30,
    fontWeight: "900",
  },
  title: {
    fontSize: 36,
    fontWeight: "800",
    color: theme.colors.text,
  },
  subtitle: {
    marginTop: 8,
    color: theme.colors.mutedText,
    fontSize: 15,
    lineHeight: 22,
  },
  card: {
    marginTop: 18,
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.xl,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: 18,
    ...theme.shadow.card,
  },
  fieldLabel: {
    color: theme.colors.mutedText,
    fontSize: 13,
    fontWeight: "700",
    marginBottom: 6,
    marginTop: 10,
  },
  input: {
    backgroundColor: theme.colors.backgroundSoft,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    paddingHorizontal: 12,
    paddingVertical: 12,
    marginBottom: 10,
    color: theme.colors.text,
  },
  passwordRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
  },
  passwordInput: {
    flex: 1,
    marginBottom: 0,
  },
  toggleButton: {
    paddingHorizontal: 10,
    paddingVertical: 10,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.primarySoft,
  },
  toggleText: {
    color: theme.colors.primary,
    fontWeight: "700",
    fontSize: 12,
  },
  error: {
    color: theme.colors.danger,
    marginBottom: 10,
  },
  primaryButton: {
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    paddingVertical: 14,
    alignItems: "center",
    marginTop: 12,
  },
  disabledButton: {
    opacity: 0.6,
  },
  primaryText: {
    color: "#ffffff",
    fontWeight: "700",
  },
  textLinkButton: {
    marginTop: 10,
    alignItems: "center",
  },
  textLink: {
    color: theme.colors.primary,
    fontWeight: "600",
  },
  separatorRow: {
    marginTop: 14,
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
  },
  separatorLine: {
    flex: 1,
    height: 1,
    backgroundColor: theme.colors.border,
  },
  separatorText: {
    color: theme.colors.mutedText,
    fontWeight: "600",
  },
  secondaryButton: {
    marginTop: 12,
    borderRadius: theme.radius.md,
    borderWidth: 1,
    borderColor: theme.colors.primary,
    backgroundColor: theme.colors.card,
    paddingVertical: 12,
    alignItems: "center",
  },
  secondaryButtonText: {
    color: theme.colors.primary,
    fontWeight: "700",
  },
  backButton: {
    alignItems: "center",
    paddingVertical: 12,
    marginTop: 10,
  },
  backText: {
    color: theme.colors.mutedText,
  },
  termsButton: {
    alignItems: "center",
    paddingVertical: 6,
  },
  termsText: {
    color: theme.colors.mutedText,
    textDecorationLine: "underline",
    fontSize: 12,
  },
});
