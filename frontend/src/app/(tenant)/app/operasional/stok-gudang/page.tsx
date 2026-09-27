import {
  redirect,
} from "next/navigation";


export default function LegacyInventoryPage() {
  redirect(
    "/app/operasional/barang-persediaan",
  );
}
