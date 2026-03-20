import { useCallback, useEffect, useMemo, useRef, useState } from "react";
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
import {
  ApiError,
  createPlayer,
  fetchPlayers,
  fetchSpaceMembers,
} from "../services/api";
import { AutocompleteSelect } from "../components/AutocompleteSelect";
import { useAppTheme } from "../context/ThemeContext";
import type { AppTheme } from "../styles";
import type { Player, Space, SpaceMember } from "../types/api";

type Props = {
  token: string;
  space: Space;
  onBack: () => void;
  onCreated: () => void;
};

const NAME_MAX = 100;

const TIPS = [
  "Le nom peut être un pseudo, un prénom ou un surnom.",
  "Liez le joueur à un compte membre pour qu'il suive ses propres stats.",
  "Un espace peut accueillir autant de joueurs que nécessaire.",
];

export function CreatePlayerScreen({ token, space, onBack, onCreated }: Props) {
  const { theme } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);

  const [name, setName] = useState("");
  const [memberQuery, setMemberQuery] = useState("");
  const [selectedUserId, setSelectedUserId] = useState<number | null>(null);

  const [members, setMembers] = useState<SpaceMember[]>([]);
  const [linkedUserIds, setLinkedUserIds] = useState<Set<number>>(new Set());
  const [dataLoading, setDataLoading] = useState(true);
  const [dataError, setDataError] = useState<string | null>(null);

  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [nameError, setNameError] = useState<string | null>(null);

  const nameInputRef = useRef<TextInput>(null);

  const loadData = useCallback(async () => {
    try {
      setDataLoading(true);
      setDataError(null);
      const [fetchedMembers, fetchedPlayers] = await Promise.all([
        fetchSpaceMembers(token, space.id),
        fetchPlayers(token, space.id),
      ]);
      setMembers(fetchedMembers);
      setLinkedUserIds(
        new Set(
          fetchedPlayers
            .map((p) => p.user_id)
            .filter((id): id is number => id !== null && id !== undefined)
        )
      );
    } catch (err) {
      if (err instanceof ApiError) {
        setDataError(err.message);
      } else {
        setDataError("Impossible de charger les données de l'espace.");
      }
    } finally {
      setDataLoading(false);
    }
  }, [token, space.id]);

  useEffect(() => {
    void loadData();
  }, [loadData]);

  const memberOptions = useMemo(
    () =>
      members
        .filter((m) => !linkedUserIds.has(m.user_id))
        .map((m) => ({ id: m.user_id, label: m.username })),
    [members, linkedUserIds]
  );

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
      setNameError("Le nom du joueur est requis.");
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
      await createPlayer(token, space.id, {
        name: name.trim(),
        userId: selectedUserId,
      });
      onCreated();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de créer le joueur. Veuillez réessayer.");
      }
    } finally {
      setSaving(false);
    }
  };

  const canSubmit = name.trim().length >= 2 && !saving;

  if (dataLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={theme.colors.primary} />
        <Text style={styles.loadingText}>Chargement…</Text>
      </View>
    );
  }

  if (dataError) {
    return (
      <View style={styles.loadingContainer}>
        <Text style={styles.dataErrorText}>{dataError}</Text>
        <Pressable style={styles.retryButton} onPress={loadData}>
          <Text style={styles.retryButtonText}>Réessayer</Text>
        </Pressable>
      </View>
    );
  }

  return (
    <KeyboardAvoidingView
      style={styles.flex}
      behavior={Platform.OS === "ios" ? "padding" : "height"}
      keyboardVerticalOffset={Platform.OS === "ios" ? 0 : 20}
    >
      {/* Header */}
      <View style={styles.header}>
        <Pressable style={styles.navButton} onPress={onBack}>
          <Text style={styles.navButtonText}>← Retour</Text>
        </Pressable>
        <Text style={styles.headerTitle}>Nouveau joueur</Text>
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
            <Text style={styles.heroIconText}>👤</Text>
          </View>
          <Text style={styles.heroTitle}>Ajouter un joueur</Text>
          <Text style={styles.heroSubtitle}>
            Créez un joueur pour l'espace{" "}
            <Text style={styles.heroSpaceName}>{space.name}</Text>
          </Text>
        </View>

        {/* Formulaire */}
        <View style={styles.formCard}>
          {/* Nom */}
          <View style={styles.fieldGroup}>
            <View style={styles.fieldHeader}>
              <Text style={styles.fieldLabel}>Nom du joueur</Text>
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
              placeholder="Ex : Alice, Bob, Équipe rouge…"
              placeholderTextColor={theme.colors.mutedText}
              style={[styles.input, nameError ? styles.inputError : undefined]}
              maxLength={NAME_MAX}
              returnKeyType="done"
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
            {nameError ? (
              <Text style={styles.fieldError}>{nameError}</Text>
            ) : null}
          </View>

          {/* Lien avec un compte membre */}
          <View style={styles.fieldGroup}>
            <Text style={styles.fieldLabel}>
              Compte membre{" "}
              <Text style={styles.fieldOptional}>(optionnel)</Text>
            </Text>
            <Text style={styles.fieldHint}>
              Rattachez ce joueur à un compte membre de l'espace pour qu'il
              accède à ses statistiques personnelles.
            </Text>
            <AutocompleteSelect
              label=""
              query={memberQuery}
              onQueryChange={setMemberQuery}
              options={memberOptions}
              onSelect={(id) => {
                const member = members.find((m) => m.user_id === id);
                setSelectedUserId(id);
                setMemberQuery(member ? member.username : "");
              }}
              placeholder="Rechercher un membre…"
            />
            {selectedUserId ? (
              <Pressable
                style={styles.unlinkButton}
                onPress={() => {
                  setSelectedUserId(null);
                  setMemberQuery("");
                }}
              >
                <Text style={styles.unlinkButtonText}>✕ Retirer la liaison</Text>
              </Pressable>
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
        <Pressable
          style={styles.cancelButton}
          onPress={onBack}
          disabled={saving}
        >
          <Text style={styles.cancelButtonText}>Annuler</Text>
        </Pressable>
        <Pressable
          style={[
            styles.submitButton,
            !canSubmit ? styles.submitButtonDisabled : undefined,
          ]}
          onPress={handleSubmit}
          disabled={!canSubmit}
        >
          {saving ? (
            <ActivityIndicator size="small" color="#ffffff" />
          ) : (
            <Text style={styles.submitButtonText}>Ajouter le joueur</Text>
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
    loadingContainer: {
      flex: 1,
      alignItems: "center",
      justifyContent: "center",
      backgroundColor: theme.colors.background,
      gap: 14,
    },
    loadingText: {
      color: theme.colors.mutedText,
      fontSize: 14,
    },
    dataErrorText: {
      color: theme.colors.danger,
      textAlign: "center",
      paddingHorizontal: 24,
      fontWeight: "600",
    },
    retryButton: {
      borderWidth: 1,
      borderColor: theme.colors.primary,
      borderRadius: theme.radius.md,
      paddingHorizontal: 18,
      paddingVertical: 10,
    },
    retryButtonText: {
      color: theme.colors.primary,
      fontWeight: "700",
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
    heroSpaceName: {
      color: theme.colors.primary,
      fontWeight: "700",
    },
    /* Formulaire */
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
    fieldOptional: {
      color: theme.colors.mutedText,
      fontWeight: "400",
      fontSize: 13,
    },
    fieldHint: {
      color: theme.colors.mutedText,
      fontSize: 12,
      lineHeight: 17,
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
    unlinkButton: {
      alignSelf: "flex-start",
      borderWidth: 1,
      borderColor: theme.colors.danger + "55",
      borderRadius: theme.radius.pill,
      paddingHorizontal: 12,
      paddingVertical: 6,
      marginTop: 4,
    },
    unlinkButtonText: {
      color: theme.colors.danger,
      fontSize: 12,
      fontWeight: "700",
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
