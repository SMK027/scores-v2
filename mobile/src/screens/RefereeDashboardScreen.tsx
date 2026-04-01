import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import {
  ApiError,
  refereeDashboard,
  refereePauseSession,
  refereeCloseSession,
  refereeVerifyCard,
  refereeCreateGame,
  refereeGetGame,
  refereeCreateRound,
  refereeUpdateRoundStatus,
  refereeUpdateScores,
  refereeDeleteRound,
  refereeCompleteGame,
} from "../services/api";
import { useAppTheme } from "../context/ThemeContext";
import type { AppTheme } from "../styles";
import type {
  RefereeDashboardResponse,
  RefereeGame,
  RefereeGameDetail,
  RefereePlayer,
  RefereeRound,
  RefereeSession,
} from "../types/api";

type SubView =
  | { name: "main" }
  | { name: "game-detail"; gameId: number }
  | { name: "new-game" }
  | { name: "verify-card" };

type Props = {
  refereeToken: string;
  session: RefereeSession;
  onClose: () => void;
};

// ─── Utilitaires ─────────────────────────────────────────────────────────────

function formatDT(value?: string | null): string {
  if (!value) return "-";
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleString("fr-FR", {
    day: "2-digit",
    month: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function winConditionLabel(wc: string): string {
  switch (wc) {
    case "highest_score": return "Score le plus élevé";
    case "lowest_score": return "Score le plus faible";
    case "ranking": return "Classement";
    case "win_loss": return "Victoire / Défaite";
    default: return wc;
  }
}

function roundStatusLabel(s: RefereeRound["status"]): string {
  switch (s) {
    case "in_progress": return "En cours";
    case "paused": return "En pause";
    case "completed": return "Terminée";
  }
}

// ─── Composant principal ──────────────────────────────────────────────────────

export function RefereeDashboardScreen({ refereeToken, session, onClose }: Props) {
  const { theme } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);
  const insets = useSafeAreaInsets();

  const [subView, setSubView] = useState<SubView>({ name: "main" });
  const [dashboard, setDashboard] = useState<RefereeDashboardResponse | null>(null);
  const [loadingDashboard, setLoadingDashboard] = useState(true);
  const [dashboardError, setDashboardError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [pauseBanner, setPauseBanner] = useState<{ pause_until: string } | null>(null);

  const loadDashboard = useCallback(async () => {
    setDashboardError(null);
    try {
      const data = await refereeDashboard(refereeToken);
      setDashboard(data);
    } catch (err) {
      if (err instanceof ApiError) {
        const raw = err as ApiError & { data?: Record<string, unknown> };
        if (raw.data && raw.data["paused"] === true) {
          setPauseBanner({ pause_until: String(raw.data["pause_until"] ?? "") });
          return;
        }
        setDashboardError(err.message);
      } else {
        setDashboardError("Impossible de charger le tableau de bord.");
      }
    } finally {
      setLoadingDashboard(false);
      setRefreshing(false);
    }
  }, [refereeToken]);

  useEffect(() => {
    void loadDashboard();
  }, [loadDashboard]);

  const handleRefresh = () => {
    setRefreshing(true);
    void loadDashboard();
  };

  if (subView.name === "game-detail") {
    return (
      <GameDetailView
        refereeToken={refereeToken}
        gameId={subView.gameId}
        players={dashboard?.players ?? []}
        onBack={() => { setSubView({ name: "main" }); void loadDashboard(); }}
        theme={theme}
        styles={styles}
        insets={insets}
      />
    );
  }

  if (subView.name === "new-game") {
    return (
      <NewGameView
        refereeToken={refereeToken}
        dashboard={dashboard}
        onCreated={(gameId) => setSubView({ name: "game-detail", gameId })}
        onBack={() => setSubView({ name: "main" })}
        theme={theme}
        styles={styles}
        insets={insets}
      />
    );
  }

  if (subView.name === "verify-card") {
    return (
      <VerifyCardView
        refereeToken={refereeToken}
        players={dashboard?.players ?? []}
        onBack={() => setSubView({ name: "main" })}
        theme={theme}
        styles={styles}
        insets={insets}
      />
    );
  }

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={[styles.content, { paddingBottom: insets.bottom + 24 }]}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={handleRefresh} tintColor={theme.colors.primary} />
      }
    >
      {/* Header session */}
      <View style={styles.sessionHeader}>
        <View style={styles.sessionHeaderTop}>
          <View style={styles.sessionHeaderInfo}>
            <Text style={styles.sessionHeaderTitle}>{session.competition_name}</Text>
            <Text style={styles.sessionHeaderMeta}>
              Session n°{session.session_number} · {session.referee_name}
            </Text>
          </View>
          <Pressable onPress={onClose} style={styles.closeBtn} hitSlop={8}>
            <Text style={styles.closeBtnText}>✕ Quitter</Text>
          </Pressable>
        </View>
      </View>

      {pauseBanner ? (
        <View style={styles.pauseBanner}>
          <Text style={styles.pauseBannerTitle}>Session en pause</Text>
          <Text style={styles.pauseBannerText}>Reprise prévue le {formatDT(pauseBanner.pause_until)}</Text>
        </View>
      ) : null}

      {dashboardError ? (
        <View style={styles.errorBanner}>
          <Text style={styles.errorText}>{dashboardError}</Text>
        </View>
      ) : null}

      {loadingDashboard ? (
        <View style={styles.centered}>
          <ActivityIndicator color={theme.colors.primary} size="large" />
        </View>
      ) : (
        <>
          <View style={styles.actionsRow}>
            <Pressable style={styles.actionBtn} onPress={() => setSubView({ name: "new-game" })}>
              <Text style={styles.actionBtnText}>+ Nouvelle partie</Text>
            </Pressable>
            <Pressable
              style={[styles.actionBtn, styles.actionBtnSecondary]}
              onPress={() => setSubView({ name: "verify-card" })}
            >
              <Text style={[styles.actionBtnText, styles.actionBtnSecondaryText]}>
                Vérifier une carte
              </Text>
            </Pressable>
          </View>

          <Text style={styles.sectionTitle}>Parties</Text>
          {(dashboard?.games ?? []).length === 0 ? (
            <View style={styles.emptyBox}>
              <Text style={styles.emptyText}>Aucune partie créée pour cette session.</Text>
            </View>
          ) : (
            (dashboard?.games ?? []).map((game) => (
              <GameCard
                key={game.id}
                game={game}
                onPress={() => setSubView({ name: "game-detail", gameId: game.id })}
                styles={styles}
                theme={theme}
              />
            ))
          )}

          <Text style={styles.sectionTitle}>Actions de session</Text>
          <SessionActions
            refereeToken={refereeToken}
            onPause={(pauseUntil) => setPauseBanner({ pause_until: pauseUntil })}
            onClose={onClose}
            styles={styles}
          />
        </>
      )}
    </ScrollView>
  );
}

// ─── Carte de partie ──────────────────────────────────────────────────────────

function GameCard({
  game,
  onPress,
  styles,
  theme,
}: {
  game: RefereeGame;
  onPress: () => void;
  styles: ReturnType<typeof createStyles>;
  theme: AppTheme;
}) {
  const isCompleted = game.status === "completed";
  return (
    <Pressable onPress={onPress} style={styles.gameCard}>
      <View style={styles.gameCardTop}>
        <Text style={styles.gameCardName}>{game.game_type_name}</Text>
        <View style={[styles.gameBadge, isCompleted ? styles.gameBadgeCompleted : styles.gameBadgeActive]}>
          <Text style={[styles.gameBadgeText, isCompleted ? styles.gameBadgeTextCompleted : styles.gameBadgeTextActive]}>
            {isCompleted ? "Terminée" : "En cours"}
          </Text>
        </View>
      </View>
      <Text style={[styles.gameMeta, { color: theme.colors.mutedText }]}>
        {game.player_count} joueur{game.player_count !== 1 ? "s" : ""} · {game.round_count} manche{game.round_count !== 1 ? "s" : ""} · {winConditionLabel(game.win_condition)}
      </Text>
      <Text style={[styles.gameMeta, { color: theme.colors.mutedText }]}>
        Créée le {formatDT(game.created_at)}{game.ended_at ? ` · Terminée le ${formatDT(game.ended_at)}` : ""}
      </Text>
    </Pressable>
  );
}

// ─── Actions de session ───────────────────────────────────────────────────────

function SessionActions({
  refereeToken,
  onPause,
  onClose,
  styles,
}: {
  refereeToken: string;
  onPause: (pauseUntil: string) => void;
  onClose: () => void;
  styles: ReturnType<typeof createStyles>;
}) {
  const [loading, setLoading] = useState(false);

  const handlePause = () => {
    Alert.alert("Mettre en pause", "Durée de la pause :", [
      { text: "30 min", onPress: () => void doPause(30) },
      { text: "60 min", onPress: () => void doPause(60) },
      { text: "120 min", onPress: () => void doPause(120) },
      { text: "Annuler", style: "cancel" },
    ]);
  };

  const doPause = async (minutes: number) => {
    setLoading(true);
    try {
      const res = await refereePauseSession(refereeToken, minutes);
      if (res.pause_until) onPause(res.pause_until);
    } catch (err) {
      Alert.alert("Erreur", err instanceof ApiError ? err.message : "Impossible de mettre en pause.");
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    Alert.alert(
      "Fermer la session",
      "Cette action est irréversible. La session sera définitivement fermée.",
      [
        { text: "Fermer définitivement", style: "destructive", onPress: () => void doClose() },
        { text: "Annuler", style: "cancel" },
      ]
    );
  };

  const doClose = async () => {
    setLoading(true);
    try {
      await refereeCloseSession(refereeToken);
      onClose();
    } catch (err) {
      Alert.alert("Erreur", err instanceof ApiError ? err.message : "Impossible de fermer la session.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={styles.sessionActionsBox}>
      <Pressable onPress={handlePause} style={[styles.warningBtn, loading && styles.btnDisabled]} disabled={loading}>
        <Text style={styles.warningBtnText}>⏸ Mettre en pause</Text>
      </Pressable>
      <Pressable onPress={handleClose} style={[styles.dangerBtn, loading && styles.btnDisabled]} disabled={loading}>
        <Text style={styles.dangerBtnText}>✕ Fermer la session</Text>
      </Pressable>
    </View>
  );
}

// ─── Sous-vue : Détail d'une partie ──────────────────────────────────────────

function GameDetailView({
  refereeToken,
  gameId,
  players,
  onBack,
  theme,
  styles,
  insets,
}: {
  refereeToken: string;
  gameId: number;
  players: RefereePlayer[];
  onBack: () => void;
  theme: AppTheme;
  styles: ReturnType<typeof createStyles>;
  insets: { bottom: number; top: number };
}) {
  const [game, setGame] = useState<RefereeGameDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [editingScores, setEditingScores] = useState<Record<number, Record<number, string>>>({});
  const [editingWinners, setEditingWinners] = useState<Record<number, Record<number, boolean>>>({});

  const loadGame = useCallback(async () => {
    setError(null);
    try {
      const res = await refereeGetGame(refereeToken, gameId);
      setGame(res.game);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Impossible de charger la partie.");
    } finally {
      setLoading(false);
    }
  }, [refereeToken, gameId]);

  useEffect(() => { void loadGame(); }, [loadGame]);

  const doAction = async (fn: () => Promise<void>) => {
    setActionLoading(true);
    setError(null);
    try {
      await fn();
      await loadGame();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Une erreur est survenue.");
    } finally {
      setActionLoading(false);
    }
  };

  const handleCreateRound = () => doAction(async () => { await refereeCreateRound(refereeToken, gameId); });

  const handleRoundStatus = (roundId: number, status: RefereeRound["status"]) =>
    doAction(async () => { await refereeUpdateRoundStatus(refereeToken, gameId, roundId, status); });

  const cancelEditing = (roundId: number) => {
    setEditingScores((prev) => { const next = { ...prev }; delete next[roundId]; return next; });
    setEditingWinners((prev) => { const next = { ...prev }; delete next[roundId]; return next; });
  };

  const toggleNegativeScore = (roundId: number, playerId: number) => {
    setEditingScores((prev) => {
      const val = (prev[roundId]?.[playerId] ?? "").trim();
      const newVal = !val ? "-" : val.startsWith("-") ? val.slice(1) : `-${val}`;
      return { ...prev, [roundId]: { ...(prev[roundId] ?? {}), [playerId]: newVal } };
    });
  };

  const handleSaveScores = async (round: RefereeRound) => {
    const wc = game?.win_condition ?? "highest_score";
    setSaving(true);
    setError(null);
    try {
      const normalizedScores: Record<number, number> = {};

      if (wc === "win_loss") {
        const winMap = editingWinners[round.id] ?? {};
        let winnerCount = 0;
        for (const gp of game?.players ?? []) {
          const isWinner = !!winMap[gp.player_id];
          normalizedScores[gp.player_id] = isWinner ? 1 : 0;
          if (isWinner) winnerCount++;
        }
        if (winnerCount === 0) {
          setError("Sélectionnez au moins un gagnant pour cette manche.");
          return;
        }
      } else {
        const usedRanks: number[] = [];
        for (const gp of game?.players ?? []) {
          const raw = (editingScores[round.id]?.[gp.player_id] ?? "").trim();
          if (!raw || raw === "-") {
            setError("Renseignez une valeur pour chaque joueur.");
            return;
          }
          const value = Number(raw.replace(",", "."));
          if (Number.isNaN(value)) {
            setError("Une valeur contient un format invalide.");
            return;
          }
          if (wc === "ranking") {
            if (!Number.isInteger(value) || value < 1) {
              setError("Le classement doit être un entier positif (1, 2, 3...).");
              return;
            }
            if (usedRanks.includes(value)) {
              setError("Chaque position doit être unique dans le classement.");
              return;
            }
            usedRanks.push(value);
          }
          normalizedScores[gp.player_id] = value;
        }
      }

      await refereeUpdateScores(refereeToken, gameId, round.id, normalizedScores);
      cancelEditing(round.id);
      await loadGame();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Erreur lors de l'enregistrement des scores.");
    } finally {
      setSaving(false);
    }
  };

  const handleDeleteRound = (roundId: number) => {
    Alert.alert("Supprimer la manche", "Raison :", [
      { text: "Erreur de saisie", onPress: () => doAction(async () => { await refereeDeleteRound(refereeToken, gameId, roundId, "Erreur de saisie"); }) },
      { text: "Manche annulée", onPress: () => doAction(async () => { await refereeDeleteRound(refereeToken, gameId, roundId, "Manche annulée"); }) },
      { text: "Annuler", style: "cancel" },
    ]);
  };

  const handleCompleteGame = () => {
    Alert.alert("Finaliser la partie", "Les résultats seront enregistrés définitivement.", [
      { text: "Finaliser", onPress: () => doAction(async () => { await refereeCompleteGame(refereeToken, gameId); }) },
      { text: "Annuler", style: "cancel" },
    ]);
  };

  const startEditingRound = (round: RefereeRound) => {
    const wc = game?.win_condition ?? "highest_score";
    if (wc === "win_loss") {
      const init: Record<number, boolean> = {};
      for (const s of round.scores) { init[s.player_id] = s.won === true; }
      for (const gp of game?.players ?? []) { if (!(gp.player_id in init)) init[gp.player_id] = false; }
      setEditingWinners((prev) => ({ ...prev, [round.id]: init }));
    } else {
      const init: Record<number, string> = {};
      for (const s of round.scores) { init[s.player_id] = s.score !== null ? String(s.score) : ""; }
      for (const gp of game?.players ?? []) { if (!(gp.player_id in init)) init[gp.player_id] = ""; }
      setEditingScores((prev) => ({ ...prev, [round.id]: init }));
    }
  };

  if (loading) {
    return (
      <View style={[styles.container, styles.centered]}>
        <ActivityIndicator color={theme.colors.primary} size="large" />
      </View>
    );
  }

  const isCompleted = game?.status === "completed";
  const winCondition = game?.win_condition ?? "highest_score";

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={[styles.content, { paddingBottom: insets.bottom + 24 }]}
    >
      <View style={styles.header}>
        <Pressable onPress={onBack} hitSlop={8} style={styles.backBtn}>
          <Text style={styles.backBtnText}>← Retour</Text>
        </Pressable>
        <View style={styles.headerTitleRow}>
          <Text style={styles.title}>{game?.game_type_name ?? "Partie"}</Text>
          <View style={[styles.gameBadge, isCompleted ? styles.gameBadgeCompleted : styles.gameBadgeActive]}>
            <Text style={[styles.gameBadgeText, isCompleted ? styles.gameBadgeTextCompleted : styles.gameBadgeTextActive]}>
              {isCompleted ? "Terminée" : "En cours"}
            </Text>
          </View>
        </View>
        <Text style={styles.subtitle}>
          {winConditionLabel(winCondition)} · {game?.players.length ?? 0} joueur{(game?.players.length ?? 0) !== 1 ? "s" : ""}
        </Text>
      </View>

      {error ? <View style={styles.errorBanner}><Text style={styles.errorText}>{error}</Text></View> : null}

      <Text style={styles.sectionTitle}>Joueurs</Text>
      <View style={styles.card}>
        {(game?.players ?? []).map((gp, idx) => (
          <View key={gp.player_id} style={[styles.playerRow, idx < (game?.players.length ?? 0) - 1 && styles.playerRowBorder]}>
            <View style={styles.playerAvatar}>
              <Text style={styles.playerAvatarText}>{gp.name[0]?.toUpperCase() ?? "?"}</Text>
            </View>
            <Text style={styles.playerName}>{gp.name}</Text>
            <View style={styles.playerScores}>
              {gp.total_score !== null ? <Text style={styles.playerScoreValue}>{gp.total_score} pts</Text> : null}
              {gp.rank !== null ? <Text style={styles.playerRank}>#{gp.rank}</Text> : null}
            </View>
          </View>
        ))}
      </View>

      <View style={styles.sectionRow}>
        <Text style={styles.sectionTitle}>Manches</Text>
        {!isCompleted ? (
          <Pressable
            onPress={handleCreateRound}
            style={[styles.smallPrimaryBtn, actionLoading && styles.btnDisabled]}
            disabled={actionLoading}
          >
            {actionLoading ? <ActivityIndicator color="#fff" size="small" /> : <Text style={styles.smallPrimaryBtnText}>+ Manche</Text>}
          </Pressable>
        ) : null}
      </View>

      {(game?.rounds ?? []).length === 0 ? (
        <View style={styles.emptyBox}>
          <Text style={styles.emptyText}>Aucune manche. Créez la première manche pour commencer.</Text>
        </View>
      ) : (
        (game?.rounds ?? []).map((round) => {
          const isEditing = round.id in editingScores || round.id in editingWinners;
          return (
            <View key={round.id} style={styles.roundCard}>
              <View style={styles.roundHeader}>
                <Text style={styles.roundTitle}>Manche {round.round_number}</Text>
                <View style={[styles.roundBadge, round.status === "completed" ? styles.roundBadgeCompleted : round.status === "paused" ? styles.roundBadgePaused : styles.roundBadgeActive]}>
                  <Text style={styles.roundBadgeText}>{roundStatusLabel(round.status)}</Text>
                </View>
              </View>

              {!isCompleted ? (
                <View style={styles.roundActions}>
                  {round.status === "in_progress" ? (
                    <>
                      <Pressable onPress={() => handleRoundStatus(round.id, "paused")} style={styles.roundActionBtn} disabled={actionLoading}>
                        <Text style={styles.roundActionBtnText}>⏸ Pause</Text>
                      </Pressable>
                      <Pressable onPress={() => handleRoundStatus(round.id, "completed")} style={styles.roundActionBtn} disabled={actionLoading}>
                        <Text style={styles.roundActionBtnText}>✓ Terminer</Text>
                      </Pressable>
                    </>
                  ) : round.status === "paused" ? (
                    <Pressable onPress={() => handleRoundStatus(round.id, "in_progress")} style={styles.roundActionBtn} disabled={actionLoading}>
                      <Text style={styles.roundActionBtnText}>▶ Reprendre</Text>
                    </Pressable>
                  ) : null}
                  <Pressable onPress={() => handleDeleteRound(round.id)} style={[styles.roundActionBtn, styles.roundActionBtnDanger]} disabled={actionLoading}>
                    <Text style={styles.roundActionBtnTextDanger}>🗑 Supprimer</Text>
                  </Pressable>
                </View>
              ) : null}

              <View style={styles.scoresBlock}>
                <Text style={styles.scoresLabel}>
                  {winCondition === "win_loss" ? "Résultats" : "Scores"}
                </Text>

                {isEditing ? (
                  winCondition === "win_loss" ? (
                    <>
                      <Text style={styles.scoresSubtitle}>Sélectionner le ou les gagnants</Text>
                      {(game?.players ?? []).map((gp) => {
                        const isWinner = editingWinners[round.id]?.[gp.player_id] ?? false;
                        return (
                          <Pressable
                            key={gp.player_id}
                            style={[styles.winnerRow, isWinner && styles.winnerRowActive]}
                            onPress={() => setEditingWinners((prev) => ({
                              ...prev,
                              [round.id]: { ...(prev[round.id] ?? {}), [gp.player_id]: !isWinner },
                            }))}
                          >
                            <Text style={styles.scoreInputPlayerName}>{gp.name}</Text>
                            <Text style={[styles.winnerLabel, isWinner && styles.winnerLabelActive]}>
                              {isWinner ? "Gagnant ✓" : "Défaite"}
                            </Text>
                          </Pressable>
                        );
                      })}
                      <View style={styles.scoreSaveRow}>
                        <Pressable
                          onPress={() => { void handleSaveScores(round); }}
                          style={[styles.smallPrimaryBtn, saving && styles.btnDisabled]}
                          disabled={saving || actionLoading}
                        >
                          {saving ? <ActivityIndicator color="#fff" size="small" /> : <Text style={styles.smallPrimaryBtnText}>Enregistrer</Text>}
                        </Pressable>
                        <Pressable onPress={() => cancelEditing(round.id)} style={styles.cancelBtn} disabled={saving}>
                          <Text style={styles.cancelBtnText}>Annuler</Text>
                        </Pressable>
                      </View>
                    </>
                  ) : (
                    <>
                      <Text style={styles.scoresSubtitle}>
                        {winCondition === "ranking" ? "Saisir le classement (1er, 2ème...)" : "Saisir les scores"}
                      </Text>
                      {(game?.players ?? []).map((gp) => (
                        <View key={gp.player_id} style={styles.scoreInputRow}>
                          <Text style={styles.scoreInputPlayerName}>{gp.name}</Text>
                          <View style={styles.scoreInputGroup}>
                            {winCondition !== "ranking" ? (
                              <Pressable
                                style={styles.scoreSignButton}
                                onPress={() => toggleNegativeScore(round.id, gp.player_id)}
                              >
                                <Text style={styles.scoreSignButtonText}>±</Text>
                              </Pressable>
                            ) : null}
                            <TextInput
                              value={editingScores[round.id]?.[gp.player_id] ?? ""}
                              onChangeText={(v) =>
                                setEditingScores((prev) => ({ ...prev, [round.id]: { ...(prev[round.id] ?? {}), [gp.player_id]: v } }))
                              }
                              keyboardType="numeric"
                              placeholder={winCondition === "ranking" ? "Place" : "0"}
                              placeholderTextColor={theme.colors.mutedText}
                              style={styles.scoreInput}
                            />
                          </View>
                        </View>
                      ))}
                      <View style={styles.scoreSaveRow}>
                        <Pressable
                          onPress={() => { void handleSaveScores(round); }}
                          style={[styles.smallPrimaryBtn, saving && styles.btnDisabled]}
                          disabled={saving || actionLoading}
                        >
                          {saving ? <ActivityIndicator color="#fff" size="small" /> : <Text style={styles.smallPrimaryBtnText}>Enregistrer</Text>}
                        </Pressable>
                        <Pressable onPress={() => cancelEditing(round.id)} style={styles.cancelBtn} disabled={saving}>
                          <Text style={styles.cancelBtnText}>Annuler</Text>
                        </Pressable>
                      </View>
                    </>
                  )
                ) : (
                  <>
                    {round.scores.length === 0 ? (
                      <Text style={styles.noScoresText}>Aucun score saisi.</Text>
                    ) : (
                      round.scores.map((s) => {
                        const p = game?.players.find((gp) => gp.player_id === s.player_id);
                        return (
                          <View key={s.player_id} style={styles.scoreDisplayRow}>
                            <Text style={styles.scoreDisplayName}>{p?.name ?? `Joueur ${s.player_id}`}</Text>
                            {winCondition === "win_loss" ? (
                              <Text style={[styles.scoreDisplayValue, s.won === true ? { color: theme.colors.success } : s.won === false ? { color: theme.colors.danger } : {}]}>
                                {s.won === true ? "Victoire" : s.won === false ? "Défaite" : "-"}
                              </Text>
                            ) : (
                              <Text style={styles.scoreDisplayValue}>{s.score !== null ? `${s.score} pts` : "-"}</Text>
                            )}
                          </View>
                        );
                      })
                    )}
                    {!isCompleted ? (
                      <Pressable onPress={() => startEditingRound(round)} style={styles.editScoresBtn}>
                        <Text style={styles.editScoresBtnText}>
                          {winCondition === "win_loss" ? "✏ Saisir les résultats" : "✏ Saisir / modifier les scores"}
                        </Text>
                      </Pressable>
                    ) : null}
                  </>
                )}
              </View>
            </View>
          );
        })
      )}

      {!isCompleted && (game?.rounds ?? []).length > 0 ? (
        <Pressable onPress={handleCompleteGame} style={[styles.successBtn, actionLoading && styles.btnDisabled]} disabled={actionLoading}>
          {actionLoading ? <ActivityIndicator color="#fff" size="small" /> : <Text style={styles.successBtnText}>✓ Finaliser la partie</Text>}
        </Pressable>
      ) : null}
    </ScrollView>
  );
}

// ─── Sous-vue : Nouvelle partie ───────────────────────────────────────────────

function NewGameView({
  refereeToken,
  dashboard,
  onCreated,
  onBack,
  theme,
  styles,
  insets,
}: {
  refereeToken: string;
  dashboard: RefereeDashboardResponse | null;
  onCreated: (gameId: number) => void;
  onBack: () => void;
  theme: AppTheme;
  styles: ReturnType<typeof createStyles>;
  insets: { bottom: number; top: number };
}) {
  const [selectedTypeId, setSelectedTypeId] = useState<number | null>(null);
  const [selectedPlayerIds, setSelectedPlayerIds] = useState<number[]>([]);
  const [notes, setNotes] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const selectedType = (dashboard?.game_types ?? []).find((gt) => gt.id === selectedTypeId);

  const togglePlayer = (id: number) =>
    setSelectedPlayerIds((prev) => prev.includes(id) ? prev.filter((p) => p !== id) : [...prev, id]);

  const handleCreate = async () => {
    setError(null);
    if (!selectedTypeId) { setError("Sélectionnez un type de jeu."); return; }
    if (selectedPlayerIds.length < (selectedType?.min_players ?? 2)) {
      setError(`Sélectionnez au moins ${selectedType?.min_players ?? 2} joueur(s).`); return;
    }
    if (selectedType?.max_players != null && selectedPlayerIds.length > selectedType.max_players) {
      setError(`Maximum ${selectedType.max_players} joueur(s) pour ce type.`); return;
    }
    setLoading(true);
    try {
      const res = await refereeCreateGame(refereeToken, selectedTypeId, selectedPlayerIds, notes.trim() || undefined);
      onCreated(res.game.id);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Impossible de créer la partie.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={[styles.content, { paddingBottom: insets.bottom + 24 }]}
      keyboardShouldPersistTaps="handled"
    >
      <View style={styles.header}>
        <Pressable onPress={onBack} hitSlop={8} style={styles.backBtn}>
          <Text style={styles.backBtnText}>← Retour</Text>
        </Pressable>
        <Text style={styles.title}>Nouvelle partie</Text>
      </View>

      {error ? <View style={styles.errorBanner}><Text style={styles.errorText}>{error}</Text></View> : null}

      <Text style={styles.sectionTitle}>Type de jeu</Text>
      {(dashboard?.game_types ?? []).length === 0 ? (
        <View style={styles.emptyBox}><Text style={styles.emptyText}>Aucun type de jeu disponible.</Text></View>
      ) : (
        (dashboard?.game_types ?? []).map((gt) => (
          <Pressable key={gt.id} onPress={() => setSelectedTypeId(gt.id)}
            style={[styles.selectableCard, selectedTypeId === gt.id && styles.selectableCardSelected]}>
            <View style={styles.selectableCardRow}>
              <View style={[styles.radioCircle, selectedTypeId === gt.id && styles.radioCircleSelected]} />
              <View style={styles.selectableCardContent}>
                <Text style={styles.selectableCardTitle}>{gt.name}</Text>
                <Text style={styles.selectableCardMeta}>
                  {gt.min_players}{gt.max_players ? `–${gt.max_players}` : "+"} joueurs · {winConditionLabel(gt.win_condition)}
                </Text>
              </View>
            </View>
          </Pressable>
        ))
      )}

      <Text style={styles.sectionTitle}>
        Joueurs{selectedType ? ` (${selectedType.min_players}${selectedType.max_players ? `–${selectedType.max_players}` : "+"} requis)` : ""}
      </Text>
      {(dashboard?.players ?? []).map((p) => {
        const selected = selectedPlayerIds.includes(p.id);
        return (
          <Pressable key={p.id} onPress={() => togglePlayer(p.id)}
            style={[styles.selectableCard, selected && styles.selectableCardSelected, p.is_restricted && styles.selectableCardRestricted]}
            disabled={p.is_restricted}>
            <View style={styles.selectableCardRow}>
              <View style={[styles.checkBox, selected && styles.checkBoxSelected]}>
                {selected ? <Text style={styles.checkMark}>✓</Text> : null}
              </View>
              <View style={styles.selectableCardContent}>
                <Text style={[styles.selectableCardTitle, p.is_restricted && { color: theme.colors.mutedText }]}>{p.name}</Text>
                {p.linked_username ? <Text style={styles.selectableCardMeta}>@{p.linked_username}</Text> : null}
                {p.is_restricted ? <Text style={[styles.selectableCardMeta, { color: theme.colors.danger }]}>Joueur restreint</Text> : null}
              </View>
            </View>
          </Pressable>
        );
      })}

      <Text style={styles.sectionTitle}>Notes (optionnel)</Text>
      <TextInput
        value={notes}
        onChangeText={setNotes}
        placeholder="Observations sur la partie..."
        placeholderTextColor={theme.colors.mutedText}
        style={[styles.input, { minHeight: 70 }]}
        multiline
        editable={!loading}
      />

      <Pressable onPress={handleCreate} style={[styles.primaryBtn, loading && styles.btnDisabled]} disabled={loading}>
        {loading ? <ActivityIndicator color="#fff" size="small" /> : <Text style={styles.primaryBtnText}>Créer la partie</Text>}
      </Pressable>
    </ScrollView>
  );
}

// ─── Sous-vue : Vérification de carte ────────────────────────────────────────

function VerifyCardView({
  refereeToken,
  players,
  onBack,
  theme,
  styles,
  insets,
}: {
  refereeToken: string;
  players: RefereePlayer[];
  onBack: () => void;
  theme: AppTheme;
  styles: ReturnType<typeof createStyles>;
  insets: { bottom: number; top: number };
}) {
  const [selectedPlayerId, setSelectedPlayerId] = useState<number | null>(null);
  const [playerQuery, setPlayerQuery] = useState("");
  const [dropdownOpen, setDropdownOpen] = useState(false);
  const [reference, setReference] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [result, setResult] = useState<{
    valid: boolean; message?: string; reason?: string;
    card?: { player_name: string | null; reference: string; is_active: boolean };
  } | null>(null);

  const selectedPlayer = useMemo(
    () => players.find((p) => p.id === selectedPlayerId) ?? null,
    [players, selectedPlayerId]
  );

  const filteredPlayers = useMemo(() => {
    const q = playerQuery.trim().toLowerCase();
    if (!q) return players;
    return players.filter(
      (p) =>
        p.name.toLowerCase().includes(q) ||
        (p.linked_username?.toLowerCase().includes(q) ?? false)
    );
  }, [players, playerQuery]);

  const handleSelectPlayer = (p: RefereePlayer) => {
    setSelectedPlayerId(p.id);
    setPlayerQuery(p.name);
    setDropdownOpen(false);
    setResult(null);
    setError(null);
  };

  const handleClearPlayer = () => {
    setSelectedPlayerId(null);
    setPlayerQuery("");
    setDropdownOpen(false);
    setResult(null);
  };

  const handleVerify = async () => {
    setError(null); setResult(null);
    if (!selectedPlayerId) { setError("Sélectionnez un joueur."); return; }
    if (!reference.trim()) { setError("Saisissez la référence de la carte."); return; }
    setLoading(true);
    try {
      const res = await refereeVerifyCard(refereeToken, selectedPlayerId, reference.trim());
      setResult({ valid: res.valid, message: res.message, reason: res.reason, card: res.card });
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Impossible de vérifier la carte.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={[styles.content, { paddingBottom: insets.bottom + 24 }]}
      keyboardShouldPersistTaps="handled"
    >
      <View style={styles.header}>
        <Pressable onPress={onBack} hitSlop={8} style={styles.backBtn}>
          <Text style={styles.backBtnText}>← Retour</Text>
        </Pressable>
        <Text style={styles.title}>Vérifier une carte</Text>
        <Text style={styles.subtitle}>Vérifiez la carte de membre d'un participant.</Text>
      </View>

      {error ? <View style={styles.errorBanner}><Text style={styles.errorText}>{error}</Text></View> : null}

      {result !== null ? (
        <View style={[styles.verifyResultBox, result.valid ? styles.verifyResultValid : styles.verifyResultInvalid]}>
          <Text style={styles.verifyResultTitle}>{result.valid ? "✓ Carte valide" : "✗ Carte invalide"}</Text>
          {result.card ? (
            <>
              <Text style={styles.verifyResultText}>Référence : {result.card.reference}</Text>
              {result.card.player_name ? <Text style={styles.verifyResultText}>Titulaire : {result.card.player_name}</Text> : null}
            </>
          ) : null}
          {result.reason ? <Text style={styles.verifyResultText}>Raison : {result.reason}</Text> : null}
          {result.message ? <Text style={styles.verifyResultText}>{result.message}</Text> : null}
        </View>
      ) : null}

      <Text style={styles.sectionTitle}>Joueur</Text>
      <View style={styles.autocompleteWrapper}>
        <View style={styles.autocompleteInputRow}>
          <TextInput
            value={playerQuery}
            onChangeText={(t) => {
              setPlayerQuery(t);
              setDropdownOpen(true);
              if (selectedPlayer && t !== selectedPlayer.name) {
                setSelectedPlayerId(null);
              }
            }}
            onFocus={() => setDropdownOpen(true)}
            placeholder="Rechercher un joueur…"
            placeholderTextColor={theme.colors.mutedText}
            style={styles.autocompleteInput}
            returnKeyType="search"
            editable={!loading}
          />
          {playerQuery.length > 0 ? (
            <Pressable onPress={handleClearPlayer} hitSlop={8} style={styles.autocompleteCloseBtn}>
              <Text style={styles.autocompleteCloseBtnText}>✕</Text>
            </Pressable>
          ) : null}
        </View>
        {selectedPlayer ? (
          <View style={styles.autocompleteSelectedBadge}>
            <Text style={styles.autocompleteSelectedText}>
              ✓ {selectedPlayer.name}
              {selectedPlayer.linked_username ? `  @${selectedPlayer.linked_username}` : ""}
            </Text>
          </View>
        ) : null}
        {dropdownOpen && filteredPlayers.length > 0 ? (
          <View style={styles.autocompleteDropdown}>
            {filteredPlayers.slice(0, 8).map((p) => (
              <Pressable
                key={p.id}
                onPress={() => handleSelectPlayer(p)}
                style={({ pressed }) => [
                  styles.autocompleteItem,
                  pressed && styles.autocompleteItemPressed,
                ]}
              >
                <Text style={[
                  styles.autocompleteItemName,
                  p.is_restricted && { color: theme.colors.mutedText },
                ]}>
                  {p.name}
                  {p.is_restricted ? "  ⚠" : ""}
                </Text>
                {p.linked_username ? (
                  <Text style={styles.autocompleteItemMeta}>@{p.linked_username}</Text>
                ) : null}
              </Pressable>
            ))}
            {filteredPlayers.length > 8 ? (
              <Text style={styles.autocompleteMore}>+{filteredPlayers.length - 8} autres résultats…</Text>
            ) : null}
          </View>
        ) : null}
        {dropdownOpen && playerQuery.trim().length > 0 && filteredPlayers.length === 0 ? (
          <View style={styles.autocompleteDropdown}>
            <Text style={styles.autocompleteMore}>Aucun joueur trouvé.</Text>
          </View>
        ) : null}
      </View>

      <Text style={styles.sectionTitle}>Référence de la carte</Text>
      <TextInput
        value={reference}
        onChangeText={setReference}
        placeholder="Ex. : CARTE-XXXXXXXXXX"
        placeholderTextColor={theme.colors.mutedText}
        style={styles.input}
        autoCapitalize="characters"
        editable={!loading}
      />

      <Pressable onPress={handleVerify} style={[styles.primaryBtn, loading && styles.btnDisabled]} disabled={loading}>
        {loading ? <ActivityIndicator color="#fff" size="small" /> : <Text style={styles.primaryBtnText}>Vérifier</Text>}
      </Pressable>
    </ScrollView>
  );
}

// ─── Styles ───────────────────────────────────────────────────────────────────

const createStyles = (theme: AppTheme) =>
  StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.colors.background },
    content: { padding: theme.spacing.lg },
    centered: { flex: 1, justifyContent: "center", alignItems: "center" },
    sessionHeader: {
      backgroundColor: theme.colors.card,
      borderRadius: theme.radius.lg,
      padding: theme.spacing.md,
      marginBottom: theme.spacing.md,
      ...theme.shadow.card,
    },
    sessionHeaderTop: { flexDirection: "row", justifyContent: "space-between", alignItems: "flex-start" },
    sessionHeaderInfo: { flex: 1 },
    sessionHeaderTitle: { fontSize: 18, fontWeight: "700", color: theme.colors.text, marginBottom: 2 },
    sessionHeaderMeta: { fontSize: 13, color: theme.colors.mutedText },
    closeBtn: { paddingHorizontal: theme.spacing.sm, paddingVertical: 6, borderWidth: 1, borderColor: theme.colors.border, borderRadius: theme.radius.md },
    closeBtnText: { fontSize: 13, color: theme.colors.mutedText },
    pauseBanner: {
      backgroundColor: theme.colors.warning + "22",
      borderRadius: theme.radius.md,
      padding: theme.spacing.md,
      marginBottom: theme.spacing.md,
      borderLeftWidth: 3,
      borderLeftColor: theme.colors.warning,
    },
    pauseBannerTitle: { fontSize: 14, fontWeight: "700", color: theme.colors.warning, marginBottom: 2 },
    pauseBannerText: { fontSize: 13, color: theme.colors.warning },
    errorBanner: {
      backgroundColor: theme.colors.danger + "20",
      borderRadius: theme.radius.md,
      padding: theme.spacing.md,
      marginBottom: theme.spacing.md,
      borderLeftWidth: 3,
      borderLeftColor: theme.colors.danger,
    },
    errorText: { color: theme.colors.danger, fontSize: 14 },
    actionsRow: { flexDirection: "row", gap: theme.spacing.sm, marginBottom: theme.spacing.md },
    actionBtn: { flex: 1, backgroundColor: theme.colors.primary, borderRadius: theme.radius.md, paddingVertical: 12, alignItems: "center" },
    actionBtnSecondary: { backgroundColor: theme.colors.backgroundSoft, borderWidth: 1, borderColor: theme.colors.border },
    actionBtnText: { color: "#fff", fontWeight: "600", fontSize: 14 },
    actionBtnSecondaryText: { color: theme.colors.text },
    sectionTitle: {
      fontSize: 13, fontWeight: "700", color: theme.colors.mutedText,
      textTransform: "uppercase", letterSpacing: 0.5,
      marginBottom: theme.spacing.sm, marginTop: theme.spacing.md,
    },
    sectionRow: { flexDirection: "row", justifyContent: "space-between", alignItems: "center", marginTop: theme.spacing.md, marginBottom: theme.spacing.sm },
    gameCard: { backgroundColor: theme.colors.card, borderRadius: theme.radius.lg, padding: theme.spacing.md, marginBottom: theme.spacing.sm, ...theme.shadow.card },
    gameCardTop: { flexDirection: "row", justifyContent: "space-between", alignItems: "center", marginBottom: 4 },
    gameCardName: { fontSize: 16, fontWeight: "600", color: theme.colors.text, flex: 1 },
    gameMeta: { fontSize: 12, marginBottom: 2 },
    gameBadge: { borderRadius: theme.radius.pill, paddingHorizontal: 8, paddingVertical: 2 },
    gameBadgeActive: { backgroundColor: theme.colors.primary + "22" },
    gameBadgeCompleted: { backgroundColor: theme.colors.success + "22" },
    gameBadgeText: { fontSize: 11, fontWeight: "600" },
    gameBadgeTextActive: { color: theme.colors.primary },
    gameBadgeTextCompleted: { color: theme.colors.success },
    sessionActionsBox: { gap: theme.spacing.sm, marginBottom: theme.spacing.lg },
    warningBtn: {
      backgroundColor: theme.colors.warning + "22",
      borderWidth: 1, borderColor: theme.colors.warning + "55",
      borderRadius: theme.radius.md, paddingVertical: 12, alignItems: "center",
    },
    warningBtnText: { color: theme.colors.warning, fontWeight: "600", fontSize: 14 },
    dangerBtn: {
      backgroundColor: theme.colors.danger + "15",
      borderWidth: 1, borderColor: theme.colors.danger + "55",
      borderRadius: theme.radius.md, paddingVertical: 12, alignItems: "center",
    },
    dangerBtnText: { color: theme.colors.danger, fontWeight: "600", fontSize: 14 },
    header: { marginBottom: theme.spacing.lg },
    backBtn: { marginBottom: theme.spacing.sm },
    backBtnText: { color: theme.colors.primary, fontSize: 15 },
    headerTitleRow: { flexDirection: "row", alignItems: "center", gap: theme.spacing.sm, flexWrap: "wrap" },
    title: { fontSize: 22, fontWeight: "700", color: theme.colors.text, marginBottom: 4 },
    subtitle: { fontSize: 13, color: theme.colors.mutedText },
    emptyBox: { backgroundColor: theme.colors.card, borderRadius: theme.radius.lg, padding: theme.spacing.xl, alignItems: "center" },
    emptyText: { color: theme.colors.mutedText, fontSize: 14, textAlign: "center" },
    card: { backgroundColor: theme.colors.card, borderRadius: theme.radius.lg, padding: theme.spacing.md, marginBottom: theme.spacing.md, ...theme.shadow.card },
    playerRow: { flexDirection: "row", alignItems: "center", paddingVertical: theme.spacing.sm },
    playerRowBorder: { borderBottomWidth: 1, borderBottomColor: theme.colors.border },
    playerAvatar: { width: 32, height: 32, borderRadius: 16, backgroundColor: theme.colors.primary + "33", justifyContent: "center", alignItems: "center", marginRight: theme.spacing.sm },
    playerAvatarText: { fontSize: 14, fontWeight: "700", color: theme.colors.primary },
    playerName: { flex: 1, fontSize: 14, color: theme.colors.text, fontWeight: "500" },
    playerScores: { flexDirection: "row", gap: 8, alignItems: "center" },
    playerScoreValue: { fontSize: 13, color: theme.colors.text, fontWeight: "600" },
    playerRank: { fontSize: 12, color: theme.colors.mutedText, backgroundColor: theme.colors.backgroundSoft, borderRadius: theme.radius.pill, paddingHorizontal: 6, paddingVertical: 1 },
    roundCard: { backgroundColor: theme.colors.card, borderRadius: theme.radius.lg, padding: theme.spacing.md, marginBottom: theme.spacing.sm, ...theme.shadow.card },
    roundHeader: { flexDirection: "row", justifyContent: "space-between", alignItems: "center", marginBottom: theme.spacing.sm },
    roundTitle: { fontSize: 15, fontWeight: "600", color: theme.colors.text },
    roundBadge: { borderRadius: theme.radius.pill, paddingHorizontal: 8, paddingVertical: 2 },
    roundBadgeActive: { backgroundColor: theme.colors.primary + "22" },
    roundBadgePaused: { backgroundColor: theme.colors.warning + "22" },
    roundBadgeCompleted: { backgroundColor: theme.colors.success + "22" },
    roundBadgeText: { fontSize: 11, fontWeight: "600", color: theme.colors.text },
    roundActions: { flexDirection: "row", flexWrap: "wrap", gap: 6, marginBottom: theme.spacing.sm },
    roundActionBtn: { paddingHorizontal: 12, paddingVertical: 6, backgroundColor: theme.colors.backgroundSoft, borderRadius: theme.radius.md, borderWidth: 1, borderColor: theme.colors.border },
    roundActionBtnDanger: { borderColor: theme.colors.danger + "55" },
    roundActionBtnText: { fontSize: 12, color: theme.colors.text, fontWeight: "500" },
    roundActionBtnTextDanger: { fontSize: 12, color: theme.colors.danger, fontWeight: "500" },
    scoresBlock: { marginTop: theme.spacing.xs },
    scoresLabel: { fontSize: 12, fontWeight: "600", color: theme.colors.mutedText, marginBottom: 4, textTransform: "uppercase", letterSpacing: 0.4 },
    scoresSubtitle: { fontSize: 12, color: theme.colors.mutedText, marginBottom: 8, fontStyle: "italic" },
    noScoresText: { fontSize: 13, color: theme.colors.mutedText },
    scoreDisplayRow: { flexDirection: "row", justifyContent: "space-between", paddingVertical: 3 },
    scoreDisplayName: { fontSize: 13, color: theme.colors.text },
    scoreDisplayValue: { fontSize: 13, color: theme.colors.text, fontWeight: "600" },
    scoreInputRow: { flexDirection: "row", alignItems: "center", marginBottom: 8, gap: 8 },
    scoreInputPlayerName: { flex: 1, fontSize: 13, color: theme.colors.text },
    scoreInputGroup: { flexDirection: "row", alignItems: "center", gap: 6 },
    scoreSignButton: {
      width: 32, height: 32, borderRadius: theme.radius.sm,
      borderWidth: 1, borderColor: theme.colors.border,
      backgroundColor: theme.colors.backgroundSoft,
      alignItems: "center", justifyContent: "center",
    },
    scoreSignButtonText: { color: theme.colors.text, fontSize: 18, fontWeight: "700", lineHeight: 20 },
    scoreInput: {
      width: 90, backgroundColor: theme.colors.card,
      borderWidth: 1, borderColor: theme.colors.border,
      borderRadius: theme.radius.sm, paddingHorizontal: 10,
      paddingVertical: 8, color: theme.colors.text, fontSize: 14, textAlign: "right",
    },
    winnerRow: {
      flexDirection: "row", alignItems: "center", justifyContent: "space-between",
      borderWidth: 1, borderColor: theme.colors.border,
      borderRadius: theme.radius.sm, paddingHorizontal: 10, paddingVertical: 10,
      marginBottom: 6, backgroundColor: theme.colors.card,
    },
    winnerRowActive: { borderColor: theme.colors.success, backgroundColor: theme.colors.primarySoft ?? theme.colors.backgroundSoft },
    winnerLabel: { fontSize: 13, color: theme.colors.mutedText, fontWeight: "500" },
    winnerLabelActive: { color: theme.colors.success, fontWeight: "700" },
    scoreSaveRow: { flexDirection: "row", gap: 8, marginTop: 8 },
    editScoresBtn: { marginTop: 6, alignSelf: "flex-start" },
    editScoresBtnText: { fontSize: 13, color: theme.colors.primary, fontWeight: "500" },
    primaryBtn: { backgroundColor: theme.colors.primary, borderRadius: theme.radius.md, paddingVertical: 12, alignItems: "center", marginTop: theme.spacing.sm },
    primaryBtnText: { color: "#fff", fontWeight: "600", fontSize: 15 },
    smallPrimaryBtn: { backgroundColor: theme.colors.primary, borderRadius: theme.radius.md, paddingHorizontal: theme.spacing.md, paddingVertical: 8, alignItems: "center" },
    smallPrimaryBtnText: { color: "#fff", fontWeight: "600", fontSize: 13 },
    successBtn: { backgroundColor: theme.colors.success, borderRadius: theme.radius.md, paddingVertical: 12, alignItems: "center", marginTop: theme.spacing.md },
    successBtnText: { color: "#fff", fontWeight: "600", fontSize: 15 },
    cancelBtn: { borderWidth: 1, borderColor: theme.colors.border, borderRadius: theme.radius.md, paddingHorizontal: theme.spacing.md, paddingVertical: 8, alignItems: "center" },
    cancelBtnText: { fontSize: 13, color: theme.colors.mutedText },
    btnDisabled: { opacity: 0.6 },
    selectableCard: { backgroundColor: theme.colors.card, borderRadius: theme.radius.lg, padding: theme.spacing.md, marginBottom: theme.spacing.xs, borderWidth: 1.5, borderColor: "transparent", ...theme.shadow.card },
    selectableCardSelected: { borderColor: theme.colors.primary },
    selectableCardRestricted: { opacity: 0.5 },
    selectableCardRow: { flexDirection: "row", alignItems: "center", gap: theme.spacing.sm },
    selectableCardContent: { flex: 1 },
    selectableCardTitle: { fontSize: 15, fontWeight: "500", color: theme.colors.text },
    selectableCardMeta: { fontSize: 12, color: theme.colors.mutedText, marginTop: 2 },
    radioCircle: { width: 18, height: 18, borderRadius: 9, borderWidth: 2, borderColor: theme.colors.border },
    radioCircleSelected: { borderColor: theme.colors.primary, backgroundColor: theme.colors.primary },
    checkBox: { width: 20, height: 20, borderRadius: theme.radius.sm, borderWidth: 2, borderColor: theme.colors.border, justifyContent: "center", alignItems: "center" },
    checkBoxSelected: { borderColor: theme.colors.primary, backgroundColor: theme.colors.primary },
    checkMark: { color: "#fff", fontSize: 12, fontWeight: "700" },
    input: {
      backgroundColor: theme.colors.backgroundSoft, borderWidth: 1, borderColor: theme.colors.border,
      borderRadius: theme.radius.md, paddingHorizontal: theme.spacing.md, paddingVertical: 10,
      color: theme.colors.text, fontSize: 15, marginBottom: 8,
    },
    verifyResultBox: { borderRadius: theme.radius.lg, padding: theme.spacing.md, marginBottom: theme.spacing.md, borderLeftWidth: 4 },
    verifyResultValid: { backgroundColor: theme.colors.success + "18", borderLeftColor: theme.colors.success },
    verifyResultInvalid: { backgroundColor: theme.colors.danger + "18", borderLeftColor: theme.colors.danger },
    verifyResultTitle: { fontSize: 16, fontWeight: "700", color: theme.colors.text, marginBottom: 4 },
    verifyResultText: { fontSize: 13, color: theme.colors.mutedText, marginBottom: 2 },
    autocompleteWrapper: { marginBottom: theme.spacing.md, zIndex: 10 },
    autocompleteInputRow: {
      flexDirection: "row",
      alignItems: "center",
      backgroundColor: theme.colors.backgroundSoft,
      borderWidth: 1,
      borderColor: theme.colors.border,
      borderRadius: theme.radius.md,
      paddingHorizontal: theme.spacing.md,
    },
    autocompleteInput: {
      flex: 1,
      paddingVertical: 10,
      color: theme.colors.text,
      fontSize: 15,
    },
    autocompleteCloseBtn: { paddingLeft: theme.spacing.sm },
    autocompleteCloseBtnText: { fontSize: 14, color: theme.colors.mutedText },
    autocompleteSelectedBadge: {
      marginTop: theme.spacing.xs,
      backgroundColor: theme.colors.primary + "18",
      borderRadius: theme.radius.sm,
      paddingHorizontal: theme.spacing.sm,
      paddingVertical: 4,
      alignSelf: "flex-start",
    },
    autocompleteSelectedText: { fontSize: 13, color: theme.colors.primary, fontWeight: "600" },
    autocompleteDropdown: {
      backgroundColor: theme.colors.card,
      borderWidth: 1,
      borderColor: theme.colors.border,
      borderRadius: theme.radius.md,
      marginTop: 2,
      overflow: "hidden",
      ...theme.shadow.card,
    },
    autocompleteItem: {
      paddingHorizontal: theme.spacing.md,
      paddingVertical: theme.spacing.sm,
      borderBottomWidth: StyleSheet.hairlineWidth,
      borderBottomColor: theme.colors.border,
    },
    autocompleteItemPressed: { backgroundColor: theme.colors.backgroundSoft },
    autocompleteItemName: { fontSize: 15, color: theme.colors.text },
    autocompleteItemMeta: { fontSize: 12, color: theme.colors.mutedText, marginTop: 1 },
    autocompleteMore: { fontSize: 12, color: theme.colors.mutedText, padding: theme.spacing.sm, textAlign: "center" },
  });
