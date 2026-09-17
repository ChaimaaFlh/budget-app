import { NavLink, useNavigate } from "react-router-dom";
import { useAuth } from "../../context/AuthContext";
import logoAgma from "../../assets/agma_logo.png";

const NAV_ICONS = {
  dashboard: <path d="M3 3h8v8H3zM13 3h8v5h-8zM13 12h8v9h-8zM3 14h8v7H3z" />,
  departements: <path d="M3 4h18v17H3zM3 9h18M8 3v3M16 3v3" />,
  utilisateurs: (
    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 7a4 4 0 100 8 4 4 0 000-8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
  ),
  categories: <path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z" />,
  budgets: <path d="M3 12a9 9 0 1018 0 9 9 0 00-18 0zM12 7v5l3 3" />,
  lignes: <path d="M4 6h16M4 12h10M4 18h13" />,
  bons: (
    <path d="M6 3h9l5 5v13a1 1 0 01-1 1H6a1 1 0 01-1-1V4a1 1 0 011-1zM9 12h6M9 16h6M9 8h3" />
  ),
  factures: (
    <path d="M6 3h9l5 5v13a1 1 0 01-1 1H6a1 1 0 01-1-1V4a1 1 0 011-1zM9 8h6M9 12h6M9 16h3" />
  ),
  fournisseurs: <path d="M4 21V7l8-4 8 4v14M8 21v-5h8v5M4 11h16" />,
  rapports: <path d="M4 20V10M11 20V4M18 20V13" />,
};

function Icon({ name }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
    >
      {NAV_ICONS[name]}
    </svg>
  );
}

export default function Sidebar({ open, onNavigate }) {
  const { user, departement, canViewAllDepartments, hasPermission, isSysAdmin } = useAuth();

  const linkClass = ({ isActive }) => `nav-item${isActive ? " active" : ""}`;

  return (
    <aside className={`sidebar${open ? " open" : ""}`}>
      <div className="brand">
        <div className="brand-mark">
          <img
            src={logoAgma}
            alt="AGMA Logo"
            style={{ width: "100%", height: "100%", objectFit: "contain" }}
          />
        </div>
        <div>
          <div className="brand-name">AGMA</div>
          <div className="brand-sub">Gestion Budgétaire</div>
        </div>
      </div>

      <div className="exercice-pill">
        <span className="lbl">département</span>
        <span className="val">
          {canViewAllDepartments()
            ? "Tous les départements"
            : departement?.nom || "—"}
        </span>
      </div>

      <nav className="nav-list" onClick={onNavigate}>
        <NavLink to="/" end className={linkClass}>
          <Icon name="dashboard" /> Tableau de bord
        </NavLink>

        {canViewAllDepartments() && (
          <NavLink to="/departements" className={linkClass}>
            <Icon name="departements" /> Départements
          </NavLink>
        )}

        {isSysAdmin() && (
          <NavLink to="/utilisateurs" className={linkClass}>
            <Icon name="utilisateurs" /> Utilisateurs
          </NavLink>
        )}

        <NavLink to="/arborescence" className={linkClass}>
          <Icon name="categories" /> Arborescence
        </NavLink>
        {isSysAdmin() && <NavLink to="/categories" className={linkClass}>
          <Icon name="categories" /> Catégories
        </NavLink>}
        <NavLink to="/budgets" className={linkClass}>
          <Icon name="budgets" /> Budgets
        </NavLink>
        <NavLink to="/lignes" className={linkClass}>
          <Icon name="lignes" /> Lignes budgétaires
        </NavLink>

        <NavLink to="/bons" className={linkClass}>
          <Icon name="bons" /> Bons de commande
        </NavLink>
        <NavLink to="/factures" className={linkClass}>
          <Icon name="factures" /> Factures
        </NavLink>
        {(hasPermission("fournisseur.create") ||
          hasPermission("fournisseur.edit") ||
          hasPermission("fournisseur.delete")) && (
          <NavLink to="/fournisseurs" className={linkClass}>
            <Icon name="fournisseurs" /> Fournisseurs
          </NavLink>
        )}

        <NavLink to="/rapports" className={linkClass}>
          <Icon name="rapports" /> Rapports
        </NavLink>
      </nav>

      <div className="sidebar-footer">
        <UserChip user={user} />
      </div>
    </aside>
  );
}

function UserChip({ user }) {
  const { permissions } = useAuth();
  const navigate = useNavigate();
  if (!user) return null;
  return (
    <button
      type="button"
      className="user-chip"
      onClick={() => navigate("/profil")}
      title="Voir mon profil"
    >
      <div className="avatar">{user.name?.[0]?.toUpperCase() || "?"}</div>
      <div>
        <div className="user-name">{user.name}</div>
        <div className="user-role">
          {permissions.length
            ? `${permissions.length} permission(s)`
            : "lecture seule"}
        </div>
      </div>
    </button>
  );
}
