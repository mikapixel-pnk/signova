"use client";

import {
  ArrowLeft,
  FileText,
  Plus,
  ReceiptText,
  Save,
  Search,
  Trash2,
} from "lucide-react";

import Link from "next/link";

import {
  type FormEvent,
  useEffect,
  useRef,
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

import {
  ActionFeedback,
} from "@/components/feedback/action-feedback";

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
  getActiveCapabilities,
} from "@/lib/auth/active-capabilities-service";

import {
  isNonNegativeDecimal,
  isPositiveDecimal,
} from "@/lib/format/decimal";

import {
  getModule,
} from "@/lib/module/registry";

import {
  formatPurchaseMoney,
  procurementTypeLabel,
} from "@/lib/purchasing/labels";

import {
  createPurchaseOrder,
  getPurchaseOrder,
  getPurchaseRequest,
  updatePurchaseOrder,
} from "@/lib/purchasing/service";

import {
  listSuppliers,
} from "@/lib/supplier/service";

import type {
  ProcurementType,
  PurchaseOrderItemPayload,
  PurchaseRequest,
} from "@/types/purchasing";

import type {
  Supplier,
} from "@/types/supplier";

import styles from "./purchase-order.module.css";


type FormItem = {
  key: string;
  source_purchase_request_item_id:
    | string
    | null;
  material_id:
    | string
    | null;
  procurement_type:
    ProcurementType;
  name: string;
  description: string;
  quantity: string;
  unit_price: string;
  discount_amount: string;
  tax_amount: string;
  unit_symbol:
    | string
    | null;
};


type PurchaseOrderFormProps = {
  purchaseOrderId?: string;
  sourcePurchaseRequestId?: string;
};


function initialItem(
  key = "item-1",
): FormItem {
  return {
    key,
    source_purchase_request_item_id:
      null,
    material_id:
      null,
    procurement_type:
      "NON_STOCK_GOOD",
    name: "",
    description: "",
    quantity: "1",
    unit_price: "0",
    discount_amount: "0",
    tax_amount: "0",
    unit_symbol: null,
  };
}


export function PurchaseOrderForm({
  purchaseOrderId,
  sourcePurchaseRequestId,
}: PurchaseOrderFormProps) {
  const router =
    useRouter();

  const moduleDef =
    getModule(
      "purchase-orders",
    );

  const ModuleIcon =
    moduleDef.icon;

  const editing =
    Boolean(
      purchaseOrderId,
    );

  const itemSequence =
    useRef(2);

  const [
    supplierId,
    setSupplierId,
  ] = useState("");

  const [
    supplierSearch,
    setSupplierSearch,
  ] = useState("");

  const [
    suppliers,
    setSuppliers,
  ] = useState<
    Supplier[]
  >([]);

  const [
    supplierLoading,
    setSupplierLoading,
  ] = useState(true);

  const [
    expectedAt,
    setExpectedAt,
  ] = useState("");

  const [
    notes,
    setNotes,
  ] = useState("");

  const [
    sourceRequest,
    setSourceRequest,
  ] = useState<
    PurchaseRequest | null
  >(null);

  const [
    items,
    setItems,
  ] = useState<FormItem[]>([
    initialItem(),
  ]);

  const [
    loading,
    setLoading,
  ] = useState(
    Boolean(
      purchaseOrderId ||
      sourcePurchaseRequestId,
    ),
  );

  const [
    saving,
    setSaving,
  ] = useState(false);

  const [
    error,
    setError,
  ] = useState<unknown>(
    null,
  );

  const [
    canManage,
    setCanManage,
  ] = useState(false);


  useEffect(() => {
    let cancelled = false;

    void getActiveCapabilities()
      .then((response) => {
        if (cancelled) {
          return;
        }

        setCanManage(
          response.data
            .capability_codes
            .includes(
              "purchasing.create_po",
            ),
        );
      })
      .catch(() => {
        if (!cancelled) {
          setCanManage(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);


  useEffect(() => {
    let cancelled = false;

    const timer =
      window.setTimeout(
        () => {
          void listSuppliers({
            search:
              supplierSearch ||
              undefined,
            status:
              "ACTIVE",
          })
            .then((response) => {
              if (cancelled) {
                return;
              }

              setSuppliers(
                response.data,
              );
            })
            .catch((caught) => {
              if (!cancelled) {
                setError(caught);
              }
            })
            .finally(() => {
              if (!cancelled) {
                setSupplierLoading(
                  false,
                );
              }
            });
        },
        250,
      );

    return () => {
      cancelled = true;
      window.clearTimeout(
        timer,
      );
    };
  }, [
    supplierSearch,
  ]);


  useEffect(() => {
    if (!sourcePurchaseRequestId ||
        purchaseOrderId) {
      return;
    }

    let cancelled = false;

    void getPurchaseRequest(
      sourcePurchaseRequestId,
    )
      .then((response) => {
        if (cancelled) {
          return;
        }

        if (
          response.data.status !==
          "APPROVED"
        ) {
          throw new Error(
            "Hanya Permintaan Pembelian yang sudah disetujui yang dapat dilanjutkan ke Pesanan Pembelian.",
          );
        }

        setSourceRequest(
          response.data,
        );
      })
      .catch((caught) => {
        if (!cancelled) {
          setError(caught);
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [
    sourcePurchaseRequestId,
    purchaseOrderId,
  ]);


  useEffect(() => {
    if (!purchaseOrderId) {
      return;
    }

    let cancelled = false;

    void getPurchaseOrder(
      purchaseOrderId,
    )
      .then((response) => {
        if (cancelled) {
          return;
        }

        const po =
          response.data;

        if (
          po.status !==
          "DRAFT"
        ) {
          throw new Error(
            "Hanya Pesanan Pembelian berstatus Draf yang dapat diubah.",
          );
        }

        setSupplierId(
          po.supplier_id,
        );

        if (po.supplier) {
          setSuppliers(
            (current) => {
              if (
                current.some(
                  (supplier) =>
                    supplier.id ===
                    po.supplier?.id,
                )
              ) {
                return current;
              }

              return [
                po.supplier as Supplier,
                ...current,
              ];
            },
          );
        }

        setExpectedAt(
          po.expected_at ??
          "",
        );

        setNotes(
          po.notes ??
          "",
        );

        if (
          po.source_purchase_request
        ) {
          setSourceRequest({
            id:
              po.source_purchase_request.id,
            request_number:
              po.source_purchase_request
                .request_number,
            status:
              po.source_purchase_request
                .status,
            needed_at:
              null,
            currency:
              "IDR",
            estimated_total:
              "0.00",
            notes:
              null,
            submitted_at:
              null,
            approved_at:
              null,
            rejected_at:
              null,
            rejection_reason:
              null,
            cancelled_at:
              null,
            cancellation_reason:
              null,
          });
        }

        const loadedItems =
          (
            po.items ??
            []
          ).map(
            (
              item,
              index,
            ): FormItem => ({
              key:
                `existing-${index}-${item.id}`,

              source_purchase_request_item_id:
                item
                  .source_purchase_request_item_id,

              material_id:
                item.material_id,

              procurement_type:
                item.procurement_type,

              name:
                item.name,

              description:
                item.description ??
                "",

              quantity:
                item.quantity,

              unit_price:
                item.unit_price,

              discount_amount:
                item.discount_amount,

              tax_amount:
                item.tax_amount,

              unit_symbol:
                item.unit_symbol,
            }),
          );

        setItems(
          loadedItems.length > 0
            ? loadedItems
            : [
                initialItem(),
              ],
        );
      })
      .catch((caught) => {
        if (!cancelled) {
          setError(caught);
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [
    purchaseOrderId,
  ]);


  function updateItem(
    key: string,
    field:
      | "procurement_type"
      | "name"
      | "description"
      | "quantity"
      | "unit_price"
      | "discount_amount"
      | "tax_amount",
    value: string,
  ) {
    setItems(
      (current) =>
        current.map(
          (item) =>
            item.key === key
              ? {
                  ...item,
                  [field]:
                    value,
                }
              : item,
        ),
    );
  }


  function addItem() {
    const key =
      `item-${itemSequence.current}`;

    itemSequence.current += 1;

    setItems(
      (current) => [
        ...current,
        initialItem(key),
      ],
    );
  }


  function removeItem(
    key: string,
  ) {
    setItems(
      (current) =>
        current.length <= 1
          ? current
          : current.filter(
              (item) =>
                item.key !== key,
            ),
    );
  }


  async function handleSubmit(
    event:
      FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    if (
      saving ||
      !canManage
    ) {
      return;
    }

    setError(null);

    if (!supplierId) {
      setError(
        new Error(
          "Pilih pemasok terlebih dahulu.",
        ),
      );

      return;
    }

    const createFromSource =
      !purchaseOrderId &&
      Boolean(
        sourcePurchaseRequestId,
      );

    if (!createFromSource) {
      const invalidItem =
        items.find(
          (item) =>
            item.name.trim() ===
              "" ||
            !isPositiveDecimal(
              item.quantity,
              4,
            ) ||
            !isNonNegativeDecimal(
              item.unit_price,
            ) ||
            !isNonNegativeDecimal(
              item.discount_amount,
            ) ||
            !isNonNegativeDecimal(
              item.tax_amount,
            ),
        );

      if (invalidItem) {
        setError(
          new Error(
            "Lengkapi item, jumlah lebih dari 0, serta harga, diskon, dan pajak dengan nilai yang valid.",
          ),
        );

        return;
      }
    }

    const payloadItems:
      PurchaseOrderItemPayload[] =
      items.map(
        (
          item,
          index,
        ) => {
          if (
            item
              .source_purchase_request_item_id
          ) {
            return {
              source_purchase_request_item_id:
                item
                  .source_purchase_request_item_id,

              quantity:
                item.quantity,

              unit_price:
                item.unit_price,

              discount_amount:
                item.discount_amount,

              tax_amount:
                item.tax_amount,

              sort_order:
                index,
            };
          }

          return {
            material_id:
              item.material_id,

            procurement_type:
              item.procurement_type,

            item_type:
              item.procurement_type ===
              "SERVICE"
                ? "SERVICE"
                : "PRODUCT",

            name:
              item.name.trim(),

            description:
              item.description
                .trim() ||
              null,

            quantity:
              item.quantity,

            unit_price:
              item.unit_price,

            discount_amount:
              item.discount_amount,

            tax_amount:
              item.tax_amount,

            sort_order:
              index,
          };
        },
      );

    setSaving(true);

    try {
      if (purchaseOrderId) {
        const response =
          await updatePurchaseOrder(
            purchaseOrderId,
            {
              supplier_id:
                supplierId,

              expected_at:
                expectedAt ||
                null,

              currency:
                "IDR",

              notes:
                notes.trim() ||
                null,

              items:
                payloadItems,
            },
          );

        router.push(
          "/app/operasional/pesanan-pembelian",
        );

        router.refresh();

        return response;
      }

      if (
        sourcePurchaseRequestId
      ) {
        const response =
          await createPurchaseOrder({
            supplier_id:
              supplierId,

            source_purchase_request_id:
              sourcePurchaseRequestId,

            expected_at:
              expectedAt ||
              null,

            currency:
              "IDR",

            notes:
              notes.trim() ||
              null,
          });

        router.push(
          `/app/operasional/pesanan-pembelian/${response.data.id}/ubah`,
        );

        return;
      }

      await createPurchaseOrder({
        supplier_id:
          supplierId,

        expected_at:
          expectedAt ||
          null,

        currency:
          "IDR",

        notes:
          notes.trim() ||
          null,

        items:
          payloadItems,
      });

      router.push(
        "/app/operasional/pesanan-pembelian",
      );

      router.refresh();
    } catch (caught) {
      setError(caught);
    } finally {
      setSaving(false);
    }
  }


  if (loading) {
    return (
      <section
        className={
          styles.page
        }
      >
        <div
          className={
            styles.formLoading
          }
        >
          Memuat Pesanan Pembelian...
        </div>
      </section>
    );
  }


  const sourceLinked =
    Boolean(
      sourcePurchaseRequestId ||
      sourceRequest,
    );


  return (
    <section
      className={
        styles.page
      }
    >
      <Link
        href="/app/operasional/pesanan-pembelian"
        className={
          styles.backLink
        }
      >
        <ArrowLeft
          size={17}
        />
        Pesanan Pembelian
      </Link>

      <ModuleHero
        eyebrow="Operasional"
        title={
          editing
            ? "Ubah Draf Pesanan Pembelian"
            : sourceLinked
              ? "Buat Pesanan dari Permintaan"
              : "Buat Pesanan Pembelian"
        }
        description={
          sourceLinked
            ? "Pilih pemasok. SIGNOVA membawa sisa kebutuhan dari Permintaan Pembelian agar tidak perlu diketik ulang."
            : "Catat pesanan resmi kepada pemasok sebelum barang atau jasa diterima."
        }
        icon={
          ModuleIcon
        }
        tone={
          moduleDef.tone
        }
        insightTitle="Simpan sebagai Draf"
        insightDescription={
          sourceLinked
            ? "Sisa item dihitung server. Setelah draf dibuat, periksa jumlah dan harga final sebelum diterbitkan."
            : "Nomor dokumen dan total dihitung SIGNOVA saat draf disimpan."
        }
      />

      {!canManage ? (
        <ActionFeedback
          tone="warning"
          title="Akses terbatas"
          message="Anda tidak memiliki hak untuk membuat atau mengubah Pesanan Pembelian."
        />
      ) : null}

      {error ? (
        <ActionFeedback
          tone="error"
          title="Pesanan belum dapat disimpan"
          message={
            apiErrorMessage(
              error,
              error instanceof Error
                ? error.message
                : "Periksa kembali data Pesanan Pembelian.",
            )
          }
          requestId={
            apiRequestId(
              error,
            )
          }
        />
      ) : null}

      <form
        className={
          styles.poForm
        }
        onSubmit={
          (event) =>
            void handleSubmit(
              event,
            )
        }
      >
        {sourceRequest ? (
          <section
            className={
              styles.sourceCard
            }
          >
            <FileText
              size={21}
            />

            <div>
              <span>
                SUMBER PERMINTAAN
              </span>

              <strong>
                {
                  sourceRequest
                    .request_number
                }
              </strong>

              {!editing ? (
                <p>
                  Total estimasi awal{" "}
                  {formatPurchaseMoney(
                    sourceRequest
                      .estimated_total,
                  )}
                  . SIGNOVA hanya akan
                  membawa sisa item yang
                  belum dialokasikan ke PO.
                </p>
              ) : (
                <p>
                  Item pada draf ini tetap
                  terhubung ke Permintaan
                  Pembelian sumber.
                </p>
              )}
            </div>
          </section>
        ) : null}

        <section
          className={
            styles.formCard
          }
        >
          <header
            className={
              styles.formCardHeader
            }
          >
            <ReceiptText
              size={20}
            />

            <div>
              <h2>
                Informasi Pesanan
              </h2>

              <p>
                Tentukan pemasok dan
                perkiraan penerimaan.
              </p>
            </div>
          </header>

          <label
            className={
              styles.field
            }
          >
            <span>
              Cari Pemasok
            </span>

            <div
              className={
                styles.searchField
              }
            >
              <Search
                size={17}
              />

              <input
                value={
                  supplierSearch
                }
                placeholder="Ketik nama atau kode pemasok"
                onChange={
                  (event) => {
                    setSupplierSearch(
                      event.target.value,
                    );

                    setSupplierLoading(
                      true,
                    );
                  }
                }
                disabled={
                  !canManage ||
                  saving
                }
              />
            </div>
          </label>

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
                Pemasok *
              </span>

              <select
                value={
                  supplierId
                }
                onChange={
                  (event) =>
                    setSupplierId(
                      event.target.value,
                    )
                }
                disabled={
                  !canManage ||
                  saving
                }
                required
              >
                <option value="">
                  {supplierLoading
                    ? "Memuat pemasok..."
                    : "Pilih pemasok"}
                </option>

                {suppliers.map(
                  (supplier) => (
                    <option
                      key={
                        supplier.id
                      }
                      value={
                        supplier.id
                      }
                    >
                      {supplier.code
                        ? `${supplier.code} — ${supplier.name}`
                        : supplier.name}
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
                Perkiraan Diterima
              </span>

              <input
                type="date"
                value={
                  expectedAt
                }
                onChange={
                  (event) =>
                    setExpectedAt(
                      event.target.value,
                    )
                }
                disabled={
                  !canManage ||
                  saving
                }
              />
            </label>
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
              value={
                notes
              }
              rows={3}
              maxLength={5000}
              placeholder="Contoh: kirim ke gudang utama, konfirmasi sebelum pengiriman"
              onChange={
                (event) =>
                  setNotes(
                    event.target.value,
                  )
              }
              disabled={
                !canManage ||
                saving
              }
            />
          </label>
        </section>

        {sourcePurchaseRequestId &&
        !editing ? (
          <section
            className={
              styles.guidanceCard
            }
          >
            <strong>
              Item akan dibawa otomatis
            </strong>

            <p>
              Setelah draf dibuat,
              SIGNOVA membuka draf tersebut
              agar Anda dapat memeriksa
              jumlah dan memasukkan harga
              final pemasok.
            </p>
          </section>
        ) : (
          <section
            className={
              styles.formCard
            }
          >
            <header
              className={
                styles.itemsHeader
              }
            >
              <div
                className={
                  styles.formCardHeader
                }
              >
                <ReceiptText
                  size={20}
                />

                <div>
                  <h2>
                    Item Pesanan
                  </h2>

                  <p>
                    Jumlah dan harga yang
                    benar-benar dipesan.
                  </p>
                </div>
              </div>
            </header>

            <div
              className={
                styles.formItemList
              }
            >
              {items.map(
                (
                  item,
                  index,
                ) => {
                  const fromRequest =
                    Boolean(
                      item
                        .source_purchase_request_item_id,
                    );

                  return (
                    <article
                      key={
                        item.key
                      }
                      className={
                        styles.formItemCard
                      }
                    >
                      <div
                        className={
                          styles.formItemTop
                        }
                      >
                        <div>
                          <strong>
                            Item{" "}
                            {index + 1}
                          </strong>

                          {fromRequest ? (
                            <span
                              className={
                                styles.sourceBadge
                              }
                            >
                              Dari PR
                            </span>
                          ) : null}
                        </div>

                        <button
                          type="button"
                          className={
                            styles.removeButton
                          }
                          aria-label={
                            `Hapus item ${index + 1}`
                          }
                          onClick={() =>
                            removeItem(
                              item.key,
                            )
                          }
                          disabled={
                            !canManage ||
                            saving ||
                            items.length <= 1
                          }
                        >
                          <Trash2
                            size={17}
                          />
                        </button>
                      </div>

                      <div
                        className={
                          styles.formItemGrid
                        }
                      >
                        <label
                          className={
                            styles.field
                          }
                        >
                          <span>
                            Jenis Kebutuhan
                          </span>

                          {fromRequest ? (
                            <input
                              value={
                                procurementTypeLabel(
                                  item.procurement_type,
                                )
                              }
                              readOnly
                              disabled
                            />
                          ) : (
                            <select
                              value={
                                item.procurement_type
                              }
                              onChange={
                                (event) =>
                                  updateItem(
                                    item.key,
                                    "procurement_type",
                                    event.target.value,
                                  )
                              }
                              disabled={
                                !canManage ||
                                saving
                              }
                            >
                              <option
                                value="NON_STOCK_GOOD"
                              >
                                Barang Non-Stok
                              </option>

                              <option
                                value="SERVICE"
                              >
                                Jasa Vendor
                              </option>

                              {item.procurement_type ===
                              "INVENTORY_ITEM" ? (
                                <option
                                  value="INVENTORY_ITEM"
                                >
                                  Item Persediaan
                                </option>
                              ) : null}
                            </select>
                          )}
                        </label>

                        <label
                          className={
                            styles.field
                          }
                        >
                          <span>
                            Nama Item *
                          </span>

                          <input
                            value={
                              item.name
                            }
                            readOnly={
                              fromRequest
                            }
                            onChange={
                              (event) =>
                                updateItem(
                                  item.key,
                                  "name",
                                  event.target.value,
                                )
                            }
                            disabled={
                              !canManage ||
                              saving
                            }
                            required
                          />
                        </label>

                        <label
                          className={
                            styles.field
                          }
                        >
                          <span>
                            Jumlah *
                          </span>

                          <input
                            inputMode="decimal"
                            value={
                              item.quantity
                            }
                            onChange={
                              (event) =>
                                updateItem(
                                  item.key,
                                  "quantity",
                                  event.target.value,
                                )
                            }
                            disabled={
                              !canManage ||
                              saving
                            }
                            required
                          />

                          {item.unit_symbol ? (
                            <small>
                              Satuan:{" "}
                              {
                                item.unit_symbol
                              }
                            </small>
                          ) : null}
                        </label>

                        <label
                          className={
                            styles.field
                          }
                        >
                          <span>
                            Harga Satuan *
                          </span>

                          <input
                            inputMode="decimal"
                            value={
                              item.unit_price
                            }
                            onChange={
                              (event) =>
                                updateItem(
                                  item.key,
                                  "unit_price",
                                  event.target.value,
                                )
                            }
                            disabled={
                              !canManage ||
                              saving
                            }
                            required
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
                            inputMode="decimal"
                            value={
                              item.discount_amount
                            }
                            onChange={
                              (event) =>
                                updateItem(
                                  item.key,
                                  "discount_amount",
                                  event.target.value,
                                )
                            }
                            disabled={
                              !canManage ||
                              saving
                            }
                          />
                        </label>

                        <label
                          className={
                            styles.field
                          }
                        >
                          <span>
                            Pajak
                          </span>

                          <input
                            inputMode="decimal"
                            value={
                              item.tax_amount
                            }
                            onChange={
                              (event) =>
                                updateItem(
                                  item.key,
                                  "tax_amount",
                                  event.target.value,
                                )
                            }
                            disabled={
                              !canManage ||
                              saving
                            }
                          />
                        </label>
                      </div>

                      {!fromRequest ? (
                        <label
                          className={
                            styles.field
                          }
                        >
                          <span>
                            Keterangan Item
                          </span>

                          <textarea
                            value={
                              item.description
                            }
                            rows={2}
                            maxLength={5000}
                            onChange={
                              (event) =>
                                updateItem(
                                  item.key,
                                  "description",
                                  event.target.value,
                                )
                            }
                            disabled={
                              !canManage ||
                              saving
                            }
                          />
                        </label>
                      ) : null}
                    </article>
                  );
                },
              )}
            </div>

            {!sourceLinked ? (
              <button
                type="button"
                className={
                  styles.addItemButton
                }
                onClick={
                  addItem
                }
                disabled={
                  !canManage ||
                  saving
                }
              >
                <Plus
                  size={18}
                />
                Tambah Item
              </button>
            ) : null}
          </section>
        )}

        <section
          className={
            styles.formFooter
          }
        >
          <p>
            Total resmi dihitung oleh
            SIGNOVA menggunakan presisi
            transaksi saat draf disimpan.
          </p>

          <Button
            type="submit"
            leadingIcon={
              <Save
                size={18}
              />
            }
            loading={
              saving
            }
            loadingLabel="Menyimpan..."
            disabled={
              !canManage
            }
          >
            {sourcePurchaseRequestId &&
            !editing
              ? "Buat Draf & Atur Harga"
              : "Simpan Draf"}
          </Button>
        </section>
      </form>
    </section>
  );
}
