"use client";

import {
  Bell,
  ChevronDown,
  FileText,
  Home,
  LogOut,
  Settings,
  WalletCards,
} from "lucide-react";

import Link from "next/link";

import {
  usePathname,
  useRouter,
  useSearchParams,
} from "next/navigation";

import {
  Suspense,
  useCallback,
  useEffect,
  useState,
} from "react";

import {
  TenantSessionGate,
} from "@/components/auth/tenant-session-gate";

import {
  OfflineRouteWarmup,
} from "@/components/system/offline-route-warmup";

import {
  SyncStatus,
} from "@/components/system/sync-status";

import {
  Brand,
} from "@/components/ui/brand";

import {
  ThemeQuickToggle,
} from "@/components/theme/theme-quick-toggle";

import {
  getAuthContext,
} from "@/lib/auth/context";

import {
  getActiveCapabilities,
} from "@/lib/auth/active-capabilities-service";

import {
  logout,
} from "@/lib/auth/logout";

import {
  getNavigationModel,
} from "@/lib/module/navigation";

import {
  modulePlanLabel,
} from "@/lib/module/registry";

import styles from "./tenant-shell.module.css";

type Tone =
  | "blue"
  | "cyan"
  | "teal"
  | "green"
  | "violet"
  | "rose"
  | "amber";

type NavigationItem = {
  label: string;
  href: string;
  tone: Tone;

  badge?:
    | "Business"
    | "Pro";

  icon: React.ComponentType<{
    size?: number;
    strokeWidth?: number;
  }>;
};

type NavigationGroup = {
  id: string;
  label: string;
  icon: React.ComponentType<{
    size?: number;
    strokeWidth?: number;
  }>;
  tone: Tone;
  defaultOpen?: boolean;
  items: NavigationItem[];
};

type TenantShellProps = {
  children: React.ReactNode;
};

function buildNavigationGroups(
  capabilityCodes:
    readonly string[],
): NavigationGroup[] {
  return getNavigationModel(
    "desktop",
    capabilityCodes,
  ).map(
    (group) => ({
      id: group.key,
      label: group.label,
      icon: group.icon,
      tone: group.tone,

      defaultOpen:
        group.defaultOpen,

      items:
        group.modules.map(
          (moduleDef) => ({
            label:
              moduleDef.shortLabel ??
              moduleDef.label,

            href:
              moduleDef.href,

            tone:
              moduleDef.tone,

            icon:
              moduleDef.icon,

            badge:
              modulePlanLabel(
                moduleDef.plan,
              ) ??
              undefined,
          }),
        ),
    }),
  );
}

function cleanHref(
  href: string,
): string {
  return href.split("?")[0];
}

function settingsSectionFromHref(
  href: string,
): string | null {
  const query =
    href.split("?")[1];

  if (!query) {
    return null;
  }

  return new URLSearchParams(
    query,
  ).get("bagian");
}

function isActiveRoute(
  pathname: string,
  href: string,
  settingsSection:
    string | null = null,
): boolean {
  const clean =
    cleanHref(href);

  if (clean === "/app") {
    return pathname === "/app";
  }

  if (
    clean ===
      "/app/pengaturan" &&
    pathname ===
      "/app/pengaturan"
  ) {
    const targetSection =
      settingsSectionFromHref(
        href,
      );

    if (targetSection) {
      return (
        targetSection ===
        settingsSection
      );
    }
  }

  return (
    pathname === clean ||
    pathname.startsWith(
      `${clean}/`,
    )
  );
}

function groupIsActive(
  pathname: string,
  group: NavigationGroup,
  settingsSection:
    string | null,
): boolean {
  return group.items.some(
    (item) =>
      isActiveRoute(
        pathname,
        item.href,
        settingsSection,
      ),
  );
}

function settingsSubgroupLabel(
  href: string,
): string | null {
  const section =
    settingsSectionFromHref(
      href,
    );

  switch (section) {
    case "bisnis":
    case "dokumen":
      return "Usaha & Dokumen";

    case "keuangan":
      return "Keuangan & Pembayaran";

    case "profil":
    case "tim":
      return "Akun & Akses";

    case "paket":
    case "integrasi":
      return "Sistem & Langganan";

    default:
      return null;
  }
}

function NavigationLink({
  item,
  pathname,
  settingsSection,
}: {
  item: NavigationItem;
  pathname: string;
  settingsSection:
    string | null;
}) {
  const Icon =
    item.icon;

  const active =
    isActiveRoute(
      pathname,
      item.href,
      settingsSection,
    );

  return (
    <Link
      href={item.href}
      data-tone={item.tone}
      className={
        active
          ? styles.sidebarLinkActive
          : styles.sidebarLink
      }
    >
      <Icon
        size={18}
        strokeWidth={1.9}
      />

      <span
        className={
          styles.sidebarLinkLabel
        }
      >
        {item.label}
      </span>

      {item.badge ? (
        <span
          className={
            styles.planBadge
          }
        >
          {item.badge}
        </span>
      ) : null}
    </Link>
  );
}

function AccordionGroup({
  group,
  pathname,
  settingsSection,
}: {
  group: NavigationGroup;
  pathname: string;
  settingsSection:
    string | null;
}) {
  const initiallyOpen =
    groupIsActive(
      pathname,
      group,
      settingsSection,
    );

  const [
    open,
    setOpen,
  ] = useState(
    initiallyOpen ||
      group.defaultOpen ===
        true,
  );

  const Icon =
    group.icon;

  return (
    <section
      className={
        styles.accordionGroup
      }
      data-tone={
        group.tone
      }
    >
      <button
        type="button"
        className={
          open
            ? styles.accordionTriggerOpen
            : styles.accordionTrigger
        }
        aria-expanded={open}
        onClick={() =>
          setOpen(
            (current) =>
              !current,
          )
        }
      >
        <span
          className={
            styles.accordionIcon
          }
        >
          <Icon
            size={18}
            strokeWidth={1.9}
          />
        </span>

        <span>
          {group.label}
        </span>

        <ChevronDown
          size={16}
          className={
            open
              ? styles.chevronOpen
              : styles.chevron
          }
        />
      </button>

      {open ? (
        <div
          className={
            styles.accordionContent
          }
        >
          {group.items.map(
            (
              item,
              index,
            ) => {
              const subgroup =
                group.id ===
                "pengaturan"
                  ? settingsSubgroupLabel(
                      item.href,
                    )
                  : null;

              const previousSubgroup =
                group.id ===
                  "pengaturan" &&
                index > 0
                  ? settingsSubgroupLabel(
                      group.items[
                        index - 1
                      ].href,
                    )
                  : null;

              const showSubgroup =
                subgroup !== null &&
                subgroup !==
                  previousSubgroup;

              return (
                <div
                  key={
                    `${group.id}-${item.label}`
                  }
                  className={
                    styles.accordionItemGroup
                  }
                >
                  {showSubgroup ? (
                    <div
                      className={
                        styles.settingsSubgroupLabel
                      }
                    >
                      {subgroup}
                    </div>
                  ) : null}

                  <NavigationLink
                    item={item}
                    pathname={
                      pathname
                    }
                    settingsSection={
                      settingsSection
                    }
                  />
                </div>
              );
            },
          )}
        </div>
      ) : null}
    </section>
  );
}

function SidebarNavigationGroups({
  pathname,
  groups,
}: {
  pathname: string;
  groups: NavigationGroup[];
}) {
  const searchParams =
    useSearchParams();

  const settingsSection =
    searchParams.get(
      "bagian",
    );

  return (
    <>
      {groups.map(
        (group) => (
          <AccordionGroup
            key={
              group.id
            }
            group={
              group
            }
            pathname={
              pathname
            }
            settingsSection={
              settingsSection
            }
          />
        ),
      )}
    </>
  );
}

export function TenantShell({
  children,
}: TenantShellProps) {
  const pathname =
    usePathname();

  const router =
    useRouter();

  const [
    loggingOut,
    setLoggingOut,
  ] = useState(false);

  const [
    activeBusinessName,
    setActiveBusinessName,
  ] = useState(
    "Memuat usaha...",
  );

  const [
    userName,
    setUserName,
  ] = useState(
    "Pengguna",
  );

  const [
    visibleNavigationGroups,
    setVisibleNavigationGroups,
  ] = useState<
    NavigationGroup[]
  >(
    () =>
      buildNavigationGroups(
        [],
      ),
  );

  useEffect(() => {
    let cancelled =
      false;

    window.queueMicrotask(
      () => {
        void getAuthContext()
          .then(
            (response) => {
              if (
                cancelled
              ) {
                return;
              }

              const name =
                response.data.user
                  .name?.trim();

              if (name) {
                setUserName(
                  name,
                );
              }
            },
          )
          .catch(() => {
            // Session gate menangani auth.
          });
      },
    );

    return () => {
      cancelled = true;
    };
  }, []);

  const refreshNavigationCapabilities =
    useCallback(
      async () => {
        try {
          const response =
            await getActiveCapabilities();

          setVisibleNavigationGroups(
            buildNavigationGroups(
              response.data
                .capability_codes,
            ),
          );
        } catch {
          /*
           * Backend tetap authority final.
           * Jika capability belum dapat dimuat,
           * menu protected tidak ditampilkan.
           */
          setVisibleNavigationGroups(
            buildNavigationGroups(
              [],
            ),
          );
        }
      },
      [],
    );

  const handleBusinessResolved =
    useCallback(
      (businessName: string) => {
        setActiveBusinessName(
          businessName,
        );

        void refreshNavigationCapabilities();
      },
      [
        refreshNavigationCapabilities,
      ],
    );

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

  const initial =
    userName
      .trim()
      .charAt(0)
      .toUpperCase() ||
    "U";

  return (
    <div className={styles.shell}>
      <OfflineRouteWarmup />

      <aside className={styles.sidebar}>
        <div
          className={
            styles.sidebarBrand
          }
        >
          <Brand />
        </div>

        <nav
          className={
            styles.sidebarNavigation
          }
          aria-label="Menu SIGNOVA"
        >
          <NavigationLink
            pathname={
              pathname
            }
            settingsSection={
              null
            }
            item={{
              label: "Beranda",
              href: "/app",
              tone: "cyan",
              icon: Home,
            }}
          />

          <Suspense
            fallback={
              null
            }
          >
            <SidebarNavigationGroups
              pathname={
                pathname
              }
              groups={
                visibleNavigationGroups
              }
            />
          </Suspense>
        </nav>

        <div
          className={
            styles.sidebarFooter
          }
        >
          <div
            className={
              styles.sidebarSync
            }
          >
            <SyncStatus />
          </div>

          <button
            className={
              styles.sidebarLogout
            }
            type="button"
            disabled={
              loggingOut
            }
            onClick={
              () =>
                void handleLogout()
            }
          >
            <LogOut
              size={18}
              strokeWidth={1.9}
            />

            <span>
              {loggingOut
                ? "Sedang keluar..."
                : "Keluar"}
            </span>
          </button>
        </div>
      </aside>

      <div className={styles.content}>
        <header
          className={
            styles.header
          }
        >
          <div
            className={
              styles.mobileBrand
            }
          >
            <Brand />
          </div>

          <div
            className={
              styles.headerContext
            }
          >
            <span
              className={
                styles.headerLabel
              }
            >
              Usaha aktif
            </span>

            <strong
              className={
                styles.headerBusiness
              }
            >
              {activeBusinessName}
            </strong>
          </div>

          <div
            className={
              styles.headerActions
            }
          >
            <SyncStatus />

            <ThemeQuickToggle />

            <button
              className={
                styles.iconButton
              }
              type="button"
              aria-label="Notifikasi"
            >
              <Bell
                size={20}
                strokeWidth={1.9}
              />

              <span
                className={
                  styles.notificationDot
                }
              />
            </button>

            <Link
              href="/app/pengaturan?bagian=profil"
              className={
                styles.userMenu
              }
              aria-label="Buka profil saya"
            >
              <span
                className={
                  styles.userAvatar
                }
              >
                {initial}
              </span>

              <span
                className={
                  styles.userCopy
                }
              >
                <small>
                  Pengguna
                </small>

                <strong>
                  {userName}
                </strong>
              </span>
            </Link>
          </div>
        </header>

        <main
          className={
            styles.main
          }
        >
          <TenantSessionGate
            onBusinessResolved={
              handleBusinessResolved
            }
          >
            {children}
          </TenantSessionGate>
        </main>

        <nav
          className={
            styles.bottomNavigation
          }
          aria-label="Navigasi utama"
        >
          <Link
            href="/app"
            data-tone="cyan"
            className={
              isActiveRoute(
                pathname,
                "/app",
              )
                ? styles.bottomLinkActive
                : styles.bottomLink
            }
          >
            <Home
              size={21}
              strokeWidth={1.9}
            />
            <span>Beranda</span>
          </Link>

          <Link
            href="/app/tagihan"
            data-tone="blue"
            className={
              isActiveRoute(
                pathname,
                "/app/tagihan",
              )
                ? styles.bottomLinkActive
                : styles.bottomLink
            }
          >
            <FileText
              size={21}
              strokeWidth={1.9}
            />
            <span>Tagihan</span>
          </Link>

          <Link
            href="/app/aksi"
            className={
              styles.bottomPrimary
            }
            aria-label="Tambah"
          >
            <span>+</span>
          </Link>

          <Link
            href="/app/keuangan"
            data-tone="green"
            className={
              isActiveRoute(
                pathname,
                "/app/keuangan",
              )
                ? styles.bottomLinkActive
                : styles.bottomLink
            }
          >
            <WalletCards
              size={21}
              strokeWidth={1.9}
            />
            <span>Keuangan</span>
          </Link>

          <Link
            href="/app/menu"
            data-tone="violet"
            className={
              isActiveRoute(
                pathname,
                "/app/menu",
              )
                ? styles.bottomLinkActive
                : styles.bottomLink
            }
          >
            <Settings
              size={21}
              strokeWidth={1.9}
            />
            <span>Lainnya</span>
          </Link>
        </nav>
      </div>
    </div>
  );
}
