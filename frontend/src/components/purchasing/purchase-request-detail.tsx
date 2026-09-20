"use client";

import {
  ArrowLeft,
  CalendarDays,
  CheckCircle2,
  ClipboardCheck,
  ClipboardList,
  Pencil,
  ReceiptText,
  RotateCcw,
  Send,
  XCircle,
} from "lucide-react";

import Link from "next/link";

import {
  useEffect,
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
  formatPurchaseDate,
  formatPurchaseMoney,
  purchaseItemTypeLabel,
  purchaseRequestStatusDescription,
  purchaseRequestStatusLabel,
} from "@/lib/purchasing/labels";

import {
  approvePurchaseRequest,
  cancelPurchaseRequest,
  getPurchaseRequest,
  rejectPurchaseRequest,
  revisePurchaseRequest,
  submitPurchaseRequest,
} from "@/lib/purchasing/service";

import type {
  PurchaseRequest,
} from "@/types/purchasing";

import styles from "./purchase-request.module.css";


type ReasonMode =
  | "REJECT"
  | "CANCEL"
  | null;


type PurchaseRequestDetailProps = {
  purchaseRequestId: string;
};


export function PurchaseRequestDetail({
  purchaseRequestId,
}: PurchaseRequestDetailProps) {
  const router =
    useRouter();

  const moduleDef =
    getModule(
      "purchase-requests",
    );

  const [
    data,
    setData,
  ] = useState<
    PurchaseRequest | null
  >(null);

  const [
    loading,
    setLoading,
  ] = useState(true);

  const [
    error,
    setError,
  ] = useState<unknown>(null);

  const [
    feedback,
    setFeedback,
  ] = useState<
    string | null
  >(null);

  const [
    busy,
    setBusy,
  ] = useState<
    string | null
  >(null);

  const [
    canRequest,
    setCanRequest,
  ] = useState(false);

  const [
    canApprove,
    setCanApprove,
  ] = useState(false);

  const [
    canCreatePo,
    setCanCreatePo,
  ] = useState(false);

  const [
    reasonMode,
    setReasonMode,
  ] = useState<ReasonMode>(
    null,
  );

  const [
    reason,
    setReason,
  ] = useState("");


  useEffect(() => {
    let cancelled = false;

    void getActiveCapabilities()
      .then((response) => {
        if (cancelled) {
          return;
        }

        const codes =
          response.data
            .capability_codes;

        setCanRequest(
          codes.includes(
            "purchasing.request",
          ),
        );

        setCanApprove(
          codes.includes(
            "purchasing.approve_request",
          ),
        );

        setCanCreatePo(
          codes.includes(
            "purchasing.create_po",
          ),
        );
      })
      .catch(() => {
        if (!cancelled) {
          setCanRequest(false);
          setCanApprove(false);
          setCanCreatePo(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);


  useEffect(() => {
    let cancelled = false;

    void getPurchaseRequest(
      purchaseRequestId,
    )
      .then((response) => {
        if (!cancelled) {
          setData(
            response.data,
          );
        }
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


  async function runAction(
    action:
      | "SUBMIT"
      | "APPROVE"
      | "REVISE",
  ) {
    if (
      !data ||
      busy
    ) {
      return;
    }

    setBusy(action);
    setError(null);
    setFeedback(null);

    try {
      if (
        action === "SUBMIT"
      ) {
        const response =
          await submitPurchaseRequest(
            data.id,
          );

        setData(
          response.data,
        );

        setFeedback(
          response.message ??
            "Permintaan Pembelian berhasil diajukan.",
        );
      }

      if (
        action === "APPROVE"
      ) {
        const response =
          await approvePurchaseRequest(
            data.id,
          );

        setData(
          response.data,
        );

        setFeedback(
          response.message ??
            "Permintaan Pembelian berhasil disetujui.",
        );
      }

      if (
        action === "REVISE"
      ) {
        const response =
          await revisePurchaseRequest(
            data.id,
          );

        router.push(
          `/app/operasional/permintaan-pembelian/${response.data.id}/ubah`,
        );
      }
    } catch (caught) {
      setError(caught);
    } finally {
      setBusy(null);
    }
  }


  async function submitReason() {
    if (
      !data ||
      !reasonMode ||
      busy ||
      reason.trim().length < 3
    ) {
      return;
    }

    setBusy(
      reasonMode,
    );

    setError(null);
    setFeedback(null);

    try {
      const response =
        reasonMode ===
        "REJECT"
          ? await rejectPurchaseRequest(
              data.id,
              reason.trim(),
            )
          : await cancelPurchaseRequest(
              data.id,
              reason.trim(),
            );

      setData(
        response.data,
      );

      setFeedback(
        response.message ??
          (
            reasonMode === "REJECT"
              ? "Permintaan Pembelian berhasil ditolak."
              : "Permintaan Pembelian berhasil dibatalkan."
          ),
      );

      setReason("");
      setReasonMode(null);
    } catch (caught) {
      setError(caught);
    } finally {
      setBusy(null);
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


  if (
    error &&
    !data
  ) {
    return (
      <section
        className={styles.page}
      >
        <Link
          href="/app/operasional/permintaan-pembelian"
          className={
            styles.backLink
          }
        >
          <ArrowLeft size={17} />
          Permintaan Pembelian
        </Link>

        <ActionFeedback
          tone="error"
          title="Permintaan belum dapat dibuka"
          message={
            apiErrorMessage(
              error,
              "Permintaan Pembelian tidak ditemukan.",
            )
          }
          requestId={
            apiRequestId(
              error,
            )
          }
        />
      </section>
    );
  }


  if (!data) {
    return null;
  }


  const items =
    data.items ??
    [];


  return (
    <section
      className={styles.page}
    >
      <div
        className={
          styles.detailTopBar
        }
      >
        <Link
          href="/app/operasional/permintaan-pembelian"
          className={
            styles.backLink
          }
        >
          <ArrowLeft size={17} />
          Permintaan Pembelian
        </Link>

        {data.status ===
          "DRAFT" &&
        canRequest ? (
          <Link
            href={
              `/app/operasional/permintaan-pembelian/${data.id}/ubah`
            }
            className={
              styles.secondaryLink
            }
          >
            <Pencil size={17} />
            Ubah Draf
          </Link>
        ) : null}
      </div>

      <ModuleHero
        eyebrow="Permintaan Pembelian"
        title={
          data.request_number
        }
        description={
          purchaseRequestStatusDescription(
            data.status,
          )
        }
        icon={
          ClipboardList
        }
        tone={
          moduleDef.tone
        }
        insightTitle={
          purchaseRequestStatusLabel(
            data.status,
          )
        }
        insightDescription={
          data.notes?.trim() ||
          "Tidak ada catatan tambahan."
        }
      />

      {feedback ? (
        <ActionFeedback
          tone="success"
          title="Berhasil"
          message={feedback}
        />
      ) : null}

      {error ? (
        <ActionFeedback
          tone="error"
          title="Tindakan belum dapat diproses"
          message={
            apiErrorMessage(
              error,
              "Silakan coba kembali.",
            )
          }
          requestId={
            apiRequestId(
              error,
            )
          }
        />
      ) : null}

      <div
        className={
          styles.detailGrid
        }
      >
        <section
          className={
            styles.detailCard
          }
        >
          <header
            className={
              styles.detailCardHeader
            }
          >
            <ClipboardCheck
              size={20}
            />

            <div>
              <h2>
                Informasi Permintaan
              </h2>

              <p>
                Ringkasan dokumen
                kebutuhan pembelian.
              </p>
            </div>
          </header>

          <dl>
            <div>
              <dt>Status</dt>

              <dd>
                <span
                  className={
                    styles.statusBadge
                  }
                  data-status={
                    data.status
                  }
                >
                  {purchaseRequestStatusLabel(
                    data.status,
                  )}
                </span>
              </dd>
            </div>

            <div>
              <dt>
                Tanggal Dibutuhkan
              </dt>

              <dd>
                <CalendarDays
                  size={15}
                />

                {formatPurchaseDate(
                  data.needed_at,
                )}
              </dd>
            </div>

            <div>
              <dt>
                Total Estimasi
              </dt>

              <dd>
                {formatPurchaseMoney(
                  data.estimated_total,
                )}
              </dd>
            </div>

            <div>
              <dt>
                Mata Uang
              </dt>

              <dd>
                {data.currency}
              </dd>
            </div>
          </dl>
        </section>

        <section
          className={
            styles.detailCard
          }
        >
          <header
            className={
              styles.detailCardHeader
            }
          >
            <ReceiptText
              size={20}
            />

            <div>
              <h2>
                Catatan & Keputusan
              </h2>

              <p>
                Informasi yang perlu
                diperhatikan.
              </p>
            </div>
          </header>

          <dl>
            <div>
              <dt>
                Catatan
              </dt>

              <dd>
                {data.notes?.trim() ||
                  "-"}
              </dd>
            </div>

            {data.rejection_reason ? (
              <div>
                <dt>
                  Alasan Ditolak
                </dt>

                <dd>
                  {
                    data
                      .rejection_reason
                  }
                </dd>
              </div>
            ) : null}

            {data.cancellation_reason ? (
              <div>
                <dt>
                  Alasan Dibatalkan
                </dt>

                <dd>
                  {
                    data
                      .cancellation_reason
                  }
                </dd>
              </div>
            ) : null}
          </dl>
        </section>
      </div>

      <section
        className={
          styles.detailCard
        }
      >
        <header
          className={
            styles.detailCardHeader
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
              {items.length} item
              dalam permintaan ini.
            </p>
          </div>
        </header>

        <div
          className={
            styles.detailItemList
          }
        >
          {items.map(
            (
              item,
              index,
            ) => (
              <article
                key={item.id}
                className={
                  styles.detailItemCard
                }
              >
                <div
                  className={
                    styles.detailItemTop
                  }
                >
                  <div>
                    <span
                      className={
                        styles.itemIndex
                      }
                    >
                      Item{" "}
                      {index + 1}
                    </span>

                    <strong>
                      {item.name}
                    </strong>
                  </div>

                  <span
                    className={
                      styles.itemType
                    }
                  >
                    {purchaseItemTypeLabel(
                      item.item_type,
                    )}
                  </span>
                </div>

                {item.description ? (
                  <p
                    className={
                      styles.detailItemDescription
                    }
                  >
                    {item.description}
                  </p>
                ) : null}

                <div
                  className={
                    styles.itemMetrics
                  }
                >
                  <span>
                    Jumlah

                    <strong>
                      {item.quantity}{" "}
                      {item.unit_symbol ??
                        ""}
                    </strong>
                  </span>

                  <span>
                    Harga Satuan

                    <strong>
                      {formatPurchaseMoney(
                        item
                          .estimated_unit_price,
                      )}
                    </strong>
                  </span>

                  <span>
                    Estimasi

                    <strong>
                      {formatPurchaseMoney(
                        item.amount,
                      )}
                    </strong>
                  </span>
                </div>
              </article>
            ),
          )}
        </div>
      </section>

      <section
        className={
          styles.nextActionCard
        }
      >
        <div
          className={
            styles.nextActionCopy
          }
        >
          <span>
            LANGKAH BERIKUTNYA
          </span>

          {data.status ===
          "DRAFT" ? (
            <>
              <h2>
                Ajukan Permintaan
              </h2>

              <p>
                Setelah diajukan,
                permintaan menunggu
                persetujuan.
              </p>
            </>
          ) : null}

          {data.status ===
          "SUBMITTED" ? (
            <>
              <h2>
                Tinjau Permintaan
              </h2>

              <p>
                Permintaan menunggu
                pihak dengan hak
                persetujuan.
              </p>
            </>
          ) : null}

          {data.status ===
          "REJECTED" ? (
            <>
              <h2>
                Perbaiki Permintaan
              </h2>

              <p>
                Kembalikan ke Draf,
                perbaiki kebutuhan,
                lalu ajukan kembali.
              </p>
            </>
          ) : null}

          {data.status ===
          "APPROVED" ? (
            <>
              <h2>
                Lanjut ke Pesanan Pembelian
              </h2>

              <p>
                Permintaan telah
                disetujui dan siap
                dilanjutkan ke PO.
              </p>
            </>
          ) : null}

          {data.status ===
          "CANCELLED" ? (
            <>
              <h2>
                Proses Selesai
              </h2>

              <p>
                Permintaan telah
                dibatalkan.
              </p>
            </>
          ) : null}
        </div>

        <div
          className={
            styles.workflowActions
          }
        >
          {data.status ===
            "DRAFT" &&
          canRequest ? (
            <Button
              type="button"
              leadingIcon={
                <Send size={18} />
              }
              loading={
                busy ===
                "SUBMIT"
              }
              loadingLabel="Mengajukan..."
              onClick={() =>
                void runAction(
                  "SUBMIT",
                )
              }
            >
              Ajukan Permintaan
            </Button>
          ) : null}

          {data.status ===
            "SUBMITTED" &&
          canApprove ? (
            <>
              <Button
                type="button"
                leadingIcon={
                  <CheckCircle2
                    size={18}
                  />
                }
                loading={
                  busy ===
                  "APPROVE"
                }
                loadingLabel="Menyetujui..."
                onClick={() =>
                  void runAction(
                    "APPROVE",
                  )
                }
              >
                Setujui
              </Button>

              <Button
                type="button"
                variant="secondary"
                leadingIcon={
                  <XCircle
                    size={18}
                  />
                }
                disabled={
                  Boolean(busy)
                }
                onClick={() => {
                  setReason("");
                  setReasonMode(
                    "REJECT",
                  );
                }}
              >
                Tolak
              </Button>
            </>
          ) : null}

          {data.status ===
            "REJECTED" &&
          canRequest ? (
            <Button
              type="button"
              leadingIcon={
                <RotateCcw
                  size={18}
                />
              }
              loading={
                busy ===
                "REVISE"
              }
              loadingLabel="Membuka Draf..."
              onClick={() =>
                void runAction(
                  "REVISE",
                )
              }
            >
              Perbaiki Permintaan
            </Button>
          ) : null}

          {data.status ===
            "APPROVED" &&
          canCreatePo ? (
            <Link
              href="/app/operasional/pesanan-pembelian"
              className={
                styles.primaryLink
              }
            >
              <ReceiptText
                size={18}
              />
              Pesanan Pembelian
            </Link>
          ) : null}

          {[
            "DRAFT",
            "SUBMITTED",
            "REJECTED",
          ].includes(
            data.status,
          ) &&
          canRequest ? (
            <Button
              type="button"
              variant="ghost"
              disabled={
                Boolean(busy)
              }
              onClick={() => {
                setReason("");
                setReasonMode(
                  "CANCEL",
                );
              }}
            >
              Batalkan Permintaan
            </Button>
          ) : null}
        </div>
      </section>

      {reasonMode ? (
        <div
          className={
            styles.dialogOverlay
          }
          onMouseDown={() => {
            if (!busy) {
              setReasonMode(null);
            }
          }}
        >
          <section
            className={
              styles.reasonDialog
            }
            role="dialog"
            aria-modal="true"
            aria-labelledby="pr-reason-title"
            onMouseDown={
              (event) =>
                event.stopPropagation()
            }
          >
            <header>
              <h2
                id="pr-reason-title"
              >
                {reasonMode ===
                "REJECT"
                  ? "Tolak Permintaan"
                  : "Batalkan Permintaan"}
              </h2>

              <p>
                Alasan akan disimpan
                pada riwayat dokumen.
              </p>
            </header>

            <label
              className={
                styles.field
              }
            >
              <span>
                Alasan
              </span>

              <textarea
                autoFocus
                rows={4}
                minLength={3}
                maxLength={1000}
                value={reason}
                onChange={(event) =>
                  setReason(
                    event.target.value,
                  )
                }
                placeholder="Tuliskan alasan minimal 3 karakter..."
                disabled={
                  Boolean(busy)
                }
              />
            </label>

            <div
              className={
                styles.dialogActions
              }
            >
              <Button
                type="button"
                variant="secondary"
                disabled={
                  Boolean(busy)
                }
                onClick={() =>
                  setReasonMode(null)
                }
              >
                Kembali
              </Button>

              <Button
                type="button"
                loading={
                  busy ===
                  reasonMode
                }
                disabled={
                  reason.trim()
                    .length < 3
                }
                onClick={() =>
                  void submitReason()
                }
              >
                {reasonMode ===
                "REJECT"
                  ? "Konfirmasi Tolak"
                  : "Konfirmasi Batalkan"}
              </Button>
            </div>
          </section>
        </div>
      ) : null}
    </section>
  );
}
