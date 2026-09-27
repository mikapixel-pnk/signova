"use client";

import {
  Boxes,
  PackageCheck,
  PackageX,
  RefreshCw,
  Search,
} from "lucide-react";

import {
  useEffect,
  useMemo,
  useState,
} from "react";

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
  getActiveCapabilities,
} from "@/lib/auth/active-capabilities-service";

import {
  listCatalogUnits,
} from "@/lib/catalog/service";

import {
  inventoryStatusLabels,
} from "@/lib/inventory/labels";

import {
  listStockBalances,
} from "@/lib/inventory/service";

import type {
  CatalogUnit,
} from "@/types/catalog";

import type {
  StockBalance,
} from "@/types/inventory";

import styles from "./inventory-stock-workspace.module.css";


function normalized(
  value: string,
): string {
  return value
    .trim()
    .toLocaleLowerCase(
      "id-ID",
    );
}


function formatQuantity(
  value: string,
): string {
  const match =
    /^(-?)(\d+)(?:\.(\d+))?$/.exec(
      value.trim(),
    );

  if (!match) {
    return value;
  }

  const sign =
    match[1];

  const whole =
    match[2]
      .replace(
        /^0+(?=\d)/,
        "",
      )
      .replace(
        /\B(?=(\d{3})+(?!\d))/g,
        ".",
      );

  const fraction =
    (
      match[3] ??
      ""
    ).replace(
      /0+$/,
      "",
    );

  return (
    sign
    + whole
    + (
      fraction
        ? `,${fraction}`
        : ""
    )
  );
}


function quantityWithUnit(
  value: string,
  symbol:
    | string
    | null,
): string {
  const formatted =
    formatQuantity(
      value,
    );

  return symbol
    ? `${formatted} ${symbol}`
    : formatted;
}


function isZero(
  value: string,
): boolean {
  return /^-?0+(?:\.0+)?$/.test(
    value.trim(),
  );
}


export function InventoryStockWorkspace() {
  const [
    canView,
    setCanView,
  ] = useState<
    boolean | null
  >(null);

  const [
    canViewCatalog,
    setCanViewCatalog,
  ] = useState(false);

  const [
    balances,
    setBalances,
  ] = useState<
    StockBalance[]
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
    reloadToken,
    setReloadToken,
  ] = useState(0);


  useEffect(() => {
    let cancelled = false;

    void getActiveCapabilities()
      .then((response) => {
        if (cancelled) {
          return;
        }

        const capabilityCodes =
          response.data
            .capability_codes;

        const allowed =
          capabilityCodes.includes(
            "inventory.view",
          );

        setCanView(
          allowed,
        );

        setCanViewCatalog(
          capabilityCodes.includes(
            "catalog.view",
          ),
        );

        if (!allowed) {
          setLoading(false);
        }
      })
      .catch((caught) => {
        if (cancelled) {
          return;
        }

        setCanView(false);
        setCanViewCatalog(false);
        setError(caught);
        setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, []);


  useEffect(() => {
    if (canView !== true) {
      return;
    }

    let cancelled = false;

    const unitRequest =
      canViewCatalog
        ? listCatalogUnits()
            .catch(
              () => null,
            )
        : Promise.resolve(
            null,
          );

    void Promise.all([
      listStockBalances(),
      unitRequest,
    ])
      .then(([
        stockResponse,
        unitResponse,
      ]) => {
        if (cancelled) {
          return;
        }

        setBalances(
          stockResponse.data,
        );

        setUnits(
          unitResponse?.data ??
          [],
        );

        setError(null);
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
    canView,
    canViewCatalog,
    reloadToken,
  ]);


  const unitById =
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


  const visibleBalances =
    useMemo(
      () => {
        const query =
          normalized(
            search,
          );

        if (!query) {
          return balances;
        }

        return balances.filter(
          (balance) =>
            [
              balance.code,
              balance.name,
              balance.category,
            ].some(
              (value) =>
                value
                  ? normalized(
                      value,
                    ).includes(
                      query,
                    )
                  : false,
            ),
        );
      },
      [
        balances,
        search,
      ],
    );


  const activeCount =
    balances.filter(
      (balance) =>
        balance.status ===
        "ACTIVE",
    ).length;

  const zeroCount =
    balances.filter(
      (balance) =>
        isZero(
          balance.on_hand,
        ),
    ).length;


  function reloadData() {
    setLoading(true);
    setError(null);

    setReloadToken(
      (current) =>
        current + 1,
    );
  }


  return (
    <section
      className={
        styles.page
      }
    >
      <ModuleHero
        eyebrow="Operasional"
        title="Stok"
        description="Lihat saldo barang yang dipantau berdasarkan pergerakan stok yang benar-benar tercatat."
        icon={Boxes}
        tone="green"
        insightTitle="Saldo tidak diedit secara langsung"
        insightDescription="Nilai pada halaman ini berasal dari ledger pergerakan stok. Penerimaan dan transaksi stok menjadi sumber perubahan saldo."
      />

      {canView === false ? (
        <ActionFeedback
          tone="error"
          title="Stok belum dapat dibuka"
          message={
            error
              ? apiErrorMessage(
                  error,
                  "Hak akses Stok belum dapat diverifikasi.",
                )
              : "Akun Anda belum memiliki hak untuk melihat stok."
          }
          requestId={
            error
              ? apiRequestId(
                  error,
                )
              : null
          }
        />
      ) : null}

      {canView === true ? (
        <>
          <section
            className={
              styles.summaryGrid
            }
            aria-label="Ringkasan stok"
          >
            <article
              className={
                styles.summaryCard
              }
            >
              <span
                className={
                  styles.summaryIcon
                }
                aria-hidden="true"
              >
                <Boxes
                  size={20}
                />
              </span>

              <div>
                <strong>
                  {
                    balances.length
                  }
                </strong>

                <span>
                  Barang dipantau
                </span>
              </div>
            </article>

            <article
              className={
                styles.summaryCard
              }
            >
              <span
                className={
                  styles.summaryIcon
                }
                aria-hidden="true"
              >
                <PackageCheck
                  size={20}
                />
              </span>

              <div>
                <strong>
                  {
                    activeCount
                  }
                </strong>

                <span>
                  Barang aktif
                </span>
              </div>
            </article>

            <article
              className={
                styles.summaryCard
              }
            >
              <span
                className={
                  styles.summaryIcon
                }
                aria-hidden="true"
              >
                <PackageX
                  size={20}
                />
              </span>

              <div>
                <strong>
                  {
                    zeroCount
                  }
                </strong>

                <span>
                  Saldo nol
                </span>
              </div>
            </article>
          </section>

          <div
            className={
              styles.infoNote
            }
          >
            <strong>
              Total semua gudang
            </strong>

            <span>
              Saldo yang tampil merupakan
              total per barang dari seluruh
              pergerakan stok dalam usaha
              aktif.
            </span>
          </div>

          <section
            className={
              styles.toolbar
            }
            aria-label="Pencarian stok"
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
                value={
                  search
                }
                placeholder="Cari nama, kode, atau kategori"
                aria-label="Cari stok"
                onChange={(
                  event,
                ) =>
                  setSearch(
                    event.target
                      .value,
                  )
                }
              />
            </label>

            <button
              type="button"
              className={
                styles.refreshButton
              }
              disabled={
                loading
              }
              onClick={
                reloadData
              }
            >
              <RefreshCw
                size={18}
                aria-hidden="true"
              />

              Muat ulang
            </button>
          </section>

          {error ? (
            <ActionFeedback
              tone="error"
              title="Saldo stok belum dapat dimuat"
              message={
                apiErrorMessage(
                  error,
                  "Data stok belum berhasil dimuat. Silakan coba lagi.",
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
                styles.loadingCard
              }
              role="status"
              aria-live="polite"
            >
              Memuat saldo stok…
            </div>
          ) : null}

          {!loading &&
          !error &&
          balances.length ===
            0 ? (
            <div
              className={
                styles.emptyState
              }
            >
              <Boxes
                size={30}
                aria-hidden="true"
              />

              <strong>
                Belum ada barang yang dipantau
              </strong>

              <span>
                Aktifkan pemantauan stok pada
                menu Barang Persediaan agar
                saldo barang muncul di sini.
              </span>
            </div>
          ) : null}

          {!loading &&
          !error &&
          balances.length >
            0 &&
          visibleBalances.length ===
            0 ? (
            <div
              className={
                styles.emptyState
              }
            >
              <Search
                size={28}
                aria-hidden="true"
              />

              <strong>
                Stok tidak ditemukan
              </strong>

              <span>
                Coba gunakan nama, kode, atau
                kategori yang berbeda.
              </span>
            </div>
          ) : null}

          {!loading &&
          !error &&
          visibleBalances.length >
            0 ? (
            <>
              <div
                className={
                  styles.mobileList
                }
              >
                {visibleBalances.map(
                  (balance) => {
                    const unit =
                      balance.unit_id
                        ? unitById.get(
                            balance.unit_id,
                          )
                        : null;

                    const symbol =
                      unit?.symbol ??
                      null;

                    return (
                      <article
                        key={
                          balance.material_id
                        }
                        className={
                          styles.stockCard
                        }
                      >
                        <header
                          className={
                            styles.cardHeader
                          }
                        >
                          <div>
                            <span
                              className={
                                styles.code
                              }
                            >
                              {
                                balance.code ??
                                "Tanpa kode"
                              }
                            </span>

                            <h2>
                              {
                                balance.name
                              }
                            </h2>
                          </div>

                          <span
                            className={
                              styles.statusBadge
                            }
                          >
                            {
                              inventoryStatusLabels[
                                balance.status
                              ]
                            }
                          </span>
                        </header>

                        <div
                          className={
                            styles.balanceBlock
                          }
                        >
                          <span>
                            Saldo saat ini
                          </span>

                          <strong>
                            {
                              quantityWithUnit(
                                balance.on_hand,
                                symbol,
                              )
                            }
                          </strong>

                          {balance.unit_id &&
                          !symbol ? (
                            <small>
                              Satuan tersimpan
                              (akses terbatas)
                            </small>
                          ) : null}
                        </div>

                        <dl
                          className={
                            styles.metaGrid
                          }
                        >
                          <div>
                            <dt>
                              Kategori
                            </dt>

                            <dd>
                              {
                                balance.category ??
                                "Belum ditentukan"
                              }
                            </dd>
                          </div>

                          <div>
                            <dt>
                              Minimum
                            </dt>

                            <dd>
                              {
                                balance.minimum_stock
                                  ? quantityWithUnit(
                                      balance.minimum_stock,
                                      symbol,
                                    )
                                  : "—"
                              }
                            </dd>
                          </div>

                          <div>
                            <dt>
                              Pesan ulang
                            </dt>

                            <dd>
                              {
                                balance.reorder_point
                                  ? quantityWithUnit(
                                      balance.reorder_point,
                                      symbol,
                                    )
                                  : "—"
                              }
                            </dd>
                          </div>

                          <div>
                            <dt>
                              Maksimum
                            </dt>

                            <dd>
                              {
                                balance.maximum_stock
                                  ? quantityWithUnit(
                                      balance.maximum_stock,
                                      symbol,
                                    )
                                  : "—"
                              }
                            </dd>
                          </div>
                        </dl>
                      </article>
                    );
                  },
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
                        Barang
                      </th>

                      <th>
                        Kategori
                      </th>

                      <th>
                        Saldo
                      </th>

                      <th>
                        Minimum
                      </th>

                      <th>
                        Pesan ulang
                      </th>

                      <th>
                        Maksimum
                      </th>

                      <th>
                        Status
                      </th>
                    </tr>
                  </thead>

                  <tbody>
                    {visibleBalances.map(
                      (balance) => {
                        const unit =
                          balance.unit_id
                            ? unitById.get(
                                balance.unit_id,
                              )
                            : null;

                        const symbol =
                          unit?.symbol ??
                          null;

                        return (
                          <tr
                            key={
                              balance.material_id
                            }
                          >
                            <td>
                              <strong>
                                {
                                  balance.name
                                }
                              </strong>

                              <span>
                                {
                                  balance.code ??
                                  "Tanpa kode"
                                }
                              </span>
                            </td>

                            <td>
                              {
                                balance.category ??
                                "Belum ditentukan"
                              }
                            </td>

                            <td>
                              <strong>
                                {
                                  quantityWithUnit(
                                    balance.on_hand,
                                    symbol,
                                  )
                                }
                              </strong>

                              {balance.unit_id &&
                              !symbol ? (
                                <span>
                                  Satuan akses terbatas
                                </span>
                              ) : null}
                            </td>

                            <td>
                              {
                                balance.minimum_stock
                                  ? quantityWithUnit(
                                      balance.minimum_stock,
                                      symbol,
                                    )
                                  : "—"
                              }
                            </td>

                            <td>
                              {
                                balance.reorder_point
                                  ? quantityWithUnit(
                                      balance.reorder_point,
                                      symbol,
                                    )
                                  : "—"
                              }
                            </td>

                            <td>
                              {
                                balance.maximum_stock
                                  ? quantityWithUnit(
                                      balance.maximum_stock,
                                      symbol,
                                    )
                                  : "—"
                              }
                            </td>

                            <td>
                              {
                                inventoryStatusLabels[
                                  balance.status
                                ]
                              }
                            </td>
                          </tr>
                        );
                      },
                    )}
                  </tbody>
                </table>
              </div>
            </>
          ) : null}
        </>
      ) : null}
    </section>
  );
}
