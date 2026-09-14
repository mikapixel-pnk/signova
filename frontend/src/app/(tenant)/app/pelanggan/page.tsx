"use client";

import {
  Building2,
  Mail,
  Phone,
  Plus,
  Search,
  UserRound,
} from "lucide-react";

import Link from "next/link";

import {
  useEffect,
  useMemo,
  useState,
} from "react";

import {
  ActionFeedback,
} from "@/components/feedback/action-feedback";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  ModuleHero,
} from "@/components/module/module-hero";

import {
  ModuleFooterCard,
} from "@/components/module/module-footer-card";

import {
  apiErrorMessage,
  apiRequestId,
} from "@/lib/api/error-message";

import {
  listCustomers,
} from "@/lib/customer/service";

import {
  customerStatusLabel,
  customerTypeLabel,
} from "@/lib/customer/labels";

import {
  getModule,
} from "@/lib/module/registry";

import type {
  Customer,
  CustomerStatus,
} from "@/types/customer";

import styles from "./customers.module.css";

type StatusFilter =
  | "ALL"
  | CustomerStatus;

export default function CustomersPage() {
  const customerModule =
    getModule(
      "customers",
    );

  const CustomerModuleIcon =
    customerModule.icon;

  const [
    customers,
    setCustomers,
  ] = useState<Customer[]>([]);

  const [
    search,
    setSearch,
  ] = useState("");

  const [
    status,
    setStatus,
  ] = useState<StatusFilter>(
    "ALL",
  );

  const [
    loading,
    setLoading,
  ] = useState(true);

  const [
    error,
    setError,
  ] = useState<unknown>(null);

  const [
    total,
    setTotal,
  ] = useState(0);

  const effectiveStatus =
    useMemo(
      () =>
        status === "ALL"
          ? undefined
          : status,
      [status],
    );

  useEffect(() => {
    let cancelled = false;

    const timer =
      window.setTimeout(
        async () => {
          setLoading(true);
          setError(null);

          try {
            const response =
              await listCustomers({
                search,
                status:
                  effectiveStatus,
                page: 1,
              });

            if (cancelled) {
              return;
            }

            setCustomers(
              response.data,
            );

            setTotal(
              response.meta.total,
            );
          } catch (caught) {
            if (!cancelled) {
              setError(caught);
            }
          } finally {
            if (!cancelled) {
              setLoading(false);
            }
          }
        },
        280,
      );

    return () => {
      cancelled = true;
      window.clearTimeout(
        timer,
      );
    };
  }, [
    search,
    effectiveStatus,
  ]);

  return (
    <TenantShell>
      <section
        className={styles.page}
      >
        <ModuleHero
          eyebrow="Master Data"
          title={customerModule.label}
          description={customerModule.description}
          icon={CustomerModuleIcon}
          tone={
            customerModule.tone === "amber"
              ? "blue"
              : customerModule.tone
          }
          insightTitle={
            customerModule.insight.title
          }
          insightDescription={
            customerModule.insight.description
          }
          actions={
            <Link
              href="/app/pelanggan/tambah"
              className={styles.primaryAction}
            >
              <Plus size={18} />
              Tambah Pelanggan
            </Link>
          }
        />

        <section
          className={styles.toolbar}
        >
          <label
            className={
              styles.searchBox
            }
          >
            <Search
              size={18}
              strokeWidth={1.9}
            />

            <input
              type="search"
              value={search}
              onChange={(event) =>
                setSearch(
                  event.target.value,
                )
              }
              placeholder="Cari nama, kode, atau kontak..."
              aria-label="Cari pelanggan"
            />
          </label>

          <div
            className={
              styles.filters
            }
          >
            {(
              [
                [
                  "ALL",
                  "Semua",
                ],
                [
                  "ACTIVE",
                  "Aktif",
                ],
                [
                  "INACTIVE",
                  "Nonaktif",
                ],
              ] as const
            ).map(
              ([
                value,
                label,
              ]) => (
                <button
                  key={value}
                  type="button"
                  className={
                    status ===
                    value
                      ? styles.filterActive
                      : styles.filter
                  }
                  onClick={() =>
                    setStatus(
                      value,
                    )
                  }
                >
                  {label}
                </button>
              ),
            )}
          </div>
        </section>

        <div
          className={
            styles.resultMeta
          }
        >
          <span>
            {loading
              ? "Memuat pelanggan..."
              : `${total} pelanggan`}
          </span>
        </div>

        {error ? (
          <ActionFeedback
            tone="error"
            title="Pelanggan belum dapat dimuat"
            message={apiErrorMessage(
              error,
            )}
            requestId={
              apiRequestId(
                error,
              )
            }
          />
        ) : null}

        {loading ? (
          <div
            className={
              styles.skeletonList
            }
            aria-hidden="true"
          >
            {Array.from({
              length: 5,
            }).map(
              (_, index) => (
                <div
                  key={index}
                  className={
                    styles.skeletonCard
                  }
                />
              ),
            )}
          </div>
        ) : null}

        {!loading &&
        !error &&
        customers.length ===
          0 ? (
          <section
            className={
              styles.emptyState
            }
          >
            <span
              className={
                styles.emptyIcon
              }
            >
              <UserRound
                size={27}
              />
            </span>

            <h2>
              Belum ada pelanggan
            </h2>

            <p>
              Tambahkan pelanggan pertama agar
              bisa digunakan saat membuat
              penawaran dan tagihan.
            </p>

            <Link
              href="/app/pelanggan/tambah"
              className={
                styles.primaryAction
              }
            >
              <Plus size={18} />
              Tambah Pelanggan
            </Link>
          </section>
        ) : null}

        {!loading &&
        !error &&
        customers.length >
          0 ? (
          <>
            <div
              className={
                styles.mobileList
              }
            >
              {customers.map(
                (customer) => {
                  const TypeIcon =
                    customer.type ===
                    "COMPANY"
                      ? Building2
                      : UserRound;

                  return (
                    <Link
                      key={
                        customer.id
                      }
                      href={
                        `/app/pelanggan/${customer.id}`
                      }
                      className={
                        styles.customerCard
                      }
                    >
                      <span
                        className={
                          styles.customerIcon
                        }
                      >
                        <TypeIcon
                          size={21}
                        />
                      </span>

                      <div
                        className={
                          styles.customerMain
                        }
                      >
                        <div
                          className={
                            styles.customerTop
                          }
                        >
                          <strong>
                            {
                              customer.name
                            }
                          </strong>

                          <span
                            className={
                              customer.status ===
                              "ACTIVE"
                                ? styles.statusActive
                                : styles.statusInactive
                            }
                          >
                            {customerStatusLabel(
                              customer.status,
                            )}
                          </span>
                        </div>

                        <span
                          className={
                            styles.customerType
                          }
                        >
                          {customerTypeLabel(
                            customer.type,
                          )}
                          {customer.code
                            ? ` • ${customer.code}`
                            : ""}
                        </span>

                        <div
                          className={
                            styles.contactRow
                          }
                        >
                          {customer.phone ? (
                            <span>
                              <Phone
                                size={14}
                              />
                              {
                                customer.phone
                              }
                            </span>
                          ) : null}

                          {customer.email ? (
                            <span>
                              <Mail
                                size={14}
                              />
                              {
                                customer.email
                              }
                            </span>
                          ) : null}
                        </div>
                      </div>
                    </Link>
                  );
                },
              )}
            </div>

            <div
              className={
                styles.desktopTableWrap
              }
            >
              <table
                className={
                  styles.table
                }
              >
                <thead>
                  <tr>
                    <th>Pelanggan</th>
                    <th>Kontak</th>
                    <th>Tipe</th>
                    <th>Status</th>
                    <th />
                  </tr>
                </thead>

                <tbody>
                  {customers.map(
                    (customer) => (
                      <tr
                        key={
                          customer.id
                        }
                      >
                        <td>
                          <strong>
                            {
                              customer.name
                            }
                          </strong>

                          <small>
                            {customer.code ??
                              "Tanpa kode"}
                          </small>
                        </td>

                        <td>
                          <span>
                            {customer.phone ??
                              "-"}
                          </span>

                          <small>
                            {customer.email ??
                              "-"}
                          </small>
                        </td>

                        <td>
                          {customerTypeLabel(
                            customer.type,
                          )}
                        </td>

                        <td>
                          <span
                            className={
                              customer.status ===
                              "ACTIVE"
                                ? styles.statusActive
                                : styles.statusInactive
                            }
                          >
                            {customerStatusLabel(
                              customer.status,
                            )}
                          </span>
                        </td>

                        <td>
                          <Link
                            href={
                              `/app/pelanggan/${customer.id}`
                            }
                            className={
                              styles.detailLink
                            }
                          >
                            Lihat
                          </Link>
                        </td>
                      </tr>
                    ),
                  )}
                </tbody>
              </table>
            </div>
          </>
        ) : null}

        <ModuleFooterCard
          tone={
            customerModule.tone === "amber"
              ? "blue"
              : customerModule.tone
          }
          title={
            customerModule.footer?.title ??
            customerModule.insight.title
          }
          description={
            customerModule.footer?.description ??
            customerModule.insight.description
          }
        />
      </section>
    </TenantShell>
  );
}
