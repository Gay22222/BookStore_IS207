import type { Metadata } from "next";
import "./globals.css";
import { Toaster } from "react-hot-toast";
import NextTopLoader from "nextjs-toploader";
import FaviconProgress from "./components/layout/Favicon-progress";
import ChatbotWidget from "./components/chatbot/ChatbotWidget";

export const metadata: Metadata = {
  title: "Bookstore",
  description: "Created by RGBunny",
};

export default async function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" suppressHydrationWarning>
      <body suppressHydrationWarning>
        <Toaster></Toaster>
        <NextTopLoader showSpinner={false}></NextTopLoader>
        <FaviconProgress></FaviconProgress>
        {children}
        <ChatbotWidget />
      </body>
    </html>
  );
}
