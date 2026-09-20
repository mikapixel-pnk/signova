"use client";

import {
  ArrowLeft,
  ClipboardList,
  Plus,
  Save,
  Trash2,
} from "lucide-react";

import Link from "next/link";

import {
  type FormEvent,
  useEffect,
  useMemo,
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
  getModule,
} from "@/lib/module/registry";

import {
  formatPurchaseMoney,
} from "@/lib/purchasing/labels";

import {
  createPurchaseRequest,
  getPurchaseRequest,
  updatePurchaseRequest,
} from "@/lib/purchasing/service";

import type {
  PurchaseRequestItemPayload,
} from "@/types/purchasing";

import styles from "./purchase-request.module.css";


type FormItem = {
  key: string;

  item_type:
    | "PRODUCT"
    | "SERVICE";

  name: string;
  description: string;
  quantity: string;
  estimated_unit_price: string;
};


type PurchaseRequestFormProps = {
  purchaseRequestId?: string;
};


function makeInitialItem(
  key = "item-1",
): FormItem {
  return {
    key,
    item_type: "PRODUCT",
    name: "",
    description: "",
    quantity: "1",
    estimated_unit_price: "0",
  };
}


function numericValue(
  value: string,
): number {
  const parsed =
    Number(value);

  return Number.isFinite(parsed)
    ? parsed
    : 0;
}


export function PurchaseRequestForm({
  purchaseRequestId,
}: PurchaseRequestFormProps) {
  const router =
    useRouter();

  const moduleDef =
    getModule(
      "purchase-requests",
    );

  const ModuleIcon =
    moduleDef.icon;

  const editing =
    Boolean(
      purchaseRequestId,
    );

  const itemSequence =
    useRef(2);

  const [
    neededAt,
    setNeededAt,
  ] = useState("");

  const [
    notes,
    setNotes,
  ] = useState("");

  const [
    items,
    setItems,
  ] = useState<FormItem[]>([
    makeInitialItem(),
  ]);

  const [
    loading,
    setLoading,
  ] = useState(editing);

  const [
    saving,
    setSaving,
  ] = useState(false);

  const [
    error,
    setError,
  ] = useState<unknown>(null);

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
              "purchasing.request",
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
    if (!purchaseRequestId) {
      return;
    }

    let cancelled = false;

    void getPurchaseRequest(
      purchaseRequestId,
    )
      .then((response) => {
        if (cancelled) {
          return;
        }

        if (
          response.data.status !==
          "DRAFT"
        ) {
          throw new Error(
            "Hanya Permintaan Pembelian berstatus Draf yang dapat diubah.",
          );
        }

        setNeededAt(
          response.data.needed_at ??
            "",
        );

        setNotes(
          response.data.notes ??
            "",
        );

        const loadedItems =
          (
            response.data.items ??
            []
          ).map(
            (
              item,
              index,
            ) => ({
              key:
                `existing-${index}-${item.id}`,
              item_type:
                item.item_type,
              name:
                item.name,
              description:
                item.description ??
                "",
              quantity:
                item.quantity,
              estimated_unit_price:
                item
                  .estimated_unit_price,
            }),
          );

        setItems(
          loadedItems.length > 0
            ? loadedItems
            : [
                makeInitialItem(),
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
    purchaseRequestId,
  ]);


  const estimatedTotal =
    useMemo(
      () =>
        items.reduce(
          (
            total,
            item,
          ) =>
            total +
            (
              numericValue(
                item.quantity,
              ) *
              numericValue(
                item
                  .estimated_unit_price,
              )
            ),
          0,
        ),
      [items],
    );


  function updateItem(
    key: string,
    field:
      | "item_type"
      | "name"
      | "description"
      | "quantity"
      | "estimated_unit_price",
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
        makeInitialItem(
          key,
        ),
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

    const invalidItem =
      items.find(
        (item) =>
          item.name.trim() ===
            "" ||
          numericValue(
            item.quantity,
          ) <= 0 ||
          numericValue(
            item
              .estimated_unit_price,
          ) < 0,
      );

    if (invalidItem) {
      setError(
        new Error(
          "Lengkapi nama item, jumlah lebih dari 0, dan estimasi harga yang valid.",
        ),
      );

      return;
    }

    const payloadItems:
      PurchaseRequestItemPayload[] =
      items.map(
        (
          item,
          index,
        ) => ({
          item_type:
            item.item_type,

          name:
            item.name.trim(),

          description:
            item.description
              .trim() ||
            null,

          quantity:
            item.quantity,

          estimated_unit_price:
            item
              .estimated_unit_price,

          sort_order:
            index,
        }),
      );

    setSaving(true);

    try {
      const payload = {
        needed_at:
          neededAt ||
          null,

        currency:
          "IDR",

        notes:
          notes.trim() ||
          null,

        items:
          payloadItems,
      };

      const response =
        purchaseRequestId
          ? await updatePurchaseRequest(
              purchaseRequestId,
              payload,
            )
          : await createPurchaseRequest(
              payload,
            );

      router.push(
        `/app/operasional/permintaan-pembelian/${response.data.id}`,
      );
    } catch (caught) {
      setError(caught);
    } finally {
      setSaving(false);
    }
  }


  if (loading) {
    return (
      <section
        className={styles.page}
      >
        <div
          className={
            styles.formLoading
          }
        >
          Memuat Permintaan Pembelian...
        </div>
      </section>
    );
  }


  return (
    <section
      className={styles.page}
    >
      <Link
        href={
          purchaseRequestId
            ? `/app/operasional/permintaan-pembelian/${purchaseRequestId}`
            : "/app/operasional/permintaan-pembelian"
        }
        className={
          styles.backLink
        }
      >
        <ArrowLeft size={17} />
        Kembali
      </Link>

      <ModuleHero
        eyebrow="Operasional"
        title={
          editing
            ? "Ubah Permintaan Pembelian"
            : "Buat Permintaan Pembelian"
        }
        description={
          editing
            ? "Perbarui kebutuhan sebelum Permintaan Pembelian diajukan."
            : "Catat kebutuhan barang atau jasa sebelum proses pembelian dimulai."
        }
        icon={ModuleIcon}
        tone={moduleDef.tone}
        insightTitle="Simpan sebagai Draf"
        insightDescription="Nomor dokumen dan total estimasi dihitung oleh SIGNOVA saat data disimpan."
      />

      {!canManage ? (
        <ActionFeedback
          tone="warning"
          title="Akses terbatas"
          message="Anda tidak memiliki hak untuk membuat atau mengubah Permintaan Pembelian."
        />
      ) : null}

      {error ? (
        <ActionFeedback
          tone="error"
          title="Permintaan belum dapat disimpan"
          message={
            apiErrorMessage(
              error,
              error instanceof Error
                ? error.message
                : "Periksa kembali data Permintaan Pembelian.",
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
          styles.prForm
        }
        onSubmit={
          (event) =>
            void handleSubmit(
              event,
            )
        }
      >
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
            <ClipboardList
              size={20}
            />

            <div>
              <h2>
                Informasi Permintaan
              </h2>

              <p>
                Tentukan tanggal kebutuhan
                dan catatan internal.
              </p>
            </div>
          </header>

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
                Tanggal Dibutuhkan
              </span>

              <input
                type="date"
                value={neededAt}
                onChange={(event) =>
                  setNeededAt(
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
                Mata Uang
              </span>

              <input
                value="IDR"
                readOnly
                disabled
              />
            </label>
          </div>

          <label
            className={
              styles.field
            }
          >
            <span>Catatan</span>

            <textarea
              value={notes}
              onChange={(event) =>
                setNotes(
                  event.target.value,
                )
              }
              rows={3}
              maxLength={5000}
              placeholder="Contoh: kebutuhan bahan produksi minggu depan"
              disabled={
                !canManage ||
                saving
              }
            />
          </label>
        </section>

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
              <ClipboardList
                size={20}
              />

              <div>
                <h2>
                  Item Kebutuhan
                </h2>

                <p>
                  Tambahkan barang atau
                  jasa yang akan dibeli.
                </p>
              </div>
            </div>

            <button
              type="button"
              className={
                styles.secondaryButton
              }
              onClick={addItem}
              disabled={
                !canManage ||
                saving ||
                items.length >= 100
              }
            >
              <Plus size={17} />
              Tambah Item
            </button>
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
                const amount =
                  numericValue(
                    item.quantity,
                  ) *
                  numericValue(
                    item
                      .estimated_unit_price,
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
                      <strong>
                        Item{" "}
                        {index + 1}
                      </strong>

                      <button
                        type="button"
                        className={
                          styles.removeButton
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
                        aria-label={
                          `Hapus item ${index + 1}`
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
                          Jenis
                        </span>

                        <select
                          value={
                            item.item_type
                          }
                          onChange={(event) =>
                            updateItem(
                              item.key,
                              "item_type",
                              event.target.value,
                            )
                          }
                          disabled={
                            !canManage ||
                            saving
                          }
                        >
                          <option
                            value="PRODUCT"
                          >
                            Barang
                          </option>

                          <option
                            value="SERVICE"
                          >
                            Jasa
                          </option>
                        </select>
                      </label>

                      <label
                        className={
                          styles.field
                        }
                      >
                        <span>
                          Nama Item
                        </span>

                        <input
                          value={
                            item.name
                          }
                          onChange={(event) =>
                            updateItem(
                              item.key,
                              "name",
                              event.target.value,
                            )
                          }
                          maxLength={190}
                          placeholder="Contoh: Akrilik 5mm"
                          required
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
                          Jumlah
                        </span>

                        <input
                          type="number"
                          min="0.0001"
                          step="0.0001"
                          value={
                            item.quantity
                          }
                          onChange={(event) =>
                            updateItem(
                              item.key,
                              "quantity",
                              event.target.value,
                            )
                          }
                          required
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
                          Estimasi Harga Satuan
                        </span>

                        <input
                          type="number"
                          min="0"
                          step="0.01"
                          value={
                            item
                              .estimated_unit_price
                          }
                          onChange={(event) =>
                            updateItem(
                              item.key,
                              "estimated_unit_price",
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
                        Deskripsi
                      </span>

                      <textarea
                        rows={2}
                        maxLength={5000}
                        value={
                          item.description
                        }
                        onChange={(event) =>
                          updateItem(
                            item.key,
                            "description",
                            event.target.value,
                          )
                        }
                        placeholder="Spesifikasi, ukuran, atau keterangan lain"
                        disabled={
                          !canManage ||
                          saving
                        }
                      />
                    </label>

                    <div
                      className={
                        styles.itemEstimate
                      }
                    >
                      <span>
                        Estimasi Item
                      </span>

                      <strong>
                        {formatPurchaseMoney(
                          amount,
                        )}
                      </strong>
                    </div>
                  </article>
                );
              },
            )}
          </div>

          <div
            className={
              styles.estimateTotal
            }
          >
            <span>
              Total Estimasi
            </span>

            <strong>
              {formatPurchaseMoney(
                estimatedTotal,
              )}
            </strong>

            <small>
              Nilai final dihitung ulang
              oleh backend saat disimpan.
            </small>
          </div>
        </section>

        <div
          className={
            styles.formActions
          }
        >
          <Link
            href={
              purchaseRequestId
                ? `/app/operasional/permintaan-pembelian/${purchaseRequestId}`
                : "/app/operasional/permintaan-pembelian"
            }
            className={
              styles.secondaryLink
            }
          >
            Batal
          </Link>

          <Button
            type="submit"
            leadingIcon={
              <Save size={18} />
            }
            loading={saving}
            loadingLabel="Menyimpan..."
            disabled={
              !canManage
            }
          >
            {editing
              ? "Simpan Perubahan"
              : "Simpan Draf"}
          </Button>
        </div>
      </form>
    </section>
  );
}
