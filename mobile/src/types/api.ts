export type ApiErrorPayload = {
  success?: boolean;
  message?: string;
  errors?: string[];
};

export type User = {
  id: number;
  username: string;
  email: string;
  global_role: string;
  avatar?: string | null;
  bio?: string | null;
  created_at?: string;
};

export type Space = {
  id: number;
  name: string;
  description?: string | null;
  created_by?: number;
  user_role?: string;
};

export type SpacesResponse = {
  success: boolean;
  spaces: Space[];
};

export type GameType = {
  id: number;
  space_id: number;
  name: string;
  description?: string;
  win_condition: "highest_score" | "lowest_score" | "ranking" | "win_loss";
  min_players?: number;
  max_players?: number | null;
};

export type Player = {
  id: number;
  space_id: number;
  name: string;
  user_id?: number | null;
  linked_username?: string | null;
};

export type SpaceMember = {
  id: number;
  space_id: number;
  user_id: number;
  role: "admin" | "manager" | "member" | "guest";
  username: string;
  email?: string;
  avatar?: string | null;
};

export type Game = {
  id: number;
  space_id: number;
  game_type_id: number;
  game_type_name?: string;
  status: "pending" | "in_progress" | "paused" | "completed";
  created_at?: string;
  started_at?: string | null;
  ended_at?: string | null;
  notes?: string | null;
  player_count?: number;
};

export type GamesResponse = {
  success: boolean;
  data: Game[];
  total: number;
  page: number;
  perPage: number;
  lastPage: number;
};

export type GamePlayer = {
  id: number;
  game_id: number;
  player_id: number;
  player_name: string;
  total_score: number;
  is_winner?: number;
  rank?: number | null;
};

export type Round = {
  id: number;
  game_id: number;
  round_number: number;
  status: "in_progress" | "paused" | "completed";
  notes?: string | null;
};

export type ProfileStats = {
  total_rounds: number;
  rounds_won: number;
  win_rate: number;
  total_spaces: number;
};

export type GameDetailsResponse = {
  success: boolean;
  game: Game & { win_condition?: string };
  players: GamePlayer[];
  rounds: Round[];
  round_scores: Record<string, Record<string, number>>;
};
