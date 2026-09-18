import type {
  Metadata,
} from "next";

import {
  PublicInvoiceView,
} from "@/components/invoice/public-invoice";

export const metadata:
  Metadata = {
  title:
    "Tagihan | SIGNOVA",

  robots: {
    index:
      false,

    follow:
      false,

    nocache:
      true,
  },

  referrer:
    "no-referrer",
};

export default function PublicInvoicePage() {
  return (
    <PublicInvoiceView />
  );
}
