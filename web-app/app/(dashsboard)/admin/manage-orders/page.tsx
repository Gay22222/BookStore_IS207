"use server";

import { getAllOrders } from "@/app/(user)/actions/adminApi";
import OrderClientPage from "./OrderClientPage";

export default async function ManageOrdersPage() {
  const res = await getAllOrders();
  const orders = "error" in (res as any) ? [] : (res as any);

  return (
    <section className="w-full max-w-7xl mx-auto space-y-4">
      <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">Orders</h1>
      <OrderClientPage initialOrders={orders} />
    </section>
  );
}
