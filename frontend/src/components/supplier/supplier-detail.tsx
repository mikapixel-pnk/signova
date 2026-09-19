"use client";

import {
  ArrowLeft,
  Building2,
  Mail,
  MapPin,
  Phone,
  Pencil,
  ReceiptText,
  UserRound,
} from "lucide-react";

import Link from "next/link";

import {
  useEffect,
  useState,
} from "react";

import {
  useParams,
} from "next/navigation";

import {
  ActionFeedback,
} from "@/components/feedback/action-feedback";

import {
  ModuleHero,
} from "@/components/module/module-hero";

import {
  apiErrorMessage,
  apiRequestId,
} from "@/lib/api/error-message";

import {
  getModule,
} from "@/lib/module/registry";

import {
  getActiveCapabilities,
} from "@/lib/auth/active-capabilities-service";

import {
  getSupplier,
} from "@/lib/supplier/service";

import {
  supplierStatusLabel,
} from "@/lib/supplier/labels";

import type {
  Supplier,
} from "@/types/supplier";

import styles from "./supplier-detail.module.css";

function textOrDash(
  value:
    | string
    | null
    | undefined,
): string {
  const text =
    value?.trim();

  return text
    ? text
    : "-";
}

export function SupplierDetail() {
  const params =
    useParams<{
      id: string;
    }>();

  const supplierModule =
    getModule(
      "suppliers",
    );

  const [
    supplier,
    setSupplier,
  ] = useState<
    Supplier | null
  >(null);

  const [
    loading,
    setLoading,
  ] = useState(true);

  const [
    error,
    setError,
  ] = useState<
    string | null
  >(null);

  const [
    requestId,
    setRequestId,
  ] = useState<
    string | null
  >(null);

  const [
    canUpdate,
    setCanUpdate,
  ] = useState(false);

  useEffect(() => {
    let cancelled = false;

    async function loadCapabilities() {
      try {
        const response =
          await getActiveCapabilities();

        if (!cancelled) {
          setCanUpdate(
            response.data
              .capability_codes
              .includes(
                "supplier.update",
              ),
          );
        }
      } catch {
        if (!cancelled) {
          setCanUpdate(false);
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

    async function load() {
      setLoading(true);
      setError(null);
      setRequestId(null);

      try {
        const response =
          await getSupplier(
            params.id,
          );

        if (!cancelled) {
          setSupplier(
            response.data,
          );
        }
      } catch (loadError) {
        if (!cancelled) {
          setError(
            apiErrorMessage(
              loadError,
              "Data pemasok belum berhasil dimuat.",
            ),
          );

          setRequestId(
            apiRequestId(
              loadError,
            ),
          );
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    void load();

    return () => {
      cancelled = true;
    };
  }, [
    params.id,
  ]);

  if (loading) {
    return (
      <section
        className={styles.page}
      >
        <div
          className={
            styles.loading
          }
        >
          Memuat data pemasok...
        </div>
      </section>
    );
  }

  if (
    error ||
    !supplier
  ) {
    return (
      <section
        className={styles.page}
      >
        <Link
          href="/app/pemasok"
          className={
            styles.backLink
          }
        >
          <ArrowLeft size={17} />
          Kembali ke Pemasok
        </Link>

        <ActionFeedback
          tone="error"
          title="Data pemasok belum dapat dibuka"
          message={
            error ??
            "Pemasok tidak ditemukan."
          }
          requestId={
            requestId
          }
        />
      </section>
    );
  }

  return (
    <section
      className={styles.page}
    >
      <div
        className={
          styles.topBar
        }
      >
        <Link
          href="/app/pemasok"
          className={
            styles.backLink
          }
        >
          <ArrowLeft size={17} />
          Pemasok
        </Link>

        {canUpdate ? (
          <Link
            href={
              `/app/pemasok/${supplier.id}/ubah`
            }
            className={
              styles.editButton
            }
          >
            <Pencil size={17} />
            Ubah Pemasok
          </Link>
        ) : null}
      </div>

      <ModuleHero
        eyebrow="Data Pemasok"
        title={supplier.name}
        description={
          supplier.code
            ? `Kode pemasok: ${supplier.code}`
            : supplierModule.description
        }
        icon={Building2}
        tone={
          supplierModule.tone
        }
        insightTitle={
          supplierStatusLabel(
            supplier.status,
          )
        }
        insightDescription={
          supplier.contact_name
            ? `Kontak utama: ${supplier.contact_name}`
            : "Kontak utama belum diisi."
        }
      />

      <div
        className={styles.grid}
      >
        <section
          className={styles.card}
        >
          <header>
            <Building2 size={20} />
            <div>
              <h2>
                Informasi Pemasok
              </h2>
              <p>
                Identitas utama pemasok.
              </p>
            </div>
          </header>

          <dl>
            <div>
              <dt>Kode pemasok</dt>
              <dd>
                {textOrDash(
                  supplier.code,
                )}
              </dd>
            </div>

            <div>
              <dt>Status</dt>
              <dd>
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
              </dd>
            </div>

            <div>
              <dt>Kontak utama</dt>
              <dd>
                <UserRound size={15} />
                {textOrDash(
                  supplier.contact_name,
                )}
              </dd>
            </div>
          </dl>
        </section>

        <section
          className={styles.card}
        >
          <header>
            <Phone size={20} />
            <div>
              <h2>Kontak</h2>
              <p>
                Informasi komunikasi
                pemasok.
              </p>
            </div>
          </header>

          <dl>
            <div>
              <dt>
                Telepon / WhatsApp
              </dt>
              <dd>
                <Phone size={15} />
                {textOrDash(
                  supplier.phone,
                )}
              </dd>
            </div>

            <div>
              <dt>Email</dt>
              <dd>
                <Mail size={15} />
                {textOrDash(
                  supplier.email,
                )}
              </dd>
            </div>
          </dl>
        </section>

        <section
          className={styles.card}
        >
          <header>
            <MapPin size={20} />
            <div>
              <h2>Alamat Pemasok</h2>
              <p>
                Lokasi utama pemasok.
              </p>
            </div>
          </header>

          <dl>
            <div>
              <dt>Alamat</dt>
              <dd>
                {textOrDash(
                  supplier.address,
                )}
              </dd>
            </div>

            <div>
              <dt>Kota</dt>
              <dd>
                {textOrDash(
                  supplier.city,
                )}
              </dd>
            </div>

            <div>
              <dt>Provinsi</dt>
              <dd>
                {textOrDash(
                  supplier.province,
                )}
              </dd>
            </div>
          </dl>
        </section>

        <section
          className={styles.card}
        >
          <header>
            <ReceiptText size={20} />
            <div>
              <h2>
                Informasi Bisnis
              </h2>
              <p>
                Ketentuan yang dapat
                digunakan saat
                pembelian.
              </p>
            </div>
          </header>

          <dl>
            <div>
              <dt>NPWP</dt>
              <dd>
                {textOrDash(
                  supplier.tax_id,
                )}
              </dd>
            </div>

            <div>
              <dt>
                Termin pembayaran
              </dt>
              <dd>
                {
                  supplier
                    .payment_terms_days ??
                  0
                }{" "}
                hari
              </dd>
            </div>

            <div>
              <dt>Catatan</dt>
              <dd>
                {textOrDash(
                  supplier.notes,
                )}
              </dd>
            </div>
          </dl>
        </section>
      </div>
    </section>
  );
}
