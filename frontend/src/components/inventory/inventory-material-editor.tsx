"use client";

import {
  Save,
  X,
} from "lucide-react";

import {
  type FormEvent,
  useEffect,
  useRef,
  useState,
} from "react";

import {
  ActionFeedback,
} from "@/components/feedback/action-feedback";

import {
  apiErrorMessage,
  apiRequestId,
} from "@/lib/api/error-message";

import {
  ApiClientError,
} from "@/lib/api/types";

import {
  isNonNegativeDecimal,
} from "@/lib/format/decimal";

import {
  inventoryStatusLabels,
  inventoryTypeLabels,
  stockTrackingLabels,
} from "@/lib/inventory/labels";

import {
  createMaterial,
  updateMaterial,
} from "@/lib/inventory/service";

import type {
  CatalogUnit,
} from "@/types/catalog";

import type {
  InventoryCategory,
  InventoryStatus,
  InventoryStockTracking,
  Material,
  MaterialInventoryType,
  MaterialUpdatePayload,
} from "@/types/inventory";

import styles from "./inventory-material-editor.module.css";


type InventoryMaterialEditorProps = {
  material:
    | Material
    | null;

  categories:
    InventoryCategory[];

  units:
    CatalogUnit[];

  onClose:
    () => void;

  onSaved:
    (
      message: string,
    ) => void;
};


type MaterialFieldErrors = {
  code?: string;
  name?: string;
  categoryId?: string;
  unitId?: string;
  inventoryType?: string;
  stockTracking?: string;
  minimumStock?: string;
  reorderPoint?: string;
  maximumStock?: string;
  description?: string;
  status?: string;
};


function materialApiFieldErrors(
  error: unknown,
): MaterialFieldErrors {
  if (
    !(error instanceof ApiClientError)
  ) {
    return {};
  }

  return {
    code:
      error.fieldError(
        "code",
      ) ?? undefined,

    name:
      error.fieldError(
        "name",
      ) ?? undefined,

    categoryId:
      error.fieldError(
        "category_id",
      ) ?? undefined,

    unitId:
      error.fieldError(
        "unit_id",
      ) ?? undefined,

    inventoryType:
      error.fieldError(
        "inventory_type",
      ) ?? undefined,

    stockTracking:
      error.fieldError(
        "stock_tracking",
      ) ?? undefined,

    minimumStock:
      error.fieldError(
        "minimum_stock",
      ) ?? undefined,

    reorderPoint:
      error.fieldError(
        "reorder_point",
      ) ?? undefined,

    maximumStock:
      error.fieldError(
        "maximum_stock",
      ) ?? undefined,

    description:
      error.fieldError(
        "description",
      ) ?? undefined,

    status:
      error.fieldError(
        "status",
      ) ?? undefined,
  };
}


const inventoryTypeOptions: {
  value:
    MaterialInventoryType;
  label: string;
  description: string;
}[] = [
  {
    value:
      "RAW_MATERIAL",
    label:
      inventoryTypeLabels.RAW_MATERIAL,
    description:
      "Material utama yang diproses menjadi produk atau pekerjaan.",
  },
  {
    value:
      "COMPONENT",
    label:
      inventoryTypeLabels.COMPONENT,
    description:
      "Bagian atau komponen yang dirakit bersama material lain.",
  },
  {
    value:
      "CONSUMABLE",
    label:
      inventoryTypeLabels.CONSUMABLE,
    description:
      "Bahan pendukung yang habis selama pekerjaan atau produksi.",
  },
  {
    value:
      "RESALE",
    label:
      inventoryTypeLabels.RESALE,
    description:
      "Barang fisik yang dibeli untuk dijual kembali.",
  },
  {
    value:
      "FINISHED_GOOD",
    label:
      inventoryTypeLabels.FINISHED_GOOD,
    description:
      "Hasil produksi atau barang siap digunakan/dijual.",
  },
];


function decimalUnits(
  value: string,
): bigint | null {
  const clean =
    value.trim();

  const match =
    /^(\d+)(?:\.(\d{1,4}))?$/.exec(
      clean,
    );

  if (!match) {
    return null;
  }

  const whole =
    match[1];

  const decimal =
    (
      match[2] ??
      ""
    )
      .padEnd(
        4,
        "0",
      );

  return BigInt(
    `${whole}${decimal}`,
  );
}


function validateStockValue(
  value: string,
  label: string,
): string | undefined {
  const clean =
    value.trim();

  if (!clean) {
    return undefined;
  }

  if (
    !isNonNegativeDecimal(
      clean,
    ) ||
    decimalUnits(
      clean,
    ) === null
  ) {
    return (
      `${label} harus berupa angka `
      + "0 atau lebih dengan maksimal 4 angka desimal."
    );
  }

  return undefined;
}


export function InventoryMaterialEditor({
  material,
  categories,
  units,
  onClose,
  onSaved,
}: InventoryMaterialEditorProps) {
  const editing =
    material !== null;

  const [
    code,
    setCode,
  ] = useState(
    material?.code ??
    "",
  );

  const [
    name,
    setName,
  ] = useState(
    material?.name ??
    "",
  );

  const [
    categoryId,
    setCategoryId,
  ] = useState(
    material?.category_id ??
    "",
  );

  const [
    unitId,
    setUnitId,
  ] = useState(
    material?.unit_id ??
    "",
  );

  const [
    inventoryType,
    setInventoryType,
  ] = useState<
    MaterialInventoryType
  >(
    material?.inventory_type ??
    "RAW_MATERIAL",
  );

  const [
    stockTracking,
    setStockTracking,
  ] = useState<
    InventoryStockTracking
  >(
    material?.stock_tracking ??
    "TRACKED",
  );

  const [
    minimumStock,
    setMinimumStock,
  ] = useState(
    material?.minimum_stock ??
    "",
  );

  const [
    reorderPoint,
    setReorderPoint,
  ] = useState(
    material?.reorder_point ??
    "",
  );

  const [
    maximumStock,
    setMaximumStock,
  ] = useState(
    material?.maximum_stock ??
    "",
  );

  const [
    description,
    setDescription,
  ] = useState(
    material?.description ??
    "",
  );

  const [
    status,
    setStatus,
  ] = useState<
    InventoryStatus
  >(
    material?.status ??
    "ACTIVE",
  );

  const [
    fieldErrors,
    setFieldErrors,
  ] = useState<
    MaterialFieldErrors
  >({});

  const [
    submitError,
    setSubmitError,
  ] = useState<unknown>(
    null,
  );

  const [
    busy,
    setBusy,
  ] = useState(false);


  const closeButtonRef =
    useRef<HTMLButtonElement>(
      null,
    );

  const busyRef =
    useRef(busy);

  const onCloseRef =
    useRef(onClose);


  useEffect(() => {
    busyRef.current =
      busy;
  }, [busy]);


  useEffect(() => {
    onCloseRef.current =
      onClose;
  }, [onClose]);


  useEffect(() => {
    const previousFocus =
      document.activeElement
      instanceof HTMLElement
        ? document.activeElement
        : null;

    closeButtonRef.current
      ?.focus();

    function handleKeyDown(
      event: KeyboardEvent,
    ) {
      if (
        event.key !==
        "Escape" ||
        busyRef.current
      ) {
        return;
      }

      event.preventDefault();

      onCloseRef.current();
    }

    document.addEventListener(
      "keydown",
      handleKeyDown,
    );

    return () => {
      document.removeEventListener(
        "keydown",
        handleKeyDown,
      );

      previousFocus
        ?.focus();
    };
  }, []);


  const selectedType =
    inventoryTypeOptions.find(
      (
        option,
      ) =>
        option.value ===
        inventoryType,
    );


  function clearThresholds() {
    setMinimumStock("");
    setReorderPoint("");
    setMaximumStock("");

    setFieldErrors(
      (
        current,
      ) => ({
        ...current,
        minimumStock:
          undefined,
        reorderPoint:
          undefined,
        maximumStock:
          undefined,
      }),
    );
  }


  async function submit(
    event:
      FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    const cleanCode =
      code.trim();

    const cleanName =
      name.trim();

    const nextErrors:
      MaterialFieldErrors = {};

    if (!cleanCode) {
      nextErrors.code =
        "Kode barang wajib diisi.";
    }

    if (!cleanName) {
      nextErrors.name =
        "Nama barang wajib diisi.";
    }

    if (
      stockTracking ===
      "TRACKED"
    ) {
      nextErrors.minimumStock =
        validateStockValue(
          minimumStock,
          "Stok minimum",
        );

      nextErrors.reorderPoint =
        validateStockValue(
          reorderPoint,
          "Titik pesan ulang",
        );

      nextErrors.maximumStock =
        validateStockValue(
          maximumStock,
          "Stok maksimum",
        );

      const minimum =
        minimumStock.trim()
          ? decimalUnits(
              minimumStock,
            )
          : null;

      const reorder =
        reorderPoint.trim()
          ? decimalUnits(
              reorderPoint,
            )
          : null;

      const maximum =
        maximumStock.trim()
          ? decimalUnits(
              maximumStock,
            )
          : null;

      if (
        minimum !== null &&
        reorder !== null &&
        minimum > reorder
      ) {
        nextErrors.reorderPoint =
          "Titik pesan ulang tidak boleh lebih kecil dari stok minimum.";
      }

      if (
        reorder !== null &&
        maximum !== null &&
        reorder > maximum
      ) {
        nextErrors.maximumStock =
          "Stok maksimum tidak boleh lebih kecil dari titik pesan ulang.";
      }

      if (
        minimum !== null &&
        maximum !== null &&
        minimum > maximum
      ) {
        nextErrors.maximumStock =
          "Stok maksimum tidak boleh lebih kecil dari stok minimum.";
      }
    }

    setFieldErrors(
      nextErrors,
    );

    if (
      Object.values(
        nextErrors,
      ).some(Boolean)
    ) {
      return;
    }

    setBusy(true);
    setSubmitError(null);

    try {
      const tracked =
        stockTracking ===
        "TRACKED";

      const basePayload = {
        code:
          cleanCode,

        name:
          cleanName,

        inventory_type:
          inventoryType,

        stock_tracking:
          stockTracking,

        minimum_stock:
          tracked
            ? (
                minimumStock.trim() ||
                null
              )
            : null,

        reorder_point:
          tracked
            ? (
                reorderPoint.trim() ||
                null
              )
            : null,

        maximum_stock:
          tracked
            ? (
                maximumStock.trim() ||
                null
              )
            : null,

        description:
          description.trim() ||
          null,
      };

      const response =
        editing
          ? await updateExisting(
              basePayload,
            )
          : await createMaterial({
              ...basePayload,

              category_id:
                categoryId ||
                null,

              unit_id:
                unitId ||
                null,
            });

      onSaved(
        response.message ??
          (
            editing
              ? "Barang persediaan berhasil diperbarui."
              : "Barang persediaan berhasil ditambahkan."
          ),
      );
    } catch (caught) {
      const apiErrors =
        materialApiFieldErrors(
          caught,
        );

      if (
        Object.values(
          apiErrors,
        ).some(Boolean)
      ) {
        setFieldErrors(
          apiErrors,
        );
      }

      setSubmitError(
        caught,
      );
    } finally {
      setBusy(false);
    }
  }


  async function updateExisting(
    basePayload: {
      code: string;
      name: string;
      inventory_type:
        MaterialInventoryType;
      stock_tracking:
        InventoryStockTracking;
      minimum_stock:
        string | null;
      reorder_point:
        string | null;
      maximum_stock:
        string | null;
      description:
        string | null;
    },
  ) {
    if (!material) {
      throw new Error(
        "Material edit context missing.",
      );
    }

    const payload:
      MaterialUpdatePayload = {
        ...basePayload,
        status,
    };

    if (
      categoryId !==
      (
        material.category_id ??
        ""
      )
    ) {
      payload.category_id =
        categoryId ||
        null;
    }

    if (
      unitId !==
      (
        material.unit_id ??
        ""
      )
    ) {
      payload.unit_id =
        unitId ||
        null;
    }

    return updateMaterial(
      material.id,
      payload,
    );
  }


  return (
    <div
      className={
        styles.overlay
      }
      onMouseDown={(
        event,
      ) => {
        if (
          event.target ===
            event.currentTarget &&
          !busy
        ) {
          onClose();
        }
      }}
    >
      <section
        className={
          styles.sheet
        }
        role="dialog"
        aria-modal="true"
        aria-label={
          editing
            ? "Ubah Barang & Persediaan"
            : "Tambah Barang & Persediaan"
        }
      >
        <header
          className={
            styles.header
          }
        >
          <div>
            <h2>
              {editing
                ? "Ubah Barang & Persediaan"
                : "Tambah Barang & Persediaan"}
            </h2>

            <p>
              Master barang menyimpan identitas dan kebijakan persediaan.
              Saldo stok tetap berasal dari transaksi pergerakan stok.
            </p>
          </div>

          <button
            ref={
              closeButtonRef
            }
            type="button"
            className={
              styles.closeButton
            }
            disabled={
              busy
            }
            onClick={
              onClose
            }
            aria-label="Tutup"
          >
            <X
              size={20}
              aria-hidden="true"
            />
          </button>
        </header>

        <form
          className={
            styles.form
          }
          onSubmit={
            submit
          }
        >
          <div
            className={
              styles.body
            }
          >
            <section
              className={
                styles.formSection
              }
            >
              <div
                className={
                  styles.sectionHeading
                }
              >
                <h3>
                  Informasi Utama
                </h3>

                <p>
                  Gunakan kode dan nama yang mudah dikenali tim pembelian,
                  gudang, dan produksi.
                </p>
              </div>

              <div
                className={
                  styles.twoColumns
                }
              >
                <label
                  className={
                    styles.field
                  }
                >
                  <span>
                    Kode barang
                    <b>*</b>
                  </span>

                  <input
                    type="text"
                    value={
                      code
                    }
                    maxLength={100}
                    autoComplete="off"
                    aria-invalid={
                      Boolean(
                        fieldErrors.code,
                      )
                    }
                    aria-describedby={
                      fieldErrors.code
                        ? "material-code-error"
                        : undefined
                    }
                    onChange={(
                      event,
                    ) => {
                      setCode(
                        event.target
                          .value
                          .toUpperCase(),
                      );

                      if (
                        fieldErrors.code
                      ) {
                        setFieldErrors(
                          (
                            current,
                          ) => ({
                            ...current,
                            code:
                              undefined,
                          }),
                        );
                      }
                    }}
                  />

                  {fieldErrors.code ? (
                    <small
                      id="material-code-error"
                      className={
                        styles.fieldError
                      }
                    >
                      {
                        fieldErrors.code
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
                    Nama barang
                    <b>*</b>
                  </span>

                  <input
                    type="text"
                    value={
                      name
                    }
                    maxLength={190}
                    aria-invalid={
                      Boolean(
                        fieldErrors.name,
                      )
                    }
                    aria-describedby={
                      fieldErrors.name
                        ? "material-name-error"
                        : undefined
                    }
                    onChange={(
                      event,
                    ) => {
                      setName(
                        event.target
                          .value,
                      );

                      if (
                        fieldErrors.name
                      ) {
                        setFieldErrors(
                          (
                            current,
                          ) => ({
                            ...current,
                            name:
                              undefined,
                          }),
                        );
                      }
                    }}
                  />

                  {fieldErrors.name ? (
                    <small
                      id="material-name-error"
                      className={
                        styles.fieldError
                      }
                    >
                      {
                        fieldErrors.name
                      }
                    </small>
                  ) : null}
                </label>
              </div>

              <div
                className={
                  styles.twoColumns
                }
              >
                <label
                  className={
                    styles.field
                  }
                >
                  <span>
                    Kategori
                  </span>

                  <select
                    value={
                      categoryId
                    }
                    aria-invalid={
                      Boolean(
                        fieldErrors.categoryId,
                      )
                    }
                    aria-describedby={
                      fieldErrors.categoryId
                        ? "material-category-error"
                        : undefined
                    }
                    onChange={(
                      event,
                    ) => {
                      setCategoryId(
                        event.target
                          .value,
                      );

                      if (
                        fieldErrors.categoryId
                      ) {
                        setFieldErrors(
                          (
                            current,
                          ) => ({
                            ...current,
                            categoryId:
                              undefined,
                          }),
                        );
                      }
                    }}
                  >
                    <option value="">
                      Tanpa kategori
                    </option>

                    {categories.map(
                      (
                        category,
                      ) => {
                        const current =
                          category.id ===
                          material?.category_id;

                        const disabled =
                          category.status !==
                            "ACTIVE" &&
                          !current;

                        return (
                          <option
                            key={
                              category.id
                            }
                            value={
                              category.id
                            }
                            disabled={
                              disabled
                            }
                          >
                            {
                              category.name
                            }
                            {category.status !==
                            "ACTIVE"
                              ? " (Nonaktif)"
                              : ""}
                          </option>
                        );
                      },
                    )}
                  </select>

                  {fieldErrors.categoryId ? (
                    <small
                      id="material-category-error"
                      className={
                        styles.fieldError
                      }
                    >
                      {
                        fieldErrors.categoryId
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
                    Satuan
                  </span>

                  <select
                    value={
                      unitId
                    }
                    aria-invalid={
                      Boolean(
                        fieldErrors.unitId,
                      )
                    }
                    aria-describedby={
                      fieldErrors.unitId
                        ? "material-unit-error"
                        : undefined
                    }
                    onChange={(
                      event,
                    ) => {
                      setUnitId(
                        event.target
                          .value,
                      );

                      if (
                        fieldErrors.unitId
                      ) {
                        setFieldErrors(
                          (
                            current,
                          ) => ({
                            ...current,
                            unitId:
                              undefined,
                          }),
                        );
                      }
                    }}
                  >
                    <option value="">
                      Tanpa satuan
                    </option>

                    {material?.unit_id &&
                    !units.some(
                      (unit) =>
                        unit.id ===
                        material.unit_id,
                    ) ? (
                      <option
                        value={
                          material.unit_id
                        }
                      >
                        Satuan terhubung (akses terbatas)
                      </option>
                    ) : null}

                    {units.map(
                      (
                        unit,
                      ) => {
                        const current =
                          unit.id ===
                          material?.unit_id;

                        const disabled =
                          unit.status !==
                            "ACTIVE" &&
                          !current;

                        return (
                          <option
                            key={
                              unit.id
                            }
                            value={
                              unit.id
                            }
                            disabled={
                              disabled
                            }
                          >
                            {unit.symbol
                              ? `${unit.name} (${unit.symbol})`
                              : unit.name}
                            {unit.status !==
                            "ACTIVE"
                              ? " (Nonaktif)"
                              : ""}
                          </option>
                        );
                      },
                    )}
                  </select>

                  {fieldErrors.unitId ? (
                    <small
                      id="material-unit-error"
                      className={
                        styles.fieldError
                      }
                    >
                      {
                        fieldErrors.unitId
                      }
                    </small>
                  ) : null}

                  {units.length ===
                  0 ? (
                    <small
                      className={
                        styles.help
                      }
                    >
                      Satuan dapat dikosongkan bila referensi satuan tidak
                      tersedia untuk akun ini.
                    </small>
                  ) : null}
                </label>
              </div>
            </section>

            <section
              className={
                styles.formSection
              }
            >
              <div
                className={
                  styles.sectionHeading
                }
              >
                <h3>
                  Jenis & Kebijakan Stok
                </h3>

                <p>
                  Jenis barang menjelaskan fungsi material. Pemantauan stok
                  menentukan apakah penerimaan barang membuat persediaan.
                </p>
              </div>

              <label
                className={
                  styles.field
                }
              >
                <span>
                  Jenis barang
                </span>

                <select
                  value={
                    inventoryType
                  }
                  aria-invalid={
                    Boolean(
                      fieldErrors.inventoryType,
                    )
                  }
                  aria-describedby={
                    fieldErrors.inventoryType
                      ? "material-inventory-type-error"
                      : undefined
                  }
                  onChange={(
                    event,
                  ) => {
                    setInventoryType(
                      event.target
                        .value as
                        MaterialInventoryType,
                    );

                    if (
                      fieldErrors.inventoryType
                    ) {
                      setFieldErrors(
                        (
                          current,
                        ) => ({
                          ...current,
                          inventoryType:
                            undefined,
                        }),
                      );
                    }
                  }}
                >
                  {inventoryTypeOptions.map(
                    (
                      option,
                    ) => (
                      <option
                        key={
                          option.value
                        }
                        value={
                          option.value
                        }
                      >
                        {
                          option.label
                        }
                      </option>
                    ),
                  )}
                </select>

                {fieldErrors.inventoryType ? (
                  <small
                    id="material-inventory-type-error"
                    className={
                      styles.fieldError
                    }
                  >
                    {
                      fieldErrors.inventoryType
                    }
                  </small>
                ) : null}

                <small
                  className={
                    styles.help
                  }
                >
                  {
                    selectedType
                      ?.description
                  }
                </small>
              </label>

              <div
                className={
                  styles.trackingChoices
                }
              >
                <button
                  type="button"
                  className={
                    stockTracking ===
                    "TRACKED"
                      ? styles.trackingChoiceActive
                      : styles.trackingChoice
                  }
                  aria-describedby={
                    fieldErrors.stockTracking
                      ? "material-stock-tracking-error"
                      : undefined
                  }
                  onClick={() => {
                    setStockTracking(
                      "TRACKED",
                    );

                    if (
                      fieldErrors.stockTracking
                    ) {
                      setFieldErrors(
                        (
                          current,
                        ) => ({
                          ...current,
                          stockTracking:
                            undefined,
                        }),
                      );
                    }
                  }}
                >
                  <strong>
                    {
                      stockTrackingLabels.TRACKED
                    }
                  </strong>

                  <span>
                    Penerimaan item persediaan dapat menghasilkan Stock IN
                    melalui transaksi yang sah.
                  </span>
                </button>

                <button
                  type="button"
                  className={
                    stockTracking ===
                    "NOT_TRACKED"
                      ? styles.trackingChoiceActive
                      : styles.trackingChoice
                  }
                  aria-describedby={
                    fieldErrors.stockTracking
                      ? "material-stock-tracking-error"
                      : undefined
                  }
                  onClick={() => {
                    setStockTracking(
                      "NOT_TRACKED",
                    );

                    if (
                      fieldErrors.stockTracking
                    ) {
                      setFieldErrors(
                        (
                          current,
                        ) => ({
                          ...current,
                          stockTracking:
                            undefined,
                        }),
                      );
                    }

                    clearThresholds();
                  }}
                >
                  <strong>
                    {
                      stockTrackingLabels.NOT_TRACKED
                    }
                  </strong>

                  <span>
                    Barang tetap ada di master tetapi tidak memiliki saldo
                    persediaan.
                  </span>
                </button>
              </div>

              {fieldErrors.stockTracking ? (
                <small
                  id="material-stock-tracking-error"
                  className={
                    styles.fieldError
                  }
                >
                  {
                    fieldErrors.stockTracking
                  }
                </small>
              ) : null}

              {stockTracking ===
              "TRACKED" ? (
                <div
                  className={
                    styles.thresholdSection
                  }
                >
                  <div
                    className={
                      styles.thresholdHeading
                    }
                  >
                    <strong>
                      Batas Persediaan
                    </strong>

                    <span>
                      Opsional. Isi jika Anda ingin menyiapkan dasar reminder
                      kebutuhan stok.
                    </span>
                  </div>

                  <div
                    className={
                      styles.thresholdGrid
                    }
                  >
                    <StockField
                      id="material-minimum-stock"
                      label="Stok minimum"
                      value={
                        minimumStock
                      }
                      error={
                        fieldErrors.minimumStock
                      }
                      onChange={(
                        value,
                      ) => {
                        setMinimumStock(
                          value,
                        );

                        setFieldErrors(
                          (
                            current,
                          ) => ({
                            ...current,
                            minimumStock:
                              undefined,
                          }),
                        );
                      }}
                    />

                    <StockField
                      id="material-reorder-point"
                      label="Pesan ulang"
                      value={
                        reorderPoint
                      }
                      error={
                        fieldErrors.reorderPoint
                      }
                      onChange={(
                        value,
                      ) => {
                        setReorderPoint(
                          value,
                        );

                        setFieldErrors(
                          (
                            current,
                          ) => ({
                            ...current,
                            reorderPoint:
                              undefined,
                          }),
                        );
                      }}
                    />

                    <StockField
                      id="material-maximum-stock"
                      label="Stok maksimum"
                      value={
                        maximumStock
                      }
                      error={
                        fieldErrors.maximumStock
                      }
                      onChange={(
                        value,
                      ) => {
                        setMaximumStock(
                          value,
                        );

                        setFieldErrors(
                          (
                            current,
                          ) => ({
                            ...current,
                            maximumStock:
                              undefined,
                          }),
                        );
                      }}
                    />
                  </div>
                </div>
              ) : (
                <div
                  className={
                    styles.nonTrackedNote
                  }
                >
                  Barang tidak dipantau tidak memakai stok minimum, titik
                  pesan ulang, atau stok maksimum.
                </div>
              )}
            </section>

            <section
              className={
                styles.formSection
              }
            >
              <div
                className={
                  styles.sectionHeading
                }
              >
                <h3>
                  Keterangan
                </h3>
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
                  value={
                    description
                  }
                  maxLength={5000}
                  rows={4}
                  placeholder="Contoh: akrilik bening 5 mm untuk cutting huruf dan panel."
                  aria-invalid={
                    Boolean(
                      fieldErrors.description,
                    )
                  }
                  aria-describedby={
                    fieldErrors.description
                      ? "material-description-error"
                      : undefined
                  }
                  onChange={(
                    event,
                  ) => {
                    setDescription(
                      event.target
                        .value,
                    );

                    if (
                      fieldErrors.description
                    ) {
                      setFieldErrors(
                        (
                          current,
                        ) => ({
                          ...current,
                          description:
                            undefined,
                        }),
                      );
                    }
                  }}
                />

                {fieldErrors.description ? (
                  <small
                    id="material-description-error"
                    className={
                      styles.fieldError
                    }
                  >
                    {
                      fieldErrors.description
                    }
                  </small>
                ) : null}
              </label>

              {editing ? (
                <label
                  className={
                    styles.field
                  }
                >
                  <span>
                    Status master
                  </span>

                  <select
                    value={
                      status
                    }
                    aria-invalid={
                      Boolean(
                        fieldErrors.status,
                      )
                    }
                    aria-describedby={
                      fieldErrors.status
                        ? "material-status-error"
                        : undefined
                    }
                    onChange={(
                      event,
                    ) => {
                      setStatus(
                        event.target
                          .value as
                          InventoryStatus,
                      );

                      if (
                        fieldErrors.status
                      ) {
                        setFieldErrors(
                          (
                            current,
                          ) => ({
                            ...current,
                            status:
                              undefined,
                          }),
                        );
                      }
                    }}
                  >
                    <option
                      value="ACTIVE"
                    >
                      {
                        inventoryStatusLabels.ACTIVE
                      }
                    </option>

                    <option
                      value="INACTIVE"
                    >
                      {
                        inventoryStatusLabels.INACTIVE
                      }
                    </option>
                  </select>

                  {fieldErrors.status ? (
                    <small
                      id="material-status-error"
                      className={
                        styles.fieldError
                      }
                    >
                      {
                        fieldErrors.status
                      }
                    </small>
                  ) : null}
                </label>
              ) : null}

              {submitError ? (
                <ActionFeedback
                  tone="error"
                  title="Barang belum dapat disimpan"
                  message={
                    apiErrorMessage(
                      submitError,
                      "Periksa kembali data barang lalu coba lagi.",
                    )
                  }
                  requestId={
                    apiRequestId(
                      submitError,
                    )
                  }
                />
              ) : null}
            </section>
          </div>

          <footer
            className={
              styles.footer
            }
          >
            <button
              type="button"
              className={
                styles.secondaryButton
              }
              disabled={
                busy
              }
              onClick={
                onClose
              }
            >
              Batal
            </button>

            <button
              type="submit"
              className={
                styles.primaryButton
              }
              disabled={
                busy
              }
            >
              <Save
                size={18}
                aria-hidden="true"
              />

              {busy
                ? "Menyimpan..."
                : editing
                  ? "Simpan Perubahan"
                  : "Tambah Barang"}
            </button>
          </footer>
        </form>
      </section>
    </div>
  );
}


function StockField({
  id,
  label,
  value,
  error,
  onChange,
}: {
  id: string;
  label: string;
  value: string;

  error?:
    string;

  onChange:
    (
      value: string,
    ) => void;
}) {
  const errorId =
    `${id}-error`;

  return (
    <label
      className={
        styles.field
      }
    >
      <span>
        {label}
      </span>

      <input
        id={
          id
        }
        type="text"
        inputMode="decimal"
        value={
          value
        }
        placeholder="0"
        aria-invalid={
          Boolean(
            error,
          )
        }
        aria-describedby={
          error
            ? errorId
            : undefined
        }
        onChange={(
          event,
        ) =>
          onChange(
            event.target
              .value,
          )
        }
      />

      {error ? (
        <small
          id={
            errorId
          }
          className={
            styles.fieldError
          }
        >
          {error}
        </small>
      ) : null}
    </label>
  );
}
