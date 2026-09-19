"use client";

import {
  ArrowLeft,
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
  apiErrorMessage,
  apiRequestId,
} from "@/lib/api/error-message";

import {
  getSupplier,
} from "@/lib/supplier/service";

import type {
  Supplier,
} from "@/types/supplier";

import {
  SupplierForm,
} from "./supplier-form";

import styles from "./supplier-edit-screen.module.css";

export function SupplierEditScreen() {
  const params =
    useParams<{
      id: string;
    }>();

  const [
    supplier,
    setSupplier,
  ] = useState<
    Supplier | null
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
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);
      setRequestId(null);

      try {
        const response =
          await getSupplier(
            params.id,
          );

        if (!cancelled) {
          setSupplier(
            response.data,
          );
        }
      } catch (loadError) {
        if (!cancelled) {
          setError(
            apiErrorMessage(
              loadError,
              "Data pemasok belum berhasil dimuat.",
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
      <div
        className={
          styles.loading
        }
      >
        Memuat data pemasok...
      </div>
    );
  }

  if (
    error ||
    !supplier
  ) {
    return (
      <section
        className={
          styles.errorPage
        }
      >
        <Link
          href="/app/pemasok"
          className={
            styles.backLink
          }
        >
          <ArrowLeft size={17} />
          Kembali ke Pemasok
        </Link>

        <ActionFeedback
          tone="error"
          title="Data pemasok belum dapat diubah"
          message={
            error ??
            "Pemasok tidak ditemukan."
          }
          requestId={
            requestId
          }
        />
      </section>
    );
  }

  return (
    <SupplierForm
      mode="edit"
      initialSupplier={
        supplier
      }
    />
  );
}
