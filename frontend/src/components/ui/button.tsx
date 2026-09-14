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
  };

export function Button({
  variant = "primary",
  fullWidth = false,
  leadingIcon,
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
      {...props}
    >
      {leadingIcon}
      {children}
    </button>
  );
}
