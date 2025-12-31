import Link from "next/link";
import type { DashboardStats } from "@/app/(user)/actions/adminApi";

function money(v: number) {
  try {
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: "USD",
    }).format(v);
  } catch {
    return String(v);
  }
}

function Card({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-2xl border border-gray-200 bg-white/80 shadow-sm p-4">
      <div className="text-sm font-medium text-gray-600">{label}</div>
      <div className="mt-2 text-2xl font-bold text-gray-900">{value}</div>
    </div>
  );
}

export default function StatsCards({
  stats,
}: {
  stats: DashboardStats | null;
}) {
  if (!stats) {
    return (
      <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        Cannot load dashboard stats (check admin permission / API).
      </div>
    );
  }

  const paidRate =
    stats.revenueAll > 0
      ? Math.round((stats.revenuePaid / stats.revenueAll) * 100)
      : 0;

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card label="Users" value={String(stats.usersCount)} />
        <Card label="Customers" value={String(stats.customersCount)} />
        <Card label="Employees" value={String(stats.employeesCount)} />
        <Card label="Books" value={String(stats.booksCount)} />

        <Card label="Orders" value={String(stats.ordersCount)} />
        <Card label="Revenue (All)" value={money(stats.revenueAll)} />
        <Card label="Revenue (Paid)" value={money(stats.revenuePaid)} />
        <Card label="Paid Rate" value={`${paidRate}%`} />
      </div>

      <div className="flex flex-col sm:flex-row gap-2">
        <Link
          href="/admin/manage-orders"
          className="inline-flex justify-center rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-700"
        >
          Manage Orders
        </Link>
        <Link
          href="/admin/manage-users"
          className="inline-flex justify-center rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
        >
          Manage Users
        </Link>
      </div>
    </div>
  );
}
