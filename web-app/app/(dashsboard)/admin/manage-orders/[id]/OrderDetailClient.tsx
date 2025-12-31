"use client";

import { useMemo, useState } from "react";
import toast from "react-hot-toast";
import type { AdminOrder } from "@/app/(user)/actions/adminApi";
import {
  updateOrderStatus,
  updatePaymentStatus,
} from "@/app/(user)/actions/adminApi";
import Link from "next/link";

const ORDER_STATUS = [
  "ACCEPTED",
  "SHIPPING",
  "COMPLETED",
  "CANCELLED",
] as const;
const PAYMENT_STATUS = ["PENDING", "PAID", "FAILED"] as const;

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

export default function OrderDetailClient({ order }: { order: AdminOrder }) {
  const [orderStatus, setOrderStatus] = useState(order.orderStatus);
  const [paymentStatus, setPaymentStatus] = useState(order.paymentStatus);
  const [saving, setSaving] = useState(false);

  const itemTotal = useMemo(() => order.orderItems?.length ?? 0, [order]);

  const handleSave = async () => {
    setSaving(true);
    try {
      const res1 = await updateOrderStatus(order.orderId, orderStatus);
      if ("error" in (res1 as any)) throw (res1 as any).error;

      const res2 = await updatePaymentStatus(order.orderId, paymentStatus);
      if ("error" in (res2 as any)) throw (res2 as any).error;

      toast.success("Order updated");
    } catch (e: any) {
      toast.error((e?.status ?? "") + " " + (e?.message ?? "Update failed"));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-4">
      {/* Summary */}
      <div className="rounded-2xl border border-gray-200 bg-white/80 backdrop-blur shadow-sm p-5 space-y-3">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
          <div>
            <div className="text-sm text-gray-600">Order Code</div>
            <div className="text-xl font-bold text-gray-900">
              {order.orderCode}
            </div>
          </div>

          <Link
            href="/admin/manage-orders"
            className="inline-flex justify-center rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Back to Orders
          </Link>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <Info label="Email" value={order.email} />
          <Info
            label="Total Amount"
            value={fmtMoney(Number(order.totalAmount) || 0)}
          />
          <Info
            label="Created At"
            value={fmtDate(order.createdAt ?? order.orderDate)}
          />
          <Info label="Paid At" value={fmtDate(order.paidAt)} />
        </div>

        {/* Update controls */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-3 pt-2">
          <div className="space-y-1">
            <div className="text-sm font-semibold text-gray-800">
              Order Status
            </div>
            <select
              value={orderStatus}
              onChange={(e) => setOrderStatus(e.target.value)}
              className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"
            >
              {ORDER_STATUS.map((s) => (
                <option key={s} value={s}>
                  {s}
                </option>
              ))}
            </select>
          </div>

          <div className="space-y-1">
            <div className="text-sm font-semibold text-gray-800">
              Payment Status
            </div>
            <select
              value={paymentStatus}
              onChange={(e) => setPaymentStatus(e.target.value)}
              className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"
            >
              {PAYMENT_STATUS.map((s) => (
                <option key={s} value={s}>
                  {s}
                </option>
              ))}
            </select>
          </div>

          <div className="flex items-end">
            <button
              onClick={handleSave}
              disabled={saving}
              className="w-full rounded-lg bg-purple-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-purple-700 disabled:opacity-50"
            >
              {saving ? "Saving..." : "Save changes"}
            </button>
          </div>
        </div>

        {/* Payment info */}
        <div className="pt-2">
          <div className="text-sm font-semibold text-gray-800 mb-1">
            Payment Info
          </div>
          {order.payment ? (
            <div className="text-sm text-gray-700 space-y-1">
              <div>
                Method:{" "}
                <span className="font-medium">
                  {order.payment.paymentMethod}
                </span>
              </div>
              <div>
                PG Name:{" "}
                <span className="font-medium">
                  {order.payment.pgName ?? "-"}
                </span>
              </div>
              <div>
                PG Status:{" "}
                <span className="font-medium">
                  {order.payment.pgStatus ?? "-"}
                </span>
              </div>
              <div>
                PG Message:{" "}
                <span className="font-medium">
                  {order.payment.pgResponseMessage ?? "-"}
                </span>
              </div>
            </div>
          ) : (
            <div className="text-sm text-gray-600">No payment record.</div>
          )}
        </div>
      </div>

      {/* Items */}
      <div className="rounded-2xl border border-gray-200 bg-white/80 backdrop-blur shadow-sm overflow-hidden">
        <div className="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
          <div className="text-lg font-semibold text-gray-900">Order Items</div>
          <div className="text-sm text-gray-600">{itemTotal} items</div>
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-[900px] w-full divide-y divide-gray-200">
            <thead className="bg-purple-100">
              <tr>
                {["Book", "Qty", "Discount", "Ordered Price"].map((h) => (
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
              {order.orderItems?.map((it) => (
                <tr
                  key={it.orderItemId}
                  className="odd:bg-white even:bg-gray-50"
                >
                  <td className="px-4 py-3 text-sm font-medium text-gray-900">
                    {it.title ?? `Book #${it.bookId}`}
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-700">
                    {it.quantity}
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-700">
                    {it.discount}
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-700">
                    {fmtMoney(Number(it.orderedBookPrice) || 0)}
                  </td>
                </tr>
              ))}
              {(!order.orderItems || order.orderItems.length === 0) && (
                <tr>
                  <td colSpan={4} className="px-4 py-6 text-sm text-gray-600">
                    No items.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-xl border border-gray-200 bg-white px-4 py-3">
      <div className="text-xs font-semibold text-gray-600 uppercase tracking-wider">
        {label}
      </div>
      <div className="mt-1 text-sm font-medium text-gray-900">{value}</div>
    </div>
  );
}
