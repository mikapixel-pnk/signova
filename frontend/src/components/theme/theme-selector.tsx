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
  type SignovaTheme,
} from "@/lib/theme/theme";

import styles from "./theme-selector.module.css";

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

export function ThemeSelector() {
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

    const handleChange = () => {
      if (
        getStoredTheme() ===
        "system"
      ) {
        applyTheme(
          "system",
        );
      }
    };

    media.addEventListener(
      "change",
      handleChange,
    );

    return () => {
      media.removeEventListener(
        "change",
        handleChange,
      );
    };
  }, []);

  function selectTheme(
    value: SignovaTheme,
  ) {
    setTheme(value);
    applyTheme(value);
  }

  return (
    <div
      className={styles.group}
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
                  ? styles.active
                  : styles.option
              }
              aria-pressed={
                active
              }
              onClick={() =>
                selectTheme(
                  option.value,
                )
              }
            >
              <Icon
                size={16}
                strokeWidth={2}
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
