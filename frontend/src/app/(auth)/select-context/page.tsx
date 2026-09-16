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
  setPendingAccessSelection,
  type PendingAccessSelection,
} from "@/lib/auth/access-selection";
import {
  clearSelectedContext,
  setSelectedContext,
} from "@/lib/auth/active-context";
import {
  getAuthContext,
} from "@/lib/auth/context";

import styles from "./select-context.module.css";

export default function SelectContextPage() {
  const router = useRouter();

  const [
    access,
    setAccess,
  ] = useState<
    PendingAccessSelection
    | null
  >(
    () =>
      getPendingAccessSelection()
  );

  useEffect(() => {
    if (access) {
      return;
    }

    let cancelled = false;

    void getAuthContext()
      .then((response) => {
        if (cancelled) {
          return;
        }

        setPendingAccessSelection(
          response.data.access,
        );

        setAccess(
          response.data.access,
        );
      })
      .catch(() => {
        if (!cancelled) {
          router.replace(
            "/login",
          );
        }
      });

    return () => {
      cancelled = true;
    };
  }, [
    access,
    router,
  ]);

  function openPlatform() {
    setSelectedContext({
      type: "PLATFORM",
      tenantId: null,
      businessId: null,
    });

    clearPendingAccessSelection();

    router.replace(
      "/admin",
    );
  }

  function openBusiness(
    tenantId: string,
    businessId: string,
  ) {
    setSelectedContext({
      type: "TENANT",
      tenantId,
      businessId,
    });

    clearPendingAccessSelection();

    router.replace(
      "/app",
    );
  }

  function backToLogin() {
    clearPendingAccessSelection();
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
            Pilih usaha atau akses
            SIGNOVA yang ingin Anda buka.
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

            {access.tenants.flatMap(
              (tenant) =>
                tenant.businesses.map(
                  (business) => (
                    <button
                      key={
                        `${tenant.id}-${business.id}`
                      }
                      type="button"
                      className={
                        styles.option
                      }
                      onClick={() =>
                        openBusiness(
                          tenant.id,
                          business.id,
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
                          {business.name}
                        </span>

                        <span
                          className={
                            styles.detail
                          }
                        >
                          {tenant.name}
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
