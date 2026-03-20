export type ThemePreference = "system" | "light" | "dark";
export type ResolvedTheme = "light" | "dark";

export type AppTheme = {
  colors: {
    background: string;
    backgroundSoft: string;
    card: string;
    text: string;
    mutedText: string;
    primary: string;
    primarySoft: string;
    primaryStrong: string;
    danger: string;
    border: string;
    success: string;
    warning: string;
  };
  radius: {
    sm: number;
    md: number;
    lg: number;
    xl: number;
    pill: number;
  };
  spacing: {
    xs: number;
    sm: number;
    md: number;
    lg: number;
    xl: number;
  };
  shadow: {
    card: {
      shadowColor: string;
      shadowOpacity: number;
      shadowRadius: number;
      shadowOffset: { width: number; height: number };
      elevation: number;
    };
  };
};

const shared = {
  radius: {
    sm: 10,
    md: 14,
    lg: 20,
    xl: 24,
    pill: 999,
  },
  spacing: {
    xs: 6,
    sm: 10,
    md: 14,
    lg: 18,
    xl: 24,
  },
};

export const lightTheme: AppTheme = {
  colors: {
    background: "#f3f6fb",
    backgroundSoft: "#eef3fb",
    card: "#ffffff",
    text: "#101828",
    mutedText: "#667085",
    primary: "#0f62fe",
    primarySoft: "#e6efff",
    primaryStrong: "#0043ce",
    danger: "#d92d20",
    border: "#d8e0ee",
    success: "#067647",
    warning: "#b54708",
  },
  ...shared,
  shadow: {
    card: {
      shadowColor: "#0f172a",
      shadowOpacity: 0.06,
      shadowRadius: 10,
      shadowOffset: { width: 0, height: 4 },
      elevation: 3,
    },
  },
};

export const darkTheme: AppTheme = {
  colors: {
    background: "#0d1117",
    backgroundSoft: "#161b22",
    card: "#111827",
    text: "#e5e7eb",
    mutedText: "#9ca3af",
    primary: "#60a5fa",
    primarySoft: "#1e293b",
    primaryStrong: "#3b82f6",
    danger: "#f87171",
    border: "#243244",
    success: "#34d399",
    warning: "#f59e0b",
  },
  ...shared,
  shadow: {
    card: {
      shadowColor: "#000000",
      shadowOpacity: 0.35,
      shadowRadius: 12,
      shadowOffset: { width: 0, height: 5 },
      elevation: 5,
    },
  },
};

export function resolveTheme(preference: ThemePreference, systemScheme: "light" | "dark" | null): ResolvedTheme {
  if (preference === "light") {
    return "light";
  }
  if (preference === "dark") {
    return "dark";
  }
  return systemScheme === "dark" ? "dark" : "light";
}

export function getThemeByMode(mode: ResolvedTheme): AppTheme {
  return mode === "dark" ? darkTheme : lightTheme;
}
