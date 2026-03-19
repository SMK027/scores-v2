import { API_BASE_URL } from "../config/constants";
import type {
  ApiErrorPayload,
  Game,
  GameDetailsResponse,
  GameType,
  GamesResponse,
  LeaderboardEntry,
  LeaderboardResponse,
  Player,
  ProfileStats,
  SpaceSearchResponse,
  SpaceMember,
  Competition,
  CompetitionDetailsResponse,
  Space,
  SpacesResponse,
  User,
} from "../types/api";

class ApiError extends Error {
  readonly status: number;

  constructor(message: string, status: number) {
    super(message);
    this.status = status;
  }
}

async function request<T>(
  path: string,
  options: RequestInit = {},
  token?: string
): Promise<T> {
  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    ...(options.headers as Record<string, string> | undefined),
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers,
  });

  const raw = await response.text();
  const payload = raw ? (JSON.parse(raw) as unknown) : null;

  if (!response.ok) {
    const errorPayload = (payload || {}) as ApiErrorPayload;
    const message =
      errorPayload.message ||
      (errorPayload.errors && errorPayload.errors.join("\n")) ||
      "Une erreur est survenue.";
    throw new ApiError(message, response.status);
  }

  return payload as T;
}

export async function login(email: string, password: string): Promise<{ token: string; user: User }> {
  return request<{ token: string; user: User }>("/api/login", {
    method: "POST",
    body: JSON.stringify({ email, password }),
  });
}

export async function refreshAuthToken(token: string): Promise<{ token: string; user: User }> {
  return request<{ success: boolean; token: string; user: User }>("/api/refresh-token", {
    method: "POST",
  }, token);
}

export async function fetchProfile(token: string): Promise<User> {
  const response = await request<{ success: boolean; user: User }>("/api/profile", {}, token);
  return response.user;
}

export async function fetchProfileStats(token: string): Promise<ProfileStats> {
  const response = await request<{ success: boolean; stats: ProfileStats }>("/api/profile/stats", {}, token);
  return response.stats;
}

export async function updateProfile(
  token: string,
  payload: { username?: string; email?: string; bio?: string }
): Promise<User> {
  const response = await request<{ success: boolean; user: User }>("/api/profile", {
    method: "PUT",
    body: JSON.stringify(payload),
  }, token);
  return response.user;
}

export async function fetchSpaces(token: string): Promise<Space[]> {
  const response = await request<SpacesResponse>("/api/spaces", {}, token);
  return response.spaces;
}

export async function createSpace(
  token: string,
  payload: { name: string; description?: string }
): Promise<Space> {
  const response = await request<{ success: boolean; space: Space }>(
    "/api/spaces",
    {
      method: "POST",
      body: JSON.stringify({
        name: payload.name,
        description: payload.description ?? "",
      }),
    },
    token
  );
  return response.space;
}

export async function deleteSpace(token: string, spaceId: number): Promise<void> {
  await request<{ success: boolean; message?: string }>(`/api/spaces/${spaceId}`, {
    method: "DELETE",
  }, token);
}

export async function leaveSpace(token: string, spaceId: number): Promise<void> {
  await request<{ success: boolean; message?: string }>(`/api/spaces/${spaceId}/leave`, {
    method: "POST",
  }, token);
}

export async function fetchSpaceGames(
  token: string,
  spaceId: number,
  status?: "completed" | "in_progress" | "paused"
): Promise<Game[]> {
  const query = status ? `?status=${encodeURIComponent(status)}` : "";
  const response = await request<GamesResponse>(`/api/spaces/${spaceId}/games${query}`, {}, token);
  return response.data;
}

export async function fetchPlayers(token: string, spaceId: number): Promise<Player[]> {
  const response = await request<{ success: boolean; players: Player[] }>(
    `/api/spaces/${spaceId}/players`,
    {},
    token
  );
  return response.players;
}

export async function fetchGameTypes(token: string, spaceId: number): Promise<GameType[]> {
  const response = await request<{ success: boolean; game_types: GameType[] }>(
    `/api/spaces/${spaceId}/game-types`,
    {},
    token
  );
  return response.game_types;
}

export async function createGameType(
  token: string,
  spaceId: number,
  payload: {
    name: string;
    description?: string;
    winCondition: GameType["win_condition"];
    minPlayers?: number;
    maxPlayers?: number | null;
  }
): Promise<GameType> {
  const response = await request<{ success: boolean; game_type: GameType }>(`/api/spaces/${spaceId}/game-types`, {
    method: "POST",
    body: JSON.stringify({
      name: payload.name,
      description: payload.description ?? "",
      win_condition: payload.winCondition,
      min_players: payload.minPlayers ?? 1,
      max_players: payload.maxPlayers ?? null,
    }),
  }, token);

  return response.game_type;
}

export async function updateGameType(
  token: string,
  spaceId: number,
  gameTypeId: number,
  payload: {
    name: string;
    description?: string;
    winCondition: GameType["win_condition"];
    minPlayers?: number;
    maxPlayers?: number | null;
  }
): Promise<GameType> {
  const response = await request<{ success: boolean; game_type: GameType }>(
    `/api/spaces/${spaceId}/game-types/${gameTypeId}`,
    {
      method: "PUT",
      body: JSON.stringify({
        name: payload.name,
        description: payload.description ?? "",
        win_condition: payload.winCondition,
        min_players: payload.minPlayers ?? 1,
        max_players: payload.maxPlayers ?? null,
      }),
    },
    token
  );

  return response.game_type;
}

export async function deleteGameType(token: string, spaceId: number, gameTypeId: number): Promise<void> {
  await request<{ success: boolean }>(`/api/spaces/${spaceId}/game-types/${gameTypeId}`, {
    method: "DELETE",
  }, token);
}

export async function fetchSpaceMembers(token: string, spaceId: number): Promise<SpaceMember[]> {
  const response = await request<{ success: boolean; members: SpaceMember[] }>(
    `/api/spaces/${spaceId}/members`,
    {},
    token
  );
  return response.members;
}

export async function inviteMember(
  token: string,
  spaceId: number,
  username: string,
  role: SpaceMember["role"] = "member"
): Promise<void> {
  await request<{ success: boolean }>(
    `/api/spaces/${spaceId}/members`,
    {
      method: "POST",
      body: JSON.stringify({ username, role }),
    },
    token
  );
}

export async function updateMemberRole(
  token: string,
  spaceId: number,
  memberId: number,
  role: SpaceMember["role"]
): Promise<void> {
  await request<{ success: boolean }>(
    `/api/spaces/${spaceId}/members/${memberId}/role`,
    {
      method: "PUT",
      body: JSON.stringify({ role }),
    },
    token
  );
}

export async function removeSpaceMember(
  token: string,
  spaceId: number,
  memberId: number
): Promise<void> {
  await request<{ success: boolean }>(
    `/api/spaces/${spaceId}/members/${memberId}`,
    { method: "DELETE" },
    token
  );
}

export async function createInviteLink(
  token: string,
  spaceId: number
): Promise<string> {
  const response = await request<{ success: boolean; token: string }>(
    `/api/spaces/${spaceId}/invite-link`,
    { method: "POST" },
    token
  );
  return response.token;
}

export async function createGame(
  token: string,
  spaceId: number,
  payload: { gameTypeId: number; playerIds: number[]; notes?: string }
): Promise<Game> {
  const response = await request<{ success: boolean; game: Game }>(`/api/spaces/${spaceId}/games`, {
    method: "POST",
    body: JSON.stringify({
      game_type_id: payload.gameTypeId,
      player_ids: payload.playerIds,
      notes: payload.notes ?? "",
    }),
  }, token);

  return response.game;
}

export async function createPlayer(
  token: string,
  spaceId: number,
  payload: { name: string; userId?: number | null }
): Promise<Player> {
  const response = await request<{ success: boolean; player: Player }>(`/api/spaces/${spaceId}/players`, {
    method: "POST",
    body: JSON.stringify({
      name: payload.name,
      user_id: payload.userId ?? null,
    }),
  }, token);

  return response.player;
}

export async function updatePlayer(
  token: string,
  spaceId: number,
  playerId: number,
  payload: { name: string; userId?: number | null }
): Promise<Player> {
  const response = await request<{ success: boolean; player: Player }>(
    `/api/spaces/${spaceId}/players/${playerId}`,
    {
      method: "PUT",
      body: JSON.stringify({
        name: payload.name,
        user_id: payload.userId ?? null,
      }),
    },
    token
  );

  return response.player;
}

export async function deletePlayer(token: string, spaceId: number, playerId: number): Promise<void> {
  await request<{ success: boolean }>(`/api/spaces/${spaceId}/players/${playerId}`, {
    method: "DELETE",
  }, token);
}

export async function fetchGameDetails(
  token: string,
  spaceId: number,
  gameId: number
): Promise<GameDetailsResponse> {
  return request<GameDetailsResponse>(`/api/spaces/${spaceId}/games/${gameId}`, {}, token);
}

export async function createRound(token: string, spaceId: number, gameId: number): Promise<number> {
  const response = await request<{ success: boolean; round: { id: number } }>(
    `/api/spaces/${spaceId}/games/${gameId}/rounds`,
    { method: "POST", body: JSON.stringify({}) },
    token
  );

  return response.round.id;
}

export async function updateRoundScores(
  token: string,
  spaceId: number,
  gameId: number,
  roundId: number,
  scores: Record<number, number>
): Promise<void> {
  await request<{ success: boolean }>(
    `/api/spaces/${spaceId}/games/${gameId}/rounds/${roundId}/scores`,
    {
      method: "PUT",
      body: JSON.stringify({ scores }),
    },
    token
  );
}

export async function completeGame(token: string, spaceId: number, gameId: number): Promise<void> {
  await request<{ success: boolean }>(`/api/spaces/${spaceId}/games/${gameId}/status`, {
    method: "PUT",
    body: JSON.stringify({ status: "completed" }),
  }, token);
}

export async function updateGameStatus(
  token: string,
  spaceId: number,
  gameId: number,
  status: "pending" | "in_progress" | "paused" | "completed"
): Promise<void> {
  await request<{ success: boolean }>(`/api/spaces/${spaceId}/games/${gameId}/status`, {
    method: "PUT",
    body: JSON.stringify({ status }),
  }, token);
}

export async function updateRoundStatus(
  token: string,
  spaceId: number,
  gameId: number,
  roundId: number,
  status: "in_progress" | "paused" | "completed"
): Promise<void> {
  await request<{ success: boolean }>(`/api/spaces/${spaceId}/games/${gameId}/rounds/${roundId}/status`, {
    method: "PUT",
    body: JSON.stringify({ status }),
  }, token);
}

export async function fetchSpaceSearch(
  token: string,
  spaceId: number,
  query: string
): Promise<SpaceSearchResponse["results"]> {
  const response = await request<SpaceSearchResponse>(
    `/api/spaces/${spaceId}/search?q=${encodeURIComponent(query)}`,
    {},
    token
  );
  return response.results;
}

export async function fetchLeaderboard(
  token: string,
  period: LeaderboardResponse["period"] = "all"
): Promise<{ entries: LeaderboardEntry[]; criteria: LeaderboardResponse["criteria"] }> {
  const response = await request<LeaderboardResponse>(`/api/leaderboard?period=${period}`, {}, token);
  return {
    entries: response.leaderboard,
    criteria: response.criteria,
  };
}

export async function fetchCompetitions(token: string, spaceId: number): Promise<Competition[]> {
  const response = await request<{ success: boolean; competitions: Competition[] }>(
    `/api/spaces/${spaceId}/competitions`,
    {},
    token
  );
  return response.competitions;
}

export async function fetchCompetitionDetails(
  token: string,
  spaceId: number,
  competitionId: number
): Promise<Omit<CompetitionDetailsResponse, "success">> {
  const response = await request<CompetitionDetailsResponse>(
    `/api/spaces/${spaceId}/competitions/${competitionId}`,
    {},
    token
  );

  return {
    competition: response.competition,
    participants: response.participants,
    stats: response.stats,
  };
}

export { ApiError };
