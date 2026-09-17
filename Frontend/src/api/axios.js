import axios from "axios";

// Les pages sont démontées lors de la navigation. Ce cache en mémoire évite de
// recharger les mêmes listes à chaque retour tout en gardant des données récentes.
const GET_CACHE_TTL_MS = 30_000;
const getCache = new Map();

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? "http://127.0.0.1:8000/api",
  headers: { Accept: "application/json" },
});

const originalGet = api.get.bind(api);

function canCacheGet(url, config = {}) {
  return (
    config.cache !== false &&
    (!config.responseType || config.responseType === "json") &&
    !url.startsWith("/references/")
  );
}

function cacheKey(url, config = {}) {
  return `${url}:${JSON.stringify(config.params ?? {})}`;
}

api.get = (url, config = {}) => {
  if (!canCacheGet(url, config)) return originalGet(url, config);

  const key = cacheKey(url, config);
  const cached = getCache.get(key);

  if (cached && cached.expiresAt > Date.now()) {
    return cached.request;
  }

  const request = originalGet(url, config).catch((error) => {
    getCache.delete(key);
    throw error;
  });

  getCache.set(key, { request, expiresAt: Date.now() + GET_CACHE_TTL_MS });
  return request;
};

// Injecte le token Sanctum stocké dans localStorage sur chaque requête
api.interceptors.request.use((config) => {
  const token = localStorage.getItem("agma_token");
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// Si le token est invalide/expiré, on nettoie et on renvoie vers /login
api.interceptors.response.use(
  (response) => {
    // Toute écriture peut rendre plusieurs écrans obsolètes.
    if (response.config.method?.toLowerCase() !== "get") getCache.clear();
    return response;
  },
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem("agma_token");
      localStorage.removeItem("agma_user");
      if (window.location.pathname !== "/login") {
        window.location.href = "/login";
      }
    }
    return Promise.reject(error);
  },
);

export default api;
