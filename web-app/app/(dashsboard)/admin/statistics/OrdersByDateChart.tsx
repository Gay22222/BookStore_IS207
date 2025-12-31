"use client";

import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from "recharts";
import type { AdminOrder } from "@/app/(user)/actions/adminApi";

type Props = {
  orders: AdminOrder[];
};

export default function OrdersByDateChart({ orders }: Props) {
  const countMap: Record<string, number> = {};

  orders.forEach((order) => {
    const rawDate = order.orderDate || order.createdAt || "";
    if (!rawDate) return;
    const dateKey = String(rawDate).slice(0, 10);
    countMap[dateKey] = (countMap[dateKey] || 0) + 1;
  });

  const chartData = Object.entries(countMap)
    .map(([date, count]) => ({ date, count }))
    .sort((a, b) => a.date.localeCompare(b.date));

  if (chartData.length === 0) {
    return (
      <div>
        <h2 className="text-xl font-semibold mb-2">Orders by Date</h2>
        <p className="text-sm text-gray-500">No orders to display.</p>
      </div>
    );
  }

  return (
    <div>
      <h2 className="text-xl font-semibold mb-2">Orders by Date</h2>
      <div className="w-full h-[300px]">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={chartData}>
            <CartesianGrid strokeDasharray="3 3" />
            <XAxis dataKey="date" />
            <YAxis />
            <Tooltip />
            <Bar dataKey="count" />
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
