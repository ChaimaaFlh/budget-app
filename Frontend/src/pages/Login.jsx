import { useState } from "react";
import { useNavigate, useLocation } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import { useToast } from "../context/ToastContext";
import "../styles/Login.css";
import logoAgma from "../assets/agma_logo.png";

export default function Login() {
  const { login } = useAuth();
  const { showToast } = useToast();
  const navigate = useNavigate();
  const location = useLocation();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);
  const [globalError, setGlobalError] = useState("");

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrors({});
    setGlobalError("");

    if (!email.trim() || !password) {
      setErrors({
        email: !email.trim() ? "Email requis." : undefined,
        password: !password.trim() ? "Mot de passe requis." : undefined,
      });
      return;
    }

    setSubmitting(true);
    try {
      const session = await login(email.trim(), password);
      showToast("Connexion réussie !");
      const redirectTo = session.must_change_password
        ? "/change-password"
        : location.state?.from?.pathname || "/";
      navigate(redirectTo, { replace: true });
    } catch (err) {
      if (err.response?.status === 401 || err.response?.status === 422) {
        setErrors(err.response.data.errors || {});
        setGlobalError(err.response.data.message || "Identifiants incorrect.");
      } else {
        setGlobalError("Erreur serveur. Veuillez réessayer plus tard.");
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="login-screen">
      <div className="login-left">
        <div className="login-brand">
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
        <div className="login-quote">
          <div className="fig">Bienvenue</div>
          <p>
            Connectez-vous pour accéder à l'application de gestion budgétaire.
          </p>
        </div>
      </div>

      <div className="login-right">
        <form className="login-form" onSubmit={handleSubmit} noValidate>
          <h2>Connexion</h2>
          <p className="sub">
            Entrez vos identifiants pour accéder à l'application.
          </p>

          {globalError && <div className="login-alert">{globalError}</div>}

          <div className="form-field" style={{ marginBottom: 14 }}>
            <label>
              Email <span className="req">*</span>
            </label>
            <input
              type="email"
              placeholder="Ex: admin@agma.ma"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              autoComplete="email"
            />
            {errors.email && (
              <span className="field-error">
                {Array.isArray(errors.email) ? errors.email[0] : errors.email}
              </span>
            )}
          </div>

          <div className="form-field">
            <label>
              Mot de passe <span className="req">*</span>
            </label>
            <input
              type="password"
              placeholder="********"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoComplete="current-password"
            />
            {errors.password && (
              <span className="field-error">
                {Array.isArray(errors.password)
                  ? errors.password[0]
                  : errors.password}
              </span>
            )}
          </div>

          <button className="login-submit" type="submit" disabled={submitting}>
            {submitting && <span className="spinner" />}
            {submitting ? "Connexion..." : "Se connecter"}
          </button>
        </form>
      </div>
    </div>
  );
}
