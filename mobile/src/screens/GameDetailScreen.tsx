import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  Image,
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
  addComment,
  completeGame,
  createRound,
  deleteComment,
  fetchGameDetails,
  updateGameStatus,
  updateRoundStatus,
  updateRoundScores,
} from "../services/api";
import { useAppTheme } from "../context/ThemeContext";
import { getAvatarUri } from "../utils/avatar";
import type { AppTheme } from "../styles";
import type { Comment, GameDetailsResponse, Space, User } from "../types/api";

type Props = {
  token: string;
  user: User | null;
  space: Space;
  gameId: number;
  onBack: () => void;
};

function getStatusMeta(
  status: "pending" | "in_progress" | "paused" | "completed",
  theme: AppTheme
) {
  switch (status) {
    case "in_progress":
      return { label: "En cours", backgroundColor: theme.colors.backgroundSoft, textColor: theme.colors.success };
    case "completed":
      return { label: "Terminée", backgroundColor: theme.colors.primarySoft, textColor: theme.colors.primary };
    case "paused":
      return { label: "En pause", backgroundColor: theme.colors.backgroundSoft, textColor: theme.colors.warning };
    case "pending":
    default:
      return { label: "En attente", backgroundColor: theme.colors.backgroundSoft, textColor: theme.colors.mutedText };
  }
}

function extractScoreValue(entry: unknown): number | null {
  if (typeof entry === "number") {
    return entry;
  }

  if (entry && typeof entry === "object" && "score" in entry) {
    const raw = (entry as { score?: unknown }).score;
    if (typeof raw === "number") {
      return raw;
    }
    if (typeof raw === "string") {
      const parsed = Number(raw);
      return Number.isNaN(parsed) ? null : parsed;
    }
  }

  return null;
}

function formatRoundValue(value: number | null, winCondition: string): string {
  if (value === null) {
    return "Non saisi";
  }

  if (winCondition === "win_loss") {
    return value === 1 ? "Gagnant" : "Défaite";
  }

  if (winCondition === "ranking") {
    return Number.isInteger(value) && value === 1 ? "1er" : `${value}e`;
  }

  return String(value);
}

function formatDuration(seconds: number): string {
  const safe = Math.max(0, Math.floor(seconds));
  const h = Math.floor(safe / 3600);
  const m = Math.floor((safe % 3600) / 60);
  const s = safe % 60;

  if (h > 0) {
    return `${h}h ${m}m ${s}s`;
  }
  if (m > 0) {
    return `${m}m ${s}s`;
  }
  return `${s}s`;
}

function getCommentInitials(username?: string): string {
  const source = (username || "?").trim();
  return source.slice(0, 2).toUpperCase();
}

export function GameDetailScreen({ token, user, space, gameId, onBack }: Props) {
  const { theme } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);

  const insets = useSafeAreaInsets();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [finishing, setFinishing] = useState(false);
  const [updatingGameStatus, setUpdatingGameStatus] = useState(false);
  const [updatingRoundId, setUpdatingRoundId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [details, setDetails] = useState<GameDetailsResponse | null>(null);

  const [editingRoundId, setEditingRoundId] = useState<number | null>(null);
  const [scores, setScores] = useState<Record<number, string>>({});
  const [winners, setWinners] = useState<Record<number, boolean>>({});
  const [expandedRoundIds, setExpandedRoundIds] = useState<number[]>([]);

  const [commentText, setCommentText] = useState("");
  const [submittingComment, setSubmittingComment] = useState(false);
  const [deletingCommentId, setDeletingCommentId] = useState<number | null>(null);
  const [commentError, setCommentError] = useState<string | null>(null);
  const commentInputRef = useRef<TextInput>(null);

  const loadDetails = useCallback(async () => {
    try {
      setError(null);
      const data = await fetchGameDetails(token, space.id, gameId);
      setDetails(data);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de charger la partie.");
      }
    }
  }, [gameId, space.id, token]);

  useEffect(() => {
    const run = async () => {
      setLoading(true);
      await loadDetails();
      setLoading(false);
    };
    void run();
  }, [loadDetails]);

  useEffect(() => {
    if (!details) {
      return;
    }

    const validIds = new Set(details.rounds.map((round) => round.id));
    setExpandedRoundIds((current) => current.filter((id) => validIds.has(id)));
  }, [details]);

  const playerIds = useMemo(
    () => details?.players.map((player) => player.player_id) ?? [],
    [details?.players]
  );

  const winCondition = details?.game.win_condition ?? "highest_score";

  const canComment = details?.can_comment ?? false;
  const totalDurationWithPauses = useMemo(() => {
    if (!details) {
      return 0;
    }

    const durations = details.round_durations ?? {};
    const roundIds = details.rounds.map((round) => String(round.id));
    const sumRaw = roundIds.reduce((acc, id) => acc + (durations[id]?.raw ?? 0), 0);

    if (sumRaw > 0) {
      return sumRaw;
    }

    return details.total_play_seconds ?? 0;
  }, [details]);

  const handleSubmitComment = useCallback(async () => {
    const content = commentText.trim();
    if (!content) return;
    try {
      setSubmittingComment(true);
      setCommentError(null);
      await addComment(token, space.id, gameId, content);
      setCommentText("");
      commentInputRef.current?.blur();
      await loadDetails();
    } catch (err) {
      if (err instanceof ApiError) {
        setCommentError(err.message);
      } else {
        setCommentError("Impossible de publier le commentaire.");
      }
    } finally {
      setSubmittingComment(false);
    }
  }, [commentText, token, space.id, gameId, loadDetails]);

  const handleDeleteComment = useCallback(async (comment: Comment) => {
    try {
      setDeletingCommentId(comment.id);
      setCommentError(null);
      await deleteComment(token, space.id, gameId, comment.id);
      await loadDetails();
    } catch (err) {
      if (err instanceof ApiError) {
        setCommentError(err.message);
      } else {
        setCommentError("Impossible de supprimer le commentaire.");
      }
    } finally {
      setDeletingCommentId(null);
    }
  }, [token, space.id, gameId, loadDetails]);

  const winConditionLabel =
    winCondition === "highest_score"
      ? "Score le plus élevé"
      : winCondition === "lowest_score"
      ? "Score le plus bas"
      : winCondition === "ranking"
      ? "Classement"
      : "Victoire/Défaite";

  const gameStatusMeta = getStatusMeta(details?.game.status ?? "pending", theme);

  const beginRoundScoring = (roundId: number) => {
    const currentRoundScores = (details?.round_scores?.[String(roundId)] || {}) as Record<string, unknown>;

    setEditingRoundId(roundId);

    if (winCondition === "win_loss") {
      const initialWinners: Record<number, boolean> = {};
      playerIds.forEach((id) => {
        const value = extractScoreValue(currentRoundScores[String(id)]);
        initialWinners[id] = value === 1;
      });
      setWinners(initialWinners);
      setScores({});
      return;
    }

    const initialScores: Record<number, string> = {};
    playerIds.forEach((id) => {
      const value = extractScoreValue(currentRoundScores[String(id)]);
      if (value === null) {
        initialScores[id] = winCondition === "ranking" ? "1" : "";
      } else {
        initialScores[id] = String(value);
      }
    });
    setScores(initialScores);
    setWinners({});
  };

  const toggleRoundExpanded = (roundId: number) => {
    setExpandedRoundIds((current) =>
      current.includes(roundId)
        ? current.filter((id) => id !== roundId)
        : [...current, roundId]
    );
  };

  const startRoundAndScores = async () => {
    try {
      setSaving(true);
      setError(null);
      const roundId = await createRound(token, space.id, gameId);
      await loadDetails();
      beginRoundScoring(roundId);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de créer une manche.");
      }
    } finally {
      setSaving(false);
    }
  };

  const saveScores = async () => {
    if (!editingRoundId) {
      return;
    }

    const normalizedScores: Record<number, number> = {};

    if (winCondition === "win_loss") {
      let winnerCount = 0;
      for (const playerId of playerIds) {
        const isWinner = !!winners[playerId];
        normalizedScores[playerId] = isWinner ? 1 : 0;
        if (isWinner) {
          winnerCount += 1;
        }
      }

      if (winnerCount === 0) {
        setError("Sélectionnez au moins un gagnant pour cette manche.");
        return;
      }
    } else {
      const usedRanks: number[] = [];
      for (const playerId of playerIds) {
        const raw = (scores[playerId] || "").trim();
        if (!raw) {
          setError("Merci de renseigner une valeur pour chaque joueur.");
          return;
        }

        const value = Number(raw.replace(",", "."));
        if (Number.isNaN(value)) {
          setError("Une valeur contient un format invalide.");
          return;
        }

        if (winCondition === "ranking") {
          if (!Number.isInteger(value) || value < 1) {
            setError("Le classement doit être un entier positif (1, 2, 3...).");
            return;
          }
          if (usedRanks.includes(value)) {
            setError("Chaque place doit être unique pour le classement.");
            return;
          }
          usedRanks.push(value);
        }

        normalizedScores[playerId] = value;
      }
    }

    try {
      setSaving(true);
      setError(null);
      await updateRoundScores(token, space.id, gameId, editingRoundId, normalizedScores);
      setEditingRoundId(null);
      setScores({});
      setWinners({});
      await loadDetails();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible d'enregistrer les scores.");
      }
    } finally {
      setSaving(false);
    }
  };

  const toggleNegativeScore = (playerId: number) => {
    setScores((current) => {
      const raw = (current[playerId] ?? "").trim();

      if (!raw) {
        return { ...current, [playerId]: "-" };
      }

      if (raw.startsWith("-")) {
        return { ...current, [playerId]: raw.slice(1) };
      }

      return { ...current, [playerId]: `-${raw}` };
    });
  };

  const finishGame = async () => {
    try {
      setFinishing(true);
      setError(null);
      await completeGame(token, space.id, gameId);
      await loadDetails();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de terminer la partie.");
      }
    } finally {
      setFinishing(false);
    }
  };

  const toggleGamePause = async () => {
    if (!details) {
      return;
    }

    const current = details.game.status;
    if (current !== "in_progress" && current !== "paused") {
      return;
    }

    const nextStatus = current === "paused" ? "in_progress" : "paused";

    try {
      setUpdatingGameStatus(true);
      setError(null);
      await updateGameStatus(token, space.id, gameId, nextStatus);
      await loadDetails();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de mettre à jour le statut de la partie.");
      }
    } finally {
      setUpdatingGameStatus(false);
    }
  };

  const toggleRoundPause = async (roundId: number, roundStatus: "in_progress" | "paused" | "completed") => {
    if (roundStatus === "completed") {
      return;
    }

    const nextStatus = roundStatus === "paused" ? "in_progress" : "paused";

    try {
      setUpdatingRoundId(roundId);
      setError(null);
      await updateRoundStatus(token, space.id, gameId, roundId, nextStatus);
      await loadDetails();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de mettre à jour le statut de la manche.");
      }
    } finally {
      setUpdatingRoundId(null);
    }
  };

  if (loading || !details) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={[styles.content, { paddingTop: Math.max(insets.top, 12) + 8 }]}
    >
      <View style={styles.header}>
        <Pressable style={styles.navButton} onPress={onBack}>
          <Text style={styles.navButtonText}>← Retour</Text>
        </Pressable>
        <Pressable style={styles.navButton} onPress={loadDetails}>
          <Text style={styles.navButtonText}>↻ Rafraîchir</Text>
        </Pressable>
      </View>

      <Text style={styles.title}>{details.game.game_type_name || "Partie"}</Text>
      <View style={styles.gameStatusRow}>
        <Text style={styles.meta}>Statut</Text>
        <View style={[styles.statusBadge, { backgroundColor: gameStatusMeta.backgroundColor }]}> 
          <Text style={[styles.statusText, { color: gameStatusMeta.textColor }]}>{gameStatusMeta.label}</Text>
        </View>
      </View>
      <Text style={styles.meta}>Condition de victoire : {winConditionLabel}</Text>
      <Text style={styles.meta}>Durée totale (pauses incluses) : {formatDuration(totalDurationWithPauses)}</Text>

      <View style={styles.overviewCard}>
        <View style={styles.overviewItem}>
          <Text style={styles.overviewValue}>{details.players.length}</Text>
          <Text style={styles.overviewLabel}>Joueurs</Text>
        </View>
        <View style={styles.overviewDivider} />
        <View style={styles.overviewItem}>
          <Text style={styles.overviewValue}>{details.rounds.length}</Text>
          <Text style={styles.overviewLabel}>Manches</Text>
        </View>
        <View style={styles.overviewDivider} />
        <View style={styles.overviewItem}>
          <Text style={styles.overviewValue}>{details.comments.length}</Text>
          <Text style={styles.overviewLabel}>Commentaires</Text>
        </View>
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <View style={styles.block}>
        <Text style={styles.blockTitle}>Joueurs</Text>
        {details.players.map((player) => (
          <View style={styles.row} key={player.id}>
            <Text style={styles.rowLabel}>{player.player_name}</Text>
            <Text style={styles.rowValue}>Total: {player.total_score}</Text>
          </View>
        ))}
      </View>

      <View style={styles.block}>
        <Text style={styles.blockTitle}>Manches</Text>
        {details.rounds.length === 0 ? <Text style={styles.meta}>Aucune manche.</Text> : null}
        {details.rounds.map((round) => {
          const statusMeta = getStatusMeta(round.status, theme);
          const roundScores = (details.round_scores?.[String(round.id)] || {}) as Record<string, unknown>;
          const isExpanded = expandedRoundIds.includes(round.id);
          const roundDuration = details.round_durations?.[String(round.id)]?.raw ?? 0;

          return (
            <View key={round.id} style={styles.roundCard}>
              <Pressable style={styles.roundHeader} onPress={() => toggleRoundExpanded(round.id)}>
                <View style={styles.roundHeaderMain}>
                  <Text style={styles.roundTitle}>Manche #{round.round_number}</Text>
                  <Text style={styles.roundToggleIcon}>{isExpanded ? "▲" : "▼"}</Text>
                </View>
                <View style={[styles.statusBadge, { backgroundColor: statusMeta.backgroundColor }]}>
                  <Text style={[styles.statusText, { color: statusMeta.textColor }]}>{statusMeta.label}</Text>
                </View>
              </Pressable>
              <Text style={styles.roundDurationText}>
                Durée (pauses incluses) : {formatDuration(roundDuration)}
              </Text>

              {isExpanded && round.status !== "completed" ? (
                <View style={styles.roundActions}>
                  <Pressable
                    style={[
                      styles.secondaryButton,
                      styles.roundActionButton,
                      updatingRoundId === round.id ? styles.disabled : undefined,
                    ]}
                    disabled={updatingRoundId === round.id}
                    onPress={() => toggleRoundPause(round.id, round.status)}
                  >
                    <Text style={styles.secondaryText}>
                      {round.status === "paused" ? "Reprendre la manche" : "Mettre la manche en pause"}
                    </Text>
                  </Pressable>

                  <Pressable
                    style={[
                      styles.secondaryButton,
                      styles.roundActionButton,
                      editingRoundId === round.id ? styles.roundActionButtonActive : undefined,
                    ]}
                    onPress={() => beginRoundScoring(round.id)}
                  >
                    <Text
                      style={[
                        styles.secondaryText,
                        editingRoundId === round.id ? styles.roundActionButtonTextActive : undefined,
                      ]}
                    >
                      {editingRoundId === round.id ? "Saisie en cours" : "Saisir les scores"}
                    </Text>
                  </Pressable>
                </View>
              ) : null}

              {isExpanded ? details.players.map((player) => {
                const scoreValue = extractScoreValue(roundScores[String(player.player_id)]);
                return (
                  <View style={styles.row} key={`${round.id}-${player.id}`}>
                    <Text style={styles.rowLabel}>{player.player_name}</Text>
                    <Text style={styles.rowValue}>{formatRoundValue(scoreValue, winCondition)}</Text>
                  </View>
                );
              }) : null}
            </View>
          );
        })}
      </View>

      <View style={styles.block}>
        <Text style={styles.blockTitle}>Actions de partie</Text>
        <Pressable
          style={[styles.primaryButton, saving ? styles.disabled : undefined]}
          disabled={saving}
          onPress={startRoundAndScores}
        >
          <Text style={styles.primaryText}>Ajouter une manche</Text>
        </Pressable>

        {editingRoundId ? (
          <View style={styles.scoreEditor}>
            <Text style={styles.blockTitle}>
              {`Manche #${details.rounds.find((round) => round.id === editingRoundId)?.round_number ?? "?"}`}
            </Text>
            <Text style={styles.meta}>
              {winCondition === "ranking"
                ? "Saisir le classement de la manche"
                : winCondition === "win_loss"
                ? "Sélectionner le ou les gagnants"
                : "Saisir les scores"}
            </Text>

            {winCondition === "win_loss"
              ? details.players.map((player) => {
                  const isWinner = !!winners[player.player_id];
                  return (
                    <Pressable
                      key={player.id}
                      style={[styles.winnerRow, isWinner ? styles.winnerRowActive : undefined]}
                      onPress={() =>
                        setWinners((current) => ({
                          ...current,
                          [player.player_id]: !current[player.player_id],
                        }))
                      }
                    >
                      <Text style={styles.scoreLabel}>{player.player_name}</Text>
                      <Text style={styles.winnerStatus}>{isWinner ? "Gagnant" : "Défaite"}</Text>
                    </Pressable>
                  );
                })
              : details.players.map((player) => (
                  <View key={player.id} style={styles.scoreRow}>
                    <Text style={styles.scoreLabel}>{player.player_name}</Text>
                    <View style={styles.scoreInputGroup}>
                      {winCondition !== "ranking" ? (
                        <Pressable
                          style={styles.scoreSignButton}
                          onPress={() => toggleNegativeScore(player.player_id)}
                        >
                          <Text style={styles.scoreSignButtonText}>-</Text>
                        </Pressable>
                      ) : null}
                      <TextInput
                        value={scores[player.player_id] ?? ""}
                        onChangeText={(value) =>
                          setScores((current) => ({ ...current, [player.player_id]: value }))
                        }
                        keyboardType="numeric"
                        style={styles.scoreInput}
                        placeholder={winCondition === "ranking" ? "Place" : "0"}
                        placeholderTextColor={theme.colors.mutedText}
                      />
                    </View>
                  </View>
                ))}

            <Pressable
              style={[styles.primaryButton, saving ? styles.disabled : undefined]}
              disabled={saving}
              onPress={saveScores}
            >
              <Text style={styles.primaryText}>Enregistrer les scores</Text>
            </Pressable>

            <Pressable
              style={[styles.secondaryButton, saving ? styles.disabled : undefined]}
              disabled={saving}
              onPress={() => {
                setEditingRoundId(null);
                setScores({});
                setWinners({});
              }}
            >
              <Text style={styles.secondaryText}>Annuler la saisie</Text>
            </Pressable>
          </View>
        ) : null}

        <View style={styles.actionDivider} />

        <View style={styles.gameActionsRow}>
          {details.game.status !== "completed" && (details.game.status === "in_progress" || details.game.status === "paused") ? (
            <Pressable
              style={[styles.secondaryButton, updatingGameStatus ? styles.disabled : undefined]}
              disabled={updatingGameStatus}
              onPress={toggleGamePause}
            >
              <Text style={styles.secondaryText}>
                {details.game.status === "paused" ? "Reprendre la partie" : "Mettre la partie en pause"}
              </Text>
            </Pressable>
          ) : null}

          <Pressable
            style={[styles.finishButton, finishing ? styles.disabled : undefined]}
            disabled={finishing || details.game.status === "completed"}
            onPress={finishGame}
          >
            <Text style={styles.primaryText}>
              {details.game.status === "completed" ? "Partie terminée" : "Terminer la partie"}
            </Text>
          </Pressable>
        </View>
      </View>

      <View style={styles.block}>
        <Text style={styles.blockTitle}>Commentaires ({details.comments.length})</Text>

        {details.comments.length === 0 ? (
          <Text style={styles.meta}>Aucun commentaire pour l&apos;instant.</Text>
        ) : null}

        {details.comments.map((comment) => {
          const isAuthor = user && comment.user_id === user.id;
          const canDelete = isAuthor || (user && (user.global_role === "admin" || user.global_role === "superadmin" || user.global_role === "moderator"));
          const avatarUri = getAvatarUri(comment.avatar);
          return (
            <View key={comment.id} style={styles.commentCard}>
              <View style={styles.commentHeader}>
                <View style={styles.commentAuthorWrap}>
                  <View style={styles.commentAvatar}>
                    {avatarUri ? (
                      <Image source={{ uri: avatarUri }} style={styles.commentAvatarImage} />
                    ) : (
                      <Text style={styles.commentAvatarText}>{getCommentInitials(comment.username)}</Text>
                    )}
                  </View>
                  <Text style={styles.commentAuthor}>{comment.username}</Text>
                </View>
                <Text style={styles.commentDate}>
                  {new Date(comment.created_at).toLocaleDateString("fr-FR", {
                    day: "2-digit",
                    month: "2-digit",
                    year: "numeric",
                    hour: "2-digit",
                    minute: "2-digit",
                  })}
                </Text>
              </View>
              <Text style={styles.commentContent}>{comment.content}</Text>
              {canDelete ? (
                <Pressable
                  style={[styles.commentDeleteBtn, deletingCommentId === comment.id ? styles.disabled : undefined]}
                  disabled={deletingCommentId === comment.id}
                  onPress={() => handleDeleteComment(comment)}
                >
                  <Text style={styles.commentDeleteText}>
                    {deletingCommentId === comment.id ? "Suppression..." : "Supprimer"}
                  </Text>
                </Pressable>
              ) : null}
            </View>
          );
        })}

        {commentError ? <Text style={styles.error}>{commentError}</Text> : null}

        {canComment ? (
          <KeyboardAvoidingView behavior={Platform.OS === "ios" ? "padding" : undefined}>
            <TextInput
              ref={commentInputRef}
              style={styles.commentInput}
              placeholder="Écrire un commentaire..."
              placeholderTextColor={theme.colors.mutedText}
              value={commentText}
              onChangeText={setCommentText}
              multiline
              maxLength={1000}
            />
            <Pressable
              style={[styles.primaryButton, (submittingComment || !commentText.trim()) ? styles.disabled : undefined]}
              disabled={submittingComment || !commentText.trim()}
              onPress={handleSubmitComment}
            >
              <Text style={styles.primaryText}>
                {submittingComment ? "Publication..." : "Publier"}
              </Text>
            </Pressable>
          </KeyboardAvoidingView>
        ) : (
          <Text style={styles.commentRestricted}>
            La publication de commentaires est désactivée sur votre compte.
          </Text>
        )}
      </View>
    </ScrollView>
  );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
  centered: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: theme.colors.background,
  },
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  content: {
    padding: 14,
  },
  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    marginBottom: 8,
  },
  navButton: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.md,
    paddingHorizontal: 10,
    paddingVertical: 7,
  },
  navButtonText: {
    color: theme.colors.primary,
    fontWeight: "700",
    fontSize: 12,
  },
  title: {
    fontSize: 22,
    fontWeight: "700",
    color: theme.colors.text,
  },
  meta: {
    color: theme.colors.mutedText,
    marginTop: 6,
  },
  gameStatusRow: {
    marginTop: 6,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  overviewCard: {
    marginTop: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.card,
    paddingVertical: 10,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  overviewItem: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
  },
  overviewValue: {
    color: theme.colors.text,
    fontWeight: "800",
    fontSize: 20,
  },
  overviewLabel: {
    marginTop: 2,
    color: theme.colors.mutedText,
    fontSize: 12,
    fontWeight: "600",
  },
  overviewDivider: {
    width: 1,
    height: 32,
    backgroundColor: theme.colors.border,
  },
  error: {
    color: theme.colors.danger,
    marginTop: 10,
  },
  block: {
    marginTop: 14,
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: 12,
  },
  blockTitle: {
    fontSize: 16,
    fontWeight: "700",
    color: theme.colors.text,
    marginBottom: 8,
  },
  roundCard: {
    marginTop: 10,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.sm,
    padding: 10,
    backgroundColor: theme.colors.backgroundSoft,
  },
  roundHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 8,
  },
  roundHeaderMain: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
  },
  roundToggleIcon: {
    color: theme.colors.mutedText,
    fontSize: 11,
    fontWeight: "700",
  },
  roundActions: {
    marginBottom: 8,
    gap: 8,
  },
  roundDurationText: {
    color: theme.colors.mutedText,
    fontSize: 12,
    marginBottom: 8,
  },
  roundActionButton: {
    marginTop: 0,
  },
  roundActionButtonActive: {
    borderColor: theme.colors.primary,
    backgroundColor: theme.colors.primarySoft,
  },
  roundActionButtonTextActive: {
    color: theme.colors.primary,
  },
  roundTitle: {
    color: theme.colors.text,
    fontWeight: "700",
  },
  statusBadge: {
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 5,
  },
  statusText: {
    fontWeight: "700",
    fontSize: 12,
  },
  row: {
    flexDirection: "row",
    justifyContent: "space-between",
    marginBottom: 6,
  },
  rowLabel: {
    color: theme.colors.text,
    fontWeight: "600",
  },
  rowValue: {
    color: theme.colors.mutedText,
  },
  primaryButton: {
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    paddingVertical: 12,
    alignItems: "center",
  },
  finishButton: {
    marginTop: 8,
    backgroundColor: theme.colors.success,
    borderRadius: theme.radius.md,
    paddingVertical: 12,
    alignItems: "center",
  },
  gameActionsRow: {
    marginTop: 10,
    gap: 8,
  },
  actionDivider: {
    marginTop: 12,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
  },
  primaryText: {
    color: "#ffffff",
    fontWeight: "700",
  },
  secondaryButton: {
    marginTop: 8,
    borderRadius: theme.radius.md,
    paddingVertical: 10,
    alignItems: "center",
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.backgroundSoft,
  },
  secondaryText: {
    color: theme.colors.text,
    fontWeight: "700",
  },
  disabled: {
    opacity: 0.6,
  },
  scoreEditor: {
    marginTop: 12,
  },
  scoreRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 8,
  },
  scoreInputGroup: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
  },
  scoreSignButton: {
    width: 32,
    height: 32,
    borderRadius: theme.radius.sm,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.backgroundSoft,
    alignItems: "center",
    justifyContent: "center",
  },
  scoreSignButtonText: {
    color: theme.colors.text,
    fontSize: 20,
    lineHeight: 22,
    fontWeight: "700",
  },
  scoreLabel: {
    color: theme.colors.text,
    fontWeight: "600",
  },
  scoreInput: {
    width: 90,
    backgroundColor: theme.colors.card,
    borderColor: theme.colors.border,
    borderWidth: 1,
    borderRadius: theme.radius.sm,
    paddingHorizontal: 10,
    paddingVertical: 8,
    textAlign: "right",
    color: theme.colors.text,
  },
  winnerRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.sm,
    paddingHorizontal: 10,
    paddingVertical: 10,
    marginBottom: 8,
    backgroundColor: theme.colors.card,
  },
  winnerRowActive: {
    borderColor: theme.colors.success,
    backgroundColor: theme.colors.primarySoft,
  },
  winnerStatus: {
    color: theme.colors.mutedText,
    fontWeight: "600",
  },
  commentCard: {
    marginTop: 10,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.sm,
    padding: 10,
    backgroundColor: theme.colors.backgroundSoft,
  },
  commentHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 4,
  },
  commentAuthorWrap: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    flex: 1,
    marginRight: 8,
  },
  commentAvatar: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: theme.colors.primarySoft,
    alignItems: "center",
    justifyContent: "center",
    overflow: "hidden",
  },
  commentAvatarImage: {
    width: "100%",
    height: "100%",
  },
  commentAvatarText: {
    color: theme.colors.primary,
    fontSize: 11,
    fontWeight: "700",
  },
  commentAuthor: {
    color: theme.colors.text,
    fontWeight: "700",
    fontSize: 13,
    flexShrink: 1,
  },
  commentDate: {
    color: theme.colors.mutedText,
    fontSize: 11,
  },
  commentContent: {
    color: theme.colors.text,
    fontSize: 14,
    lineHeight: 20,
  },
  commentDeleteBtn: {
    marginTop: 6,
    alignSelf: "flex-end",
  },
  commentDeleteText: {
    color: theme.colors.danger,
    fontSize: 12,
    fontWeight: "600",
  },
  commentInput: {
    marginTop: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.sm,
    paddingHorizontal: 12,
    paddingVertical: 10,
    color: theme.colors.text,
    backgroundColor: theme.colors.card,
    minHeight: 72,
    textAlignVertical: "top",
    fontSize: 14,
  },
  commentRestricted: {
    marginTop: 12,
    color: theme.colors.mutedText,
    fontStyle: "italic",
    fontSize: 13,
  },
});
