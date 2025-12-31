"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/utils";
import { X } from "lucide-react";

const navStatistic = [{ label: "Statistics", href: "/admin" }];

const navItems = [
  { label: "Books", href: "/admin/manage-books" },
  { label: "Users", href: "/admin/manage-users" },
  { label: "Customers", href: "/admin/manage-customers" },
  { label: "Employees", href: "/admin/manage-employees" },
  { label: "Orders", href: "/admin/manage-orders" },
];

interface Props {
  open: boolean;
  onClose: () => void;
}

export default function NavAdmin({ open, onClose }: Props) {
  const pathname = usePathname();

  const isActive = (href: string) =>
    pathname === href || pathname.startsWith(href + "/");

  return (
    <>
      {/* Overlay mobile */}
      <div
        className={cn(
          "fixed inset-0 z-40 bg-black/30 transition-opacity md:hidden",
          open ? "opacity-100" : "pointer-events-none opacity-0"
        )}
        onClick={onClose}
        aria-hidden="true"
      />

      <aside
        className={cn(
          `
          z-50 h-dvh w-72 md:w-64
          bg-gradient-to-b from-purple-100 via-rose-100 to-white
          border-r border-gray-200
          px-4 py-6
          flex flex-col
          transition-transform duration-300

          fixed left-0 top-0 md:sticky md:top-0
          md:translate-x-0
        `,
          open ? "translate-x-0" : "-translate-x-full md:translate-x-0"
        )}
      >
        {/* Brand + close (mobile) */}
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-2xl font-bold text-purple-700">WKangaroo</h2>
          <button
            type="button"
            onClick={onClose}
            className="md:hidden rounded-lg border border-gray-200 bg-white p-2 shadow-sm"
            aria-label="Close sidebar"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Analytics */}
        <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
          ANALYTICS
        </h3>
        <nav className="flex flex-col space-y-1 mb-5">
          {navStatistic.map((item) => (
            <Link
              prefetch={false}
              key={item.href}
              href={item.href}
              onClick={onClose}
              className={cn(
                "px-4 py-2.5 rounded-lg text-sm font-medium transition-all",
                isActive(item.href)
                  ? "bg-purple-200 text-purple-900 shadow-sm"
                  : "text-gray-700 hover:bg-purple-50"
              )}
            >
              {item.label}
            </Link>
          ))}
        </nav>

        {/* Sales */}
        <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
          SALES
        </h3>
        <nav className="flex flex-col space-y-1">
          {navItems.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              onClick={onClose}
              className={cn(
                "px-4 py-2.5 rounded-lg text-sm font-medium transition-all",
                isActive(item.href)
                  ? "bg-purple-200 text-purple-900 shadow-sm"
                  : "text-gray-700 hover:bg-purple-50"
              )}
            >
              {item.label}
            </Link>
          ))}
        </nav>
      </aside>
    </>
  );
}
