"use client";

import {
  Building2,
  Mail,
  MapPin,
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
  apiErrorMessage,
  apiRequestId,
} from "@/lib/api/error-message";

import {
  getActiveCapabilities,
} from "@/lib/auth/active-capabilities-service";

import {
  getModule,
} from "@/lib/module/registry";

import {
  listSuppliers,
} from "@/lib/supplier/service";

import {
  supplierStatusLabel,
} from "@/lib/supplier/labels";

import type {
  Supplier,
  SupplierStatus,
} from "@/types/supplier";

import styles from "./suppliers.module.css";

type StatusFilter =
  | "ALL"
  | SupplierStatus;

export default function SuppliersPage() {
  const supplierModule =
    getModule(
      "suppliers",
    );

  const SupplierIcon =
    supplierModule.icon;

  const [
    suppliers,
    setSuppliers,
  ] = useState<Supplier[]>([]);

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

  const [
    canCreate,
    setCanCreate,
  ] = useState(false);

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

    async function loadCapabilities() {
      try {
        const response =
          await getActiveCapabilities();

        if (!cancelled) {
          setCanCreate(
            response.data
              .capability_codes
              .includes(
                "supplier.create",
              ),
          );
        }
      } catch {
        if (!cancelled) {
          setCanCreate(false);
        }
      }
    }

    void loadCapabilities();

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    let cancelled = false;

    const timer =
      window.setTimeout(
        async () => {
          setLoading(true);
          setError(null);

          try {
            const response =
              await listSuppliers({
                search,
                status:
                  effectiveStatus,
                page: 1,
              });

            if (cancelled) {
              return;
            }

            setSuppliers(
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
          eyebrow="Fitur Lanjutan"
          title={
            supplierModule.label
          }
          description={
            supplierModule.description
          }
          icon={SupplierIcon}
          tone={
            supplierModule.tone
          }
          insightTitle={
            supplierModule.insight
              .title
          }
          insightDescription={
            supplierModule.insight
              .description
          }
          actions={
            canCreate ? (
              <Link
                href="/app/pemasok/tambah"
                className={
                  styles.primaryAction
                }
              >
                <Plus size={18} />
                Tambah Pemasok
              </Link>
            ) : undefined
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
              placeholder="Cari nama, kode, kontak, telepon, atau email..."
              aria-label="Cari pemasok"
            />
          </label>

          <div
            className={
              styles.filters
            }
          >
            {(
              [
                ["ALL", "Semua"],
                ["ACTIVE", "Aktif"],
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
                    status === value
                      ? styles.filterActive
                      : styles.filter
                  }
                  onClick={() =>
                    setStatus(value)
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
          {loading
            ? "Memuat pemasok..."
            : `${total} pemasok`}
        </div>

        {error ? (
          <ActionFeedback
            tone="error"
            title="Pemasok belum dapat dimuat"
            message={
              apiErrorMessage(
                error,
                "Data pemasok belum berhasil dimuat.",
              )
            }
            requestId={
              apiRequestId(
                error,
              )
            }
          />
        ) : null}

        {!error &&
        !loading &&
        suppliers.length === 0 ? (
          <section
            className={
              styles.empty
            }
          >
            <Building2
              size={32}
              strokeWidth={1.7}
            />

            <h2>
              Belum ada pemasok
            </h2>

            <p>
              Simpan data pemasok
              agar dapat digunakan
              kembali saat proses
              pembelian dikembangkan.
            </p>

            {canCreate ? (
              <Link
                href="/app/pemasok/tambah"
                className={
                  styles.primaryAction
                }
              >
                <Plus size={18} />
                Tambah Pemasok
              </Link>
            ) : null}
          </section>
        ) : null}

        {!error &&
        suppliers.length > 0 ? (
          <div
            className={
              styles.grid
            }
          >
            {suppliers.map(
              (supplier) => (
                <Link
                  key={supplier.id}
                  href={
                    `/app/pemasok/${supplier.id}`
                  }
                  className={
                    styles.card
                  }
                >
                  <div
                    className={
                      styles.cardHeader
                    }
                  >
                    <span
                      className={
                        styles.cardIcon
                      }
                    >
                      <Building2
                        size={20}
                      />
                    </span>

                    <div
                      className={
                        styles.cardTitle
                      }
                    >
                      <strong>
                        {supplier.name}
                      </strong>

                      <span>
                        {supplier.code ??
                          "Tanpa kode"}
                      </span>
                    </div>

                    <span
                      className={
                        supplier.status ===
                        "ACTIVE"
                          ? styles.statusActive
                          : styles.statusInactive
                      }
                    >
                      {supplierStatusLabel(
                        supplier.status,
                      )}
                    </span>
                  </div>

                  <div
                    className={
                      styles.cardDetails
                    }
                  >
                    <span>
                      <UserRound
                        size={15}
                      />
                      {supplier.contact_name ??
                        "Kontak belum diisi"}
                    </span>

                    <span>
                      <Phone
                        size={15}
                      />
                      {supplier.phone ??
                        "Telepon belum diisi"}
                    </span>

                    <span>
                      <Mail
                        size={15}
                      />
                      {supplier.email ??
                        "Email belum diisi"}
                    </span>

                    <span>
                      <MapPin
                        size={15}
                      />
                      {[
                        supplier.city,
                        supplier.province,
                      ]
                        .filter(Boolean)
                        .join(", ") ||
                        "Lokasi belum diisi"}
                    </span>
                  </div>
                </Link>
              ),
            )}
          </div>
        ) : null}
      </section>
    </TenantShell>
  );
}
