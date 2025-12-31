"use client";

import {
  PieChart,
  Pie,
  Tooltip,
  Cell,
  Legend,
  ResponsiveContainer,
} from "recharts";
import type { AdminOrder } from "@/app/(user)/actions/adminApi";

const COLORS = ["#8884d8", "#82ca9d", "#ffc658", "#ff7f50"];

type Props = {
  orders: AdminOrder[];
};

export default function PaymentMethodsChart({ orders }: Props) {
  const methodMap: Record<string, number> = {};

  orders.forEach((order) => {
    const rawMethod = order.payment?.paymentMethod ?? "UNKNOWN";
    const method = String(rawMethod).toUpperCase();
    methodMap[method] = (methodMap[method] || 0) + 1;
  });

  const chartData = Object.entries(methodMap).map(([method, count]) => ({
    name: method,
    value: count,
  }));

  if (chartData.length === 0) {
    return (
      <div>
        <h2 className="text-xl font-semibold mb-2">Payment Methods</h2>
        <p className="text-sm text-gray-500">No payment data.</p>
      </div>
    );
  }

  return (
    <div>
      <h2 className="text-xl font-semibold mb-2">Payment Methods</h2>
      <div className="w-full h-[300px]">
        <ResponsiveContainer width="100%" height="100%">
          <PieChart>
            <Pie
              data={chartData}
              dataKey="value"
              nameKey="name"
              cx="50%"
              cy="50%"
              outerRadius={100}
            >
              {chartData.map((_, index) => (
                <Cell key={index} fill={COLORS[index % COLORS.length]} />
              ))}
            </Pie>
            <Tooltip />
            <Legend />
          </PieChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
