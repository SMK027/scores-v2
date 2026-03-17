import AsyncStorage from "@react-native-async-storage/async-storage";
import type { User } from "../types/api";

type PersistedSession = {
  token: string;
  user: User;
};

const SESSION_KEY = "scores.mobile.session.v1";

export async function saveSession(token: string, user: User): Promise<void> {
  const payload: PersistedSession = { token, user };
  await AsyncStorage.setItem(SESSION_KEY, JSON.stringify(payload));
}

export async function loadSession(): Promise<PersistedSession | null> {
  const raw = await AsyncStorage.getItem(SESSION_KEY);
  if (!raw) {
    return null;
  }

  try {
    const parsed = JSON.parse(raw) as PersistedSession;
    if (!parsed?.token || !parsed?.user) {
      return null;
    }
    return parsed;
  } catch {
    return null;
  }
}

export async function clearSession(): Promise<void> {
  await AsyncStorage.removeItem(SESSION_KEY);
}
