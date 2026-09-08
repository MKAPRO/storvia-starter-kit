export type DataTableLabels = {
  columns: string
  toggleColumns: string
  clearSearch: string
  rowsPerPage: string
  page: string
  of: string
  rowsSelected: (selected: number, total: number) => string
  rowCount: (total: number) => string
  firstPage: string
  previousPage: string
  nextPage: string
  lastPage: string
  selectAllRows: string
  selectRow: string
  columnLabel: (columnId: string) => string
}

export const defaultDataTableLabels: DataTableLabels = {
  columns: "Columns",
  toggleColumns: "Toggle columns",
  clearSearch: "Clear search",
  rowsPerPage: "Rows per page",
  page: "Page",
  of: "of",
  rowsSelected: (selected, total) =>
    `${selected} of ${total} row(s) selected`,
  rowCount: (total) => `${total} row(s)`,
  firstPage: "First page",
  previousPage: "Previous page",
  nextPage: "Next page",
  lastPage: "Last page",
  selectAllRows: "Select all rows on this page",
  selectRow: "Select row",
  columnLabel: (columnId) => columnId.replaceAll("_", " "),
}
