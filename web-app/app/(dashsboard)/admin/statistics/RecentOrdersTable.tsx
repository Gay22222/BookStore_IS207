import Link from "next/link";
import type { AdminOrder } from "@/app/(user)/actions/adminApi";

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

function Badge({ text }: { text: string }) {
  const t = (text || "").toUpperCase();

  const cls =
    t === "PAID" || t === "SUCCESS" || t === "COMPLETED"
      ? "bg-emerald-50 text-emerald-700 border-emerald-200"
      : t === "FAILED" || t === "CANCELLED"
      ? "bg-rose-50 text-rose-700 border-rose-200"
      : "bg-amber-50 text-amber-800 border-amber-200";

  return (
    <span
      className={`inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold ${cls}`}
    >
      {text}
    </span>
  );
}

export default function RecentOrdersTable({
  orders,
  limit = 8,
}: {
  orders: AdminOrder[];
  limit?: number;
}) {
  const recent = (orders ?? []).slice(0, limit);

  return (
    <div className="bg-white p-4 rounded-xl shadow">
      <div className="flex items-center justify-between mb-3">
        <h2 className="text-lg font-semibold text-gray-900">Recent Orders</h2>

        <Link
          href="/admin/manage-orders"
          className="text-sm font-medium text-purple-700 hover:text-purple-900"
        >
          View all
        </Link>
      </div>

      <div className="overflow-x-auto">
        <table className="min-w-[1050px] w-full divide-y divide-gray-200">
          <thead className="bg-purple-100">
            <tr>
              {[
                "Order Code",
                "Email",
                "Total",
                "Order Status",
                "Payment Status",
                "Created At",
                "Action",
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
            {recent.length === 0 ? (
              <tr>
                <td className="px-4 py-6 text-sm text-gray-600" colSpan={7}>
                  No orders found.
                </td>
              </tr>
            ) : (
              recent.map((o) => (
                <tr key={o.orderId} className="odd:bg-white even:bg-gray-50">
                  <td className="px-4 py-3 text-sm font-medium text-gray-900">
                    {o.orderCode}
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-700">{o.email}</td>
                  <td className="px-4 py-3 text-sm text-gray-700">
                    {fmtMoney(Number(o.totalAmount) || 0)}
                  </td>
                  <td className="px-4 py-3 text-sm">
                    <Badge text={o.orderStatus} />
                  </td>
                  <td className="px-4 py-3 text-sm">
                    <Badge text={o.paymentStatus} />
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
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
