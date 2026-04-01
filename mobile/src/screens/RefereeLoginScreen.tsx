import { useCallback, useEffect, useMemo, useState } from "react";
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
import { useSafeAreaInsets } from "react-native-safe-area-context";
import {
  ApiError,
  refereeGetAssigned,
  refereeLogin,
  refereeOpenAssigned,
} from "../services/api";
import { useAppTheme } from "../context/ThemeContext";
import type { AppTheme } from "../styles";
import type { RefereeAssignedSession, RefereeSession } from "../types/api";

type Props = {
  token: string | null;
  initialCompetitionId?: number;
  onLogin: (refereeToken: string, session: RefereeSession) => void;
  onBack: () => void;
};

function formatSessionStatus(s: RefereeAssignedSession): string {
  if (!s.is_active) return "Inactive";
  if (s.pause_until && new Date(s.pause_until) > new Date()) return "En pause";
  if (s.is_locked) return "Verrouillée";
  return "Active";
}

export function RefereeLoginScreen({
  token,
  initialCompetitionId,
  onLogin,
  onBack,
}: Props) {
  const { theme } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);
  const insets = useSafeAreaInsets();

  const [tab, setTab] = useState<"free" | "assigned">(
    token ? "assigned" : "free"
  );

  const [competitionId, setCompetitionId] = useState(
    initialCompetitionId ? String(initialCompetitionId) : ""
  );
  const [sessionNumber, setSessionNumber] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [loadingSessionId, setLoadingSessionId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  const [assignedSessions, setAssignedSessions] = useState<RefereeAssignedSession[]>([]);
  const [loadingAssigned, setLoadingAssigned] = useState(false);
  const [assignedError, setAssignedError] = useState<string | null>(null);

  const fetchAssigned = useCallback(async () => {
    if (!token) return;
    setLoadingAssigned(true);
    setAssignedError(null);
    try {
      const result = await refereeGetAssigned(token);
      setAssignedSessions(result.sessions ?? []);
    } catch (err) {
      setAssignedError(
        err instanceof ApiError ? err.message : "Impossible de charger les sessions."
      );
    } finally {
      setLoadingAssigned(false);
    }
  }, [token]);

  useEffect(() => {
    if (tab === "assigned" && token) {
      void fetchAssigned();
    }
  }, [tab, fetchAssigned, token]);

  const handleFreeLogin = async () => {
    setError(null);
    const cid = parseInt(competitionId.trim(), 10);
    const sn = parseInt(sessionNumber.trim(), 10);
    if (!cid || !sn || !password.trim()) {
      setError("Remplissez tous les champs.");
      return;
    }
    setLoading(true);
    try {
      const result = await refereeLogin(cid, sn, password.trim());
      onLogin(result.token, result.session);
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : "Connexion impossible pour le moment."
      );
    } finally {
      setLoading(false);
    }
  };

  const handleOpenAssigned = async (sessionId: number) => {
    if (!token) return;
    setError(null);
    setLoadingSessionId(sessionId);
    try {
      const result = await refereeOpenAssigned(token, sessionId);
      onLogin(result.token, result.session);
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : "Connexion impossible pour le moment."
      );
    } finally {
      setLoadingSessionId(null);
    }
  };

  const switchTab = (next: "free" | "assigned") => {
    setTab(next);
    setError(null);
  };

  return (
    <KeyboardAvoidingView
      style={{ flex: 1, backgroundColor: theme.colors.background }}
      behavior={Platform.OS === "ios" ? "padding" : undefined}
    >
      <ScrollView
        style={styles.container}
        contentContainerStyle={[styles.content, { paddingBottom: insets.bottom + 24 }]}
        keyboardShouldPersistTaps="handled"
      >
        {/* Header */}
        <View style={styles.header}>
          <Pressable onPress={onBack} hitSlop={8} style={styles.backBtn}>
            <Text style={styles.backBtnText}>← Retour</Text>
          </Pressable>
          <Text style={styles.title}>Accès arbitrage</Text>
          <Text style={styles.subtitle}>
            Connectez-vous pour gérer une session de compétition.
          </Text>
        </View>

        {/* Tabs (seulement si connecté) */}
        {token ? (
          <View style={styles.tabs}>
            <Pressable
              style={[styles.tab, tab === "free" && styles.tabActive]}
              onPress={() => switchTab("free")}
            >
              <Text style={[styles.tabText, tab === "free" && styles.tabTextActive]}>
                Accès libre
              </Text>
            </Pressable>
            <Pressable
              style={[styles.tab, tab === "assigned" && styles.tabActive]}
              onPress={() => switchTab("assigned")}
            >
              <Text style={[styles.tabText, tab === "assigned" && styles.tabTextActive]}>
                Mon compte
              </Text>
            </Pressable>
          </View>
        ) : null}

        {/* Bannière d'erreur */}
        {error ? (
          <View style={styles.errorBanner}>
            <Text style={styles.errorText}>{error}</Text>
          </View>
        ) : null}

        {/* Tab : Accès libre */}
        {tab === "free" ? (
          <View style={styles.card}>
            <Text style={styles.cardTitle}>Session d'arbitrage</Text>

            <Text style={styles.fieldLabel}>ID compétition</Text>
            <TextInput
              value={competitionId}
              onChangeText={setCompetitionId}
              keyboardType="numeric"
              placeholder="Numéro de compétition"
              placeholderTextColor={theme.colors.mutedText}
              style={styles.input}
              editable={!loading}
            />

            <Text style={styles.fieldLabel}>Numéro de session</Text>
            <TextInput
              value={sessionNumber}
              onChangeText={setSessionNumber}
              keyboardType="numeric"
              placeholder="Numéro de session"
              placeholderTextColor={theme.colors.mutedText}
              style={styles.input}
              editable={!loading}
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
                editable={!loading}
              />
              <Pressable
                onPress={() => setShowPassword((v) => !v)}
                hitSlop={8}
                style={styles.eyeBtn}
              >
                <Text style={styles.eyeBtnText}>
                  {showPassword ? "Masquer" : "Afficher"}
                </Text>
              </Pressable>
            </View>

            <Pressable
              onPress={handleFreeLogin}
              style={[styles.primaryBtn, loading && styles.btnDisabled]}
              disabled={loading}
            >
              {loading ? (
                <ActivityIndicator color="#fff" size="small" />
              ) : (
                <Text style={styles.primaryBtnText}>Se connecter</Text>
              )}
            </Pressable>
          </View>
        ) : null}

        {/* Tab : Mon compte (sessions assignées) */}
        {tab === "assigned" ? (
          <View>
            {loadingAssigned ? (
              <View style={styles.centered}>
                <ActivityIndicator color={theme.colors.primary} />
              </View>
            ) : assignedError ? (
              <View style={styles.errorBanner}>
                <Text style={styles.errorText}>{assignedError}</Text>
              </View>
            ) : assignedSessions.length === 0 ? (
              <View style={styles.emptyBox}>
                <Text style={styles.emptyTitle}>Aucune session assignée</Text>
                <Text style={styles.emptyText}>
                  Demandez à votre administrateur de vous assigner une session d'arbitrage.
                </Text>
              </View>
            ) : (
              assignedSessions.map((s) => {
                const statusLabel = formatSessionStatus(s);
                const isActive = s.is_active && !s.is_locked;
                return (
                  <View key={s.session_id} style={styles.sessionCard}>
                    <View style={styles.sessionCardInfo}>
                      <Text style={styles.sessionName}>{s.competition_name}</Text>
                      <Text style={styles.sessionMeta}>
                        Session n°{s.session_number} · {s.space_name}
                      </Text>
                      <Text style={styles.sessionMeta}>
                        {s.game_count} partie{s.game_count !== 1 ? "s" : ""} · {s.referee_name}
                      </Text>
                      <View
                        style={[
                          styles.statusBadge,
                          isActive ? styles.statusBadgeActive : styles.statusBadgeInactive,
                        ]}
                      >
                        <Text
                          style={[
                            styles.statusBadgeText,
                            isActive ? styles.statusBadgeTextActive : styles.statusBadgeTextInactive,
                          ]}
                        >
                          {statusLabel}
                        </Text>
                      </View>
                    </View>
                    <Pressable
                      onPress={() => void handleOpenAssigned(s.session_id)}
                      style={[
                        styles.sessionBtn,
                        (loadingSessionId === s.session_id || loadingSessionId !== null) &&
                          styles.btnDisabled,
                      ]}
                      disabled={loadingSessionId !== null}
                    >
                      {loadingSessionId === s.session_id ? (
                        <ActivityIndicator color="#fff" size="small" />
                      ) : (
                        <Text style={styles.sessionBtnText}>Accéder →</Text>
                      )}
                    </Pressable>
                  </View>
                );
              })
            )}
          </View>
        ) : null}
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const createStyles = (theme: AppTheme) =>
  StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.colors.background },
    content: { padding: theme.spacing.lg },
    header: { marginBottom: theme.spacing.lg },
    backBtn: { marginBottom: theme.spacing.md },
    backBtnText: { color: theme.colors.primary, fontSize: 15 },
    title: { fontSize: 24, fontWeight: "700", color: theme.colors.text, marginBottom: 6 },
    subtitle: { fontSize: 14, color: theme.colors.mutedText },
    tabs: {
      flexDirection: "row",
      backgroundColor: theme.colors.backgroundSoft,
      borderRadius: theme.radius.md,
      padding: 4,
      marginBottom: theme.spacing.lg,
    },
    tab: {
      flex: 1,
      paddingVertical: 8,
      alignItems: "center",
      borderRadius: theme.radius.sm,
    },
    tabActive: { backgroundColor: theme.colors.card },
    tabText: { fontSize: 14, color: theme.colors.mutedText, fontWeight: "500" },
    tabTextActive: { color: theme.colors.text, fontWeight: "600" },
    errorBanner: {
      backgroundColor: theme.colors.danger + "20",
      borderRadius: theme.radius.md,
      padding: theme.spacing.md,
      marginBottom: theme.spacing.md,
      borderLeftWidth: 3,
      borderLeftColor: theme.colors.danger,
    },
    errorText: { color: theme.colors.danger, fontSize: 14 },
    card: {
      backgroundColor: theme.colors.card,
      borderRadius: theme.radius.lg,
      padding: theme.spacing.lg,
      ...theme.shadow.card,
    },
    cardTitle: {
      fontSize: 16,
      fontWeight: "600",
      color: theme.colors.text,
      marginBottom: theme.spacing.md,
    },
    fieldLabel: {
      fontSize: 13,
      fontWeight: "500",
      color: theme.colors.mutedText,
      marginBottom: 4,
      marginTop: theme.spacing.sm,
    },
    input: {
      backgroundColor: theme.colors.backgroundSoft,
      borderWidth: 1,
      borderColor: theme.colors.border,
      borderRadius: theme.radius.md,
      paddingHorizontal: theme.spacing.md,
      paddingVertical: 10,
      color: theme.colors.text,
      fontSize: 15,
      marginBottom: 12,
    },
    passwordRow: { flexDirection: "row", alignItems: "center", gap: 8, marginBottom: 12 },
    passwordInput: { flex: 1, marginBottom: 0 },
    eyeBtn: { paddingHorizontal: 12, paddingVertical: 10 },
    eyeBtnText: { color: theme.colors.primary, fontSize: 13 },
    primaryBtn: {
      backgroundColor: theme.colors.primary,
      borderRadius: theme.radius.md,
      paddingVertical: 12,
      alignItems: "center",
      marginTop: theme.spacing.sm,
    },
    btnDisabled: { opacity: 0.6 },
    primaryBtnText: { color: "#fff", fontWeight: "600", fontSize: 15 },
    centered: { paddingVertical: 40, alignItems: "center" },
    emptyBox: {
      backgroundColor: theme.colors.card,
      borderRadius: theme.radius.lg,
      padding: theme.spacing.xl,
      alignItems: "center",
    },
    emptyTitle: { fontSize: 16, fontWeight: "600", color: theme.colors.text, marginBottom: 6 },
    emptyText: { color: theme.colors.mutedText, fontSize: 14, textAlign: "center" },
    sessionCard: {
      backgroundColor: theme.colors.card,
      borderRadius: theme.radius.lg,
      padding: theme.spacing.md,
      marginBottom: theme.spacing.sm,
      flexDirection: "row",
      alignItems: "center",
      justifyContent: "space-between",
      ...theme.shadow.card,
    },
    sessionCardInfo: { flex: 1, marginRight: theme.spacing.sm },
    sessionName: { fontSize: 15, fontWeight: "600", color: theme.colors.text, marginBottom: 2 },
    sessionMeta: { fontSize: 12, color: theme.colors.mutedText, marginBottom: 2 },
    statusBadge: {
      alignSelf: "flex-start",
      borderRadius: theme.radius.pill,
      paddingHorizontal: 8,
      paddingVertical: 2,
      marginTop: 4,
    },
    statusBadgeActive: { backgroundColor: theme.colors.success + "22" },
    statusBadgeInactive: { backgroundColor: theme.colors.danger + "22" },
    statusBadgeText: { fontSize: 11, fontWeight: "600" },
    statusBadgeTextActive: { color: theme.colors.success },
    statusBadgeTextInactive: { color: theme.colors.danger },
    sessionBtn: {
      backgroundColor: theme.colors.primary,
      borderRadius: theme.radius.md,
      paddingHorizontal: theme.spacing.md,
      paddingVertical: 10,
      minWidth: 80,
      alignItems: "center",
    },
    sessionBtnText: { color: "#fff", fontWeight: "600", fontSize: 14 },
  });
