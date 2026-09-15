"use client";

import {
  Box,
  Pencil,
  Plus,
  Ruler,
  Search,
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
  apiErrorMessage,
  apiRequestId,
} from "@/lib/api/error-message";

import {
  createCatalogUnit,
  listCatalogUnits,
  updateCatalogUnit,
} from "@/lib/catalog/service";

import {
  getModule,
} from "@/lib/module/registry";

import type {
  CatalogStatus,
  CatalogUnit,
  CatalogUnitCreatePayload,
  CatalogUnitType,
} from "@/types/catalog";

import styles from "./units.module.css";

type StatusFilter =
  | "ALL"
  | CatalogStatus;

type UnitFormState = {
  code: string;
  name: string;
  symbol: string;
  unit_type: CatalogUnitType;
  decimal_precision: string;
};

const emptyForm:
  UnitFormState = {
    code: "",
    name: "",
    symbol: "",
    unit_type: "COUNT",
    decimal_precision: "0",
  };

const unitTypeOptions: {
  value: CatalogUnitType;
  label: string;
}[] = [
  {
    value: "COUNT",
    label: "Jumlah",
  },
  {
    value: "LENGTH",
    label: "Panjang",
  },
  {
    value: "AREA",
    label: "Luas",
  },
  {
    value: "VOLUME",
    label: "Volume",
  },
  {
    value: "TIME",
    label: "Waktu",
  },
  {
    value: "PACKAGE",
    label: "Paket",
  },
  {
    value: "OTHER",
    label: "Lainnya",
  },
];

function statusLabel(
  status:
    CatalogStatus,
) {
  return status === "ACTIVE"
    ? "Aktif"
    : "Nonaktif";
}

export default function UnitsPage() {
  const moduleDef =
    getModule("units");

  const ModuleIcon =
    moduleDef.icon;

  const [
    units,
    setUnits,
  ] = useState<
    CatalogUnit[]
  >([]);

  const [
    loading,
    setLoading,
  ] = useState(true);

  const [
    error,
    setError,
  ] = useState<
    unknown
  >(null);

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
    editorOpen,
    setEditorOpen,
  ] = useState(false);

  const [
    editingUnit,
    setEditingUnit,
  ] = useState<
    CatalogUnit | null
  >(null);

  const [
    form,
    setForm,
  ] = useState<
    UnitFormState
  >(emptyForm);

  const [
    saving,
    setSaving,
  ] = useState(false);

  const [
    saveError,
    setSaveError,
  ] = useState<
    unknown
  >(null);

  const [
    success,
    setSuccess,
  ] = useState<
    string | null
  >(null);

  useEffect(() => {
    if (!success) {
      return;
    }

    const timeoutId =
      window.setTimeout(
        () => {
          setSuccess(null);
        },
        4000,
      );

    return () => {
      window.clearTimeout(
        timeoutId,
      );
    };
  }, [success]);

  const loadUnits =
    useCallback(
      async () => {
        setLoading(true);
        setError(null);

        try {
          const response =
            await listCatalogUnits();

          setUnits(
            response.data,
          );
        } catch (caught) {
          setError(caught);
        } finally {
          setLoading(false);
        }
      },
      [],
    );

  useEffect(() => {
    let active = true;

    listCatalogUnits()
      .then((response) => {
        if (!active) {
          return;
        }

        setUnits(
          response.data,
        );
      })
      .catch((caught) => {
        if (!active) {
          return;
        }

        setError(caught);
      })
      .finally(() => {
        if (!active) {
          return;
        }

        setLoading(false);
      });

    return () => {
      active = false;
    };
  }, []);

  const visibleUnits =
    useMemo(
      () => {
        const query =
          search
            .trim()
            .toLocaleLowerCase(
              "id-ID",
            );

        return units.filter(
          (unit) => {
            if (
              status !== "ALL" &&
              unit.status !==
                status
            ) {
              return false;
            }

            if (!query) {
              return true;
            }

            return [
              unit.code,
              unit.name,
              unit.symbol ?? "",
              unit.unit_type_label,
            ].some(
              (value) =>
                value
                  .toLocaleLowerCase(
                    "id-ID",
                  )
                  .includes(query),
            );
          },
        );
      },
      [
        units,
        search,
        status,
      ],
    );

  function openCreate() {
    setEditingUnit(null);
    setForm(emptyForm);
    setSaveError(null);
    setSuccess(null);
    setEditorOpen(true);
  }

  function openEdit(
    unit:
      CatalogUnit,
  ) {
    setEditingUnit(unit);

    setForm({
      code: unit.code,
      name: unit.name,
      symbol:
        unit.symbol ?? "",
      unit_type:
        unit.unit_type as CatalogUnitType,
      decimal_precision:
        String(
          unit.decimal_precision,
        ),
    });

    setSaveError(null);
    setSuccess(null);
    setEditorOpen(true);
  }

  function closeEditor() {
    if (saving) {
      return;
    }

    setEditorOpen(false);
    setSaveError(null);
  }

  async function submitForm(
    event:
      React.FormEvent,
  ) {
    event.preventDefault();

    setSaving(true);
    setSaveError(null);
    setSuccess(null);

    const payload:
      CatalogUnitCreatePayload = {
        code:
          form.code.trim(),

        name:
          form.name.trim(),

        symbol:
          form.symbol.trim()
            ? form.symbol.trim()
            : null,

        unit_type:
          form.unit_type,

        decimal_precision:
          Number(
            form.decimal_precision,
          ),
      };

    try {
      if (editingUnit) {
        await updateCatalogUnit(
          editingUnit.id,
          payload,
        );

        setSuccess(
          "Satuan berhasil diperbarui.",
        );
      } else {
        await createCatalogUnit(
          payload,
        );

        setSuccess(
          "Satuan berhasil ditambahkan.",
        );
      }

      await loadUnits();

      setEditorOpen(false);
      setEditingUnit(null);
      setForm(emptyForm);
    } catch (caught) {
      setSaveError(caught);
    } finally {
      setSaving(false);
    }
  }

  async function toggleStatus(
    unit:
      CatalogUnit,
  ) {
    setSaveError(null);
    setSuccess(null);

    try {
      const nextStatus:
        CatalogStatus =
          unit.status ===
          "ACTIVE"
            ? "INACTIVE"
            : "ACTIVE";

      await updateCatalogUnit(
        unit.id,
        {
          status:
            nextStatus,
        },
      );

      setSuccess(
        nextStatus ===
        "ACTIVE"
          ? "Satuan berhasil diaktifkan."
          : "Satuan berhasil dinonaktifkan.",
      );

      await loadUnits();
    } catch (caught) {
      setSaveError(caught);
    }
  }

  return (
    <TenantShell>
      <section
        className={
          styles.page
        }
      >
        <ModuleHero
          eyebrow="Master Data"
          title={
            moduleDef.label
          }
          description={
            moduleDef.description
          }
          icon={
            ModuleIcon
          }
          tone={
            moduleDef.tone ===
            "amber"
              ? "blue"
              : moduleDef.tone
          }
          insightTitle={
            moduleDef.insight.title
          }
          insightDescription={
            moduleDef.insight.description
          }
          actions={
            <button
              type="button"
              className={
                styles.primaryAction
              }
              onClick={
                openCreate
              }
            >
              <Plus
                size={18}
              />
              Tambah Satuan
            </button>
          }
        />

        <section
          className={
            styles.toolbar
          }
        >
          <label
            className={
              styles.searchBox
            }
          >
            <Search
              size={18}
            />

            <input
              type="search"
              value={search}
              onChange={(
                event,
              ) =>
                setSearch(
                  event
                    .target
                    .value,
                )
              }
              placeholder="Cari nama, kode, simbol..."
              aria-label="Cari satuan"
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
                  type="button"
                  key={value}
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
          {loading
            ? "Memuat satuan..."
            : `${visibleUnits.length} satuan`}
        </div>

        {success ? (
          <ActionFeedback
            tone="success"
            placement="viewport"
            title="Berhasil"
            message={
              success
            }
          />
        ) : null}

        {saveError ? (
          <ActionFeedback
            tone="error"
            title="Perubahan belum dapat disimpan"
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

        {error ? (
          <ActionFeedback
            tone="error"
            title="Satuan belum dapat dimuat"
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
        visibleUnits.length ===
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
              <Ruler
                size={28}
              />
            </span>

            <h2>
              Belum ada satuan
            </h2>

            <p>
              Tambahkan satuan yang
              digunakan untuk barang
              dan jasa usaha Anda.
            </p>

            <button
              type="button"
              className={
                styles.primaryAction
              }
              onClick={
                openCreate
              }
            >
              <Plus
                size={18}
              />
              Tambah Satuan
            </button>
          </section>
        ) : null}

        {!loading &&
        !error &&
        visibleUnits.length >
          0 ? (
          <>
            <div
              className={
                styles.mobileList
              }
            >
              {visibleUnits.map(
                (unit) => (
                  <article
                    key={
                      unit.id
                    }
                    className={
                      styles.unitCard
                    }
                  >
                    <span
                      className={
                        styles.unitIcon
                      }
                    >
                      <Box
                        size={20}
                      />
                    </span>

                    <div
                      className={
                        styles.unitMain
                      }
                    >
                      <strong>
                        {unit.name}
                      </strong>

                      <span
                        className={
                          styles.unitMeta
                        }
                      >
                        {unit.symbol ??
                          "Tanpa simbol"}
                        {" • "}
                        {
                          unit.unit_type_label
                        }
                      </span>

                      <span
                        className={
                          unit.status ===
                          "ACTIVE"
                            ? styles.statusActive
                            : styles.statusInactive
                        }
                      >
                        {statusLabel(
                          unit.status,
                        )}
                      </span>
                    </div>

                    <button
                      type="button"
                      className={
                        styles.iconButton
                      }
                      aria-label={`Ubah ${unit.name}`}
                      onClick={() =>
                        openEdit(
                          unit,
                        )
                      }
                    >
                      <Pencil
                        size={17}
                      />
                    </button>
                  </article>
                ),
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
                    <th>
                      Satuan
                    </th>
                    <th>
                      Simbol
                    </th>
                    <th>
                      Jenis
                    </th>
                    <th>
                      Presisi
                    </th>
                    <th>
                      Status
                    </th>
                    <th>
                      Aksi
                    </th>
                  </tr>
                </thead>

                <tbody>
                  {visibleUnits.map(
                    (unit) => (
                      <tr
                        key={
                          unit.id
                        }
                      >
                        <td>
                          <strong>
                            {
                              unit.name
                            }
                          </strong>
                          <small>
                            {
                              unit.code
                            }
                          </small>
                        </td>

                        <td>
                          {unit.symbol ??
                            "-"}
                        </td>

                        <td>
                          {
                            unit.unit_type_label
                          }
                        </td>

                        <td>
                          {
                            unit.decimal_precision
                          }
                        </td>

                        <td>
                          <span
                            className={
                              unit.status ===
                              "ACTIVE"
                                ? styles.statusActive
                                : styles.statusInactive
                            }
                          >
                            {statusLabel(
                              unit.status,
                            )}
                          </span>
                        </td>

                        <td>
                          <div
                            className={
                              styles.rowActions
                            }
                          >
                            <button
                              type="button"
                              className={
                                styles.textButton
                              }
                              onClick={() =>
                                openEdit(
                                  unit,
                                )
                              }
                            >
                              Ubah
                            </button>

                            <button
                              type="button"
                              className={
                                styles.textButton
                              }
                              onClick={() =>
                                void toggleStatus(
                                  unit,
                                )
                              }
                            >
                              {unit.status ===
                              "ACTIVE"
                                ? "Nonaktifkan"
                                : "Aktifkan"}
                            </button>
                          </div>
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
            moduleDef.tone ===
            "amber"
              ? "blue"
              : moduleDef.tone
          }
          title={
            moduleDef.footer
              ?.title ??
            moduleDef.insight.title
          }
          description={
            moduleDef.footer
              ?.description ??
            moduleDef.insight
              .description
          }
        />

        {editorOpen ? (
          <div
            className={
              styles.modalBackdrop
            }
            role="presentation"
            onMouseDown={(
              event,
            ) => {
              if (
                event.target ===
                event.currentTarget
              ) {
                closeEditor();
              }
            }}
          >
            <section
              className={
                styles.modal
              }
              role="dialog"
              aria-modal="true"
              aria-labelledby="unit-form-title"
            >
              <header
                className={
                  styles.modalHeader
                }
              >
                <div>
                  <span>
                    Master Data
                  </span>
                  <h2
                    id="unit-form-title"
                  >
                    {editingUnit
                      ? "Ubah Satuan"
                      : "Tambah Satuan"}
                  </h2>
                </div>

                <button
                  type="button"
                  className={
                    styles.iconButton
                  }
                  onClick={
                    closeEditor
                  }
                  aria-label="Tutup"
                >
                  <X
                    size={20}
                  />
                </button>
              </header>

              <form
                className={
                  styles.form
                }
                onSubmit={
                  submitForm
                }
              >
                <label>
                  <span>
                    Kode *
                  </span>

                  <input
                    value={
                      form.code
                    }
                    maxLength={80}
                    required
                    placeholder="Contoh: M2"
                    onChange={(
                      event,
                    ) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          code:
                            event
                              .target
                              .value,
                        }),
                      )
                    }
                  />
                </label>

                <label>
                  <span>
                    Nama *
                  </span>

                  <input
                    value={
                      form.name
                    }
                    maxLength={120}
                    required
                    placeholder="Contoh: Meter Persegi"
                    onChange={(
                      event,
                    ) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          name:
                            event
                              .target
                              .value,
                        }),
                      )
                    }
                  />
                </label>

                <label>
                  <span>
                    Simbol
                  </span>

                  <input
                    value={
                      form.symbol
                    }
                    maxLength={40}
                    placeholder="Contoh: m²"
                    onChange={(
                      event,
                    ) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          symbol:
                            event
                              .target
                              .value,
                        }),
                      )
                    }
                  />
                </label>

                <label>
                  <span>
                    Jenis Satuan
                  </span>

                  <select
                    value={
                      form.unit_type
                    }
                    onChange={(
                      event,
                    ) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          unit_type:
                            event
                              .target
                              .value as CatalogUnitType,
                        }),
                      )
                    }
                  >
                    {unitTypeOptions.map(
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
                </label>

                <label>
                  <span>
                    Presisi Desimal
                  </span>

                  <select
                    value={
                      form.decimal_precision
                    }
                    onChange={(
                      event,
                    ) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          decimal_precision:
                            event
                              .target
                              .value,
                        }),
                      )
                    }
                  >
                    {Array.from({
                      length: 7,
                    }).map(
                      (_, value) => (
                        <option
                          key={
                            value
                          }
                          value={
                            value
                          }
                        >
                          {value}
                        </option>
                      ),
                    )}
                  </select>
                </label>

                {saveError ? (
                  <ActionFeedback
                    tone="error"
                    title="Satuan belum dapat disimpan"
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

                <footer
                  className={
                    styles.formActions
                  }
                >
                  <button
                    type="button"
                    className={
                      styles.secondaryAction
                    }
                    disabled={
                      saving
                    }
                    onClick={
                      closeEditor
                    }
                  >
                    Batal
                  </button>

                  <button
                    type="submit"
                    className={
                      styles.primaryAction
                    }
                    disabled={
                      saving
                    }
                  >
                    {saving
                      ? "Menyimpan..."
                      : editingUnit
                        ? "Simpan Perubahan"
                        : "Tambah Satuan"}
                  </button>
                </footer>
              </form>
            </section>
          </div>
        ) : null}
      </section>
    </TenantShell>
  );
}
