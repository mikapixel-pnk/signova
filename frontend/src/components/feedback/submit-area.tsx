import type {
  ReactNode,
} from "react";

import {
  ActionFeedback,
  type ActionFeedbackProps,
} from "./action-feedback";

import styles from "./submit-area.module.css";

type SubmitAreaProps = {
  feedback?: ActionFeedbackProps;
  children: ReactNode;
  stickyMobile?: boolean;
};

export function SubmitArea({
  feedback,
  children,
  stickyMobile = false,
}: SubmitAreaProps) {
  return (
    <div
      className={
        stickyMobile
          ? `${styles.area} ${styles.sticky}`
          : styles.area
      }
    >
      {feedback ? (
        <ActionFeedback {...feedback} />
      ) : null}

      <div className={styles.actions}>
        {children}
      </div>
    </div>
  );
}
