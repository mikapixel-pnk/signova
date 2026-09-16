"use client";

import Link from "next/link";

import {
  useEffect,
  useRef,
} from "react";

import {
  useSearchParams,
} from "next/navigation";

import {
  BusinessProfileSettings,
} from "@/components/settings/business-profile-settings";

import {
  DocumentSettings,
} from "@/components/settings/document-settings";

import {
  PaymentSettings,
} from "@/components/settings/payment-settings";

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

const groups = [
  {
    key: "business",
    title: "Usaha & Dokumen",
    description:
      "Identitas usaha dan pengaturan dokumen bisnis.",
    keys: [
      "business-settings",
      "document-settings",
    ],
  },
  {
    key: "finance",
    title: "Keuangan & Pembayaran",
    description:
      "Rekening, pembayaran, dan pengaturan finansial.",
    keys: [
      "finance-settings",
    ],
  },
  {
    key: "account",
    title: "Akun & Akses",
    description:
      "Akun pengguna, anggota tim, dan hak akses.",
    keys: [
      "profile-settings",
      "team-access",
    ],
  },
  {
    key: "system",
    title: "Sistem & Langganan",
    description:
      "Langganan dan koneksi layanan eksternal.",
    keys: [
      "subscription",
      "integrations",
    ],
  },
] as const;

export function SettingsPage() {
  const searchParams =
    useSearchParams();

  const active =
    getSettingsSection(
      searchParams.get(
        "bagian",
      ),
    );

  const detailRef =
    useRef<HTMLElement | null>(
      null,
    );

  useEffect(
    () => {
      const mobileOrTablet =
        window.matchMedia(
          "(max-width: 1179px)",
        ).matches;

      const hasExplicitSection =
        searchParams.has(
          "bagian",
        );

      if (
        !mobileOrTablet ||
        !hasExplicitSection
      ) {
        return;
      }

      const timer =
        window.setTimeout(
          () => {
            detailRef.current
              ?.scrollIntoView({
                behavior:
                  "smooth",
                block:
                  "start",
              });
          },
          80,
        );

      return () => {
        window.clearTimeout(
          timer,
        );
      };
    },
    [
      active.key,
      searchParams,
    ],
  );

  return (
    <div
      className={
        styles.layout
      }
    >
      <section
        className={
          styles.settingsMenu
        }
        aria-label="Menu pengaturan"
      >
        {groups.map(
          (group) => {
            const items =
              sections.filter(
                (section) =>
                  group.keys.some(
                    (key) =>
                      key ===
                      section.key,
                  ),
              );

            if (
              items.length === 0
            ) {
              return null;
            }

            return (
              <section
                key={
                  group.key
                }
                className={
                  styles.group
                }
              >
                <header
                  className={
                    styles.groupHeader
                  }
                >
                  <div>
                    <h2
                      className={
                        styles.groupTitle
                      }
                    >
                      {
                        group.title
                      }
                    </h2>

                    <p
                      className={
                        styles.groupDescription
                      }
                    >
                      {
                        group.description
                      }
                    </p>
                  </div>
                </header>

                <div
                  className={
                    styles.groupItems
                  }
                >
                  {items.map(
                    (
                      section,
                    ) => {
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
                              ? styles.menuItemActive
                              : styles.menuItem
                          }
                        >
                          <span
                            className={
                              styles.menuIcon
                            }
                          >
                            <Icon
                              size={
                                19
                              }
                              strokeWidth={
                                1.9
                              }
                            />
                          </span>

                          <span
                            className={
                              styles.menuContent
                            }
                          >
                            <strong>
                              {
                                section.label
                              }
                            </strong>

                            <small>
                              {
                                section.description
                              }
                            </small>
                          </span>

                          <span
                            aria-hidden="true"
                            className={
                              styles.chevron
                            }
                          >
                            ›
                          </span>
                        </Link>
                      );
                    },
                  )}
                </div>
              </section>
            );
          },
        )}
      </section>

      <section
        id="settings-detail"
        ref={
          detailRef
        }
        className={
          styles.detail
        }
      >
        {active.key ===
        "business-settings" ? (
          <BusinessProfileSettings />
        ) : active.key ===
          "document-settings" ? (
          <DocumentSettings />
        ) : active.key ===
          "finance-settings" ? (
          <PaymentSettings />
        ) : (
          <ModulePage
            moduleKey={
              active.key
            }
          />
        )}
      </section>
    </div>
  );
}
