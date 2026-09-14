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
  getCustomer,
} from "@/lib/customer/service";

import {
  apiErrorMessage,
  apiRequestId,
} from "@/lib/api/error-message";

import type {
  Customer,
} from "@/types/customer";

import {
  CustomerForm,
} from "./customer-form";

import styles from "./customer-edit-screen.module.css";

export function CustomerEditScreen() {
  const params =
    useParams<{
      id: string;
    }>();

  const [
    customer,
    setCustomer,
  ] = useState<
    Customer | null
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
    let cancelled =
      false;

    async function load() {
      setLoading(true);
      setError(null);
      setRequestId(null);

      try {
        const response =
          await getCustomer(
            params.id,
          );

        if (!cancelled) {
          setCustomer(
            response.data,
          );
        }
      } catch (loadError) {
        if (!cancelled) {
          setError(
            apiErrorMessage(
              loadError,
              "Data pelanggan belum berhasil dimuat.",
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
        Memuat data pelanggan...
      </div>
    );
  }

  if (
    error ||
    !customer
  ) {
    return (
      <section
        className={
          styles.errorPage
        }
      >
        <Link
          href="/app/pelanggan"
          className={
            styles.backLink
          }
        >
          <ArrowLeft
            size={17}
          />

          Kembali ke Pelanggan
        </Link>

        <ActionFeedback
          tone="error"
          title="Data pelanggan belum dapat diubah"
          message={
            error ??
            "Pelanggan tidak ditemukan."
          }
          requestId={
            requestId
          }
        />
      </section>
    );
  }

  return (
    <CustomerForm
      mode="edit"
      initialCustomer={
        customer
      }
    />
  );
}
