"use client"

import * as React from "react"
import {
  Bell,
  Download,
  FileText,
  Folder,
  FolderOpen,
  Home,
  LayoutDashboard,
  MoreHorizontal,
  Pencil,
  Settings,
  Share2,
  Shield,
  Trash2,
  Users,
} from "lucide-react"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogMedia,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import {
  Navigation,
  NavigationBadge,
  NavigationItem,
  NavigationLabel,
  NavigationLink,
  NavigationList,
  NavigationSection,
} from "@/components/ui/navigation"
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/components/ui/tabs"
import { toast } from "@/components/ui/toast"

export function NavigationOverlaysDemo() {
  const [deleteOpen, setDeleteOpen] = React.useState(false)

  function showToast(
    type: "success" | "info" | "warning" | "error",
    title: string,
    description: string
  ) {
    toast.add({
      type,
      title,
      description,
    })
  }

  return (
    <section className="border-t border-border py-10">
      <div className="mb-8">
        <div className="inline-flex rounded-full border border-border bg-surface px-3 py-1 text-xs font-semibold text-surface-foreground">
          STAGE 02F
        </div>
        <h2 className="mt-3 text-xl font-semibold">
          Navigation & Overlay Components
        </h2>
        <p className="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">
          STORVIA navigation hierarchy, action menus, modal workflows,
          confirmation patterns, and transient feedback.
        </p>
      </div>

      <div className="space-y-12">
        {/* Dropdown Menu */}
        <div>
          <h3 className="mb-4 text-sm font-semibold">Dropdown Menu</h3>

          <div className="flex flex-wrap gap-3">
            <DropdownMenu>
              <DropdownMenuTrigger render={<Button variant="outline" />}>
                <MoreHorizontal />
                File actions
              </DropdownMenuTrigger>

              <DropdownMenuContent className="min-w-52">
                <DropdownMenuLabel>Quarterly Report.pdf</DropdownMenuLabel>
                <DropdownMenuSeparator />

                <DropdownMenuGroup>
                  <DropdownMenuItem>
                    <Pencil />
                    Rename
                    <DropdownMenuShortcut>F2</DropdownMenuShortcut>
                  </DropdownMenuItem>

                  <DropdownMenuItem>
                    <Share2 />
                    Share
                  </DropdownMenuItem>

                  <DropdownMenuItem>
                    <Download />
                    Download
                  </DropdownMenuItem>
                </DropdownMenuGroup>

                <DropdownMenuSeparator />

                <DropdownMenuItem variant="destructive">
                  <Trash2 />
                  Delete
                  <DropdownMenuShortcut>Del</DropdownMenuShortcut>
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            <DropdownMenu>
              <DropdownMenuTrigger render={<Button variant="ghost" />}>
                <Settings />
                Workspace
              </DropdownMenuTrigger>

              <DropdownMenuContent>
                <DropdownMenuItem>
                  <Users />
                  Members
                </DropdownMenuItem>
                <DropdownMenuItem>
                  <Shield />
                  Permissions
                </DropdownMenuItem>
                <DropdownMenuItem>
                  <Bell />
                  Notifications
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>

        {/* Dialogs */}
        <div>
          <h3 className="mb-4 text-sm font-semibold">
            Dialogs & Confirmations
          </h3>

          <div className="flex flex-wrap gap-3">
            <Dialog>
              <DialogTrigger render={<Button />}>
                <Share2 />
                Share file
              </DialogTrigger>

              <DialogContent>
                <DialogHeader>
                  <DialogTitle>Share Quarterly Report.pdf</DialogTitle>
                  <DialogDescription>
                    Invite a collaborator or create a secure share link.
                    Permissions will still be enforced by the backend.
                  </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                  <div>
                    <label
                      htmlFor="share-recipient"
                      className="mb-2 block text-sm font-medium"
                    >
                      Email or user
                    </label>
                    <Input
                      id="share-recipient"
                      placeholder="name@example.com"
                    />
                  </div>

                  <div className="rounded-lg border border-border bg-surface-muted/60 p-4 text-sm">
                    <p className="font-medium">Access</p>
                    <p className="mt-1 text-muted-foreground">
                      Viewer · Can preview and download
                    </p>
                  </div>
                </div>

                <DialogFooter>
                  <DialogClose render={<Button variant="outline" />}>
                    Cancel
                  </DialogClose>

                  <Button
                    onClick={() =>
                      showToast(
                        "success",
                        "Share created",
                        "The invitation is ready to be sent."
                      )
                    }
                  >
                    Share
                  </Button>
                </DialogFooter>
              </DialogContent>
            </Dialog>

            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
              <AlertDialogTrigger render={<Button variant="destructive" />}>
                <Trash2 />
                Delete file
              </AlertDialogTrigger>

              <AlertDialogContent size="sm">
                <AlertDialogHeader>
                  <AlertDialogMedia>
                    <Trash2 className="size-5" />
                  </AlertDialogMedia>
                  <AlertDialogTitle>Delete this file?</AlertDialogTitle>
                  <AlertDialogDescription>
                    Quarterly Report.pdf will be moved to deleted items.
                    This action requires explicit confirmation.
                  </AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                  <AlertDialogCancel>Cancel</AlertDialogCancel>
                  <AlertDialogAction
                    variant="destructive"
                    onClick={() => {
                      setDeleteOpen(false)
                      showToast(
                        "success",
                        "File moved to deleted items",
                        "Quarterly Report.pdf can be restored later."
                      )
                    }}
                  >
                    Delete
                  </AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
          </div>
        </div>

        {/* Toasts */}
        <div>
          <h3 className="mb-4 text-sm font-semibold">Toast Feedback</h3>

          <div className="flex flex-wrap gap-3">
            <Button
              variant="outline"
              onClick={() =>
                showToast(
                  "success",
                  "Upload completed",
                  "Quarterly Report.pdf is now available."
                )
              }
            >
              Success toast
            </Button>

            <Button
              variant="outline"
              onClick={() =>
                showToast(
                  "info",
                  "Share updated",
                  "Permissions were refreshed successfully."
                )
              }
            >
              Info toast
            </Button>

            <Button
              variant="outline"
              onClick={() =>
                showToast(
                  "warning",
                  "Storage nearing quota",
                  "The workspace has used more than 80% of its storage."
                )
              }
            >
              Warning toast
            </Button>

            <Button
              variant="outline"
              onClick={() =>
                showToast(
                  "error",
                  "Upload failed",
                  "The connection was interrupted. Try again."
                )
              }
            >
              Error toast
            </Button>
          </div>
        </div>

        {/* Breadcrumbs */}
        <div>
          <h3 className="mb-4 text-sm font-semibold">Breadcrumbs</h3>

          <div className="rounded-lg border border-border bg-card p-5">
            <Breadcrumb>
              <BreadcrumbList>
                <BreadcrumbItem>
                  <BreadcrumbLink href="#">My files</BreadcrumbLink>
                </BreadcrumbItem>
                <BreadcrumbSeparator />
                <BreadcrumbItem>
                  <BreadcrumbLink href="#">Finance</BreadcrumbLink>
                </BreadcrumbItem>
                <BreadcrumbSeparator />
                <BreadcrumbItem>
                  <BreadcrumbPage>Reports</BreadcrumbPage>
                </BreadcrumbItem>
              </BreadcrumbList>
            </Breadcrumb>
          </div>
        </div>

        {/* Tabs */}
        <div>
          <h3 className="mb-4 text-sm font-semibold">Tabs</h3>

          <Tabs defaultValue="files">
            <TabsList variant="line">
              <TabsTrigger value="files">
                <FolderOpen />
                Files
              </TabsTrigger>
              <TabsTrigger value="shares">
                <Share2 />
                Shared
              </TabsTrigger>
              <TabsTrigger value="activity">
                <Bell />
                Activity
              </TabsTrigger>
            </TabsList>

            <TabsContent
              value="files"
              className="rounded-lg border border-border bg-card p-5"
            >
              <p className="font-medium">Files workspace</p>
              <p className="mt-1 text-sm text-muted-foreground">
                File-management content will render here.
              </p>
            </TabsContent>

            <TabsContent
              value="shares"
              className="rounded-lg border border-border bg-card p-5"
            >
              <p className="font-medium">Shared items</p>
              <p className="mt-1 text-sm text-muted-foreground">
                Active internal and external shares.
              </p>
            </TabsContent>

            <TabsContent
              value="activity"
              className="rounded-lg border border-border bg-card p-5"
            >
              <p className="font-medium">Recent activity</p>
              <p className="mt-1 text-sm text-muted-foreground">
                Workspace events and file operations.
              </p>
            </TabsContent>
          </Tabs>
        </div>

        {/* Navigation primitives */}
        <div>
          <h3 className="mb-4 text-sm font-semibold">
            Navigation Primitives
          </h3>

          <div className="grid gap-6 lg:grid-cols-2">
            <div className="rounded-xl border border-sidebar-border bg-sidebar p-4 text-sidebar-foreground">
              <Navigation aria-label="STORVIA workspace">
                <NavigationSection>
                  <NavigationLabel>Workspace</NavigationLabel>
                  <NavigationList>
                    <NavigationItem>
                      <NavigationLink href="#" active>
                        <LayoutDashboard />
                        Overview
                      </NavigationLink>
                    </NavigationItem>

                    <NavigationItem>
                      <NavigationLink href="#">
                        <Folder />
                        My files
                        <NavigationBadge>428 GB</NavigationBadge>
                      </NavigationLink>
                    </NavigationItem>

                    <NavigationItem>
                      <NavigationLink href="#">
                        <Share2 />
                        Shared
                        <NavigationBadge>126</NavigationBadge>
                      </NavigationLink>
                    </NavigationItem>
                  </NavigationList>
                </NavigationSection>

                <NavigationSection className="mt-6">
                  <NavigationLabel>Administration</NavigationLabel>
                  <NavigationList>
                    <NavigationItem>
                      <NavigationLink href="#">
                        <Users />
                        Users
                      </NavigationLink>
                    </NavigationItem>

                    <NavigationItem>
                      <NavigationLink href="#">
                        <Settings />
                        Settings
                      </NavigationLink>
                    </NavigationItem>
                  </NavigationList>
                </NavigationSection>
              </Navigation>
            </div>

            <div
              lang="ar"
              dir="rtl"
              className="font-arabic rounded-xl border border-sidebar-border bg-sidebar p-4 text-sidebar-foreground"
            >
              <Navigation aria-label="تنقل ستورفيا">
                <NavigationSection>
                  <NavigationLabel>مساحة العمل</NavigationLabel>
                  <NavigationList>
                    <NavigationItem>
                      <NavigationLink href="#" active>
                        <Home />
                        الرئيسية
                      </NavigationLink>
                    </NavigationItem>

                    <NavigationItem>
                      <NavigationLink href="#">
                        <Folder />
                        ملفاتي
                        <NavigationBadge>٤٢٨ GB</NavigationBadge>
                      </NavigationLink>
                    </NavigationItem>

                    <NavigationItem>
                      <NavigationLink href="#">
                        <Share2 />
                        المشاركات
                        <NavigationBadge>١٢٦</NavigationBadge>
                      </NavigationLink>
                    </NavigationItem>
                  </NavigationList>
                </NavigationSection>
              </Navigation>
            </div>
          </div>
        </div>

        {/* RTL overlay validation */}
        <div
          lang="ar"
          dir="rtl"
          className="font-arabic rounded-xl border border-border bg-card p-6"
        >
          <h3 className="text-sm font-semibold">العربية / RTL</h3>
          <p className="mt-1 text-sm text-muted-foreground">
            اختبار القوائم ومسار التنقل والحوارات في الاتجاه من اليمين إلى اليسار.
          </p>

          <div className="mt-5 flex flex-wrap gap-3">
            <DropdownMenu>
              <DropdownMenuTrigger render={<Button variant="outline" />}>
                <MoreHorizontal />
                إجراءات الملف
              </DropdownMenuTrigger>

              <DropdownMenuContent>
                <DropdownMenuItem>
                  <Pencil />
                  إعادة تسمية
                </DropdownMenuItem>
                <DropdownMenuItem>
                  <Share2 />
                  مشاركة
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem variant="destructive">
                  <Trash2 />
                  حذف
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            <Dialog>
              <DialogTrigger render={<Button variant="secondary" />}>
                <FileText />
                خصائص الملف
              </DialogTrigger>

              <DialogContent dir="rtl" className="font-arabic">
                <DialogHeader>
                  <DialogTitle>خصائص التقرير السنوي.pdf</DialogTitle>
                  <DialogDescription>
                    معاينة مظهر الحوار العربي ومحاذاة المحتوى والأزرار.
                  </DialogDescription>
                </DialogHeader>

                <div className="rounded-lg border border-border bg-surface-muted/60 p-4 text-sm">
                  <div className="flex justify-between gap-4">
                    <span className="text-muted-foreground">النوع</span>
                    <span dir="ltr">PDF · 8.6 MB</span>
                  </div>
                </div>

                <DialogFooter>
                  <DialogClose render={<Button variant="outline" />}>
                    إغلاق
                  </DialogClose>
                </DialogFooter>
              </DialogContent>
            </Dialog>
          </div>

          <div className="mt-6 rounded-lg bg-surface p-4">
            <Breadcrumb>
              <BreadcrumbList>
                <BreadcrumbItem>
                  <BreadcrumbLink href="#">ملفاتي</BreadcrumbLink>
                </BreadcrumbItem>
                <BreadcrumbSeparator />
                <BreadcrumbItem>
                  <BreadcrumbLink href="#">الإدارة</BreadcrumbLink>
                </BreadcrumbItem>
                <BreadcrumbSeparator />
                <BreadcrumbItem>
                  <BreadcrumbPage>التقارير</BreadcrumbPage>
                </BreadcrumbItem>
              </BreadcrumbList>
            </Breadcrumb>
          </div>
        </div>
      </div>
    </section>
  )
}
