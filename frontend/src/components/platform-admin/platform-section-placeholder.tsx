import {
  Construction,
} from "lucide-react";

import type {
  PlatformAdminNavigationItem,
} from "@/lib/platform-admin/navigation";

import styles from "./platform-admin.module.css";

type PlatformSectionPlaceholderProps = {
  item: PlatformAdminNavigationItem;
};

export function PlatformSectionPlaceholder({
  item,
}: PlatformSectionPlaceholderProps) {
  return (
    <div
      className={
        styles.pageStack
      }
    >
      <section
        className={
          styles.sectionHero
        }
      >
        <span
          className={
            styles.sectionIcon
          }
        >
          <Construction
            size={24}
          />
        </span>

        <div>
          <span
            className={
              styles.eyebrow
            }
          >
            Platform Admin
          </span>

          <h2>
            {item.label}
          </h2>

          <p>
            {item.description}
          </p>
        </div>
      </section>

      <section
        className={
          styles.infoPanel
        }
      >
        <h3>
          Modul sedang disiapkan
        </h3>

        <p>
          Foundation dan pembatasan
          akses sudah tersedia.
          Implementasi data dan aksi
          akan ditambahkan pada slice
          berikutnya tanpa mencampur
          modul tenant.
        </p>
      </section>
    </div>
  );
}
