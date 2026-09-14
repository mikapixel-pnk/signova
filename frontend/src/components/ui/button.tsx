import {
  LoaderCircle,
} from "lucide-react";

import type {
  ButtonHTMLAttributes,
  ReactNode,
} from "react";

import { cx } from "@/lib/utils";

import styles from "./button.module.css";

type ButtonVariant =
  | "primary"
  | "secondary"
  | "ghost";

type ButtonProps =
  ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: ButtonVariant;
    fullWidth?: boolean;
    leadingIcon?: ReactNode;
    loading?: boolean;
    loadingLabel?: string;
  };

export function Button({
  variant = "primary",
  fullWidth = false,
  leadingIcon,
  loading = false,
  loadingLabel = "Memproses...",
  disabled,
  className,
  children,
  ...props
}: ButtonProps) {
  return (
    <button
      className={cx(
        styles.button,
        styles[variant],
        fullWidth && styles.full,
        className,
      )}
      disabled={
        disabled ||
        loading
      }
      aria-busy={loading}
      {...props}
    >
      {loading ? (
        <LoaderCircle
          className={styles.spinner}
          size={18}
          strokeWidth={2}
          aria-hidden="true"
        />
      ) : (
        leadingIcon
      )}

      <span>
        {loading
          ? loadingLabel
          : children}
      </span>
    </button>
  );
}
