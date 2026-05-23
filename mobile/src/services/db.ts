/**
 * Service SQLite local — cache hors connexion
 *
 * Tables :
 *  - cached_spaces       : liste des espaces + rôle utilisateur
 *  - cached_game_types   : types de jeux (space_id NULL = global)
 *  - cached_players      : joueurs par espace
 *  - offline_queue       : actions en attente de synchronisation
 */

import * as SQLite from "expo-sqlite";
import type { Game, GameDetailsResponse, GameType, Player, Space, User } from "../types/api";

// ─── Types session offline ────────────────────────────────────────────────────

export type AuthSession = {
  token: string;
  refreshToken: string | null;
  expiresAt: number; // timestamp ms
  user: User;
  savedAt: number;   // timestamp ms
};

// ─── Types internes ───────────────────────────────────────────────────────────

export type QueueAction =
  | { type: "create_game"; spaceId: number; gameTypeId: number; playerIds: number[]; notes?: string; tempId: string }
  | { type: "create_round"; spaceId: number; gameId: number | string; tempId: string }
  | { type: "update_scores"; spaceId: number; gameId: number | string; roundId: number | string; scores: Record<number, number | null> };

export type QueueEntry = {
  id: number;
  action: QueueAction;
  createdAt: number;
  attempts: number;
};

// ─── Initialisation ───────────────────────────────────────────────────────────

let _db: SQLite.SQLiteDatabase | null = null;

export async function getDb(): Promise<SQLite.SQLiteDatabase> {
  if (_db) return _db;
  _db = await SQLite.openDatabaseAsync("scores_offline.db");
  await initSchema(_db);
  return _db;
}

async function initSchema(db: SQLite.SQLiteDatabase): Promise<void> {
  await db.execAsync(`
    PRAGMA journal_mode = WAL;

    CREATE TABLE IF NOT EXISTS cached_spaces (
      id         INTEGER PRIMARY KEY,
      name       TEXT    NOT NULL,
      user_role  TEXT    NOT NULL,
      data_json  TEXT    NOT NULL,
      cached_at  INTEGER NOT NULL
    );

    CREATE TABLE IF NOT EXISTS cached_game_types (
      id         INTEGER PRIMARY KEY,
      space_id   INTEGER,
      data_json  TEXT    NOT NULL,
      cached_at  INTEGER NOT NULL
    );

    CREATE TABLE IF NOT EXISTS cached_players (
      id         INTEGER PRIMARY KEY,
      space_id   INTEGER NOT NULL,
      data_json  TEXT    NOT NULL,
      cached_at  INTEGER NOT NULL
    );

    CREATE TABLE IF NOT EXISTS cached_games (
      id         INTEGER PRIMARY KEY,
      space_id   INTEGER NOT NULL,
      data_json  TEXT    NOT NULL,
      cached_at  INTEGER NOT NULL
    );

    CREATE TABLE IF NOT EXISTS cached_game_details (
      game_id    INTEGER PRIMARY KEY,
      space_id   INTEGER NOT NULL,
      data_json  TEXT    NOT NULL,
      cached_at  INTEGER NOT NULL
    );

    CREATE TABLE IF NOT EXISTS offline_queue (
      id          INTEGER PRIMARY KEY AUTOINCREMENT,
      action_json TEXT    NOT NULL,
      created_at  INTEGER NOT NULL,
      attempts    INTEGER NOT NULL DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS auth_session (
      id            INTEGER PRIMARY KEY CHECK (id = 1),
      token         TEXT    NOT NULL,
      refresh_token TEXT,
      expires_at    INTEGER NOT NULL,
      user_json     TEXT    NOT NULL,
      saved_at      INTEGER NOT NULL
    );
  `);
}

// ─── Cache : espaces ──────────────────────────────────────────────────────────

export async function cacheSpaces(spaces: Space[]): Promise<void> {
  const db = await getDb();
  const now = Date.now();
  await db.withTransactionAsync(async () => {
    await db.runAsync("DELETE FROM cached_spaces");
    for (const space of spaces) {
      await db.runAsync(
        "INSERT OR REPLACE INTO cached_spaces (id, name, user_role, data_json, cached_at) VALUES (?, ?, ?, ?, ?)",
        space.id,
        space.name,
        space.user_role ?? "member",
        JSON.stringify(space),
        now,
      );
    }
  });
}

export async function getCachedSpaces(): Promise<Space[]> {
  const db = await getDb();
  const rows = await db.getAllAsync<{ data_json: string }>("SELECT data_json FROM cached_spaces");
  return rows.map((r) => JSON.parse(r.data_json) as Space);
}

// ─── Cache : types de jeux ────────────────────────────────────────────────────

export async function cacheGameTypes(gameTypes: GameType[], spaceId: number | null): Promise<void> {
  const db = await getDb();
  const now = Date.now();
  await db.withTransactionAsync(async () => {
    if (spaceId !== null) {
      await db.runAsync("DELETE FROM cached_game_types WHERE space_id = ?", spaceId);
    } else {
      await db.runAsync("DELETE FROM cached_game_types WHERE space_id IS NULL");
    }
    for (const gt of gameTypes) {
      await db.runAsync(
        "INSERT OR REPLACE INTO cached_game_types (id, space_id, data_json, cached_at) VALUES (?, ?, ?, ?)",
        gt.id,
        spaceId,
        JSON.stringify(gt),
        now,
      );
    }
  });
}

export async function getCachedGameTypes(spaceId: number | null): Promise<GameType[]> {
  const db = await getDb();
  let rows: { data_json: string }[];
  if (spaceId !== null) {
    rows = await db.getAllAsync<{ data_json: string }>(
      "SELECT data_json FROM cached_game_types WHERE space_id = ?",
      spaceId,
    );
  } else {
    rows = await db.getAllAsync<{ data_json: string }>(
      "SELECT data_json FROM cached_game_types WHERE space_id IS NULL",
    );
  }
  return rows.map((r) => JSON.parse(r.data_json) as GameType);
}

// ─── Cache : joueurs ──────────────────────────────────────────────────────────

export async function cachePlayers(players: Player[], spaceId: number): Promise<void> {
  const db = await getDb();
  const now = Date.now();
  await db.withTransactionAsync(async () => {
    await db.runAsync("DELETE FROM cached_players WHERE space_id = ?", spaceId);
    for (const player of players) {
      await db.runAsync(
        "INSERT OR REPLACE INTO cached_players (id, space_id, data_json, cached_at) VALUES (?, ?, ?, ?)",
        player.id,
        spaceId,
        JSON.stringify(player),
        now,
      );
    }
  });
}

export async function getCachedPlayers(spaceId: number): Promise<Player[]> {
  const db = await getDb();
  const rows = await db.getAllAsync<{ data_json: string }>(
    "SELECT data_json FROM cached_players WHERE space_id = ?",
    spaceId,
  );
  return rows.map((r) => JSON.parse(r.data_json) as Player);
}

// ─── Cache : parties ─────────────────────────────────────────────────────────

export async function cacheGames(games: Game[], spaceId: number): Promise<void> {
  const db = await getDb();
  const now = Date.now();
  await db.withTransactionAsync(async () => {
    await db.runAsync("DELETE FROM cached_games WHERE space_id = ?", spaceId);
    for (const game of games) {
      await db.runAsync(
        "INSERT OR REPLACE INTO cached_games (id, space_id, data_json, cached_at) VALUES (?, ?, ?, ?)",
        game.id,
        spaceId,
        JSON.stringify(game),
        now,
      );
    }
  });
}

export async function getCachedGames(spaceId: number): Promise<Game[]> {
  const db = await getDb();
  const rows = await db.getAllAsync<{ data_json: string }>(
    "SELECT data_json FROM cached_games WHERE space_id = ? ORDER BY id DESC",
    spaceId,
  );
  return rows.map((r) => JSON.parse(r.data_json) as Game);
}

// ─── Cache : détails de partie ────────────────────────────────────────────────

export async function cacheGameDetails(details: GameDetailsResponse, spaceId: number): Promise<void> {
  const db = await getDb();
  await db.runAsync(
    `INSERT OR REPLACE INTO cached_game_details (game_id, space_id, data_json, cached_at)
     VALUES (?, ?, ?, ?)`,
    details.game.id,
    spaceId,
    JSON.stringify(details),
    Date.now(),
  );
}

export async function getCachedGameDetails(
  spaceId: number,
  gameId: number,
): Promise<GameDetailsResponse | null> {
  const db = await getDb();
  const row = await db.getFirstAsync<{ data_json: string }>(
    "SELECT data_json FROM cached_game_details WHERE game_id = ? AND space_id = ?",
    gameId,
    spaceId,
  );
  if (!row) return null;
  try {
    return JSON.parse(row.data_json) as GameDetailsResponse;
  } catch {
    return null;
  }
}

// ─── File d'attente hors connexion ────────────────────────────────────────────

export async function enqueue(action: QueueAction): Promise<number> {
  const db = await getDb();
  const result = await db.runAsync(
    "INSERT INTO offline_queue (action_json, created_at, attempts) VALUES (?, ?, 0)",
    JSON.stringify(action),
    Date.now(),
  );
  return result.lastInsertRowId;
}

export async function getQueue(): Promise<QueueEntry[]> {
  const db = await getDb();
  const rows = await db.getAllAsync<{ id: number; action_json: string; created_at: number; attempts: number }>(
    "SELECT id, action_json, created_at, attempts FROM offline_queue ORDER BY created_at ASC",
  );
  return rows.map((r) => ({
    id: r.id,
    action: JSON.parse(r.action_json) as QueueAction,
    createdAt: r.created_at,
    attempts: r.attempts,
  }));
}

export async function dequeue(id: number): Promise<void> {
  const db = await getDb();
  await db.runAsync("DELETE FROM offline_queue WHERE id = ?", id);
}

export async function incrementAttempts(id: number): Promise<void> {
  const db = await getDb();
  await db.runAsync("UPDATE offline_queue SET attempts = attempts + 1 WHERE id = ?", id);
}

export async function getQueueLength(): Promise<number> {
  const db = await getDb();
  const row = await db.getFirstAsync<{ count: number }>("SELECT COUNT(*) as count FROM offline_queue");
  return row?.count ?? 0;
}

// ─── Session d'authentification offline ──────────────────────────────────────

// Durée de validité du token considérée par défaut : 7 jours
export const DEFAULT_TOKEN_TTL_MS = 7 * 24 * 60 * 60 * 1000;

export async function saveAuthSession(
  token: string,
  user: User,
  opts: { refreshToken?: string; expiresAt?: number } = {},
): Promise<void> {
  const db = await getDb();
  const now = Date.now();
  const expiresAt = opts.expiresAt ?? now + DEFAULT_TOKEN_TTL_MS;
  await db.runAsync(
    `INSERT INTO auth_session (id, token, refresh_token, expires_at, user_json, saved_at)
     VALUES (1, ?, ?, ?, ?, ?)
     ON CONFLICT(id) DO UPDATE SET
       token         = excluded.token,
       refresh_token = excluded.refresh_token,
       expires_at    = excluded.expires_at,
       user_json     = excluded.user_json,
       saved_at      = excluded.saved_at`,
    token,
    opts.refreshToken ?? null,
    expiresAt,
    JSON.stringify(user),
    now,
  );
}

export async function loadAuthSession(): Promise<AuthSession | null> {
  const db = await getDb();
  const row = await db.getFirstAsync<{
    token: string;
    refresh_token: string | null;
    expires_at: number;
    user_json: string;
    saved_at: number;
  }>("SELECT token, refresh_token, expires_at, user_json, saved_at FROM auth_session WHERE id = 1");
  if (!row) return null;
  try {
    return {
      token: row.token,
      refreshToken: row.refresh_token,
      expiresAt: row.expires_at,
      user: JSON.parse(row.user_json) as User,
      savedAt: row.saved_at,
    };
  } catch {
    return null;
  }
}

export async function clearAuthSession(): Promise<void> {
  const db = await getDb();
  await db.runAsync("DELETE FROM auth_session WHERE id = 1");
}
