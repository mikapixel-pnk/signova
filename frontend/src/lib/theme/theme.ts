export type SignovaTheme =
  | "system"
  | "light"
  | "dark";

export const THEME_STORAGE_KEY =
  "signova.theme.v1";

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
      THEME_STORAGE_KEY,
    );

  if (
    stored === "light" ||
    stored === "dark" ||
    stored === "system"
  ) {
    return stored;
  }

  return "system";
}

export function resolveTheme(
  theme: SignovaTheme,
): "light" | "dark" {
  if (theme !== "system") {
    return theme;
  }

  if (
    typeof window !==
    "undefined" &&
    window.matchMedia(
      "(prefers-color-scheme: dark)",
    ).matches
  ) {
    return "dark";
  }

  return "light";
}

export function applyTheme(
  theme: SignovaTheme,
): void {
  if (
    typeof document ===
    "undefined"
  ) {
    return;
  }

  document.documentElement.dataset.theme =
    resolveTheme(theme);

  document.documentElement.dataset.themePreference =
    theme;

  if (
    typeof window !==
    "undefined"
  ) {
    window.localStorage.setItem(
      THEME_STORAGE_KEY,
      theme,
    );
  }
}
