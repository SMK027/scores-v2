import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
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
  completeGame,
  createRound,
  fetchGameDetails,
  updateRoundScores,
} from "../services/api";
import { theme } from "../styles/theme";
import type { GameDetailsResponse, Space } from "../types/api";

type Props = {
  token: string;
  space: Space;
  gameId: number;
  onBack: () => void;
};

export function GameDetailScreen({ token, space, gameId, onBack }: Props) {
  const insets = useSafeAreaInsets();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [finishing, setFinishing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [details, setDetails] = useState<GameDetailsResponse | null>(null);

  const [editingRoundId, setEditingRoundId] = useState<number | null>(null);
  const [scores, setScores] = useState<Record<number, string>>({});

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

  const playerIds = useMemo(
    () => details?.players.map((player) => player.player_id) ?? [],
    [details?.players]
  );

  const startRoundAndScores = async () => {
    try {
      setSaving(true);
      setError(null);
      const roundId = await createRound(token, space.id, gameId);
      setEditingRoundId(roundId);

      const initial: Record<number, string> = {};
      playerIds.forEach((id) => {
        initial[id] = "";
      });
      setScores(initial);
      await loadDetails();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de creer une manche.");
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
    for (const playerId of playerIds) {
      const raw = (scores[playerId] || "").trim();
      if (!raw) {
        setError("Merci de renseigner un score pour chaque joueur.");
        return;
      }

      const value = Number(raw.replace(",", "."));
      if (Number.isNaN(value)) {
        setError("Un score contient une valeur invalide.");
        return;
      }

      normalizedScores[playerId] = value;
    }

    try {
      setSaving(true);
      setError(null);
      await updateRoundScores(token, space.id, gameId, editingRoundId, normalizedScores);
      setEditingRoundId(null);
      setScores({});
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
        <Pressable onPress={onBack}>
          <Text style={styles.back}>Retour</Text>
        </Pressable>
        <Pressable onPress={loadDetails}>
          <Text style={styles.back}>Rafraichir</Text>
        </Pressable>
      </View>

      <Text style={styles.title}>{details.game.game_type_name || "Partie"}</Text>
      <Text style={styles.meta}>Statut: {details.game.status}</Text>

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
        {details.rounds.map((round) => (
          <Text style={styles.meta} key={round.id}>
            Manche #{round.round_number} - {round.status}
          </Text>
        ))}
      </View>

      <View style={styles.block}>
        <Pressable
          style={[styles.primaryButton, saving ? styles.disabled : undefined]}
          disabled={saving}
          onPress={startRoundAndScores}
        >
          <Text style={styles.primaryText}>Ajouter une manche</Text>
        </Pressable>

        {editingRoundId ? (
          <View style={styles.scoreEditor}>
            <Text style={styles.blockTitle}>Saisir les scores</Text>
            {details.players.map((player) => (
              <View key={player.id} style={styles.scoreRow}>
                <Text style={styles.scoreLabel}>{player.player_name}</Text>
                <TextInput
                  value={scores[player.player_id] ?? ""}
                  onChangeText={(value) =>
                    setScores((current) => ({ ...current, [player.player_id]: value }))
                  }
                  keyboardType="numeric"
                  style={styles.scoreInput}
                  placeholder="0"
                />
              </View>
            ))}

            <Pressable
              style={[styles.primaryButton, saving ? styles.disabled : undefined]}
              disabled={saving}
              onPress={saveScores}
            >
              <Text style={styles.primaryText}>Enregistrer les scores</Text>
            </Pressable>
          </View>
        ) : null}
      </View>

      <Pressable
        style={[styles.finishButton, finishing ? styles.disabled : undefined]}
        disabled={finishing || details.game.status === "completed"}
        onPress={finishGame}
      >
        <Text style={styles.primaryText}>
          {details.game.status === "completed" ? "Partie terminee" : "Terminer la partie"}
        </Text>
      </Pressable>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
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
  back: {
    color: theme.colors.primary,
    fontWeight: "700",
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
    marginTop: 16,
    backgroundColor: theme.colors.success,
    borderRadius: theme.radius.md,
    paddingVertical: 12,
    alignItems: "center",
  },
  primaryText: {
    color: "#ffffff",
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
});
