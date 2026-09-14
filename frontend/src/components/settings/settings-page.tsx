"use client";

import {
  useSearchParams,
} from "next/navigation";

import Link from "next/link";

import {
  ModulePage,
} from "@/components/module/module-page";

import {
  getSettingsSection,
  getSettingsSections,
} from "@/lib/module/settings";

import styles from "./settings-page.module.css";

const sections =
  getSettingsSections();

export function SettingsPage() {
  const searchParams =
    useSearchParams();

  const active =
    getSettingsSection(
      searchParams.get(
        "bagian",
      ),
    );

  return (
    <div
      className={
        styles.layout
      }
    >
      <nav
        className={
          styles.tabs
        }
        aria-label="Bagian pengaturan"
      >
        {sections.map(
          (section) => {
            const Icon =
              section.icon;

            const selected =
              section.key ===
              active.key;

            return (
              <Link
                key={
                  section.key
                }
                href={
                  section.href
                }
                data-tone={
                  section.tone
                }
                className={
                  selected
                    ? styles.tabActive
                    : styles.tab
                }
              >
                <Icon
                  size={17}
                  strokeWidth={
                    1.9
                  }
                />

                <span>
                  {
                    section.shortLabel ??
                    section.label
                  }
                </span>
              </Link>
            );
          },
        )}
      </nav>

      <ModulePage
        moduleKey={
          active.key
        }
      />
    </div>
  );
}
