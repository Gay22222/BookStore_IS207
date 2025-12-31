"use client";

import { useMemo, useState } from "react";
import SearchBar from "@/app/components/ui/SearchBarReusable";
import OrderTable from "@/app/components/ui/OrderTable";
import toast from "react-hot-toast";

import { searchOrders, type AdminOrder } from "@/app/(user)/actions/adminApi";

export default function OrderClientPage({
  initialOrders,
}: {
  initialOrders: AdminOrder[];
}) {
  const [orders, setOrders] = useState<AdminOrder[]>(initialOrders);
  const [loading, setLoading] = useState(false);

  const count = useMemo(() => orders.length, [orders]);

  const handleSearch = async (term: string) => {
    const t = (term || "").trim();

    // nếu rỗng thì reset lại list ban đầu
    if (!t) {
      setOrders(initialOrders);
      return;
    }

    setLoading(true);
    try {
      const res = await searchOrders(t);

      if ("error" in (res as any)) {
        toast.error((res as any).error.message || "Search failed");
        setOrders([]);
      } else {
        setOrders(res as AdminOrder[]);
      }
      // eslint-disable-next-line @typescript-eslint/no-unused-vars
    } catch (e: any) {
      toast.error("Something went wrong");
      setOrders([]);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-col sm:flex-row gap-3 sm:items-center">
        <div className="flex-1 min-w-0">
          <SearchBar
            onSearch={handleSearch}
            placeholder="Search by order code..."
          />
        </div>

        <div className="text-sm text-gray-600">
          {loading ? "Loading..." : `${count} results`}
        </div>

        <button
          type="button"
          onClick={() => setOrders(initialOrders)}
          className="w-full sm:w-auto inline-flex justify-center rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
        >
          Reset
        </button>
      </div>

      <OrderTable orders={orders} />
    </div>
  );
}
