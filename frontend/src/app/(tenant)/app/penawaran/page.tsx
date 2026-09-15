"use client";

import {
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  FileText,
  Plus,
  Search,
  Trash2,
  X,
} from "lucide-react";

import {
  useCallback,
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
  ModuleFooterCard,
} from "@/components/module/module-footer-card";

import {
  ModuleHero,
} from "@/components/module/module-hero";

import {
  Button,
} from "@/components/ui/button";

import {
  apiErrorMessage,
  apiRequestId,
} from "@/lib/api/error-message";

import {
  listCatalogItems,
  listCatalogUnits,
} from "@/lib/catalog/service";

import {
  listCustomers,
} from "@/lib/customer/service";

import {
  formatCurrency,
} from "@/lib/format/currency";

import {
  getModule,
} from "@/lib/module/registry";

import {
  createQuotation,
  listQuotations,
} from "@/lib/quotation/service";

import type {
  CatalogItem,
  CatalogPricingMethod,
  CatalogUnit,
} from "@/types/catalog";

import type {
  Customer,
} from "@/types/customer";

import type {
  Quotation,
  QuotationItemPayload,
  QuotationStatus,
} from "@/types/quotation";

import styles from "./quotation.module.css";

type StatusFilter =
  | "ALL"
  | QuotationStatus;

type LineForm = {
  key: string;
  catalogItemId: string;
  quantity: string;
  unitPrice: string;
  discountAmount: string;
  taxRate: string;
  width: string;
  height: string;
  depth: string;
  length: string;
  duration: string;
};

type FormState = {
  customerId: string;
  validUntil: string;
  notes: string;
  terms: string;
  lines: LineForm[];
};

const statusOptions: {
  value: StatusFilter;
  label: string;
}[] = [
  {
    value: "ALL",
    label: "Semua",
  },
  {
    value: "DRAFT",
    label: "Draf",
  },
  {
    value: "SENT",
    label: "Menunggu Keputusan",
  },
  {
    value: "VIEWED",
    label: "Sudah Dilihat",
  },
  {
    value: "APPROVED",
    label: "Disetujui",
  },
  {
    value: "REJECTED",
    label: "Perlu Revisi",
  },
  {
    value: "EXPIRED",
    label: "Kedaluwarsa",
  },
  {
    value: "CANCELLED",
    label: "Dibatalkan",
  },
];

function lineKey(): string {
  return (
    globalThis.crypto
      ?.randomUUID?.()
    ?? `${Date.now()}-${Math.random()}`
  );
}

function emptyLine(): LineForm {
  return {
    key: lineKey(),
    catalogItemId: "",
    quantity: "1",
    unitPrice: "",
    discountAmount: "0",
    taxRate: "0",
    width: "",
    height: "",
    depth: "",
    length: "",
    duration: "",
  };
}

function emptyForm(): FormState {
  return {
    customerId: "",
    validUntil: "",
    notes: "",
    terms: "",
    lines: [
      emptyLine(),
    ],
  };
}

function numeric(
  value: string,
): number {
  const result =
    Number(value);

  return Number.isFinite(result)
    ? result
    : 0;
}

function statusLabel(
  status: QuotationStatus,
): string {
  switch (status) {
    case "DRAFT":
      return "Draf";
    case "SENT":
      return "Menunggu Keputusan";
    case "VIEWED":
      return "Sudah Dilihat";
    case "APPROVED":
      return "Disetujui";
    case "REJECTED":
      return "Perlu Revisi";
    case "EXPIRED":
      return "Kedaluwarsa";
    case "CANCELLED":
      return "Dibatalkan";

    default:
      return status;
  }
}

function localDate(
  value:
    | string
    | null,
): string {
  if (!value) {
    return "Tanpa batas waktu";
  }

  const date =
    new Date(
      `${value}T00:00:00`,
    );

  return new Intl.DateTimeFormat(
    "id-ID",
    {
      day: "numeric",
      month: "short",
      year: "numeric",
    },
  ).format(date);
}

function pricingInputs(
  method: CatalogPricingMethod,
): (
  | "width"
  | "height"
  | "depth"
  | "length"
  | "duration"
)[] {
  switch (method) {
    case "AREA":
      return [
        "width",
        "height",
      ];

    case "LENGTH":
      return [
        "length",
      ];

    case "VOLUME":
      return [
        "width",
        "height",
        "depth",
      ];

    case "TIME":
      return [
        "duration",
      ];

    default:
      return [];
  }
}

const dimensionLabels = {
  width: "Lebar",
  height: "Tinggi",
  depth: "Kedalaman",
  length: "Panjang",
  duration: "Durasi",
} as const;

export default function QuotationsPage() {
  const moduleDef =
    getModule("quotations");

  const ModuleIcon =
    moduleDef.icon;

  const [
    quotations,
    setQuotations,
  ] = useState<Quotation[]>([]);

  const [
    customers,
    setCustomers,
  ] = useState<Customer[]>([]);

  const [
    catalogItems,
    setCatalogItems,
  ] = useState<CatalogItem[]>([]);

  const [
    units,
    setUnits,
  ] = useState<CatalogUnit[]>([]);

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
    page,
    setPage,
  ] = useState(1);

  const [
    lastPage,
    setLastPage,
  ] = useState(1);

  const [
    total,
    setTotal,
  ] = useState(0);

  const [
    loading,
    setLoading,
  ] = useState(true);

  const [
    error,
    setError,
  ] = useState<unknown>(null);

  const [
    editorOpen,
    setEditorOpen,
  ] = useState(false);

  const [
    form,
    setForm,
  ] = useState<FormState>(
    emptyForm,
  );

  const [
    saving,
    setSaving,
  ] = useState(false);

  const [
    saveError,
    setSaveError,
  ] = useState<unknown>(null);

  const [
    success,
    setSuccess,
  ] = useState<string | null>(
    null,
  );

  const effectiveStatus =
    status === "ALL"
      ? undefined
      : status;

  const loadQuotations =
    useCallback(
      async () => {
        setLoading(true);
        setError(null);

        try {
          const response =
            await listQuotations({
              search,
              status:
                effectiveStatus,
              page,
              per_page: 20,
            });

          setQuotations(
            response.data,
          );

          setTotal(
            response.meta.total,
          );

          setLastPage(
            response.meta.last_page,
          );
        } catch (caught) {
          setError(caught);
        } finally {
          setLoading(false);
        }
      },
      [
        search,
        effectiveStatus,
        page,
      ],
    );

  useEffect(() => {
    const timer =
      window.setTimeout(
        () => {
          void loadQuotations();
        },
        280,
      );

    return () => {
      window.clearTimeout(
        timer,
      );
    };
  }, [
    loadQuotations,
  ]);

  useEffect(() => {
    let cancelled = false;

    async function loadMasterData() {
      try {
        const [
          customerResponse,
          catalogResponse,
          unitResponse,
        ] = await Promise.all([
          listCustomers({
            status: "ACTIVE",
            page: 1,
          }),
          listCatalogItems({
            status: "ACTIVE",
            page: 1,
          }),
          listCatalogUnits(),
        ]);

        if (cancelled) {
          return;
        }

        setCustomers(
          customerResponse.data,
        );

        setCatalogItems(
          catalogResponse.data,
        );

        setUnits(
          unitResponse.data,
        );
      } catch {
        /*
         * List utama tetap dapat digunakan.
         * Error master akan muncul saat user
         * membuka form bila datanya kosong.
         */
      }
    }

    void loadMasterData();

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    const timeoutId =
      window.setTimeout(
        () => {
          setPage(1);
        },
        0,
      );

    return () => {
      window.clearTimeout(
        timeoutId,
      );
    };
  }, [
    search,
    status,
  ]);

  useEffect(() => {
    if (!success) {
      return;
    }

    const timer =
      window.setTimeout(
        () => {
          setSuccess(null);
        },
        4000,
      );

    return () => {
      window.clearTimeout(
        timer,
      );
    };
  }, [
    success,
  ]);

  const itemMap =
    useMemo(
      () =>
        new Map(
          catalogItems.map(
            (item) => [
              item.id,
              item,
            ],
          ),
        ),
      [
        catalogItems,
      ],
    );

  const unitMap =
    useMemo(
      () =>
        new Map(
          units.map(
            (unit) => [
              unit.id,
              unit,
            ],
          ),
        ),
      [
        units,
      ],
    );

  const estimatedTotals =
    useMemo(
      () => {
        let subtotal = 0;
        let discount = 0;
        let tax = 0;

        for (
          const line
          of form.lines
        ) {
          const quantity =
            Math.max(
              numeric(
                line.quantity,
              ),
              0,
            );

          const unitPrice =
            Math.max(
              numeric(
                line.unitPrice,
              ),
              0,
            );

          const gross =
            quantity *
            unitPrice;

          const lineDiscount =
            Math.min(
              Math.max(
                numeric(
                  line.discountAmount,
                ),
                0,
              ),
              gross,
            );

          const taxable =
            gross -
            lineDiscount;

          const lineTax =
            taxable *
            Math.max(
              numeric(
                line.taxRate,
              ),
              0,
            ) /
            100;

          subtotal += gross;
          discount +=
            lineDiscount;
          tax += lineTax;
        }

        return {
          subtotal,
          discount,
          tax,
          total:
            subtotal -
            discount +
            tax,
        };
      },
      [
        form.lines,
      ],
    );

  function openCreate() {
    setForm(
      emptyForm(),
    );
    setSaveError(null);
    setEditorOpen(true);
  }

  function closeCreate() {
    if (saving) {
      return;
    }

    setEditorOpen(false);
    setSaveError(null);
  }

  function updateLine(
    key: string,
    patch: Partial<LineForm>,
  ) {
    setForm(
      (current) => ({
        ...current,
        lines:
          current.lines.map(
            (line) =>
              line.key === key
                ? {
                    ...line,
                    ...patch,
                  }
                : line,
          ),
      }),
    );
  }

  function selectCatalogItem(
    key: string,
    catalogItemId: string,
  ) {
    const item =
      itemMap.get(
        catalogItemId,
      );

    updateLine(
      key,
      {
        catalogItemId,
        unitPrice:
          item
            ? String(
                item.base_price,
              )
            : "",
        width: "",
        height: "",
        depth: "",
        length: "",
        duration: "",
      },
    );
  }

  function addLine() {
    setForm(
      (current) => ({
        ...current,
        lines: [
          ...current.lines,
          emptyLine(),
        ],
      }),
    );
  }

  function removeLine(
    key: string,
  ) {
    setForm(
      (current) => ({
        ...current,
        lines:
          current.lines.length <=
          1
            ? current.lines
            : current.lines.filter(
                (line) =>
                  line.key !== key,
              ),
      }),
    );
  }

  async function saveDraft() {
    setSaveError(null);

    if (!form.customerId) {
      setSaveError(
        new Error(
          "Pilih pelanggan terlebih dahulu.",
        ),
      );
      return;
    }

    if (
      form.lines.some(
        (line) =>
          !line.catalogItemId,
      )
    ) {
      setSaveError(
        new Error(
          "Pilih Barang & Jasa pada setiap baris.",
        ),
      );
      return;
    }

    const items:
      QuotationItemPayload[] =
      form.lines.map(
        (
          line,
          index,
        ) => {
          const item =
            itemMap.get(
              line.catalogItemId,
            );

          if (!item) {
            throw new Error(
              "Barang & Jasa tidak ditemukan.",
            );
          }

          const config:
            Record<
              string,
              number
            > = {};

          for (
            const field
            of pricingInputs(
              item.pricing_method,
            )
          ) {
            const value =
              numeric(
                line[field],
              );

            if (value > 0) {
              config[field] =
                value;
            }
          }

          return {
            catalog_item_id:
              item.id,
            unit_id:
              item.unit_id,
            item_type:
              item.type,
            quantity:
              numeric(
                line.quantity,
              ),
            pricing_config:
              Object.keys(
                config,
              ).length > 0
                ? config
                : null,
            unit_price:
              numeric(
                line.unitPrice,
              ),
            discount_amount:
              numeric(
                line.discountAmount,
              ),
            tax_rate:
              numeric(
                line.taxRate,
              ),
            sort_order:
              index,
          };
        },
      );

    setSaving(true);

    try {
      const response =
        await createQuotation({
          customer_id:
            form.customerId,
          valid_until:
            form.validUntil ||
            null,
          currency: "IDR",
          notes:
            form.notes.trim() ||
            null,
          terms:
            form.terms.trim() ||
            null,
          items,
        });

      setEditorOpen(false);

      setSuccess(
        `Penawaran ${response.data.quotation_number} berhasil dibuat.`,
      );

      setPage(1);

      await loadQuotations();
    } catch (caught) {
      setSaveError(caught);
    } finally {
      setSaving(false);
    }
  }

  return (
    <TenantShell>
      <section
        className={styles.page}
      >
        <ModuleHero
          eyebrow="Penjualan"
          title={moduleDef.label}
          description={
            moduleDef.description
          }
          icon={ModuleIcon}
          tone="teal"
          insightTitle={
            moduleDef.insight?.title
          }
          insightDescription={
            moduleDef.insight
              ?.description
          }
          actions={
            <Button
              type="button"
              leadingIcon={
                <Plus size={18} />
              }
              onClick={
                openCreate
              }
            >
              Buat Penawaran
            </Button>
          }
        />

        {success ? (
          <ActionFeedback
            tone="success"
            title="Berhasil"
            message={success}
            placement="viewport"
          />
        ) : null}

        <section
          className={
            styles.workspace
          }
        >
          <div
            className={
              styles.toolbar
            }
          >
            <label
              className={
                styles.search
              }
            >
              <Search
                size={18}
                aria-hidden="true"
              />

              <input
                value={search}
                onChange={
                  (event) =>
                    setSearch(
                      event.target
                        .value,
                    )
                }
                placeholder="Cari nomor atau pelanggan..."
              />
            </label>

            <div
              className={
                styles.statusFilters
              }
            >
              {statusOptions.map(
                (option) => (
                  <button
                    key={
                      option.value
                    }
                    type="button"
                    className={
                      status ===
                      option.value
                        ? styles.statusActive
                        : styles.statusButton
                    }
                    onClick={
                      () =>
                        setStatus(
                          option.value,
                        )
                    }
                  >
                    {option.label}
                  </button>
                ),
              )}
            </div>
          </div>

          <div
            className={
              styles.summaryRow
            }
          >
            <div>
              <strong>
                {total}
              </strong>
              <span>
                penawaran
              </span>
            </div>

            <span>
              Halaman {page} dari{" "}
              {Math.max(
                lastPage,
                1,
              )}
            </span>
          </div>

          {error ? (
            <ActionFeedback
              tone="error"
              title="Penawaran belum dapat dimuat"
              message={
                apiErrorMessage(
                  error,
                )
              }
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
                styles.emptyState
              }
            >
              Memuat penawaran...
            </div>
          ) : null}

          {!loading &&
          !error &&
          quotations.length ===
            0 ? (
            <div
              className={
                styles.emptyState
              }
            >
              <FileText
                size={34}
                strokeWidth={1.6}
              />

              <strong>
                Belum ada penawaran
              </strong>

              <p>
                Buat penawaran
                pertama untuk
                pelanggan dari
                katalog yang sudah
                tersedia.
              </p>

              <Button
                type="button"
                leadingIcon={
                  <Plus
                    size={18}
                  />
                }
                onClick={
                  openCreate
                }
              >
                Buat Penawaran
              </Button>
            </div>
          ) : null}

          {!loading &&
          quotations.length >
            0 ? (
            <>
              <div
                className={
                  styles.desktopTable
                }
              >
                <table>
                  <thead>
                    <tr>
                      <th>
                        Penawaran
                      </th>
                      <th>
                        Pelanggan
                      </th>
                      <th>
                        Berlaku Sampai
                      </th>
                      <th>
                        Total
                      </th>
                      <th>
                        Status
                      </th>
                    </tr>
                  </thead>

                  <tbody>
                    {quotations.map(
                      (
                        quotation,
                      ) => (
                        <tr
                          key={
                            quotation.id
                          }
                        >
                          <td>
                            <strong>
                              {
                                quotation.quotation_number
                              }
                            </strong>

                            <small>
                              REV-
                              {String(
                                quotation
                                  .current_version
                                  ?.revision_no ??
                                  1,
                              ).padStart(
                                2,
                                "0",
                              )}
                            </small>
                          </td>

                          <td>
                            {
                              quotation
                                .customer
                                ?.name ??
                              "-"
                            }
                          </td>

                          <td>
                            {localDate(
                              quotation.valid_until,
                            )}
                          </td>

                          <td>
                            <strong>
                              {formatCurrency(
                                quotation
                                  .current_version
                                  ?.total,
                                quotation
                                  .current_version
                                  ?.currency ??
                                  "IDR",
                              )}
                            </strong>
                          </td>

                          <td>
                            <span
                              className={
                                styles.statusBadge
                              }
                              data-status={
                                quotation.status
                              }
                            >
                              {statusLabel(
                                quotation.status,
                              )}
                            </span>
                          </td>
                        </tr>
                      ),
                    )}
                  </tbody>
                </table>
              </div>

              <div
                className={
                  styles.mobileCards
                }
              >
                {quotations.map(
                  (
                    quotation,
                  ) => (
                    <article
                      key={
                        quotation.id
                      }
                      className={
                        styles.quotationCard
                      }
                    >
                      <div
                        className={
                          styles.cardTop
                        }
                      >
                        <div>
                          <strong>
                            {
                              quotation.quotation_number
                            }
                          </strong>

                          <span>
                            {
                              quotation
                                .customer
                                ?.name ??
                              "-"
                            }
                          </span>
                        </div>

                        <span
                          className={
                            styles.statusBadge
                          }
                          data-status={
                            quotation.status
                          }
                        >
                          {statusLabel(
                            quotation.status,
                          )}
                        </span>
                      </div>

                      <strong
                        className={
                          styles.cardTotal
                        }
                      >
                        {formatCurrency(
                          quotation
                            .current_version
                            ?.total,
                          quotation
                            .current_version
                            ?.currency ??
                            "IDR",
                        )}
                      </strong>

                      <div
                        className={
                          styles.cardMeta
                        }
                      >
                        <span>
                          REV-
                          {String(
                            quotation
                              .current_version
                              ?.revision_no ??
                              1,
                          ).padStart(
                            2,
                            "0",
                          )}
                        </span>

                        <span>
                          <CalendarDays
                            size={15}
                          />
                          {localDate(
                            quotation.valid_until,
                          )}
                        </span>
                      </div>
                    </article>
                  ),
                )}
              </div>

              <div
                className={
                  styles.pagination
                }
              >
                <Button
                  type="button"
                  variant="secondary"
                  leadingIcon={
                    <ChevronLeft
                      size={18}
                    />
                  }
                  disabled={
                    page <= 1
                  }
                  onClick={
                    () =>
                      setPage(
                        (
                          current,
                        ) =>
                          Math.max(
                            current -
                              1,
                            1,
                          ),
                      )
                  }
                >
                  Sebelumnya
                </Button>

                <Button
                  type="button"
                  variant="secondary"
                  disabled={
                    page >=
                    lastPage
                  }
                  onClick={
                    () =>
                      setPage(
                        (
                          current,
                        ) =>
                          Math.min(
                            current +
                              1,
                            lastPage,
                          ),
                      )
                  }
                >
                  Berikutnya
                  <ChevronRight
                    size={18}
                  />
                </Button>
              </div>
            </>
          ) : null}
        </section>

        <ModuleFooterCard
          title="Penawaran tetap mudah ditelusuri"
          description="Setiap revisi disimpan sebagai versi baru sehingga riwayat harga dan keputusan pelanggan tidak hilang."
          tone="teal"
        />

        {editorOpen ? (
          <div
            className={
              styles.modalBackdrop
            }
            role="presentation"
          >
            <section
              className={
                styles.modal
              }
              role="dialog"
              aria-modal="true"
              aria-labelledby="quotation-form-title"
            >
              <header
                className={
                  styles.modalHeader
                }
              >
                <div>
                  <span>
                    Penawaran
                  </span>

                  <h2
                    id="quotation-form-title"
                  >
                    Penawaran Baru
                  </h2>

                  <p>
                    Pilih pelanggan
                    dan Barang & Jasa.
                    Nomor dibuat
                    otomatis oleh
                    SIGNOVA.
                  </p>
                </div>

                <button
                  type="button"
                  className={
                    styles.closeButton
                  }
                  onClick={
                    closeCreate
                  }
                  aria-label="Tutup"
                >
                  <X size={20} />
                </button>
              </header>

              <div
                className={
                  styles.modalBody
                }
              >
                {saveError ? (
                  <ActionFeedback
                    tone="error"
                    title="Penawaran belum dapat disimpan"
                    message={
                      apiErrorMessage(
                        saveError,
                      )
                    }
                    requestId={
                      apiRequestId(
                        saveError,
                      )
                    }
                  />
                ) : null}

                <div
                  className={
                    styles.headerFields
                  }
                >
                  <label>
                    <span>
                      Pelanggan *
                    </span>

                    <select
                      value={
                        form.customerId
                      }
                      onChange={
                        (
                          event,
                        ) =>
                          setForm(
                            (
                              current,
                            ) => ({
                              ...current,
                              customerId:
                                event
                                  .target
                                  .value,
                            }),
                          )
                      }
                    >
                      <option value="">
                        Pilih pelanggan
                      </option>

                      {customers.map(
                        (
                          customer,
                        ) => (
                          <option
                            key={
                              customer.id
                            }
                            value={
                              customer.id
                            }
                          >
                            {
                              customer.name
                            }
                          </option>
                        ),
                      )}
                    </select>
                  </label>

                  <label>
                    <span>
                      Berlaku Sampai
                    </span>

                    <input
                      type="date"
                      value={
                        form.validUntil
                      }
                      onChange={
                        (
                          event,
                        ) =>
                          setForm(
                            (
                              current,
                            ) => ({
                              ...current,
                              validUntil:
                                event
                                  .target
                                  .value,
                            }),
                          )
                      }
                    />
                  </label>
                </div>

                <section
                  className={
                    styles.lineSection
                  }
                >
                  <div
                    className={
                      styles.sectionHeading
                    }
                  >
                    <div>
                      <strong>
                        Item Penawaran
                      </strong>

                      <p>
                        Harga katalog
                        menjadi harga
                        awal dan dapat
                        disesuaikan
                        khusus penawaran
                        ini.
                      </p>
                    </div>

                    <Button
                      type="button"
                      variant="secondary"
                      leadingIcon={
                        <Plus
                          size={17}
                        />
                      }
                      onClick={
                        addLine
                      }
                    >
                      Tambah Item
                    </Button>
                  </div>

                  <div
                    className={
                      styles.lines
                    }
                  >
                    {form.lines.map(
                      (
                        line,
                        index,
                      ) => {
                        const item =
                          itemMap.get(
                            line.catalogItemId,
                          );

                        const unit =
                          item?.unit_id
                            ? unitMap.get(
                                item.unit_id,
                              )
                            : undefined;

                        const dimensions =
                          item
                            ? pricingInputs(
                                item.pricing_method,
                              )
                            : [];

                        const gross =
                          numeric(
                            line.quantity,
                          ) *
                          numeric(
                            line.unitPrice,
                          );

                        const discount =
                          Math.min(
                            numeric(
                              line.discountAmount,
                            ),
                            gross,
                          );

                        const taxable =
                          gross -
                          discount;

                        const tax =
                          taxable *
                          numeric(
                            line.taxRate,
                          ) /
                          100;

                        return (
                          <article
                            key={
                              line.key
                            }
                            className={
                              styles.lineCard
                            }
                          >
                            <div
                              className={
                                styles.lineHeader
                              }
                            >
                              <strong>
                                Item{" "}
                                {index +
                                  1}
                              </strong>

                              <button
                                type="button"
                                className={
                                  styles.removeButton
                                }
                                disabled={
                                  form.lines
                                    .length <=
                                  1
                                }
                                onClick={
                                  () =>
                                    removeLine(
                                      line.key,
                                    )
                                }
                                aria-label="Hapus item"
                              >
                                <Trash2
                                  size={
                                    17
                                  }
                                />
                              </button>
                            </div>

                            <div
                              className={
                                styles.lineGrid
                              }
                            >
                              <label
                                className={
                                  styles.itemField
                                }
                              >
                                <span>
                                  Barang
                                  & Jasa
                                  *
                                </span>

                                <select
                                  value={
                                    line.catalogItemId
                                  }
                                  onChange={
                                    (
                                      event,
                                    ) =>
                                      selectCatalogItem(
                                        line.key,
                                        event
                                          .target
                                          .value,
                                      )
                                  }
                                >
                                  <option value="">
                                    Pilih
                                    Barang
                                    & Jasa
                                  </option>

                                  {catalogItems.map(
                                    (
                                      catalogItem,
                                    ) => (
                                      <option
                                        key={
                                          catalogItem.id
                                        }
                                        value={
                                          catalogItem.id
                                        }
                                      >
                                        {
                                          catalogItem.name
                                        }
                                        {" — "}
                                        {formatCurrency(
                                          catalogItem.base_price,
                                          catalogItem.currency,
                                        )}
                                      </option>
                                    ),
                                  )}
                                </select>

                                {item ? (
                                  <small>
                                    {
                                      item.pricing_method_label
                                    }
                                    {unit
                                      ? ` • ${unit.symbol ?? unit.name}`
                                      : ""}
                                  </small>
                                ) : null}
                              </label>

                              <label>
                                <span>
                                  Qty *
                                </span>

                                <input
                                  type="number"
                                  min="0.01"
                                  step="any"
                                  value={
                                    line.quantity
                                  }
                                  onChange={
                                    (
                                      event,
                                    ) =>
                                      updateLine(
                                        line.key,
                                        {
                                          quantity:
                                            event
                                              .target
                                              .value,
                                        },
                                      )
                                  }
                                />
                              </label>

                              <label>
                                <span>
                                  Harga
                                </span>

                                <input
                                  type="number"
                                  min="0"
                                  step="any"
                                  value={
                                    line.unitPrice
                                  }
                                  onChange={
                                    (
                                      event,
                                    ) =>
                                      updateLine(
                                        line.key,
                                        {
                                          unitPrice:
                                            event
                                              .target
                                              .value,
                                        },
                                      )
                                  }
                                />
                              </label>

                              <label>
                                <span>
                                  Diskon
                                </span>

                                <input
                                  type="number"
                                  min="0"
                                  step="any"
                                  value={
                                    line.discountAmount
                                  }
                                  onChange={
                                    (
                                      event,
                                    ) =>
                                      updateLine(
                                        line.key,
                                        {
                                          discountAmount:
                                            event
                                              .target
                                              .value,
                                        },
                                      )
                                  }
                                />
                              </label>

                              <label>
                                <span>
                                  Pajak %
                                </span>

                                <input
                                  type="number"
                                  min="0"
                                  max="100"
                                  step="any"
                                  value={
                                    line.taxRate
                                  }
                                  onChange={
                                    (
                                      event,
                                    ) =>
                                      updateLine(
                                        line.key,
                                        {
                                          taxRate:
                                            event
                                              .target
                                              .value,
                                        },
                                      )
                                  }
                                />
                              </label>

                              {dimensions.map(
                                (
                                  field,
                                ) => (
                                  <label
                                    key={
                                      field
                                    }
                                  >
                                    <span>
                                      {
                                        dimensionLabels[
                                          field
                                        ]
                                      }
                                    </span>

                                    <input
                                      type="number"
                                      min="0.01"
                                      step="any"
                                      value={
                                        line[
                                          field
                                        ]
                                      }
                                      onChange={
                                        (
                                          event,
                                        ) =>
                                          updateLine(
                                            line.key,
                                            {
                                              [field]:
                                                event
                                                  .target
                                                  .value,
                                            },
                                          )
                                      }
                                    />
                                  </label>
                                ),
                              )}
                            </div>

                            <div
                              className={
                                styles.lineTotal
                              }
                            >
                              <span>
                                Estimasi
                                item
                              </span>

                              <strong>
                                {formatCurrency(
                                  taxable +
                                    tax,
                                )}
                              </strong>
                            </div>
                          </article>
                        );
                      },
                    )}
                  </div>
                </section>

                <div
                  className={
                    styles.notesGrid
                  }
                >
                  <label>
                    <span>
                      Catatan
                    </span>

                    <textarea
                      rows={4}
                      value={
                        form.notes
                      }
                      onChange={
                        (
                          event,
                        ) =>
                          setForm(
                            (
                              current,
                            ) => ({
                              ...current,
                              notes:
                                event
                                  .target
                                  .value,
                            }),
                          )
                      }
                      placeholder="Catatan untuk pelanggan..."
                    />
                  </label>

                  <label>
                    <span>
                      Syarat &
                      Ketentuan
                    </span>

                    <textarea
                      rows={4}
                      value={
                        form.terms
                      }
                      onChange={
                        (
                          event,
                        ) =>
                          setForm(
                            (
                              current,
                            ) => ({
                              ...current,
                              terms:
                                event
                                  .target
                                  .value,
                            }),
                          )
                      }
                      placeholder="Contoh: harga berlaku 14 hari..."
                    />
                  </label>
                </div>

                <aside
                  className={
                    styles.totalCard
                  }
                >
                  <div>
                    <span>
                      Subtotal
                    </span>
                    <strong>
                      {formatCurrency(
                        estimatedTotals.subtotal,
                      )}
                    </strong>
                  </div>

                  <div>
                    <span>
                      Diskon
                    </span>
                    <strong>
                      -{" "}
                      {formatCurrency(
                        estimatedTotals.discount,
                      )}
                    </strong>
                  </div>

                  <div>
                    <span>
                      Pajak
                    </span>
                    <strong>
                      {formatCurrency(
                        estimatedTotals.tax,
                      )}
                    </strong>
                  </div>

                  <div
                    className={
                      styles.grandTotal
                    }
                  >
                    <span>
                      Estimasi Total
                    </span>
                    <strong>
                      {formatCurrency(
                        estimatedTotals.total,
                      )}
                    </strong>
                  </div>

                  <small>
                    Nilai final
                    dihitung ulang oleh
                    server saat
                    disimpan.
                  </small>
                </aside>
              </div>

              <footer
                className={
                  styles.modalFooter
                }
              >
                <Button
                  type="button"
                  variant="secondary"
                  disabled={saving}
                  onClick={
                    closeCreate
                  }
                >
                  Batal
                </Button>

                <Button
                  type="button"
                  loading={saving}
                  loadingLabel="Menyimpan..."
                  onClick={
                    () =>
                      void saveDraft()
                  }
                >
                  Simpan Draft
                </Button>
              </footer>
            </section>
          </div>
        ) : null}
      </section>
    </TenantShell>
  );
}
