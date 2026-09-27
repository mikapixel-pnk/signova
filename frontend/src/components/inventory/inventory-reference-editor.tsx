"use client";

import {
  Save,
  X,
} from "lucide-react";

import {
  type FormEvent,
  type ReactNode,
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
  inventoryStatusLabels,
} from "@/lib/inventory/labels";

import {
  createInventoryCategory,
  createWarehouse,
  updateInventoryCategory,
  updateWarehouse,
} from "@/lib/inventory/service";

import type {
  InventoryCategory,
  InventoryStatus,
  Warehouse,
} from "@/types/inventory";

import styles from "./inventory-reference-editor.module.css";


type EditorCommonProps = {
  onClose: () => void;
  onSaved: (
    message: string,
  ) => void;
};


type InventoryCategoryEditorProps =
  EditorCommonProps & {
    category:
      | InventoryCategory
      | null;
  };


type WarehouseEditorProps =
  EditorCommonProps & {
    warehouse:
      | Warehouse
      | null;
  };


type EditorShellProps = {
  title: string;
  description: string;
  busy: boolean;
  onClose: () => void;
  children: ReactNode;
};


type CategoryFieldErrors = {
  code?: string;
  name?: string;
  description?: string;
  status?: string;
};


type WarehouseFieldErrors = {
  name?: string;
  location?: string;
  status?: string;
};


function categoryApiFieldErrors(
  error: unknown,
): CategoryFieldErrors {
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


function warehouseApiFieldErrors(
  error: unknown,
): WarehouseFieldErrors {
  if (
    !(error instanceof ApiClientError)
  ) {
    return {};
  }

  return {
    name:
      error.fieldError(
        "name",
      ) ?? undefined,

    location:
      error.fieldError(
        "location",
      ) ?? undefined,

    status:
      error.fieldError(
        "status",
      ) ?? undefined,
  };
}


function EditorShell({
  title,
  description,
  busy,
  onClose,
  children,
}: EditorShellProps) {
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
        aria-label={title}
      >
        <header
          className={
            styles.header
          }
        >
          <div>
            <h2>
              {title}
            </h2>

            <p>
              {description}
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

        {children}
      </section>
    </div>
  );
}


export function InventoryCategoryEditor({
  category,
  onClose,
  onSaved,
}: InventoryCategoryEditorProps) {
  const editing =
    category !== null;

  const [
    code,
    setCode,
  ] = useState(
    category?.code ??
    "",
  );

  const [
    name,
    setName,
  ] = useState(
    category?.name ??
    "",
  );

  const [
    description,
    setDescription,
  ] = useState(
    category?.description ??
    "",
  );

  const [
    status,
    setStatus,
  ] = useState<
    InventoryStatus
  >(
    category?.status ??
    "ACTIVE",
  );

  const [
    fieldErrors,
    setFieldErrors,
  ] = useState<
    CategoryFieldErrors
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
      CategoryFieldErrors = {};

    if (!cleanCode) {
      nextErrors.code =
        "Kode kategori wajib diisi.";
    }

    if (!cleanName) {
      nextErrors.name =
        "Nama kategori wajib diisi.";
    }

    setFieldErrors(
      nextErrors,
    );

    if (
      Object.keys(
        nextErrors,
      ).length > 0
    ) {
      return;
    }

    setBusy(true);
    setSubmitError(null);

    try {
      const cleanDescription =
        description.trim() ||
        null;

      const response =
        editing
          ? await updateInventoryCategory(
              category.id,
              {
                code:
                  cleanCode,
                name:
                  cleanName,
                description:
                  cleanDescription,
                status,
              },
            )
          : await createInventoryCategory(
              {
                code:
                  cleanCode,
                name:
                  cleanName,
                description:
                  cleanDescription,
              },
            );

      onSaved(
        response.message ??
          (
            editing
              ? "Kategori persediaan berhasil diperbarui."
              : "Kategori persediaan berhasil ditambahkan."
          ),
      );
    } catch (caught) {
      const apiErrors =
        categoryApiFieldErrors(
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


  return (
    <EditorShell
      title={
        editing
          ? "Ubah Kategori"
          : "Tambah Kategori"
      }
      description="Kategori membantu mengelompokkan barang persediaan tanpa mengubah saldo stok."
      busy={
        busy
      }
      onClose={
        onClose
      }
    >
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
          <label
            className={
              styles.field
            }
          >
            <span>
              Kode kategori
              <b>*</b>
            </span>

            <input
              type="text"
              value={
                code
              }
              maxLength={80}
              autoComplete="off"
              aria-invalid={
                Boolean(
                  fieldErrors.code,
                )
              }
              aria-describedby={
                fieldErrors.code
                  ? "inventory-category-code-error"
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
                id="inventory-category-code-error"
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
              Nama kategori
              <b>*</b>
            </span>

            <input
              type="text"
              value={
                name
              }
              maxLength={120}
              aria-invalid={
                Boolean(
                  fieldErrors.name,
                )
              }
              aria-describedby={
                fieldErrors.name
                  ? "inventory-category-name-error"
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
                id="inventory-category-name-error"
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

          <label
            className={
              styles.field
            }
          >
            <span>
              Keterangan
            </span>

            <textarea
              value={
                description
              }
              maxLength={5000}
              rows={4}
              placeholder="Contoh: bahan utama untuk produksi signage."
              aria-invalid={
                Boolean(
                  fieldErrors.description,
                )
              }
              aria-describedby={
                fieldErrors.description
                  ? "inventory-category-description-error"
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
                id="inventory-category-description-error"
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
                Status
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
                    ? "inventory-category-status-error"
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
                  id="inventory-category-status-error"
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
              title="Kategori belum dapat disimpan"
              message={
                apiErrorMessage(
                  submitError,
                  "Periksa kembali data kategori lalu coba lagi.",
                )
              }
              requestId={
                apiRequestId(
                  submitError,
                )
              }
            />
          ) : null}
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
                : "Tambah Kategori"}
          </button>
        </footer>
      </form>
    </EditorShell>
  );
}


export function WarehouseEditor({
  warehouse,
  onClose,
  onSaved,
}: WarehouseEditorProps) {
  const editing =
    warehouse !== null;

  const [
    name,
    setName,
  ] = useState(
    warehouse?.name ??
    "",
  );

  const [
    location,
    setLocation,
  ] = useState(
    warehouse?.location ??
    "",
  );

  const [
    status,
    setStatus,
  ] = useState<
    InventoryStatus
  >(
    warehouse?.status ??
    "ACTIVE",
  );

  const [
    fieldErrors,
    setFieldErrors,
  ] = useState<
    WarehouseFieldErrors
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


  async function submit(
    event:
      FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    const cleanName =
      name.trim();

    const nextErrors:
      WarehouseFieldErrors = {};

    if (!cleanName) {
      nextErrors.name =
        "Nama gudang wajib diisi.";
    }

    setFieldErrors(
      nextErrors,
    );

    if (
      Object.keys(
        nextErrors,
      ).length > 0
    ) {
      return;
    }

    setBusy(true);
    setSubmitError(null);

    try {
      const cleanLocation =
        location.trim() ||
        null;

      const response =
        editing
          ? await updateWarehouse(
              warehouse.id,
              {
                name:
                  cleanName,
                location:
                  cleanLocation,
                status,
              },
            )
          : await createWarehouse(
              {
                name:
                  cleanName,
                location:
                  cleanLocation,
              },
            );

      onSaved(
        response.message ??
          (
            editing
              ? "Gudang berhasil diperbarui."
              : "Gudang berhasil ditambahkan."
          ),
      );
    } catch (caught) {
      const apiErrors =
        warehouseApiFieldErrors(
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


  return (
    <EditorShell
      title={
        editing
          ? "Ubah Gudang"
          : "Tambah Gudang"
      }
      description="Gudang adalah lokasi fisik persediaan. Proyek tetap menjadi tujuan atau alokasi, bukan gudang."
      busy={
        busy
      }
      onClose={
        onClose
      }
    >
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
          <label
            className={
              styles.field
            }
          >
            <span>
              Nama gudang
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
                  ? "warehouse-name-error"
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
                id="warehouse-name-error"
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

          <label
            className={
              styles.field
            }
          >
            <span>
              Lokasi
            </span>

            <textarea
              value={
                location
              }
              maxLength={5000}
              rows={4}
              placeholder="Contoh: Workshop utama, rak A."
              aria-invalid={
                Boolean(
                  fieldErrors.location,
                )
              }
              aria-describedby={
                fieldErrors.location
                  ? "warehouse-location-error"
                  : undefined
              }
              onChange={(
                event,
              ) => {
                setLocation(
                  event.target
                    .value,
                );

                if (
                  fieldErrors.location
                ) {
                  setFieldErrors(
                    (
                      current,
                    ) => ({
                      ...current,
                      location:
                        undefined,
                    }),
                  );
                }
              }}
            />

            {fieldErrors.location ? (
              <small
                id="warehouse-location-error"
                className={
                  styles.fieldError
                }
              >
                {
                  fieldErrors.location
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
                Status
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
                    ? "warehouse-status-error"
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
                  id="warehouse-status-error"
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
              title="Gudang belum dapat disimpan"
              message={
                apiErrorMessage(
                  submitError,
                  "Periksa kembali data gudang lalu coba lagi.",
                )
              }
              requestId={
                apiRequestId(
                  submitError,
                )
              }
            />
          ) : null}
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
                : "Tambah Gudang"}
          </button>
        </footer>
      </form>
    </EditorShell>
  );
}
