// Simple in-memory cache used to make page navigation feel instant.
//
// Each page keeps its data ("budgets", "lignes", etc.) in this module-level
// Map so it survives route changes (it only resets on a full page reload).
// Pages read from the cache synchronously on mount (no loading spinner if
// something is already there) and then silently refetch in the background
// to keep the data fresh ("stale-while-revalidate").

const store = new Map();

export function getPageCache(key) {
  return store.get(key);
}

export function setPageCache(key, data) {
  store.set(key, data);
}