"use client";

import { CurrentUser } from "@/app/(user)/actions/getCurrentUser";
import AccountDropdown from "./AccountDropdown";
import { CalendarDays, Menu } from "lucide-react";

interface Props {
  currentUser: CurrentUser | null;
  onOpenSidebar?: () => void; // thêm để mở Nav mobile
}

export default function AdminHeader({ currentUser, onOpenSidebar }: Props) {
  const today = new Date();
  const formattedDate = today.toLocaleDateString("en-US", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });

  return (
    <header className="sticky top-0 z-50 w-full flex items-center justify-between px-4 sm:px-6 lg:px-8 py-4 bg-gradient-to-r from-purple-100 via-rose-100 to-white border-b border-gray-200 shadow-md rounded-b-xl">
      {/* Left: menu mobile + spacer */}
      <div className="flex-1 flex items-center">
        <button
          type="button"
          onClick={onOpenSidebar}
          className="md:hidden inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white p-2 shadow-sm hover:bg-gray-50 transition"
          aria-label="Open sidebar"
        >
          <Menu className="w-5 h-5 text-gray-700" />
        </button>
      </div>

      {/* Centered Welcome Text (giữ như cũ) */}
      <div className="flex-1 flex justify-center">
        <h1 className="text-lg sm:text-xl font-semibold text-gray-800 text-center">
          Welcome{" "}
          <span className="text-purple-700 font-bold">
            {currentUser?.userName}
          </span>{" "}
          to WKangaroo Dashboard
        </h1>
      </div>

      {/* Right Section - Date and Account (giữ như cũ) */}
      <div className="flex-1 flex items-center justify-end gap-4 sm:gap-6">
        <div className="hidden md:flex items-center gap-2 bg-white rounded-lg px-4 py-2 shadow-sm border border-gray-200 text-gray-700">
          <CalendarDays className="w-4 h-4 text-purple-600" />
          <span className="text-sm font-medium">{formattedDate}</span>
        </div>

        <div className="ml-2 sm:ml-4">
          <AccountDropdown user={currentUser} />
        </div>
      </div>
    </header>
  );
}
