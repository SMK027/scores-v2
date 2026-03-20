import { createContext, useContext, useEffect, useMemo, useState } from "react";
import type { ReactNode } from "react";
import { useColorScheme } from "react-native";
import AsyncStorage from "@react-native-async-storage/async-storage";
import type { AppTheme, ResolvedTheme, ThemePreference } from "../styles";
import { getThemeByMode, resolveTheme } from "../styles";

const THEME_PREFERENCE_KEY = "scores.mobile.theme.preference.v1";

type ThemeContextValue = {
  theme: AppTheme;
  preference: ThemePreference;
  resolvedMode: ResolvedTheme;
  setPreference: (next: ThemePreference) => void;
};

const ThemeContext = createContext<ThemeContextValue | undefined>(undefined);

type Props = {
  children: ReactNode;
};

export function ThemeProvider({ children }: Props) {
  const systemScheme = useColorScheme();
  const [preference, setPreferenceState] = useState<ThemePreference>("system");

  useEffect(() => {
    const loadPreference = async () => {
      try {
        const raw = await AsyncStorage.getItem(THEME_PREFERENCE_KEY);
        if (raw === "light" || raw === "dark" || raw === "system") {
          setPreferenceState(raw);
        }
      } catch {
        // Fallback silencieux vers le mode système.
      }
    };

    void loadPreference();
  }, []);

  const setPreference = (next: ThemePreference) => {
    setPreferenceState(next);
    void AsyncStorage.setItem(THEME_PREFERENCE_KEY, next).catch(() => undefined);
  };

  const resolvedMode = useMemo<ResolvedTheme>(
    () => resolveTheme(preference, systemScheme ?? null),
    [preference, systemScheme]
  );

  const theme = useMemo(() => getThemeByMode(resolvedMode), [resolvedMode]);

  const value = useMemo<ThemeContextValue>(
    () => ({ theme, preference, resolvedMode, setPreference }),
    [theme, preference, resolvedMode]
  );

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useAppTheme(): ThemeContextValue {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error("useAppTheme doit être utilisé dans un ThemeProvider");
  }
  return context;
}
