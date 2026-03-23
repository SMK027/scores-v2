import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Alert,
  ActivityIndicator,
  FlatList,
  Image,
  Modal,
  Pressable,
  Share,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  useWindowDimensions,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { AutocompleteSelect } from "../components/AutocompleteSelect";
import {
  ApiError,
  createGame,
  createInviteLink,
  fetchCompetitions,
  fetchLeaderboard,
  deleteGameType,
  deletePlayer,
  fetchGameDetails,
  fetchGameTypes,
  fetchPlayers,
  fetchSpaceSearch,
  fetchSpaceMembers,
  fetchSpaceGames,
  inviteMember,
  removeSpaceMember,
  updateGameType,
  updateMemberRole,
  updatePlayer,
  deleteMemberCard,
  fetchMemberCard,
  generateMemberCard,
  regenerateMemberCard,
  toggleMemberCard,
} from "../services/api";
import { useAppTheme } from "../context/ThemeContext";
import type { AppTheme } from "../styles";
import type { Competition, Game, GameType, LeaderboardEntry, MemberCard, Player, SearchResults, Space, SpaceMember, User } from "../types/api";
import { getAvatarUri, getInitials } from "../utils/avatar";
import { getRoleLabel } from "../utils/roles";

type Props = {
  token: string;
  user: User;
  space: Space;
  onBack: () => void;
  onOpenProfile: () => void;
  onOpenGame: (gameId: number) => void;
  onOpenCompetition: (competitionId: number) => void;
  onOpenCreatePlayer: () => void;
  onOpenCreateGameType: () => void;
};

type SpaceView =
  | "menu"
  | "games"
  | "create"
  | "stats"
  | "players"
  | "gameTypes"
  | "members"
  | "search"
  | "leaderboard"
  | "competitions"
  | "card";

const MEMBER_ROLES: SpaceMember["role"][] = ["admin", "manager", "member", "guest"];

type PlayerStats = {
  playerId: number;
  playerName: string;
  roundsPlayed: number;
  roundsWon: number;
  gamesPlayed: number;
  wins: number;
  winRate: number;
};

function getStatusMeta(status: Game["status"], theme: AppTheme): {
  label: string;
  backgroundColor: string;
  textColor: string;
} {
  switch (status) {
    case "in_progress":
      return {
        label: "En cours",
        backgroundColor: theme.colors.backgroundSoft,
        textColor: theme.colors.success,
      };
    case "completed":
      return {
        label: "Terminée",
        backgroundColor: theme.colors.primarySoft,
        textColor: theme.colors.primary,
      };
    case "paused":
      return {
        label: "En pause",
        backgroundColor: theme.colors.backgroundSoft,
        textColor: theme.colors.warning,
      };
    case "pending":
    default:
      return {
        label: "En attente",
        backgroundColor: theme.colors.backgroundSoft,
        textColor: theme.colors.mutedText,
      };
  }
}

function getCompetitionStatusMeta(status: Competition["status"], theme: AppTheme): {
  backgroundColor: string;
  textColor: string;
} {
  switch (status) {
    case "active":
      return { backgroundColor: theme.colors.backgroundSoft, textColor: theme.colors.success };
    case "paused":
      return { backgroundColor: theme.colors.backgroundSoft, textColor: theme.colors.warning };
    case "planned":
      return { backgroundColor: theme.colors.backgroundSoft, textColor: theme.colors.mutedText };
    case "closed":
    default:
      return { backgroundColor: theme.colors.primarySoft, textColor: theme.colors.primary };
  }
}

function getWinConditionLabel(winCondition: GameType["win_condition"]): string {
  switch (winCondition) {
    case "highest_score":
      return "Score le plus élevé";
    case "lowest_score":
      return "Score le plus bas";
    case "ranking":
      return "Classement";
    case "win_loss":
      return "Victoire/Défaite";
    default:
      return winCondition;
  }
}

function getViewMeta(view: Exclude<SpaceView, "menu">): { title: string; hint: string } {
  switch (view) {
    case "games":
      return { title: "Parties", hint: "Consultez et ouvrez rapidement vos parties." };
    case "create":
      return { title: "Créer une partie", hint: "Configurez la partie puis lancez-la." };
    case "stats":
      return { title: "Statistiques", hint: "Suivez la dynamique des joueurs de cet espace." };
    case "players":
      return { title: "Joueurs", hint: "Gérez les joueurs liés à cet espace." };
    case "gameTypes":
      return { title: "Types de jeu", hint: "Paramétrez les règles et conditions de victoire." };
    case "members":
      return { title: "Membres", hint: "Invitez et organisez les rôles des membres." };
    case "search":
      return { title: "Recherche", hint: "Filtrez les contenus de l'espace en un seul endroit." };
    case "leaderboard":
      return { title: "Leaderboard", hint: "Comparez les performances globales." };
    case "competitions":
      return { title: "Compétitions", hint: "Consultez et suivez les compétitions en cours." };
    case "card":
      return { title: "Ma carte de membre", hint: "Votre carte de membre numérique pour cet espace." };
    default:
      return { title: "Section", hint: "" };
  }
}

function formatShortDate(value?: string | null): string {
  if (!value) {
    return "Date inconnue";
  }

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return "Date inconnue";
  }

  return parsed.toLocaleDateString("fr-FR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
}

export function SpaceScreen({ token, user, space, onBack, onOpenProfile, onOpenGame, onOpenCompetition, onOpenCreatePlayer, onOpenCreateGameType }: Props) {
  const { theme } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);

  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const [loading, setLoading] = useState(true);
  const [gamesLoading, setGamesLoading] = useState(false);
  const [games, setGames] = useState<Game[]>([]);
  const [gamesStatusFilter, setGamesStatusFilter] = useState<"all" | "completed" | "in_progress" | "paused">("all");
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
  const [activeGroup, setActiveGroup] = useState<"view" | "manage" | null>(null);
  const [statsLoading, setStatsLoading] = useState(false);
  const [statsError, setStatsError] = useState<string | null>(null);
  const [playerStats, setPlayerStats] = useState<PlayerStats[]>([]);
  const [editingPlayerId, setEditingPlayerId] = useState<number | null>(null);
  const [savingPlayer, setSavingPlayer] = useState(false);
  const [deletingPlayerId, setDeletingPlayerId] = useState<number | null>(null);

  const [editPlayerName, setEditPlayerName] = useState("");
  const [editPlayerMemberQuery, setEditPlayerMemberQuery] = useState("");
  const [editPlayerUserId, setEditPlayerUserId] = useState<number | null>(null);
  const [editingGameTypeId, setEditingGameTypeId] = useState<number | null>(null);
  const [savingGameType, setSavingGameType] = useState(false);
  const [deletingGameTypeId, setDeletingGameTypeId] = useState<number | null>(null);

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
  const [searchText, setSearchText] = useState("");
  const [searchLoading, setSearchLoading] = useState(false);
  const [searchError, setSearchError] = useState<string | null>(null);
  const [searchResults, setSearchResults] = useState<SearchResults | null>(null);
  const [searchFilter, setSearchFilter] = useState<"all" | "players" | "game_types" | "games" | "comments">("all");
  const [highlightedPlayerId, setHighlightedPlayerId] = useState<number | null>(null);
  const [highlightedGameTypeId, setHighlightedGameTypeId] = useState<number | null>(null);
  const [highlightedGameId, setHighlightedGameId] = useState<number | null>(null);
  const [leaderboardEntries, setLeaderboardEntries] = useState<LeaderboardEntry[]>([]);
  const [leaderboardLoading, setLeaderboardLoading] = useState(false);
  const [leaderboardError, setLeaderboardError] = useState<string | null>(null);
  const [leaderboardPeriod, setLeaderboardPeriod] = useState<"all" | "7d" | "30d" | "3m" | "6m" | "1y">("all");
  const [leaderboardCriteriaLabel, setLeaderboardCriteriaLabel] = useState<string | null>(null);
  const [competitions, setCompetitions] = useState<Competition[]>([]);
  const [competitionsLoading, setCompetitionsLoading] = useState(false);
  const [competitionsError, setCompetitionsError] = useState<string | null>(null);
  const [competitionsLoaded, setCompetitionsLoaded] = useState(false);

  const [memberCard, setMemberCard] = useState<MemberCard | null | undefined>(undefined);
  const [cardLoading, setCardLoading] = useState(false);
  const [cardError, setCardError] = useState<string | null>(null);
  const [cardSaving, setCardSaving] = useState(false);
  const [confirmDialog, setConfirmDialog] = useState<{
    visible: boolean;
    title: string;
    message: string;
    confirmText: string;
    destructive: boolean;
    onConfirm: (() => void) | null;
  }>({
    visible: false,
    title: "",
    message: "",
    confirmText: "Confirmer",
    destructive: false,
    onConfirm: null,
  });

  const openConfirmDialog = (
    title: string,
    message: string,
    onConfirm: () => void,
    options?: { confirmText?: string; destructive?: boolean }
  ) => {
    setConfirmDialog({
      visible: true,
      title,
      message,
      confirmText: options?.confirmText ?? "Confirmer",
      destructive: options?.destructive ?? false,
      onConfirm,
    });
  };

  const closeConfirmDialog = () => {
    setConfirmDialog((current) => ({
      ...current,
      visible: false,
      onConfirm: null,
    }));
  };

  const loadGames = useCallback(async () => {
    try {
      setGamesLoading(true);
      const gamesData = await fetchSpaceGames(token, space.id);
      setGames(gamesData);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de charger les parties.");
      }
    } finally {
      setGamesLoading(false);
    }
  }, [space.id, token]);

  const loadData = useCallback(async () => {
    try {
      setError(null);
      const [playersData, gameTypesData] = await Promise.all([
        fetchPlayers(token, space.id),
        fetchGameTypes(token, space.id),
      ]);

      setPlayers(playersData);
      setGameTypes(gameTypesData);

      try {
        const membersData = await fetchSpaceMembers(token, space.id);
        setMembers(membersData);
      } catch {
        // Ne bloque pas la création de partie si l'API membres est indisponible.
        setMembers([]);
      }

      await loadGames();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de charger cet espace.");
      }
    }
  }, [loadGames, space.id, token]);

  useEffect(() => {
    const run = async () => {
      setLoading(true);
      await loadData();
      setLoading(false);
    };

    void run();
  }, [loadData]);

  const filteredGames = useMemo(() => {
    if (gamesStatusFilter === "all") {
      return games;
    }
    return games.filter((game) => game.status === gamesStatusFilter);
  }, [games, gamesStatusFilter]);

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

  const restrictedLinkedPlayersCount = useMemo(
    () => players.filter((player) => !!player.user_id && restrictedParticipationUserIds.has(player.user_id)).length,
    [players, restrictedParticipationUserIds]
  );

  const inviteLinkUrl = useMemo(
    () => (inviteToken ? `https://scores.leofranz.fr/spaces/join/${inviteToken}` : null),
    [inviteToken]
  );

  const avatarUri = useMemo(() => getAvatarUri(user.avatar), [user.avatar]);

  useEffect(() => {
    const allowedIds = new Set(availablePlayersForGame.map((player) => player.id));
    setSelectedPlayerIds((current) => current.filter((id) => allowedIds.has(id)));
  }, [availablePlayersForGame]);

  const loadCompetitions = useCallback(async () => {
    try {
      setCompetitionsError(null);
      setCompetitionsLoading(true);
      const data = await fetchCompetitions(token, space.id);
      setCompetitions(data);
      setCompetitionsLoaded(true);
    } catch (err) {
      if (err instanceof ApiError) {
        setCompetitionsError(err.message);
      } else {
        setCompetitionsError("Impossible de charger les competitions.");
      }
    } finally {
      setCompetitionsLoading(false);
    }
  }, [space.id, token]);

  const loadLeaderboard = useCallback(async () => {
    try {
      setLeaderboardError(null);
      setLeaderboardLoading(true);
      const data = await fetchLeaderboard(token, leaderboardPeriod);
      setLeaderboardEntries(data.entries);
      setLeaderboardCriteriaLabel(
        `Éligibilité : min ${data.criteria.min_rounds_played} manches sur ${data.criteria.min_spaces_played} espaces.`
      );
    } catch (err) {
      if (err instanceof ApiError) {
        setLeaderboardError(err.message);
      } else {
        setLeaderboardError("Impossible de charger le leaderboard.");
      }
    } finally {
      setLeaderboardLoading(false);
    }
  }, [leaderboardPeriod, token]);

  const submitSearch = useCallback(async () => {
    const query = searchText.trim();

    try {
      setSearchLoading(true);
      setSearchError(null);
      const data = await fetchSpaceSearch(token, space.id, query);
      setSearchResults(data);
    } catch (err) {
      if (err instanceof ApiError) {
        setSearchError(err.message);
      } else {
        setSearchError("Impossible de lancer la recherche.");
      }
      setSearchResults(null);
    } finally {
      setSearchLoading(false);
    }
  }, [searchText, space.id, token]);

  const openSearchResult = useCallback(
    (type: "players" | "game_types" | "games" | "comments", targetId: number, gameId?: number) => {
      if (type === "players") {
        setHighlightedPlayerId(targetId);
        setHighlightedGameTypeId(null);
        setHighlightedGameId(null);
        setCurrentView("players");
        return;
      }

      if (type === "game_types") {
        setHighlightedGameTypeId(targetId);
        setHighlightedPlayerId(null);
        setHighlightedGameId(null);
        setCurrentView("gameTypes");
        return;
      }

      if (type === "games") {
        setHighlightedGameId(targetId);
        setHighlightedPlayerId(null);
        setHighlightedGameTypeId(null);
        setGamesStatusFilter("all");
        setCurrentView("games");
        return;
      }

      if (type === "comments" && typeof gameId === "number") {
        onOpenGame(gameId);
      }
    },
    [onOpenGame]
  );

  useEffect(() => {
    if (currentView === "competitions" && !competitionsLoaded && !competitionsLoading) {
      void loadCompetitions();
    }
  }, [competitionsLoaded, competitionsLoading, currentView, loadCompetitions]);

  useEffect(() => {
    if (currentView === "leaderboard") {
      void loadLeaderboard();
    }
  }, [currentView, loadLeaderboard]);

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
      setError("Veuillez sélectionner un type de jeu.");
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
        setError("Impossible de créer la partie.");
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
          roundsWon: 0,
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
            const isWinner = Number(gamePlayer.is_winner ?? 0) === 1;
            if (!existing) {
              baseByPlayerId.set(gamePlayer.player_id, {
                playerId: gamePlayer.player_id,
                playerName: gamePlayer.player_name,
                roundsPlayed: 0,
                roundsWon: 0,
                gamesPlayed: 1,
                wins: isWinner ? 1 : 0,
                winRate: 0,
              });
              return;
            }

            existing.gamesPlayed += 1;
            if (isWinner) {
              existing.wins += 1;
            }
          });

          const winCondition = details.game.win_condition ?? "highest_score";

          details.rounds.forEach((round) => {
            const scoresForRound = details.round_scores?.[String(round.id)] || {};

            // Collecter les scores valides du round
            const roundEntries: Array<{ playerId: number; value: number }> = [];
            Object.keys(scoresForRound).forEach((playerIdRaw) => {
              const playerId = Number(playerIdRaw);
              const raw = scoresForRound[String(playerId)] as unknown;
              let value: number | null = null;
              if (typeof raw === "number") {
                value = raw;
              } else if (raw && typeof raw === "object" && "score" in (raw as object)) {
                const s = (raw as { score?: unknown }).score;
                if (typeof s === "number") {
                  value = Number.isFinite(s) ? s : null;
                } else if (typeof s === "string") {
                  const parsed = Number(s);
                  value = Number.isFinite(parsed) ? parsed : null;
                }
              }
              if (value !== null) {
                roundEntries.push({ playerId, value });
              }
            });

            // Déterminer les gagnants du round
            const roundWinnerIds = new Set<number>();
            if (roundEntries.length > 0) {
              if (winCondition === "win_loss") {
                roundEntries.forEach(({ playerId, value }) => { if (value === 1) roundWinnerIds.add(playerId); });
              } else if (winCondition === "ranking") {
                roundEntries.forEach(({ playerId, value }) => { if (value === 1) roundWinnerIds.add(playerId); });
              } else if (winCondition === "highest_score") {
                const maxScore = Math.max(...roundEntries.map((e) => e.value));
                roundEntries.forEach(({ playerId, value }) => { if (value === maxScore) roundWinnerIds.add(playerId); });
              } else if (winCondition === "lowest_score") {
                const minScore = Math.min(...roundEntries.map((e) => e.value));
                roundEntries.forEach(({ playerId, value }) => { if (value === minScore) roundWinnerIds.add(playerId); });
              }
            }

            roundEntries.forEach(({ playerId }) => {
              const existing = baseByPlayerId.get(playerId);
              if (!existing) return;
              existing.roundsPlayed += 1;
              if (roundWinnerIds.has(playerId)) {
                existing.roundsWon += 1;
              }
            });
          });
        });
      }

      const computed = Array.from(baseByPlayerId.values()).map((stats) => ({
        ...stats,
        winRate: stats.roundsPlayed > 0 ? (stats.roundsWon / stats.roundsPlayed) * 100 : 0,
      }));

      computed.sort((a, b) => {
        if (b.winRate !== a.winRate) {
          return b.winRate - a.winRate;
        }
        if (b.roundsWon !== a.roundsWon) {
          return b.roundsWon - a.roundsWon;
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

  const availableEditMemberOptions = useMemo(
    () => memberOptions.filter((option) => option.id === editPlayerUserId || !linkedUserIds.has(option.id)),
    [editPlayerUserId, linkedUserIds, memberOptions]
  );

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
        setError("Ce compte est déjà rattaché à un autre joueur de cet espace.");
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
        setError("Impossible de mettre à jour le joueur.");
      }
    } finally {
      setSavingPlayer(false);
    }
  };

  const confirmAndDeletePlayer = (playerId: number, playerName: string) => {
    openConfirmDialog(
      "Supprimer le joueur",
      "Confirmer la suppression de " + playerName + " ?",
      () => removePlayerSubmit(playerId),
      { confirmText: "Supprimer", destructive: true }
    );
  };

  const confirmAndDeleteGameType = (gameTypeId: number, gameTypeName: string) => {
    openConfirmDialog(
      "Supprimer le type de jeu",
      "Confirmer la suppression de " + gameTypeName + " ?",
      () => removeGameTypeSubmit(gameTypeId),
      { confirmText: "Supprimer", destructive: true }
    );
  };

  const confirmAndRemoveMember = (memberId: number, memberUsername: string) => {
    openConfirmDialog(
      "Retirer le membre",
      "Confirmer le retrait de " + memberUsername + " ?",
      () => removeMemberSubmit(memberId),
      { confirmText: "Retirer", destructive: true }
    );
  };

  const confirmAndUpdatePlayer = () => {
    openConfirmDialog(
      "Mettre à jour le joueur",
      "Confirmer les modifications ?",
      () => saveEditPlayer(),
      { confirmText: "Confirmer" }
    );
  };

  const confirmAndUpdateGameType = () => {
    openConfirmDialog(
      "Mettre à jour le type de jeu",
      "Confirmer les modifications ?",
      () => saveEditGameType(),
      { confirmText: "Confirmer" }
    );
  };

  const confirmAndUpdateMemberRole = (memberId: number) => {
    openConfirmDialog(
      "Changer le rôle",
      "Confirmer le changement de rôle ?",
      () => saveEditMemberRole(memberId),
      { confirmText: "Confirmer" }
    );
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
      setError("Le nombre maximum de joueurs doit être supérieur ou égal au minimum.");
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
        setError("Impossible de mettre à jour le type de jeu.");
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
      setError(err instanceof ApiError ? err.message : "Impossible de modifier le rôle.");
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
      setError(err instanceof ApiError ? err.message : "Impossible de générer le lien.");
    } finally {
      setGeneratingLink(false);
    }
  };

  const shareInviteLink = async () => {
    if (!inviteLinkUrl) {
      return;
    }
    try {
      await Share.share({
        message: `Rejoins mon espace sur Scores: ${inviteLinkUrl}`,
      });
    } catch {
      setError("Impossible de partager le lien d'invitation.");
    }
  };

  const isAdmin = space.user_role === "admin";
  const canManageMembers = isAdmin || space.user_role === "manager";

  // Joueur lié à l'utilisateur connecté dans cet espace
  const linkedPlayer = players.find((p) => p.user_id === user.id) ?? null;

  const loadCard = useCallback(async () => {
    if (!linkedPlayer) return;
    try {
      setCardError(null);
      setCardLoading(true);
      const data = await fetchMemberCard(token, space.id, linkedPlayer.id);
      setMemberCard(data);
    } catch (err) {
      setCardError(err instanceof ApiError ? err.message : "Impossible de charger la carte.");
    } finally {
      setCardLoading(false);
    }
  }, [linkedPlayer, space.id, token]);

  useEffect(() => {
    if (currentView === "card" && memberCard === undefined && !cardLoading) {
      void loadCard();
    }
  }, [cardLoading, currentView, loadCard, memberCard]);

  const handleGenerateCard = useCallback(async () => {
    if (!linkedPlayer) return;
    try {
      setCardError(null);
      setCardSaving(true);
      const data = await generateMemberCard(token, space.id, linkedPlayer.id);
      setMemberCard(data);
    } catch (err) {
      setCardError(err instanceof ApiError ? err.message : "Impossible de générer la carte.");
    } finally {
      setCardSaving(false);
    }
  }, [linkedPlayer, space.id, token]);

  const handleToggleCard = useCallback(async (active: boolean) => {
    if (!linkedPlayer || !memberCard) return;
    try {
      setCardError(null);
      setCardSaving(true);
      const data = await toggleMemberCard(token, space.id, linkedPlayer.id, active);
      setMemberCard(data);
    } catch (err) {
      setCardError(err instanceof ApiError ? err.message : "Impossible de modifier la carte.");
    } finally {
      setCardSaving(false);
    }
  }, [linkedPlayer, memberCard, space.id, token]);

  const handleRegenerateCard = useCallback(async () => {
    if (!linkedPlayer) return;
    Alert.alert(
      "Régénérer la carte",
      "La référence et la signature seront recréées. L'ancienne carte sera invalide. Continuer ?",
      [
        { text: "Annuler", style: "cancel" },
        {
          text: "Régénérer", style: "destructive",
          onPress: async () => {
            try {
              setCardError(null);
              setCardSaving(true);
              const data = await regenerateMemberCard(token, space.id, linkedPlayer.id);
              setMemberCard(data);
            } catch (err) {
              setCardError(err instanceof ApiError ? err.message : "Impossible de régénérer la carte.");
            } finally {
              setCardSaving(false);
            }
          },
        },
      ]
    );
  }, [linkedPlayer, space.id, token]);

  const handleDeleteCard = useCallback(async () => {
    if (!linkedPlayer) return;
    Alert.alert(
      "Supprimer la carte",
      "La carte de membre sera définitivement supprimée. Continuer ?",
      [
        { text: "Annuler", style: "cancel" },
        {
          text: "Supprimer", style: "destructive",
          onPress: async () => {
            try {
              setCardError(null);
              setCardSaving(true);
              await deleteMemberCard(token, space.id, linkedPlayer.id);
              setMemberCard(null);
            } catch (err) {
              setCardError(err instanceof ApiError ? err.message : "Impossible de supprimer la carte.");
            } finally {
              setCardSaving(false);
            }
          },
        },
      ]
    );
  }, [linkedPlayer, space.id, token]);

  const viewGroupItems: Array<{ key: Exclude<SpaceView, "menu">; label: string; icon: string }> = [
    { key: "leaderboard", label: "Classement", icon: "🏆" },
    { key: "stats", label: "Stats", icon: "📊" },
    { key: "competitions", label: "Compét.", icon: "🏁" },
  ];

  const manageGroupItems: Array<{ key: Exclude<SpaceView, "menu">; label: string; icon: string }> = [
    { key: "players", label: "Joueurs", icon: "👥" },
    { key: "gameTypes", label: "Types", icon: "🧩" },
  ];

  if (linkedPlayer) {
    viewGroupItems.push({ key: "card", label: "Ma carte", icon: "🪪" });
  }

  if (canManageMembers) {
    manageGroupItems.push({ key: "members", label: "Membres", icon: "🤝" });
  }

  const viewGroupKeys = viewGroupItems.map((i) => i.key);
  const manageGroupKeys = manageGroupItems.map((i) => i.key);

  const compactNav = width < 380;
  const subNavHeight = activeGroup ? (compactNav ? 54 : 62) : 0;
  const bottomNavExtraHeight = (compactNav ? 62 : 72) + subNavHeight;
  const currentViewMeta = currentView !== "menu" ? getViewMeta(currentView) : null;

  const menuStats = [
    { label: "Parties", value: games.length },
    { label: "Joueurs", value: players.length },
    { label: "Types", value: gameTypes.length },
    { label: "Membres", value: members.length },
    { label: "Compet.", value: competitions.length },
  ];

  if (loading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <View
      style={[
        styles.container,
        {
          paddingTop: Math.max(insets.top, 4),
          paddingBottom: Math.max(insets.bottom, 8) + bottomNavExtraHeight,
        },
      ]}
    >
      <View style={styles.header}>
        <View style={styles.headerSide}>
          {currentView === "menu" ? (
            <Pressable style={styles.navButton} onPress={onBack}>
              <Text style={styles.navButtonText}>← Retour</Text>
            </Pressable>
          ) : (
            <Pressable style={styles.navButton} onPress={() => setCurrentView("menu")}>
              <Text style={styles.navButtonText}>↩ Espace</Text>
            </Pressable>
          )}
        </View>
        <Text style={styles.title} numberOfLines={2}>
          {space.name}
        </Text>
        <View style={[styles.headerSide, styles.headerSideRight]}>
          <Pressable style={styles.profileButton} onPress={onOpenProfile}>
            {avatarUri ? (
              <Image source={{ uri: avatarUri }} style={styles.profileAvatar} />
            ) : (
              <Text style={styles.profileAvatarText}>{getInitials(user)}</Text>
            )}
          </Pressable>
        </View>
      </View>

      {currentView !== "menu" ? (
        <View style={styles.secondaryHeaderRow}>
          <View>
            <Text style={styles.contextTitle}>{currentViewMeta?.title}</Text>
            <Text style={styles.contextHint}>{currentViewMeta?.hint}</Text>
          </View>
          <Pressable style={styles.refreshPill} onPress={loadData}>
            <Text style={styles.refreshPillText}>Rafraîchir</Text>
          </Pressable>
        </View>
      ) : null}

      {error ? <Text style={styles.error}>{error}</Text> : null}

      {currentView === "menu" ? (
        <View style={styles.menuContainer}>
          <Text style={styles.sectionTitle}>Navigation</Text>
          <Text style={styles.menuHint}>Utilisez la barre en bas pour changer de section.</Text>

          <View style={styles.quickStatsGrid}>
            {menuStats.map((entry) => (
              <View key={entry.label} style={styles.quickStatCard}>
                <Text style={styles.quickStatValue}>{entry.value}</Text>
                <Text style={styles.quickStatLabel}>{entry.label}</Text>
              </View>
            ))}
          </View>
        </View>
      ) : null}

      {currentView === "create" ? (
        <ScrollView contentContainerStyle={styles.formCard} keyboardShouldPersistTaps="handled">
          <Text style={styles.sectionTitle}>Créer une partie</Text>

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

          {restrictedLinkedPlayersCount > 0 ? (
            <Text style={styles.infoText}>
              {restrictedLinkedPlayersCount} joueur(s) lié(s) à un compte restreint sont exclus de la sélection.
            </Text>
          ) : null}

          {selectablePlayers.length === 0 ? (
            <Text style={styles.infoText}>
              Aucun joueur supplémentaire disponible pour cette partie.
            </Text>
          ) : null}

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
            <Text style={styles.primaryText}>{saving ? "Création..." : "Créer la partie"}</Text>
          </Pressable>
        </ScrollView>
      ) : null}

      {currentView === "games" ? (
        <View style={styles.gamesContainer}>
          <Text style={styles.sectionTitle}>Parties</Text>
          <View style={styles.filterChipsRow}>
            {([
              ["all", "Toutes"],
              ["in_progress", "En cours"],
              ["paused", "En pause"],
              ["completed", "Terminées"],
            ] as const).map(([status, label]) => (
              <Pressable
                key={status}
                style={[styles.filterChip, gamesStatusFilter === status ? styles.filterChipActive : undefined]}
                onPress={() => setGamesStatusFilter(status)}
              >
                <Text
                  style={[styles.filterChipText, gamesStatusFilter === status ? styles.filterChipTextActive : undefined]}
                >
                  {label}
                </Text>
              </Pressable>
            ))}
          </View>
          {gamesLoading ? (
            <View style={styles.gamesLoadingInline}>
              <ActivityIndicator />
            </View>
          ) : null}
          <FlatList
            data={filteredGames}
            keyExtractor={(item) => String(item.id)}
            ListEmptyComponent={<Text style={styles.empty}>Aucune partie pour le moment.</Text>}
            renderItem={({ item }) => {
              const statusMeta = getStatusMeta(item.status, theme);
              return (
                <Pressable
                  style={[styles.gameCard, item.id === highlightedGameId ? styles.targetCard : undefined]}
                  onPress={() => onOpenGame(item.id)}
                >
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
                    <Text style={styles.statsMeta}>Manches jouées : {mostActivePlayer.roundsPlayed}</Text>
                  </>
                ) : (
                  <Text style={styles.statsMeta}>Aucune donnée disponible.</Text>
                )}
              </View>

              <View style={styles.statsCard}>
                <Text style={styles.statsCardTitle}>Classement par taux de victoire</Text>

                {playerStats.length === 0 ? (
                  <Text style={styles.statsMeta}>Aucune donnée disponible.</Text>
                ) : (
                  playerStats.map((stats, index) => (
                    <View style={styles.statsRow} key={stats.playerId}>
                      <Text style={styles.statsRank}>{index + 1}.</Text>
                      <View style={styles.statsRowMain}>
                        <Text style={styles.statsPlayerName}>{stats.playerName}</Text>
                        <Text style={styles.statsMeta}>
                          {stats.winRate.toFixed(1)}% ({stats.roundsWon} manche{stats.roundsWon > 1 ? "s" : ""} gagnée{stats.roundsWon > 1 ? "s" : ""} / {stats.roundsPlayed} jouée{stats.roundsPlayed > 1 ? "s" : ""})
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

      {currentView === "search" ? (
        <ScrollView contentContainerStyle={styles.formCard} keyboardShouldPersistTaps="handled">
          <Text style={styles.sectionTitle}>Recherche</Text>

          <View style={styles.searchBarRow}>
            <TextInput
              value={searchText}
              onChangeText={setSearchText}
              placeholder="Rechercher joueurs, jeux, parties..."
              style={[styles.input, styles.searchInputField]}
            />
            <Pressable
              style={[styles.primaryButton, styles.searchButton, searchLoading ? styles.disabledButton : undefined]}
              onPress={() => void submitSearch()}
              disabled={searchLoading}
            >
              <Text style={styles.primaryText}>{searchLoading ? "..." : "Go"}</Text>
            </Pressable>
          </View>

          <View style={styles.filterChipsRow}>
            {([
              ["all", "Tous"],
              ["players", "Joueurs"],
              ["game_types", "Types"],
              ["games", "Parties"],
              ["comments", "Commentaires"],
            ] as const).map(([key, label]) => (
              <Pressable
                key={key}
                style={[styles.filterChip, searchFilter === key ? styles.filterChipActive : undefined]}
                onPress={() => setSearchFilter(key)}
              >
                <Text style={[styles.filterChipText, searchFilter === key ? styles.filterChipTextActive : undefined]}>
                  {label}
                </Text>
              </Pressable>
            ))}
          </View>

          {searchResults ? (
            <View style={styles.searchSummaryRow}>
              <Text style={styles.searchSummaryText}>
                {searchResults.players.length + searchResults.game_types.length + searchResults.games.length + searchResults.comments.length} résultat
                {searchResults.players.length + searchResults.game_types.length + searchResults.games.length + searchResults.comments.length > 1 ? "s" : ""}
              </Text>
              <Text style={styles.searchSummaryMuted}>
                {searchFilter === "all" ? "Toutes catégories" : "Catégorie filtrée"}
              </Text>
            </View>
          ) : null}

          {searchError ? <Text style={styles.error}>{searchError}</Text> : null}

          {searchResults ? (
            <View style={styles.searchResultsWrap}>
              {(searchFilter === "all" || searchFilter === "players") && (
                <View style={styles.searchSection}>
                  <View style={styles.searchSectionHeaderRow}>
                    <Text style={styles.searchSectionTitle}>Joueurs</Text>
                    <Text style={styles.searchSectionCount}>{searchResults.players.length}</Text>
                  </View>
                  {searchResults.players.length === 0 ? (
                    <Text style={styles.searchEmptyText}>Aucun joueur trouvé.</Text>
                  ) : (
                    searchResults.players.map((player) => (
                      <Pressable key={`player-${player.id}`} style={styles.searchRow} onPress={() => openSearchResult("players", player.id)}>
                        <Text style={styles.searchRowIcon}>👤</Text>
                        <View style={styles.searchRowContent}>
                          <Text style={styles.searchRowTitle}>{player.name}</Text>
                          <Text style={styles.searchRowMeta}>Joueur</Text>
                        </View>
                        <Text style={styles.searchRowAction}>Ouvrir</Text>
                      </Pressable>
                    ))
                  )}
                </View>
              )}

              {(searchFilter === "all" || searchFilter === "game_types") && (
                <View style={styles.searchSection}>
                  <View style={styles.searchSectionHeaderRow}>
                    <Text style={styles.searchSectionTitle}>Types de jeu</Text>
                    <Text style={styles.searchSectionCount}>{searchResults.game_types.length}</Text>
                  </View>
                  {searchResults.game_types.length === 0 ? (
                    <Text style={styles.searchEmptyText}>Aucun type de jeu trouvé.</Text>
                  ) : (
                    searchResults.game_types.map((gameType) => (
                      <Pressable key={`gt-${gameType.id}`} style={styles.searchRow} onPress={() => openSearchResult("game_types", gameType.id)}>
                        <Text style={styles.searchRowIcon}>🧩</Text>
                        <View style={styles.searchRowContent}>
                          <Text style={styles.searchRowTitle}>{gameType.name}</Text>
                          <Text style={styles.searchRowMeta}>Type de jeu</Text>
                        </View>
                        <Text style={styles.searchRowAction}>Ouvrir</Text>
                      </Pressable>
                    ))
                  )}
                </View>
              )}

              {(searchFilter === "all" || searchFilter === "games") && (
                <View style={styles.searchSection}>
                  <View style={styles.searchSectionHeaderRow}>
                    <Text style={styles.searchSectionTitle}>Parties</Text>
                    <Text style={styles.searchSectionCount}>{searchResults.games.length}</Text>
                  </View>
                  {searchResults.games.length === 0 ? (
                    <Text style={styles.searchEmptyText}>Aucune partie trouvée.</Text>
                  ) : (
                    searchResults.games.map((game) => (
                      <Pressable key={`game-${game.id}`} style={styles.searchRow} onPress={() => openSearchResult("games", game.id)}>
                        <Text style={styles.searchRowIcon}>🎮</Text>
                        <View style={styles.searchRowContent}>
                          <Text style={styles.searchRowTitle}>{game.game_type_name || `Partie #${game.id}`}</Text>
                          <Text style={styles.searchRowMeta}>Partie · Créée le {formatShortDate(game.created_at)}</Text>
                        </View>
                        <Text style={styles.searchRowAction}>Voir</Text>
                      </Pressable>
                    ))
                  )}
                </View>
              )}

              {(searchFilter === "all" || searchFilter === "comments") && (
                <View style={styles.searchSection}>
                  <View style={styles.searchSectionHeaderRow}>
                    <Text style={styles.searchSectionTitle}>Commentaires</Text>
                    <Text style={styles.searchSectionCount}>{searchResults.comments.length}</Text>
                  </View>
                  {searchResults.comments.length === 0 ? (
                    <Text style={styles.searchEmptyText}>Aucun commentaire trouvé.</Text>
                  ) : (
                    searchResults.comments.map((comment) => {
                      const commentContainerStyle = styles.searchRow;
                      const canOpenGame = typeof comment.game_id === "number";

                      if (canOpenGame) {
                        return (
                          <Pressable key={`comment-${comment.id}`} style={commentContainerStyle} onPress={() => openSearchResult("comments", comment.id, comment.game_id as number)}>
                            <Text style={styles.searchRowIcon}>💬</Text>
                            <View style={styles.searchRowContent}>
                              <Text style={styles.searchRowTitle} numberOfLines={2}>
                                {comment.content}
                              </Text>
                              <Text style={styles.searchRowMeta}>{comment.username || "Auteur inconnu"}</Text>
                            </View>
                            <Text style={styles.searchRowAction}>Voir</Text>
                          </Pressable>
                        );
                      }

                      return (
                        <View key={`comment-${comment.id}`} style={commentContainerStyle}>
                          <Text style={styles.searchRowIcon}>💬</Text>
                          <View style={styles.searchRowContent}>
                            <Text style={styles.searchRowTitle} numberOfLines={2}>
                              {comment.content}
                            </Text>
                            <Text style={styles.searchRowMeta}>{comment.username || "Auteur inconnu"}</Text>
                          </View>
                        </View>
                      );
                    })
                  )}
                </View>
              )}
            </View>
          ) : (
            <Text style={styles.infoText}>Saisissez un terme puis lancez la recherche.</Text>
          )}
        </ScrollView>
      ) : null}

      {currentView === "leaderboard" ? (
        <ScrollView contentContainerStyle={styles.formCard}>
          <Text style={styles.sectionTitle}>Leaderboard global</Text>

          <View style={styles.filterChipsRow}>
            {([
              ["all", "Tout"],
              ["7d", "7j"],
              ["30d", "30j"],
              ["3m", "3m"],
              ["6m", "6m"],
              ["1y", "1a"],
            ] as const).map(([period, label]) => (
              <Pressable
                key={period}
                style={[styles.filterChip, leaderboardPeriod === period ? styles.filterChipActive : undefined]}
                onPress={() => setLeaderboardPeriod(period)}
              >
                <Text
                  style={[
                    styles.filterChipText,
                    leaderboardPeriod === period ? styles.filterChipTextActive : undefined,
                  ]}
                >
                  {label}
                </Text>
              </Pressable>
            ))}
          </View>

          {leaderboardCriteriaLabel ? <Text style={styles.infoText}>{leaderboardCriteriaLabel}</Text> : null}
          {leaderboardError ? <Text style={styles.error}>{leaderboardError}</Text> : null}
          {leaderboardLoading ? (
            <ActivityIndicator />
          ) : leaderboardEntries.length === 0 ? (
            <Text style={styles.empty}>Aucune donnée de classement disponible.</Text>
          ) : (
            leaderboardEntries.map((entry) => (
              <View key={entry.user_id} style={styles.rankingRow}>
                <Text style={styles.rankingRank}>#{entry.rank}</Text>
                <View style={styles.rankingMain}>
                  <Text style={styles.rankingName}>{entry.username}</Text>
                  <Text style={styles.rankingMeta}>
                    {entry.win_rate.toFixed(1)}% · {entry.rounds_won}/{entry.rounds_played} manches
                  </Text>
                </View>
              </View>
            ))
          )}
        </ScrollView>
      ) : null}

      {currentView === "competitions" ? (
        <ScrollView contentContainerStyle={styles.formCard}>
          <Text style={styles.sectionTitle}>Compétitions</Text>

          {competitionsError ? <Text style={styles.error}>{competitionsError}</Text> : null}
          {competitionsLoading ? (
            <ActivityIndicator />
          ) : competitions.length === 0 ? (
            <Text style={styles.empty}>Aucune compétition dans cet espace.</Text>
          ) : (
            competitions.map((competition) => {
              const statusTone = getCompetitionStatusMeta(competition.status, theme);
              return (
                <Pressable
                  key={competition.id}
                  style={styles.competitionCard}
                  onPress={() => onOpenCompetition(competition.id)}
                >
                  <View style={styles.gameRow}>
                    <Text style={styles.gameTitle}>{competition.name}</Text>
                    <View style={[styles.statusBadge, { backgroundColor: statusTone.backgroundColor }]}> 
                      <Text style={[styles.statusText, { color: statusTone.textColor }]}>{competition.status}</Text>
                    </View>
                  </View>
                  {competition.description ? <Text style={styles.gameMeta}>{competition.description}</Text> : null}
                  <Text style={styles.gameMeta}>Sessions: {competition.session_count}</Text>
                  <Text style={styles.gameMeta}>
                    Période : {competition.starts_at ? competition.starts_at.slice(0, 10) : "-"} - {competition.ends_at ? competition.ends_at.slice(0, 10) : "-"}
                  </Text>
                </Pressable>
              );
            })
          )}
        </ScrollView>
      ) : null}

      {currentView === "card" ? (
        <ScrollView contentContainerStyle={styles.formCard}>
          <Text style={styles.sectionTitle}>Ma carte de membre</Text>
          {cardError ? <Text style={styles.error}>{cardError}</Text> : null}
          {cardLoading ? (
            <ActivityIndicator style={{ marginTop: 24 }} />
          ) : memberCard === null ? (
            <View style={styles.cardEmptyContainer}>
              <Text style={styles.empty}>
                Vous n'avez pas encore de carte de membre pour cet espace.
              </Text>
              <Pressable
                style={[styles.cardActionButton, cardSaving && styles.buttonDisabled]}
                disabled={cardSaving}
                onPress={() => { void handleGenerateCard(); }}
              >
                <Text style={styles.cardActionButtonText}>
                  {cardSaving ? "Génération…" : "🪪 Générer ma carte"}
                </Text>
              </Pressable>
            </View>
          ) : memberCard ? (
            <View>
              {/* Widget carte visuel */}
              <View style={[styles.cardWidget, memberCard.is_active ? styles.cardWidgetActive : styles.cardWidgetInactive]}>
                <View style={styles.cardWidgetHeader}>
                  <Text style={styles.cardWidgetSpaceName}>{memberCard.space_name ?? space.name}</Text>
                  <View style={[styles.cardStatusBadge, memberCard.is_active ? styles.badgeActive : styles.badgeInactive]}>
                    <Text style={styles.cardStatusText}>{memberCard.is_active ? "Active" : "Désactivée"}</Text>
                  </View>
                </View>
                <Text style={styles.cardPlayerName}>{memberCard.player_name ?? linkedPlayer?.name}</Text>
                <Text style={styles.cardRoleText}>{memberCard.space_role ?? "Membre"}</Text>
                <View style={styles.cardWidgetFooter}>
                  <View>
                    <Text style={styles.cardLabel}>RÉFÉRENCE</Text>
                    <Text style={styles.cardReference}>{memberCard.reference}</Text>
                  </View>
                  <View style={styles.cardSigContainer}>
                    <Text style={styles.cardLabel}>SIGNATURE</Text>
                    <Text style={styles.cardSignatureText}>
                      {memberCard.signature_valid === true ? "✅ valide" : memberCard.signature_valid === false ? "❌ invalide" : "—"}
                    </Text>
                  </View>
                </View>
              </View>

              {/* Informations */}
              <View style={styles.cardInfoSection}>
                <Text style={styles.cardInfoRow}>
                  <Text style={styles.cardInfoLabel}>Émise le : </Text>
                  {memberCard.created_at ? memberCard.created_at.slice(0, 10) : "—"}
                </Text>
                {memberCard.player_joined_at ? (
                  <Text style={styles.cardInfoRow}>
                    <Text style={styles.cardInfoLabel}>Membre depuis : </Text>
                    {memberCard.player_joined_at.slice(0, 10)}
                  </Text>
                ) : null}
              </View>

              {/* Actions */}
              <View style={styles.cardActionsRow}>
                <Pressable
                  style={[
                    styles.cardActionButton,
                    memberCard.is_active ? styles.cardActionButtonSecondary : styles.cardActionButton,
                    cardSaving && styles.buttonDisabled,
                  ]}
                  disabled={cardSaving}
                  onPress={() => { void handleToggleCard(memberCard.is_active ? false : true); }}
                >
                  <Text
                    style={[
                      styles.cardActionButtonText,
                      memberCard.is_active ? styles.cardActionButtonTextSecondary : undefined,
                    ]}
                  >
                    {cardSaving ? "…" : memberCard.is_active ? "⏸ Désactiver" : "▶ Activer"}
                  </Text>
                </Pressable>
                <Pressable
                  style={[styles.cardActionButton, styles.cardActionButtonSecondary, cardSaving && styles.buttonDisabled]}
                  disabled={cardSaving}
                  onPress={() => { void handleRegenerateCard(); }}
                >
                  <Text style={[styles.cardActionButtonText, styles.cardActionButtonTextSecondary]}>
                    {cardSaving ? "…" : "🔄 Régénérer"}
                  </Text>
                </Pressable>
              </View>
              <Pressable
                style={[styles.cardDeleteButton, cardSaving && styles.buttonDisabled]}
                disabled={cardSaving}
                onPress={() => { void handleDeleteCard(); }}
              >
                <Text style={styles.cardDeleteButtonText}>🗑 Supprimer la carte</Text>
              </Pressable>
            </View>
          ) : null}
        </ScrollView>
      ) : null}

      {currentView === "players" ? (
        <ScrollView contentContainerStyle={styles.formCard} keyboardShouldPersistTaps="handled">
          <View style={styles.playersHeader}>
            <Text style={styles.sectionTitle}>Gérer les joueurs</Text>
            <Pressable style={styles.addPlayerButton} onPress={onOpenCreatePlayer}>
              <Text style={styles.addPlayerButtonText}>+ Ajouter un joueur</Text>
            </Pressable>
          </View>

          <View style={styles.playerListSection}>
            {players.length === 0 ? <Text style={styles.empty}>Aucun joueur dans cet espace.</Text> : null}

            {players.map((player) => (
              <View key={player.id} style={[styles.playerCard, player.id === highlightedPlayerId ? styles.targetCard : undefined]}>
                <View style={styles.playerCardHeader}>
                  <View style={styles.playerCardHeaderMain}>
                    <Text style={styles.playerName}>{player.name}</Text>
                    <Text style={styles.playerLinkInfo}>
                      {player.user_id
                        ? `Compte lié : ${player.linked_username || `Utilisateur #${player.user_id}`}`
                        : "Aucun compte lié"}
                    </Text>
                  </View>

                  <View style={styles.playerActionsRow}>
                    <Pressable onPress={() => startEditPlayer(player)}>
                      <Text style={styles.linkAction}>Modifier</Text>
                    </Pressable>
                    <Pressable
                      disabled={deletingPlayerId === player.id}
                      onPress={() => confirmAndDeletePlayer(player.id, player.name)}
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
                      label="Rattacher à un compte (optionnel)"
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
                        onPress={confirmAndUpdatePlayer}
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
          <View style={styles.playersHeader}>
            <Text style={styles.sectionTitle}>Gérer les types de jeu</Text>
            <Pressable style={styles.addPlayerButton} onPress={onOpenCreateGameType}>
              <Text style={styles.addPlayerButtonText}>+ Ajouter un type</Text>
            </Pressable>
          </View>

          <View style={styles.playerListSection}>
            {gameTypes.length === 0 ? <Text style={styles.empty}>Aucun type de jeu dans cet espace.</Text> : null}

            {gameTypes.map((gameType) => (
              <View key={gameType.id} style={[styles.playerCard, gameType.id === highlightedGameTypeId ? styles.targetCard : undefined]}>
                <View style={styles.playerCardHeader}>
                  <View style={styles.playerCardHeaderMain}>
                    <Text style={styles.playerName}>{gameType.name}</Text>
                    <Text style={styles.playerLinkInfo}>{getWinConditionLabel(gameType.win_condition)}</Text>
                    <Text style={styles.playerLinkInfo}>
                      Joueurs: min {gameType.min_players ?? 1}
                      {gameType.max_players ? ` / max ${gameType.max_players}` : " / max illimité"}
                    </Text>
                    {gameType.description ? <Text style={styles.playerLinkInfo}>{gameType.description}</Text> : null}
                  </View>

                  <View style={styles.playerActionsRow}>
                    <Pressable onPress={() => startEditGameType(gameType)}>
                      <Text style={styles.linkAction}>Modifier</Text>
                    </Pressable>
                    <Pressable
                      disabled={deletingGameTypeId === gameType.id}
                      onPress={() => confirmAndDeleteGameType(gameType.id, gameType.name)}
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
                          placeholder="Illimité"
                          style={styles.input}
                        />
                      </View>
                    </View>

                    <View style={styles.inlineEditorActions}>
                      <Pressable
                        style={[styles.secondaryButton, savingGameType ? styles.disabledButton : undefined]}
                        disabled={savingGameType}
                        onPress={confirmAndUpdateGameType}
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
                          <Text style={styles.linkAction}>Rôle</Text>
                        </Pressable>
                      ) : null}
                      <Pressable
                        disabled={removingMemberId === member.id}
                        onPress={() => confirmAndRemoveMember(member.id, member.username)}
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
                    <Text style={styles.label}>Nouveau rôle</Text>
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
                        onPress={() => confirmAndUpdateMemberRole(member.id)}
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
            <Text style={styles.label}>Rôle attribué</Text>
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
              <Text style={styles.primaryText}>{generatingLink ? "Génération..." : "Générer un lien"}</Text>
            </Pressable>
            {inviteToken ? (
              <View style={styles.inviteLinkBox}>
                <Text style={styles.inviteLinkLabel}>Lien valable 72h :</Text>
                <Text style={styles.inviteLinkText} selectable>
                  {inviteLinkUrl}
                </Text>
                <Pressable style={styles.shareLinkButton} onPress={shareInviteLink}>
                  <Text style={styles.shareLinkButtonText}>Partager le lien</Text>
                </Pressable>
              </View>
            ) : null}
          </View>
        </ScrollView>
      ) : null}

      <View style={[styles.bottomNav, { paddingBottom: Math.max(insets.bottom, 8) }]}>
        {activeGroup ? (
          <View style={styles.subNav}>
            {(activeGroup === "view" ? viewGroupItems : manageGroupItems).map((item) => (
              <Pressable
                key={item.key}
                style={[styles.subNavItem, currentView === item.key ? styles.subNavItemActive : undefined]}
                onPress={() => {
                  setCurrentView(item.key);
                  setActiveGroup(null);
                }}
              >
                <Text style={styles.bottomNavIcon}>{item.icon}</Text>
                <Text
                  style={[styles.bottomNavLabel, currentView === item.key ? styles.bottomNavLabelActive : undefined]}
                  numberOfLines={1}
                >
                  {item.label}
                </Text>
              </Pressable>
            ))}
          </View>
        ) : null}

        {/* Barre principale */}
        <View style={styles.mainNavRow}>
          {/* Parties */}
          <Pressable
            style={[styles.bottomNavItem, styles.bottomNavItemFluid, currentView === "games" ? styles.bottomNavItemActive : undefined]}
            onPress={() => { setCurrentView("games"); setActiveGroup(null); }}
          >
            <Text style={[styles.bottomNavIcon, currentView === "games" ? styles.bottomNavIconActive : undefined]}>🎮</Text>
            <Text style={[styles.bottomNavLabel, currentView === "games" ? styles.bottomNavLabelActive : undefined]} numberOfLines={1}>Parties</Text>
          </Pressable>

          {/* Vue */}
          <Pressable
            style={[
              styles.bottomNavItem,
              styles.bottomNavItemFluid,
              (activeGroup === "view" || viewGroupKeys.includes(currentView as Exclude<SpaceView, "menu">)) ? styles.bottomNavItemActive : undefined,
            ]}
            onPress={() => setActiveGroup(activeGroup === "view" ? null : "view")}
          >
            <Text style={[styles.bottomNavIcon, (activeGroup === "view" || viewGroupKeys.includes(currentView as Exclude<SpaceView, "menu">)) ? styles.bottomNavIconActive : undefined]}>📋</Text>
            <Text style={[styles.bottomNavLabel, (activeGroup === "view" || viewGroupKeys.includes(currentView as Exclude<SpaceView, "menu">)) ? styles.bottomNavLabelActive : undefined]} numberOfLines={1}>
              Voir {activeGroup === "view" ? "▲" : "▼"}
            </Text>
          </Pressable>

          {/* Bouton Créer (FAB central) */}
          <View style={styles.fabWrapper}>
            <Pressable
              style={[styles.fab, currentView === "create" ? styles.fabActive : undefined]}
              onPress={() => { setCurrentView("create"); setActiveGroup(null); }}
            >
              <Text style={styles.fabIcon}>＋</Text>
            </Pressable>
          </View>

          {/* Gérer */}
          <Pressable
            style={[
              styles.bottomNavItem,
              styles.bottomNavItemFluid,
              (activeGroup === "manage" || manageGroupKeys.includes(currentView as Exclude<SpaceView, "menu">)) ? styles.bottomNavItemActive : undefined,
            ]}
            onPress={() => setActiveGroup(activeGroup === "manage" ? null : "manage")}
          >
            <Text style={[styles.bottomNavIcon, (activeGroup === "manage" || manageGroupKeys.includes(currentView as Exclude<SpaceView, "menu">)) ? styles.bottomNavIconActive : undefined]}>⚙️</Text>
            <Text style={[styles.bottomNavLabel, (activeGroup === "manage" || manageGroupKeys.includes(currentView as Exclude<SpaceView, "menu">)) ? styles.bottomNavLabelActive : undefined]} numberOfLines={1}>
              Gérer {activeGroup === "manage" ? "▲" : "▼"}
            </Text>
          </Pressable>

          {/* Recherche */}
          <Pressable
            style={[styles.bottomNavItem, styles.bottomNavItemFluid, currentView === "search" ? styles.bottomNavItemActive : undefined]}
            onPress={() => { setCurrentView("search"); setActiveGroup(null); }}
          >
            <Text style={[styles.bottomNavIcon, currentView === "search" ? styles.bottomNavIconActive : undefined]}>🔍</Text>
            <Text style={[styles.bottomNavLabel, currentView === "search" ? styles.bottomNavLabelActive : undefined]} numberOfLines={1}>Recherche</Text>
          </Pressable>
        </View>
      </View>

      <Modal
        visible={confirmDialog.visible}
        transparent
        animationType="fade"
        onRequestClose={closeConfirmDialog}
      >
        <View style={styles.confirmOverlay}>
          <View style={styles.confirmCard}>
            <Text style={styles.confirmTitle}>{confirmDialog.title}</Text>
            <Text style={styles.confirmMessage}>{confirmDialog.message}</Text>

            <View style={styles.confirmActionsRow}>
              <Pressable style={styles.confirmCancelButton} onPress={closeConfirmDialog}>
                <Text style={styles.confirmCancelText}>Annuler</Text>
              </Pressable>

              <Pressable
                style={[
                  styles.confirmSubmitButton,
                  confirmDialog.destructive ? styles.confirmSubmitButtonDanger : undefined,
                ]}
                onPress={() => {
                  const callback = confirmDialog.onConfirm;
                  closeConfirmDialog();
                  if (callback) {
                    callback();
                  }
                }}
              >
                <Text style={styles.confirmSubmitText}>{confirmDialog.confirmText}</Text>
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </View>
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
    padding: 14,
  },
  header: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 8,
    gap: 8,
  },
  headerSide: {
    flexShrink: 1,
    maxWidth: "42%",
  },
  headerSideRight: {
    width: 44,
    maxWidth: 44,
    alignItems: "flex-end",
  },
  navButton: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.md,
    paddingHorizontal: 8,
    paddingVertical: 6,
  },
  navButtonText: {
    color: theme.colors.primary,
    fontWeight: "700",
    fontSize: 11,
  },
  title: {
    flex: 1,
    minWidth: 0,
    textAlign: "center",
    fontSize: 20,
    fontWeight: "800",
    color: theme.colors.text,
    paddingHorizontal: 4,
  },
  secondaryHeaderRow: {
    alignItems: "center",
    flexDirection: "row",
    justifyContent: "space-between",
    marginBottom: 8,
    backgroundColor: theme.colors.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    paddingHorizontal: 10,
    paddingVertical: 8,
  },
  contextTitle: {
    color: theme.colors.text,
    fontWeight: "700",
    fontSize: 14,
  },
  contextHint: {
    color: theme.colors.mutedText,
    fontSize: 12,
    marginTop: 2,
    maxWidth: 210,
  },
  refreshPill: {
    backgroundColor: theme.colors.primarySoft,
    borderRadius: theme.radius.md,
    paddingHorizontal: 10,
    paddingVertical: 7,
  },
  refreshPillText: {
    color: theme.colors.primary,
    fontWeight: "700",
    fontSize: 12,
  },
  profileButton: {
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: theme.colors.primarySoft,
    alignItems: "center",
    justifyContent: "center",
    overflow: "hidden",
  },
  profileAvatar: {
    width: "100%",
    height: "100%",
  },
  profileAvatarText: {
    color: theme.colors.primary,
    fontWeight: "700",
    fontSize: 11,
  },
  error: {
    color: theme.colors.danger,
    marginBottom: 10,
  },
  menuContainer: {
    gap: 8,
  },
  menuHint: {
    color: theme.colors.mutedText,
    fontSize: 13,
  },
  quickStatsGrid: {
    marginTop: 4,
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 10,
  },
  quickStatCard: {
    width: "47%",
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.lg,
    backgroundColor: theme.colors.card,
    paddingVertical: 10,
    paddingHorizontal: 12,
    alignItems: "center",
    justifyContent: "center",
  },
  quickStatValue: {
    color: theme.colors.text,
    fontWeight: "800",
    fontSize: 22,
  },
  quickStatLabel: {
    marginTop: 2,
    color: theme.colors.mutedText,
    fontSize: 12,
    fontWeight: "600",
  },
  bottomNav: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
    backgroundColor: theme.colors.card,
  },
  subNav: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 12,
    paddingTop: 8,
    paddingBottom: 4,
    gap: 6,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
    backgroundColor: theme.colors.background,
  },
  subNavItem: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: theme.radius.md,
    paddingVertical: 6,
    paddingHorizontal: 4,
  },
  subNavItemActive: {
    backgroundColor: theme.colors.primarySoft,
  },
  mainNavRow: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 8,
    paddingTop: 8,
    paddingBottom: 6,
    gap: 4,
  },
  fabWrapper: {
    alignItems: "center",
    justifyContent: "center",
    flex: 1,
  },
  fab: {
    width: 52,
    height: 52,
    borderRadius: 26,
    backgroundColor: theme.colors.primary,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 8,
    ...theme.shadow.card,
  },
  fabActive: {
    backgroundColor: theme.colors.primaryStrong,
  },
  fabIcon: {
    fontSize: 26,
    color: "#fff",
    lineHeight: 30,
  },
  bottomNavContent: {
    paddingHorizontal: 10,
    paddingTop: 8,
    paddingBottom: 6,
    gap: 8,
  },
  bottomNavRow: {
    flexDirection: "row",
    alignItems: "stretch",
    paddingHorizontal: 10,
    paddingTop: 8,
    paddingBottom: 6,
    gap: 8,
  },
  bottomNavRowLarge: {
    justifyContent: "center",
  },
  bottomNavItem: {
    minWidth: 0,
    borderRadius: theme.radius.md,
    alignItems: "center",
    justifyContent: "center",
    paddingHorizontal: 4,
    paddingVertical: 6,
  },
  bottomNavItemCompact: {
    minWidth: 56,
    paddingHorizontal: 6,
    paddingVertical: 5,
  },
  bottomNavItemFluid: {
    flex: 1,
    minWidth: 0,
  },
  bottomNavItemActive: {
    backgroundColor: theme.colors.primarySoft,
  },
  bottomNavIcon: {
    color: theme.colors.mutedText,
    fontSize: 16,
    fontWeight: "800",
  },
  bottomNavIconActive: {
    color: theme.colors.primary,
  },
  bottomNavLabel: {
    marginTop: 2,
    color: theme.colors.mutedText,
    fontSize: 11,
    fontWeight: "600",
  },
  bottomNavLabelCompact: {
    fontSize: 10,
  },
  bottomNavLabelActive: {
    color: theme.colors.primary,
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
  formCard: {
    backgroundColor: theme.colors.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.lg,
    padding: 14,
    marginBottom: 14,
    ...theme.shadow.card,
  },
  cardEmptyContainer: {
    marginTop: 8,
    gap: 10,
  },
  cardWidget: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.lg,
    padding: 14,
    marginTop: 6,
    backgroundColor: theme.colors.background,
    ...theme.shadow.card,
  },
  cardWidgetActive: {
    borderColor: theme.colors.primary,
    backgroundColor: theme.colors.primarySoft,
  },
  cardWidgetInactive: {
    opacity: 0.8,
  },
  cardWidgetHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 8,
    gap: 10,
  },
  cardWidgetSpaceName: {
    color: theme.colors.text,
    fontSize: 16,
    fontWeight: "800",
    flex: 1,
  },
  cardStatusBadge: {
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 4,
  },
  badgeActive: {
    backgroundColor: theme.colors.success,
  },
  badgeInactive: {
    backgroundColor: theme.colors.danger,
  },
  cardStatusText: {
    color: "#fff",
    fontWeight: "700",
    fontSize: 12,
  },
  cardPlayerName: {
    color: theme.colors.text,
    fontSize: 20,
    fontWeight: "800",
  },
  cardRoleText: {
    color: theme.colors.mutedText,
    marginTop: 2,
    marginBottom: 12,
    fontWeight: "600",
  },
  cardWidgetFooter: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-end",
    gap: 10,
  },
  cardLabel: {
    color: theme.colors.mutedText,
    fontSize: 11,
    letterSpacing: 0.5,
    fontWeight: "700",
    marginBottom: 2,
  },
  cardReference: {
    color: theme.colors.text,
    fontWeight: "800",
  },
  cardSigContainer: {
    alignItems: "flex-end",
  },
  cardSignatureText: {
    color: theme.colors.text,
    fontWeight: "700",
  },
  cardInfoSection: {
    marginTop: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    padding: 10,
    backgroundColor: theme.colors.card,
  },
  cardInfoRow: {
    color: theme.colors.text,
    marginTop: 4,
  },
  cardInfoLabel: {
    color: theme.colors.mutedText,
    fontWeight: "600",
  },
  cardActionsRow: {
    marginTop: 12,
    flexDirection: "row",
    gap: 10,
  },
  cardActionButton: {
    flex: 1,
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    paddingVertical: 11,
    alignItems: "center",
  },
  cardActionButtonSecondary: {
    backgroundColor: theme.colors.primarySoft,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  cardActionButtonText: {
    color: "#fff",
    fontWeight: "700",
    fontSize: 13,
  },
  cardActionButtonTextSecondary: {
    color: theme.colors.primary,
  },
  cardDeleteButton: {
    marginTop: 10,
    borderWidth: 1,
    borderColor: theme.colors.danger,
    borderRadius: theme.radius.md,
    paddingVertical: 11,
    alignItems: "center",
    backgroundColor: "transparent",
  },
  cardDeleteButtonText: {
    color: theme.colors.danger,
    fontWeight: "700",
  },
  buttonDisabled: {
    opacity: 0.55,
  },
  gamesContainer: {
    flex: 1,
  },
  gamesLoadingInline: {
    paddingVertical: 6,
    alignItems: "center",
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
  infoText: {
    marginTop: 8,
    color: theme.colors.mutedText,
    fontSize: 13,
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
    color: theme.colors.text,
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
    ...theme.shadow.card,
  },
  targetCard: {
    borderColor: theme.colors.primary,
    backgroundColor: theme.colors.primarySoft,
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
  playersHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 12,
  },
  addPlayerButton: {
    backgroundColor: theme.colors.primary,
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: theme.radius.md,
  },
  addPlayerButtonText: {
    color: "#fff",
    fontWeight: "700",
    fontSize: 13,
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
    ...theme.shadow.card,
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
  searchBarRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
  },
  searchInputField: {
    flex: 1,
    marginBottom: 0,
  },
  searchButton: {
    marginTop: 0,
    paddingHorizontal: 14,
  },
  filterChipsRow: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 8,
    marginTop: 10,
    marginBottom: 10,
  },
  filterChip: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.card,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 7,
  },
  filterChipActive: {
    backgroundColor: theme.colors.primarySoft,
    borderColor: theme.colors.primary,
  },
  filterChipText: {
    color: theme.colors.text,
    fontWeight: "600",
    fontSize: 12,
  },
  filterChipTextActive: {
    color: theme.colors.primary,
  },
  searchResultsWrap: {
    gap: 12,
  },
  searchSummaryRow: {
    marginTop: 2,
    marginBottom: 10,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    backgroundColor: theme.colors.background,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    paddingHorizontal: 10,
    paddingVertical: 8,
  },
  searchSummaryText: {
    color: theme.colors.text,
    fontWeight: "700",
  },
  searchSummaryMuted: {
    color: theme.colors.mutedText,
    fontSize: 12,
  },
  searchSection: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.card,
    padding: 10,
  },
  searchSectionHeaderRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 6,
  },
  searchSectionTitle: {
    color: theme.colors.text,
    fontWeight: "700",
  },
  searchSectionCount: {
    color: theme.colors.primary,
    backgroundColor: theme.colors.primarySoft,
    borderRadius: 999,
    paddingHorizontal: 8,
    paddingVertical: 2,
    fontSize: 12,
    fontWeight: "700",
  },
  searchRow: {
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
    paddingVertical: 8,
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
  },
  searchRowContent: {
    flex: 1,
  },
  searchRowIcon: {
    fontSize: 16,
  },
  searchRowTitle: {
    color: theme.colors.text,
    fontWeight: "600",
  },
  searchRowMeta: {
    color: theme.colors.mutedText,
    marginTop: 2,
    fontSize: 12,
  },
  searchRowAction: {
    color: theme.colors.primary,
    fontWeight: "700",
    fontSize: 12,
  },
  searchEmptyText: {
    color: theme.colors.mutedText,
    fontSize: 13,
    fontStyle: "italic",
    paddingVertical: 6,
  },
  rankingRow: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.card,
    padding: 10,
    marginBottom: 8,
    flexDirection: "row",
    alignItems: "center",
  },
  rankingRank: {
    width: 42,
    color: theme.colors.primary,
    fontWeight: "800",
    fontSize: 16,
  },
  rankingMain: {
    flex: 1,
  },
  rankingName: {
    color: theme.colors.text,
    fontWeight: "700",
  },
  rankingMeta: {
    color: theme.colors.mutedText,
    marginTop: 2,
  },
  competitionCard: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.card,
    padding: 12,
    marginBottom: 8,
    ...theme.shadow.card,
  },
  shareLinkButton: {
    marginTop: 10,
    alignSelf: "flex-start",
    backgroundColor: theme.colors.primarySoft,
    borderRadius: theme.radius.md,
    paddingHorizontal: 10,
    paddingVertical: 8,
  },
  shareLinkButtonText: {
    color: theme.colors.primary,
    fontWeight: "700",
  },
  confirmOverlay: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.45)",
    alignItems: "center",
    justifyContent: "center",
    paddingHorizontal: 18,
  },
  confirmCard: {
    width: "100%",
    maxWidth: 420,
    backgroundColor: theme.colors.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.lg,
    padding: 16,
    ...theme.shadow.card,
  },
  confirmTitle: {
    color: theme.colors.text,
    fontSize: 16,
    fontWeight: "800",
    marginBottom: 8,
  },
  confirmMessage: {
    color: theme.colors.mutedText,
    fontSize: 14,
    lineHeight: 20,
  },
  confirmActionsRow: {
    flexDirection: "row",
    justifyContent: "flex-end",
    gap: 10,
    marginTop: 16,
  },
  confirmCancelButton: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.background,
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  confirmCancelText: {
    color: theme.colors.mutedText,
    fontWeight: "700",
  },
  confirmSubmitButton: {
    borderWidth: 1,
    borderColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.primary,
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  confirmSubmitButtonDanger: {
    borderColor: theme.colors.danger,
    backgroundColor: theme.colors.danger,
  },
  confirmSubmitText: {
    color: "#fff",
    fontWeight: "700",
  },
});
