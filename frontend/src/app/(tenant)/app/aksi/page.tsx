import Link from "next/link";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  ModuleHero,
} from "@/components/module/module-hero";

import {
  getQuickActions,
} from "@/lib/module/actions";

import styles from "./page.module.css";

const quickActions =
  getQuickActions();

export default function ActionsPage() {
  return (
    <TenantShell>
      <section
        className={
          styles.page
        }
      >
        <ModuleHero
          eyebrow="Aksi Cepat"
          title="Mau melakukan apa?"
          description="Pilih pekerjaan yang ingin Anda mulai."
          icon={
            quickActions[0]
              .icon
          }
          tone="blue"
          insightTitle="Mulai pekerjaan utama lebih cepat"
          insightDescription="Akses tugas harian SIGNOVA tanpa harus membuka banyak menu."
        />

        <div
          className={
            styles.actionGrid
          }
        >
          {quickActions.map(
            (action) => {
              const Icon =
                action.icon;

              return (
                <Link
                  key={
                    action.key
                  }
                  href={
                    action.href
                  }
                  data-tone={
                    action.tone
                  }
                  className={
                    styles.actionCard
                  }
                >
                  <span
                    className={
                      styles.actionIcon
                    }
                  >
                    <Icon
                      size={22}
                      strokeWidth={1.9}
                    />
                  </span>

                  <span
                    className={
                      styles.actionCopy
                    }
                  >
                    <strong>
                      {action.label}
                    </strong>

                    <small>
                      {
                        action.description
                      }
                    </small>
                  </span>
                </Link>
              );
            },
          )}
        </div>
      </section>
    </TenantShell>
  );
}
