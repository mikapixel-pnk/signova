import {
  CheckCircle2,
} from "lucide-react";

import type {
  ModuleKey,
} from "@/config/module-types";

import {
  getModule,
  modulePlanLabel,
} from "@/lib/module/registry";

import {
  ModuleFooterCard,
} from "./module-footer-card";

import {
  ModuleHero,
} from "./module-hero";

import styles from "./module-page.module.css";

type ModulePageProps = {
  moduleKey:
    ModuleKey;

  children?:
    React.ReactNode;
};

export function ModulePage({
  moduleKey,
  children,
}: ModulePageProps) {
  const moduleDef =
    getModule(
      moduleKey,
    );

  const planLabel =
    modulePlanLabel(
      moduleDef.plan,
    );

  const ModuleIcon =
    moduleDef.icon;

  const heroTone =
    moduleDef.tone ===
    "amber"
      ? "blue"
      : moduleDef.tone;

  return (
    <section
      className={
        styles.page
      }
    >
      <ModuleHero
        eyebrow={
          moduleDef.group ===
          "master-data"
            ? "Master Data"
            : moduleDef.group ===
                "penjualan"
              ? "Penjualan"
              : moduleDef.group ===
                  "operasional"
                ? "Operasional"
                : moduleDef.group ===
                    "keuangan"
                  ? "Keuangan"
                  : moduleDef.group ===
                      "pengaturan"
                    ? "Pengaturan"
                    : moduleDef.group ===
                        "fitur-lanjutan"
                      ? "Fitur Lanjutan"
                      : moduleDef.label
        }
        title={
          moduleDef.label
        }
        description={
          moduleDef.description
        }
        icon={
          ModuleIcon
        }
        tone={
          heroTone
        }
        insightTitle={
          moduleDef.insight
            .title
        }
        insightDescription={
          moduleDef.insight
            .description
        }
      />

      {moduleDef.highlights
        ?.length ? (
        <section
          className={
            styles.highlights
          }
        >
          {moduleDef.highlights.map(
            (item) => (
              <div
                key={item}
                className={
                  styles.highlight
                }
              >
                <span
                  className={
                    styles.highlightIcon
                  }
                >
                  <CheckCircle2
                    size={18}
                    strokeWidth={
                      1.9
                    }
                  />
                </span>

                <span>
                  {item}
                </span>
              </div>
            ),
          )}
        </section>
      ) : null}

      {children ? (
        <section
          className={
            styles.content
          }
        >
          {children}
        </section>
      ) : (
        <section
          className={
            styles.workspace
          }
        >
          <div
            className={
              styles.workspaceIcon
            }
          >
            <ModuleIcon
              size={27}
              strokeWidth={1.8}
            />
          </div>

          <h2>
            Ruang kerja{" "}
            {moduleDef.label}
          </h2>

          <p>
            Fondasi modul sudah
            tersedia. Fungsi
            transaksi akan
            diaktifkan bertahap
            menggunakan data asli
            SIGNOVA.
          </p>
        </section>
      )}

      <ModuleFooterCard
        tone={
          heroTone
        }
        title={
          planLabel
            ? `Tersedia pada paket ${planLabel}`
            : moduleDef.footer
                ?.title ??
              `Tentang ${moduleDef.label}`
        }
        description={
          moduleDef.footer
            ?.description ??
          (
            planLabel
              ? `${moduleDef.label} merupakan kemampuan tambahan untuk paket ${planLabel}.`
              : moduleDef.insight
                  .description
          )
        }
        href={
          moduleDef.footer
            ?.href
        }
        actionLabel={
          moduleDef.footer
            ?.actionLabel
        }
      />
    </section>
  );
}
