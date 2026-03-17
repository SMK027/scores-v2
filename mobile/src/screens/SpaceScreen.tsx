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
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { AutocompleteSelect } from "../components/AutocompleteSelect";
import {
  ApiError,
  createPlayer,
  createGame,
  deletePlayer,
  fetchGameDetails,
  fetchGameTypes,
  fetchPlayers,
  fetchSpaceMembers,
  fetchSpaceGames,
  updatePlayer,
} from "../services/api";
import { theme } from "../styles/theme";
import type { Game, GameType, Player, Space, SpaceMember } from "../types/api";

type Props = {
  token: string;
  space: Space;
  onBack: () => void;
  onOpenGame: (gameId: number) => void;
};

type SpaceView = "menu" | "games" | "create" | "stats" | "players";

type PlayerStats = {
  playerId: number;
  playerName: string;
  roundsPlayed: number;
  gamesPlayed: number;
  wins: number;
  winRate: number;
};

function getStatusMeta(status: Game["status"]): {
  label: string;
  backgroundColor: string;
  textColor: string;
} {
  switch (status) {
    case "in_progress":
      return {
        label: "En cours",
        backgroundColor: "#caefe5",
        textColor: "#0b7a61",
      };
    case "completed":
      return {
        label: "Terminee",
        backgroundColor: "#dfe0ff",
        textColor: "#3d4bdf",
      };
    case "paused":
      return {
        label: "En pause",
        backgroundColor: "#ffe8c5",
        textColor: "#8a5a00",
      };
    case "pending":
    default:
      return {
        label: "En attente",
        backgroundColor: "#e9edf5",
        textColor: "#5b6780",
      };
  }
}

export function SpaceScreen({ token, space, onBack, onOpenGame }: Props) {
  const insets = useSafeAreaInsets();
  const [loading, setLoading] = useState(true);
  const [games, setGames] = useState<Game[]>([]);
  const [players, setPlayers] = useState<Player[]>([]);
  const [members, setMembers] = useState<SpaceMember[]>([]);
  const [gameTypes, setGameTypes] = useState<GameType[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const [gameTypeQuery, setGameTypeQuery] = useState("");
  const [selectedGameTypeId, setSelectedGameTypeId] = useState<number | null>(null);
  const [playerQuery, setPlayerQuery] = useState("");
  const [selectedPlayerIds, setSelectedPlayerIds] = useState<number[]>([]);
  const [notes, setNotes] = useState("");
  const [currentView, setCurrentView] = useState<SpaceView>("menu");
  const [statsLoading, setStatsLoading] = useState(false);
  const [statsError, setStatsError] = useState<string | null>(null);
  const [playerStats, setPlayerStats] = useState<PlayerStats[]>([]);
  const [creatingPlayer, setCreatingPlayer] = useState(false);
  const [editingPlayerId, setEditingPlayerId] = useState<number | null>(null);
  const [savingPlayer, setSavingPlayer] = useState(false);
  const [deletingPlayerId, setDeletingPlayerId] = useState<number | null>(null);

  const [newPlayerName, setNewPlayerName] = useState("");
  const [newPlayerMemberQuery, setNewPlayerMemberQuery] = useState("");
  const [newPlayerUserId, setNewPlayerUserId] = useState<number | null>(null);

  const [editPlayerName, setEditPlayerName] = useState("");
  const [editPlayerMemberQuery, setEditPlayerMemberQuery] = useState("");
  const [editPlayerUserId, setEditPlayerUserId] = useState<number | null>(null);

  const loadData = useCallback(async () => {
    try {
      setError(null);
      const [gamesData, playersData, gameTypesData, membersData] = await Promise.all([
        fetchSpaceGames(token, space.id),
        fetchPlayers(token, space.id),
        fetchGameTypes(token, space.id),
        fetchSpaceMembers(token, space.id),
      ]);

      setGames(gamesData);
      setPlayers(playersData);
      setGameTypes(gameTypesData);
      setMembers(membersData);
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
      const createdGame = await createGame(token, space.id, {
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
      onOpenGame(createdGame.id);
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

  const loadSpaceStats = useCallback(async () => {
    try {
      setStatsLoading(true);
      setStatsError(null);

      const baseByPlayerId = new Map<number, PlayerStats>();
      players.forEach((player) => {
        baseByPlayerId.set(player.id, {
          playerId: player.id,
          playerName: player.name,
          roundsPlayed: 0,
          gamesPlayed: 0,
          wins: 0,
          winRate: 0,
        });
      });

      if (games.length > 0) {
        const detailsList = await Promise.all(
          games.map((game) => fetchGameDetails(token, space.id, game.id))
        );

        detailsList.forEach((details) => {
          details.players.forEach((gamePlayer) => {
            const existing = baseByPlayerId.get(gamePlayer.player_id);
            if (!existing) {
              baseByPlayerId.set(gamePlayer.player_id, {
                playerId: gamePlayer.player_id,
                playerName: gamePlayer.player_name,
                roundsPlayed: 0,
                gamesPlayed: 1,
                wins: gamePlayer.is_winner ? 1 : 0,
                winRate: 0,
              });
              return;
            }

            existing.gamesPlayed += 1;
            if (gamePlayer.is_winner) {
              existing.wins += 1;
            }
          });

          details.rounds.forEach((round) => {
            const scoresForRound = details.round_scores?.[String(round.id)] || {};
            Object.keys(scoresForRound).forEach((playerIdRaw) => {
              const playerId = Number(playerIdRaw);
              const existing = baseByPlayerId.get(playerId);
              if (!existing) {
                return;
              }
              existing.roundsPlayed += 1;
            });
          });
        });
      }

      const computed = Array.from(baseByPlayerId.values()).map((stats) => ({
        ...stats,
        winRate: stats.gamesPlayed > 0 ? (stats.wins / stats.gamesPlayed) * 100 : 0,
      }));

      computed.sort((a, b) => {
        if (b.winRate !== a.winRate) {
          return b.winRate - a.winRate;
        }
        if (b.wins !== a.wins) {
          return b.wins - a.wins;
        }
        if (b.gamesPlayed !== a.gamesPlayed) {
          return b.gamesPlayed - a.gamesPlayed;
        }
        return a.playerName.localeCompare(b.playerName);
      });

      setPlayerStats(computed);
    } catch (err) {
      if (err instanceof ApiError) {
        setStatsError(err.message);
      } else {
        setStatsError("Impossible de charger les statistiques de l'espace.");
      }
    } finally {
      setStatsLoading(false);
    }
  }, [games, players, space.id, token]);

  useEffect(() => {
    if (currentView !== "stats") {
      return;
    }
    void loadSpaceStats();
  }, [currentView, loadSpaceStats]);

  const mostActivePlayer = useMemo(() => {
    if (playerStats.length === 0) {
      return null;
    }

    const sorted = [...playerStats].sort((a, b) => {
      if (b.roundsPlayed !== a.roundsPlayed) {
        return b.roundsPlayed - a.roundsPlayed;
      }
      return a.playerName.localeCompare(b.playerName);
    });

    return sorted[0] || null;
  }, [playerStats]);

  const memberOptions = useMemo(
    () => members.map((member) => ({ id: member.user_id, label: member.username })),
    [members]
  );

  const linkedUserIds = useMemo(
    () => new Set(players.map((player) => player.user_id).filter((userId): userId is number => userId !== null && userId !== undefined)),
    [players]
  );

  const availableNewPlayerMemberOptions = useMemo(
    () => memberOptions.filter((option) => !linkedUserIds.has(option.id)),
    [linkedUserIds, memberOptions]
  );

  const availableEditMemberOptions = useMemo(
    () => memberOptions.filter((option) => option.id === editPlayerUserId || !linkedUserIds.has(option.id)),
    [editPlayerUserId, linkedUserIds, memberOptions]
  );

  const createPlayerSubmit = async () => {
    const trimmed = newPlayerName.trim();
    if (!trimmed) {
      setError("Le nom du joueur est requis.");
      return;
    }

    if (newPlayerUserId !== null && linkedUserIds.has(newPlayerUserId)) {
      setError("Ce compte est deja rattache a un autre joueur de cet espace.");
      return;
    }

    try {
      setCreatingPlayer(true);
      setError(null);
      await createPlayer(token, space.id, { name: trimmed, userId: newPlayerUserId });
      setNewPlayerName("");
      setNewPlayerMemberQuery("");
      setNewPlayerUserId(null);
      await loadData();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de creer le joueur.");
      }
    } finally {
      setCreatingPlayer(false);
    }
  };

  const startEditPlayer = (player: Player) => {
    setEditingPlayerId(player.id);
    setEditPlayerName(player.name);
    setEditPlayerUserId(player.user_id ?? null);

    const linkedMember = members.find((member) => member.user_id === (player.user_id ?? null));
    setEditPlayerMemberQuery(linkedMember ? linkedMember.username : "");
  };

  const cancelEditPlayer = () => {
    setEditingPlayerId(null);
    setEditPlayerName("");
    setEditPlayerMemberQuery("");
    setEditPlayerUserId(null);
  };

  const saveEditPlayer = async () => {
    if (!editingPlayerId) {
      return;
    }

    const trimmed = editPlayerName.trim();
    if (!trimmed) {
      setError("Le nom du joueur est requis.");
      return;
    }

    if (editPlayerUserId !== null) {
      const conflictingPlayer = players.find(
        (player) => player.id !== editingPlayerId && player.user_id === editPlayerUserId
      );

      if (conflictingPlayer) {
        setError("Ce compte est deja rattache a un autre joueur de cet espace.");
        return;
      }
    }

    try {
      setSavingPlayer(true);
      setError(null);
      await updatePlayer(token, space.id, editingPlayerId, {
        name: trimmed,
        userId: editPlayerUserId,
      });
      cancelEditPlayer();
      await loadData();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de mettre a jour le joueur.");
      }
    } finally {
      setSavingPlayer(false);
    }
  };

  const removePlayerSubmit = async (playerId: number) => {
    try {
      setDeletingPlayerId(playerId);
      setError(null);
      await deletePlayer(token, space.id, playerId);
      if (editingPlayerId === playerId) {
        cancelEditPlayer();
      }
      await loadData();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de supprimer le joueur.");
      }
    } finally {
      setDeletingPlayerId(null);
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
    <View style={[styles.container, { paddingTop: Math.max(insets.top, 12) + 8 }]}>
      <View style={styles.header}>
        {currentView === "menu" ? (
          <Pressable onPress={onBack}>
            <Text style={styles.back}>Retour</Text>
          </Pressable>
        ) : (
          <Pressable onPress={() => setCurrentView("menu")}>
            <Text style={styles.back}>Menu</Text>
          </Pressable>
        )}
        <Text style={styles.title} numberOfLines={1}>
          {space.name}
        </Text>
        {currentView === "menu" ? <View style={styles.headerPlaceholder} /> : (
          <Pressable onPress={loadData}>
            <Text style={styles.back}>Rafraichir</Text>
          </Pressable>
        )}
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      {currentView === "menu" ? (
        <View style={styles.menuContainer}>
          <Text style={styles.sectionTitle}>Que souhaites-tu faire ?</Text>

          <Pressable style={styles.menuCard} onPress={() => setCurrentView("games")}>
            <Text style={styles.menuTitle}>Consulter les parties</Text>
            <Text style={styles.menuSubtitle}>Voir toutes les parties de cet espace</Text>
          </Pressable>

          <Pressable style={styles.menuCard} onPress={() => setCurrentView("create")}>
            <Text style={styles.menuTitle}>Creer une partie</Text>
            <Text style={styles.menuSubtitle}>Ouvrir le formulaire de creation</Text>
          </Pressable>

          <Pressable style={styles.menuCard} onPress={() => setCurrentView("stats")}>
            <Text style={styles.menuTitle}>Consulter les statistiques</Text>
            <Text style={styles.menuSubtitle}>Activite des joueurs et taux de victoire</Text>
          </Pressable>

          <Pressable style={styles.menuCard} onPress={() => setCurrentView("players")}>
            <Text style={styles.menuTitle}>Gerer les joueurs</Text>
            <Text style={styles.menuSubtitle}>Afficher, modifier, rattacher un compte, supprimer</Text>
          </Pressable>
        </View>
      ) : null}

      {currentView === "create" ? (
        <ScrollView contentContainerStyle={styles.formCard} keyboardShouldPersistTaps="handled">
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
      ) : null}

      {currentView === "games" ? (
        <View style={styles.gamesContainer}>
          <Text style={styles.sectionTitle}>Parties</Text>
          <FlatList
            data={games}
            keyExtractor={(item) => String(item.id)}
            ListEmptyComponent={<Text style={styles.empty}>Aucune partie pour le moment.</Text>}
            renderItem={({ item }) => {
              const statusMeta = getStatusMeta(item.status);
              return (
                <Pressable style={styles.gameCard} onPress={() => onOpenGame(item.id)}>
                  <View style={styles.gameRow}>
                    <Text style={styles.gameTitle}>{item.game_type_name || "Partie"}</Text>
                    <View style={[styles.statusBadge, { backgroundColor: statusMeta.backgroundColor }]}> 
                      <Text style={[styles.statusText, { color: statusMeta.textColor }]}>{statusMeta.label}</Text>
                    </View>
                  </View>
                  <Text style={styles.gameMeta}>Joueurs: {item.player_count ?? "-"}</Text>
                </Pressable>
              );
            }}
          />
        </View>
      ) : null}

      {currentView === "stats" ? (
        <ScrollView contentContainerStyle={styles.formCard}>
          <Text style={styles.sectionTitle}>Statistiques de l'espace</Text>

          {statsLoading ? (
            <View style={styles.statsLoadingContainer}>
              <ActivityIndicator />
            </View>
          ) : null}

          {statsError ? <Text style={styles.error}>{statsError}</Text> : null}

          {!statsLoading && !statsError ? (
            <>
              <View style={styles.statsCard}>
                <Text style={styles.statsCardTitle}>Joueur le plus actif</Text>
                {mostActivePlayer ? (
                  <>
                    <Text style={styles.statsHighlightName}>{mostActivePlayer.playerName}</Text>
                    <Text style={styles.statsMeta}>Manches jouees: {mostActivePlayer.roundsPlayed}</Text>
                  </>
                ) : (
                  <Text style={styles.statsMeta}>Aucune donnee disponible.</Text>
                )}
              </View>

              <View style={styles.statsCard}>
                <Text style={styles.statsCardTitle}>Classement par taux de victoire</Text>

                {playerStats.length === 0 ? (
                  <Text style={styles.statsMeta}>Aucune donnee disponible.</Text>
                ) : (
                  playerStats.map((stats, index) => (
                    <View style={styles.statsRow} key={stats.playerId}>
                      <Text style={styles.statsRank}>{index + 1}.</Text>
                      <View style={styles.statsRowMain}>
                        <Text style={styles.statsPlayerName}>{stats.playerName}</Text>
                        <Text style={styles.statsMeta}>
                          {stats.winRate.toFixed(1)}% ({stats.wins} victoire{stats.wins > 1 ? "s" : ""} / {stats.gamesPlayed} partie{stats.gamesPlayed > 1 ? "s" : ""})
                        </Text>
                      </View>
                    </View>
                  ))
                )}
              </View>
            </>
          ) : null}
        </ScrollView>
      ) : null}

      {currentView === "players" ? (
        <ScrollView contentContainerStyle={styles.formCard} keyboardShouldPersistTaps="handled">
          <Text style={styles.sectionTitle}>Gerer les joueurs</Text>

          <View style={styles.playerEditorCard}>
            <Text style={styles.playerEditorTitle}>Nouveau joueur</Text>
            <TextInput
              value={newPlayerName}
              onChangeText={setNewPlayerName}
              placeholder="Nom du joueur"
              style={styles.input}
            />

            <View style={styles.spacer} />

            <AutocompleteSelect
              label="Rattacher a un compte (optionnel)"
              query={newPlayerMemberQuery}
              onQueryChange={setNewPlayerMemberQuery}
              options={availableNewPlayerMemberOptions}
              onSelect={(id) => {
                const member = members.find((m) => m.user_id === id);
                setNewPlayerUserId(id);
                setNewPlayerMemberQuery(member ? member.username : "");
              }}
              placeholder="Rechercher un membre"
            />

            {newPlayerUserId ? (
              <Pressable
                onPress={() => {
                  setNewPlayerUserId(null);
                  setNewPlayerMemberQuery("");
                }}
              >
                <Text style={styles.unlinkText}>Retirer la liaison</Text>
              </Pressable>
            ) : null}

            <Pressable
              style={[styles.primaryButton, creatingPlayer ? styles.disabledButton : undefined]}
              disabled={creatingPlayer}
              onPress={createPlayerSubmit}
            >
              <Text style={styles.primaryText}>{creatingPlayer ? "Creation..." : "Ajouter le joueur"}</Text>
            </Pressable>
          </View>

          <View style={styles.playerListSection}>
            {players.length === 0 ? <Text style={styles.empty}>Aucun joueur dans cet espace.</Text> : null}

            {players.map((player) => (
              <View key={player.id} style={styles.playerCard}>
                <View style={styles.playerCardHeader}>
                  <View style={styles.playerCardHeaderMain}>
                    <Text style={styles.playerName}>{player.name}</Text>
                    <Text style={styles.playerLinkInfo}>
                      {player.user_id
                        ? `Compte lie: ${player.linked_username || `Utilisateur #${player.user_id}`}`
                        : "Aucun compte lie"}
                    </Text>
                  </View>

                  <View style={styles.playerActionsRow}>
                    <Pressable onPress={() => startEditPlayer(player)}>
                      <Text style={styles.linkAction}>Modifier</Text>
                    </Pressable>
                    <Pressable
                      disabled={deletingPlayerId === player.id}
                      onPress={() => removePlayerSubmit(player.id)}
                    >
                      <Text style={styles.deleteAction}>
                        {deletingPlayerId === player.id ? "Suppression..." : "Supprimer"}
                      </Text>
                    </Pressable>
                  </View>
                </View>

                {editingPlayerId === player.id ? (
                  <View style={styles.inlineEditor}>
                    <TextInput
                      value={editPlayerName}
                      onChangeText={setEditPlayerName}
                      placeholder="Nom du joueur"
                      style={styles.input}
                    />

                    <View style={styles.spacer} />

                    <AutocompleteSelect
                      label="Rattacher a un compte (optionnel)"
                      query={editPlayerMemberQuery}
                      onQueryChange={setEditPlayerMemberQuery}
                      options={availableEditMemberOptions}
                      onSelect={(id) => {
                        const member = members.find((m) => m.user_id === id);
                        setEditPlayerUserId(id);
                        setEditPlayerMemberQuery(member ? member.username : "");
                      }}
                      placeholder="Rechercher un membre"
                    />

                    {editPlayerUserId ? (
                      <Pressable
                        onPress={() => {
                          setEditPlayerUserId(null);
                          setEditPlayerMemberQuery("");
                        }}
                      >
                        <Text style={styles.unlinkText}>Retirer la liaison</Text>
                      </Pressable>
                    ) : null}

                    <View style={styles.inlineEditorActions}>
                      <Pressable
                        style={[styles.secondaryButton, savingPlayer ? styles.disabledButton : undefined]}
                        disabled={savingPlayer}
                        onPress={saveEditPlayer}
                      >
                        <Text style={styles.secondaryButtonText}>
                          {savingPlayer ? "Enregistrement..." : "Enregistrer"}
                        </Text>
                      </Pressable>

                      <Pressable style={styles.ghostButton} onPress={cancelEditPlayer}>
                        <Text style={styles.ghostButtonText}>Annuler</Text>
                      </Pressable>
                    </View>
                  </View>
                ) : null}
              </View>
            ))}
          </View>
        </ScrollView>
      ) : null}
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
  headerPlaceholder: {
    width: 70,
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
  menuContainer: {
    gap: 10,
  },
  menuCard: {
    backgroundColor: theme.colors.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.lg,
    padding: 14,
  },
  menuTitle: {
    color: theme.colors.text,
    fontWeight: "700",
    fontSize: 17,
  },
  menuSubtitle: {
    color: theme.colors.mutedText,
    marginTop: 4,
  },
  formCard: {
    backgroundColor: theme.colors.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.lg,
    padding: 14,
    marginBottom: 14,
  },
  gamesContainer: {
    flex: 1,
  },
  statsLoadingContainer: {
    paddingVertical: 20,
    alignItems: "center",
    justifyContent: "center",
  },
  statsCard: {
    marginTop: 10,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    padding: 12,
    backgroundColor: theme.colors.card,
  },
  statsCardTitle: {
    color: theme.colors.text,
    fontWeight: "700",
    fontSize: 16,
    marginBottom: 8,
  },
  statsHighlightName: {
    color: theme.colors.primary,
    fontWeight: "700",
    fontSize: 18,
  },
  statsMeta: {
    color: theme.colors.mutedText,
    marginTop: 4,
  },
  statsRow: {
    flexDirection: "row",
    alignItems: "flex-start",
    marginBottom: 10,
  },
  statsRank: {
    width: 28,
    color: theme.colors.text,
    fontWeight: "700",
  },
  statsRowMain: {
    flex: 1,
  },
  statsPlayerName: {
    color: theme.colors.text,
    fontWeight: "700",
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
  secondaryButton: {
    backgroundColor: theme.colors.primarySoft,
    borderRadius: theme.radius.md,
    paddingVertical: 10,
    alignItems: "center",
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  secondaryButtonText: {
    color: theme.colors.primary,
    fontWeight: "700",
  },
  ghostButton: {
    marginTop: 8,
    alignItems: "center",
    paddingVertical: 8,
  },
  ghostButtonText: {
    color: theme.colors.mutedText,
    fontWeight: "600",
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
    flex: 1,
    marginRight: 10,
  },
  gameRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  statusBadge: {
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  statusText: {
    fontWeight: "700",
    fontSize: 13,
  },
  gameMeta: {
    color: theme.colors.mutedText,
    marginTop: 4,
  },
  playerEditorCard: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    padding: 12,
    backgroundColor: theme.colors.card,
  },
  playerEditorTitle: {
    color: theme.colors.text,
    fontWeight: "700",
    marginBottom: 10,
  },
  unlinkText: {
    marginTop: 8,
    color: theme.colors.mutedText,
    fontWeight: "600",
  },
  playerListSection: {
    marginTop: 12,
  },
  playerCard: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    padding: 12,
    backgroundColor: theme.colors.card,
    marginBottom: 10,
  },
  playerCardHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-start",
  },
  playerCardHeaderMain: {
    flex: 1,
    marginRight: 8,
  },
  playerName: {
    color: theme.colors.text,
    fontWeight: "700",
    fontSize: 16,
  },
  playerLinkInfo: {
    color: theme.colors.mutedText,
    marginTop: 4,
  },
  playerActionsRow: {
    alignItems: "flex-end",
    gap: 8,
  },
  linkAction: {
    color: theme.colors.primary,
    fontWeight: "700",
  },
  deleteAction: {
    color: theme.colors.danger,
    fontWeight: "700",
  },
  inlineEditor: {
    marginTop: 10,
  },
  inlineEditorActions: {
    marginTop: 10,
  },
});
