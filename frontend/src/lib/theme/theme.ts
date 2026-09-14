export type SignovaTheme =
  | "system"
  | "light"
  | "dark";

export type ResolvedSignovaTheme =
  | "light"
  | "dark";

export const SIGNOVA_THEME_STORAGE_KEY =
  "signova.theme.v1";

export const SIGNOVA_THEME_CHANGE_EVENT =
  "signova:theme-change";

function isSignovaTheme(
  value: string | null,
): value is SignovaTheme {
  return (
    value === "system" ||
    value === "light" ||
    value === "dark"
  );
}

export function getStoredTheme():
  SignovaTheme {
  if (
    typeof window ===
    "undefined"
  ) {
    return "system";
  }

  const stored =
    window.localStorage.getItem(
      SIGNOVA_THEME_STORAGE_KEY,
    );

  return isSignovaTheme(
    stored,
  )
    ? stored
    : "system";
}

export function resolveTheme(
  theme: SignovaTheme,
): ResolvedSignovaTheme {
  if (
    theme === "light" ||
    theme === "dark"
  ) {
    return theme;
  }

  if (
    typeof window ===
    "undefined"
  ) {
    return "light";
  }

  return window.matchMedia(
    "(prefers-color-scheme: dark)",
  ).matches
    ? "dark"
    : "light";
}

export function applyTheme(
  theme: SignovaTheme,
): ResolvedSignovaTheme {
  const resolved =
    resolveTheme(theme);

  if (
    typeof document !==
    "undefined"
  ) {
    document.documentElement.dataset.theme =
      resolved;

    document.documentElement.dataset.themePreference =
      theme;
  }

  if (
    typeof window !==
    "undefined"
  ) {
    window.localStorage.setItem(
      SIGNOVA_THEME_STORAGE_KEY,
      theme,
    );

    window.dispatchEvent(
      new CustomEvent(
        SIGNOVA_THEME_CHANGE_EVENT,
        {
          detail: {
            preference:
              theme,
            resolved,
          },
        },
      ),
    );
  }

  return resolved;
}
