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
  createGameType,
  createPlayer,
  createGame,
  createInviteLink,
  deleteGameType,
  deletePlayer,
  fetchGameDetails,
  fetchGameTypes,
  fetchPlayers,
  fetchSpaceMembers,
  fetchSpaceGames,
  inviteMember,
  removeSpaceMember,
  updateGameType,
  updateMemberRole,
  updatePlayer,
} from "../services/api";
import { theme } from "../styles/theme";
import type { Game, GameType, Player, Space, SpaceMember } from "../types/api";
import { getRoleLabel } from "../utils/roles";

type Props = {
  token: string;
  space: Space;
  onBack: () => void;
  onOpenGame: (gameId: number) => void;
};

type SpaceView = "menu" | "games" | "create" | "stats" | "players" | "gameTypes" | "members";

const MEMBER_ROLES: SpaceMember["role"][] = ["admin", "manager", "member", "guest"];

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

function getWinConditionLabel(winCondition: GameType["win_condition"]): string {
  switch (winCondition) {
    case "highest_score":
      return "Score le plus eleve";
    case "lowest_score":
      return "Score le plus bas";
    case "ranking":
      return "Classement";
    case "win_loss":
      return "Victoire/Defaite";
    default:
      return winCondition;
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
  const [creatingGameType, setCreatingGameType] = useState(false);
  const [editingGameTypeId, setEditingGameTypeId] = useState<number | null>(null);
  const [savingGameType, setSavingGameType] = useState(false);
  const [deletingGameTypeId, setDeletingGameTypeId] = useState<number | null>(null);

  const [newGameTypeName, setNewGameTypeName] = useState("");
  const [newGameTypeDescription, setNewGameTypeDescription] = useState("");
  const [newGameTypeWinCondition, setNewGameTypeWinCondition] = useState<GameType["win_condition"]>("highest_score");
  const [newGameTypeMinPlayers, setNewGameTypeMinPlayers] = useState("1");
  const [newGameTypeMaxPlayers, setNewGameTypeMaxPlayers] = useState("");

  const [editGameTypeName, setEditGameTypeName] = useState("");
  const [editGameTypeDescription, setEditGameTypeDescription] = useState("");
  const [editGameTypeWinCondition, setEditGameTypeWinCondition] = useState<GameType["win_condition"]>("highest_score");
  const [editGameTypeMinPlayers, setEditGameTypeMinPlayers] = useState("1");
  const [editGameTypeMaxPlayers, setEditGameTypeMaxPlayers] = useState("");

  // ─── Members state ───────────────────────────────────────────────────────
  const [editingMemberId, setEditingMemberId] = useState<number | null>(null);
  const [editMemberRole, setEditMemberRole] = useState<SpaceMember["role"]>("member");
  const [savingMemberRole, setSavingMemberRole] = useState(false);
  const [removingMemberId, setRemovingMemberId] = useState<number | null>(null);
  const [inviteUsername, setInviteUsername] = useState("");
  const [inviteRole, setInviteRole] = useState<SpaceMember["role"]>("member");
  const [inviting, setInviting] = useState(false);
  const [generatingLink, setGeneratingLink] = useState(false);
  const [inviteToken, setInviteToken] = useState<string | null>(null);

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

  const restrictedParticipationUserIds = useMemo(
    () => new Set(members.filter((member) => member.games_participation_restricted).map((member) => member.user_id)),
    [members]
  );

  const availablePlayersForGame = useMemo(
    () => players.filter((player) => !player.user_id || !restrictedParticipationUserIds.has(player.user_id)),
    [players, restrictedParticipationUserIds]
  );

  const selectedPlayers = useMemo(
    () => availablePlayersForGame.filter((player) => selectedPlayerIds.includes(player.id)),
    [availablePlayersForGame, selectedPlayerIds]
  );

  const selectablePlayers = useMemo(
    () => availablePlayersForGame.filter((player) => !selectedPlayerIds.includes(player.id)),
    [availablePlayersForGame, selectedPlayerIds]
  );

  useEffect(() => {
    const allowedIds = new Set(availablePlayersForGame.map((player) => player.id));
    setSelectedPlayerIds((current) => current.filter((id) => allowedIds.has(id)));
  }, [availablePlayersForGame]);

  const selectGameType = (id: number) => {
    const gameType = gameTypes.find((item) => item.id === id);
    setSelectedGameTypeId(id);
    setGameTypeQuery(gameType ? gameType.name : "");
  };

  const addPlayer = (id: number) => {
    const player = availablePlayersForGame.find((item) => item.id === id);
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

  const createGameTypeSubmit = async () => {
    const trimmed = newGameTypeName.trim();
    if (!trimmed) {
      setError("Le nom du type de jeu est requis.");
      return;
    }

    const minPlayers = parsePlayerCount(newGameTypeMinPlayers, 1);
    const maxPlayers = parseOptionalPlayerCount(newGameTypeMaxPlayers);

    if (maxPlayers !== null && maxPlayers < minPlayers) {
      setError("Le nombre maximum de joueurs doit etre superieur ou egal au minimum.");
      return;
    }

    try {
      setCreatingGameType(true);
      setError(null);
      await createGameType(token, space.id, {
        name: trimmed,
        description: newGameTypeDescription,
        winCondition: newGameTypeWinCondition,
        minPlayers,
        maxPlayers,
      });
      setNewGameTypeName("");
      setNewGameTypeDescription("");
      setNewGameTypeWinCondition("highest_score");
      setNewGameTypeMinPlayers("1");
      setNewGameTypeMaxPlayers("");
      await loadData();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de creer le type de jeu.");
      }
    } finally {
      setCreatingGameType(false);
    }
  };

  const startEditGameType = (gameType: GameType) => {
    setEditingGameTypeId(gameType.id);
    setEditGameTypeName(gameType.name);
    setEditGameTypeDescription(gameType.description ?? "");
    setEditGameTypeWinCondition(gameType.win_condition);
    setEditGameTypeMinPlayers(String(gameType.min_players ?? 1));
    setEditGameTypeMaxPlayers(gameType.max_players ? String(gameType.max_players) : "");
  };

  const cancelEditGameType = () => {
    setEditingGameTypeId(null);
    setEditGameTypeName("");
    setEditGameTypeDescription("");
    setEditGameTypeWinCondition("highest_score");
    setEditGameTypeMinPlayers("1");
    setEditGameTypeMaxPlayers("");
  };

  const saveEditGameType = async () => {
    if (!editingGameTypeId) {
      return;
    }

    const trimmed = editGameTypeName.trim();
    if (!trimmed) {
      setError("Le nom du type de jeu est requis.");
      return;
    }

    const minPlayers = parsePlayerCount(editGameTypeMinPlayers, 1);
    const maxPlayers = parseOptionalPlayerCount(editGameTypeMaxPlayers);

    if (maxPlayers !== null && maxPlayers < minPlayers) {
      setError("Le nombre maximum de joueurs doit etre superieur ou egal au minimum.");
      return;
    }

    try {
      setSavingGameType(true);
      setError(null);
      await updateGameType(token, space.id, editingGameTypeId, {
        name: trimmed,
        description: editGameTypeDescription,
        winCondition: editGameTypeWinCondition,
        minPlayers,
        maxPlayers,
      });
      cancelEditGameType();
      await loadData();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de mettre a jour le type de jeu.");
      }
    } finally {
      setSavingGameType(false);
    }
  };

  const removeGameTypeSubmit = async (gameTypeId: number) => {
    try {
      setDeletingGameTypeId(gameTypeId);
      setError(null);
      await deleteGameType(token, space.id, gameTypeId);
      if (editingGameTypeId === gameTypeId) {
        cancelEditGameType();
      }
      await loadData();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de supprimer le type de jeu.");
      }
    } finally {
      setDeletingGameTypeId(null);
    }
  };

  // ─── Members handlers ───────────────────────────────────────────────────
  const saveEditMemberRole = async (memberId: number) => {
    try {
      setSavingMemberRole(true);
      setError(null);
      await updateMemberRole(token, space.id, memberId, editMemberRole);
      setEditingMemberId(null);
      await loadData();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Impossible de modifier le role.");
    } finally {
      setSavingMemberRole(false);
    }
  };

  const removeMemberSubmit = async (memberId: number) => {
    try {
      setRemovingMemberId(memberId);
      setError(null);
      await removeSpaceMember(token, space.id, memberId);
      if (editingMemberId === memberId) setEditingMemberId(null);
      await loadData();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Impossible de retirer ce membre.");
    } finally {
      setRemovingMemberId(null);
    }
  };

  const inviteMemberSubmit = async () => {
    const username = inviteUsername.trim();
    if (!username) {
      setError("Le nom d\'utilisateur est requis.");
      return;
    }
    try {
      setInviting(true);
      setError(null);
      await inviteMember(token, space.id, username, inviteRole);
      setInviteUsername("");
      setInviteRole("member");
      await loadData();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Impossible d\'envoyer l\'invitation.");
    } finally {
      setInviting(false);
    }
  };

  const handleGenerateLink = async () => {
    try {
      setGeneratingLink(true);
      setError(null);
      const newToken = await createInviteLink(token, space.id);
      setInviteToken(newToken);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Impossible de generer le lien.");
    } finally {
      setGeneratingLink(false);
    }
  };

  const isAdmin = space.user_role === "admin";
  const canManageMembers = isAdmin || space.user_role === "manager";

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

          <Pressable style={styles.menuCard} onPress={() => setCurrentView("gameTypes")}>
            <Text style={styles.menuTitle}>Gerer les types de jeu</Text>
            <Text style={styles.menuSubtitle}>Afficher, creer, modifier et supprimer</Text>
          </Pressable>

          {canManageMembers ? (
            <Pressable
              style={styles.menuCard}
              onPress={() => {
                setInviteToken(null);
                setError(null);
                setEditingMemberId(null);
                setCurrentView("members");
              }}
            >
              <Text style={styles.menuTitle}>Gerer les membres</Text>
              <Text style={styles.menuSubtitle}>Liste, roles, invitations et liens d\'acces</Text>
            </Pressable>
          ) : null}
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

      {currentView === "gameTypes" ? (
        <ScrollView contentContainerStyle={styles.formCard} keyboardShouldPersistTaps="handled">
          <Text style={styles.sectionTitle}>Gerer les types de jeu</Text>

          <View style={styles.playerEditorCard}>
            <Text style={styles.playerEditorTitle}>Nouveau type de jeu</Text>
            <TextInput
              value={newGameTypeName}
              onChangeText={setNewGameTypeName}
              placeholder="Nom du type de jeu"
              style={styles.input}
            />

            <Text style={styles.label}>Description</Text>
            <TextInput
              value={newGameTypeDescription}
              onChangeText={setNewGameTypeDescription}
              placeholder="Description optionnelle"
              style={[styles.input, styles.notes]}
              multiline
            />

            <Text style={styles.label}>Condition de victoire</Text>
            <View style={styles.conditionOptionsRow}>
              {(["highest_score", "lowest_score", "ranking", "win_loss"] as const).map((condition) => (
                <Pressable
                  key={condition}
                  style={[
                    styles.conditionOption,
                    newGameTypeWinCondition === condition ? styles.conditionOptionActive : undefined,
                  ]}
                  onPress={() => setNewGameTypeWinCondition(condition)}
                >
                  <Text
                    style={[
                      styles.conditionOptionText,
                      newGameTypeWinCondition === condition ? styles.conditionOptionTextActive : undefined,
                    ]}
                  >
                    {getWinConditionLabel(condition)}
                  </Text>
                </Pressable>
              ))}
            </View>

            <View style={styles.countInputsRow}>
              <View style={styles.countInputBlock}>
                <Text style={styles.label}>Joueurs min.</Text>
                <TextInput
                  value={newGameTypeMinPlayers}
                  onChangeText={setNewGameTypeMinPlayers}
                  keyboardType="numeric"
                  style={styles.input}
                />
              </View>

              <View style={styles.countInputBlock}>
                <Text style={styles.label}>Joueurs max.</Text>
                <TextInput
                  value={newGameTypeMaxPlayers}
                  onChangeText={setNewGameTypeMaxPlayers}
                  keyboardType="numeric"
                  placeholder="Illimite"
                  style={styles.input}
                />
              </View>
            </View>

            <Pressable
              style={[styles.primaryButton, creatingGameType ? styles.disabledButton : undefined]}
              disabled={creatingGameType}
              onPress={createGameTypeSubmit}
            >
              <Text style={styles.primaryText}>{creatingGameType ? "Creation..." : "Ajouter le type de jeu"}</Text>
            </Pressable>
          </View>

          <View style={styles.playerListSection}>
            {gameTypes.length === 0 ? <Text style={styles.empty}>Aucun type de jeu dans cet espace.</Text> : null}

            {gameTypes.map((gameType) => (
              <View key={gameType.id} style={styles.playerCard}>
                <View style={styles.playerCardHeader}>
                  <View style={styles.playerCardHeaderMain}>
                    <Text style={styles.playerName}>{gameType.name}</Text>
                    <Text style={styles.playerLinkInfo}>{getWinConditionLabel(gameType.win_condition)}</Text>
                    <Text style={styles.playerLinkInfo}>
                      Joueurs: min {gameType.min_players ?? 1}
                      {gameType.max_players ? ` / max ${gameType.max_players}` : " / max illimite"}
                    </Text>
                    {gameType.description ? <Text style={styles.playerLinkInfo}>{gameType.description}</Text> : null}
                  </View>

                  <View style={styles.playerActionsRow}>
                    <Pressable onPress={() => startEditGameType(gameType)}>
                      <Text style={styles.linkAction}>Modifier</Text>
                    </Pressable>
                    <Pressable
                      disabled={deletingGameTypeId === gameType.id}
                      onPress={() => removeGameTypeSubmit(gameType.id)}
                    >
                      <Text style={styles.deleteAction}>
                        {deletingGameTypeId === gameType.id ? "Suppression..." : "Supprimer"}
                      </Text>
                    </Pressable>
                  </View>
                </View>

                {editingGameTypeId === gameType.id ? (
                  <View style={styles.inlineEditor}>
                    <TextInput
                      value={editGameTypeName}
                      onChangeText={setEditGameTypeName}
                      placeholder="Nom du type de jeu"
                      style={styles.input}
                    />

                    <Text style={styles.label}>Description</Text>
                    <TextInput
                      value={editGameTypeDescription}
                      onChangeText={setEditGameTypeDescription}
                      placeholder="Description optionnelle"
                      style={[styles.input, styles.notes]}
                      multiline
                    />

                    <Text style={styles.label}>Condition de victoire</Text>
                    <View style={styles.conditionOptionsRow}>
                      {(["highest_score", "lowest_score", "ranking", "win_loss"] as const).map((condition) => (
                        <Pressable
                          key={condition}
                          style={[
                            styles.conditionOption,
                            editGameTypeWinCondition === condition ? styles.conditionOptionActive : undefined,
                          ]}
                          onPress={() => setEditGameTypeWinCondition(condition)}
                        >
                          <Text
                            style={[
                              styles.conditionOptionText,
                              editGameTypeWinCondition === condition ? styles.conditionOptionTextActive : undefined,
                            ]}
                          >
                            {getWinConditionLabel(condition)}
                          </Text>
                        </Pressable>
                      ))}
                    </View>

                    <View style={styles.countInputsRow}>
                      <View style={styles.countInputBlock}>
                        <Text style={styles.label}>Joueurs min.</Text>
                        <TextInput
                          value={editGameTypeMinPlayers}
                          onChangeText={setEditGameTypeMinPlayers}
                          keyboardType="numeric"
                          style={styles.input}
                        />
                      </View>

                      <View style={styles.countInputBlock}>
                        <Text style={styles.label}>Joueurs max.</Text>
                        <TextInput
                          value={editGameTypeMaxPlayers}
                          onChangeText={setEditGameTypeMaxPlayers}
                          keyboardType="numeric"
                          placeholder="Illimite"
                          style={styles.input}
                        />
                      </View>
                    </View>

                    <View style={styles.inlineEditorActions}>
                      <Pressable
                        style={[styles.secondaryButton, savingGameType ? styles.disabledButton : undefined]}
                        disabled={savingGameType}
                        onPress={saveEditGameType}
                      >
                        <Text style={styles.secondaryButtonText}>
                          {savingGameType ? "Enregistrement..." : "Enregistrer"}
                        </Text>
                      </Pressable>

                      <Pressable style={styles.ghostButton} onPress={cancelEditGameType}>
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

      {currentView === "members" ? (
        <ScrollView contentContainerStyle={styles.formCard} keyboardShouldPersistTaps="handled">
          <Text style={styles.sectionTitle}>Membres de l'espace</Text>

          {error ? <Text style={styles.error}>{error}</Text> : null}

          {members.length === 0 ? (
            <Text style={styles.empty}>Aucun membre.</Text>
          ) : (
            members.map((member) => (
              <View key={member.id} style={styles.playerCard}>
                <View style={styles.playerCardHeader}>
                  <View style={styles.playerCardHeaderMain}>
                    <Text style={styles.playerName}>{member.username}</Text>
                    <Text style={styles.playerLinkInfo}>{getRoleLabel(member.role)}</Text>
                  </View>

                  {isAdmin ? (
                    <View style={styles.playerActionsRow}>
                      {editingMemberId !== member.id ? (
                        <Pressable
                          onPress={() => {
                            setEditingMemberId(member.id);
                            setEditMemberRole(member.role);
                          }}
                        >
                          <Text style={styles.linkAction}>Role</Text>
                        </Pressable>
                      ) : null}
                      <Pressable
                        disabled={removingMemberId === member.id}
                        onPress={() => removeMemberSubmit(member.id)}
                      >
                        <Text style={styles.deleteAction}>
                          {removingMemberId === member.id ? "Retrait..." : "Retirer"}
                        </Text>
                      </Pressable>
                    </View>
                  ) : null}
                </View>

                {isAdmin && editingMemberId === member.id ? (
                  <View style={styles.inlineEditor}>
                    <Text style={styles.label}>Nouveau role</Text>
                    <View style={styles.conditionOptionsRow}>
                      {MEMBER_ROLES.map((role) => (
                        <Pressable
                          key={role}
                          style={[
                            styles.conditionOption,
                            editMemberRole === role ? styles.conditionOptionActive : undefined,
                          ]}
                          onPress={() => setEditMemberRole(role)}
                        >
                          <Text
                            style={[
                              styles.conditionOptionText,
                              editMemberRole === role ? styles.conditionOptionTextActive : undefined,
                            ]}
                          >
                            {getRoleLabel(role)}
                          </Text>
                        </Pressable>
                      ))}
                    </View>
                    <View style={styles.inlineEditorActions}>
                      <Pressable
                        style={[styles.secondaryButton, savingMemberRole ? styles.disabledButton : undefined]}
                        disabled={savingMemberRole}
                        onPress={() => saveEditMemberRole(member.id)}
                      >
                        <Text style={styles.secondaryButtonText}>
                          {savingMemberRole ? "Enregistrement..." : "Enregistrer"}
                        </Text>
                      </Pressable>
                      <Pressable style={styles.ghostButton} onPress={() => setEditingMemberId(null)}>
                        <Text style={styles.ghostButtonText}>Annuler</Text>
                      </Pressable>
                    </View>
                  </View>
                ) : null}
              </View>
            ))
          )}

          <View style={[styles.playerEditorCard, { marginTop: 16 }]}>
            <Text style={styles.playerEditorTitle}>Inviter un utilisateur</Text>
            <TextInput
              value={inviteUsername}
              onChangeText={setInviteUsername}
              placeholder="Nom d'utilisateur"
              style={styles.input}
              autoCapitalize="none"
            />
            <Text style={styles.label}>Role attribue</Text>
            <View style={styles.conditionOptionsRow}>
              {MEMBER_ROLES.map((role) => (
                <Pressable
                  key={role}
                  style={[
                    styles.conditionOption,
                    inviteRole === role ? styles.conditionOptionActive : undefined,
                  ]}
                  onPress={() => setInviteRole(role)}
                >
                  <Text
                    style={[
                      styles.conditionOptionText,
                      inviteRole === role ? styles.conditionOptionTextActive : undefined,
                    ]}
                  >
                    {getRoleLabel(role)}
                  </Text>
                </Pressable>
              ))}
            </View>
            <Pressable
              style={[styles.primaryButton, inviting ? styles.disabledButton : undefined]}
              disabled={inviting}
              onPress={inviteMemberSubmit}
            >
              <Text style={styles.primaryText}>{inviting ? "Envoi..." : "Envoyer l'invitation"}</Text>
            </Pressable>
          </View>

          <View style={[styles.playerEditorCard, { marginTop: 16 }]}>
            <Text style={styles.playerEditorTitle}>Lien d'invitation</Text>
            <Pressable
              style={[styles.primaryButton, generatingLink ? styles.disabledButton : undefined]}
              disabled={generatingLink}
              onPress={handleGenerateLink}
            >
              <Text style={styles.primaryText}>{generatingLink ? "Generation..." : "Generer un lien"}</Text>
            </Pressable>
            {inviteToken ? (
              <View style={styles.inviteLinkBox}>
                <Text style={styles.inviteLinkLabel}>Lien valable 72h :</Text>
                <Text style={styles.inviteLinkText} selectable>
                  {`https://scores.leofranz.fr/spaces/join/${inviteToken}`}
                </Text>
              </View>
            ) : null}
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
  conditionOptionsRow: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 8,
    marginTop: 4,
  },
  conditionOption: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 8,
    backgroundColor: theme.colors.card,
  },
  conditionOptionActive: {
    backgroundColor: theme.colors.primarySoft,
    borderColor: theme.colors.primary,
  },
  conditionOptionText: {
    color: theme.colors.text,
    fontWeight: "600",
  },
  conditionOptionTextActive: {
    color: theme.colors.primary,
  },
  countInputsRow: {
    flexDirection: "row",
    gap: 10,
    marginTop: 8,
  },
  countInputBlock: {
    flex: 1,
  },
  inviteLinkBox: {
    marginTop: 14,
    backgroundColor: theme.colors.background,
    borderRadius: theme.radius.md,
    padding: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  inviteLinkLabel: {
    color: theme.colors.mutedText,
    fontWeight: "600",
    marginBottom: 6,
  },
  inviteLinkText: {
    color: theme.colors.primary,
    fontWeight: "600",
  },
});
