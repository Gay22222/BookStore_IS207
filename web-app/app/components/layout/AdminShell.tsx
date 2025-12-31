"use client";

import { PropsWithChildren, useState } from "react";
import { CurrentUser } from "@/app/(user)/actions/getCurrentUser";
import NavAdmin from "./NavAdmin";
import AdminHeader from "./AdminHeader";

interface Props extends PropsWithChildren {
  user: CurrentUser | null;
}

export default function AdminShell({ user, children }: Props) {
  const [sidebarOpen, setSidebarOpen] = useState(false);

  return (
    <div className="min-h-screen bg-gradient-to-r from-purple-100 via-rose-100 to-white md:flex">
      <NavAdmin open={sidebarOpen} onClose={() => setSidebarOpen(false)} />

      {/* Content bên phải */}
      <div className="flex-1 min-w-0 flex flex-col">
        <AdminHeader
          currentUser={user}
          onOpenSidebar={() => setSidebarOpen(true)}
        />

        <main className="flex-1 p-4 sm:p-6 lg:p-8">{children}</main>
      </div>
    </div>
  );
}
