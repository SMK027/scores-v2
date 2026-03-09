import React, { createContext, useContext, useEffect, useState, useCallback } from 'react';
import { auth, setToken, getToken } from '../services/api';
import * as SecureStore from 'expo-secure-store';

let memoryStorage: Record<string, string> = {};

async function secureGet(key: string): Promise<string | null> {
  try {
    return await SecureStore.getItemAsync(key);
  } catch {
    return memoryStorage[key] ?? null;
  }
}

async function secureSet(key: string, value: string): Promise<void> {
  try {
    await SecureStore.setItemAsync(key, value);
  } catch {
    memoryStorage[key] = value;
  }
}

async function secureDelete(key: string): Promise<void> {
  try {
    await SecureStore.deleteItemAsync(key);
  } catch {
    delete memoryStorage[key];
  }
}

export interface User {
  id: number;
  username: string;
  email: string;
  global_role: string;
  avatar: string | null;
  bio: string | null;
  created_at?: string;
}

interface AuthContextType {
  user: User | null;
  token: string | null;
  isLoading: boolean;
  login: (email: string, password: string) => Promise<void>;
  register: (
    username: string,
    email: string,
    password: string,
    passwordConfirm: string
  ) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType>({
  user: null,
  token: null,
  isLoading: true,
  login: async () => {},
  register: async () => {},
  logout: async () => {},
  refreshUser: async () => {},
});

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [token, setTokenState] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  // Charger le token au démarrage
  useEffect(() => {
    (async () => {
      try {
        const savedToken = await secureGet('auth_token');
        if (savedToken) {
          setToken(savedToken);
          setTokenState(savedToken);
          const response = await auth.me();
          setUser(response.user);
        }
      } catch {
        await secureDelete('auth_token');
        setToken(null);
        setTokenState(null);
      } finally {
        setIsLoading(false);
      }
    })();
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    const response = await auth.login(email, password);
    setToken(response.token);
    setTokenState(response.token);
    setUser(response.user);
    await secureSet('auth_token', response.token);
  }, []);

  const register = useCallback(
    async (
      username: string,
      email: string,
      password: string,
      passwordConfirm: string
    ) => {
      const response = await auth.register({
        username,
        email,
        password,
        password_confirm: passwordConfirm,
      });
      setToken(response.token);
      setTokenState(response.token);
      setUser(response.user);
      await secureSet('auth_token', response.token);
    },
    []
  );

  const logout = useCallback(async () => {
    setToken(null);
    setTokenState(null);
    setUser(null);
    await secureDelete('auth_token');
  }, []);

  const refreshUser = useCallback(async () => {
    try {
      const response = await auth.me();
      setUser(response.user);
    } catch {
      await logout();
    }
  }, [logout]);

  return (
    <AuthContext.Provider
      value={{ user, token, isLoading, login, register, logout, refreshUser }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  return useContext(AuthContext);
}
