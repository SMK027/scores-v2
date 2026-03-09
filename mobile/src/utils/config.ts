import Constants from 'expo-constants';

/**
 * Configuration de l'application mobile Scores.
 *
 * En développement (Expo Go), l'IP du serveur est détectée automatiquement
 * à partir de la connexion Expo. En production, définir PRODUCTION_API_URL.
 */

const API_PORT = 8080;
const PRODUCTION_API_URL = ''; // À remplir pour une build de production

function getDevApiBaseUrl(): string {
  // expo-constants expose le host du dev server (ex: "192.168.1.42:8081")
  const debuggerHost =
    Constants.expoGoConfig?.debuggerHost ??
    Constants.expoConfig?.hostUri ??
    '';
  const host = debuggerHost.split(':')[0];
  if (host) {
    return `http://${host}:${API_PORT}/api`;
  }
  // Fallback
  return `http://localhost:${API_PORT}/api`;
}

export const API_BASE_URL = PRODUCTION_API_URL || getDevApiBaseUrl();

export const APP_NAME = 'Scores';

export const COLORS = {
  primary: '#4361ee',
  primaryDark: '#3a0ca3',
  secondary: '#7209b7',
  accent: '#f72585',
  success: '#06d6a0',
  warning: '#ffd166',
  danger: '#e94560',
  background: '#1a1a2e',
  surface: '#16213e',
  surfaceLight: '#1f2b47',
  card: '#0f3460',
  text: '#e0e0e0',
  textSecondary: '#a0a0b0',
  textMuted: '#6c757d',
  border: '#2a3a5c',
  white: '#ffffff',
  black: '#000000',
};

export const GAME_STATUS_LABELS: Record<string, string> = {
  pending: 'En attente',
  in_progress: 'En cours',
  paused: 'En pause',
  completed: 'Terminée',
};

export const GAME_STATUS_COLORS: Record<string, string> = {
  pending: COLORS.textMuted,
  in_progress: COLORS.success,
  paused: COLORS.warning,
  completed: COLORS.primary,
};

export const WIN_CONDITION_LABELS: Record<string, string> = {
  highest_score: 'Score le plus élevé',
  lowest_score: 'Score le plus bas',
  ranking: 'Classement (positions)',
  win_loss: 'Victoire / Défaite',
};

export const SPACE_ROLE_LABELS: Record<string, string> = {
  admin: 'Administrateur',
  manager: 'Gestionnaire',
  member: 'Membre',
  guest: 'Invité',
};

export const ROUND_STATUS_LABELS: Record<string, string> = {
  in_progress: 'En cours',
  paused: 'En pause',
  completed: 'Terminée',
};
