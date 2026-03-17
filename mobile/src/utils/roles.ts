export function getRoleLabel(role?: string): string {
  switch (role) {
    case "superadmin":
      return "Super administrateur";
    case "admin":
      return "Administrateur";
    case "moderator":
      return "Moderateur";
    case "manager":
      return "Gestionnaire";
    case "member":
      return "Membre";
    case "guest":
      return "Invite";
    case "user":
      return "Utilisateur";
    default:
      return role || "Inconnu";
  }
}
