"use client";

import {
  Building2,
  ChevronRight,
  ShieldCheck,
} from "lucide-react";
import {
  useRouter,
} from "next/navigation";
import {
  useEffect,
  useState,
} from "react";

import {
  AuthShell,
} from "@/components/layout/auth-shell";
import {
  Brand,
} from "@/components/ui/brand";
import {
  Button,
} from "@/components/ui/button";
import {
  Card,
} from "@/components/ui/card";

import {
  clearPendingAccessSelection,
  getPendingAccessSelection,
  type PendingAccessSelection,
} from "@/lib/auth/access-selection";
import {
  clearSelectedContext,
  setSelectedContext,
} from "@/lib/auth/active-context";
import {
  clearAccessToken,
  getAccessToken,
} from "@/lib/auth/session";

import styles from "./select-context.module.css";

export default function SelectContextPage() {
  const router = useRouter();

  const [access] =
    useState<
      PendingAccessSelection
      | null
    >(
      () =>
        getPendingAccessSelection()
    );

  useEffect(() => {
    const token =
      getAccessToken();

    if (
      ! token
      || ! access
    ) {
      router.replace(
        "/login",
      );
    }
  }, [
    access,
    router,
  ]);

  function openPlatform() {
    setSelectedContext({
      type: "PLATFORM",
      tenantId: null,
    });

    clearPendingAccessSelection();

    router.replace(
      "/admin",
    );
  }

  function openTenant(
    tenantId: string,
  ) {
    /*
     * Tenant aktif belum disimpan
     * permanen di tahap ini.
     *
     * A.2.9 akan mengikat tenantId
     * ke bootstrap/session context.
     */
    setSelectedContext({
      type: "TENANT",
      tenantId,
    });

    clearPendingAccessSelection();

    router.replace(
      "/app",
    );
  }

  function backToLogin() {
    clearPendingAccessSelection();
    clearAccessToken();

    clearSelectedContext();

    router.replace(
      "/login",
    );
  }

  if (! access) {
    return (
      <AuthShell>
        <Card
          className={
            styles.card
          }
        >
          <p
            className={
              styles.description
            }
          >
            Menyiapkan akses Anda...
          </p>
        </Card>
      </AuthShell>
    );
  }

  return (
    <AuthShell>
      <Card className={styles.card}>
        <div className={styles.cardBrand}>
          <Brand />
        </div>
        <header
          className={
            styles.header
          }
        >
          <h1
            className={
              styles.title
            }
          >
            Pilih Akses
          </h1>

          <p
            className={
              styles.description
            }
          >
            Pilih bagian yang ingin
            Anda buka.
          </p>
        </header>

        {access.tenants.length > 0 ? (
          <section
            className={
              styles.section
            }
          >
            <h2
              className={
                styles.sectionTitle
              }
            >
              Usaha Anda
            </h2>

            {access.tenants.map(
              (tenant) => (
                <button
                  key={tenant.id}
                  type="button"
                  className={
                    styles.option
                  }
                  onClick={() =>
                    openTenant(
                      tenant.id,
                    )
                  }
                >
                  <span
                    className={
                      styles.icon
                    }
                  >
                    <Building2
                      size={21}
                    />
                  </span>

                  <span
                    className={
                      styles.body
                    }
                  >
                    <span
                      className={
                        styles.name
                      }
                    >
                      {tenant.name}
                    </span>

                    <span
                      className={
                        styles.detail
                      }
                    >
                      Kelola pelanggan,
                      tagihan,
                      pembayaran,
                      dan kegiatan
                      usaha.
                    </span>
                  </span>

                  <ChevronRight
                    size={19}
                    className={
                      styles.chevron
                    }
                  />
                </button>
              ),
            )}
          </section>
        ) : null}

        {access.platform.available ? (
          <section
            className={
              styles.section
            }
          >
            <h2
              className={
                styles.sectionTitle
              }
            >
              Pengelolaan SIGNOVA
            </h2>

            <button
              type="button"
              className={
                styles.option
              }
              onClick={
                openPlatform
              }
            >
              <span
                className={
                  styles.icon
                }
              >
                <ShieldCheck
                  size={21}
                />
              </span>

              <span
                className={
                  styles.body
                }
              >
                <span
                  className={
                    styles.name
                  }
                >
                  Kelola SIGNOVA
                </span>

                <span
                  className={
                    styles.detail
                  }
                >
                  Kelola usaha
                  pelanggan, paket,
                  langganan, dan
                  pengaturan sistem.
                </span>
              </span>

              <ChevronRight
                size={19}
                className={
                  styles.chevron
                }
              />
            </button>
          </section>
        ) : null}

        <div
          className={
            styles.notice
          }
        >
          Akses yang tersedia
          mengikuti hak pengguna
          Anda.
        </div>

        <div
          className={
            styles.actions
          }
        >
          <Button
            type="button"
            variant="ghost"
            fullWidth
            onClick={
              backToLogin
            }
          >
            Gunakan akun lain
          </Button>
        </div>
      </Card>
    </AuthShell>
  );
}
