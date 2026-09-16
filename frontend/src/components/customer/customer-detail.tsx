"use client";

import {
  ArrowLeft,
  Building2,
  CalendarDays,
  Mail,
  MapPin,
  Pencil,
  Phone,
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
  getCustomer,
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
} from "@/types/customer";

import styles from "./customer-detail.module.css";

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

export function CustomerDetail() {
  const params =
    useParams<{
      id: string;
    }>();

  const customerModule =
    getModule(
      "customers",
    );

  const [
    customer,
    setCustomer,
  ] = useState<
    Customer | null
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

  useEffect(() => {
    let cancelled =
      false;

    async function load() {
      setLoading(true);
      setError(null);
      setRequestId(null);

      try {
        const response =
          await getCustomer(
            params.id,
          );

        if (!cancelled) {
          setCustomer(
            response.data,
          );
        }
      } catch (loadError) {
        if (!cancelled) {
          setError(
            apiErrorMessage(
              loadError,
              "Data pelanggan belum berhasil dimuat.",
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
        className={
          styles.page
        }
      >
        <div
          className={
            styles.loadingCard
          }
        >
          Memuat data pelanggan...
        </div>
      </section>
    );
  }

  if (
    error ||
    !customer
  ) {
    return (
      <section
        className={
          styles.page
        }
      >
        <Link
          href="/app/pelanggan"
          className={
            styles.backLink
          }
        >
          <ArrowLeft
            size={17}
          />

          Kembali ke Pelanggan
        </Link>

        <ActionFeedback
          tone="error"
          title="Data pelanggan belum dapat dibuka"
          message={
            error ??
            "Pelanggan tidak ditemukan."
          }
          requestId={
            requestId
          }
        />
      </section>
    );
  }

  const TypeIcon =
    customer.type ===
    "COMPANY"
      ? Building2
      : UserRound;

  return (
    <section
      className={
        styles.page
      }
    >
      <div
        className={
          styles.topBar
        }
      >
        <Link
          href="/app/pelanggan"
          className={
            styles.backLink
          }
        >
          <ArrowLeft
            size={17}
          />

          Pelanggan
        </Link>

        <Link
          href={
            `/app/pelanggan/${customer.id}/ubah`
          }
          className={
            styles.editButton
          }
        >
          <Pencil
            size={17}
          />

          Ubah Pelanggan
        </Link>
      </div>

      <ModuleHero
        eyebrow={
          customerModule.eyebrow ??
          customerModule.label
        }
        title={
          customer.name
        }
        description={
          customer.code
            ? `Kode pelanggan: ${customer.code}`
            : customerModule.description
        }
        icon={
          TypeIcon
        }
        tone={
          customerModule.tone ===
          "amber"
            ? "blue"
            : customerModule.tone
        }
        insightTitle={
          customerTypeLabel(
            customer.type,
          )
        }
        insightDescription={
          `Status pelanggan: ${customerStatusLabel(
            customer.status,
          )}`
        }
      />

      <div
        className={
          styles.grid
        }
      >
        <section
          className={
            styles.card
          }
        >
          <header
            className={
              styles.cardHeader
            }
          >
            <UserRound
              size={20}
            />

            <div>
              <h2>
                Informasi Pelanggan
              </h2>

              <p>
                Identitas dan kontak
                utama pelanggan.
              </p>
            </div>
          </header>

          <dl
            className={
              styles.detailList
            }
          >
            <div>
              <dt>
                Tipe
              </dt>

              <dd>
                {customerTypeLabel(
                  customer.type,
                )}
              </dd>
            </div>

            <div>
              <dt>
                Kode pelanggan
              </dt>

              <dd>
                {textOrDash(
                  customer.code,
                )}
              </dd>
            </div>

            <div>
              <dt>
                Status
              </dt>

              <dd>
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
              </dd>
            </div>
          </dl>
        </section>

        <section
          className={
            styles.card
          }
        >
          <header
            className={
              styles.cardHeader
            }
          >
            <Phone
              size={20}
            />

            <div>
              <h2>
                Kontak
              </h2>

              <p>
                Informasi komunikasi
                pelanggan.
              </p>
            </div>
          </header>

          <dl
            className={
              styles.detailList
            }
          >
            <div>
              <dt>
                Telepon / WhatsApp
              </dt>

              <dd>
                <Phone
                  size={15}
                />

                {textOrDash(
                  customer.phone,
                )}
              </dd>
            </div>

            <div>
              <dt>
                Email
              </dt>

              <dd>
                <Mail
                  size={15}
                />

                {textOrDash(
                  customer.email,
                )}
              </dd>
            </div>
          </dl>
        </section>

        <section
          className={
            styles.card
          }
        >
          <header
            className={
              styles.cardHeader
            }
          >
            <MapPin
              size={20}
            />

            <div>
              <h2>
                Alamat Pelanggan
              </h2>

              <p>
                Lokasi utama pelanggan.
              </p>
            </div>
          </header>

          <dl
            className={
              styles.detailList
            }
          >
            <div>
              <dt>
                Alamat
              </dt>

              <dd>
                {textOrDash(
                  customer.address,
                )}
              </dd>
            </div>

            <div>
              <dt>
                Kota
              </dt>

              <dd>
                {textOrDash(
                  customer.city,
                )}
              </dd>
            </div>

            <div>
              <dt>
                Provinsi
              </dt>

              <dd>
                {textOrDash(
                  customer.province,
                )}
              </dd>
            </div>
          </dl>
        </section>

        <section
          className={
            styles.card
          }
        >
          <header
            className={
              styles.cardHeader
            }
          >
            <ReceiptText
              size={20}
            />

            <div>
              <h2>
                Informasi Bisnis
              </h2>

              <p>
                Informasi yang digunakan
                dalam transaksi.
              </p>
            </div>
          </header>

          <dl
            className={
              styles.detailList
            }
          >
            <div>
              <dt>
                NPWP / Tax ID
              </dt>

              <dd>
                {textOrDash(
                  customer.tax_id,
                )}
              </dd>
            </div>

            <div>
              <dt>
                Termin pembayaran
              </dt>

              <dd>
                {customer.payment_terms_days ??
                  0}{" "}
                hari
              </dd>
            </div>
          </dl>
        </section>

        <section
          className={
            styles.card
          }
        >
          <header
            className={
              styles.cardHeader
            }
          >
            <CalendarDays
              size={20}
            />

            <div>
              <h2>
                Catatan
              </h2>

              <p>
                Catatan internal terkait
                pelanggan.
              </p>
            </div>
          </header>

          <p
            className={
              styles.notes
            }
          >
            {textOrDash(
              customer.notes,
            )}
          </p>
        </section>
      </div>
    </section>
  );
}
