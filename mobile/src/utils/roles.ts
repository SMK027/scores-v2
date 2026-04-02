import type { AppTheme } from "../styles";

export function getRoleLabel(role?: string): string {
  switch (role) {
    case "superadmin":
      return "Super administrateur";
    case "admin":
      return "Administrateur";
    case "moderator":
      return "Modérateur";
    case "manager":
      return "Gestionnaire";
    case "member":
      return "Membre";
    case "guest":
      return "Invité";
    case "user":
      return "Utilisateur";
    default:
      return role || "Inconnu";
  }
}

export function getRoleBadgeColors(
  theme: AppTheme,
  role?: string
): { bg: string; text: string; border: string } {
  switch (role) {
    case "superadmin":
      return { bg: theme.colors.danger + "18", text: theme.colors.danger, border: theme.colors.danger + "55" };
    case "admin":
      return { bg: theme.colors.primary + "18", text: theme.colors.primary, border: theme.colors.primary + "55" };
    case "moderator":
      return { bg: theme.colors.primarySoft, text: theme.colors.primaryStrong, border: theme.colors.primary + "30" };
    case "manager":
      return { bg: theme.colors.success + "18", text: theme.colors.success, border: theme.colors.success + "55" };
    case "guest":
      return { bg: theme.colors.warning + "18", text: theme.colors.warning, border: theme.colors.warning + "55" };
    case "member":
    default:
      return { bg: theme.colors.backgroundSoft, text: theme.colors.mutedText, border: theme.colors.border };
  }
}
