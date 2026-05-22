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
    brand: string;
    brandSoft: string;
    danger: string;
    dangerSoft: string;
    border: string;
    borderStrong: string;
    success: string;
    successSoft: string;
    warning: string;
    warningSoft: string;
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
    xxl: number;
  };
  font: {
    size: {
      xs: number;
      sm: number;
      md: number;
      lg: number;
      xl: number;
      xxl: number;
      xxxl: number;
    };
    weight: {
      regular: "400";
      medium: "500";
      semibold: "600";
      bold: "700";
      extrabold: "800";
      heavy: "900";
    };
    lineHeight: {
      tight: number;
      normal: number;
      relaxed: number;
    };
  };
  shadow: {
    sm: {
      shadowColor: string;
      shadowOpacity: number;
      shadowRadius: number;
      shadowOffset: { width: number; height: number };
      elevation: number;
    };
    card: {
      shadowColor: string;
      shadowOpacity: number;
      shadowRadius: number;
      shadowOffset: { width: number; height: number };
      elevation: number;
    };
    strong: {
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
    xxl: 32,
  },
  font: {
    size: {
      xs: 11,
      sm: 13,
      md: 15,
      lg: 17,
      xl: 20,
      xxl: 24,
      xxxl: 30,
    },
    weight: {
      regular: "400" as const,
      medium: "500" as const,
      semibold: "600" as const,
      bold: "700" as const,
      extrabold: "800" as const,
      heavy: "900" as const,
    },
    lineHeight: {
      tight: 20,
      normal: 24,
      relaxed: 28,
    },
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
    brand: "#635bff",
    brandSoft: "#eeecff",
    danger: "#d92d20",
    dangerSoft: "#fef3f2",
    border: "#d8e0ee",
    borderStrong: "#b0bdd4",
    success: "#067647",
    successSoft: "#ecfdf3",
    warning: "#b54708",
    warningSoft: "#fffaeb",
  },
  ...shared,
  shadow: {
    sm: {
      shadowColor: "#0f172a",
      shadowOpacity: 0.04,
      shadowRadius: 4,
      shadowOffset: { width: 0, height: 2 },
      elevation: 1,
    },
    card: {
      shadowColor: "#0f172a",
      shadowOpacity: 0.06,
      shadowRadius: 10,
      shadowOffset: { width: 0, height: 4 },
      elevation: 3,
    },
    strong: {
      shadowColor: "#0f172a",
      shadowOpacity: 0.12,
      shadowRadius: 20,
      shadowOffset: { width: 0, height: 8 },
      elevation: 6,
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
    brand: "#7c74ff",
    brandSoft: "#1e1b4b",
    danger: "#f87171",
    dangerSoft: "#2d1b1b",
    border: "#243244",
    borderStrong: "#334155",
    success: "#34d399",
    successSoft: "#052e16",
    warning: "#f59e0b",
    warningSoft: "#1c1508",
  },
  ...shared,
  shadow: {
    sm: {
      shadowColor: "#000000",
      shadowOpacity: 0.15,
      shadowRadius: 4,
      shadowOffset: { width: 0, height: 2 },
      elevation: 1,
    },
    card: {
      shadowColor: "#000000",
      shadowOpacity: 0.35,
      shadowRadius: 12,
      shadowOffset: { width: 0, height: 5 },
      elevation: 5,
    },
    strong: {
      shadowColor: "#000000",
      shadowOpacity: 0.5,
      shadowRadius: 24,
      shadowOffset: { width: 0, height: 10 },
      elevation: 10,
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
