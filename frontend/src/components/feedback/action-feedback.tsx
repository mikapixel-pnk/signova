import {
  AlertCircle,
  CheckCircle2,
  Info,
  TriangleAlert,
} from "lucide-react";

import { cx } from "@/lib/utils";

import styles from "./action-feedback.module.css";

export type FeedbackTone =
  | "error"
  | "success"
  | "warning"
  | "info";

export type ActionFeedbackProps = {
  tone: FeedbackTone;
  title: string;
  message?: string;
  requestId?: string | null;
  className?: string;
};

const icons = {
  error: AlertCircle,
  success: CheckCircle2,
  warning: TriangleAlert,
  info: Info,
};

export function ActionFeedback({
  tone,
  title,
  message,
  requestId,
  className,
}: ActionFeedbackProps) {
  const Icon = icons[tone];

  return (
    <div
      className={cx(
        styles.feedback,
        styles[tone],
        className,
      )}
      role={tone === "error" ? "alert" : "status"}
      aria-live={
        tone === "error"
          ? "assertive"
          : "polite"
      }
    >
      <span className={styles.icon}>
        <Icon
          size={19}
          strokeWidth={2}
        />
      </span>

      <div className={styles.content}>
        <strong className={styles.title}>
          {title}
        </strong>

        {message ? (
          <p className={styles.message}>
            {message}
          </p>
        ) : null}

        {requestId ? (
          <p className={styles.reference}>
            Referensi: {requestId}
          </p>
        ) : null}
      </div>
    </div>
  );
}
