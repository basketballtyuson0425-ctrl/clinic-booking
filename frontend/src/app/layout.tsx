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

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="ja" className="h-full antialiased">
      <body className="flex min-h-full flex-col">
        <header className="bg-brand text-white">
          <div className="mx-auto max-w-md px-4 py-3 text-lg font-bold">{clinic.name}</div>
        </header>
        <main className="mx-auto w-full max-w-md flex-1 px-4 py-6">{children}</main>
      </body>
    </html>
  );
}
