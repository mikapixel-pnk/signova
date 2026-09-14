"use client";

import {
  LogOut,
  Settings,
  Sparkles,
} from "lucide-react";

import Link from "next/link";

import {
  useRouter,
} from "next/navigation";

import {
  useState,
} from "react";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  ThemeSelector,
} from "@/components/theme/theme-selector";

import type {
  ModuleDefinition,
} from "@/config/module-types";

import {
  logout,
} from "@/lib/auth/logout";

import {
  getNavigationModel,
} from "@/lib/module/navigation";

import {
  modulePlanLabel,
} from "@/lib/module/registry";

import styles from "./page.module.css";

const navigation =
  getNavigationModel(
    "mobile",
  );

function MenuTile({
  moduleDef,
}: {
  moduleDef:
    ModuleDefinition;
}) {
  const Icon =
    moduleDef.icon;

  const badge =
    modulePlanLabel(
      moduleDef.plan,
    );

  return (
    <Link
      href={
        moduleDef.href
      }
      data-tone={
        moduleDef.tone
      }
      className={
        styles.menuTile
      }
    >
      <span
        className={
          styles.tileIcon
        }
      >
        <Icon
          size={22}
          strokeWidth={1.9}
        />
      </span>

      <span
        className={
          styles.tileLabel
        }
      >
        {moduleDef.shortLabel ??
          moduleDef.label}
      </span>

      {badge ? (
        <span
          className={
            styles.planBadge
          }
        >
          {badge}
        </span>
      ) : null}
    </Link>
  );
}

export default function MoreMenuPage() {
  const router =
    useRouter();

  const [
    loggingOut,
    setLoggingOut,
  ] = useState(false);

  async function handleLogout() {
    if (loggingOut) {
      return;
    }

    setLoggingOut(true);

    try {
      await logout();
    } finally {
      router.replace(
        "/login",
      );
    }
  }

  const normalGroups =
    navigation.filter(
      (group) =>
        group.key !==
        "fitur-lanjutan",
    );

  const advancedGroup =
    navigation.find(
      (group) =>
        group.key ===
        "fitur-lanjutan",
    );

  return (
    <TenantShell>
      <section
        className={
          styles.page
        }
      >
        <header
          className={
            styles.hero
          }
        >
          <div
            className={
              styles.heroCopy
            }
          >
            <p
              className={
                styles.eyebrow
              }
            >
              SIGNOVA
            </p>

            <h1
              className={
                styles.title
              }
            >
              Menu Lainnya
            </h1>

            <p
              className={
                styles.description
              }
            >
              Semua kebutuhan usaha
              dalam satu tempat.
            </p>
          </div>

          <aside
            className={
              styles.heroInsight
            }
          >
            <Sparkles
              size={20}
              strokeWidth={1.8}
            />

            <strong>
              Kelola bisnis lebih
              mudah dengan SIGNOVA
            </strong>

            <p>
              Pilih menu sesuai
              pekerjaan yang ingin
              Anda selesaikan.
            </p>
          </aside>
        </header>

        <div
          className={
            styles.sections
          }
        >
          {normalGroups.map(
            (group) => {
              const GroupIcon =
                group.icon;

              return (
                <section
                  key={
                    group.key
                  }
                  className={
                    styles.menuSection
                  }
                  data-tone={
                    group.tone
                  }
                >
                  <header
                    className={
                      styles.sectionHeader
                    }
                  >
                    <span
                      className={
                        styles.sectionIcon
                      }
                    >
                      <GroupIcon
                        size={20}
                        strokeWidth={
                          1.9
                        }
                      />
                    </span>

                    <div
                      className={
                        styles.sectionCopy
                      }
                    >
                      <h2>
                        {group.label}
                      </h2>

                      <p>
                        {
                          group.description
                        }
                      </p>
                    </div>

                    <span
                      className={
                        styles.menuCount
                      }
                    >
                      {
                        group.modules
                          .length
                      }{" "}
                      menu
                    </span>
                  </header>

                  <div
                    className={
                      styles.tileGrid
                    }
                  >
                    {group.modules.map(
                      (
                        moduleDef,
                      ) => (
                        <MenuTile
                          key={
                            moduleDef.key
                          }
                          moduleDef={
                            moduleDef
                          }
                        />
                      ),
                    )}
                  </div>
                </section>
              );
            },
          )}
        </div>

        {advancedGroup ? (
          <section
            className={
              styles.advancedSection
            }
          >
            <header
              className={
                styles.sectionHeader
              }
            >
              <span
                className={
                  styles.advancedIcon
                }
              >
                <Sparkles
                  size={20}
                  strokeWidth={1.9}
                />
              </span>

              <div
                className={
                  styles.sectionCopy
                }
              >
                <h2>
                  {
                    advancedGroup.label
                  }
                </h2>

                <p>
                  {
                    advancedGroup.description
                  }
                </p>
              </div>

              <span
                className={
                  styles.menuCount
                }
              >
                {
                  advancedGroup.modules
                    .length
                }{" "}
                menu
              </span>
            </header>

            <div
              className={
                styles.tileGrid
              }
            >
              {advancedGroup.modules.map(
                (
                  moduleDef,
                ) => (
                  <MenuTile
                    key={
                      moduleDef.key
                    }
                    moduleDef={
                      moduleDef
                    }
                  />
                ),
              )}
            </div>
          </section>
        ) : null}

        <section
          className={
            styles.themeSection
          }
        >
          <div
            className={
              styles.themeHeading
            }
          >
            <span
              className={
                styles.themeIcon
              }
            >
              <Settings
                size={20}
                strokeWidth={1.9}
              />
            </span>

            <div>
              <h2
                className={
                  styles.sectionTitle
                }
              >
                Tampilan
              </h2>

              <p
                className={
                  styles.sectionNote
                }
              >
                Pilih tema SIGNOVA
                di perangkat ini.
              </p>
            </div>
          </div>

          <ThemeSelector />
        </section>

        <button
          type="button"
          className={
            styles.logout
          }
          onClick={
            () =>
              void handleLogout()
          }
          disabled={
            loggingOut
          }
        >
          <LogOut
            size={19}
          />

          {loggingOut
            ? "Sedang keluar..."
            : "Keluar dari SIGNOVA"}
        </button>
      </section>
    </TenantShell>
  );
}
