import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import { useToast } from "../context/ToastContext";
import "../styles/Login.css";

export default function ChangePassword() {
  const { changePassword, logout } = useAuth();
  const { showToast } = useToast();
  const navigate = useNavigate();
  const [current_password, setCurrentPassword] = useState("");
  const [password, setPassword] = useState("");
  const [password_confirmation, setPasswordConfirmation] = useState("");
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError("");

    if (password.length < 12) {
      setError("Le nouveau mot de passe doit contenir au moins 12 caractères.");
      return;
    }

    if (password !== password_confirmation) {
      setError("Les deux nouveaux mots de passe ne correspondent pas.");
      return;
    }

    setSubmitting(true);
    try {
      await changePassword({
        current_password,
        password,
        password_confirmation,
      });
      showToast("Mot de passe mis à jour.");
      navigate("/", { replace: true });
    } catch (requestError) {
      setError(
        requestError.response?.data?.errors?.current_password?.[0] ||
          requestError.response?.data?.message ||
          "Impossible de modifier le mot de passe.",
      );
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="login-screen">
      <div className="login-right" style={{ width: "100%", margin: "auto" }}>
        <form className="login-form" onSubmit={handleSubmit} noValidate>
          <h2>Modifier le mot de passe</h2>
          <p className="sub">
            Votre compte utilise un mot de passe temporaire. Choisissez-en un
            nouveau pour continuer.
          </p>
          {error && <div className="login-alert">{error}</div>}
          <div className="form-field" style={{ marginBottom: 14 }}>
            <label>Mot de passe temporaire</label>
            <input
              type="password"
              value={current_password}
              onChange={(e) => setCurrentPassword(e.target.value)}
              autoComplete="current-password"
              required
            />
          </div>
          <div className="form-field" style={{ marginBottom: 14 }}>
            <label>Nouveau mot de passe</label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoComplete="new-password"
              required
            />
          </div>
          <div className="form-field">
            <label>Confirmer le nouveau mot de passe</label>
            <input
              type="password"
              value={password_confirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              autoComplete="new-password"
              required
            />
          </div>
          <button className="login-submit" type="submit" disabled={submitting}>
            {submitting ? "Mise à jour..." : "Mettre à jour"}
          </button>
          <button
            className="login-submit"
            type="button"
            onClick={logout}
            disabled={submitting}
            style={{
              marginTop: 10,
              background: "transparent",
              color: "var(--navy-900)",
              border: "1px solid var(--border)",
            }}
          >
            Se déconnecter
          </button>
        </form>
      </div>
    </div>
  );
}
