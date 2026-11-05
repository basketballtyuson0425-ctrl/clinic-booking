import type { ReactNode } from "react";
import { clinic } from "@/lib/clinic";
import ClinicInfo from "./_components/ClinicInfo";

/**
 * 患者側のページの枠（スマートフォン向けの幅）。
 * (patient) のように ( ) で囲んだフォルダは URL に含まれないので、URL は /reserve などのまま
 */
export default function PatientLayout({ children }: { children: ReactNode }) {
  return (
    <>
      <header className="bg-brand text-white">
        <div className="mx-auto flex max-w-md items-center justify-between gap-3 px-4 py-3">
          <span className="text-lg font-bold">{clinic.name}</span>
          {/* どの画面からでも、すぐ院に電話できるように（スマートフォンでは押すと電話がかかる） */}
          {clinic.phone && (
            <a href={`tel:${clinic.phone}`} className="text-sm whitespace-nowrap underline-offset-4 hover:underline">
              電話 {clinic.phone}
            </a>
          )}
        </div>
      </header>
      <main className="mx-auto w-full max-w-md flex-1 px-4 py-6">{children}</main>
      <ClinicInfo />
    </>
  );
}
