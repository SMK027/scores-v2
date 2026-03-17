import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { AutocompleteSelect } from "../components/AutocompleteSelect";
import {
  ApiError,
  createGame,
  fetchGameTypes,
  fetchPlayers,
  fetchSpaceGames,
} from "../services/api";
import { theme } from "../styles/theme";
import type { Game, GameType, Player, Space } from "../types/api";

type Props = {
  token: string;
  space: Space;
  onBack: () => void;
  onOpenGame: (gameId: number) => void;
};

export function SpaceScreen({ token, space, onBack, onOpenGame }: Props) {
  const [loading, setLoading] = useState(true);
  const [games, setGames] = useState<Game[]>([]);
  const [players, setPlayers] = useState<Player[]>([]);
  const [gameTypes, setGameTypes] = useState<GameType[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const [gameTypeQuery, setGameTypeQuery] = useState("");
  const [selectedGameTypeId, setSelectedGameTypeId] = useState<number | null>(null);
  const [playerQuery, setPlayerQuery] = useState("");
  const [selectedPlayerIds, setSelectedPlayerIds] = useState<number[]>([]);
  const [notes, setNotes] = useState("");

  const loadData = useCallback(async () => {
    try {
      setError(null);
      const [gamesData, playersData, gameTypesData] = await Promise.all([
        fetchSpaceGames(token, space.id),
        fetchPlayers(token, space.id),
        fetchGameTypes(token, space.id),
      ]);

      setGames(gamesData);
      setPlayers(playersData);
      setGameTypes(gameTypesData);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de charger cet espace.");
      }
    }
  }, [space.id, token]);

  useEffect(() => {
    const run = async () => {
      setLoading(true);
      await loadData();
      setLoading(false);
    };

    void run();
  }, [loadData]);

  const selectedGameType = useMemo(
    () => gameTypes.find((item) => item.id === selectedGameTypeId) || null,
    [gameTypes, selectedGameTypeId]
  );

  const selectedPlayers = useMemo(
    () => players.filter((player) => selectedPlayerIds.includes(player.id)),
    [players, selectedPlayerIds]
  );

  const selectablePlayers = useMemo(
    () => players.filter((player) => !selectedPlayerIds.includes(player.id)),
    [players, selectedPlayerIds]
  );

  const selectGameType = (id: number) => {
    const gameType = gameTypes.find((item) => item.id === id);
    setSelectedGameTypeId(id);
    setGameTypeQuery(gameType ? gameType.name : "");
  };

  const addPlayer = (id: number) => {
    const player = players.find((item) => item.id === id);
    if (!player || selectedPlayerIds.includes(id)) {
      return;
    }

    setSelectedPlayerIds((current) => [...current, id]);
    setPlayerQuery("");
  };

  const removePlayer = (id: number) => {
    setSelectedPlayerIds((current) => current.filter((value) => value !== id));
  };

  const submitCreateGame = async () => {
    if (!selectedGameTypeId) {
      setError("Veuillez selectionner un type de jeu.");
      return;
    }

    if (selectedPlayerIds.length === 0) {
      setError("Veuillez ajouter au moins un joueur.");
      return;
    }

    try {
      setSaving(true);
      setError(null);
      await createGame(token, space.id, {
        gameTypeId: selectedGameTypeId,
        playerIds: selectedPlayerIds,
        notes,
      });

      setSelectedGameTypeId(null);
      setGameTypeQuery("");
      setSelectedPlayerIds([]);
      setPlayerQuery("");
      setNotes("");
      await loadData();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de creer la partie.");
      }
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Pressable onPress={onBack}>
          <Text style={styles.back}>Retour</Text>
        </Pressable>
        <Text style={styles.title} numberOfLines={1}>
          {space.name}
        </Text>
        <Pressable onPress={loadData}>
          <Text style={styles.back}>Rafraichir</Text>
        </Pressable>
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <ScrollView contentContainerStyle={styles.formCard}>
        <Text style={styles.sectionTitle}>Creer une partie</Text>

        <AutocompleteSelect
          label="Type de jeu"
          query={gameTypeQuery}
          onQueryChange={setGameTypeQuery}
          options={gameTypes.map((item) => ({ id: item.id, label: item.name }))}
          onSelect={selectGameType}
          placeholder="Rechercher un type de jeu"
        />

        {selectedGameType ? <Text style={styles.selectedHint}>Type choisi: {selectedGameType.name}</Text> : null}

        <View style={styles.spacer} />

        <AutocompleteSelect
          label="Joueurs"
          query={playerQuery}
          onQueryChange={setPlayerQuery}
          options={selectablePlayers.map((item) => ({ id: item.id, label: item.name }))}
          onSelect={addPlayer}
          placeholder="Rechercher un joueur"
        />

        {selectedPlayers.length > 0 ? (
          <View style={styles.chipsContainer}>
            {selectedPlayers.map((player) => (
              <Pressable key={player.id} style={styles.chip} onPress={() => removePlayer(player.id)}>
                <Text style={styles.chipText}>{player.name} x</Text>
              </Pressable>
            ))}
          </View>
        ) : null}

        <Text style={styles.label}>Notes</Text>
        <TextInput
          value={notes}
          onChangeText={setNotes}
          placeholder="Notes optionnelles"
          style={[styles.input, styles.notes]}
          multiline
        />

        <Pressable
          style={[styles.primaryButton, saving ? styles.disabledButton : undefined]}
          onPress={submitCreateGame}
          disabled={saving}
        >
          <Text style={styles.primaryText}>{saving ? "Creation..." : "Creer la partie"}</Text>
        </Pressable>
      </ScrollView>

      <Text style={styles.sectionTitle}>Parties</Text>
      <FlatList
        data={games}
        keyExtractor={(item) => String(item.id)}
        ListEmptyComponent={<Text style={styles.empty}>Aucune partie pour le moment.</Text>}
        renderItem={({ item }) => (
          <Pressable style={styles.gameCard} onPress={() => onOpenGame(item.id)}>
            <Text style={styles.gameTitle}>{item.game_type_name || "Partie"}</Text>
            <Text style={styles.gameMeta}>Statut: {item.status}</Text>
            <Text style={styles.gameMeta}>Joueurs: {item.player_count ?? "-"}</Text>
          </Pressable>
        )}
      />
    </View>
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
    padding: 14,
  },
  header: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 12,
  },
  back: {
    color: theme.colors.primary,
    fontWeight: "700",
  },
  title: {
    flex: 1,
    textAlign: "center",
    fontSize: 20,
    fontWeight: "700",
    color: theme.colors.text,
    marginHorizontal: 8,
  },
  error: {
    color: theme.colors.danger,
    marginBottom: 10,
  },
  formCard: {
    backgroundColor: theme.colors.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.lg,
    padding: 14,
    marginBottom: 14,
  },
  sectionTitle: {
    color: theme.colors.text,
    fontWeight: "700",
    fontSize: 18,
    marginBottom: 10,
  },
  selectedHint: {
    marginTop: 8,
    color: theme.colors.success,
    fontWeight: "600",
  },
  spacer: {
    height: 12,
  },
  chipsContainer: {
    flexDirection: "row",
    flexWrap: "wrap",
    marginTop: 8,
  },
  chip: {
    backgroundColor: theme.colors.primarySoft,
    borderRadius: 999,
    paddingVertical: 6,
    paddingHorizontal: 10,
    marginRight: 6,
    marginBottom: 6,
  },
  chipText: {
    color: theme.colors.primary,
    fontWeight: "600",
  },
  label: {
    color: theme.colors.mutedText,
    fontSize: 13,
    marginTop: 10,
    marginBottom: 6,
    fontWeight: "600",
  },
  input: {
    backgroundColor: theme.colors.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  notes: {
    minHeight: 80,
    textAlignVertical: "top",
  },
  primaryButton: {
    marginTop: 12,
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    paddingVertical: 12,
    alignItems: "center",
  },
  disabledButton: {
    opacity: 0.6,
  },
  primaryText: {
    color: "#ffffff",
    fontWeight: "700",
  },
  empty: {
    color: theme.colors.mutedText,
    textAlign: "center",
    marginVertical: 20,
  },
  gameCard: {
    backgroundColor: theme.colors.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    padding: 12,
    marginBottom: 8,
  },
  gameTitle: {
    color: theme.colors.text,
    fontWeight: "700",
    fontSize: 16,
  },
  gameMeta: {
    color: theme.colors.mutedText,
    marginTop: 4,
  },
});
