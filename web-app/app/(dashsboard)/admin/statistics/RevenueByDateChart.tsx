"use client";

import dynamic from "next/dynamic";
import {
  Line,
  XAxis,
  YAxis,
  Tooltip,
  CartesianGrid,
  ResponsiveContainer,
} from "recharts";
import type { AdminOrder } from "@/app/(user)/actions/adminApi";

const LineChart = dynamic(
  () => import("recharts").then((mod) => mod.LineChart),
  { ssr: false }
);

function toNumber(value: unknown) {
  if (typeof value === "number") return value;
  if (typeof value === "string") {
    const n = Number(value.replace(/,/g, ""));
    return Number.isFinite(n) ? n : 0;
  }
  return 0;
}

export default function RevenueByDateChart({
  orders,
}: {
  orders: AdminOrder[];
}) {
  const revenueMap: Record<string, number> = {};

  orders.forEach((order) => {
    const raw = order.orderDate || order.createdAt;
    if (!raw) return;

    const date = new Date(raw);
    if (Number.isNaN(date.getTime())) return;

    const dateKey = date.toISOString().slice(0, 10);
    const amount = toNumber((order as any).totalAmount);
    revenueMap[dateKey] = (revenueMap[dateKey] || 0) + amount;
  });

  const chartData = Object.entries(revenueMap)
    .map(([date, total]) => ({ date, total }))
    .sort((a, b) => a.date.localeCompare(b.date));

  if (chartData.length === 0) {
    return (
      <div>
        <h2 className="text-xl font-semibold mb-2">Revenue by Date</h2>
        <p className="text-sm text-gray-500">No revenue data.</p>
      </div>
    );
  }

  return (
    <div>
      <h2 className="text-xl font-semibold mb-2">Revenue by Date</h2>
      <div className="w-full h-[300px]">
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={chartData}>
            <CartesianGrid strokeDasharray="3 3" />
            <XAxis dataKey="date" />
            <YAxis />
            <Tooltip
              formatter={(value) =>
                new Intl.NumberFormat("vi-VN").format(toNumber(value))
              }
              labelFormatter={(label) => String(label)}
            />
            <Line type="monotone" dataKey="total" />
          </LineChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
