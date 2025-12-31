"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/utils";

const navItems = [
  { label: "Profile Information", href: "/account/profile" },
  { label: "Address Information", href: "/account/addresses" },
  { label: "Change Password", href: "/account/change-password" },
];

export default function AccountSidebar({
  orientation = "vertical",
  className,
  onNavigate,
}: {
  orientation?: "vertical" | "horizontal";
  className?: string;
  onNavigate?: () => void;
}) {
  const pathname = usePathname();
  const isHorizontal = orientation === "horizontal";

  return (
    <div className={cn("w-full", className)}>
      {!isHorizontal && (
        <h2 className="text-xl font-semibold text-rose-600 mb-4">
          Account Center
        </h2>
      )}
      <nav
        className={cn(
          "flex",
          isHorizontal
            ? "flex-row gap-2 overflow-x-auto no-scrollbar"
            : "flex-col space-y-2"
        )}
        aria-label="Account navigation"
      >
        {navItems.map((item) => {
          const active = pathname === item.href;
          return (
            <Link
              key={item.href}
              href={item.href}
              onClick={onNavigate}
              aria-current={active ? "page" : undefined}
              className={cn(
                isHorizontal
                  ? cn(
                      "px-3 py-2 text-sm rounded-full border whitespace-nowrap transition-colors",
                      active
                        ? "bg-rose-600 text-white border-rose-600"
                        : "text-gray-700 border-gray-200 hover:bg-rose-100 hover:text-rose-700"
                    )
                  : cn(
                      "px-4 py-2 rounded-lg text-base font-medium transition-colors",
                      active
                        ? "bg-rose-600 text-white"
                        : "text-gray-700 hover:bg-rose-100 hover:text-rose-700"
                    )
              )}
            >
              {item.label}
            </Link>
          );
        })}
      </nav>
    </div>
  );
}
