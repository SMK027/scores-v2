import { API_BASE_URL } from "../config/constants";
import type {
  ApiErrorPayload,
  Game,
  GameDetailsResponse,
  GameType,
  GamesResponse,
  Player,
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

export async function fetchSpaces(token: string): Promise<Space[]> {
  const response = await request<SpacesResponse>("/api/spaces", {}, token);
  return response.spaces;
}

export async function fetchSpaceGames(token: string, spaceId: number): Promise<Game[]> {
  const response = await request<GamesResponse>(`/api/spaces/${spaceId}/games`, {}, token);
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

export { ApiError };
