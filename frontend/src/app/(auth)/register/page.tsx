"use client";

import {
  Building2,
  LockKeyhole,
  Mail,
  Phone,
  UserRound,
} from "lucide-react";
import Link from "next/link";
import {
  useRouter,
} from "next/navigation";
import {
  FormEvent,
  useState,
} from "react";

import {
  SubmitArea,
} from "@/components/feedback/submit-area";
import {
  AuthShell,
} from "@/components/layout/auth-shell";
import {
  Brand,
} from "@/components/ui/brand";
import {
  Button,
} from "@/components/ui/button";
import {
  Card,
} from "@/components/ui/card";
import {
  Input,
} from "@/components/ui/input";

import {
  ApiClientError,
} from "@/lib/api/types";
import {
  register,
} from "@/lib/auth/register";
import {
  setSelectedContext,
} from "@/lib/auth/active-context";

import styles from "./register.module.css";

function detectTimezone(): string | null {
  try {
    return (
      Intl.DateTimeFormat()
        .resolvedOptions()
        .timeZone || null
    );
  } catch {
    return null;
  }
}

function detectLocale(): string | null {
  if (
    typeof navigator === "undefined"
  ) {
    return null;
  }

  return navigator.language || null;
}

export default function RegisterPage() {
  const router = useRouter();

  const [name, setName] =
    useState("");

  const [tenantName, setTenantName] =
    useState("");

  const [email, setEmail] =
    useState("");

  const [phone, setPhone] =
    useState("");

  const [password, setPassword] =
    useState("");

  const [
    passwordConfirmation,
    setPasswordConfirmation,
  ] = useState("");

  const [error, setError] =
    useState<string | null>(null);

  const [
    passwordConfirmationError,
    setPasswordConfirmationError,
  ] = useState<string | null>(null);

  const [loading, setLoading] =
    useState(false);

  async function handleSubmit(
    event: FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    setError(null);
    setPasswordConfirmationError(null);

    if (
      password !==
      passwordConfirmation
    ) {
      setPasswordConfirmationError(
        "Ulangi kata sandi harus sama dengan kata sandi di atas.",
      );

      return;
    }

    setLoading(true);

    try {
      const response =
        await register({
          name,
          tenant_name:
            tenantName,

          email,

          phone:
            phone.trim() === ""
              ? null
              : phone.trim(),

          password,

          password_confirmation:
            passwordConfirmation,

          timezone:
            detectTimezone(),

          locale:
            detectLocale(),
        });

      if (
        !response.data.tenant
      ) {
        setError(
          "Akun berhasil dibuat, tetapi usaha aktif belum dapat disiapkan.",
        );

        return;
      }

      setSelectedContext({
        type: "TENANT",
        tenantId:
          response.data.tenant.id,
      });

      router.replace("/app");
    } catch (caught) {
      if (
        caught instanceof
        ApiClientError
      ) {
        setError(
          caught.message,
        );
      } else {
        setError(
          "Pendaftaran tidak dapat diproses. Silakan coba lagi.",
        );
      }
    } finally {
      setLoading(false);
    }
  }

  return (
    <AuthShell>
      <Card className={styles.card}>
        <div className={styles.cardBrand}>
          <Brand />
        </div>
        <header
          className={styles.header}
        >
          <h1 className={styles.title}>
            Mulai Pakai SIGNOVA
          </h1>

          <p
            className={
              styles.description
            }
          >
            Mulai dengan data dasar
            bisnis Anda. Pengaturan lain
            dapat dilengkapi setelah
            masuk.
          </p>
        </header>

        <form
          className={styles.form}
          onSubmit={handleSubmit}
        >
          <Input
            label="Nama Lengkap"
            type="text"
            autoComplete="name"
            placeholder="Nama Anda"
            value={name}
            onChange={(event) =>
              setName(
                event.target.value,
              )
            }
            leadingIcon={
              <UserRound size={18} />
            }
            required
          />

          <Input
            label="Nama Usaha"
            type="text"
            autoComplete="organization"
            placeholder="Nama bisnis atau usaha"
            value={tenantName}
            onChange={(event) =>
              setTenantName(
                event.target.value,
              )
            }
            leadingIcon={
              <Building2 size={18} />
            }
            required
          />

          <div className={styles.grid}>
            <Input
              label="Email"
              type="email"
              autoComplete="email"
              placeholder="nama@bisnis.com"
              value={email}
              onChange={(event) =>
                setEmail(
                  event.target.value,
                )
              }
              leadingIcon={
                <Mail size={18} />
              }
              required
            />

            <Input
              label="No. WhatsApp"
              type="tel"
              autoComplete="tel"
              placeholder="Opsional"
              value={phone}
              onChange={(event) =>
                setPhone(
                  event.target.value,
                )
              }
              leadingIcon={
                <Phone size={18} />
              }
              helperText="Dapat digunakan untuk OTP dan notifikasi setelah diverifikasi."
            />
          </div>

          <div className={styles.grid}>
            <Input
              label="Kata Sandi"
              type="password"
              autoComplete="new-password"
              placeholder="Minimal 8 karakter"
              value={password}
              onChange={(event) =>
                setPassword(
                  event.target.value,
                )
              }
              leadingIcon={
                <LockKeyhole
                  size={18}
                />
              }
              minLength={8}
              required
            />

            <Input
              label="Ulangi Kata Sandi"
              type="password"
              autoComplete="new-password"
              placeholder="Ulangi kata sandi"
              value={
                passwordConfirmation
              }
              onChange={(event) => {
                setPasswordConfirmation(
                  event.target.value,
                );

                if (
                  passwordConfirmationError
                ) {
                  setPasswordConfirmationError(
                    null,
                  );
                }
              }}
              error={
                passwordConfirmationError ??
                undefined
              }
              leadingIcon={
                <LockKeyhole
                  size={18}
                />
              }
              minLength={8}
              required
            />
          </div>

          <p className={styles.note}>
            Dengan mendaftar, akun
            pertama akan menjadi pemilik
            workspace bisnis yang baru.
          </p>

          <SubmitArea
            feedback={
              error
                ? {
                    tone: "error",
                    title:
                      "Akun belum berhasil dibuat",
                    message: error,
                  }
                : undefined
            }
          >
            <Button
              type="submit"
              fullWidth
              loading={loading}
              loadingLabel="Membuat akun..."
            >
              Daftar
            </Button>
          </SubmitArea>
        </form>

        <p className={styles.footer}>
          Sudah punya akun?{" "}
          <Link
            className={styles.link}
            href="/login"
          >
            Masuk
          </Link>
        </p>
      </Card>
    </AuthShell>
  );
}
