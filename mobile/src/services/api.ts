import { API_BASE_URL } from '../utils/config';

let authToken: string | null = null;

export function setToken(token: string | null) {
  authToken = token;
}

export function getToken(): string | null {
  return authToken;
}

type HttpMethod = 'GET' | 'POST' | 'PUT' | 'DELETE';

interface RequestOptions {
  body?: Record<string, unknown>;
  params?: Record<string, string>;
}

async function request<T = any>(
  method: HttpMethod,
  path: string,
  options: RequestOptions = {}
): Promise<T> {
  let url = `${API_BASE_URL}${path}`;

  if (options.params) {
    const qs = new URLSearchParams(options.params).toString();
    url += `?${qs}`;
  }

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  };

  if (authToken) {
    headers['Authorization'] = `Bearer ${authToken}`;
  }

  const config: RequestInit = { method, headers };
  if (options.body && method !== 'GET') {
    config.body = JSON.stringify(options.body);
  }

  const response = await fetch(url, config);

  if (response.status === 204) {
    return {} as T;
  }

  const data = await response.json();

  if (!response.ok) {
    const message =
      data?.message || data?.errors?.join('\n') || 'Erreur inconnue';
    throw new ApiError(message, response.status, data);
  }

  return data as T;
}

export class ApiError extends Error {
  status: number;
  data: any;

  constructor(message: string, status: number, data?: any) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.data = data;
  }
}

// ─── Auth ─────────────────────────────────────────────────

export const auth = {
  login: (email: string, password: string) =>
    request('POST', '/login', { body: { email, password } }),

  register: (data: {
    username: string;
    email: string;
    password: string;
    password_confirm: string;
  }) => request('POST', '/register', { body: data }),

  me: () => request('GET', '/me'),
};

// ─── Profil ────────────────────────────────────────────────

export const profile = {
  get: () => request('GET', '/profile'),

  update: (data: { username?: string; email?: string; bio?: string }) =>
    request('PUT', '/profile', { body: data }),

  updatePassword: (data: {
    current_password: string;
    new_password: string;
    new_password_confirm: string;
  }) => request('PUT', '/profile/password', { body: data }),
};

// ─── Espaces ──────────────────────────────────────────────

export const spaces = {
  list: () => request('GET', '/spaces'),

  get: (id: number) => request('GET', `/spaces/${id}`),

  create: (data: { name: string; description?: string }) =>
    request('POST', '/spaces', { body: data }),

  update: (id: number, data: { name: string; description?: string }) =>
    request('PUT', `/spaces/${id}`, { body: data }),

  delete: (id: number) => request('DELETE', `/spaces/${id}`),

  leave: (id: number) => request('POST', `/spaces/${id}/leave`),

  join: (token: string) => request('POST', `/spaces/join/${token}`),
};

// ─── Membres ──────────────────────────────────────────────

export const members = {
  list: (spaceId: number) => request('GET', `/spaces/${spaceId}/members`),

  add: (spaceId: number, username: string, role: string = 'member') =>
    request('POST', `/spaces/${spaceId}/members`, {
      body: { username, role },
    }),

  updateRole: (spaceId: number, memberId: number, role: string) =>
    request('PUT', `/spaces/${spaceId}/members/${memberId}/role`, {
      body: { role },
    }),

  remove: (spaceId: number, memberId: number) =>
    request('DELETE', `/spaces/${spaceId}/members/${memberId}`),
};

// ─── Invitations ──────────────────────────────────────────

export const invitations = {
  accept: (invId: number) =>
    request('POST', `/invitations/${invId}/accept`),

  decline: (invId: number) =>
    request('POST', `/invitations/${invId}/decline`),
};

// ─── Types de jeux ────────────────────────────────────────

export const gameTypes = {
  list: (spaceId: number) =>
    request('GET', `/spaces/${spaceId}/game-types`),

  get: (spaceId: number, gtId: number) =>
    request('GET', `/spaces/${spaceId}/game-types/${gtId}`),

  create: (
    spaceId: number,
    data: {
      name: string;
      description?: string;
      win_condition: string;
      min_players?: number;
      max_players?: number;
    }
  ) => request('POST', `/spaces/${spaceId}/game-types`, { body: data }),

  update: (
    spaceId: number,
    gtId: number,
    data: {
      name: string;
      description?: string;
      win_condition: string;
      min_players?: number;
      max_players?: number;
    }
  ) =>
    request('PUT', `/spaces/${spaceId}/game-types/${gtId}`, { body: data }),

  delete: (spaceId: number, gtId: number) =>
    request('DELETE', `/spaces/${spaceId}/game-types/${gtId}`),
};

// ─── Joueurs ──────────────────────────────────────────────

export const players = {
  list: (spaceId: number) =>
    request('GET', `/spaces/${spaceId}/players`),

  get: (spaceId: number, playerId: number) =>
    request('GET', `/spaces/${spaceId}/players/${playerId}`),

  create: (spaceId: number, data: { name: string; user_id?: number }) =>
    request('POST', `/spaces/${spaceId}/players`, { body: data }),

  update: (
    spaceId: number,
    playerId: number,
    data: { name: string; user_id?: number }
  ) =>
    request('PUT', `/spaces/${spaceId}/players/${playerId}`, { body: data }),

  delete: (spaceId: number, playerId: number) =>
    request('DELETE', `/spaces/${spaceId}/players/${playerId}`),
};

// ─── Parties ──────────────────────────────────────────────

export const games = {
  list: (
    spaceId: number,
    params?: { page?: string; status?: string; game_type_id?: string }
  ) => request('GET', `/spaces/${spaceId}/games`, { params }),

  get: (spaceId: number, gameId: number) =>
    request('GET', `/spaces/${spaceId}/games/${gameId}`),

  create: (
    spaceId: number,
    data: { game_type_id: number; player_ids: number[]; notes?: string }
  ) => request('POST', `/spaces/${spaceId}/games`, { body: data }),

  update: (spaceId: number, gameId: number, data: { notes: string }) =>
    request('PUT', `/spaces/${spaceId}/games/${gameId}`, { body: data }),

  delete: (spaceId: number, gameId: number) =>
    request('DELETE', `/spaces/${spaceId}/games/${gameId}`),

  updateStatus: (spaceId: number, gameId: number, status: string) =>
    request('PUT', `/spaces/${spaceId}/games/${gameId}/status`, {
      body: { status },
    }),
};

// ─── Commentaires ─────────────────────────────────────────

export const comments = {
  add: (spaceId: number, gameId: number, content: string) =>
    request('POST', `/spaces/${spaceId}/games/${gameId}/comments`, {
      body: { content },
    }),

  delete: (spaceId: number, gameId: number, commentId: number) =>
    request(
      'DELETE',
      `/spaces/${spaceId}/games/${gameId}/comments/${commentId}`
    ),
};

// ─── Manches ──────────────────────────────────────────────

export const rounds = {
  create: (spaceId: number, gameId: number, notes?: string) =>
    request('POST', `/spaces/${spaceId}/games/${gameId}/rounds`, {
      body: { notes },
    }),

  updateScores: (
    spaceId: number,
    gameId: number,
    roundId: number,
    scores: Record<string, number>
  ) =>
    request(
      'PUT',
      `/spaces/${spaceId}/games/${gameId}/rounds/${roundId}/scores`,
      { body: { scores } }
    ),

  updateStatus: (
    spaceId: number,
    gameId: number,
    roundId: number,
    status: string
  ) =>
    request(
      'PUT',
      `/spaces/${spaceId}/games/${gameId}/rounds/${roundId}/status`,
      { body: { status } }
    ),

  delete: (spaceId: number, gameId: number, roundId: number) =>
    request(
      'DELETE',
      `/spaces/${spaceId}/games/${gameId}/rounds/${roundId}`
    ),
};

// ─── Statistiques ─────────────────────────────────────────

export const stats = {
  get: (spaceId: number) => request('GET', `/spaces/${spaceId}/stats`),
};

// ─── Recherche ────────────────────────────────────────────

export const search = {
  query: (spaceId: number, q: string) =>
    request('GET', `/spaces/${spaceId}/search`, { params: { q } }),
};
