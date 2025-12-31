"use server";

import { getAllOrders, getDashboardStats } from "@/app/(user)/actions/adminApi";

import StatsCards from "./statistics/StatsCards";
import RevenueByDateChart from "./statistics/RevenueByDateChart";
import OrdersByDateChart from "./statistics/OrdersByDateChart";
import PaymentMethodsChart from "./statistics/PaymentMethodsChart";
import TopProductsChart from "./statistics/TopProductsChart";
import RecentOrdersTable from "./statistics/RecentOrdersTable";

export default async function StatisticsPage() {
  const [statsRes, ordersRes] = await Promise.all([
    getDashboardStats(),
    getAllOrders(),
  ]);

  const stats = "error" in (statsRes as any) ? null : (statsRes as any);
  const orders = "error" in (ordersRes as any) ? [] : (ordersRes as any);

  return (
    <div className="p-6 space-y-6">
      <StatsCards stats={stats} />

      <div className="grid grid-cols-12 gap-6">
        <div className="col-span-12 lg:col-span-8 bg-white p-4 rounded-xl shadow">
          <RevenueByDateChart orders={orders} />
        </div>

        <div className="col-span-12 lg:col-span-4 bg-white p-4 rounded-xl shadow">
          <PaymentMethodsChart orders={orders} />
        </div>

        <div className="col-span-12 lg:col-span-6 bg-white p-4 rounded-xl shadow">
          <OrdersByDateChart orders={orders} />
        </div>

        <div className="col-span-12 lg:col-span-6 bg-white p-4 rounded-xl shadow">
          <TopProductsChart orders={orders} />
        </div>

        <div className="col-span-12">
          <RecentOrdersTable orders={orders} limit={8} />
        </div>
      </div>
    </div>
  );
}
