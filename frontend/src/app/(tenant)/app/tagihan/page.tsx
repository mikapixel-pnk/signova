"use client";

import {
  ChevronLeft,
  ChevronRight,
  FileText,
  Plus,
  Search,
  Trash2,
  X,
} from "lucide-react";

import Link from "next/link";

import {
  useCallback,
  useEffect,
  useMemo,
  useState,
} from "react";

import type {
  FormEvent,
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
  Button,
} from "@/components/ui/button";

import {
  ApiClientError,
} from "@/lib/api/types";

import {
  listCatalogItems,
  listCatalogUnits,
} from "@/lib/catalog/service";

import {
  listCustomers,
} from "@/lib/customer/service";

import {
  createInvoice,
  listInvoices,
} from "@/lib/invoice/service";

import {
  getModule,
} from "@/lib/module/registry";

import type {
  CatalogItem,
  CatalogPricingMethod,
  CatalogUnit,
} from "@/types/catalog";

import type {
  Customer,
} from "@/types/customer";

import type {
  Invoice,
  InvoiceCreatePayload,
  InvoiceStatus,
} from "@/types/invoice";

import styles from "./invoice.module.css";

type StatusFilter =
  | "ALL"
  | InvoiceStatus;

type DimensionField =
  | "width"
  | "height"
  | "depth"
  | "length"
  | "duration";

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
  dueAt: string;
  notes: string;
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
    value: "ISSUED",
    label: "Terbit",
  },
  {
    value: "PARTIALLY_PAID",
    label: "Dibayar Sebagian",
  },
  {
    value: "PAID",
    label: "Lunas",
  },
  {
    value: "VOID",
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
    dueAt: "",
    notes: "",
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

function rupiah(
  value:
    | number
    | string,
): string {
  return new Intl.NumberFormat(
    "id-ID",
    {
      style: "currency",
      currency: "IDR",
      maximumFractionDigits: 0,
    },
  ).format(
    Number(value) || 0,
  );
}

function localDate(
  value:
    | string
    | null,
): string {
  if (!value) {
    return "Belum ditentukan";
  }

  const date =
    new Date(value);

  if (
    Number.isNaN(
      date.getTime(),
    )
  ) {
    return value;
  }

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
  method:
    CatalogPricingMethod,
): DimensionField[] {
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

function dimensionLabel(
  field:
    DimensionField,
): string {
  switch (field) {
    case "width":
      return "Lebar";

    case "height":
      return "Tinggi";

    case "depth":
      return "Tebal / Kedalaman";

    case "length":
      return "Panjang";

    case "duration":
      return "Durasi";

    default:
      return field;
  }
}

function errorMessage(
  error: unknown,
): string {
  if (
    error instanceof Error
  ) {
    return error.message;
  }

  return "Permintaan belum berhasil diproses.";
}

function requestId(
  error: unknown,
): string | null {
  return error instanceof ApiClientError
    ? error.requestId
    : null;
}

function estimateLine(
  line: LineForm,
  item:
    | CatalogItem
    | undefined,
): {
  subtotal: number;
  discount: number;
  tax: number;
  total: number;
} {
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

  let multiplier =
    quantity;

  switch (
    item?.pricing_method
  ) {
    case "AREA":
      multiplier =
        quantity *
        Math.max(
          numeric(line.width),
          0,
        ) *
        Math.max(
          numeric(line.height),
          0,
        );
      break;

    case "LENGTH":
      multiplier =
        quantity *
        Math.max(
          numeric(line.length),
          0,
        );
      break;

    case "VOLUME":
      multiplier =
        quantity *
        Math.max(
          numeric(line.width),
          0,
        ) *
        Math.max(
          numeric(line.height),
          0,
        ) *
        Math.max(
          numeric(line.depth),
          0,
        );
      break;

    case "TIME":
      multiplier =
        quantity *
        Math.max(
          numeric(line.duration),
          0,
        );
      break;

    default:
      break;
  }

  const subtotal =
    multiplier *
    unitPrice;

  const discount =
    Math.min(
      Math.max(
        numeric(
          line.discountAmount,
        ),
        0,
      ),
      subtotal,
    );

  const taxable =
    Math.max(
      subtotal -
      discount,
      0,
    );

  const tax =
    taxable *
    (
      Math.max(
        numeric(
          line.taxRate,
        ),
        0,
      ) /
      100
    );

  return {
    subtotal,
    discount,
    tax,
    total:
      taxable + tax,
  };
}

export default function InvoicePage() {
  const moduleDef =
    getModule("invoices");

  const ModuleIcon =
    moduleDef.icon;

  const [
    invoices,
    setInvoices,
  ] = useState<
    Invoice[]
  >([]);

  const [
    customers,
    setCustomers,
  ] = useState<
    Customer[]
  >([]);

  const [
    catalogItems,
    setCatalogItems,
  ] = useState<
    CatalogItem[]
  >([]);

  const [
    units,
    setUnits,
  ] = useState<
    CatalogUnit[]
  >([]);

  const [
    search,
    setSearch,
  ] = useState("");

  const [
    status,
    setStatus,
  ] = useState<
    StatusFilter
  >("ALL");

  const [
    page,
    setPage,
  ] = useState(1);

  const [
    total,
    setTotal,
  ] = useState(0);

  const [
    lastPage,
    setLastPage,
  ] = useState(1);

  const [
    loading,
    setLoading,
  ] = useState(true);

  const [
    error,
    setError,
  ] = useState<unknown>(
    null,
  );

  const [
    masterError,
    setMasterError,
  ] = useState<unknown>(
    null,
  );

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
  ] = useState<unknown>(
    null,
  );

  const [
    success,
    setSuccess,
  ] = useState<
    string | null
  >(null);

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

  const loadInvoices =
    useCallback(
      async (
        silent = false,
      ) => {
        if (!silent) {
          setLoading(true);
        }

        setError(null);

        try {
          const response =
            await listInvoices({
              search:
                search.trim()
                || undefined,

              status:
                status === "ALL"
                  ? undefined
                  : status,

              page,
              per_page: 20,
            });

          setInvoices(
            response.data,
          );

          setTotal(
            response.meta.total,
          );

          setLastPage(
            response.meta
              .last_page,
          );
        } catch (caught) {
          setError(caught);
        } finally {
          if (!silent) {
            setLoading(false);
          }
        }
      },
      [
        search,
        status,
        page,
      ],
    );

  useEffect(() => {
    const timer =
      window.setTimeout(
        () => {
          void loadInvoices();
        },
        280,
      );

    return () => {
      window.clearTimeout(
        timer,
      );
    };
  }, [
    loadInvoices,
  ]);

  useEffect(() => {
    let cancelled =
      false;

    async function loadMasterData() {
      setMasterError(null);

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
      } catch (caught) {
        if (!cancelled) {
          setMasterError(
            caught,
          );
        }
      }
    }

    void loadMasterData();

    return () => {
      cancelled = true;
    };
  }, []);


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

  const estimatedTotals =
    useMemo(
      () => {
        let subtotal = 0;
        let discount = 0;
        let tax = 0;
        let totalValue = 0;

        for (
          const line
          of form.lines
        ) {
          const estimate =
            estimateLine(
              line,
              itemMap.get(
                line.catalogItemId,
              ),
            );

          subtotal +=
            estimate.subtotal;

          discount +=
            estimate.discount;

          tax +=
            estimate.tax;

          totalValue +=
            estimate.total;
        }

        return {
          subtotal,
          discount,
          tax,
          total:
            totalValue,
        };
      },
      [
        form.lines,
        itemMap,
      ],
    );

  function openEditor() {
    setForm(
      emptyForm(),
    );

    setSaveError(null);
    setEditorOpen(true);
  }

  function closeEditor() {
    if (saving) {
      return;
    }

    setEditorOpen(false);
    setSaveError(null);
  }

  function patchLine(
    key: string,
    patch:
      Partial<LineForm>,
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

    patchLine(
      key,
      {
        catalogItemId,

        unitPrice:
          item
            ? String(
                item.base_price
                ?? "",
              )
            : "",
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
      (current) => {
        if (
          current.lines.length
          <= 1
        ) {
          return current;
        }

        return {
          ...current,

          lines:
            current.lines.filter(
              (line) =>
                line.key !== key,
            ),
        };
      },
    );
  }

  async function handleSubmit(
    event:
      FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

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
          "Pilih barang atau jasa pada setiap baris.",
        ),
      );
      return;
    }

    if (
      form.lines.some(
        (line) =>
          numeric(
            line.quantity,
          ) <= 0,
      )
    ) {
      setSaveError(
        new Error(
          "Jumlah item harus lebih dari 0.",
        ),
      );
      return;
    }

    for (
      const line
      of form.lines
    ) {
      const item =
        itemMap.get(
          line.catalogItemId,
        );

      if (!item) {
        setSaveError(
          new Error(
            "Barang atau jasa tidak ditemukan.",
          ),
        );
        return;
      }

      for (
        const field
        of pricingInputs(
          item.pricing_method,
        )
      ) {
        if (
          numeric(
            line[field],
          ) <= 0
        ) {
          setSaveError(
            new Error(
              `${dimensionLabel(
                field,
              )} wajib lebih dari 0 untuk ${item.name}.`,
            ),
          );
          return;
        }
      }
    }

    const payload:
      InvoiceCreatePayload = {
        customer_id:
          form.customerId,

        due_at:
          form.dueAt
            || null,

        notes:
          form.notes.trim()
            || null,

        items:
          form.lines.map(
            (
              line,
              index,
            ) => {
              const item =
                itemMap.get(
                  line.catalogItemId,
                )!;

              const dimensions =
                pricingInputs(
                  item.pricing_method,
                );

              const pricingConfig:
                Record<
                  string,
                  number
                > = {};

              for (
                const field
                of dimensions
              ) {
                pricingConfig[field] =
                  numeric(
                    line[field],
                  );
              }

              return {
                catalog_item_id:
                  item.id,

                quantity:
                  numeric(
                    line.quantity,
                  ),

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

                pricing_config:
                  dimensions.length > 0
                    ? pricingConfig
                    : null,

                sort_order:
                  index,
              };
            },
          ),
      };

    setSaving(true);

    try {
      const response =
        await createInvoice(
          payload,
        );

      setSuccess(
        `Tagihan ${response.data.invoice_number} berhasil dibuat sebagai draf.`,
      );

      setEditorOpen(false);
      setForm(
        emptyForm(),
      );

      await loadInvoices(
        true,
      );
    } catch (caught) {
      setSaveError(caught);
    } finally {
      setSaving(false);
    }
  }

  return (
    <TenantShell>
      <main
        className={
          styles.page
        }
      >
        <ModuleHero
          eyebrow="Penjualan"
          title="Tagihan"
          description="Buat dan pantau tagihan pelanggan dari barang atau jasa yang sudah tersimpan."
          icon={ModuleIcon}
          tone="amber"
          insightTitle="Tagihan rapi membantu arus kas"
          insightDescription="Simpan sebagai draf terlebih dahulu. Nomor dan total final dikendalikan oleh SIGNOVA."
          actions={
            <Button
              type="button"
              leadingIcon={
                <Plus
                  size={18}
                />
              }
              onClick={
                openEditor
              }
            >
              Buat Tagihan
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
              type="search"
              value={search}
              placeholder="Cari nomor tagihan atau pelanggan..."
              aria-label="Cari tagihan"
              onChange={
                (event) => {
                  setSearch(
                    event.target.value,
                  );
                  setPage(1);
                }
}
            />
          </label>

          <select
            className={
              styles.filter
            }
            value={status}
            aria-label="Filter status tagihan"
            onChange={
              (event) => {
                  setStatus(
                    event.target.value as StatusFilter,
                  );
                  setPage(1);
                }
}
          >
            {statusOptions.map(
              (option) => (
                <option
                  key={
                    option.value
                  }
                  value={
                    option.value
                  }
                >
                  {option.label}
                </option>
              ),
            )}
          </select>
        </section>

        {error ? (
          <ActionFeedback
            tone="error"
            title="Tagihan belum dapat dimuat"
            message={
              errorMessage(
                error,
              )
            }
            requestId={
              requestId(
                error,
              )
            }
          />
        ) : null}

        <section
          className={
            styles.listCard
          }
        >
          <div
            className={
              styles.listHeader
            }
          >
            <div>
              <strong>
                Daftar Tagihan
              </strong>

              <span>
                {loading
                  ? "Memuat..."
                  : `${total} tagihan`}
              </span>
            </div>
          </div>

          {!loading &&
          invoices.length === 0 ? (
            <div
              className={
                styles.empty
              }
            >
              <FileText
                size={34}
                aria-hidden="true"
              />

              <strong>
                Belum ada tagihan
              </strong>

              <p>
                Buat tagihan pertama
                dari pelanggan dan
                barang atau jasa yang
                sudah tersimpan.
              </p>

              <Button
                type="button"
                leadingIcon={
                  <Plus
                    size={18}
                  />
                }
                onClick={
                  openEditor
                }
              >
                Buat Tagihan
              </Button>
            </div>
          ) : (
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
                        Tagihan
                      </th>
                      <th>
                        Pelanggan
                      </th>
                      <th>
                        Jatuh Tempo
                      </th>
                      <th>
                        Status
                      </th>
                      <th
                        className={
                          styles.amount
                        }
                      >
                        Total
                      </th>
                    </tr>
                  </thead>

                  <tbody>
                    {invoices.map(
                      (invoice) => (
                        <tr
                          key={
                            invoice.id
                          }
                        >
                          <td>
                            <Link
                              href={`/app/tagihan/${invoice.id}`}
                              className={
                                styles.detailLink
                              }
                            >
                              {
                                invoice.invoice_number
                              }
                            </Link>

                            <small>
                              Dibuat{" "}
                              {localDate(
                                invoice.created_at,
                              )}
                            </small>
                          </td>

                          <td>
                            {
                              invoice.customer
                                ?.name
                              ?? "-"
                            }
                          </td>

                          <td>
                            {localDate(
                              invoice.due_at,
                            )}
                          </td>

                          <td>
                            <span
                              className={
                                styles.badge
                              }
                              data-status={
                                invoice.status
                              }
                            >
                              {
                                invoice.status_label
                              }
                            </span>
                          </td>

                          <td
                            className={
                              styles.amount
                            }
                          >
                            {rupiah(
                              invoice.total,
                            )}
                          </td>
                        </tr>
                      ),
                    )}
                  </tbody>
                </table>
              </div>

              <div
                className={
                  styles.mobileList
                }
              >
                {invoices.map(
                  (invoice) => (
                    <article
                      className={
                        styles.mobileCard
                      }
                      key={
                        invoice.id
                      }
                    >
                      <div
                        className={
                          styles.mobileTop
                        }
                      >
                        <div>
                          <Link
                            href={`/app/tagihan/${invoice.id}`}
                            className={
                              styles.detailLink
                            }
                          >
                            {
                              invoice.invoice_number
                            }
                          </Link>

                          <span>
                            {
                              invoice.customer
                                ?.name
                              ?? "Tanpa pelanggan"
                            }
                          </span>
                        </div>

                        <span
                          className={
                            styles.badge
                          }
                          data-status={
                            invoice.status
                          }
                        >
                          {
                            invoice.status_label
                          }
                        </span>
                      </div>

                      <div
                        className={
                          styles.mobileMeta
                        }
                      >
                        <span>
                          Jatuh tempo
                          <strong>
                            {localDate(
                              invoice.due_at,
                            )}
                          </strong>
                        </span>

                        <span>
                          Total
                          <strong>
                            {rupiah(
                              invoice.total,
                            )}
                          </strong>
                        </span>
                      </div>
                    </article>
                  ),
                )}
              </div>
            </>
          )}

          {lastPage > 1 ? (
            <div
              className={
                styles.pagination
              }
            >
              <Button
                type="button"
                variant="ghost"
                disabled={
                  page <= 1
                }
                leadingIcon={
                  <ChevronLeft
                    size={18}
                  />
                }
                onClick={
                  () =>
                    setPage(
                      (current) =>
                        Math.max(
                          1,
                          current - 1,
                        ),
                    )
                }
              >
                Sebelumnya
              </Button>

              <span>
                Halaman {page} dari{" "}
                {lastPage}
              </span>

              <Button
                type="button"
                variant="ghost"
                disabled={
                  page >= lastPage
                }
                onClick={
                  () =>
                    setPage(
                      (current) =>
                        Math.min(
                          lastPage,
                          current + 1,
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
          ) : null}
        </section>
      </main>

      {editorOpen ? (
        <div
          className={
            styles.overlay
          }
          role="presentation"
        >
          <section
            className={
              styles.editor
            }
            role="dialog"
            aria-modal="true"
            aria-labelledby="invoice-editor-title"
          >
            <header
              className={
                styles.editorHeader
              }
            >
              <div>
                <span>
                  Tagihan Baru
                </span>

                <h2
                  id="invoice-editor-title"
                >
                  Buat Tagihan
                </h2>

                <p>
                  Pilih pelanggan lalu
                  tambahkan barang atau
                  jasa dari katalog
                  usaha aktif.
                </p>
              </div>

              <button
                type="button"
                className={
                  styles.close
                }
                aria-label="Tutup form tagihan"
                onClick={
                  closeEditor
                }
              >
                <X
                  size={22}
                />
              </button>
            </header>

            <form
              className={
                styles.form
              }
              onSubmit={
                handleSubmit
              }
            >
              {masterError ? (
                <ActionFeedback
                  tone="warning"
                  title="Data referensi belum lengkap"
                  message={
                    errorMessage(
                      masterError,
                    )
                  }
                  requestId={
                    requestId(
                      masterError,
                    )
                  }
                />
              ) : null}

              {saveError ? (
                <ActionFeedback
                  tone="error"
                  title="Tagihan belum dapat disimpan"
                  message={
                    errorMessage(
                      saveError,
                    )
                  }
                  requestId={
                    requestId(
                      saveError,
                    )
                  }
                />
              ) : null}

              <div
                className={
                  styles.formGrid
                }
              >
                <label
                  className={
                    styles.field
                  }
                >
                  <span>
                    Pelanggan *
                  </span>

                  <select
                    required
                    value={
                      form.customerId
                    }
                    onChange={
                      (event) =>
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
                      (customer) => (
                        <option
                          key={
                            customer.id
                          }
                          value={
                            customer.id
                          }
                        >
                          {customer.name}
                          {customer.code
                            ? ` — ${customer.code}`
                            : ""}
                        </option>
                      ),
                    )}
                  </select>
                </label>

                <label
                  className={
                    styles.field
                  }
                >
                  <span>
                    Jatuh Tempo
                  </span>

                  <input
                    type="date"
                    value={
                      form.dueAt
                    }
                    onChange={
                      (event) =>
                        setForm(
                          (
                            current,
                          ) => ({
                            ...current,
                            dueAt:
                              event
                                .target
                                .value,
                          }),
                        )
                    }
                  />
                </label>
              </div>

              <div
                className={
                  styles.itemsHeader
                }
              >
                <div>
                  <strong>
                    Item Tagihan
                  </strong>

                  <p>
                    Harga katalog
                    otomatis digunakan
                    dan tetap dapat
                    disesuaikan.
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

                    const estimate =
                      estimateLine(
                        line,
                        item,
                      );

                    return (
                      <article
                        className={
                          styles.line
                        }
                        key={
                          line.key
                        }
                      >
                        <div
                          className={
                            styles.lineTitle
                          }
                        >
                          <strong>
                            Item{" "}
                            {index + 1}
                          </strong>

                          {form.lines
                            .length >
                          1 ? (
                            <button
                              type="button"
                              className={
                                styles.remove
                              }
                              aria-label={`Hapus item ${index + 1}`}
                              onClick={
                                () =>
                                  removeLine(
                                    line.key,
                                  )
                              }
                            >
                              <Trash2
                                size={
                                  17
                                }
                              />
                            </button>
                          ) : null}
                        </div>

                        <label
                          className={
                            styles.field
                          }
                        >
                          <span>
                            Barang /
                            Jasa *
                          </span>

                          <select
                            required
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
                              Pilih dari
                              katalog
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
                                  {
                                    catalogItem.code
                                      ? ` — ${catalogItem.code}`
                                      : ""
                                  }
                                </option>
                              ),
                            )}
                          </select>
                        </label>

                        {item ? (
                          <div
                            className={
                              styles.catalogHint
                            }
                          >
                            <span>
                              {
                                item.pricing_method
                              }
                            </span>

                            <span>
                              Satuan:{" "}
                              {unit
                                ?.symbol
                                ?? unit
                                  ?.name
                                ?? "-"}
                            </span>

                            <span>
                              Harga acuan:{" "}
                              {rupiah(
                                item.base_price,
                              )}
                            </span>
                          </div>
                        ) : null}

                        {dimensions.length >
                        0 ? (
                          <div
                            className={
                              styles.dimensionGrid
                            }
                          >
                            {dimensions.map(
                              (
                                field,
                              ) => (
                                <label
                                  className={
                                    styles.field
                                  }
                                  key={
                                    field
                                  }
                                >
                                  <span>
                                    {dimensionLabel(
                                      field,
                                    )}
                                  </span>

                                  <input
                                    type="number"
                                    min="0"
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
                                        patchLine(
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
                        ) : null}

                        <div
                          className={
                            styles.numberGrid
                          }
                        >
                          <label
                            className={
                              styles.field
                            }
                          >
                            <span>
                              Jumlah *
                            </span>

                            <input
                              required
                              type="number"
                              min="0.0001"
                              step="any"
                              value={
                                line.quantity
                              }
                              onChange={
                                (
                                  event,
                                ) =>
                                  patchLine(
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

                          <label
                            className={
                              styles.field
                            }
                          >
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
                                  patchLine(
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

                          <label
                            className={
                              styles.field
                            }
                          >
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
                                  patchLine(
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

                          <label
                            className={
                              styles.field
                            }
                          >
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
                                  patchLine(
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
                        </div>

                        <div
                          className={
                            styles.lineTotal
                          }
                        >
                          <span>
                            Perkiraan
                            item
                          </span>

                          <strong>
                            {rupiah(
                              estimate.total,
                            )}
                          </strong>
                        </div>
                      </article>
                    );
                  },
                )}
              </div>

              <label
                className={
                  styles.field
                }
              >
                <span>
                  Catatan
                </span>

                <textarea
                  rows={3}
                  value={
                    form.notes
                  }
                  placeholder="Catatan internal atau informasi tambahan pada tagihan..."
                  onChange={
                    (event) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          notes:
                            event.target
                              .value,
                        }),
                      )
                  }
                />
              </label>

              <section
                className={
                  styles.summary
                }
              >
                <div>
                  <span>
                    Subtotal
                  </span>

                  <strong>
                    {rupiah(
                      estimatedTotals
                        .subtotal,
                    )}
                  </strong>
                </div>

                <div>
                  <span>
                    Diskon
                  </span>

                  <strong>
                    -{" "}
                    {rupiah(
                      estimatedTotals
                        .discount,
                    )}
                  </strong>
                </div>

                <div>
                  <span>
                    Pajak
                  </span>

                  <strong>
                    {rupiah(
                      estimatedTotals
                        .tax,
                    )}
                  </strong>
                </div>

                <div
                  className={
                    styles.grandTotal
                  }
                >
                  <span>
                    Perkiraan Total
                  </span>

                  <strong>
                    {rupiah(
                      estimatedTotals
                        .total,
                    )}
                  </strong>
                </div>

                <p>
                  Nilai final dihitung
                  ulang oleh server saat
                  tagihan disimpan.
                </p>
              </section>

              <footer
                className={
                  styles.editorFooter
                }
              >
                <Button
                  type="button"
                  variant="ghost"
                  disabled={saving}
                  onClick={
                    closeEditor
                  }
                >
                  Batal
                </Button>

                <Button
                  type="submit"
                  loading={saving}
                  loadingLabel="Menyimpan Tagihan..."
                >
                  Simpan Draf
                </Button>
              </footer>
            </form>
          </section>
        </div>
      ) : null}
    </TenantShell>
  );
}
