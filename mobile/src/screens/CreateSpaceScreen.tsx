import { useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { ApiError, createSpace } from "../services/api";
import { useAppTheme } from "../context/ThemeContext";
import type { AppTheme } from "../styles";
import type { Space } from "../types/api";

type Props = {
  token: string;
  onBack: () => void;
  onCreated: (space: Space) => void;
};

const NAME_MAX = 100;
const DESC_MAX = 280;

const TIPS = [
  "Choisissez un nom court et mémorable.",
  "Un bon nom décrit l'activité ou le groupe.",
  "Exemples : Soirée belote, Tournoi ping-pong, Équipe A vs B…",
];

export function CreateSpaceScreen({ token, onBack, onCreated }: Props) {
  const { theme } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);

  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [nameError, setNameError] = useState<string | null>(null);

  const descriptionRef = useRef<TextInput>(null);

  const nameProgress = name.trim().length / NAME_MAX;
  const descProgress = description.trim().length / DESC_MAX;

  const validate = (): boolean => {
    const trimmed = name.trim();
    if (!trimmed) {
      setNameError("Le nom de l'espace est requis.");
      return false;
    }
    if (trimmed.length < 2) {
      setNameError("Le nom doit contenir au moins 2 caractères.");
      return false;
    }
    setNameError(null);
    return true;
  };

  const handleSubmit = async () => {
    if (!validate()) {
      return;
    }

    try {
      setSaving(true);
      setError(null);
      const space = await createSpace(token, {
        name: name.trim(),
        description: description.trim() || undefined,
      });
      onCreated(space);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de créer l'espace. Veuillez réessayer.");
      }
    } finally {
      setSaving(false);
    }
  };

  const nameProgressColor =
    nameProgress > 0.9 ? theme.colors.danger : nameProgress > 0.7 ? theme.colors.warning : theme.colors.success;

  const canSubmit = name.trim().length >= 2 && !saving;

  return (
    <KeyboardAvoidingView
      style={styles.flex}
      behavior={Platform.OS === "ios" ? "padding" : "height"}
      keyboardVerticalOffset={Platform.OS === "ios" ? 0 : 20}
    >
      <View style={styles.header}>
        <Pressable style={styles.navButton} onPress={onBack}>
          <Text style={styles.navButtonText}>← Retour</Text>
        </Pressable>
        <Text style={styles.headerTitle}>Nouvel espace</Text>
        <View style={styles.navButtonPlaceholder} />
      </View>

      <ScrollView
        style={styles.scroll}
        contentContainerStyle={styles.scrollContent}
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}
      >
        {/* Hero */}
        <View style={styles.heroSection}>
          <View style={styles.heroIcon}>
            <Text style={styles.heroIconText}>＋</Text>
          </View>
          <Text style={styles.heroTitle}>Créer un espace de jeu</Text>
          <Text style={styles.heroSubtitle}>
            Un espace regroupe vos parties, joueurs et compétitions au même endroit.
          </Text>
        </View>

        {/* Formulaire */}
        <View style={styles.formCard}>
          {/* Nom */}
          <View style={styles.fieldGroup}>
            <View style={styles.fieldHeader}>
              <Text style={styles.fieldLabel}>Nom de l'espace</Text>
              <Text style={[styles.fieldCounter, { color: nameProgressColor }]}>
                {name.trim().length}/{NAME_MAX}
              </Text>
            </View>
            <TextInput
              value={name}
              onChangeText={(v) => {
                setName(v);
                if (nameError) {
                  setNameError(null);
                }
              }}
              placeholder="Ex: Soirée belote, Tournoi ping-pong…"
              placeholderTextColor={theme.colors.mutedText}
              style={[styles.input, nameError ? styles.inputError : undefined]}
              maxLength={NAME_MAX}
              returnKeyType="next"
              onSubmitEditing={() => descriptionRef.current?.focus()}
              autoFocus
            />
            {/* Barre de progression du nom */}
            <View style={styles.progressTrack}>
              <View
                style={[
                  styles.progressBar,
                  {
                    width: `${Math.round(nameProgress * 100)}%` as `${number}%`,
                    backgroundColor: nameProgressColor,
                  },
                ]}
              />
            </View>
            {nameError ? <Text style={styles.fieldError}>{nameError}</Text> : null}
          </View>

          {/* Description */}
          <View style={styles.fieldGroup}>
            <View style={styles.fieldHeader}>
              <Text style={styles.fieldLabel}>
                Description{" "}
                <Text style={styles.fieldOptional}>(optionnelle)</Text>
              </Text>
              <Text style={styles.fieldCounter}>
                {description.trim().length}/{DESC_MAX}
              </Text>
            </View>
            <TextInput
              ref={descriptionRef}
              value={description}
              onChangeText={setDescription}
              placeholder="Ex : Parties du vendredi soir entre collègues…"
              placeholderTextColor={theme.colors.mutedText}
              style={[styles.input, styles.inputMultiline]}
              maxLength={DESC_MAX}
              multiline
              textAlignVertical="top"
              returnKeyType="done"
            />
            {description.trim().length > 0 ? (
              <View style={styles.progressTrack}>
                <View
                  style={[
                    styles.progressBar,
                    {
                      width: `${Math.round(descProgress * 100)}%` as `${number}%`,
                      backgroundColor:
                        descProgress > 0.9
                          ? theme.colors.danger
                          : descProgress > 0.7
                            ? theme.colors.warning
                            : theme.colors.primary,
                    },
                  ]}
                />
              </View>
            ) : null}
          </View>

          {/* Erreur globale */}
          {error ? (
            <View style={styles.errorBanner}>
              <Text style={styles.errorBannerText}>{error}</Text>
            </View>
          ) : null}
        </View>

        {/* Tips */}
        <View style={styles.tipsCard}>
          <Text style={styles.tipsTitle}>Conseils</Text>
          {TIPS.map((tip, index) => (
            <View key={index} style={styles.tipRow}>
              <View style={styles.tipDot} />
              <Text style={styles.tipText}>{tip}</Text>
            </View>
          ))}
        </View>
      </ScrollView>

      {/* Footer fixe */}
      <View style={styles.footer}>
        <Pressable style={styles.cancelButton} onPress={onBack} disabled={saving}>
          <Text style={styles.cancelButtonText}>Annuler</Text>
        </Pressable>
        <Pressable
          style={[styles.submitButton, !canSubmit ? styles.submitButtonDisabled : undefined]}
          onPress={handleSubmit}
          disabled={!canSubmit}
        >
          {saving ? (
            <ActivityIndicator size="small" color="#ffffff" />
          ) : (
            <Text style={styles.submitButtonText}>Créer l'espace</Text>
          )}
        </Pressable>
      </View>
    </KeyboardAvoidingView>
  );
}

const createStyles = (theme: AppTheme) =>
  StyleSheet.create({
    flex: {
      flex: 1,
      backgroundColor: theme.colors.background,
    },
    header: {
      flexDirection: "row",
      alignItems: "center",
      justifyContent: "space-between",
      paddingHorizontal: 16,
      paddingTop: 16,
      paddingBottom: 10,
      borderBottomWidth: 1,
      borderBottomColor: theme.colors.border,
      backgroundColor: theme.colors.background,
    },
    navButton: {
      borderWidth: 1,
      borderColor: theme.colors.border,
      backgroundColor: theme.colors.card,
      borderRadius: theme.radius.pill,
      paddingHorizontal: 14,
      paddingVertical: 8,
    },
    navButtonText: {
      color: theme.colors.primary,
      fontWeight: "700",
      fontSize: 13,
    },
    navButtonPlaceholder: {
      width: 80,
    },
    headerTitle: {
      color: theme.colors.text,
      fontWeight: "800",
      fontSize: 17,
    },
    scroll: {
      flex: 1,
    },
    scrollContent: {
      padding: 20,
      paddingBottom: 24,
      gap: 16,
    },
    /* Hero */
    heroSection: {
      alignItems: "center",
      paddingVertical: 12,
      gap: 10,
    },
    heroIcon: {
      width: 72,
      height: 72,
      borderRadius: 36,
      backgroundColor: theme.colors.primarySoft,
      alignItems: "center",
      justifyContent: "center",
    },
    heroIconText: {
      fontSize: 32,
      color: theme.colors.primary,
      fontWeight: "700",
    },
    heroTitle: {
      fontSize: 22,
      fontWeight: "800",
      color: theme.colors.text,
      textAlign: "center",
    },
    heroSubtitle: {
      color: theme.colors.mutedText,
      textAlign: "center",
      maxWidth: 280,
      lineHeight: 20,
    },
    /* Formulaire */
    formCard: {
      backgroundColor: theme.colors.card,
      borderRadius: theme.radius.xl,
      borderWidth: 1,
      borderColor: theme.colors.border,
      padding: 18,
      gap: 16,
      ...theme.shadow.card,
    },
    fieldGroup: {
      gap: 6,
    },
    fieldHeader: {
      flexDirection: "row",
      justifyContent: "space-between",
      alignItems: "center",
    },
    fieldLabel: {
      color: theme.colors.text,
      fontWeight: "700",
      fontSize: 14,
    },
    fieldOptional: {
      color: theme.colors.mutedText,
      fontWeight: "400",
      fontSize: 13,
    },
    fieldCounter: {
      color: theme.colors.mutedText,
      fontSize: 12,
    },
    input: {
      backgroundColor: theme.colors.backgroundSoft,
      borderWidth: 1,
      borderColor: theme.colors.border,
      borderRadius: theme.radius.md,
      paddingHorizontal: 14,
      paddingVertical: 12,
      color: theme.colors.text,
      fontSize: 15,
    },
    inputError: {
      borderColor: theme.colors.danger,
      backgroundColor: theme.colors.backgroundSoft,
    },
    inputMultiline: {
      minHeight: 90,
      paddingTop: 12,
    },
    progressTrack: {
      height: 3,
      backgroundColor: theme.colors.border,
      borderRadius: 2,
      overflow: "hidden",
      marginTop: 4,
    },
    progressBar: {
      height: "100%",
      borderRadius: 2,
    },
    fieldError: {
      color: theme.colors.danger,
      fontSize: 12,
      fontWeight: "600",
    },
    errorBanner: {
      backgroundColor: theme.colors.danger + "18",
      borderWidth: 1,
      borderColor: theme.colors.danger + "44",
      borderRadius: theme.radius.md,
      padding: 12,
    },
    errorBannerText: {
      color: theme.colors.danger,
      fontWeight: "600",
      fontSize: 13,
    },
    /* Tips */
    tipsCard: {
      backgroundColor: theme.colors.primarySoft,
      borderRadius: theme.radius.xl,
      borderWidth: 1,
      borderColor: theme.colors.primary + "33",
      padding: 16,
      gap: 10,
    },
    tipsTitle: {
      color: theme.colors.primary,
      fontWeight: "800",
      fontSize: 14,
      marginBottom: 2,
    },
    tipRow: {
      flexDirection: "row",
      alignItems: "flex-start",
      gap: 10,
    },
    tipDot: {
      width: 6,
      height: 6,
      borderRadius: 3,
      backgroundColor: theme.colors.primary,
      marginTop: 6,
      flexShrink: 0,
    },
    tipText: {
      color: theme.colors.primary,
      flex: 1,
      lineHeight: 20,
      fontSize: 13,
    },
    /* Footer */
    footer: {
      flexDirection: "row",
      padding: 16,
      gap: 12,
      borderTopWidth: 1,
      borderTopColor: theme.colors.border,
      backgroundColor: theme.colors.background,
    },
    cancelButton: {
      flex: 1,
      borderWidth: 1,
      borderColor: theme.colors.border,
      backgroundColor: theme.colors.card,
      borderRadius: theme.radius.md,
      paddingVertical: 14,
      alignItems: "center",
    },
    cancelButtonText: {
      color: theme.colors.mutedText,
      fontWeight: "700",
      fontSize: 15,
    },
    submitButton: {
      flex: 2,
      backgroundColor: theme.colors.primary,
      borderRadius: theme.radius.md,
      paddingVertical: 14,
      alignItems: "center",
    },
    submitButtonDisabled: {
      opacity: 0.5,
    },
    submitButtonText: {
      color: "#ffffff",
      fontWeight: "700",
      fontSize: 15,
    },
  });
