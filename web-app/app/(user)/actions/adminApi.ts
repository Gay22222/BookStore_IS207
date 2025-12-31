"use server";

import { fetchWrapper } from "@/lib/fetchWrapper";

export type ApiError = { error: { status: string; message: string } };

// ===== DASHBOARD =====
export type DashboardStats = {
  usersCount: number;
  customersCount: number;
  employeesCount: number;
  booksCount: number;
  ordersCount: number;
  revenueAll: number;
  revenuePaid: number;
};

export async function getDashboardStats(): Promise<DashboardStats | ApiError> {
  return await fetchWrapper.get("/manage/dashboard-stats");
}

// ===== USERS =====
export type AdminUser = {
  id: number;
  userName: string;
  email: string;
  roles: string[];
};

export type CreateUserPayload = {
  userName: string;
  email: string;
  password: string;
  roles?: string[];
};

export type UpdateUserPayload = {
  updatedUserId: number;
  userName?: string;
  email?: string;
  roles?: string[];
};

export async function getAllUsers(): Promise<AdminUser[] | ApiError> {
  return await fetchWrapper.get("/manage/get-all-users");
}
export async function searchUsers(
  term: string
): Promise<AdminUser[] | ApiError> {
  const q = encodeURIComponent(term ?? "");
  return await fetchWrapper.get(`/manage/search/users?searchTerm=${q}`);
}

export async function getAllCustomers(): Promise<AdminUser[] | ApiError> {
  return await fetchWrapper.get("/manage/get-all-customers");
}
export async function searchCustomers(
  term: string
): Promise<AdminUser[] | ApiError> {
  const q = encodeURIComponent(term ?? "");
  return await fetchWrapper.get(`/manage/search/customers?searchTerm=${q}`);
}

export async function getAllEmployees(): Promise<AdminUser[] | ApiError> {
  return await fetchWrapper.get("/manage/get-all-employees");
}
export async function searchEmployees(
  term: string
): Promise<AdminUser[] | ApiError> {
  const q = encodeURIComponent(term ?? "");
  return await fetchWrapper.get(`/manage/search/employees?searchTerm=${q}`);
}

export async function createUser(
  payload: CreateUserPayload
): Promise<AdminUser | ApiError> {
  return await fetchWrapper.post("/manage/user", payload);
}
export async function updateUser(
  payload: UpdateUserPayload
): Promise<AdminUser | ApiError> {
  return await fetchWrapper.patch("/manage/user", payload);
}
export async function deleteUser(userId: number): Promise<unknown | ApiError> {
  return await fetchWrapper.del(`/manage/user/${userId}`);
}

// ===== BOOKS (ADMIN MANAGE) =====
export type AdminBook = {
  id?: number;
  bookId?: number;
  title: string;
  author: string;
  description: string;
  category: string;
  price: number;
  publisher: string;
  publicationDate: string;
  language: string;
  readingAge: number;
  pages: number;
  dimension?: string | null;
  quantity: number;
  discount: number;
  imageUrl?: string | null;
};

export type ResourceResponse<T> = { data: T };
export type ResourceCollectionResponse<T> = {
  data: T[];
  meta?: unknown;
  links?: unknown;
};

export type CreateBookPayload = {
  title: string;
  author: string;
  description: string;
  category: string;
  price: number;
  publisher: string;
  publicationDate: string;
  language: string;
  readingAge: number;
  pages: number;
  dimension?: string | null;
  quantity?: number;
  discount?: number;
  imageUrl?: string | null;
};

export type UpdateBookPayload = Partial<CreateBookPayload>;

function normalizeBook(raw: any): AdminBook {
  const id = raw?.id ?? raw?.bookId ?? raw?.book_id ?? undefined;
  const bookId = raw?.bookId ?? raw?.id ?? raw?.book_id ?? undefined;

  return {
    ...raw,
    id,
    bookId,
    publicationDate: raw?.publicationDate ?? raw?.publication_date,
    readingAge: raw?.readingAge ?? raw?.reading_age,
    imageUrl: raw?.imageUrl ?? raw?.image_url,
  };
}

function normalizeBookListResponse(
  res: any
): ResourceCollectionResponse<AdminBook> {
  if (res && typeof res === "object" && Array.isArray(res.data)) {
    return { ...res, data: res.data.map(normalizeBook) };
  }
  return { data: [] };
}

export async function getAllBooks(): Promise<
  ResourceCollectionResponse<AdminBook> | ApiError
> {
  const res = await fetchWrapper.get("/manage/books");
  if (res && typeof res === "object" && "error" in res) return res as ApiError;
  return normalizeBookListResponse(res);
}

export async function getBookById(
  bookId: number
): Promise<ResourceResponse<AdminBook> | ApiError> {
  const res = await fetchWrapper.get(`/manage/books/book?Id=${bookId}`);
  if (res && typeof res === "object" && "error" in res) return res as ApiError;

  if (res && typeof res === "object" && "data" in res) {
    return { ...(res as any), data: normalizeBook((res as any).data) };
  }
  return res as ResourceResponse<AdminBook>;
}

export async function addBook(
  payload: CreateBookPayload
): Promise<unknown | ApiError> {
  return await fetchWrapper.post("/manage/books", payload);
}

export async function updateBook(
  payload: UpdateBookPayload,
  bookId: number
): Promise<unknown | ApiError> {
  return await fetchWrapper.patch(`/manage/books?id=${bookId}`, payload);
}

export async function deleteBook(bookId: number): Promise<unknown | ApiError> {
  return await fetchWrapper.del(`/manage/books?id=${bookId}`);
}

export type SearchBooksOptions = {
  page?: number;
  size?: number;
  sort?: string;
  filters?: Record<string, string | number>;
};

export async function searchBooks(
  term: string,
  opts?: SearchBooksOptions
): Promise<ResourceCollectionResponse<AdminBook> | ApiError> {
  const params = new URLSearchParams();
  params.set("searchTerm", term ?? "");

  if (typeof opts?.page === "number") params.set("page", String(opts.page));
  if (typeof opts?.size === "number") params.set("size", String(opts.size));
  if (opts?.sort) params.set("sort", opts.sort);

  if (opts?.filters) {
    Object.entries(opts.filters).forEach(([k, v]) => {
      params.set(`filter_${k}`, String(v));
    });
  }

  const res = await fetchWrapper.get(
    `/manage/books/search?${params.toString()}`
  );
  if (res && typeof res === "object" && "error" in res) return res as ApiError;
  return normalizeBookListResponse(res);
}

export async function searchBookTitle(
  term: string
): Promise<ResourceCollectionResponse<AdminBook> | ApiError> {
  const q = encodeURIComponent(term ?? "");
  const res = await fetchWrapper.get(`/manage/books/searchTitle?term=${q}`);
  if (res && typeof res === "object" && "error" in res) return res as ApiError;
  return normalizeBookListResponse(res);
}

// ===== ORDERS =====
export type AdminOrderItem = {
  orderItemId: number;
  bookId: number;
  title: string | null;
  quantity: number;
  discount: number;
  orderedBookPrice: number;
  imageUrl?: string | null;
};

export type AdminPayment = {
  paymentId: number;
  paymentMethod: string;
  pgPaymentId?: string | null;
  pgStatus?: string | null;
  pgResponseMessage?: string | null;
  pgName?: string | null;
} | null;

export type AdminOrder = {
  orderId: number;
  orderCode: string;
  email: string;
  orderDate: string;
  createdAt?: string | null;
  paidAt?: string | null;
  totalAmount: number;
  orderStatus: string;
  paymentStatus: string;
  addressId: number;
  payment: AdminPayment;
  orderItems: AdminOrderItem[];
};

export async function getAllOrders(): Promise<AdminOrder[] | ApiError> {
  return await fetchWrapper.get("/manage/orders");
}

export async function getOrderById(
  orderId: number
): Promise<AdminOrder | ApiError> {
  return await fetchWrapper.get(`/manage/orders/${orderId}`);
}

export async function getOrderByCode(
  orderCode: string
): Promise<AdminOrder | ApiError> {
  const c = encodeURIComponent(orderCode);
  return await fetchWrapper.get(`/manage/orders/by-code/${c}`);
}

export async function updateOrderStatus(
  orderId: number,
  orderStatus: string
): Promise<AdminOrder | ApiError> {
  return await fetchWrapper.patch(`/manage/orders/${orderId}/status`, {
    orderStatus,
  });
}

export async function updatePaymentStatus(
  orderId: number,
  paymentStatus: string
): Promise<AdminOrder | ApiError> {
  return await fetchWrapper.patch(`/manage/orders/${orderId}/payment-status`, {
    paymentStatus,
  });
}

export async function searchOrders(
  term: string
): Promise<AdminOrder[] | ApiError> {
  const q = encodeURIComponent(term ?? "");
  return await fetchWrapper.get(`/manage/orders/search?searchTerm=${q}`);
}
