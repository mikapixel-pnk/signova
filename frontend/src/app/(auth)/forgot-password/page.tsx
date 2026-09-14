"use client";

import {
  ArrowLeft,
  CheckCircle2,
  KeyRound,
  LockKeyhole,
  Mail,
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
  requestPasswordReset,
  resetPassword,
  verifyPasswordResetOtp,
} from "@/lib/auth/password-recovery";

import styles from "./forgot-password.module.css";

type RecoveryStep =
  | "EMAIL"
  | "OTP"
  | "PASSWORD"
  | "SUCCESS";

function errorMessage(
  error: unknown,
  fallback: string,
): string {
  if (
    error instanceof ApiClientError
  ) {
    if (error.status === 429) {
      return "Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.";
    }

    return error.message;
  }

  return fallback;
}

export default function ForgotPasswordPage() {
  const router = useRouter();

  const [step, setStep] =
    useState<RecoveryStep>(
      "EMAIL",
    );

  const [email, setEmail] =
    useState("");

  const [requestId, setRequestId] =
    useState<string | null>(
      null,
    );

  const [code, setCode] =
    useState("");

  const [resetProof, setResetProof] =
    useState<string | null>(
      null,
    );

  const [password, setPassword] =
    useState("");

  const [
    passwordConfirmation,
    setPasswordConfirmation,
  ] = useState("");

  const [error, setError] =
    useState<string | null>(
      null,
    );

  const [loading, setLoading] =
    useState(false);

  async function handleRequest(
    event: FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    setError(null);
    setLoading(true);

    try {
      const response =
        await requestPasswordReset({
          identifier: email,
        });

      setRequestId(
        response.data.request_id,
      );

      setStep("OTP");
    } catch (caught) {
      setError(
        errorMessage(
          caught,
          "Permintaan pemulihan akun tidak dapat diproses.",
        ),
      );
    } finally {
      setLoading(false);
    }
  }

  async function handleVerify(
    event: FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    if (! requestId) {
      setStep("EMAIL");

      return;
    }

    setError(null);
    setLoading(true);

    try {
      const response =
        await verifyPasswordResetOtp({
          challenge_id:
            requestId,

          code,
        });

      setResetProof(
        response.data.reset_proof,
      );

      setStep("PASSWORD");
    } catch (caught) {
      setError(
        errorMessage(
          caught,
          "Kode verifikasi tidak valid atau sudah tidak berlaku.",
        ),
      );
    } finally {
      setLoading(false);
    }
  }

  async function handleReset(
    event: FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    if (! resetProof) {
      setStep("EMAIL");

      return;
    }

    setError(null);

    if (
      password !==
      passwordConfirmation
    ) {
      setError(
        "Konfirmasi kata sandi tidak sama.",
      );

      return;
    }

    setLoading(true);

    try {
      await resetPassword({
        reset_proof:
          resetProof,

        password,

        password_confirmation:
          passwordConfirmation,
      });

      /*
       * Sensitive recovery state
       * remains memory-only and is
       * cleared after success.
       */
      setRequestId(null);
      setResetProof(null);
      setCode("");
      setPassword("");
      setPasswordConfirmation("");

      setStep("SUCCESS");
    } catch (caught) {
      setError(
        errorMessage(
          caught,
          "Kata sandi tidak dapat diperbarui. Silakan ulangi proses pemulihan.",
        ),
      );
    } finally {
      setLoading(false);
    }
  }

  function renderProgress() {
    const index =
      step === "EMAIL"
        ? 1
        : step === "OTP"
          ? 2
          : 3;

    return (
      <div
        className={styles.steps}
        aria-hidden="true"
      >
        {[1, 2, 3].map(
          (number) => (
            <span
              key={number}
              className={
                number <= index
                  ? `${styles.step} ${styles.stepActive}`
                  : styles.step
              }
            />
          ),
        )}
      </div>
    );
  }

  return (
    <AuthShell>
      <Card className={styles.card}>
        <div className={styles.cardBrand}>
          <Brand />
        </div>
        {step !== "SUCCESS" ? (
          <>
            <header
              className={
                styles.header
              }
            >
              <Link
                href="/login"
                className={
                  styles.back
                }
              >
                <ArrowLeft
                  size={16}
                />

                Kembali ke Masuk
              </Link>

              <h1
                className={
                  styles.title
                }
              >
                Pulihkan akun
              </h1>

              <p
                className={
                  styles.description
                }
              >
                {step === "EMAIL"
                  ? "Masukkan email akun SIGNOVA Anda."
                  : step === "OTP"
                    ? "Masukkan kode 6 digit yang dikirim melalui WhatsApp."
                    : "Buat kata sandi baru untuk akun Anda."}
              </p>
            </header>

            {renderProgress()}

            {error ? (
              <div
                className={
                  styles.error
                }
                role="alert"
              >
                {error}
              </div>
            ) : null}
          </>
        ) : null}

        {step === "EMAIL" ? (
          <form
            className={
              styles.form
            }
            onSubmit={
              handleRequest
            }
          >
            <Input
              label="Email"
              type="email"
              autoComplete="email"
              placeholder="nama@bisnis.com"
              value={email}
              onChange={(
                event,
              ) =>
                setEmail(
                  event.target
                    .value,
                )
              }
              leadingIcon={
                <Mail size={18} />
              }
              required
            />

            <div
              className={
                styles.notice
              }
            >
              Jika email tersebut
              terdaftar dan memiliki
              nomor WhatsApp aktif,
              kode verifikasi akan
              dikirim.
            </div>

            <Button
              type="submit"
              fullWidth
              disabled={loading}
            >
              {loading
                ? "Memproses..."
                : "Kirim kode verifikasi"}
            </Button>
          </form>
        ) : null}

        {step === "OTP" ? (
          <form
            className={
              styles.form
            }
            onSubmit={
              handleVerify
            }
          >
            <Input
              label="Kode Verifikasi"
              type="text"
              inputMode="numeric"
              autoComplete="one-time-code"
              placeholder="000000"
              value={code}
              onChange={(
                event,
              ) => {
                const next =
                  event.target.value
                    .replace(
                      /\D/g,
                      "",
                    )
                    .slice(0, 6);

                setCode(next);
              }}
              className={
                styles.otp
              }
              leadingIcon={
                <KeyRound
                  size={18}
                />
              }
              minLength={6}
              maxLength={6}
              required
            />

            <div
              className={
                styles.notice
              }
            >
              Kode berlaku sekitar
              5 menit. Jangan
              berikan kode tersebut
              kepada siapa pun.
            </div>

            <Button
              type="submit"
              fullWidth
              disabled={
                loading
                || code.length !== 6
              }
            >
              {loading
                ? "Memverifikasi..."
                : "Verifikasi kode"}
            </Button>

            <Button
              type="button"
              variant="ghost"
              fullWidth
              disabled={loading}
              onClick={() => {
                setError(null);
                setCode("");
                setRequestId(
                  null,
                );
                setStep("EMAIL");
              }}
            >
              Gunakan email lain
            </Button>
          </form>
        ) : null}

        {step === "PASSWORD" ? (
          <form
            className={
              styles.form
            }
            onSubmit={
              handleReset
            }
          >
            <Input
              label="Kata Sandi Baru"
              type="password"
              autoComplete="new-password"
              placeholder="Minimal 8 karakter"
              value={password}
              onChange={(
                event,
              ) =>
                setPassword(
                  event.target
                    .value,
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
              placeholder="Ulangi kata sandi baru"
              value={
                passwordConfirmation
              }
              onChange={(
                event,
              ) =>
                setPasswordConfirmation(
                  event.target
                    .value,
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

            <Button
              type="submit"
              fullWidth
              disabled={loading}
            >
              {loading
                ? "Menyimpan..."
                : "Simpan kata sandi baru"}
            </Button>
          </form>
        ) : null}

        {step === "SUCCESS" ? (
          <div
            className={
              styles.success
            }
          >
            <div
              className={
                styles.successIcon
              }
            >
              <CheckCircle2
                size={30}
              />
            </div>

            <h2>
              Kata sandi diperbarui
            </h2>

            <p>
              Silakan masuk kembali
              menggunakan kata sandi
              baru Anda.
            </p>

            <Button
              type="button"
              fullWidth
              onClick={() =>
                router.replace(
                  "/login",
                )
              }
            >
              Kembali ke Masuk
            </Button>
          </div>
        ) : null}

        {step !== "SUCCESS" ? (
          <p
            className={
              styles.footer
            }
          >
            Untuk keamanan, proses
            pemulihan akan dimulai
            ulang jika halaman
            dimuat ulang.
          </p>
        ) : null}
      </Card>
    </AuthShell>
  );
}
