import {
  forwardRef,
  type InputHTMLAttributes,
  type ReactNode,
} from "react";

import { cx } from "@/lib/utils";

import styles from "./input.module.css";

type InputProps =
  InputHTMLAttributes<HTMLInputElement> & {
    label: string;
    helperText?: string;
    error?: string;
    leadingIcon?: ReactNode;
  };

export const Input = forwardRef<
  HTMLInputElement,
  InputProps
>(function Input(
  {
    label,
    helperText,
    error,
    leadingIcon,
    id,
    className,
    ...props
  },
  ref,
) {
  const inputId =
    id ??
    `field-${label
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")}`;

  const messageId =
    error || helperText
      ? `${inputId}-message`
      : undefined;

  return (
    <div className={styles.field}>
      <label
        className={styles.label}
        htmlFor={inputId}
      >
        {label}
      </label>

      <div className={styles.control}>
        {leadingIcon ? (
          <span className={styles.icon}>
            {leadingIcon}
          </span>
        ) : null}

        <input
          ref={ref}
          id={inputId}
          className={cx(
            styles.input,
            className,
          )}
          aria-invalid={Boolean(error)}
          aria-describedby={messageId}
          {...props}
        />
      </div>

      {error || helperText ? (
        <p
          id={messageId}
          className={cx(
            styles.helper,
            Boolean(error) &&
              styles.error,
          )}
        >
          {error ?? helperText}
        </p>
      ) : null}
    </div>
  );
});
