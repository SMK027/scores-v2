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
      <Text style={styles.title}>Connexion</Text>

      <TextInput
        value={email}
        onChangeText={setEmail}
        keyboardType="email-address"
        autoCapitalize="none"
        placeholder="Adresse mail"
        style={styles.input}
      />

      <TextInput
        value={password}
        onChangeText={setPassword}
        secureTextEntry
        placeholder="Mot de passe"
        style={styles.input}
      />

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <Pressable
        style={[styles.primaryButton, loading ? styles.disabledButton : undefined]}
        disabled={loading}
        onPress={submit}
      >
        {loading ? <ActivityIndicator color="#ffffff" /> : <Text style={styles.primaryText}>Valider</Text>}
      </Pressable>

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
    justifyContent: "center",
    backgroundColor: theme.colors.background,
  },
  title: {
    fontSize: 28,
    fontWeight: "700",
    color: theme.colors.text,
    marginBottom: 24,
  },
  input: {
    backgroundColor: theme.colors.card,
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
    marginTop: 4,
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
    marginTop: 10,
  },
  backText: {
    color: theme.colors.mutedText,
  },
});
