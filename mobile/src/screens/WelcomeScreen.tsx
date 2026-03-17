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
      <Text style={styles.title}>Scores Mobile</Text>
      <Text style={styles.subtitle}>
        Enregistrez vos parties rapidement depuis votre telephone.
      </Text>

      <Pressable style={styles.primaryButton} onPress={onLoginPress}>
        <Text style={styles.primaryButtonText}>Se connecter</Text>
      </Pressable>

      <Pressable style={styles.secondaryButton} onPress={openRegister}>
        <Text style={styles.secondaryButtonText}>S'inscrire</Text>
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
    fontSize: 32,
    fontWeight: "700",
    color: theme.colors.text,
  },
  subtitle: {
    marginTop: 12,
    marginBottom: 28,
    fontSize: 16,
    color: theme.colors.mutedText,
  },
  primaryButton: {
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    paddingVertical: 14,
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
