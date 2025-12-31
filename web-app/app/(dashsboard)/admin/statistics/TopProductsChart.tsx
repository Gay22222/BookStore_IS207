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

export default function TopProductsChart({ orders }: { orders: AdminOrder[] }) {
  const productMap: Record<string, number> = {};

  orders.forEach((order) => {
    order.orderItems?.forEach((item) => {
      const title = item.title || `Book #${item.bookId}`;
      productMap[title] =
        (productMap[title] || 0) + (Number(item.quantity) || 0);
    });
  });

  const chartData = Object.entries(productMap)
    .map(([title, quantity]) => ({ title, quantity }))
    .sort((a, b) => b.quantity - a.quantity)
    .slice(0, 10);

  if (chartData.length === 0) {
    return (
      <div>
        <h2 className="text-xl font-semibold mb-2">Top Products</h2>
        <p className="text-sm text-gray-500">No order items.</p>
      </div>
    );
  }

  return (
    <div>
      <h2 className="text-xl font-semibold mb-2">Top Products</h2>
      <div className="w-full h-[300px]">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={chartData}>
            <CartesianGrid strokeDasharray="3 3" />
            <XAxis dataKey="title" hide />
            <YAxis />
            <Tooltip />
            <Bar dataKey="quantity" />
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
