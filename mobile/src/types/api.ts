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
  games_count?: number;
};

export type Invitation = {
  id: number;
  space_id: number;
  invited_user_id: number;
  invited_by: number;
  role: string;
  status: 'pending' | 'accepted' | 'declined';
  created_at: string;
  space_name: string;
  invited_by_name: string;
};

export type SpacesResponse = {
  success: boolean;
  spaces: Space[];
  pending_invitations: Invitation[];
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
  games_participation_restricted?: boolean;
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

export type Comment = {
  id: number;
  game_id: number;
  user_id: number;
  content: string;
  created_at: string;
  updated_at?: string;
  username: string;
  avatar?: string | null;
};

export type GameDetailsResponse = {
  success: boolean;
  game: Game & { win_condition?: string };
  players: GamePlayer[];
  rounds: Round[];
  round_scores: Record<string, Record<string, number>>;
  comments: Comment[];
  can_comment: boolean;
};

export type SearchComment = {
  id: number;
  content: string;
  created_at?: string;
  username?: string;
  game_id?: number;
};

export type SearchResults = {
  players: Player[];
  game_types: GameType[];
  games: Game[];
  comments: SearchComment[];
};

export type SpaceSearchResponse = {
  success: boolean;
  query: string;
  results: SearchResults;
  total: number;
};

export type LeaderboardEntry = {
  rank: number;
  user_id: number;
  username: string;
  avatar?: string | null;
  rounds_played: number;
  rounds_won: number;
  win_rate: number;
};

export type LeaderboardResponse = {
  success: boolean;
  period: "all" | "7d" | "30d" | "3m" | "6m" | "1y" | "custom";
  custom_from?: string;
  custom_to?: string;
  criteria: {
    min_rounds_played: number;
    min_spaces_played: number;
  };
  leaderboard: LeaderboardEntry[];
  total: number;
};

export type Competition = {
  id: number;
  space_id: number;
  name: string;
  description?: string | null;
  status: "planned" | "active" | "paused" | "closed";
  starts_at?: string | null;
  ends_at?: string | null;
  created_by?: number | null;
  creator_name?: string | null;
  session_count: number;
};

export type CompetitionParticipant = {
  player_id: number;
  name: string;
  linked_username?: string | null;
  rounds_played: number;
  rounds_won: number;
  win_rate: number;
};

export type CompetitionStats = {
  total_games: number;
  completed_games: number;
  total_rounds: number;
  total_play_seconds: number;
  avg_play_seconds_per_game: number;
  avg_rounds_per_competitor: number;
  avg_win_rate: number;
};

export type CompetitionDetailsResponse = {
  success: boolean;
  competition: Competition;
  participants: CompetitionParticipant[];
  stats: CompetitionStats;
};
