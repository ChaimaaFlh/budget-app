import { KeyRound, LogOut, Mail, ShieldCheck } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import "../styles/Profil.css";

export default function Profil() {
  const { user, departement, permissions, logout } = useAuth();
  const navigate = useNavigate();

  return (
    <div className="profile-page">
      <div className="profile-head">
        <div>
          <div className="eyebrow">Compte</div>
          <h1>Mon profil</h1>
          <p>Consultez les informations associées à votre session.</p>
        </div>
      </div>
      <section className="panel profile-card">
        <div className="profile-identity">
          <div className="profile-avatar">{user?.name?.[0]?.toUpperCase() || "?"}</div>
          <div>
            <h2>{user?.name}</h2>
            <p>{departement?.nom || "Aucun département"}</p>
          </div>
        </div>
        <div className="profile-details">
          <div><Mail size={17} /><span>Email</span><strong>{user?.email}</strong></div>
          <div><ShieldCheck size={17} /><span>Autorisations</span><strong>{permissions.length} permission(s)</strong></div>
        </div>
        <div className="profile-actions">
          <button className="btn-primary" onClick={() => navigate("/change-password")}>
            <KeyRound size={16} /> Modifier le mot de passe
          </button>
          <button className="btn-ghost btn-danger" onClick={logout}>
            <LogOut size={16} /> Se déconnecter
          </button>
        </div>
      </section>
    </div>
  );
}
