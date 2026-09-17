import {
  createContext,
  useContext,
  useEffect,
  useState,
  useCallback,
  useMemo,
} from "react";
import api from "../api/axios";

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [permissions, setPermissions] = useState([]);
  const [departements, setDepartements] = useState([]);
  const [loading, setLoading] = useState(true);

  const loadSession = useCallback(async () => {
    const { data } = await api.get("/me");
    setUser(data.user);
    setPermissions(data.permissions);
    setDepartements(data.departements);
    return data;
  }, []);

  const bootstrapSession = useCallback(async () => {
    const token = localStorage.getItem("agma_token");
    if (!token) {
      setLoading(false);
      return;
    }
    try {
      await loadSession();
    } catch {
      localStorage.removeItem("agma_token");
      setUser(null);
      setPermissions([]);
    } finally {
      setLoading(false);
    }
  }, [loadSession]);

  useEffect(() => {
    bootstrapSession();
  }, [bootstrapSession]);

  const login = useCallback(
    async (email, password) => {
      const { data } = await api.post("/login", { email, password });
      localStorage.setItem("agma_token", data.token);
      await loadSession();
      return data;
    },
    [loadSession],
  );

  const changePassword = useCallback(
    async (payload) => {
      await api.put("/me/password", payload);
      await loadSession();
    },
    [loadSession],
  );

  const logout = useCallback(async () => {
    try {
      await api.post("/logout");
    } catch {}
    localStorage.removeItem("agma_token");
    setUser(null);
    setPermissions([]);
  }, []);

  const hasPermission = useCallback(
    (code) => permissions.includes(code),
    [permissions],
  );
  const canViewAllDepartments = useCallback(
    () => hasPermission("departement.view_all"),
    [hasPermission],
  );
  const isSysAdmin = useCallback(
    () => hasPermission("user.manage_permissions"),
    [hasPermission],
  );

  const departement = useMemo(
    () => departements.find((d) => d.id === user?.departement_id) || null,
    [departements, user],
  );

  const value = {
    user,
    permissions,
    departements,
    departement,
    loading,
    isAuthenticated: !!user,
    login,
    changePassword,
    logout,
    hasPermission,
    canViewAllDepartments,
    isSysAdmin,
    refreshPermissions: () => user && loadSession(),
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth doit être utilisé dans un <AuthProvider>");
  return ctx;
}
