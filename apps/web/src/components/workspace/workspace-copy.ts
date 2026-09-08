import type { StorviaLocale } from "@/lib/i18n";

export type WorkspaceCopy = {
  dashboard: string;
  adminDashboard: string;
  dashboardDescription: string;
  dashboardPersonalStorageDescription: string;
  dashboardRecentDescription: string;
  dashboardNoRecentFiles: string;
  dashboardNoRecentFilesDescription: string;
  dashboardCouldNotLoad: string;
  dashboardCouldNotLoadDescription: string;
  dashboardRefreshFailed: string;
  loadingDashboard: string;
  viewAll: string;
  files: string;
  fileManager: string;
  home: string;
  recent: string;
  favorites: string;
  trash: string;
  administration: string;
  users: string;
  departments: string;
  roles: string;
  permissions: string;
  settings: string;
  fileManagerSettings: string;
  myFiles: string;
  companyDrive: string;
  fileSpaceScope: string;
  chooseDepartment: string;
  departmentSelector: string;
  sectionSelector: string;
  chooseSection: string;
  noSectionsAvailable: string;
  navigationOnlyDepartment: string;
  chooseAllowedSection: string;
  noFileAccessTitle: string;
  noFileAccessDescription: string;
  directory: string;
  projects: string;
  design: string;
  finance: string;
  archive: string;
  searchPlaceholder: string;
  newFolder: string;
  rename: string;
  confirm: string;
  cancel: string;
  close: string;
  folderNamePlaceholder: string;
  nameRequired: string;
  nameTooLong: string;
  nameUnavailable: string;
  couldNotCreateFolder: string;
  couldNotRename: string;
  actionNotAllowed: string;
  accessSummary: string;
  viewAccess: string;
  createFolderAccess: string;
  uploadAccess: string;
  networkActionFailed: string;
  download: string;
  downloadComplete: string;
  couldNotDownload: string;
  downloadUnavailable: string;
  upload: string;
  uploadingFile: string;
  uploadComplete: string;
  couldNotUpload: string;
  uploadTooLarge: string;
  uploadNameConflict: string;
  uploadDestinationUnavailable: string;
  uploadInvalid: string;
  uploadCenterTitle: string;
  uploadCenterDescription: string;
  attachFiles: string;
  uploadAll: string;
  removeAll: string;
  dropFilesHere: string;
  uploadLimitSummary: string;
  uploadQueueSummary: string;
  uploadQueued: string;
  uploadInProgress: string;
  uploadCompletedStatus: string;
  uploadFailedStatus: string;
  uploadCancelledStatus: string;
  removeUpload: string;
  removeAllUploadsTitle: string;
  removeAllUploadsDescription: string;
  removeAllUploadsConfirm: string;
  uploadPolicyLoading: string;
  uploadPolicyUnavailable: string;
  uploadBatchLimitReached: string;
  uploadFilesTooLarge: string;
  uploadFileTypeNotAllowed: string;
  uploadAcceptedTypes: string;
  uploadCloseBlocked: string;
  notifications: string;
  lightTheme: string;
  darkTheme: string;
  signOut: string;
  profile: string;
  storage: string;
  storageUsed: string;
  storageQuotas: string;
  fileTypes: string;
  storageQuota: string;
  storageUsedLabel: string;
  storageLimitLabel: string;
  storageRemainingLabel: string;
  storageUnlimited: string;
  storageOverLimit: string;
  storageAvailable: string;
  uploadQuotaExceeded: string;
  uploadQuotaRemaining: string;
  quickAccess: string;
  folders: string;
  recentFiles: string;
  items: string;
  name: string;
  modified: string;
  size: string;
  owner: string;
  affiliation: string;
  personalScope: string;
  departmentScope: string;
  directoryPath: string;
  type: string;
  folder: string;
  file: string;
  sort: string;
  sortByName: string;
  sortByModified: string;
  ascending: string;
  descending: string;
  filter: string;
  listView: string;
  gridView: string;
  allFiles: string;
  noResults: string;
  noResultsDescription: string;
  emptyFolder: string;
  emptyFolderDescription: string;
  loadingWorkspace: string;
  loadingFiles: string;
  secureWorkspace: string;
  language: string;
  english: string;
  arabic: string;
  breadcrumb: string;
  closeNavigation: string;
  fileActions: string;
  addToFavorites: string;
  removeFromFavorites: string;
  moveToFolder: string;
  moveHere: string;
  chooseDestination: string;
  rootLocation: string;
  noFoldersHere: string;
  deleteItem: string;
  trashConfirmTitle: string;
  trashConfirmDescription: string;
  restore: string;
  viewItem: string;
  couldNotMove: string;
  couldNotTrash: string;
  couldNotFavorite: string;
  couldNotRestore: string;
  moveConflict: string;
  restoreConflict: string;
  favoritesEmpty: string;
  favoritesEmptyDescription: string;
  trashEmpty: string;
  trashEmptyDescription: string;
  emptyTrash: string;
  emptyTrashTitle: string;
  emptyTrashDescription: string;
  emptyTrashConfirm: string;
  emptyingTrash: string;
  trashEmptied: string;
  couldNotEmptyTrash: string;
  emptyTrashRetryDescription: string;
  releasedStorage: string;
  details: string;
  back: string;
  fileManagerHome: string;
  openFileManagerSettings: string;
  openFolder: string;
  retry: string;
  previousPage: string;
  nextPage: string;
  couldNotLoadFiles: string;
  personalSpaceUnavailable: string;
  personalSpaceUnavailableDescription: string;
  sectionPending: string;
  sectionPendingDescription: string;
  selected: string;
  selectItem: string;
  selectAll: string;
  clearSelection: string;
  selectionActions: string;
  deleteSelected: string;
  restoreSelected: string;
  addSelectedToFavorites: string;
  removeSelectedFromFavorites: string;
  trashSelectedConfirmTitle: string;
  trashSelectedConfirmDescription: string;
  selectionActionPartial: string;
};

export const WORKSPACE_COPY: Record<StorviaLocale, WorkspaceCopy> = {
  en: {
    dashboard: "Dashboard",
    adminDashboard: "Admin dashboard",
    dashboardDescription:
      "A focused view of your files, storage, favorites, and assigned departments.",
    dashboardPersonalStorageDescription:
      "Your Personal FileSpace quota from the backend-authoritative storage ledger.",
    dashboardRecentDescription:
      "Your latest viewable files, ordered by their most recent update.",
    dashboardNoRecentFiles: "No recent files yet",
    dashboardNoRecentFilesDescription:
      "Files you own and can currently view will appear here after they are created or updated.",
    dashboardCouldNotLoad: "Could not load your dashboard",
    dashboardCouldNotLoadDescription:
      "STORVIA could not load the current dashboard snapshot. Try again.",
    dashboardRefreshFailed:
      "The latest refresh failed. The previous dashboard snapshot is still shown.",
    loadingDashboard: "Loading your dashboard…",
    viewAll: "View all",
    files: "Files",
    fileManager: "File Manager",
    home: "Home",
    recent: "Recent",
    favorites: "Favorites",
    trash: "Trash",
    administration: "Administration",
    users: "Users",
    departments: "Departments",
    roles: "Roles",
    permissions: "Permissions",
    settings: "Settings",
    fileManagerSettings: "File Manager Settings",
    myFiles: "My Files",
    companyDrive: "Company Drive",
    fileSpaceScope: "File space",
    chooseDepartment: "Choose a department",
    departmentSelector: "Department",
    sectionSelector: "Section",
    chooseSection: "Choose a section",
    noSectionsAvailable: "No sections are available under this department.",
    navigationOnlyDepartment:
      "This department is a navigation container for your assigned sections. Parent files and upload tools are not available here.",
    chooseAllowedSection: "Choose one of your assigned sections to continue.",
    noFileAccessTitle:
      "You currently do not have permission to upload or browse files.",
    noFileAccessDescription:
      "Please contact the technical administrator to grant you the appropriate access.",
    directory: "Directory",
    projects: "Projects",
    design: "Design",
    finance: "Finance",
    archive: "Archive",
    searchPlaceholder: "Search files and folders…",
    newFolder: "New folder",
    rename: "Rename",
    confirm: "Confirm",
    cancel: "Cancel",
    close: "Close",
    folderNamePlaceholder: "Enter the folder name",
    nameRequired: "Enter a name to continue.",
    nameTooLong: "The name cannot exceed 255 characters.",
    nameUnavailable: "This name is not available in this location. Choose another name.",
    couldNotCreateFolder: "Could not create the folder. Try again.",
    couldNotRename: "Could not rename this item. Try again.",
    actionNotAllowed: "You no longer have permission to perform this action.",
    accessSummary: "Access",
    viewAccess: "View",
    createFolderAccess: "Create folders",
    uploadAccess: "Upload files",
    networkActionFailed: "Could not reach STORVIA. Check the connection and try again.",
    download: "Download",
    downloadComplete: "File downloaded",
    couldNotDownload: "Could not download this file. Try again.",
    downloadUnavailable: "The file content is unavailable right now.",
    upload: "Upload file",
    uploadingFile: "Uploading…",
    uploadComplete: "File uploaded",
    couldNotUpload: "Could not upload this file. Try again.",
    uploadTooLarge: "This file is larger than the allowed upload limit.",
    uploadNameConflict: "An item with this name already exists in this location.",
    uploadDestinationUnavailable:
      "The upload destination is no longer available. Refresh and choose another location.",
    uploadInvalid: "This file could not be accepted. Check the file and try again.",
    uploadCenterTitle: "Upload files",
    uploadCenterDescription:
      "Add multiple files, review the queue, and upload with live progress.",
    attachFiles: "Attach files",
    uploadAll: "Upload all",
    removeAll: "Remove all",
    dropFilesHere: "Drop files here or choose files",
    uploadLimitSummary: "Up to {count} files • {size} per file",
    uploadQueueSummary: "{count} files • {size}",
    uploadQueued: "Waiting",
    uploadInProgress: "Uploading",
    uploadCompletedStatus: "Completed",
    uploadFailedStatus: "Failed",
    uploadCancelledStatus: "Cancelled",
    removeUpload: "Remove from list",
    removeAllUploadsTitle: "Remove all files?",
    removeAllUploadsDescription:
      "Queued items will be cleared and active uploads will be cancelled.",
    removeAllUploadsConfirm: "Yes, remove all",
    uploadPolicyLoading: "Loading upload limits…",
    uploadPolicyUnavailable:
      "Upload limits could not be loaded. Close this window and try again.",
    uploadBatchLimitReached:
      "Some files were not added because this upload batch is already at its limit.",
    uploadFilesTooLarge:
      "Some files were not added because they exceed the allowed file size.",
    uploadFileTypeNotAllowed:
      "Some files were not added because their extension is not allowed in this file space.",
    uploadAcceptedTypes: "Allowed types: {types}",
    uploadCloseBlocked:
      "Active uploads are still running. Cancel them or wait for completion before closing.",
    notifications: "Notifications",
    lightTheme: "Light theme",
    darkTheme: "Dark theme",
    signOut: "Sign out",
    profile: "Profile",
    storage: "Storage",
    storageUsed: "164 GB of 256 GB used",
    storageQuotas: "Storage quotas",
    fileTypes: "File types",
    storageQuota: "Storage quota",
    storageUsedLabel: "Used",
    storageLimitLabel: "Limit",
    storageRemainingLabel: "Remaining",
    storageUnlimited: "Unlimited",
    storageOverLimit: "Over limit",
    storageAvailable: "Available",
    uploadQuotaExceeded: "This file exceeds the remaining storage quota.",
    uploadQuotaRemaining: "Remaining storage: {size}",
    quickAccess: "Quick access",
    folders: "Folders",
    recentFiles: "Recent files",
    items: "items",
    name: "Name",
    modified: "Last modified",
    size: "Size",
    owner: "Owner",
    affiliation: "Affiliation",
    personalScope: "Personal",
    departmentScope: "Department",
    directoryPath: "Current folder path",
    type: "Type",
    folder: "Folder",
    file: "File",
    sort: "Sort",
    sortByName: "Name",
    sortByModified: "Last modified",
    ascending: "Ascending",
    descending: "Descending",
    filter: "Filter",
    listView: "List view",
    gridView: "Grid view",
    allFiles: "All files",
    noResults: "No matching files",
    noResultsDescription: "Try another name or clear your search.",
    emptyFolder: "This folder is empty",
    emptyFolderDescription: "Folders and files stored here will appear in this list.",
    loadingWorkspace: "Opening your secure workspace…",
    loadingFiles: "Loading files…",
    secureWorkspace: "Private workspace",
    language: "Language",
    english: "English",
    arabic: "Arabic",
    breadcrumb: "Breadcrumb",
    closeNavigation: "Close navigation",
    fileActions: "File actions",
    addToFavorites: "Add to favorites",
    removeFromFavorites: "Remove from favorites",
    moveToFolder: "Move to folder",
    moveHere: "Move here",
    chooseDestination: "Choose a destination folder",
    rootLocation: "Drive root",
    noFoldersHere: "No folders in this location",
    deleteItem: "Move to trash",
    trashConfirmTitle: "Move this item to trash?",
    trashConfirmDescription: "The item will move to Trash and can be restored later.",
    restore: "Restore",
    viewItem: "View",
    couldNotMove: "Could not move this item. Try again.",
    couldNotTrash: "Could not move this item to trash. Try again.",
    couldNotFavorite: "Could not update favorites. Try again.",
    couldNotRestore: "Could not restore this item. Try again.",
    moveConflict: "An item with this name already exists in the destination.",
    restoreConflict: "This item cannot be restored to its original location yet.",
    favoritesEmpty: "No favorites yet",
    favoritesEmptyDescription: "Items you add to favorites will appear here.",
    trashEmpty: "Trash is empty",
    trashEmptyDescription: "Items moved to trash will appear here until they are restored.",
    emptyTrash: "Empty Trash",
    emptyTrashTitle: "Permanently empty Trash?",
    emptyTrashDescription: "All items in this file space Trash will be permanently deleted and cannot be restored.",
    emptyTrashConfirm: "Delete permanently",
    emptyingTrash: "Deleting permanently...",
    trashEmptied: "Trash emptied permanently.",
    couldNotEmptyTrash: "Could not empty Trash.",
    emptyTrashRetryDescription: "The permanent purge did not finish. Storage accounting is not released until the backend finalizes the purge; retry safely.",
    releasedStorage: "Released storage",
    details: "Details",
    back: "Back",
    fileManagerHome: "Go to File Manager",
    openFileManagerSettings: "Open File Manager settings",
    openFolder: "Open folder",
    retry: "Try again",
    previousPage: "Previous",
    nextPage: "Next",
    couldNotLoadFiles: "Could not load files",
    personalSpaceUnavailable: "My Files is not available",
    personalSpaceUnavailableDescription:
      "Your personal file space could not be resolved from the current account.",
    sectionPending: "This section is being prepared",
    sectionPendingDescription:
      "The File Manager foundation is ready. This section will be connected in its roadmap step.",
    selected: "selected",
    selectItem: "Select item",
    selectAll: "Select all",
    clearSelection: "Clear selection",
    selectionActions: "Selected item actions",
    deleteSelected: "Delete selected",
    restoreSelected: "Restore selected",
    addSelectedToFavorites: "Add selected to favorites",
    removeSelectedFromFavorites: "Remove selected from favorites",
    trashSelectedConfirmTitle: "Move selected items to trash?",
    trashSelectedConfirmDescription:
      "{count} selected items will move to Trash and can be restored later.",
    selectionActionPartial:
      "Some selected items could not be updated. The successful changes were kept.",
  },
  ar: {
    dashboard: "لوحة التحكم",
    adminDashboard: "لوحة تحكم الإدارة",
    dashboardDescription:
      "نظرة مركزة على ملفاتك ومساحة التخزين والمفضلة والمشاركات والإدارات المسندة إليك.",
    dashboardPersonalStorageDescription:
      "حصة مساحة ملفاتك الشخصية من سجل التخزين المعتمد في الخادم.",
    dashboardRecentDescription:
      "أحدث ملفاتك التي لا تزال قابلة للعرض، مرتبة حسب آخر تحديث.",
    dashboardNoRecentFiles: "لا توجد ملفات حديثة بعد",
    dashboardNoRecentFilesDescription:
      "ستظهر هنا الملفات التي تملكها ويمكنك عرضها حاليًا بعد إنشائها أو تحديثها.",
    dashboardCouldNotLoad: "تعذر تحميل لوحة التحكم",
    dashboardCouldNotLoadDescription:
      "تعذر على STORVIA تحميل لقطة لوحة التحكم الحالية. حاول مرة أخرى.",
    dashboardRefreshFailed:
      "فشل التحديث الأخير، ولا تزال لقطة لوحة التحكم السابقة معروضة.",
    loadingDashboard: "جاري تحميل لوحة التحكم…",
    viewAll: "عرض الكل",
    files: "الملفات",
    fileManager: "مدير الملفات",
    home: "الرئيسية",
    recent: "الأخيرة",
    favorites: "المفضلة",
    trash: "سلة المحذوفات",
    administration: "الإدارة",
    users: "المستخدمون",
    departments: "الإدارات",
    roles: "الأدوار",
    permissions: "الصلاحيات",
    settings: "الإعدادات",
    fileManagerSettings: "إعدادات مدير الملفات",
    myFiles: "ملفاتي",
    companyDrive: "ملفات الشركة",
    fileSpaceScope: "مساحة الملفات",
    chooseDepartment: "اختر إدارة",
    departmentSelector: "الإدارة",
    sectionSelector: "القسم",
    chooseSection: "اختر قسمًا",
    noSectionsAvailable: "لا توجد أقسام متاحة تحت هذه الإدارة.",
    navigationOnlyDepartment:
      "هذه الإدارة حاوية تنقل للأقسام المرتبط بها حسابك. لا تتوفر هنا ملفات الإدارة الأب أو أدوات الرفع.",
    chooseAllowedSection: "اختر أحد الأقسام المرتبط بها حسابك للمتابعة.",
    noFileAccessTitle:
      "لا تمتلك حاليًا صلاحية لرفع أو استعراض الملفات.",
    noFileAccessDescription:
      "يرجى التواصل مع المدير التقني لمنحك الصلاحيات المناسبة.",
    directory: "الدليل",
    projects: "المشاريع",
    design: "التصميم",
    finance: "المالية",
    archive: "الأرشيف",
    searchPlaceholder: "ابحث في الملفات والمجلدات…",
    newFolder: "مجلد جديد",
    rename: "إعادة تسمية",
    confirm: "تأكيد",
    cancel: "إلغاء",
    close: "إغلاق",
    folderNamePlaceholder: "أدخل اسم المجلد",
    nameRequired: "أدخل اسمًا للمتابعة.",
    nameTooLong: "يجب ألا يتجاوز الاسم 255 حرفًا.",
    nameUnavailable: "هذا الاسم غير متاح في هذا الموقع. اختر اسمًا آخر.",
    couldNotCreateFolder: "تعذر إنشاء المجلد. حاول مرة أخرى.",
    couldNotRename: "تعذرت إعادة تسمية هذا العنصر. حاول مرة أخرى.",
    actionNotAllowed: "لم تعد لديك صلاحية تنفيذ هذا الإجراء.",
    accessSummary: "الوصول",
    viewAccess: "عرض",
    createFolderAccess: "إنشاء مجلدات",
    uploadAccess: "رفع ملفات",
    networkActionFailed: "تعذر الوصول إلى STORVIA. تحقق من الاتصال وحاول مرة أخرى.",
    download: "تنزيل",
    downloadComplete: "تم تنزيل الملف",
    couldNotDownload: "تعذر تنزيل هذا الملف. حاول مرة أخرى.",
    downloadUnavailable: "محتوى الملف غير متاح للتنزيل حاليًا.",
    upload: "رفع ملف",
    uploadingFile: "جاري رفع الملف…",
    uploadComplete: "تم رفع الملف",
    couldNotUpload: "تعذر رفع هذا الملف. حاول مرة أخرى.",
    uploadTooLarge: "حجم هذا الملف أكبر من الحد المسموح للرفع.",
    uploadNameConflict: "يوجد عنصر بالاسم نفسه في هذا الموقع.",
    uploadDestinationUnavailable:
      "وجهة الرفع لم تعد متاحة. حدّث الصفحة واختر موقعًا آخر.",
    uploadInvalid: "تعذر قبول هذا الملف. تحقق من الملف وحاول مرة أخرى.",
    uploadCenterTitle: "رفع الملفات",
    uploadCenterDescription:
      "أضف عدة ملفات، راجع قائمة الرفع، وتابع التقدم الفعلي لكل ملف.",
    attachFiles: "إرفاق ملفات",
    uploadAll: "رفع الكل",
    removeAll: "إزالة الكل",
    dropFilesHere: "اسحب الملفات وأفلتها هنا أو اختر الملفات",
    uploadLimitSummary: "حتى {count} ملفات • {size} لكل ملف",
    uploadQueueSummary: "{count} ملفات • {size}",
    uploadQueued: "في الانتظار",
    uploadInProgress: "جاري الرفع",
    uploadCompletedStatus: "مكتمل",
    uploadFailedStatus: "فشل",
    uploadCancelledStatus: "ملغي",
    removeUpload: "إزالة من القائمة",
    removeAllUploadsTitle: "إزالة جميع الملفات؟",
    removeAllUploadsDescription:
      "سيتم مسح الملفات المنتظرة وإلغاء أي عمليات رفع نشطة.",
    removeAllUploadsConfirm: "نعم، إزالة الكل",
    uploadPolicyLoading: "جاري تحميل حدود الرفع…",
    uploadPolicyUnavailable:
      "تعذر تحميل حدود الرفع. أغلق النافذة وحاول مرة أخرى.",
    uploadBatchLimitReached:
      "لم تتم إضافة بعض الملفات لأن قائمة الرفع وصلت إلى الحد المسموح.",
    uploadFilesTooLarge:
      "لم تتم إضافة بعض الملفات لأن حجمها يتجاوز الحد المسموح.",
    uploadFileTypeNotAllowed:
      "لم تتم إضافة بعض الملفات لأن امتدادها غير مسموح في مساحة الملفات هذه.",
    uploadAcceptedTypes: "الأنواع المسموحة: {types}",
    uploadCloseBlocked:
      "لا تزال هناك عمليات رفع نشطة. ألغها أو انتظر اكتمالها قبل إغلاق النافذة.",
    notifications: "الإشعارات",
    lightTheme: "الوضع الفاتح",
    darkTheme: "الوضع الداكن",
    signOut: "تسجيل الخروج",
    profile: "الملف الشخصي",
    storage: "التخزين",
    storageUsed: "164 جيجابايت مستخدمة من 256 جيجابايت",
    storageQuotas: "حصص التخزين",
    fileTypes: "أنواع الملفات",
    storageQuota: "حصة التخزين",
    storageUsedLabel: "المستخدم",
    storageLimitLabel: "الحد",
    storageRemainingLabel: "المتبقي",
    storageUnlimited: "غير محدود",
    storageOverLimit: "متجاوز للحد",
    storageAvailable: "متاح",
    uploadQuotaExceeded: "حجم هذا الملف يتجاوز مساحة التخزين المتبقية.",
    uploadQuotaRemaining: "مساحة التخزين المتبقية: {size}",
    quickAccess: "وصول سريع",
    folders: "المجلدات",
    recentFiles: "الملفات الأخيرة",
    items: "عناصر",
    name: "الاسم",
    modified: "آخر تعديل",
    size: "الحجم",
    owner: "المالك",
    affiliation: "التبعية",
    personalScope: "شخصي",
    departmentScope: "إدارة",
    directoryPath: "مسار المجلدات الحالي",
    type: "النوع",
    folder: "مجلد",
    file: "ملف",
    sort: "ترتيب",
    sortByName: "الاسم",
    sortByModified: "آخر تعديل",
    ascending: "تصاعدي",
    descending: "تنازلي",
    filter: "تصفية",
    listView: "عرض قائمة",
    gridView: "عرض شبكي",
    allFiles: "كل الملفات",
    noResults: "لا توجد ملفات مطابقة",
    noResultsDescription: "جرّب اسمًا آخر أو امسح البحث.",
    emptyFolder: "هذا المجلد فارغ",
    emptyFolderDescription: "ستظهر هنا المجلدات والملفات المحفوظة في هذا المسار.",
    loadingWorkspace: "جاري فتح مساحة العمل الآمنة…",
    loadingFiles: "جاري تحميل الملفات…",
    secureWorkspace: "مساحة عمل خاصة",
    language: "اللغة",
    english: "الإنجليزية",
    arabic: "العربية",
    breadcrumb: "مسار التنقل",
    closeNavigation: "إغلاق التنقل",
    fileActions: "إجراءات الملف",
    addToFavorites: "إضافة إلى المفضلة",
    removeFromFavorites: "إزالة من المفضلة",
    moveToFolder: "نقل إلى مجلد",
    moveHere: "نقل إلى هنا",
    chooseDestination: "اختر مجلد الوجهة",
    rootLocation: "جذر مساحة الملفات",
    noFoldersHere: "لا توجد مجلدات في هذا الموقع",
    deleteItem: "نقل إلى سلة المحذوفات",
    trashConfirmTitle: "نقل هذا العنصر إلى سلة المحذوفات؟",
    trashConfirmDescription: "سيتم نقل العنصر إلى سلة المحذوفات ويمكن استعادته لاحقًا.",
    restore: "استعادة",
    viewItem: "عرض",
    couldNotMove: "تعذر نقل هذا العنصر. حاول مرة أخرى.",
    couldNotTrash: "تعذر نقل هذا العنصر إلى سلة المحذوفات. حاول مرة أخرى.",
    couldNotFavorite: "تعذر تحديث المفضلة. حاول مرة أخرى.",
    couldNotRestore: "تعذرت استعادة هذا العنصر. حاول مرة أخرى.",
    moveConflict: "يوجد عنصر بالاسم نفسه في مجلد الوجهة.",
    restoreConflict: "لا يمكن استعادة هذا العنصر إلى موقعه الأصلي حاليًا.",
    favoritesEmpty: "لا توجد عناصر مفضلة بعد",
    favoritesEmptyDescription: "ستظهر هنا العناصر التي تضيفها إلى المفضلة.",
    trashEmpty: "سلة المحذوفات فارغة",
    trashEmptyDescription: "ستظهر هنا العناصر المنقولة إلى سلة المحذوفات حتى تتم استعادتها.",
    emptyTrash: "إفراغ سلة المحذوفات",
    emptyTrashTitle: "إفراغ سلة المحذوفات نهائيًا؟",
    emptyTrashDescription: "سيتم حذف جميع العناصر الموجودة في سلة هذه المساحة نهائيًا ولا يمكن استعادتها.",
    emptyTrashConfirm: "حذف نهائي",
    emptyingTrash: "جاري الحذف النهائي...",
    trashEmptied: "تم إفراغ سلة المحذوفات نهائيًا.",
    couldNotEmptyTrash: "تعذر إفراغ سلة المحذوفات.",
    emptyTrashRetryDescription: "لم تكتمل عملية الحذف النهائي. لا يتم تحرير حصة التخزين حتى ينهي الخادم العملية؛ ويمكن إعادة المحاولة بأمان.",
    releasedStorage: "المساحة المحررة",
    details: "التفاصيل",
    back: "رجوع",
    fileManagerHome: "العودة إلى مدير الملفات",
    openFileManagerSettings: "فتح إعدادات مدير الملفات",
    openFolder: "فتح المجلد",
    retry: "إعادة المحاولة",
    previousPage: "السابق",
    nextPage: "التالي",
    couldNotLoadFiles: "تعذر تحميل الملفات",
    personalSpaceUnavailable: "ملفاتي غير متاحة",
    personalSpaceUnavailableDescription:
      "تعذر تحديد مساحة الملفات الشخصية للحساب الحالي.",
    sectionPending: "هذا القسم قيد التجهيز",
    sectionPendingDescription:
      "أساس مدير الملفات جاهز، وسيتم ربط هذا القسم في مرحلته المحددة ضمن خارطة الطريق.",
    selected: "محدد",
    selectItem: "تحديد العنصر",
    selectAll: "تحديد الكل",
    clearSelection: "إلغاء التحديد",
    selectionActions: "إجراءات العناصر المحددة",
    deleteSelected: "حذف المحدد",
    restoreSelected: "استعادة المحدد",
    addSelectedToFavorites: "إضافة المحدد إلى المفضلة",
    removeSelectedFromFavorites: "إزالة المحدد من المفضلة",
    trashSelectedConfirmTitle: "نقل العناصر المحددة إلى سلة المحذوفات؟",
    trashSelectedConfirmDescription:
      "سيتم نقل {count} من العناصر المحددة إلى سلة المحذوفات، ويمكن استعادتها لاحقًا.",
    selectionActionPartial:
      "تعذر تحديث بعض العناصر المحددة، وتم الاحتفاظ بالتغييرات التي نجحت.",
  },
};
