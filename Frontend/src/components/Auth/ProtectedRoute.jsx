import { Navigate, Outlet, useLocation } from "react-router-dom";
import { useAuth } from "../../context/AuthContext";
import { useToast } from "../../context/ToastContext";

export default function ProtectedRoute({
  requiredPermission,
  allowPasswordChange = false,
}) {
  const { isAuthenticated, loading, hasPermission, user } = useAuth();
  const { showToast } = useToast();
  const location = useLocation();

  if (loading) {
    return (
      <div
        style={{
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          height: "100vh",
        }}
      >
        <span
          className="spinner"
          style={{
            borderTopColor: "var(--navy-900)",
            borderColor: "var(--border)",
          }}
        />
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  if (user?.must_change_password && !allowPasswordChange) {
    return <Navigate to="/change-password" replace />;
  }

  if (requiredPermission && !hasPermission(requiredPermission)) {
    showToast(
      `Accès réservé : permission ${requiredPermission} requise.`,
      true,
    );
    return <Navigate to="/" replace />;
  }

  return <Outlet />;
}
