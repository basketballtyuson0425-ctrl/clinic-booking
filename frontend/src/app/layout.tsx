import type { Metadata } from "next";
import { clinic } from "@/lib/clinic";
import "./globals.css";

export const metadata: Metadata = {
  // 各ページの title が「ネット予約 | さくら接骨院」のようになる
  title: {
    template: `%s | ${clinic.name}`,
    default: clinic.name,
  },
};

/**
 * すべてのページに共通の枠。ヘッダーや幅は、患者側（(patient)/layout.tsx）と管理画面（admin/layout.tsx）でそれぞれ決める
 */
export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="ja" className="h-full antialiased">
      <body className="flex min-h-full flex-col">{children}</body>
    </html>
  );
}
