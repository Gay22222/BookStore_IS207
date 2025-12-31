"use server";
import { fetchWrapper } from "@/lib/fetchWrapper";
import { FieldValues } from "react-hook-form";
import { cookies } from "next/headers";

type ApiError = { error: { status: string; message: string } };

type ChangePasswordResponse = {
  message: string;
  jwtToken?: string; // nếu backend rotate token
};

export const updateMe = async (data: FieldValues) => {
  return await fetchWrapper.patch(`/user/me`, data);
};

export const getAllUsers = async () => {
  return await fetchWrapper.get(`/manage/get-all-users`);
};

export const getAllCustomers = async () => {
  return await fetchWrapper.get(`/manage/get-all-customers`);
};

export const getAllEmployees = async () => {
  return await fetchWrapper.get(`/manage/get-all-employees`);
};

export const searchUsers = async (searchTerm: string) => {
  return await fetchWrapper.get(
    `/manage/search/users?searchTerm=${searchTerm}`
  );
};

export const searchCustomers = async (searchTerm: string) => {
  return await fetchWrapper.get(
    `/manage/search/customers?searchTerm=${searchTerm}`
  );
};

export const searchEmployees = async (searchTerm: string) => {
  return await fetchWrapper.get(
    `/manage/search/employees?searchTerm=${searchTerm}`
  );
};

export const changePassword = async (
  oldPassword: string,
  newPassword: string,
  newPasswordConfirmation: string
): Promise<ChangePasswordResponse | ApiError> => {
  const res = await fetchWrapper.post("/auth/change-password", {
    oldPassword,
    newPassword,
    newPassword_confirmation: newPasswordConfirmation,
  });

  if (
    res &&
    typeof res === "object" &&
    !("error" in res) &&
    "jwtToken" in res &&
    typeof (res as any).jwtToken === "string" &&
    (res as any).jwtToken.length > 0
  ) {
    (await cookies()).set("jwtToken", (res as any).jwtToken, {
      httpOnly: true,
      sameSite: "lax",
      secure: process.env.NODE_ENV === "production",
      path: "/",
    });
  }

  return res as any;
};
