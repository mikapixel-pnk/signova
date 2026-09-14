import {
  ArrowRight,
  Lightbulb,
} from "lucide-react";

import Link from "next/link";

import styles from "./module-footer-card.module.css";

type ModuleFooterCardProps = {
  title: string;
  description: string;

  actionLabel?: string;
  href?: string;

  tone?:
    | "blue"
    | "cyan"
    | "teal"
    | "green"
    | "violet"
    | "rose";
};

export function ModuleFooterCard({
  title,
  description,
  actionLabel,
  href,
  tone = "violet",
}: ModuleFooterCardProps) {
  return (
    <section
      className={styles.card}
      data-tone={tone}
    >
      <span className={styles.icon}>
        <Lightbulb
          size={20}
          strokeWidth={1.9}
        />
      </span>

      <div className={styles.copy}>
        <strong>{title}</strong>
        <p>{description}</p>
      </div>

      {href && actionLabel ? (
        <Link
          href={href}
          className={styles.action}
        >
          {actionLabel}

          <ArrowRight
            size={16}
            strokeWidth={1.9}
          />
        </Link>
      ) : null}
    </section>
  );
}
