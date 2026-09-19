"use client";

import Link from "next/link";

import {
  usePathname,
  useRouter,
} from "next/navigation";

import {
  BadgeDollarSign,
  Bell,
  Building2,
  Gauge,
  LayoutDashboard,
  LogOut,
  Menu,
  PlugZap,
  RefreshCcw,
  ScrollText,
  Settings,
  ShieldAlert,
  ShieldCheck,
  ShoppingCart,
  X,
  type LucideIcon,
} from "lucide-react";

import {
  useMemo,
  useState,
  type ReactNode,
} from "react";

import {
  Brand,
} from "@/components/ui/brand";

import {
  ThemeQuickToggle,
} from "@/components/theme/theme-quick-toggle";

import {
  logout,
} from "@/lib/auth/logout";

import {
  platformAdminNavigation,
  platformNavigationItemForPath,
  type PlatformAdminIconKey,
} from "@/lib/platform-admin/navigation";

import {
  usePlatformAccess,
} from "./platform-session-gate";

import styles from "./platform-admin.module.css";

const icons:
  Record<
    PlatformAdminIconKey,
    LucideIcon
  > = {
    dashboard:
      LayoutDashboard,
    tenant:
      Building2,
    plan:
      BadgeDollarSign,
    subscription:
      RefreshCcw,
    order:
      ShoppingCart,
    usage:
      Gauge,
    integration:
      PlugZap,
    notification:
      Bell,
    audit:
      ScrollText,
    settings:
      Settings,
  };

type PlatformAdminShellProps = {
  children: ReactNode;
};

export function PlatformAdminShell({
  children,
}: PlatformAdminShellProps) {
  const pathname =
    usePathname();

  const router =
    useRouter();

  const {
    user,
    capabilities,
  } =
    usePlatformAccess();

  const [
    menuOpen,
    setMenuOpen,
  ] =
    useState(false);

  const [
    loggingOut,
    setLoggingOut,
  ] =
    useState(false);

  const visibleNavigation =
    useMemo(
      () =>
        platformAdminNavigation.filter(
          (item) =>
            capabilities.includes(
              item.capability,
            ),
        ),
      [capabilities],
    );

  const currentItem =
    platformNavigationItemForPath(
      pathname,
    );

  const canViewCurrent =
    !currentItem ||
    capabilities.includes(
      currentItem.capability,
    );

  async function handleLogout() {
    if (loggingOut) {
      return;
    }

    setLoggingOut(true);

    try {
      await logout();
    } finally {
      router.replace("/login");
    }
  }

  if (!canViewCurrent) {
    return (
      <main
        className={
          styles.gateState
        }
      >
        <div
          className={
            styles.gateCard
          }
        >
          <ShieldAlert
            size={34}
          />

          <span
            className={
              styles.eyebrow
            }
          >
            Akses dibatasi
          </span>

          <h1>
            Hak akses tidak tersedia
          </h1>

          <p>
            Akun Anda tidak memiliki
            capability untuk membuka
            bagian ini.
          </p>

          <Link
            href="/signova/admin"
            className={
              styles.primaryButton
            }
          >
            Kembali ke Beranda
          </Link>
        </div>
      </main>
    );
  }

  return (
    <div
      className={
        styles.shell
      }
    >
      <header
        className={
          styles.mobileHeader
        }
      >
        <button
          type="button"
          className={
            styles.iconButton
          }
          aria-label="Buka menu"
          onClick={() =>
            setMenuOpen(true)
          }
        >
          <Menu size={21} />
        </button>

        <Brand />

        <span
          className={
            styles.mobilePlatformMark
          }
        >
          <ShieldCheck
            size={18}
          />
        </span>
      </header>

      {menuOpen ? (
        <button
          type="button"
          className={
            styles.backdrop
          }
          aria-label="Tutup menu"
          onClick={() =>
            setMenuOpen(false)
          }
        />
      ) : null}

      <aside
        className={
          menuOpen
            ? styles.sidebarOpen
            : styles.sidebar
        }
      >
        <div
          className={
            styles.sidebarTop
          }
        >
          <div
            className={
              styles.desktopBrand
            }
          >
            <Brand />
          </div>

          <button
            type="button"
            className={
              styles.closeButton
            }
            aria-label="Tutup menu"
            onClick={() =>
              setMenuOpen(false)
            }
          >
            <X size={20} />
          </button>

          <div
            className={
              styles.platformBadge
            }
          >
            <ShieldCheck
              size={16}
            />

            <span>
              Super Admin
            </span>
          </div>
        </div>

        <nav
          className={
            styles.navigation
          }
          aria-label="Menu Super Admin"
        >
          {visibleNavigation.map(
            (item) => {
              const Icon =
                icons[item.icon];

              const active =
                pathname ===
                  item.href ||
                (
                  item.href !==
                    "/signova/admin" &&
                  pathname.startsWith(
                    `${item.href}/`,
                  )
                );

              return (
                <Link
                  key={
                    item.href
                  }
                  href={
                    item.href
                  }
                  className={
                    active
                      ? styles.navLinkActive
                      : styles.navLink
                  }
                  onClick={() =>
                    setMenuOpen(false)
                  }
                >
                  <Icon
                    size={19}
                    strokeWidth={1.9}
                  />

                  <span>
                    {item.label}
                  </span>
                </Link>
              );
            },
          )}
        </nav>

        <div
          className={
            styles.sidebarFooter
          }
        >
          <div
            className={
              styles.userCard
            }
          >
            <span
              className={
                styles.userName
              }
            >
              {user.name}
            </span>

            <span
              className={
                styles.userEmail
              }
            >
              {user.email}
            </span>
          </div>

          <ThemeQuickToggle />

          <button
            type="button"
            className={
              styles.logoutButton
            }
            disabled={
              loggingOut
            }
            onClick={() =>
              void handleLogout()
            }
          >
            <LogOut
              size={18}
            />

            {loggingOut
              ? "Keluar…"
              : "Keluar"}
          </button>
        </div>
      </aside>

      <div
        className={
          styles.workspace
        }
      >
        <header
          className={
            styles.desktopHeader
          }
        >
          <div>
            <span
              className={
                styles.headerEyebrow
              }
            >
              SIGNOVA Platform
            </span>

            <h1
              className={
                styles.headerTitle
              }
            >
              {currentItem
                ?.label ??
                "Super Admin"}
            </h1>
          </div>

          <div
            className={
              styles.headerActions
            }
          >
            <ThemeQuickToggle />

            <div
              className={
                styles.headerUser
              }
            >
              {user.name}
            </div>
          </div>
        </header>

        <main
          className={
            styles.content
          }
        >
          {children}
        </main>
      </div>
    </div>
  );
}
