"use client";

import Link from "next/link";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import type { AdminOrder } from "@/app/(user)/actions/adminApi";

const PAGE_SIZE = 10;

function fmtMoney(v: number, currency = "USD") {
  try {
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency,
    }).format(v);
  } catch {
    return String(v);
  }
}

function fmtDate(v?: string | null) {
  if (!v) return "-";
  const d = new Date(v);
  if (Number.isNaN(d.getTime())) return v;
  return d.toLocaleString();
}

export default function OrderTable({ orders }: { orders: AdminOrder[] }) {
  const [page, setPage] = useState(1);

  useEffect(() => setPage(1), [orders]);

  const totalPages = Math.max(1, Math.ceil(orders.length / PAGE_SIZE));
  useEffect(() => {
    if (page > totalPages) setPage(totalPages);
  }, [page, totalPages]);

  const paged = useMemo(() => {
    const start = (page - 1) * PAGE_SIZE;
    return orders.slice(start, start + PAGE_SIZE);
  }, [orders, page]);

  const canPrev = page > 1;
  const canNext = page < totalPages;

  const from = orders.length === 0 ? 0 : (page - 1) * PAGE_SIZE + 1;
  const to = Math.min(page * PAGE_SIZE, orders.length);

  return (
    <div className="bg-white shadow rounded-lg overflow-hidden">
      {orders.length === 0 ? (
        <div className="p-6 text-gray-600 italic">No orders.</div>
      ) : (
        <>
          <div className="overflow-x-auto">
            <table className="min-w-[1100px] w-full divide-y divide-gray-200">
              <thead className="bg-purple-100">
                <tr>
                  {[
                    "#",
                    "Order Code",
                    "Email",
                    "Total",
                    "Order Status",
                    "Payment",
                    "Order Date",
                    "Actions",
                  ].map((h) => (
                    <th
                      key={h}
                      className="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider"
                    >
                      {h}
                    </th>
                  ))}
                </tr>
              </thead>

              <tbody className="divide-y divide-gray-200">
                {paged.map((o, idx) => {
                  const globalIndex = (page - 1) * PAGE_SIZE + idx + 1;
                  return (
                    <tr
                      key={o.orderId}
                      className="odd:bg-white even:bg-gray-50"
                    >
                      <td className="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                        {globalIndex}
                      </td>
                      <td className="px-4 py-3 text-sm font-medium text-gray-900">
                        {o.orderCode}
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-700">
                        {o.email}
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-700">
                        {fmtMoney(Number(o.totalAmount) || 0)}
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-700">
                        {o.orderStatus}
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-700">
                        {o.paymentStatus}
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-700">
                        {fmtDate(o.createdAt ?? o.orderDate)}
                      </td>
                      <td className="px-4 py-3 text-sm">
                        <Link
                          href={`/admin/manage-orders/${o.orderId}`}
                          className="text-purple-700 hover:text-purple-900 font-medium"
                        >
                          View / Update
                        </Link>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 py-3 border-t border-gray-200">
            <div className="text-sm text-gray-600">
              Showing <span className="font-medium">{from}</span>–{" "}
              <span className="font-medium">{to}</span> of{" "}
              <span className="font-medium">{orders.length}</span>
            </div>

            <div className="flex items-center gap-2">
              <button
                onClick={() => canPrev && setPage((p) => p - 1)}
                disabled={!canPrev}
                className="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40"
              >
                <ChevronLeft className="w-4 h-4" />
                Prev
              </button>

              <button
                onClick={() => canNext && setPage((p) => p + 1)}
                disabled={!canNext}
                className="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40"
              >
                Next
                <ChevronRight className="w-4 h-4" />
              </button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
