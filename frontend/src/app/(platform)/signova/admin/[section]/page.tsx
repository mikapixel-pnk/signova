import {
  notFound,
} from "next/navigation";

import {
  PlatformSectionPlaceholder,
} from "@/components/platform-admin/platform-section-placeholder";

import {
  platformNavigationItemForSection,
} from "@/lib/platform-admin/navigation";

type PlatformAdminSectionPageProps = {
  params: Promise<{
    section: string;
  }>;
};

export default async function PlatformAdminSectionPage({
  params,
}: PlatformAdminSectionPageProps) {
  const {
    section,
  } =
    await params;

  const item =
    platformNavigationItemForSection(
      section,
    );

  if (!item) {
    notFound();
  }

  return (
    <PlatformSectionPlaceholder
      item={item}
    />
  );
}
