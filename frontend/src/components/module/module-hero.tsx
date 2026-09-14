import {
  Sparkles,
} from "lucide-react";

import type {
  ComponentType,
  ReactNode,
} from "react";

import styles from "./module-hero.module.css";

type ModuleHeroProps = {
  eyebrow: string;
  title: string;
  description: string;

  icon: ComponentType<{
    size?: number;
    strokeWidth?: number;
  }>;

  tone?:
    | "blue"
    | "cyan"
    | "teal"
    | "green"
    | "violet"
    | "rose";

  insightTitle?: string;
  insightDescription?: string;

  actions?: ReactNode;
};

export function ModuleHero({
  eyebrow,
  title,
  description,
  icon: Icon,
  tone = "cyan",
  insightTitle,
  insightDescription,
  actions,
}: ModuleHeroProps) {
  return (
    <section
      className={styles.hero}
      data-tone={tone}
    >
      <div className={styles.main}>
        <span className={styles.icon}>
          <Icon
            size={25}
            strokeWidth={1.9}
          />
        </span>

        <div className={styles.copy}>
          <p className={styles.eyebrow}>
            {eyebrow}
          </p>

          <h1>{title}</h1>

          <p className={styles.description}>
            {description}
          </p>

          {actions ? (
            <div className={styles.actions}>
              {actions}
            </div>
          ) : null}
        </div>
      </div>

      {insightTitle ? (
        <aside className={styles.insight}>
          <span
            className={styles.spark}
            aria-hidden="true"
          >
            <Sparkles
              size={22}
              strokeWidth={1.8}
            />
          </span>

          <strong>
            {insightTitle}
          </strong>

          {insightDescription ? (
            <p>
              {insightDescription}
            </p>
          ) : null}
        </aside>
      ) : null}
    </section>
  );
}
