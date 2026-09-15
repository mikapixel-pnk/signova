import type {
  Metadata,
} from "next";

import {
  PublicQuotationView,
} from "@/components/quotation/public-quotation";

export const metadata:
  Metadata = {
  title:
    "Penawaran | SIGNOVA",

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

export default function PublicQuotationPage() {
  return (
    <PublicQuotationView />
  );
}
