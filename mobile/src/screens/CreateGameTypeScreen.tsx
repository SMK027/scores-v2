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
import { ApiError, createGameType } from "../services/api";
import { useAppTheme } from "../context/ThemeContext";
import type { AppTheme } from "../styles";
import type { GameType, Space } from "../types/api";

type Props = {
  token: string;
  space: Space;
  onBack: () => void;
  onCreated: () => void;
};

const NAME_MAX = 100;

const WIN_CONDITIONS: Array<GameType["win_condition"]> = [
  "highest_score",
  "lowest_score",
  "ranking",
  "win_loss",
];

const TIPS = [
  "Définissez un nom clair pour retrouver rapidement ce format.",
  "Le minimum de joueurs doit être supérieur ou égal à 1.",
  "Laissez le maximum vide pour autoriser un nombre illimité de joueurs.",
];

const getWinConditionLabel = (condition: GameType["win_condition"]): string => {
  switch (condition) {
    case "highest_score":
      return "Score le plus élevé";
    case "lowest_score":
      return "Score le plus faible";
    case "ranking":
      return "Classement";
    case "win_loss":
      return "Victoires / Défaites";
    default:
      return condition;
  }
};

const parsePlayerCount = (value: string, fallback: number): number => {
  const parsed = Number(value.trim());
  if (!Number.isInteger(parsed) || parsed < 1) {
    return fallback;
  }
  return parsed;
};

const parseOptionalPlayerCount = (value: string): number | null => {
  const trimmed = value.trim();
  if (!trimmed) {
    return null;
  }
  const parsed = Number(trimmed);
  if (!Number.isInteger(parsed) || parsed < 1) {
    return null;
  }
  return parsed;
};

export function CreateGameTypeScreen({ token, space, onBack, onCreated }: Props) {
  const { theme } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);

  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [winCondition, setWinCondition] = useState<GameType["win_condition"]>("highest_score");
  const [minPlayers, setMinPlayers] = useState("1");
  const [maxPlayers, setMaxPlayers] = useState("");

  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [nameError, setNameError] = useState<string | null>(null);

  const nameInputRef = useRef<TextInput>(null);

  const nameProgress = name.trim().length / NAME_MAX;
  const nameProgressColor =
    nameProgress > 0.9
      ? theme.colors.danger
      : nameProgress > 0.7
        ? theme.colors.warning
        : theme.colors.success;

  const validate = (): boolean => {
    const trimmed = name.trim();
    if (!trimmed) {
      setNameError("Le nom du type de jeu est requis.");
      return false;
    }
    if (trimmed.length < 2) {
      setNameError("Le nom doit contenir au moins 2 caractères.");
      return false;
    }

    const parsedMin = parsePlayerCount(minPlayers, 1);
    const parsedMax = parseOptionalPlayerCount(maxPlayers);
    if (parsedMax !== null && parsedMax < parsedMin) {
      setError("Le nombre maximum de joueurs doit être supérieur ou égal au minimum.");
      return false;
    }

    setNameError(null);
    return true;
  };

  const handleSubmit = async () => {
    if (!validate()) {
      return;
    }

    const parsedMin = parsePlayerCount(minPlayers, 1);
    const parsedMax = parseOptionalPlayerCount(maxPlayers);

    try {
      setSaving(true);
      setError(null);
      await createGameType(token, space.id, {
        name: name.trim(),
        description,
        winCondition,
        minPlayers: parsedMin,
        maxPlayers: parsedMax,
      });
      onCreated();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de créer le type de jeu. Veuillez réessayer.");
      }
    } finally {
      setSaving(false);
    }
  };

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
        <Text style={styles.headerTitle}>Nouveau type</Text>
        <View style={styles.navButtonPlaceholder} />
      </View>

      <ScrollView
        style={styles.scroll}
        contentContainerStyle={styles.scrollContent}
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.heroSection}>
          <View style={styles.heroIcon}>
            <Text style={styles.heroIconText}>🧩</Text>
          </View>
          <Text style={styles.heroTitle}>Créer un type de jeu</Text>
          <Text style={styles.heroSubtitle}>
            Définissez un format pour l'espace <Text style={styles.heroSpaceName}>{space.name}</Text>
          </Text>
        </View>

        <View style={styles.formCard}>
          <View style={styles.fieldGroup}>
            <View style={styles.fieldHeader}>
              <Text style={styles.fieldLabel}>Nom du type</Text>
              <Text style={[styles.fieldCounter, { color: nameProgressColor }]}> 
                {name.trim().length}/{NAME_MAX}
              </Text>
            </View>
            <TextInput
              ref={nameInputRef}
              value={name}
              onChangeText={(v) => {
                setName(v);
                if (nameError) {
                  setNameError(null);
                }
              }}
              placeholder="Ex : Scrabble classique, Rapid chess..."
              placeholderTextColor={theme.colors.mutedText}
              style={[styles.input, nameError ? styles.inputError : undefined]}
              maxLength={NAME_MAX}
              returnKeyType="next"
              autoFocus
            />
            {name.trim().length > 0 ? (
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
            ) : null}
            {nameError ? <Text style={styles.fieldError}>{nameError}</Text> : null}
          </View>

          <View style={styles.fieldGroup}>
            <Text style={styles.fieldLabel}>Description</Text>
            <TextInput
              value={description}
              onChangeText={setDescription}
              placeholder="Description optionnelle"
              placeholderTextColor={theme.colors.mutedText}
              multiline
              style={[styles.input, styles.notes]}
            />
          </View>

          <View style={styles.fieldGroup}>
            <Text style={styles.fieldLabel}>Condition de victoire</Text>
            <View style={styles.conditionOptionsRow}>
              {WIN_CONDITIONS.map((condition) => (
                <Pressable
                  key={condition}
                  style={[
                    styles.conditionOption,
                    winCondition === condition ? styles.conditionOptionActive : undefined,
                  ]}
                  onPress={() => setWinCondition(condition)}
                >
                  <Text
                    style={[
                      styles.conditionOptionText,
                      winCondition === condition ? styles.conditionOptionTextActive : undefined,
                    ]}
                  >
                    {getWinConditionLabel(condition)}
                  </Text>
                </Pressable>
              ))}
            </View>
          </View>

          <View style={styles.countInputsRow}>
            <View style={styles.countInputBlock}>
              <Text style={styles.fieldLabel}>Joueurs min.</Text>
              <TextInput
                value={minPlayers}
                onChangeText={setMinPlayers}
                keyboardType="numeric"
                style={styles.input}
              />
            </View>
            <View style={styles.countInputBlock}>
              <Text style={styles.fieldLabel}>Joueurs max.</Text>
              <TextInput
                value={maxPlayers}
                onChangeText={setMaxPlayers}
                keyboardType="numeric"
                placeholder="Illimité"
                placeholderTextColor={theme.colors.mutedText}
                style={styles.input}
              />
            </View>
          </View>

          {error ? (
            <View style={styles.errorBanner}>
              <Text style={styles.errorBannerText}>{error}</Text>
            </View>
          ) : null}
        </View>

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
            <Text style={styles.submitButtonText}>Créer le type</Text>
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
      maxWidth: 300,
      lineHeight: 20,
    },
    heroSpaceName: {
      color: theme.colors.primary,
      fontWeight: "700",
    },
    formCard: {
      backgroundColor: theme.colors.card,
      borderRadius: theme.radius.xl,
      borderWidth: 1,
      borderColor: theme.colors.border,
      padding: 18,
      gap: 18,
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
    fieldCounter: {
      fontWeight: "700",
      fontSize: 12,
    },
    input: {
      borderWidth: 1,
      borderColor: theme.colors.border,
      borderRadius: theme.radius.md,
      paddingHorizontal: 12,
      paddingVertical: 10,
      color: theme.colors.text,
      backgroundColor: theme.colors.background,
    },
    inputError: {
      borderColor: theme.colors.danger,
    },
    notes: {
      minHeight: 86,
      textAlignVertical: "top",
    },
    progressTrack: {
      marginTop: 4,
      height: 6,
      borderRadius: 999,
      backgroundColor: theme.colors.primarySoft,
      overflow: "hidden",
    },
    progressBar: {
      height: "100%",
      borderRadius: 999,
    },
    fieldError: {
      color: theme.colors.danger,
      fontSize: 12,
      fontWeight: "600",
    },
    conditionOptionsRow: {
      flexDirection: "row",
      flexWrap: "wrap",
      gap: 8,
    },
    conditionOption: {
      borderWidth: 1,
      borderColor: theme.colors.border,
      borderRadius: theme.radius.md,
      paddingVertical: 8,
      paddingHorizontal: 10,
      backgroundColor: theme.colors.background,
    },
    conditionOptionActive: {
      borderColor: theme.colors.primary,
      backgroundColor: theme.colors.primarySoft,
    },
    conditionOptionText: {
      color: theme.colors.mutedText,
      fontWeight: "600",
      fontSize: 12,
    },
    conditionOptionTextActive: {
      color: theme.colors.primary,
    },
    countInputsRow: {
      flexDirection: "row",
      gap: 10,
    },
    countInputBlock: {
      flex: 1,
      gap: 6,
    },
    errorBanner: {
      borderWidth: 1,
      borderColor: theme.colors.danger,
      backgroundColor: theme.colors.backgroundSoft,
      borderRadius: theme.radius.md,
      padding: 10,
    },
    errorBannerText: {
      color: theme.colors.danger,
      fontWeight: "600",
    },
    tipsCard: {
      borderWidth: 1,
      borderColor: theme.colors.border,
      backgroundColor: theme.colors.card,
      borderRadius: theme.radius.lg,
      padding: 16,
      gap: 10,
    },
    tipsTitle: {
      color: theme.colors.text,
      fontWeight: "800",
      fontSize: 15,
    },
    tipRow: {
      flexDirection: "row",
      alignItems: "flex-start",
      gap: 8,
    },
    tipDot: {
      width: 6,
      height: 6,
      borderRadius: 3,
      marginTop: 6,
      backgroundColor: theme.colors.primary,
    },
    tipText: {
      flex: 1,
      color: theme.colors.mutedText,
      lineHeight: 20,
    },
    footer: {
      borderTopWidth: 1,
      borderTopColor: theme.colors.border,
      backgroundColor: theme.colors.card,
      paddingHorizontal: 16,
      paddingVertical: 12,
      flexDirection: "row",
      gap: 10,
    },
    cancelButton: {
      flex: 1,
      borderWidth: 1,
      borderColor: theme.colors.border,
      borderRadius: theme.radius.md,
      alignItems: "center",
      justifyContent: "center",
      paddingVertical: 12,
      backgroundColor: theme.colors.background,
    },
    cancelButtonText: {
      color: theme.colors.mutedText,
      fontWeight: "700",
    },
    submitButton: {
      flex: 1.4,
      borderRadius: theme.radius.md,
      alignItems: "center",
      justifyContent: "center",
      paddingVertical: 12,
      backgroundColor: theme.colors.primary,
    },
    submitButtonDisabled: {
      opacity: 0.5,
    },
    submitButtonText: {
      color: "#ffffff",
      fontWeight: "800",
      fontSize: 14,
    },
  });
