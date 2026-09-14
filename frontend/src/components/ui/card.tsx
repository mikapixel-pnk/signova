import type {
  HTMLAttributes,
} from "react";

import { cx } from "@/lib/utils";

import styles from "./card.module.css";

export function Card({
  className,
  ...props
}: HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cx(
        styles.card,
        className,
      )}
      {...props}
    />
  );
}
