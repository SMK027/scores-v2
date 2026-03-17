import { API_BASE_URL } from "../config/constants";
import type { User } from "../types/api";

export function getInitials(user: Pick<User, "username" | "email">): string {
  const source = user.username || user.email || "?";
  return source.slice(0, 2).toUpperCase();
}

export function getAvatarUri(avatar?: string | null): string | null {
  if (!avatar) {
    return null;
  }

  if (/^https?:\/\//i.test(avatar)) {
    return avatar;
  }

  if (avatar.startsWith("/")) {
    return `${API_BASE_URL}${avatar}`;
  }

  return `${API_BASE_URL}/${avatar}`;
}
