import type { ReactNode } from "react";
import { clinic } from "@/lib/clinic";

/**
 * 患者側のページの枠（スマートフォン向けの幅）。
 * (patient) のように ( ) で囲んだフォルダは URL に含まれないので、URL は /reserve などのまま
 */
export default function PatientLayout({ children }: { children: ReactNode }) {
  return (
    <>
      <header className="bg-brand text-white">
        <div className="mx-auto max-w-md px-4 py-3 text-lg font-bold">{clinic.name}</div>
      </header>
      <main className="mx-auto w-full max-w-md flex-1 px-4 py-6">{children}</main>
    </>
  );
}
