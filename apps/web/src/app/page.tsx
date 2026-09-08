import { redirect } from "next/navigation";

import { DEFAULT_WORKSPACE_ROUTE } from "@/lib/routing/workspace-routes";

export default function Home() {
  redirect(DEFAULT_WORKSPACE_ROUTE);
}
