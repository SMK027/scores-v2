import { useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { ApiError, login } from "../services/api";
import type { User } from "../types/api";
import { theme } from "../styles/theme";

type Props = {
  onBack: () => void;
  onLoginSuccess: (payload: { token: string; user: User }) => void;
};

export function LoginScreen({ onBack, onLoginSuccess }: Props) {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

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

  return (
    <View style={styles.container}>
      <View>
        <Text style={styles.kicker}>Bon retour</Text>
        <Text style={styles.title}>Connexion</Text>
        <Text style={styles.subtitle}>Connectez-vous pour accéder à vos espaces et statistiques.</Text>
      </View>

      <View style={styles.card}>
        <Text style={styles.fieldLabel}>Adresse mail</Text>
        <TextInput
          value={email}
          onChangeText={setEmail}
          keyboardType="email-address"
          autoCapitalize="none"
          placeholder="vous@domaine.com"
          style={styles.input}
        />

        <Text style={styles.fieldLabel}>Mot de passe</Text>
        <TextInput
          value={password}
          onChangeText={setPassword}
          secureTextEntry
          placeholder="Votre mot de passe"
          style={styles.input}
        />

        {error ? <Text style={styles.error}>{error}</Text> : null}

        <Pressable
          style={[styles.primaryButton, loading ? styles.disabledButton : undefined]}
          disabled={loading}
          onPress={submit}
        >
          {loading ? <ActivityIndicator color="#ffffff" /> : <Text style={styles.primaryText}>Se connecter</Text>}
        </Pressable>
      </View>

      <Pressable style={styles.backButton} onPress={onBack}>
        <Text style={styles.backText}>Retour</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    padding: 20,
    justifyContent: "space-between",
    backgroundColor: theme.colors.background,
  },
  kicker: {
    marginTop: 18,
    color: theme.colors.primary,
    fontWeight: "700",
    fontSize: 13,
  },
  title: {
    fontSize: 34,
    fontWeight: "800",
    color: theme.colors.text,
    marginTop: 4,
  },
  subtitle: {
    marginTop: 8,
    color: theme.colors.mutedText,
    fontSize: 15,
    lineHeight: 22,
  },
  card: {
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
  backButton: {
    alignItems: "center",
    paddingVertical: 12,
    marginBottom: 4,
  },
  backText: {
    color: theme.colors.mutedText,
  },
});
