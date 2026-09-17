import { useAuth } from "../../context/AuthContext";
import { useToast } from "../../context/ToastContext";
import { CalendarDays, LogOut, UserRound } from "lucide-react";
import { useNavigate } from "react-router-dom";

export default function Topbar({ onToggleSidebar, onSearch }) {
  const { logout, user, departement } = useAuth();
  const { showToast } = useToast();
  const navigate = useNavigate();
  const today = new Intl.DateTimeFormat("fr-FR", {
    weekday: "long",
    day: "numeric",
    month: "long",
  }).format(new Date());

  return (
    <div className="topbar">
      <button className="hamburger" onClick={onToggleSidebar} aria-label="Menu">
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.8"
        >
          <path d="M3 6h18M3 12h18M3 18h18" />
        </svg>
      </button>

      <div className="topbar-context">
        <span className="topbar-greeting">Bonjour, {user?.name || ""}</span>
        <span className="topbar-department">{departement?.nom || "Gestion budgétaire"}</span>
      </div>

      <div className="topbar-right">
        <span className="topbar-date">
          <CalendarDays size={15} /> {today}
        </span>
        <button className="topbar-profile" onClick={() => navigate("/profil")}>
          <span className="topbar-avatar">{user?.name?.[0]?.toUpperCase() || "?"}</span>
          <span>Mon profil</span>
          <UserRound size={15} />
        </button>
        <button
          className="icon-btn topbar-logout"
          title="Se déconnecter"
          aria-label="Se déconnecter"
          onClick={async () => {
            await logout();
            showToast("Déconnecté.");
          }}
        >
          <LogOut size={17} />
        </button>
      </div>
    </div>
  );
}
