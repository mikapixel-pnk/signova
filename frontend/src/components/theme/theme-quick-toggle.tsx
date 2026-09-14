"use client";

import {
  Monitor,
  Moon,
  Sun,
} from "lucide-react";

import {
  useEffect,
  useState,
} from "react";

import {
  applyTheme,
  getStoredTheme,
  SIGNOVA_THEME_CHANGE_EVENT,
  type SignovaTheme,
} from "@/lib/theme/theme";

import styles from "./theme-quick-toggle.module.css";

const options: Array<{
  value: SignovaTheme;
  label: string;
  icon: typeof Monitor;
}> = [
  {
    value: "system",
    label: "Sistem",
    icon: Monitor,
  },
  {
    value: "light",
    label: "Terang",
    icon: Sun,
  },
  {
    value: "dark",
    label: "Gelap",
    icon: Moon,
  },
];

export function ThemeQuickToggle() {
  const [
    theme,
    setTheme,
  ] = useState<SignovaTheme>(
    "system",
  );

  useEffect(() => {
    const media =
      window.matchMedia(
        "(prefers-color-scheme: dark)",
      );

    window.queueMicrotask(
      () => {
        const stored =
          getStoredTheme();

        setTheme(stored);
        applyTheme(stored);
      },
    );

    const handleSystemChange =
      () => {
        if (
          getStoredTheme() ===
          "system"
        ) {
          applyTheme(
            "system",
          );
        }
      };

    const handleThemeEvent =
      (
        event: Event,
      ) => {
        const customEvent =
          event as CustomEvent<{
            preference?:
              SignovaTheme;
          }>;

        const preference =
          customEvent.detail
            ?.preference;

        if (preference) {
          setTheme(
            preference,
          );
        }
      };

    media.addEventListener(
      "change",
      handleSystemChange,
    );

    window.addEventListener(
      SIGNOVA_THEME_CHANGE_EVENT,
      handleThemeEvent,
    );

    return () => {
      media.removeEventListener(
        "change",
        handleSystemChange,
      );

      window.removeEventListener(
        SIGNOVA_THEME_CHANGE_EVENT,
        handleThemeEvent,
      );
    };
  }, []);

  function handleChange(
    value: SignovaTheme,
  ) {
    setTheme(value);
    applyTheme(value);
  }

  return (
    <div
      className={
        styles.control
      }
      aria-label="Tema tampilan"
    >
      {options.map(
        (option) => {
          const Icon =
            option.icon;

          const active =
            theme ===
            option.value;

          return (
            <button
              key={
                option.value
              }
              type="button"
              className={
                active
                  ? styles.optionActive
                  : styles.option
              }
              title={
                option.label
              }
              aria-label={
                `Gunakan tema ${option.label}`
              }
              aria-pressed={
                active
              }
              onClick={() =>
                handleChange(
                  option.value,
                )
              }
            >
              <Icon
                size={16}
                strokeWidth={1.9}
              />

              <span>
                {option.label}
              </span>
            </button>
          );
        },
      )}
    </div>
  );
}
