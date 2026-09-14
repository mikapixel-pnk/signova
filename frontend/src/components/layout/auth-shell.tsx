import type {
  ReactNode,
} from "react";

import { Brand } from "@/components/ui/brand";

import styles from "./auth-shell.module.css";

type AuthShellProps = {
  children: ReactNode;
};

export function AuthShell({
  children,
}: AuthShellProps) {
  return (
    <main className={styles.shell}>
      <div className={styles.container}>
        <aside className={styles.info}>
          <Brand />

          <div>
            <h1>
              Operasional bisnis,
              lebih sederhana.
            </h1>

            <p>
              Kelola pelanggan, tagihan,
              pembayaran, dan arus kas
              dalam satu sistem yang
              ringan dan mudah digunakan.
            </p>
          </div>

          <small>
            SIGNOVA Platform
          </small>
        </aside>

        <section
          className={styles.content}
        >
          {children}
        </section>
      </div>
    </main>
  );
}
